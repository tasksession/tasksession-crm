/**
 * Kanban / calendar column headers: fixed at top while scrolling.
 */
(function () {
  'use strict';

  var STICKY_TOP = 0;
  var rafId = 0;
  var boardWrap = null;
  var columns = [];
  var bound = false;

  function getBoardWrap() {
    if (boardWrap && document.body.contains(boardWrap)) {
      return boardWrap;
    }
    var wraps = document.querySelectorAll('.board-wrap');
    if (wraps.length) {
      boardWrap = wraps[wraps.length - 1];
      return boardWrap;
    }
    boardWrap = null;
    return null;
  }

  function collectColumns() {
    var wrap = getBoardWrap();
    if (!wrap) {
      columns = [];
      return;
    }
    columns = Array.prototype.slice.call(wrap.querySelectorAll('.board-column'));
    columns.forEach(function (col) {
      var h2 = col.querySelector(':scope > h2');
      if (!h2) return;
      if (h2.dataset.kanbanStickyReady === '1') return;
      h2.dataset.kanbanStickyReady = '1';
      var spacer = document.createElement('div');
      spacer.className = 'kanban-col-header-spacer';
      spacer.setAttribute('aria-hidden', 'true');
      if (h2.nextSibling) {
        col.insertBefore(spacer, h2.nextSibling);
      } else {
        col.appendChild(spacer);
      }
    });
  }

  function clearFixed(h2, spacer) {
    h2.classList.remove('kanban-col-header-is-fixed');
    h2.style.position = '';
    h2.style.top = '';
    h2.style.left = '';
    h2.style.width = '';
    h2.style.zIndex = '';
    h2.style.boxSizing = '';
    h2.style.borderRight = '';
    if (spacer) {
      spacer.style.display = 'none';
      spacer.style.height = '0';
    }
  }

  function updateHeaders() {
    rafId = 0;
    if (!columns.length) return;

    columns.forEach(function (col) {
      var h2 = col.querySelector(':scope > h2');
      var spacer = col.querySelector(':scope > .kanban-col-header-spacer');
      if (!h2) return;

      var colRect = col.getBoundingClientRect();
      var h2Height = h2.offsetHeight || 40;
      var stickLine = STICKY_TOP;

      if (colRect.bottom <= stickLine + h2Height || colRect.top >= stickLine) {
        clearFixed(h2, spacer);
        return;
      }

      if (colRect.top < stickLine) {
        h2.classList.add('kanban-col-header-is-fixed');
        h2.style.position = 'fixed';
        h2.style.top = stickLine + 'px';
        h2.style.left = colRect.left + 'px';
        h2.style.width = colRect.width + 'px';
        h2.style.zIndex = '8';
        h2.style.boxSizing = 'border-box';
        if (!col.classList.contains('add-column-col')) {
          h2.style.borderRight = '1px solid var(--border-color)';
        }
        if (spacer) {
          spacer.style.display = 'block';
          spacer.style.height = h2Height + 'px';
        }
      } else {
        clearFixed(h2, spacer);
      }
    });
  }

  function scheduleUpdate() {
    if (rafId) return;
    rafId = window.requestAnimationFrame(updateHeaders);
  }

  function bindEvents() {
    if (bound) return;
    bound = true;
    window.addEventListener('scroll', scheduleUpdate, { passive: true });
    window.addEventListener('resize', scheduleUpdate, { passive: true });
    var pageContent = document.querySelector('.page-content');
    if (pageContent) {
      pageContent.addEventListener('scroll', scheduleUpdate, { passive: true });
    }
    var wrap = getBoardWrap();
    if (wrap) {
      wrap.addEventListener('scroll', scheduleUpdate, { passive: true });
    }
  }

  function init() {
    if (window.matchMedia('(max-width: 991.98px), (pointer: coarse)').matches) {
      return;
    }
    if (!getBoardWrap()) return;
    collectColumns();
    if (!columns.length) return;
    bindEvents();
    scheduleUpdate();
  }

  window.kanbanStickyHeadersRefresh = function () {
    collectColumns();
    scheduleUpdate();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.addEventListener('load', function () {
    collectColumns();
    scheduleUpdate();
  });
})();

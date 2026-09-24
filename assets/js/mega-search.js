/**
 * Mega search — header + results page instant search.
 */
(function () {
  'use strict';

  var cfg = window.MEGA_SEARCH || {};
  var debounceMs = typeof cfg.debounceMs === 'number' ? cfg.debounceMs : 300;
  var timer = null;
  var inflight = null;

  function appBaseUrl() {
    var base = (typeof window.baseUrl === 'string' && window.baseUrl) ? window.baseUrl : '/';
    if (base.charAt(base.length - 1) !== '/') base += '/';
    return base;
  }

  function getSearchUrl(q, tab) {
    if (cfg.searchUrl) {
      var u = String(cfg.searchUrl);
      var params = new URLSearchParams();
      if (q) params.set('q', q);
      if (tab) params.set('tab', tab);
      return params.toString() ? u + '?' + params.toString() : u;
    }
    var params = new URLSearchParams();
    if (q) params.set('q', q);
    if (tab) params.set('tab', tab);
    var qs = params.toString();
    return appBaseUrl() + 'search.php' + (qs ? '?' + qs : '');
  }

  function getAjaxUrl(q, tab, extra) {
    var base = cfg.ajaxUrl || (appBaseUrl() + 'ajax/mega_search.php');
    var params = new URLSearchParams();
    if (q) params.set('q', q);
    if (tab) params.set('tab', tab);
    if (extra) {
      Object.keys(extra).forEach(function (k) {
        if (extra[k] != null && extra[k] !== '') params.set(k, String(extra[k]));
      });
    }
    return base + '?' + params.toString();
  }

  function qs(sel, root) {
    return (root || document).querySelector(sel);
  }

  function qsa(sel, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(sel));
  }

  function isSearchPage() {
    return document.getElementById('mega-search-results') !== null;
  }

  function getHeaderInput() {
    return document.getElementById('mega-search-header-input');
  }

  function getPageInput() {
    return document.getElementById('mega-search-page-input');
  }

  function syncSearchInputs(fromInput) {
    if (!fromInput) {
      return;
    }
    var val = fromInput.value;
    var pageInput = getPageInput();
    var headerInput = getHeaderInput();
    if (pageInput && pageInput !== fromInput) {
      pageInput.value = val;
    }
    if (headerInput && headerInput !== fromInput) {
      headerInput.value = val;
    }
  }

  function getSearchQuery() {
    var pageInput = getPageInput();
    if (pageInput) {
      return pageInput.value.trim();
    }
    var headerInput = getHeaderInput();
    if (headerInput) return headerInput.value.trim();
    if (typeof cfg.initialQuery === 'string') return cfg.initialQuery.trim();
    return '';
  }

  function getActiveTab() {
    var hidden = document.getElementById('mega-search-tab-input');
    if (hidden && hidden.value) return hidden.value;
    var activeBtn = qs('.mega-search-tabs__btn.active') || qs('.mega-search-tabs__btn.is-active');
    return activeBtn ? activeBtn.getAttribute('data-tab') : cfg.activeTab || 'people';
  }

  function setActiveTab(tab) {
    var hidden = document.getElementById('mega-search-tab-input');
    if (hidden) hidden.value = tab;
    var pageTab = document.getElementById('mega-search-page-tab');
    if (pageTab) pageTab.value = tab;
    qsa('.mega-search-tabs__btn').forEach(function (btn) {
      var on = btn.getAttribute('data-tab') === tab;
      btn.classList.toggle('active', on);
      btn.classList.toggle('is-active', on);
      btn.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    if (window.MegaSearchBulk) {
      window.MegaSearchBulk.onTabChange();
    }
    updateBulkToolbarForTab(tab);
  }

  function updateBulkToolbarForTab(tab) {
    var wrap = document.getElementById('megaSearchBulkToolbar');
    if (!wrap || !window.MEGA_SEARCH_BULK || !window.MEGA_SEARCH_BULK.tabs) {
      return;
    }
    var conf = window.MEGA_SEARCH_BULK.tabs[tab];
    wrap.style.display = conf && conf.enabled ? '' : 'none';
  }

  function updateUrl(q, tab) {
    if (!window.history || !window.history.replaceState) return;
    var next = getSearchUrl(q, tab);
    try {
      var parsed = new URL(next, window.location.origin);
      window.history.replaceState({}, '', parsed.pathname + parsed.search);
    } catch (e) {
      window.history.replaceState({}, '', next);
    }
  }

  function setLoading(on) {
    var box = document.getElementById('mega-search-results');
    if (!box) return;
    box.classList.toggle('is-loading', !!on);
  }

  function renderResults(data, tab) {
    var box = document.getElementById('mega-search-results');
    if (!box || !data || !data.tabs) return;

    var total = 0;
    Object.keys(data.tabs).forEach(function (key) {
      var t = data.tabs[key];
      var count = t && t.count != null ? parseInt(t.count, 10) || 0 : 0;
      total += count;
      var countEl = document.querySelector('[data-tab-count="' + key + '"]');
      if (countEl && t) countEl.textContent = String(count);
    });

    var headingCount = document.querySelector('.mega-search-page .main-heading span');
    if (headingCount) headingCount.textContent = '(' + total + ')';

    var active = data.tabs[tab] || data.tabs[data.active_tab];
    if (active && typeof active.html === 'string') {
      box.innerHTML = active.html;
    }
    if (window.MegaSearchBulk) {
      window.MegaSearchBulk.refresh();
    }
  }

  window.megaSearchRunNow = function (pushUrl) {
    runSearch(getSearchQuery(), getActiveTab(), pushUrl !== false);
  };

  function runSearch(q, tab, pushUrl) {
    if (inflight) {
      try { inflight.abort(); } catch (e) {}
    }

    setLoading(true);
    var url = getAjaxUrl(q, tab);
    inflight = new XMLHttpRequest();
    inflight.open('GET', url, true);
    inflight.onreadystatechange = function () {
      if (inflight.readyState !== 4) return;
      setLoading(false);
      if (inflight.status >= 200 && inflight.status < 300) {
        try {
          var data = JSON.parse(inflight.responseText);
          if (data && data.success) {
            renderResults(data, tab);
            if (pushUrl) updateUrl(q, tab);
          }
        } catch (e) {}
      }
      inflight = null;
    };
    inflight.send();
  }

  function scheduleSearch() {
    var q = getSearchQuery();

    if (!isSearchPage()) {
      if (getHeaderInput() && q !== '') {
        window.location.href = getSearchUrl(q, '');
      }
      return;
    }

    clearTimeout(timer);
    timer = setTimeout(function () {
      runSearch(q, getActiveTab(), true);
    }, debounceMs);
  }

  function initTabs() {
    qsa('.mega-search-tabs__btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var tab = btn.getAttribute('data-tab');
        if (!tab) return;
        setActiveTab(tab);
        runSearch(getSearchQuery(), tab, true);
      });
    });
  }

  function initHeaderInput() {
    var headerInput = getHeaderInput();
    if (!headerInput) return;

    headerInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        var q = headerInput.value.trim();
        if (isSearchPage()) {
          clearTimeout(timer);
          runSearch(q, getActiveTab(), true);
          return;
        }
        window.location.href = getSearchUrl(q, '');
      }
    });

    if (isSearchPage()) {
      headerInput.addEventListener('input', function () {
        syncSearchInputs(headerInput);
        scheduleSearch();
      });
    }

    headerInput.addEventListener('click', function () {
      if (!isSearchPage()) {
        headerInput.focus();
      }
    });
  }

  function initPageInput() {
    var pageInput = getPageInput();
    if (!pageInput) {
      return;
    }

    pageInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        syncSearchInputs(pageInput);
        clearTimeout(timer);
        runSearch(pageInput.value.trim(), getActiveTab(), true);
      }
    });

    pageInput.addEventListener('input', function () {
      syncSearchInputs(pageInput);
      scheduleSearch();
    });
  }

  function initLoadMore() {
    var box = document.getElementById('mega-search-results');
    if (!box) return;

    box.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('.mega-search-load-more-btn') : null;
      if (!btn) return;
      e.preventDefault();

      var wrap = btn.closest('.mega-search-load-more-wrap');
      if (!wrap || btn.disabled) return;

      var tab = wrap.getAttribute('data-tab') || getActiveTab();
      var q = wrap.getAttribute('data-q') || getSearchQuery();
      var offset = parseInt(wrap.getAttribute('data-offset') || '0', 10) || 0;
      var selector = wrap.getAttribute('data-tbody-selector') || '';

      btn.disabled = true;
      var labelEl = btn.querySelector('.load-more-text');
      var prevLabel = labelEl ? labelEl.textContent : '';
      if (labelEl) labelEl.textContent = 'Loading…';

      var xhr = new XMLHttpRequest();
      xhr.open('GET', getAjaxUrl(q, tab, { load_more: '1', offset: String(offset) }), true);
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        btn.disabled = false;
        if (labelEl) labelEl.textContent = prevLabel;

        if (xhr.status < 200 || xhr.status >= 300) {
          return;
        }
        try {
          var data = JSON.parse(xhr.responseText);
          if (!data || !data.success) return;

          var tbodySel = data.tbody_selector || selector;
          var tbody = tbodySel ? document.querySelector('#mega-search-results ' + tbodySel) : null;
          if (tbody && data.rows_html) {
            tbody.insertAdjacentHTML('beforeend', data.rows_html);
          }

          if (wrap.parentNode) {
            if (data.load_more_html) {
              wrap.outerHTML = data.load_more_html;
            } else {
              wrap.parentNode.removeChild(wrap);
            }
          }

          if (window.MegaSearchBulk) {
            window.MegaSearchBulk.refresh();
          }
        } catch (err) {}
      };
      xhr.send();
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initTabs();
    initHeaderInput();
    initPageInput();
    initLoadMore();
    updateBulkToolbarForTab(getActiveTab());
  });
})();

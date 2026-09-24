/**
 * Global toast notifications — same markup/classes as mail/new.php (style.min.css: .toast-container, .toast, .toast-*).
 *
 * Programmatic:
 *   showToast('Saved.', 'success');
 *   showToast('Something failed.', 'error');
 *   showToast('FYI.', 'info');
 *   showToast('Quick note', 'success', 1500); // optional duration override (ms)
 *   var dismiss = showToast('Processing…', 'info', -1); dismiss(); // stay until dismissed
 *
 * Server-driven (minimal PHP — no inline handlers per page):
 *   <script>window.__toastFlash=<?php echo json_encode($toast_flash, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>;</script>
 *   <script src=".../assets/js/toast.js" defer></script>
 *   // $toast_flash = null OR array('msg'=>'…','type'=>'success'|'error'|'info', optional 'duration'=>ms)
 *
 * Container #toastContainer is created automatically if missing.
 */
(function (global) {
  'use strict';

  function ensureToastContainer() {
    var c = document.getElementById('toastContainer');
    if (!c) {
      c = document.createElement('div');
      c.id = 'toastContainer';
      c.className = 'toast-container';
      c.setAttribute('aria-live', 'polite');
      document.body.appendChild(c);
    }
    return c;
  }

  /**
   * @param {string} message
   * @param {'success'|'error'|'info'} [type]
   * @param {number} [durationMs]  optional; default 2000 for success, 3000 otherwise
   */
  function showToast(message, type, durationMs) {
    type = type || 'info';
    var container = ensureToastContainer();
    var toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    var span = document.createElement('span');
    span.className = 'toast-message';
    span.textContent = message == null ? '' : String(message);
    toast.appendChild(span);
    container.appendChild(toast);

    requestAnimationFrame(function () {
      toast.classList.add('toast-show');
    });

    var duration =
      typeof durationMs === 'number' && durationMs >= 0
        ? durationMs
        : type === 'success'
          ? 2000
          : 3000;

    if (durationMs === -1) {
      return function dismissToast() {
        toast.classList.remove('toast-show');
        toast.classList.add('toast-hide');
        setTimeout(function () {
          if (toast.parentNode) {
            toast.remove();
          }
        }, 300);
      };
    }

    setTimeout(function () {
      toast.classList.remove('toast-show');
      toast.classList.add('toast-hide');
      setTimeout(function () {
        if (toast.parentNode) {
          toast.remove();
        }
      }, 300);
    }, duration);
  }

  /**
   * Reads window.__toastFlash set by PHP before this script loads; shows one toast and clears it.
   */
  function consumeToastFlash() {
    var f = global.__toastFlash;
    if (f == null || typeof f !== 'object') {
      return;
    }
    var msg = f.msg != null ? f.msg : f.message;
    if (msg === '' || msg == null) {
      global.__toastFlash = null;
      return;
    }
    var type = f.type || 'info';
    var dur = typeof f.duration === 'number' ? f.duration : undefined;
    showToast(String(msg), type, dur);
    global.__toastFlash = null;
  }

  function initToastFlashListener() {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', consumeToastFlash);
    } else {
      consumeToastFlash();
    }
  }

  global.ensureToastContainer = ensureToastContainer;
  global.showToast = showToast;
  global.consumeToastFlash = consumeToastFlash;

  initToastFlashListener();
})(typeof window !== 'undefined' ? window : this);

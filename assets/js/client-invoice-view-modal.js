(function (global) {
  'use strict';

  function getConfig() {
    return global.clientInvoiceViewModalConfig || {};
  }

  function getModalEl() {
    return document.getElementById('edit-milestone1');
  }

  function getContentEl() {
    var modalEl = getModalEl();
    return modalEl ? modalEl.querySelector('.client-invoice-view-modal__content') : null;
  }

  function ensureModal() {
    var modalEl = getModalEl();
    if (!modalEl || typeof global.bootstrap === 'undefined' || !global.bootstrap.Modal) {
      return null;
    }
    return global.bootstrap.Modal.getOrCreateInstance(modalEl);
  }

    function hideOutstandingModal() {
        var outstandingModal = document.getElementById('clientOutstandingInvoicesModal');
        if (!outstandingModal || typeof global.bootstrap === 'undefined') {
            return;
        }
        var outstandingInstance = global.bootstrap.Modal.getInstance(outstandingModal);
        if (outstandingInstance) {
            outstandingInstance.hide();
        }
    }

    function hidePaymentModal() {
    var paymentModal = document.getElementById('edit-milestone');
    if (!paymentModal || typeof global.bootstrap === 'undefined') {
      return;
    }
    var paymentInstance = global.bootstrap.Modal.getInstance(paymentModal);
    if (paymentInstance) {
      paymentInstance.hide();
    }
  }

  function openClientInvoiceViewModal(invoiceId) {
    var cfg = getConfig();
    var contentEl = getContentEl();
    var modalEl = getModalEl();
    if (!contentEl || !modalEl || !cfg.ajaxUrl) {
      return;
    }

    invoiceId = parseInt(invoiceId, 10);
    if (!invoiceId) {
      return;
    }

    var modal = ensureModal();
    if (!modal) {
      return;
    }

    hideOutstandingModal();
    hidePaymentModal();

    contentEl.innerHTML = '<div class="text-center py-4 text-muted client-invoice-view-modal__loading">' +
      (cfg.loadingLabel || 'Loading...') + '</div>';
    modal.show();

    fetch(cfg.ajaxUrl + '?invoice_id=' + encodeURIComponent(invoiceId), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    })
      .then(function (response) {
        return response.text().then(function (text) {
          var data;
          try {
            data = JSON.parse(text);
          } catch (parseError) {
            throw new Error(cfg.errorLabel || 'Unable to load invoice');
          }
          if (!response.ok && (!data || !data.message)) {
            throw new Error(cfg.errorLabel || 'Unable to load invoice');
          }
          return data;
        });
      })
      .then(function (data) {
        if (!data || !data.success) {
          throw new Error((data && data.message) || cfg.errorLabel || 'Unable to load invoice');
        }
        contentEl.innerHTML = data.html;
      })
      .catch(function (err) {
        contentEl.innerHTML = '<div class="alert alert-danger m-3">' +
          (err && err.message ? err.message : (cfg.errorLabel || 'Unable to load invoice')) + '</div>';
      });
  }

  function bindTriggers() {
    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('.client-invoice-view-trigger');
      if (!trigger) {
        return;
      }
      e.preventDefault();
      openClientInvoiceViewModal(trigger.getAttribute('data-invoice-id'));
    });
  }

  function bindModalEvents() {
    var modalEl = getModalEl();
    if (!modalEl) {
      return;
    }

    modalEl.addEventListener('hidden.bs.modal', function () {
      var contentEl = getContentEl();
      if (contentEl) {
        contentEl.innerHTML = '<div class="text-center py-4 text-muted client-invoice-view-modal__loading d-none">' +
          (getConfig().loadingLabel || 'Loading...') + '</div>';
      }
    });

    var paymentModal = document.getElementById('edit-milestone');
    if (paymentModal) {
      paymentModal.addEventListener('show.bs.modal', function () {
        var invoiceModalInstance = global.bootstrap.Modal.getInstance(modalEl);
        if (invoiceModalInstance) {
          invoiceModalInstance.hide();
        }
      });
    }
  }

  global.openClientInvoiceViewModal = openClientInvoiceViewModal;

  /**
   * Pay now from invoice modal (available on client pages that don't load copy-link.js).
   * Client project → payment modal; admin/staff project → Mark as Paid;
   * otherwise open pay.php / data-pay-url.
   */
  function openInvoicePayNow(invoiceId, projectId, btnEl) {
    invoiceId = parseInt(invoiceId, 10);
    projectId = parseInt(projectId, 10) || 0;
    if (!invoiceId) {
      return;
    }

    if (projectId > 0) {
      if (typeof global.openClientPaymentModal === 'function') {
        global.openClientPaymentModal(projectId, invoiceId);
        return;
      }
      var form = document.getElementById('invoicePayNowMarkPaidForm');
      var idInput = document.getElementById('invoicePayNowInvoiceId');
      var modalEl = document.getElementById('invoicePayNowConfirmModal');
      if (!form || !idInput || !modalEl || typeof global.bootstrap === 'undefined') {
        return;
      }
      idInput.value = String(invoiceId);
      form.setAttribute('action', window.location.pathname + window.location.search);
      var modal = global.bootstrap.Modal.getOrCreateInstance(modalEl);
      modal.show();
      return;
    }

    var payUrl = '';
    if (btnEl && btnEl.getAttribute) {
      payUrl = btnEl.getAttribute('data-pay-url') || '';
    }
    if (payUrl) {
      window.open(payUrl, '_blank');
      return;
    }

    var cfg = getConfig();
    var urlBase = (cfg && cfg.ajaxUrl) ? cfg.ajaxUrl.replace(/\/ajax\/[^/]+$/, '/') : '/';
    fetch(urlBase + 'ajax/generate-payment-link.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: (window.tasksessionCsrfHeaders ? window.tasksessionCsrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }) : { 'Content-Type': 'application/x-www-form-urlencoded' }),
      body: 'invoice_id=' + encodeURIComponent(invoiceId) + (window.csrfToken ? '&csrf_token=' + encodeURIComponent(window.csrfToken) : '')
    })
      .then(function (response) {
        return response.text().then(function (text) {
          try {
            return JSON.parse(text);
          } catch (e) {
            throw new Error('Invalid server response');
          }
        });
      })
      .then(function (data) {
        if (data && data.success && data.payment_url) {
          window.open(data.payment_url, '_blank');
          return;
        }
        alert((data && data.message) || 'Error generating payment link');
      })
      .catch(function () {
        alert('Error generating payment link');
      });
  }

  global.openInvoicePayNow = openInvoicePayNow;

  document.addEventListener('DOMContentLoaded', function () {
    bindTriggers();
    bindModalEvents();
  });
})(window);

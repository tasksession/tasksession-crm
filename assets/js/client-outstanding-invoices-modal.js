(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var modalEl = document.getElementById('clientOutstandingInvoicesModal');
    if (!modalEl || typeof bootstrap === 'undefined' || !bootstrap.Modal) {
      return;
    }

    var dismissUrl = modalEl.getAttribute('data-dismiss-url') || '';
    var autoShow = modalEl.getAttribute('data-auto-show') !== '0';
    var restoreOnPaymentClose = modalEl.getAttribute('data-restore-on-payment-close') !== '0';
    var dismissed = false;
    var hideForPayment = false;
    var pendingRestoreAfterPayment = false;
    var wasAutoShown = false;

    function dismissModal() {
      if (dismissed || !dismissUrl) {
        return;
      }
      dismissed = true;
      fetch(dismissUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      }).catch(function () {
        dismissed = false;
      });
    }

    window.clientOutstandingInvoicesKeepOpen = function () {
      if (restoreOnPaymentClose) {
        pendingRestoreAfterPayment = true;
      }
      hideForPayment = true;
    };

    window.clientOutstandingInvoicesClearRestore = function () {
      pendingRestoreAfterPayment = false;
      hideForPayment = false;
    };

    window.clientOutstandingInvoicesRestoreAfterPayment = function () {
      if (!pendingRestoreAfterPayment || !restoreOnPaymentClose) {
        pendingRestoreAfterPayment = false;
        hideForPayment = false;
        return;
      }
      pendingRestoreAfterPayment = false;
      hideForPayment = false;
      modal.show();
    };

    modalEl.addEventListener('hidden.bs.modal', function () {
      if (hideForPayment) {
        hideForPayment = false;
        return;
      }
      pendingRestoreAfterPayment = false;
      if (wasAutoShown) {
        dismissModal();
        wasAutoShown = false;
      }
    });

    var modal = bootstrap.Modal.getOrCreateInstance(modalEl, {
      backdrop: 'static',
      keyboard: false
    });

    if (autoShow) {
      wasAutoShown = true;
      modal.show();
    }

    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('.client-outstanding-invoices-open-trigger');
      if (!trigger) {
        return;
      }
      e.preventDefault();
      modal.show();
    });
  });
})();

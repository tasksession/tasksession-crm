/**
 * Copy Payment Link Function
 * Generates and copies payment link to clipboard
 */
function copyPaymentLink(invoiceId) {
    const urlBase = typeof baseUrl !== 'undefined' ? baseUrl : (typeof url !== 'undefined' ? url : '');

    fetch(urlBase + 'ajax/generate-payment-link.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: (window.tasksessionCsrfHeaders ? window.tasksessionCsrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }) : { 'Content-Type': 'application/x-www-form-urlencoded' }),
        body: 'invoice_id=' + encodeURIComponent(invoiceId) + (window.csrfToken ? '&csrf_token=' + encodeURIComponent(window.csrfToken) : '')
    })
    .then(function (response) {
        return response.text().then(function (text) {
            if (!text || !String(text).trim()) {
                throw new Error('Empty server response');
            }
            try {
                return JSON.parse(text);
            } catch (parseErr) {
                console.error('Payment link response was not JSON:', text.substring(0, 200));
                throw new Error('Invalid server response');
            }
        });
    })
    .then(function (data) {
        if (data.success) {
            navigator.clipboard.writeText(data.payment_url).then(function () {
                const successMessage = typeof lang !== 'undefined' && lang['Payment link copied to clipboard!']
                    ? lang['Payment link copied to clipboard!']
                    : 'Payment link copied to clipboard!';
                alert(successMessage);
            }, function (err) {
                console.error('Failed to copy: ', err);
                const errorMessage = typeof lang !== 'undefined' && lang['Error copying payment link']
                    ? lang['Error copying payment link']
                    : 'Error copying payment link';
                alert(errorMessage);
            });
        } else {
            const errorMessage = data.message || (typeof lang !== 'undefined' && lang['Error generating payment link']
                ? lang['Error generating payment link']
                : 'Error generating payment link');
            alert(errorMessage);
        }
    })
    .catch(function (error) {
        console.error('Error:', error);
        const errorMessage = typeof lang !== 'undefined' && lang['Error generating payment link']
            ? lang['Error generating payment link']
            : 'Error generating payment link';
        alert(errorMessage);
    });
}

/**
 * Pay now from invoice modal.
 * Client project → payment modal; admin/staff project → Mark as Paid confirm;
 * otherwise open pay.php (or data-pay-url).
 */
function openInvoicePayNow(invoiceId, projectId, btnEl) {
    invoiceId = parseInt(invoiceId, 10);
    projectId = parseInt(projectId, 10) || 0;
    if (!invoiceId) {
        return;
    }

    if (projectId > 0) {
        // Client checkout modal (client pages load this)
        if (typeof openClientPaymentModal === 'function') {
            openClientPaymentModal(projectId, invoiceId);
            return;
        }
        // Admin/staff: Mark as Paid confirm
        var form = document.getElementById('invoicePayNowMarkPaidForm');
        var idInput = document.getElementById('invoicePayNowInvoiceId');
        var modalEl = document.getElementById('invoicePayNowConfirmModal');
        if (!form || !idInput || !modalEl || typeof bootstrap === 'undefined') {
            return;
        }
        idInput.value = String(invoiceId);
        form.setAttribute('action', window.location.pathname + window.location.search);
        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        return;
    }

    // Non-project: prefer server-provided pay URL (client), else generate link (admin/staff)
    var payUrl = '';
    if (btnEl && btnEl.getAttribute) {
        payUrl = btnEl.getAttribute('data-pay-url') || '';
    }
    if (payUrl) {
        window.open(payUrl, '_blank');
        return;
    }

    var urlBase = typeof baseUrl !== 'undefined' ? baseUrl : (typeof url !== 'undefined' ? url : '');
    fetch(urlBase + 'ajax/generate-payment-link.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: (window.tasksessionCsrfHeaders ? window.tasksessionCsrfHeaders({ 'Content-Type': 'application/x-www-form-urlencoded' }) : { 'Content-Type': 'application/x-www-form-urlencoded' }),
        body: 'invoice_id=' + encodeURIComponent(invoiceId) + (window.csrfToken ? '&csrf_token=' + encodeURIComponent(window.csrfToken) : '')
    })
    .then(function (response) {
        return response.text().then(function (text) {
            if (!text || !String(text).trim()) {
                throw new Error('Empty server response');
            }
            try {
                return JSON.parse(text);
            } catch (parseErr) {
                throw new Error('Invalid server response');
            }
        });
    })
    .then(function (data) {
        if (data && data.success && data.payment_url) {
            window.open(data.payment_url, '_blank');
            return;
        }
        var errorMessage = (data && data.message)
            || (typeof lang !== 'undefined' && lang['Error generating payment link']
                ? lang['Error generating payment link']
                : 'Error generating payment link');
        if (typeof showToast === 'function') {
            showToast(errorMessage, 'error');
        } else {
            alert(errorMessage);
        }
    })
    .catch(function () {
        var errorMessage = typeof lang !== 'undefined' && lang['Error generating payment link']
            ? lang['Error generating payment link']
            : 'Error generating payment link';
        if (typeof showToast === 'function') {
            showToast(errorMessage, 'error');
        } else {
            alert(errorMessage);
        }
    });
}

window.openInvoicePayNow = openInvoicePayNow;

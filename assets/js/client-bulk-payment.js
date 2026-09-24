(function (global) {
    "use strict";

    var bulkRunning = false;
    var bulkReloadOnClose = false;

    function getConfig() {
        return global.clientPaymentModalConfig || {};
    }

    function getLabels() {
        var cfg = getConfig();
        return cfg.bulkLabels || {};
    }

    function label(key, fallback) {
        var labels = getLabels();
        return labels[key] || fallback;
    }

    function getModalEl() {
        return document.getElementById("edit-milestone");
    }

    function getBodyEl() {
        return document.getElementById("client-payment-modal-body");
    }

    function getBulkRoot() {
        var body = getBodyEl();
        return body ? body.querySelector(".client-bulk-payment") : null;
    }

    function setSubmitLoading(submitButton, loading, originalContent) {
        if (!submitButton) {
            return;
        }
        if (loading) {
            submitButton.innerHTML =
                "<svg class=\"spinner-icon\" xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\">" +
                "<circle class=\"spinner-circle-animated\" cx=\"12\" cy=\"12\" r=\"10\" stroke-dasharray=\"24\" stroke-dashoffset=\"24\"></circle>" +
                "</svg>" +
                originalContent;
            submitButton.disabled = true;
            submitButton.style.pointerEvents = "none";
            submitButton.style.opacity = "0.6";
            submitButton.style.cursor = "not-allowed";
        } else {
            submitButton.disabled = false;
            submitButton.style.pointerEvents = "";
            submitButton.style.opacity = "";
            submitButton.style.cursor = "";
            submitButton.innerHTML = originalContent;
        }
    }

    function setModalCloseDisabled(disabled) {
        var modalEl = getModalEl();
        if (!modalEl) {
            return;
        }
        var closeBtn = modalEl.querySelector(".btn-close");
        if (closeBtn) {
            closeBtn.disabled = !!disabled;
            closeBtn.style.pointerEvents = disabled ? "none" : "";
            closeBtn.style.opacity = disabled ? "0.45" : "";
        }
        modalEl.dataset.bulkProcessing = disabled ? "1" : "0";
    }

    function getRow(invoiceId) {
        var body = getBodyEl();
        if (!body) {
            return null;
        }
        return body.querySelector('.client-bulk-payment__row[data-invoice-id="' + invoiceId + '"]');
    }

    function getPendingInvoiceIds() {
        var body = getBodyEl();
        if (!body) {
            return [];
        }
        var rows = body.querySelectorAll(".client-bulk-payment__row:not(.client-bulk-payment__row--paid)");
        var ids = [];
        rows.forEach(function (row) {
            var id = parseInt(row.getAttribute("data-invoice-id"), 10);
            if (id > 0) {
                ids.push(id);
            }
        });
        return ids;
    }

    function setRowProcessing(invoiceId, processing) {
        var row = getRow(invoiceId);
        if (!row) {
            return;
        }
        row.classList.toggle("client-bulk-payment__row--processing", !!processing);
        row.classList.remove("client-bulk-payment__row--failed");
        var spinner = row.querySelector(".client-bulk-payment__amount-spinner");
        if (spinner) {
            if (processing) {
                spinner.removeAttribute("hidden");
            } else {
                spinner.setAttribute("hidden", "");
            }
        }
    }

    function setRowPaid(invoiceId) {
        var row = getRow(invoiceId);
        if (!row) {
            return;
        }
        row.classList.remove("client-bulk-payment__row--processing", "client-bulk-payment__row--failed");
        row.classList.add("client-bulk-payment__row--paid");
        var spinner = row.querySelector(".client-bulk-payment__amount-spinner");
        if (spinner) {
            spinner.setAttribute("hidden", "");
        }
        var statusEl = row.querySelector(".client-bulk-payment__status");
        if (statusEl) {
            statusEl.innerHTML = '<span class="client-bulk-payment__paid-badge">' + label("paid", "Paid") + "</span>";
        }
    }

    function setRowFailed(invoiceId, message) {
        var row = getRow(invoiceId);
        if (!row) {
            return;
        }
        row.classList.remove("client-bulk-payment__row--processing");
        row.classList.add("client-bulk-payment__row--failed");
        var spinner = row.querySelector(".client-bulk-payment__amount-spinner");
        if (spinner) {
            spinner.setAttribute("hidden", "");
        }
        if (message) {
            row.setAttribute("title", message);
            row.setAttribute("data-error", message);
        }
    }

    function clearBulkMessages() {
        var root = getBulkRoot();
        if (!root) {
            return;
        }
        var existing = root.querySelector(".client-bulk-payment__message");
        if (existing) {
            existing.remove();
        }
    }

    function showBulkMessage(type, text) {
        var root = getBulkRoot();
        if (!root || !text) {
            return;
        }
        clearBulkMessages();
        var el = document.createElement("div");
        el.className = "client-bulk-payment__message client-bulk-payment__message--" + type;
        el.setAttribute("role", type === "error" ? "alert" : "status");
        el.textContent = text;
        root.insertBefore(el, root.querySelector(".client-bulk-payment__groups"));
    }

    function removeRetryButton() {
        var root = getBulkRoot();
        if (!root) {
            return;
        }
        var btn = root.querySelector(".client-bulk-payment__retry");
        if (btn) {
            btn.remove();
        }
    }

    function showRetryButton(form, paymentPayload, submitButton, originalContent) {
        var root = getBulkRoot();
        if (!root) {
            return;
        }
        removeRetryButton();
        var wrap = document.createElement("div");
        wrap.className = "client-bulk-payment__retry-wrap";
        var btn = document.createElement("button");
        btn.type = "button";
        btn.className = "bigbutton client-bulk-payment__retry";
        btn.textContent = label("retryRemaining", "Retry remaining invoices");
        btn.addEventListener("click", function () {
            removeRetryButton();
            clearBulkMessages();
            runClientBulkPayment(form, paymentPayload, submitButton, originalContent);
        });
        wrap.appendChild(btn);
        root.appendChild(wrap);
    }

    function removeBulkComplete() {
        var root = getBulkRoot();
        if (!root) {
            return;
        }
        var completeEl = root.querySelector(".client-bulk-payment__complete");
        if (completeEl) {
            completeEl.remove();
        }
    }

    function showBulkComplete() {
        var root = getBulkRoot();
        if (!root) {
            return;
        }
        removeBulkComplete();

        var intro = root.querySelector(".client-bulk-payment__intro");
        var summary = root.querySelector(".client-bulk-payment__summary");
        if (intro) {
            intro.style.display = "none";
        }
        if (summary) {
            summary.style.display = "none";
        }

        var completeEl = document.createElement("div");
        completeEl.className = "client-bulk-payment__complete";
        completeEl.setAttribute("role", "status");
        completeEl.innerHTML =
            '<div class="client-bulk-payment__complete-icon" aria-hidden="true">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' +
            '<path d="M20 6 9 17l-5-5"></path>' +
            "</svg></div>" +
            '<h3 class="client-bulk-payment__complete-title">' + label("allPaid", "All invoices paid successfully") + "</h3>" +
            '<p class="client-bulk-payment__complete-detail">' + label("allPaidDetail", "Your payment has been processed and all selected invoices are now marked as paid.") + "</p>";

        root.insertBefore(completeEl, root.firstChild);
    }

    function showAllPaidSuccess(form) {
        var root = getBulkRoot();
        if (!root) {
            return;
        }
        clearBulkMessages();
        removeRetryButton();
        showBulkComplete();
        var gateway = getBodyEl().querySelector(".client-payment-gateway");
        if (gateway) {
            gateway.style.display = "none";
        }
        var footer = getBodyEl().querySelector(".client-payment-footer");
        if (footer) {
            footer.style.display = "none";
        }
        if (form) {
            form.dataset.bulkComplete = "1";
        }
        bulkReloadOnClose = true;
    }

    function getCsrfToken() {
        if (typeof global.csrfToken === "string" && global.csrfToken) {
            return global.csrfToken;
        }
        var cfg = getConfig();
        return (typeof cfg.csrfToken === "string" && cfg.csrfToken) ? cfg.csrfToken : "";
    }

    function appendCsrf(body) {
        var token = getCsrfToken();
        if (token) {
            body.set("csrf_token", token);
        }
        return body;
    }

    function csrfHeaders() {
        var token = getCsrfToken();
        var headers = {
            "Content-Type": "application/x-www-form-urlencoded",
            "X-Requested-With": "XMLHttpRequest",
            Accept: "application/json",
        };
        if (token) {
            headers["X-CSRF-TOKEN"] = token;
        }
        return headers;
    }

    function buildInvoiceRequestBody(form, invoiceId, paymentPayload, includeSaveCard) {
        var body = appendCsrf(new URLSearchParams(new FormData(form)));
        body.set("invoice_id", String(invoiceId));
        body.set("bulk_pay", "1");
        body.set("stripePaymentMethodId", paymentPayload.stripePaymentMethodId || "");
        if (paymentPayload.use_saved) {
            body.set("use_saved", "1");
        } else {
            body.delete("use_saved");
        }
        if (includeSaveCard && paymentPayload.save_card) {
            body.set("save_card", "1");
        } else {
            body.delete("save_card");
        }
        return body;
    }

    function chargeInvoice(form, invoiceId, paymentPayload, includeSaveCard) {
        var cfg = getConfig();
        var url = cfg.bulkProcessUrl;
        if (!url) {
            return Promise.reject(new Error(label("unavailable", "Bulk payment is not available.")));
        }
        return fetch(url, {
            method: "POST",
            credentials: "same-origin",
            headers: csrfHeaders(),
            body: buildInvoiceRequestBody(form, invoiceId, paymentPayload, includeSaveCard).toString(),
        }).then(function (response) {
            return response.text().then(function (text) {
                var data;
                try {
                    data = JSON.parse(text);
                } catch (err) {
                    if (typeof console !== "undefined" && console.error) {
                        console.error("[Bulk payment] Non-JSON response:", text);
                    }
                    throw new Error(label("invalidResponse", "Invalid server response. Please refresh and try again."));
                }
                if (!data) {
                    throw new Error(label("invalidResponse", "Invalid server response. Please refresh and try again."));
                }
                if (!response.ok && data.message) {
                    throw new Error(data.message);
                }
                return data;
            });
        });
    }

    function setCardErrors(form, message) {
        var errorsEl = form.querySelector("#card-errors");
        if (errorsEl) {
            errorsEl.textContent = message || "";
        }
    }

    function runClientBulkPayment(form, paymentPayload, submitButton, originalContent) {
        if (bulkRunning) {
            return Promise.resolve();
        }
        var cfg = getConfig();
        if (!cfg.bulkProcessUrl) {
            setCardErrors(form, label("unavailable", "Bulk payment is not available."));
            return Promise.resolve();
        }

        var invoiceIds = getPendingInvoiceIds();
        if (!invoiceIds.length) {
            showAllPaidSuccess(form);
            return Promise.resolve();
        }

        bulkRunning = true;
        setModalCloseDisabled(true);
        removeRetryButton();
        clearBulkMessages();
        if (submitButton && originalContent) {
            setSubmitLoading(submitButton, true, originalContent);
        }

        var saveCardUsed = false;
        var chain = Promise.resolve();

        invoiceIds.forEach(function (invoiceId) {
            chain = chain.then(function () {
                if (!bulkRunning) {
                    return { stopped: true };
                }
                setRowProcessing(invoiceId, true);
                var includeSaveCard = !saveCardUsed;
                return chargeInvoice(form, invoiceId, paymentPayload, includeSaveCard).then(function (data) {
                    if (data.success) {
                        if (includeSaveCard && paymentPayload.save_card) {
                            saveCardUsed = true;
                        }
                        setRowPaid(invoiceId);
                        return data;
                    }
                    setRowFailed(invoiceId, data.message || label("invoiceFailed", "Invoice payment failed."));
                    throw new Error(data.message || label("invoiceFailed", "Invoice payment failed."));
                });
            });
        });

        return chain
            .then(function () {
                var remaining = getPendingInvoiceIds();
                if (!remaining.length) {
                    showAllPaidSuccess(form);
                }
            })
            .catch(function (err) {
                var msg = (err && err.message) ? err.message : label("invoiceFailed", "Invoice payment failed.");
                showBulkMessage("error", msg);
                if (getPendingInvoiceIds().length) {
                    showRetryButton(form, paymentPayload, submitButton, originalContent);
                }
            })
            .then(function () {
                bulkRunning = false;
                setModalCloseDisabled(false);
                if (submitButton && originalContent) {
                    var remaining = getPendingInvoiceIds();
                    if (remaining.length) {
                        setSubmitLoading(submitButton, false, originalContent);
                    }
                }
            });
    }

    function bindModalReload() {
        var modalEl = getModalEl();
        if (!modalEl || modalEl.dataset.bulkReloadBound === "1") {
            return;
        }
        modalEl.dataset.bulkReloadBound = "1";
        modalEl.addEventListener("hide.bs.modal", function (e) {
            if (modalEl.dataset.bulkProcessing === "1" || modalEl.dataset.singleProcessing === "1") {
                e.preventDefault();
                return;
            }
        });
        modalEl.addEventListener("hidden.bs.modal", function () {
            if (bulkReloadOnClose) {
                bulkReloadOnClose = false;
                global.location.reload();
            }
        });
    }

    bindModalReload();

    global.runClientBulkPayment = runClientBulkPayment;
})(window);

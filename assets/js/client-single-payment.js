(function (global) {
    "use strict";

    var singleReloadOnClose = false;

    function getConfig() {
        return global.clientPaymentModalConfig || {};
    }

    function getLabels() {
        var cfg = getConfig();
        return cfg.singleLabels || cfg.bulkLabels || {};
    }

    function label(key, fallback) {
        var labels = getLabels();
        return labels[key] || fallback;
    }

    function getBodyEl() {
        return document.getElementById("client-payment-modal-body");
    }

    function getModalEl() {
        return document.getElementById("edit-milestone");
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
        modalEl.dataset.singleProcessing = disabled ? "1" : "0";
    }

    function removeSingleComplete() {
        var body = getBodyEl();
        if (!body) {
            return;
        }
        var el = body.querySelector(".client-payment-complete");
        if (el) {
            el.remove();
        }
    }

    function showSinglePaymentSuccess() {
        var body = getBodyEl();
        if (!body) {
            return;
        }
        removeSingleComplete();

        var gateway = body.querySelector(".client-payment-gateway");
        var footer = body.querySelector(".client-payment-footer");
        if (gateway) {
            gateway.style.display = "none";
        }
        if (footer) {
            footer.style.display = "none";
        }

        var milestoneRow = body.querySelector(".client-payment-milestone__table tbody tr");
        if (milestoneRow) {
            milestoneRow.classList.add("client-payment-milestone__row--paid");
            var amountEl = milestoneRow.querySelector(".client-payment-milestone__amount");
            if (amountEl && !milestoneRow.querySelector(".client-payment-milestone__paid-badge")) {
                var badge = document.createElement("span");
                badge.className = "client-payment-milestone__paid-badge";
                badge.textContent = label("paid", "Paid");
                amountEl.insertAdjacentElement("afterend", badge);
            }
        }

        var completeEl = document.createElement("div");
        completeEl.className = "client-payment-complete client-bulk-payment__complete";
        completeEl.setAttribute("role", "status");
        completeEl.innerHTML =
            '<div class="client-bulk-payment__complete-icon" aria-hidden="true">' +
            '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">' +
            '<path d="M20 6 9 17l-5-5"></path>' +
            "</svg></div>" +
            '<h3 class="client-bulk-payment__complete-title">' + label("thankYouTitle", "Thank you for your payment!") + "</h3>" +
            '<p class="client-bulk-payment__complete-detail">' + label("thankYouDetail", "We've received your payment. Thank you!.") + "</p>";

        body.insertBefore(completeEl, body.firstChild);
        singleReloadOnClose = true;
    }

    function setCardErrors(form, message) {
        var errorsEl = form.querySelector("#card-errors");
        if (errorsEl) {
            errorsEl.textContent = message || "";
        }
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

    function runClientSinglePayment(form, paymentPayload, submitButton, originalContent) {
        var cfg = getConfig();
        var url = cfg.singleProcessUrl;
        if (!url) {
            setCardErrors(form, label("unavailable", "Payment is not available."));
            setSubmitLoading(submitButton, false, originalContent);
            return Promise.resolve();
        }

        setModalCloseDisabled(true);
        setSubmitLoading(submitButton, true, originalContent);

        var body = appendCsrf(new URLSearchParams(new FormData(form)));
        body.set("stripePaymentMethodId", paymentPayload.stripePaymentMethodId || "");
        if (paymentPayload.use_saved) {
            body.set("use_saved", "1");
        } else {
            body.delete("use_saved");
        }
        if (paymentPayload.save_card) {
            body.set("save_card", "1");
        } else {
            body.delete("save_card");
        }

        return fetch(url, {
            method: "POST",
            credentials: "same-origin",
            headers: csrfHeaders(),
            body: body.toString(),
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    var data;
                    try {
                        data = JSON.parse(text);
                    } catch (err) {
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
            })
            .then(function (data) {
                if (!data.success) {
                    throw new Error(data.message || label("invoiceFailed", "Invoice payment failed."));
                }
                showSinglePaymentSuccess();
            })
            .catch(function (err) {
                setCardErrors(form, (err && err.message) ? err.message : label("invoiceFailed", "Invoice payment failed."));
                setSubmitLoading(submitButton, false, originalContent);
            })
            .then(function () {
                setModalCloseDisabled(false);
            });
    }

    function bindModalReload() {
        var modalEl = getModalEl();
        if (!modalEl || modalEl.dataset.singleReloadBound === "1") {
            return;
        }
        modalEl.dataset.singleReloadBound = "1";
        modalEl.addEventListener("hide.bs.modal", function (e) {
            if (modalEl.dataset.singleProcessing === "1" || modalEl.dataset.bulkProcessing === "1") {
                e.preventDefault();
            }
        });
        modalEl.addEventListener("hidden.bs.modal", function () {
            if (singleReloadOnClose) {
                singleReloadOnClose = false;
                global.location.reload();
            }
        });
    }

    bindModalReload();

    global.runClientSinglePayment = runClientSinglePayment;
})(window);

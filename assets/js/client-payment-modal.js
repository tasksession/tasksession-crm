(function (global) {
    "use strict";

    var modalInstance = null;
    var currentStripeConfig = null;
    var twoCoBound = false;

    function getConfig() {
        return global.clientPaymentModalConfig || {};
    }

    function getModalEl() {
        return document.getElementById("edit-milestone");
    }

    function getBodyEl() {
        return document.getElementById("client-payment-modal-body");
    }

    function getLoadingEl() {
        var body = getBodyEl();
        return body ? body.querySelector(".client-payment-modal__loading") : null;
    }

    function showLoading(show) {
        var loading = getLoadingEl();
        if (loading) {
            loading.classList.toggle("d-none", !show);
        }
    }

    function bindTabGuards() {
        var paymentForms = document.querySelectorAll("#client-payment-modal-body form");
        paymentForms.forEach(function (form) {
            form.addEventListener("submit", function (e) {
                if (!e.submitter || !e.submitter.classList.contains("bigbutton")) {
                    e.preventDefault();
                }
            });
        });

        var paypalForm = document.querySelector('#client-payment-modal-body form[action*="paypal.com"]');
        if (paypalForm) {
            paypalForm.addEventListener("submit", function () {
                var submitButton = paypalForm.querySelector('input[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.style.opacity = "0.6";
                    submitButton.style.cursor = "not-allowed";
                    submitButton.value = "Processing payment...";
                }
            });
        }
    }

    function initStripe(config) {
        if (!config || !config.enabled || typeof global.initStripePayment !== "function") {
            return;
        }
        global.initStripePayment({
            publishableKey: config.publishableKey,
            formSelector: "#stripe-payment-form",
            rootSelector: "#client-payment-modal-body",
            amountCents: config.amountCents,
            currency: config.currency,
            country: config.country,
            companyName: config.companyName,
            usePaymentMethod: true,
            savedMethods: config.savedMethods || [],
        });
    }

    function bindTwoCheckout(config) {
        if (!config || !config.enabled || typeof global.TCO === "undefined") {
            return;
        }

        var cfg = getConfig();
        var makePaymentLabel = cfg.makePaymentLabel || "Complete payment";

        var successCallback = function (data) {
            var myForm = document.getElementById("myCCForm");
            if (!myForm) {
                return;
            }
            var submitButton = myForm.querySelector("button[type=\"submit\"]");
            if (submitButton) {
                var originalContent = submitButton.innerHTML;
                submitButton.innerHTML =
                    "<svg class=\"spinner-icon\" xmlns=\"http://www.w3.org/2000/svg\" width=\"20\" height=\"20\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" stroke-linecap=\"round\">" +
                    "<circle class=\"spinner-circle-animated\" cx=\"12\" cy=\"12\" r=\"10\" stroke-dasharray=\"24\" stroke-dashoffset=\"24\"></circle>" +
                    "</svg>" + originalContent;
                submitButton.style.pointerEvents = "none";
                submitButton.style.opacity = "0.6";
                submitButton.style.cursor = "not-allowed";
            }
            if (myForm.token) {
                myForm.token.value = data.response.token.token;
            }
            myForm.submit();
        };

        var errorCallback = function (data) {
            if (data.errorCode === 200) {
                return;
            }
            alert(data.errorMsg || "Payment error");
            var myForm = document.getElementById("myCCForm");
            var submitButton = myForm ? myForm.querySelector("button[type=\"submit\"]") : null;
            if (submitButton) {
                submitButton.style.pointerEvents = "";
                submitButton.style.opacity = "";
                submitButton.style.cursor = "";
                submitButton.innerHTML = makePaymentLabel;
            }
        };

        var tokenRequest = function () {
            global.TCO.requestToken(successCallback, errorCallback, {
                sellerId: config.sellerId,
                publishableKey: config.publishableKey,
                ccNo: global.jQuery ? global.jQuery("#ccNo").val() : "",
                cvv: global.jQuery ? global.jQuery("#cvv").val() : "",
                expMonth: global.jQuery ? global.jQuery("#expMonth").val() : "",
                expYear: global.jQuery ? global.jQuery("#expYear").val() : "",
            });
        };

        if (!twoCoBound && global.jQuery) {
            global.jQuery(function () {
                if (typeof global.TCO.loadPubKey === "function") {
                    global.TCO.loadPubKey("production");
                }
                global.jQuery("#myCCForm").off("submit.clientPaymentModal").on("submit.clientPaymentModal", function (e) {
                    e.preventDefault();
                    tokenRequest();
                    return false;
                });
            });
            twoCoBound = true;
        } else if (global.jQuery) {
            global.jQuery("#myCCForm").off("submit.clientPaymentModal").on("submit.clientPaymentModal", function (e) {
                e.preventDefault();
                tokenRequest();
                return false;
            });
        }
    }

    function initSavedCardDropdown() {
        var body = getBodyEl();
        if (!body) {
            return;
        }
        var select = body.querySelector("#saved-payment-method-select");
        var menu = body.querySelector("#savedPaymentDropdownMenu");
        var btnText = body.querySelector("#savedPaymentDropdownBtnText");
        var dropdownBtn = body.querySelector("#savedPaymentDropdownBtn");
        if (!select || !menu || !dropdownBtn) {
            return;
        }

        function syncButtonLabel() {
            if (!btnText) {
                return;
            }
            var selectedOption = select.options[select.selectedIndex];
            btnText.textContent = selectedOption ? selectedOption.textContent.trim() : "";
        }

        function setActiveItem(value) {
            menu.querySelectorAll("a[data-value]").forEach(function (link) {
                link.classList.toggle("active", link.getAttribute("data-value") === value);
            });
        }

        if (!body.dataset.savedCardDropdownBound) {
            body.dataset.savedCardDropdownBound = "1";
            body.addEventListener("click", function (e) {
                var item = e.target.closest("a[data-value]");
                if (!item) {
                    return;
                }
                var menuEl = item.closest("#savedPaymentDropdownMenu");
                if (!menuEl || !body.contains(menuEl)) {
                    return;
                }
                e.preventDefault();
                var currentSelect = body.querySelector("#saved-payment-method-select");
                var currentMenu = body.querySelector("#savedPaymentDropdownMenu");
                var currentBtnText = body.querySelector("#savedPaymentDropdownBtnText");
                var currentBtn = body.querySelector("#savedPaymentDropdownBtn");
                if (!currentSelect) {
                    return;
                }
                var value = item.getAttribute("data-value");
                currentSelect.value = value;
                currentMenu.querySelectorAll("a[data-value]").forEach(function (link) {
                    link.classList.toggle("active", link.getAttribute("data-value") === value);
                });
                if (currentBtnText) {
                    var selectedOption = currentSelect.options[currentSelect.selectedIndex];
                    currentBtnText.textContent = selectedOption ? selectedOption.textContent.trim() : "";
                }
                currentSelect.dispatchEvent(new Event("change", { bubbles: true }));
                if (currentBtn && global.bootstrap && global.bootstrap.Dropdown) {
                    var dropdownInstance = global.bootstrap.Dropdown.getInstance(currentBtn);
                    if (dropdownInstance) {
                        dropdownInstance.hide();
                    }
                }
            });
        }

        function adjustSavedCardDropup() {
            var wrap = dropdownBtn.closest(".client-payment-saved-card");
            var dropdownWrap = dropdownBtn.closest(".dropdown");
            if (!wrap || !dropdownWrap) {
                return;
            }
            var isBulk = !!body.querySelector(".client-bulk-payment");
            var rect = dropdownBtn.getBoundingClientRect();
            var bodyRect = body.getBoundingClientRect();
            var spaceBelow = bodyRect.bottom - rect.bottom;
            var menuHeight = Math.min(menu.scrollHeight || 240, 240);
            var useDropup = isBulk || spaceBelow < menuHeight + 16;
            wrap.classList.toggle("client-payment-saved-card--dropup", useDropup);
            dropdownWrap.classList.toggle("dropup", useDropup);
        }

        var lockedScrollTop = null;

        dropdownBtn.setAttribute("data-bs-boundary", "viewport");
        dropdownBtn.setAttribute("data-bs-auto-close", "outside");

        if (global.bootstrap && global.bootstrap.Dropdown) {
            var existing = global.bootstrap.Dropdown.getInstance(dropdownBtn);
            if (existing) {
                existing.dispose();
            }
            global.bootstrap.Dropdown.getOrCreateInstance(dropdownBtn, {
                popperConfig: function (defaultConfig) {
                    defaultConfig.strategy = "fixed";
                    defaultConfig.modifiers = (defaultConfig.modifiers || []).concat([
                        { name: "preventOverflow", enabled: false },
                        { name: "flip", enabled: false },
                        { name: "computeStyles", options: { adaptive: false } }
                    ]);
                    return defaultConfig;
                }
            });
            if (!dropdownBtn.dataset.savedCardDropdownEvents) {
                dropdownBtn.dataset.savedCardDropdownEvents = "1";
                dropdownBtn.addEventListener("click", function () {
                    lockedScrollTop = body.scrollTop;
                }, true);
                dropdownBtn.addEventListener("show.bs.dropdown", function () {
                    adjustSavedCardDropup();
                    if (lockedScrollTop !== null) {
                        body.scrollTop = lockedScrollTop;
                    }
                    body.classList.add("client-payment-modal__dropdown-open");
                });
                dropdownBtn.addEventListener("shown.bs.dropdown", function () {
                    if (lockedScrollTop !== null) {
                        body.scrollTop = lockedScrollTop;
                    }
                });
                dropdownBtn.addEventListener("hidden.bs.dropdown", function () {
                    lockedScrollTop = null;
                    body.classList.remove("client-payment-modal__dropdown-open");
                });
            }
        }

        syncButtonLabel();
        setActiveItem(select.value);
        adjustSavedCardDropup();
    }

    function initGateways(stripeConfig, twoCheckoutConfig, gatewayConfigs) {
        currentStripeConfig = stripeConfig || null;
        bindTabGuards();

        function finishInit() {
            initSavedCardDropdown();
            if (gatewayConfigs && typeof global.initClientPaymentGateways === "function") {
                global.initClientPaymentGateways(gatewayConfigs);
            } else {
                initStripe(currentStripeConfig);
            }
            bindTwoCheckout(twoCheckoutConfig);
        }

        finishInit();
        if (typeof requestAnimationFrame === "function") {
            requestAnimationFrame(finishInit);
        }
    }

    function hideOutstandingForPayment() {
        var outstandingModal = document.getElementById("clientOutstandingInvoicesModal");
        if (outstandingModal && typeof global.bootstrap !== "undefined") {
            var isVisible = outstandingModal.classList.contains("show");
            if (isVisible && typeof global.clientOutstandingInvoicesKeepOpen === "function") {
                global.clientOutstandingInvoicesKeepOpen();
            }
            var outstandingInstance = global.bootstrap.Modal.getInstance(outstandingModal);
            if (outstandingInstance) {
                outstandingInstance.hide();
            }
        }
    }

    function setPaymentModalTitle(isBulk) {
        var modalEl = getModalEl();
        if (!modalEl) {
            return;
        }
        var titleEl = modalEl.querySelector(".card-title");
        if (!titleEl) {
            return;
        }
        var cfg = getConfig();
        titleEl.textContent = isBulk
            ? (cfg.payAllTitle || "Pay all invoices")
            : (cfg.paymentFormTitle || "Payment form");
    }

    function clearUrlMilestoneParam() {
        if (!window.history || !window.history.replaceState) {
            return;
        }
        var url = new URL(window.location.href);
        if (!url.searchParams.has("milestone_id")) {
            return;
        }
        url.searchParams.delete("milestone_id");
        window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
    }

    function ensureModal() {
        var modalEl = getModalEl();
        if (!modalEl || typeof global.bootstrap === "undefined") {
            return null;
        }
        modalInstance = global.bootstrap.Modal.getOrCreateInstance(modalEl);
        return modalInstance;
    }

    function getPaymentReturnUrl() {
        var path = window.location.pathname || "";
        var marker = "/client/";
        var idx = path.indexOf(marker);
        if (idx !== -1) {
            return path.substring(idx + marker.length) + (window.location.search || "");
        }
        var parts = path.split("/");
        return (parts[parts.length - 1] || "invoices") + (window.location.search || "");
    }

    function openClientPaymentModal(projectId, milestoneId) {
        var cfg = getConfig();
        var modalEl = getModalEl();
        var bodyEl = getBodyEl();
        if (!modalEl || !bodyEl || !cfg.ajaxUrl) {
            return;
        }

        projectId = parseInt(projectId, 10);
        milestoneId = parseInt(milestoneId, 10);
        if (!projectId || !milestoneId) {
            return;
        }

        var modal = ensureModal();
        if (!modal) {
            return;
        }

        var outstandingModal = document.getElementById("clientOutstandingInvoicesModal");
        hideOutstandingForPayment();

        setPaymentModalTitle(false);
        bodyEl.innerHTML = '<div class="text-center py-4 text-muted client-payment-modal__loading">' +
            (cfg.loadingLabel || "Loading...") + "</div>";
        modal.show();

        var requestUrl = cfg.ajaxUrl +
            "?projectId=" + encodeURIComponent(projectId) +
            "&milestone_id=" + encodeURIComponent(milestoneId) +
            "&return_url=" + encodeURIComponent(getPaymentReturnUrl());

        fetch(requestUrl, {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    var data;
                    try {
                        data = JSON.parse(text);
                    } catch (parseError) {
                        throw new Error(cfg.invalidResponseLabel || "Invalid server response. Please refresh and try again.");
                    }
                    if (!response.ok && (!data || !data.message)) {
                        throw new Error("Unable to load payment form");
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (!data || !data.success) {
                    throw new Error((data && data.message) || "Unable to load payment form");
                }
                bodyEl.innerHTML = data.html;
                initGateways(data.stripe, data.twoCheckout, data.gateways || null);
            })
            .catch(function (err) {
                bodyEl.innerHTML = '<div class="alert alert-danger m-3">' +
                    (err && err.message ? err.message : "Unable to load payment form") + "</div>";
            });
    }

    function openClientBulkPaymentModal() {
        var cfg = getConfig();
        var modalEl = getModalEl();
        var bodyEl = getBodyEl();
        if (!modalEl || !bodyEl || !cfg.bulkAjaxUrl) {
            return;
        }

        var modal = ensureModal();
        if (!modal) {
            return;
        }

        hideOutstandingForPayment();
        setPaymentModalTitle(true);

        bodyEl.innerHTML = '<div class="text-center py-4 text-muted client-payment-modal__loading">' +
            (cfg.loadingLabel || "Loading...") + "</div>";
        modal.show();

        var requestUrl = cfg.bulkAjaxUrl +
            "?return_url=" + encodeURIComponent(getPaymentReturnUrl());

        fetch(requestUrl, {
            credentials: "same-origin",
            headers: { Accept: "application/json" },
        })
            .then(function (response) {
                return response.text().then(function (text) {
                    var data;
                    try {
                        data = JSON.parse(text);
                    } catch (parseError) {
                        throw new Error(cfg.invalidResponseLabel || "Invalid server response. Please refresh and try again.");
                    }
                    if (!response.ok && (!data || !data.message)) {
                        throw new Error("Unable to load bulk payment form");
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (!data || !data.success) {
                    throw new Error((data && data.message) || "Unable to load bulk payment form");
                }
                bodyEl.innerHTML = data.html;
                initGateways(null, null, data.gateways || null);
            })
            .catch(function (err) {
                bodyEl.innerHTML = '<div class="alert alert-danger m-3">' +
                    (err && err.message ? err.message : "Unable to load bulk payment form") + "</div>";
            });
    }

    function bindTriggers() {
        document.addEventListener("click", function (e) {
            var payAllTrigger = e.target.closest(".client-pay-all-trigger");
            if (payAllTrigger) {
                e.preventDefault();
                openClientBulkPaymentModal();
                return;
            }

            var trigger = e.target.closest(".client-pay-now-trigger");
            if (!trigger) {
                return;
            }
            e.preventDefault();
            openClientPaymentModal(trigger.getAttribute("data-project-id"), trigger.getAttribute("data-milestone-id"));
        });
    }

    function bindModalEvents() {
        var modalEl = getModalEl();
        if (!modalEl) {
            return;
        }

        modalEl.addEventListener("shown.bs.modal", function () {
            if (currentStripeConfig) {
                initStripe(currentStripeConfig);
            }
        });

        modalEl.addEventListener("hidden.bs.modal", function () {
            currentStripeConfig = null;
            var bodyEl = getBodyEl();
            if (bodyEl) {
                bodyEl.innerHTML = '<div class="text-center py-4 text-muted client-payment-modal__loading d-none">' +
                    (getConfig().loadingLabel || "Loading...") + "</div>";
            }
            setPaymentModalTitle(false);
            clearUrlMilestoneParam();

            if (typeof global.clientOutstandingInvoicesRestoreAfterPayment === "function") {
                global.clientOutstandingInvoicesRestoreAfterPayment();
            } else if (typeof global.clientOutstandingInvoicesClearRestore === "function") {
                global.clientOutstandingInvoicesClearRestore();
            }
        });

        var invoiceModal = document.getElementById("edit-milestone1");
        if (invoiceModal) {
            modalEl.addEventListener("show.bs.modal", function () {
                var invoiceModalInstance = global.bootstrap.Modal.getInstance(invoiceModal);
                if (invoiceModalInstance) {
                    invoiceModalInstance.hide();
                }
            });
            invoiceModal.addEventListener("show.bs.modal", function () {
                var paymentModalInstance = global.bootstrap.Modal.getInstance(modalEl);
                if (paymentModalInstance) {
                    paymentModalInstance.hide();
                }
                clearUrlMilestoneParam();
            });
        }
    }

    function autoOpenFromUrl() {
        var params = new URLSearchParams(window.location.search);
        var milestoneId = params.get("milestone_id");
        var projectId = params.get("projectId");
        var status = params.get("status");
        if (!milestoneId || !projectId || status === "success" || status === "fail") {
            return;
        }
        openClientPaymentModal(projectId, milestoneId);
    }

    global.openClientPaymentModal = openClientPaymentModal;
    global.openClientBulkPaymentModal = openClientBulkPaymentModal;

    document.addEventListener("DOMContentLoaded", function () {
        bindTriggers();
        bindModalEvents();
        autoOpenFromUrl();
    });
})(window);

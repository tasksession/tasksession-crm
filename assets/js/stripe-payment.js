/**
 * Stripe card + Apple Pay / Google Pay (Payment Request Button)
 */
(function (global) {
    "use strict";

    function setCardErrors(form, message) {
        var errorsEl = form.querySelector("#card-errors");
        if (errorsEl) {
            errorsEl.textContent = message || "";
        }
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
            submitButton.style.pointerEvents = "none";
            submitButton.style.opacity = "0.6";
            submitButton.style.cursor = "not-allowed";
        } else {
            submitButton.style.pointerEvents = "";
            submitButton.style.opacity = "";
            submitButton.style.cursor = "";
            submitButton.innerHTML = originalContent;
        }
    }

    function buildFormBody(form, extraFields) {
        var body = new URLSearchParams(new FormData(form));
        if (extraFields) {
            Object.keys(extraFields).forEach(function (key) {
                body.set(key, extraFields[key]);
            });
        }
        return body;
    }

    function initStripePayment(options) {
        options = options || {};
        var formSelector = options.formSelector || "#stripe-payment-form";
        var rootSelector = options.rootSelector || "";
        var form = null;
        if (rootSelector) {
            var root = document.querySelector(rootSelector);
            if (root) {
                form = root.querySelector(formSelector);
            }
        }
        if (!form) {
            form = document.querySelector(formSelector);
        }
        if (!form || !options.publishableKey) {
            return;
        }
        if (form.dataset.stripeInitialized === "1") {
            return;
        }
        form.dataset.stripeInitialized = "1";

        var cardMount = form.querySelector("#card-element");
        if (!cardMount || typeof global.Stripe === "undefined") {
            return;
        }

        var stripe = global.Stripe(options.publishableKey);
        var elements = stripe.elements();
        var card = elements.create("card");
        card.mount(cardMount);
        card.on("change", function (event) {
            setCardErrors(form, event.error ? event.error.message : "");
        });

        var submitButton = form.querySelector("#submit-payment");
        var savedSelect = form.querySelector("#saved-payment-method-select");
        var cardFieldsWrap = form.querySelector("#new-card-fields");
        var usePaymentMethod = !!options.usePaymentMethod;
        var savedMethods = options.savedMethods || [];

        function isUsingSavedCard() {
            return savedSelect && savedSelect.value && savedSelect.value !== "new";
        }

        if (savedSelect && savedMethods.length) {
            savedSelect.addEventListener("change", function () {
                var useNew = savedSelect.value === "new";
                if (cardFieldsWrap) {
                    cardFieldsWrap.style.display = useNew ? "" : "none";
                }
            });
            if (cardFieldsWrap && savedSelect.value !== "new") {
                cardFieldsWrap.style.display = "none";
            }
        }

        function isBulkForm(targetForm) {
            return !!targetForm.querySelector('input[name="bulk_pay"]');
        }

        function isModalSinglePayment(targetForm) {
            return !!targetForm.closest("#client-payment-modal-body") && !isBulkForm(targetForm);
        }

        function startBulkPayment(paymentPayload, buttonOriginalContent) {
            if (typeof global.runClientBulkPayment !== "function") {
                setSubmitLoading(submitButton, false, buttonOriginalContent);
                setCardErrors(form, "Bulk payment is not available.");
                return;
            }
            global.runClientBulkPayment(form, paymentPayload, submitButton, buttonOriginalContent);
        }

        function startSinglePayment(paymentPayload, buttonOriginalContent) {
            if (typeof global.runClientSinglePayment !== "function") {
                setSubmitLoading(submitButton, false, buttonOriginalContent);
                setCardErrors(form, "Payment is not available.");
                return;
            }
            global.runClientSinglePayment(form, paymentPayload, submitButton, buttonOriginalContent);
        }

        form.addEventListener("submit", function (e) {
            e.preventDefault();
            if (!submitButton) {
                return;
            }
            var originalContent = submitButton.innerHTML;
            setSubmitLoading(submitButton, true, originalContent);
            var bulkForm = isBulkForm(form);
            var modalSingle = isModalSinglePayment(form);

            if (isUsingSavedCard()) {
                var existingPm = form.querySelector("input[name=\"stripePaymentMethodId\"]");
                if (existingPm) {
                    existingPm.remove();
                }
                if (bulkForm) {
                    startBulkPayment({
                        stripePaymentMethodId: savedSelect.value,
                        use_saved: true,
                        save_card: false,
                    }, originalContent);
                    return;
                }
                if (modalSingle) {
                    startSinglePayment({
                        stripePaymentMethodId: savedSelect.value,
                        use_saved: true,
                        save_card: false,
                    }, originalContent);
                    return;
                }
                var pmInput = document.createElement("input");
                pmInput.type = "hidden";
                pmInput.name = "stripePaymentMethodId";
                pmInput.value = savedSelect.value;
                form.appendChild(pmInput);
                var useSavedInput = document.createElement("input");
                useSavedInput.type = "hidden";
                useSavedInput.name = "use_saved";
                useSavedInput.value = "1";
                form.appendChild(useSavedInput);
                form.submit();
                return;
            }

            if (usePaymentMethod) {
                stripe.createPaymentMethod({ type: "card", card: card }).then(function (result) {
                    if (result.error) {
                        setCardErrors(form, result.error.message);
                        setSubmitLoading(submitButton, false, originalContent);
                        return;
                    }
                    if (bulkForm) {
                        var saveCb = form.querySelector("#save-card-checkbox");
                        startBulkPayment({
                            stripePaymentMethodId: result.paymentMethod.id,
                            use_saved: false,
                            save_card: !!(saveCb && saveCb.checked),
                        }, originalContent);
                        return;
                    }
                    if (modalSingle) {
                        var saveCbSingle = form.querySelector("#save-card-checkbox");
                        startSinglePayment({
                            stripePaymentMethodId: result.paymentMethod.id,
                            use_saved: false,
                            save_card: !!(saveCbSingle && saveCbSingle.checked),
                        }, originalContent);
                        return;
                    }
                    var existingPm2 = form.querySelector("input[name=\"stripePaymentMethodId\"]");
                    if (existingPm2) {
                        existingPm2.remove();
                    }
                    var hiddenPm = document.createElement("input");
                    hiddenPm.type = "hidden";
                    hiddenPm.name = "stripePaymentMethodId";
                    hiddenPm.value = result.paymentMethod.id;
                    form.appendChild(hiddenPm);
                    var saveCb2 = form.querySelector("#save-card-checkbox");
                    if (saveCb2 && saveCb2.checked) {
                        var saveInput = document.createElement("input");
                        saveInput.type = "hidden";
                        saveInput.name = "save_card";
                        saveInput.value = "1";
                        form.appendChild(saveInput);
                    }
                    form.submit();
                });
                return;
            }

            stripe.createToken(card).then(function (result) {
                if (result.error) {
                    setCardErrors(form, result.error.message);
                    setSubmitLoading(submitButton, false, originalContent);
                } else {
                    var existing = form.querySelector("input[name=\"stripeToken\"]");
                    if (existing) {
                        existing.remove();
                    }
                    var hiddenInput = document.createElement("input");
                    hiddenInput.setAttribute("type", "hidden");
                    hiddenInput.setAttribute("name", "stripeToken");
                    hiddenInput.setAttribute("value", result.token.id);
                    form.appendChild(hiddenInput);
                    form.submit();
                }
            });
        });

        var amountCents = parseInt(options.amountCents, 10) || 0;
        var currency = (options.currency || "usd").toLowerCase();
        var country = (options.country || "US").toUpperCase();
        var companyName = options.companyName || "Total";

        var prContainer = form.querySelector("#payment-request-button");
        if (prContainer && amountCents > 0) {
            try {
            var paymentRequest = stripe.paymentRequest({
                country: country,
                currency: currency,
                total: {
                    label: companyName,
                    amount: amountCents
                },
                requestPayerName: true,
                requestPayerEmail: true
            });

            paymentRequest.canMakePayment().then(function (result) {
                if (typeof console !== "undefined" && console.info) {
                    console.info("[Stripe wallets] canMakePayment:", result);
                    if (result && result.googlePay === false) {
                        console.info(
                            "[Stripe wallets] Google Pay off — enable in Stripe Dashboard (Payment methods), use Chrome with a Google Pay card, disable ad blockers (manifest errors), redeploy if country was wrong for currency."
                        );
                    }
                }
                if (result && prContainer) {
                    var prButton = elements.create("paymentRequestButton", {
                        paymentRequest: paymentRequest,
                        style: {
                            paymentRequestButton: {
                                type: "default",
                                theme: "dark",
                                height: "44px"
                            }
                        }
                    });
                    prButton.mount(prContainer);
                    prContainer.classList.add("is-visible");
                } else if (prContainer) {
                    prContainer.style.display = "none";
                }
            });

            paymentRequest.on("paymentmethod", function (ev) {
                var body = buildFormBody(form, {
                    stripePaymentMethodId: ev.paymentMethod.id,
                    payment_type: "wallet"
                });

                fetch(form.action, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded",
                        "X-Requested-With": "XMLHttpRequest"
                    },
                    body: body.toString()
                })
                    .then(function (response) {
                        return response.json();
                    })
                    .then(function (data) {
                        if (data && data.success) {
                            ev.complete("success");
                            if (data.redirect) {
                                global.location.href = data.redirect;
                            }
                        } else {
                            ev.complete("fail");
                            setCardErrors(form, (data && data.message) ? data.message : "Payment failed.");
                        }
                    })
                    .catch(function () {
                        ev.complete("fail");
                        setCardErrors(form, "Payment failed. Please try again.");
                    });
            });
            } catch (walletErr) {
                prContainer.style.display = "none";
                if (typeof console !== "undefined" && console.warn) {
                    console.warn("[Stripe wallets] Could not initialize payment request (card checkout still works):", walletErr);
                }
            }
        }
    }

    global.initStripePayment = initStripePayment;
})(window);

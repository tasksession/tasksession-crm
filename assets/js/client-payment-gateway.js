(function (global) {
    'use strict';

    function initClientPaymentGateway(gatewayId, config) {
        if (!gatewayId || !config) {
            return;
        }
        if (gatewayId === 'stripe' && typeof global.initStripePayment === 'function') {
        global.initStripePayment({
            publishableKey: config.publishableKey,
            formSelector: config.formSelector || "#stripe-payment-form",
            rootSelector: "#client-payment-modal-body",
                amountCents: config.amountCents || 0,
                currency: config.currency || 'usd',
                country: config.country || 'US',
                companyName: config.companyName || 'Total',
                usePaymentMethod: true,
                savedMethods: config.savedMethods || []
            });
        }
    }

    function initClientPaymentGateways(gatewayConfigs) {
        if (!gatewayConfigs || typeof gatewayConfigs !== 'object') {
            return;
        }
        Object.keys(gatewayConfigs).forEach(function (gatewayId) {
            initClientPaymentGateway(gatewayId, gatewayConfigs[gatewayId]);
        });
    }

    global.initClientPaymentGateway = initClientPaymentGateway;
    global.initClientPaymentGateways = initClientPaymentGateways;
})(window);

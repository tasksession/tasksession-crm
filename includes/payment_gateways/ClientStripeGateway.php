<?php

require_once __DIR__ . '/ClientPaymentGatewayInterface.php';
require_once __DIR__ . '/../stripe_saved_payment.php';
require_once __DIR__ . '/../client-stripe-payment-success.php';

class ClientStripeGateway implements ClientPaymentGatewayInterface
{
    public function id(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Stripe';
    }

    public function logoUrl(): string
    {
        global $url;

        return rtrim((string) ($url ?? ''), '/') . '/assets/images/stripe.svg';
    }

    public function isConfigured(): bool
    {
        $credentials = client_payment_gateway_credentials();

        return !empty($credentials['stripe_sk']) && !empty($credentials['stripe_pk']);
    }

    public function supportsBulkPay(): bool
    {
        return true;
    }

    public function supportsSavedCards(): bool
    {
        return true;
    }

    public function renderPaymentSection(array $context): string
    {
        if (!$this->isConfigured()) {
            return '';
        }

        $gatewayContext = array_merge($context, [
            'gatewayId' => $this->id(),
            'isBulk' => !empty($context['isBulk']),
        ]);

        ob_start();
        include __DIR__ . '/../../templates/partials/gateways/client-stripe-payment-form.php';

        return (string) ob_get_clean();
    }

    public function clientScriptConfig(array $context): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $credentials = client_payment_gateway_credentials();
        global $company_name;

        $clientId = (int) ($context['clientId'] ?? $context['id'] ?? 0);
        $savedForJs = [];
        foreach (stripe_list_payment_methods_for_user($clientId) as $pmJs) {
            $savedForJs[] = [
                'id' => $pmJs['stripe_payment_method_id'],
                'label' => ucfirst($pmJs['brand'] ?: 'Card') . ' •••• ' . $pmJs['last4'],
            ];
        }

        $amountCents = 0;
        $currency = 'usd';
        $country = 'US';

        if (empty($context['isBulk']) && !empty($context['latestMile'])) {
            require_once __DIR__ . '/../stripe_wallet_payment.php';
            require_once __DIR__ . '/../client_payment_modal_helper.php';

            $mile = $context['latestMile'];
            $totalAmount = calculateInvoiceTotal(client_payment_row_from_milestone($mile));
            $currency = stripe_currency_from_invoice_field($mile->currency);
            $amountCents = (int) stripe_amount_to_cents($totalAmount, $currency);
            $country = stripe_payment_request_country($currency);
        }

        return [
            'enabled' => true,
            'publishableKey' => $credentials['stripe_pk'],
            'amountCents' => $amountCents,
            'currency' => $currency,
            'country' => $country,
            'companyName' => $company_name ?? 'Total',
            'savedMethods' => $savedForJs,
            'formSelector' => '#stripe-payment-form',
            'skipWallet' => !empty($context['isBulk']),
        ];
    }

    public function chargeInvoice($mile, int $clientId, array $paymentPayload, $adminSettings): array
    {
        $paymentMethodId = (string) ($paymentPayload['stripePaymentMethodId'] ?? '');
        if ($paymentMethodId === '') {
            return ['success' => false, 'error' => 'Payment method is required.'];
        }

        $useSaved = !empty($paymentPayload['use_saved']);
        $saveCard = !empty($paymentPayload['save_card']);

        return stripe_client_charge_milestone($mile, $clientId, $paymentMethodId, $adminSettings, $saveCard, $useSaved);
    }

    public function chargeBulkInvoices(array $invoices, int $clientId, array $paymentPayload, $adminSettings, string $url): array
    {
        $paidCount = 0;
        $failedCount = 0;
        $lastError = '';

        foreach ($invoices as $mile) {
            if (!$mile) {
                $failedCount++;
                $lastError = 'Invoice not found.';
                break;
            }

            $chargeResult = $this->chargeInvoice($mile, $clientId, $paymentPayload, $adminSettings);
            if (empty($chargeResult['success'])) {
                $failedCount++;
                $lastError = $chargeResult['error'] ?? 'Payment failed.';
                break;
            }

            $projectId = (int) ($mile->p_id ?? 0);
            $milestoneId = (int) ($mile->id ?? 0);
            client_finalize_stripe_payment($mile, $projectId, $milestoneId, $clientId, $adminSettings, $url);
            $paidCount++;
        }

        $total = count($invoices);
        $partial = $paidCount > 0 && ($failedCount > 0 || $paidCount < $total);

        return [
            'success' => $failedCount === 0 && $paidCount > 0,
            'paid_count' => $paidCount,
            'failed_count' => $failedCount,
            'partial' => $partial,
            'error' => $lastError,
        ];
    }
}

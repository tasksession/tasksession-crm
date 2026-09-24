<?php

require_once __DIR__ . '/ClientPaymentGatewayInterface.php';

/**
 * Authorize.net gateway stub — implement when admin settings are added.
 */
class ClientAuthorizeNetGateway implements ClientPaymentGatewayInterface
{
    public function id(): string
    {
        return 'authorize_net';
    }

    public function label(): string
    {
        return 'Authorize.net';
    }

    public function logoUrl(): string
    {
        global $url;

        return rtrim((string) ($url ?? ''), '/') . '/assets/images/gatways.png';
    }

    public function isConfigured(): bool
    {
        $credentials = function_exists('client_payment_gateway_credentials')
            ? client_payment_gateway_credentials()
            : [];

        return !empty($credentials['authorize_api_login']) && !empty($credentials['authorize_transaction_key']);
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
        return '';
    }

    public function clientScriptConfig(array $context): ?array
    {
        return null;
    }

    public function chargeInvoice($mile, int $clientId, array $paymentPayload, $adminSettings): array
    {
        return ['success' => false, 'error' => 'Authorize.net is not configured.'];
    }

    public function chargeBulkInvoices(array $invoices, int $clientId, array $paymentPayload, $adminSettings, string $url): array
    {
        return [
            'success' => false,
            'paid_count' => 0,
            'failed_count' => count($invoices),
            'partial' => false,
            'error' => 'Authorize.net is not configured.',
        ];
    }
}

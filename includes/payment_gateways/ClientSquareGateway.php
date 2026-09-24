<?php

require_once __DIR__ . '/ClientPaymentGatewayInterface.php';

/**
 * Square gateway stub — implement when admin settings are added.
 */
class ClientSquareGateway implements ClientPaymentGatewayInterface
{
    public function id(): string
    {
        return 'square';
    }

    public function label(): string
    {
        return 'Square';
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

        return !empty($credentials['square_access_token']) && !empty($credentials['square_location_id']);
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
        return ['success' => false, 'error' => 'Square is not configured.'];
    }

    public function chargeBulkInvoices(array $invoices, int $clientId, array $paymentPayload, $adminSettings, string $url): array
    {
        return [
            'success' => false,
            'paid_count' => 0,
            'failed_count' => count($invoices),
            'partial' => false,
            'error' => 'Square is not configured.',
        ];
    }
}

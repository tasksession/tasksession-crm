<?php

interface ClientPaymentGatewayInterface
{
    public function id(): string;

    public function label(): string;

    public function logoUrl(): string;

    public function isConfigured(): bool;

    public function supportsBulkPay(): bool;

    public function supportsSavedCards(): bool;

    /**
     * @param array<string,mixed> $context
     */
    public function renderPaymentSection(array $context): string;

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>|null
     */
    public function clientScriptConfig(array $context): ?array;

    /**
     * @param object $mile
     * @param array<string,mixed> $paymentPayload
     * @return array{success:bool,error?:string}
     */
    public function chargeInvoice($mile, int $clientId, array $paymentPayload, $adminSettings): array;

    /**
     * @param array<int,object> $invoices
     * @param array<string,mixed> $paymentPayload
     * @return array{success:bool,paid_count:int,failed_count:int,partial:bool,error?:string}
     */
    public function chargeBulkInvoices(array $invoices, int $clientId, array $paymentPayload, $adminSettings, string $url): array;
}

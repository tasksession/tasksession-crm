<?php
/**
 * Shared client invoice view modal helpers.
 */

require_once __DIR__ . '/client_outstanding_invoices_helper.php';

if (!function_exists('invoice_view_modal_context')) {
    /**
     * Build invoice modal context (no ownership check).
     *
     * @return array<string,mixed>|null
     */
    function invoice_view_modal_context(int $invoiceId): ?array
    {
        if ($invoiceId <= 0) {
            return null;
        }

        $mile = milestone::findByMilestoneId($invoiceId);
        if (!$mile) {
            return null;
        }

        $proj = null;
        $client = null;

        if (!empty($mile->p_id)) {
            $proj = projects::findByProjectId($mile->p_id);
            if ($proj && isset($proj->c_id)) {
                $client = user::findById($proj->c_id);
            }
        }

        if (!$client && !empty($mile->c_id)) {
            $client = user::findById($mile->c_id);
        }

        $adminUser = user::findById(1);
        if (!$adminUser) {
            return null;
        }

        if (!function_exists('getCurrencySymbol')) {
            require_once __DIR__ . '/invoice_display_helpers.php';
        }

        return [
            'invoiceId' => $invoiceId,
            'latestMile1' => $mile,
            'latestProj1' => $proj,
            'latestUser1' => $client,
            'adminUser1' => $adminUser,
            'currency_symbol' => getCurrencySymbol($mile->currency),
        ];
    }
}

if (!function_exists('client_invoice_view_context')) {
    /**
     * @return array<string,mixed>|null
     */
    function client_invoice_view_context(int $clientId, int $invoiceId): ?array
    {
        if ($invoiceId <= 0 || !client_invoice_belongs_to_client($invoiceId, $clientId)) {
            return null;
        }

        return invoice_view_modal_context($invoiceId);
    }
}

if (!function_exists('client_invoice_view_render_content')) {
    function client_invoice_view_render_content(array $context): string
    {
        global $lang, $url, $company_name;

        $edit_id1 = (int) ($context['invoiceId'] ?? 0);
        $latestMile1 = $context['latestMile1'] ?? null;
        $latestProj1 = $context['latestProj1'] ?? null;
        $latestUser1 = $context['latestUser1'] ?? null;
        $adminUser1 = $context['adminUser1'] ?? null;
        $currency_symbol = $context['currency_symbol'] ?? '$';

        ob_start();
        include __DIR__ . '/../templates/partials/client-invoice-view-modal-content.php';

        return (string) ob_get_clean();
    }
}

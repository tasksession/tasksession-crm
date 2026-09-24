<?php
/**
 * Shared invoice display helpers for list/table views.
 */

if (!function_exists('getCurrencySymbol')) {
    function getCurrencySymbol($currencyCode)
    {
        if (empty($currencyCode)) {
            return '$';
        }

        if (strlen((string) $currencyCode) <= 3) {
            return (string) $currencyCode;
        }

        $parts = explode(',', (string) $currencyCode);
        if (count($parts) >= 2) {
            $symbol = trim($parts[1]);
            return str_replace([':', ' '], '', $symbol);
        }

        return '$';
    }
}

if (!function_exists('calculateInvoiceTotal')) {
    function calculateInvoiceTotal($row)
    {
        require_once __DIR__ . '/invoice_item.php';
        $subtotal = 0;
        $invoiceItems = InvoiceItem::findByMilestoneId($row['id']);
        if ($invoiceItems && count($invoiceItems) > 0) {
            foreach ($invoiceItems as $item) {
                $subtotal += floatval($item->rate) * floatval($item->quantity);
            }
        } else {
            $subtotal = floatval($row['budget'] ?? 0);
        }

        $discount = isset($row['discount']) ? floatval($row['discount']) : 0;
        $discount_type = isset($row['discount_type']) ? $row['discount_type'] : 'percentage';

        $discount_amount = 0;
        if ($discount > 0 && $subtotal > 0) {
            if ($discount_type === 'percentage') {
                $discount_amount = ($subtotal * $discount) / 100;
            } else {
                $discount_amount = $discount;
            }
        }

        $amount_after_discount = $subtotal - $discount_amount;

        $sales_tax = isset($row['sales_tax']) ? floatval($row['sales_tax']) : 0;
        $sales_tax_type = isset($row['sales_tax_type']) ? $row['sales_tax_type'] : 'percentage';

        $tax_amount = 0;
        if ($sales_tax > 0 && $amount_after_discount > 0) {
            if ($sales_tax_type === 'percentage') {
                $tax_amount = ($amount_after_discount * $sales_tax) / 100;
            } else {
                $tax_amount = $sales_tax;
            }
        }

        return $amount_after_discount + $tax_amount;
    }
}

if (!function_exists('getRecurringFrequencyLabel')) {
    function getRecurringFrequencyLabel($frequency, $lang)
    {
        if (empty($frequency)) {
            return '';
        }

        $frequencyMap = [
            'daily' => isset($lang['Daily']) ? $lang['Daily'] : 'Daily',
            'monthly' => isset($lang['Monthly']) ? $lang['Monthly'] : 'Monthly',
            '6month' => isset($lang['6 months']) ? $lang['6 months'] : '6 months',
            '12month' => isset($lang['12 months']) ? $lang['12 months'] : '12 months',
        ];

        return isset($frequencyMap[$frequency]) ? $frequencyMap[$frequency] : ucfirst((string) $frequency);
    }
}

if (!function_exists('invoice_list_display_title')) {
    /**
     * Invoice title for list/grid rows, e.g. "#1474 - Invoice (Copy)".
     * Number matches Invoice No on the invoice document (p_id + id).
     *
     * @param array|object $row Row with id, title, and optional p_id
     */
    function invoice_list_display_title($row): string
    {
        $id = 0;
        $pId = '';
        $title = '';
        if (is_array($row)) {
            $id = isset($row['id']) ? (int) $row['id'] : 0;
            $pId = isset($row['p_id']) ? (string) $row['p_id'] : '';
            $title = isset($row['title']) ? trim((string) $row['title']) : '';
        } elseif (is_object($row)) {
            $id = isset($row->id) ? (int) $row->id : 0;
            $pId = isset($row->p_id) ? (string) $row->p_id : '';
            $title = isset($row->title) ? trim((string) $row->title) : '';
        }
        if ($id <= 0) {
            return $title;
        }
        // Same format as templates/invoice-modal.php Invoice No
        $invoiceNo = $pId . $id;
        if ($title === '') {
            return '#' . $invoiceNo;
        }

        return '#' . $invoiceNo . ' - ' . $title;
    }
}

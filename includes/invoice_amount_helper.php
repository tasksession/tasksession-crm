<?php
/**
 * Invoice totals: calculated from line items vs frozen paid snapshot.
 */
require_once __DIR__ . '/invoice_item.php';

function invoice_calculate_total_from_row(array $row): float
{
    $milestoneId = isset($row['id']) ? (int) $row['id'] : 0;
    $subtotal = 0;

    if ($milestoneId > 0) {
        $invoiceItems = InvoiceItem::findByMilestoneId($milestoneId);
        if ($invoiceItems && count($invoiceItems) > 0) {
            foreach ($invoiceItems as $item) {
                $subtotal += floatval($item->rate) * floatval($item->quantity);
            }
        } else {
            $subtotal = floatval($row['budget'] ?? 0);
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

    return max(0, $amount_after_discount + $tax_amount);
}

/**
 * Amount to show in lists / subscription totals for a paid invoice.
 */
function invoice_collected_display_total(array $row): float
{
    if ((int) ($row['status'] ?? 0) === 1
        && isset($row['paid_total'])
        && $row['paid_total'] !== ''
        && $row['paid_total'] !== null
    ) {
        return max(0, floatval($row['paid_total']));
    }

    return invoice_calculate_total_from_row($row);
}

/**
 * Persist collected amount once when invoice becomes paid (do not overwrite).
 */
function invoice_freeze_paid_total_on_milestone($milestone): void
{
    if (!$milestone || (int) ($milestone->status ?? 0) !== 1) {
        return;
    }
    if ($milestone->paid_total !== null && $milestone->paid_total !== '' && (float) $milestone->paid_total > 0) {
        return;
    }
    $row = [
        'id' => $milestone->id,
        'budget' => $milestone->budget,
        'discount' => $milestone->discount,
        'discount_type' => $milestone->discount_type,
        'sales_tax' => $milestone->sales_tax,
        'sales_tax_type' => $milestone->sales_tax_type,
    ];
    $milestone->paid_total = invoice_calculate_total_from_row($row);
}

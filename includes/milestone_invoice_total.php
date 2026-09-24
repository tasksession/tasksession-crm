<?php
/**
 * Net invoice amount per milestone: line items (or budget) − discount + tax.
 * Matches admin/staff payments.php calculateInvoiceTotal().
 *
 * @param object $milestone
 * @param list<object>|false|null $preloadedItems Optional batch-loaded invoice_items for this milestone
 */
function milestone_calculate_invoice_total($milestone, $preloadedItems = null) {
    require_once __DIR__ . '/invoice_item.php';
    $subtotal = 0;
    if ($preloadedItems !== null) {
        $invoiceItems = (is_array($preloadedItems) && count($preloadedItems) > 0) ? $preloadedItems : false;
    } else {
        $invoiceItems = InvoiceItem::findByMilestoneId($milestone->id);
    }
    if ($invoiceItems && count($invoiceItems) > 0) {
        foreach ($invoiceItems as $item) {
            $subtotal += floatval($item->rate) * floatval($item->quantity);
        }
    } else {
        $subtotal = floatval($milestone->budget ?? 0);
    }

    $discount = isset($milestone->discount) ? floatval($milestone->discount) : 0;
    $discount_type = isset($milestone->discount_type) ? $milestone->discount_type : 'percentage';

    $discount_amount = 0;
    if ($discount > 0 && $subtotal > 0) {
        if ($discount_type === 'percentage') {
            $discount_amount = ($subtotal * $discount) / 100;
        } else {
            $discount_amount = $discount;
        }
    }

    $amount_after_discount = $subtotal - $discount_amount;

    $sales_tax = isset($milestone->sales_tax) ? floatval($milestone->sales_tax) : 0;
    $sales_tax_type = isset($milestone->sales_tax_type) ? $milestone->sales_tax_type : 'percentage';

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

<?php
/**
 * Duplicate an invoice (milestone) and its line items as a new unpaid invoice.
 *
 * @return array{success:bool,id?:int,message?:string}
 */
function comon_duplicate_invoice($sourceId, $createdByUserId)
{
    require_once __DIR__ . '/invoice_item.php';

    $sourceId = (int) $sourceId;
    $source = milestone::findById($sourceId);
    if (!$source) {
        return ['success' => false, 'message' => 'Invoice not found.'];
    }

    $copy = new milestone();
    $copy->p_id = $source->p_id;
    $copy->c_id = $source->c_id;
    $copy->company_id = $source->company_id ?? null;
    $copy->company_billing_snapshot = $source->company_billing_snapshot ?? null;
    $copy->title = trim((string) $source->title);
    if ($copy->title !== '' && stripos($copy->title, '(copy)') === false) {
        $copy->title .= ' (Copy)';
    } elseif ($copy->title === '') {
        $copy->title = 'Invoice (Copy)';
    }
    $copy->deadline = $source->deadline;
    $copy->budget = $source->budget;
    $copy->bill_from = $source->bill_from ?? '';
    $copy->bill_from_address = $source->bill_from_address ?? '';
    $copy->currency = $source->currency ?? '';
    $copy->sales_tax = $source->sales_tax ?? 0;
    $copy->sales_tax_type = $source->sales_tax_type ?? 'percentage';
    $copy->discount = $source->discount ?? 0;
    $copy->discount_type = $source->discount_type ?? 'percentage';
    $copy->memo = $source->memo ?? '';
    $copy->footer = $source->footer ?? '';

    $today = date('Y-m-d');
    $copy->issue_date = $today;
    $copy->releaseDate = $today;
    $copy->status = 0;
    $copy->created_by = (int) $createdByUserId;

    $copy->is_recurring = 0;
    $copy->recurring_frequency = null;
    $copy->recurring_parent_id = null;
    $copy->recurring_next_date = null;
    $copy->recurring_stopped = 0;
    $copy->recurring_end_date = null;
    $copy->recurring_next_renewal_date = null;
    $copy->recurring_billing_cycle_days = null;
    $copy->recurring_paused = 0;
    $copy->recurring_auto_charge = 0;

    $saveResult = $copy->save();
    if (!is_numeric($saveResult) && empty($copy->id)) {
        return ['success' => false, 'message' => 'Could not save duplicated invoice.'];
    }

    $newId = (int) $copy->id;
    $items = InvoiceItem::findByMilestoneId($sourceId);
    if ($items && is_array($items)) {
        $sort = 0;
        foreach ($items as $item) {
            $row = new InvoiceItem();
            $row->milestone_id = $newId;
            $row->description = $item->description ?? '';
            $row->item_description = $item->item_description ?? '';
            $row->rate = $item->rate ?? 0;
            $row->quantity = $item->quantity ?? 1;
            $row->sort_order = isset($item->sort_order) ? (int) $item->sort_order : $sort;
            $row->save();
            $sort++;
        }
    }

    if (class_exists('NotificationHelper')) {
        require_once __DIR__ . '/notification_helper.php';
        NotificationHelper::invoiceCreated($newId, $copy->title, (int) $createdByUserId, $copy->p_id ? (int) $copy->p_id : null);
    }

    return ['success' => true, 'id' => $newId];
}

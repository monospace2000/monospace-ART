<?php
/**
 * The invoice of a commission covers the WHOLE job (owner, 2026-11-23): the artwork at its full price, shipping,
 * the total, and what was paid (deposit and final payment) with the balance due. It is the invoice PDF of the BALANCE
 * order (the one that ships), built with the PDF Invoices & Packing Slips filters - no extra plugin, nothing saved.
 * Ordinary orders and the deposit order's own invoice are untouched.
 */
if (!defined('ABSPATH')) exit;

/** The pieces of the whole-job invoice for a balance order, or null for any other order */
function ms_commission_whole_invoice($order) {
    if (!function_exists('ms_commission_label_is_balance') || !ms_commission_label_is_balance($order)) return null;
    $original = ms_commission_original_order($order);
    if (!$original) return null;
    $deposit_item = null;
    foreach ($original->get_items('line_item') as $it) { $deposit_item = $it; break; }
    if (!$deposit_item) return null;
    $full = (float) ms_get_commission_meta($deposit_item, 'full_price');
    if ($full <= 0) return null;
    return ['original' => $original, 'item' => $deposit_item, 'full' => $full];
}

function ms_commission_plain_price($amount) {
    return html_entity_decode(wp_strip_all_tags(wc_price($amount)), ENT_QUOTES, 'UTF-8');
}

// the product line: one row, the artwork at its full price
add_filter('wpo_wcpdf_order_items_data', function ($list, $order, $type) {
    if ($type !== 'invoice') return $list;
    $w = ms_commission_whole_invoice($order);
    if (!$w) return $list;
    $meta = '';
    foreach (['medium' => 'Medium', 'size' => 'Size', 'surface' => 'Surface'] as $k => $label) {
        $v = ms_get_commission_meta($w['item'], $k);
        if ($v) $meta .= '<li><strong class="wc-item-meta-label">' . esc_html($label) . ':</strong> ' . esc_html($v) . '</li>';
    }
    return ['commission' => [
        'row_class'   => 'commission',
        'name'        => preg_replace('/^\s*Deposit \(\d+%\)\s*[—–-]\s*/u', '', $w['item']->get_name()),
        'quantity'    => 1,
        'order_price' => ms_commission_plain_price($w['full']),
        'price'       => ms_commission_plain_price($w['full']),
        'sku'         => '',
        'weight'      => '',
        'meta'        => $meta ? '<ul class="wc-item-meta">' . $meta . '</ul>' : '',
    ]];
}, 20, 3);

// the totals: whole job, what was paid, balance due
add_filter('wpo_wcpdf_woocommerce_totals', function ($totals, $order, $type) {
    if ($type !== 'invoice') return $totals;
    $w = ms_commission_whole_invoice($order);
    if (!$w) return $totals;

    $orig = $w['original'];
    $job  = (float) $orig->get_total() + (float) $order->get_total(); // deposit order + final order (incl. shipping and tax)
    $out  = [];
    $out['cart_subtotal'] = ['label' => 'Subtotal', 'value' => ms_commission_plain_price($w['full'])];
    if ($order->get_shipping_total() > 0) $out['shipping'] = ['label' => 'Shipping', 'value' => ms_commission_plain_price((float) $order->get_shipping_total() + (float) $order->get_shipping_tax())];
    foreach ($totals as $k => $t) if (strpos($k, 'tax') !== false) $out[$k] = $t; // any tax lines of the final order
    $out['order_total'] = ['label' => 'Total', 'value' => ms_commission_plain_price($job)];

    $paid = 0;
    $rows = [['deposit', 'Deposit', $orig], ['final', 'Final payment', $order]];
    foreach ($rows as $r) {
        [$key, $label, $o] = $r;
        if (!$o->get_date_paid()) continue;
        $paid += (float) $o->get_total();
        $out['paid_' . $key] = ['label' => 'Paid ' . $o->get_date_paid()->date_i18n('M j') . ': ' . strtolower($label), 'value' => '−' . ms_commission_plain_price($o->get_total())];
    }
    $out['balance_due'] = ['label' => 'Balance due', 'value' => ms_commission_plain_price(max(0, $job - $paid))];
    return $out;
}, 20, 3);

// a tidier summary: paid lines in a quiet gray under the total, balance due set off by a rule
add_action('wpo_wcpdf_custom_styles', function ($type = '', $document = null) {
    if ($type && $type !== 'invoice') return;
    echo 'table.totals tr.paid_deposit th, table.totals tr.paid_deposit td, table.totals tr.paid_final th, table.totals tr.paid_final td { color: #555; font-size: 9pt; }'
       . 'table.totals tr.balance_due th, table.totals tr.balance_due td { border-top: 1px solid #000; font-weight: bold; }'
       . 'table.totals th.description { white-space: normal; }';
}, 20, 2);

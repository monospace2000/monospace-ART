<?php
/**
 * The Orders list for commissions (owner, 2026-11-23): a job's deposit order and balance order read as one pair.
 *  - the Commission column tags each row ("Deposit" / "Balance") and links to its partner order,
 *  - the balance order borrows the artwork name and the stage chip from its deposit order (it only holds a fee line),
 *  - Tracking and Paid columns, and a blue edge on both rows of a pair,
 *  - when the balance order is Completed (Mark shipped) the deposit order is completed too, with a note.
 */
if (!defined('ABSPATH')) exit;

function ms_commission_list_edit_link($id) {
    $o = wc_get_order($id);
    return $o ? '<a href="' . esc_url($o->get_edit_order_url()) . '">#' . (int) $id . '</a>' : '';
}

/** The balance order of a deposit order, or null */
function ms_commission_list_balance_of($deposit) {
    foreach (ms_commission_order_items($deposit) as $item) {
        $id = ms_get_commission_meta($item, 'final_payment_order_id');
        if ($id && ($o = wc_get_order($id))) return $o;
    }
    return null;
}

/** Part tag + link to the partner, printed under a deposit row's stage chip */
function ms_commission_list_deposit_tag($deposit) {
    $b = ms_commission_list_balance_of($deposit);
    echo '<br><small style="color:#646970"><strong>Deposit</strong>' . ($b ? ' &middot; balance ' . ms_commission_list_edit_link($b->get_id()) : ' &middot; no balance order yet') . '</small>';
}

/** The Commission cell of a balance order; false when the order is not one */
function ms_commission_list_balance_cell($order) {
    if (!ms_commission_label_is_balance($order)) return false;
    $orig = ms_commission_original_order($order);
    if (!$orig) return false;
    echo '<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;border:1px solid #c3c4c7;color:#1d2327">' . esc_html(ms_commission_stages()[ms_commission_stage($orig)] ?? ms_commission_stage($orig)) . '</span>';
    echo '<br><small style="color:#646970"><strong>Balance</strong> &middot; deposit ' . ms_commission_list_edit_link($orig->get_id()) . '</small>';
    return true;
}

// the Product column of a balance order: the artwork it ships
add_filter('monospace_order_list_empty_product', function ($html, $order) {
    if (!function_exists('ms_commission_label_is_balance') || !ms_commission_label_is_balance($order)) return $html;
    $orig = ms_commission_original_order($order);
    if (!$orig) return $html;
    foreach ($orig->get_items('line_item') as $it) {
        return esc_html(preg_replace('/^\s*Deposit \(\d+%\)\s*[—–-]\s*/u', '', html_entity_decode($it->get_name())));
    }
    return $html;
}, 10, 2);

// Tracking + Paid columns
add_filter('manage_woocommerce_page_wc-orders_columns', function ($columns) {
    $out = [];
    foreach ($columns as $key => $label) {
        $out[$key] = $label;
        if ($key === 'ms_commission') { $out['ms_tracking'] = 'Tracking'; $out['ms_paid'] = 'Paid'; }
    }
    if (!isset($out['ms_tracking'])) { $out['ms_tracking'] = 'Tracking'; $out['ms_paid'] = 'Paid'; }
    return $out;
}, 30);

add_action('manage_woocommerce_page_wc-orders_custom_column', function ($column, $order) {
    if ($column === 'ms_paid') {
        $d = $order->get_date_paid();
        echo $d ? esc_html($d->date_i18n('M j')) : '<span style="color:#646970">&ndash;</span>';
    } elseif ($column === 'ms_tracking') {
        $items = $order->get_meta('_wc_shipment_tracking_items');
        $n = is_array($items) && $items ? reset($items) : null;
        if (!$n || empty($n['tracking_number'])) { echo '<span style="color:#646970">&ndash;</span>'; return; }
        $num = $n['tracking_number'];
        $url = !empty($n['custom_tracking_link']) ? $n['custom_tracking_link'] : '';
        echo $url ? '<a href="' . esc_url($url) . '" target="_blank" rel="noopener" style="font-family:monospace;font-size:12px">' . esc_html(substr($num, -8)) . '</a>' : '<span style="font-family:monospace;font-size:12px">' . esc_html(substr($num, -8)) . '</span>';
        echo '<br><small style="color:#646970">' . esc_html(trim(($n['custom_tracking_provider'] ?? '') ?: ($n['tracking_provider'] ?? ''))) . '</small>';
    }
}, 10, 2);

// both rows of a pair get a blue edge
add_filter('woocommerce_shop_order_list_table_order_css_classes', function ($classes, $order) {
    if (ms_commission_label_is_balance($order) || ms_commission_order_items($order)) $classes[] = 'ms-commission-pair';
    return $classes;
}, 10, 2);
add_action('admin_head', function () {
    $s = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$s || strpos($s->id, 'wc-orders') === false) return;
    echo '<style>.wp-list-table tr.ms-commission-pair > td:first-child, .wp-list-table tr.ms-commission-pair > th:first-child { box-shadow: inset 3px 0 0 #2271b1; }
.wp-list-table td.column-ms_tracking, .wp-list-table td.column-ms_paid { white-space: nowrap; }</style>';
});

// a finished balance order finishes the deposit order (no email for it: the customer got the shipped email already)
add_action('woocommerce_order_status_completed', function ($order_id, $order = false) {
    $order = $order ?: wc_get_order($order_id);
    if (!$order || !ms_commission_label_is_balance($order)) return;
    $dep = ms_commission_original_order($order);
    if ($dep && !$dep->has_status(['completed', 'cancelled', 'refunded'])) {
        $dep->update_status('completed', sprintf('Balance order #%d was shipped, so the deposit order is completed too.', $order->get_id()));
    }
}, 30, 2);
add_filter('woocommerce_email_enabled_customer_completed_order', function ($enabled, $order) {
    return ($order instanceof WC_Order && function_exists('ms_commission_order_items') && ms_commission_order_items($order)) ? false : $enabled;
}, 11, 2);

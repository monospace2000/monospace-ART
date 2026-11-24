<?php
/**
 * Shipping labels for commissions are bought on the BALANCE (final payment) order, not on the deposit order
 * (owner, 2026-11-23).
 *
 * WooCommerce Shipping only shows its Shipping Label box on an order that holds a shippable product. The deposit order
 * has the product; the balance order (model B) only has a fee line, so the box never appeared there. This module
 *  1. removes the Shipping Label box from commission deposit orders (unless a label was already bought there), and
 *  2. lends the balance order the artwork line of its deposit order, in memory only and only while WooCommerce
 *     Shipping's own code, or the PDF plugin's packing slip, asks for the items (nothing is saved, emails/invoices/totals/the Items table never see it).
 */
if (!defined('ABSPATH')) exit;

/** The Shipping Label box id used by WooCommerce Shipping */
const MS_COMMISSION_LABEL_BOX = 'woocommerce-order-label';

/** Is this a commission balance order? */
function ms_commission_label_is_balance($order) {
    return $order instanceof WC_Order && function_exists('ms_commission_final_items') && (bool) ms_commission_final_items($order);
}

// 1. no label box on the deposit order
function ms_commission_label_hide_on_deposit($order_or_post) {
    $order = $order_or_post instanceof WC_Order ? $order_or_post : wc_get_order($order_or_post);
    if (!$order || ms_commission_label_is_balance($order)) return;
    if (!function_exists('ms_commission_order_items') || !ms_commission_order_items($order)) return; // not a commission deposit
    if ($order->get_meta('wcshipping_labels', true)) return; // a label was bought here already: keep showing it
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen) remove_meta_box(MS_COMMISSION_LABEL_BOX, $screen->id, 'normal');
}
add_action('add_meta_boxes_woocommerce_page_wc-orders', 'ms_commission_label_hide_on_deposit', 10000);
add_action('add_meta_boxes_shop_order', 'ms_commission_label_hide_on_deposit', 10000);

// 2. the balance order borrows the artwork line, but only for WooCommerce Shipping
add_filter('woocommerce_order_get_items', function ($items, $order, $types) {
    static $busy = false;
    if ($busy) return $items; // admin screens, REST and the PDF download all run through here, the backtrace check below decides
    if (!in_array('line_item', (array) $types, true)) return $items;
    foreach ($items as $item) if ($item instanceof WC_Order_Item_Product) return $items; // it has its own product already

    $busy = true;
    $final = ms_commission_label_is_balance($order) ? ms_commission_final_items($order) : [];
    $busy = false;
    if (!$final) return $items;

    // only when WooCommerce Shipping is the caller, or the PDF plugin while it builds a PACKING SLIP (not the invoice)
    $from_shipping = false; $for_label = false;
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT, 25) as $f) {
        $file = !empty($f['file']) ? str_replace('\\', '/', $f['file']) : '';
        if (strpos($file, '/plugins/woocommerce-shipping/') !== false) { $from_shipping = true; $for_label = true; break; }
        if (strpos($file, '/plugins/woocommerce-pdf-invoices-packing-slips/') !== false
            && !empty($f['object']) && is_callable([$f['object'], 'get_type']) && $f['object']->get_type() === 'packing-slip') { $from_shipping = true; break; }
    }
    if (!$from_shipping) return $items;

    $original = ms_commission_original_order($order);
    if (!$original) return $items;
    $busy = true;
    $lent = $original->get_items('line_item');
    $busy = false;
    foreach ($lent as $id => $item) {
        if (!($item instanceof WC_Order_Item_Product) || !$item->get_product()) continue;
        if ($for_label) { // a copy for the label screen: not "Deposit (50%) —", and no "&" (that screen prints it as &amp;)
            $item = clone $item;
            ms_commission_label_item_facts($item, $original);
            $item->set_name(str_replace(' & ', ' and ', preg_replace('/^\s*Deposit \(\d+%\)\s*[—–-]\s*/u', '', html_entity_decode($item->get_name()))));
        }
        $items[$id] = $item;
    }
    return $items;
}, 10, 3);

// On the packing slip the borrowed line reads "Custom Order: Line & Wash", not "Deposit (50%) — ..." (it ships, it is not a deposit)
add_filter('wpo_wcpdf_order_item_data', function ($data, $order, $type) {
    if ($type === 'packing-slip' && !empty($data['name']) && ms_commission_label_is_balance($order)) {
        $data['name'] = preg_replace('/^\s*Deposit \(\d+%\)\s*[—–-]\s*/u', '', $data['name']);
    }
    return $data;
}, 10, 3);

// A balance order has only a fee line, so WooCommerce thought it needed no processing and completed it the moment it was
// paid, which sent the customer "on its way" with no tracking (2026-11-23). It stays Processing until "Mark shipped".
add_filter('woocommerce_payment_complete_order_status', function ($status, $order_id, $order) {
    return ($status === 'completed' && ms_commission_label_is_balance($order)) ? 'processing' : $status;
}, 20, 3);

/**
 * What the label screen should know about the borrowed artwork line (owner, 2026-11-23): its value is the artwork's FULL price
 * (not the deposit), and a default weight by surface - paper 0.2 lb, canvas panel 0.6 lb; other formats are filled in by hand.
 * Applied to a copy of the line, in memory only.
 */
function ms_commission_label_item_facts($item, $original) {
    $full = (float) ms_get_commission_meta($item, 'full_price');
    if ($full > 0) {
        $item->set_subtotal($full);
        $item->set_total($full);
    }
    $surface = strtolower(trim((string) ms_get_commission_meta($item, 'surface')));
    $lbs = $surface === 'paper' ? 0.2 : ($surface === 'canvas panel' ? 0.6 : 0);
    if ($lbs) ms_commission_label_weights($item, $lbs);
}

/** Remembers a default weight (lb) for one borrowed item copy; the filter below hands it to whoever asks for the item's product */
function ms_commission_label_weights($item = null, $lbs = null) {
    static $weights = [];
    if ($item === null) return $weights;
    $weights[spl_object_id($item)] = $lbs;
    return $weights;
}
add_filter('woocommerce_order_item_product', function ($product, $item) {
    $w = ms_commission_label_weights();
    if ($product && isset($w[spl_object_id($item)]) && !(float) $product->get_weight()) {
        $product = clone $product;
        $product->set_weight(wc_get_weight($w[spl_object_id($item)], get_option('woocommerce_weight_unit', 'lbs'), 'lbs'));
    }
    return $product;
}, 10, 2);

/** The tracking of the label bought on this order (WooCommerce Shipping): ['carrier' => 'USPS', 'number' => '...'] or null */
function ms_commission_label_tracking($order) {
    $labels = $order->get_meta('wcshipping_labels', true);
    if (!is_array($labels)) return null;
    foreach (array_reverse($labels) as $l) {
        if (!empty($l['tracking']) && (($l['status'] ?? '') === 'PURCHASED') && empty($l['is_return'])) {
            $known = function_exists('ms_commission_carriers') ? array_keys(ms_commission_carriers()) : [];
            $carrier = 'Other';
            foreach ($known as $k) if (strcasecmp($k, (string) ($l['carrier_id'] ?? '')) === 0) { $carrier = $k; break; }
            return ['carrier' => $carrier, 'number' => (string) $l['tracking']];
        }
    }
    return null;
}

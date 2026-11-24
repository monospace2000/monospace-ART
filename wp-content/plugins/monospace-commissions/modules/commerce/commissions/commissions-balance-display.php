<?php
/**
 * Commissions: how a balance order LOOKS (display only, nothing stored changes).
 *
 * Model b books the balance as a fee line (so the piece is counted once in reports), and WooCommerce then shows the
 * customer "Subtotal $0.00" plus a fee row. Owner, 2026-11-23: the customer must see one item - the commission, marked
 * as the balance (the remaining part after the deposit) - with that amount as the subtotal, then shipping, no fees.
 * Covers the pay page, the order view / thank-you page and the order emails; in wp-admin the "Fees:" total of a
 * balance order reads "Balance:". The final-invoice email has its own body (commissions-emails.php).
 */

if (!defined('ABSPATH')) exit;

/** The balance (fee) lines of a model-b balance order; [] for any other order */
function ms_commission_balance_fees($order) {
    if (!$order instanceof WC_Order || !function_exists('ms_commission_final_items')) return [];
    $fees = [];
    foreach (ms_commission_final_items($order) as $item) {
        if ($item instanceof WC_Order_Item_Fee) $fees[$item->get_id()] = $item;
    }
    return $fees;
}

/** What the customer sees for a balance line: "Line & Wash commission" + "Balance: the remaining 50%" */
function ms_commission_balance_label($item) {
    $title = function_exists('ms_commission_friendly_name') ? ms_commission_friendly_name($item->get_name()) : $item->get_name();
    $title = ucfirst($title);
    $sub = 'Balance: the remaining part after your deposit';
    $orig = wc_get_order(ms_get_commission_meta($item, 'original_order_id'));
    if ($orig) {
        $orig_item = $orig->get_item((int) ms_get_commission_meta($item, 'original_item_id'));
        $full = $orig_item ? (float) ms_get_commission_meta($orig_item, 'full_price') : 0;
        $balance = (float) $item->get_total();
        if ($full > 0 && $balance > 0) $sub = sprintf('Balance: the remaining %d%%', round($balance / $full * 100));
        $sub .= ' (deposit: order #' . $orig->get_order_number() . ')';
    }
    return [$title, $sub];
}

// The item name wherever WooCommerce prints it for the customer (also model a's product line)
add_filter('woocommerce_order_item_name', function($name, $item) {
    if (!is_object($item) || !ms_get_commission_meta($item, 'is_final_payment')) return $name;
    [$title, $sub] = ms_commission_balance_label($item);
    return esc_html($title) . '<br><small>' . esc_html($sub) . '</small>';
}, 20, 2);

// Totals: subtotal = the balance, the fee row goes
add_filter('woocommerce_get_order_item_totals', function($rows, $order) {
    $fees = ms_commission_balance_fees($order);
    if (!$fees) return $rows;
    $balance = 0.0;
    foreach ($fees as $id => $fee) {
        $balance += (float) $fee->get_total();
        unset($rows['fee_' . $id]);
    }
    if (isset($rows['cart_subtotal'])) {
        $rows['cart_subtotal']['value'] = wc_price((float) $order->get_subtotal() + $balance, ['currency' => $order->get_currency()]);
    }
    return $rows;
}, 20, 2);

// Pay page: while its item table is printed (and only then), the balance lines count as items. form-pay.php works
// out its totals first (that code asks every item for its tax, which a fee line can't answer), so the window is
// armed before the form and opens only once those totals are done; it closes before the gateways render.
$GLOBALS['ms_commission_pay_armed'] = 0;
$GLOBALS['ms_commission_pay_table'] = 0;
add_action('before_woocommerce_pay_form', function($order) {
    $GLOBALS['ms_commission_pay_armed'] = ms_commission_balance_fees($order) ? $order->get_id() : 0;
}, 1);
add_filter('woocommerce_get_order_item_totals', function($rows, $order) {
    if ($GLOBALS['ms_commission_pay_armed'] && $order->get_id() === $GLOBALS['ms_commission_pay_armed']) {
        $GLOBALS['ms_commission_pay_table'] = $GLOBALS['ms_commission_pay_armed'];
        $GLOBALS['ms_commission_pay_armed'] = 0;
    }
    return $rows;
}, 99, 2);
add_action('woocommerce_pay_order_before_payment', function() {
    $GLOBALS['ms_commission_pay_armed'] = $GLOBALS['ms_commission_pay_table'] = 0;
}, 0);
add_filter('woocommerce_order_get_items', function($items, $order, $types) {
    if (!$GLOBALS['ms_commission_pay_table'] || $order->get_id() !== $GLOBALS['ms_commission_pay_table'] || (array) $types !== ['line_item']) return $items;
    return $items + ms_commission_balance_fees($order);
}, 20, 3);
add_filter('woocommerce_order_amount_line_subtotal', function($subtotal, $order, $item) {
    return ($item instanceof WC_Order_Item_Fee && ms_get_commission_meta($item, 'is_final_payment')) ? (float) $item->get_total() : $subtotal;
}, 20, 3);

// Order view / thank-you page: a row of its own (the item template expects a product)
add_action('woocommerce_order_details_after_order_table_items', function($order) {
    foreach (ms_commission_balance_fees($order) as $fee) {
        echo '<tr class="woocommerce-table__line-item order_item"><td class="woocommerce-table__product-name product-name">'
            . wp_kses_post(apply_filters('woocommerce_order_item_name', $fee->get_name(), $fee, false))
            . ' <strong class="product-quantity">&times;&nbsp;1</strong></td><td class="woocommerce-table__product-total product-total">'
            . wc_price($fee->get_total(), ['currency' => $order->get_currency()]) . '</td></tr>';
    }
});

// Emails: a row after the item rows (plain-text emails get a text line)
$GLOBALS['ms_commission_email_plain'] = false;
add_filter('woocommerce_email_order_items_args', function($args) {
    $GLOBALS['ms_commission_email_plain'] = !empty($args['plain_text']);
    return $args;
});
add_filter('woocommerce_email_order_items_table', function($html, $order) {
    foreach (ms_commission_balance_fees($order) as $fee) {
        [$title, $sub] = ms_commission_balance_label($fee);
        $price = wc_price($fee->get_total(), ['currency' => $order->get_currency()]);
        if ($GLOBALS['ms_commission_email_plain']) {
            $html .= $title . ' (' . $sub . ') X 1 = ' . wp_strip_all_tags($price) . "\n";
            continue;
        }
        $td = 'class="td font-family" style="vertical-align:middle;word-wrap:break-word;';
        $html .= '<tr class="order_item"><td ' . $td . 'text-align:left;">' . esc_html($title) . '<br><small>' . esc_html($sub) . '</small></td>'
            . '<td ' . $td . 'text-align:left;">1</td><td ' . $td . 'text-align:right;">' . $price . '</td></tr>';
    }
    return $html;
}, 20, 2);

// wp-admin order screen: "Fees:" reads "Balance:" on a balance order
$GLOBALS['ms_commission_admin_balance'] = false;
add_action('woocommerce_admin_order_items_after_fees', function($order_id) {
    $GLOBALS['ms_commission_admin_balance'] = (bool) ms_commission_balance_fees(wc_get_order($order_id));
});
add_action('woocommerce_admin_order_totals_after_total', function() {
    $GLOBALS['ms_commission_admin_balance'] = false;
});
add_filter('gettext_woocommerce', function($text, $original) {
    return ($GLOBALS['ms_commission_admin_balance'] && $original === 'Fees:') ? 'Balance:' : $text;
}, 20, 2);

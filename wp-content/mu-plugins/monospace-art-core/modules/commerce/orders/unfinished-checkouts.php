<?php
/**
 * An unpaid checkout is not an order (owner, 2026-11-18).
 *
 * WooCommerce holds the pieces for "Hold stock" minutes (WooCommerce > Settings > Products > Inventory) while the buyer pays.
 * When that time runs out unpaid, WooCommerce would mark the order Cancelled - it then shows in the customer's My Account as a
 * cancelled order and sends the "Cancelled order" email. Instead it goes back to a checkout draft (WooCommerce's own status for
 * a checkout in progress): never shown in My Account, left out of sales reports, still payable if a late payment comes in,
 * and deleted by WooCommerce's daily clean-up once it is a day old.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'woocommerce_cancel_unpaid_order', function ( $cancel, $order ) {
    if ( ! $cancel || ! $order instanceof WC_Order || ! array_key_exists( 'wc-checkout-draft', wc_get_order_statuses() ) ) {
        return $cancel;
    }

    wc_release_stock_for_order( $order ); // the held pieces are free again (Cancelled would do the same)
    $order->update_status( 'checkout-draft', 'Checkout not finished: no payment within the hold time. Kept as a checkout draft (not an order); WooCommerce deletes it after a day.' );

    return false;
}, 10, 2 );

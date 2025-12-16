<?php
/**
 * WooCommerce Custom Free Shipping Rules
 * Compatible with custom volume/discount plugin
 * Drop in inc/addons/commerce/
 *
 * Features:
 * - Free shipping for products with a specific tag above a set subtotal (after discounts)
 * - Keeps WooCommerce's default free shipping rules (minimum order, coupons)
 * - Hides paid methods whenever free shipping is active
 *
 * Editable parameters at the top
 */

// ===================
// Editable parameters
// ===================
$miniature_tag      = 'miniature';  // Product tag to trigger special free shipping
$miniature_min      = 99;           // Minimum subtotal for tagged products (after discounts)
$general_min_order  = 180;          // General free shipping minimum (handled by WC)

// ===================
// Free Shipping Activation
// ===================
add_filter( 'woocommerce_shipping_free_shipping_is_available', function( $is_available, $package ) use ( $miniature_tag, $miniature_min ) {

    $cart = WC()->cart;
    if ( ! $cart ) return $is_available;

    // Sum the discounted prices of miniature-tagged items
    $mini_total = 0;
    foreach ( $cart->get_cart() as $cart_item ) {
        $product_id = $cart_item['product_id'];
        if ( has_term( $miniature_tag, 'product_tag', $product_id ) ) {
            // Use discounted price * quantity
            $mini_total += $cart_item['data']->get_price() * $cart_item['quantity'];
        }
    }

    // Miniature-specific free shipping condition
    $mini_condition = $mini_total >= $miniature_min;

    // Enable free shipping if either WooCommerce standard rules or miniature condition applies
    return $is_available || $mini_condition;

}, 999, 2 ); // priority 999 to run after custom discounts

// ===================
// Hide Paid Methods when Free Shipping is Active
// ===================
add_filter( 'woocommerce_package_rates', function( $rates, $package ) {

    $free_rates = [];
    foreach ( $rates as $rate_id => $rate ) {
        if ( 'free_shipping' === $rate->method_id ) {
            $free_rates[$rate_id] = $rate;
            break;
        }
    }

    // If free shipping exists, return only free shipping
    return ! empty( $free_rates ) ? $free_rates : $rates;

}, 100, 2 );

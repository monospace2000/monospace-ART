<?php
/**
 * WooCommerce Custom Free Shipping Rules
 * Drop this file in inc/addons/commerce and it will automatically load.
 *
 * Features:
 * - Free shipping for products with a specific tag above a set subtotal
 * - Keeps WooCommerce's default free shipping rules (minimum order, coupons)
 * - Hides paid methods whenever free shipping is active
 *
 * Editable parameters are at the top
 */

// ===================
// Editable parameters
// ===================
$miniature_tag      = 'miniature';  // Product tag to trigger special free shipping
$miniature_min      = 99;           // Minimum subtotal for tagged products
$general_min_order  = 180;          // General free shipping minimum (already handled by WC, keep for reference)

// ===================
// Free Shipping Activation
// ===================
add_filter( 'woocommerce_shipping_free_shipping_is_available', function( $is_available, $package ) use ( $miniature_tag, $miniature_min ) {

    $cart = WC()->cart;
    if ( ! $cart ) {
        return $is_available;
    }

    // Check if cart contains any product with the target tag
    $contains_tag = false;
    foreach ( $cart->get_cart() as $cart_item ) {
        $product_id = $cart_item['product_id'];
        if ( has_term( $miniature_tag, 'product_tag', $product_id ) ) {
            $contains_tag = true;
            break;
        }
    }

    // Miniature-specific free shipping condition
    $mini_condition = $contains_tag && $cart->get_subtotal() >= $miniature_min;

    // Enable free shipping if either WooCommerce or miniature condition applies
    return $is_available || $mini_condition;

}, 99, 2 );

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

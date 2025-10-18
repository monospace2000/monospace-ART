<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */




/**
 * WooCommerce: Change Add-to-Cart button text and disable purchase
 * if the product is already in the cart and stock is exactly 1.
 *
 * Adds a body class for styling purposes as well.
 *
 * @since 1.0.0
 */

/**
 * Modify single product add-to-cart button text.
 *
 * @param string $text Original button text.
 * @return string Modified button text if product is in cart and stock is 1.
 */
add_filter( 'woocommerce_product_single_add_to_cart_text', function( $text ) {
    global $product;

    if ( $product instanceof WC_Product && $product->managing_stock() && $product->get_stock_quantity() === 1 ) {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( $cart_item['product_id'] === $product->get_id() ) {
                return __( 'Already in cart', 'your-textdomain' );
            }
        }
    }

    return $text;
} );

/**
 * Disable purchasing of product if already in cart and stock is 1.
 *
 * @param bool        $purchasable Whether the product is purchasable.
 * @param WC_Product  $product The current product object.
 * @return bool Modified purchasable status.
 */
add_filter( 'woocommerce_is_purchasable', function( $purchasable, $product ) {
    if ( $product instanceof WC_Product && $product->managing_stock() && $product->get_stock_quantity() === 1 ) {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( $cart_item['product_id'] === $product->get_id() ) {
                return false; // Disable purchase
            }
        }
    }
    return $purchasable;
}, 10, 2 );

/**
 * Add a body class to the product page when the product is in cart and stock is 1.
 *
 * @param array $classes Existing body classes.
 * @return array Modified body classes.
 */
add_filter( 'body_class', function( $classes ) {
    global $product;

    if ( is_product() && $product instanceof WC_Product ) {
        if ( $product->managing_stock() && $product->get_stock_quantity() === 1 ) {
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                if ( $cart_item['product_id'] === $product->get_id() ) {
                    $classes[] = 'already-in-cart-single-stock';
                }
            }
        }
    }

    return $classes;
} );



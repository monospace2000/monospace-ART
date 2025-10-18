<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */



/**
 * Force Google for WooCommerce to use root domain for Merchant API connection
 */
add_filter( 'woocommerce_google_product_api_base_url', function( $url ) {
    // Replace with your store root domain
    return 'https://monospace.com';
});
/**
 * Force Google for WooCommerce to use root domain for Merchant connection
 */
add_filter( 'woocommerce_google_product_connection_url', function( $url ) {
    // Force root domain for connection
    return 'https://monospace.com';
});


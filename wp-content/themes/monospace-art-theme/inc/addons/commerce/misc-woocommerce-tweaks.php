<?php
/**
 * WooCommerce: Hide other shipping options when free shipping is available
 * Using JavaScript as a fallback when PHP filters don't work
 */

// Try PHP filter first (highest priority)
add_filter( 'woocommerce_package_rates', 'force_hide_shipping_when_free', PHP_INT_MAX, 2 );

function force_hide_shipping_when_free( $rates, $package ) {

    if ( empty( $rates ) ) {
        return $rates;
    }

    $free = array();

    foreach ( $rates as $rate_id => $rate ) {
        if ( strpos( $rate->method_id, 'free_shipping' ) !== false ) {
            $free[ $rate_id ] = $rate;
        }
    }

    return ! empty( $free ) ? $free : $rates;
}

// Make sure free shipping is selected by default when available
add_filter( 'woocommerce_shipping_chosen_method', 'set_free_shipping_as_default', 10, 3 );

function set_free_shipping_as_default( $chosen_method, $available_methods, $package ) {

    // Check if free shipping is available
    foreach ( $available_methods as $rate_id => $rate ) {
        if ( strpos( $rate->method_id, 'free_shipping' ) !== false ) {
            return $rate_id; // Return the free shipping rate ID
        }
    }

    return $chosen_method; // Return default if no free shipping
}

// CSS solution - hide non-free shipping when free shipping exists
add_action( 'wp_head', 'hide_shipping_options_css' );

function hide_shipping_options_css() {

    // Only run on cart and checkout pages
    if ( ! is_cart() && ! is_checkout() ) {
        return;
    }
    ?>
    <style type="text/css">
        /* Hide radio button for Free Shipping when it's the only option */
        .woocommerce-shipping-methods input[type="radio"]:only-of-type {
            display: none;
        }

        /* Hide all shipping methods by default when free shipping exists */
        #shipping_method:has(input[value*="free_shipping"]) li:not(:has(input[value*="free_shipping"])) {
            display: none !important;
        }

        /* Make free shipping label bold and green */
        #shipping_method li:has(input[value*="free_shipping"]) label {
            font-weight: bold !important;
            color: #28a745 !important;
        }
    </style>
    <?php
}

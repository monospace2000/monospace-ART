<?php
/**
 * WooCommerce → Blog Post Redirects
 * Redirects product pages to their related blog post, with exceptions for custom-order products.
 */

/* ============================================================
 * CONFIGURATION
 * ============================================================ */

/**
 * Define product slug exceptions (wildcards allowed)
 */
function monospace_wc_redirect_exceptions() {
    return [
        'custom-artwork-*', // all custom-order products (slug patterns)
    ];
}

/**
 * Returns the blog post ID linked to a print product, or 0.
 * Print products are identified by belonging to the 'prints' WooCommerce product category.
 */
function monospace_get_post_for_print_product( $product_id ) {
    if ( ! has_term( 'print', 'product_cat', $product_id ) ) return 0;
    return (int) get_post_meta( $product_id, '_related_post_id', true );
}

/**
 * Check if a product slug matches an exception pattern
 */
function monospace_wc_is_exempt_product( $product_id ) {
    $post = get_post( $product_id );
    if ( ! $post ) {
        return false;
    }

    $slug = $post->post_name;

    foreach ( monospace_wc_redirect_exceptions() as $pattern ) {
        $quoted = preg_quote( $pattern, '#' );
        $regex  = '#^' . str_replace( '\*', '.*', $quoted ) . '$#i';
        if ( preg_match( $regex, $slug ) ) {
            return true;
        }
    }
    return false;
}

/* ============================================================
 * 1. Redirect product page visits from cart/checkout only
 * ============================================================ */

add_action( 'template_redirect', function () {

    if ( ! is_singular( 'product' ) ) {
        return;
    }

    // Skip redirect if coming from search results
    if ( defined('MONOSPACE_FROM_SEARCH') && MONOSPACE_FROM_SEARCH ) {
        return;
    }

    $product_id = get_queried_object_id();

    // Skip if product is exempt
    if ( monospace_wc_is_exempt_product( $product_id ) ) {
        return;
    }

    // Check if visitor is coming from cart or checkout
    $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $is_from_cart = false;

    if ( $referer && function_exists('wc_get_cart_url') && function_exists('wc_get_checkout_url') ) {
        $cart_url = wc_get_cart_url();
        $checkout_url = wc_get_checkout_url();

        // Check if referer contains cart or checkout URL
        if ( strpos( $referer, $cart_url ) !== false || strpos( $referer, $checkout_url ) !== false ) {
            $is_from_cart = true;
        }
    }

    // Only redirect if coming from cart/checkout; allow direct access otherwise
    if ( ! $is_from_cart ) {
        return;
    }

    $related_post_id = (int) get_post_meta( $product_id, '_related_post_id', true );

    // Skip if no related post or unpublished
    if ( ! $related_post_id || get_post_status( $related_post_id ) !== 'publish' ) {
        return;
    }

    // Prevent redirect loop if visiting the related post itself
    if ( $related_post_id === get_queried_object_id() ) {
        return;
    }

    wp_safe_redirect( get_permalink( $related_post_id ), 301 );
    exit;

}, 1 );

/* ============================================================
 * 2. Rewrite product permalinks
 * ============================================================ */

add_filter( 'woocommerce_product_get_permalink', function ( $permalink, $product ) {

    // Skip exempt products
    if ( monospace_wc_is_exempt_product( $product->get_id() ) ) {
        return $permalink;
    }

    $related_post_id = (int) get_post_meta( $product->get_id(), '_related_post_id', true );

    if ( $related_post_id && get_post_status( $related_post_id ) === 'publish' ) {
        return get_permalink( $related_post_id );
    }

    return $permalink;

}, 10, 2 );

/* ============================================================
 * 3. Cart & checkout links
 * ============================================================ */

add_filter( 'woocommerce_cart_item_permalink', function ( $permalink, $cart_item, $cart_item_key ) {

    $product_id = $cart_item['product_id'] ?? 0;
    if ( ! $product_id ) return $permalink;

    if ( monospace_wc_is_exempt_product( $product_id ) ) {
        return $permalink;
    }

    $related_post_id = (int) get_post_meta( $product_id, '_related_post_id', true );

    if ( $related_post_id && get_post_status( $related_post_id ) === 'publish' ) {
        return get_permalink( $related_post_id );
    }

    return $permalink;

}, 10, 3 );


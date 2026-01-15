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
 * 1. Redirect direct product page visits
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

/* ============================================================
 * 4. Add-to-cart redirects
 * ============================================================ */

add_filter( 'woocommerce_add_to_cart_redirect', function ( $url ) {

    if ( empty( $_REQUEST['add-to-cart'] ) ) return $url;

    $product_id = (int) $_REQUEST['add-to-cart'];

    // Skip exempt products
    if ( monospace_wc_is_exempt_product( $product_id ) ) {
        return $url;
    }

    $related_post_id = (int) get_post_meta( $product_id, '_related_post_id', true );
    if ( $related_post_id && get_post_status( $related_post_id ) === 'publish' ) {
        return get_permalink( $related_post_id );
    }

    return $url;

} );
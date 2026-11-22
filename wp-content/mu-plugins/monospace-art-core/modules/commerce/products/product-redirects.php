<?php
/**
 * WooCommerce → Blog Post Redirects
 * Redirects product pages to their related Art Post - always, for everyone but an administrator - with an exception for custom-order products.
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
 * 1. A product page is never shown to a visitor: it goes to its Art Post (2026-10-23, owner's rule)
 * ============================================================ */

/**
 * The rule: no product page is directly accessible - except a custom art order's (custom-artwork-*) and an administrator's
 * (to view, preview or edit a product). Everyone else - visitors, search engines, the Meta catalog's links - is sent (301) to
 * the product's Art Post (_related_post_id; a print's too); with no published post, to the home page (302).
 * The query string goes along - Meta's fbclid and the utm_* tags keep their click attribution - except WooCommerce's own
 * add-to-cart (it has already run; the post page would add it again).
 * (Until 2026-10-23 this only redirected a visitor coming from the cart or checkout, and exempted "search results" through a
 * constant nothing defined - a direct visit got the product page.)
 */
add_action( 'template_redirect', function () {

    if ( ! is_singular( 'product' ) ) {
        return;
    }

    $product_id = get_queried_object_id();

    // Custom art orders (custom-artwork-*) stay as product pages; so do they for an administrator
    if ( monospace_wc_is_exempt_product( $product_id ) || current_user_can( 'manage_options' ) ) {
        return;
    }

    $related_post_id = (int) get_post_meta( $product_id, '_related_post_id', true );
    $published       = $related_post_id && get_post_status( $related_post_id ) === 'publish';
    $to              = $published ? get_permalink( $related_post_id ) : home_url( '/' );

    $query = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY );
    if ( $query !== '' ) {
        parse_str( $query, $args );
        unset( $args['add-to-cart'] );
        if ( $args ) $to = add_query_arg( urlencode_deep( $args ), $to );
    }

    wp_safe_redirect( $to, $published ? 301 : 302, 'monospace ART product page' );
    exit;

}, 1 );

// Product pages don't belong in the sitemap (Jetpack's) - custom art orders' do, they stay open
add_filter( 'jetpack_sitemap_skip_post', function ( $skip, $post ) {
    if ( $skip || ! is_object( $post ) || ( $post->post_type ?? '' ) !== 'product' ) return $skip;
    return ! monospace_wc_is_exempt_product( $post->ID );
}, 10, 2 );

/* ============================================================
 * 2. (A 'woocommerce_product_get_permalink' filter stood here until 2026-11-18. WooCommerce never applies that filter -
 *    products get their link from WordPress' get_permalink() - so it never did anything. The places a customer sees a
 *    product link are handled one by one below; site search shows only Art Posts.)
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
 * 4. Order links (order received, My Account > View order, order emails)
 * ============================================================ */

add_filter( 'woocommerce_order_item_permalink', function ( $permalink, $item, $order ) {

    $product_id = is_callable( [ $item, 'get_product_id' ] ) ? (int) $item->get_product_id() : 0;
    if ( ! $product_id || $permalink === '' || monospace_wc_is_exempt_product( $product_id ) ) {
        return $permalink;
    }

    $related_post_id = (int) get_post_meta( $product_id, '_related_post_id', true );

    if ( $related_post_id && get_post_status( $related_post_id ) === 'publish' ) {
        return get_permalink( $related_post_id );
    }

    return $permalink;

}, 10, 3 );


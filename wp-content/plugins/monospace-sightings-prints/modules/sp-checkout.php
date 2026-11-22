<?php
/**
 * Sightings prints at checkout: an order with a Sightings print is labeled "Sightings print" (Orders list, reports), and the product
 * links in the cart, the order pages and the order emails lead to the drawing on the Sightings site (the stand-in post behind the
 * product is private, so the art site's usual link to the Art Post would go nowhere).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'ms_order_labels', function ( $labels ) {
    $labels['sightings-print'] = 'Sightings print';
    return $labels;
} );

/** The drawing's page on Sightings for a Sightings print product, or '' */
function monospace_sp_product_link( $product_id ) {
    if ( ! $product_id || ! function_exists( 'monospace_sp_is_product' ) || ! monospace_sp_is_product( $product_id ) ) return '';
    $src = get_post_meta( (int) get_post_meta( $product_id, '_related_post_id', true ), '_ms_sp_source', true );
    // ?ref=art: the Sightings page skips its welcome pop-up for someone coming back from the cart or an order page
    return is_array( $src ) && ! empty( $src['link'] ) ? add_query_arg( 'ref', 'art', $src['link'] ) : '';
}

// Before Core's own labeling (priority 20): it leaves an order that already has a label alone
add_action( 'woocommerce_checkout_order_processed', function ( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_meta( 'order_label', true ) ) return;
    foreach ( $order->get_items() as $item ) {
        if ( monospace_sp_product_link( (int) $item->get_product_id() ) ) {
            $order->update_meta_data( 'order_label', 'sightings-print' );
            $order->save();
            return;
        }
    }
}, 15 );

add_filter( 'woocommerce_cart_item_permalink', function ( $permalink, $cart_item ) {
    $link = monospace_sp_product_link( (int) ( $cart_item['product_id'] ?? 0 ) );
    return $link ?: $permalink;
}, 20, 2 );

add_filter( 'woocommerce_order_item_permalink', function ( $permalink, $item ) {
    $link = is_callable( [ $item, 'get_product_id' ] ) ? monospace_sp_product_link( (int) $item->get_product_id() ) : '';
    return $link ?: $permalink;
}, 20, 2 );

/** Sightings prints are all portrait (4:5), so sizes read "8×10", not "10×8" (Core: monospace_post_is_portrait()) */
add_filter( 'monospace_post_is_portrait', function ( $answer, $post_id ) {
    return monospace_sp_is_standin( $post_id ) ? true : $answer;
}, 10, 2 );

/**
 * The cart picture of a Sightings print is the paper on a transparent ground. The dark-band layout is near black, so on the dark site
 * the paper vanishes: in dark mode only, a faint light rim follows the paper's edge (owner, 2026-11-19: missing in the cart in dark mode).
 */
add_filter( 'woocommerce_cart_item_thumbnail', function ( $image, $cart_item ) {
    if ( ! monospace_sp_product_link( (int) ( $cart_item['product_id'] ?? 0 ) ) ) return $image;
    return preg_replace( '/<img\b/', '<img data-ms-sp="1"', (string) $image, 1 );
}, 30, 2 );

add_action( 'wp_head', function () {
    if ( ! function_exists( 'is_cart' ) || ! ( is_cart() || is_checkout() ) ) return;
    echo "<style id=\"ms-sp-thumb\">html.ms-dark-ui img[data-ms-sp]{filter:drop-shadow(0 0 1px rgba(255,255,255,.7)) drop-shadow(0 0 5px rgba(255,255,255,.18))}</style>\n";
}, 20 );

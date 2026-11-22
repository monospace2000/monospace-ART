<?php
/**
 * Sightings prints: kept out of the art shop. The stand-in posts are private (nothing public lists them). Their print products
 * are published (the cart needs that) but hidden from the shop and search and not searched by Relevanssi. A product page address
 * already redirects away (Core, product-redirects.php). Meta and Google sync are NOT blocked here (owner, 2026-11-19: they are not in
 * use now, but leave them possible for later); the sync plugins' own settings decide.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Is this product one of the Sightings prints? (its linked post is a stand-in) */
function monospace_sp_is_product( $product_id ) {
    $post_id = (int) get_post_meta( $product_id, '_related_post_id', true );
    return $post_id && monospace_sp_is_standin( $post_id );
}

function monospace_sp_hide_product( $product_id ) {
    static $busy = [];
    if ( isset( $busy[ $product_id ] ) || ! monospace_sp_is_product( $product_id ) ) return;
    $busy[ $product_id ] = true;
    wp_set_object_terms( $product_id, [ 'exclude-from-catalog', 'exclude-from-search' ], 'product_visibility', true );
    unset( $busy[ $product_id ] );
}
add_action( 'woocommerce_new_product', 'monospace_sp_hide_product', 20 );
add_action( 'woocommerce_update_product', 'monospace_sp_hide_product', 20 );

/** Relevanssi: never index stand-in posts or their products */
add_filter( 'relevanssi_do_not_index', function ( $block, $post_id ) {
    if ( $block ) return $block;
    if ( monospace_sp_is_standin( $post_id ) || ( get_post_type( $post_id ) === 'product' && monospace_sp_is_product( $post_id ) ) ) return 'Sightings print';
    return $block;
}, 10, 2 );

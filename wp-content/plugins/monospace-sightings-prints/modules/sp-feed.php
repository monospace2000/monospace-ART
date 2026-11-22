<?php
/**
 * Sightings prints: what the Sightings site needs to show its buy options. A public, read-only JSON feed of the prints on sale:
 *   GET /wp-json/monospace/v1/sightings-prints
 * Per Sightings post ID: its number, and per layout (dark / overlay / light) a preview picture and, for unframed and framed, every
 * size (and frame color) with its price and a ready-made add-to-cart address on this site's cart (the same address the price card
 * uses). Prices come from WooCommerce; nothing is typed here. Only prints that are on sale (the job has linked them) appear.
 * Cached five minutes. Nothing private: it is what a visitor sees at checkout.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The add-to-cart address for one variation */
function monospace_sp_cart_url( $parent_id, $variation ) {
    $args = [ 'add-to-cart' => (int) $parent_id, 'quantity' => 1, 'ms_redirect_cart' => 1, 'variation_id' => $variation->get_id() ];
    foreach ( $variation->get_attributes() as $tax => $slug ) $args[ 'attribute_' . $tax ] = $slug;
    return add_query_arg( $args, wc_get_cart_url() );
}

/** One print product's variations: [ [ size, frame, price, variation, url, preview, scale ] ] */
function monospace_sp_feed_product( $product_id ) {
    $product = wc_get_product( $product_id );
    if ( ! $product || $product->get_status() !== 'publish' ) return null;
    $rows = [];
    foreach ( $product->get_children() as $vid ) {
        $v = wc_get_product( $vid );
        if ( ! $v || ! $v->is_in_stock() || $v->get_price() === '' ) continue;
        $attrs = $v->get_attributes();
        $rows[] = [
            'size'      => preg_replace( '/-\d+$/', '', (string) ( $attrs['pa_size'] ?? '' ) ), // "8x10-2" -> "8x10"
            'frame'     => (string) ( $attrs['pa_color'] ?? '' ),                             // "" = unframed
            'price'     => (float) $v->get_price(),
            'variation' => $vid,
            'url'       => monospace_sp_cart_url( $product_id, $v ),
            'preview'   => function_exists( 'monospace_print_mockup_url' ) ? monospace_print_mockup_url( $vid ) : '',   // the print drawn on the site (transparent WebP), for the Sightings preview
            'scale'     => function_exists( 'monospace_print_mockup_scale' ) ? monospace_print_mockup_scale( $vid ) : 1, // its size next to the largest print, 0-1
        ];
    }
    return $rows ? [ 'product' => (int) $product_id, 'name' => $product->get_name(), 'options' => $rows ] : null;
}

function monospace_sp_feed() {
    $feed = get_transient( 'ms_sp_feed_v2' );
    if ( is_array( $feed ) ) return $feed;
    $drawings = [];
    $ids = get_posts( [ 'post_type' => 'post', 'post_status' => 'private', 'numberposts' => -1, 'fields' => 'ids',
        'meta_query' => [ [ 'key' => '_ms_sp_key', 'compare' => 'EXISTS' ] ] ] );
    foreach ( $ids as $post_id ) {
        $src = get_post_meta( $post_id, '_ms_sp_source', true );
        if ( ! is_array( $src ) || monospace_prints_get_job( $post_id ) ) continue; // still being made
        // The picture buyers see: the postcard's upright one (its print file is turned sideways for Printful), else the print file
        $shown = (int) get_post_meta( $post_id, '_ms_sp_upright_file', true ) ?: (int) get_post_meta( $post_id, '_ms_print_file', true );
        $entry = [ 'canvas' => $src['canvas'], 'preview' => (string) wp_get_attachment_image_url( $shown, 'medium_large' ) ];
        foreach ( [ 'unframed' => '_print_product_id', 'framed' => '_framed_print_product_id', 'postcard' => '_postcard_product_id' ] as $type => $meta ) {
            $row = ( $pid = (int) get_post_meta( $post_id, $meta, true ) ) ? monospace_sp_feed_product( $pid ) : null;
            if ( $row ) $entry[ $type ] = $row;
        }
        if ( count( $entry ) < 3 ) continue; // nothing on sale
        $d =& $drawings[ (int) $src['post'] ];
        $d['number'] = (int) $src['number'];
        $d['link']   = (string) $src['link'];
        $d['layouts'][ $src['layout'] ][ $src['canvas'] ] = $entry;
        unset( $d );
    }
    $layouts = array_map( function ( $l ) { return $l['label']; }, monospace_sp_layouts() );
    $feed = [ 'updated' => time(), 'cart' => wc_get_cart_url(), 'layouts' => $layouts, 'drawings' => (object) $drawings ];
    set_transient( 'ms_sp_feed_v2', $feed, 5 * MINUTE_IN_SECONDS );
    return $feed;
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'monospace/v1', '/sightings-prints', [
        'methods' => 'GET', 'permission_callback' => '__return_true',
        'callback' => function () {
            $response = rest_ensure_response( monospace_sp_feed() );
            // The Sightings pop-up reads this from the browser: allow that one site (read-only data)
            $response->header( 'Access-Control-Allow-Origin', MONOSPACE_SP_SITE );
            $response->header( 'Vary', 'Origin' );
            return $response;
        },
    ] );
} );

/** A changed price or a new print shows up at once */
add_action( 'woocommerce_update_product', function ( $id ) { if ( function_exists( 'monospace_sp_is_product' ) && monospace_sp_is_product( $id ) ) delete_transient( 'ms_sp_feed_v2' ); } );
add_action( 'woocommerce_update_product_variation', function ( $id ) { delete_transient( 'ms_sp_feed_v2' ); } );

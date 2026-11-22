<?php
/**
 * An original's availability, as the site shows it (2026-10-21 - was worked out three times inside the Art Filter).
 *
 *   monospace_artwork_statuses()            key => the name visitors see (the price card's labels)
 *   monospace_artwork_status( $product_id ) the status: the product's painting_availability_status ('available' when
 *                                           not set), but sold when WooCommerce says out of stock - except gallery and
 *                                           private works, whose stock doesn't say whether they're sold
 *   monospace_artwork_in_stock( $product_id ) WooCommerce's stock status is "in stock"
 *
 * The Quick Artwork Editor keeps stock and status together when an artwork is saved (sold = 0 and out of stock); this
 * reads both, so a pair that disagrees (marked sold, stock left at 1) still counts as sold.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_artwork_statuses() {
    return [
        'available'        => 'Available',
        'reserved'         => 'Reserved',
        'sold'             => 'Private Collection',
        'private'          => 'Artist’s Private Collection',
        'gallery'          => 'At Gallery',
        'gallery-reserved' => 'At Gallery (QR Purchase Only)',
    ];
}

function monospace_artwork_in_stock( $product_id ) {
    return get_post_meta( $product_id, '_stock_status', true ) === 'instock';
}

function monospace_artwork_status( $product_id ) {
    $status = strtolower( trim( (string) get_post_meta( $product_id, 'painting_availability_status', true ) ) );
    if ( $status === '' ) $status = 'available';
    if ( get_post_meta( $product_id, '_stock_status', true ) === 'outofstock' && ! in_array( $status, [ 'gallery', 'private' ], true ) ) $status = 'sold';
    return $status;
}

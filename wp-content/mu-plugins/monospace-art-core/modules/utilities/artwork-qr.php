<?php
/**
 * An artwork's QR code (2026-10-30, owner's request: "scan to buy at galleries", on sheets and slideshows too).
 *
 * What the code leads to ($mode):
 *   'page'  the artwork's Art Post - what a visitor would see.
 *   'buy'   "scan to buy": for a Reserved or Gallery QR piece the Art Post *with its reserved purchase key*
 *           (?reserved_key=..., the link the Quick Artwork Editor shows as "Reserved purchase link") - it shows the buy
 *           button the piece otherwise withholds; for any other piece the Art Post.
 * Careful: a 'buy' code is a purchase link. Print it on a gallery label; don't put it on a page or a sheet link anyone
 * can open unless that is what you want (the sheet and slideshow screens say so).
 *
 * The QR drawing itself is qr-code.php (monospace_qr_svg / _png).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The published Art Post of a product (an original's or a print's), or 0 */
function monospace_artwork_post_id( $product_id ) {
    $post_id = (int) get_post_meta( (int) $product_id, '_related_post_id', true );
    return $post_id && get_post_status( $post_id ) === 'publish' ? $post_id : 0;
}

/** Does this product's status hold it back for a buyer with the reserved link? */
function monospace_artwork_is_reserved( $product_id ) {
    return in_array( get_post_meta( (int) $product_id, 'painting_availability_status', true ), [ 'reserved', 'gallery-reserved' ], true );
}

/** The reserved purchase link of an artwork: its Art Post + the key. '' without an Art Post */
function monospace_reserved_purchase_url( $product_id ) {
    $post_id = monospace_artwork_post_id( $product_id );
    return $post_id ? add_query_arg( 'reserved_key', monospace_generate_reserved_key( $product_id ), get_permalink( $post_id ) ) : '';
}

/** The address a product's QR code leads to ('' when there is no Art Post) - see the file header for $mode */
function monospace_artwork_qr_url( $product_id, $mode = 'page' ) {
    $post_id = monospace_artwork_post_id( $product_id );
    if ( ! $post_id ) return '';
    if ( $mode === 'buy' && monospace_artwork_is_reserved( $product_id ) ) return monospace_reserved_purchase_url( $product_id );
    return get_permalink( $post_id );
}

/** The words under a code: "Scan to buy" when 'buy' mode and the piece can be bought (available, reserved, Gallery QR), else "Scan to view" */
function monospace_artwork_qr_caption( $product_id, $mode = 'page' ) {
    if ( $mode === 'buy' && function_exists( 'wc_get_product' ) ) {
        $status  = (string) get_post_meta( (int) $product_id, 'painting_availability_status', true );
        $product = wc_get_product( $product_id );
        if ( $product && $product->is_in_stock() && in_array( $status, [ '', 'available', 'reserved', 'gallery-reserved' ], true ) ) return __( 'Scan to buy', 'monospace-art' );
    }
    return __( 'Scan to view', 'monospace-art' );
}

/** The QR code (inline SVG) of a product, or '' - $args as monospace_qr_svg(); white background by default so it scans on any page */
function monospace_artwork_qr_svg( $product_id, $mode = 'page', $args = [] ) {
    $url = monospace_artwork_qr_url( $product_id, $mode );
    return $url === '' ? '' : monospace_qr_svg( $url, array_merge( [ 'bg' => '#ffffff', 'margin' => 2 ], (array) $args ) );
}

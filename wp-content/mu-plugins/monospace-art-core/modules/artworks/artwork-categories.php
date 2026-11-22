<?php
/**
 * Details drive categories (2026-10-19, owner's wish: so categories can't drift from the details).
 *
 * An artwork's format, medium and size decide three of its post categories - the same rule New artwork
 * uses: the format's category (painting, drawing, …), the medium's (acrylic, ink, pencil, …:
 * monospace_artwork_medium_category()) and "miniature" for 4" × 4" (and the 2- / 3-piece 4" × 4" sets).
 * Whenever the details are saved - the Quick Artwork Editor, the Artwork Editor, New artwork, anything that
 * goes through QuickProduct\ProductSyncer, which stores them as the post's _qp_attributes - the post gets
 * the categories the new details call for, and loses the ones only the old details called for. Categories
 * added by hand (a second medium, a look, a place…) are never removed.
 *
 * Hooked on the update_post_metadata / add_post_metadata filters for _qp_attributes: they run on every
 * save, before WordPress skips an unchanged value, while the old value can still be read.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_MINIATURE_SIZES = [ '4x4', '2x4x4', '3x4x4' ];
const MONOSPACE_MINIATURE_SET_SIZES = [ '2x4x4', '3x4x4' ]; // diptych, triptych: one piece with its own price

/**
 * What is a miniature - the one test for every part of the site (owner, 2026-11-14): the SKU's type letter M
 * (MNSPC-2026-M-0753). The letter is given when the artwork is made (a 4" × 4", or the miniature category:
 * QuickProduct's SKU generator); the "miniature" post category follows the same details. Product tags are not a
 * test: no artwork tool writes them (the volume discount read the tag "miniature" until 2026-11-14 and missed
 * every miniature made after 2026-11-04).
 */

/** The type letter of a SKU (MNSPC-2026-M-0753 -> M); '' when it has none */
function monospace_sku_type( $sku ) {
    return preg_match( '/^[A-Z]+-\d{4}-([A-Z])-\d+$/', (string) $sku, $m ) ? $m[1] : '';
}

/** Is this product a miniature - a single one or a set? */
function monospace_is_miniature_product( $product_id ) {
    return $product_id && monospace_sku_type( get_post_meta( $product_id, '_sku', true ) ) === 'M';
}

/** Is this product a miniature set (diptych, triptych)? */
function monospace_is_miniature_set( $product_id ) {
    if ( ! monospace_is_miniature_product( $product_id ) ) return false;
    $sizes = wp_get_post_terms( $product_id, 'pa_size', [ 'fields' => 'slugs' ] );
    return ! is_wp_error( $sizes ) && (bool) array_intersect( $sizes, MONOSPACE_MINIATURE_SET_SIZES );
}

/**
 * Does a product belong to the group a price rule names (the Sales Dashboard's rule keys)? "miniature" = a single
 * miniature by its SKU - a set is priced as one piece and never counts towards a deal for single miniatures.
 * Any other key: a product category, a product tag, or attr:pa_taxonomy=slug.
 */
function monospace_product_in_group( $product_id, $key ) {
    if ( ! $product_id || ! $key ) return false;
    if ( $key === 'miniature' ) return monospace_is_miniature_product( $product_id ) && ! monospace_is_miniature_set( $product_id );
    if ( has_term( $key, 'product_cat', $product_id ) || has_term( $key, 'product_tag', $product_id ) ) return true;
    if ( strpos( $key, 'attr:' ) === 0 && strpos( $key, '=' ) !== false ) {
        list( $tax, $slug ) = array_map( 'sanitize_text_field', array_map( 'trim', explode( '=', substr( $key, 5 ), 2 ) ) );
        $terms = $tax && $slug ? wp_get_post_terms( $product_id, $tax, [ 'fields' => 'slugs' ] ) : [];
        return ! is_wp_error( $terms ) && in_array( $slug, (array) $terms, true );
    }
    return false;
}

/** The category slugs a set of details calls for (format, medium, miniature) */
function monospace_artwork_detail_categories( $attrs ) {
    $attrs = is_array( $attrs ) ? $attrs : [];
    $slugs = [];
    if ( ! empty( $attrs['pa_format'] ) && function_exists( 'monospace_artwork_format_category' ) ) $slugs[] = monospace_artwork_format_category( $attrs['pa_format'] );
    if ( ! empty( $attrs['pa_medium'] ) && function_exists( 'monospace_artwork_medium_category' ) ) {
        $term = get_term_by( 'slug', $attrs['pa_medium'], 'pa_medium' );
        $slugs[] = $term ? monospace_artwork_medium_category( $term->name ) : '';
    }
    if ( in_array( $attrs['pa_size'] ?? '', MONOSPACE_MINIATURE_SIZES, true ) && get_category_by_slug( 'miniature' ) ) $slugs[] = 'miniature';
    return array_values( array_unique( array_filter( $slugs ) ) );
}

/** Bring the post's categories in line with its details ($old: the details before this save) */
function monospace_artwork_follow_details( $post_id, $old, $new ) {
    $want = monospace_artwork_detail_categories( $new );
    $drop = array_diff( monospace_artwork_detail_categories( $old ), $want );
    $cats = array_map( 'intval', wp_get_post_categories( $post_id ) );
    $next = $cats;
    foreach ( $drop as $slug ) { $c = get_category_by_slug( $slug ); if ( $c ) $next = array_diff( $next, [ (int) $c->term_id ] ); }
    foreach ( $want as $slug ) { $c = get_category_by_slug( $slug ); if ( $c ) $next[] = (int) $c->term_id; }
    $next = array_values( array_unique( $next ) );
    // "Uncategorized" goes once there is a real category
    $default = (int) get_option( 'default_category' );
    if ( count( $next ) > 1 ) $next = array_values( array_diff( $next, [ $default ] ) );
    sort( $next ); $sorted = $cats; sort( $sorted );
    if ( $next !== $sorted ) wp_set_post_categories( $post_id, $next );
}

add_filter( 'update_post_metadata', function ( $check, $post_id, $key, $value ) {
    if ( $key === '_qp_attributes' && get_post_type( $post_id ) === 'post' ) {
        monospace_artwork_follow_details( $post_id, get_post_meta( $post_id, '_qp_attributes', true ), $value );
    }
    return $check;
}, 10, 4 );

add_filter( 'add_post_metadata', function ( $check, $post_id, $key, $value ) {
    if ( $key === '_qp_attributes' && get_post_type( $post_id ) === 'post' ) {
        monospace_artwork_follow_details( $post_id, [], $value );
    }
    return $check;
}, 10, 4 );

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

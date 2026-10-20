<?php
/**
 * What makes a post an artwork (2026-10-19).
 *
 * An artwork is a post linked to an existing WooCommerce product: its _qp_product_id. There is no separate
 * category or flag - the link is the flag. Since 2026-10-19 the link is only stored when there is one (the
 * Quick Artwork Editor deletes it instead of saving it empty), so "has _qp_product_id" = "is an artwork",
 * also in a meta_query (EXISTS / NOT EXISTS).
 *
 * monospace_is_artwork( $post )   the one test every tool uses (the artwork toolbar, …)
 * monospace_sku_number( $sku )    the SKU's number, the way the owner identifies a piece (MNSPC-2026-P-0426 → 426)
 *
 * wp-admin's Posts list gets an "Artworks / Other posts" filter and a "#" column with the SKU's number
 * (admin only - nothing changes for visitors).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Is this post an artwork - a post linked to a product that still exists? (default: the current post) */
function monospace_is_artwork( $post = null ) {
    $post = get_post( $post );
    if ( ! $post || $post->post_type !== 'post' || ! function_exists( 'monospace_get_post_product_id' ) ) return false;
    return monospace_get_post_product_id( $post->ID ) > 0;
}

/**
 * Does an artwork with this availability need a price? (owner's rule, 2026-10-20) Yes when it can be bought or
 * is held for a buyer: available, reserved, gallery-reserved (Gallery QR). No for sold, private (the artist's
 * collection) and gallery (on view, sold through the gallery) - their price may be left empty, shown as "--".
 * The editors (Quick Artwork Editor, New artwork, Artwork Editor, the artwork toolbar) all ask this.
 */
function monospace_price_required( $status ) {
    return ! in_array( (string) $status, monospace_price_optional_statuses(), true );
}

/** The availabilities whose price may be empty */
function monospace_price_optional_statuses() {
    return [ 'sold', 'private', 'gallery' ];
}

/** The number at the end of a SKU, without leading zeros ('' when it has none). */
function monospace_sku_number( $sku ) {
    return preg_match( '/(\d+)\D*$/', (string) $sku, $m ) ? ( ltrim( $m[1], '0' ) ?: '0' ) : '';
}

// Posts list: All posts / Artworks / Other posts
add_action( 'restrict_manage_posts', function ( $post_type ) {
    if ( $post_type !== 'post' ) return;
    $kind = sanitize_key( $_GET['ms_kind'] ?? '' );
    echo '<label class="screen-reader-text" for="ms-kind">Artworks or other posts</label><select name="ms_kind" id="ms-kind">';
    foreach ( [ '' => 'Artworks and other posts', 'artwork' => 'Artworks', 'other' => 'Other posts' ] as $v => $label ) {
        printf( '<option value="%s"%s>%s</option>', esc_attr( $v ), selected( $kind, $v, false ), esc_html( $label ) );
    }
    echo '</select>';
} );

add_action( 'pre_get_posts', function ( $q ) {
    if ( ! is_admin() || ! $q->is_main_query() || ( $GLOBALS['pagenow'] ?? '' ) !== 'edit.php' || $q->get( 'post_type' ) !== 'post' ) return;
    $kind = sanitize_key( $_GET['ms_kind'] ?? '' );
    if ( $kind !== 'artwork' && $kind !== 'other' ) return;
    $mq   = (array) $q->get( 'meta_query' );
    $mq[] = [ 'key' => '_qp_product_id', 'compare' => $kind === 'artwork' ? 'EXISTS' : 'NOT EXISTS' ];
    $q->set( 'meta_query', $mq );
} );

// Posts list: the SKU's number, after the title
add_filter( 'manage_post_posts_columns', function ( $cols ) {
    $out = [];
    foreach ( $cols as $k => $v ) {
        $out[ $k ] = $v;
        if ( $k === 'title' ) $out['ms_sku'] = '#';
    }
    return $out;
} );

add_action( 'manage_post_posts_custom_column', function ( $col, $post_id ) {
    if ( $col !== 'ms_sku' ) return;
    $pid = function_exists( 'monospace_get_post_product_id' ) ? monospace_get_post_product_id( $post_id ) : 0;
    if ( ! $pid ) { echo '<span aria-hidden="true">–</span><span class="screen-reader-text">Not an artwork</span>'; return; }
    $sku = get_post_meta( $pid, '_sku', true );
    $num = monospace_sku_number( $sku );
    printf( '<strong title="%s">%s</strong>', esc_attr( $sku ), esc_html( $num !== '' ? $num : '?' ) );
}, 10, 2 );

add_action( 'admin_head-edit.php', function () {
    if ( ( $_GET['post_type'] ?? 'post' ) !== 'post' ) return;
    echo '<style>.wp-list-table .column-ms_sku { width: 4em; } .wp-list-table td.column-ms_sku strong { font-size: 15px; }</style>';
} );

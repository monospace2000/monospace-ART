<?php
/**
 * The Art Filter's results (admin-ajax.php?action=ms_filter_products - for everyone, so the page itself stays cached).
 *
 * Asks with: pa_format / pa_medium / pa_surface / pa_size / pa_year (a term slug; <name>_not=1 = exclude it),
 * availability (available | reserved | sold | private | gallery; availability_not=1 = exclude), price_min / price_max
 * (with Available only), order (ASC = oldest first), sort (newest | oldest | price-asc | price-desc | size-asc |
 * size-desc | random, with seed), view (grid | list | detail | cards), page (cards: 12 at a time).
 * Answers { html, count (the whole match), more (cards: another page to come) }.
 *
 * Availability is Core's (monospace_artwork_status()): Available = in stock and not sold or private; the green dot =
 * available, or at a gallery.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_ajax_ms_filter_products', 'ms_filter_products_callback' );
add_action( 'wp_ajax_nopriv_ms_filter_products', 'ms_filter_products_callback' );

/** Does this original match the availability asked for? */
function monospace_art_filter_matches_availability( $product_id, $avail ) {
    $status = monospace_artwork_status( $product_id );
    switch ( $avail ) {
        case '':          return true;
        case 'available': return monospace_artwork_in_stock( $product_id ) && ! in_array( $status, [ 'sold', 'private' ], true );
        default:          return $status === $avail;
    }
}

/** The matching originals, in the order asked for */
function monospace_art_filter_results( $req ) {
    $tax_query = [];
    foreach ( [ 'pa_medium', 'pa_surface', 'pa_size', 'pa_year', 'pa_format' ] as $tax ) {
        if ( empty( $req[ $tax ] ) ) continue;
        $tax_query[] = [ 'taxonomy' => $tax, 'field' => 'slug', 'terms' => sanitize_text_field( $req[ $tax ] ), 'operator' => empty( $req[ $tax . '_not' ] ) ? 'IN' : 'NOT IN' ];
    }
    $ids = monospace_art_filter_product_ids();
    if ( ! $ids ) return [];
    $ids = get_posts( [
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'post__in'       => $ids,
        'tax_query'      => $tax_query,
        'orderby'        => 'date',
        'order'          => ( $req['order'] ?? '' ) === 'ASC' ? 'ASC' : 'DESC',
    ] );

    // Availability, and the price range with Available (the prices as sold - get_price() runs the Sales Dashboard's filters)
    $avail = sanitize_key( $req['availability'] ?? '' );
    $not   = ! empty( $req['availability_not'] );
    $range = $avail === 'available' && ! $not && ! empty( $req['price_min'] ) && ! empty( $req['price_max'] ) ? [ (float) $req['price_min'], (float) $req['price_max'] ] : null;
    $ids   = array_values( array_filter( $ids, function ( $id ) use ( $avail, $not, $range ) {
        if ( monospace_art_filter_matches_availability( $id, $avail ) === $not ) return false;
        if ( ! $range ) return true;
        $product = wc_get_product( $id );
        $price   = $product ? (float) $product->get_price() : 0;
        return $price >= $range[0] && $price <= $range[1];
    } ) );

    // Sort: newest / oldest come from the query; price, size and random here (random: a shuffle fixed by the seed, so it
    // stays put while filters change). Without a price / size: last.
    $sort = sanitize_key( $req['sort'] ?? '' );
    if ( $sort === 'random' ) {
        $seed = absint( $req['seed'] ?? 0 ) ?: (int) wp_date( 'Ymd' );
        usort( $ids, fn( $a, $b ) => strcmp( md5( $seed . '-' . $a ), md5( $seed . '-' . $b ) ) );
    } elseif ( preg_match( '/^(price|size)-(asc|desc)$/', $sort, $m ) ) {
        $value = function ( $id ) use ( $m ) {
            if ( $m[1] === 'price' ) { $p = wc_get_product( $id ); $v = $p ? (float) $p->get_price() : 0; return $v > 0 ? $v : null; }
            $terms = get_the_terms( $id, 'pa_size' );
            return $terms && ! is_wp_error( $terms ) && preg_match_all( '/\d+(?:\.\d+)?/', $terms[0]->name, $n ) && count( $n[0] ) >= 2 ? array_product( array_map( 'floatval', $n[0] ) ) : null;
        };
        $known = $unknown = [];
        foreach ( $ids as $i => $id ) { $v = $value( $id ); if ( $v === null ) $unknown[] = $id; else $known[] = [ $v, $i, $id ]; }
        usort( $known, fn( $a, $b ) => ( $m[2] === 'desc' ? $b[0] <=> $a[0] : $a[0] <=> $b[0] ) ?: $a[1] <=> $b[1] );
        $ids = array_merge( array_column( $known, 2 ), $unknown );
    }
    return $ids;
}

/** A result's picture: the product's own, else the artwork's (featured, first in it, the art placeholder) - never an empty spot */
function monospace_art_filter_thumb( $product_id, $post_id ) {
    $img = get_the_post_thumbnail( $product_id, 'medium' );
    return $img !== '' ? $img : ( function_exists( 'monospace_thumbnail_html' ) ? monospace_thumbnail_html( $post_id, 'medium' ) : '' );
}

/** One result, as the view shows it */
function monospace_art_filter_item_html( $product_id, $view ) {
    $post_id = (int) get_post_meta( $product_id, '_related_post_id', true );
    if ( ! $post_id ) return '';
    $url    = esc_url( get_permalink( $post_id ) );
    $title  = esc_html( get_the_title( $product_id ) );
    $status = monospace_artwork_status( $product_id );

    // Price card view: the artwork's standalone price card
    if ( $view === 'cards' ) return '<div class="ms-product-card">' . do_shortcode( '[price_card_standalone post="' . $post_id . '"]' ) . '</div>';

    // Detail list view: Core's artwork row - picture on the left, details on the right (the same as the archive's)
    if ( $view === 'detail' ) return function_exists( 'monospace_artwork_row_html' ) ? monospace_artwork_row_html( $post_id ) : '';

    // List view: picture, title, status (the price card's status colors) and price
    if ( $view === 'list' ) {
        $names   = monospace_artwork_statuses();
        $product = wc_get_product( $product_id );
        $price   = $product ? $product->get_price() : 0;
        $label   = $status === 'gallery-reserved' ? $names['gallery'] : ( $names[ $status ] ?? ucfirst( $status ) );
        return '<div class="ms-product-row"><a href="' . $url . '" class="ms-product-row-thumb">' . monospace_art_filter_thumb( $product_id, $post_id ) . '</a>'
             . '<div class="ms-product-row-text"><a href="' . $url . '" class="ms-product-row-title">' . $title . '</a>'
             . '<span class="status-label status-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>'
             . ( $status === 'available' && $price ? '<span class="ms-product-row-price">' . wp_kses_post( wc_price( $price ) ) . '</span>' : '' )
             . '</div></div>';
    }

    // Grid: the picture, with the green dot when it's available (or at a gallery) and an M when it's a miniature
    $dot  = $status === 'gallery' || monospace_art_filter_matches_availability( $product_id, 'available' );
    $mini = function_exists( 'monospace_is_miniature' ) && monospace_is_miniature( $post_id ); // an "M" opposite the dot
    return '<div class="ms-product-item"><a href="' . $url . '"><div class="ms-product-item-pic">' . monospace_art_filter_thumb( $product_id, $post_id )
         . ( $dot ? '<span class="ms-availability-dot"></span>' : '' )
         . ( $mini ? '<span class="ms-miniature-mark" title="Miniature" aria-label="Miniature">M</span>' : '' ) . '</div><div class="ms-product-item-title">' . $title . '</div></a></div>';
}

function ms_filter_products_callback() {
    $req  = wp_unslash( $_REQUEST );
    $ids  = monospace_art_filter_results( $req );
    $view = in_array( $req['view'] ?? '', [ 'list', 'detail', 'cards' ], true ) ? $req['view'] : 'grid';
    if ( $view === 'cards' && ! shortcode_exists( 'price_card_standalone' ) ) $view = 'grid';
    // Real cards in this ajax answer, not the editor's placeholders (Core's monospace_is_editor_context())
    if ( $view === 'cards' ) add_filter( 'monospace_is_editor_context', '__return_false' );

    // Price card view: 12 cards at a time (a card takes ~40 ms to draw - all 300+ at once took 14 s); "Show more" asks for
    // the next page. The count stays the whole match.
    $count = count( $ids );
    $more  = false;
    // Detail list: 24 at a time, the same way
    if ( $view === 'cards' || $view === 'detail' ) {
        $per  = $view === 'cards' ? 12 : 24;
        $page = max( 1, absint( $req['page'] ?? 1 ) );
        $more = $count > $page * $per;
        $ids  = array_slice( $ids, ( $page - 1 ) * $per, $per );
    }

    $html = implode( '', array_map( fn( $id ) => monospace_art_filter_item_html( $id, $view ), $ids ) );
    wp_send_json( [
        'html'  => $html !== '' ? $html : '<p class="ms-no-matches">No matches.</p>',
        'count' => $count,
        'more'  => $more,
    ] );
}

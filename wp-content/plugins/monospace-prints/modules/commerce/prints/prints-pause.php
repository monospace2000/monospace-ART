<?php
/**
 * Show prints / Hide prints (2026-10-17 as "Pause / Resume"; 2026-10-19 renamed, and the framed and the
 * unframed print can be hidden on their own as well as together - owner's wish).
 *
 * Hidden = off the price card and the site, nothing deleted: every in-stock variation of that print
 * product is set out of stock, and its IDs are kept so Show puts back exactly those (a size made out of
 * stock by hand stays so). Products, prices, mockups and the Printful link stay. Keep them synced in
 * Printful: unsync / ignore there only stops fulfilment, not sales. Out of stock = gone from the price
 * card's tab, "Prints available", "See a preview" and the market terminal.
 *
 * State: post meta _ms_prints_paused = [ 'framed' => [ variation IDs ], 'unframed' => [ … ] ] - a type
 * present = hidden ([ 0 ] when it had nothing in stock). The 2026-10-17 format (one flat list for the
 * whole artwork) is read as "every linked print hidden" (monospace_prints_hidden()).
 * The post is in the "print" category while at least one of its prints is shown; with all hidden it
 * leaves it, and anything that puts it back (the Quick Artwork Editor's save, the block editor's
 * category panel, Quick Edit) is undone at once (set_object_terms below).
 *
 * Naming, the same on every screen: Show prints / Hide prints, Show framed print / Hide framed print
 * (… unframed print); states Shown / Hidden.
 *
 * Used by Prints > All prints (row button), the Quick Artwork Editor ("Show prints" switch) and
 * Artworks > Artwork Editor (in the row, the Prints panel, bulk): AJAX monospace_prints_pause,
 * op = hide | show (old names pause | resume still work), types[] = framed / unframed (none = both),
 * nonce monospace_prints_pause.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The artwork's linked print products that exist: [ type => product ID ] */
function monospace_prints_linked( $post_id ) {
    $out = [];
    foreach ( [ 'unframed' => '_print_product_id', 'framed' => '_framed_print_product_id' ] as $type => $key ) {
        $pid = (int) get_post_meta( $post_id, $key, true );
        if ( $pid && wc_get_product( $pid ) ) $out[ $type ] = $pid;
    }
    return $out;
}

/** The artwork's print product IDs (unframed, framed) that exist */
function monospace_prints_of_artwork( $post_id ) {
    return array_values( monospace_prints_linked( $post_id ) );
}

/** The hidden prints: [ type => [ variation IDs set out of stock ] ] */
function monospace_prints_hidden( $post_id ) {
    $saved = get_post_meta( $post_id, '_ms_prints_paused', true );
    if ( ! is_array( $saved ) || ! $saved ) return [];
    if ( ! isset( $saved['framed'] ) && ! isset( $saved['unframed'] ) ) {
        // The first format: one list for the whole artwork = every linked print hidden
        $hidden = [];
        $linked = monospace_prints_linked( $post_id );
        foreach ( $linked as $type => $pid ) $hidden[ $type ] = [];
        foreach ( array_map( 'intval', $saved ) as $vid ) {
            $parent = $vid ? (int) wp_get_post_parent_id( $vid ) : 0;
            $type = array_search( $parent, $linked, true );
            if ( $type !== false ) $hidden[ $type ][] = $vid;
        }
        foreach ( $hidden as $type => $vids ) if ( ! $vids ) $hidden[ $type ] = [ 0 ];
        return $hidden;
    }
    return array_intersect_key( $saved, [ 'framed' => 1, 'unframed' => 1 ] );
}

function monospace_prints_save_hidden( $post_id, array $hidden ) {
    $hidden ? update_post_meta( $post_id, '_ms_prints_paused', $hidden ) : delete_post_meta( $post_id, '_ms_prints_paused' );
}

/** Is that print hidden ($type), or - no type - every linked print? */
function monospace_prints_is_hidden( $post_id, $type = null ) {
    $hidden = monospace_prints_hidden( $post_id );
    if ( $type ) return isset( $hidden[ $type ] );
    $linked = monospace_prints_linked( $post_id );
    return $linked && ! array_diff_key( $linked, $hidden );
}

/** Older name: every linked print hidden */
function monospace_prints_is_paused( $post_id ) {
    return monospace_prints_is_hidden( $post_id );
}

/**
 * Hide ($hide true) or show an artwork's prints - $types: [ 'framed', 'unframed' ], null = both.
 * Returns how many variations changed.
 */
function monospace_prints_set_hidden( $post_id, $hide, $types = null ) {
    $linked = monospace_prints_linked( $post_id );
    if ( ! $linked ) throw new Exception( 'This artwork has no prints.' );
    $types = $types ? array_intersect_key( $linked, array_flip( (array) $types ) ) : $linked;
    if ( ! $types ) throw new Exception( 'This artwork has no such print.' );

    $hidden  = monospace_prints_hidden( $post_id );
    $changed = 0;
    foreach ( $types as $type => $pid ) {
        if ( $hide && ! isset( $hidden[ $type ] ) ) {
            $vids = [];
            foreach ( wc_get_product( $pid )->get_children() as $vid ) {
                $v = wc_get_product( $vid );
                if ( ! $v || ! $v->is_in_stock() ) continue;
                $v->set_stock_status( 'outofstock' );
                $v->save();
                $vids[] = (int) $vid;
            }
            $hidden[ $type ] = $vids ?: [ 0 ];
            $changed += count( $vids );
        } elseif ( ! $hide && isset( $hidden[ $type ] ) ) {
            foreach ( $hidden[ $type ] as $vid ) {
                $v = $vid ? wc_get_product( $vid ) : null;
                if ( ! $v ) continue;
                $v->set_stock_status( 'instock' );
                $v->save();
                $changed++;
            }
            unset( $hidden[ $type ] );
        } else {
            continue;
        }
        WC_Product_Variable::sync( $pid );
        wc_delete_product_transients( $pid );
    }
    // Saved before the category changes, so the guard below sees the new state
    monospace_prints_save_hidden( $post_id, $hidden );

    // In "print" while at least one print is shown
    $print_cat = get_term_by( 'slug', 'print', 'category' );
    if ( $print_cat ) {
        if ( monospace_prints_is_hidden( $post_id ) ) wp_remove_object_terms( $post_id, (int) $print_cat->term_id, 'category' );
        else wp_set_post_categories( $post_id, [ (int) $print_cat->term_id ], true );
    }
    delete_transient( 'ms_pf_index' );
    if ( function_exists( 'sg_cachepress_purge_everything' ) ) sg_cachepress_purge_everything();
    return $changed;
}

/** Older name: hide / show both */
function monospace_prints_set_paused( $post_id, $pause ) {
    return monospace_prints_set_hidden( $post_id, $pause );
}

/** The state in words, for messages: "Prints hidden", "Framed print hidden", … */
function monospace_prints_hidden_words( $post_id ) {
    $linked = monospace_prints_linked( $post_id );
    $hidden = array_intersect_key( monospace_prints_hidden( $post_id ), $linked );
    if ( ! $hidden ) return 'Prints shown';
    if ( ! array_diff_key( $linked, $hidden ) ) return 'Prints hidden';
    return ( isset( $hidden['framed'] ) ? 'Framed' : 'Unframed' ) . ' print hidden';
}

// With every print hidden, the post stays out of "print" whatever sets its categories
add_action( 'set_object_terms', function ( $object_id, $terms, $tt_ids, $taxonomy ) {
    static $busy = false;
    if ( $busy || $taxonomy !== 'category' || get_post_type( $object_id ) !== 'post' || ! monospace_prints_is_hidden( $object_id ) ) return;
    $print_cat = get_term_by( 'slug', 'print', 'category' );
    if ( ! $print_cat || ! in_array( (int) $print_cat->term_taxonomy_id, array_map( 'intval', (array) $tt_ids ), true ) ) return;
    $busy = true;
    wp_remove_object_terms( $object_id, (int) $print_cat->term_id, 'category' );
    $busy = false;
}, 10, 4 );

add_action( 'wp_ajax_monospace_prints_pause', function () {
    check_ajax_referer( 'monospace_prints_pause' );
    $post_id = absint( $_POST['post_id'] ?? 0 );
    if ( ! $post_id || ! current_user_can( 'edit_products' ) || ! current_user_can( 'edit_post', $post_id ) ) wp_send_json_error( 'Not allowed' );
    $op = [ 'pause' => 'hide', 'resume' => 'show' ][ sanitize_key( $_POST['op'] ?? '' ) ] ?? sanitize_key( $_POST['op'] ?? '' );
    if ( ! in_array( $op, [ 'hide', 'show' ], true ) ) wp_send_json_error( 'Unknown operation' );
    $types = array_values( array_intersect( (array) ( $_POST['types'] ?? [] ), [ 'framed', 'unframed' ] ) );
    try {
        $n = monospace_prints_set_hidden( $post_id, $op === 'hide', $types ?: null );
        $words = monospace_prints_hidden_words( $post_id );
        wp_send_json_success( [
            'hidden'  => monospace_prints_is_hidden( $post_id ),
            'paused'  => monospace_prints_is_hidden( $post_id ), // older name
            'types'   => [ 'framed' => monospace_prints_is_hidden( $post_id, 'framed' ), 'unframed' => monospace_prints_is_hidden( $post_id, 'unframed' ) ],
            'message' => $words . ( $words === 'Prints shown' ? " - for sale on the price card and the site ($n options)." : " - off the price card and the site. Nothing is deleted ($n options)." ),
        ] );
    } catch ( Exception $e ) {
        wp_send_json_error( $e->getMessage() );
    }
} );

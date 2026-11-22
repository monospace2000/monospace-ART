<?php
/**
 * Postcards for the artworks (2026-11-21): the same 4x6 postcard the Sightings drawings sell, added as one more option to every artwork
 * that has prints (an unframed or framed print product), whether its prints are shown or hidden.
 *
 *   monospace_prints_postcards_start()     start (or restart) the background job   (the paced runner: prints-rollout.php)
 *   monospace_prints_postcards_status()    how far it is
 *   monospace_prints_postcards_stop()      stop it
 *
 * Step 1 - the job makes each artwork's postcard through the normal print job (monospace_prints_queue(): WooCommerce product, Printful
 * sync, mockup) a few at a time. The new postcard is made HIDDEN (request 'hidden'), so nothing changes on the site while it runs.
 * Step 2 - when every postcard is made, the postcard is SHOWN for each artwork that has a shown print (unframed or framed), in one
 * go with one cache purge. An artwork whose prints are hidden keeps its postcard hidden; Show prints later shows it with the rest.
 * Never miniatures; never the Sightings stand-ins (they have their own postcards).
 * An artwork that cannot get one (no clean print file, too small) is listed in the status; the job leaves it and carries on.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_PRINTS_POSTCARD_OPTION = 'monospace_prints_postcard_rollout';

/** Artwork post IDs that have an unframed or framed print product, minus miniatures and the Sightings stand-ins */
function monospace_prints_postcards_artworks() {
    global $wpdb;
    $ids = array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'post' AND p.post_status NOT IN ( 'trash', 'auto-draft' )
        WHERE pm.meta_key IN ( '_print_product_id', '_framed_print_product_id' ) AND pm.meta_value <> '' ORDER BY pm.post_id" ) );
    $out = [];
    foreach ( $ids as $id ) {
        if ( get_post_meta( $id, '_ms_sp_key', true ) || has_category( 'miniature', $id ) ) continue;
        $has = false;
        foreach ( [ 'unframed', 'framed' ] as $type ) {
            $pid = (int) get_post_meta( $id, monospace_print_all_templates()[ $type ]['meta_key'], true );
            if ( $pid && get_post_type( $pid ) === 'product' && get_post_status( $pid ) !== 'trash' ) $has = true;
        }
        if ( $has ) $out[] = $id;
    }
    return $out;
}

/** Has one of the artwork's unframed / framed prints that is not hidden (the postcard does not count) */
function monospace_prints_has_shown_print( $post_id ) {
    foreach ( [ 'unframed', 'framed' ] as $type ) {
        $pid = (int) get_post_meta( $post_id, monospace_print_all_templates()[ $type ]['meta_key'], true );
        if ( $pid && wc_get_product( $pid ) && ! monospace_prints_is_hidden( $post_id, $type ) ) return true;
    }
    return false;
}

/** [ counts, [ post id => post id ] still to start, [ error lines ] ] */
function monospace_prints_postcards_scan( $o ) {
    $counts = [ 'total' => 0, 'on_sale' => 0, 'making' => 0, 'error' => 0, 'none' => 0 ];
    $todo = []; $errors = [];
    foreach ( monospace_prints_postcards_artworks() as $id ) {
        $counts['total']++;
        $job = monospace_prints_get_job( $id );
        $pid = (int) get_post_meta( $id, '_postcard_product_id', true );
        if ( $job ) {
            if ( $job['error'] !== '' ) { $counts['error']++; $errors[] = get_the_title( $id ) . " (#$id): " . $job['error']; }
            else $counts['making']++;
        } elseif ( $pid && wc_get_product( $pid ) ) {
            $counts['on_sale']++; // made (shown or hidden)
        } elseif ( (int) ( $o['tries'][ $id ] ?? 0 ) >= 2 ) {
            $counts['error']++;
            $why = '';
            foreach ( array_reverse( (array) $o['log'] ) as $line ) if ( strpos( $line[1], "$id: " ) === 0 ) { $why = substr( $line[1], strlen( "$id: " ) ); break; }
            $errors[] = get_the_title( $id ) . " (#$id): " . ( $why ?: 'could not be started' );
        } else {
            $counts['none']++;
            $todo[ $id ] = $id;
        }
    }
    return [ $counts, $todo, $errors ];
}

/** Show the postcard of every artwork that has a shown print (the job's last step). Returns how many were shown. */
function monospace_prints_postcards_activate() {
    $n = 0;
    foreach ( monospace_prints_postcards_artworks() as $id ) {
        if ( ! (int) get_post_meta( $id, '_ms_postcard_rollout', true ) ) continue;       // not one this job made
        if ( ! (int) get_post_meta( $id, '_postcard_product_id', true ) || monospace_prints_get_job( $id ) ) continue;
        if ( ! monospace_prints_is_hidden( $id, 'postcard' ) || ! monospace_prints_has_shown_print( $id ) ) continue;
        monospace_prints_set_hidden( $id, false, [ 'postcard' ], false );
        delete_post_meta( $id, '_ms_postcard_rollout' );
        $n++;
    }
    if ( function_exists( 'sg_cachepress_purge_everything' ) ) sg_cachepress_purge_everything();
    return $n;
}

add_filter( 'monospace_prints_rollouts', function ( $rollouts ) {
    $rollouts['postcards'] = [
        'option' => MONOSPACE_PRINTS_POSTCARD_OPTION,
        'scan'   => 'monospace_prints_postcards_scan',
        'start'  => function ( $id ) {
            $price = monospace_print_all_templates()['postcard']['variants']['4x6']['price'];
            monospace_prints_queue( (int) $id, [ 'postcard' => [ 'variants' => [ '4x6' => $price ] ], 'min_dpi' => 100, 'hidden' => true ] );
            update_post_meta( (int) $id, '_ms_postcard_rollout', time() );
        },
        'finish' => function ( &$o ) {
            $o['log'][] = [ time(), 'Postcards shown for ' . monospace_prints_postcards_activate() . ' artworks with shown prints.' ];
        },
    ];
    return $rollouts;
} );

function monospace_prints_postcards_start( $max_running = 4, $per_tick = 2, $every = 90 ) {
    monospace_prints_rollout_start( 'postcards', [], $max_running, $per_tick, $every );
}
function monospace_prints_postcards_status() { return monospace_prints_rollout_status( 'postcards' ); }
function monospace_prints_postcards_stop()   { monospace_prints_rollout_stop( 'postcards' ); }

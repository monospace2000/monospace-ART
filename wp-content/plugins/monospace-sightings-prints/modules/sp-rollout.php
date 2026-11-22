<?php
/**
 * Sightings prints: roll one canvas (the 4x6 postcard, '2x3') out to every drawing and layout, in the background (2026-11-21).
 * The pacing (a few at a time, never flooding Printful, two tries, stops by itself) is the prints plugin's shared runner
 * (monospace-prints, prints-rollout.php); this file only says what "still to do" means for the drawings.
 *   monospace_sp_rollout_start( '2x3' )   start (or restart) it     monospace_sp_rollout_status()   how far it is
 *   monospace_sp_rollout_stop()           stop it
 * Making prints stays opt-in: nothing runs unless this is started by hand.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_SP_ROLLOUT_OPTION = 'monospace_sp_rollout';

/** [ counts per state, [ "id-layout" => [ drawing, layout ] still to start ], [ error lines ] ] for a canvas */
function monospace_sp_rollout_counts( $canvas ) {
    $counts = [ 'total' => 0, 'on_sale' => 0, 'making' => 0, 'error' => 0, 'none' => 0 ];
    $todo = []; $errors = [];
    foreach ( monospace_sp_drawings() as $id => $d ) {
        foreach ( array_keys( monospace_sp_layouts() ) as $layout ) {
            $s = monospace_sp_layout_state( $id, $layout, $canvas );
            $counts['total']++;
            $counts[ $s['state'] ]++;
            if ( $s['state'] === 'none' ) $todo[ $id . '-' . $layout ] = [ $id, $layout ];
            if ( $s['state'] === 'error' ) $errors[] = 'No. ' . $d['number'] . ' ' . $layout . ': ' . ( $s['message'] ?? '' );
        }
    }
    return [ $counts, $todo, $errors ];
}

add_filter( 'monospace_prints_rollouts', function ( $rollouts ) {
    $rollouts['sightings'] = [
        'option' => MONOSPACE_SP_ROLLOUT_OPTION,
        'scan'   => function ( $o ) { return monospace_sp_rollout_counts( $o['canvas'] ); },
        'start'  => function ( $item, $o ) { monospace_sp_queue( $item[0], $item[1], $o['canvas'] ); },
        'tick'   => function () { delete_transient( 'ms_sp_feed_v2' ); }, // finished prints show up in the feed at once
    ];
    return $rollouts;
} );

function monospace_sp_rollout_start( $canvas = '2x3', $max_running = 6, $per_tick = 3, $every = 90 ) {
    monospace_sp_request( $canvas ); // throws for a canvas that is not set up
    monospace_prints_rollout_start( 'sightings', [ 'canvas' => $canvas ], $max_running, $per_tick, $every );
}

function monospace_sp_rollout_stop() {
    monospace_prints_rollout_stop( 'sightings' );
}

/** The saved state plus fresh counts */
function monospace_sp_rollout_status() {
    return monospace_prints_rollout_status( 'sightings' );
}

<?php
/**
 * A paced background rollout: make something for many artworks without flooding Printful (2026-11-21). One runner for every
 * rollout, so there is one place that decides how fast things go - the Sightings postcards (monospace-sightings-prints) and the
 * artworks' postcards (prints-postcard-rollout.php) both use it.
 *
 * A task on the site's own scheduler (Action Scheduler): every `every` seconds it asks the rollout what is still to do, and starts
 * a few more (`per_tick`) while fewer than `max_running` are running. It skips what already exists, tries a failing item twice and
 * then leaves it for the owner (listed in the status), and stops by itself when nothing is left to start or running. Nothing runs
 * unless a rollout is started by hand (the owner's "no automatic prints" rule).
 *
 * A rollout registers itself with the filter `monospace_prints_rollouts`:
 *   $rollouts['name'] = [
 *     'option' => option that keeps its state,
 *     'scan'   => function ( array $state ) { return [ [ 'making' => n, … counts ], [ key => payload, … still to start ], [ error lines ] ]; },
 *     'start'  => function ( $payload, array $state ) { … start ONE item (may throw) },
 *     'tick'   => optional function ( array &$state ) after every look,
 *     'finish' => optional function ( array &$state ) once, when everything is done,
 *   ];
 *   monospace_prints_rollout_start( 'name', [ extra settings kept in the state ] )   start (or restart)
 *   monospace_prints_rollout_status( 'name' )                                        the state plus fresh counts, or null
 *   monospace_prints_rollout_stop( 'name' )                                          stop it
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_PRINTS_ROLLOUT_HOOK  = 'monospace_prints_rollout_tick';
const MONOSPACE_PRINTS_ROLLOUT_GROUP = 'monospace-prints-rollout';

/** The registered rollouts: [ name => definition ] */
function monospace_prints_rollouts() {
    return (array) apply_filters( 'monospace_prints_rollouts', [] );
}

function monospace_prints_rollout_def( $name ) {
    $def = monospace_prints_rollouts()[ $name ] ?? null;
    if ( ! $def ) throw new Exception( "Unknown rollout '$name'." );
    return $def;
}

/** Start (or restart) a rollout. $args are kept in its state and handed to the rollout's own functions (e.g. [ 'canvas' => '2x3' ]). */
function monospace_prints_rollout_start( $name, array $args = [], $max_running = 6, $per_tick = 3, $every = 90 ) {
    if ( ! function_exists( 'as_schedule_single_action' ) ) throw new Exception( 'Action Scheduler is not available.' );
    $def = monospace_prints_rollout_def( $name );
    update_option( $def['option'], $args + [ 'max_running' => (int) $max_running, 'per_tick' => (int) $per_tick,
        'every' => (int) $every, 'started' => time(), 'done' => 0, 'tries' => [], 'log' => [], 'errors' => [], 'last' => null ], false );
    as_unschedule_all_actions( MONOSPACE_PRINTS_ROLLOUT_HOOK, [ $name ], MONOSPACE_PRINTS_ROLLOUT_GROUP );
    as_schedule_single_action( time() + 5, MONOSPACE_PRINTS_ROLLOUT_HOOK, [ $name ], MONOSPACE_PRINTS_ROLLOUT_GROUP );
}

function monospace_prints_rollout_stop( $name ) {
    $def = monospace_prints_rollout_def( $name );
    $o = get_option( $def['option'] );
    if ( is_array( $o ) ) { $o['done'] = time(); $o['log'][] = [ time(), 'Stopped by hand.' ]; update_option( $def['option'], $o, false ); }
    if ( function_exists( 'as_unschedule_all_actions' ) ) as_unschedule_all_actions( MONOSPACE_PRINTS_ROLLOUT_HOOK, [ $name ], MONOSPACE_PRINTS_ROLLOUT_GROUP );
}

/** The saved state plus fresh counts, or null when it never ran */
function monospace_prints_rollout_status( $name ) {
    $def = monospace_prints_rollout_def( $name );
    $o = get_option( $def['option'] );
    if ( ! is_array( $o ) ) return null;
    [ $counts, $todo, $errors ] = $def['scan']( $o );
    return $o + [ 'counts_now' => $counts, 'errors_now' => $errors ];
}

add_action( MONOSPACE_PRINTS_ROLLOUT_HOOK, function ( $name ) {
    $def = monospace_prints_rollouts()[ $name ] ?? null;
    if ( ! $def ) return;
    $o = get_option( $def['option'] );
    if ( ! is_array( $o ) || ! empty( $o['done'] ) ) return;
    [ $counts, $todo, $errors ] = $def['scan']( $o );

    $room = min( (int) $o['per_tick'], max( 0, (int) $o['max_running'] - (int) $counts['making'] ) );
    $started = 0; $left = 0;
    foreach ( $todo as $key => $payload ) {
        if ( (int) ( $o['tries'][ $key ] ?? 0 ) >= 2 ) continue;  // failed twice: left for the owner
        if ( $started >= $room ) { $left++; continue; }
        $o['tries'][ $key ] = (int) ( $o['tries'][ $key ] ?? 0 ) + 1;
        try {
            $def['start']( $payload, $o );
            $started++;
        } catch ( Throwable $e ) {
            $o['log'][] = [ time(), "$key: " . $e->getMessage() ];
            $left++;
        }
    }
    $o['last']   = [ 'time' => time(), 'counts' => $counts, 'started' => $started, 'left' => $left ];
    $o['errors'] = $errors;
    $o['log']    = array_slice( (array) $o['log'], -40 );
    if ( ! empty( $def['tick'] ) ) $def['tick']( $o );
    if ( $left === 0 && (int) $counts['making'] + $started === 0 ) {
        $o['done'] = time();
        $o['log'][] = [ time(), 'Finished.' ];
        if ( ! empty( $def['finish'] ) ) {
            try { $def['finish']( $o ); } catch ( Throwable $e ) { $o['log'][] = [ time(), 'Finishing step failed: ' . $e->getMessage() ]; }
        }
    }
    update_option( $def['option'], $o, false );
    if ( empty( $o['done'] ) ) as_schedule_single_action( time() + (int) $o['every'], MONOSPACE_PRINTS_ROLLOUT_HOOK, [ $name ], MONOSPACE_PRINTS_ROLLOUT_GROUP );
} );

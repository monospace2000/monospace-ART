<?php
/**
 * Error log cleanup, daily (owner, 2026-11-22: "keep it lean and mean, clean up regularly").
 *
 * PHP writes a php_errorlog file in the folder of the script that failed (SiteGround), so logs pile up per folder and
 * never shrink: wp-admin/php_errorlog had reached 126 MB (683,000 lines since 2025-10). Once a day this keeps every
 * error log on the hosting account (all sites under ~/www: php_errorlog, error_log, wp-content/debug.log, up to three
 * folders deep) to the last 30 days and at most 1 MB, newest lines kept. An empty log is removed.
 * Runs on the art site's WP-Cron; the other sites on the account share the same home folder, so they are covered too.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const MONOSPACE_LOG_KEEP_DAYS  = 30;
const MONOSPACE_LOG_KEEP_BYTES = 1048576;

// modules load on init, so schedule right away (an init hook added now would not run)
if ( ! wp_next_scheduled( 'monospace_log_cleanup' ) ) {
    wp_schedule_event( strtotime( 'tomorrow 09:20 UTC' ), 'daily', 'monospace_log_cleanup' );   // 4:20 AM New York
}
add_action( 'monospace_log_cleanup', 'monospace_log_cleanup' );

/** The error logs on the hosting account (the folder that holds every site's folder) */
function monospace_log_files() {
    $www = dirname( untrailingslashit( realpath( ABSPATH ) ), 2 );   // .../www (ABSPATH = .../www/<site>/public_html)
    $files = array();
    foreach ( array( '', '*/', '*/*/', '*/*/*/' ) as $depth ) {
        foreach ( array( 'php_errorlog', 'error_log', 'debug.log' ) as $name ) {
            foreach ( (array) glob( $www . '/*/public_html/' . $depth . $name ) as $f ) $files[] = $f;
        }
    }
    $files = array_values( array_unique( array_filter( $files, 'is_file' ) ) );
    // only real logs: never a file that merely has the name inside a backup or uploads folder
    return array_filter( $files, function ( $f ) {
        return ! preg_match( '#/(backups|uploads|node_modules|vendor)/#', $f ) && ( basename( $f ) !== 'debug.log' || strpos( $f, '/wp-content/debug.log' ) !== false );
    } );
}

/** Trim one log: drop lines dated before the cut-off, then keep at most the newest MONOSPACE_LOG_KEEP_BYTES. Returns bytes saved. */
function monospace_log_trim( $file, $now = null ) {
    $size = filesize( $file );
    if ( ! $size ) {
        @unlink( $file );
        return 0;
    }
    $cut = ( $now ?: time() ) - MONOSPACE_LOG_KEEP_DAYS * DAY_IN_SECONDS;
    $first = (string) file_get_contents( $file, false, null, 0, 64 );
    $oldest = preg_match( '/^\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2}) UTC\]/', $first, $m ) ? strtotime( $m[1] . ' UTC' ) : 0;
    if ( $size <= MONOSPACE_LOG_KEEP_BYTES && ( ! $oldest || $oldest >= $cut ) ) {
        return 0;   // small and recent: leave it
    }
    // read at most the newest 2 MB (a huge log is not loaded whole), keep whole lines dated after the cut-off
    $fh = fopen( $file, 'rb' );
    if ( ! $fh ) return 0;
    $read = min( $size, 2 * MONOSPACE_LOG_KEEP_BYTES );
    fseek( $fh, -$read, SEEK_END );
    $tail = (string) fread( $fh, $read );
    fclose( $fh );
    $lines = explode( "\n", $tail );
    if ( $read < $size ) array_shift( $lines );   // first line is cut in the middle
    $keep = array();
    $dated_ok = false;
    foreach ( $lines as $line ) {
        if ( preg_match( '/^\[(\d{2}-\w{3}-\d{4} \d{2}:\d{2}:\d{2}) UTC\]/', $line, $m ) ) {
            $dated_ok = strtotime( $m[1] . ' UTC' ) >= $cut;
        }
        if ( $dated_ok ) $keep[] = $line;   // undated lines (stack traces) follow the line before them
    }
    $out = implode( "\n", $keep );
    if ( strlen( $out ) > MONOSPACE_LOG_KEEP_BYTES ) {
        $out = substr( $out, -MONOSPACE_LOG_KEEP_BYTES );
        $out = substr( $out, (int) strpos( $out, "\n" ) + 1 );
    }
    $out = rtrim( $out, "\n" );
    if ( '' === $out ) {
        @unlink( $file );
        return $size;
    }
    file_put_contents( $file, $out . "\n", LOCK_EX );
    clearstatcache( true, $file );
    return $size - filesize( $file );
}

function monospace_log_cleanup() {
    $saved = 0;
    foreach ( monospace_log_files() as $file ) {
        $saved += monospace_log_trim( $file );
    }
    update_option( 'monospace_log_cleanup_last', array( 't' => time(), 'saved' => $saved ), false );
}

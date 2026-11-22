<?php
/**
 * HEIC photos become JPEGs on upload (2026-11-15, owner: "is there a way to add heic conversion on the server?").
 * The web server has no Imagick (so WordPress cannot read HEIC), but it has ffmpeg - the same one video-poster.php uses -
 * and ffmpeg 9 reads an iPhone HEIC (the tiled picture, the rotation). So when a HEIC/HEIF file is uploaded, it is turned
 * into a JPEG right after it arrives, before WordPress makes the sizes (priority 5: the watermark plugin, priority 10, then
 * sees the JPEG); the HEIC is not kept. If the conversion fails the upload is refused with a plain message, never a blank
 * Media Library tile. Works in any browser (Chrome does not convert by itself).
 *
 * Photos already in the library as HEIC are untouched. The New artwork screen's browser-side conversion (Safari) stays as it is.
 *
 * @package monospace-art-core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** HEIC/HEIF file -> JPEG next to it; returns the JPEG's path, or '' when it could not be made */
function monospace_heic_to_jpeg( $file ) {
    $ffmpeg = '';
    foreach ( [ '/usr/local/bin/ffmpeg', '/usr/bin/ffmpeg' ] as $path ) {
        if ( is_executable( $path ) ) { $ffmpeg = $path; break; }
    }
    if ( ! $ffmpeg || ! is_readable( $file ) || ! function_exists( 'exec' ) ) return '';
    $dir  = dirname( $file );
    $name = wp_unique_filename( $dir, preg_replace( '/\.hei[cf]s?$/i', '', basename( $file ) ) . '.jpg' );
    $jpg  = trailingslashit( $dir ) . $name;
    // One thread each: more fails on this host ("Resource temporarily unavailable"); the picture's rotation is applied by ffmpeg
    @exec( 'timeout 90 ' . escapeshellarg( $ffmpeg ) . ' -v error -y -threads 1 -filter_threads 1 -filter_complex_threads 1 -i ' . escapeshellarg( $file )
        . ' -frames:v 1 -q:v 2 ' . escapeshellarg( $jpg ) . ' 2>&1', $out, $code );
    if ( $code !== 0 || ! file_exists( $jpg ) || filesize( $jpg ) < 1000 ) {
        if ( file_exists( $jpg ) ) @unlink( $jpg );
        return '';
    }
    return $jpg;
}

add_filter( 'wp_handle_upload', function ( $upload ) {
    if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) ) return $upload;
    if ( ! preg_match( '/\.hei[cf]s?$/i', $upload['file'] ) && ! preg_match( '#^image/hei[cf]#i', (string) ( $upload['type'] ?? '' ) ) ) return $upload;
    $jpg = monospace_heic_to_jpeg( $upload['file'] );
    if ( $jpg === '' ) {
        @unlink( $upload['file'] );
        return [ 'error' => 'This HEIC photo could not be turned into a JPEG here. Export it as a JPEG (Preview > File > Export) and upload that.' ];
    }
    @unlink( $upload['file'] );
    return [ 'file' => $jpg, 'url' => preg_replace( '#[^/]+$#', rawurlencode( basename( $jpg ) ), $upload['url'] ), 'type' => 'image/jpeg' ];
}, 5 );

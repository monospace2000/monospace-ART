<?php
/**
 * Video previews (2026-11-10, owner: "video embed previews need help" - an uploaded video showed a black box with a play button on
 * the iPad). A <video> block from the media library has no poster, and Safari on iPhone / iPad doesn't load any of the file until
 * it is played, so there is nothing to draw. Two small things fix it for every uploaded video that has no poster of its own:
 *   preload="metadata"   the browser fetches the start of the file (not the whole video)
 *   #t=0.001 on the src  makes Safari show the first frame as the preview
 * playsinline keeps it playing in the page on iPhone. A video whose upload got a poster from Core (_ms_video_poster) uses that instead.
 * A video with a poster, and a video set to autoplay or preload "none", is left alone.
 * (YouTube / Vimeo embeds draw their own thumbnail.)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'render_block_core/video', function ( $html, $block = [] ) {
    if ( is_admin() || is_feed() || stripos( $html, '<video' ) === false || stripos( $html, ' poster=' ) !== false ) return $html;

    // The poster Core made when the video was uploaded (modules/utilities/video-poster.php)
    $poster = ! empty( $block['attrs']['id'] ) ? (int) get_post_meta( (int) $block['attrs']['id'], '_ms_video_poster', true ) : 0;
    $url    = $poster ? wp_get_attachment_image_url( $poster, 'full' ) : '';
    if ( $url ) $html = preg_replace( '/<video\b/i', '<video poster="' . esc_url( $url ) . '"', $html, 1 );

    return preg_replace_callback( '/<video\b([^>]*)>/i', function ( $m ) {
        $attrs = $m[1];
        if ( preg_match( '/\bpreload=["\']none["\']/i', $attrs ) || preg_match( '/\bautoplay\b/i', $attrs ) ) return $m[0];

        if ( ! preg_match( '/\bpreload=/i', $attrs ) ) $attrs .= ' preload="metadata"';
        if ( ! preg_match( '/\bplaysinline\b/i', $attrs ) ) $attrs .= ' playsinline';
        $attrs = preg_replace_callback( '/\bsrc=(["\'])([^"\'#]+)\1/i', fn( $s ) => 'src=' . $s[1] . $s[2] . '#t=0.001' . $s[1], $attrs, 1 );
        return '<video' . $attrs . '>';
    }, $html );
}, 10, 2 );

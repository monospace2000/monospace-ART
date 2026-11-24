<?php
/**
 * Video stills (Kit part since 2026-11-23; from the art site's Core video-poster.php and its theme's video-preview.php - edit them
 * there first). Poster for an uploaded video (2026-11-10, owner: "video embed previews need help"). Safari on iPhone / iPad draws nothing for a
 * video that has no poster until it is played - a black box with a play arrow. When a video is added to the media library, a still
 * from its first seconds is made with the server's ffmpeg and added to the library as "<video title> poster"; the video's meta
 * _ms_video_poster holds that picture's ID. The video block filter at the end of this file uses it as the poster
 * of a video block that has none of its own. A poster set in the block always wins.
 * No ffmpeg (or a failure) = nothing is made; the video then only gets the theme's first-frame fallback. Videos added before this
 * got their posters by hand.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'add_attachment', 'monospace_kit_video_poster' );

function monospace_kit_video_poster( $attachment_id ) {
    if ( strpos( (string) get_post_mime_type( $attachment_id ), 'video/' ) !== 0 || get_post_meta( $attachment_id, '_ms_video_poster', true ) ) return;
    if ( ! function_exists( 'exec' ) || in_array( 'exec', array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) ), true ) ) return;

    $ffmpeg = '';
    foreach ( [ '/usr/local/bin/ffmpeg', '/usr/bin/ffmpeg' ] as $path ) {
        if ( is_executable( $path ) ) { $ffmpeg = $path; break; }
    }
    $file = get_attached_file( $attachment_id );
    if ( ! $ffmpeg || ! $file || ! is_readable( $file ) ) return;

    $jpg = preg_replace( '/\.[^.\/]+$/', '', $file ) . '-poster.jpg';
    $jpg = dirname( $jpg ) . '/' . wp_unique_filename( dirname( $jpg ), basename( $jpg ) );

    // A second in (the very first frame is often black); a clip shorter than that falls back to its start. One thread: the host limits them
    foreach ( [ '1', '0' ] as $at ) {
        @exec( 'timeout 60 ' . escapeshellarg( $ffmpeg ) . ' -v error -y -threads 1 -ss ' . $at . ' -i ' . escapeshellarg( $file )
            . ' -frames:v 1 -vf ' . escapeshellarg( "scale='min(1280,iw)':-2" ) . ' -q:v 3 -threads 1 ' . escapeshellarg( $jpg ) . ' 2>&1' );
        if ( file_exists( $jpg ) && filesize( $jpg ) > 0 ) break;
    }
    if ( ! file_exists( $jpg ) || ! filesize( $jpg ) ) return;

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $title = get_the_title( $attachment_id );
    $poster = wp_insert_attachment( [
        'post_mime_type' => 'image/jpeg',
        'post_title'     => ( $title !== '' ? $title : 'Video' ) . ' poster',
        'post_status'    => 'inherit',
    ], $jpg );
    if ( ! $poster || is_wp_error( $poster ) ) return;

    wp_update_attachment_metadata( $poster, wp_generate_attachment_metadata( $poster, $jpg ) );
    update_post_meta( $poster, '_wp_attachment_image_alt', 'Still from the video' );
    update_post_meta( $attachment_id, '_ms_video_poster', $poster );
}

/* Front end (the art theme's video-preview.php): the poster above, or preload=metadata + #t=0.001 so Safari shows the first frame;
 * playsinline keeps it in the page on iPhone. A poster set in the block, autoplay or preload=none are left alone. */
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

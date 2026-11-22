<?php
/**
 * YouTube thumbnail as the featured image (2026-11-12, owner's wish)
 *
 * A post with a YouTube video and no pictures of its own has nothing to show in the feed, the archives, search results and link
 * previews. When such a post is saved, the video's thumbnail is copied into the Media library and set as its featured image.
 *
 * WHEN: a post (not an autosave or revision) with no featured image, no <img> in its content and a YouTube link (watch, youtu.be,
 * embed or shorts). A featured image set by hand always wins, and a post that has other pictures is left alone.
 * ONCE PER POST: the post gets the meta _ms_yt_poster (the picture's ID); if the featured image is removed later it is not made again.
 * THE PICTURE: YouTube's largest thumbnail (maxres, else sd, else hq), stored in the library as "youtube-<id>.jpg" with the alt text
 * "YouTube video thumbnail: <video title>" (the title from YouTube's oEmbed, else the post's). The picture's meta _ms_yt_video holds the
 * video ID, so a second post with the same video reuses the picture. Changing the video later does not refresh it.
 *
 * monospace_youtube_poster_for( $post_id, $dry ) is also what a one-off run calls.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The first YouTube video ID in a post's content, or ''. */
function monospace_youtube_id( $content ) {
	if ( preg_match( '~(?:youtube\.com/(?:watch\?(?:[^"\s<]*&(?:amp;)?)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', (string) $content, $m ) ) return $m[1];
	return '';
}

/** Fetch an image URL; the body if it is a real picture, else ''. */
function monospace_youtube_fetch( $url ) {
	$r = wp_remote_get( $url, array( 'timeout' => 20 ) );
	if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) return '';
	$body = wp_remote_retrieve_body( $r );
	return strlen( $body ) > 4000 && 0 === strpos( (string) wp_remote_retrieve_header( $r, 'content-type' ), 'image/' ) ? $body : '';
}

/**
 * Give a post its video's thumbnail as featured image, if it qualifies.
 * @return array [ 'status' => string, 'attachment' => int ]
 */
function monospace_youtube_poster_for( $post_id, $dry = false ) {
	$post = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type || in_array( $post->post_status, array( 'auto-draft', 'trash', 'inherit' ), true ) ) return array( 'status' => 'not a post' );
	if ( has_post_thumbnail( $post ) ) return array( 'status' => 'has a featured image' );
	if ( get_post_meta( $post->ID, '_ms_yt_poster', true ) ) return array( 'status' => 'already made once' );
	if ( preg_match( '/<img\b/i', $post->post_content ) ) return array( 'status' => 'has other pictures' );
	$vid = monospace_youtube_id( $post->post_content );
	if ( '' === $vid ) return array( 'status' => 'no YouTube video' );

	$existing = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_ms_yt_video', 'meta_value' => $vid, 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $existing ) {
		if ( ! $dry ) { set_post_thumbnail( $post->ID, $existing[0] ); update_post_meta( $post->ID, '_ms_yt_poster', $existing[0] ); }
		return array( 'status' => $dry ? 'would reuse the picture of video ' . $vid : 'reused the picture of video ' . $vid, 'attachment' => (int) $existing[0] );
	}

	$body = '';
	foreach ( array( 'maxresdefault', 'sddefault', 'hqdefault' ) as $size ) {
		$body = monospace_youtube_fetch( "https://i.ytimg.com/vi/$vid/$size.jpg" );
		if ( '' !== $body ) break;
	}
	if ( '' === $body ) return array( 'status' => 'no thumbnail found for video ' . $vid );
	if ( $dry ) return array( 'status' => 'would use the thumbnail of video ' . $vid . ' (' . strlen( $body ) . ' bytes, ' . $size . ')' );

	$title = '';
	$o = wp_remote_get( 'https://www.youtube.com/oembed?format=json&url=' . rawurlencode( 'https://www.youtube.com/watch?v=' . $vid ), array( 'timeout' => 10 ) );
	if ( ! is_wp_error( $o ) && 200 === wp_remote_retrieve_response_code( $o ) ) {
		$j = json_decode( wp_remote_retrieve_body( $o ), true );
		$title = is_array( $j ) && ! empty( $j['title'] ) ? wp_strip_all_tags( $j['title'] ) : '';
	}
	if ( '' === $title ) $title = wp_strip_all_tags( get_the_title( $post ) );

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = wp_tempnam( 'youtube-' . $vid . '.jpg' );
	file_put_contents( $tmp, $body );
	$att = media_handle_sideload( array( 'name' => 'youtube-' . $vid . '.jpg', 'tmp_name' => $tmp ), $post->ID, null, array( 'post_title' => 'YouTube thumbnail: ' . $title ) );
	if ( is_wp_error( $att ) ) { @unlink( $tmp ); return array( 'status' => 'could not save the picture: ' . $att->get_error_message() ); }

	update_post_meta( $att, '_wp_attachment_image_alt', 'YouTube video thumbnail: ' . $title );
	update_post_meta( $att, '_ms_yt_video', $vid );
	set_post_thumbnail( $post->ID, $att );
	update_post_meta( $post->ID, '_ms_yt_poster', $att );
	return array( 'status' => 'made the featured image from video ' . $vid, 'attachment' => (int) $att );
}

add_action( 'wp_after_insert_post', function ( $post_id, $post ) {
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) return;
	monospace_youtube_poster_for( $post_id );
}, 20, 2 );

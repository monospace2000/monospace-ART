<?php
/**
 * Alt text: one text, two places (Kit part since 2026-11-23; from the art site's monospace-admin-tools, alt-text-sync.php, made
 * 2026-11-12 - edit it there first, then copy)
 *
 * An image's alt text lives in two places: the Media library field ("Alternative Text", meta
 * _wp_attachment_image_alt) and a copy inside the post (the image block's own alt box). This module keeps them the
 * same, whichever one is edited:
 *
 * - Saving a post: an alt text typed in an image block's box is copied to the Media library; an empty box is
 *   filled from the Media library.
 * - Changing the Media library field: the new text is written into the image block of every post that uses the
 *   image (posts, pages and products, drafts included), straight in the database - no new revision, the modified
 *   date stays.
 *
 * Clearing the Media library field leaves the posts alone. Only images with the "wp-image-<ID>" class WordPress
 * puts on library images are handled. Also used by the one-off fill: monospace_kit_alt_fill_content().
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Post types whose content is kept in step. */
function monospace_kit_alt_sync_types() {
	return array( 'post', 'page', 'product' );
}

/** The library alt text of an image, trimmed. */
function monospace_kit_alt_library( $id ) {
	return trim( (string) get_post_meta( (int) $id, '_wp_attachment_image_alt', true ) );
}

/** The library image ID of an <img> tag (from its wp-image-<ID> class), or 0. */
function monospace_kit_alt_tag_id( $html ) {
	return preg_match( '/(?:^|\s)wp-image-(\d+)(?:\s|$)/', (string) $html->get_attribute( 'class' ), $m ) ? (int) $m[1] : 0;
}

/**
 * Fill empty alt attributes from the Media library.
 * @return string The content (unchanged when there was nothing to fill).
 */
function monospace_kit_alt_fill_content( $content ) {
	if ( false === stripos( (string) $content, '<img' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) return $content;
	$html = new WP_HTML_Tag_Processor( $content );
	while ( $html->next_tag( 'img' ) ) {
		$id = monospace_kit_alt_tag_id( $html );
		if ( ! $id || '' !== trim( (string) $html->get_attribute( 'alt' ) ) ) continue;
		$alt = monospace_kit_alt_library( $id );
		if ( '' !== $alt ) $html->set_attribute( 'alt', $alt );
	}
	return $html->get_updated_html();
}

/** Set the alt attribute of every <img class="wp-image-ID"> in the content. */
function monospace_kit_alt_set_content( $content, $id, $alt ) {
	$html = new WP_HTML_Tag_Processor( $content );
	while ( $html->next_tag( 'img' ) ) {
		if ( monospace_kit_alt_tag_id( $html ) === (int) $id ) $html->set_attribute( 'alt', $alt );
	}
	return $html->get_updated_html();
}

// A post is being saved: the box wins over the library, and an empty box is filled.
add_filter( 'wp_insert_post_data', function ( $data ) {
	if ( ! in_array( $data['post_type'], monospace_kit_alt_sync_types(), true ) || 'auto-draft' === $data['post_status'] || 'trash' === $data['post_status'] ) return $data;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return $data;
	$content = wp_unslash( $data['post_content'] );
	if ( false === stripos( $content, '<img' ) || ! class_exists( 'WP_HTML_Tag_Processor' ) ) return $data;

	$html = new WP_HTML_Tag_Processor( $content );
	while ( $html->next_tag( 'img' ) ) {
		$id  = monospace_kit_alt_tag_id( $html );
		$alt = trim( (string) $html->get_attribute( 'alt' ) );
		if ( ! $id || '' === $alt || monospace_kit_alt_library( $id ) === $alt ) continue;
		$GLOBALS['monospace_kit_alt_syncing_post'] = (int) ( $data['ID'] ?? 0 ); // the other posts follow; this one is being saved
		update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $alt ) );
		unset( $GLOBALS['monospace_kit_alt_syncing_post'] );
	}
	$data['post_content'] = wp_slash( monospace_kit_alt_fill_content( $content ) );
	return $data;
} );

// The Media library field changed: write it into every post that uses the image.
$monospace_kit_alt_propagate = function ( $meta_id, $id, $key, $value ) {
	if ( '_wp_attachment_image_alt' !== $key ) return;
	$alt = trim( (string) $value );
	if ( '' === $alt ) return;
	global $wpdb;
	$skip  = (int) ( $GLOBALS['monospace_kit_alt_syncing_post'] ?? 0 );
	$types = "'" . implode( "','", monospace_kit_alt_sync_types() ) . "'";
	$posts = $wpdb->get_results( $wpdb->prepare(
		"SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type IN ($types) AND post_status NOT IN ('trash','auto-draft') AND post_content LIKE %s",
		'%wp-image-' . (int) $id . '%' ) );
	foreach ( $posts as $p ) {
		if ( (int) $p->ID === $skip ) continue;
		$new = monospace_kit_alt_set_content( $p->post_content, $id, $alt );
		if ( $new === $p->post_content ) continue;
		$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $p->ID ) );
		clean_post_cache( $p->ID );
	}
};
add_action( 'updated_post_meta', $monospace_kit_alt_propagate, 10, 4 );
add_action( 'added_post_meta', $monospace_kit_alt_propagate, 10, 4 );

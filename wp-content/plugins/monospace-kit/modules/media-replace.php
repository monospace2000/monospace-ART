<?php
/**
 * Replace a media file (replaces Enable Media Replace, owner 2026-11-23).
 *
 * Media Library > a file > "Edit more details": the box "Replace file". Choose a new file of the same type and Update: the
 * new file takes the old one's place under the same name and address, all its sizes are made again, and every post that
 * shows it shows the new one. The old file and its sizes are gone (no copy kept).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'post_edit_form_tag', function ( $post ) {
	if ( $post && $post->post_type === 'attachment' ) echo ' enctype="multipart/form-data"';
} );

add_action( 'add_meta_boxes_attachment', function () {
	add_meta_box( 'monospace-replace-file', 'Replace file', function ( $post ) {
		wp_nonce_field( 'monospace_replace_' . $post->ID, 'monospace_replace_nonce' );
		echo '<p><input type="file" name="monospace_replace_file" accept="' . esc_attr( get_post_mime_type( $post ) ) . '"></p>'
			. '<p class="description">Same type only (' . esc_html( get_post_mime_type( $post ) ) . '). Keeps the name and address; the old file is replaced when you click Update.</p>';
	}, 'attachment', 'side', 'low' );
} );

add_action( 'edit_attachment', function ( $id ) {
	if ( empty( $_FILES['monospace_replace_file']['tmp_name'] ) || ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $id ) ) return;
	if ( ! isset( $_POST['monospace_replace_nonce'] ) || ! wp_verify_nonce( $_POST['monospace_replace_nonce'], 'monospace_replace_' . $id ) ) return;

	$upload = $_FILES['monospace_replace_file'];
	$old    = get_attached_file( $id );
	$check  = wp_check_filetype_and_ext( $upload['tmp_name'], $upload['name'] );
	if ( $upload['error'] || ! $old || ! is_uploaded_file( $upload['tmp_name'] ) || $check['type'] !== get_post_mime_type( $id ) ) {
		set_transient( 'monospace_replace_msg_' . get_current_user_id(), 'The file was not replaced: choose a file of the same type (' . get_post_mime_type( $id ) . ').', 60 );
		return;
	}

	// The old sizes go first, so no stale copy is left behind
	$meta = wp_get_attachment_metadata( $id );
	if ( ! empty( $meta['sizes'] ) ) {
		foreach ( $meta['sizes'] as $size ) {
			$file = path_join( dirname( $old ), $size['file'] );
			if ( is_file( $file ) ) wp_delete_file( $file );
		}
	}
	if ( ! empty( $meta['original_image'] ) ) wp_delete_file( path_join( dirname( $old ), $meta['original_image'] ) );
	if ( ! move_uploaded_file( $upload['tmp_name'], $old ) ) return;
	@chmod( $old, 0644 );

	require_once ABSPATH . 'wp-admin/includes/image.php';
	remove_all_actions( 'edit_attachment' ); // no loop through wp_update_post below
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $old ) );
	wp_update_post( [ 'ID' => $id, 'post_modified' => current_time( 'mysql' ), 'post_modified_gmt' => current_time( 'mysql', true ) ] );
	if ( function_exists( 'sg_cachepress_purge_everything' ) ) sg_cachepress_purge_everything();
	set_transient( 'monospace_replace_msg_' . get_current_user_id(), 'File replaced: same name and address, all sizes made again.', 60 );
} );

add_action( 'admin_notices', function () {
	$key = 'monospace_replace_msg_' . get_current_user_id();
	$msg = get_transient( $key );
	if ( ! $msg ) return;
	delete_transient( $key );
	echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
} );

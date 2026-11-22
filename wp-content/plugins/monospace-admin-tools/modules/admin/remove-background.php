<?php
/**
 * Remove background (2026-11-18, owner's wish: "like Keynote - swipe over the image to remove multiple areas of color").
 *
 * A button in the image editor of the Media Library (Edit Image: the row with Crop / Rotate / Flip, in the pop-up and on
 * the attachment's edit screen) opens a full-screen editor: swipe (mouse, finger or pen) over a color to make it, and the
 * touching colors like it, transparent. Settings: how alike a color must be, touching areas only or the whole picture,
 * Undo, Reset. Everything is drawn in the browser (assets/remove-bg.js); only Save sends anything: the result goes up as
 * a NEW PNG in the Media Library ("<title> (no background)"), the original is never changed.
 *
 * The file is written with wp_upload_bits(), not wp_handle_upload(): the watermark plugin hooks wp_handle_upload, and a
 * copy of an already stamped original must not be stamped twice.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_rmbg_enqueue() {
    static $done = false;
    if ( $done || ! current_user_can( 'upload_files' ) ) return;
    $done = true;
    $dir = MONOSPACE_ADMIN_TOOLS_DIR . '/assets/';
    wp_enqueue_style( 'monospace-remove-bg', MONOSPACE_ADMIN_TOOLS_URL . 'assets/remove-bg.css', [], filemtime( $dir . 'remove-bg.css' ) );
    wp_enqueue_script( 'monospace-remove-bg', MONOSPACE_ADMIN_TOOLS_URL . 'assets/remove-bg.js', [], filemtime( $dir . 'remove-bg.js' ), true );
    wp_localize_script( 'monospace-remove-bg', 'msRemoveBg', [
        'ajax'  => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'monospace_rmbg' ),
    ] );
}
add_action( 'wp_enqueue_media', 'monospace_rmbg_enqueue' );
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( in_array( $hook, [ 'post.php', 'upload.php' ], true ) ) monospace_rmbg_enqueue();
} );

/** The picture to work on: its address and name */
add_action( 'wp_ajax_monospace_rmbg_info', function () {
    check_ajax_referer( 'monospace_rmbg', 'nonce' );
    $id = (int) ( $_POST['id'] ?? 0 );
    if ( ! $id || get_post_type( $id ) !== 'attachment' || ! current_user_can( 'edit_post', $id ) || ! wp_attachment_is_image( $id ) ) wp_send_json_error( 'That picture can’t be edited.' );
    wp_send_json_success( [ 'url' => wp_get_attachment_url( $id ), 'title' => get_the_title( $id ) ] );
} );

/** Save the result as a new PNG attachment */
add_action( 'wp_ajax_monospace_rmbg_save', function () {
    check_ajax_referer( 'monospace_rmbg', 'nonce' );
    $src = (int) ( $_POST['id'] ?? 0 );
    if ( ! $src || get_post_type( $src ) !== 'attachment' || ! current_user_can( 'edit_post', $src ) || ! current_user_can( 'upload_files' ) ) wp_send_json_error( 'Not allowed.' );
    $file = $_FILES['image'] ?? null;
    if ( ! $file || ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) wp_send_json_error( 'The picture did not arrive.' );
    $bytes = file_get_contents( $file['tmp_name'] );
    $info  = $bytes ? getimagesizefromstring( $bytes ) : false;
    if ( ! $info || $info[2] !== IMAGETYPE_PNG ) wp_send_json_error( 'Not a PNG picture.' );

    $base = pathinfo( get_attached_file( $src ), PATHINFO_FILENAME );
    $up   = wp_upload_bits( sanitize_file_name( $base . '-no-bg.png' ), null, $bytes );
    if ( ! empty( $up['error'] ) ) wp_send_json_error( $up['error'] );

    $title = get_the_title( $src ) . ' (no background)';
    $new = wp_insert_attachment( [
        'post_mime_type' => 'image/png',
        'post_title'     => $title,
        'post_status'    => 'inherit',
    ], $up['file'] );
    if ( is_wp_error( $new ) || ! $new ) wp_send_json_error( 'Could not add it to the Media Library.' );
    require_once ABSPATH . 'wp-admin/includes/image.php';
    wp_update_attachment_metadata( $new, wp_generate_attachment_metadata( $new, $up['file'] ) );
    $alt = get_post_meta( $src, '_wp_attachment_image_alt', true );
    if ( $alt ) update_post_meta( $new, '_wp_attachment_image_alt', $alt );

    wp_send_json_success( [ 'id' => $new, 'title' => $title, 'edit' => get_edit_post_link( $new, 'raw' ) ] );
} );

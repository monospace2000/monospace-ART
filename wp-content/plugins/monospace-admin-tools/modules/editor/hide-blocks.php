<?php
/**
 * Hide a block ("comment it out") - block editor, any block (owner, 2026-10-26).
 *
 * Block settings sidebar > Visibility > "Hide this block" sets the attribute msHidden. The editor shows the block
 * grayed out with a dashed frame and a "Hidden" tag; the site leaves it (and the blocks inside it) out. The block
 * stays in the post, so one click brings it back. assets/hide-blocks.js (editor) and assets/hide-blocks.css.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// The attribute exists on the server too, so server-rendered blocks (ServerSideRender) accept it
add_filter( 'register_block_type_args', function ( $args ) {
    $args['attributes']['msHidden'] = [ 'type' => 'boolean', 'default' => false ];
    return $args;
} );

// The site: a hidden block prints nothing (inner blocks with it)
add_filter( 'pre_render_block', function ( $pre, $block ) {
    return ! empty( $block['attrs']['msHidden'] ) ? '' : $pre;
}, 10, 2 );

add_action( 'enqueue_block_editor_assets', function () {
    $file = MONOSPACE_ADMIN_TOOLS_DIR . '/assets/hide-blocks.js';
    wp_enqueue_script( 'monospace-hide-blocks', MONOSPACE_ADMIN_TOOLS_URL . 'assets/hide-blocks.js',
        [ 'wp-hooks', 'wp-element', 'wp-compose', 'wp-block-editor', 'wp-components', 'wp-i18n' ], filemtime( $file ), true );
} );

// enqueue_block_assets in the admin = also inside the editor's iframe
add_action( 'enqueue_block_assets', function () {
    if ( ! is_admin() ) return;
    $file = MONOSPACE_ADMIN_TOOLS_DIR . '/assets/hide-blocks.css';
    wp_enqueue_style( 'monospace-hide-blocks', MONOSPACE_ADMIN_TOOLS_URL . 'assets/hide-blocks.css', [], filemtime( $file ) );
} );

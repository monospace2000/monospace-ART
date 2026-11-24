<?php
/**
 * Hide a block (replaces Block Visibility, owner 2026-11-23): the art site's own "Hide this block" (monospace-admin-tools,
 * modules/editor/hide-blocks.php; assets copied unchanged).
 *
 * Block settings sidebar > Visibility > "Hide this block" sets the attribute msHidden. The editor shows the block grayed out
 * with a dashed frame and a "Hidden on the site" tag; the site leaves it (and the blocks inside it) out. The block stays in
 * the post, so one click brings it back.
 * Blocks hidden with Block Visibility (attribute blockVisibility.hideBlock) stay hidden too.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'register_block_type_args', function ( $args ) {
	$args['attributes']['msHidden'] = [ 'type' => 'boolean', 'default' => false ];
	return $args;
} );

add_filter( 'pre_render_block', function ( $pre, $block ) {
	$attrs = $block['attrs'] ?? [];
	if ( ! empty( $attrs['msHidden'] ) || ! empty( $attrs['blockVisibility']['hideBlock'] ) ) return '';
	return $pre;
}, 10, 2 );

add_action( 'enqueue_block_editor_assets', function () {
	wp_enqueue_script( 'monospace-hide-blocks', MONOSPACE_KIT_URL . 'assets/hide-blocks.js',
		[ 'wp-hooks', 'wp-element', 'wp-compose', 'wp-block-editor', 'wp-components', 'wp-i18n' ], filemtime( MONOSPACE_KIT_DIR . '/assets/hide-blocks.js' ), true );
} );

add_action( 'enqueue_block_assets', function () {
	if ( ! is_admin() ) return;
	wp_enqueue_style( 'monospace-hide-blocks', MONOSPACE_KIT_URL . 'assets/hide-blocks.css', [], filemtime( MONOSPACE_KIT_DIR . '/assets/hide-blocks.css' ) );
} );

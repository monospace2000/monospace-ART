<?php
/**
 * Featured image column in the Posts and Pages lists (replaces Display Featured Image In Post List, owner 2026-11-23).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_init', function () {
	foreach ( [ 'post', 'page' ] as $type ) {
		add_filter( "manage_{$type}_posts_columns", function ( $cols ) {
			return array_merge( array_slice( $cols, 0, 1, true ), [ 'ms_thumb' => '<span class="screen-reader-text">Featured image</span>' ], array_slice( $cols, 1, null, true ) );
		} );
		add_action( "manage_{$type}_posts_custom_column", function ( $col, $id ) {
			if ( $col === 'ms_thumb' ) echo get_the_post_thumbnail( $id, [ 60, 60 ], [ 'style' => 'width:60px;height:60px;object-fit:cover;border-radius:4px' ] );
		}, 10, 2 );
	}
} );

add_action( 'admin_head-edit.php', function () {
	echo '<style>.column-ms_thumb{width:68px}</style>';
} );

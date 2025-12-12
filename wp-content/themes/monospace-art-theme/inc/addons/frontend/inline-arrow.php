<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Make arrow.png available via CSS variable
 */
add_action( 'enqueue_block_assets', function() {
    wp_add_inline_style(
        'astra-theme-css', // attach to Astra's main CSS
        ':root { --arrow-image: url("' . get_stylesheet_directory_uri() . '/assets/images/arrow.png"); }'
    );
});

/**
 * Load editor scripts + styles for arrow format
 */
add_action( 'enqueue_block_editor_assets', function() {

    // JS for Gutenberg formatting button
    wp_enqueue_script(
        'monospace-inline-arrow-format',
        get_stylesheet_directory_uri() . '/assets/scripts/inline-arrow-format.js',
        [
            'wp-rich-text',
            'wp-block-editor',
            'wp-element',
            'wp-components'
        ],
        filemtime( get_stylesheet_directory() . '/assets/scripts/inline-arrow-format.js' ),
        true
    );

    // Editor CSS
    wp_enqueue_style(
        'monospace-inline-arrow-editor-style',
        get_stylesheet_directory_uri() . '/assets/styles/inline-arrow-editor.css',
        [],
        filemtime( get_stylesheet_directory() . '/assets/styles/inline-arrow-editor.css' )
    );
});

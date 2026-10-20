<?php
/**
 * Block Editor Configuration
 */

add_action('after_setup_theme', function() {
    add_theme_support('editor-styles');
});

/**
 * Enqueue block editor styles
 */
add_action('enqueue_block_editor_assets', function() {
    wp_enqueue_style(
        'theme-editor-styles',
        get_theme_file_uri('/assets/css/editor.css'),
        [],
        filemtime(get_theme_file_path('/assets/css/editor.css'))
    );
});

/**
 * Image block style "No shadow" (Image block > Styles): removes the image's drop shadow on
 * posts, pages and in the feeds (the feed keeps is-style-* classes, clickable-feed-image.php).
 * CSS: _global.scss.
 */
add_action('init', function() {
    register_block_style('core/image', [
        'name'  => 'no-shadow',
        'label' => __('No shadow', 'monospace'),
    ]);
});

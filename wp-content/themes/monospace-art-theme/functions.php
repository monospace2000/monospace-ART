<?php

/*
Theme Name: monospace ART
Theme URI: https://art.monospace.com
Author: Hens Breet
Description: Custom top-level theme for monospace ART.
Version: 1.0.0
Text Domain: monospace-art-theme
*/

define( 'MONOSPACE_ART_THEME_VERSION', '1.0.0' );
define( 'MONOSPACE_ART_THEME_DIR', get_template_directory() );
define( 'MONOSPACE_ART_THEME_URI', get_template_directory_uri() );

require_once MONOSPACE_ART_THEME_DIR . '/inc/core/enqueue.php';
require_once MONOSPACE_ART_THEME_DIR . '/inc/core/setup.php';
require_once MONOSPACE_ART_THEME_DIR . '/inc/core/sidebars.php';
require_once MONOSPACE_ART_THEME_DIR . '/inc/core/editor.php';


/**
 * ------------------------------
 * Global Debug Settings
 * ------------------------------
 */
define( 'MONOSPACE_MODULE_DEBUG', true ); // Set to false to disable loader logging

function monospace_log( $message ) {
    if ( defined( 'MONOSPACE_MODULE_DEBUG' ) && MONOSPACE_MODULE_DEBUG ) {
        error_log( '[monospace-module] ' . $message ); // goes to SiteGround php_errorlog
    }
}

/**
 * ------------------------------
 * Module Loaders
 * ------------------------------
 */
function monospace_art_load_modules() {
    $base_dir = MONOSPACE_ART_THEME_DIR . '/inc/modules';
    if ( ! is_dir( $base_dir ) ) return;

    $exclude_dirs = ['disabled'];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $base_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $files = [];
    foreach ( $iterator as $file ) {
        if ( $file->isFile() && 'php' === $file->getExtension() ) {
            $files[] = $file->getPathname();
        }
    }

    // Sort alphabetically for predictable load order
    sort($files);

    foreach ( $files as $file_path ) {
        // Skip disabled dirs
        foreach ( $exclude_dirs as $dir ) {
            if ( strpos( $file_path, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR ) !== false ) {
                monospace_log( "Skipped (disabled dir): $file_path" );
                continue 2;
            }
        }

        // Load the file
        require_once $file_path;
        monospace_log( "Loaded: $file_path" );
    }
}
// module bootstrap
add_action( 'init', 'monospace_art_load_modules');















// Include About page only in admin
if ( is_admin() ) {
   // require_once get_stylesheet_directory() . '/inc/admin/about.php';
}

add_action( 'admin_menu', function() {
    add_theme_page(
        'About monospace ART',  // Page title
        'About monospace ART',  // Menu title
        'edit_theme_options',   // Capability
        'monospace-art-about',  // Menu slug
        'monospace_art_child_about_page' // Callback
    );
});
add_action( 'after_switch_theme', function() {
    if ( current_user_can( 'edit_theme_options' ) ) {
        add_option( 'monospace_art_about_redirect', true );
    }
});

add_action( 'admin_init', function() {
    if ( get_option( 'monospace_art_about_redirect', false ) ) {
        delete_option( 'monospace_art_about_redirect' );
        wp_redirect( admin_url( 'themes.php?page=monospace-art-about' ) );
        exit;
    }
});












/**
 * Show publication date for posts in the "blog" category:
 * - single blog posts
 * - blog category archive
 * - main posts feed (home/blog index)
 */
add_filter( 'the_content', 'ms_show_date_on_blog_posts_everywhere' );
function ms_show_date_on_blog_posts_everywhere( $content ) {

    // We only add dates to normal posts
    if ( get_post_type() !== 'post' ) {
        return $content;
    }

    $is_blog_post = has_category( 'blog' );

    // --- SINGLE POST ---
    if ( is_singular( 'post' ) && $is_blog_post ) {
        $date_html = '<p class="post-date">' . get_the_date() . '</p>';
        return $date_html . $content;
    }

    // --- BLOG CATEGORY ARCHIVE ---
    if ( is_category( 'blog' ) && is_main_query() ) {
        $date_html = '<p class="post-date">' . get_the_date() . '</p>';
        return $date_html . $content;
    }

    // --- MAIN FEED (home / posts page) ---
    if ( ( is_home() || is_archive() ) && is_main_query() && $is_blog_post ) {
        $date_html = '<p class="post-date">' . get_the_date() . '</p>';
        return $date_html . $content;
    }

    return $content;
}












// TEMPORARY DEBUG - Remove after troubleshooting
add_action('wp_footer', function() {
    if (!current_user_can('manage_options')) return;

    global $post;
    if (!$post) return;

    $product = wc_get_product($post->ID);
    if (!$product) return;

    echo '<div style="position:fixed;bottom:0;left:0;background:#000;color:#0f0;padding:10px;font-family:monospace;font-size:11px;max-width:400px;z-index:9999;">';
    echo '<strong>DEBUG INFO:</strong><br>';

    // Check settings
    echo 'Volume enabled: ' . (get_option('msd_volume_enable') === 'yes' ? 'YES' : 'NO') . '<br>';
    echo 'Hints enabled: ' . (get_option('msd_hints_enable') === 'yes' ? 'YES' : 'NO') . '<br>';

    // Check JSON
    $json = get_option('msd_volume_rules', '');
    echo 'JSON exists: ' . (!empty($json) ? 'YES' : 'NO') . '<br>';

    // Check product attributes
    $terms = wp_get_post_terms($post->ID, 'pa_size', ['fields' => 'slugs']);
    echo 'pa_size terms: ' . print_r($terms, true) . '<br>';
    echo 'Has 4x4: ' . (in_array('4x4', (array)$terms) ? 'YES' : 'NO') . '<br>';

    // Check match
    $matches = monospace_product_matches_volume_rule($post->ID, 'miniature');
    echo 'Matches miniature rule: ' . ($matches ? 'YES' : 'NO') . '<br>';

    echo '</div>';
});









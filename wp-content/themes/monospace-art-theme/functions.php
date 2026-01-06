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

require_once MONOSPACE_ART_THEME_DIR . '/includes/core/enqueue.php';
require_once MONOSPACE_ART_THEME_DIR . '/includes/core/setup.php';
require_once MONOSPACE_ART_THEME_DIR . '/includes/core/sidebars.php';
require_once MONOSPACE_ART_THEME_DIR . '/includes/core/editor.php';


/**
 * ------------------------------
 * Global Debug Settings
 * ------------------------------
 */
define( 'MONOSPACE_MODULE_DEBUG', false ); // Set to false to disable loader logging

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
    $base_dir = MONOSPACE_ART_THEME_DIR . '/includes/modules';
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
   // require_once get_stylesheet_directory() . '/includes/admin/about.php';
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













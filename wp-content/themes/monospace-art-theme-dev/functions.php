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
define( 'MONOSPACE_ADDON_DEBUG', true ); // Set to false to disable loader logging

function monospace_log( $message ) {
    if ( defined( 'MONOSPACE_ADDON_DEBUG' ) && MONOSPACE_ADDON_DEBUG ) {
        error_log( '[monospace-addon] ' . $message ); // goes to SiteGround php_errorlog
    }
}

/**
 * ------------------------------
 * Addon Loaders
 * ------------------------------
 */
function monospace_art_load_addons() {
    $base_dir = MONOSPACE_ART_THEME_DIR . '/inc/addons';
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
// Addon bootstrap
add_action( 'init', 'monospace_art_load_addons');






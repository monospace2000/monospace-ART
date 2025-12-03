<?php
/**
 * monospace ART
 * --------------------------------------
 * Theme setup and addon loader
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ------------------------------
 * Theme Constants (define first)
 * ------------------------------
 */
define( 'MONOSPACE_ART_THEME_VERSION', '1.8.0' );
define( 'MONOSPACE_ART_THEME_DIR', get_stylesheet_directory() );
define( 'MONOSPACE_ART_THEME_URI', get_stylesheet_directory_uri() );

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
$priority_addon_files = [
    'inc/addons/astra_site_identity.php',
    'inc/addons/enqueue_styles.php',
];

function monospace_art_theme_load_priority_addons( $files ) {
    foreach ( $files as $relative_path ) {
        $file = get_stylesheet_directory() . '/' . $relative_path;
        if ( file_exists( $file ) ) {
            require_once $file;
            monospace_log( "Loaded priority file: $file" );
        } else {
            monospace_log( "Priority file missing: $file" );
        }
    }
}

function monospace_art_theme_load_remaining_addons( $base_dir, $priority_files, $exclude_dirs = ['disabled'] ) {
    if ( ! is_dir( $base_dir ) ) return;

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

        // Skip priority files
        foreach ( $priority_files as $priority ) {
            if ( realpath($file_path) === realpath( get_stylesheet_directory() . '/' . $priority ) ) {
                monospace_log( "Skipped (priority file already loaded): $file_path" );
                continue 2;
            }
        }

        // Load the file
        require_once $file_path;
        monospace_log( "Loaded: $file_path" );
    }
}

// Hook on init (slightly later)
add_action( 'init', function() use ( $priority_addon_files ) {
    monospace_art_theme_load_priority_addons( $priority_addon_files );
    monospace_art_theme_load_remaining_addons(
        get_stylesheet_directory() . '/inc/addons',
        $priority_addon_files
    );
}, 5 );



// Include About page only in admin
require_once get_stylesheet_directory() . '/inc/admin/about.php';

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











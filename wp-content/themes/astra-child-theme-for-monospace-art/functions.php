<?php
/**
 * Astra Child Theme for monospace | art
 * --------------------------------------
 * Theme setup and addon loader
 *
 * @package astra-child-theme-for-monospace-art
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ------------------------------
 * Theme Constants (define first)
 * ------------------------------
 */
define( 'CHILD_THEME_ASTRA_CHILD_THEME_FOR_MONOSPACE_ART_VERSION', '1.0.0' );
define( 'CHILD_THEME_MONOSPACE_DIR', get_stylesheet_directory() );
define( 'CHILD_THEME_MONOSPACE_URI', get_stylesheet_directory_uri() );

/**
 * ------------------------------
 * Global Debug Settings
 * ------------------------------
 */
define( 'MONOSPACE_ADDON_DEBUG', false ); // Set to false to disable loader logging

function monospace_log( $message ) {
	if ( defined( 'MONOSPACE_ADDON_DEBUG' ) && MONOSPACE_ADDON_DEBUG ) {
		error_log( '[monospace-addon] ' . $message ); // goes to SiteGround php_errorlog
	}
}

/**
 * ------------------------------
 * Define priority files (once)
 * Paths are relative to theme root
 * ------------------------------
 */
$priority_addon_files = [
    'addons/astra_site_identity.php', // custom logo link
	'addons/enqueue_styles.php',         // needs constants defined first
	//'addons/frontend/frontend-init.php',
	//'addons/admin/admin-init.php',
];

/**
 * ------------------------------
 * Load priority files
 * ------------------------------
 */
function astra_child_monospace_load_priority_addons( $files ) {
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

/**
 * ------------------------------
 * Recursively load remaining addons
 * ------------------------------
 */
function astra_child_monospace_load_remaining_addons( $base_dir, $priority_files, $exclude_dirs = [ 'disabled' ] ) {
	if ( ! is_dir( $base_dir ) ) {
		monospace_log( "Base directory does not exist: $base_dir" );
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $base_dir, RecursiveDirectoryIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'php' === $file->getExtension() ) {
			$file_path = $file->getPathname();

			// Skip disabled directories
			$skip = false;
			foreach ( $exclude_dirs as $dir ) {
				if ( strpos( $file_path, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR ) !== false ) {
					$skip = true;
					monospace_log( "Skipped (disabled dir): $file_path" );
					break;
				}
			}
			if ( $skip ) continue;

			// Skip priority files
			foreach ( $priority_files as $priority ) {
				if ( str_ends_with( $file_path, basename( $priority ) ) ) {
					$skip = true;
					monospace_log( "Skipped (priority file already loaded): $file_path" );
					break;
				}
			}
			if ( $skip ) continue;

			// Load the file
			require_once $file_path;
			monospace_log( "Loaded: $file_path" );
		}
	}
}

/**
 * ------------------------------
 * Hook everything up
 * ------------------------------
 */
add_action( 'after_setup_theme', function() use ( $priority_addon_files ) {
	// 1. Load priority files (constants guaranteed to exist first)
	astra_child_monospace_load_priority_addons( $priority_addon_files );

	// 2. Load all remaining files recursively
	astra_child_monospace_load_remaining_addons(
		get_stylesheet_directory() . '/addons',
		$priority_addon_files
	);
});

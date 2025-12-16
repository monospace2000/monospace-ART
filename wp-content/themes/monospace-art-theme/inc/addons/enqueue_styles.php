<?php
/**
 * Enqueue scripts and styles
 *
 * @package monospace-art-theme
 */


/**
 * Enqueue the child theme stylesheet.
 *
 * This function loads the child theme's main stylesheet (`style.css`) 
 * after the parent Astra theme's stylesheet. The version is set using 
 * a defined constant to ensure proper cache busting when the child theme 
 * is updated.
 *
 * @since 1.0.0
 *
 * @return void
 */
function child_enqueue_styles() {

	wp_enqueue_style(
		'monospace-art-theme-css', // Handle for the child theme stylesheet
		get_stylesheet_directory_uri() . '/style.css',   // Path to the child theme stylesheet
		array('astra-theme-css'),                        // Dependencies (load after parent theme CSS)
		MONOSPACE_ART_THEME_VERSION, // Version number for cache busting
		'all'                                           // Media type
	);

}
add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );


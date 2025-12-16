<?php

// -----------------------------------------------------------
// Enqueue Styles & Scripts
// -----------------------------------------------------------
function monospace_art_enqueue_assets() {
    $theme_version = wp_get_theme()->get('Version');

    // -------------------------------------------------------
    // Stylesheets
    // -------------------------------------------------------

    // Google Fonts - using display=optional to prevent layout shift
    wp_enqueue_style(
        'monospace-art-google-fonts',
        'https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400;1,700&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Oswald:wght@200..700&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap',
        array(),
        null
    );

    // Theme styles
    wp_enqueue_style('monospace-global', get_template_directory_uri() . '/assets/styles/01-global.css', array('monospace-art-google-fonts'), $theme_version);
    wp_enqueue_style('monospace-header', get_template_directory_uri() . '/assets/styles/02-header.css', array('monospace-global'), $theme_version);
    wp_enqueue_style('monospace-menu', get_template_directory_uri() . '/assets/styles/03-menu.css', array('monospace-global'), $theme_version);
    wp_enqueue_style('monospace-layout', get_template_directory_uri() . '/assets/styles/04-layout.css', array('monospace-global'), $theme_version);
    wp_enqueue_style('monospace-archive', get_template_directory_uri() . '/assets/styles/05-archive.css', array('monospace-global'), $theme_version);
    wp_enqueue_style('monospace-buttons', get_template_directory_uri() . '/assets/styles/06-buttons.css', array('monospace-global'), $theme_version);
    wp_enqueue_style('monospace-woocommerce', get_template_directory_uri() . '/assets/styles/07-woocommerce.css', array('monospace-global'), $theme_version);

    // -------------------------------------------------------
    // Scripts
    // -------------------------------------------------------
    wp_enqueue_script(
        'monospace-art-menu',
        get_template_directory_uri() . '/assets/scripts/menu.js',
        array('jquery'),
        $theme_version,
        true
    );
}
add_action('wp_enqueue_scripts', 'monospace_art_enqueue_assets');

// -----------------------------------------------------------
// Add preconnect for Google Fonts in head (performance optimization)
// -----------------------------------------------------------
function monospace_art_resource_hints($hints, $relation_type) {
    if ('preconnect' === $relation_type) {
        $hints[] = [
            'href' => 'https://fonts.googleapis.com',
            'crossorigin' => 'anonymous',
        ];
        $hints[] = [
            'href' => 'https://fonts.gstatic.com',
            'crossorigin' => 'anonymous',
        ];
    }
    return $hints;
}
add_filter('wp_resource_hints', 'monospace_art_resource_hints', 10, 2);
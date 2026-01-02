<?php

// -----------------------------------------------------------
// Add inline critical CSS to prevent FOUC (runs first)
// -----------------------------------------------------------
//add_action('wp_head', 'monospace_block_spacing_critical_css', 1);

function monospace_block_spacing_critical_css() {
    ?>
    <style id="block-spacing-override">

        .wp-block-group,
        .wp-block-columns,
        .wp-block-column {
            margin-block-start: 0 !important;
            margin-block-end: 0 !important;
            padding-block-start: 0 !important;
            padding-block-end: 0 !important;
        }

        .entry-content .wp-block-group {
            margin:  0 !important;
            padding: calc(var(--global-padding)/2) var(--global-padding) !important;
        }

    </style>
    <?php
}

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

// -----------------------------------------------------------
// Enqueue Styles & Scripts (early priority to load before block styles)
// -----------------------------------------------------------
add_action('wp_enqueue_scripts', 'monospace_art_enqueue_assets', 1);

function monospace_art_enqueue_assets() {
    $theme_version = wp_get_theme()->get('Version');

    // -------------------------------------------------------
    // Stylesheets
    // -------------------------------------------------------

    // Google Fonts - using display=optional to prevent layout shift
    wp_enqueue_style(
        'monospace-art-google-fonts',
        'https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400;1,700&family=Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Oswald:wght@200..700&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap',
        array(),
        null
    );

    // Theme styles
/*     wp_enqueue_style('monospace-global', get_template_directory_uri() . '/assets/styles/01-global.css', array('monospace-art-google-fonts'), $theme_version);
    wp_enqueue_style('monospace-header', get_template_directory_uri() . '/assets/styles/02-header.css', array('monospace-global'), $theme_version);
    wp_enqueue_style('monospace-menu', get_template_directory_uri() . '/assets/styles/03-menu.css', array('monospace-global'), $theme_version);
    wp_enqueue_style('monospace-layout', get_template_directory_uri() . '/assets/styles/04-layout.css', array('monospace-global'), $theme_version);
    wp_enqueue_style('monospace-archives', get_template_directory_uri() . '/assets/styles/05-archives.css', array('monospace-global'), $theme_version);
    wp_enqueue_style('monospace-components', get_template_directory_uri() . '/assets/styles/06-components.css', array('monospace-global'), $theme_version);
    wp_enqueue_style('monospace-woocommerce', get_template_directory_uri() . '/assets/styles/07-woocommerce.css', array('monospace-global'), $theme_version);
    wp_enqueue_style('monospace-start-page', get_template_directory_uri() . '/assets/styles/start_template.css',  array('monospace-global'), $theme_version);
 */

        // Theme styles - compiled from SASS
    wp_enqueue_style('monospace-theme', get_template_directory_uri() . '/assets/css/style.css', array('monospace-art-google-fonts'), $theme_version);


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
    // Fit-text script (container-relative)
    wp_enqueue_script(
        'monospace-fit-text',
        get_template_directory_uri() . '/assets/scripts/fit-text.js',
        array(),
        $theme_version,
        true
    );

}

// -----------------------------------------------------------
// Enqueue Infinite Scroll on all pages (for scroll-to-top button)
// -----------------------------------------------------------
add_action('wp_enqueue_scripts', 'monospace_art_enqueue_infinite_scroll');

function monospace_art_enqueue_infinite_scroll() {
    $theme_version = wp_get_theme()->get('Version');

    wp_enqueue_script(
        'monospace-infinite-scroll',
        get_template_directory_uri() . '/assets/scripts/infinite-scroll.js',
        array(),
        $theme_version, // Change back to $theme_version when done developing
        true
    );
}
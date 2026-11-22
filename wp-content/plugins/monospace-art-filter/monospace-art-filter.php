<?php
/**
 * Plugin Name: monospace ART - Art Filter
 * Update URI: false
 * Description: The [monospace_art_filter] page: find artworks by format, medium, surface, size, availability and price (each with exclude), sorted and shown as a grid, a list or price cards.
 * Version: 3.0.0
 * Author: Hens Breet
 * Requires Plugins: woocommerce
 *
 * A feature plugin (ARCHITECTURE.md §5.10; reshaped 2026-10-21 - it was one file with its CSS and script inline): it
 * depends only on monospace ART - Core (the artworks' availability: monospace_artwork_status()) and can be switched off -
 * then Core hides the Art Filter page's menu items and redirects the page (monospace_feature( 'art-filter' )).
 *
 *   modules/frontend/art-filter-shortcode.php   the form, the viewing toolbar, the empty results box; the assets
 *   modules/frontend/art-filter-results.php     the results (admin-ajax.php?action=ms_filter_products)
 *   assets/art-filter.css, art-filter.js        the look and the script, only where the shortcode is
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! function_exists( 'monospace_load_modules' ) ) return; // needs monospace ART - Core (wp-content/mu-plugins)

define( 'MONOSPACE_ART_FILTER_DIR', __DIR__ );
define( 'MONOSPACE_ART_FILTER_URL', plugin_dir_url( __FILE__ ) );

add_filter( 'monospace_feature_art-filter', '__return_true' );
add_action( 'init', function () { monospace_load_modules( __DIR__ . '/modules' ); } );

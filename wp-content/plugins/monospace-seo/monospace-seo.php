<?php
/**
 * Plugin Name: monospace ART - SEO
 * Update URI: false
 * Description: The site's search and sharing tags: title, description, Open Graph, JSON-LD, canonical; artwork headings
 *              and alt text. Off = Jetpack's own SEO tags come back.
 * Version: 1.0.0
 * Author: Hens Breet
 *
 * A feature plugin (2026-10-19 restructure, ARCHITECTURE.md): it depends only on monospace ART - Core (the
 * mu-plugin) and can be switched off without breaking anything else. Its modules/ load on init, like the theme's
 * modules did (monospace_load_modules()). monospace_feature( 'seo' ) is true while it's on.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! function_exists( 'monospace_load_modules' ) ) return; // needs monospace ART - Core (wp-content/mu-plugins)

define( 'MONOSPACE_SEO_DIR', __DIR__ );
define( 'MONOSPACE_SEO_URL', plugin_dir_url( __FILE__ ) );

add_filter( 'monospace_feature_seo', '__return_true' );
add_action( 'init', function () { monospace_load_modules( __DIR__ . '/modules' ); } );

// Jetpack SEO Tools' meta tags off - this plugin writes them (modules/frontend/seo/head-tags.php, which still
// reads Jetpack's SEO fields). Here, at plugin load: Jetpack checks the filter on init, before modules load.
add_filter( 'jetpack_seo_meta_tags_enabled', '__return_false' );

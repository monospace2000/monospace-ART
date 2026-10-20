<?php
/**
 * Plugin Name: monospace ART - Artwork Sheets
 * Update URI: false
 * Description: Printable artwork sheets, exports, public sheet links and artwork lists (shared with the Artwork Editor).
 * Version: 1.0.0
 * Author: Hens Breet
 * Requires Plugins: woocommerce
 *
 * A feature plugin (2026-10-19 restructure, ARCHITECTURE.md): it depends only on monospace ART - Core (the
 * mu-plugin) and can be switched off without breaking anything else. Its modules/ load on init, like the theme's
 * modules did (monospace_load_modules()). monospace_feature( 'sheets' ) is true while it's on.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! function_exists( 'monospace_load_modules' ) ) return; // needs monospace ART - Core (wp-content/mu-plugins)

define( 'MONOSPACE_SHEETS_DIR', __DIR__ );
define( 'MONOSPACE_SHEETS_URL', plugin_dir_url( __FILE__ ) );

add_filter( 'monospace_feature_sheets', '__return_true' );
add_action( 'init', function () { monospace_load_modules( __DIR__ . '/modules' ); } );

add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

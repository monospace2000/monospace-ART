<?php
/**
 * Plugin Name: monospace ART - Prints
 * Update URI: false
 * Description: Print products made and sold through Printful: creating prints (background jobs), Show / Hide prints,
 *              mockups drawn on the site, the Framed / Unframed print tabs on the price card, "See a preview",
 *              the Prints menu (New prints, All prints, Watermarked images) and the Prints box on an artwork.
 * Version: 1.0.0
 * Author: Hens Breet
 * Requires Plugins: woocommerce
 *
 * A feature plugin (2026-10-19 restructure, ARCHITECTURE.md): it depends only on monospace ART - Core (the
 * mu-plugin) and can be switched off without breaking anything else. Its modules/ load on init, like the theme's
 * modules did (monospace_load_modules()). monospace_feature( 'prints' ) is true while it's on.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! function_exists( 'monospace_load_modules' ) ) return; // needs monospace ART - Core (wp-content/mu-plugins)

define( 'MONOSPACE_PRINTS_DIR', __DIR__ );
define( 'MONOSPACE_PRINTS_URL', plugin_dir_url( __FILE__ ) );

add_filter( 'monospace_feature_prints', '__return_true' );
add_action( 'init', function () { monospace_load_modules( __DIR__ . '/modules' ); } );

add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

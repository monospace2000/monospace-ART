<?php
/**
 * Plugin Name: monospace ART - Admin tools
 * Update URI: false
 * Description: wp-admin conveniences: the artwork toolbar (price, availability, Publish / Unpublish on an artwork's page),
 *              post navigation, save shortcuts, SKU search, media "Used in" (and the unused-media folder), quieter
 *              rating notices. (Dashboard shortcuts: its own plugin since 2026-10-19.)
 * Version: 1.0.0
 * Author: Hens Breet
 *
 * A feature plugin (2026-10-19 restructure, ARCHITECTURE.md): it depends only on monospace ART - Core (the
 * mu-plugin) and can be switched off without breaking anything else. Its modules/ load on init, like the theme's
 * modules did (monospace_load_modules()). monospace_feature( 'admin-tools' ) is true while it's on.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! function_exists( 'monospace_load_modules' ) ) return; // needs monospace ART - Core (wp-content/mu-plugins)

define( 'MONOSPACE_ADMIN_TOOLS_DIR', __DIR__ );
define( 'MONOSPACE_ADMIN_TOOLS_URL', plugin_dir_url( __FILE__ ) );

add_filter( 'monospace_feature_admin-tools', '__return_true' );
add_action( 'init', function () { monospace_load_modules( __DIR__ . '/modules' ); } );

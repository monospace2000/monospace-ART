<?php
/**
 * Plugin Name: monospace ART - Privacy
 * Update URI: false
 * Description: Cookie consent (opt-in for every visitor) and the trackers behind it: Facebook for WooCommerce held
 *              until Accept, its Pixel off.
 * Version: 1.0.0
 * Author: Hens Breet
 *
 * A feature plugin (2026-10-19 restructure, ARCHITECTURE.md): it depends only on monospace ART - Core (the
 * mu-plugin) and can be switched off without breaking anything else. Its modules/ load on init, like the theme's
 * modules did (monospace_load_modules()). monospace_feature( 'privacy' ) is true while it's on.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! function_exists( 'monospace_load_modules' ) ) return; // needs monospace ART - Core (wp-content/mu-plugins)

define( 'MONOSPACE_PRIVACY_DIR', __DIR__ );
define( 'MONOSPACE_PRIVACY_URL', plugin_dir_url( __FILE__ ) );

add_filter( 'monospace_feature_privacy', '__return_true' );
add_action( 'init', function () { monospace_load_modules( __DIR__ . '/modules' ); } );

// Facebook for WooCommerce reads its signal state on init - before modules load - and treats a visitor with no
// state cookie as consenting (it then writes the _fbp cookie). Mark such a visitor as held here; the plugin then
// holds itself and sets the held cookie. Accept on the banner (modules/frontend/forms/cookie-consent.php)
// releases it. (Moved from the theme's functions.php, 2026-10-19.)
if ( ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && empty( $_COOKIE['wc_facebook_signals_state'] ) ) {
    $_COOKIE['wc_facebook_signals_state'] = 'held';
}

// Facebook Pixel off (2026-10-14): no Pixel / Conversions API tracking on the front end. Facebook for
// WooCommerce stays active for its catalog sync to Facebook / Instagram.
add_filter( 'facebook_for_woocommerce_integration_pixel_enabled', '__return_false' );

// ...and no Pixel ID on visitors' pages (2026-10-16): while one is set, the plugin re-sends the
// wc_facebook_signals_state cookie on every page view, and SiteGround's page cache never stores a page that sets
// a cookie (SKIP_CACHE_SET_COOKIE). wp-admin, AJAX, cron, REST and WP-CLI keep the real ID (settings, catalog sync).
if ( ! is_admin() && ! wp_doing_ajax() && ! wp_doing_cron() && ! ( defined( 'WP_CLI' ) && WP_CLI )
    && strpos( $_SERVER['REQUEST_URI'] ?? '', '/wp-json/' ) === false ) {
    add_filter( 'wc_facebook_pixel_id', '__return_empty_string' );
}

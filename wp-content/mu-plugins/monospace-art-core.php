<?php
/**
 * Plugin Name: monospace ART - Core
 * Update URI: false
 * Description: The Artwork = Post + Product paradigm - the part of the site that is never switched off. A
 *              must-use plugin: WordPress loads it before every other plugin, and it can't be deactivated.
 * Version: 1.0.0
 * Author: Hens Breet
 *
 * Layers (2026-10-19 restructure - ARCHITECTURE.md next to public_html has the full map):
 *   Core (this)        what the site is: artwork posts linked to their products, SKUs, availability, details
 *                      and the categories they decide, the price card's logic, the artwork tools (Quick Artwork
 *                      Editor, New artwork, Artwork Editor), product redirects, orders.
 *   Feature plugins    optional (wp-content/plugins/monospace-*): Prints, Sales Dashboard, Commissions, Artwork
 *                      Sheets, Market terminal, SEO, Privacy, Admin tools, Art Filter. Each depends only on Core;
 *                      switched off, its feature is gone and nothing else breaks.
 *   Theme              appearance only (themes/monospace-art-theme).
 * Dependencies point only towards Core. Core reaches a feature only through its extension points (below) or
 * after monospace_feature() says it's there.
 *
 * Extension points (filters):
 *   monospace_feature_<name>      a feature plugin returns true: monospace_feature( 'prints' ) etc.
 *   monospace_price_card_panes    extra tabs on the price card: [ key => [ 'label', 'html' ] ] (Prints adds
 *                                 Framed / Unframed print)
 *   monospace_shop_price          the price a customer pays (the Sales Dashboard applies its adjustments):
 *                                 monospace_shop_price( $product )
 *
 * Modules: this file loads the Quick Artwork Editor at once (it registers hooks for later, as it did as a
 * plugin) and the folder monospace-art-core/modules/ on init - the same moment, order (alphabetical by path)
 * and rules as the theme's module loader, so nothing runs earlier or later than it did in the theme.
 * Feature plugins load their modules/ the same way with monospace_load_modules().
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MONOSPACE_CORE_DIR', __DIR__ . '/monospace-art-core' );
define( 'MONOSPACE_CORE_URL', content_url( 'mu-plugins/monospace-art-core' ) );

/**
 * Load every .php file in $dir (recursively, sorted by path; anything under a disabled/ folder skipped) - the
 * theme's module loader, for plugins. A module the theme still has at the same path is left to the theme (so
 * a module can move from the theme to a plugin without ever being loaded twice), as is one whose theme
 * original is named in $replaces ( plugin path => theme path ).
 */
function monospace_load_modules( $dir, $replaces = [] ) {
    if ( ! is_dir( $dir ) ) return;
    $theme = get_template_directory() . '/includes/modules/';
    $files = [];
    foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
        if ( $f->isFile() && $f->getExtension() === 'php' ) $files[] = $f->getPathname();
    }
    sort( $files );
    foreach ( $files as $path ) {
        $rel = ltrim( substr( $path, strlen( $dir ) ), '/' );
        if ( strpos( '/' . $rel, '/disabled/' ) !== false ) continue;
        if ( file_exists( $theme . $rel ) ) continue;
        if ( isset( $replaces[ $rel ] ) && file_exists( $theme . $replaces[ $rel ] ) ) continue;
        require_once $path;
    }
}

/** Is a feature plugin on? ( 'prints', 'sales', 'commissions', 'sheets', 'terminal', 'seo', 'privacy', 'admin-tools' ) */
function monospace_feature( $name ) {
    return (bool) apply_filters( 'monospace_feature_' . $name, false );
}

/**
 * The price a customer pays for a product ($price: the price to start from, default its current price) - with
 * the Sales Dashboard's adjustments when it's on (monospace_shop_price filter). Works in wp-admin too, unlike
 * WooCommerce's own price filters, which the Sales Dashboard leaves alone there.
 */
function monospace_shop_price( $product, $price = null ) {
    if ( ! $product ) return '';
    $price = $price ?? $product->get_price();
    if ( ! $price ) return $price;
    return apply_filters( 'monospace_shop_price', $price, $product );
}

// The Quick Artwork Editor (the artwork <-> product link). Until 2026-10-19 the plugin monospace-quick-product:
// while that is still active, it wins (it's loaded after this file - loading both would declare the classes twice).
if ( ! in_array( 'monospace-quick-product/monospace-quick-product.php', (array) get_option( 'active_plugins', [] ), true )
    || ! file_exists( WP_PLUGIN_DIR . '/monospace-quick-product/monospace-quick-product.php' ) ) {
    require_once MONOSPACE_CORE_DIR . '/quick-artwork-editor.php';
}

add_action( 'init', function () {
    monospace_load_modules( MONOSPACE_CORE_DIR . '/modules', [
        // Split out of the Prints module (commerce/print-order-shortcode.php) - the theme's copy still has them
        'commerce/price-card-layout.php' => 'commerce/print-order-shortcode.php',
    ] );
} );

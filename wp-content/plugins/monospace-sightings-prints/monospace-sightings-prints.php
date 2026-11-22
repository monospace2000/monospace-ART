<?php
/**
 * Plugin Name: monospace ART - Sightings prints
 * Update URI: false
 * Description: Sells prints of the Sightings of the Strangely Familiar drawings (sightings.monospace.com). The art site is the fulfillment
 *              center: the drawing and its caption are composed into one print file (three layouts), made into hidden Printful print
 *              products, and sold through this site's cart and checkout. Nothing here is visible in the art shop.
 * Version: 1.0.0
 * Author: Hens Breet
 * Requires Plugins: woocommerce
 *
 * A feature plugin (ARCHITECTURE.md): depends on monospace ART - Core and monospace ART - Prints only. monospace_feature( 'sightings_prints' ).
 *
 *   modules/sp-composite.php   draws the print file: the drawing + its caption in a layout (dark band / overlay / light margin)
 *   modules/sp-standin.php     reads the Sightings site, makes the private stand-in post per drawing and layout, queues its prints
 *   modules/sp-hiding.php      keeps the products out of the shop and search
 *   modules/sp-feed.php        the public list of prints on sale (read by the Sightings site)
 *   modules/sp-checkout.php    the order label, the product links, the cart picture
 *   modules/sp-api.php         the key-guarded API the Sightings controls page uses to make prints
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! function_exists( 'monospace_load_modules' ) ) return; // needs monospace ART - Core

define( 'MONOSPACE_SP_DIR', __DIR__ );

add_filter( 'monospace_feature_sightings_prints', '__return_true' );
add_action( 'init', function () {
    if ( ! function_exists( 'monospace_prints_queue' ) ) return; // needs monospace ART - Prints
    monospace_load_modules( __DIR__ . '/modules' );
} );

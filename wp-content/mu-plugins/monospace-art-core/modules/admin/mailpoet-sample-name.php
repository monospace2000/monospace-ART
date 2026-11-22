<?php
/**
 * MailPoet's WooCommerce email editor shows a made-up username in its "New account" sample text. The owner does not want
 * that name anywhere on the site or dashboard (2026-11-15): on MailPoet's own admin pages the name is swapped for
 * "marc.bolan" as the page is sent, so a MailPoet update cannot bring it back (the plugin's own file said it in
 * views/newsletter/templates/blocks/woocommerceContent/new_account.hbs). Only the page's HTML is changed, no stored data.
 *
 * @package monospace-art-core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_init', function () {
    if ( wp_doing_ajax() || empty( $_GET['page'] ) || strpos( (string) $_GET['page'], 'mailpoet' ) !== 0 ) return;
    ob_start( function ( $html ) {
        return str_ireplace( [ 'elon.musk', 'elon musk' ], [ 'marc.bolan', 'Marc Bolan' ], $html );
    } );
} );

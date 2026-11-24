<?php
/**
 * Cart count for the other monospace sites: a small cookie ms_cart = number of items in this visitor's art cart, set for
 * all of monospace.com, so Sightings (and later other properties) can show a cart button with a count without asking
 * this site (the Store API does not allow other origins, and a browser request would meet the host's bot check).
 *
 * Only a number, nothing about what is in the cart. 0 when the cart is emptied or the order is placed, so the button on
 * the other site disappears. Lasts 48 hours, as long as a WooCommerce cart session. Readable by scripts on purpose.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** ".monospace.com" for art.monospace.com (the registrable domain with a leading dot), '' if it cannot be worked out */
function monospace_cart_cookie_domain() {
    $host  = (string) parse_url( home_url(), PHP_URL_HOST );
    $parts = explode( '.', $host );
    return count( $parts ) >= 3 ? '.' . implode( '.', array_slice( $parts, -2 ) ) : '';
}

function monospace_set_cart_count_cookie( $count ) {
    if ( headers_sent() || ! monospace_cart_cookie_domain() ) return;
    $count = max( 0, (int) $count );
    if ( isset( $_COOKIE['ms_cart'] ) && (string) $_COOKIE['ms_cart'] === (string) $count ) return; // unchanged: no new header
    setcookie( 'ms_cart', (string) $count, [
        'expires'  => time() + 2 * DAY_IN_SECONDS,
        'path'     => '/',
        'domain'   => monospace_cart_cookie_domain(),
        'secure'   => true,
        'httponly' => false,
        'samesite' => 'Lax',
    ] );
    $_COOKIE['ms_cart'] = (string) $count;
}

// WooCommerce sets (true) or clears (false) its own cart cookies here: after add to cart, on page loads and at shutdown,
// for classic pages and Store API (block cart) requests alike.
add_action( 'woocommerce_set_cart_cookies', function ( $set ) {
    $count = ( $set && function_exists( 'WC' ) && WC()->cart ) ? WC()->cart->get_cart_contents_count() : 0;
    monospace_set_cart_count_cookie( $count );
} );

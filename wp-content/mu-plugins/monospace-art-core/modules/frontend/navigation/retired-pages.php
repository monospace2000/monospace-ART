<?php
/**
 * Retired pages (2026-10-15 menu redesign): permanent redirects, and left out of Jetpack's sitemap.
 *
 * Services, Connect, Markets and Events and About only served as menu headers - the menu now has
 * real headers (menu-items.php). They stay published, because their child pages' URLs run through
 * them (/services/custom-work/, /connect/contact/, ...); deleting one would change those URLs.
 * Art Shop is WooCommerce's shop page (its archive of all 497 products); Print Gallery is obsolete.
 *
 * Keyed by page path (get_page_uri). Editing them in wp-admin still works; "View page" redirects.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_RETIRED_PAGES = [
    'art-shop'           => '/category/available/',
    'services'           => '/services/custom-work/',
    'markets-and-events' => '/markets-and-events/schedule/',
    'about'              => '/about/about-me/',
    'connect'            => '/connect/contact/',
    'print-gallery'      => '/category/print/',
];

add_action( 'template_redirect', function () {
    if ( is_search() ) return; // WooCommerce product searches also count as the shop

    $page_id = 0;
    if ( function_exists( 'is_shop' ) && is_shop() ) {
        $page_id = wc_get_page_id( 'shop' );
    } elseif ( is_page() ) {
        $page_id = get_queried_object_id();
    }
    if ( $page_id <= 0 ) return;

    $path = get_page_uri( $page_id );
    if ( isset( MONOSPACE_RETIRED_PAGES[ $path ] ) ) {
        wp_safe_redirect( home_url( MONOSPACE_RETIRED_PAGES[ $path ] ), 301, 'monospace retired page' );
        exit;
    }
}, 5 );

/**
 * Retired archive addresses (2026-10-19): a category merged into another. Only when the old address
 * would be a 404, so a category re-created with that name wins. Keyed by path, any /page/N/ after it.
 */
const MONOSPACE_RETIRED_ARCHIVES = [
    'category/medium/line-wash' => '/category/medium/ink/', // "line & wash" merged into Ink & Wash
    'category/line-wash'        => '/category/medium/ink/',
];

add_action( 'template_redirect', function () {
    if ( ! is_404() ) return;
    $path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    $path = preg_replace( '#/page/\d+$#', '', $path );
    if ( isset( MONOSPACE_RETIRED_ARCHIVES[ $path ] ) ) {
        wp_safe_redirect( home_url( MONOSPACE_RETIRED_ARCHIVES[ $path ] ), 301, 'monospace retired archive' );
        exit;
    }
}, 5 );

/**
 * /art-shop/<category>/ (2026-10-30): /art-shop/ is the base of the product addresses
 * (/art-shop/print/<product>/), so the address with only a category after it is a natural guess.
 * The menu's Shop and Work entries, singular or plural - without this they 404 (or, for /art-shop/print/,
 * WordPress guessed a random print). Only when it would be a 404; a /page/N/ after it is kept.
 */
const MONOSPACE_ART_SHOP_SHORTCUTS = [
    'available'  => '/category/available/',
    'original'   => '/category/available/',
    'originals'  => '/category/available/',
    'print'      => '/category/print/',
    'prints'     => '/category/print/',
    'miniature'  => '/category/format/miniature/',
    'miniatures' => '/category/format/miniature/',
    'favorite'   => '/category/favorite/',
    'favorites'  => '/category/favorite/',
    'painting'   => '/category/format/painting/',
    'paintings'  => '/category/format/painting/',
    'drawing'    => '/category/format/drawing/',
    'drawings'   => '/category/format/drawing/',
];

add_action( 'template_redirect', function () {
    if ( ! is_404() ) return;
    $path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    if ( ! preg_match( '#^art-shop/([a-z-]+)(?:/page/(\d+))?$#', $path, $m ) || ! isset( MONOSPACE_ART_SHOP_SHORTCUTS[ $m[1] ] ) ) return;
    $to = MONOSPACE_ART_SHOP_SHORTCUTS[ $m[1] ] . ( ! empty( $m[2] ) ? 'page/' . $m[2] . '/' : '' );
    wp_safe_redirect( home_url( $to ), 301, 'monospace art-shop shortcut' );
    exit;
}, 5 );

add_filter( 'jetpack_sitemap_skip_post', function ( $skip, $post ) {
    if ( $skip || ! is_object( $post ) || ( $post->post_type ?? '' ) !== 'page' ) return $skip;
    return isset( MONOSPACE_RETIRED_PAGES[ get_page_uri( $post->ID ) ] );
}, 10, 2 );

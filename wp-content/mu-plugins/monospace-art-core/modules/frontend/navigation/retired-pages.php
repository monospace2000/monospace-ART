<?php
/**
 * Retired pages (2026-10-15 menu redesign): permanent redirects, and left out of Jetpack's sitemap.
 *
 * Art Shop is WooCommerce's shop page (its archive of all 497 products) and forwards to Available Originals.
 * The old menu-header pages (Services, Connect, Markets and Events, About) used to be listed here too: they stayed published
 * because their children's addresses ran through them. Since 2026-11-16 (owner) they are folders - Private pages with children,
 * no forwarding, a bare folder address is a 404 and that is intended (Admin tools' page-folders.php); the entries were removed.
 *
 * Keyed by page path (get_page_uri). Editing them in wp-admin still works; "View page" redirects.
 * wp-admin's Pages list labels each one "Forwarding page → /where/" (2026-11-16, owner: "label them") and, for a parent, how many
 * child pages run through it - the list below is the one definition of which pages these are.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_RETIRED_PAGES = [
    'art-shop'           => '/category/artworks/available/',
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
    'category/medium/line-wash'         => '/category/artworks/medium/ink/', // "line & wash" merged into the one line & wash category (slug ink)
    'category/line-wash'                => '/category/artworks/medium/ink/',
    'category/artworks/medium/line-wash' => '/category/artworks/medium/ink/',
    'category/print'                    => '/category/artworks/prints/',   // the category's slug became prints (2026-11)
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
    'available'  => '/category/artworks/available/',
    'original'   => '/category/artworks/available/',
    'originals'  => '/category/artworks/available/',
    'print'      => '/category/artworks/prints/',
    'prints'     => '/category/artworks/prints/',
    'miniature'  => '/category/artworks/format/miniature/',
    'miniatures' => '/category/artworks/format/miniature/',
    'favorite'   => '/category/artworks/collections/favorite/',
    'favorites'  => '/category/artworks/collections/favorite/',
    'painting'   => '/category/artworks/format/painting/',
    'paintings'  => '/category/artworks/format/painting/',
    'drawing'    => '/category/artworks/format/drawing/',
    'drawings'   => '/category/artworks/format/drawing/',
];

add_action( 'template_redirect', function () {
    if ( ! is_404() ) return;
    $path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    if ( ! preg_match( '#^art-shop/([a-z-]+)(?:/page/(\d+))?$#', $path, $m ) || ! isset( MONOSPACE_ART_SHOP_SHORTCUTS[ $m[1] ] ) ) return;
    $to = MONOSPACE_ART_SHOP_SHORTCUTS[ $m[1] ] . ( ! empty( $m[2] ) ? 'page/' . $m[2] . '/' : '' );
    wp_safe_redirect( home_url( $to ), 301, 'monospace art-shop shortcut' );
    exit;
}, 5 );

// The Pages list in wp-admin: a label next to the title, so these pages aren't taken for real ones (WordPress / WooCommerce add their own
// "Front Page", "Posts Page", "Shop Page" labels beside it)
add_filter( 'display_post_states', function ( $states, $post ) {
    if ( ! is_object( $post ) || ( $post->post_type ?? '' ) !== 'page' ) return $states;
    if ( ( $post->post_status ?? '' ) !== 'publish' ) return $states;   // it only forwards while published (an unpublished one is a folder: Admin tools' page-folders.php)
    $path = get_page_uri( $post->ID );
    if ( ! isset( MONOSPACE_RETIRED_PAGES[ $path ] ) ) return $states;
    $kids  = count( get_posts( [ 'post_type' => 'page', 'post_parent' => $post->ID, 'post_status' => [ 'publish', 'draft', 'private' ], 'numberposts' => -1, 'fields' => 'ids' ] ) );
    $label = 'Forwarding page → ' . esc_html( MONOSPACE_RETIRED_PAGES[ $path ] );
    if ( $kids ) $label .= ' · parent of ' . $kids . ( $kids === 1 ? ' page' : ' pages' );
    $states['ms_forwarding'] = '<span class="ms-pg-chip ms-pg-forwarding">' . $label . '</span>';
    return $states;
}, 10, 2 );

add_filter( 'jetpack_sitemap_skip_post', function ( $skip, $post ) {
    if ( $skip || ! is_object( $post ) || ( $post->post_type ?? '' ) !== 'page' ) return $skip;
    return isset( MONOSPACE_RETIRED_PAGES[ get_page_uri( $post->ID ) ] );
}, 10, 2 );

<?php
/**
 * Short links: art.monospace.com/<slug> leads to a page, a category or any address.
 *
 * The list is the option monospace_short_links: [ [ 'slug', 'target', 'code' (302 | 301), 'note' (optional: an admin-only note),
 * 'below' (optional: also forward everything below the slug - /my-account/orders/ to <target>/orders/) ] ]. A target is stored as
 *   post:<id>              a page / post / product - its address is looked up each time, so a renamed page is followed
 *   term:<taxonomy>:<id>   a category, product category or tag - likewise
 *   url:<address>          a path on this site (/art-filter/?sort=price) or a full address
 * A short link only answers where the site has nothing of its own (a 404): a real page at the same address always wins,
 * and the screen refuses a slug a page already uses. The visitor's own query string (utm_..., ?sort=) rides along.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_SHORT_LINKS_OPTION = 'monospace_short_links';

/** First part of an address WordPress or WooCommerce use themselves - never a short link */
function monospace_short_links_reserved() {
    return [ 'wp-admin', 'wp-json', 'wp-content', 'wp-includes', 'wp-login.php', 'wp-sitemap.xml', 'feed', 'category', 'tag', 'art-shop',
        'art-category', 'art-tag', 'cart', 'checkout', 'my-account', 'sitemap.xml', 'robots.txt', 'xmlrpc.php', 'artwork-sheets', 'artwork-slideshows' ];
}

function monospace_short_links_all() {
    $list = get_option( MONOSPACE_SHORT_LINKS_OPTION, [] );
    $list = is_array( $list ) ? array_values( array_filter( $list, function ( $l ) { return is_array( $l ) && ! empty( $l['slug'] ); } ) ) : [];
    usort( $list, function ( $a, $b ) { return strnatcasecmp( $a['slug'], $b['slug'] ); } ); // A-Z by short address
    return $list;
}

/** lower case, letters / digits / - _ and / between parts, no slashes at the ends */
function monospace_short_links_clean_slug( $slug ) {
    $slug = strtolower( trim( (string) $slug ) );
    $slug = preg_replace( '#^https?://[^/]+/#', '', $slug );
    $slug = preg_replace( '/[^a-z0-9_\/-]+/', '-', $slug );
    $slug = preg_replace( '#/{2,}#', '/', $slug );
    return trim( $slug, '-/' );
}

/** Why this slug can't be used ('' = fine) - for the screen */
function monospace_short_links_slug_problem( $slug ) {
    if ( $slug === '' ) return __( 'The address is empty.', 'monospace-short-links' );
    if ( in_array( strtok( $slug, '/' ), monospace_short_links_reserved(), true ) ) return __( 'That start of an address belongs to WordPress or the shop.', 'monospace-short-links' );
    if ( get_page_by_path( $slug, OBJECT, [ 'page', 'post', 'product' ] ) ) return __( 'A page already lives at that address.', 'monospace-short-links' );
    return '';
}

/** The address a target leads to ('' when the page or category is gone) */
function monospace_short_links_url( $target ) {
    $target = (string) $target;
    if ( strpos( $target, 'post:' ) === 0 ) {
        $id = (int) substr( $target, 5 );
        if ( get_post_status( $id ) === 'publish' ) return (string) get_permalink( $id );
        // A hidden artwork (Core's Hide artwork; the target may be its product): to the Art Filter, not a dead end
        $art = get_post_type( $id ) === 'product' ? (int) get_post_meta( $id, '_related_post_id', true ) : $id;
        return $art && function_exists( 'monospace_artwork_is_hidden' ) && monospace_artwork_is_hidden( $art ) ? monospace_artwork_hidden_redirect_url() : '';
    }
    if ( strpos( $target, 'term:' ) === 0 ) {
        $parts = explode( ':', $target );
        $url   = ( count( $parts ) === 3 && taxonomy_exists( $parts[1] ) ) ? get_term_link( (int) $parts[2], $parts[1] ) : '';
        return is_wp_error( $url ) ? '' : (string) $url;
    }
    if ( strpos( $target, 'url:' ) === 0 ) {
        $url = trim( substr( $target, 4 ) );
        if ( $url === '' ) return '';
        if ( $url[0] === '/' ) return home_url( $url );
        return preg_match( '#^https?://#i', $url ) ? $url : '';
    }
    return '';
}

/** The site path of a URL on this site, with a trailing slash ('' for another site or no path) */
function monospace_short_links_site_path( $url ) {
    $url = (string) $url;
    if ( $url === '' || $url[0] === '#' ) return '';
    $host = wp_parse_url( $url, PHP_URL_HOST );
    if ( $host && $host !== wp_parse_url( home_url(), PHP_URL_HOST ) ) return '';
    $path = (string) wp_parse_url( $url, PHP_URL_PATH );
    $base = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
    if ( $base !== '' && strpos( $path, $base ) === 0 ) $path = substr( $path, strlen( $base ) );
    return $path === '' ? '' : trailingslashit( $path );
}

/** A short link's full address, as shared */
function monospace_short_links_public_url( $slug ) {
    return home_url( '/' . $slug );
}

add_action( 'template_redirect', function () {
    if ( ! is_404() || is_admin() ) return;
    $path = (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
    $base = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
    if ( $base !== '' && strpos( $path, $base ) === 0 ) $path = substr( $path, strlen( $base ) );
    $path = strtolower( trim( rawurldecode( $path ), '/' ) );
    if ( $path === '' ) return;

    foreach ( monospace_short_links_all() as $link ) {
        $rest = '';
        if ( $link['slug'] !== $path ) {
            // A link that also covers everything below it (an old account address with its endpoints, for links in sent emails)
            if ( empty( $link['below'] ) || strpos( $path, $link['slug'] . '/' ) !== 0 ) continue;
            $rest = substr( $path, strlen( $link['slug'] ) + 1 );
        }
        $url = monospace_short_links_url( $link['target'] ?? '' );
        if ( $url === '' ) return;
        if ( $rest !== '' ) $url = trailingslashit( $url ) . $rest . '/';
        if ( ! empty( $_SERVER['QUERY_STRING'] ) ) $url .= ( strpos( $url, '?' ) === false ? '?' : '&' ) . $_SERVER['QUERY_STRING'];
        $code = (int) ( $link['code'] ?? 302 ) === 301 ? 301 : 302;
        header( 'X-Robots-Tag: noindex' );
        wp_redirect( $url, $code, 'Short link' );
        exit;
    }
}, 1 ); // before WordPress guesses a nearby address for the 404 (redirect_canonical, priority 10)

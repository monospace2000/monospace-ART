<?php
/**
 * Feature plugins as master switches (2026-10-19, owner's wish: "deactivating a plugin should switch its
 * feature off - cleanly - and activating it switch it back on").
 *
 * A switched-off plugin takes its own code with it, but some of its feature lives in the site's content: menu
 * items and home page boxes that link to its pages, the pages themselves, products only it knows how to sell,
 * shortcodes in pages. Core can't ask the plugin (it isn't loaded), so the map below says what belongs to
 * which feature, and while monospace_feature( <name> ) is false:
 *   - menu items linking to its paths are left out (and a "—" separator left dangling by that),
 *   - its pages redirect to the home page (302 - temporary, it comes back with the plugin),
 *   - its products (product categories) can't be bought, their product pages redirect home, and Facebook for
 *     WooCommerce stops syncing them to the Meta catalog,
 *   - its shortcodes print nothing (instead of their [raw text]).
 * Nothing is deleted or changed in the database - activate the plugin and it's all back. The home page boxes
 * are left alone on purpose (owner: the Layout Manager is separate) - a box to a switched-off page follows the redirect.
 * Code elsewhere that reads a feature's data (print links, Sales Dashboard options) checks monospace_feature().
 *
 * Paths are site paths (no domain); a path covers everything below it. Filter: monospace_feature_switch_map.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_feature_switch_map() {
    return apply_filters( 'monospace_feature_switch_map', [
        'prints'      => [ 'paths' => [ '/category/print/' ], 'product_cats' => [ 'print' ], 'shortcodes' => [ 'print_order_button' ] ],
        'commissions' => [ 'paths' => [ '/services/custom-work/' ], 'product_cats' => [ 'custom-order' ] ],
        'art-filter'  => [ 'paths' => [ '/art-filter/' ], 'shortcodes' => [ 'monospace_art_filter' ] ],
        'terminal'    => [ 'paths' => [ '/inventory/' ], 'shortcodes' => [ 'market_inventory_terminal' ] ],
    ] );
}

/** The map's entries for the features that are off right now */
function monospace_features_off() {
    $off = [];
    foreach ( monospace_feature_switch_map() as $name => $parts ) {
        if ( ! monospace_feature( $name ) ) $off[ $name ] = $parts;
    }
    return $off;
}

/** The site path of a URL or path, with a trailing slash ('' for another site or no path) */
function monospace_feature_switch_path( $url ) {
    $url = (string) $url;
    if ( $url === '' || $url[0] === '#' ) return '';
    $host = wp_parse_url( $url, PHP_URL_HOST );
    if ( $host && $host !== wp_parse_url( home_url(), PHP_URL_HOST ) ) return '';
    $path = (string) wp_parse_url( $url, PHP_URL_PATH );
    $base = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
    if ( $base !== '' && strpos( $path, $base ) === 0 ) $path = substr( $path, strlen( $base ) );
    return $path === '' ? '' : trailingslashit( $path );
}

/** Does this link lead to a switched-off feature? (menus, anything that links) */
function monospace_feature_link_off( $url ) {
    $path = monospace_feature_switch_path( $url );
    if ( $path === '' ) return false;
    foreach ( monospace_features_off() as $parts ) {
        foreach ( $parts['paths'] ?? [] as $p ) {
            if ( strpos( $path, $p ) === 0 ) return true;
        }
    }
    return false;
}

/** Is this product (or the parent of this variation) sold only through a switched-off feature? */
function monospace_feature_product_off( $product ) {
    $id = $product instanceof WC_Product ? ( $product->get_parent_id() ?: $product->get_id() ) : (int) $product;
    foreach ( monospace_features_off() as $parts ) {
        if ( ! empty( $parts['product_cats'] ) && has_term( $parts['product_cats'], 'product_cat', $id ) ) return true;
    }
    return false;
}

// Menus: leave out the items, their sub-items, and separators ("—" with no link) left at an edge or doubled
add_filter( 'wp_nav_menu_objects', function ( $items ) {
    if ( ! monospace_features_off() ) return $items;
    $gone = [];
    foreach ( $items as $item ) {
        if ( monospace_feature_link_off( $item->url ) || isset( $gone[ (int) $item->menu_item_parent ] ) ) $gone[ (int) $item->ID ] = true;
    }
    if ( ! $gone ) return $items;
    $items = array_values( array_filter( $items, function ( $i ) use ( $gone ) { return ! isset( $gone[ (int) $i->ID ] ); } ) );

    $is_sep   = function ( $i ) { return trim( wp_strip_all_tags( $i->title ) ) === '—' && in_array( $i->url, [ '', '#' ], true ); };
    $siblings = [];
    foreach ( $items as $k => $i ) $siblings[ (int) $i->menu_item_parent ][] = $k;
    $drop = [];
    foreach ( $siblings as $keys ) {
        $prev_sep = true; // a separator first in its group goes
        foreach ( $keys as $n => $k ) {
            if ( $is_sep( $items[ $k ] ) ) {
                if ( $prev_sep || $n === count( $keys ) - 1 ) $drop[ $k ] = true;
                $prev_sep = true;
            } else {
                $prev_sep = false;
            }
        }
        // a separator that ended up last once the one after it went
        for ( $n = count( $keys ) - 1; $n >= 0 && ( isset( $drop[ $keys[ $n ] ] ) || $is_sep( $items[ $keys[ $n ] ] ) ); $n-- ) $drop[ $keys[ $n ] ] = true;
    }
    return array_values( array_diff_key( $items, $drop ) );
}, 20 );

// Pages of a switched-off feature: to the home page, for now. Its product pages: to their artwork when they
// have one (a print → the artwork's post), else home.
add_action( 'template_redirect', function () {
    if ( is_admin() || ! monospace_features_off() ) return;
    $to = '';
    if ( is_singular( 'product' ) && monospace_feature_product_off( get_queried_object_id() ) ) {
        $post_id = (int) get_post_meta( get_queried_object_id(), '_related_post_id', true );
        $to      = $post_id && get_post_status( $post_id ) === 'publish' ? get_permalink( $post_id ) : home_url( '/' );
    } elseif ( monospace_feature_link_off( home_url( add_query_arg( [] ) ) ) ) {
        $to = home_url( '/' );
    }
    if ( $to ) {
        wp_safe_redirect( $to, 302, 'monospace ART feature switch' );
        exit;
    }
}, 1 );

// Its products can't be bought (cart links, a cart saved before the switch, the Meta shop)
add_filter( 'woocommerce_is_purchasable', function ( $yes, $product ) {
    return $yes && ! monospace_feature_product_off( $product );
}, 20, 2 );
add_filter( 'woocommerce_variation_is_purchasable', function ( $yes, $variation ) {
    return $yes && ! monospace_feature_product_off( $variation );
}, 20, 2 );

// …and leave the Meta catalog until the feature is back (Facebook for WooCommerce)
add_filter( 'wc_facebook_should_sync_product', function ( $yes, $product ) {
    return $yes && ! ( $product instanceof WC_Product && monospace_feature_product_off( $product ) );
}, 20, 2 );

// Its shortcodes print nothing - registered late on init, only when the plugin didn't register its own
add_action( 'init', function () {
    foreach ( monospace_features_off() as $parts ) {
        foreach ( $parts['shortcodes'] ?? [] as $tag ) {
            if ( ! shortcode_exists( $tag ) ) add_shortcode( $tag, '__return_empty_string' );
        }
    }
}, 99 );

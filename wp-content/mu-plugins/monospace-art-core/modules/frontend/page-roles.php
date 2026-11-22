<?php
/**
 * Page roles (2026-11-16, owner restructured the pages into virtual folders - /services/commissions/policies/ - and code that
 * looked a page up by its slug or hard-coded its address broke: the commission terms and steps fell back to built-in text, the
 * footer lost the GPSR link, the feature switch watched the wrong addresses).
 *
 * A few pages are used by the code itself, not just linked: the commission terms and steps (read into the product page and the
 * emails), the page that is the commission order list, the FAQ, the Price Estimator, the Terminal, the Inventory Manager, the EU
 * GPSR notice. Each carries a ROLE in the page meta _monospace_role, so the code finds the page wherever it is moved or whatever its
 * slug becomes (a page keeps its meta, not its address). Roles are explicit data set once - never guessed from a title or a slug.
 *
 *   monospace_role_page( $role )  the published or private page (WP_Post), or null
 *   monospace_role_url( $role )   its address ('' = none)
 *   monospace_role_path( $role )  its site path with slashes ('' = none) - what the feature switch watches
 *   monospace_term_path( $slug )  a category's site path, as the tree has it now ('' = none)
 *
 * The Pages list shows the role next to the title ("Used by the site: commission terms"), so a page the code depends on isn't
 * deleted or re-created by mistake (a re-created page has no role: set _monospace_role on it again).
 *
 * @package monospace-art-core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The roles: role => label */
function monospace_page_roles() {
    return [
        'custom-artwork'          => 'custom order page',
        'commission-policies'     => 'commission terms',
        'commission-how-it-works' => 'commission steps',
        'commission-faq'          => 'commission FAQ',
        'estimator'               => 'Price Estimator',
        'inventory'               => 'Inventory Manager',
        'terminal'                => 'Terminal',
        'gpsr'                    => 'EU GPSR notice',
        'markets'                 => 'market schedule (the [next_market] shortcode)',
    ];
}

/** Role => page ID, one query per request */
function monospace_role_ids() {
    static $map = null;
    if ( $map !== null ) return $map;
    $map = [];
    foreach ( get_posts( [ 'post_type' => 'page', 'post_status' => [ 'publish', 'private', 'draft' ], 'numberposts' => -1, 'fields' => 'ids',
                           'meta_key' => '_monospace_role', 'no_found_rows' => true ] ) as $id ) {
        $role = (string) get_post_meta( $id, '_monospace_role', true );
        if ( isset( monospace_page_roles()[ $role ] ) && ! isset( $map[ $role ] ) ) $map[ $role ] = (int) $id;
    }
    return $map;
}

/** The page with this role (any status except trash), or null */
function monospace_role_page( $role ) {
    $id = monospace_role_ids()[ $role ] ?? 0;
    return $id ? get_post( $id ) : null;
}

/** Its address, '' while there is none or it isn't published */
function monospace_role_url( $role ) {
    $page = monospace_role_page( $role );
    return $page && $page->post_status === 'publish' ? (string) get_permalink( $page ) : '';
}

/** Its site path ("/services/commissions/policies/"), '' while there is none */
function monospace_role_path( $role ) {
    $page = monospace_role_page( $role );
    return $page ? trailingslashit( (string) wp_parse_url( get_permalink( $page ), PHP_URL_PATH ) ) : '';
}

/** A category's site path in the tree as it is now ("/category/artworks/prints/"), '' when there is no such category */
function monospace_term_path( $slug, $taxonomy = 'category' ) {
    $term = get_term_by( 'slug', $slug, $taxonomy );
    return $term ? trailingslashit( (string) wp_parse_url( (string) get_term_link( $term ), PHP_URL_PATH ) ) : '';
}

// The Pages list: which pages the code depends on
add_filter( 'display_post_states', function ( $states, $post ) {
    if ( ! is_object( $post ) || ( $post->post_type ?? '' ) !== 'page' ) return $states;
    $role = array_search( (int) $post->ID, monospace_role_ids(), true );
    if ( $role !== false ) $states['ms_role'] = '<span class="ms-pg-chip ms-pg-role">Used by the site: ' . esc_html( monospace_page_roles()[ $role ] ) . '</span>';
    return $states;
}, 10, 2 );

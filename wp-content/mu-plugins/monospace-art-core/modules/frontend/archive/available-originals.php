<?php
/**
 * Available Originals - /category/available/
 *
 * A real category (term "available", created 2026-10-15) so the page is an ordinary category archive:
 * feed header, list/grid toggle, sidebar and SEO all come from its term settings, like Paintings or
 * Prints. No post is ever put in it - its archive query is replaced here by the posts whose original
 * is for sale right now, so it can't go stale.
 *
 * For sale = what the price card sells (buy-button-shortcode.php): in stock, has a price, status
 * available (or none). Reserved, gallery and private works are left out - the Art Filter's
 * "Available" is broader (anything in stock and not private). Prints don't count; originals only.
 * Miniatures (post category "miniature") are left out too - they have their own page.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_AVAILABLE_SLUG = 'available';

/** Post IDs of the artworks whose original can be bought now, miniatures included. */
function monospace_for_sale_post_ids() {
    static $ids = null;
    if ( $ids !== null ) return $ids;

    $products = get_posts( [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => [ [
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => [ 'custom-order', 'print' ],
            'operator' => 'NOT IN',
        ] ],
        'meta_query'     => [
            [ 'key' => '_stock_status', 'value' => 'instock' ],
            [ 'key' => '_price', 'value' => 0, 'compare' => '>', 'type' => 'DECIMAL(10,2)' ],
            [ 'key' => '_related_post_id', 'compare' => 'EXISTS' ],
        ],
    ] );

    $ids = [];
    foreach ( $products as $product_id ) {
        $status = (string) get_post_meta( $product_id, 'painting_availability_status', true );
        if ( ! in_array( $status, [ '', 'available' ], true ) ) continue;
        $post_id = (int) get_post_meta( $product_id, '_related_post_id', true );
        if ( $post_id && get_post_status( $post_id ) === 'publish' && get_post_type( $post_id ) === 'post' ) {
            $ids[] = $post_id;
        }
    }
    return $ids = array_values( array_unique( $ids ) );
}

/**
 * Post IDs of the artworks with a print on sale: an unframed or framed print product (the post's
 * _print_product_id / _framed_print_product_id) that is published and in stock - the price card's
 * print tabs show under the same conditions. The archive toolbar's "Prints available".
 */
function monospace_prints_for_sale_post_ids() {
    static $ids = null;
    if ( $ids !== null ) return $ids;
    if ( ! monospace_feature( 'prints' ) ) return $ids = []; // the Prints plugin is off
    global $wpdb;
    $rows = $wpdb->get_results( "SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm
        JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'post' AND p.post_status = 'publish'
        WHERE pm.meta_key IN ( '_print_product_id', '_framed_print_product_id' ) AND pm.meta_value <> ''" );
    $ids = [];
    foreach ( $rows as $r ) {
        $pid = (int) $r->meta_value;
        if ( $pid && get_post_status( $pid ) === 'publish' && get_post_meta( $pid, '_stock_status', true ) === 'instock' ) {
            $ids[] = (int) $r->post_id;
        }
    }
    return $ids = array_values( array_unique( $ids ) );
}

/** The Available Originals page's posts: for sale, minus miniatures (they have their own page - owner's choice, 2026-10-15). */
function monospace_available_post_ids() {
    static $ids = null;
    if ( $ids !== null ) return $ids;
    $miniatures = get_posts( [ 'category_name' => 'miniature', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ] );
    return $ids = array_values( array_diff( monospace_for_sale_post_ids(), $miniatures ) );
}

// The archive shows those posts (newest first, paged as usual) instead of the term's (none)
add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() || ! $query->is_main_query() || ! $query->is_category( MONOSPACE_AVAILABLE_SLUG ) ) return;

    // is_category() above cached the term as the queried object - the header, toggle and SEO read it
    $query->set( 'category_name', '' );
    $query->set( 'cat', '' );
    $query->set( 'post_type', 'post' );
    $query->set( 'ignore_sticky_posts', true );
    $query->set( 'post__in', monospace_available_post_ids() ?: [ 0 ] );
} );

/* ------------------------------------------------------------
 * Not offered when editing posts - ticking it would do nothing for the page, but the post would say
 * "Filed under: Available Originals" even after it sells. Still listed (and editable) under
 * Posts > Categories.
 * ------------------------------------------------------------ */

function monospace_available_term_id() {
    static $id = null, $busy = false;
    if ( $id !== null ) return $id;
    if ( $busy ) return 0; // get_term_by() runs a term query - which comes back through get_terms_args below
    $busy = true;
    $term = get_term_by( 'slug', MONOSPACE_AVAILABLE_SLUG, 'category' );
    $busy = false;
    return $id = $term ? (int) $term->term_id : 0;
}

// Block editor: its category panel loads the list through the REST API (edit context, logged in)
add_filter( 'rest_category_query', function ( $args, $request ) {
    if ( $request['context'] === 'edit' && ( $id = monospace_available_term_id() ) ) {
        $args['exclude'] = array_merge( (array) ( $args['exclude'] ?? [] ), [ $id ] );
    }
    return $args;
}, 10, 2 );

// Classic editor / Quick Edit checklists
add_filter( 'get_terms_args', function ( $args, $taxonomies ) {
    global $pagenow;
    if ( ! is_admin() || ! in_array( $pagenow, [ 'post.php', 'post-new.php', 'edit.php' ], true ) ) return $args;
    if ( ! in_array( 'category', (array) $taxonomies, true ) || ! ( $id = monospace_available_term_id() ) ) return $args;
    $args['exclude'] = array_merge( wp_parse_id_list( $args['exclude'] ?? [] ), [ $id ] );
    return $args;
}, 10, 2 );

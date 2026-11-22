<?php
/**
 * Surprise Me (2026-10-21, owner: "'Surprise Me' - open a random artwork post + shortcode").
 *
 *   art.monospace.com/random-artwork       a random published artwork (a post with a product - monospace_is_artwork's test),
 *                                          redirected to its post (owner, 2026-10-21; /surprise/ goes to the same place)
 *   /random-artwork?category=print         only from a category (or a tag / place: ?tag=hoboken)
 *   /random-artwork?available=1            only originals for sale (monospace_for_sale_post_ids())
 *   [surprise_me]                          a link to it: text="Surprise me", style="button | link", category="", tag="",
 *                                          available="yes"
 * The address works anywhere - as a menu item (Custom Link), a home box's link, in text. Never cached (no-store headers,
 * DONOTCACHEPAGE), so every visit draws again; the one just shown isn't drawn twice in a row (cookie).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The Surprise Me address, with its narrowing */
function monospace_surprise_url( $args = [] ) {
    return add_query_arg( array_filter( [
        'category'  => sanitize_title( $args['category'] ?? '' ),
        'tag'       => sanitize_title( $args['tag'] ?? '' ),
        'available' => ! empty( $args['available'] ) && $args['available'] !== 'no' ? 1 : null,
    ] ), home_url( '/random-artwork' ) );
}

/** A random artwork's post ID for these narrowings (0 = none) */
function monospace_surprise_pick( $args = [], $not = 0 ) {
    $query = [ 'post_type' => 'post', 'post_status' => 'publish', 'fields' => 'ids', 'numberposts' => -1, 'no_found_rows' => true,
               'meta_query' => [ [ 'key' => '_qp_product_id', 'value' => '', 'compare' => '!=' ] ] ];
    if ( ! empty( $args['category'] ) ) $query['category_name'] = sanitize_title( $args['category'] );
    if ( ! empty( $args['tag'] ) ) $query['tag'] = sanitize_title( $args['tag'] );
    if ( ! empty( $args['available'] ) && function_exists( 'monospace_for_sale_post_ids' ) ) $query['post__in'] = monospace_for_sale_post_ids() ?: [ 0 ];
    $ids = get_posts( $query );
    if ( count( $ids ) > 1 && $not ) $ids = array_values( array_diff( $ids, [ (int) $not ] ) );
    return $ids ? (int) $ids[ array_rand( $ids ) ] : 0;
}

// /random-artwork (and /surprise/) - before WordPress decides it's a 404
add_action( 'parse_request', function () {
    $path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    $home = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
    if ( ! in_array( $path, [ ltrim( $home . '/random-artwork', '/' ), ltrim( $home . '/surprise', '/' ) ], true ) ) return;
    if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
    nocache_headers();
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    $args = [ 'category' => $_GET['category'] ?? '', 'tag' => $_GET['tag'] ?? '', 'available' => $_GET['available'] ?? '' ];
    $last = absint( $_COOKIE['ms_surprise_last'] ?? 0 );
    $id   = monospace_surprise_pick( $args, $last );
    if ( ! $id ) { wp_safe_redirect( home_url( '/' ), 302 ); exit; }
    setcookie( 'ms_surprise_last', (string) $id, 0, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );
    wp_safe_redirect( get_permalink( $id ), 302 );
    exit;
}, 1 );

add_shortcode( 'surprise_me', function ( $atts ) {
    $a = shortcode_atts( [ 'text' => 'Surprise me', 'style' => 'button', 'category' => '', 'tag' => '', 'available' => '' ], $atts );
    $class = $a['style'] === 'link' ? 'ms-surprise-link' : 'ms-surprise-button button';
    return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( monospace_surprise_url( $a ) ) . '" rel="nofollow">' . esc_html( $a['text'] ) . '</a>';
} );

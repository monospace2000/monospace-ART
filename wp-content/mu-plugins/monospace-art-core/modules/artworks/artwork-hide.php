<?php
/**
 * Hide artwork / Show artwork (2026-11-12, owner's wish: "unpublish or hide artworks from the site entirely - blog post and
 * product and everything related in one place"). The reversible sibling of Remove artwork (artwork-remove.php).
 *
 * Hide = the Art Post and the product both become Drafts, and whatever features have attached to the artwork hide their
 * part too (the Prints plugin hides the prints with its own Hide prints). Show = everything back. Nothing is deleted,
 * the SKU stays, and an artwork that has been ordered can be hidden (its orders are untouched).
 *
 * State: post meta _ms_hidden = [ 'date', 'user' ] on the Art Post while it is hidden. The statuses themselves say what is
 * on the site; the marker says "hidden on purpose" (a Draft someone is still writing isn't "hidden").
 *
 * Visitors who follow an old link to a hidden artwork (a printed QR code, a shared address) are sent to the Art Filter
 * instead of a dead end - logged-in editors still see the real 404, so they notice. Short links do the same
 * (Short links plugin).
 *
 * Used by: the admin toolbar's Unpublish / Publish (Admin tools plugin), Artworks > Artwork Editor (row link, bulk
 * "Artwork…" menu, the "Not on the site" filter).
 *
 * Extension points (features hook in; Core stays independent of them):
 *   action monospace_artwork_hiding  ( $post_id, $product_id )   do your part of hiding; throw an Exception to stop (nothing hidden yet)
 *   action monospace_artwork_showing ( $post_id, $product_id )   put your part back
 *
 * AJAX monospace_artwork_editor_visibility: op = hide | show, post_ids[] (max 10) - nonce monospace_artwork_editor.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_ARTWORK_HIDDEN_META = '_ms_hidden';

/** Hidden on purpose: the marker is set and the Art Post is off the site */
function monospace_artwork_is_hidden( $post_id ) {
    return (bool) get_post_meta( (int) $post_id, MONOSPACE_ARTWORK_HIDDEN_META, true ) && get_post_status( (int) $post_id ) !== 'publish';
}

/** Where visitors of a hidden artwork's old address go: the Art Filter (else the home page) */
function monospace_artwork_hidden_redirect_url() {
    return function_exists( 'monospace_feature' ) && monospace_feature( 'art-filter' ) ? home_url( '/art-filter/' ) : home_url( '/' );
}

/** Take the artwork off the site: Art Post and product to Draft, features hide their part. True, or the reason it didn't work */
function monospace_artwork_hide( $post_id ) {
    $post_id = (int) $post_id;
    if ( get_post_type( $post_id ) !== 'post' || ! monospace_is_artwork( $post_id ) ) return 'Not an artwork.';
    $product_id = (int) monospace_get_post_product_id( $post_id );

    try {
        do_action( 'monospace_artwork_hiding', $post_id, $product_id );
    } catch ( Exception $e ) {
        return $e->getMessage();
    }

    if ( $product_id && get_post_status( $product_id ) !== 'draft' ) {
        $product = wc_get_product( $product_id );
        if ( $product ) { $product->set_status( 'draft' ); $product->save(); }
    }
    if ( get_post_status( $post_id ) !== 'draft' ) {
        $r = wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ], true );
        if ( is_wp_error( $r ) ) return $r->get_error_message();
    }
    update_post_meta( $post_id, MONOSPACE_ARTWORK_HIDDEN_META, [ 'date' => current_time( 'Y-m-d H:i' ), 'user' => wp_get_current_user()->user_login ] );
    monospace_artwork_visibility_purge();
    return true;
}

/** Put the artwork back on the site. True, or the reason it didn't work */
function monospace_artwork_show( $post_id ) {
    $post_id = (int) $post_id;
    if ( get_post_type( $post_id ) !== 'post' || ! monospace_is_artwork( $post_id ) ) return 'Not an artwork.';
    if ( ! current_user_can( 'publish_post', $post_id ) ) return 'Not allowed to publish.';
    $product_id = (int) monospace_get_post_product_id( $post_id );

    if ( $product_id && get_post_status( $product_id ) !== 'publish' ) {
        $product = wc_get_product( $product_id );
        if ( $product ) { $product->set_status( 'publish' ); $product->save(); }
    }
    if ( get_post_status( $post_id ) !== 'publish' ) {
        $r = wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ], true );
        if ( is_wp_error( $r ) ) return $r->get_error_message();
    }
    delete_post_meta( $post_id, MONOSPACE_ARTWORK_HIDDEN_META );
    try {
        do_action( 'monospace_artwork_showing', $post_id, $product_id );
    } catch ( Exception $e ) {
        return 'Back on the site, but: ' . $e->getMessage();
    }
    monospace_artwork_visibility_purge();
    return true;
}

/** Listings, the Art Filter, the home page boxes and feeds all change: clear the page cache */
function monospace_artwork_visibility_purge() {
    if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache();
}

/* ============================================================
 * VISITORS OF A HIDDEN ARTWORK
 * ============================================================ */

// An old address (printed QR code, shared link) of a hidden artwork: to the Art Filter, not a dead end
add_action( 'template_redirect', function () {
    if ( ! is_404() || is_admin() || current_user_can( 'edit_posts' ) ) return;
    $path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
    if ( $path === '' || strpos( $path, '/' ) !== false ) return;
    $found = get_posts( [ 'post_type' => 'post', 'post_status' => 'draft', 'name' => sanitize_title( rawurldecode( $path ) ),
                          'meta_key' => MONOSPACE_ARTWORK_HIDDEN_META, 'numberposts' => 1, 'fields' => 'ids', 'no_found_rows' => true ] );
    if ( ! $found ) return;
    header( 'X-Robots-Tag: noindex' );
    wp_redirect( monospace_artwork_hidden_redirect_url(), 302, 'Hidden artwork' );
    exit;
}, 2 ); // after short links (1), before WordPress guesses a nearby address (redirect_canonical, 10)

/* ============================================================
 * AJAX - Artwork Editor (row link, bulk menu)
 * ============================================================ */

add_action( 'wp_ajax_monospace_artwork_editor_visibility', function () {
    check_ajax_referer( 'monospace_artwork_editor' );
    if ( ! current_user_can( 'edit_products' ) ) wp_send_json_error( 'Not allowed.' );
    $op  = sanitize_key( $_POST['op'] ?? '' );
    $ids = array_slice( array_map( 'absint', (array) ( $_POST['post_ids'] ?? [] ) ), 0, 10 );
    if ( ! in_array( $op, [ 'hide', 'show' ], true ) || ! $ids ) wp_send_json_error( 'Nothing to do.' );
    $done = $errors = [];
    foreach ( $ids as $post_id ) {
        if ( ! current_user_can( 'edit_post', $post_id ) ) { $errors[ $post_id ] = 'Not an artwork you can edit.'; continue; }
        $r = $op === 'hide' ? monospace_artwork_hide( $post_id ) : monospace_artwork_show( $post_id );
        if ( $r === true ) $done[] = $post_id; else $errors[ $post_id ] = $r;
    }
    wp_send_json_success( [ 'rows' => function_exists( 'monospace_artwork_editor_rows' ) ? monospace_artwork_editor_rows( $ids ) : [], 'done' => $done, 'errors' => $errors ] );
} );

<?php
/**
 * Short links' part of Artworks > Remove artwork (Core, artwork-remove.php): a short link that leads to the
 * artwork's post or product is listed on the confirm screen and removed with it, so no short address is left
 * pointing at nothing.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The short links whose target is one of these posts: [ slug => 'post:<id>' ] */
function monospace_short_links_for_posts( array $ids ) {
    $targets = array_map( function ( $id ) { return 'post:' . (int) $id; }, array_filter( $ids ) );
    $found = [];
    foreach ( monospace_short_links_all() as $l ) {
        if ( in_array( $l['target'] ?? '', $targets, true ) ) $found[ $l['slug'] ] = $l['target'];
    }
    return $found;
}

add_filter( 'monospace_artwork_removal_parts', function ( $parts, $post_id, $product_id ) {
    foreach ( monospace_short_links_for_posts( [ $post_id, $product_id ] ) as $slug => $target ) {
        $parts[] = [ 'kind' => 'Short link', 'label' => '/' . $slug, 'url' => admin_url( 'edit.php?post_type=page&page=monospace-short-links' ) ];
    }
    return $parts;
}, 20, 3 );

add_action( 'monospace_artwork_removing', function ( $post_id, $product_id, $mode ) {
    $gone = monospace_short_links_for_posts( [ $post_id, $product_id ] );
    if ( ! $gone ) return;
    $list = get_option( MONOSPACE_SHORT_LINKS_OPTION, [] );
    $list = array_values( array_filter( (array) $list, function ( $l ) use ( $gone ) {
        return ! ( is_array( $l ) && isset( $l['slug'] ) && isset( $gone[ $l['slug'] ] ) );
    } ) );
    update_option( MONOSPACE_SHORT_LINKS_OPTION, $list, false );
}, 20, 3 );

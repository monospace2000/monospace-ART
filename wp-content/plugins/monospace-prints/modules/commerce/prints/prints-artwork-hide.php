<?php
/**
 * Prints' part of Hide artwork (Core, artwork-hide.php): hiding an artwork hides its prints too, with the same Hide prints
 * the owner uses everywhere (prints-pause.php) - nothing is deleted, Printful is not touched. Showing the artwork shows
 * back only the prints that Hide artwork hid: a print the owner had hidden on its own stays hidden.
 * State: post meta _ms_prints_hidden_with_artwork = [ 'framed', 'unframed' ] - the prints hidden by Hide artwork.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'monospace_artwork_hiding', function ( $post_id ) {
    if ( ! function_exists( 'monospace_prints_linked' ) ) return;
    $linked = monospace_prints_linked( $post_id );
    if ( ! $linked ) return;
    $types = array_values( array_diff( array_keys( $linked ), array_keys( monospace_prints_hidden( $post_id ) ) ) );
    if ( ! $types ) return;
    monospace_prints_set_hidden( $post_id, true, $types );
    update_post_meta( $post_id, '_ms_prints_hidden_with_artwork', $types );
} );

add_action( 'monospace_artwork_showing', function ( $post_id ) {
    $types = get_post_meta( $post_id, '_ms_prints_hidden_with_artwork', true );
    delete_post_meta( $post_id, '_ms_prints_hidden_with_artwork' );
    if ( ! is_array( $types ) || ! $types || ! function_exists( 'monospace_prints_set_hidden' ) ) return;
    monospace_prints_set_hidden( $post_id, false, $types );
} );

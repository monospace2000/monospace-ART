<?php
/**
 * Prints' part of Artworks > Remove artwork (Core, artwork-remove.php): the artwork's print products are listed
 * on the confirm screen (and count in its "has been ordered" check), and removed with it - each through
 * monospace_prints_remove() (out of Printful, product to the Trash, unlinked), then, for "Delete permanently",
 * the trashed product is deleted for good. Printful is asked first for every print: if it refuses or a print job
 * is running, an Exception stops the whole removal before anything is gone.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'monospace_artwork_removal_parts', function ( $parts, $post_id, $product_id ) {
    if ( ! $post_id || ! function_exists( 'monospace_print_templates' ) ) return $parts;
    foreach ( monospace_print_all_templates() as $type => $tpl ) {
        $pid = (int) get_post_meta( $post_id, $tpl['meta_key'], true );
        if ( ! $pid || get_post_type( $pid ) !== 'product' ) continue;
        $sku = (string) get_post_meta( $pid, '_sku', true );
        $parts[] = [
            'kind'    => 'Print product',
            'label'   => $tpl['label'] . ' (SKU ' . ( $sku !== '' ? $sku : 'none' ) . ', ' . get_post_status( $pid ) . ', #' . $pid . ') - also removed in Printful',
            'url'     => get_edit_post_link( $pid, 'raw' ),
            'product' => $pid,
        ];
    }
    return $parts;
}, 10, 3 );

add_action( 'monospace_artwork_removing', function ( $post_id, $product_id, $mode ) {
    if ( ! $post_id || ! function_exists( 'monospace_prints_remove' ) ) return;
    $templates = monospace_print_all_templates();

    // Check everything that could refuse before removing the first print
    if ( function_exists( 'monospace_prints_get_job' ) && monospace_prints_get_job( $post_id ) ) {
        throw new Exception( 'A print job for this artwork is running - wait until it\'s done.' );
    }

    foreach ( $templates as $type => $tpl ) {
        if ( ! (int) get_post_meta( $post_id, $tpl['meta_key'], true ) ) continue;
        $pid = monospace_prints_remove( $post_id, $type );
        if ( $mode === 'delete' && $pid ) {
            $product = wc_get_product( $pid );
            if ( $product ) $product->delete( true );
        }
    }
}, 10, 3 );

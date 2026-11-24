<?php
/**
 * Auto-Sync Availability Status
 *
 * Syncs the custom 'painting_availability_status' field with WooCommerce stock status.
 * WooCommerce handles stock updates automatically - this just syncs the ACF field.
 *
 * FUNCTIONALITY:
 * - When WooCommerce marks item as out of stock → Updates availability to "sold"
 * - Reserved/Available → Sold (automatic)
 * - Updates buy button display and archive badges
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sync availability status when WooCommerce updates stock
 * Uses ACF functions for both reading and writing
 */
add_action( 'woocommerce_product_set_stock_status', 'monospace_sync_availability_on_stock_change', 10, 3 );

function monospace_sync_availability_on_stock_change( $product_id, $stock_status, $product ) {
    // Only update when item goes out of stock
    if ( $stock_status !== 'outofstock' ) {
        return;
    }

    // Get current availability status
    $current_status = get_post_meta( $product_id, 'painting_availability_status', true );

    // Normalize to lowercase for comparison
    $current_status = strtolower( trim( (string) $current_status ) );


    // Don't update if already sold or private collection
    // Gallery items DO update to sold when stock hits 0 (item was purchased)
    if ( in_array( $current_status, ['sold', 'private'], true ) ) {
        return;
    }

    // Update to sold for everything else (reserved, available, gallery, empty, etc.)
    update_post_meta( $product_id, 'painting_availability_status', 'sold' );

    monospace_log_availability_sold( $product_id, $current_status );
}

/**
 * Also sync when stock quantity changes to 0
 */
add_action( 'woocommerce_product_set_stock', 'monospace_sync_availability_on_stock_quantity', 10, 1 );

function monospace_sync_availability_on_stock_quantity( $product ) {
    if ( ! $product ) {
        return;
    }

    $product_id = $product->get_id();
    $stock_quantity = $product->get_stock_quantity();

    // Only update when stock reaches 0
    if ( $stock_quantity !== 0 ) {
        return;
    }

    // Get current availability status
    $current_status = get_post_meta( $product_id, 'painting_availability_status', true );

    // Normalize to lowercase for comparison
    $current_status = strtolower( trim( (string) $current_status ) );


    // Don't update if already sold or private collection
    // Gallery items DO update to sold when stock hits 0 (item was purchased)
    if ( in_array( $current_status, ['sold', 'private'], true ) ) {
        return;
    }

    // Update to sold for everything else (reserved, available, gallery, empty, etc.)
    update_post_meta( $product_id, 'painting_availability_status', 'sold' );

    monospace_log_availability_sold( $product_id, $current_status );
}

/**
 * One log line when an original really changes state (available, reserved, gallery -> sold). Products without a state
 * (prints, postcards) are not logged: an overnight postcard batch wrote 784 lines on 2026-11-22 (lean logs, owner).
 */
function monospace_log_availability_sold( $product_id, $was ) {
    if ( '' === $was ) {
        return;
    }
    error_log( sprintf( '[monospace] Product #%d availability: %s -> sold (out of stock)', $product_id, $was ) );
}

<?php
/**
 * Add Product column to WooCommerce Orders list view
 * Compatible with both legacy and HPOS (High-Performance Order Storage)
 *
 * @package astra-child-theme-for-monospace-art
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add Product column to orders list (HPOS compatible)
 */
add_filter('manage_woocommerce_page_wc-orders_columns', function($columns) {
    // Add new column after order status
    $new_columns = [];
    foreach($columns as $key => $value) {
        $new_columns[$key] = $value;
        if($key === 'order_status') {
            $new_columns['order_products'] = 'Product';
        }
    }
    return $new_columns;
}, 20);

/**
 * Legacy: Add Product column (for older WooCommerce)
 */
add_filter('manage_edit-shop_order_columns', function($columns) {
    $new_columns = [];
    foreach($columns as $key => $value) {
        $new_columns[$key] = $value;
        if($key === 'order_status') {
            $new_columns['order_products'] = 'Product';
        }
    }
    return $new_columns;
}, 20);

/**
 * Display product names in the Product column (HPOS compatible)
 */
add_action('manage_woocommerce_page_wc-orders_custom_column', function($column, $order) {
    if($column === 'order_products') {
        if(is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if(!$order) return;

        $items = $order->get_items();
        if(empty($items)) {
            echo '—';
            return;
        }

        $product_names = [];
        foreach($items as $item) {
            $product_names[] = $item->get_name();
        }

        // Show first product, or indicate multiple items
        if(count($product_names) === 1) {
            echo esc_html($product_names[0]);
        } else {
            echo '<span title="' . esc_attr(implode(', ', $product_names)) . '">' . esc_html($product_names[0]) . ' <em>(+' . (count($product_names) - 1) . ' more)</em></span>';
        }
    }
}, 10, 2);

/**
 * Legacy: Display product names (for older WooCommerce)
 */
add_action('manage_shop_order_posts_custom_column', function($column, $post_id) {
    if($column === 'order_products') {
        $order = wc_get_order($post_id);
        if(!$order) return;

        $items = $order->get_items();
        if(empty($items)) {
            echo '—';
            return;
        }

        $product_names = [];
        foreach($items as $item) {
            $product_names[] = $item->get_name();
        }

        if(count($product_names) === 1) {
            echo esc_html($product_names[0]);
        } else {
            echo '<span title="' . esc_attr(implode(', ', $product_names)) . '">' . esc_html($product_names[0]) . ' <em>(+' . (count($product_names) - 1) . ' more)</em></span>';
        }
    }
}, 10, 2);
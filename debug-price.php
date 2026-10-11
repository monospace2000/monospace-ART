<?php
/**
 * Debug price adjustment for a specific product.
 * Usage: https://art.monospace.com/debug-price.php?key=mnspc&id=PRODUCT_ID
 * Delete after use.
 */
if (($_GET['key'] ?? '') !== 'mnspc') die('Forbidden.');

define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_woocommerce')) die('Not authorised.');

$id      = intval($_GET['id'] ?? 0);
$product = wc_get_product($id);

if (!$product) die("Product $id not found.");

echo "<pre>";

// Raw DB price (bypasses filters)
$raw_price = get_post_meta($id, '_regular_price', true);
echo "Raw DB price:              $raw_price\n";

// Options
echo "msd_price_adj_enable:     " . get_option('msd_price_adj_enable') . "\n";
echo "msd_global_enable:        " . get_option('msd_global_enable') . "\n";
echo "msd_price_adj_percentage: " . get_option('msd_price_adj_percentage') . "\n";

// Per-product meta
$additional = get_post_meta($id, '_msd_additional_percentage', true);
echo "_msd_additional_pct:      " . var_export($additional, true) . "\n";

// Exclusion
$excluded = get_post_meta($id, '_msd_exclude_price_adj', true);
echo "_msd_exclude_price_adj:   " . var_export($excluded, true) . "\n";

// What the filter would produce
if (is_numeric($raw_price) && $raw_price > 0) {
    $price = (float) $raw_price;
    $global_pct = (float) get_option('msd_price_adj_percentage', 25);
    $price *= 1 + ($global_pct / 100);
    echo "\nAfter global ($global_pct%):  $price\n";

    if ($additional !== '' && is_numeric($additional)) {
        $price *= 1 + ((float)$additional / 100);
        echo "After additional ($additional%): $price\n";
    }
}

// What WooCommerce actually returns (goes through all filters)
echo "\nget_price() result:        " . $product->get_price() . "\n";
echo "get_regular_price() result: " . $product->get_regular_price() . "\n";

echo "</pre>";
echo "<p>Delete this file when done.</p>";

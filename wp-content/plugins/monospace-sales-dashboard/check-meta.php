<?php
/**
 * Quick check if additional percentage was saved
 * Access at: /wp-content/plugins/monospace-sales-dashboard/check-meta.php?id=PRODUCT_ID
 */

// Load WordPress
$wp_load = __DIR__ . '/../../../../wp-load.php';
if (!file_exists($wp_load)) {
    die('Cannot find wp-load.php at: ' . $wp_load);
}
require_once($wp_load);

if (!current_user_can('manage_woocommerce')) {
    die('Access denied');
}

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 6576; // Default to first product from your list

$additional_pct = get_post_meta($product_id, '_msd_additional_percentage', true);

echo "Product ID: $product_id<br>";
echo "Additional Percentage: " . ($additional_pct !== '' ? $additional_pct . '%' : 'NOT SET') . "<br>";
echo "Meta value type: " . gettype($additional_pct) . "<br>";
echo "Is empty: " . (empty($additional_pct) ? 'YES' : 'NO') . "<br>";

// Also check the product price to see if it's being applied
$product = wc_get_product($product_id);
if ($product) {
    $base_price = $product->get_regular_price();
    $current_price = $product->get_price();

    echo "<br>Base Regular Price: $base_price<br>";
    echo "Current Display Price: $current_price<br>";

    // Calculate what it SHOULD be with 20% global + 30% additional
    if ($base_price) {
        $expected_with_20 = $base_price * 1.20;
        $expected_with_20_and_30 = $base_price * 1.20 * 1.30;

        echo "<br>Expected with 20% only: " . number_format($expected_with_20, 2) . "<br>";
        echo "Expected with 20% + 30%: " . number_format($expected_with_20_and_30, 2) . "<br>";
    }
}

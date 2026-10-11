<?php
/**
 * Check _msd_additional_percentage for a product.
 * Usage: https://art.monospace.com/check-additional-pct.php?key=mnspc&id=PRODUCT_ID
 * Delete after use.
 */
if (($_GET['key'] ?? '') !== 'mnspc') die('Forbidden.');

define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_woocommerce')) die('Not authorised.');

$id  = intval($_GET['id'] ?? 0);
$val = get_post_meta($id, '_msd_additional_percentage', true);

echo "<pre>";
echo "Product ID: $id\n";
echo "Meta value: " . var_export($val, true) . "\n";
echo "Is numeric: " . (is_numeric($val) ? 'yes' : 'no') . "\n";
echo "</pre>";
echo "<p>Delete this file when done.</p>";

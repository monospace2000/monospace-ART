<?php
/**
 * Quick diagnostic tool to check product meta
 * Access at: /wp-content/plugins/monospace-sales-dashboard/test-meta.php?id=PRODUCT_ID
 */

// Load WordPress
require_once(__DIR__ . '/../../../../wp-load.php');

if (!current_user_can('manage_woocommerce')) {
    die('Access denied');
}

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$product_id) {
    die('Please provide a product ID: ?id=123');
}

$product = wc_get_product($product_id);
if (!$product) {
    die("Product $product_id not found");
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Product Meta Diagnostic</title>
    <style>
        body { font-family: monospace; padding: 20px; }
        table { border-collapse: collapse; width: 100%; max-width: 800px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f0f0f0; }
        .highlight { background: #ffffcc; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Product Meta Diagnostic</h1>
    <h2>Product: <?php echo esc_html($product->get_name()); ?> (ID: <?php echo $product_id; ?>)</h2>

    <h3>MSD-Related Meta Fields:</h3>
    <table>
        <tr>
            <th>Meta Key</th>
            <th>Value</th>
            <th>Type</th>
        </tr>
        <?php
        $meta_keys = [
            '_msd_additional_percentage',
            '_msd_exclude_price_adj',
            '_msd_enable_sale',
            '_msd_show_original',
            '_msd_exclude_volume',
            '_msd_custom_badge',
        ];

        foreach ($meta_keys as $key) {
            $value = get_post_meta($product_id, $key, true);
            $is_set = $value !== '';
            $row_class = ($key === '_msd_additional_percentage') ? 'highlight' : '';

            echo "<tr class='$row_class'>";
            echo "<td>$key</td>";
            echo "<td>" . ($is_set ? esc_html(print_r($value, true)) : '<em>not set</em>') . "</td>";
            echo "<td>" . gettype($value) . "</td>";
            echo "</tr>";
        }
        ?>
    </table>

    <h3>Product Price Info:</h3>
    <table>
        <tr>
            <th>Field</th>
            <th>Value</th>
        </tr>
        <tr>
            <td>Regular Price</td>
            <td><?php echo $product->get_regular_price(); ?></td>
        </tr>
        <tr>
            <td>Sale Price</td>
            <td><?php echo $product->get_sale_price() ?: '<em>none</em>'; ?></td>
        </tr>
        <tr>
            <td>Current Price (Display)</td>
            <td><?php echo $product->get_price(); ?></td>
        </tr>
    </table>

    <h3>Global MSD Settings:</h3>
    <table>
        <tr>
            <th>Option</th>
            <th>Value</th>
        </tr>
        <tr>
            <td>msd_global_enable</td>
            <td><?php echo get_option('msd_global_enable', 'NOT SET'); ?></td>
        </tr>
        <tr>
            <td>msd_price_adj_enable</td>
            <td><?php echo get_option('msd_price_adj_enable', 'NOT SET'); ?></td>
        </tr>
        <tr>
            <td>msd_price_adj_percentage</td>
            <td><?php echo get_option('msd_price_adj_percentage', 'NOT SET'); ?>%</td>
        </tr>
    </table>

    <p><a href="?id=<?php echo $product_id; ?>">Refresh</a></p>
</body>
</html>

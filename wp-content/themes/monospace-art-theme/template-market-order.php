<?php
/**
 * Template Name: Market Order Entry (Standalone)
 */

if (!defined('ABSPATH')) exit;

// Restrict access to admins only
if (!current_user_can('manage_woocommerce')) {
    wp_die('You do not have permission to access this page.');
}

// Handle form submission
$message = '';
if (isset($_POST['sku'])) {
    $sku = sanitize_text_field($_POST['sku']);
    $payment_method = sanitize_text_field($_POST['payment_method'] ?? 'Square');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');

    $product_id = wc_get_product_id_by_sku($sku);
    if (!$product_id) {
        $message = "Error: Product with SKU '$sku' not found.";
    } else {
        $product = wc_get_product($product_id);
        if ($product->get_stock_quantity() <= 0) {
            $message = "Error: Product '$sku' is out of stock.";
        } else {
            $order = wc_create_order();
            $order->add_product($product, 1);
            $order->set_payment_method($payment_method);
            $order->set_status('completed');
            if ($notes) $order->add_order_note($notes);
            $order->calculate_totals();
            $order->save();
            $message = "Success! Order created for SKU '$sku'.";
            $_POST = array(); // reset form
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Market Order Entry</title>
    <style>
        body { font-family: sans-serif; padding: 20px; max-width: 400px; margin: auto; }
        input, select, textarea, button { width: 100%; padding: 8px; margin-bottom: 12px; }
        button { background: #0071a1; color: #fff; border: none; cursor: pointer; }
        button:hover { background: #005f80; }
        .message { margin-bottom: 15px; font-weight: bold; }
    </style>
</head>
<body>

<h2>Market Order Entry</h2>

<?php if ($message) echo "<div class='message'>{$message}</div>"; ?>

<form method="post">
    <label>SKU</label>
    <input name="sku" placeholder="Enter SKU" required value="<?php echo esc_attr($_POST['sku'] ?? ''); ?>">

    <label>Payment Method</label>
    <select name="payment_method">
        <option value="Square" <?php selected($_POST['payment_method'] ?? '', 'Square'); ?>>Square</option>
        <option value="Cash" <?php selected($_POST['payment_method'] ?? '', 'Cash'); ?>>Cash</option>
        <option value="Venmo" <?php selected($_POST['payment_method'] ?? '', 'Venmo'); ?>>Venmo</option>
        <option value="PayPal" <?php selected($_POST['payment_method'] ?? '', 'PayPal'); ?>>PayPal</option>
    </select>

    <label>Notes / Event</label>
    <textarea name="notes" placeholder="Event name, receipt #, etc."><?php echo esc_textarea($_POST['notes'] ?? ''); ?></textarea>

    <button type="submit">Sold</button>
</form>

</body>
</html>

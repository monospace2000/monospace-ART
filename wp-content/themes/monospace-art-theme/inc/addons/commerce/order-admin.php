<?php
/**
 * Simple Market Order Entry Page for WooCommerce
 * Place this in your child theme folder or as a custom plugin page.
 */

if (!defined('ABSPATH')) exit;

if (isset($_POST['sku'])) {

    // Sanitize input
    $sku = sanitize_text_field($_POST['sku']);
    $payment_method = sanitize_text_field($_POST['payment_method'] ?? 'Square');
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');

    // Find product by SKU
    $product_id = wc_get_product_id_by_sku($sku);
    if (!$product_id) {
        $message = "Error: Product with SKU '$sku' not found.";
    } else {
        $product = wc_get_product($product_id);

        if ($product->get_stock_quantity() <= 0) {
            $message = "Error: Product '$sku' is out of stock.";
        } else {
            // Create WooCommerce order
            $order = wc_create_order();
            $order->add_product($product, 1); // quantity 1
            $order->set_payment_method($payment_method);
            $order->set_status('completed'); // mark as sold
            if ($notes) {
                $order->add_order_note($notes);
            }
            $order->calculate_totals();
            $order->save();

            $message = "Success! Order created for SKU '$sku'.";
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

<?php if (!empty($message)) echo "<div class='message'>{$message}</div>"; ?>

<form method="post">
    <label>SKU</label>
    <input name="sku" placeholder="Enter SKU" required>

    <label>Payment Method</label>
    <select name="payment_method">
        <option value="Square">Square</option>
        <option value="Cash">Cash</option>
        <option value="Venmo">Venmo</option>
        <option value="PayPal">PayPal</option>
    </select>

    <label>Notes / Event</label>
    <textarea name="notes" placeholder="Event name, receipt #, etc."></textarea>

    <button type="submit">Sold</button>
</form>

</body>
</html>

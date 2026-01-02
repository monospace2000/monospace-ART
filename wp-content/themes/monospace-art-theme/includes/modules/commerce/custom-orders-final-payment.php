<?php
/**
 * Final Payment System for Custom Orders - Multi-Item Support
 * Add this to your existing commission code
 */

/**
 * ================================
 * Admin: Create Final Payment Order Buttons (Multi-Item)
 * ================================
 */
add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    // Find all commission items that need final payment
    $commission_items = [];

    foreach($order->get_items() as $item_id => $item){
        $unique_key = $item->get_meta('_unique_commission_key', true)
                   ?: $item->get_meta('unique_commission_key', true)
                   ?: $item->get_meta('Unique Commission Key', true);

        if($unique_key){
            $final_payment_id = $item->get_meta('_final_payment_order_id', true);
            $final_created = $item->get_meta('_final_payment_created', true);

            $needs_final_payment = true;
            if ($final_payment_id) {
                $final_order = wc_get_order($final_payment_id);
                if ($final_order && !$final_order->has_status('cancelled')) {
                    $needs_final_payment = false;
                } else {
                    $item->delete_meta_data('_final_payment_created');
                    $item->delete_meta_data('_final_payment_order_id');
                    $item->save();
                }
            }

            if($needs_final_payment){
                $commission_items[] = [
                    'item_id' => $item_id,
                    'item' => $item,
                    'commission_key' => $unique_key,
                    'product_name' => $item->get_name()
                ];
            }
        }
    }

    if(empty($commission_items)) return;

    // Build item IDs array for batch processing
    $item_ids = array_column($commission_items, 'item_id');
    $item_ids_json = json_encode($item_ids);

    ?>
    <br class="clear" />
    <div style="margin: 20px 0; padding: 20px; border: 1px solid #c3c4c7;">
        <h3 style="margin: 0 0 15px 0; font-size: 14px; line-height: 1.4;">
            <span style="display: inline-block; margin-right: 8px;">💰</span> Commission Final Payment
        </h3>
        <p style="margin: 0 0 15px 0; font-size: 13px; line-height: 1.5;">
            When the artwork is complete, create a final payment invoice for the customer.
        </p>

        <div style="margin-bottom: 15px; padding: 12px; background: #f9f9f9;">
            <div style="margin-bottom: 12px; font-weight: 600;">Items ready for final payment:</div>
            <?php foreach($commission_items as $comm): ?>
            <div style="margin-bottom: 6px; padding-left: 12px;">
                • <?php echo esc_html($comm['product_name']); ?>
            </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="button button-primary create-combined-final-payment-btn"
                data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                data-item-ids='<?php echo esc_attr($item_ids_json); ?>'>
            Create Combined Final Payment Invoice (<?php echo count($commission_items); ?> item<?php echo count($commission_items) > 1 ? 's' : ''; ?>)
        </button>

        <p style="margin: 12px 0 0 0; font-size: 12px; line-height: 1.5;">
            This will create ONE order combining all remaining balances + shipping and send an invoice email to the customer.
        </p>
    </div>

    <script>
    jQuery(function($){
        $('.create-combined-final-payment-btn').on('click', function(e){
            e.preventDefault();
            var $btn = $(this);
            var orderId = $btn.data('order-id');
            var itemIds = $btn.data('item-ids');

            $btn.prop('disabled', true).html('⏳ Calculating...');

            // Step 1: Get calculation preview
            $.post(ajaxurl, {
                action: 'preview_combined_final_payment',
                order_id: orderId,
                item_ids: JSON.stringify(itemIds),
                nonce: '<?php echo wp_create_nonce('create_final_payment'); ?>'
            }, function(response){
                if(response.success){
                    var data = response.data;

                    // Show confirmation dialog with ALL items
                    var message = '⚠️ CONFIRM COMBINED FINAL PAYMENT\n\n';
                    message += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n';

                    // Show each item breakdown
                    data.items.forEach(function(item){
                        message += item.product_name + '\n';
                        message += '  Full Price: $' + item.full_price.toFixed(2) + '\n';
                        message += '  Deposit Paid: $' + item.deposit_paid.toFixed(2) + '\n';
                        message += '  Balance Due: $' + item.remaining_balance.toFixed(2) + '\n';
                        message += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n';
                    });

                    message += 'TOTAL REMAINING BALANCE: $' + data.total_balance.toFixed(2) + '\n';
                    message += 'Combined Shipping: $' + data.shipping_total.toFixed(2) + '\n';
                    message += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n';
                    message += 'TOTAL DUE: $' + data.final_total.toFixed(2) + '\n';
                    message += '━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n';
                    message += 'This will create ONE combined invoice and email it to the customer.';

                    if(confirm(message)){
                        $btn.html('⏳ Creating order...');

                        $.post(ajaxurl, {
                            action: 'create_combined_final_payment_order',
                            order_id: orderId,
                            item_ids: JSON.stringify(itemIds),
                            confirmed: 'yes',
                            nonce: '<?php echo wp_create_nonce('create_final_payment'); ?>'
                        }, function(createResponse){
                            if(createResponse.success){
                                alert('✅ Combined final payment order created!\n\nOrder #' + createResponse.data.order_id + '\n\nInvoice sent to customer.');
                                window.location.href = '<?php echo admin_url("post.php?post="); ?>' + createResponse.data.order_id + '&action=edit';
                            } else {
                                alert('❌ Error: ' + createResponse.data.message);
                                $btn.prop('disabled', false).html('Create Combined Final Payment Invoice');
                            }
                        }).fail(function(xhr, status, error){
                            console.error('Create error:', xhr.responseText);
                            alert('❌ Connection error: ' + error);
                            $btn.prop('disabled', false).html('Create Combined Final Payment Invoice');
                        });
                    } else {
                        $btn.prop('disabled', false).html('Create Combined Final Payment Invoice');
                    }
                } else {
                    alert('❌ Error: ' + response.data.message);
                    $btn.prop('disabled', false).html('Create Combined Final Payment Invoice');
                }
            }).fail(function(xhr, status, error){
                console.error('Preview error:', xhr.responseText);
                alert('❌ Connection error: ' + error + '\n\nCheck browser console for details.');
                $btn.prop('disabled', false).html('Create Combined Final Payment Invoice');
            });
        });
    });
    </script>
    <?php

}, 10);


/**
 * ================================
 * AJAX: Preview Final Payment
 * ================================
 */
add_action('wp_ajax_preview_combined_final_payment', function(){
    check_ajax_referer('create_final_payment', 'nonce');

    if(!current_user_can('edit_shop_orders')){
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $original_order_id = intval($_POST['order_id']);
    $item_ids = json_decode(stripslashes($_POST['item_ids']), true);

    if(empty($item_ids)){
        wp_send_json_error(['message' => 'No items specified']);
    }

    $original_order = wc_get_order($original_order_id);
    if(!$original_order){
        wp_send_json_error(['message' => 'Original order not found']);
    }

    $items_data = [];
    $total_balance = 0;

    foreach($item_ids as $item_id){
        $original_item = $original_order->get_item($item_id);
        if(!$original_item) continue;

        $full_price = floatval(
            $original_item->get_meta('Commission Full Price', true)
            ?: $original_item->get_meta('commission_full_price', true)
            ?: $original_item->get_meta('_commission_full_price', true)
        );

        $deposit_price = floatval(
            $original_item->get_meta('Applied Deposit Price', true)
            ?: $original_item->get_meta('applied_deposit_price', true)
            ?: $original_item->get_meta('_applied_deposit_price', true)
        );

        if($full_price <= 0 || $deposit_price <= 0) continue;

        $remaining_balance = $full_price - $deposit_price;
        $total_balance += $remaining_balance;

        $items_data[] = [
            'item_id' => $item_id,
            'product_name' => $original_item->get_name(),
            'full_price' => $full_price,
            'deposit_paid' => $deposit_price,
            'remaining_balance' => $remaining_balance,
            'product' => wc_get_product($original_item->get_product_id())
        ];
    }

    if(empty($items_data)){
        wp_send_json_error(['message' => 'No valid commission items found']);
    }

    // Calculate combined shipping
    $shipping_total = 0;
    try {
        $shipping_address = $original_order->get_address('shipping');

        if (!empty($shipping_address['country'])) {
            $package_contents = [];

            foreach($items_data as $idx => $item_data){
                $package_contents['item_'.$idx] = [
                    'data' => $item_data['product'],
                    'quantity' => 1,
                    'line_subtotal' => $item_data['remaining_balance'],
                    'line_total' => $item_data['remaining_balance'],
                ];
            }

            $package = [
                'contents' => $package_contents,
                'contents_cost' => $total_balance,
                'applied_coupons' => [],
                'user' => ['ID' => $original_order->get_customer_id()],
                'destination' => [
                    'country' => $shipping_address['country'],
                    'state' => $shipping_address['state'],
                    'postcode' => $shipping_address['postcode'],
                    'city' => $shipping_address['city'],
                    'address' => $shipping_address['address_1'],
                    'address_2' => $shipping_address['address_2'],
                ],
            ];

            WC()->shipping()->calculate_shipping([$package]);
            $packages = WC()->shipping()->get_packages();

            if (!empty($packages)) {
                $pkg = reset($packages);
                if (!empty($pkg['rates'])) {
                    $rate = reset($pkg['rates']);
                    $shipping_total = floatval($rate->cost);
                }
            }
        }
    } catch (Exception $e) {
        error_log('Combined shipping calculation error: ' . $e->getMessage());
    }

    $final_total = $total_balance + $shipping_total;

    wp_send_json_success([
        'items' => $items_data,
        'total_balance' => $total_balance,
        'shipping_total' => $shipping_total,
        'final_total' => $final_total
    ]);
});


/**
 * ================================
 * AJAX: Create Final Payment Order
 * ================================
 */
add_action('wp_ajax_create_combined_final_payment_order', function(){
    check_ajax_referer('create_final_payment', 'nonce');

    if(!current_user_can('edit_shop_orders')){
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    if(empty($_POST['confirmed']) || $_POST['confirmed'] !== 'yes'){
        wp_send_json_error(['message' => 'Confirmation required']);
    }

    $original_order_id = intval($_POST['order_id']);
    $item_ids = json_decode(stripslashes($_POST['item_ids']), true);

    $original_order = wc_get_order($original_order_id);
    if(!$original_order){
        wp_send_json_error(['message' => 'Original order not found']);
    }

    // Process all items
    $items_to_add = [];
    $all_products = [];
    $total_balance = 0;

    foreach($item_ids as $item_id){
        $original_item = $original_order->get_item($item_id);
        if(!$original_item) continue;

        // Check if already has final payment
        $final_id = $original_item->get_meta('_final_payment_order_id', true);
        if($final_id){
            $final_order = wc_get_order($final_id);
            if($final_order && !$final_order->has_status('cancelled')){
                continue; // Skip this item
            } else {
                $original_item->delete_meta_data('_final_payment_created');
                $original_item->delete_meta_data('_final_payment_order_id');
                $original_item->save();
            }
        }

        $full_price = floatval(
            $original_item->get_meta('Commission Full Price', true)
            ?: $original_item->get_meta('commission_full_price', true)
            ?: $original_item->get_meta('_commission_full_price', true)
        );

        $deposit_price = floatval(
            $original_item->get_meta('Applied Deposit Price', true)
            ?: $original_item->get_meta('applied_deposit_price', true)
            ?: $original_item->get_meta('_applied_deposit_price', true)
        );

        if($full_price <= 0 || $deposit_price <= 0) continue;

        $remaining_balance = $full_price - $deposit_price;
        $total_balance += $remaining_balance;

        $product = wc_get_product($original_item->get_product_id());
        $all_products[] = $product;

        $items_to_add[] = [
            'item_id' => $item_id,
            'original_item' => $original_item,
            'product' => $product,
            'remaining_balance' => $remaining_balance,
            'product_name' => $product->get_name()
        ];
    }

    if(empty($items_to_add)){
        wp_send_json_error(['message' => 'No valid items to process']);
    }

    // Create single final payment order
    $final_order = wc_create_order([
        'customer_id' => $original_order->get_customer_id(),
        'status' => 'pending'
    ]);

    // Add all line items
    foreach($items_to_add as $item_data){
        $item = new WC_Order_Item_Product();
        $item->set_props([
            'product' => $item_data['product'],
            'quantity' => 1,
            'subtotal' => $item_data['remaining_balance'],
            'total' => $item_data['remaining_balance'],
            'name' => 'Remaining Balance – ' . $item_data['product_name']
        ]);

        $item->add_meta_data('_is_final_payment', 'yes', true);
        $item->add_meta_data('_original_order_id', $original_order_id, true);
        $item->add_meta_data('_original_item_id', $item_data['item_id'], true);

        $final_order->add_item($item);

        // Mark original item
        $item_data['original_item']->add_meta_data('_final_payment_created', 'yes', true);
        $item_data['original_item']->add_meta_data('_final_payment_order_id', $final_order->get_id(), true);
        $item_data['original_item']->save_meta_data();
    }

    // Copy addresses
    $final_order->set_address($original_order->get_address('billing'), 'billing');
    $final_order->set_address($original_order->get_address('shipping'), 'shipping');

    // Calculate combined shipping
    $shipping_address = $final_order->get_address('shipping');
    $package_contents = [];

    foreach($items_to_add as $idx => $item_data){
        $package_contents['item_'.$idx] = [
            'data' => $item_data['product'],
            'quantity' => 1,
            'line_subtotal' => $item_data['remaining_balance'],
            'line_total' => $item_data['remaining_balance'],
        ];
    }

    $package = [
        'contents' => $package_contents,
        'contents_cost' => $total_balance,
        'applied_coupons' => [],
        'destination' => [
            'country' => $shipping_address['country'],
            'state' => $shipping_address['state'],
            'postcode' => $shipping_address['postcode'],
            'city' => $shipping_address['city'],
            'address' => $shipping_address['address_1'],
            'address_2' => $shipping_address['address_2'],
        ],
    ];

    WC()->shipping()->calculate_shipping([$package]);
    $packages = WC()->shipping()->get_packages();

    if (!empty($packages)) {
        $pkg = reset($packages);
        if (!empty($pkg['rates'])) {
            $rate = reset($pkg['rates']);
            $shipping_item = new WC_Order_Item_Shipping();
            $shipping_item->set_method_title($rate->label);
            $shipping_item->set_method_id($rate->method_id);
            $shipping_item->set_total($rate->cost);
            $final_order->add_item($shipping_item);
        }
    }

    $final_order->calculate_totals();
    $final_order->add_order_note('Combined final payment order for ' . count($items_to_add) . ' commission items from Order #' . $original_order_id);

    // ADD THIS:
    $final_order->add_order_note('⚠️ Invoice email NOT yet sent - review order then click "Send Invoice" manually');

    // COMMENT OUT OR REMOVE THIS LINE:
    // WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger($final_order->get_id(), $final_order);

    wp_send_json_success([
        'order_id' => $final_order->get_id(),
        'message' => 'Combined final payment order created (email NOT sent - send manually)'
    ]);
});

/**
 * ================================
 * Display final payment info in order details (admin)
 * ================================
 */
add_action('woocommerce_admin_order_data_after_order_details', function($order){
    // Check if this order contains any final payment items
    foreach($order->get_items() as $item){
        if($item->get_meta('_is_final_payment', true)){
            $original_order_id = $item->get_meta('_original_order_id', true);
            echo '<div style="margin-top:15px; padding:12px; border-left:4px solid white;">';
            echo '<strong>⚠️ This is a Final Payment Order</strong><br/>';
            echo 'Original deposit order: <a href="'.admin_url('post.php?post='.$original_order_id.'&action=edit').'">Order #'.$original_order_id.'</a><br/>';
            echo 'Your order will ship as soon as payment is received.';
            echo '</div>';
            break;
        }
    }

    // Check if any items in this order have final payment orders created
    $final_payment_items = [];
    foreach($order->get_items() as $item){
        $final_payment_order_id = $item->get_meta('_final_payment_order_id', true)
                               ?: $item->get_meta('final_payment_order_id', true);

        if($final_payment_order_id){
            $final_order = wc_get_order($final_payment_order_id);
            if($final_order){
                $final_payment_items[] = [
                    'item_name' => $item->get_name(),
                    'order_id' => $final_payment_order_id,
                    'status' => $final_order->get_status()
                ];
            }
        }
    }

    if(!empty($final_payment_items)){
        echo '<div style="margin-top:15px; padding:12px; border-left:4px solid white;">';
        echo '<strong>ℹ️ Final Payment Orders Created</strong><br/>';
        foreach($final_payment_items as $fp_item){
            echo '<div style="margin-top: 8px;">';
            echo '<strong>' . esc_html($fp_item['item_name']) . ':</strong> ';
            echo '<a href="'.admin_url('post.php?post='.$fp_item['order_id'].'&action=edit').'">Order #'.$fp_item['order_id'].'</a>';
            echo ' - Status: '.$fp_item['status'];
            echo '</div>';
        }
        echo '</div>';
    }
}, 15);

/**
 * ================================
 * Display final payment info in customer order view
 * ================================
 */
add_filter('woocommerce_order_item_name', function($item_name, $item){
    if($item->get_meta('_is_final_payment', true)){
        $original_order_id = $item->get_meta('_original_order_id', true);
        $item_name .= '<br/><small>Completing commission from Order #'.$original_order_id.'</small>';
    }

    return $item_name;
}, 10, 2);
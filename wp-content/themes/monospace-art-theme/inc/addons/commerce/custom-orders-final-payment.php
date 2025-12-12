<?php
/**
 * Final Payment System for Custom Orders
 * Add this to your existing commission code
 */

/**
 * ================================
 * Admin: Create Final Payment Order Button
 * ================================
 */
add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    // Check if this order has commission items and no final payment yet
    $has_commission = false;
    $commission_item_id = null;
    $commission_key = null;

    foreach($order->get_items() as $item_id => $item){
        $unique_key = $item->get_meta('_unique_commission_key', true)
                   ?: $item->get_meta('unique_commission_key', true)
                   ?: $item->get_meta('Unique Commission Key', true);

        if($unique_key){
            $has_commission = true;
            $commission_item_id = $item_id;
            $commission_key = $unique_key;

            $final_payment_id = $item->get_meta('_final_payment_order_id', true);
            $final_created     = $item->get_meta('_final_payment_created', true);

            // Only hide the button if a final-payment order exists AND is not cancelled
            if ($final_payment_id) {
                $final_order = wc_get_order($final_payment_id);

                if ($final_order && !$final_order->has_status('cancelled')) {
                    return; // Final payment exists and is valid: hide button
                }

                // Cancelled: allow recreation by clearing flags
                $item->delete_meta_data('_final_payment_created');
                $item->delete_meta_data('_final_payment_order_id');
                $item->save();
            }
            break;
        }
    }

    if(!$has_commission) return;

    ?>
    <br class="clear" />
    <div style="margin: 20px 0; padding: 20px; border: 1px solid #c3c4c7;">
        <h3 style="margin: 0 0 15px 0; font-size: 14px; line-height: 1.4;">
            <span style="display: inline-block; margin-right: 8px;">💰</span> Commission Final Payment
        </h3>
        <p style="margin: 0 0 15px 0; font-size: 13px; line-height: 1.5;">
            When the artwork is complete, create a final payment invoice for the customer.
        </p>
        <button type="button" class="button button-primary create-final-payment-btn"
                data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                data-item-id="<?php echo esc_attr($commission_item_id); ?>"
                data-commission-key="<?php echo esc_attr($commission_key); ?>">
            Create Final Payment Invoice
        </button>
        <p style="margin: 12px 0 0 0; font-size: 12px; line-height: 1.5;">
            This will create a new order for the remaining balance + shipping and send an invoice email to the customer.
        </p>
    </div>

    <script>
    jQuery(function($){
        $('.create-final-payment-btn').on('click', function(e){
            e.preventDefault();
            var $btn = $(this);
            var orderId = $btn.data('order-id');
            var itemId = $btn.data('item-id');
            var commissionKey = $btn.data('commission-key');

            if(!confirm('Create final payment order for this commission?\n\nThis will:\n• Calculate remaining balance\n• Add shipping costs\n• Create new order\n• Email invoice to customer')) return;

            $btn.prop('disabled', true).html('⏳ Creating order...');

            $.post(ajaxurl, {
                action: 'create_final_payment_order',
                order_id: orderId,
                item_id: itemId,
                commission_key: commissionKey,
                nonce: '<?php echo wp_create_nonce('create_final_payment'); ?>'
            }, function(response){
                if(response.success){
                    alert('✅ Final payment order created successfully!\n\nOrder #' + response.data.order_id + '\n\nAn invoice email has been sent to the customer.');
                    // Redirect to the newly created order page
                    window.location.href = '<?php echo admin_url("post.php?post="); ?>' + response.data.order_id + '&action=edit';
                } else {
                    alert('❌ Error: ' + response.data.message);
                    $btn.prop('disabled', false).html('🎨 Create Final Payment Invoice');
                }
            }).fail(function(){
                alert('❌ Connection error. Please try again.');
                $btn.prop('disabled', false).html('🎨 Create Final Payment Invoice');
            });
        });
    });
    </script>
    <?php
}, 10);

/**
 * ================================
 * AJAX: Create Final Payment Order
 * ================================
 */
add_action('wp_ajax_create_final_payment_order', function(){
    check_ajax_referer('create_final_payment', 'nonce');

    if(!current_user_can('edit_shop_orders')){
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $original_order_id = intval($_POST['order_id']);
    $item_id = intval($_POST['item_id']);
    $commission_key = sanitize_text_field($_POST['commission_key']);

    $original_order = wc_get_order($original_order_id);
    if(!$original_order){
        wp_send_json_error(['message' => 'Original order not found']);
    }

    $original_item = $original_order->get_item($item_id);
    if(!$original_item){
        wp_send_json_error(['message' => 'Order item not found']);
    }

    /**
     * Allow recreation if prior final-payment order was cancelled
     */
    $final_id = $original_item->get_meta('_final_payment_order_id', true);
    $final_created = $original_item->get_meta('_final_payment_created', true);

    if ($final_created === 'yes' && $final_id) {
        $final_order = wc_get_order($final_id);

        if ($final_order && $final_order->has_status('cancelled')) {
            // Auto-reset metadata so a new FP order can be generated
            $original_item->delete_meta_data('_final_payment_created');
            $original_item->delete_meta_data('_final_payment_order_id');
            $original_item->save();

        } else {
            wp_send_json_error([
                'message' => 'A final payment order already exists (Order #' . $final_id . '). Cancel that order first if you need to regenerate.'
            ]);
        }
    }



    // Get full price
    $full_price = floatval(
        $original_item->get_meta('commission_full_price', true)
        ?: $original_item->get_meta('_commission_full_price', true)
        ?: $original_item->get_total()  // fallback to line total
    );

    // Get applied deposit
    $deposit_price = floatval(
        $original_item->get_meta('applied_deposit_price', true)
        ?: $original_item->get_meta('_applied_deposit_price', true)
    );

    // Fallback: assume 30% if still missing
    if($deposit_price <= 0){
        $deposit_price = round(0.3 * $full_price, wc_get_price_decimals());
    }

    // Calculate remaining balance
    $remaining_balance = max(0, $full_price - $deposit_price);

    if($remaining_balance <= 0){
        wp_send_json_error([
            'message' => 'Error: Remaining balance is zero or negative. Please verify deposit amount.'
        ]);
    }


    // Create new order for final payment
    $final_order = wc_create_order([
        'customer_id' => $original_order->get_customer_id(),
        'status' => 'pending'
    ]);

    // Add line item for remaining balance
    $product_id = $original_item->get_product_id();
    $product = wc_get_product($product_id);

    $item = new WC_Order_Item_Product();
    $item->set_props([
        'product' => $product,
        'quantity' => 1,
        'subtotal' => $remaining_balance,
        'total' => $remaining_balance,
        'name' => 'Final Payment: ' . $product->get_name()
    ]);

    // Copy commission metadata
    $item->add_meta_data('_is_final_payment', 'yes', true);
    $item->add_meta_data('_original_order_id', $original_order_id, true);
    $item->add_meta_data('_original_item_id', $item_id, true);
    $item->add_meta_data('_commission_key', $commission_key, true);

    $display_keys = ['_commission_medium', '_commission_size', '_commission_surface', '_commission_special_request', '_commission_reference_upload'];
    foreach($display_keys as $key){
        $value = $original_item->get_meta($key, true);
        if(!empty($value)){
            $item->add_meta_data($key, $value, true);
        }
    }

    $final_order->add_item($item);

    // Copy billing/shipping address
    $final_order->set_address($original_order->get_address('billing'), 'billing');
    $final_order->set_address($original_order->get_address('shipping'), 'shipping');

    // Calculate shipping
    $final_order->calculate_shipping();

    // Calculate totals
    $final_order->calculate_totals();

    // Add note
    $final_order->add_order_note('Final payment order created for commission from Order #' . $original_order_id);

    // Mark original item as having final payment created
    $original_item->add_meta_data('_final_payment_created', 'yes', true);
    $original_item->add_meta_data('_final_payment_order_id', $final_order->get_id(), true);
    $original_item->save_meta_data();

    // Send invoice email
    WC()->mailer()->get_emails()['WC_Email_Customer_Invoice']->trigger($final_order->get_id(), $final_order);

    wp_send_json_success([
        'order_id' => $final_order->get_id(),
        'message' => 'Final payment order created successfully'
    ]);
});

/**
 * ================================
 * Display final payment info in order details (admin)
 * ================================
 */
add_action('woocommerce_admin_order_data_after_order_details', function($order){
    foreach($order->get_items() as $item){
        if($item->get_meta('_is_final_payment', true)){
            $original_order_id = $item->get_meta('_original_order_id', true);
            echo '<div style="margin-top:15px; padding:12px; border-left:4px solid white;">';
            echo '<strong>⚠️ This is a Final Payment Order</strong><br/>';
            echo 'Original deposit order: <a href="'.admin_url('post.php?post='.$original_order_id.'&action=edit').'">Order #'.$original_order_id.'</a>';
            echo '</div>';
            break;
        }
    }

    foreach($order->get_items() as $item){
        $final_payment_order_id = $item->get_meta('_final_payment_order_id', true)
                               ?: $item->get_meta('final_payment_order_id', true);

        if($final_payment_order_id){
            $final_order = wc_get_order($final_payment_order_id);
            echo '<div style="margin-top:15px; padding:12px; border-left:4px solid white;">';
            echo '<strong>ℹ️ Final Payment Order Created</strong><br/>';
            echo 'Final payment order: <a href="'.admin_url('post.php?post='.$final_payment_order_id.'&action=edit').'">Order #'.$final_payment_order_id.'</a>';
            echo ' - Status: '.$final_order->get_status();
            echo '</div>';
            break;
        }
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
       // $item_name = '<strong style="color:#d4a017;">🎨 Final Payment</strong><br/>' . $item_name;
        $item_name .= '<br/><small>Completing commission from Order #'.$original_order_id.'</small>';
    }

    $commission_key = $item->get_meta('_commission_key', true)
                   ?: $item->get_meta('_unique_commission_key', true)
                   ?: $item->get_meta('commission_key', true)
                   ?: $item->get_meta('unique_commission_key', true);

    if ($commission_key){
        $display_map = [
            '_commission_medium' => 'Medium',
            'commission_medium' => 'Medium',
            '_commission_size' => 'Size',
            'commission_size' => 'Size',
            '_commission_surface' => 'Surface',
            'commission_surface' => 'Surface',
            '_commission_special_request' => 'Special Requests',
            'commission_special_request' => 'Special Requests',
            '_commission_reference_upload' => 'Reference Image',
            'commission_reference_upload' => 'Reference Image'
        ];

        $meta_lines = [];
        $already_shown = [];

        foreach($display_map as $meta_key => $label){
            if(in_array($label, $already_shown)) continue;

            $value = $item->get_meta($meta_key, true);
            if(!empty($value)){
                if(strpos($meta_key, 'reference_upload') !== false){
                    $value = '<a href="'.esc_url($value).'" target="_blank">View Image</a>';
                } else {
                    $value = esc_html($value);
                }
                $meta_lines[] = '<strong>'.$label.':</strong> ' . $value;
                $already_shown[] = $label;
            }
        }

        if ($meta_lines){
            $item_name .= '<br/><small class="commission-details" style="display:block; margin-top:8px; line-height:1.6;">'.implode('<br/>',$meta_lines).'</small>';
        }
    }

    return $item_name;
}, 10, 2);

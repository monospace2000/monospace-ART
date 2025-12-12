<?php
/**
 * Robust Commissions Tab for WooCommerce My Account
 * Admin Upload & Display for Commission Previews (AJAX Only)
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* =====================================================
   FRONTEND: Customer-Facing Commissions Tab
   ===================================================== */

/* 1) Register endpoint */
add_action('init', function() {
    add_rewrite_endpoint('commissions', EP_ROOT | EP_PAGES);
}, 5);

/* 2) Add endpoint to query vars */
add_filter('woocommerce_get_query_vars', function($vars) {
    $vars['commissions'] = 'commissions';
    return $vars;
});

/* 3) Insert tab after Orders */
add_filter('woocommerce_account_menu_items', function($items) {
    $new = array();
    foreach ($items as $key => $label) {
        $new[$key] = $label;
        if ($key === 'orders') {
            $new['commissions'] = __('Commissions', 'monospace-art-theme');
        }
    }
    return $new;
}, 20);

/* 3b) Add badge count via JavaScript */
add_action('wp_footer', function() {
    if (!is_account_page()) return;

    $new_preview_count = ms_count_new_previews();
    if ($new_preview_count > 0) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var commissionLink = document.querySelector('.woocommerce-MyAccount-navigation-link--commissions a');
            if (commissionLink) {
                var badge = document.createElement('span');
                badge.className = 'ms-badge';
                badge.textContent = '<?php echo $new_preview_count; ?>';
                commissionLink.appendChild(badge);
            }
        });
        </script>
        <style>
        .ms-badge {
    background: #46b450;
    color: #fff;
    border-radius: 50%;
    font-size: 0.75em;
    margin-left: 5px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 22px;
    width: 22px;
    height: 22px;
    font-weight: 600;
    line-height: 1;
}
        </style>
        <?php
    }
});


/**
 * Update the commissions endpoint display section
 * Replace the existing woocommerce_account_commissions_endpoint action
 */

/* 4) Render commissions tab */
add_action( 'woocommerce_account_commissions_endpoint', function() {

    if ( ! is_user_logged_in() ) {
        echo '<p>Please log in to view your commissions.</p>';
        return;
    }

    $customer_id = get_current_user_id();

    // Clear "new preview" flags when customer views this page
    ms_clear_new_preview_flags($customer_id);

    $orders = wc_get_orders([
        'customer_id' => $customer_id,
        'limit'       => -1,
        'status'      => ['pending','on-hold','processing','completed'],
    ]);

    // Check if there are any commission items
    $has_commissions = false;
    if (!empty($orders)) {
        foreach ($orders as $order) {
            foreach ($order->get_items('line_item') as $item) {
                $unique_key = $item->get_meta('_unique_commission_key', true);
                if ($unique_key) {
                    $has_commissions = true;
                    break 2; // Break out of both loops
                }
            }
        }
    }

    // Display "no commissions" message styled like WooCommerce downloads
    if (!$has_commissions) {
        echo '<div class="woocommerce-notices-wrapper woocommerce-info">';
        echo esc_html__('No commissions available yet.', 'monospace-art-theme');
        echo '</div>';
        return;
    }

    echo '<div class="ms-commissions-list">';

    foreach ( $orders as $order ) {

        $commission_items = [];

        foreach ( $order->get_items('line_item') as $item ) {
            $unique_key = $item->get_meta('_unique_commission_key', true);
            if ( ! $unique_key ) continue;

            $full_price = (float) $item->get_meta('Commission Full Price', true );
            if ( ! $full_price || $full_price <= 0 ) {
                $prod = wc_get_product( $item->get_product_id() );
                $full_price = $prod ? (float) $prod->get_price() : 0;
            }

            $deposit_percent = (int) get_post_meta( $item->get_product_id(), '_commission_deposit_percent', true );
            if ( $deposit_percent <= 0 ) $deposit_percent = 30;

            $deposit_amount = round( ( $deposit_percent / 100 ) * $full_price, wc_get_price_decimals() );
            $remaining_amount = round( $full_price - $deposit_amount, wc_get_price_decimals() );

            // Get and normalize previews
            $previews = $item->get_meta('Commission Preview Images', true );
            $previews = ms_normalize_preview_images($previews);

            $commission_items[] = [
                'item' => $item,
                'full_price' => $full_price,
                'deposit' => $deposit_amount,
                'remaining' => $remaining_amount,
                'deposit_percent' => $deposit_percent,
                'previews' => $previews,
            ];
        }

        if ( empty($commission_items) ) continue;

        // Order block
        echo '<div class="commission-block">';
        echo '<h2>Order #' . esc_html($order->get_id()) . ' (' . esc_html(wc_get_order_status_name($order->get_status())) . ')</h2>';

        foreach ( $commission_items as $c ) {
            $item = $c['item'];

            echo '<div class="commission-item">';
            echo '<div class="commission-grid">';

            // Left column: label, Right column: value
            $fields = [
              'Item' => $item->get_name(),
                'Full Price' => '<span class="commission-price" style="font-size:1em;font-weight:normal;">' . wc_price($c['full_price']) . '</span>',
                'Deposit (' . intval($c['deposit_percent']) . '%)' => '<span class="commission-price" style="font-size:1em;font-weight:normal;">' . wc_price($c['deposit']) . '</span>',
                'Remaining' => '<span class="commission-price" style="font-size:1em;font-weight:normal;">' . wc_price($c['remaining']) . '</span>',
                'Special Requests' => $item->get_meta('_commission_special_request', true) ?: '—',
            ];

            foreach ($fields as $label => $value) {
                echo '<div class="commission-label">' . esc_html($label) . '</div>';
                echo '<div class="commission-value">' . wp_kses_post($value) . '</div>';
            }

            // Previews
            echo '<div class="commission-label">Previews</div>';
            echo '<div class="commission-value">';
            if ( ! empty($c['previews']) ) {
                echo '<div class="commission-preview-gallery">';
                foreach ( $c['previews'] as $url ) {
                    $url = esc_url($url);
                    echo '<a href="'.$url.'" target="_blank" class="commission-preview-link">';
                    echo '<img src="'.$url.'" alt="Commission Preview" class="commission-preview-img">';
                    echo '</a>';
                }
                echo '</div>';
            } else {
                echo '—';
            }
            echo '</div>';

            echo '</div>'; // .commission-grid
            echo '</div>'; // .commission-item
        }

        echo '</div>'; // .commission-block
    }

    echo '</div>'; // .ms-commissions-list

    // Responsive CSS
    echo '<style>
        .commission-block {
            border:1px solid #e1e1e1;
            padding:1em;
            margin-bottom:1.5em;
            background:#fff;
        }
        .commission-block h2 {
            margin-top:0;
            font-size:1.25em;
        }
        .commission-item {
            border-top:1px solid #ccc;
            padding-top:0.75em;
            margin-top:0.75em;
        }
        .commission-price {
            font-size: 1em;
            font-weight: normal !important;
        }

        .commission-grid {
            display:grid;
            grid-template-columns: 150px 1fr;
            gap:0.5em 1em;
            align-items:start;
        }
        .commission-label {
            font-weight:bold;
            color:#555;
        }
        .commission-value {
            color:#333;
        }
        .commission-preview-gallery {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .commission-preview-link {
            display: inline-block;
        }
        .commission-preview-img {
            width:70px;
            height:70px;
            object-fit:cover;
            border:1px solid #ddd;
            padding:2px;
            background:#fff;
            transition: transform 0.2s;
        }
        .commission-preview-img:hover {
            transform: scale(1.05);
        }
        @media (max-width:600px) {
            .commission-grid {
                grid-template-columns:1fr;
            }
            .commission-label {
                margin-top:0.5em;
            }
        }
    </style>';
});


/* =====================================================
   ADMIN: Upload Interface & AJAX Handler
   ===================================================== */

/**
 * Add file upload interface in admin order items
 */
add_action( 'woocommerce_after_order_itemmeta', function( $item_id, $item, $product ) {

    if ( $item->get_type() !== 'line_item' ) return;
    //if ( ! $item->get_meta('Unique Commission Key', true ) ) return;
    if ( ! $item->get_meta('_unique_commission_key', true ) ) return;

    $previews = $item->get_meta('Commission Preview Images', true );
    $previews = ms_normalize_preview_images($previews);

    echo '<div class="commission-admin-preview-box" data-item-id="' . esc_attr($item_id) . '">';
    echo '<strong>Commission Previews</strong><br>';

    if ( ! empty($previews) ) {
        echo '<div class="commission-admin-preview-list">';
        foreach ( $previews as $url ) {
            $url_esc = esc_url($url);
            echo '<div class="preview-item">';
            echo '<a href="' . $url_esc . '" target="_blank">';
            echo '<img src="' . $url_esc . '" alt="Preview" style="max-width:80px;height:auto;margin-right:10px;">';
            echo '</a>';
            echo '<a href="' . $url_esc . '" target="_blank">' . esc_html(basename($url)) . '</a>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p style="color:#666;font-style:italic;">No previews uploaded yet.</p>';
    }

    echo '<div style="margin-top:10px;">';
    echo '<label><strong>Upload New Previews:</strong></label><br>';
    echo '<input type="file" multiple accept="image/*" class="commission-preview-file-input" data-item-id="' . esc_attr($item_id) . '" />';
    echo '<button type="button" class="button commission-upload-btn" data-item-id="' . esc_attr($item_id) . '" style="margin-left:10px;">Upload Now</button>';
    echo '<span class="commission-upload-status" style="margin-left:10px;"></span>';
    echo '<p class="description">Select image files and click "Upload Now" to add previews. Customer will be notified by email.</p>';
    echo '</div>';

    echo '</div>';

    echo '<style>
        .commission-admin-preview-box {
            margin:1em 0;
            padding:1em;
            border:1px solid #ddd;
            background:#f9f9f9;
            border-radius:4px;
        }
        .commission-admin-preview-list {
            margin:0.5em 0;
        }
        .commission-admin-preview-list .preview-item {
            margin:0.5em 0;
            padding:0.5em;
            background:#fff;
            border:1px solid #e5e5e5;
            border-radius:3px;
            display:flex;
            align-items:center;
        }
        .commission-upload-status.uploading {
            color: #0073aa;
        }
        .commission-upload-status.success {
            color: #46b450;
        }
        .commission-upload-status.error {
            color: #dc3232;
        }
    </style>';

}, 10, 3 );

/**
 * Add AJAX upload script to admin
 */
add_action( 'admin_footer', function() {
    $screen = get_current_screen();
    if ( ! $screen || ( $screen->id !== 'shop_order' && $screen->id !== 'woocommerce_page_wc-orders' ) ) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Handle upload button click
        $(document).on('click', '.commission-upload-btn', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var itemId = $btn.data('item-id');
            var $fileInput = $('.commission-preview-file-input[data-item-id="' + itemId + '"]');
            var $status = $btn.siblings('.commission-upload-status');
            var files = $fileInput[0].files;

            if (!files || files.length === 0) {
                alert('Please select at least one image file.');
                return;
            }

            // Get order ID from URL or form
            var orderId = $('#post_ID').val() || $('input[name="post_ID"]').val();
            if (!orderId) {
                // Try to get from URL for HPOS
                var urlParams = new URLSearchParams(window.location.search);
                orderId = urlParams.get('id');
            }

            if (!orderId) {
                alert('Could not determine order ID');
                return;
            }

            // Prepare FormData
            var formData = new FormData();
            formData.append('action', 'upload_commission_preview');
            formData.append('order_id', orderId);
            formData.append('item_id', itemId);
            formData.append('nonce', '<?php echo wp_create_nonce("commission_preview_upload"); ?>');

            // Add all selected files
            for (var i = 0; i < files.length; i++) {
                formData.append('preview_images[]', files[i]);
            }

            // Show uploading status
            $btn.prop('disabled', true);
            $status.removeClass('success error').addClass('uploading').text('Uploading...');

            // Upload via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Upload response:', response);

                    if (response.success) {
                        $status.removeClass('uploading').addClass('success').text('✓ Uploaded ' + response.data.count + ' file(s) - Email sent!');
                        $fileInput.val(''); // Clear file input

                        // Reload page after 1.5 seconds to show new previews
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $status.removeClass('uploading').addClass('error').text('Error: ' + (response.data || 'Upload failed'));
                    }

                    $btn.prop('disabled', false);
                },
                error: function(xhr, status, error) {
                    console.error('Upload error:', error);
                    $status.removeClass('uploading').addClass('error').text('Upload failed');
                    $btn.prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
});

/**
 * AJAX Handler for Commission Preview Upload
 */
add_action( 'wp_ajax_upload_commission_preview', function() {

    // Verify nonce
    if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'commission_preview_upload') ) {
        wp_send_json_error('Invalid security token');
        return;
    }

    // Check permissions
    if ( ! current_user_can('edit_shop_orders') ) {
        wp_send_json_error('Insufficient permissions');
        return;
    }

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

    if ( ! $order_id || ! $item_id ) {
        wp_send_json_error('Missing order or item ID');
        return;
    }

    // Get order and item
    $order = wc_get_order($order_id);
    if ( ! $order ) {
        wp_send_json_error('Invalid order');
        return;
    }

    $item = $order->get_item($item_id);
    if ( ! $item ) {
        wp_send_json_error('Invalid order item');
        return;
    }

    // Check if files were uploaded
    if ( empty($_FILES['preview_images']) ) {
        wp_send_json_error('No files uploaded');
        return;
    }

    // Setup upload directory
    $upload_dir = wp_upload_dir();
    $custom_dir = trailingslashit($upload_dir['basedir']) . 'commission-previews/';
    $custom_url = trailingslashit($upload_dir['baseurl']) . 'commission-previews/';

    if ( ! file_exists($custom_dir) ) {
        wp_mkdir_p($custom_dir);
    }

    $uploaded_urls = [];
    $files = $_FILES['preview_images'];

    // Handle multiple files
    $file_count = count($files['name']);

    for ( $i = 0; $i < $file_count; $i++ ) {

        // Skip if error
        if ( $files['error'][$i] !== UPLOAD_ERR_OK ) {
            continue;
        }

        $file_name = $files['name'][$i];
        $tmp_name = $files['tmp_name'][$i];

        // Validate image type
        $file_type = wp_check_filetype($file_name);
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if ( ! in_array($file_type['ext'], $allowed_types) ) {
            continue;
        }

        // Generate unique filename
        $filename = wp_unique_filename( $custom_dir, sanitize_file_name($file_name) );
        $target_path = $custom_dir . $filename;

        // Move uploaded file
        if ( move_uploaded_file( $tmp_name, $target_path ) ) {
            $uploaded_urls[] = $custom_url . $filename;
        }
    }

    if ( empty($uploaded_urls) ) {
        wp_send_json_error('No valid images were uploaded');
        return;
    }

    // Get existing previews
    $existing = $item->get_meta('Commission Preview Images', true );
    $existing = ms_normalize_preview_images($existing);

    // Merge with new uploads
    $all_previews = array_merge($existing, $uploaded_urls);

    // Save to item meta
    $item->update_meta_data('Commission Preview Images', $all_previews);
    $item->save();

    // Mark as having new previews (for badge)
    $item->update_meta_data('Commission Preview New', true);
    $item->save();

    // Send customer notification email
    ms_send_commission_preview_email( $order, $item, $uploaded_urls );

    // Add order note
    $order->add_order_note(
        sprintf(
            __('Added %d preview image(s) to commission item: %s. Customer notified by email.', 'monospace-art-theme'),
            count($uploaded_urls),
            $item->get_name()
        )
    );

    wp_send_json_success([
        'count' => count($uploaded_urls),
        'message' => 'Successfully uploaded ' . count($uploaded_urls) . ' image(s) and sent notification email'
    ]);
});


/* =====================================================
   EMAIL NOTIFICATION SYSTEM
   ===================================================== */

/**
 * Send customer email when new preview is added
 */
function ms_send_commission_preview_email( $order, $item, $preview_urls ) {
    if ( ! $order || ! $item || empty($preview_urls) ) {
        return;
    }

    $mailer = WC()->mailer();
    $email_heading = sprintf(
        __('New preview added for your commission: %s', 'monospace-art-theme'),
        $item->get_name()
    );
    $subject = __('Your commission preview is ready', 'monospace-art-theme');

    ob_start();
    echo '<p>' . sprintf(__('Hi %s,', 'monospace-art-theme'), $order->get_billing_first_name()) . '</p>';
    echo '<p>' . __('We have uploaded a new preview for your commission. You can view it below or on your account page:', 'monospace-art-theme') . '</p>';

    echo '<div style="margin: 20px 0;">';
    foreach ( $preview_urls as $url ) {
        echo '<img src="' . esc_url($url) . '" style="max-width:100%;height:auto;border:1px solid #ddd;padding:2px;" alt="Commission Preview">';
    }
    echo '</div>';

    echo '<p><a href="' . esc_url(wc_get_account_endpoint_url('commissions')) . '" style="background:#46b450;color:#fff;padding:10px 20px;text-decoration:none;display:inline-block;border-radius:3px;">' . __('View All Commissions', 'monospace-art-theme') . '</a></p>';

    echo '<p style="color:#666;font-size:0.9em;">' . __('Order #', 'monospace-art-theme') . $order->get_id() . '</p>';

    $message = ob_get_clean();

    $mailer->send(
        $order->get_billing_email(),
        $subject,
        $mailer->wrap_message($email_heading, $message)
    );
}


/* =====================================================
   HELPER FUNCTIONS
   ===================================================== */

/**
 * Normalize preview images from various formats to array
 */
function ms_normalize_preview_images( $previews ) {
    if ( empty($previews) ) {
        return [];
    }

    if ( is_array($previews) ) {
        return array_filter($previews);
    }

    if ( is_string($previews) ) {
        // Try JSON first
        $maybe_json = json_decode($previews, true);
        if ( is_array($maybe_json) ) {
            return array_filter($maybe_json);
        }
        // Try comma-separated
        $arr = array_filter(array_map('trim', explode(',', $previews)));
        return $arr;
    }

    return [];
}

/**
 * Count new previews for current customer
 */
function ms_count_new_previews() {
    $customer_id = get_current_user_id();
    if ( ! $customer_id ) {
        return 0;
    }

    $count = 0;
    $orders = wc_get_orders([
        'customer_id' => $customer_id,
        'limit'       => -1,
        'status'      => ['pending','on-hold','processing','completed'],
    ]);

    foreach ( $orders as $order ) {
        foreach ( $order->get_items('line_item') as $item ) {
            if ( $item->get_meta('Commission Preview New', true) ) {
                $count++;
            }
        }
    }

    return $count;
}

/**
 * Clear new preview flags when customer views commissions tab
 */
function ms_clear_new_preview_flags( $customer_id ) {
    if ( ! $customer_id ) {
        return;
    }

    $orders = wc_get_orders([
        'customer_id' => $customer_id,
        'limit'       => -1,
        'status'      => ['pending','on-hold','processing','completed'],
    ]);

    foreach ( $orders as $order ) {
        foreach ( $order->get_items('line_item') as $item ) {
            if ( $item->get_meta('Commission Preview New', true) ) {
                $item->update_meta_data('Commission Preview New', false);
                $item->save();
            }
        }
    }
}
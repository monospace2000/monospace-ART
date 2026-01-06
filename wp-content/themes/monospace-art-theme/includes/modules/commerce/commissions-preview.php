<?php
/**
 * Commission Preview System
 * Customer-facing tab + admin upload interface
 *
 * @package monospace-art-theme
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

// Constants
define('MS_PREVIEW_UPLOAD_DIR', 'commissions');
define('MS_PREVIEW_MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('MS_PREVIEW_MAX_FILES', 10);
define('MS_PREVIEW_CACHE_GROUP', 'ms_commission_previews');

/**
 * ===========================
 * SETUP & ACTIVATION
 * ===========================
 */

/**
 * Register endpoint (with proper timing)
 */
function ms_register_commissions_endpoint() {
    add_rewrite_endpoint('my-commissions', EP_ROOT | EP_PAGES);
}

if (did_action('init')) {
    ms_register_commissions_endpoint();
} else {
    add_action('init', 'ms_register_commissions_endpoint');
}

add_filter('query_vars', function($vars) {
    $vars[] = 'my-commissions';
    return $vars;
});

add_filter('woocommerce_get_query_vars', function($vars) {
    $vars['my-commissions'] = 'my-commissions';
    return $vars;
});

// One-time flush on activation
if (!get_option('ms_commissions_endpoint_v2')) {
    add_action('init', function() {
        flush_rewrite_rules();
        update_option('ms_commissions_endpoint_v2', true);
    }, 999);
}

/**
 * ===========================
 * HELPER FUNCTIONS
 * ===========================
 */

/**
 * Get commission meta (uses helper from core)
 */
if (!function_exists('ms_get_commission_meta')) {
    function ms_get_commission_meta($item, $key, $single = true) {
        $new_key = MS_COMMISSION_META_PREFIX . $key;
        return $item->get_meta($new_key, $single);
    }
}

/**
 * Set commission meta (uses helper from core)
 */
if (!function_exists('ms_set_commission_meta')) {
    function ms_set_commission_meta($item, $key, $value) {
        $item->update_meta_data(MS_COMMISSION_META_PREFIX . $key, $value);
    }
}

/**
 * Normalize preview images
 */
function ms_normalize_preview_images($previews) {
    if (empty($previews)) return [];
    if (is_array($previews)) return array_filter($previews);

    $maybe_json = json_decode($previews, true);
    if (is_array($maybe_json)) return array_filter($maybe_json);

    return array_filter(array_map('trim', explode(',', $previews)));
}

/**
 * Count new previews (with caching)
 */
function ms_count_new_previews() {
    $customer_id = get_current_user_id();
    if (!$customer_id) return 0;

    // Check cache first
    $cache_key = 'new_preview_count_' . $customer_id;
    $cached = wp_cache_get($cache_key, MS_PREVIEW_CACHE_GROUP);

    if ($cached !== false) {
        return intval($cached);
    }

    // Query database
    $count = 0;
    $orders = wc_get_orders([
        'customer_id' => $customer_id,
        'limit' => -1,
        'status' => ['pending', 'on-hold', 'processing', 'completed'],
    ]);

    foreach ($orders as $order) {
        foreach ($order->get_items('line_item') as $item) {
            if (ms_get_commission_meta($item, 'preview_new')) {
                $count++;
            }
        }
    }

    // Cache for 5 minutes
    wp_cache_set($cache_key, $count, MS_PREVIEW_CACHE_GROUP, 300);

    return $count;
}

/**
 * Clear new preview flags
 */
function ms_clear_new_preview_flags($customer_id) {
    if (!$customer_id) return;

    $orders = wc_get_orders([
        'customer_id' => $customer_id,
        'limit' => -1,
        'status' => ['pending', 'on-hold', 'processing', 'completed'],
    ]);

    foreach ($orders as $order) {
        foreach ($order->get_items('line_item') as $item) {
            if (ms_get_commission_meta($item, 'preview_new')) {
                ms_set_commission_meta($item, 'preview_new', false);
                $item->save();
            }
        }
    }

    // Clear cache
    wp_cache_delete('new_preview_count_' . $customer_id, MS_PREVIEW_CACHE_GROUP);
}

/**
 * ===========================
 * FRONTEND: MY ACCOUNT TAB
 * ===========================
 */

/**
 * Add tab to My Account
 */
add_filter('woocommerce_account_menu_items', function($items) {
    $new = [];
    foreach ($items as $key => $label) {
        $new[$key] = $label;
        if ($key === 'orders') {
            $new['my-commissions'] = __('Commissions', 'monospace-art-theme');
        }
    }
    return $new;
}, 20);

/**
 * Add badge count
 */
add_action('wp_footer', function() {
    if (!is_account_page()) return;

    $count = ms_count_new_previews();
    if ($count <= 0) return;
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var link = document.querySelector('.woocommerce-MyAccount-navigation-link--my-commissions a');
        if (link) {
            var badge = document.createElement('span');
            badge.className = 'ms-badge';
            badge.textContent = '<?php echo intval($count); ?>';
            link.appendChild(badge);
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
});

/**
 * Render commissions tab
 */
add_action('woocommerce_account_my-commissions_endpoint', function() {
    if (!is_user_logged_in()) {
        echo '<p>Please log in to view your commissions.</p>';
        return;
    }

    $customer_id = get_current_user_id();
    ms_clear_new_preview_flags($customer_id);

    $orders = wc_get_orders([
        'customer_id' => $customer_id,
        'limit' => -1,
        'status' => ['pending', 'on-hold', 'processing', 'completed'],
    ]);

    $has_commissions = false;
    if (!empty($orders)) {
        foreach ($orders as $order) {
            foreach ($order->get_items('line_item') as $item) {
                if (ms_get_commission_meta($item, 'key')) {
                    $has_commissions = true;
                    break 2;
                }
            }
        }
    }

    if (!$has_commissions) {
        echo '<div class="woocommerce-info">';
        echo esc_html__('No commissions available yet.', 'monospace-art-theme');
        echo ' <a class="button" href="' . esc_url(home_url('/services/custom-work/')) . '">' . esc_html__('Browse custom orders', 'monospace-art-theme') . '</a>';
        echo '</div>';
        return;
    }

    echo '<div class="ms-commissions-list">';

    foreach ($orders as $order) {
        $commission_items = [];

        foreach ($order->get_items('line_item') as $item) {
            $commission_key = ms_get_commission_meta($item, 'key');
            if (!$commission_key) continue;

            $full_price = (float) ms_get_commission_meta($item, 'full_price');
            if (!$full_price || $full_price <= 0) {
                $prod = wc_get_product($item->get_product_id());
                $full_price = $prod ? (float) $prod->get_price() : 0;
            }

            $deposit_percent = (int) get_post_meta($item->get_product_id(), '_commission_deposit_percent', true);
            if ($deposit_percent <= 0) $deposit_percent = 30;

            $deposit_amount = round(($deposit_percent / 100) * $full_price, wc_get_price_decimals());
            $remaining_amount = round($full_price - $deposit_amount, wc_get_price_decimals());

            $previews = ms_get_commission_meta($item, 'preview_images');
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

        if (empty($commission_items)) continue;

        echo '<div class="commission-block">';
        echo '<h2>Order #' . esc_html($order->get_id()) . ' (' . esc_html(wc_get_order_status_name($order->get_status())) . ')</h2>';

        foreach ($commission_items as $c) {
            $item = $c['item'];

            echo '<div class="commission-item">';
            echo '<div class="commission-grid">';

            $fields = [
                'Item' => $item->get_name(),
                'Full Price' => '<span class="commission-price">' . wc_price($c['full_price']) . '</span>',
                'Deposit (' . intval($c['deposit_percent']) . '%)' => '<span class="commission-price">' . wc_price($c['deposit']) . '</span>',
                'Remaining' => '<span class="commission-price">' . wc_price($c['remaining']) . '</span>',
                'Special Requests' => ms_get_commission_meta($item, 'special_request') ?: '—',
            ];

            foreach ($fields as $label => $value) {
                echo '<div class="commission-label">' . esc_html($label) . '</div>';
                echo '<div class="commission-value">' . wp_kses_post($value) . '</div>';
            }

            echo '<div class="commission-label">Previews</div>';
            echo '<div class="commission-value">';
            if (!empty($c['previews'])) {
                echo '<div class="commission-preview-gallery">';
                foreach ($c['previews'] as $url) {
                    $url = esc_url($url);
                    echo '<a href="' . $url . '" target="_blank" class="commission-preview-link">';
                    echo '<img src="' . $url . '" alt="Commission Preview" class="commission-preview-img">';
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

    // CSS
    echo '<style>
        .commission-block {
            border: 1px solid #e1e1e1;
            padding: 1em;
            margin-bottom: 1.5em;
            background: #fff;
        }
        .commission-block h2 {
            margin-top: 0;
            font-size: 1.25em;
        }
        .commission-item {
            border-top: 1px solid #ccc;
            padding-top: 0.75em;
            margin-top: 0.75em;
        }
        .commission-price {
            font-size: 1em;
            font-weight: normal !important;
        }
        .commission-grid {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 0.5em 1em;
            align-items: start;
        }
        .commission-label {
            font-weight: bold;
            color: #555;
        }
        .commission-value {
            color: #333;
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
            width: 70px;
            height: 70px;
            object-fit: cover;
            border: 1px solid #ddd;
            padding: 2px;
            background: #fff;
            transition: transform 0.2s;
        }
        .commission-preview-img:hover {
            transform: scale(1.05);
        }
        @media (max-width: 600px) {
            .commission-grid {
                grid-template-columns: 1fr;
            }
            .commission-label {
                margin-top: 0.5em;
            }
        }
    </style>';
});

/**
 * ===========================
 * ADMIN: UPLOAD INTERFACE
 * ===========================
 */

/**
 * Add upload interface to order items
 */
add_action('woocommerce_after_order_itemmeta', function($item_id, $item, $product) {
    if ($item->get_type() !== 'line_item') return;

    $commission_key = ms_get_commission_meta($item, 'key');
    if (!$commission_key) return;

    $previews = ms_get_commission_meta($item, 'preview_images');
    $previews = ms_normalize_preview_images($previews);

    echo '<div class="commission-admin-preview-box" data-item-id="' . esc_attr($item_id) . '">';
    echo '<strong>Commission Previews</strong><br>';

    if (!empty($previews)) {
        echo '<div class="commission-admin-preview-list">';
        foreach ($previews as $url) {
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
    echo '<button type="button" class="button commission-upload-btn" data-item-id="' . esc_attr($item_id) . '" style="margin-left:10px;">Upload & Send Email</button>';
    echo '<span class="commission-upload-status" style="margin-left:10px;"></span>';
    echo '<p class="description">Max ' . MS_PREVIEW_MAX_FILES . ' images, ' . (MS_PREVIEW_MAX_FILE_SIZE / 1024 / 1024) . 'MB each. You can customize the email message.</p>';
    echo '</div>';

    echo '</div>';

    echo '<style>
        .commission-admin-preview-box {
            margin: 1em 0;
            padding: 1em;
            border: 1px solid #ddd;
            background: #f9f9f9;
            border-radius: 4px;
        }
        .commission-admin-preview-list {
            margin: 0.5em 0;
        }
        .commission-admin-preview-list .preview-item {
            margin: 0.5em 0;
            padding: 0.5em;
            background: #fff;
            border: 1px solid #e5e5e5;
            border-radius: 3px;
            display: flex;
            align-items: center;
        }
        .commission-upload-status.uploading { color: #0073aa; }
        .commission-upload-status.success { color: #46b450; }
        .commission-upload-status.error { color: #dc3232; }
    </style>';
}, 10, 3);

/**
 * Add upload AJAX script
 */
add_action('admin_footer', function() {
    $screen = get_current_screen();
    if (!$screen || ($screen->id !== 'shop_order' && $screen->id !== 'woocommerce_page_wc-orders')) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Create modal
        if (!$('#commission-email-modal').length) {
            $('body').append(`
                <div id="commission-email-modal" style="display:none;">
                    <div style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:100000;display:flex;align-items:center;justify-content:center;">
                        <div style="background:#fff;padding:30px;border-radius:8px;max-width:600px;width:90%;max-height:80vh;overflow-y:auto;">
                            <h2 style="margin-top:0;">Customize Email Message</h2>
                            <p style="color:#666;">Write a personal message for the notification email:</p>

                            <textarea id="commission-custom-message" rows="8" style="width:100%;padding:10px;border:1px solid #ddd;border-radius:4px;font-family:inherit;font-size:14px;" placeholder="Example: Hi! Your commission is coming along great. Here's the latest progress. Let me know if you'd like any changes!"></textarea>

                            <p style="color:#666;font-size:12px;margin-top:10px;">
                                <strong>Tip:</strong> Customer name, images, and account link are added automatically.
                            </p>

                            <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end;">
                                <button type="button" class="button" id="commission-modal-cancel">Cancel</button>
                                <button type="button" class="button button-primary" id="commission-modal-send">Send Email & Upload</button>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        }

        // Upload button click - show modal
        $(document).on('click', '.commission-upload-btn', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var itemId = $btn.data('item-id');
            var $fileInput = $('.commission-preview-file-input[data-item-id="' + itemId + '"]');
            var files = $fileInput[0].files;

            if (!files || files.length === 0) {
                alert('Please select at least one image file.');
                return;
            }

            if (files.length > <?php echo MS_PREVIEW_MAX_FILES; ?>) {
                alert('Maximum <?php echo MS_PREVIEW_MAX_FILES; ?> files allowed.');
                return;
            }

            $('#commission-email-modal').data('btn', $btn);
            $('#commission-email-modal').data('itemId', itemId);
            $('#commission-email-modal').data('fileInput', $fileInput);

            $('#commission-custom-message').val('');
            $('#commission-email-modal').fadeIn(200);
        });

        // Modal cancel
        $(document).on('click', '#commission-modal-cancel', function() {
            $('#commission-email-modal').fadeOut(200);
        });

        // Modal send
        $(document).on('click', '#commission-modal-send', function() {
            var $modal = $('#commission-email-modal');
            var $btn = $modal.data('btn');
            var itemId = $modal.data('itemId');
            var $fileInput = $modal.data('fileInput');
            var $status = $btn.siblings('.commission-upload-status');
            var customMessage = $('#commission-custom-message').val().trim();
            var files = $fileInput[0].files;

            $modal.fadeOut(200);

            var orderId = $('#post_ID').val() || $('input[name="post_ID"]').val();
            if (!orderId) {
                var urlParams = new URLSearchParams(window.location.search);
                orderId = urlParams.get('id');
            }

            if (!orderId) {
                alert('Could not determine order ID');
                return;
            }

            var formData = new FormData();
            formData.append('action', 'ms_upload_commission_preview');
            formData.append('order_id', orderId);
            formData.append('item_id', itemId);
            formData.append('custom_message', customMessage);
            formData.append('nonce', '<?php echo wp_create_nonce("ms_commission_preview_upload"); ?>');

            for (var i = 0; i < files.length; i++) {
                formData.append('preview_images[]', files[i]);
            }

            $btn.prop('disabled', true);
            $status.removeClass('success error').addClass('uploading').text('Uploading...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $status.removeClass('uploading').addClass('success').text('✓ Uploaded ' + response.data.count + ' file(s) - Email sent!');
                        $fileInput.val('');
                        setTimeout(function() { location.reload(); }, 1500);
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

        // ESC to close modal
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#commission-email-modal').is(':visible')) {
                $('#commission-email-modal').fadeOut(200);
            }
        });
    });
    </script>
    <?php
});

/**
 * ===========================
 * AJAX: UPLOAD HANDLER
 * ===========================
 */
add_action('wp_ajax_ms_upload_commission_preview', function() {
    check_ajax_referer('ms_commission_preview_upload', 'nonce');

    if (!current_user_can('edit_shop_orders')) {
        wp_send_json_error('Insufficient permissions');
    }

    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;

    if (!$order_id || !$item_id) {
        wp_send_json_error('Missing order or item ID');
    }

    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Invalid order');
    }

    $item = $order->get_item($item_id);
    if (!$item) {
        wp_send_json_error('Invalid order item');
    }

    if (empty($_FILES['preview_images'])) {
        wp_send_json_error('No files uploaded');
    }

    $custom_message = isset($_POST['custom_message']) ? sanitize_textarea_field($_POST['custom_message']) : '';

    // Setup upload directory - organize by order ID with previews subfolder
    $upload_dir = wp_upload_dir();
    $base_dir = trailingslashit($upload_dir['basedir']) . MS_PREVIEW_UPLOAD_DIR;
    $base_url = trailingslashit($upload_dir['baseurl']) . MS_PREVIEW_UPLOAD_DIR;

    // Create order-specific subdirectory: /commissions/order-xxxx/previews/
    $order_dir = trailingslashit($base_dir) . 'order-' . $order_id;
    $previews_dir = trailingslashit($order_dir) . 'previews';
    $previews_url = trailingslashit($base_url) . 'order-' . $order_id . '/previews';

    if (!file_exists($previews_dir)) {
        wp_mkdir_p($previews_dir);
        // Add protection files to order directory
        if (!file_exists($order_dir . '/.htaccess')) {
            file_put_contents($order_dir . '/.htaccess', 'Options -Indexes');
        }
        if (!file_exists($order_dir . '/index.php')) {
            file_put_contents($order_dir . '/index.php', '<?php // Silence is golden');
        }
    }

    $uploaded_urls = [];
    $files = $_FILES['preview_images'];
    $file_count = count($files['name']);

    if ($file_count > MS_PREVIEW_MAX_FILES) {
        wp_send_json_error('Maximum ' . MS_PREVIEW_MAX_FILES . ' files allowed');
    }

    for ($i = 0; $i < $file_count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $file_array = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i]
        ];

        // Validate
        if ($file_array['size'] > MS_PREVIEW_MAX_FILE_SIZE) {
            continue;
        }

        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file_array['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed_types)) {
            continue;
        }

        // Upload with timestamp prefix to prevent overwrites
        $timestamp = current_time('Y-m-d_H-i-s');
        $filename = $timestamp . '_' . sanitize_file_name($file_array['name']);
        $filename = wp_unique_filename($previews_dir, $filename);

        if (move_uploaded_file($file_array['tmp_name'], $previews_dir . '/' . $filename)) {
            $uploaded_urls[] = $previews_url . '/' . $filename;
        }
    }

    if (empty($uploaded_urls)) {
        wp_send_json_error('No valid images were uploaded');
    }

    // Get existing previews
    $existing = ms_get_commission_meta($item, 'preview_images');
    $existing = ms_normalize_preview_images($existing);

    // Merge
    $all_previews = array_merge($existing, $uploaded_urls);

    // Save
    ms_set_commission_meta($item, 'preview_images', $all_previews);
    ms_set_commission_meta($item, 'preview_new', true);
    $item->save();

    // Clear customer's cache
    $customer_id = $order->get_customer_id();
    if ($customer_id) {
        wp_cache_delete('new_preview_count_' . $customer_id, MS_PREVIEW_CACHE_GROUP);
    }

    // Send email
    ms_send_commission_preview_email($order, $item, $uploaded_urls, $custom_message);

    // Add order note
    $order->add_order_note(
        sprintf(
            'Added %d preview image(s) to commission: %s. Customer notified.',
            count($uploaded_urls),
            $item->get_name()
        )
    );

    wp_send_json_success([
        'count' => count($uploaded_urls),
        'message' => 'Successfully uploaded and sent email'
    ]);
});

/**
 * ===========================
 * EMAIL NOTIFICATION
 * ===========================
 */
function ms_send_commission_preview_email($order, $item, $preview_urls, $custom_message = '') {
    if (!$order || !$item || empty($preview_urls)) {
        return;
    }

    $mailer = WC()->mailer();
    $email_heading = sprintf('New preview for your commission: %s', $item->get_name());
    $subject = 'Your commission preview is ready';

    ob_start();
    echo '<p>' . sprintf('Hi %s,', $order->get_billing_first_name()) . '</p>';

    if (!empty($custom_message)) {
        echo '<p>' . nl2br(esc_html($custom_message)) . '</p>';
    } else {
        echo '<p>We have uploaded a new preview for your commission. You can view it below or on your account page:</p>';
    }

    echo '<div style="margin: 20px 0;">';
    foreach ($preview_urls as $url) {
        echo '<img src="' . esc_url($url) . '" style="max-width:100%;height:auto;border:1px solid #ddd;padding:2px;margin-bottom:10px;" alt="Commission Preview">';
    }
    echo '</div>';

    echo '<p><a href="' . esc_url(wc_get_account_endpoint_url('my-commissions')) . '" style="background:#46b450;color:#fff;padding:10px 20px;text-decoration:none;display:inline-block;border-radius:3px;">View All Commissions</a></p>';

    echo '<p style="color:#666;font-size:0.9em;">Order #' . $order->get_id() . '</p>';

    $message = ob_get_clean();

    $wrapped_message = $mailer->wrap_message($email_heading, $message);

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'Bcc: ' . MS_COMMISSION_ADMIN_EMAIL
    ];

    wp_mail(
        $order->get_billing_email(),
        $subject,
        $wrapped_message,
        $headers
    );
}
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
            if (ms_get_commission_meta($item, 'preview_new') && !(function_exists('ms_view_as_reading_only') && ms_view_as_reading_only())) {   // not while an admin looks as the customer (View As)
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
        echo esc_html__('No commissions available yet.', 'monospace-art-theme');   // text only, no button (owner, 2026-11-18)
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

            // The amounts stored on the order at purchase (the product's current deposit % may have changed since)
            $deposit_amount = (float) ms_get_commission_meta($item, 'deposit_paid');
            if ($deposit_amount > 0) {
                $deposit_percent = $full_price > 0 ? (int) round($deposit_amount / $full_price * 100) : 0;
            } else {
                $deposit_percent = (int) get_post_meta($item->get_product_id(), '_commission_deposit_percent', true);
                if ($deposit_percent <= 0) $deposit_percent = 30;
                $deposit_amount = round(($deposit_percent / 100) * $full_price, wc_get_price_decimals());
            }
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
        if (function_exists('ms_commission_render_status')) ms_commission_render_status($order);
        if (function_exists('ms_commission_render_tracking')) ms_commission_render_tracking($order);

        foreach ($commission_items as $c) {
            $item = $c['item'];

            echo '<div class="commission-item">';
            echo '<div class="commission-grid">';

            $fields = [
                'Item' => ucfirst(ms_commission_friendly_name($item->get_name())),
                'Full Price' => '<span class="commission-price">' . wc_price($c['full_price']) . '</span>',
                'Deposit (' . intval($c['deposit_percent']) . '%)' => '<span class="commission-price">' . wc_price($c['deposit']) . '</span>',
                'Remaining' => '<span class="commission-price">' . wc_price($c['remaining']) . '</span>',
                'Special Requests' => ms_get_commission_meta($item, 'special_request') ?: '—',
            ];

            // The photos the customer sent with the order (kept privately; 6 months after completion they are deleted)
            $refs = ms_normalize_image_array(ms_get_commission_meta($item, 'reference_images'));
            $refs_deleted = ms_get_commission_meta($item, 'references_deleted');
            if ($refs_deleted) {
                $fields['Reference photos'] = 'Deleted on ' . esc_html($refs_deleted) . ', as the policies say.';
            } elseif ($refs) {
                $html = '<div class="commission-preview-gallery">';
                foreach ($refs as $url) {
                    $html .= '<a href="' . esc_url($url) . '" target="_blank" class="commission-preview-link"><img src="' . esc_url(ms_commission_thumb_url($url)) . '" alt="Reference photo" class="commission-preview-img" loading="lazy"></a>';
                }
                $fields['Reference photos'] = $html . '</div>';
            }

            foreach ($fields as $label => $value) {
                echo '<div class="commission-label">' . esc_html($label) . '</div>';
                echo '<div class="commission-value">' . wp_kses_post($value) . '</div>';
            }

            echo '<div class="commission-label">Work in progress</div>';
            echo '<div class="commission-value">';
            if (!empty($c['previews'])) {
                echo '<div class="commission-preview-gallery">';
                foreach ($c['previews'] as $url) {
                    $url = esc_url($url);
                    echo '<a href="' . $url . '" target="_blank" class="commission-preview-link">';
                    echo '<img src="' . esc_url(ms_commission_web_url($url)) . '" alt="Commission work in progress" class="commission-preview-img">';
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

        // Reply buttons, the conversation (newest on top, a foldout), then Approve final artwork below it (owner, 2026-11-17)
        if (function_exists('ms_commission_render_respond')) ms_commission_render_respond($order, 'reply');
        if (function_exists('ms_commission_render_conversation')) ms_commission_render_conversation($order);
        if (function_exists('ms_commission_render_respond')) ms_commission_render_respond($order, 'approve');
        echo '</div>'; // .commission-block
    }

    echo '</div>'; // .ms-commissions-list

    // CSS
    echo '<style>
        .commission-block {
            border: solid var(--ms-card-border-width, 1px) var(--ms-card-border, #e1e1e1);
            border-radius: var(--ms-card-radius, var(--ms-radius, 8px));
            padding: 1em;
            margin-bottom: 1.5em;
            background: var(--ms-card-bg, #fff);
            color: var(--ms-text, #333);
        }
        .commission-block h2 {
            margin-top: 0;
            font-size: 1.25em;
        }
        .commission-item {
            border-top: 1px solid var(--ms-card-border, #ccc);
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
            color: var(--ms-text-light, #555);
        }
        .commission-value {
            color: var(--ms-text, #333);
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
            width: 140px;
            height: 140px;
            object-fit: cover;
            border: 1px solid var(--ms-card-border, #ddd);
            padding: 2px;
            background: var(--ms-card-bg, #fff);
            transition: transform 0.2s;
        }
        .commission-preview-img:hover {
            transform: scale(1.05);
        }
        .ms-respond-approve {
            --btn-color: rgba(34, 139, 58, 0.92);
            --btn-text: #fff;
        }
        .ms-respond-approve:disabled {
            --btn-color: rgba(120, 120, 120, 0.9);
        }
        .ms-respond-approve-hint {
            margin: 0.5em 0 0;
            font-size: 0.85em;
            color: var(--ms-text-light, #555);
        }
        .commission-status-box {
            padding: 0.8em 1em;
            margin-bottom: 1em;
            border-radius: 6px;
            color: #1d2327;
            background: #e8f1fb;
        }
        .commission-status-box.is-waiting {
            background: #fff4d6;
        }
        .commission-status-box.is-paid {
            background: #e4f4e8;
        }
        html.ms-dark-ui .commission-block {
            background: var(--ms-card-bg, #2b2926);
            border-color: var(--ms-card-border, #48443f);
        }
        html.ms-dark-ui .commission-item,
        html.ms-dark-ui .commission-conversation {
            border-top-color: var(--ms-card-border, #48443f);
        }
        html.ms-dark-ui .commission-preview-img {
            background: var(--ms-card-bg, #2b2926);
            border-color: var(--ms-card-border, #48443f);
        }
        html.ms-dark-ui .commission-status-box {
            color: inherit;
            background: rgba(31, 111, 178, 0.28);
        }
        html.ms-dark-ui .commission-status-box.is-waiting {
            background: rgba(240, 184, 73, 0.22);
        }
        html.ms-dark-ui .commission-status-box.is-paid {
            background: rgba(46, 125, 50, 0.32);
        }
        .commission-status-box .commission-status,
        .commission-status-box .commission-status-hint {
            margin: 0;
        }
        .commission-status-box .commission-status-hint {
            margin-top: 0.3em;
            opacity: 0.85;
        }
        .commission-status-box .commission-status-dates {
            margin: 0.5em 0 0;
            font-size: 0.85em;
            opacity: 0.7;
        }
        .commission-conversation {
            border-top: 1px solid var(--ms-card-border, #ccc);
            padding-top: 1em;
            margin-top: 1em;
        }
        .commission-conversation > summary {
            font-weight: bold;
            cursor: pointer;
        }
        .commission-message-meta,
        .commission-message-text {
            margin: 0;
        }
        .commission-message {
            padding: 0.5em 0;
        }
        .commission-message-meta {
            font-size: 0.85em;
            color: var(--ms-text-light, #555);
        }
        .commission-message-photos {
            margin-top: 0.5em;
        }
        .commission-message-owner .commission-message-text {
            font-style: italic;
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
    echo '<strong>Work in progress</strong><br>';

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
        echo '<p style="color:#666;font-style:italic;">Nothing shared yet.</p>';
    }

    echo '<div style="margin-top:10px;">';
    echo '<label><strong>Share an update:</strong></label><br>';
    echo '<input type="file" multiple accept="image/*" class="commission-preview-file-input" data-item-id="' . esc_attr($item_id) . '" />';
    echo '<button type="button" class="button commission-upload-btn" data-item-id="' . esc_attr($item_id) . '" style="margin-left:10px;">Upload & Send Email</button>';
    echo '<span class="commission-upload-status" style="margin-left:10px;"></span>';
    echo '<p class="description">Max ' . MS_PREVIEW_MAX_FILES . ' images, ' . (MS_PREVIEW_MAX_FILE_SIZE / 1024 / 1024) . 'MB each. JPEG, PNG, GIF, WebP and HEIC (iPhone, Mac) work; HEIC is converted and large images are scaled down. You can customize the email message.</p>';
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

                            <p style="margin:14px 0 0;">
                                <label><input type="checkbox" id="commission-final"> <strong>This is the finished artwork</strong> - the customer can then press Approve final artwork (it stays grayed out until you share the finished piece this way).</label>
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
            formData.append('final', $('#commission-final').is(':checked') ? '1' : '');
            $('#commission-final').prop('checked', false);
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
                        var skipped = response.data.skipped || [];
                        $status.removeClass('uploading').addClass('success').text('✓ Uploaded ' + response.data.count + ' file(s) - Email sent!' + (skipped.length ? ' Not uploaded: ' + skipped.join(' ') : ''));
                        $fileInput.val('');
                        setTimeout(function() { location.reload(); }, skipped.length ? 8000 : 1500);
                    } else {
                        $status.removeClass('uploading').addClass('error').text('Error: ' + (response.data || 'Upload failed'));
                    }
                    $btn.prop('disabled', false);
                },
                error: function(xhr, status, error) {
                    console.error('Upload error:', error, xhr.status, xhr.responseText);
                    $status.removeClass('uploading').addClass('error').text('Upload failed (HTTP ' + xhr.status + '): ' + xhr.responseText.substring(0, 200));
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
 * Put one uploaded work-in-progress image into the commission's folder. HEIC/HEIF (iPhone, Mac) is turned into a JPEG with the
 * site's converter (Core, heic-uploads.php: ffmpeg); a file over the size limit is scaled down instead of refused (2026-11-16).
 * Returns the stored file name, or a WP_Error whose message says what was wrong with this file.
 *
 * @param string $tmp   uploaded temp file
 * @param string $name  name the browser gave it
 * @param string $dir   the previews folder (no trailing slash)
 */
function ms_commission_place_image($tmp, $name, $dir) {
    $stamp = current_time('Y-m-d_H-i-s') . '_';
    $path = $dir . '/' . wp_unique_filename($dir, $stamp . sanitize_file_name($name));

    $placed = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $path) : copy($tmp, $path);   // copy: only for tests
    if (!$placed) return new WP_Error('move_failed', $name . ': could not be saved on the server.');

    if (preg_match('/\.hei[cf]s?$/i', $path)) {
        $jpg = function_exists('monospace_heic_to_jpeg') ? monospace_heic_to_jpeg($path) : '';
        @unlink($path);
        if ($jpg === '') return new WP_Error('heic_failed', $name . ': this HEIC photo could not be turned into a JPEG. Export it as a JPEG and try again.');
        $path = $jpg;
    }

    $file = ['name' => basename($path), 'tmp_name' => $path, 'size' => filesize($path)];
    $ok = ms_validate_upload_file($file);

    if (is_wp_error($ok) && $ok->get_error_code() === 'file_too_large') {
        $editor = wp_get_image_editor($path);
        if (!is_wp_error($editor)) {
            $editor->resize(2400, 2400, false);
            $editor->set_quality(85);
            $editor->save($path);
            clearstatcache(true, $path);
            $file['size'] = filesize($path);
            $ok = ms_validate_upload_file($file);
        }
    }
    if (is_wp_error($ok)) {
        @unlink($path);
        return new WP_Error($ok->get_error_code(), $name . ': ' . $ok->get_error_message());
    }
    return basename($path);
}

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

    $custom_message = isset($_POST['custom_message']) ? sanitize_textarea_field(wp_unslash($_POST['custom_message'])) : '';
    $is_final = !empty($_POST['final']);
    if ($is_final) {
        // the customer is told in the email, and the same words are kept in the conversation
        $custom_message = trim($custom_message . "\n\nThis is the finished artwork. When you are happy with it, you can approve it in your account.");
    }

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

    $skipped = [];
    for ($i = 0; $i < $file_count; $i++) {
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            $skipped[] = $files['name'][$i] . ': the upload did not arrive (error ' . (int) $files['error'][$i] . ').';
            continue;
        }
        $stored = ms_commission_place_image($files['tmp_name'][$i], $files['name'][$i], $previews_dir);
        if (is_wp_error($stored)) {
            $skipped[] = $stored->get_error_message();
            continue;
        }
        $uploaded_urls[] = $previews_url . '/' . $stored;
    }

    if (empty($uploaded_urls)) {
        wp_send_json_error('Nothing was uploaded. ' . implode(' ', $skipped));
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

    // The customer's Approve final artwork button is on only while the last thing shared is the finished artwork (owner, 2026-11-17)
    if ($is_final) $order->update_meta_data('_ms_commission_final_shared', time());
    else $order->delete_meta_data('_ms_commission_final_shared');
    $order->save();

    // Add order note
    $order->add_order_note(
        sprintf(
            'Shared %d %s image(s) on commission: %s. Customer notified.',
            count($uploaded_urls),
            $is_final ? 'finished-artwork' : 'work-in-progress',
            $item->get_name()
        ) . ($custom_message !== '' ? "\nMessage: " . $custom_message : '')   // read back by ms_commission_thread()
    );

    wp_send_json_success([
        'count' => count($uploaded_urls),
        'skipped' => $skipped,
        'message' => 'Successfully uploaded and sent email'
    ]);
});

/**
 * The preview email now lives in commissions-emails.php (a WooCommerce email class, via ms_send_commission_preview_email()).
 */

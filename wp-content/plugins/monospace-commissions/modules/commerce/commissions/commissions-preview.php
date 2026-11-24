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

        echo '<div class="commission-block" id="commission-' . esc_attr($order->get_id()) . '" style="scroll-margin-top:140px">';   // the message email's link lands here
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
    echo '<span class="commission-upload-status" role="status" aria-live="polite" style="margin-left:10px;"></span>';
    echo '<progress class="commission-upload-progress" max="100" value="0" hidden></progress>';
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
        /* one class per state, set by the send script (2026-11-22) */
        .commission-upload-status { display: inline-block; vertical-align: middle; font-weight: 600; }
        .commission-upload-status.is-working { color: #2271b1; }
        .commission-upload-status.is-done { color: #00a32a; }
        .commission-upload-status.is-error { color: #d63638; }
        .commission-upload-status.is-unknown { color: #996800; }
        .commission-upload-progress { display: block; width: 100%; max-width: 420px; height: 8px; margin: 8px 0 2px; accent-color: #2271b1; }
        .commission-upload-progress[hidden] { display: none; }
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

        // ---- Sending an update (rewritten 2026-11-22, after an update hung on "Uploading..." on an iPhone with no way to tell
        // whether it went out). Photos are shrunk on the device first (the server keeps 2400 px anyway), the upload shows
        // progress, a stalled upload is given up on, and the screen then asks the server what happened to this send
        // (each send has an id; ms_commission_upload_check). A retry keeps the id, so the server never emails it twice.
        var cpuNonce = '<?php echo wp_create_nonce("ms_commission_preview_upload"); ?>';
        var cpuBusy = false;
        var CPU_STALL_UPLOAD = 45000;   // no upload progress for this long: give up and check
        var CPU_STALL_SERVER = 120000;  // upload finished but no answer for this long: give up and check

        function cpuId() {
            var a = new Uint8Array(12);
            window.crypto.getRandomValues(a);
            return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
        }
        function cpuKey(orderId, itemId) { return 'msCpu:' + orderId + ':' + itemId; }
        function cpuSave(orderId, itemId, rec) { try { localStorage.setItem(cpuKey(orderId, itemId), JSON.stringify(rec)); } catch (e) {} }
        function cpuLoad(orderId, itemId) { try { return JSON.parse(localStorage.getItem(cpuKey(orderId, itemId)) || 'null'); } catch (e) { return null; } }
        function cpuSig(files) { return files.map(function (f) { return f.name + ':' + f.size; }).join('|'); }
        function cpuTime(t) { return new Date(t).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }); }
        function cpuPlural(n, word) { return n + ' ' + word + (n === 1 ? '' : 's'); }
        function cpuShow($status, state, text) {
            $status.removeClass('is-working is-done is-error is-unknown').addClass('is-' + state).text(text);
        }
        function cpuOrderId() {
            var id = $('#post_ID').val() || $('input[name="post_ID"]').val();
            return id || new URLSearchParams(window.location.search).get('id');
        }

        // a photo straight from a phone is 3-10 MB: send a 2400 px JPEG instead (falls back to the original file)
        function cpuShrink(file) {
            return new Promise(function (resolve) {
                if (!/\.(jpe?g|png|webp|heic|heif)$/i.test(file.name) && !/^image\/(jpeg|png|webp|heic|heif)$/i.test(file.type)) return resolve(file);
                var url = URL.createObjectURL(file), img = new Image();
                var done = function (out) { URL.revokeObjectURL(url); resolve(out); };
                img.onload = function () {
                    var w = img.naturalWidth, h = img.naturalHeight;
                    if (!w || !h) return done(file);
                    var scale = Math.min(1, 2400 / Math.max(w, h));
                    var heic = /hei[cf]/i.test(file.type + ' ' + file.name);
                    if (scale === 1 && !heic && file.size < 2.5 * 1024 * 1024) return done(file);
                    var c = document.createElement('canvas');
                    c.width = Math.round(w * scale); c.height = Math.round(h * scale);
                    var ctx = c.getContext('2d');
                    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, c.width, c.height);
                    ctx.drawImage(img, 0, 0, c.width, c.height);
                    c.toBlob(function (blob) {
                        if (!blob || (!heic && blob.size >= file.size)) return done(file);
                        done(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg', lastModified: file.lastModified }));
                    }, 'image/jpeg', 0.88);
                };
                img.onerror = function () { done(file); };
                img.src = url;
            });
        }

        // ask the server what happened to a send: calls back with {state: unknown|working|done|failed, ...} or null (no connection)
        function cpuCheck(sendId, cb) {
            $.ajax({ url: ajaxurl, type: 'POST', timeout: 20000,
                data: { action: 'ms_commission_upload_check', nonce: cpuNonce, send_id: sendId } })
             .done(function (r) { cb(r && r.success ? r.data : null); })
             .fail(function () { cb(null); });
        }

        // the outcome, in plain words
        function cpuReport(state, data, n) {
            if (state === 'done') {
                var c = data && data.count ? data.count : n;
                var skipped = data && data.skipped && data.skipped.length ? ' Not uploaded: ' + data.skipped.join(' ') : '';
                return ['done', 'Sent: ' + cpuPlural(c, 'photo') + ' shared and the customer emailed.' + skipped];
            }
            if (state === 'failed') return ['error', 'Not sent: ' + ((data && data.message) || 'the server refused the upload.') + ' Nothing was emailed.'];
            // 'unknown' after a cut connection: SiteGround's bot check stops uploads from a network it has flagged after the first
            // 64 KB (tested 2026-11-22: home Wi-Fi blocked, the same upload from a clean network went through)
            if (state === 'unknown') return ['unknown', 'Not sent: the connection was cut before the upload reached the site, so nothing was saved or emailed. The hosting company does this to networks its bot check has flagged. Switch between Wi-Fi and mobile data, then send again.'];
            return ['working', 'The server is still saving the photos and emailing the customer...'];
        }

        $(document).on('click', '#commission-modal-send', function () {
            var $modal = $('#commission-email-modal');
            var $btn = $modal.data('btn');
            var itemId = $modal.data('itemId');
            var $fileInput = $modal.data('fileInput');
            var $status = $btn.siblings('.commission-upload-status');
            var $bar = $btn.siblings('.commission-upload-progress');
            var customMessage = $('#commission-custom-message').val().trim();
            var isFinal = $('#commission-final').is(':checked');
            var files = Array.prototype.slice.call($fileInput[0].files || []);
            var label = $btn.data('label') || $btn.text();
            $btn.data('label', label);

            $modal.fadeOut(200);
            if (cpuBusy || !files.length) return;

            var orderId = cpuOrderId();
            if (!orderId) { alert('Could not determine order ID'); return; }

            var prev = cpuLoad(orderId, itemId);
            var sig = cpuSig(files);
            var sendId = (prev && prev.state !== 'done' && prev.sig === sig) ? prev.id : cpuId();
            var rec = { id: sendId, t: Date.now(), state: 'sending', sig: sig, n: files.length };
            cpuSave(orderId, itemId, rec);

            cpuBusy = true;
            $('#commission-final').prop('checked', false);
            $btn.prop('disabled', true).text('Sending...');
            $(window).on('beforeunload.cpu', function () { return 'An update is still being sent.'; });
            $bar.prop('hidden', false).removeAttr('value');
            cpuShow($status, 'working', 'Preparing ' + cpuPlural(files.length, 'photo') + '...');

            function finish(state, data) {
                var out = cpuReport(state, data, files.length);
                cpuShow($status, out[0], out[1]);
                rec.state = state; rec.t = Date.now(); cpuSave(orderId, itemId, rec);
                cpuBusy = false;
                $(window).off('beforeunload.cpu');
                $btn.prop('disabled', false).text(state === 'unknown' || state === 'failed' ? 'Try again' : label);
                if (state === 'done') {
                    $bar.attr('value', 100);
                    $fileInput.val('');
                    setTimeout(function () { location.reload(); }, data && data.skipped && data.skipped.length ? 8000 : 2500);
                } else {
                    $bar.prop('hidden', true);
                }
            }

            // after a dropped or stalled upload: ask until the server gives a final answer (every 5 s, up to 3 minutes)
            function settle(tries) {
                cpuCheck(sendId, function (s) {
                    if (s && (s.state === 'done' || s.state === 'failed' || s.state === 'unknown')) return finish(s.state, s);
                    if (tries >= 36) {
                        cpuShow($status, 'unknown', s ? 'Still not finished after 3 minutes. Reload this page in a while: it will say whether the update went out. Do not send it again before then.'
                                                      : 'Can\'t reach the server to check. When you have a connection again, reload this page: it will say whether the update went out. Do not send it again before then.');
                        rec.state = 'checking'; cpuSave(orderId, itemId, rec);
                        cpuBusy = false; $(window).off('beforeunload.cpu'); $bar.prop('hidden', true); $btn.prop('disabled', false).text(label);
                        return;
                    }
                    cpuShow($status, 'working', s ? 'The server is still saving the photos and emailing the customer...' : 'No connection to the server. Checking again...');
                    setTimeout(function () { settle(tries + 1); }, 5000);
                });
            }

            Promise.all(files.map(cpuShrink)).then(function (ready) {
                var fd = new FormData();
                fd.append('action', 'ms_upload_commission_preview');
                fd.append('order_id', orderId);
                fd.append('item_id', itemId);
                fd.append('custom_message', customMessage);
                fd.append('final', isFinal ? '1' : '');
                fd.append('nonce', cpuNonce);
                fd.append('send_id', sendId);
                ready.forEach(function (f) { fd.append('preview_images[]', f, f.name); });
                var mb = ready.reduce(function (s, f) { return s + f.size; }, 0) / 1048576;
                var what = cpuPlural(ready.length, 'photo') + ' (' + (mb < 10 ? mb.toFixed(1) : Math.round(mb)) + ' MB)';

                var lastMove = Date.now(), sent = false;
                $bar.attr('value', 0);
                cpuShow($status, 'working', 'Uploading ' + what + '... 0%');
                var xhr = $.ajax({
                    url: ajaxurl, type: 'POST', data: fd, processData: false, contentType: false,
                    xhr: function () {
                        var x = new XMLHttpRequest();
                        x.upload.addEventListener('progress', function (e) {
                            lastMove = Date.now();
                            if (!e.lengthComputable) return;
                            var pct = Math.min(99, Math.round(e.loaded / e.total * 100));
                            $bar.attr('value', pct);
                            cpuShow($status, 'working', 'Uploading ' + what + '... ' + pct + '%');
                        });
                        x.upload.addEventListener('load', function () {
                            sent = true; lastMove = Date.now();
                            $bar.removeAttr('value');
                            cpuShow($status, 'working', 'Uploaded. Saving the photos and emailing the customer...');
                        });
                        return x;
                    }
                });
                var watchdog = setInterval(function () {
                    if (Date.now() - lastMove > (sent ? CPU_STALL_SERVER : CPU_STALL_UPLOAD)) { clearInterval(watchdog); xhr.abort(); }
                }, 2000);
                xhr.done(function (r) {
                    clearInterval(watchdog);
                    if (r && r.success) return finish('done', r.data);
                    if (r && r.data && r.data.working) return settle(0);
                    finish('failed', { message: r && r.data ? (r.data.message || r.data) : '' });
                }).fail(function () {
                    // stalled, dropped or a server error: never guess - ask the server what happened to this send
                    clearInterval(watchdog);
                    cpuShow($status, 'working', 'No answer from the server. Checking whether the update arrived...');
                    settle(0);
                });
            });
        });

        // after a reload: say what happened to the last send of each commission (kept for a day on this device)
        $('.commission-upload-btn').each(function () {
            var $btn = $(this), itemId = $btn.data('item-id'), orderId = cpuOrderId();
            var rec = orderId ? cpuLoad(orderId, itemId) : null;
            if (!rec || Date.now() - rec.t > 86400000) return;
            var $status = $btn.siblings('.commission-upload-status');
            if (rec.state === 'done') return cpuShow($status, 'done', 'Last update sent at ' + cpuTime(rec.t) + '.');
            cpuShow($status, 'working', 'Checking your last update (' + cpuTime(rec.t) + ')...');
            cpuCheck(rec.id, function (s) {
                if (!s) return cpuShow($status, 'unknown', 'Could not check your last update (' + cpuTime(rec.t) + '): no connection. Reload when you are online.');
                var out = cpuReport(s.state, s, rec.n);
                cpuShow($status, out[0], 'Your last update (' + cpuTime(rec.t) + '): ' + out[1].charAt(0).toLowerCase() + out[1].slice(1));
                if (s.state === 'done' || s.state === 'failed' || s.state === 'unknown') { rec.state = s.state; cpuSave(orderId, itemId, rec); }
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

    // Each send carries an id made in the browser (2026-11-22, after an update hung on "Uploading..." on an iPhone and
    // nobody could tell whether it went out): the outcome is kept for a day so the screen can ask what happened
    // (ms_commission_upload_check), and a send that already went out is never emailed twice when it is retried.
    $send_id = ms_commission_send_id($_POST['send_id'] ?? '');
    if ($send_id !== '') {
        $prev = get_transient('ms_cpu_' . $send_id);
        if (is_array($prev) && ($prev['state'] ?? '') === 'done') {
            wp_send_json_success(['count' => (int) $prev['count'], 'skipped' => $prev['skipped'] ?? [], 'again' => true,
                'message' => 'This update was already sent']);
        }
        if (is_array($prev) && ($prev['state'] ?? '') === 'working' && time() - (int) ($prev['t'] ?? 0) < 600) {
            wp_send_json_error(['working' => true, 'message' => 'This update is still being saved and sent.']);
        }
        set_transient('ms_cpu_' . $send_id, ['state' => 'working', 't' => time(), 'order' => $order_id, 'item' => $item_id], DAY_IN_SECONDS);
    }
    // finish the job even when the phone drops the connection, and give HEIC conversions time
    ignore_user_abort(true);
    @set_time_limit(300);
    $fail = function ($message) use ($send_id, $order_id, $item_id) {
        if ($send_id !== '') set_transient('ms_cpu_' . $send_id, ['state' => 'failed', 't' => time(), 'message' => $message, 'order' => $order_id, 'item' => $item_id], DAY_IN_SECONDS);
        wp_send_json_error($message);
    };

    $custom_message = isset($_POST['custom_message']) ? sanitize_textarea_field(wp_unslash($_POST['custom_message'])) : '';
    $is_final = !empty($_POST['final']);
    $email_message = $custom_message;   // the email carries its own Approve block when final (owner, 2026-11-23)
    if ($is_final) {
        // the conversation keeps the request in words
        $custom_message = trim($custom_message . "\n\nThis is the finished artwork. Please approve it with the Approve final artwork button on your commission page: a reply or comment does not count as approval.");
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
        $fail('Maximum ' . MS_PREVIEW_MAX_FILES . ' files allowed');
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
        $fail('Nothing was uploaded. ' . implode(' ', $skipped));
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
    ms_send_commission_preview_email($order, $item, $uploaded_urls, $email_message, $is_final);

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

    if ($send_id !== '') {
        set_transient('ms_cpu_' . $send_id, ['state' => 'done', 't' => time(), 'count' => count($uploaded_urls), 'skipped' => $skipped,
            'order' => $order_id, 'item' => $item_id], DAY_IN_SECONDS);
    }

    wp_send_json_success([
        'count' => count($uploaded_urls),
        'skipped' => $skipped,
        'message' => 'Successfully uploaded and sent email'
    ]);
});

/** A send id from the browser: lowercase letters and digits only, 16-40 long, else '' */
function ms_commission_send_id($raw) {
    $id = preg_replace('/[^a-z0-9]/', '', strtolower((string) $raw));
    return (strlen($id) >= 16 && strlen($id) <= 40) ? $id : '';
}

/**
 * What happened to a send (2026-11-22): 'unknown' = it never reached the server (nothing saved, nothing emailed),
 * 'working' = being saved and emailed now, 'done' = saved and emailed, 'failed' = refused (message says why).
 */
add_action('wp_ajax_ms_commission_upload_check', function () {
    check_ajax_referer('ms_commission_preview_upload', 'nonce');
    if (!current_user_can('edit_shop_orders')) {
        wp_send_json_error('Insufficient permissions');
    }
    $send_id = ms_commission_send_id($_POST['send_id'] ?? '');
    if ($send_id === '') {
        wp_send_json_error('Missing send id');
    }
    $state = get_transient('ms_cpu_' . $send_id);
    wp_send_json_success(is_array($state) ? $state : ['state' => 'unknown']);
});

/**
 * The preview email now lives in commissions-emails.php (a WooCommerce email class, via ms_send_commission_preview_email()).
 */

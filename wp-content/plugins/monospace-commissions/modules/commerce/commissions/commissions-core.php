<?php
/**
 * Custom Commission Orders System
 * Consolidated core functionality with improved security, performance, and maintainability
 *
 * @package astra-child-theme-for-monospace-art
 * @version 2.0.0
 */

if (!defined('ABSPATH')) exit;

// Constants
define('MS_COMMISSION_VERSION', '2.0.0');
define('MS_COMMISSION_META_PREFIX', '_ms_commission_');
define('MS_COMMISSION_UPLOAD_DIR', 'commissions');
define('MS_COMMISSION_MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('MS_COMMISSION_MAX_FILES', 5);
define('MS_COMMISSION_ADMIN_EMAIL', 'hens@monospace.com');

/**
 * ===========================
 * ACTIVATION & SETUP
 * ===========================
 */
register_activation_hook(__FILE__, 'ms_commission_activate');
function ms_commission_activate() {
    // Create upload directory
    $upload_dir = wp_upload_dir();
    $custom_dir = trailingslashit($upload_dir['basedir']) . MS_COMMISSION_UPLOAD_DIR;
    if (!file_exists($custom_dir)) {
        wp_mkdir_p($custom_dir);
        // Security: prevent direct access
        file_put_contents($custom_dir . '/.htaccess', 'Options -Indexes');
        file_put_contents($custom_dir . '/index.php', '<?php // Silence is golden');
    }

    // Migrate old meta keys
    ms_commission_migrate_meta_keys();

    // Flush rewrite rules once
    flush_rewrite_rules();
    update_option('ms_commission_activated', MS_COMMISSION_VERSION);
}

/**
 * Migrate old meta key formats to new standard
 */
function ms_commission_migrate_meta_keys() {
    global $wpdb;

    $old_keys = [
        'unique_commission_key' => MS_COMMISSION_META_PREFIX . 'key',
        'Unique Commission Key' => MS_COMMISSION_META_PREFIX . 'key',
        'commission_full_price' => MS_COMMISSION_META_PREFIX . 'full_price',
        'Commission Full Price' => MS_COMMISSION_META_PREFIX . 'full_price',
        'applied_deposit_price' => MS_COMMISSION_META_PREFIX . 'deposit_paid',
        'Applied Deposit Price' => MS_COMMISSION_META_PREFIX . 'deposit_paid',
        'commission_special_request' => MS_COMMISSION_META_PREFIX . 'special_request',
        'commission_medium' => MS_COMMISSION_META_PREFIX . 'medium',
        'commission_size' => MS_COMMISSION_META_PREFIX . 'size',
        'commission_surface' => MS_COMMISSION_META_PREFIX . 'surface',
        'commission_reference_upload' => MS_COMMISSION_META_PREFIX . 'reference_images',
    ];

    foreach ($old_keys as $old => $new) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}woocommerce_order_itemmeta
             SET meta_key = %s
             WHERE meta_key = %s
             AND meta_key != %s",
            $new, $old, $new
        ));
    }
}

/**
 * ===========================
 * HELPER FUNCTIONS
 * ===========================
 */

/**
 * Get commission meta with fallback to old keys
 */
function ms_get_commission_meta($item, $key, $single = true) {
    $new_key = MS_COMMISSION_META_PREFIX . $key;
    $value = $item->get_meta($new_key, $single);

    // Fallback to old formats if empty
    if (empty($value)) {
        $old_keys = [
            'key' => ['unique_commission_key', 'Unique Commission Key'],
            'full_price' => ['commission_full_price', 'Commission Full Price'],
            'deposit_paid' => ['applied_deposit_price', 'Applied Deposit Price'],
            'special_request' => ['commission_special_request'],
            'medium' => ['commission_medium'],
            'size' => ['commission_size'],
            'surface' => ['commission_surface'],
            'reference_images' => ['commission_reference_upload'],
        ];

        if (isset($old_keys[$key])) {
            foreach ($old_keys[$key] as $old_key) {
                $value = $item->get_meta($old_key, $single);
                if (!empty($value)) {
                    // Migrate to new format
                    $item->update_meta_data($new_key, $value);
                    $item->save();
                    break;
                }
            }
        }
    }

    return $value;
}

/**
 * Set commission meta (always uses new format)
 */
function ms_set_commission_meta($item, $key, $value) {
    $item->update_meta_data(MS_COMMISSION_META_PREFIX . $key, $value);
}

/**
 * Normalize image URLs to array
 */
function ms_normalize_image_array($images) {
    if (empty($images)) return [];
    if (is_array($images)) return array_filter($images);

    $maybe_json = json_decode($images, true);
    if (is_array($maybe_json)) return array_filter($maybe_json);

    return array_filter(array_map('trim', explode(',', $images)));
}

/**
 * Validate uploaded file
 */
function ms_validate_upload_file($file) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // Check size
    if ($file['size'] > MS_COMMISSION_MAX_FILE_SIZE) {
        return new WP_Error('file_too_large', sprintf('File exceeds maximum size of %dMB', MS_COMMISSION_MAX_FILE_SIZE / 1024 / 1024));
    }

    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types)) {
        return new WP_Error('invalid_type', 'Only image files (JPEG, PNG, GIF, WebP) are allowed');
    }

    // Check extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_exts)) {
        return new WP_Error('invalid_extension', 'Invalid file extension');
    }

    return true;
}

/**
 * Log commission error
 */
function ms_commission_log($message, $data = []) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf('[MS Commission] %s | Data: %s', $message, print_r($data, true)));
    }
}

/**
 * ===========================
 * CONFIGURATION
 * ===========================
 */
function ms_get_commission_options() {
    return apply_filters('ms_commission_options', [
        'mediums' => [
            'acrylic' => 'Acrylic',
            'casein' => 'Casein',
            'gouache' => 'Gouache',
            'watercolor' => 'Watercolor',
            'oil' => 'Oil',
            'pen_ink' => 'Pen & Ink',
            'line_wash' => 'Line & Wash',
            'other' => 'Other',
        ],
        'surfaces' => [
            'paper' => 'Paper',
            'board' => 'Illustration Board',
            'MDF' => 'MDF Board',
            'canvas' => 'Canvas',
            'canvas_panel' => 'Canvas Panel',
            'other' => 'Other',
        ],
        'sizes' => [
            '4x4' => '4 × 4 inches',
            '7x5' => '7 × 5 inches',
            '10x8' => '10 × 8 inches',
            '12x9' => '12 × 9 inches',
            '14x11' => '14 × 11 inches',
            'other' => 'Other',
        ],
        'special_order_types' => [
            'painting' => 'Painting',
            'sketch' => 'Sketch / Drawing',
        ]
    ]);
}

/**
 * ===========================
 * ADMIN: PRODUCT META BOX
 * ===========================
 */
add_action('add_meta_boxes', function() {
    add_meta_box(
        'ms_commission_product_meta',
        'Custom Order Settings',
        'ms_render_commission_product_meta_box',
        'product',
        'side',
        'default'
    );
});

function ms_render_commission_product_meta_box($post) {
    wp_nonce_field('ms_save_commission_product_meta', 'ms_commission_product_nonce');

    $is_commission = get_post_meta($post->ID, '_is_commission_product', true) === 'yes';
    $deposit_percent = get_post_meta($post->ID, '_commission_deposit_percent', true) ?: 30;
    $special_type = get_post_meta($post->ID, '_special_order_type', true) ?: 'painting';
    $allowed_mediums = get_post_meta($post->ID, '_commission_allowed_mediums', true) ?: [];
    $allowed_sizes = get_post_meta($post->ID, '_commission_allowed_sizes', true) ?: [];
    $allowed_surfaces = get_post_meta($post->ID, '_commission_allowed_surfaces', true) ?: [];
    $options = ms_get_commission_options();

    echo '<p><label><input type="checkbox" name="is_commission_product" value="yes" ' . checked($is_commission, true, false) . ' /> This is a Custom Artwork order</label></p>';

    echo '<p><label for="commission_deposit_percent">Deposit percent (%)</label><br/>';
    echo '<input type="number" name="commission_deposit_percent" id="commission_deposit_percent" value="' . esc_attr($deposit_percent) . '" min="0" max="100" style="width:100%;" />';
    echo '<small class="description">Percent of artwork price charged as deposit (e.g. 30).</small></p>';

    echo '<p><label for="special_order_type">Special Order Type</label><br/>';
    echo '<select name="special_order_type" id="special_order_type" style="width:100%;">';
    foreach($options['special_order_types'] as $key => $label) {
        echo '<option value="' . esc_attr($key) . '" ' . selected($special_type, $key, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';

    echo '<p><strong>Allowed Mediums</strong><br/>';
    foreach($options['mediums'] as $key => $label) {
        echo '<label><input type="checkbox" name="commission_allowed_mediums[]" value="' . esc_attr($key) . '" ' . checked(in_array($key, $allowed_mediums), true, false) . ' /> ' . esc_html($label) . '</label><br/>';
    }
    echo '</p>';

    echo '<p><strong>Allowed Sizes</strong><br/>';
    foreach($options['sizes'] as $key => $label) {
        echo '<label><input type="checkbox" name="commission_allowed_sizes[]" value="' . esc_attr($key) . '" ' . checked(in_array($key, $allowed_sizes), true, false) . ' /> ' . esc_html($label) . '</label><br/>';
    }
    echo '</p>';

    echo '<p><strong>Allowed Surfaces</strong><br/>';
    foreach($options['surfaces'] as $key => $label) {
        echo '<label><input type="checkbox" name="commission_allowed_surfaces[]" value="' . esc_attr($key) . '" ' . checked(in_array($key, $allowed_surfaces), true, false) . ' /> ' . esc_html($label) . '</label><br/>';
    }
    echo '</p>';
}

add_action('save_post_product', function($post_id) {
    if (!isset($_POST['ms_commission_product_nonce'])) return;
    if (!wp_verify_nonce($_POST['ms_commission_product_nonce'], 'ms_save_commission_product_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    update_post_meta($post_id, '_is_commission_product', isset($_POST['is_commission_product']) && $_POST['is_commission_product'] === 'yes' ? 'yes' : '');
    update_post_meta($post_id, '_commission_deposit_percent', max(0, min(100, floatval($_POST['commission_deposit_percent'] ?? 30))));
    update_post_meta($post_id, '_special_order_type', sanitize_text_field($_POST['special_order_type'] ?? 'painting'));
    update_post_meta($post_id, '_commission_allowed_mediums', array_map('sanitize_text_field', $_POST['commission_allowed_mediums'] ?? []));
    update_post_meta($post_id, '_commission_allowed_sizes', array_map('sanitize_text_field', $_POST['commission_allowed_sizes'] ?? []));
    update_post_meta($post_id, '_commission_allowed_surfaces', array_map('sanitize_text_field', $_POST['commission_allowed_surfaces'] ?? []));
}, 10);

/**
 * ===========================
 * FRONTEND: PRODUCT PAGE
 * ===========================
 */
add_action('woocommerce_before_add_to_cart_button', function() {
    global $product;
    if (!$product) return;

    $product_id = $product->get_id();
    if (get_post_meta($product_id, '_is_commission_product', true) !== 'yes') return;

    $options = ms_get_commission_options();
    $allowed_mediums = get_post_meta($product_id, '_commission_allowed_mediums', true) ?: [];
    $allowed_sizes = get_post_meta($product_id, '_commission_allowed_sizes', true) ?: [];
    $allowed_surfaces = get_post_meta($product_id, '_commission_allowed_surfaces', true) ?: [];
    $deposit_percent = get_post_meta($product_id, '_commission_deposit_percent', true) ?: 30;
    $full_price = floatval($product->get_price());
    $deposit_price = ($deposit_percent / 100) * $full_price;

    echo '<style>.custom-order-options p {margin: 0;}</style>';
    echo '<div class="custom-order-options">';

    echo '<p class="commission-full-price" style="margin-bottom: 0em !important; font-size: 1.1em; font-weight: 600;">';
    echo '<strong>Total Price:</strong> ' . wc_price($full_price);
    echo '</p>';

    echo '<p class="commission-deposit-info" style="margin-bottom: 2em !important; font-size: 0.9em; color: #444;">';
    echo '<strong>Upfront payment:</strong> ' . wc_price($deposit_price) . ' (' . intval($deposit_percent) . '%)';
    echo '</p>';

    echo '<p"><strong>Medium:</strong> ' . (!empty($allowed_mediums) ? implode(', ', array_map(fn($k) => $options['mediums'][$k] ?? $k, $allowed_mediums)) : 'N/A') . '</p>';
    echo '<p"><strong>Surface:</strong> ' . (!empty($allowed_surfaces) ? implode(', ', array_map(fn($k) => $options['surfaces'][$k] ?? $k, $allowed_surfaces)) : 'N/A') . '</p>';
    echo '<p style="margin-bottom: 1em !important"><strong>Size:</strong> ' . (!empty($allowed_sizes) ? implode(', ', array_map(fn($k) => $options['sizes'][$k] ?? $k, $allowed_sizes)) : 'N/A') . '</p>';

    echo '<p><label>Notes/Special Requests (optional)<br/>
    <textarea name="commission_special_request" rows="3" style="width:100%" placeholder="Describe the scene: season, time of day, features to include or avoid, preferred colors or atmosphere."></textarea>
    </label></p>';

    echo '<p style="margin-bottom: 1em !important"><label>Reference Image(s) <span style="color:red">(required)</span><br/>';
    echo '<input type="file" name="commission_reference_upload[]" accept="image/*" multiple required />';
    echo '<small class="description" style="display:block;margin-top:5px;">Max ' . MS_COMMISSION_MAX_FILES . ' images, ' . (MS_COMMISSION_MAX_FILE_SIZE / 1024 / 1024) . 'MB each</small>';
    echo '</label></p>';

    echo '</div>';
});

/**
 * ===========================
 * CART: ADD COMMISSION DATA
 * ===========================
 */
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id) {
    if (get_post_meta($product_id, '_is_commission_product', true) !== 'yes') {
        return $cart_item_data;
    }

    // Validate file upload
    if (empty($_FILES['commission_reference_upload']['name'][0])) {
        wc_add_notice('Reference images are required for commission orders.', 'error');
        return $cart_item_data;
    }

    $files = $_FILES['commission_reference_upload'];
    $file_count = count(array_filter($files['name']));

    if ($file_count > MS_COMMISSION_MAX_FILES) {
        wc_add_notice(sprintf('Maximum %d reference images allowed.', MS_COMMISSION_MAX_FILES), 'error');
        return $cart_item_data;
    }

    $options = ms_get_commission_options();
    $allowed_mediums = get_post_meta($product_id, '_commission_allowed_mediums', true) ?: [];
    $allowed_sizes = get_post_meta($product_id, '_commission_allowed_sizes', true) ?: [];
    $allowed_surfaces = get_post_meta($product_id, '_commission_allowed_surfaces', true) ?: [];

    $full_price = floatval(wc_get_price_to_display(wc_get_product($product_id)));
    $deposit_percent = get_post_meta($product_id, '_commission_deposit_percent', true) ?: 30;
    $deposit_price = round(($deposit_percent / 100) * $full_price, wc_get_price_decimals());

    $data = [
        'medium' => !empty($allowed_mediums) ? implode(', ', array_map(fn($k) => $options['mediums'][$k] ?? $k, $allowed_mediums)) : '',
        'size' => !empty($allowed_sizes) ? implode(', ', array_map(fn($k) => $options['sizes'][$k] ?? $k, $allowed_sizes)) : '',
        'surface' => !empty($allowed_surfaces) ? implode(', ', array_map(fn($k) => $options['surfaces'][$k] ?? $k, $allowed_surfaces)) : '',
        'special_request' => sanitize_textarea_field($_REQUEST['commission_special_request'] ?? ''),
        'full_price' => $full_price,
        'deposit_paid' => $deposit_price,
        'key' => uniqid('commission_', true),
    ];

    // Handle file uploads with validation
    $uploaded_urls = [];
    $upload_dir = wp_upload_dir();
    $base_dir = trailingslashit($upload_dir['basedir']) . MS_COMMISSION_UPLOAD_DIR;
    $base_url = trailingslashit($upload_dir['baseurl']) . MS_COMMISSION_UPLOAD_DIR;

    // We don't have order ID yet (cart stage), so use temp commission key
    $temp_key = uniqid('temp_', true);

    // Create order folder structure: /commissions/temp-{key}/references/
    $order_dir = trailingslashit($base_dir) . 'temp-' . $temp_key;
    $references_dir = trailingslashit($order_dir) . 'references';
    $references_url = trailingslashit($base_url) . 'temp-' . $temp_key . '/references';

    if (!file_exists($references_dir)) {
        wp_mkdir_p($references_dir);
        file_put_contents($order_dir . '/.htaccess', 'Options -Indexes');
        file_put_contents($order_dir . '/index.php', '<?php // Silence is golden');
    }

    foreach ($files['name'] as $i => $name) {
        if (!$name) continue;

        $file_array = [
            'name' => $files['name'][$i],
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i]
        ];

        // Validate
        $validation = ms_validate_upload_file($file_array);
        if (is_wp_error($validation)) {
            wc_add_notice($validation->get_error_message(), 'error');
            continue;
        }

        // Sanitize filename with timestamp
        $timestamp = current_time('Y-m-d_H-i-s');
        $filename = $timestamp . '_' . sanitize_file_name($name);
        $filename = wp_unique_filename($references_dir, $filename);

        if (move_uploaded_file($file_array['tmp_name'], $references_dir . '/' . $filename)) {
            $uploaded_urls[] = $references_url . '/' . $filename;
        } else {
            ms_commission_log('File upload failed', ['file' => $filename]);
        }
    }

    if (empty($uploaded_urls)) {
        wc_add_notice('Failed to upload reference images. Please try again.', 'error');
        return $cart_item_data;
    }

    $data['reference_images'] = $uploaded_urls;
    $cart_item_data['commission_data'] = $data;

    return $cart_item_data;
}, 20, 3);

/**
 * ===========================
 * CART: RESTORE FROM SESSION
 * ===========================
 */
add_filter('woocommerce_get_cart_item_from_session', function($cart_item, $values) {
    if (isset($values['commission_data'])) {
        $cart_item['commission_data'] = $values['commission_data'];
    }
    return $cart_item;
}, 20, 2);

/**
 * ===========================
 * CART: DISPLAY DATA
 * ===========================
 */
add_filter('woocommerce_get_item_data', function($item_data, $cart_item) {
    if (!isset($cart_item['commission_data'])) return $item_data;

    $data = $cart_item['commission_data'];
    $display = [
        'Medium' => $data['medium'],
        'Size' => $data['size'],
        'Surface' => $data['surface'],
        'Special Requests' => $data['special_request'],
        'Reference Images' => count($data['reference_images']) . ' image(s)',
    ];

    foreach ($display as $key => $value) {
        if (!empty($value)) {
            $item_data[] = ['key' => $key, 'value' => $value];
        }
    }

    return $item_data;
}, 10, 2);

/**
 * ===========================
 * CHECKOUT: SAVE TO ORDER
 * ===========================
 */
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order) {
    if (!isset($values['commission_data'])) return;

    $data = $values['commission_data'];

    // Save all commission data with new meta format
    foreach ($data as $key => $value) {
        if ($key === 'reference_images') {
            $value = is_array($value) ? $value : [$value];
        }
        ms_set_commission_meta($item, $key, $value);
    }

    // Calculate final payment
    $final_payment = max(0, $data['full_price'] - $data['deposit_paid']);
    ms_set_commission_meta($item, 'final_payment', $final_payment);

}, 10, 4);

/**
 * Move temp reference images to proper order folder after order is created
 */
add_action('woocommerce_checkout_order_created', function($order) {
    $upload_dir = wp_upload_dir();
    $base_dir = trailingslashit($upload_dir['basedir']) . MS_COMMISSION_UPLOAD_DIR;
    $base_url = trailingslashit($upload_dir['baseurl']) . MS_COMMISSION_UPLOAD_DIR;

    foreach ($order->get_items() as $item) {
        $reference_images = ms_get_commission_meta($item, 'reference_images');
        if (empty($reference_images)) continue;

        $reference_images = is_array($reference_images) ? $reference_images : [$reference_images];

        // Check if images are in temp folder
        $first_image = $reference_images[0];
        if (strpos($first_image, '/temp-') === false) continue;

        // Extract temp key from URL
        preg_match('/temp-([^\/]+)/', $first_image, $matches);
        if (empty($matches[1])) continue;

        $temp_key = $matches[1];
        $temp_dir = $base_dir . 'temp-' . $temp_key;
        $order_dir = $base_dir . 'order-' . $order->get_id();

        // Move temp folder to order folder
        if (file_exists($temp_dir) && !file_exists($order_dir)) {
            rename($temp_dir, $order_dir);

            // Update image URLs in meta
            $new_images = [];
            foreach ($reference_images as $url) {
                $new_url = str_replace('/temp-' . $temp_key . '/', '/order-' . $order->get_id() . '/', $url);
                $new_images[] = $new_url;
            }

            ms_set_commission_meta($item, 'reference_images', $new_images);
            $item->save();

            ms_commission_log('Moved temp folder to order folder', [
                'order_id' => $order->get_id(),
                'temp_key' => $temp_key
            ]);
        }
    }
}, 10);


/**
 * ===========================
 * ADMIN: HIDE INTERNAL META
 * ===========================
 */
add_filter('woocommerce_hidden_order_itemmeta', function($hidden) {
    $internal = [
        MS_COMMISSION_META_PREFIX . 'key',
        MS_COMMISSION_META_PREFIX . 'medium',
        MS_COMMISSION_META_PREFIX . 'size',
        MS_COMMISSION_META_PREFIX . 'surface',
        MS_COMMISSION_META_PREFIX . 'special_request',
        MS_COMMISSION_META_PREFIX . 'reference_images',
        MS_COMMISSION_META_PREFIX . 'deposit_paid',
        MS_COMMISSION_META_PREFIX . 'full_price',
        MS_COMMISSION_META_PREFIX . 'final_payment',
        MS_COMMISSION_META_PREFIX . 'final_payment_created',
        MS_COMMISSION_META_PREFIX . 'final_payment_order_id',
        MS_COMMISSION_META_PREFIX . 'is_final_payment',
        MS_COMMISSION_META_PREFIX . 'original_order_id',
        MS_COMMISSION_META_PREFIX . 'original_item_id',
        MS_COMMISSION_META_PREFIX . 'preview_images',
        MS_COMMISSION_META_PREFIX . 'preview_new',
    ];

    return array_merge($hidden, $internal);
});

/**
 * ===========================
 * ADMIN: DISPLAY ORDER DETAILS
 * ===========================
 */
add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    foreach ($order->get_items() as $item) {
        if (!ms_get_commission_meta($item, 'key')) continue;

        echo '<div style="margin-top:20px; padding:15px; background:#f7f7f7; border-left:4px solid #0073aa;">';
        echo '<h4 style="margin-top:0;">Custom Order Details: ' . esc_html($item->get_name()) . '</h4>';

        $options = ms_get_commission_options();
        $display_map = [
            'medium' => 'Medium',
            'size' => 'Size',
            'surface' => 'Surface',
            'special_request' => 'Special Requests',
            'reference_images' => 'Reference Images',
            'deposit_paid' => 'Deposit Paid',
            'final_payment' => 'Final Payment Due'
        ];

        echo '<table class="commission-details" style="width:100%;">';
        foreach ($display_map as $meta_key => $label) {
            $value = ms_get_commission_meta($item, $meta_key);
            if (empty($value)) continue;

            if ($meta_key === 'reference_images') {
                $images = ms_normalize_image_array($value);
                $links = [];
                foreach ($images as $url) {
                    $links[] = '<a href="' . esc_url($url) . '" target="_blank">View Image</a>';
                }
                $value = implode(' | ', $links);
            } elseif (in_array($meta_key, ['deposit_paid', 'final_payment'])) {
                $value = wc_price($value);
            } else {
                $value = esc_html($value);
            }

            echo '<tr><td style="padding:5px 10px 5px 0; font-weight:600;">' . esc_html($label) . ':</td><td style="padding:5px 0;">' . $value . '</td></tr>';
        }
        echo '</table>';
        echo '</div>';
    }
}, 10);

/**
 * ===========================
 * CART: DEPOSIT REDUCTION
 * ===========================
 */
add_action('woocommerce_cart_calculate_fees', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    $deposit_total = 0;

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['commission_data'])) {
            $full_price = floatval($cart_item['commission_data']['full_price']);
            $deposit_price = floatval($cart_item['commission_data']['deposit_paid']);
            $deposit_total += $full_price - $deposit_price;
        }
    }

    if ($deposit_total > 0) {
        $cart->add_fee('Balance Due Later', -$deposit_total, false);
    }
});

/**
 * ===========================
 * CHECKOUT: REQUIRE LOGIN
 * ===========================
 */
add_action('woocommerce_before_checkout_form', function() {
    if (!is_user_logged_in()) {
        foreach (WC()->cart->get_cart() as $cart_item) {
            if (isset($cart_item['commission_data'])) {
                wc_add_notice('You must be logged in to order custom commissions.', 'error');
                wp_safe_redirect(wc_get_page_permalink('myaccount'));
                exit;
            }
        }
    }
});

/**
 * ===========================
 * REDIRECT AFTER ADD TO CART
 * ===========================
 */
add_filter('woocommerce_add_to_cart_redirect', function($url) {
    $product_id = isset($_REQUEST['add-to-cart']) ? absint($_REQUEST['add-to-cart']) : 0;
    if ($product_id && get_post_meta($product_id, '_is_commission_product', true) === 'yes') {
        return wc_get_cart_url();
    }
    return $url;
});

/**
 * ===========================
 * SHIPPING: EXCLUDE COMMISSIONS UNTIL FINAL PAYMENT
 * ===========================
 */
add_filter('woocommerce_cart_shipping_packages', function($packages) {
    $cart = WC()->cart;
    if (!$cart) return $packages;

    $cart_items = $cart->get_cart();
    $has_commission = false;
    $has_non_commission = false;

    foreach ($cart_items as $item) {
        $product_id = $item['product_id'];
        $is_commission = get_post_meta($product_id, '_is_commission_product', true) === 'yes';

        if ($is_commission) {
            $has_commission = true;
        } else {
            $has_non_commission = true;
        }
    }

    // Only commission items - no shipping
    if ($has_commission && !$has_non_commission) {
        return [];
    }

    // Mixed cart - only ship non-commission items
    if ($has_commission && $has_non_commission) {
        foreach ($packages as &$package) {
            $new_contents = [];
            foreach ($package['contents'] as $key => $item) {
                $pid = $item['product_id'];
                if (get_post_meta($pid, '_is_commission_product', true) !== 'yes') {
                    $new_contents[$key] = $item;
                }
            }
            $package['contents'] = $new_contents;
        }
    }

    return $packages;
}, 100);

/**
 * Shipping notice
 */
add_action('woocommerce_before_checkout_form', function() {
    static $shown = false;
    if ($shown) return;
    $shown = true;

    $has_commission = false;
    foreach (WC()->cart->get_cart() as $item) {
        if (get_post_meta($item['product_id'], '_is_commission_product', true) === 'yes') {
            $has_commission = true;
            break;
        }
    }

    if ($has_commission) {
        echo '<div class="woocommerce-info commission-shipping-note" style="margin:1em 0; font-size:0.95em;">';
        echo 'Shipping for commissioned artwork will be billed with the final payment.';
        echo '</div>';
    }
}, 20);

/**
 * ===========================
 * FINAL PAYMENT SYSTEM
 * ===========================
 */

/**
 * Admin button to create final payment
 */
add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    $commission_items = [];

    foreach ($order->get_items() as $item_id => $item) {
        $commission_key = ms_get_commission_meta($item, 'key');
        if (!$commission_key) continue;

        $final_payment_id = ms_get_commission_meta($item, 'final_payment_order_id');
        $needs_final_payment = true;

        if ($final_payment_id) {
            $final_order = wc_get_order($final_payment_id);
            if ($final_order && !$final_order->has_status('cancelled')) {
                $needs_final_payment = false;
            } else {
                ms_set_commission_meta($item, 'final_payment_created', '');
                ms_set_commission_meta($item, 'final_payment_order_id', '');
                $item->save();
            }
        }

        if ($needs_final_payment) {
            $commission_items[] = [
                'item_id' => $item_id,
                'item' => $item,
                'commission_key' => $commission_key,
                'product_name' => $item->get_name()
            ];
        }
    }

    if (empty($commission_items)) return;

    $item_ids = array_column($commission_items, 'item_id');
    $item_ids_json = json_encode($item_ids);
    ?>
    <br class="clear" />
    <div style="margin: 20px 0; padding: 20px; border: 1px solid #c3c4c7; background: #f9f9f9;">
        <h3 style="margin: 0 0 15px 0; font-size: 14px;">💰 Commission Final Payment</h3>
        <p style="margin: 0 0 15px 0;">When the artwork is complete, create a final payment invoice for the customer.</p>

        <div style="margin-bottom: 15px; padding: 12px; background: #fff; border: 1px solid #ddd;">
            <div style="margin-bottom: 12px; font-weight: 600;">Items ready for final payment:</div>
            <?php foreach($commission_items as $comm): ?>
            <div style="margin-bottom: 6px; padding-left: 12px;">• <?php echo esc_html($comm['product_name']); ?></div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="button button-primary ms-create-final-payment-btn"
                data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                data-item-ids='<?php echo esc_attr($item_ids_json); ?>'>
            Create Final Payment Invoice (<?php echo count($commission_items); ?> item<?php echo count($commission_items) > 1 ? 's' : ''; ?>)
        </button>
    </div>

    <script>
    jQuery(function($){
        $('.ms-create-final-payment-btn').on('click', function(e){
            e.preventDefault();
            var $btn = $(this);
            var orderId = $btn.data('order-id');
            var itemIds = $btn.data('item-ids');

            $btn.prop('disabled', true).html('⏳ Calculating...');

            $.post(ajaxurl, {
                action: 'ms_preview_final_payment',
                order_id: orderId,
                item_ids: JSON.stringify(itemIds),
                nonce: '<?php echo wp_create_nonce('ms_final_payment'); ?>'
            }, function(response){
                if(response.success){
                    var data = response.data;
                    var message = '⚠️ CONFIRM FINAL PAYMENT\n\n';
                    message += '─────────────────────────\n';

                    data.items.forEach(function(item){
                        message += item.product_name + '\n';
                        message += '  Full Price: $' + item.full_price.toFixed(2) + '\n';
                        message += '  Deposit Paid: $' + item.deposit_paid.toFixed(2) + '\n';
                        message += '  Balance Due: $' + item.remaining_balance.toFixed(2) + '\n';
                        message += '─────────────────────────\n';
                    });

                    message += 'TOTAL BALANCE: $' + data.total_balance.toFixed(2) + '\n';
                    message += 'Shipping: $' + data.shipping_total.toFixed(2) + '\n';
                    message += '─────────────────────────\n';
                    message += 'TOTAL DUE: $' + data.final_total.toFixed(2) + '\n\n';
                    message += 'Create invoice? (You can send it manually after review)';

                    if(confirm(message)){
                        $btn.html('⏳ Creating order...');

                        $.post(ajaxurl, {
                            action: 'ms_create_final_payment_order',
                            order_id: orderId,
                            item_ids: JSON.stringify(itemIds),
                            confirmed: 'yes',
                            nonce: '<?php echo wp_create_nonce('ms_final_payment'); ?>'
                        }, function(createResponse){
                            if(createResponse.success){
                                alert('✅ Final payment order created!\n\nOrder #' + createResponse.data.order_id + '\n\nReview before sending invoice.');
                                window.location.href = '<?php echo admin_url("post.php?post="); ?>' + createResponse.data.order_id + '&action=edit';
                            } else {
                                alert('❌ Error: ' + createResponse.data.message);
                                $btn.prop('disabled', false).html('Create Final Payment Invoice');
                            }
                        });
                    } else {
                        $btn.prop('disabled', false).html('Create Final Payment Invoice');
                    }
                } else {
                    alert('❌ Error: ' + response.data.message);
                    $btn.prop('disabled', false).html('Create Final Payment Invoice');
                }
            });
        });
    });
    </script>
    <?php
}, 10);

/**
 * AJAX: Preview final payment
 */
add_action('wp_ajax_ms_preview_final_payment', function() {
    check_ajax_referer('ms_final_payment', 'nonce');

    if (!current_user_can('edit_shop_orders')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $original_order_id = intval($_POST['order_id']);
    $item_ids = json_decode(stripslashes($_POST['item_ids']), true);

    if (empty($item_ids)) {
        wp_send_json_error(['message' => 'No items specified']);
    }

    $original_order = wc_get_order($original_order_id);
    if (!$original_order) {
        wp_send_json_error(['message' => 'Invalid order']);
    }

    $items_data = [];
    $total_balance = 0;

    foreach ($item_ids as $item_id) {
        $item = $original_order->get_item($item_id);
        if (!$item) continue;

        $full_price = floatval(ms_get_commission_meta($item, 'full_price'));
        $deposit_paid = floatval(ms_get_commission_meta($item, 'deposit_paid'));

        if ($full_price <= 0 || $deposit_paid <= 0) continue;

        $remaining_balance = $full_price - $deposit_paid;
        $total_balance += $remaining_balance;

        $items_data[] = [
            'item_id' => $item_id,
            'product_name' => $item->get_name(),
            'full_price' => $full_price,
            'deposit_paid' => $deposit_paid,
            'remaining_balance' => $remaining_balance,
            'product' => wc_get_product($item->get_product_id())
        ];
    }

    if (empty($items_data)) {
        wp_send_json_error(['message' => 'No valid commission items found']);
    }

    // Calculate shipping
    $shipping_total = 0;
    try {
        $shipping_address = $original_order->get_address('shipping');
        if (!empty($shipping_address['country'])) {
            $package_contents = [];
            foreach ($items_data as $idx => $item_data) {
                $package_contents['item_' . $idx] = [
                    'data' => $item_data['product'],
                    'quantity' => 1,
                    'line_subtotal' => $item_data['remaining_balance'],
                    'line_total' => $item_data['remaining_balance'],
                ];
            }

            $package = [
                'contents' => $package_contents,
                'contents_cost' => $total_balance,
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
        ms_commission_log('Shipping calculation error: ' . $e->getMessage());
    }

    wp_send_json_success([
        'items' => $items_data,
        'total_balance' => $total_balance,
        'shipping_total' => $shipping_total,
        'final_total' => $total_balance + $shipping_total
    ]);
});

/**
 * AJAX: Create final payment order
 */
add_action('wp_ajax_ms_create_final_payment_order', function() {
    check_ajax_referer('ms_final_payment', 'nonce');

    if (!current_user_can('edit_shop_orders')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    if (empty($_POST['confirmed']) || $_POST['confirmed'] !== 'yes') {
        wp_send_json_error(['message' => 'Confirmation required']);
    }

    $original_order_id = intval($_POST['order_id']);
    $item_ids = json_decode(stripslashes($_POST['item_ids']), true);

    $original_order = wc_get_order($original_order_id);
    if (!$original_order) {
        wp_send_json_error(['message' => 'Original order not found']);
    }

    // Use transaction to prevent race conditions
    global $wpdb;
    $wpdb->query('START TRANSACTION');

    try {
        $items_to_add = [];
        $total_balance = 0;

        foreach ($item_ids as $item_id) {
            $original_item = $original_order->get_item($item_id);
            if (!$original_item) continue;

            // Check for existing final payment with lock
            $final_id = ms_get_commission_meta($original_item, 'final_payment_order_id');
            if ($final_id) {
                $final_order = wc_get_order($final_id);
                if ($final_order && !$final_order->has_status('cancelled')) {
                    continue;
                }
            }

            $full_price = floatval(ms_get_commission_meta($original_item, 'full_price'));
            $deposit_paid = floatval(ms_get_commission_meta($original_item, 'deposit_paid'));

            if ($full_price <= 0 || $deposit_paid <= 0) continue;

            $remaining_balance = $full_price - $deposit_paid;
            $total_balance += $remaining_balance;

            $product = wc_get_product($original_item->get_product_id());

            $items_to_add[] = [
                'item_id' => $item_id,
                'original_item' => $original_item,
                'product' => $product,
                'remaining_balance' => $remaining_balance,
                'product_name' => $product->get_name()
            ];
        }

        if (empty($items_to_add)) {
            throw new Exception('No valid items to process');
        }

        // Create final payment order
        $final_order = wc_create_order([
            'customer_id' => $original_order->get_customer_id(),
            'status' => 'pending'
        ]);

        foreach ($items_to_add as $item_data) {
            $item = new WC_Order_Item_Product();
            $item->set_props([
                'product' => $item_data['product'],
                'quantity' => 1,
                'subtotal' => $item_data['remaining_balance'],
                'total' => $item_data['remaining_balance'],
                'name' => 'Final Payment — ' . $item_data['product_name']
            ]);

            ms_set_commission_meta($item, 'is_final_payment', 'yes');
            ms_set_commission_meta($item, 'original_order_id', $original_order_id);
            ms_set_commission_meta($item, 'original_item_id', $item_data['item_id']);

            $final_order->add_item($item);

            // Mark original item
            ms_set_commission_meta($item_data['original_item'], 'final_payment_created', 'yes');
            ms_set_commission_meta($item_data['original_item'], 'final_payment_order_id', $final_order->get_id());
            $item_data['original_item']->save_meta_data();
        }

        // Copy addresses
        $final_order->set_address($original_order->get_address('billing'), 'billing');
        $final_order->set_address($original_order->get_address('shipping'), 'shipping');

        // Add shipping
        $shipping_address = $final_order->get_address('shipping');
        $package_contents = [];

        foreach ($items_to_add as $idx => $item_data) {
            $package_contents['item_' . $idx] = [
                'data' => $item_data['product'],
                'quantity' => 1,
                'line_subtotal' => $item_data['remaining_balance'],
                'line_total' => $item_data['remaining_balance'],
            ];
        }

        $package = [
            'contents' => $package_contents,
            'contents_cost' => $total_balance,
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
        $final_order->add_order_note('Final payment for ' . count($items_to_add) . ' commission item(s) from Order #' . $original_order_id);
        $final_order->add_order_note('⚠️ Review order, then use Order Actions → "Email invoice" to send to customer');

        $wpdb->query('COMMIT');

        wp_send_json_success([
            'order_id' => $final_order->get_id(),
            'message' => 'Final payment order created successfully'
        ]);

    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        ms_commission_log('Final payment creation failed', ['error' => $e->getMessage()]);
        wp_send_json_error(['message' => $e->getMessage()]);
    }
});

/**
 * Display final payment info in admin
 */
add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    // Check if this IS a final payment order
    $is_final_payment = false;
    foreach ($order->get_items() as $item) {
        if (ms_get_commission_meta($item, 'is_final_payment')) {
            $is_final_payment = true;
            $original_order_id = ms_get_commission_meta($item, 'original_order_id');
            echo '<div style="margin-top:15px; padding:12px; background:#fff3cd; border-left:4px solid #ffc107;">';
            echo '<strong>⚠️ This is a Final Payment Order</strong><br/>';
            echo 'Original deposit order: <a href="' . admin_url('post.php?post=' . $original_order_id . '&action=edit') . '">Order #' . $original_order_id . '</a>';
            echo '</div>';

            // Add Send Invoice button
            echo '<div style="margin-top:15px; padding:12px; background:#d1ecf1; border-left:4px solid #0c5460;">';
            echo '<strong>📧 Send Invoice to Customer</strong><br/>';
            echo '<p style="margin:10px 0;">Review the order details above, then click to send the invoice email:</p>';
            echo '<button type="button" class="button button-primary ms-send-invoice-btn" data-order-id="' . esc_attr($order->get_id()) . '">Send Invoice Email Now</button>';
            echo '<span class="ms-invoice-status" style="margin-left:10px;"></span>';
            echo '</div>';

            break;
        }
    }

    if ($is_final_payment) {
        ?>
        <script>
        jQuery(function($){
            $('.ms-send-invoice-btn').on('click', function(e){
                e.preventDefault();
                var $btn = $(this);
                var $status = $('.ms-invoice-status');
                var orderId = $btn.data('order-id');

                if (!confirm('Send invoice email to customer now?')) {
                    return;
                }

                $btn.prop('disabled', true).text('Sending...');
                $status.text('');

                $.post(ajaxurl, {
                    action: 'ms_send_final_payment_invoice',
                    order_id: orderId,
                    nonce: '<?php echo wp_create_nonce('ms_send_invoice'); ?>'
                }, function(response){
                    if(response.success){
                        $status.html('<span style="color:#46b450;">✓ Invoice sent!</span>');
                        $btn.text('Resend Invoice Email');

                        // Add order note
                        location.reload();
                    } else {
                        $status.html('<span style="color:#dc3232;">✗ ' + response.data.message + '</span>');
                        $btn.prop('disabled', false).text('Send Invoice Email Now');
                    }
                });
            });
        });
        </script>
        <?php
    }

    // Check if any items HAVE final payment orders
    $final_payment_items = [];
    foreach ($order->get_items() as $item) {
        $final_payment_order_id = ms_get_commission_meta($item, 'final_payment_order_id');
        if ($final_payment_order_id) {
            $final_order = wc_get_order($final_payment_order_id);
            if ($final_order) {
                $final_payment_items[] = [
                    'item_name' => $item->get_name(),
                    'order_id' => $final_payment_order_id,
                    'status' => $final_order->get_status()
                ];
            }
        }
    }

    if (!empty($final_payment_items)) {
        echo '<div style="margin-top:15px; padding:12px; background:#d1ecf1; border-left:4px solid #0c5460;">';
        echo '<strong>ℹ️ Final Payment Orders Created</strong><br/>';
        foreach ($final_payment_items as $fp_item) {
            echo '<div style="margin-top: 8px;">';
            echo '<strong>' . esc_html($fp_item['item_name']) . ':</strong> ';
            echo '<a href="' . admin_url('post.php?post=' . $fp_item['order_id'] . '&action=edit') . '">Order #' . $fp_item['order_id'] . '</a>';
            echo ' - Status: ' . esc_html($fp_item['status']);
            echo '</div>';
        }
        echo '</div>';
    }
}, 15);

/**
 * Display final payment info in customer order view
 */
add_filter('woocommerce_order_item_name', function($item_name, $item) {
    if (ms_get_commission_meta($item, 'is_final_payment')) {
        $original_order_id = ms_get_commission_meta($item, 'original_order_id');
        $item_name .= '<br/><small>Completing commission from Order #' . $original_order_id . '</small>';
    }
    return $item_name;
}, 10, 2);

/**
 * AJAX: Send final payment invoice
 */
add_action('wp_ajax_ms_send_final_payment_invoice', function() {
    check_ajax_referer('ms_send_invoice', 'nonce');

    if (!current_user_can('edit_shop_orders')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);

    if (!$order) {
        wp_send_json_error(['message' => 'Invalid order']);
    }

    // Send invoice email
    $mailer = WC()->mailer();
    $email = $mailer->get_emails()['WC_Email_Customer_Invoice'];

    if ($email) {
        $email->trigger($order_id, $order);
        $order->add_order_note('Invoice email sent to customer manually');
        wp_send_json_success(['message' => 'Invoice sent successfully']);
    } else {
        wp_send_json_error(['message' => 'Email system not available']);
    }
});
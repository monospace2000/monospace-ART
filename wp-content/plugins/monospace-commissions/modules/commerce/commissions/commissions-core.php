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
define('MS_COMMISSION_VERSION', '2.1.0');
define('MS_COMMISSION_META_PREFIX', '_ms_commission_');
define('MS_COMMISSION_UPLOAD_DIR', 'commissions');
define('MS_COMMISSION_MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('MS_COMMISSION_MAX_FILES', 5);

/**
 * ===========================
 * ACTIVATION & SETUP
 * ===========================
 */
// This file is a module, so register_activation_hook( __FILE__ ) never fired here: the setup runs once per
// version on init instead (the option below records which version has run).
add_action('init', function() {
    if (get_option('ms_commission_activated') !== MS_COMMISSION_VERSION) {
        ms_commission_activate();
    }
}, 20);

function ms_commission_activate() {
    // Create upload directory with protection files
    $custom_dir = untrailingslashit(ms_commission_paths()['dir']);
    wp_mkdir_p($custom_dir);
    if (!file_exists($custom_dir . '/.htaccess')) {
        file_put_contents($custom_dir . '/.htaccess', 'Options -Indexes');
    }
    if (!file_exists($custom_dir . '/index.php')) {
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
 * Upload folder of the commissions, always with a trailing slash (dir and url)
 */
function ms_commission_paths() {
    $upload_dir = wp_upload_dir();
    return [
        'dir' => trailingslashit($upload_dir['basedir']) . MS_COMMISSION_UPLOAD_DIR . '/',
        'url' => trailingslashit($upload_dir['baseurl']) . MS_COMMISSION_UPLOAD_DIR . '/',
    ];
}

function ms_is_commission_product($product_id) {
    return get_post_meta($product_id, '_is_commission_product', true) === 'yes';
}

/**
 * How the deposit is booked (D2, owner 2026-10-27). 'b' (default): the deposit order's line IS the deposit
 * ("Deposit (60%) - X" at the deposit price, no negative fee) and the balance order carries a fee line - each
 * piece is counted once in product reports. 'a': the old shape (full-price line + negative "Balance Due Later"
 * fee; the balance order has a product line). The model is stored per commission (commission_data / item
 * meta `model`), so orders of either shape keep working whatever this returns.
 */
function ms_commission_model() {
    $model = apply_filters('ms_commission_model', get_option('ms_commission_model', 'b'));
    return $model === 'a' ? 'a' : 'b';
}

/**
 * The lines of a balance order that carry the commission marker: a product line (model a) or a fee line (model b)
 */
function ms_commission_final_items($order) {
    $found = [];
    foreach ($order->get_items(['line_item', 'fee']) as $item) {
        if (ms_get_commission_meta($item, 'is_final_payment')) $found[] = $item;
    }
    return $found;
}

/**
 * Customers' reference photos live OUTSIDE the web root (next to public_html, like the backups) and are served
 * by ms_commission_serve_file() to the order's customer and to shop admins only.
 */
function ms_commission_private_dir() {
    return trailingslashit(dirname(untrailingslashit(realpath(ABSPATH)))) . 'commission-files/';
}

function ms_commission_file_url($folder, $filename) {
    // the slash is encoded up front: the host redirects an unencoded one to its encoded form
    return home_url('/') . '?ms_commission_file=' . rawurlencode($folder . '/' . $filename);
}

/** The small-copy address of a stored reference photo URL (see the file handler below) */
function ms_commission_thumb_url($url) {
    return strpos((string) $url, 'ms_commission_file=') !== false ? add_query_arg('ms_commission_thumb', 1, $url) : $url;
}

/**
 * Re-save a reference photo: applies the camera's rotation, then re-encodes it, which drops all metadata (GPS
 * location, camera, date) - and caps huge photos at 2400 px. False if it couldn't be processed.
 */
function ms_commission_clean_image($path) {
    $editor = wp_get_image_editor($path);
    if (is_wp_error($editor)) return false;

    $editor->maybe_exif_rotate();
    $size = $editor->get_size();
    if (max($size['width'], $size['height']) > 2400) {
        $editor->resize(2400, 2400, false);
    }
    $editor->set_quality(88);

    $saved = $editor->save($path);
    return !is_wp_error($saved);
}

/**
 * Delete a folder inside the commissions folder (nothing outside it)
 */
function ms_commission_remove_dir($dir) {
    $real = realpath($dir);
    $inside = false;
    foreach ([ms_commission_paths()['dir'], ms_commission_private_dir()] as $base) {
        $base = realpath($base);
        if ($base && $real && $real !== $base && strpos($real, $base . DIRECTORY_SEPARATOR) === 0) $inside = true;
    }
    if (!$inside) return;

    foreach (scandir($real) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $path = $real . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($path) && !is_link($path)) {
            ms_commission_remove_dir($path);
        } else {
            @unlink($path);
        }
    }
    @rmdir($real);
}

/**
 * Log commission error
 */
function ms_commission_log($message, $data = []) {
    // the last 50 problems stay visible under WooCommerce -> Commission files (and WP_DEBUG still writes the log file)
    $log = get_option('ms_commission_log', []);
    $log[] = ['t' => time(), 'm' => (string) $message, 'd' => $data];
    update_option('ms_commission_log', array_slice($log, -50), false);

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

    echo '<p class="commission-deposit-info" style="margin-bottom: 2em !important; font-size: 0.9em; opacity: .85;">';
    echo '<strong>Upfront payment:</strong> ' . wc_price($deposit_price) . ' (' . intval($deposit_percent) . '%)';
    echo '</p>';

    echo '<p><strong>Medium:</strong> ' . (!empty($allowed_mediums) ? implode(', ', array_map(fn($k) => $options['mediums'][$k] ?? $k, $allowed_mediums)) : 'N/A') . '</p>';
    echo '<p><strong>Surface:</strong> ' . (!empty($allowed_surfaces) ? implode(', ', array_map(fn($k) => $options['surfaces'][$k] ?? $k, $allowed_surfaces)) : 'N/A') . '</p>';
    echo '<p style="margin-bottom: 1em !important"><strong>Size:</strong> ' . (!empty($allowed_sizes) ? implode(', ', array_map(fn($k) => $options['sizes'][$k] ?? $k, $allowed_sizes)) : 'N/A') . '</p>';

    echo '<p><label>Notes/Special Requests (optional)<br/>
    <textarea name="commission_special_request" rows="3" style="width:100%" placeholder="Describe the scene: season, time of day, features to include or avoid, preferred colors or atmosphere."></textarea>
    </label></p>';

    echo '<p style="margin-bottom: 1em !important"><label>Reference Image(s) <span style="color:red">(required)</span><br/>';
    echo '<input type="file" name="commission_reference_upload[]" accept="image/*" multiple required />';
    echo '<small class="description" style="display:block;margin-top:5px;">Max ' . MS_COMMISSION_MAX_FILES . ' images (JPEG, PNG, GIF or WebP), ' . (MS_COMMISSION_MAX_FILE_SIZE / 1024 / 1024) . 'MB each</small>';
    echo '</label></p>';
    echo '<div id="ms-ref-msg" role="alert" style="color:#b32d2e;font-size:0.9em"></div>';
    echo '<div id="ms-ref-previews" style="margin-bottom:1em"></div>';

    echo '</div>';
    ?>
    <script>
    (function(){
        var inp = document.querySelector('input[name="commission_reference_upload[]"]');
        if (!inp || !window.DataTransfer) return;
        var MAX = <?php echo (int) MS_COMMISSION_MAX_FILES; ?>, MAX_BYTES = <?php echo (int) MS_COMMISSION_MAX_FILE_SIZE; ?>;
        var box = document.getElementById('ms-ref-previews'), msg = document.getElementById('ms-ref-msg');
        var okType = /^image\/(jpeg|png|gif|webp)$/, files = [];

        function sync() {
            var dt = new DataTransfer();
            files.forEach(function(f){ dt.items.add(f); });
            inp.files = dt.files;
            box.textContent = '';
            files.forEach(function(f, i) {
                var wrap = document.createElement('span');
                wrap.style.cssText = 'position:relative;display:inline-block;margin:6px 8px 0 0';
                var img = document.createElement('img');
                img.src = URL.createObjectURL(f);
                img.alt = f.name;
                img.style.cssText = 'width:64px;height:64px;object-fit:cover;border:1px solid #ccc;display:block';
                var rm = document.createElement('span');
                rm.setAttribute('role', 'button');
                rm.tabIndex = 0;
                rm.textContent = String.fromCharCode(215); // not a literal x: an output filter rewrites it to &times; in scripts
                rm.setAttribute('aria-label', 'Remove ' + f.name);
                rm.style.cssText = 'position:absolute;top:-8px;right:-8px;width:20px;height:20px;line-height:20px;text-align:center;border-radius:50%;background:#b32d2e;color:#fff;font:700 14px/20px sans-serif;cursor:pointer;box-shadow:0 1px 3px rgba(0,0,0,.5)';
                var drop = function(){ msg.textContent = ''; files.splice(i, 1); sync(); };
                rm.addEventListener('click', drop);
                rm.addEventListener('keydown', function(e){ if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); drop(); } });
                wrap.appendChild(img);
                wrap.appendChild(rm);
                box.appendChild(wrap);
            });
        }

        // each selection ADDS to the list (the native input would replace its files every time)
        inp.addEventListener('change', function() {
            var problems = [];
            Array.prototype.slice.call(inp.files).forEach(function(f) {
                if (!okType.test(f.type)) { problems.push('"' + f.name + '" is not a JPEG, PNG, GIF or WebP image.'); return; }
                if (f.size > MAX_BYTES) { problems.push('"' + f.name + '" is larger than ' + (MAX_BYTES / 1048576) + ' MB.'); return; }
                var dupe = files.some(function(x){ return x.name === f.name && x.size === f.size && x.lastModified === f.lastModified; });
                if (dupe) return;
                if (files.length >= MAX) { problems.push('Only ' + MAX + ' images fit - "' + f.name + '" was not added.'); return; }
                files.push(f);
            });
            msg.textContent = problems.join(' ');
            sync();
        });
    })();
    </script>
    <?php
});

/**
 * ===========================
 * CART: ADD COMMISSION DATA
 * ===========================
 */

/**
 * Collect and check the uploaded reference images (WP_Error on the first problem)
 */
function ms_commission_collect_uploads() {
    $files = $_FILES['commission_reference_upload'] ?? null;
    $required = 'Reference images are required for commission orders (JPEG, PNG, GIF or WebP, max ' . (MS_COMMISSION_MAX_FILE_SIZE / 1024 / 1024) . 'MB each).';

    if (!$files || empty($files['name']) || !is_array($files['name'])) {
        return new WP_Error('no_files', $required);
    }

    $list = [];
    foreach ($files['name'] as $i => $name) {
        if ($name === '') continue;
        $list[] = [
            'name' => $name,
            'type' => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i],
            'size' => $files['size'][$i],
        ];
    }

    if (!$list) return new WP_Error('no_files', $required);

    if (count($list) > MS_COMMISSION_MAX_FILES) {
        return new WP_Error('too_many', sprintf('Maximum %d reference images allowed.', MS_COMMISSION_MAX_FILES));
    }

    foreach ($list as $file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', sprintf('"%s" could not be uploaded - it may be too large. Please try again.', $file['name']));
        }
        $check = ms_validate_upload_file($file);
        if (is_wp_error($check)) {
            return new WP_Error($check->get_error_code(), sprintf('"%s": %s', $file['name'], $check->get_error_message()));
        }
    }

    return $list;
}

/**
 * Images staged by the validation below, picked up by woocommerce_add_cart_item_data in the same request
 */
function ms_commission_staged($product_id, $set = null) {
    static $staged = [];
    if ($set !== null) $staged[$product_id] = $set;
    return $staged[$product_id] ?? null;
}

/**
 * Add to cart: all checks happen here, because only this filter can stop the add. A failed upload leaves
 * the cart untouched (before 2.1.0 the product went in as a plain full-price product).
 */
add_filter('woocommerce_add_to_cart_validation', function($passed, $product_id) {
    if (!$passed || !ms_is_commission_product($product_id)) return $passed;

    // Limit upload attempts per visitor (the product page is open to guests)
    $limit_key = 'ms_comm_up_' . substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . wp_salt()), 0, 32);
    $attempts = (int) get_transient($limit_key);
    if ($attempts >= 10) {
        wc_add_notice('Too many upload attempts. Please try again in an hour.', 'error');
        return false;
    }
    set_transient($limit_key, $attempts + 1, HOUR_IN_SECONDS);

    $files = ms_commission_collect_uploads();
    if (is_wp_error($files)) {
        wc_add_notice($files->get_error_message(), 'error');
        return false;
    }

    // Move the files into a private temp folder; it is renamed to order-{id} when the order is created
    $private = ms_commission_private_dir();
    $folder = 'temp-' . bin2hex(random_bytes(8));
    $references_dir = $private . $folder . '/references/';
    wp_mkdir_p($references_dir);

    $urls = [];
    foreach ($files as $file) {
        $filename = wp_unique_filename($references_dir, current_time('Y-m-d_H-i-s') . '_' . sanitize_file_name($file['name']));
        if (!move_uploaded_file($file['tmp_name'], $references_dir . $filename) || !ms_commission_clean_image($references_dir . $filename)) {
            ms_commission_log('File upload failed', ['file' => $filename]);
            ms_commission_remove_dir($private . $folder);
            wc_add_notice(sprintf('"%s" could not be processed. Please try another photo.', $file['name']), 'error');
            return false;
        }
        $urls[] = ms_commission_file_url($folder, $filename);
    }

    ms_commission_staged($product_id, $urls);
    return true;
}, 10, 2);

add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id) {
    if (!ms_is_commission_product($product_id)) {
        return $cart_item_data;
    }

    // No staged images: leave the line plain; woocommerce_check_cart_items removes it again
    $uploaded_urls = ms_commission_staged($product_id);
    if (empty($uploaded_urls)) {
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
        'terms_accepted' => wp_date('Y-m-d H:i'),
        'model' => ms_commission_model(),
    ];

    $data['reference_images'] = $uploaded_urls;
    $cart_item_data['commission_data'] = $data;

    return $cart_item_data;
}, 20, 3);

/**
 * Safety net: a commission line without its data (added some other way than the form) can't be bought -
 * it would be charged in full with no references. It is removed and the customer told (cart and checkout).
 */
add_action('woocommerce_check_cart_items', function() {
    if (!WC()->cart) return;

    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if (ms_is_commission_product($cart_item['product_id']) && empty($cart_item['commission_data'])) {
            WC()->cart->remove_cart_item($cart_item_key);
            wc_add_notice(sprintf('"%s" was removed from your cart because its reference photos are missing. Please order it again from its page and add your photos.', $cart_item['data']->get_name()), 'error');
        }
    }
});

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

    // Model b: the line carries the deposit (its price is set in the cart, below) - say so in its name
    if (($data['model'] ?? 'a') === 'b') {
        $percent = $data['full_price'] > 0 ? (int) round($data['deposit_paid'] / $data['full_price'] * 100) : 0;
        $item->set_name(sprintf('Deposit (%d%%) %s %s', $percent, "\u{2014}", $item->get_name()));
    }

}, 10, 4);

/**
 * Move the temp reference images into the order's folder after the order is created
 * (temp-{key}/references/ -> order-{id}/references/); a file that can't be moved is logged and noted on the order.
 */
add_action('woocommerce_checkout_order_created', function($order) {
    $private = ms_commission_private_dir();
    $order_id = $order->get_id();

    foreach ($order->get_items() as $item) {
        $images = ms_normalize_image_array(ms_get_commission_meta($item, 'reference_images'));
        if (!$images) continue;

        $new_images = [];
        $temp_folders = [];
        $moved = false;

        foreach ($images as $url) {
            $query = (string) wp_parse_url($url, PHP_URL_QUERY);
            parse_str($query, $args);
            $ref = $args['ms_commission_file'] ?? '';
            if (!preg_match('#^(temp-[A-Za-z0-9_.-]+)/([A-Za-z0-9._-]+)$#', $ref, $m)) {
                $new_images[] = $url;
                continue;
            }

            $source = $private . $m[1] . '/references/' . $m[2];
            $target_dir = $private . 'order-' . $order_id . '/references/';
            wp_mkdir_p($target_dir);
            $name = wp_unique_filename($target_dir, $m[2]);

            if (is_file($source) && rename($source, $target_dir . $name)) {
                $new_images[] = ms_commission_file_url('order-' . $order_id, $name);
                $temp_folders[$m[1]] = true;
                $moved = true;
            } else {
                $new_images[] = $url;
                ms_commission_log('Reference image could not be moved', ['order_id' => $order_id, 'file' => $source]);
                $order->add_order_note('Reference photo could not be moved to the order folder: ' . $m[2]);
            }
        }

        if ($moved) {
            ms_set_commission_meta($item, 'reference_images', $new_images);
            $item->save();
            foreach (array_keys($temp_folders) as $folder) {
                ms_commission_remove_dir($private . $folder);
            }
        }
    }
}, 10);

/**
 * Serve a reference photo: /?ms_commission_file=order-{id}/{file} - the order's customer and shop admins only
 * (temp folders: admins only).
 */
add_action('template_redirect', function() {
    if (empty($_GET['ms_commission_file']) || !is_string($_GET['ms_commission_file'])) return;

    if (!preg_match('#^((?:order|temp)-[A-Za-z0-9_.-]+)/([A-Za-z0-9._-]+)$#', wp_unslash($_GET['ms_commission_file']), $m)) {
        status_header(404);
        exit;
    }

    $allowed = current_user_can('edit_shop_orders');
    if (!$allowed && is_user_logged_in() && preg_match('/^order-(\d+)$/', $m[1], $o)) {
        $order = wc_get_order((int) $o[1]);
        $allowed = $order && $order->get_customer_id() === get_current_user_id();
    }

    $file = ms_commission_private_dir() . $m[1] . '/references/' . $m[2];
    if (!$allowed || !is_file($file)) {
        status_header($allowed ? 404 : 403);
        nocache_headers();
        exit;
    }

    // &ms_commission_thumb=1: a small copy (made once, kept in references/thumbs/) for the page's thumbnails
    if (!empty($_GET['ms_commission_thumb'])) {
        $thumb = ms_commission_private_dir() . $m[1] . '/references/thumbs/' . $m[2];
        if (!is_file($thumb)) {
            wp_mkdir_p(dirname($thumb));
            $editor = wp_get_image_editor($file);
            if (!is_wp_error($editor)) {
                $editor->resize(360, 360, false);
                $editor->set_quality(80);
                $editor->save($thumb);
            }
        }
        if (is_file($thumb)) $file = $thumb;
    }

    $type = wp_check_filetype($file)['type'];
    if (!$type || strpos($type, 'image/') !== 0) {
        status_header(404);
        exit;
    }

    nocache_headers();
    header('Content-Type: ' . $type);
    header('Content-Length: ' . filesize($file));
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    readfile($file);
    exit;
}, 1); // not on init: the modules load during init, a priority-1 hook would be too late

/**
 * When a commission's reference photos are deleted: 6 months after every one of its balance orders is completed
 * (the customer keeps the rights to them; owner's rule 2026-10-27). 0 = not scheduled yet.
 */
function ms_commission_references_expire($order) {
    $items = ms_commission_order_items($order);
    if (!$items) return 0;

    $latest = 0;
    foreach ($items as $item) {
        $final_id = ms_get_commission_meta($item, 'final_payment_order_id');
        $final = $final_id ? wc_get_order($final_id) : null;
        if (!$final || !$final->has_status('completed') || !$final->get_date_completed()) return 0;
        $latest = max($latest, $final->get_date_completed()->getTimestamp());
    }
    return $latest + 6 * MONTH_IN_SECONDS;
}

/**
 * Delete a commission's reference photos now (the cron, or the Commission files screen) and say so on the order
 */
function ms_commission_delete_references($order, $how) {
    // only the photos: the commission's records (manifest, notes, correspondence) stay in the folder
    ms_commission_remove_dir(ms_commission_private_dir() . 'order-' . $order->get_id() . '/references');
    foreach ($order->get_items() as $item) {
        if (!ms_get_commission_meta($item, 'key')) continue;
        ms_set_commission_meta($item, 'references_deleted', wp_date('Y-m-d'));
        $item->save_meta_data();
    }
    $order->add_order_note('Reference photos deleted ' . $how . '.');
}

/**
 * Daily: delete temp folders of carts that never became an order (older than 3 days), and the reference photos
 * of commissions completed more than 6 months ago (the customer keeps the rights to them; owner's rule 2026-10-27).
 * The old-style public folders (temp-temp_...) are left alone - an early order still points into one.
 */
add_action('init', function() {
    if (!wp_next_scheduled('ms_commission_cleanup_temp')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'ms_commission_cleanup_temp');
    }
}, 20);

add_action('ms_commission_cleanup_temp', function() {
    $private = ms_commission_private_dir();

    foreach ((array) glob($private . 'temp-*', GLOB_ONLYDIR) as $folder) {
        if (filemtime($folder) < time() - 3 * DAY_IN_SECONDS) {
            ms_commission_remove_dir($folder);
        }
    }

    foreach ((array) glob($private . 'order-*', GLOB_ONLYDIR) as $folder) {
        if (!preg_match('/order-(\d+)$/', $folder, $o) || !is_dir($folder . '/references')) continue;
        $order = wc_get_order((int) $o[1]);
        if (!$order) continue;

        $expires = ms_commission_references_expire($order);
        if (!$expires || $expires > time()) continue;

        ms_commission_delete_references($order, 'automatically (6 months after completion)');
    }
});

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
        MS_COMMISSION_META_PREFIX . 'references_deleted',
        MS_COMMISSION_META_PREFIX . 'terms_accepted',
        MS_COMMISSION_META_PREFIX . 'model',
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

        echo '<div class="ms-order-panel ms-zone-brief ms-commission-details">';
        echo '<h4>Custom Order Details: ' . esc_html($item->get_name()) . '</h4>';

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
            if ($meta_key === 'reference_images' && ($deleted = ms_get_commission_meta($item, 'references_deleted'))) {
                $value = 'Deleted automatically on ' . $deleted . ' (6 months after completion)';
            } elseif (empty($value)) {
                continue;
            }
            if ($meta_key === 'reference_images' && empty($deleted)) {
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

    // Model a only: the full-price line is reduced to the deposit by a negative fee
    $deposit_total = 0;

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['commission_data']) && ($cart_item['commission_data']['model'] ?? 'a') === 'a') {
            $full_price = floatval($cart_item['commission_data']['full_price']);
            $deposit_price = floatval($cart_item['commission_data']['deposit_paid']);
            $deposit_total += $full_price - $deposit_price;
        }
    }

    if ($deposit_total > 0) {
        $cart->add_fee('Balance Due Later', -$deposit_total, false);
    }
});

// Model b: the line is priced at the deposit
add_action('woocommerce_before_calculate_totals', function($cart) {
    if (is_admin() && !defined('DOING_AJAX')) return;

    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['commission_data']) && ($cart_item['commission_data']['model'] ?? 'a') === 'b') {
            $cart_item['data']->set_price(floatval($cart_item['commission_data']['deposit_paid']));
        }
    }
}, 20);

// ...and called one in the cart and at checkout
add_filter('woocommerce_cart_item_name', function($name, $cart_item) {
    $d = $cart_item['commission_data'] ?? null;
    if (!$d || ($d['model'] ?? 'a') !== 'b') return $name;

    $percent = $d['full_price'] > 0 ? (int) round($d['deposit_paid'] / $d['full_price'] * 100) : 0;
    return sprintf('Deposit (%d%%) %s %s', $percent, "\u{2014}", $name);
}, 10, 2);


/**
 * ===========================
 * CHECKOUT: ACCOUNT FOR COMMISSIONS
 * ===========================
 * A guest ordering a commission gets an account created at checkout (My Commissions needs one) instead of
 * being redirected mid-page. WooCommerce's own account settings (auto-generated password, set-password email) apply.
 */
function ms_cart_has_commission() {
    if (!function_exists('WC') || !WC()->cart) return false;
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (ms_is_commission_product($cart_item['product_id'])) return true;
    }
    return false;
}

/**
 * Orders list > Label: a commission deposit order is labelled "Commission" at checkout (before Core's own rule, which would
 * call a fully-discounted order "Loan" and any other "Online Sale"; it leaves an existing label alone)
 */
add_action('woocommerce_checkout_order_processed', function($order_id) {
    $order = wc_get_order($order_id);
    if (!$order || $order->get_meta('order_label', true) || !ms_commission_order_items($order)) return;
    $order->update_meta_data('order_label', 'commission');
    $order->save();
}, 10);

add_filter('woocommerce_checkout_registration_required', function($required) {
    return ms_cart_has_commission() ? true : $required;
});
add_filter('woocommerce_checkout_registration_enabled', function($enabled) {
    return ms_cart_has_commission() ? true : $enabled;
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
    <div class="ms-order-panel ms-zone-main">
        <h3>Next step: the balance</h3>
        <p style="margin: 0 0 12px 0;">When the artwork is complete, create the final payment invoice for the customer.</p>

        <div style="margin-bottom: 15px; padding: 12px; background: #fff; border: 1px solid #ddd;">
            <div style="margin-bottom: 12px; font-weight: 600;">Items ready for final payment:</div>
            <?php foreach($commission_items as $comm): ?>
            <div style="margin-bottom: 6px; padding-left: 12px;">• <?php echo esc_html($comm['product_name']); ?></div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="button button-primary ms-create-final-payment-btn"
                data-order-id="<?php echo esc_attr($order->get_id()); ?>"
                data-item-ids='<?php echo esc_attr($item_ids_json); ?>'>
            Review final payment (<?php echo count($commission_items); ?> item<?php echo count($commission_items) > 1 ? 's' : ''; ?>)
        </button>
    </div>

    <script>
    jQuery(function($){
        var nonce = '<?php echo esc_js(wp_create_nonce('ms_final_payment')); ?>';

        function money(c, n) { return c + Number(n).toFixed(2); }
        function el(tag, text, css) { var e = document.createElement(tag); if (text) e.textContent = text; if (css) e.style.cssText = css; return e; }

        $('.ms-create-final-payment-btn').on('click', function(e){
            e.preventDefault();
            var $btn = $(this), orderId = $btn.data('order-id'), itemIds = $btn.data('item-ids');
            $('.ms-fp-review').remove();
            $btn.prop('disabled', true).text('Calculating...');

            $.post(ajaxurl, { action: 'ms_preview_final_payment', order_id: orderId, item_ids: JSON.stringify(itemIds), nonce: nonce }, function(response){
                $btn.prop('disabled', false).text('Review final payment');
                if (!response.success) { alert('Error: ' + response.data.message); return; }
                var d = response.data, c = d.currency;
                var box = el('div', '', 'margin-top:15px;padding:15px;background:#fff;border:1px solid #c3c4c7;border-radius:6px');
                box.className = 'ms-fp-review';
                box.appendChild(el('h4', 'Review the final payment', 'margin:0 0 10px'));

                d.items.forEach(function(it){
                    box.appendChild(el('div', it.product_name + ' - full ' + money(c, it.full_price) + ', deposit paid ' + money(c, it.deposit_paid) + ', balance ' + money(c, it.remaining_balance), 'margin-bottom:4px'));
                });
                box.appendChild(el('p', 'Ship to: ' + (d.address || '(no shipping address on the order)'), 'margin:10px 0'));

                if (!d.address_ok) {
                    var w = el('div', 'This order has no shipping country, so WooCommerce can\u2019t offer shipping methods. Add the address to the deposit order first, or choose "No shipping" on purpose.', 'padding:8px 12px;border-left:4px solid #dba617;background:#fcf9e8;margin:10px 0');
                    box.appendChild(w);
                } else if (!d.rates.length) {
                    box.appendChild(el('div', 'No shipping method matches this address in your WooCommerce shipping zones.', 'padding:8px 12px;border-left:4px solid #dba617;background:#fcf9e8;margin:10px 0'));
                }

                var opts = d.rates.slice();
                opts.push({ id: 'none', label: 'No shipping', cost: 0 });
                var checked = d.rates.length ? 0 : opts.length - 1;
                var cost = el('input'); cost.type = 'number'; cost.step = '0.01'; cost.min = '0'; cost.style.width = '90px';
                var total = el('strong');
                var zero = el('div', 'Shipping is 0.00 - check that this is right before creating the order.', 'padding:8px 12px;border-left:4px solid #dba617;background:#fcf9e8;margin:10px 0;display:none');

                function refresh() {
                    var sel = box.querySelector('input[name=ms_fp_rate]:checked');
                    var o = opts[sel.value];
                    if (document.activeElement !== cost) cost.value = Number(o.cost).toFixed(2);
                    cost.disabled = o.id === 'none';
                    var ship = o.id === 'none' ? 0 : (parseFloat(cost.value) || 0);
                    total.textContent = 'Total due: ' + money(c, d.total_balance + ship);
                    zero.style.display = (o.id !== 'none' && ship === 0) ? 'block' : 'none';
                }

                opts.forEach(function(o, i){
                    var label = el('label', '', 'display:block;margin:3px 0');
                    var r = el('input'); r.type = 'radio'; r.name = 'ms_fp_rate'; r.value = i; r.checked = i === checked;
                    r.addEventListener('change', function(){ cost.value = Number(o.cost).toFixed(2); refresh(); });
                    label.appendChild(r);
                    label.appendChild(document.createTextNode(' ' + o.label + (o.id === 'none' ? '' : ' - ' + money(c, o.cost))));
                    box.appendChild(label);
                });

                var costRow = el('p', 'Shipping amount (editable): ', 'margin:10px 0');
                costRow.appendChild(document.createTextNode(c + ' '));
                costRow.appendChild(cost);
                box.appendChild(costRow);
                cost.addEventListener('input', refresh);

                box.appendChild(zero);
                box.appendChild(el('p', '', 'margin:10px 0')).appendChild(total);

                var go = el('button', 'Create final payment order'); go.type = 'button'; go.className = 'button button-primary';
                var cancel = el('button', 'Cancel', 'margin-left:8px'); cancel.type = 'button'; cancel.className = 'button';
                cancel.addEventListener('click', function(){ box.remove(); });
                go.addEventListener('click', function(){
                    var o = opts[box.querySelector('input[name=ms_fp_rate]:checked').value];
                    go.disabled = true; go.textContent = 'Creating order...';
                    $.post(ajaxurl, {
                        action: 'ms_create_final_payment_order', order_id: orderId, item_ids: JSON.stringify(itemIds), confirmed: 'yes', nonce: nonce,
                        shipping: JSON.stringify({ rate_id: o.id, cost: cost.value })
                    }, function(res){
                        if (res.success) {
                            window.location.href = res.data.edit_url;
                        } else {
                            alert('Error: ' + res.data.message);
                            go.disabled = false; go.textContent = 'Create final payment order';
                        }
                    });
                });
                var row = el('p', '', 'margin:15px 0 0');
                row.appendChild(go); row.appendChild(cancel);
                box.appendChild(row);
                box.appendChild(el('p', 'The order is created as pending - you send the invoice from it after a last look.', 'margin:10px 0 0;color:#646970'));

                $btn.closest('div').after(box);
                refresh();
            });
        });
    });
    </script>
    <?php
}, 10);

/**
 * Shipping rates WooCommerce offers for a balance order, from the shipping zones (never typed amounts).
 * $lines: [ ['product' => WC_Product, 'balance' => float], ... ]
 */
function ms_commission_balance_shipping($address, $lines) {
    $result = ['rates' => [], 'address_ok' => !empty($address['country'])];
    if (!$result['address_ok']) return $result;

    $contents = [];
    $cost = 0;
    foreach ($lines as $idx => $line) {
        $contents['item_' . $idx] = [
            'data' => $line['product'],
            'quantity' => 1,
            'line_subtotal' => $line['balance'],
            'line_total' => $line['balance'],
        ];
        $cost += $line['balance'];
    }

    $package = [
        'contents' => $contents,
        'contents_cost' => $cost,
        'destination' => [
            'country' => $address['country'],
            'state' => $address['state'] ?? '',
            'postcode' => $address['postcode'] ?? '',
            'city' => $address['city'] ?? '',
            'address' => $address['address_1'] ?? '',
            'address_2' => $address['address_2'] ?? '',
        ],
    ];

    try {
        WC()->shipping()->calculate_shipping([$package]);
        $packages = WC()->shipping()->get_packages();
        $pkg = $packages ? reset($packages) : null;
        foreach (($pkg['rates'] ?? []) as $rate) {
            $result['rates'][] = [
                'id' => $rate->get_id(),
                'label' => $rate->get_label(),
                'method_id' => $rate->get_method_id(),
                'instance_id' => $rate->get_instance_id(),
                'cost' => (float) $rate->get_cost(),
            ];
        }
    } catch (Exception $e) {
        ms_commission_log('Shipping calculation error: ' . $e->getMessage());
    }

    return $result;
}

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

    $lines = [];
    foreach ($items_data as $d) {
        $lines[] = ['product' => $d['product'], 'balance' => $d['remaining_balance']];
    }
    $address = $original_order->get_address('shipping');
    if (empty($address['country'])) $address = $original_order->get_address('billing'); // no shipping address on the order
    $shipping = ms_commission_balance_shipping($address, $lines);

    foreach ($items_data as &$d) {
        unset($d['product']); // an object, not for the browser
    }
    unset($d);

    wp_send_json_success([
        'items' => $items_data,
        'total_balance' => $total_balance,
        'address' => trim(preg_replace('/\s*\n\s*/', ', ', wp_strip_all_tags(str_replace(['<br/>', '<br>'], "\n", $original_order->get_formatted_shipping_address('')))), ', '),
        'address_ok' => $shipping['address_ok'],
        'rates' => $shipping['rates'],
        'currency' => html_entity_decode(get_woocommerce_currency_symbol()),
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

    // Lock per original order: add_option() is atomic, so a second click / request stops here
    $lock = 'ms_commission_fp_lock_' . $original_order_id;
    if (!add_option($lock, time(), '', 'no')) {
        if ((int) get_option($lock) < time() - 120) {
            delete_option($lock); // stale lock of a request that died
            add_option($lock, time(), '', 'no');
        } else {
            wp_send_json_error(['message' => 'A final payment is already being created for this order. Reload the order and check.']);
        }
    }

    $final_order = null;
    $original_items = [];
    $result = null;

    try {
        $items_to_add = [];
        $total_balance = 0;

        foreach ($item_ids as $item_id) {
            $original_item = $original_order->get_item($item_id);
            if (!$original_item) continue;

            // Skip items that already have a live final payment order
            $final_id = ms_get_commission_meta($original_item, 'final_payment_order_id');
            if ($final_id) {
                $existing = wc_get_order($final_id);
                if ($existing && !$existing->has_status('cancelled')) {
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
                'product_name' => $product->get_name(),
                'model' => ms_get_commission_meta($original_item, 'model') === 'b' ? 'b' : 'a',
            ];
        }

        if (empty($items_to_add)) {
            throw new Exception('No valid items to process (a final payment may already exist).');
        }

        // Create final payment order
        $final_order = wc_create_order([
            'customer_id' => $original_order->get_customer_id(),
            'status' => 'pending'
        ]);

        if (is_wp_error($final_order)) {
            throw new Exception($final_order->get_error_message());
        }

        foreach ($items_to_add as $item_data) {
            if ($item_data['model'] === 'b') {
                // Model b: the balance is a fee line, so the piece is counted once (on the deposit line).
                // Tax status none: see COMMISSIONS-ACTION-PLAN F21 if sales tax is ever collected.
                $item = new WC_Order_Item_Fee();
                $item->set_name('Final Payment ' . "\u{2014}" . ' ' . $item_data['product_name']);
                $item->set_amount($item_data['remaining_balance']);
                $item->set_total($item_data['remaining_balance']);
                $item->set_tax_status('none');
            } else {
                $item = new WC_Order_Item_Product();
                $item->set_props([
                    'product' => $item_data['product'],
                    'quantity' => 1,
                    'subtotal' => $item_data['remaining_balance'],
                    'total' => $item_data['remaining_balance'],
                    'name' => 'Final Payment ' . "\u{2014}" . ' ' . $item_data['product_name']
                ]);
            }

            ms_set_commission_meta($item, 'is_final_payment', 'yes');
            ms_set_commission_meta($item, 'original_order_id', $original_order_id);
            ms_set_commission_meta($item, 'original_item_id', $item_data['item_id']);

            $final_order->add_item($item);
        }

        // Copy addresses
        $final_order->set_address($original_order->get_address('billing'), 'billing');
        $ship_to = $original_order->get_address('shipping');
        $final_order->set_address(!empty($ship_to['country']) ? $ship_to : $original_order->get_address('billing'), 'shipping');

        // Shipping as chosen on the review screen (recalculated here from the WooCommerce zones)
        $choice = json_decode(stripslashes($_POST['shipping'] ?? ''), true);
        if (!is_array($choice) || empty($choice['rate_id'])) {
            throw new Exception('Choose a shipping method (or "No shipping") in the review first.');
        }

        if ($choice['rate_id'] !== 'none') {
            $lines = [];
            foreach ($items_to_add as $item_data) {
                $lines[] = ['product' => $item_data['product'], 'balance' => $item_data['remaining_balance']];
            }
            $options = ms_commission_balance_shipping($final_order->get_address('shipping'), $lines)['rates'];
            $rate = null;
            foreach ($options as $option) {
                if ($option['id'] === $choice['rate_id']) $rate = $option;
            }
            if (!$rate) {
                throw new Exception('That shipping method is not available for this address any more. Reload and review again.');
            }

            $cost = isset($choice['cost']) && is_numeric($choice['cost']) ? max(0, (float) $choice['cost']) : $rate['cost'];
            $shipping_item = new WC_Order_Item_Shipping();
            $shipping_item->set_method_title($rate['label']);
            $shipping_item->set_method_id($rate['method_id']);
            $shipping_item->set_instance_id($rate['instance_id']);
            $shipping_item->set_total(wc_format_decimal($cost));
            $final_order->add_item($shipping_item);
        }

        $final_order->update_meta_data('order_label', 'commission'); // Orders list > Label (Core, order-labels.php)
        $final_order->calculate_totals(false); // no tax on the balance (see F21 above)
        $final_order->add_order_note('Final payment for ' . count($items_to_add) . ' commission item(s) from Order #' . $original_order_id);
        $final_order->add_order_note('⚠️ Review order, then use Order Actions → "Email invoice" to send to customer');

        // Only now mark the original items (nothing to undo if an earlier step failed)
        foreach ($items_to_add as $item_data) {
            ms_set_commission_meta($item_data['original_item'], 'final_payment_created', 'yes');
            ms_set_commission_meta($item_data['original_item'], 'final_payment_order_id', $final_order->get_id());
            $item_data['original_item']->save_meta_data();
        }

        $result = [
            'order_id' => $final_order->get_id(),
            'edit_url' => $final_order->get_edit_order_url(),
            'message' => 'Final payment order created successfully'
        ];
    } catch (Exception $e) {
        // No half-made order left behind
        if ($final_order instanceof WC_Order && $final_order->get_id()) {
            $final_order->delete(true);
        }
        ms_commission_log('Final payment creation failed', ['error' => $e->getMessage()]);
        $result = new WP_Error('failed', $e->getMessage());
    }

    delete_option($lock);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }
    wp_send_json_success($result);
});

/**
 * Display final payment info in admin
 */
add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    // Check if this IS a final payment order
    $is_final_payment = false;
    foreach (ms_commission_final_items($order) as $item) {
        $is_final_payment = true;
        $original_order_id = ms_get_commission_meta($item, 'original_order_id');
        $original_order = wc_get_order($original_order_id);
        echo '<div class="ms-order-panel ms-zone-main" style="background:#fff8e5;border-left:4px solid #dba617;">';
        echo '<strong>⚠️ This is a Final Payment Order</strong><br/>';
        echo 'Original deposit order: <a href="' . esc_url($original_order ? $original_order->get_edit_order_url() : '') . '">Order #' . $original_order_id . '</a>';
        echo '</div>';

        // Add Send Invoice button
        echo '<div class="ms-order-panel ms-zone-main" style="border-left:4px solid #2271b1;">';
        echo '<strong>📧 Send Invoice to Customer</strong><br/>';
        echo '<p style="margin:10px 0;">Review the order details above, then click to send the invoice email:</p>';
        $sent_at = (int) $order->get_meta('_ms_commission_invoice_sent');
        echo '<button type="button" class="button button-primary ms-send-invoice-btn" data-order-id="' . esc_attr($order->get_id()) . '" data-sent="' . esc_attr($sent_at ? wp_date('M j, Y H:i', $sent_at) : '') . '">' . ($sent_at ? 'Resend Invoice Email' : 'Send Invoice Email Now') . '</button>';
        echo '<span class="ms-invoice-status" style="margin-left:10px;"></span>';
        echo '</div>';

        break;
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

                var sentAt = $btn.data('sent');
                if (!confirm(sentAt
                    ? 'The invoice was already emailed to the customer on ' + sentAt + '.\n\nSend it AGAIN? (The customer gets a second email.)'
                    : 'Send invoice email to customer now?')) {
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
                    'edit_url' => $final_order->get_edit_order_url(),
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
            echo '<a href="' . esc_url($fp_item['edit_url']) . '">Order #' . $fp_item['order_id'] . '</a>';
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

    // Send invoice email - and only say so when WordPress really handed the message to the mail system
    $mailer = WC()->mailer();
    $email = $mailer->get_emails()['WC_Email_Customer_Invoice'];

    if ($email) {
        $sent = false;
        $mark = function() use (&$sent) { $sent = true; };
        add_action('wp_mail_succeeded', $mark);
        $email->trigger($order_id, $order);
        remove_action('wp_mail_succeeded', $mark);

        if (!$sent) {
            $order->add_order_note('Invoice email FAILED to send.');
            wp_send_json_error(['message' => 'The email could not be sent. Check the customer\'s email address, or try Order actions - Email invoice.']);
        }
        $order->add_order_note('Invoice email sent to customer manually');
        do_action('ms_commission_invoice_sent', $order);
        wp_send_json_success(['message' => 'Invoice sent successfully']);
    } else {
        wp_send_json_error(['message' => 'Email system not available']);
    }
});
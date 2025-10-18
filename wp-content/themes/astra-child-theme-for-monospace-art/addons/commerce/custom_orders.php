<?php
/**
 * Echo-only commission / custom order support for WooCommerce
 *
 * Data-driven: Mediums, sizes, surfaces are configurable
 *
 * @package astra-child-theme-for-monospace-art
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ===========================
 * 0) Configuration: Options
 * ===========================
 */
function get_custom_order_options() {
    return [
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
    ];
}

/**
 * ===========================
 * 1) Admin meta box for products
 * ===========================
 */
add_action('add_meta_boxes', function() {
    add_meta_box(
        'commission_product_meta',
        'Custom Order Settings',
        'render_commission_product_meta_box',
        'product',
        'side',
        'default'
    );
});

function render_commission_product_meta_box($post) {
    wp_nonce_field('save_commission_product_meta', 'commission_product_meta_nonce');

    $is_commission = get_post_meta($post->ID, '_is_commission_product', true) === 'yes';
    $deposit_percent = get_post_meta($post->ID, '_commission_deposit_percent', true) ?: 30;
    $special_type = get_post_meta($post->ID, '_special_order_type', true) ?: 'painting';
    $allowed_mediums = get_post_meta($post->ID, '_commission_allowed_mediums', true) ?: [];
    $allowed_sizes = get_post_meta($post->ID, '_commission_allowed_sizes', true) ?: [];
    $allowed_surfaces = get_post_meta($post->ID, '_commission_allowed_surfaces', true) ?: [];
    $options = get_custom_order_options();

    // Enable custom commission
    echo '<p><label><input type="checkbox" name="is_commission_product" value="yes" '.($is_commission?'checked':'').' /> This is a Custom Artwork order</label></p>';

    // Deposit percent
    echo '<p><label for="commission_deposit_percent">Deposit percent (%)</label><br/>';
    echo '<input type="number" name="commission_deposit_percent" id="commission_deposit_percent" value="'.esc_attr($deposit_percent).'" min="0" max="100" style="width:100%;" />';
    echo '<small class="description">Percent of artwork price charged as deposit (e.g. 30).</small></p>';

    // Special order type
    echo '<p><label for="special_order_type">Special Order Type</label><br/>';
    echo '<select name="special_order_type" id="special_order_type" style="width:100%;">';
    foreach($options['special_order_types'] as $key => $label) {
        echo '<option value="'.esc_attr($key).'" '.selected($special_type,$key,false).'>'.esc_html($label).'</option>';
    }
    echo '</select></p>';

    // Allowed Mediums
    echo '<p><strong>Allowed Mediums</strong><br/>';
    foreach($options['mediums'] as $key => $label) {
        echo '<label><input type="checkbox" name="commission_allowed_mediums[]" value="'.esc_attr($key).'" '.(in_array($key,$allowed_mediums)?'checked':'').' /> '.esc_html($label).'</label><br/>';
    }
    echo '</p>';

    // Allowed Sizes
    echo '<p><strong>Allowed Sizes</strong><br/>';
    foreach($options['sizes'] as $key => $label) {
        echo '<label><input type="checkbox" name="commission_allowed_sizes[]" value="'.esc_attr($key).'" '.(in_array($key,$allowed_sizes)?'checked':'').' /> '.esc_html($label).'</label><br/>';
    }
    echo '</p>';

    // Allowed Surfaces
    echo '<p><strong>Allowed Surfaces</strong><br/>';
    foreach($options['surfaces'] as $key => $label) {
        echo '<label><input type="checkbox" name="commission_allowed_surfaces[]" value="'.esc_attr($key).'" '.(in_array($key,$allowed_surfaces)?'checked':'').' /> '.esc_html($label).'</label><br/>';
    }
    echo '</p>';
}

/**
 * ===========================
 * 2) Save product meta
 * ===========================
 */
add_action('save_post_product', function($post_id){
    if(!isset($_POST['commission_product_meta_nonce'])) return;
    if(!wp_verify_nonce($_POST['commission_product_meta_nonce'],'save_commission_product_meta')) return;
    if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if(!current_user_can('edit_post',$post_id)) return;

    update_post_meta($post_id,'_is_commission_product',isset($_POST['is_commission_product']) && $_POST['is_commission_product']==='yes'?'yes':'');
    update_post_meta($post_id,'_commission_deposit_percent',max(0,min(100,floatval($_POST['commission_deposit_percent'] ?? 30))));
    update_post_meta($post_id,'_special_order_type',$_POST['special_order_type'] ?? 'painting');
    update_post_meta($post_id,'_commission_allowed_mediums',$_POST['commission_allowed_mediums'] ?? []);
    update_post_meta($post_id,'_commission_allowed_sizes',$_POST['commission_allowed_sizes'] ?? []);
    update_post_meta($post_id,'_commission_allowed_surfaces',$_POST['commission_allowed_surfaces'] ?? []);
}, 10 );

/**
 * ===========================
 * 3) Frontend product page fields
 * ===========================
 */
add_action('woocommerce_before_add_to_cart_button', function(){
    global $product;
    if(!$product) return;
    $product_id = $product->get_id();
    if(get_post_meta($product_id,'_is_commission_product',true)!=='yes') return;

    $options = get_custom_order_options();
    $allowed_mediums = get_post_meta($product_id,'_commission_allowed_mediums',true) ?: [];
    $allowed_sizes = get_post_meta($product_id,'_commission_allowed_sizes',true) ?: [];
    $allowed_surfaces = get_post_meta($product_id,'_commission_allowed_surfaces',true) ?: [];


    $deposit_percent = get_post_meta($product_id, '_commission_deposit_percent', true) ?: 30;
    $full_price = floatval($product->get_price());
    $deposit_price = ($deposit_percent / 100) * $full_price;



    echo '<div class="custom-order-options">';

    echo '<p class="commission-deposit-info" style="margin-bottom: 2em !important; font-size: 0.9em; color: #444;">';
    echo '<strong>Upfront payment:</strong> ' . wc_price($deposit_price) . ' (' . intval($deposit_percent) . '%)';
    echo '</p>';

    // Display Medium, Size, Surface
    echo '<p><strong>Medium:</strong> '.(!empty($allowed_mediums)?implode(', ',array_map(fn($k)=>$options['mediums'][$k] ?? $k,$allowed_mediums)):'N/A').'</p>';
    echo '<p><strong>Surface:</strong> '.(!empty($allowed_surfaces)?implode(', ',array_map(fn($k)=>$options['surfaces'][$k] ?? $k,$allowed_surfaces)):'N/A').'</p>';
    echo '<p style="margin-bottom: 1em !important"><strong>Size:</strong> '.(!empty($allowed_sizes)?implode(', ',array_map(fn($k)=>$options['sizes'][$k] ?? $k,$allowed_sizes)):'N/A').'</p>';

    // Textareas for customer
    echo '<p><label>Special Requests<br/><textarea name="commission_special_request" rows="3" style="width:100%"></textarea></label></p>';

    // Reference image upload
    echo '<p style="margin-bottom: 1em !important"><label>Reference Image <span style="color:red">(required)</span><br/>
          <input type="file" name="commission_reference_upload" accept="image/*" required />
          </label></p>';

    echo '</div>';
});


/**
 * ===========================
 * 4) Add commission data to cart (handle file upload)
 * ===========================
 */
add_filter('woocommerce_add_cart_item_data', function($cart_item_data, $product_id, $variation_id){
    if(get_post_meta($product_id,'_is_commission_product',true)!=='yes') return $cart_item_data;

    $options = get_custom_order_options();
    $allowed_mediums = get_post_meta($product_id,'_commission_allowed_mediums',true) ?: [];
    $allowed_sizes = get_post_meta($product_id,'_commission_allowed_sizes',true) ?: [];
    $allowed_surfaces = get_post_meta($product_id,'_commission_allowed_surfaces',true) ?: [];

    $data = [];
    $data['commission_medium'] = !empty($allowed_mediums)?implode(', ',array_map(fn($k)=>$options['mediums'][$k]??$k,$allowed_mediums)):'';
    $data['commission_size'] = !empty($allowed_sizes)?implode(', ',array_map(fn($k)=>$options['sizes'][$k]??$k,$allowed_sizes)):'';
    $data['commission_surface'] = !empty($allowed_surfaces)?implode(', ',array_map(fn($k)=>$options['surfaces'][$k]??$k,$allowed_surfaces)):'';
    $data['commission_special_request'] = sanitize_textarea_field($_REQUEST['commission_special_request'] ?? '');
    $data['commission_full_price'] = floatval(wc_get_price_to_display(wc_get_product($product_id)));
    $data['unique_commission_key'] = uniqid('commission_',true);

    // Handle file upload
    if (!empty($_FILES['commission_reference_upload']['name'])) {

        // Temporarily change upload directory to /uploads/custom-orders
        add_filter('upload_dir', function($dirs) {
            $subdir = '/custom-orders';
            $dirs['subdir'] = $subdir;
            $dirs['path'] = $dirs['basedir'] . $subdir;
            $dirs['url']  = $dirs['baseurl'] . $subdir;
            return $dirs;
        });

        // Handle upload (WooCommerce form-safe)
        $upload = wp_handle_upload($_FILES['commission_reference_upload'], ['test_form' => false]);

        // Remove filter to avoid affecting other uploads
        remove_all_filters('upload_dir');

        // Store uploaded file URL if successful
        if (!empty($upload['url'])) {
            $data['commission_reference_upload'] = $upload['url'];
        }
    }


    $cart_item_data['commission_data'] = $data;
    return $cart_item_data;
}, 20, 3);

/**
 * ===========================
 * 5) Restore commission data from session
 * ===========================
 */
add_filter('woocommerce_get_cart_item_from_session', function($cart_item,$values){
    if(isset($values['commission_data'])) $cart_item['commission_data'] = $values['commission_data'];
    return $cart_item;
}, 20, 2);

/**
 * ===========================
 * 6) Display commission details in cart/checkout
 * ===========================
 */
add_filter('woocommerce_get_item_data', function($item_data, $cart_item){
    if(isset($cart_item['commission_data'])){
        $data = $cart_item['commission_data'];
        $fields = [
            'commission_medium' => 'Medium',
            'commission_size' => 'Size',
            'commission_surface' => 'Surface',
            'commission_special_request' => 'Special Requests',
            'commission_reference_upload' => 'Reference Image'
        ];

        foreach($fields as $key => $label){
            if(!empty($data[$key])){
                if($key === 'commission_reference_upload'){
                    $item_data[] = [
                        'key' => $label,
                        'value' => '<a href="'.esc_url($data[$key]).'" target="_blank">View Image</a>'
                    ];
                } else {
                    $item_data[] = ['key'=>$label,'value'=>wc_clean($data[$key])];
                }
            }
        }
    }
    return $item_data;
}, 10, 2);

/**
 * ===========================
 * 7) Save commission data to order items
 * ===========================
 */
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order){
    if(isset($values['commission_data'])){
        $data = $values['commission_data'];
        foreach($data as $key => $val){
            if($key === 'commission_reference_upload' && !empty($val)){
                $item->add_meta_data('Reference Image', $val, true);
            } elseif(is_scalar($val) && $key !== 'commission_other_notes'){
                $item->add_meta_data(ucwords(str_replace('_',' ',$key)), $val, true);
            }
        }
    }
}, 10, 4);

/**
 * ===========================
 * 8) Display commission data in admin order page
 * ===========================
 */
add_action('woocommerce_admin_order_data_after_order_details', function($order){
//    echo '<h3>Commission Details</h3>';
    foreach($order->get_items() as $item){
        if($item->get_meta('Unique Commission Key', true)){
            echo '<p><strong>Item:</strong> '.$item->get_name().'</p>';
            foreach($item->get_meta_data() as $meta){
                if($meta->key === 'Reference Image'){
                    echo '<p>'.$meta->key.': <a href="'.esc_url($meta->value).'" target="_blank">View Image</a></p>';
                } else {
                    echo '<p>'.$meta->key.': '.$meta->value.'</p>';
                }
            }
        }
    }
    echo '<br/>';
});



/**
 * ================================
 * 9) Deposit Reduction Fee
 * ================================
 */
add_action('woocommerce_cart_calculate_fees', function($cart){
    if ( is_admin() && !defined('DOING_AJAX') ) return;
    $deposit_total = 0;

    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['commission_data'])) {
            $product_id = $cart_item['product_id'];
            $deposit_percent = get_post_meta($product_id,'_commission_deposit_percent',true) ?: 30;
            $full_price = floatval($cart_item['commission_data']['commission_full_price']);
            $deposit_price = round(($deposit_percent/100) * $full_price, wc_get_price_decimals());
            $cart_item['commission_data']['applied_deposit_price'] = $deposit_price;
            WC()->cart->cart_contents[$cart_item_key] = $cart_item;
            $deposit_total += $full_price - $deposit_price;
        }
    }

    if ($deposit_total>0) $cart->add_fee('Deposit Reduction', -$deposit_total, false);
});

/**
 * ================================
 * 10) Require Login for Commission Orders
 * ================================
 */
add_action('woocommerce_before_checkout_form', function(){
    foreach (WC()->cart->get_cart() as $cart_item){
        if (isset($cart_item['commission_data']) && !is_user_logged_in()){
            wc_add_notice('You must be logged in to order custom commissions.','error');
            wp_safe_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }
    }
});



/**
 * ================================
 * 11) Redirect After Add-to-Cart
 * ================================
 */
add_filter('woocommerce_add_to_cart_redirect', function($url){
    $product_id = isset($_REQUEST['add-to-cart']) ? absint($_REQUEST['add-to-cart']) : 0;
    if ($product_id && get_post_meta($product_id,'_is_commission_product',true)==='yes') return wc_get_cart_url();
    return $url;
});

/**
 * ================================
 * 12) Display Commission Info in Cart / Mini-Cart / Emails
 * ================================
 */

add_filter('woocommerce_order_item_name', function($item_name, $item){
    if ($item->get_meta('Unique Commission Key', true)){
        $meta_lines = [];
        foreach($item->get_meta_data() as $meta){
            if (in_array($meta->key, ['Commission Medium','Commission Size','Commission Surface','Commission Special Request','Commission Other Notes','Commission Reference Upload'])){
                $value = $meta->key === 'Commission Reference Upload'
                    ? '<a href="'.esc_url($meta->value).'" target="_blank">View Image</a>'
                    : esc_html($meta->value);
                $meta_lines[] = esc_html($meta->key) . ': ' . $value;
            }
        }
        if ($meta_lines){
            $item_name .= '<br/><small class="commission-details">'.implode('<br/>',$meta_lines).'</small>';
        }
    }
    return $item_name;
}, 10, 2);


/**
 * ===========================
 * Exclude commission products from shipping until final payment
 * ===========================
 */
add_filter('woocommerce_cart_shipping_packages', function($packages) {

    // Grab the cart contents
    $cart = WC()->cart;
    if (!$cart) return $packages;

    $cart_items = $cart->get_cart();
    $has_commission = false;
    $has_non_commission = false;

    // Classify cart contents
    foreach ($cart_items as $item) {
        $product_id = $item['product_id'];
        $is_commission = get_post_meta($product_id, '_is_commission_product', true) === 'yes';

        if ($is_commission) {
            $has_commission = true;
        } else {
            $has_non_commission = true;
        }
    }

    // Case 1: only commission items — no shipping at all
    if ($has_commission && !$has_non_commission) {
        return []; // completely remove shipping packages
    }

    // Case 2: mixed cart — only ship non-commission items
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

    // Case 3: all regular items — no change
    return $packages;
}, 100);


/**
 * Show one-time shipping notice below the coupon prompt on checkout
 */
add_action('woocommerce_before_checkout_form', function () {
    // Prevent duplicates in AJAX reloads or fragments
    if (did_action('showed_commission_shipping_notice')) return;
    do_action('showed_commission_shipping_notice');

    // Check if there’s any commission item in cart
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

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
    echo '<p><label>Comments/Notes/Special Requests<br/><textarea name="commission_special_request" rows="3" style="width:100%"></textarea></label></p>';

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

    $full_price = floatval(wc_get_price_to_display(wc_get_product($product_id)));
    $deposit_percent = get_post_meta($product_id,'_commission_deposit_percent',true) ?: 30;
    $deposit_price = round(($deposit_percent/100)*$full_price, wc_get_price_decimals());

    $data = [];
    $data['commission_medium'] = !empty($allowed_mediums)?implode(', ',array_map(fn($k)=>$options['mediums'][$k]??$k,$allowed_mediums)):'';
    $data['commission_size'] = !empty($allowed_sizes)?implode(', ',array_map(fn($k)=>$options['sizes'][$k]??$k,$allowed_sizes)):'';
    $data['commission_surface'] = !empty($allowed_surfaces)?implode(', ',array_map(fn($k)=>$options['surfaces'][$k]??$k,$allowed_surfaces)):'';
    $data['commission_special_request'] = sanitize_textarea_field($_REQUEST['commission_special_request'] ?? '');
    $data['commission_full_price'] = $full_price;
    $data['applied_deposit_price'] = $deposit_price; // Save deposit for later
    $data['unique_commission_key'] = uniqid('commission_',true);

    // Handle file upload
    if (!empty($_FILES['commission_reference_upload']['name'])) {
        add_filter('upload_dir', function($dirs) {
            $subdir = '/custom-orders';
            $dirs['subdir'] = $subdir;
            $dirs['path'] = $dirs['basedir'] . $subdir;
            $dirs['url']  = $dirs['baseurl'] . $subdir;
            return $dirs;
        });

        $upload = wp_handle_upload($_FILES['commission_reference_upload'], ['test_form' => false]);

        remove_all_filters('upload_dir');

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
 * Helper: Format commission data for display
 * ===========================
 */
function ms_get_commission_meta_html($data, $as_array = false){
    $display_keys = [
        'commission_medium' => 'Medium',
        'commission_size' => 'Size',
        'commission_surface' => 'Surface',
        'commission_special_request' => 'Special Requests',
        'commission_reference_upload' => 'Reference Image'
    ];

    $output = [];
    foreach($display_keys as $key => $label){
        if(empty($data[$key])) continue;

        if($key === 'commission_reference_upload'){
            $value = '<a href="'.esc_url($data[$key]).'" target="_blank">View Image</a>';
        } else {
            $value = esc_html($data[$key]);
        }

        if($as_array){
            $output[] = ['key'=>$label,'value'=>$value];
        } else {
            $output[] = $label . ': ' . $value;
        }
    }

    if($as_array) return $output;
    return '<br/><small class="commission-details">'.implode('<br/>',$output).'</small>';
}

/**
 * ===========================
 * 6) Display commission details in cart/checkout
 * ===========================
 */
add_filter('woocommerce_get_item_data', function($item_data, $cart_item){
    if(isset($cart_item['commission_data'])){
        $item_data = array_merge($item_data, ms_get_commission_meta_html($cart_item['commission_data'], true));
    }
    return $item_data;
}, 10, 2);


/**
 * ===========================
 * 7) Save commission data to order items (patched)
 * ===========================
 */
add_action('woocommerce_checkout_create_order_line_item', function($item, $cart_item_key, $values, $order){
    if(isset($values['commission_data'])){
        $data = $values['commission_data'];

        $full_price = floatval($data['commission_full_price'] ?? $item->get_total());
        $deposit_paid = floatval($data['applied_deposit_price'] ?? 0);

        // Fallback: calculate deposit if missing
        if($deposit_paid <= 0){
            $product_id = $item->get_product_id();
            $deposit_percent = get_post_meta($product_id,'_commission_deposit_percent',true) ?: 30;
            $deposit_paid = round(($deposit_percent/100)*$full_price, wc_get_price_decimals());
        }

        $final_payment = max(0, $full_price - $deposit_paid);

        // Save all existing commission data
        foreach($data as $key => $val){
            if(is_scalar($val)){
                $meta_key = '_' . $key;
                $item->add_meta_data($meta_key, $val, true);
            }
        }

        // Save applied deposit and final payment
        $item->add_meta_data('_applied_deposit_price', $deposit_paid, true);
        $item->add_meta_data('_commission_final_payment', $final_payment, true);
    }
}, 10, 4);

// hide these:
add_filter('woocommerce_hidden_order_itemmeta', function($hidden){
    $internal_keys = [
        '_unique_commission_key',
        '_commission_medium',
        '_commission_size',
        '_commission_surface',
        '_commission_special_request',
        '_commission_reference_upload',
        '_applied_deposit_price',
        '_commission_full_price',
        '_commission_final_payment',
        '_is_final_payment',
        '_original_order_id',
        '_original_item_id',

        // Fix for your issue
        '_final_payment_created',
        '_final_payment_order_id'
    ];

    return array_merge($hidden, $internal_keys);
});
/**
 * ===========================
 * 8) Display commission data in admin order page
 * ===========================
 */
add_action('woocommerce_admin_order_data_after_order_details', function($order){
    foreach($order->get_items() as $item){
        if($item->get_meta('_unique_commission_key', true)){ // Note underscore prefix
            echo '<div style="margin-top:20px; padding:15px; background:#f7f7f7; border-left:4px solid white;">';
            echo '<h4 style="margin-top:0;">Custom Order Details: '.$item->get_name().'</h4>';

            $display_map = [
                '_commission_medium' => 'Medium',
                '_commission_size' => 'Size',
                '_commission_surface' => 'Surface',
                '_commission_special_request' => 'Special Requests',
                '_commission_reference_upload' => 'Reference Image',
                '_applied_deposit_price' => 'Deposit Paid',
                '_commission_final_payment' => 'Final Payment Due'

            ];

            echo '<table class="commission-details" style="width:100%;">';
            foreach($display_map as $meta_key => $label){
                $value = $item->get_meta($meta_key, true);
                if(!empty($value)){
                    if($meta_key === '_commission_reference_upload'){
                        $value = '<a href="'.esc_url($value).'" target="_blank">View Image</a>';
                    } else {
                        $value = esc_html($value);
                    }
                    echo '<tr><td style="padding:5px 10px 5px 0; font-weight:600;">'.$label.':</td><td style="padding:5px 0;">'.$value.'</td></tr>';
                }
            }
            echo '</table>';
            echo '</div>';
        }
    }
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

/* add_filter('woocommerce_order_item_name', function($item_name, $item){
    if ($item->get_meta('_unique_commission_key', true)){ // Note underscore prefix
        $display_map = [
            '_commission_medium' => 'Medium',
            '_commission_size' => 'Size',
            '_commission_surface' => 'Surface',
            '_commission_special_request' => 'Special Requests',
            '_commission_reference_upload' => 'Reference Image'
        ];

        $meta_lines = [];
        foreach($display_map as $meta_key => $label){
            $value = $item->get_meta($meta_key, true);
            if(!empty($value)){
                if($meta_key === '_commission_reference_upload'){
                    $value = '<a href="'.esc_url($value).'" target="_blank">View Image</a>';
                } else {
                    $value = esc_html($value);
                }
                $meta_lines[] = '<strong>'.$label.':</strong> ' . $value;
            }
        }

        if ($meta_lines){
            $item_name .= '<br/><small class="commission-details" style="display:block; margin-top:8px; line-height:1.6;">'.implode('<br/>',$meta_lines).'</small>';
        }
    }
    return $item_name;
}, 10, 2); */

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

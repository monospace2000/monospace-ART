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
 * -----------------------------
 * 0) Configuration
 * -----------------------------
 */
function get_custom_order_options() {
    return array(
        'mediums' => array(
            'acrylic' => 'Acrylic',
            'casein'  => 'Casein',
            'gouache'  => 'Gouache',
            'watercolor' => 'Watercolor',
            'oil'     => 'Oil',
            'pen_ink'=> 'Pen & Ink',
            'line_wash' => 'Line & Wash',
            'other' => 'Other',
        ),
        'surfaces' => array(
            'paper'  => 'Paper',
            'MDF'    => 'MDF Board',
            'canvas' => 'Canvas',
            'canvas_panel' => 'Canvas Panel',
        ),
        'sizes' => array(
            '7x7'  => '7 × 5 inches',
            '10x8'  => '10 × 8 inches (standard)',
            '12x9' => '12 × 9 inches',
            '14x11' => '14 × 11 inches',
            'other' => 'Other / Custom request',
        ),
        'special_order_types' => array(
            'painting' => 'Painting',
            'sketch'   => 'Sketch / Drawing',
        )
    );
}

/**
 * -----------------------------
 * 1) Admin meta box for products
 * -----------------------------
 */
add_action( 'add_meta_boxes', 'register_commission_product_meta_box_dynamic' );
function register_commission_product_meta_box_dynamic() {
    add_meta_box(
        'commission_product_meta',
        'Custom Order Settings',
        'render_commission_product_meta_box_dynamic',
        'product',
        'side',
        'default'
    );
}

function render_commission_product_meta_box_dynamic( $post ) {
    wp_nonce_field( 'save_commission_product_meta', 'commission_product_meta_nonce' );

    // Load existing meta
    $is_commission    = get_post_meta( $post->ID, '_is_commission_product', true ) === 'yes';
    $deposit_percent  = get_post_meta( $post->ID, '_commission_deposit_percent', true ) ?: 30;
    $special_type     = get_post_meta( $post->ID, '_special_order_type', true ) ?: 'painting';
    $allowed_mediums  = get_post_meta( $post->ID, '_commission_allowed_mediums', true ) ?: [];
    $allowed_sizes    = get_post_meta( $post->ID, '_commission_allowed_sizes', true ) ?: [];
    $allowed_surfaces = get_post_meta( $post->ID, '_commission_allowed_surfaces', true ) ?: [];

    // Section 0 options
    $options = get_custom_order_options();

    // Commission checkbox
    echo '<p><label>';
    echo '<input type="checkbox" name="is_commission_product" value="yes" ' . ($is_commission ? 'checked' : '') . ' />';
    echo ' This is a Custom Artwork order</label></p>';

    // Deposit percent
    echo '<p><label for="commission_deposit_percent">Deposit percent (%)</label><br/>';
    echo '<input type="number" name="commission_deposit_percent" id="commission_deposit_percent" value="' . esc_attr($deposit_percent) . '" min="0" max="100" style="width:100%;" />';
    echo '<small class="description">Percent of artwork price charged as deposit (e.g. 30).</small></p>';

    // Special order type dropdown
    echo '<p><label for="special_order_type">Special Order Type</label><br/>';
    echo '<select name="special_order_type" id="special_order_type" style="width:100%;">';
    foreach( $options['special_order_types'] as $key => $label ) {
        echo '<option value="' . esc_attr($key) . '" ' . selected($special_type, $key, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';

    // Allowed Mediums
    echo '<p><strong>Allowed Mediums</strong><br/>';
    foreach( $options['mediums'] as $key => $label ) {
        echo '<label><input type="checkbox" name="commission_allowed_mediums[]" value="' . esc_attr($key) . '" ' . (in_array($key,$allowed_mediums) ? 'checked' : '') . ' /> ' . esc_html($label) . '</label><br/>';
    }
    echo '</p>';

    // Allowed Sizes
    echo '<p><strong>Allowed Sizes</strong><br/>';
    foreach( $options['sizes'] as $key => $label ) {
        echo '<label><input type="checkbox" name="commission_allowed_sizes[]" value="' . esc_attr($key) . '" ' . (in_array($key,$allowed_sizes) ? 'checked' : '') . ' /> ' . esc_html($label) . '</label><br/>';
    }
    echo '</p>';

    // Allowed Surfaces
    echo '<p><strong>Allowed Surfaces</strong><br/>';
    foreach( $options['surfaces'] as $key => $label ) {
        echo '<label><input type="checkbox" name="commission_allowed_surfaces[]" value="' . esc_attr($key) . '" ' . (in_array($key,$allowed_surfaces) ? 'checked' : '') . ' /> ' . esc_html($label) . '</label><br/>';
    }
    echo '</p>';
}


/**
 * 2) Save commission product meta including allowed mediums/sizes/surfaces
 */
add_action( 'save_post_product', 'save_commission_product_meta_echo' );
function save_commission_product_meta_echo( $post_id ) {
    if ( ! isset( $_POST['commission_product_meta_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['commission_product_meta_nonce'], 'save_commission_product_meta' ) ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    // Basic flags
    if ( isset( $_POST['is_commission_product'] ) && $_POST['is_commission_product'] === 'yes' ) {
        update_post_meta( $post_id, '_is_commission_product', 'yes' );
    } else {
        delete_post_meta( $post_id, '_is_commission_product' );
    }

    $dp = isset( $_POST['commission_deposit_percent'] ) ? floatval( $_POST['commission_deposit_percent'] ) : 30;
    $dp = max(0,min(100,$dp));
    update_post_meta( $post_id, '_commission_deposit_percent', $dp );

    $type = isset( $_POST['special_order_type'] ) && in_array($_POST['special_order_type'],['painting','sketch','line_wash']) ? $_POST['special_order_type'] : 'painting';
    update_post_meta( $post_id, '_special_order_type', $type );

    // Allowed options
    $mediums = isset($_POST['commission_allowed_mediums']) && is_array($_POST['commission_allowed_mediums']) ? array_map('sanitize_text_field',$_POST['commission_allowed_mediums']) : array();
    update_post_meta( $post_id, '_commission_allowed_mediums', $mediums );

    $sizes = isset($_POST['commission_allowed_sizes']) && is_array($_POST['commission_allowed_sizes']) ? array_map('sanitize_text_field',$_POST['commission_allowed_sizes']) : array();
    update_post_meta( $post_id, '_commission_allowed_sizes', $sizes );

    $surfaces = isset($_POST['commission_allowed_surfaces']) && is_array($_POST['commission_allowed_surfaces']) ? array_map('sanitize_text_field',$_POST['commission_allowed_surfaces']) : array();
    update_post_meta( $post_id, '_commission_allowed_surfaces', $surfaces );
}

/**
 * -----------------------------
 * 2) Show frontend fields
 * -----------------------------
 */
add_action( 'woocommerce_before_add_to_cart_button', 'show_commission_fields_on_product_echo' );
function show_commission_fields_on_product_echo() {
    global $product;
    if ( ! $product || ! is_product() ) return;

    $product_id = $product->get_id();
    $is_commission = get_post_meta( $product_id, '_is_commission_product', true );
    if ( $is_commission !== 'yes' ) return;

    $special_type = get_post_meta( $product_id, '_special_order_type', true );
    if ( $special_type === '' ) $special_type = 'painting';

    $config = get_custom_order_options();
    $mediums = $config['mediums'];
    $surfaces = $config['surfaces'];
    $sizes = $config['sizes'];

    echo '<div class="commission-fields" style="margin:1em 0; padding:.8em; border:1px solid #eee; background:#fafafa;">';
    echo '<h3 style="margin:0 0 .5em;">Custom Order Details</h3>';

    // Medium radio buttons
    echo '<p><strong>Medium</strong><br/>';
    foreach ( $mediums as $key => $label ) {
        $checked = ($key === 'acrylic') ? 'checked' : '';
        echo "<label><input type='radio' name='commission_medium' value='{$key}' {$checked}> {$label}</label><br/>";
    }
    echo '</p>';

    // Surface radio buttons
    echo '<p><strong>Surface</strong><br/>';
    foreach ( $surfaces as $key => $label ) {
        $checked = ($key === 'paper') ? 'checked' : '';
        echo "<label><input type='radio' name='commission_surface' value='{$key}' {$checked}> {$label}</label><br/>";
    }
    echo '</p>';

    // Size dropdown
    echo '<p><label><strong>Size</strong></label><br/><select name="commission_size">';
    foreach ( $sizes as $key => $label ) {
        $sel = ($key==='10x8') ? 'selected' : '';
        echo "<option value='{$key}' {$sel}>{$label}</option>";
    }
    echo '</select></p>';

    // Reference uploads
    echo '<p><label><strong>Reference image(s) - <span style="color: red">required</span></strong></label><br/>';
    echo '<input type="file" name="commission_reference_files[]" accept=".jpg,.jpeg,.png,.pdf" multiple required />';
    echo '<br/><small>Allowed: jpg, png, pdf. Max size per file: 5MB.</small></p>';

    // Special request
    echo '<p><label><strong>Special request</strong></label><br/>';
    echo '<textarea name="commission_special_request" rows="3" placeholder="Remove people, change season, color changes, etc."></textarea></p>';

    // Other notes
    echo '<p><label><strong>Other notes</strong></label><br/>';
    echo '<textarea name="commission_other_notes" rows="2" placeholder="Any additional notes for this custom order."></textarea></p>';

    echo '<input type="hidden" name="is_commission_request" value="1" />';
    echo '</div>';
}

/**
 * -----------------------------
 * 3) Validate fields on add to cart
 * -----------------------------
 */
add_filter( 'woocommerce_add_to_cart_validation', 'validate_commission_fields_on_add_to_cart_echo', 10, 3 );
function validate_commission_fields_on_add_to_cart_echo( $passed, $product_id, $quantity ) {
    $is_commission = get_post_meta( $product_id, '_is_commission_product', true );
    if ( $is_commission !== 'yes' ) return $passed;

    $config = get_custom_order_options();

    // Validate medium
    if ( empty($_REQUEST['commission_medium']) || ! array_key_exists($_REQUEST['commission_medium'], $config['mediums']) ) {
        wc_add_notice( 'Please select a valid medium.', 'error' );
        return false;
    }

    // Validate surface
    if ( empty($_REQUEST['commission_surface']) || ! array_key_exists($_REQUEST['commission_surface'], $config['surfaces']) ) {
        wc_add_notice( 'Please select a valid surface.', 'error' );
        return false;
    }

    // Validate size
    if ( empty($_REQUEST['commission_size']) || ! array_key_exists($_REQUEST['commission_size'], $config['sizes']) ) {
        wc_add_notice( 'Please select a valid size.', 'error' );
        return false;
    }

    // Reference files
    if ( empty( $_FILES['commission_reference_files'] ) ) {
        wc_add_notice( 'Please upload at least one reference image (jpg, png or pdf).', 'error' );
        return false;
    }

    $files = $_FILES['commission_reference_files'];
    $count = 0;
    for ( $i = 0; $i < count( $files['name'] ); $i++ ) {
        if ( ! empty( $files['name'][$i] ) ) {
            $count++;
            if ( $files['size'][$i] > 5 * 1024 * 1024 ) {
                wc_add_notice( 'Each reference file must be under 5MB.', 'error' );
                return false;
            }
            $ext = strtolower( pathinfo( $files['name'][$i], PATHINFO_EXTENSION) );
            if ( ! in_array( $ext, array('jpg','jpeg','png','pdf') ) ) {
                wc_add_notice( 'Allowed reference file types: jpg, png, pdf.', 'error' );
                return false;
            }
        }
    }
    if ( $count === 0 ) {
        wc_add_notice( 'Please upload at least one reference file.', 'error' );
        return false;
    }

    return $passed;
}

/**
 * -----------------------------
 * 4) Process uploads & store custom data in cart
 * -----------------------------
 */
add_filter( 'woocommerce_add_cart_item_data', 'add_commission_data_to_cart_item_echo', 20, 3 );
function add_commission_data_to_cart_item_echo( $cart_item_data, $product_id, $variation_id ) {
    $is_commission = get_post_meta( $product_id, '_is_commission_product', true );
    if ( $is_commission !== 'yes' ) return $cart_item_data;

    $custom = array();
    $custom['commission_medium'] = isset($_REQUEST['commission_medium']) ? sanitize_text_field(wp_unslash($_REQUEST['commission_medium'])) : '';
    $custom['commission_surface'] = isset($_REQUEST['commission_surface']) ? sanitize_text_field(wp_unslash($_REQUEST['commission_surface'])) : '';
    $custom['commission_size'] = isset($_REQUEST['commission_size']) ? sanitize_text_field(wp_unslash($_REQUEST['commission_size'])) : '';
    $custom['commission_special_request'] = isset($_REQUEST['commission_special_request']) ? sanitize_textarea_field(wp_unslash($_REQUEST['commission_special_request'])) : '';
    $custom['commission_other_notes'] = isset($_REQUEST['commission_other_notes']) ? sanitize_textarea_field(wp_unslash($_REQUEST['commission_other_notes'])) : '';

    // Handle uploads
    $custom['commission_reference_uploads'] = array();
    if ( ! empty($_FILES['commission_reference_files']) ) {
        $files = $_FILES['commission_reference_files'];
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        $uploads = array();

        for ($i=0; $i<count($files['name']); $i++) {
            if (empty($files['name'][$i])) continue;

            $file = array(
                'name'=>$files['name'][$i],
                'type'=>$files['type'][$i],
                'tmp_name'=>$files['tmp_name'][$i],
                'error'=>$files['error'][$i],
                'size'=>$files['size'][$i]
            );

            $filter = function($dirs){
                $dirs['subdir'] = '/custom-orders';
                $dirs['path'] = $dirs['basedir'].$dirs['subdir'];
                $dirs['url'] = $dirs['baseurl'].$dirs['subdir'];
                if (!file_exists($dirs['path'])) wp_mkdir_p($dirs['path']);
                return $dirs;
            };
            add_filter('upload_dir', $filter);
            $movefile = wp_handle_upload($file,array('test_form'=>false));
            remove_filter('upload_dir', $filter);

            if ($movefile && !isset($movefile['error'])) {
                $uploads[] = array(
                    'url'=>$movefile['url'],
                    'file'=>$movefile['file'],
                    'name'=>basename($movefile['file'])
                );
            }
        }
        $custom['commission_reference_uploads'] = $uploads;
    }

    $custom['commission_full_price'] = floatval( wc_get_price_to_display( wc_get_product($product_id) ) );
    $custom['unique_commission_key'] = uniqid('commission_', true);

    $cart_item_data['commission_data'] = $custom;

    return $cart_item_data;
}

/**
 * -----------------------------
 * 5) Restore cart data from session
 * -----------------------------
 */
add_filter( 'woocommerce_get_cart_item_from_session', function($cart_item,$values){
    if ( isset($values['commission_data']) ) $cart_item['commission_data']=$values['commission_data'];
    return $cart_item;
}, 20, 2 );

/**
 * -----------------------------
 * 6) Apply deposit fee (negative fee)
 * -----------------------------
 */
add_action('woocommerce_cart_calculate_fees', function($cart){
    if ( is_admin() && ! defined('DOING_AJAX') ) return;

    $deposit_total = 0;
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['commission_data'])) {
            $product_id = $cart_item['product_id'];
            $deposit_percent = get_post_meta($product_id,'_commission_deposit_percent',true);
            if ($deposit_percent==='') $deposit_percent=30;
            $full_price = floatval($cart_item['commission_data']['commission_full_price']);
            $deposit_price = round(($deposit_percent/100)*$full_price, wc_get_price_decimals());

            $cart_item['commission_data']['applied_deposit_price'] = $deposit_price;
            WC()->cart->cart_contents[$cart_item_key]=$cart_item;

            $deposit_total += $full_price - $deposit_price;
        }
    }
    if ($deposit_total>0) $cart->add_fee('Deposit Reduction', -$deposit_total, false);
});


/**
 * -----------------------------
 * 7) Show commission details in cart & mini-cart
 * -----------------------------
 */
add_filter( 'woocommerce_get_item_data', 'show_commission_data_in_cart_echo', 10, 2 );
function show_commission_data_in_cart_echo( $item_data, $cart_item ) {
    if ( isset($cart_item['commission_data']) ) {
        $data = $cart_item['commission_data'];

        $item_data[] = array(
            'key' => 'Medium',
            'value' => ucfirst($data['commission_medium'])
        );
        $item_data[] = array(
            'key' => 'Surface',
            'value' => ucfirst($data['commission_surface'])
        );
        $item_data[] = array(
            'key' => 'Size',
            'value' => $data['commission_size']
        );
        if (!empty($data['commission_special_request'])) {
            $item_data[] = array(
                'key' => 'Special Request',
                'value' => $data['commission_special_request']
            );
        }
        if (!empty($data['commission_other_notes'])) {
            $item_data[] = array(
                'key' => 'Other Notes',
                'value' => $data['commission_other_notes']
            );
        }

        if (!empty($data['commission_reference_uploads'])) {
            $urls = array_map(function($u){ return "<a href='{$u['url']}' target='_blank'>".$u['name']."</a>"; }, $data['commission_reference_uploads']);
            $item_data[] = array(
                'key' => 'Reference Files',
                'value' => implode(', ', $urls)
            );
        }
    }
    return $item_data;
}

/**
 * -----------------------------
 * 8) Save commission data to order
 * -----------------------------
 */
add_action( 'woocommerce_checkout_create_order_line_item', 'save_commission_data_to_order_items_echo', 10, 4 );
function save_commission_data_to_order_items_echo( $item, $cart_item_key, $values, $order ) {
    if ( isset($values['commission_data']) ) {
        $data = $values['commission_data'];
        foreach ( $data as $key => $val ) {
            if ( $key === 'commission_reference_uploads' && is_array($val) ) {
                $val = implode(', ', array_map(function($u){ return $u['url']; }, $val));
            }
            if ( is_scalar($val) ) $item->add_meta_data( ucfirst(str_replace('_',' ',$key)), $val, true );
        }
    }
}

/**
 * -----------------------------
 * 9) Require account for commissions
 * -----------------------------
 */
add_action( 'woocommerce_before_checkout_form', function() {
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( isset($cart_item['commission_data']) && ! is_user_logged_in() ) {
            wc_print_notice( 'You must be logged in to order custom commissions.', 'error' );
            wp_safe_redirect( wc_get_page_permalink('myaccount') );
            exit;
        }
    }
});

/**
 * -----------------------------
 * 10) Admin order display
 * -----------------------------
 */
add_action( 'woocommerce_admin_order_data_after_order_details', function($order){
    echo '<h3>Commission Details</h3>';
    foreach( $order->get_items() as $item_id => $item ) {
        if ( $item->get_meta('Unique Commission Key', true) ) {
            echo '<p><strong>Item:</strong> '.$item->get_name().'</p>';
            foreach( $item->get_meta_data() as $meta ) {
                echo '<p>'.$meta->key.': '.$meta->value.'</p>';
            }
        }
    }
});

/**
 * -----------------------------
 * 11) Optional: add commission note to customer email
 * -----------------------------
 */
add_filter( 'woocommerce_email_order_meta_fields', function($fields, $sent_to_admin, $order){
    foreach( $order->get_items() as $item ) {
        if ( $item->get_meta('Unique Commission Key', true) ) {
            $fields['commission_notice'] = array(
                'label' => 'Commission Details',
                'value' => 'See order items for full details and reference uploads.'
            );
            break;
        }
    }
    return $fields;
}, 10, 3);

/**
 * -----------------------------
 * 12) Optional: redirect after add-to-cart to avoid double submission
 * -----------------------------
 */
add_filter( 'woocommerce_add_to_cart_redirect', function( $url ) {
    global $product;
    if ( $product && get_post_meta($product->get_id(), '_is_commission_product', true) === 'yes' ) {
        return wc_get_cart_url();
    }
    return $url;
});

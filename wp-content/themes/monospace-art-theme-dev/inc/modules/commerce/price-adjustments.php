<?php
/**
 * WooCommerce Category & Tag Price Multiplier with Admin UI
 */

/* ---------------------------------------------------------
   SETTINGS TAB
--------------------------------------------------------- */
add_filter('woocommerce_settings_tabs_array', function ($tabs) {
    $tabs['price_adjustments'] = 'Price Adjustments';
    return $tabs;
}, 50);

add_action('woocommerce_settings_tabs_price_adjustments', function () {
    woocommerce_admin_fields([
        [
            'title' => 'Price Adjustment Settings',
            'type'  => 'title',
            'id'    => 'price_adjustments_title',
        ],
        [
            'title'   => 'Enable price increase',
            'id'      => 'pa_enable',
            'type'    => 'checkbox',
            'default' => 'no',
        ],
        [
            'title'   => 'Increase percentage',
            'id'      => 'pa_percentage',
            'type'    => 'number',
            'css'     => 'width:80px;',
            'custom_attributes' => [
                'step' => '0.1',
                'min'  => '0',
            ],
            'default' => '25',
        ],
        [
            'title'   => 'Affected categories',
            'id'      => 'pa_categories',
            'type'    => 'multiselect',
            'class'   => 'wc-enhanced-select',
            'options' => wp_list_pluck(get_terms([
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
            ]), 'name', 'term_id'),
        ],
        [
            'title'   => 'Affected tags',
            'id'      => 'pa_tags',
            'type'    => 'multiselect',
            'class'   => 'wc-enhanced-select',
            'options' => wp_list_pluck(get_terms([
                'taxonomy'   => 'product_tag',
                'hide_empty' => false,
            ]), 'name', 'slug'),
        ],
        [
            'title'   => 'Rounding',
            'id'      => 'pa_rounding',
            'type'    => 'select',
            'options' => [
                'none'    => 'None',
                'up'      => 'Round up',
                'down'    => 'Round down',
                'nearest' => 'Round to nearest integer',
            ],
        ],
        [
            'title'   => 'Charm pricing',
            'id'      => 'pa_charm',
            'type'    => 'select',
            'options' => [
                'none' => 'None',
                'up'   => 'Round up to .99',
                'down' => 'Round down to .99',
            ],
        ],
        [
            'type' => 'sectionend',
            'id'   => 'price_adjustments_end',
        ],
    ]);
});

/* ---------------------------------------------------------
   SAVE OPTIONS
--------------------------------------------------------- */
add_action('woocommerce_update_options_price_adjustments', function () {
    $fields = [
        ['title'=>'Enable price increase','id'=>'pa_enable','type'=>'checkbox'],
        ['title'=>'Increase percentage','id'=>'pa_percentage','type'=>'number','css'=>'width:80px;','custom_attributes'=>['step'=>'0.1','min'=>'0']],
        ['title'=>'Affected categories','id'=>'pa_categories','type'=>'multiselect','class'=>'wc-enhanced-select','options'=>wp_list_pluck(get_terms(['taxonomy'=>'product_cat','hide_empty'=>false]),'name','term_id')],
        ['title'=>'Affected tags','id'=>'pa_tags','type'=>'multiselect','class'=>'wc-enhanced-select','options'=>wp_list_pluck(get_terms(['taxonomy'=>'product_tag','hide_empty'=>false]),'name','slug')],
        ['title'=>'Rounding','id'=>'pa_rounding','type'=>'select','options'=>['none'=>'None','up'=>'Round up','down'=>'Round down','nearest'=>'Round to nearest integer']],
        ['title'=>'Charm pricing','id'=>'pa_charm','type'=>'select','options'=>['none'=>'None','up'=>'Round up to .99','down'=>'Round down to .99']],
    ];

    woocommerce_update_options($fields);
});

/* ---------------------------------------------------------
   PRICE FILTER
--------------------------------------------------------- */
add_filter('woocommerce_product_get_price', 'pa_adjust_price', 10, 2);
add_filter('woocommerce_product_get_regular_price', 'pa_adjust_price', 10, 2);
add_filter('woocommerce_product_variation_get_price', 'pa_adjust_price', 10, 2);
add_filter('woocommerce_product_variation_get_regular_price', 'pa_adjust_price', 10, 2);

function pa_adjust_price($price, $product) {
    if (!$price) return $price;

    // Admin safety
    if (is_admin() && !defined('DOING_AJAX')) return $price;

    // Toggle
    if (get_option('pa_enable') !== 'yes') return $price;

    // Exclude custom / commission products
    if ($product->get_type() === 'custom' || $product->get_meta('_commission_key') || has_term('commission', 'product_cat', $product->get_id())) {
        return $price;
    }

    $allowed_cats = (array) get_option('pa_categories', []);
    $allowed_tags = (array) get_option('pa_tags', []);

    // Category restriction
    if ($allowed_cats && !has_term($allowed_cats, 'product_cat', $product->get_id())) {
        // Check tags if categories not matched
        if (!$allowed_tags || !has_term($allowed_tags, 'product_tag', $product->get_id())) {
            return $price;
        }
    }

    // Apply multiplier
    $percent = (float) get_option('pa_percentage', 25);
    $price *= 1 + ($percent / 100);

    // Rounding
    $rounding = get_option('pa_rounding', 'none');
    switch ($rounding) {
        case 'up': $price = ceil($price); break;
        case 'down': $price = floor($price); break;
        case 'nearest': $price = round($price); break;
    }

    // Charm pricing
    $charm = get_option('pa_charm', 'none');
    switch ($charm) {
        case 'up': $price = floor($price) + 0.99; break;
        case 'down': $price = ceil($price) - 0.01; break;
    }

    return round($price, 2);
}

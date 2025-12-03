<?php
/*
Plugin Name: monospace ART - Product Filter Page (Attributes + Availability FIXED Sold)
Description: Filter WooCommerce products by attributes (medium, surface, size, year, format) and availability, and display results live linking to related posts via _related_post_id. Fixed "Sold" availability.
Version: 1.5
Author: Hens Breet
*/

if(!defined('ABSPATH')) exit;

// ----------------------------
// 1. Shortcode for filter form and results container
// ----------------------------
add_shortcode('monospace_art_filter', 'ms_product_filter_shortcode');
function ms_product_filter_shortcode(){
    $attributes = ['pa_format','pa_medium','pa_surface','pa_size'];
    $availabilities = [
        ''          => 'All Availability',
        'available' => 'Available',
        'sold'      => 'Sold',
        'private'   => 'Artist\'s Private Collection',
        'gallery'   => 'At Gallery'
    ];

    $html = '<div class="ms-product-filter-container">';

    // Filter selects for attributes
    $html .= '<div class="ms-product-filter-form" style="margin-bottom:1em;">';
    foreach($attributes as $tax){
        $terms = get_terms(['taxonomy'=>$tax,'hide_empty'=>true]);
        if($terms && !is_wp_error($terms)){
            $label = ucfirst(str_replace('pa_','',$tax));
            $html .= "<select name='{$tax}' style='margin-right:.5em;' class='ms-filter-select'>";
            $html .= "<option value=''>All {$label}s</option>";
            foreach($terms as $term){
                $html .= "<option value='{$term->slug}'>{$term->name}</option>";
            }
            $html .= "</select>";
        }
    }

    // Availability filter (ACF field)
    $html .= "<select name='availability' style='margin-right:.5em;' class='ms-filter-select'>";
    foreach($availabilities as $value=>$label){
        $html .= "<option value='{$value}'>{$label}</option>";
    }
    $html .= "</select>";

    $html .= '</div>';

    // Results container
    $html .= '<div class="ms-product-grid" style="display:flex;flex-wrap:wrap;margin:-0.5em;"></div>';

    // Inline JS for live updates
    $html .= "<script>
        jQuery(document).ready(function($){
            function updateProducts(){
                var data = {action:'ms_filter_products'};
                $('.ms-filter-select').each(function(){ data[$(this).attr('name')] = $(this).val(); });
                $.get('".admin_url('admin-ajax.php')."', data, function(resp){
                    $('.ms-product-grid').html(resp);
                });
            }
            $('.ms-filter-select').on('change', updateProducts);
            updateProducts(); // initial load
        });
    </script>";

    return $html;
}

// ----------------------------
// 2. AJAX handler
// ----------------------------
add_action('wp_ajax_ms_filter_products','ms_filter_products_callback');
add_action('wp_ajax_nopriv_ms_filter_products','ms_filter_products_callback');

function ms_filter_products_callback(){
    $attributes = ['pa_medium','pa_surface','pa_size','pa_year','pa_format'];
    $tax_query = [];

    foreach($attributes as $tax){
        if(!empty($_GET[$tax])){
            $tax_query[] = ['taxonomy'=>$tax,'field'=>'slug','terms'=>sanitize_text_field($_GET[$tax])];
        }
    }

    $args = [
        'post_type'=>'product',
        'posts_per_page'=>999,
        'tax_query'=>$tax_query ?: [],
        'orderby'=>'date',
        'order'=>'DESC'
    ];

    // Get all products first (without availability filter)
    $all_products = get_posts($args);
    
    // Availability filter (ACF field + WooCommerce stock status)
    if(!empty($_GET['availability'])){
        $avail = sanitize_text_field($_GET['availability']);
        
        $filtered_products = [];
        foreach($all_products as $p){
            // Exclude custom orders/commissions
            $product_categories = wp_get_post_terms($p->ID, 'product_cat', ['fields'=>'slugs']);
            if(in_array('custom-orders', $product_categories) || in_array('commissions', $product_categories)){
                continue; // Skip this product
            }
            
            $product_availability = get_field('painting_availability_status', $p->ID);
            
            // Handle different possible formats
            if(is_array($product_availability)){
                $product_availability = isset($product_availability['value']) ? $product_availability['value'] : reset($product_availability);
            }
            
            // Get WooCommerce product object for stock status and price
            $wc_product = wc_get_product($p->ID);
            $stock_status = $wc_product ? $wc_product->get_stock_status() : '';
            $price = $wc_product ? $wc_product->get_price() : 0;
            
            $matches = false;
            
            if($avail === 'sold'){
                // Item is "sold" if:
                // 1. ACF field is explicitly "sold", OR
                // 2. WooCommerce stock status is "outofstock"
                if(strtolower(trim($product_availability)) === 'sold' || 
                   $stock_status === 'outofstock'){
                    $matches = true;
                }
            } elseif($avail === 'available'){
                // Item is "available" if:
                // 1. ACF field is "available", AND
                // 2. Stock status is "instock", AND
                // 3. Price is greater than zero
                if(strtolower(trim($product_availability)) === 'available' && 
                   $stock_status === 'instock' && 
                   !empty($price) && $price > 0){
                    $matches = true;
                }
            } else {
                // For other availability options, just check ACF field
                if(strtolower(trim($product_availability)) === strtolower(trim($avail))){
                    $matches = true;
                }
            }
            
            if($matches){
                $filtered_products[] = $p;
            }
        }
        
        $products = $filtered_products;
    } else {
        $products = $all_products;
        
        // Exclude custom orders/commissions even when no availability filter is selected
        $filtered_products = [];
        foreach($products as $p){
            $product_categories = wp_get_post_terms($p->ID, 'product_cat', ['fields'=>'slugs']);
            if(!in_array('custom-order', $product_categories) ){
                $filtered_products[] = $p;
            }
        }
        $products = $filtered_products;
    }

    if(!$products){ echo '<p>No products found.</p>'; wp_die(); }

    foreach($products as $p){
        $related_post_id = get_post_meta($p->ID,'_related_post_id',true);
        if(!$related_post_id) continue; // skip products with no linked post

        $thumb = get_the_post_thumbnail($p->ID,'medium',['style'=>'width:100%;display:block;border-radius:4px;']);
        $title = get_the_title($p->ID);
        $post_url = get_permalink($related_post_id);

        echo '<div class="ms-product-item" style="width:calc(33.333% - 1em);margin:.5em;box-sizing:border-box;">';
        echo "<a href='{$post_url}' style='text-decoration:none;color:inherit;'>";
        echo $thumb;
        echo "<div style='margin-top:.5em;font-weight:bold;font-size:14px;'>{$title}</div>";
        echo '</a></div>';
    }

    // Inline responsive CSS
    echo '<style>
        @media(max-width:768px){
            .ms-product-item{width:100% !important;margin:.5em 0;}
        }
    </style>';

    wp_die();
}
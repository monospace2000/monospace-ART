<?php
/*
Plugin Name: monospace ART - Product Filter Page (Attributes + Availability FIXED Sold)
Description: Filter WooCommerce products by attributes (medium, surface, size, year, format) and availability, with NOT logic, custom availability logic, and loading spinner.
Version: 2.0.0
Author: Hens Breet
*/

if(!defined('ABSPATH')) exit;

// =====================================================
//  SHORTCODE: Filter UI + Results
// =====================================================
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

    ob_start();

    ?>

<div class="ms-product-filter-container">

    <!-- FILTER BLOCK -->
    <div class="ms-product-filter-form" style="
        margin-bottom:1.5em;
        display:grid;
        grid-template-columns:auto 1fr auto;
        gap:0.5em 0.75em;
        align-items:center;
        position:relative;
    ">

    <!-- spinner -->
    <div class="ms-filter-spinner" style="
        display:none;
        position:absolute;
        top:50%;
        left:50%;
        transform:translate(-50%, -50%);
        height:48px;
        width:48px;
        z-index:9999;
    ">
        <span class="woocommerce-spinner ms-big-spinner" style="
            width:48px;
            height:48px;
            display:block;
        "></span>
    </div>


        <?php foreach($attributes as $tax):
            $terms = get_terms(['taxonomy'=>$tax,'hide_empty'=>true]);
            if(!$terms || is_wp_error($terms)) continue;
            $label = ucfirst(str_replace('pa_','',$tax));
        ?>

            <label style="font-weight:600;font-size:14px;white-space:nowrap;">
                <?= esc_html($label) ?>:
            </label>

            <select name="<?= esc_attr($tax) ?>" class="ms-filter-select" data-not-checkbox="<?= $tax ?>_not_checkbox" style="width:100%;">
                <option value="">All <?= esc_html($label) ?>s</option>
                <?php foreach($terms as $term): ?>
                    <option value="<?= esc_attr($term->slug) ?>"><?= esc_html($term->name) ?></option>
                <?php endforeach; ?>
            </select>

            <label style="font-size:12px;white-space:nowrap;margin:0;">
                <input type="checkbox"
                       id="<?= $tax ?>_not_checkbox"
                       name="<?= $tax ?>_not"
                       class="ms-filter-not"
                       style="margin:0 4px 0 0;vertical-align:middle;"
                       disabled>
                EXCLUDE
            </label>

        <?php endforeach; ?>

        <!-- AVAILABILITY -->
        <label style="font-weight:600;font-size:14px;white-space:nowrap;">Availability:</label>
        <select name="availability" class="ms-filter-select" data-not-checkbox="availability_not_checkbox" style="width:100%;">
            <?php foreach($availabilities as $value=>$label): ?>
                <option value="<?= esc_attr($value) ?>"><?= esc_html($label) ?></option>
            <?php endforeach; ?>
        </select>
        <label style="font-size:12px;white-space:nowrap;margin:0;">
            <input type="checkbox"
                   id="availability_not_checkbox"
                   name="availability_not"
                   class="ms-filter-not"
                   style="margin:0 4px 0 0;vertical-align:middle;"
                   disabled>
            EXCLUDE
        </label>
    </div>

    <!-- RESULTS GRID -->
    <div class="ms-product-grid" style="display:flex;flex-wrap:wrap;margin:-0.5em;"></div>

</div>

<!-- INLINE JS + CSS -->
<script>
jQuery(function($){

    let $form = $('.ms-product-filter-form');
    let $spinner = $('.ms-filter-spinner');
    let $grid = $('.ms-product-grid');

    function toggleNotCheckboxes(){
        $('.ms-filter-select').each(function(){
            var $select = $(this);
            var notID = $select.data('not-checkbox');
            var $not = $('#' + notID);
            if($select.val() === ''){
                $not.prop('disabled',true).prop('checked',false);
            } else {
                $not.prop('disabled',false);
            }
        });
    }

    function startLoading(){
        $form.css({'opacity':'0.5','pointer-events':'none'});
        $grid.css({'opacity':'0.4'});
        $spinner.show();
    }

    function stopLoading(){
        $form.css({'opacity':'1','pointer-events':'auto'});
        $grid.css({'opacity':'1'});
        $spinner.hide();
    }

    function updateProducts(){
        startLoading();

        var data = { action:'ms_filter_products' };
        $('.ms-filter-select').each(function(){
            data[$(this).attr('name')] = $(this).val();
        });
        $('.ms-filter-not').each(function(){
            if($(this).is(':checked') && !$(this).is(':disabled')){
                data[$(this).attr('name')] = '1';
            }
        });

        $.get('<?= admin_url("admin-ajax.php") ?>', data, function(resp){
            $grid.html(resp);
            stopLoading();
        });
    }

    $('.ms-filter-select').on('change', function(){
        toggleNotCheckboxes();
        updateProducts();
    });

    $('.ms-filter-not').on('change', updateProducts);

    toggleNotCheckboxes();
    updateProducts();
});
</script>

<style>
/* make WC spinner visible always */
.woocommerce-spinner {
    animation: spin 1s linear infinite;
    border: 4px solid #ccc;
    border-top-color: #444;
    border-radius: 50%;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}

.ms-big-spinner {
    border:6px solid #ccc !important;
    border-top-color:#000 !important;
    border-radius:50%;
    animation: msSpin 0.9s linear infinite !important;
    opacity:0.85;
}

@keyframes msSpin {
    to { transform: rotate(360deg); }
}

</style>

<?php
    return ob_get_clean();
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
            $is_not = !empty($_GET[$tax . '_not']);
            $tax_query[] = [
                'taxonomy' => $tax,
                'field' => 'slug',
                'terms' => sanitize_text_field($_GET[$tax]),
                'operator' => $is_not ? 'NOT IN' : 'IN'
            ];
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
        $is_not_availability = !empty($_GET['availability_not']);
        
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
            
            // Apply NOT logic if checkbox is checked
            if($is_not_availability){
                $matches = !$matches;
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
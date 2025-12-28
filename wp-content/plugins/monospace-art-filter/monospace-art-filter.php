<?php
/*
Plugin Name: monospace ART - Product Filter Page (Attributes + Availability + Price)
Description: Filter WooCommerce products by attributes (medium, surface, size, year, format), availability, and price, with NOT logic, custom availability logic, and loading spinner.
Version: 2.2.0
Author: Hens Breet
*/

if(!defined('ABSPATH')) exit;

// =====================================================
//  SHORTCODE: Filter UI + Results
// =====================================================
add_shortcode('monospace_art_filter', 'ms_product_filter_shortcode');
function ms_product_filter_shortcode(){

    // =========================
    //  ADMIN FILTER ACCESS
    // =========================
    $ms_admin_filter_access_enabled = true;
    $ms_required_admin_name = 'Hens';

    $ms_admin_mode = (
        $ms_admin_filter_access_enabled &&
        isset($_GET['admin'])
    );
    $ms_admin_mode = true;

    $attributes = ['pa_format','pa_medium','pa_surface','pa_size'];
    $availabilities = [
        ''          => 'All Availability',
        'available' => 'Available',
        'sold'      => 'Private Collection',
        'private'   => 'Artist\'s Private Collection',
        'gallery'   => 'At Gallery'
    ];

    // Get min/max prices for slider
    global $wpdb;
    $price_range = $wpdb->get_row("
        SELECT MIN(CAST(meta_value AS DECIMAL(10,2))) as min_price,
               MAX(CAST(meta_value AS DECIMAL(10,2))) as max_price
        FROM {$wpdb->postmeta}
        WHERE meta_key = '_price'
        AND meta_value != ''
        AND meta_value > 0
    ");

    $min_price = $price_range ? floor($price_range->min_price) : 0;
    $max_price = $price_range ? ceil($price_range->max_price) : 1000;

    ob_start();
    ?>

<div class="ms-product-filter-container">

   <style>
/* ---------------------------------------------
   SPINNER
----------------------------------------------*/
.woocommerce-spinner,
.ms-big-spinner {
    border-radius: 50%;
    border: 4px solid #ccc;
    animation: spin 1s linear infinite;
}
.ms-big-spinner {
    border-width: 6px;
    border-top-color: #000 !important;
    opacity: 0.85;
    animation: spin 0.9s linear infinite !important;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ---------------------------------------------
   FILTER FORM — GRID BASE
----------------------------------------------*/
.ms-product-filter-form {
    font-family: 'Open Sans', sans-serif;
    padding: 18px 20px;
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: 0.5em 0.75em;
    align-items: center;
    position: relative;
}
.ms-product-filter-form select{
    font-family: 'Open Sans', sans-serif;
}
/* ---------------------------------------------
   LABELS
----------------------------------------------*/
.ms-product-filter-form > label {
    font-size: 15px;
    font-weight: 700;
    white-space: nowrap;
    display: flex;
    align-items: center;
}

/* ---------------------------------------------
   SELECTS — NATIVE STYLING
----------------------------------------------*/
.ms-filter-select,
.ms-product-filter-form select {
    padding: 6px 10px;
    font-size: 14px;
    cursor: pointer;
}

/* ---------------------------------------------
   CHECKBOX LABEL
----------------------------------------------*/
.ms-filter-not {
    accent-color: #c00;
}

.ms-product-filter-form select + label {
    font-size: 12px;
    font-weight: 600;
    padding: 3px 8px;
    display: inline-flex;
    align-items: center;
    white-space: nowrap;
}

/* ---------------------------------------------
   ACTION BUTTONS
----------------------------------------------*/
.ms-filter-actions {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.5em;
    margin-bottom: 2em;
    margin-top: 2em;
}

.ms-filter-btn {
    padding: 8px 14px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

#ms-filter-count {
    font-size: 15px;
    font-weight: 700;
}

/* ---------------------------------------------
   SLIDER THUMBS
----------------------------------------------*/
#ms-price-range-container input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    background: #046BD2;
    border: 0px solid rgba(0,0,0,0.25);
    border-radius: 50%;
    cursor: pointer;
}

#ms-price-range-container input[type="range"]::-moz-range-thumb {
    width: 20px;
    height: 20px;
    background: #046BD2;
    border: 0px solid rgba(0,0,0,0.25);
    border-radius: 50%;
    cursor: pointer;
}

#ms-price-range-container input[type="range"]::-ms-thumb {
    width: 20px;
    height: 20px;
    background: #046BD2;
    border: 0px solid rgba(0,0,0,0.25);
    border-radius: 50%;
    cursor: pointer;
}
/* ---------------------------------------------
   RESULTS GRID – ALWAYS 3 COLUMNS
----------------------------------------------*/
.ms-product-grid {
    display: flex;
    flex-wrap: wrap;
    margin: -1em;
}


.ms-product-item {
    width: calc(33.333% - 2em);
    margin: 1em;
    position: relative;
    aspect-ratio: 1 / 1;       /* force square container */
    overflow: hidden;          /* Add this to clip overflowing content */
    border-radius: 5px;
    box-shadow: 0px 0px 8px rgba(0, 0, 0, 0.3) !important;
}
.ms-product-item a {
    display: block;
    height: 100%;
}

.ms-product-item > a > div:first-child {
    width: 100%;
    height: 100%;
    position: relative;
}

.ms-product-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.ms-availability-dot {
    position: absolute;
    bottom: 6px;
    left: 6px;
    width: 12px;
    height: 12px;
    background: #27c327;
    border-radius: 50%;
    border: 2px solid white;
}
.ms-availability-dot2 {
    display: inline-block;
    width: 12px;
    height: 12px;
    background: #27c327;
    border-radius: 50%;
    border: 2px solid white;
}

/* ---------------------------------------------
   RESULTS GRID – SMALL SCREENS (<640px)
----------------------------------------------*/
@media (max-width: 640px) {
    .ms-product-grid {
        margin: -1em; /* reduce outer negative margin for tighter spacing */
    }
    .ms-product-item {
        width: calc(50% - 2em); /* 2 items per row */
        margin: 1em;           /* smaller spacing */
        font-size: 0.7em;        /* slightly smaller type */
    }
}



/* ---------------------------------------------
   MOBILE — RESTRUCTURE LABELS + CONTROLS
----------------------------------------------*/
@media (max-width: 640px) {
    .ms-product-filter-form {
        grid-template-columns: 1fr !important;
        gap: 0.75em !important;
    }

    .ms-product-filter-form > label {
        grid-column: 1 / -1 !important;
        margin-bottom: 0.25em;
        font-size: 14px;
    }

    .ms-product-filter-form select {
        grid-column: 1 / -1 !important;
    }

    .ms-product-filter-form select + label {
        grid-column: 1 / -1 !important;
        margin-top: -0.5em;
        margin-left: 0;
    }

    .ms-filter-row {
        display: flex;
        align-items: center;
        gap: 10px;
        grid-column: 1 / -1 !important;
    }

    .ms-filter-row select {
        flex: 1;
        min-width: 0;
    }

    .ms-filter-row label {
        flex-shrink: 0;
        margin: 0 !important;
    }

    .ms-filter-actions {
        flex-direction: column;
    }
    #ms-filter-count {
        order: -1;
        width: 100%;
        text-align: center;
        margin-bottom: 8px;
    }
    .ms-filter-btn {
        width: 100%;
    }
}
</style>

    <!-- FILTER BLOCK -->
    <div class="ms-product-filter-form">

        <!-- Spinner -->
        <div class="ms-filter-spinner" style="display:none; position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); height:48px; width:48px; z-index:9999;">
            <span class="woocommerce-spinner ms-big-spinner" style="width:48px; height:48px; display:block;"></span>
        </div>

        <?php foreach($attributes as $tax):
            $terms = get_terms(['taxonomy'=>$tax,'hide_empty'=>true]);
            if(!$terms || is_wp_error($terms)) continue;
            $label = ucfirst(str_replace('pa_','',$tax));
        ?>
            <label><?= esc_html($label) ?>:</label>
            <select name="<?= esc_attr($tax) ?>" class="ms-filter-select" data-not-checkbox="<?= $tax ?>_not_checkbox">
                <option value="">All <?= esc_html($label) ?>s</option>
                <?php foreach($terms as $term): ?>
                    <option value="<?= esc_attr($term->slug) ?>"><?= esc_html($term->name) ?></option>
                <?php endforeach; ?>
            </select>
            <label>
                <input type="checkbox" id="<?= $tax ?>_not_checkbox" name="<?= $tax ?>_not" class="ms-filter-not" disabled>
                &nbsp;&nbsp;EXCLUDE
            </label>
        <?php endforeach; ?>

        <!-- AVAILABILITY -->
        <?php if ($ms_admin_mode): ?>
        <label>Availability:</label>
            <select name="availability" class="ms-filter-select" data-not-checkbox="availability_not_checkbox">
                <?php foreach($availabilities as $value=>$label): ?>
                    <option value="<?= esc_attr($value) ?>"><?= esc_html($label) ?></option>
                <?php endforeach; ?>
            </select>
            <label>
                <input type="checkbox" id="availability_not_checkbox" name="availability_not" class="ms-filter-not" disabled>
                &nbsp;&nbsp;EXCLUDE
            </label>
        <?php endif; ?>
    </div>

    <!-- PRICE RANGE SLIDER -->
     <?php if ($ms_admin_mode): ?>
        <div id="ms-price-range-container" style="display:none; margin-top:0em; padding:18px 20px;">

            <label style="display:block; font-weight:700; font-size:15px; margin-bottom:0.75em;">Price Range:</label>
            <label style="display:block; font-size:14px; margin-bottom:1em;">Min: <span id="ms-price-min-display" style="font-weight:600;">$<?= $min_price ?></span>
                <input type="range" id="ms-price-min" min="<?= $min_price ?>" max="<?= $max_price ?>" value="<?= $min_price ?>" style="width:100%; display:block; margin-top:0.5em;"></label>
            <label style="display:block; font-size:14px;">Max: <span id="ms-price-max-display" style="font-weight:600;">$<?= $max_price ?></span>
                <input type="range" id="ms-price-max" min="<?= $min_price ?>" max="<?= $max_price ?>" value="<?= $max_price ?>" style="width:100%; display:block; margin-top:0.5em;"></label>

        </div>
    <?php endif; ?>

    <!-- ACTION BUTTONS -->
    <div style="padding:18px 20px;">A green dot <span class="ms-availability-dot2"></span> indicates item availability.</div>
    <div class="ms-filter-actions">
        <button type="button" id="ms-reverse-order" class="ms-filter-btn">Newest First</button>
        <div id="ms-filter-count">Found 0 items</div>
        <button type="button" id="ms-reset-filters" class="ms-filter-btn">Reset All</button>
    </div>

    <!-- RESULTS GRID -->
    <div class="ms-product-grid"></div>

</div>

<!-- INLINE JS -->
<script>
jQuery(function($){

    function loadFiltersFromURL() {
        const urlParams = new URLSearchParams(window.location.search);

        $('.ms-filter-select').each(function(){
            const name = $(this).attr('name');
            const value = urlParams.get(name);
            if(value) {
                $(this).val(value);
            }

            const notParam = urlParams.get(name + '_not');
            if(notParam === '1') {
                const notID = $(this).data('not-checkbox');
                $('#' + notID).prop('checked', true);
            }
        });

        if(adminMode) {
            const priceMin = urlParams.get('price_min');
            const priceMax = urlParams.get('price_max');
            if(priceMin) $priceMin.val(priceMin);
            if(priceMax) $priceMax.val(priceMax);
        }

        const order = urlParams.get('order');
        if(order === 'ASC') {
            reversed = true;
            $('#ms-reverse-order').text('Oldest First');
        }

        toggleNotCheckboxes();
        togglePriceSlider();
        updatePriceDisplay();
        updateButtonStates();
    }

    function updateURL() {
        const params = new URLSearchParams();

        const currentParams = new URLSearchParams(window.location.search);
        if(currentParams.has('admin')) {
            params.set('admin', currentParams.get('admin'));
        }

        $('.ms-filter-select').each(function(){
            const name = $(this).attr('name');
            const value = $(this).val();
            if(value) {
                params.set(name, value);
            }
        });

        $('.ms-filter-not').each(function(){
            if($(this).is(':checked') && !$(this).is(':disabled')){
                params.set($(this).attr('name'), '1');
            }
        });

        // Only add price params if availability is 'available' AND NOT excluded
        const availability = $('select[name="availability"]').val();
        const isExcluded = $('#availability_not_checkbox').is(':checked');

        if(adminMode && availability === 'available' && !isExcluded) {
            if($priceMin.val() != minPrice) {
                params.set('price_min', $priceMin.val());
            }
            if($priceMax.val() != maxPrice) {
                params.set('price_max', $priceMax.val());
            }
        }

        if(reversed) {
            params.set('order', 'ASC');
        }

        const newURL = params.toString()
            ? window.location.pathname + '?' + params.toString()
            : window.location.pathname;

        window.history.pushState({}, '', newURL);
    }

    const adminMode = <?= $ms_admin_mode ? 'true' : 'false' ?>;

    if (!adminMode) {
        togglePriceSlider = function(){};
        $('#ms-price-range-container').remove();
    }

    let $form = $('.ms-product-filter-form');
    let $spinner = $('.ms-filter-spinner');
    let $grid = $('.ms-product-grid');
    let $priceMin = $('#ms-price-min');
    let $priceMax = $('#ms-price-max');
    let $minDisplay = $('#ms-price-min-display');
    let $maxDisplay = $('#ms-price-max-display');

    const minPrice = parseInt($priceMin.attr('min')) || 0;
    const maxPrice = parseInt($priceMax.attr('max')) || 1000;

    let reversed = false;

    function updateButtonStates(){
        $('#ms-reverse-order').toggleClass('active', reversed);

        let anyFilterActive = false;
        $('.ms-product-filter-form select').each(function(){
            if($(this).val() !== '') anyFilterActive = true;
        });
        $('.ms-filter-not').each(function(){
            if($(this).is(':checked')) anyFilterActive = true;
        });

        if(adminMode && $('#ms-price-range-container').is(':visible')) {
            if($priceMin.val() != minPrice || $priceMax.val() != maxPrice) anyFilterActive = true;
        }

        $('#ms-reset-filters').prop('disabled', !anyFilterActive);
    }

    $('#ms-reverse-order').on('click', function(){
        reversed = !reversed;
        $(this).text(reversed ? 'Oldest First' : 'Newest First');
        updateButtonStates();
        updateProducts();
    });

    $('#ms-reset-filters').on('click', function(){
        $('.ms-product-filter-form select').val('');
        $('.ms-filter-not').prop('checked', false).prop('disabled', true);
        $priceMin.val(minPrice);
        $priceMax.val(maxPrice);
        updatePriceDisplay();
        toggleNotCheckboxes();
        togglePriceSlider();
        reversed = false;
        $('#ms-reverse-order').text('Newest First');
        updateButtonStates();

        const currentParams = new URLSearchParams(window.location.search);
        const newURL = currentParams.has('admin')
            ? window.location.pathname + '?admin=' + currentParams.get('admin')
            : window.location.pathname;
        window.history.pushState({}, '', newURL);

        updateProducts();
    });

    $('.ms-filter-select, .ms-filter-not').on('change', function(){
        updateButtonStates();
    });

    $priceMin.on('input change', updateButtonStates);
    $priceMax.on('input change', updateButtonStates);

    updateButtonStates();

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



    function togglePriceSlider() {
        if (!adminMode) return;

        const availability = $('select[name="availability"]').val();
        const isExcluded = $('#availability_not_checkbox').is(':checked');

        // Only show when availability is 'available' AND NOT excluded
        const show = (availability === 'available' && !isExcluded);
        const el = $('#ms-price-range-container');

        if (show) {
            el.show();
        } else {
            el.hide();
        }
    }

    function updatePriceDisplay(){
        let min = parseInt($priceMin.val());
        let max = parseInt($priceMax.val());

        if(min >= max) min = max - 1;
        if(max <= min) max = min + 1;

        $priceMin.val(min);
        $priceMax.val(max);

        $minDisplay.text('$' + min);
        $maxDisplay.text('$' + max);
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

        var data = { action:'ms_filter_products', order: reversed ? 'ASC' : 'DESC' };
        $('.ms-filter-select').each(function(){
            data[$(this).attr('name')] = $(this).val();
        });
        $('.ms-filter-not').each(function(){
            if($(this).is(':checked') && !$(this).is(':disabled')){
                data[$(this).attr('name')] = '1';
            }
        });

        // Only send price data if availability is 'available' AND NOT excluded
        const availability = $('select[name="availability"]').val();
        const isExcluded = $('#availability_not_checkbox').is(':checked');

        if(adminMode && availability === 'available' && !isExcluded) {
            data['price_min'] = $priceMin.val();
            data['price_max'] = $priceMax.val();
        }

        $.ajax({
            url: '<?= admin_url("admin-ajax.php") ?>',
            data: data,
            dataType: 'json',
            method: 'GET',
            success: function(resp){
                $grid.html(resp.html);
                $('#ms-filter-count').text(`Found ${resp.count} items`);
                stopLoading();
                updateURL();
            },
            error: function(){
                $grid.html('<p>Error loading items.</p>');
                stopLoading();
            }
        });
    }

    $('.ms-filter-select').on('change', function(){
        toggleNotCheckboxes();
        togglePriceSlider();
        updateProducts();
    });

    $('.ms-filter-not').on('change', function(){
        togglePriceSlider();  // <-- Added this call
        updateProducts();
    });

    $priceMin.on('input change', function(){ updatePriceDisplay(); });
    $priceMax.on('input change', function(){ updatePriceDisplay(); });
    $priceMin.on('change', updateProducts);
    $priceMax.on('change', updateProducts);

    toggleNotCheckboxes();
    togglePriceSlider();
    updatePriceDisplay();
    loadFiltersFromURL();
    updateProducts();
});
</script>

<?php
    return ob_get_clean();
}

// ----------------------------
// AJAX handler
// ----------------------------
function ms_filter_products_callback() {
    $attributes = ['pa_medium','pa_surface','pa_size','pa_year','pa_format'];

    $all_products = get_posts([
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    $total_count = count($all_products);

    $normal_products = array_filter($all_products, function($p_id){
        $product_categories = wp_get_post_terms($p_id, 'product_cat', ['fields'=>'slugs']);
        $linked_post_id = get_post_meta($p_id, '_related_post_id', true);
        return !in_array('custom-order', $product_categories)
            && !in_array('commissions', $product_categories)
            && !empty($linked_post_id);
    });

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

    $avail = !empty($_GET['availability']) ? sanitize_text_field($_GET['availability']) : '';
    $is_not_availability = !empty($_GET['availability_not']);

    // Apply price filter ONLY when:
    // 1. availability is 'available'
    // 2. AND NOT excluded
    // 3. AND price params are provided
    $meta_query = [];
    if ($avail === 'available' && !$is_not_availability && !empty($_GET['price_min']) && !empty($_GET['price_max'])) {
        $meta_query[] = [
            'key' => '_price',
            'value' => [floatval($_GET['price_min']), floatval($_GET['price_max'])],
            'type' => 'NUMERIC',
            'compare' => 'BETWEEN'
        ];
    }


    $args = [
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'tax_query'      => $tax_query ?: [],
        'meta_query'     => $meta_query ?: [],
        'orderby'        => 'date',
        'order'          => (!empty($_GET['order']) && $_GET['order'] === 'ASC') ? 'ASC' : 'DESC',
        'fields'         => 'ids',
        'post__in'       => $normal_products,
    ];

    $filtered_ids = get_posts($args);

   $products = array_filter($filtered_ids, function($p_id) use ($avail, $is_not_availability) {

    // --- Get availability field ---
    $product_availability = get_field('painting_availability_status', $p_id);
    if (is_array($product_availability)) {
        $product_availability = isset($product_availability['value'])
            ? $product_availability['value']
            : reset($product_availability);
    }
    $product_availability = strtolower(trim($product_availability));

    // --- WooCommerce product data ---
    $wc_product = wc_get_product($p_id);
    $stock_status = $wc_product ? $wc_product->get_stock_status() : '';

    // --- Core status flags ---
    $is_private = $product_availability === 'private';
    $is_gallery = $product_availability === 'gallery';
    $is_instock = $stock_status === 'instock';
    $is_outofstock = $stock_status === 'outofstock';

    // --- Clear availability definitions ---
    // Available: In stock AND NOT private
    $is_available = $is_instock && !$is_private;

    // Not Available: Out of stock OR private
    $is_not_available = $is_outofstock || $is_private;

    // Sold: Out of stock AND NOT private
    $is_sold = $is_outofstock && !$is_private;

    // Not Sold: In stock OR private (regardless of stock)
    $is_not_sold = $is_instock || $is_private;

    // --- Determine matches based on selection ---
    $matches = false;

    switch (strtolower($avail)) {
        case 'available':
            $matches = $is_available;
            break;
        case 'sold':
            $matches = $is_sold;
            break;
        case 'private':
            $matches = $is_private;
            break;
        case 'gallery':
            $matches = $is_gallery;
            break;
        case '':
            $matches = true; // All
            break;
        default:
            $matches = $product_availability === strtolower($avail);
    }

    // --- Handle NOT checkbox (EXCLUDE) ---
    if ($is_not_availability) {
        $matches = !$matches;
    }

    return $matches;
});



    $product_html = '';
    foreach ($products as $p_id) {
        $related_post_id = get_post_meta($p_id, '_related_post_id', true);
        if (!$related_post_id) continue;

        $product_availability = get_field('painting_availability_status', $p_id);
        if (is_array($product_availability)) {
            $product_availability = isset($product_availability['value'])
                ? $product_availability['value']
                : reset($product_availability);
        }
        $product_availability = strtolower(trim($product_availability));

        $wc_product   = wc_get_product($p_id);
        $stock_status = $wc_product ? $wc_product->get_stock_status() : '';
        $price        = $wc_product ? $wc_product->get_price() : 0;

        // --- UPDATED AVAILABILITY DOT LOGIC ---
        $is_private_status = $product_availability === 'private';
        $is_gallery_status = $product_availability === 'gallery';
        $is_instock = $stock_status === 'instock';

        // AVAILABLE: Is Gallery OR (NOT Private AND Is Instock)
        $is_available_dot = $is_gallery_status || (!$is_private_status && $is_instock);


        $thumb = get_the_post_thumbnail($p_id, 'medium');

        $title    = get_the_title($p_id);
        $post_url = get_permalink($related_post_id);

        $product_html .= '<div class="ms-product-item">';
        $product_html .= "<a href='{$post_url}' style='text-decoration:none;color:inherit;'>";
        $product_html .= '<div style="position:relative;">';
        $product_html .= $thumb;

        if ($is_available_dot) {
            $product_html .= '<span class="ms-availability-dot"></span>';
        }

        $product_html .= '</div>';
        $product_html .= "<div style='margin-top:.5em;font-weight:bold;font-size:14px;'>{$title}</div>";
        $product_html .= "</a></div>";
    }

    if(empty($product_html)){
        $product_html = '<p style="margin: 0 auto">No matches.</p>';
    }

    echo wp_json_encode([
        'html'  => $product_html,
        'count' => count($products),
        'total' => $total_count,
    ]);

    wp_die();
}

add_action('wp_ajax_ms_filter_products','ms_filter_products_callback');
add_action('wp_ajax_nopriv_ms_filter_products','ms_filter_products_callback');
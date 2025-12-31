<?php
/**
 * Sale Price Switch – Global / Taxonomy / Product Override + Visual Control
 * Recursion-safe + per-product caching
 *
 * Drop-in file
 */

if (!defined('ABSPATH')) exit;

/* ============================================================
 * CONFIGURATION (SCRIPT-LEVEL)
 * ============================================================ */

/**
 * Sale mode:
 * 'global'   → sales enabled everywhere except exclusions
 * 'taxonomy' → sales enabled only for allowed taxonomies
 */
define('SALE_MODE', 'taxonomy'); // 'global' or 'taxonomy'

/**
 * Global mode exclusions
 */
define('SALE_EXCLUDED_CATEGORIES', ['large_format']); // example
define('SALE_EXCLUDED_TAGS', []);

/**
 * Taxonomy mode inclusions
 */
//define('SALE_ALLOWED_CATEGORIES', ['miniatures']); // example
define('SALE_ALLOWED_CATEGORIES', []);
define('SALE_ALLOWED_TAGS', []);

/**
 * Default visual behavior (crossed-out regular price)
 * true  = show crossed-out price globally
 * false = hide crossed-out price globally
 */
define('SALE_SHOW_ORIGINAL_PRICE', true);

/* ============================================================
 * META KEYS (PRODUCT OVERRIDES)
 * ============================================================ */

define('META_ENABLE_SALE', '_enable_sale_price');
define('META_SHOW_ORIGINAL', '_show_original_price');

/* ============================================================
 * CENTRAL SALE ELIGIBILITY LOGIC WITH CACHING
 * ============================================================ */

function sale_allowed_by_rules($product) {

    static $cache = [];

    if (!$product || !$product->get_sale_price()) {
        return false;
    }

    $id = $product->get_id();
    if (isset($cache[$id])) return $cache[$id];

    $allowed = false;

    if (SALE_MODE === 'global') {
        $allowed = true;

        if (!empty(SALE_EXCLUDED_CATEGORIES) &&
            has_term(SALE_EXCLUDED_CATEGORIES, 'product_cat', $id)) {
            $allowed = false;
        }

        if (!empty(SALE_EXCLUDED_TAGS) &&
            has_term(SALE_EXCLUDED_TAGS, 'product_tag', $id)) {
            $allowed = false;
        }
    }

    if (SALE_MODE === 'taxonomy') {
        $allowed = false;

        if (!empty(SALE_ALLOWED_CATEGORIES) &&
            has_term(SALE_ALLOWED_CATEGORIES, 'product_cat', $id)) {
            $allowed = true;
        }

        if (!empty(SALE_ALLOWED_TAGS) &&
            has_term(SALE_ALLOWED_TAGS, 'product_tag', $id)) {
            $allowed = true;
        }
    }

    $cache[$id] = $allowed;
    return $allowed;
}

/* ============================================================
 * PRODUCT EDITOR UI
 * ============================================================ */

add_action('woocommerce_product_options_general_product_data', function () {

    global $post;
    $product = wc_get_product($post->ID);

    if (!$product || !$product->get_sale_price()) {
        return;
    }

    echo '<div class="options_group">';

    woocommerce_wp_checkbox([
        'id'          => META_ENABLE_SALE,
        'label'       => __('Enable sale (override rules)', 'woocommerce'),
        'description' => __('Forces this product on sale regardless of global or taxonomy rules.', 'woocommerce'),
    ]);

    woocommerce_wp_checkbox([
        'id'          => META_SHOW_ORIGINAL,
        'label'       => __('Show original price', 'woocommerce'),
        'description' => __('Overrides global visual sale display (crossed-out regular price).', 'woocommerce'),
    ]);

    echo '</div>';
});

/* ============================================================
 * SAVE PRODUCT META
 * ============================================================ */

add_action('woocommerce_process_product_meta', function ($post_id) {

    update_post_meta(
        $post_id,
        META_ENABLE_SALE,
        isset($_POST[META_ENABLE_SALE]) ? 'yes' : 'no'
    );

    update_post_meta(
        $post_id,
        META_SHOW_ORIGINAL,
        isset($_POST[META_SHOW_ORIGINAL]) ? 'yes' : 'no'
    );
});

/* ============================================================
 * SALE ENFORCEMENT
 * ============================================================ */

add_filter('woocommerce_product_is_on_sale', function ($on_sale, $product) {

    static $running = false;
    if ($running) return $on_sale;
    $running = true;

    if (!$product->get_sale_price()) {
        $running = false;
        return false;
    }

    // Product override
    if (get_post_meta($product->get_id(), META_ENABLE_SALE, true) === 'yes') {
        $running = false;
        return true;
    }

    $on_sale = sale_allowed_by_rules($product);
    $running = false;
    return $on_sale;

}, 10, 2);

add_filter('woocommerce_product_get_sale_price', function ($sale_price, $product) {

    static $running = false;
    if ($running) return $sale_price;
    $running = true;

    if (!$product->get_sale_price()) {
        $running = false;
        return '';
    }

    // Product override
    if (get_post_meta($product->get_id(), META_ENABLE_SALE, true) === 'yes') {
        $running = false;
        return $sale_price;
    }

    $sale_price = sale_allowed_by_rules($product) ? $sale_price : '';
    $running = false;
    return $sale_price;

}, 10, 2);

/* ============================================================
 * FRONTEND VISUAL CONTROL
 * ============================================================ */

add_filter('woocommerce_get_price_html', function ($price_html, $product) {

    static $running = false;
    if ($running) return $price_html;
    $running = true;

    if (!$product->get_sale_price()) {
        $running = false;
        return $price_html;
    }

    // Product override
    $show_original = get_post_meta($product->get_id(), META_SHOW_ORIGINAL, true);

    if ($show_original === '') {
        // No per-product override → use global default
        $show_original = SALE_SHOW_ORIGINAL_PRICE ? 'yes' : 'no';
    }

    if ($show_original !== 'yes') {
        // Remove crossed-out regular price
        $price_html = preg_replace('#<del.*?</del>#', '', $price_html);
    }

    $running = false;
    return $price_html;

}, 20, 2);

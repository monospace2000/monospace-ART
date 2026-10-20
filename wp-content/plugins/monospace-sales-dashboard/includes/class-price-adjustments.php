<?php
/**
 * Price Adjustments - Global percentage increase/decrease
 * Based on your category & tag price multiplier logic
 */

if (!defined('ABSPATH')) exit;

class MSD_Price_Adjustments {

    public static function init() {
        // Only run if enabled
        if (get_option('msd_price_adj_enable') !== 'yes') {
            return;
        }

        add_filter('woocommerce_product_get_price', [__CLASS__, 'adjust_price'], 99, 2);
        add_filter('woocommerce_product_get_regular_price', [__CLASS__, 'adjust_price'], 99, 2);
        add_filter('woocommerce_product_variation_get_price', [__CLASS__, 'adjust_price'], 99, 2);
        add_filter('woocommerce_product_variation_get_regular_price', [__CLASS__, 'adjust_price'], 99, 2);

        // The price a customer pays, for anything that asks Core (monospace_shop_price() - Artwork Sheets), in
        // wp-admin too (2026-10-19)
        add_filter('monospace_shop_price', [__CLASS__, 'calculate'], 10, 2);
    }

    /**
     * Adjust price based on settings
     */
    public static function adjust_price($price, $product) {
        if (!$price) return $price;

        // Admin safety
        if (is_admin() && !defined('DOING_AJAX')) {
            return $price;
        }

        // Skip if volume discount has already set this product's price
        if (!empty(MSD_Volume_Discounts::$discounted_product_ids[$product->get_id()])) {
            return $price;
        }

        return self::calculate($price, $product);
    }

    /**
     * The adjusted price - the rules themselves, without the admin / volume-discount checks of adjust_price()
     * (also Core's monospace_shop_price filter)
     */
    public static function calculate($price, $product) {
        if (!$price || !$product) return $price;

        // Global enable check
        if (get_option('msd_global_enable') !== 'yes') {
            return $price;
        }

        $product_id = $product->get_id();

        // Check product-level exclusion first
        if (MSD_Product_Overrides::is_excluded_from_price_adj($product_id)) {
            return $price;
        }

        // Exclude commission products (from your original logic)
        if ($product->get_type() === 'custom' ||
            $product->get_meta('_commission_key') ||
            has_term('commission', 'product_cat', $product_id)) {
            return $price;
        }

        // Check category exclusions
        $exclude_cats = (array) get_option('msd_price_adj_exclude_cats', []);
        if (!empty($exclude_cats) && has_term($exclude_cats, 'product_cat', $product_id)) {
            return $price;
        }

        // Check tag exclusions
        $exclude_tags = (array) get_option('msd_price_adj_exclude_tags', []);
        if (!empty($exclude_tags) && has_term($exclude_tags, 'product_tag', $product_id)) {
            return $price;
        }

        // Check if product qualifies (if categories are specified)
        $allowed_cats = (array) get_option('msd_price_adj_categories', []);
        $allowed_tags = (array) get_option('msd_price_adj_tags', []);

        // If categories/tags are specified, product must match at least one
        if (!empty($allowed_cats) || !empty($allowed_tags)) {
            $matches = false;

            if (!empty($allowed_cats) && has_term($allowed_cats, 'product_cat', $product_id)) {
                $matches = true;
            }

            if (!$matches && !empty($allowed_tags) && has_term($allowed_tags, 'product_tag', $product_id)) {
                $matches = true;
            }

            if (!$matches) {
                return $price;
            }
        }

        // Apply global percentage adjustment
        $percentage = (float) get_option('msd_price_adj_percentage', 25);
        $price *= 1 + ($percentage / 100);

        // Apply per-product additional percentage (if set)
        $product_percentage = get_post_meta($product_id, '_msd_additional_percentage', true);
        if ($product_percentage !== '' && is_numeric($product_percentage)) {
            $product_percentage = (float) $product_percentage;
            $price *= 1 + ($product_percentage / 100);
        }

        // Apply rounding
        $rounding = get_option('msd_rounding', 'none');
        switch ($rounding) {
            case 'up':
                $price = ceil($price);
                break;
            case 'down':
                $price = floor($price);
                break;
            case 'nearest':
                $price = round($price);
                break;
        }

        // Apply charm pricing
        $charm = get_option('msd_charm_pricing', 'none');
        switch ($charm) {
            case 'up':
                $price = floor($price) + 0.99;
                break;
            case 'down':
                $price = ceil($price) - 0.01;
                break;
        }

        return round($price, wc_get_price_decimals());
    }
}
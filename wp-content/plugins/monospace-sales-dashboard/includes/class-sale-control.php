<?php
/**
 * Sale Control - a sale price typed on a product
 *
 * A sale price stored on a product always counts and shows crossed out (owner, 2026-11-08: #351 charged its old $49 with no
 * strike-through once the schedule had ended). Campaigns (class-campaigns.php) are separate. Only the product's own
 * switch can turn its sale off, and "Show original price" (global, per product) can hide the crossed-out price.
 */

if (!defined('ABSPATH')) exit;

class MSD_Sale_Control {

    public static function init() {
        // Only run if global system is enabled
        if (get_option('msd_global_enable') !== 'yes') {
            return;
        }

        add_filter('woocommerce_product_is_on_sale', [__CLASS__, 'filter_is_on_sale'], 10, 2);
        add_filter('woocommerce_product_get_sale_price', [__CLASS__, 'filter_sale_price'], 10, 2);
        add_filter('woocommerce_product_variation_get_sale_price', [__CLASS__, 'filter_sale_price'], 10, 2);
        add_filter('woocommerce_get_price_html', [__CLASS__, 'filter_price_html'], 20, 2);
    }

    /** Product override "Enable sale": 'no' turns the stored sale price off for this product */
    private static function switched_off($product) {
        return get_post_meta($product->get_id(), '_msd_enable_sale', true) === 'no';
    }

    public static function filter_is_on_sale($on_sale, $product) {
        return $on_sale && !self::switched_off($product);
    }

    public static function filter_sale_price($sale_price, $product) {
        return self::switched_off($product) ? '' : $sale_price;
    }

    /** Remove the crossed-out regular price when "Show original price" is off */
    public static function filter_price_html($price_html, $product) {
        static $running = false;
        if ($running) return $price_html;
        $running = true;
        $has_sale = $product->get_sale_price();
        $running = false;
        if (!$has_sale) return $price_html;

        $show_original = get_post_meta($product->get_id(), '_msd_show_original', true);
        if ($show_original === '') $show_original = get_option('msd_show_original_price', 'yes');
        return $show_original !== 'yes' ? preg_replace('#<del.*?</del>#', '', $price_html) : $price_html;
    }
}

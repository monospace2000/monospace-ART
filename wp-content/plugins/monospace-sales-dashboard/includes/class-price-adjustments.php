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

        // The adjustment follows the Schedule (Sales Dashboard > Schedule: one-time dates, recurring days), like the sale
        // prices do (2026-10-29, "13% off for Halloween"). A variable product's cached price range knows whether it is
        // running, and the page caches are cleared when it starts and when it ends.
        add_filter('woocommerce_get_variation_prices_hash', function ($hash) {
            $hash['msd_adj'] = self::scheduled() ? 1 : 0;
            return $hash;
        });
        // A discount shows as a crossed-out price (2026-10-30, "show the Halloween 13% in the prices"): the adjustment changes the
        // price itself, so WooCommerce sees no sale - this puts the unadjusted price next to it, like a sale price
        add_filter('woocommerce_get_price_html', [__CLASS__, 'price_html'], 25, 2);
        add_action('msd_price_schedule_flip', [__CLASS__, 'flip']);
        add_action('init', [__CLASS__, 'plan_flips'], 30);
    }

    /**
     * The price HTML with the unadjusted price crossed out, while a discount is running: "<del>$45</del> $39", for a variable
     * product (the prints) the ranges - "<del>$45 - $55</del> $39 - $48". Only when every price is lower than before; a
     * product on a WooCommerce sale already crosses out its own price. Follows "Show original price" (Settings, and the
     * product's own override) like a sale price does.
     */
    public static function price_html($html, $product) {
        static $busy = false;
        if ($busy || !$product || (is_admin() && !wp_doing_ajax())) return $html;
        if (get_option('msd_global_enable') !== 'yes' || !self::scheduled()) return $html;
        if (!function_exists('wc_format_sale_price') || $product->is_on_sale()) return $html;

        $id   = $product->is_type('variation') && $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
        $show = get_post_meta($id, '_msd_show_original', true);
        if ($show === '') $show = get_option('msd_show_original_price', 'yes');
        if ($show !== 'yes') return $html;
        if (!empty(MSD_Volume_Discounts::$discounted_product_ids[$product->get_id()])) return $html;

        $busy = true;
        $raw = $adj = [];
        $items = $product->is_type('variable') ? array_filter(array_map('wc_get_product', $product->get_children())) : [$product];
        foreach ($items as $item) {
            if ($item->is_type('variation') && !$item->variation_is_visible()) continue;
            $before = (float) $item->get_regular_price('edit'); // 'edit' = without the filters
            $after  = (float) self::calculate($before, $item);
            if ($before <= 0 || $after >= $before) { $busy = false; return $html; }
            $raw[] = $before;
            $adj[] = $after;
        }
        $busy = false;
        if (!$raw) return $html;

        $range = fn($v) => min($v) === max($v) ? wc_price(min($v)) : wc_format_price_range(min($v), max($v));
        return wc_format_sale_price($range($raw), $range($adj)) . $product->get_price_suffix();
    }

    /** Is the adjustment inside its schedule (always, when there is none)? */
    public static function scheduled() {
        return !class_exists('MSD_Sale_Control') || MSD_Sale_Control::check_schedule();
    }

    /** One cron event at the schedule's start and at its end (site time), once each */
    public static function plan_flips() {
        if (get_option('msd_schedule_enable') !== 'yes') return;
        foreach (['msd_schedule_start', 'msd_schedule_end'] as $opt) {
            $when = trim((string) get_option($opt, ''));
            $date = $when !== '' ? date_create($when, wp_timezone()) : false;
            if (!$date) continue;
            $ts = $date->getTimestamp();
            if ($ts > time() && !wp_next_scheduled('msd_price_schedule_flip', [$ts])) {
                wp_schedule_single_event($ts + 5, 'msd_price_schedule_flip', [$ts]);
            }
        }
    }

    /** The schedule just started or ended: show the right prices on cached pages */
    public static function flip() {
        if (function_exists('wc_delete_product_transients')) {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wc\_var\_prices\_%' OR option_name LIKE '\_transient\_timeout\_wc\_var\_prices\_%'");
        }
        if (function_exists('sg_cachepress_purge_cache')) sg_cachepress_purge_cache();
        wp_cache_flush();
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

        // Outside the schedule: the regular price
        if (!self::scheduled()) {
            return $price;
        }

        // A variation has no categories or tags of its own - its product's count (the print cards price their sizes and frames
        // as variations, so the category rules never reached them)
        $product_id = $product->is_type('variation') && $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();

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
<?php
/**
 * Price Adjustments - Global percentage increase/decrease
 * Based on your category & tag price multiplier logic
 */

if (!defined('ABSPATH')) exit;

class MSD_Price_Adjustments {

    public static function init() {
        // Only run if enabled: campaigns decide per product (MSD_Campaigns), this is the switch for all of them
        if (get_option('msd_global_enable') !== 'yes') {
            return;
        }

        add_filter('woocommerce_product_get_price', [__CLASS__, 'adjust_price'], 99, 2);
        add_filter('woocommerce_product_get_regular_price', [__CLASS__, 'adjust_price'], 99, 2);
        add_filter('woocommerce_product_variation_get_price', [__CLASS__, 'adjust_price'], 99, 2);
        add_filter('woocommerce_product_variation_get_regular_price', [__CLASS__, 'adjust_price'], 99, 2);

        // The price a customer pays, for anything that asks Core (monospace_shop_price() - Artwork Sheets), in
        // wp-admin too (2026-10-19)
        add_filter('monospace_shop_price', [__CLASS__, 'calculate'], 10, 2);

        // A variable product's cached price range knows which campaigns are running (and their percentages)
        add_filter('woocommerce_get_variation_prices_hash', function ($hash) {
            $hash['msd_adj'] = MSD_Campaigns::signature();
            return $hash;
        });
        // A discount shows as a crossed-out price (2026-10-30, "show the Halloween 13% in the prices"): the adjustment changes the
        // price itself, so WooCommerce sees no sale - this puts the unadjusted price next to it, like a sale price
        add_filter('woocommerce_get_price_html', [__CLASS__, 'price_html'], 25, 2);
    }

    /**
     * The price HTML with the unadjusted price crossed out, while a discount is running: "<del>$45</del> $39", for a variable
     * product (the prints) the ranges - "<del>$45 - $55</del> $39 - $48". Only when every price is lower than before; a
     * product on a WooCommerce sale already crosses out its own price. Follows "Show original price" (Settings, the
     * campaign's own choice, and the product's own override, in that order of strength) like a sale price does.
     */
    public static function price_html($html, $product) {
        static $busy = false;
        if ($busy || !$product || (is_admin() && !wp_doing_ajax())) return $html;
        if (get_option('msd_global_enable') !== 'yes' || !MSD_Campaigns::running()) return $html;
        if (!function_exists('wc_format_sale_price') || $product->is_on_sale()) return $html;

        $id   = $product->is_type('variation') && $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
        if (!empty(MSD_Volume_Discounts::$discounted_product_ids[$product->get_id()])) return $html;

        $busy = true;
        $raw = $adj = [];
        $shows = [];
        $items = $product->is_type('variable') ? array_filter(array_map('wc_get_product', $product->get_children())) : [$product];
        foreach ($items as $item) {
            if ($item->is_type('variation') && !$item->variation_is_visible()) continue;
            $before = (float) $item->get_regular_price('edit'); // 'edit' = without the filters
            list($after, $campaign) = self::calculate_with_campaign($before, $item);
            if ($before <= 0 || $after >= $before) { $busy = false; return $html; }
            $raw[] = $before;
            $adj[] = $after;
            $shows[] = $campaign ? $campaign['show_original'] : '';
        }
        $busy = false;
        if (!$raw) return $html;

        $show = get_post_meta($id, '_msd_show_original', true);
        if ($show === '') $show = $shows && $shows[0] !== '' ? $shows[0] : get_option('msd_show_original_price', 'yes');
        if ($show !== 'yes') return $html;

        $range = fn($v) => min($v) === max($v) ? wc_price(min($v)) : wc_format_price_range(min($v), max($v));
        return wc_format_sale_price($range($raw), $range($adj)) . $product->get_price_suffix();
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
        return self::calculate_with_campaign($price, $product)[0];
    }

    /** The adjusted price and the campaign that gave it (null when none did): [price, campaign] */
    public static function calculate_with_campaign($price, $product) {
        $none = [$price, null];
        if (!$price || !$product) return $none;

        // Global enable check
        if (get_option('msd_global_enable') !== 'yes') {
            return $none;
        }

        // A variation has no categories or tags of its own - its product's count (the print cards price their sizes and frames
        // as variations, so the category rules never reached them)
        $product_id = $product->is_type('variation') && $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();

        // Check product-level exclusion first
        if (MSD_Product_Overrides::is_excluded_from_price_adj($product_id)) {
            return $none;
        }

        // Exclude commission products (from your original logic)
        if ($product->get_type() === 'custom' ||
            $product->get_meta('_commission_key') ||
            has_term('commission', 'product_cat', $product_id)) {
            return $none;
        }

        // The running campaign that gives the lowest price
        return MSD_Campaigns::best($price, $product_id, function ($price, $percentage) use ($product_id) {
            // Apply the campaign's percentage
            $price *= 1 + ($percentage / 100);

            // Apply per-product additional percentage (if set)
            $product_percentage = get_post_meta($product_id, '_msd_additional_percentage', true);
            if ($product_percentage !== '' && is_numeric($product_percentage)) {
                $product_percentage = (float) $product_percentage;
                $price *= 1 + ($product_percentage / 100);
            }

            // Apply rounding
            switch (get_option('msd_rounding', 'none')) {
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
            switch (get_option('msd_charm_pricing', 'none')) {
                case 'up':
                    $price = floor($price) + 0.99;
                    break;
                case 'down':
                    $price = ceil($price) - 0.01;
                    break;
            }

            return round($price, wc_get_price_decimals());
        });
    }
}

<?php
/**
 * Sale Control - Taxonomy-based sale enable/disable
 * Based on your sale price switch logic
 */

if (!defined('ABSPATH')) exit;

class MSD_Sale_Control {

    private static $cache = [];

    public static function init() {
        // Only run if global system is enabled
        if (get_option('msd_global_enable') !== 'yes') {
            return;
        }

        add_filter('woocommerce_product_is_on_sale', [__CLASS__, 'filter_is_on_sale'], 10, 2);
        add_filter('woocommerce_product_get_sale_price', [__CLASS__, 'filter_sale_price'], 10, 2);
        add_filter('woocommerce_product_variation_get_sale_price', [__CLASS__, 'filter_sale_price'], 10, 2);
        add_filter('woocommerce_get_price_html', [__CLASS__, 'filter_price_html'], 20, 2);

        // Handle scheduling
        add_action('init', [__CLASS__, 'check_schedule']);
    }

    /**
     * Check if current time is within scheduled period
     */
    public static function check_schedule() {
        // Check one-time schedule
        if (get_option('msd_schedule_enable') === 'yes') {
            $start = get_option('msd_schedule_start', '');
            $end = get_option('msd_schedule_end', '');
            $now = current_time('timestamp');

            if (!empty($start)) {
                $start_time = strtotime($start);
                if ($now < $start_time) {
                    return false;
                }
            }

            if (!empty($end)) {
                $end_time = strtotime($end);
                if ($now > $end_time) {
                    return false;
                }
            }
        }

        // Check recurring schedule
        if (get_option('msd_recurring_enable') === 'yes') {
            return self::check_recurring_schedule();
        }

        return true;
    }

    /**
     * Check recurring schedule
     */
    private static function check_recurring_schedule() {
        $pattern = get_option('msd_recurring_pattern', 'weekends');
        $now = current_time('timestamp');
        $current_day = intval(date('w', $now)); // 0 (Sunday) to 6 (Saturday)
        $current_time = date('H:i', $now);

        $start_time = get_option('msd_recurring_start_time', '00:00');
        $end_time = get_option('msd_recurring_end_time', '23:59');

        // Check if within time window
        if ($current_time < $start_time || $current_time > $end_time) {
            return false;
        }

        switch ($pattern) {
            case 'daily':
                return true;

            case 'weekends':
                return in_array($current_day, [0, 6]); // Sunday or Saturday

            case 'weekdays':
                return in_array($current_day, [1, 2, 3, 4, 5]); // Monday to Friday

            case 'weekly':
                $target_day = get_option('msd_recurring_weekday', '1');
                return $current_day == $target_day;

            case 'monthly_date':
                $target_date = get_option('msd_recurring_monthday', '1');
                return intval(date('j', $now)) == $target_date;

            case 'monthly_day':
                // First/Second/Third/Fourth/Last Monday (or other day) of month
                $target_day = get_option('msd_recurring_weekday', '1');
                $week = get_option('msd_recurring_week', 'first');

                return self::is_nth_weekday($now, $target_day, $week);
        }

        return false;
    }

    /**
     * Check if date is nth weekday of month
     */
    private static function is_nth_weekday($timestamp, $target_day, $week) {
        $current_day = intval(date('w', $timestamp));

        if ($current_day != $target_day) {
            return false;
        }

        $day_of_month = intval(date('j', $timestamp));
        $month = date('n', $timestamp);
        $year = date('Y', $timestamp);

        if ($week === 'last') {
            // Check if this is the last occurrence of this weekday in the month
            $next_week = strtotime('+7 days', $timestamp);
            return date('n', $next_week) != $month;
        }

        // Count which occurrence this is
        $first_of_month = mktime(0, 0, 0, $month, 1, $year);
        $occurrences = 0;

        for ($day = 1; $day <= $day_of_month; $day++) {
            $check_date = mktime(0, 0, 0, $month, $day, $year);
            if (intval(date('w', $check_date)) == $target_day) {
                $occurrences++;
            }
        }

        $week_map = [
            'first' => 1,
            'second' => 2,
            'third' => 3,
            'fourth' => 4,
        ];

        return isset($week_map[$week]) && $occurrences == $week_map[$week];
    }

    /**
     * Central eligibility check with caching
     */
    private static function sale_allowed_by_rules($product) {
        if (!$product || !$product->get_sale_price()) {
            return false;
        }

        // Check schedule first
        if (!self::check_schedule()) {
            return false;
        }

        $id = $product->get_id();

        // Check cache
        if (isset(self::$cache[$id])) {
            return self::$cache[$id];
        }

        $allowed = false;
        $mode = get_option('msd_sale_mode', 'taxonomy');

        if ($mode === 'global') {
            // Global mode: allowed unless excluded
            $allowed = true;

            $excluded_cats = (array) get_option('msd_sale_exclude_categories', []);
            if (!empty($excluded_cats) && has_term($excluded_cats, 'product_cat', $id)) {
                $allowed = false;
            }

            $excluded_tags = (array) get_option('msd_sale_exclude_tags', []);
            if ($allowed && !empty($excluded_tags) && has_term($excluded_tags, 'product_tag', $id)) {
                $allowed = false;
            }
        } else {
            // Taxonomy mode: only allowed if in specified cats/tags
            $allowed = false;

            $allowed_cats = (array) get_option('msd_sale_categories', []);
            if (!empty($allowed_cats) && has_term($allowed_cats, 'product_cat', $id)) {
                $allowed = true;
            }

            $allowed_tags = (array) get_option('msd_sale_tags', []);
            if (!$allowed && !empty($allowed_tags) && has_term($allowed_tags, 'product_tag', $id)) {
                $allowed = true;
            }
        }

        self::$cache[$id] = $allowed;
        return $allowed;
    }

    /**
     * Filter: is_on_sale
     */
    public static function filter_is_on_sale($on_sale, $product) {
        static $running = false;
        if ($running) return $on_sale;
        $running = true;

        if (!$product->get_sale_price()) {
            $running = false;
            return false;
        }

        // Check for product override
        $override = get_post_meta($product->get_id(), '_msd_enable_sale', true);
        if ($override === 'yes') {
            $running = false;
            return true;
        }
        if ($override === 'no') {
            $running = false;
            return false;
        }

        // Use rules
        $on_sale = self::sale_allowed_by_rules($product);
        $running = false;
        return $on_sale;
    }

    /**
     * Filter: sale_price
     */
    public static function filter_sale_price($sale_price, $product) {
        static $running = false;
        if ($running) return $sale_price;
        $running = true;

        if (!$product->get_sale_price()) {
            $running = false;
            return '';
        }

        // Check for product override
        $override = get_post_meta($product->get_id(), '_msd_enable_sale', true);
        if ($override === 'yes') {
            $running = false;
            return $sale_price;
        }
        if ($override === 'no') {
            $running = false;
            return '';
        }

        // Use rules
        $sale_price = self::sale_allowed_by_rules($product) ? $sale_price : '';
        $running = false;
        return $sale_price;
    }

    /**
     * Filter: price HTML (control crossed-out display)
     */
    public static function filter_price_html($price_html, $product) {
        static $running = false;
        if ($running) return $price_html;
        $running = true;

        if (!$product->get_sale_price()) {
            $running = false;
            return $price_html;
        }

        // Check for product override
        $show_original = get_post_meta($product->get_id(), '_msd_show_original', true);

        if ($show_original === '') {
            // No override, use global setting
            $show_original = get_option('msd_show_original_price', 'yes');
        }

        if ($show_original !== 'yes') {
            // Remove crossed-out regular price
            $price_html = preg_replace('#<del.*?</del>#', '', $price_html);
        }

        $running = false;
        return $price_html;
    }
}
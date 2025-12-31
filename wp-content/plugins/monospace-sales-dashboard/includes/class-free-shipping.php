<?php
/**
 * Free Shipping Automation - Auto-apply coupon based on rules
 */

if (!defined('ABSPATH')) exit;

class MSD_Free_Shipping {

    public static function init() {
        // Only run if enabled
        if (get_option('msd_freeship_enable') !== 'yes') {
            return;
        }

        // Auto-apply/remove coupon
        add_action('woocommerce_before_calculate_totals', [__CLASS__, 'auto_apply_coupon'], 10);
        add_action('woocommerce_cart_updated', [__CLASS__, 'auto_apply_coupon'], 10);

        // Cart hints (WooCommerce notice style)
        add_action('woocommerce_before_cart', [__CLASS__, 'display_cart_notice'], 10);

        // Hide our admin coupon from display
        add_filter('woocommerce_cart_totals_coupon_label', [__CLASS__, 'hide_admin_coupon_label'], 10, 2);
        add_filter('woocommerce_cart_totals_coupon_html', [__CLASS__, 'hide_admin_coupon_display'], 10, 3);

        // Suppress success message when admin coupon is applied
        add_filter('woocommerce_coupon_message', [__CLASS__, 'suppress_admin_coupon_message'], 10, 3);
    }

    /**
     * Check if cart qualifies for free shipping
     */
    private static function check_conditions() {
        if (!WC()->cart) {
            return false;
        }

        $rules_json = get_option('msd_freeship_rules', '');
        if (empty($rules_json)) {
            return false;
        }

        $rules = json_decode($rules_json, true);
        if (!is_array($rules)) {
            return false;
        }

        // Check each rule group
        foreach ($rules as $rule_key => $rule_list) {
            foreach ($rule_list as $rule) {
                if (self::evaluate_rule($rule, $rule_key)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Evaluate a single rule
     */
    private static function evaluate_rule($rule, $rule_key = '') {
        if (empty($rule['type'])) {
            return false;
        }

        switch ($rule['type']) {
            case 'min_amount':
                return self::check_min_amount($rule);

            case 'min_quantity':
                return self::check_min_quantity($rule);

            case 'category_amount':
                return self::check_category_amount($rule, $rule_key);

            case 'category_quantity':
                return self::check_category_quantity($rule, $rule_key);

            case 'all_from_category':
                return self::check_all_from_category($rule_key);

            default:
                return false;
        }
    }

    /**
     * Check minimum cart amount
     */
    private static function check_min_amount($rule) {
        if (!isset($rule['amount'])) {
            return false;
        }

        $cart_total = WC()->cart->get_subtotal();
        return $cart_total >= floatval($rule['amount']);
    }

    /**
     * Check minimum cart quantity
     */
    private static function check_min_quantity($rule) {
        if (!isset($rule['quantity'])) {
            return false;
        }

        $cart_qty = WC()->cart->get_cart_contents_count();
        return $cart_qty >= intval($rule['quantity']);
    }

    /**
     * Check category-specific amount
     */
    private static function check_category_amount($rule, $category_slug) {
        if (!isset($rule['amount']) || empty($category_slug)) {
            return false;
        }

        $category_total = 0;

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];

            if (has_term($category_slug, 'product_cat', $product_id)) {
                $category_total += $cart_item['line_subtotal'];
            }
        }

        return $category_total >= floatval($rule['amount']);
    }

    /**
     * Check category-specific quantity
     */
    private static function check_category_quantity($rule, $category_slug) {
        if (!isset($rule['quantity']) || empty($category_slug)) {
            return false;
        }

        $category_qty = 0;

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];

            if (has_term($category_slug, 'product_cat', $product_id)) {
                $category_qty += $cart_item['quantity'];
            }
        }

        return $category_qty >= intval($rule['quantity']);
    }

    /**
     * Check if all items are from specific category
     */
    private static function check_all_from_category($category_slug) {
        if (empty($category_slug)) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];

            if (!has_term($category_slug, 'product_cat', $product_id)) {
                return false; // Found item NOT in category
            }
        }

        return true; // All items are in category
    }

    /**
     * Auto-apply or remove coupon
     */
    public static function auto_apply_coupon() {
        if (!WC()->cart) {
            return;
        }

        $coupon_code = get_option('msd_freeship_coupon', '_admin_freeshipping');
        if (empty($coupon_code)) {
            return;
        }

        $qualifies = self::check_conditions();
        $already_applied = WC()->cart->has_discount($coupon_code);

        if ($qualifies && !$already_applied) {
            // Apply coupon
            WC()->cart->apply_coupon($coupon_code);

        } elseif (!$qualifies && $already_applied) {
            // Remove coupon
            WC()->cart->remove_coupon($coupon_code);
        }
    }

    /**
     * Display cart notice (WooCommerce style)
     */
    public static function display_cart_notice() {
        if (!WC()->cart || get_option('msd_freeship_hints_enable') !== 'yes') {
            return;
        }

        $coupon_code = get_option('msd_freeship_coupon', '_admin_freeshipping');
        $already_applied = WC()->cart->has_discount($coupon_code);

        if ($already_applied) {
            // Show success message as WooCommerce notice
            $message = get_option('msd_freeship_hint_active', 'Free shipping applied! ✓');
            wc_print_notice($message, 'success');

        } else {
            // Show "almost there" message if close
            $hint = self::get_almost_hint();
            if ($hint) {
                wc_print_notice($hint, 'notice');
            }
        }
    }

    /**
     * Hide admin coupon label
     */
    public static function hide_admin_coupon_label($label, $coupon) {
        $admin_coupon = get_option('msd_freeship_coupon', '_admin_freeshipping');

        if ($coupon->get_code() === $admin_coupon) {
            return ''; // Return empty to hide
        }

        return $label;
    }

    /**
     * Hide admin coupon from cart totals display
     */
    public static function hide_admin_coupon_display($coupon_html, $coupon, $discount_amount_html) {
        $admin_coupon = get_option('msd_freeship_coupon', '_admin_freeshipping');

        if ($coupon->get_code() === $admin_coupon) {
            return ''; // Return empty to hide entire row
        }

        return $coupon_html;
    }

    /**
     * Make message product-specific (replace "items" with actual product type)
     */
    private static function make_product_specific($message, $quantity) {
        // Check if message contains "items" or "item"
        if (strpos($message, 'items') === false && strpos($message, 'item') === false) {
            return $message;
        }

        // Get product type names from cart
        $type_names = self::get_cart_product_type_names();

        if (!$type_names) {
            return $message; // No specific type found, keep as is
        }

        // Use singular or plural based on quantity
        $replacement = ($quantity == 1) ? $type_names['singular'] : $type_names['plural'];

        // Replace "items" and "item"
        $message = str_replace('items', $replacement, $message);
        $message = str_replace('item', $replacement, $message);

        return $message;
    }

    /**
     * Get product type names from cart (same logic as volume discounts)
     */
    private static function get_cart_product_type_names() {
        if (!WC()->cart) {
            return null;
        }

        // Count products by rule key (miniature, category, attribute)
        $type_counts = [];

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];

            // Check if it's a miniature
            if (self::is_miniature($product_id)) {
                if (!isset($type_counts['miniature'])) {
                    $type_counts['miniature'] = 0;
                }
                $type_counts['miniature'] += $cart_item['quantity'];
                continue;
            }

            // Check categories
            $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
            if (!is_wp_error($categories) && !empty($categories)) {
                foreach ($categories as $cat_slug) {
                    if (!isset($type_counts[$cat_slug])) {
                        $type_counts[$cat_slug] = 0;
                    }
                    $type_counts[$cat_slug] += $cart_item['quantity'];
                }
            }
        }

        if (empty($type_counts)) {
            return null;
        }

        // Find the dominant type
        arsort($type_counts);
        $dominant_key = key($type_counts);

        // Try as tag first, then category
        $term = get_term_by('slug', $dominant_key, 'product_tag');
        if (!$term) {
            $term = get_term_by('slug', $dominant_key, 'product_cat');
        }

        if ($term) {
            $name = strtolower($term->name);
            $singular = (substr($name, -1) === 's') ? substr($name, 0, -1) : $name;
            $plural = (substr($name, -1) === 's') ? $name : $name . 's';
            return ['singular' => $singular, 'plural' => $plural];
        }

        return null;
    }

    /**
     * Suppress success message when admin coupon is applied
     */
    public static function suppress_admin_coupon_message($message, $message_code, $coupon) {
        $admin_coupon = get_option('msd_freeship_coupon', '_admin_freeshipping');

        if ($coupon && $coupon->get_code() === $admin_coupon) {
            return ''; // Suppress the message
        }

        return $message;
    }

    /**
     * Get "almost there" hint
     */
    private static function get_almost_hint() {
        $rules_json = get_option('msd_freeship_rules', '');
        if (empty($rules_json)) {
            return '';
        }

        $rules = json_decode($rules_json, true);
        if (!is_array($rules)) {
            return '';
        }

        $cart_total = WC()->cart->get_subtotal();
        $cart_qty = WC()->cart->get_cart_contents_count();

        // Find closest rule
        $closest_amount = null;
        $closest_qty = null;

        foreach ($rules as $rule_key => $rule_list) {
            foreach ($rule_list as $rule) {
                switch ($rule['type']) {
                    case 'min_amount':
                        if (isset($rule['amount'])) {
                            $needed = floatval($rule['amount']) - $cart_total;
                            if ($needed > 0 && ($closest_amount === null || $needed < $closest_amount)) {
                                $closest_amount = $needed;
                            }
                        }
                        break;

                    case 'min_quantity':
                        if (isset($rule['quantity'])) {
                            $needed = intval($rule['quantity']) - $cart_qty;
                            if ($needed > 0 && ($closest_qty === null || $needed < $closest_qty)) {
                                $closest_qty = $needed;
                            }
                        }
                        break;
                }
            }
        }

        // Generate message
        if ($closest_amount !== null && $closest_amount <= 50) {
            $template = get_option('msd_freeship_hint_almost_amount', 'Add {amount} more for free shipping');
            return str_replace('{amount}', wc_price($closest_amount), $template);
        }

        if ($closest_qty !== null && $closest_qty <= 3) {
            $template = get_option('msd_freeship_hint_almost_qty', 'Add {qty} more items for free shipping');
            $message = str_replace('{qty}', $closest_qty, $template);

            // Make product-specific by replacing "items" with actual product type
            $message = self::make_product_specific($message, $closest_qty);

            return $message;
        }

        return '';
    }

}

// No custom styles needed - using WooCommerce native notices
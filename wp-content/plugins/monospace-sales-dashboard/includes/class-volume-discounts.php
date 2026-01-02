<?php
/**
 * Volume Discounts - Quantity-based pricing engine
 * Based on your product filter & volume discounts logic
 */

if (!defined('ABSPATH')) exit;

class MSD_Volume_Discounts {

    public static function init() {
        // Only run if enabled
        if (get_option('msd_volume_enable') !== 'yes') {
            return;
        }

        add_action('woocommerce_before_calculate_totals', [__CLASS__, 'apply_volume_discounts'], 10);
        add_filter('woocommerce_cart_item_price', [__CLASS__, 'display_original_price'], 20, 2);
        add_filter('woocommerce_cart_item_subtotal', [__CLASS__, 'display_discount_label'], 20, 2);

        // Filter WooCommerce notices to make them product-specific
        add_filter('woocommerce_add_notice', [__CLASS__, 'filter_shipping_notice'], 10, 1);
    }

    /**
     * Helper: check if product has attribute
     */
    private static function check_product_has_attribute($product_id, $taxonomy, $term_slug) {
        if (empty($product_id) || empty($taxonomy) || empty($term_slug)) return false;

        $terms = wp_get_post_terms($product_id, $taxonomy, ['fields' => 'slugs']);
        if (is_wp_error($terms) || empty($terms)) return false;

        return in_array($term_slug, (array) $terms, true);
    }

    /**
     * Helper: check if product is in category
     */
    private static function check_product_in_category_slug($product_id, $cat_slug) {
        if (empty($product_id) || empty($cat_slug)) return false;

        $cats = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'slugs']);
        if (is_wp_error($cats) || empty($cats)) return false;

        return in_array($cat_slug, (array) $cats, true);
    }

    /**
     * Helper: match product against rule key
     */
    private static function product_matches_rule_key($product_id, $rule_key) {
        if (!$product_id || !$rule_key) return false;

        // Check if it's a product category
        if (has_term($rule_key, 'product_cat', $product_id)) {
            return true;
        }

        // Check if it's a product tag
        if (has_term($rule_key, 'product_tag', $product_id)) {
            return true;
        }

        // Attribute format: attr:pa_taxonomy=slug (for advanced use)
        if (strpos($rule_key, 'attr:') === 0) {
            $payload = substr($rule_key, 5);
            if (strpos($payload, '=') !== false) {
                list($tax, $slug) = explode('=', $payload, 2);
                $tax = sanitize_text_field(trim($tax));
                $slug = sanitize_text_field(trim($slug));
                if ($tax && $slug) {
                    return self::check_product_has_attribute($product_id, $tax, $slug);
                }
            }
        }

        return false;
    }

    /**
     * Apply volume discounts to cart
     */
    public static function apply_volume_discounts($cart) {
        // Safety checks
        if (is_admin() && !defined('DOING_AJAX')) return;
        if (!$cart || $cart->is_empty()) return;

        // Global enable check
        if (get_option('msd_global_enable') !== 'yes') return;

        // Get rules from settings
        $rules_json = get_option('msd_volume_rules', '');
        if (empty($rules_json)) return;

        $volume_rules = json_decode($rules_json, true);
        if (!is_array($volume_rules)) return;

        // Process each rule category
        foreach ($volume_rules as $rule_key => $rules) {
            // Collect matching items
            $items = [];
            $total_qty = 0;

            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                $product_id = $cart_item['product_id'];

                // Skip if product is excluded from volume discounts
                if (MSD_Product_Overrides::is_excluded_from_volume($product_id)) {
                    continue;
                }

                if (self::product_matches_rule_key($product_id, $rule_key)) {
                    $items[$cart_item_key] = $cart_item;
                    $total_qty += $cart_item['quantity'];

                    // Store original price
                    if (empty($cart->cart_contents[$cart_item_key]['original_display_price'])) {
                        $cart->cart_contents[$cart_item_key]['original_display_price'] =
                            $cart_item['data']->get_regular_price();
                    }

                    // Store rule key for specific messaging
                    $cart->cart_contents[$cart_item_key]['_volume_rule_key'] = $rule_key;
                }
            }

            if ($total_qty === 0) continue;

            // Apply each rule
            foreach ($rules as $rule) {
                if (empty($rule['type'])) continue;

                self::apply_rule($rule, $items, $total_qty, $cart);
            }
        }
    }

    /**
     * Apply individual rule
     */
    private static function apply_rule($rule, $items, $total_qty, $cart) {
        switch ($rule['type']) {
            case 'fixed_bundle':
                self::apply_fixed_bundle($rule, $items, $total_qty, $cart);
                break;

            case 'buy_x_get_y':
                self::apply_buy_x_get_y($rule, $items, $total_qty, $cart);
                break;

            case 'second_half':
                self::apply_second_half($rule, $items, $total_qty, $cart);
                break;

            case 'percent_discount':
                self::apply_percent_discount($rule, $items, $cart);
                break;

            case 'fixed_discount':
                self::apply_fixed_discount($rule, $items, $cart);
                break;

            case 'min_quantity':
                self::apply_min_quantity($rule, $items, $total_qty, $cart);
                break;
        }
    }

    /**
     * Rule: Fixed bundle (e.g., 3 for $99)
     */
    private static function apply_fixed_bundle($rule, $items, $total_qty, $cart) {
        if (!isset($rule['qty'], $rule['total'])) return;

        $sets = floor($total_qty / $rule['qty']);
        if ($sets < 1) return;

        $bundle_price = round($rule['total'] / $rule['qty'], wc_get_price_decimals());
        $qty_to_discount = $sets * $rule['qty'];

        foreach ($items as $cart_key => $cart_item) {
            if ($qty_to_discount <= 0) break;

            $apply_qty = min($cart_item['quantity'], $qty_to_discount);
            $cart->cart_contents[$cart_key]['data']->set_price($bundle_price);
            $cart->cart_contents[$cart_key]['volume_discount_label'] =
                sprintf('%d items for $%s', $rule['qty'], number_format($rule['total'], 2));

            $qty_to_discount -= $apply_qty;
        }
    }

    /**
     * Rule: Buy X get Y free
     */
    private static function apply_buy_x_get_y($rule, $items, $total_qty, $cart) {
        if (!isset($rule['buy'], $rule['get'])) return;

        $buy = intval($rule['buy']);
        $get = intval($rule['get']);
        if ($buy < 1 || $get < 1) return;

        $cycle = $buy + $get;
        $full_cycles = floor($total_qty / $cycle);
        $free_items = $full_cycles * $get;
        if ($free_items < 1) return;

        // Sort by price ascending (cheapest free)
        uasort($items, function($a, $b) {
            return $a['data']->get_price() <=> $b['data']->get_price();
        });

        foreach ($items as $key => $cart_item) {
            if ($free_items <= 0) break;

            $apply_qty = min($cart_item['quantity'], $free_items);
            $cart->cart_contents[$key]['data']->set_price(0);
            $cart->cart_contents[$key]['volume_discount_label'] =
                sprintf('Buy %d get %d free', $buy, $get);

            $free_items -= $apply_qty;
        }
    }

    /**
     * Rule: Second item half price
     */
    private static function apply_second_half($rule, $items, $total_qty, $cart) {
        if ($total_qty < 2) return;

        $pairs = floor($total_qty / 2);

        foreach ($items as $key => $cart_item) {
            if ($pairs <= 0) break;

            $reg_price = $cart_item['data']->get_regular_price();
            $half = round($reg_price * 0.5, wc_get_price_decimals());

            $cart->cart_contents[$key]['data']->set_price($half);
            $cart->cart_contents[$key]['volume_discount_label'] = 'Second item half price';

            $pairs--;
        }
    }

    /**
     * Rule: Percent discount
     */
    private static function apply_percent_discount($rule, $items, $cart) {
        if (!isset($rule['percent'])) return;

        $pct = floatval($rule['percent']);

        foreach ($items as $key => $cart_item) {
            $reg = $cart_item['data']->get_regular_price();
            $new = round($reg * (1 - $pct / 100), wc_get_price_decimals());

            $cart->cart_contents[$key]['data']->set_price($new);
            $cart->cart_contents[$key]['volume_discount_label'] =
                sprintf('%s%% off', rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.'));
        }
    }

    /**
     * Rule: Fixed discount
     */
    private static function apply_fixed_discount($rule, $items, $cart) {
        if (!isset($rule['amount'])) return;

        $amount = floatval($rule['amount']);

        foreach ($items as $key => $cart_item) {
            $reg = $cart_item['data']->get_regular_price();
            $new = max(0, round($reg - $amount, wc_get_price_decimals()));

            $cart->cart_contents[$key]['data']->set_price($new);
            $cart->cart_contents[$key]['volume_discount_label'] =
                sprintf('$%s off', number_format($amount, 2));
        }
    }

    /**
     * Rule: Minimum quantity for free shipping
     */
    private static function apply_min_quantity($rule, $items, $total_qty, $cart) {
        if (!isset($rule['quantity'])) return;

        $min_qty = intval($rule['quantity']);

        if ($total_qty >= $min_qty) {
            // Minimum reached - mark for free shipping
            foreach ($items as $key => $cart_item) {
                $cart->cart_contents[$key]['volume_discount_label'] = 'Free shipping applied';
            }
        } else {
            // Not reached yet - show how many more needed
            $needed = $min_qty - $total_qty;
            foreach ($items as $key => $cart_item) {
                $cart->cart_contents[$key]['volume_discount_label'] =
                    sprintf('Add %d more items for free shipping', $needed);
            }
        }
    }

    /**
     * Display original price in cart
     */
    public static function display_original_price($price_html, $cart_item) {
        if (!empty($cart_item['original_display_price'])) {
            return wc_price($cart_item['original_display_price']);
        }
        return $price_html;
    }

    /**
     * Display discount label in cart
     */
    public static function display_discount_label($subtotal, $cart_item) {
        if (!empty($cart_item['volume_discount_label'])) {
            $label = $cart_item['volume_discount_label'];

            // DEBUG: Log what we have
            error_log('Original label: ' . $label);
            error_log('Rule key: ' . ($cart_item['_volume_rule_key'] ?? 'NOT SET'));

            // Make messages product-specific
            if (!empty($cart_item['_volume_rule_key'])) {
                $rule_key = $cart_item['_volume_rule_key'];
                $product_names = self::get_product_type_names($rule_key);

                error_log('Product names: ' . print_r($product_names, true));

                if ($product_names) {
                    // Replace plural "items" first
                    $label = str_replace(' items ', ' ' . $product_names['plural'] . ' ', $label);
                    // Then singular "item"
                    $label = str_replace(' item ', ' ' . $product_names['singular'] . ' ', $label);

                    error_log('After replacement: ' . $label);
                }
            }

            $subtotal .= '<br><small style="color:green;">' .
                esc_html($label) . '</small>';
        }
        return $subtotal;
    }

    /**
     * Get product type names (singular and plural) from rule key
     */
    private static function get_product_type_names($rule_key) {
        // Attribute-based rules (advanced)
        if (strpos($rule_key, 'attr:') === 0) {
            $parts = explode('=', substr($rule_key, 5));
            if (count($parts) === 2) {
                $term = get_term_by('slug', $parts[1], $parts[0]);
                if ($term) {
                    $name = strtolower($term->name);
                    return [
                        'singular' => $name,
                        'plural' => (substr($name, -1) === 's') ? $name : $name . 's'
                    ];
                }
            }
        }

        // Try as product category
        $cat = get_term_by('slug', $rule_key, 'product_cat');
        if ($cat) {
            $name = strtolower($cat->name);
            $singular = (substr($name, -1) === 's') ? substr($name, 0, -1) : $name;
            $plural = (substr($name, -1) === 's') ? $name : $name . 's';
            return ['singular' => $singular, 'plural' => $plural];
        }

        // Try as product tag
        $tag = get_term_by('slug', $rule_key, 'product_tag');
        if ($tag) {
            $name = strtolower($tag->name);
            $singular = (substr($name, -1) === 's') ? substr($name, 0, -1) : $name;
            $plural = (substr($name, -1) === 's') ? $name : $name . 's';
            return ['singular' => $singular, 'plural' => $plural];
        }

        return null;
    }

    /**
     * Make WooCommerce shipping notices product-specific
     */
    public static function filter_shipping_notice($notice) {
        // Check if it's a shipping-related message with "items"
        if (strpos($notice, 'items') === false && strpos($notice, 'item') === false) {
            return $notice;
        }

        // Detect which rule applies to current cart
        if (!WC()->cart || WC()->cart->is_empty()) {
            return $notice;
        }

        // Get volume rules to find what's in cart
        $rules_json = get_option('msd_volume_rules', '');
        if (empty($rules_json)) {
            return $notice;
        }

        $volume_rules = json_decode($rules_json, true);
        if (!is_array($volume_rules)) {
            return $notice;
        }

        // Find which rule key applies to items in cart
        $rule_key = null;
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];

            foreach ($volume_rules as $key => $rules) {
                if (self::product_matches_rule_key($product_id, $key)) {
                    $rule_key = $key;
                    break 2;
                }
            }
        }

        if (!$rule_key) {
            return $notice;
        }

        // Get product-specific names
        $product_names = self::get_product_type_names($rule_key);

        if ($product_names) {
            // Replace "items" and "item" in the notice
            $notice = str_replace(' items ', ' ' . $product_names['plural'] . ' ', $notice);
            $notice = str_replace(' item ', ' ' . $product_names['singular'] . ' ', $notice);
        }

        return $notice;
    }
}
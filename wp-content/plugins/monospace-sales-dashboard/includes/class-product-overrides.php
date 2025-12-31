<?php
/**
 * Product Overrides - Per-product sale controls
 * Adds checkboxes to product editor
 */

if (!defined('ABSPATH')) exit;

class MSD_Product_Overrides {

    public static function init() {
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'add_product_fields']);
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_product_fields']);

        // Add column to products list
        add_filter('manage_product_posts_columns', [__CLASS__, 'add_admin_column']);
        add_action('manage_product_posts_custom_column', [__CLASS__, 'render_admin_column'], 10, 2);
    }

    /**
     * Add fields to product editor
     */
    public static function add_product_fields() {
        global $post;
        $product = wc_get_product($post->ID);

        if (!$product) return;

        echo '<div class="options_group msd-product-overrides">';
        echo '<p class="form-field"><strong>' . __('Sales Dashboard Overrides', 'monospace-sales') . '</strong></p>';

        // Sale override
        woocommerce_wp_select([
            'id' => '_msd_enable_sale',
            'label' => __('Sale Override', 'monospace-sales'),
            'description' => __('Override global sale rules for this product', 'monospace-sales'),
            'desc_tip' => true,
            'options' => [
                '' => __('Use global rules', 'monospace-sales'),
                'yes' => __('Force ON (always on sale)', 'monospace-sales'),
                'no' => __('Force OFF (never on sale)', 'monospace-sales'),
            ],
        ]);

        // Show original price override
        woocommerce_wp_select([
            'id' => '_msd_show_original',
            'label' => __('Show Original Price', 'monospace-sales'),
            'description' => __('Override global display setting', 'monospace-sales'),
            'desc_tip' => true,
            'options' => [
                '' => __('Use global setting', 'monospace-sales'),
                'yes' => __('Show crossed-out price', 'monospace-sales'),
                'no' => __('Hide crossed-out price', 'monospace-sales'),
            ],
        ]);

        // Exclude from price adjustments
        woocommerce_wp_checkbox([
            'id' => '_msd_exclude_price_adj',
            'label' => __('Exclude from price adjustments', 'monospace-sales'),
            'description' => __('Skip global percentage increases/decreases', 'monospace-sales'),
        ]);

        // Exclude from volume discounts
        woocommerce_wp_checkbox([
            'id' => '_msd_exclude_volume',
            'label' => __('Exclude from volume discounts', 'monospace-sales'),
            'description' => __('Do not apply quantity-based pricing', 'monospace-sales'),
        ]);

        // Custom badge text
        woocommerce_wp_text_input([
            'id' => '_msd_custom_badge',
            'label' => __('Custom Badge Text', 'monospace-sales'),
            'description' => __('Override default sale badge (leave empty for default)', 'monospace-sales'),
            'desc_tip' => true,
            'placeholder' => __('e.g., LIMITED TIME', 'monospace-sales'),
        ]);

        echo '</div>';
    }

    /**
     * Save product fields
     */
    public static function save_product_fields($post_id) {
        // Sale override
        $enable_sale = isset($_POST['_msd_enable_sale']) ? sanitize_text_field($_POST['_msd_enable_sale']) : '';
        update_post_meta($post_id, '_msd_enable_sale', $enable_sale);

        // Show original price
        $show_original = isset($_POST['_msd_show_original']) ? sanitize_text_field($_POST['_msd_show_original']) : '';
        update_post_meta($post_id, '_msd_show_original', $show_original);

        // Exclude from price adjustments
        $exclude_price = isset($_POST['_msd_exclude_price_adj']) ? 'yes' : 'no';
        update_post_meta($post_id, '_msd_exclude_price_adj', $exclude_price);

        // Exclude from volume discounts
        $exclude_volume = isset($_POST['_msd_exclude_volume']) ? 'yes' : 'no';
        update_post_meta($post_id, '_msd_exclude_volume', $exclude_volume);

        // Custom badge
        $badge = isset($_POST['_msd_custom_badge']) ? sanitize_text_field($_POST['_msd_custom_badge']) : '';
        update_post_meta($post_id, '_msd_custom_badge', $badge);
    }

    /**
     * Add column to products list
     */
    public static function add_admin_column($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'price') {
                $new_columns['msd_overrides'] = __('Sale Overrides', 'monospace-sales');
            }
        }
        return $new_columns;
    }

    /**
     * Render column content
     */
    public static function render_admin_column($column, $post_id) {
        if ($column !== 'msd_overrides') return;

        $indicators = [];

        // Check sale override
        $sale_override = get_post_meta($post_id, '_msd_enable_sale', true);
        if ($sale_override === 'yes') {
            $indicators[] = '<span style="color:green;" title="Forced ON">● Sale</span>';
        } elseif ($sale_override === 'no') {
            $indicators[] = '<span style="color:red;" title="Forced OFF">○ Sale</span>';
        }

        // Check exclusions
        if (get_post_meta($post_id, '_msd_exclude_price_adj', true) === 'yes') {
            $indicators[] = '<span title="Excluded from price adjustments">⊘ Price</span>';
        }

        if (get_post_meta($post_id, '_msd_exclude_volume', true) === 'yes') {
            $indicators[] = '<span title="Excluded from volume discounts">⊘ Volume</span>';
        }

        // Check custom badge
        if (get_post_meta($post_id, '_msd_custom_badge', true)) {
            $indicators[] = '<span title="Custom badge">★</span>';
        }

        if (empty($indicators)) {
            echo '<span style="color:#999;">—</span>';
        } else {
            echo implode('<br>', $indicators);
        }
    }

    /**
     * Check if product should be excluded from price adjustments
     */
    public static function is_excluded_from_price_adj($product_id) {
        return get_post_meta($product_id, '_msd_exclude_price_adj', true) === 'yes';
    }

    /**
     * Check if product should be excluded from volume discounts
     */
    public static function is_excluded_from_volume($product_id) {
        return get_post_meta($product_id, '_msd_exclude_volume', true) === 'yes';
    }

    /**
     * Get custom badge text for product
     */
    public static function get_custom_badge($product_id) {
        return get_post_meta($product_id, '_msd_custom_badge', true);
    }
}
<?php
/**
 * Plugin Name: monospace Sales Dashboard
 * Plugin URI: https://monospace.art
 * Description: Comprehensive sales management for WooCommerce - global pricing, taxonomy rules, volume discounts, and scheduling
 * Version: 1.0.0
 * Author: Hens Breet
 * Author URI: https://monospace.art
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * Text Domain: monospace-sales
 */

if (!defined('ABSPATH')) exit;

// Plugin constants
define('MSD_VERSION', '1.0.0');
define('MSD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MSD_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Check if WooCommerce is active
 */
function msd_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>monospace Sales Dashboard</strong> requires WooCommerce to be installed and active.';
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Declare WooCommerce HPOS compatibility
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Initialize plugin
 */
function msd_init() {
    if (!msd_check_woocommerce()) {
        return;
    }

    // Load core classes
    require_once MSD_PLUGIN_DIR . 'includes/class-settings.php';
    require_once MSD_PLUGIN_DIR . 'includes/class-sale-control.php';
    require_once MSD_PLUGIN_DIR . 'includes/class-price-adjustments.php';
    require_once MSD_PLUGIN_DIR . 'includes/class-volume-discounts.php';
    require_once MSD_PLUGIN_DIR . 'includes/class-free-shipping.php';
    require_once MSD_PLUGIN_DIR . 'includes/class-product-overrides.php';
    require_once MSD_PLUGIN_DIR . 'includes/class-import-export.php';
    require_once MSD_PLUGIN_DIR . 'includes/class-bulk-editor.php';

    // Initialize
    MSD_Settings::init();
    MSD_Sale_Control::init();
    MSD_Price_Adjustments::init();
    MSD_Volume_Discounts::init();
    MSD_Free_Shipping::init();
    MSD_Product_Overrides::init();
    MSD_Import_Export::init();
    MSD_Bulk_Editor::init();
}
add_action('plugins_loaded', 'msd_init');

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function() {
    // Set default options
    $defaults = [
        'msd_global_enable' => 'no',
        'msd_sale_mode' => 'taxonomy',
        'msd_show_original_price' => 'yes',
        'msd_price_adj_enable' => 'no',
        'msd_price_adj_percentage' => '25',
        'msd_rounding' => 'none',
        'msd_charm_pricing' => 'none',
    ];

    foreach ($defaults as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function() {
    // Clear transients
    delete_transient('msd_active_schedules');
});
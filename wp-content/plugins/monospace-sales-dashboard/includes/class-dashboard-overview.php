<?php
/**
 * Dashboard Overview - System status at a glance
 */

if (!defined('ABSPATH')) exit;

class MSD_Dashboard_Overview {

    public static function init() {
        // No hooks needed - just provides output_section() for settings integration
    }

    /**
     * Output the overview section
     */
    public static function output_section() {
        ?>
        <div class="msd-dashboard-overview">
            <h2><?php _e('Sales Dashboard Overview', 'monospace-sales'); ?></h2>
            <p class="description">
                <?php _e('Current status of all sales system features and settings.', 'monospace-sales'); ?>
            </p>

            <div class="msd-overview-grid">
                <?php
                self::render_system_status();
                self::render_price_adjustments_status();
                self::render_sale_rules_status();
                self::render_volume_discounts_status();
                self::render_free_shipping_status();
                self::render_scheduling_status();
                self::render_statistics();
                ?>
            </div>

            <style>
                .msd-overview-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
                    gap: 20px;
                    margin-top: 20px;
                }
                .msd-status-card {
                    background: #fff;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    padding: 20px;
                    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
                }
                .msd-status-card h3 {
                    margin: 0 0 15px 0;
                    padding-bottom: 10px;
                    border-bottom: 2px solid #2271b1;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .msd-status-badge {
                    display: inline-block;
                    padding: 3px 10px;
                    border-radius: 3px;
                    font-size: 12px;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                .msd-status-badge.active {
                    background: #00a32a;
                    color: #fff;
                }
                .msd-status-badge.inactive {
                    background: #dba617;
                    color: #fff;
                }
                .msd-status-badge.disabled {
                    background: #999;
                    color: #fff;
                }
                .msd-status-item {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 0;
                    border-bottom: 1px solid #f0f0f0;
                }
                .msd-status-item:last-child {
                    border-bottom: none;
                }
                .msd-status-label {
                    font-weight: 500;
                    color: #333;
                }
                .msd-status-value {
                    color: #666;
                    font-family: monospace;
                }
                .msd-status-value.highlight {
                    color: #d63638;
                    font-weight: bold;
                }
                .msd-quick-links {
                    margin-top: 15px;
                    padding-top: 15px;
                    border-top: 1px solid #f0f0f0;
                }
                .msd-quick-links a {
                    font-size: 13px;
                }
                .msd-stat-number {
                    font-size: 32px;
                    font-weight: bold;
                    color: #2271b1;
                    margin: 10px 0;
                }
                .msd-stat-label {
                    font-size: 13px;
                    color: #666;
                    text-transform: uppercase;
                    letter-spacing: 0.5px;
                }
            </style>
        </div>
        <?php
    }

    /**
     * Render system status card
     */
    private static function render_system_status() {
        $global_enabled = get_option('msd_global_enable') === 'yes';
        $sale_mode = get_option('msd_sale_mode', 'taxonomy');
        $show_original = get_option('msd_show_original_price') === 'yes';
        $priority = get_option('msd_priority', 'best');

        ?>
        <div class="msd-status-card">
            <h3>
                <?php _e('System Status', 'monospace-sales'); ?>
                <span class="msd-status-badge <?php echo $global_enabled ? 'active' : 'disabled'; ?>">
                    <?php echo $global_enabled ? __('Active', 'monospace-sales') : __('Disabled', 'monospace-sales'); ?>
                </span>
            </h3>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Sale Mode', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo ucfirst($sale_mode); ?></span>
            </div>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Show Original Price', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo $show_original ? __('Yes', 'monospace-sales') : __('No', 'monospace-sales'); ?></span>
            </div>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Priority Mode', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo ucfirst(str_replace('_', ' ', $priority)); ?></span>
            </div>

            <div class="msd-quick-links">
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=msd&section=global'); ?>" class="button button-small">
                    <?php _e('Configure', 'monospace-sales'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render price adjustments status card
     */
    private static function render_price_adjustments_status() {
        $enabled = get_option('msd_price_adj_enable') === 'yes';
        $percentage = get_option('msd_price_adj_percentage', 25);
        $rounding = get_option('msd_rounding', 'none');
        $charm = get_option('msd_charm_pricing', 'none');
        $categories = (array) get_option('msd_price_adj_categories', []);
        $tags = (array) get_option('msd_price_adj_tags', []);

        // Count products with additional percentage
        global $wpdb;
        $additional_count = $wpdb->get_var("
            SELECT COUNT(DISTINCT post_id)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_msd_additional_percentage'
            AND meta_value != ''
            AND meta_value IS NOT NULL
        ");

        ?>
        <div class="msd-status-card">
            <h3>
                <?php _e('Price Adjustments', 'monospace-sales'); ?>
                <span class="msd-status-badge <?php echo $enabled ? 'active' : 'disabled'; ?>">
                    <?php echo $enabled ? __('Active', 'monospace-sales') : __('Disabled', 'monospace-sales'); ?>
                </span>
            </h3>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Global Adjustment', 'monospace-sales'); ?></span>
                <span class="msd-status-value highlight">+<?php echo esc_html($percentage); ?>%</span>
            </div>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Products w/ Additional %', 'monospace-sales'); ?></span>
                <span class="msd-status-value highlight"><?php echo intval($additional_count); ?></span>
            </div>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Rounding', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo ucfirst($rounding); ?></span>
            </div>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Charm Pricing', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo ucfirst($charm); ?></span>
            </div>

            <?php if (!empty($categories) || !empty($tags)): ?>
            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Scope', 'monospace-sales'); ?></span>
                <span class="msd-status-value">
                    <?php
                    echo count($categories) . ' ' . __('categories', 'monospace-sales');
                    if (!empty($tags)) {
                        echo ', ' . count($tags) . ' ' . __('tags', 'monospace-sales');
                    }
                    ?>
                </span>
            </div>
            <?php endif; ?>

            <div class="msd-quick-links">
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=msd&section=price_adjustments'); ?>" class="button button-small">
                    <?php _e('Configure', 'monospace-sales'); ?>
                </a>
                <?php if ($additional_count > 0): ?>
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=msd&section=additional_tracker'); ?>" class="button button-small" style="margin-left: 5px;">
                    <?php _e('View Products', 'monospace-sales'); ?>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render sale rules status card
     */
    private static function render_sale_rules_status() {
        $categories = (array) get_option('msd_sale_categories', []);
        $tags = (array) get_option('msd_sale_tags', []);
        $exclude_cats = (array) get_option('msd_sale_exclude_categories', []);
        $exclude_tags = (array) get_option('msd_sale_exclude_tags', []);

        $has_rules = !empty($categories) || !empty($tags) || !empty($exclude_cats) || !empty($exclude_tags);

        ?>
        <div class="msd-status-card">
            <h3>
                <?php _e('Sale Rules', 'monospace-sales'); ?>
                <span class="msd-status-badge <?php echo $has_rules ? 'active' : 'inactive'; ?>">
                    <?php echo $has_rules ? __('Configured', 'monospace-sales') : __('None', 'monospace-sales'); ?>
                </span>
            </h3>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Allowed Categories', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo empty($categories) ? __('All', 'monospace-sales') : count($categories); ?></span>
            </div>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Allowed Tags', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo empty($tags) ? __('All', 'monospace-sales') : count($tags); ?></span>
            </div>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Excluded Categories', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo empty($exclude_cats) ? __('None', 'monospace-sales') : count($exclude_cats); ?></span>
            </div>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Excluded Tags', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo empty($exclude_tags) ? __('None', 'monospace-sales') : count($exclude_tags); ?></span>
            </div>

            <div class="msd-quick-links">
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=msd&section=sale_rules'); ?>" class="button button-small">
                    <?php _e('Configure', 'monospace-sales'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render volume discounts status card
     */
    private static function render_volume_discounts_status() {
        $enabled = get_option('msd_volume_enable') === 'yes';
        $rules_json = get_option('msd_volume_rules', '');
        $rules = json_decode($rules_json, true);

        $rule_count = 0;
        if (!empty($rules) && is_array($rules)) {
            foreach ($rules as $group) {
                if (is_array($group)) {
                    $rule_count += count($group);
                }
            }
        }

        ?>
        <div class="msd-status-card">
            <h3>
                <?php _e('Volume Discounts', 'monospace-sales'); ?>
                <span class="msd-status-badge <?php echo $enabled ? 'active' : 'disabled'; ?>">
                    <?php echo $enabled ? __('Active', 'monospace-sales') : __('Disabled', 'monospace-sales'); ?>
                </span>
            </h3>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Rule Groups', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo is_array($rules) ? count($rules) : 0; ?></span>
            </div>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Total Rules', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo $rule_count; ?></span>
            </div>

            <div class="msd-quick-links">
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=msd&section=volume_discounts'); ?>" class="button button-small">
                    <?php _e('Configure', 'monospace-sales'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render free shipping status card
     */
    private static function render_free_shipping_status() {
        $enabled = get_option('msd_freeship_enable') === 'yes';
        $coupon = get_option('msd_freeship_coupon', '_admin_freeshipping');
        $rules_json = get_option('msd_freeship_rules', '');
        $rules = json_decode($rules_json, true);

        $rule_count = 0;
        if (!empty($rules) && is_array($rules)) {
            foreach ($rules as $group) {
                if (is_array($group)) {
                    $rule_count += count($group);
                }
            }
        }

        ?>
        <div class="msd-status-card">
            <h3>
                <?php _e('Free Shipping', 'monospace-sales'); ?>
                <span class="msd-status-badge <?php echo $enabled ? 'active' : 'disabled'; ?>">
                    <?php echo $enabled ? __('Active', 'monospace-sales') : __('Disabled', 'monospace-sales'); ?>
                </span>
            </h3>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Coupon Code', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo esc_html($coupon); ?></span>
            </div>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Rule Groups', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo is_array($rules) ? count($rules) : 0; ?></span>
            </div>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Total Rules', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo $rule_count; ?></span>
            </div>

            <div class="msd-quick-links">
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=msd&section=free_shipping'); ?>" class="button button-small">
                    <?php _e('Configure', 'monospace-sales'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render scheduling status card
     */
    private static function render_scheduling_status() {
        $onetime_enabled = get_option('msd_schedule_enable') === 'yes';
        $recurring_enabled = get_option('msd_recurring_enable') === 'yes';
        $start = get_option('msd_schedule_start', '');
        $end = get_option('msd_schedule_end', '');
        $pattern = get_option('msd_recurring_pattern', 'weekends');

        $status = 'disabled';
        if ($onetime_enabled || $recurring_enabled) {
            $status = 'active';
        }

        ?>
        <div class="msd-status-card">
            <h3>
                <?php _e('Scheduling', 'monospace-sales'); ?>
                <span class="msd-status-badge <?php echo $status; ?>">
                    <?php echo $status === 'active' ? __('Active', 'monospace-sales') : __('Disabled', 'monospace-sales'); ?>
                </span>
            </h3>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('One-Time Schedule', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo $onetime_enabled ? __('Enabled', 'monospace-sales') : __('Disabled', 'monospace-sales'); ?></span>
            </div>

            <?php if ($onetime_enabled && ($start || $end)): ?>
            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Start/End', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo esc_html($start ?: 'Now') . ' → ' . esc_html($end ?: 'Never'); ?></span>
            </div>
            <?php endif; ?>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Recurring Schedule', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo $recurring_enabled ? __('Enabled', 'monospace-sales') : __('Disabled', 'monospace-sales'); ?></span>
            </div>

            <?php if ($recurring_enabled): ?>
            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Pattern', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo ucfirst(str_replace('_', ' ', $pattern)); ?></span>
            </div>
            <?php endif; ?>

            <div class="msd-quick-links">
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=msd&section=scheduling'); ?>" class="button button-small">
                    <?php _e('Configure', 'monospace-sales'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render statistics card
     */
    private static function render_statistics() {
        global $wpdb;

        // Count products with overrides
        $products_with_overrides = $wpdb->get_var("
            SELECT COUNT(DISTINCT post_id)
            FROM {$wpdb->postmeta}
            WHERE meta_key IN ('_msd_exclude_price_adj', '_msd_enable_sale', '_msd_additional_percentage')
            AND meta_value != ''
            AND meta_value IS NOT NULL
        ");

        // Count products with additional percentage
        $products_with_additional = $wpdb->get_var("
            SELECT COUNT(DISTINCT post_id)
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_msd_additional_percentage'
            AND meta_value != ''
            AND meta_value IS NOT NULL
        ");

        // Total published products
        $total_products = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type = 'product'
            AND post_status = 'publish'
        ");

        ?>
        <div class="msd-status-card">
            <h3><?php _e('Statistics', 'monospace-sales'); ?></h3>

            <div style="text-align: center; padding: 20px 0;">
                <div class="msd-stat-number"><?php echo intval($total_products); ?></div>
                <div class="msd-stat-label"><?php _e('Total Products', 'monospace-sales'); ?></div>
            </div>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Products w/ Overrides', 'monospace-sales'); ?></span>
                <span class="msd-status-value highlight"><?php echo intval($products_with_overrides); ?></span>
            </div>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Products w/ Additional %', 'monospace-sales'); ?></span>
                <span class="msd-status-value highlight"><?php echo intval($products_with_additional); ?></span>
            </div>

            <div class="msd-quick-links">
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=msd&section=bulk_editor'); ?>" class="button button-small">
                    <?php _e('Bulk Editor', 'monospace-sales'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=msd&section=additional_tracker'); ?>" class="button button-small" style="margin-left: 5px;">
                    <?php _e('View Additional %', 'monospace-sales'); ?>
                </a>
            </div>
        </div>
        <?php
    }
}

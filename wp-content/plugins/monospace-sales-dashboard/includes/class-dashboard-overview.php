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
            <p class="description">
                <?php _e('Where every part of the sales system stands right now. Each card links to its settings.', 'monospace-sales'); ?>
            </p>

            <div class="msd-overview-grid">
                <?php
                self::render_system_status();
                self::render_campaigns_status();
                self::render_volume_discounts_status();
                self::render_free_shipping_status();
                self::render_statistics();
                ?>
            </div>

        </div>
        <?php
    }

    /**
     * Render system status card
     */
    private static function render_system_status() {
        $global_enabled = get_option('msd_global_enable') === 'yes';
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
                <span class="msd-status-label"><?php _e('Show Original Price', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo $show_original ? __('Yes', 'monospace-sales') : __('No', 'monospace-sales'); ?></span>
            </div>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Priority Mode', 'monospace-sales'); ?></span>
                <span class="msd-status-value"><?php echo ucfirst(str_replace('_', ' ', $priority)); ?></span>
            </div>

            <div class="msd-quick-links">
                <a href="<?php echo MSD_Settings::url('global'); ?>" class="button button-small">
                    <?php _e('Configure', 'monospace-sales'); ?>
                </a>
            </div>
        </div>
        <?php
    }

    /**
     * Render campaigns status card
     */
    private static function render_campaigns_status() {
        $all = MSD_Campaigns::all();
        $running = MSD_Campaigns::running();
        global $wpdb;
        $additional_count = (int) $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_msd_additional_percentage' AND meta_value != '' AND meta_value IS NOT NULL");
        ?>
        <div class="msd-status-card">
            <h3>
                <?php _e('Campaigns', 'monospace-sales'); ?>
                <span class="msd-status-badge <?php echo $running ? 'active' : 'disabled'; ?>">
                    <?php echo $running ? sprintf(__('%d running', 'monospace-sales'), count($running)) : __('None running', 'monospace-sales'); ?>
                </span>
            </h3>

            <?php foreach ($all as $c): ?>
            <div class="msd-status-item">
                <span class="msd-status-label"><?php echo esc_html($c['name']); ?></span>
                <span class="msd-status-value"><?php echo esc_html($c['enabled'] !== 'yes' ? __('Off', 'monospace-sales') : (MSD_Campaigns::is_running($c) ? __('Running', 'monospace-sales') : __('Waiting', 'monospace-sales'))); ?> · <?php echo esc_html(MSD_Campaigns::summary($c)); ?></span>
            </div>
            <?php endforeach; ?>

            <div class="msd-status-item">
                <span class="msd-status-label"><?php _e('Products w/ Additional %', 'monospace-sales'); ?></span>
                <span class="msd-status-value highlight"><?php echo $additional_count; ?></span>
            </div>

            <div class="msd-quick-links">
                <a href="<?php echo MSD_Settings::url('campaigns'); ?>" class="button button-small">
                    <?php _e('Configure', 'monospace-sales'); ?>
                </a>
                <?php if ($additional_count > 0): ?>
                <a href="<?php echo MSD_Settings::url('additional_tracker'); ?>" class="button button-small" style="margin-left: 5px;">
                    <?php _e('View Products', 'monospace-sales'); ?>
                </a>
                <?php endif; ?>
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
                <a href="<?php echo MSD_Settings::url('volume_discounts'); ?>" class="button button-small">
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
                <a href="<?php echo MSD_Settings::url('free_shipping'); ?>" class="button button-small">
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
                <a href="<?php echo MSD_Settings::url('bulk_editor'); ?>" class="button button-small">
                    <?php _e('Bulk Editor', 'monospace-sales'); ?>
                </a>
                <a href="<?php echo MSD_Settings::url('additional_tracker'); ?>" class="button button-small" style="margin-left: 5px;">
                    <?php _e('View Additional %', 'monospace-sales'); ?>
                </a>
            </div>
        </div>
        <?php
    }
}

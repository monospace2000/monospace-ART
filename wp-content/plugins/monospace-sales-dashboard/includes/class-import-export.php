<?php
/**
 * Import/Export - Backup and restore configurations
 */

if (!defined('ABSPATH')) exit;

class MSD_Import_Export {

    public static function init() {
        add_action('admin_post_msd_export', [__CLASS__, 'handle_export']);
        add_action('admin_post_msd_import', [__CLASS__, 'handle_import']);
    }

    /**
     * Output section
     */
    public static function output_section() {
        ?>
        <div class="msd-import-export-wrap">
            <h3><?php _e('Export Configuration', 'monospace-sales'); ?></h3>
            <p><?php _e('Download your current Sales Dashboard settings as a JSON file for backup or transfer.', 'monospace-sales'); ?></p>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="msd_export">
                <?php wp_nonce_field('msd_export', 'msd_export_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th><?php _e('Export Options', 'monospace-sales'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="export_global" value="1" checked>
                                <?php _e('Global Controls', 'monospace-sales'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="export_price_adj" value="1" checked>
                                <?php _e('Price Adjustments', 'monospace-sales'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="export_sale_rules" value="1" checked>
                                <?php _e('Sale Rules', 'monospace-sales'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="export_volume" value="1" checked>
                                <?php _e('Volume Discounts', 'monospace-sales'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="export_scheduling" value="1" checked>
                                <?php _e('Scheduling', 'monospace-sales'); ?>
                            </label><br>
                            <label>
                                <input type="checkbox" name="export_badges" value="1" checked>
                                <?php _e('Badges & Messages', 'monospace-sales'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Include Product Overrides', 'monospace-sales'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="export_products" value="1">
                                <?php _e('Export per-product override settings', 'monospace-sales'); ?>
                            </label>
                            <p class="description">
                                <?php _e('Warning: This may create a large file if you have many products.', 'monospace-sales'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('Download Configuration File', 'monospace-sales'); ?>
                    </button>
                </p>
            </form>

            <hr style="margin: 40px 0;">

            <h3><?php _e('Import Configuration', 'monospace-sales'); ?></h3>
            <p><?php _e('Upload a previously exported configuration file to restore settings.', 'monospace-sales'); ?></p>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="msd_import">
                <?php wp_nonce_field('msd_import', 'msd_import_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th><?php _e('Configuration File', 'monospace-sales'); ?></th>
                        <td>
                            <input type="file" name="import_file" accept=".json" required>
                            <p class="description">
                                <?php _e('Select a .json file exported from Sales Dashboard', 'monospace-sales'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Import Options', 'monospace-sales'); ?></th>
                        <td>
                            <label>
                                <input type="radio" name="import_mode" value="merge" checked>
                                <?php _e('Merge with existing settings', 'monospace-sales'); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="import_mode" value="replace">
                                <?php _e('Replace all settings (WARNING: Current settings will be lost)', 'monospace-sales'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php _e('Import Configuration', 'monospace-sales'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle export
     */
    public static function handle_export() {
        check_admin_referer('msd_export', 'msd_export_nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'monospace-sales'));
        }

        $export_data = [
            'version' => MSD_VERSION,
            'timestamp' => current_time('mysql'),
            'site_url' => get_site_url(),
        ];

        // Global controls
        if (!empty($_POST['export_global'])) {
            $export_data['global'] = [
                'enable' => get_option('msd_global_enable'),
                'sale_mode' => get_option('msd_sale_mode'),
                'show_original_price' => get_option('msd_show_original_price'),
                'priority' => get_option('msd_priority'),
            ];
        }

        // Price adjustments
        if (!empty($_POST['export_price_adj'])) {
            $export_data['price_adjustments'] = [
                'enable' => get_option('msd_price_adj_enable'),
                'percentage' => get_option('msd_price_adj_percentage'),
                'categories' => get_option('msd_price_adj_categories'),
                'tags' => get_option('msd_price_adj_tags'),
                'exclude_cats' => get_option('msd_price_adj_exclude_cats'),
                'rounding' => get_option('msd_rounding'),
                'charm_pricing' => get_option('msd_charm_pricing'),
            ];
        }

        // Sale rules
        if (!empty($_POST['export_sale_rules'])) {
            $export_data['sale_rules'] = [
                'categories' => get_option('msd_sale_categories'),
                'tags' => get_option('msd_sale_tags'),
                'exclude_categories' => get_option('msd_sale_exclude_categories'),
                'exclude_tags' => get_option('msd_sale_exclude_tags'),
            ];
        }

        // Volume discounts
        if (!empty($_POST['export_volume'])) {
            $export_data['volume_discounts'] = [
                'enable' => get_option('msd_volume_enable'),
                'rules' => get_option('msd_volume_rules'),
            ];
        }

        // Scheduling
        if (!empty($_POST['export_scheduling'])) {
            $export_data['scheduling'] = [
                'enable' => get_option('msd_schedule_enable'),
                'start' => get_option('msd_schedule_start'),
                'end' => get_option('msd_schedule_end'),
                'recurring_enable' => get_option('msd_recurring_enable'),
                'recurring_pattern' => get_option('msd_recurring_pattern'),
                'recurring_weekday' => get_option('msd_recurring_weekday'),
                'recurring_monthday' => get_option('msd_recurring_monthday'),
                'recurring_week' => get_option('msd_recurring_week'),
                'recurring_start_time' => get_option('msd_recurring_start_time'),
                'recurring_end_time' => get_option('msd_recurring_end_time'),
            ];
        }

        // Badges
        if (!empty($_POST['export_badges'])) {
            $export_data['badges'] = [
                'enable' => get_option('msd_badges_enable'),
                'text' => get_option('msd_badge_text'),
                'hint_almost' => get_option('msd_hint_almost'),
                'hint_active' => get_option('msd_hint_active'),
            ];
        }

        // Product overrides
        if (!empty($_POST['export_products'])) {
            global $wpdb;
            $meta_keys = ['_msd_enable_sale', '_msd_show_original', '_msd_exclude_price_adj', '_msd_exclude_volume', '_msd_custom_badge'];

            $products = [];
            foreach ($meta_keys as $key) {
                $results = $wpdb->get_results($wpdb->prepare(
                    "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
                    $key
                ));

                foreach ($results as $row) {
                    $products[$row->post_id][$key] = $row->meta_value;
                }
            }

            $export_data['products'] = $products;
        }

        // Generate filename
        $filename = 'msd-config-' . date('Y-m-d-His') . '.json';

        // Send headers
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Handle import
     */
    public static function handle_import() {
        check_admin_referer('msd_import', 'msd_import_nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'monospace-sales'));
        }

        if (empty($_FILES['import_file']['tmp_name'])) {
            wp_redirect(add_query_arg([
                'page' => 'wc-settings',
                'tab' => 'msd',
                'section' => 'import_export',
                'error' => 'no_file',
            ], admin_url('admin.php')));
            exit;
        }

        $file_content = file_get_contents($_FILES['import_file']['tmp_name']);
        $import_data = json_decode($file_content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_redirect(add_query_arg([
                'page' => 'wc-settings',
                'tab' => 'msd',
                'section' => 'import_export',
                'error' => 'invalid_json',
            ], admin_url('admin.php')));
            exit;
        }

        $mode = $_POST['import_mode'] ?? 'merge';

        // Global controls
        if (!empty($import_data['global'])) {
            foreach ($import_data['global'] as $key => $value) {
                update_option('msd_' . $key, $value);
            }
        }

        // Price adjustments
        if (!empty($import_data['price_adjustments'])) {
            foreach ($import_data['price_adjustments'] as $key => $value) {
                update_option('msd_price_adj_' . $key === 'percentage' ? $key : 'price_adj_' . $key, $value);
            }
            if (isset($import_data['price_adjustments']['rounding'])) {
                update_option('msd_rounding', $import_data['price_adjustments']['rounding']);
            }
            if (isset($import_data['price_adjustments']['charm_pricing'])) {
                update_option('msd_charm_pricing', $import_data['price_adjustments']['charm_pricing']);
            }
        }

        // Sale rules
        if (!empty($import_data['sale_rules'])) {
            foreach ($import_data['sale_rules'] as $key => $value) {
                update_option('msd_sale_' . $key, $value);
            }
        }

        // Volume discounts
        if (!empty($import_data['volume_discounts'])) {
            update_option('msd_volume_enable', $import_data['volume_discounts']['enable']);
            update_option('msd_volume_rules', $import_data['volume_discounts']['rules']);
        }

        // Scheduling
        if (!empty($import_data['scheduling'])) {
            foreach ($import_data['scheduling'] as $key => $value) {
                update_option('msd_' . $key, $value);
            }
        }

        // Badges
        if (!empty($import_data['badges'])) {
            foreach ($import_data['badges'] as $key => $value) {
                $opt_key = $key === 'text' ? 'msd_badge_text' : 'msd_' . $key;
                update_option($opt_key, $value);
            }
        }

        // Product overrides
        if (!empty($import_data['products']) && $mode === 'replace') {
            foreach ($import_data['products'] as $product_id => $meta) {
                foreach ($meta as $meta_key => $meta_value) {
                    update_post_meta($product_id, $meta_key, $meta_value);
                }
            }
        }

        wp_redirect(add_query_arg([
            'page' => 'wc-settings',
            'tab' => 'msd',
            'section' => 'import_export',
            'success' => '1',
        ], admin_url('admin.php')));
        exit;
    }
}
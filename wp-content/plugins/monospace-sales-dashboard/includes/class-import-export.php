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

            <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" target="_self">

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
                    <button type="button" class="button button-primary" id="msd-export-btn">
                        <?php _e('Download Configuration File', 'monospace-sales'); ?>
                    </button>

                    <script>
                    jQuery('#msd-export-btn').on('click', function() {
                        var form = jQuery(this).closest('form');
                        var data = form.serialize();

                        window.location.href = '<?php echo admin_url('admin-post.php'); ?>?' + data;
                    });
                    </script>
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
        if (!empty($_GET['export_global'])) {
            $export_data['global'] = [
                'enable' => get_option('msd_global_enable'),
                'sale_mode' => get_option('msd_sale_mode'),
                'show_original_price' => get_option('msd_show_original_price'),
                'priority' => get_option('msd_priority'),
            ];
        }

        // Price adjustments
        if (!empty($_GET['export_price_adj'])) {
            $export_data['price_adjustments'] = [
                'enable' => get_option('msd_price_adj_enable'),
                'percentage' => get_option('msd_price_adj_percentage'),
                'categories' => get_option('msd_price_adj_categories'),
                'tags' => get_option('msd_price_adj_tags'),
                'exclude_cats' => get_option('msd_price_adj_exclude_cats'),
                'exclude_tags' => get_option('msd_price_adj_exclude_tags'),
                'rounding' => get_option('msd_rounding'),
                'charm_pricing' => get_option('msd_charm_pricing'),
            ];
        }

        // Sale rules
        if (!empty($_GET['export_sale_rules'])) {
            $export_data['sale_rules'] = [
                'categories' => get_option('msd_sale_categories'),
                'tags' => get_option('msd_sale_tags'),
                'exclude_categories' => get_option('msd_sale_exclude_categories'),
                'exclude_tags' => get_option('msd_sale_exclude_tags'),
            ];
        }

        // Volume discounts
        if (!empty($_GET['export_volume'])) {
            $export_data['volume_discounts'] = [
                'enable' => get_option('msd_volume_enable'),
                'rules' => get_option('msd_volume_rules'),
            ];
        }

        // Free shipping
        if (!empty($_GET['export_freeship'])) {
            $export_data['free_shipping'] = [
                'enable' => get_option('msd_freeship_enable'),
                'coupon' => get_option('msd_freeship_coupon'),
                'rules' => get_option('msd_freeship_rules'),
                'hints_enable' => get_option('msd_freeship_hints_enable'),
                'hint_active' => get_option('msd_freeship_hint_active'),
                'hint_almost_amount' => get_option('msd_freeship_hint_almost_amount'),
                'hint_almost_qty' => get_option('msd_freeship_hint_almost_qty'),
            ];
        }

        // Scheduling
        if (!empty($_GET['export_scheduling'])) {
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
        if (!empty($_GET['export_badges'])) {
            $export_data['badges'] = [
                'enable' => get_option('msd_badges_enable'),
                'text' => get_option('msd_badge_text'),
                'hints_enable' => get_option('msd_hints_enable'),
                'hint_position' => get_option('msd_hint_position'),
                'hint_text_color' => get_option('msd_hint_text_color'),
                'hint_secondary_color' => get_option('msd_hint_secondary_color'),
                'hint_bg_color' => get_option('msd_hint_bg_color'),
                'hint_border_color' => get_option('msd_hint_border_color'),
                'hint_text_size' => get_option('msd_hint_text_size'),
                'hint_secondary_size' => get_option('msd_hint_secondary_size'),
                'hint_font_weight' => get_option('msd_hint_font_weight'),
                'hint_text_align' => get_option('msd_hint_text_align'),
                'hint_padding' => get_option('msd_hint_padding'),
                'hint_margin' => get_option('msd_hint_margin'),
                'hint_border_radius' => get_option('msd_hint_border_radius'),
                'hint_border_width' => get_option('msd_hint_border_width'),
                'hint_template_bundle' => get_option('msd_hint_template_bundle'),
                'hint_template_bxgy' => get_option('msd_hint_template_bxgy'),
                'hint_template_percent' => get_option('msd_hint_template_percent'),
                'hint_secondary_text' => get_option('msd_hint_secondary_text'),
                'hint_almost' => get_option('msd_hint_almost'),
                'hint_active' => get_option('msd_hint_active'),
            ];
        }

        // Product overrides
        if (!empty($_GET['export_products'])) {
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

        // Generate filename and content
        $filename = 'msd-config-' . date('Y-m-d-His') . '.json';
        $json_output = json_encode($export_data, JSON_PRETTY_PRINT);

        // Send headers
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . strlen($json_output));
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Expires: 0');

        echo $json_output;
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
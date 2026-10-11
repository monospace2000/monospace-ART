<?php
/**
 * Additional Percentage Tracker - Shows all products with additional percentage increases
 */

if (!defined('ABSPATH')) exit;

class MSD_Additional_Tracker {

    public static function init() {
        // No hooks needed - just provides output_section() for settings integration
    }

    /**
     * Output the tracker section
     */
    public static function output_section() {
        ?>
        <div class="msd-additional-tracker">
            <h3><?php _e('Products with Additional Percentage Increases', 'monospace-sales'); ?></h3>
            <p class="description">
                <?php _e('This page shows all products that have an additional percentage increase applied on top of the global price adjustment.', 'monospace-sales'); ?>
            </p>

            <?php
            // Get all products with additional percentage meta
            $products_with_additional = self::get_products_with_additional();

            if (empty($products_with_additional)) {
                echo '<div class="notice notice-info inline">';
                echo '<p>' . __('No products currently have additional percentage increases set.', 'monospace-sales') . '</p>';
                echo '</div>';
            } else {
                self::display_products_table($products_with_additional);
            }
            ?>

            <div style="margin-top: 20px;">
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=msd&section=bulk_editor'); ?>" class="button button-primary">
                    <?php _e('Go to Bulk Editor', 'monospace-sales'); ?>
                </a>
                <button type="button" id="msd-export-additional-list" class="button" style="margin-left: 10px;">
                    <?php _e('Export as CSV', 'monospace-sales'); ?>
                </button>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Export to CSV functionality
            $('#msd-export-additional-list').on('click', function() {
                var csv = 'Product ID,Product Name,SKU,Additional Percentage\n';

                $('.msd-additional-table tbody tr').each(function() {
                    var cells = $(this).find('td');
                    var row = [
                        cells.eq(0).text().trim(),
                        '"' + cells.eq(1).text().trim().replace(/"/g, '""') + '"',
                        cells.eq(2).text().trim(),
                        cells.eq(3).text().trim().replace('%', '')
                    ].join(',');
                    csv += row + '\n';
                });

                // Create download
                var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement('a');
                var url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', 'additional-percentages-' + new Date().toISOString().slice(0,10) + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        });
        </script>
        <?php
    }

    /**
     * Get all products with additional percentage meta
     */
    private static function get_products_with_additional() {
        global $wpdb;

        // Query all posts with _msd_additional_percentage meta
        $results = $wpdb->get_results("
            SELECT p.ID, p.post_title, pm.meta_value as additional_pct
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type IN ('product', 'product_variation')
            AND p.post_status = 'publish'
            AND pm.meta_key = '_msd_additional_percentage'
            AND pm.meta_value != ''
            AND pm.meta_value IS NOT NULL
            ORDER BY p.post_title ASC
        ");

        if (empty($results)) {
            return [];
        }

        // Enrich with product data
        $products = [];
        foreach ($results as $row) {
            $product = wc_get_product($row->ID);
            if (!$product) {
                continue;
            }

            $products[] = [
                'id' => $row->ID,
                'name' => $row->post_title,
                'sku' => $product->get_sku() ?: '—',
                'additional_pct' => floatval($row->additional_pct),
                'edit_url' => get_edit_post_link($row->ID),
            ];
        }

        return $products;
    }

    /**
     * Display products table
     */
    private static function display_products_table($products) {
        ?>
        <table class="wp-list-table widefat fixed striped msd-additional-table" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th style="width: 80px;"><?php _e('ID', 'monospace-sales'); ?></th>
                    <th><?php _e('Product Name', 'monospace-sales'); ?></th>
                    <th style="width: 120px;"><?php _e('SKU', 'monospace-sales'); ?></th>
                    <th style="width: 150px;"><?php _e('Additional %', 'monospace-sales'); ?></th>
                    <th style="width: 100px;"><?php _e('Actions', 'monospace-sales'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td><?php echo esc_html($product['id']); ?></td>
                    <td>
                        <strong>
                            <a href="<?php echo esc_url($product['edit_url']); ?>" target="_blank">
                                <?php echo esc_html($product['name']); ?>
                            </a>
                        </strong>
                    </td>
                    <td><?php echo esc_html($product['sku']); ?></td>
                    <td>
                        <span style="color: #d63638; font-weight: bold;">
                            +<?php echo esc_html($product['additional_pct']); ?>%
                        </span>
                    </td>
                    <td>
                        <a href="<?php echo esc_url($product['edit_url']); ?>" class="button button-small" target="_blank">
                            <?php _e('Edit', 'monospace-sales'); ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="5">
                        <?php printf(__('Total: %d products with additional percentage increases', 'monospace-sales'), count($products)); ?>
                    </th>
                </tr>
            </tfoot>
        </table>
        <?php
    }
}

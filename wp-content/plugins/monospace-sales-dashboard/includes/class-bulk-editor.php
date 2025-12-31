<?php
/**
 * Bulk Product Editor - Set overrides on multiple products
 */

if (!defined('ABSPATH')) exit;

class MSD_Bulk_Editor {

    public static function init() {
        add_action('admin_post_msd_bulk_edit', [__CLASS__, 'handle_bulk_edit']);
        add_action('wp_ajax_msd_search_products', [__CLASS__, 'ajax_search_products']);
        add_action('wp_ajax_msd_load_products_bulk', [__CLASS__, 'ajax_load_products']);
    }

    /**
     * Output section
     */
    public static function output_section() {
        ?>
        <div class="msd-bulk-editor-wrap">
            <h3><?php _e('Bulk Product Editor', 'monospace-sales'); ?></h3>
            <p><?php _e('Apply sale overrides to multiple products at once. Filter by category, tag, or search.', 'monospace-sales'); ?></p>

            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" id="msd-bulk-form">
                <input type="hidden" name="action" value="msd_bulk_edit">
                <?php wp_nonce_field('msd_bulk_edit', 'msd_bulk_nonce'); ?>

                <div class="msd-bulk-filter">
                    <h4><?php _e('Step 1: Select Products', 'monospace-sales'); ?></h4>

                    <table class="form-table">
                        <tr>
                            <th><?php _e('Filter Method', 'monospace-sales'); ?></th>
                            <td>
                                <select name="filter_method" id="msd-filter-method">
                                    <option value="category"><?php _e('By Category', 'monospace-sales'); ?></option>
                                    <option value="tag"><?php _e('By Tag', 'monospace-sales'); ?></option>
                                    <option value="search"><?php _e('Search Products', 'monospace-sales'); ?></option>
                                    <option value="manual"><?php _e('Manual Selection', 'monospace-sales'); ?></option>
                                    <option value="all"><?php _e('All Products', 'monospace-sales'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr class="msd-filter-category">
                            <th><?php _e('Category', 'monospace-sales'); ?></th>
                            <td>
                                <select name="filter_category" class="wc-enhanced-select" style="width:50%;">
                                    <option value=""><?php _e('Select category...', 'monospace-sales'); ?></option>
                                    <?php
                                    $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
                                    foreach ($categories as $cat) {
                                        echo '<option value="' . esc_attr($cat->term_id) . '">' . esc_html($cat->name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr class="msd-filter-tag">
                            <th><?php _e('Tag', 'monospace-sales'); ?></th>
                            <td>
                                <select name="filter_tag" class="wc-enhanced-select" style="width:50%;">
                                    <option value=""><?php _e('Select tag...', 'monospace-sales'); ?></option>
                                    <?php
                                    $tags = get_terms(['taxonomy' => 'product_tag', 'hide_empty' => false]);
                                    foreach ($tags as $tag) {
                                        echo '<option value="' . esc_attr($tag->slug) . '">' . esc_html($tag->name) . '</option>';
                                    }
                                    ?>
                                </select>
                            </td>
                        </tr>
                        <tr class="msd-filter-search">
                            <th><?php _e('Search', 'monospace-sales'); ?></th>
                            <td>
                                <input type="text" name="filter_search" id="msd-search-input" placeholder="<?php _e('Type to search products...', 'monospace-sales'); ?>" style="width:50%;">
                                <div id="msd-search-results" class="msd-search-results"></div>
                            </td>
                        </tr>
                        <tr class="msd-filter-manual">
                            <th><?php _e('Product IDs', 'monospace-sales'); ?></th>
                            <td>
                                <textarea name="filter_manual" rows="3" style="width:50%;" placeholder="<?php _e('Enter product IDs, one per line or comma-separated', 'monospace-sales'); ?>"></textarea>
                            </td>
                        </tr>
                    </table>

                    <p>
                        <button type="button" class="button" id="msd-load-products"><?php _e('Load Products', 'monospace-sales'); ?></button>
                        <span class="spinner" style="float:none;margin:0 10px;"></span>
                        <span id="msd-product-count"></span>
                    </p>

                    <div id="msd-product-list" class="msd-product-list"></div>
                </div>

                <hr style="margin:30px 0;">

                <div class="msd-bulk-actions">
                    <h4><?php _e('Step 2: Choose Override Settings', 'monospace-sales'); ?></h4>

                    <table class="form-table">
                        <tr>
                            <th><?php _e('Sale Override', 'monospace-sales'); ?></th>
                            <td>
                                <select name="bulk_enable_sale">
                                    <option value=""><?php _e('— No Change —', 'monospace-sales'); ?></option>
                                    <option value="yes"><?php _e('Force ON', 'monospace-sales'); ?></option>
                                    <option value="no"><?php _e('Force OFF', 'monospace-sales'); ?></option>
                                    <option value="reset"><?php _e('Reset (use global rules)', 'monospace-sales'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Show Original Price', 'monospace-sales'); ?></th>
                            <td>
                                <select name="bulk_show_original">
                                    <option value=""><?php _e('— No Change —', 'monospace-sales'); ?></option>
                                    <option value="yes"><?php _e('Show crossed-out price', 'monospace-sales'); ?></option>
                                    <option value="no"><?php _e('Hide crossed-out price', 'monospace-sales'); ?></option>
                                    <option value="reset"><?php _e('Reset (use global setting)', 'monospace-sales'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Price Adjustments', 'monospace-sales'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="bulk_exclude_price_adj" value="1">
                                    <?php _e('Exclude from global price adjustments', 'monospace-sales'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Volume Discounts', 'monospace-sales'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="bulk_exclude_volume" value="1">
                                    <?php _e('Exclude from volume discounts', 'monospace-sales'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Custom Badge', 'monospace-sales'); ?></th>
                            <td>
                                <input type="text" name="bulk_custom_badge" placeholder="<?php _e('e.g., LIMITED TIME', 'monospace-sales'); ?>" style="width:300px;">
                                <p class="description"><?php _e('Leave empty to keep existing badges', 'monospace-sales'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <input type="hidden" name="product_ids" id="msd-product-ids" value="">

                <p class="submit">
                    <button type="submit" class="button button-primary" id="msd-bulk-submit" disabled>
                        <?php _e('Apply to Selected Products', 'monospace-sales'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle bulk edit
     */
    public static function handle_bulk_edit() {
        check_admin_referer('msd_bulk_edit', 'msd_bulk_nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'monospace-sales'));
        }

        $product_ids = !empty($_POST['product_ids']) ? array_map('intval', explode(',', $_POST['product_ids'])) : [];

        if (empty($product_ids)) {
            wp_redirect(add_query_arg([
                'page' => 'wc-settings',
                'tab' => 'msd',
                'section' => 'bulk_editor',
                'error' => 'no_products',
            ], admin_url('admin.php')));
            exit;
        }

        $updated = 0;

        foreach ($product_ids as $product_id) {
            // Sale override
            if (!empty($_POST['bulk_enable_sale'])) {
                if ($_POST['bulk_enable_sale'] === 'reset') {
                    delete_post_meta($product_id, '_msd_enable_sale');
                } else {
                    update_post_meta($product_id, '_msd_enable_sale', sanitize_text_field($_POST['bulk_enable_sale']));
                }
            }

            // Show original price
            if (!empty($_POST['bulk_show_original'])) {
                if ($_POST['bulk_show_original'] === 'reset') {
                    delete_post_meta($product_id, '_msd_show_original');
                } else {
                    update_post_meta($product_id, '_msd_show_original', sanitize_text_field($_POST['bulk_show_original']));
                }
            }

            // Price adjustment exclusion
            if (isset($_POST['bulk_exclude_price_adj'])) {
                update_post_meta($product_id, '_msd_exclude_price_adj', 'yes');
            }

            // Volume exclusion
            if (isset($_POST['bulk_exclude_volume'])) {
                update_post_meta($product_id, '_msd_exclude_volume', 'yes');
            }

            // Custom badge
            if (!empty($_POST['bulk_custom_badge'])) {
                update_post_meta($product_id, '_msd_custom_badge', sanitize_text_field($_POST['bulk_custom_badge']));
            }

            $updated++;
        }

        wp_redirect(add_query_arg([
            'page' => 'wc-settings',
            'tab' => 'msd',
            'section' => 'bulk_editor',
            'success' => '1',
            'updated' => $updated,
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * AJAX: Search products
     */
    public static function ajax_search_products() {
        check_ajax_referer('wc_search_products', 'security');

        $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';

        $args = [
            'post_type' => 'product',
            'posts_per_page' => 20,
            's' => $term,
        ];

        $query = new WP_Query($args);
        $results = [];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $results[] = [
                    'id' => get_the_ID(),
                    'title' => get_the_title(),
                ];
            }
        }

        wp_reset_postdata();
        wp_send_json($results);
    }

    /**
     * AJAX: Load products for bulk edit
     */
    public static function ajax_load_products() {
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $args = [
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids',
        ];

        // Filter by category
        if (!empty($_POST['category'])) {
            $args['tax_query'] = [[
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => intval($_POST['category']),
            ]];
        }

        // Filter by tag
        if (!empty($_POST['tag'])) {
            $args['tax_query'] = [[
                'taxonomy' => 'product_tag',
                'field' => 'slug',
                'terms' => sanitize_text_field($_POST['tag']),
            ]];
        }

        // Manual IDs
        if (!empty($_POST['ids'])) {
            $ids = preg_split('/[\s,]+/', $_POST['ids']);
            $args['post__in'] = array_map('intval', $ids);
        }

        $query = new WP_Query($args);
        $products = [];

        foreach ($query->posts as $id) {
            $products[] = [
                'id' => $id,
                'title' => get_the_title($id),
            ];
        }

        wp_send_json_success($products);
    }
}
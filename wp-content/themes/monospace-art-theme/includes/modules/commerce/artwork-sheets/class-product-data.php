<?php
/**
 * Product Data Handler
 *
 * @package monospace-art-theme
 */

class Monospace_Product_Data {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function gather_data($product_id, $options = []) {
        $product = wc_get_product($product_id);
        if (!$product) return null;

        $defaults = [
            'include_image' => true,
            'include_price' => true,
            'include_availability' => true,
            'include_wc_id' => false,
            'include_post_id' => false,
        ];
        $options = wp_parse_args($options, $defaults);

        // Get adjusted price (applies Sales Dashboard adjustments)
        $adjusted_price = $this->get_adjusted_price($product, $product_id);
        $price_formatted = $adjusted_price ? wc_price($adjusted_price) : '';

        $data = [
            'title' => $product->get_name(),
            'sku' => $product->get_sku(),
            'attributes' => $this->extract_attributes($product, $product_id),
            'availability_status' => get_post_meta($product_id, 'painting_availability_status', true),
            'stock_status' => $product->get_stock_status(),
            'in_stock' => $product->is_in_stock(),
            'gallery_name' => get_post_meta($product_id, 'painting_gallery_name', true),
            'gallery_url' => get_post_meta($product_id, 'painting_gallery_url', true),
            'image_url' => $this->get_image_url($product),
            'artist_name' => get_option('monospace_sheet_artist_name', 'Hens Breet'),
            'artist_website' => get_option('monospace_sheet_artist_website', 'https://art.monospace.com'),
            'price' => $adjusted_price,
            'price_formatted' => $price_formatted,
        ];

        if ($options['include_wc_id']) {
            $data['wc_product_id'] = $product_id;
        }

        if ($options['include_post_id']) {
            $data['wp_post_id'] = $product_id;
        }

        return $data;
    }

    private function extract_attributes($product, $product_id) {
        $order = ['format', 'medium', 'surface', 'size'];
        $attributes = $product->get_attributes();
        $output = [];

        foreach ($order as $attr_name) {
            foreach ($attributes as $key => $attribute) {
                $label = strtolower(wc_attribute_label($attribute->get_name()));

                if ($label === strtolower($attr_name)) {
                    $value = $this->get_attribute_value($attribute, $product_id);
                    if ($value) {
                        $output[$label] = $value;
                    }
                    unset($attributes[$key]);
                    break;
                }
            }
        }

        // Remaining attributes
        foreach ($attributes as $attribute) {
            $label = strtolower(wc_attribute_label($attribute->get_name()));
            $value = $this->get_attribute_value($attribute, $product_id);
            if ($value) {
                $output[$label] = $value;
            }
        }

        return $output;
    }

    private function get_attribute_value($attribute, $product_id) {
        if ($attribute->is_taxonomy()) {
            $terms = wp_get_post_terms($product_id, $attribute->get_name(), ['fields' => 'names']);
            return implode(', ', $terms);
        } else {
            $options = $attribute->get_options();
            return $options ? implode(', ', $options) : '';
        }
    }

    private function get_image_url($product) {
        $image_id = $product->get_image_id();
        return $image_id ? wp_get_attachment_image_url($image_id, 'large') : '';
    }

    /**
     * Get adjusted price with Sales Dashboard modifications applied
     * This manually applies the same logic as MSD_Price_Adjustments
     */
    private function get_adjusted_price($product, $product_id) {
        $price = $product->get_price();

        if (!$price) {
            return $price;
        }

        // Check if Sales Dashboard price adjustments are enabled
        if (get_option('msd_price_adj_enable') !== 'yes' || get_option('msd_global_enable') !== 'yes') {
            return $price;
        }

        // Check product-level exclusion
        if (class_exists('MSD_Product_Overrides') && MSD_Product_Overrides::is_excluded_from_price_adj($product_id)) {
            return $price;
        }

        // Exclude commission products
        if ($product->get_type() === 'custom' ||
            $product->get_meta('_commission_key') ||
            has_term('commission', 'product_cat', $product_id)) {
            return $price;
        }

        // Check category exclusions
        $exclude_cats = (array) get_option('msd_price_adj_exclude_cats', []);
        if (!empty($exclude_cats) && has_term($exclude_cats, 'product_cat', $product_id)) {
            return $price;
        }

        // Check tag exclusions
        $exclude_tags = (array) get_option('msd_price_adj_exclude_tags', []);
        if (!empty($exclude_tags) && has_term($exclude_tags, 'product_tag', $product_id)) {
            return $price;
        }

        // Check if product qualifies (if categories are specified)
        $allowed_cats = (array) get_option('msd_price_adj_categories', []);
        $allowed_tags = (array) get_option('msd_price_adj_tags', []);

        // If categories/tags are specified, product must match at least one
        if (!empty($allowed_cats) || !empty($allowed_tags)) {
            $matches = false;

            if (!empty($allowed_cats) && has_term($allowed_cats, 'product_cat', $product_id)) {
                $matches = true;
            }

            if (!$matches && !empty($allowed_tags) && has_term($allowed_tags, 'product_tag', $product_id)) {
                $matches = true;
            }

            if (!$matches) {
                return $price;
            }
        }

        // Apply global percentage adjustment
        $percentage = (float) get_option('msd_price_adj_percentage', 25);
        $price *= 1 + ($percentage / 100);

        // Apply per-product additional percentage (if set)
        $product_percentage = get_post_meta($product_id, '_msd_additional_percentage', true);
        if ($product_percentage !== '' && is_numeric($product_percentage)) {
            $product_percentage = (float) $product_percentage;
            $price *= 1 + ($product_percentage / 100);
        }

        // Apply rounding
        $rounding = get_option('msd_rounding', 'none');
        switch ($rounding) {
            case 'up':
                $price = ceil($price);
                break;
            case 'down':
                $price = floor($price);
                break;
            case 'nearest':
                $price = round($price);
                break;
        }

        // Apply charm pricing
        $charm = get_option('msd_charm_pricing', 'none');
        switch ($charm) {
            case 'up':
                $price = floor($price) + 0.99;
                break;
            case 'down':
                $price = ceil($price) - 0.01;
                break;
        }

        return round($price, wc_get_price_decimals());
    }

    public function get_filter_options() {
        $products = wc_get_products(['limit' => -1, 'return' => 'ids']);

        $formats = $mediums = $surfaces = $sizes = $statuses = [];

        foreach ($products as $id) {
            $product = wc_get_product($id);
            $attributes = $product->get_attributes();

            foreach ($attributes as $attribute) {
                $label = strtolower(wc_attribute_label($attribute->get_name()));
                $value = $this->get_attribute_value($attribute, $id);

                if (!$value) continue;

                switch ($label) {
                    case 'format': $formats[$value] = $value; break;
                    case 'medium': $mediums[$value] = $value; break;
                    case 'surface': $surfaces[$value] = $value; break;
                    case 'size': $sizes[$value] = $value; break;
                }
            }

            $status = get_post_meta($id, 'painting_availability_status', true);
            $status_display = $status ?: 'available';
            $statuses[$status_display] = ucfirst($status_display);
        }

        ksort($formats);
        ksort($mediums);
        ksort($surfaces);
        ksort($sizes);
        ksort($statuses);

        return compact('formats', 'mediums', 'surfaces', 'sizes', 'statuses');
    }

    public function get_product_list() {
        $products = wc_get_products([
            'limit' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'return' => 'ids'
        ]);

        $rows = [];
        foreach ($products as $id) {
            $product = wc_get_product($id);
            $attributes = $this->extract_attributes($product, $id);

            // Get related post ID
            $related_post_id = get_post_meta($id, '_related_post_id', true);

            $rows[] = [
                'id' => $id,
                'related_post_id' => $related_post_id,
                'title' => $product->get_name(),
                'sku' => $product->get_sku() ?: 'No SKU',
                'image_url' => $this->get_image_url($product),
                'format' => $attributes['format'] ?? '',
                'medium' => $attributes['medium'] ?? '',
                'surface' => $attributes['surface'] ?? '',
                'size' => $attributes['size'] ?? '',
                'date' => get_post_field('post_date', $id),
                'status' => get_post_meta($id, 'painting_availability_status', true) ?: 'available',
                'has_price' => $product->get_price() ? 'true' : 'false',
            ];
        }

        return $rows;
    }
}
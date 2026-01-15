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

        $data = [
            'title' => $product->get_name(),
            'sku' => $product->get_sku(),
            'attributes' => $this->extract_attributes($product, $product_id),
            'availability_status' => function_exists('get_field') ? get_field('painting_availability_status', $product_id) : '',
            'stock_status' => $product->get_stock_status(),
            'in_stock' => $product->is_in_stock(),
            'gallery_name' => function_exists('get_field') ? get_field('painting_gallery_name', $product_id) : '',
            'gallery_url' => function_exists('get_field') ? get_field('painting_gallery_url', $product_id) : '',
            'image_url' => $this->get_image_url($product),
            'artist_name' => get_option('monospace_sheet_artist_name', 'Hens Breet'),
            'artist_website' => get_option('monospace_sheet_artist_website', 'https://art.monospace.com'),
            'price' => $product->get_price(),
            'price_formatted' => $product->get_price_html(),
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

            $status = function_exists('get_field') ? get_field('painting_availability_status', $id) : '';
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
                'status' => function_exists('get_field') ? get_field('painting_availability_status', $id) : 'available',
                'has_price' => $product->get_price() ? 'true' : 'false',
            ];
        }

        return $rows;
    }
}
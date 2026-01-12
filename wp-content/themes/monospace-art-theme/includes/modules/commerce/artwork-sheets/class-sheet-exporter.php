<?php
/**
 * Sheet Exporter
 * Handles PDF, JSON, CSV exports
 *
 * @package monospace-art-theme
 */

class Monospace_Sheet_Exporter {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function handle_request() {
        $action = sanitize_text_field($_POST['action']);
        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];

        if (empty($product_ids)) {
            wp_die('No products selected');
        }

        $options = [
            'include_image' => isset($_POST['include_image']),
            'include_price' => isset($_POST['include_price']),
            'include_availability' => isset($_POST['include_availability']),
            'include_wc_id' => isset($_POST['include_wc_id']),
            'include_post_id' => isset($_POST['include_post_id']),
            'include_loan_section' => isset($_POST['include_loan_section']),
            'include_footer' => isset($_POST['include_footer']),
            'include_generated_date' => isset($_POST['include_generated_date']),
            'layout' => isset($_POST['layout']) ? sanitize_text_field($_POST['layout']) : 'single'
        ];

        switch ($action) {
            case 'preview':
                Monospace_Sheet_Renderer::instance()->render_preview($product_ids, $options);
                exit;

            case 'json':
                $this->export_json($product_ids, $options);
                break;

            case 'csv':
                $this->export_csv($product_ids, $options);
                break;
        }
    }

    private function export_json($product_ids, $options) {
        $data_handler = Monospace_Product_Data::instance();
        $export_data = [];

        foreach ($product_ids as $product_id) {
            $data = $data_handler->gather_data($product_id, $options);
            if ($data) {
                // Remove image URLs for cleaner JSON
                unset($data['image_url']);
                $export_data[] = $data;
            }
        }

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="artwork-sheets-' . date('Y-m-d') . '.json"');
        echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function export_csv($product_ids, $options) {
        $data_handler = Monospace_Product_Data::instance();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="artwork-sheets-' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM for Excel compatibility
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Build comprehensive header row with all fields
        $headers = [
            'Title',
            'SKU',
            'Format',
            'Medium',
            'Surface',
            'Size',
            'Availability Status',
            'Stock Status',
            'In Stock',
            'Gallery Name',
            'Gallery URL',
            'Artist Name',
            'Artist Website'
        ];

        if ($options['include_price']) {
            $headers[] = 'Price';
            $headers[] = 'Price Formatted';
        }
        if ($options['include_wc_id']) {
            $headers[] = 'WC Product ID';
        }
        if ($options['include_post_id']) {
            $headers[] = 'WP Post ID';
        }

        fputcsv($output, $headers);

        // Data rows
        foreach ($product_ids as $product_id) {
            $data = $data_handler->gather_data($product_id, $options);
            if (!$data) continue;

            $row = [
                $data['title'],
                $data['sku'] ?: '',
                $data['attributes']['format'] ?? '',
                $data['attributes']['medium'] ?? '',
                $data['attributes']['surface'] ?? '',
                $data['attributes']['size'] ?? '',
                ucfirst($data['availability_status'] ?: 'available'),
                $data['stock_status'],
                $data['in_stock'] ? 'Yes' : 'No',
                $data['gallery_name'] ?: '',
                $data['gallery_url'] ?: '',
                $data['artist_name'],
                $data['artist_website']
            ];

            if ($options['include_price']) {
                $row[] = $data['price'] ?? '';
                $row[] = strip_tags($data['price_formatted'] ?? '');
            }

            if ($options['include_wc_id']) {
                $row[] = $data['wc_product_id'] ?? '';
            }

            if ($options['include_post_id']) {
                $row[] = $data['wp_post_id'] ?? '';
            }

            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }
}
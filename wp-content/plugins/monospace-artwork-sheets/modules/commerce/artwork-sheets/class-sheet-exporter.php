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
            'images_only' => isset($_POST['images_only']),
            'include_availability' => isset($_POST['include_availability']),
            'include_price' => isset($_POST['include_price']),
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

            case 'images':
                $this->export_images($product_ids, $options);
                break;
        }
    }

    private function export_json($product_ids, $options) {
        $data_handler = Monospace_Product_Data::instance();
        $export_data = [];

        foreach ($product_ids as $product_id) {
            // Force include product ID for re-importing
            $options['include_wc_id'] = true;

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
            'Artist Website',
            'Price',
            'Price Formatted'
        ];
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
                $data['artist_website'],
                $data['price'] ?? '',
                strip_tags($data['price_formatted'] ?? '')
            ];

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

    private function export_images($product_ids, $options) {
        $data_handler = Monospace_Product_Data::instance();

        // Check if ZipArchive is available
        if (!class_exists('ZipArchive')) {
            wp_die('ZipArchive extension is not available on this server.');
        }

        // Create temporary directory for images
        $temp_dir = sys_get_temp_dir() . '/artwork-images-' . uniqid();
        if (!mkdir($temp_dir, 0755, true)) {
            wp_die('Failed to create temporary directory.');
        }

        $zip = new ZipArchive();
        $zip_filename = 'artwork-images-' . date('Y-m-d-His') . '.zip';
        $zip_filepath = sys_get_temp_dir() . '/' . $zip_filename;

        if ($zip->open($zip_filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            wp_die('Failed to create ZIP file.');
        }

        $image_count = 0;
        $errors = [];

        foreach ($product_ids as $product_id) {
            $data = $data_handler->gather_data($product_id, $options);
            if (!$data || empty($data['image_url'])) {
                continue;
            }

            // Get image URL and download it
            $image_url = $data['image_url'];
            $image_data = @file_get_contents($image_url);

            if ($image_data === false) {
                $errors[] = "Failed to download image for: " . $data['title'];
                continue;
            }

            // Determine file extension
            $extension = pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION);
            if (empty($extension)) {
                $extension = 'jpg'; // Default extension
            }

            // Create safe filename using SKU or title
            $safe_name = $data['sku'] ?: sanitize_file_name($data['title']);
            $safe_name = preg_replace('/[^a-zA-Z0-9-_]/', '_', $safe_name);
            $filename = $safe_name . '.' . $extension;

            // Handle duplicate filenames
            $counter = 1;
            $original_filename = $filename;
            while ($zip->locateName($filename) !== false) {
                $filename = pathinfo($original_filename, PATHINFO_FILENAME) . '_' . $counter . '.' . $extension;
                $counter++;
            }

            // Add image to ZIP
            $zip->addFromString($filename, $image_data);
            $image_count++;
        }

        // Create a metadata CSV file
        $csv_data = "Filename,Title,SKU,Format,Medium,Surface,Size\n";
        foreach ($product_ids as $product_id) {
            $data = $data_handler->gather_data($product_id, $options);
            if (!$data || empty($data['image_url'])) {
                continue;
            }

            $image_url = $data['image_url'];
            $extension = pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION);
            if (empty($extension)) {
                $extension = 'jpg';
            }

            $safe_name = $data['sku'] ?: sanitize_file_name($data['title']);
            $safe_name = preg_replace('/[^a-zA-Z0-9-_]/', '_', $safe_name);
            $filename = $safe_name . '.' . $extension;

            $csv_data .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $filename,
                str_replace('"', '""', $data['title']),
                str_replace('"', '""', $data['sku'] ?: ''),
                str_replace('"', '""', $data['attributes']['format'] ?? ''),
                str_replace('"', '""', $data['attributes']['medium'] ?? ''),
                str_replace('"', '""', $data['attributes']['surface'] ?? ''),
                str_replace('"', '""', $data['attributes']['size'] ?? '')
            );
        }

        // Add metadata CSV to ZIP
        $zip->addFromString('_metadata.csv', $csv_data);

        // Add README file with export info
        $readme = "Artwork Images Export\n";
        $readme .= "=====================\n\n";
        $readme .= "Export Date: " . date('Y-m-d H:i:s') . "\n";
        $readme .= "Total Images: " . $image_count . "\n\n";
        if (!empty($errors)) {
            $readme .= "Errors:\n";
            foreach ($errors as $error) {
                $readme .= "- " . $error . "\n";
            }
        }
        $readme .= "\nSee _metadata.csv for details about each image.\n";
        $zip->addFromString('README.txt', $readme);

        $zip->close();

        // Clean up temp directory
        @rmdir($temp_dir);

        // Send ZIP file to browser
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
        header('Content-Length: ' . filesize($zip_filepath));
        readfile($zip_filepath);

        // Clean up ZIP file
        @unlink($zip_filepath);
        exit;
    }
}
<?php
/**
 * Public Links Handler
 * Generates shareable short URLs for artwork sheets
 *
 * @package monospace-art-theme
 */

class Monospace_Sheet_Public_Links {
    private static $instance = null;
    private $transient_prefix = 'msp_sheet_';
    private $expiration = 90 * DAY_IN_SECONDS; // 90 days

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Register endpoint directly since we're loaded during init
        $this->register_endpoint();
        add_action('parse_request', [$this, 'handle_public_request']);
        add_action('wp_ajax_generate_public_sheet_link', [$this, 'ajax_generate_link']);
    }

    /**
     * Register rewrite rule for public sheet URLs
     */
    public function register_endpoint() {
        add_rewrite_rule(
            '^artwork-sheets/([a-zA-Z0-9]+)/?$',
            'index.php?artwork_sheet_code=$matches[1]',
            'top'
        );
        add_rewrite_tag('%artwork_sheet_code%', '([a-zA-Z0-9]+)');

        // One-time flush after adding endpoint
        if (!get_option('msp_sheet_links_flushed_v2')) {
            flush_rewrite_rules();
            update_option('msp_sheet_links_flushed_v2', true);
        }
    }

    /**
     * Handle public sheet requests
     */
    public function handle_public_request($wp) {
        if (!isset($wp->query_vars['artwork_sheet_code']) || empty($wp->query_vars['artwork_sheet_code'])) {
            return;
        }

        $code = $wp->query_vars['artwork_sheet_code'];
        $data = $this->get_sheet_data($code);

        if (!$data) {
            wp_die('This artwork sheet link has expired or is invalid.', 'Sheet Not Found', ['response' => 404]);
        }

        // Render the sheet publicly
        $this->render_public_sheet($data);
        exit;
    }

    /**
     * AJAX handler to generate a public link
     */
    public function ajax_generate_link() {
        check_ajax_referer('monospace_artwork_sheet', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : [];
        if (empty($product_ids)) {
            wp_send_json_error('No products selected');
        }

        $options = [
            'include_image' => isset($_POST['include_image']) && $_POST['include_image'] === 'true',
            'include_availability' => isset($_POST['include_availability']) && $_POST['include_availability'] === 'true',
            'include_price' => isset($_POST['include_price']) && $_POST['include_price'] === 'true',
            'include_wc_id' => isset($_POST['include_wc_id']) && $_POST['include_wc_id'] === 'true',
            'include_post_id' => isset($_POST['include_post_id']) && $_POST['include_post_id'] === 'true',
            'include_loan_section' => isset($_POST['include_loan_section']) && $_POST['include_loan_section'] === 'true',
            'include_footer' => isset($_POST['include_footer']) && $_POST['include_footer'] === 'true',
            'include_generated_date' => isset($_POST['include_generated_date']) && $_POST['include_generated_date'] === 'true',
            'layout' => isset($_POST['layout']) ? sanitize_text_field($_POST['layout']) : 'single'
        ];

        $code = $this->generate_link($product_ids, $options);
        $url = home_url('/artwork-sheets/' . $code . '/');

        wp_send_json_success([
            'code' => $code,
            'url' => $url,
            'expires' => date('F j, Y', time() + $this->expiration)
        ]);
    }

    /**
     * Generate a short code and store the configuration
     */
    public function generate_link($product_ids, $options) {
        $code = $this->generate_code();

        $data = [
            'product_ids' => $product_ids,
            'options' => $options,
            'created' => time(),
            'expires' => time() + $this->expiration,
            'created_by' => get_current_user_id()
        ];

        // Store directly in options to avoid object cache issues
        $option_key = $this->transient_prefix . $code;
        update_option($option_key, $data, false); // false = don't autoload

        return $code;
    }

    /**
     * Retrieve sheet data by code
     */
    public function get_sheet_data($code) {
        $code = preg_replace('/[^a-zA-Z0-9]/', '', $code);
        $option_key = $this->transient_prefix . $code;
        $data = get_option($option_key, false);

        // Check expiration
        if ($data && isset($data['expires']) && $data['expires'] < time()) {
            delete_option($option_key);
            return false;
        }

        return $data;
    }

    /**
     * Generate a random short code
     */
    private function generate_code($length = 8) {
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }

    /**
     * Render the public sheet
     */
    private function render_public_sheet($data) {
        $product_ids = $data['product_ids'];
        $options = $data['options'];

        // Use the existing renderer
        Monospace_Sheet_Renderer::instance()->render_preview($product_ids, $options);
    }
}

// Initialize
Monospace_Sheet_Public_Links::instance();

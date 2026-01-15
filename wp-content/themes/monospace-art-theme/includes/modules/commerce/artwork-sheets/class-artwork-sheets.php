<?php
/**
 * Artwork Sheets Main Class
 * Orchestrates all artwork sheet functionality
 *
 * @package monospace-art-theme
 */

class Monospace_Artwork_Sheets {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once __DIR__ . '/class-sheet-settings.php';
        require_once __DIR__ . '/class-sheet-generator.php';
        require_once __DIR__ . '/class-product-data.php';
        require_once __DIR__ . '/class-sheet-renderer.php';
        require_once __DIR__ . '/class-sheet-exporter.php';
        require_once __DIR__ . '/class-public-links.php';
    }

    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'handle_export_request']);
    }

    public function add_admin_menu() {
        add_menu_page(
            'Artwork Sheets',
            'Artwork Sheets',
            'manage_woocommerce',
            'monospace-artwork-sheets',
            [Monospace_Sheet_Generator::instance(), 'render_page'],
            'dashicons-media-document',
            56
        );

        add_submenu_page(
            'monospace-artwork-sheets',
            'Sheet Settings',
            'Settings',
            'manage_woocommerce',
            'monospace-artwork-sheets-settings',
            [Monospace_Sheet_Settings::instance(), 'render_page']
        );
    }

    public function handle_export_request() {
        if (!isset($_POST['artwork_sheet_nonce']) || !isset($_POST['action'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['artwork_sheet_nonce'], 'monospace_artwork_sheet')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }

        Monospace_Sheet_Exporter::instance()->handle_request();
    }
}

// Initialize
Monospace_Artwork_Sheets::instance();
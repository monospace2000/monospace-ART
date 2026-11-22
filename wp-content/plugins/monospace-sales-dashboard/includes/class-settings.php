<?php
/**
 * Settings Manager - Unified admin interface
 */

if (!defined('ABSPATH')) exit;

class MSD_Settings {

    /** Menu slug of the Sales Dashboard screen (admin.php?page=monospace-sales) */
    const PAGE = 'monospace-sales';

    /** Sections whose fields are WooCommerce-style fields saved by this screen's own form */
    const FORM_SECTIONS = ['global', 'volume_discounts', 'free_shipping', 'messages'];

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_init', [__CLASS__, 'redirect_old_tab'], 1);
        add_action('admin_init', [__CLASS__, 'maybe_save']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Address of a section of the screen (used by every link and redirect)
     */
    public static function url($section = 'dashboard', $args = []) {
        return add_query_arg(array_merge(['page' => self::PAGE, 'section' => $section], $args), admin_url('admin.php'));
    }

    /**
     * Top-level menu (it used to be a tab in WooCommerce > Settings)
     */
    public static function add_menu() {
        add_menu_page(
            __('Sales Dashboard', 'monospace-sales'),
            __('Sales', 'monospace-sales'),
            'manage_woocommerce',
            self::PAGE,
            [__CLASS__, 'output_settings'],
            'dashicons-tag',
            '56.5'
        );
    }

    /**
     * Old bookmarks and links: WooCommerce > Settings > Sales Dashboard now lives at the menu page
     */
    public static function redirect_old_tab() {
        if (!is_admin() || ($_GET['page'] ?? '') !== 'wc-settings' || ($_GET['tab'] ?? '') !== 'msd') {
            return;
        }
        $args = array_intersect_key($_GET, array_flip(['saved', 'success', 'updated', 'error', 'settings-updated']));
        wp_safe_redirect(self::url(sanitize_key($_GET['section'] ?? 'dashboard'), array_map('sanitize_text_field', $args)));
        exit;
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_assets($hook) {
        if ('toplevel_page_' . self::PAGE !== $hook) {
            return;
        }

        // Versioned by file date, so a style change reaches the browser at once
        wp_enqueue_style('msd-admin', MSD_PLUGIN_URL . 'assets/admin.css', [], MSD_VERSION . '.' . filemtime(MSD_PLUGIN_DIR . 'assets/admin.css'));
        wp_enqueue_script('msd-admin', MSD_PLUGIN_URL . 'assets/admin.js', ['jquery'], MSD_VERSION . '.' . filemtime(MSD_PLUGIN_DIR . 'assets/admin.js'), true);

        // Localize script with AJAX URL
        wp_localize_script('msd-admin', 'msdAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('msd_ajax')
        ]);
    }

    /**
     * The sections, in menu order, with a dashicon each
     */
    private static function sections() {
        return [
            'dashboard' => [__('Overview', 'monospace-sales'), 'dashicons-chart-area'],
            'global' => [__('Global Controls', 'monospace-sales'), 'dashicons-admin-generic'],
            'campaigns' => [__('Campaigns', 'monospace-sales'), 'dashicons-megaphone'],
            'volume_discounts' => [__('Volume Discounts', 'monospace-sales'), 'dashicons-database'],
            'free_shipping' => [__('Free Shipping', 'monospace-sales'), 'dashicons-airplane'],
            'messages' => [__('Messages', 'monospace-sales'), 'dashicons-format-status'],
            'bulk_editor' => [__('Bulk Editor', 'monospace-sales'), 'dashicons-edit'],
            'additional_tracker' => [__('Additional % Tracker', 'monospace-sales'), 'dashicons-chart-line'],
            'import_export' => [__('Import/Export', 'monospace-sales'), 'dashicons-database-export'],
        ];
    }

    /**
     * Output the screen: heading, section links, then the section
     */
    public static function output_settings() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'monospace-sales'));
        }

        $sections = self::sections();
        $current_section = isset($_GET['section']) ? sanitize_key($_GET['section']) : 'dashboard';
        if (in_array($current_section, ['price_adjustments', 'sale_rules', 'scheduling'], true)) $current_section = 'campaigns'; // old links
        if (in_array($current_section, ['badges', 'card_messages'], true)) $current_section = 'messages'; // old links
        if (!isset($sections[$current_section])) $current_section = 'dashboard';

        $on = get_option('msd_global_enable') === 'yes';

        echo '<div class="wrap msd-settings-wrap">';
        echo '<h1 class="wp-heading-inline">' . esc_html__('Sales Dashboard', 'monospace-sales') . '</h1>';
        echo '<span class="msd-chip ' . ($on ? 'is-on' : '') . ' msd-title-chip">' . ($on ? esc_html__('Sales system on', 'monospace-sales') : esc_html__('Sales system off', 'monospace-sales')) . '</span>';
        echo '<hr class="wp-header-end">';

        // Show success/error messages
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'monospace-sales') . '</p></div>';
        }

        if (isset($_GET['error']) && $_GET['error'] === '1') {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Error saving settings. Please try again.', 'monospace-sales') . '</p></div>';
        }

        // Section links
        echo '<nav class="msd-nav" aria-label="' . esc_attr__('Sales Dashboard sections', 'monospace-sales') . '">';
        foreach ($sections as $id => $info) {
            $class = 'msd-nav-link' . ($id === $current_section ? ' is-current' : '');
            echo '<a href="' . esc_url(self::url($id)) . '" class="' . $class . '"' . ($id === $current_section ? ' aria-current="page"' : '') . '>'
                . '<span class="dashicons ' . esc_attr($info[1]) . '" aria-hidden="true"></span>' . esc_html($info[0]) . '</a>';
        }
        echo '</nav>';

        // Sections made of settings fields share one form and one Save button; the others have their own forms
        $has_form = in_array($current_section, self::FORM_SECTIONS, true);
        if ($has_form) {
            echo '<form method="post" action="' . esc_url(self::url($current_section)) . '" id="msd-settings-form">';
            wp_nonce_field('msd_save_settings', 'msd_settings_nonce');
            echo '<input type="hidden" name="msd_section" value="' . esc_attr($current_section) . '">';
        }

        self::output_section($current_section);

        if ($has_form) {
            echo '<p class="submit">';
            echo '<button type="submit" name="msd_save_settings" value="1" class="button button-primary">' . esc_html__('Save changes', 'monospace-sales') . '</button>';
            echo '</p></form>';
        }

        echo '</div>';
    }

    /**
     * Save a section made of settings fields (this screen's own form), then come back with a notice
     */
    public static function maybe_save() {
        if (empty($_POST['msd_save_settings']) || ($_GET['page'] ?? '') !== self::PAGE) {
            return;
        }
        if (!current_user_can('manage_woocommerce') || !isset($_POST['msd_settings_nonce']) || !wp_verify_nonce($_POST['msd_settings_nonce'], 'msd_save_settings')) {
            wp_die(__('Insufficient permissions', 'monospace-sales'));
        }

        $section = sanitize_key($_POST['msd_section'] ?? '');
        if (!in_array($section, self::FORM_SECTIONS, true)) {
            return;
        }

        try {
            self::save_section($section);
        } catch (Exception $e) {
            error_log('MSD Settings Save Error: ' . $e->getMessage());
            wp_safe_redirect(self::url($section, ['error' => '1']));
            exit;
        }

        wp_safe_redirect(self::url($section, ['settings-updated' => 'true']));
        exit;
    }

    /**
     * Output specific section
     */
    private static function output_section($section) {
        switch ($section) {
            case 'dashboard':
                MSD_Dashboard_Overview::output_section();
                break;
            case 'global':
                self::output_global_section();
                break;
            case 'campaigns':
                MSD_Campaigns::output_section();
                break;
            case 'volume_discounts':
                self::output_volume_discounts_section();
                break;
            case 'free_shipping':
                self::output_free_shipping_section();
                break;
            case 'messages':
            case 'badges':          // old links
            case 'card_messages':   // old links
                MSD_Card_Messages::output_section();
                break;
            case 'bulk_editor':
                MSD_Bulk_Editor::output_section();
                break;
            case 'additional_tracker':
                MSD_Additional_Tracker::output_section();
                break;
            case 'import_export':
                MSD_Import_Export::output_section();
                break;
        }
    }

    /**
     * Global Controls Section
     */
    private static function output_global_section() {
        $settings = self::get_global_fields();
        woocommerce_admin_fields($settings);
    }



    /**
     * Volume Discounts Section
     */
    private static function output_volume_discounts_section() {
        echo '<div id="msd-volume-builder">';
        echo '<h3>' . __('Volume Discount Rules', 'monospace-sales') . '</h3>';

        $settings = [
            [
                'title' => __('Volume Discount Configuration', 'monospace-sales'),
                'type' => 'title',
                'id' => 'msd_volume_title',
            ],
            [
                'title' => __('Enable Volume Discounts', 'monospace-sales'),
                'id' => 'msd_volume_enable',
                'type' => 'checkbox',
                'default' => 'no',
            ],
            [
                'type' => 'sectionend',
                'id' => 'msd_volume_end',
            ],
        ];

        woocommerce_admin_fields($settings);

        // Visual Rule Builder
        echo '<div class="msd-rule-builder">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">';
        echo '<h3 style="margin:0;">' . __('Visual Rule Builder', 'monospace-sales') . '</h3>';
        echo '<button type="button" class="button" id="msd-toggle-json">' . __('View JSON', 'monospace-sales') . '</button>';
        echo '</div>';
        echo '<div id="msd-rules-container"></div>';
        echo '<button type="button" class="button button-secondary" id="msd-add-rule-group">' . __('+ Add Rule Group', 'monospace-sales') . '</button>';
        echo '</div>';

        // JSON viewer (hidden by default)
        echo '<div id="msd-json-viewer" style="display:none;margin-top:20px;">';
        echo '<h3>' . __('Current JSON Configuration', 'monospace-sales') . '</h3>';
        echo '<p class="description">' . __('Read-only view for troubleshooting. Use the visual builder above to make changes.', 'monospace-sales') . '</p>';
        echo '<textarea id="msd-json-display" readonly style="width:100%;min-height:300px;font-family:monospace;background:#f5f5f5;border:1px solid #ddd;padding:10px;"></textarea>';
        echo '</div>';

        // Hidden field for JSON storage
        echo '<input type="hidden" id="msd_volume_rules" name="msd_volume_rules" value="' . esc_attr(get_option('msd_volume_rules', '')) . '">';

        echo '</div>';
    }

    /**
     * Free Shipping Section
     */
    private static function output_free_shipping_section() {
        echo '<div id="msd-freeship-builder">';
        echo '<h3>' . __('Free Shipping Automation', 'monospace-sales') . '</h3>';
        echo '<p>' . __('Automatically apply your free shipping coupon when conditions are met.', 'monospace-sales') . '</p>';

        $settings = [
            [
                'title' => __('Free Shipping Configuration', 'monospace-sales'),
                'type' => 'title',
                'id' => 'msd_freeship_title',
            ],
            [
                'title' => __('Enable Free Shipping Rules', 'monospace-sales'),
                'id' => 'msd_freeship_enable',
                'type' => 'checkbox',
                'default' => 'no',
            ],
            [
                'title' => __('Coupon Code', 'monospace-sales'),
                'desc' => __('The coupon code that provides free shipping', 'monospace-sales'),
                'id' => 'msd_freeship_coupon',
                'type' => 'text',
                'default' => '_admin_freeshipping',
                'css' => 'width:300px;',
            ],
            [
                'type' => 'sectionend',
                'id' => 'msd_freeship_basic_end',
            ],
        ];

        woocommerce_admin_fields($settings);

        // Visual Rule Builder
        echo '<div class="msd-rule-builder">';
        echo '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">';
        echo '<h3 style="margin:0;">' . __('Visual Rule Builder', 'monospace-sales') . '</h3>';
        echo '<button type="button" class="button" id="msd-toggle-freeship-json">' . __('View JSON', 'monospace-sales') . '</button>';
        echo '</div>';
        echo '<p class="description">' . __('Define when free shipping should be automatically applied. Rules use OR logic - if any rule is met, free shipping applies.', 'monospace-sales') . '</p>';
        echo '<div id="msd-freeship-rules-container"></div>';
        echo '<button type="button" class="button button-secondary" id="msd-add-freeship-rule-group">' . __('+ Add Rule Group', 'monospace-sales') . '</button>';
        echo '</div>';

        // JSON viewer
        echo '<div id="msd-freeship-json-viewer" style="display:none;margin-top:20px;">';
        echo '<h3>' . __('Current JSON Configuration', 'monospace-sales') . '</h3>';
        echo '<p class="description">' . __('Read-only view for troubleshooting.', 'monospace-sales') . '</p>';
        echo '<textarea id="msd-freeship-json-display" readonly style="width:100%;min-height:300px;font-family:monospace;background:#f5f5f5;border:1px solid #ddd;padding:10px;"></textarea>';
        echo '</div>';

        // Hidden field
        echo '<input type="hidden" id="msd_freeship_rules" name="msd_freeship_rules" value="' . esc_attr(get_option('msd_freeship_rules', '')) . '">';


        echo '</div>';
    }


    /**
     * Badges & Messages Section
     */
    private static function output_badges_section() {
        $settings = [
            [
                'title' => __('Sale Badges & Messages', 'monospace-sales'),
                'type' => 'title',
                'desc' => __('Customize how discounts are displayed', 'monospace-sales'),
                'id' => 'msd_badges_title',
            ],
            [
                'title' => __('Enable Sale Badges', 'monospace-sales'),
                'id' => 'msd_badges_enable',
                'type' => 'checkbox',
                'default' => 'yes',
            ],
            [
                'title' => __('Badge Text', 'monospace-sales'),
                'desc' => __('Use {percent} for discount amount', 'monospace-sales'),
                'id' => 'msd_badge_text',
                'type' => 'text',
                'default' => 'SAVE {percent}%',
            ],
            [
                'type' => 'sectionend',
                'id' => 'msd_badges_basic_end',
            ],

            // Volume Discount Hints
            [
                'title' => __('Volume Discount Hints', 'monospace-sales'),
                'type' => 'title',
                'desc' => __('Display promotional hints on product pages', 'monospace-sales'),
                'id' => 'msd_hints_title',
            ],
            [
                'title' => __('Show Discount Hints', 'monospace-sales'),
                'desc' => __('Display volume discount offers on product pages', 'monospace-sales'),
                'id' => 'msd_hints_enable',
                'type' => 'checkbox',
                'default' => 'yes',
            ],
            [
                'title' => __('Hint Position', 'monospace-sales'),
                'id' => 'msd_hint_position',
                'type' => 'select',
                'options' => [
                    'after_price' => __('After price', 'monospace-sales'),
                    'before_button' => __('Before add to cart button', 'monospace-sales'),
                    'after_button' => __('After add to cart button', 'monospace-sales'),
                ],
                'default' => 'after_button',
            ],
            [
                'type' => 'sectionend',
                'id' => 'msd_hints_basic_end',
            ],

            // Styling Section
            [
                'title' => __('Discount Hint Styling', 'monospace-sales'),
                'type' => 'title',
                'desc' => __('Customize the appearance of discount hints', 'monospace-sales'),
                'id' => 'msd_hint_style_title',
            ],
            [
                'title' => __('Main Text Color', 'monospace-sales'),
                'id' => 'msd_hint_text_color',
                'type' => 'color',
                'default' => '#33aa33',
                'desc' => __('Color for the main discount text', 'monospace-sales'),
            ],
            [
                'title' => __('Secondary Text Color', 'monospace-sales'),
                'id' => 'msd_hint_secondary_color',
                'type' => 'color',
                'default' => '#666666',
                'desc' => __('Color for secondary text (e.g., "applied at checkout")', 'monospace-sales'),
            ],
            [
                'title' => __('Background Color', 'monospace-sales'),
                'id' => 'msd_hint_bg_color',
                'type' => 'color',
                'default' => '#f0f9f0',
                'desc' => __('Background color for hint box (leave empty for transparent)', 'monospace-sales'),
            ],
            [
                'title' => __('Border Color', 'monospace-sales'),
                'id' => 'msd_hint_border_color',
                'type' => 'color',
                'default' => '#33aa33',
                'desc' => __('Border color (leave empty for no border)', 'monospace-sales'),
            ],
            [
                'title' => __('Main Text Size', 'monospace-sales'),
                'id' => 'msd_hint_text_size',
                'type' => 'number',
                'css' => 'width:80px;',
                'default' => '14',
                'desc' => __('Font size in pixels', 'monospace-sales'),
                'custom_attributes' => [
                    'min' => '10',
                    'max' => '24',
                ],
            ],
            [
                'title' => __('Secondary Text Size', 'monospace-sales'),
                'id' => 'msd_hint_secondary_size',
                'type' => 'number',
                'css' => 'width:80px;',
                'default' => '12',
                'desc' => __('Font size in pixels', 'monospace-sales'),
                'custom_attributes' => [
                    'min' => '8',
                    'max' => '18',
                ],
            ],
            [
                'title' => __('Font Weight', 'monospace-sales'),
                'id' => 'msd_hint_font_weight',
                'type' => 'select',
                'options' => [
                    'normal' => __('Normal', 'monospace-sales'),
                    'bold' => __('Bold', 'monospace-sales'),
                    '600' => __('Semi-bold', 'monospace-sales'),
                    '500' => __('Medium', 'monospace-sales'),
                ],
                'default' => 'bold',
            ],
            [
                'title' => __('Text Alignment', 'monospace-sales'),
                'id' => 'msd_hint_text_align',
                'type' => 'select',
                'options' => [
                    'left' => __('Left', 'monospace-sales'),
                    'center' => __('Center', 'monospace-sales'),
                    'right' => __('Right', 'monospace-sales'),
                ],
                'default' => 'left',
            ],
            [
                'title' => __('Padding', 'monospace-sales'),
                'id' => 'msd_hint_padding',
                'type' => 'text',
                'css' => 'width:150px;',
                'default' => '10px 12px',
                'desc' => __('CSS padding (e.g., "10px 12px" or "8px")', 'monospace-sales'),
            ],
            [
                'title' => __('Margin', 'monospace-sales'),
                'id' => 'msd_hint_margin',
                'type' => 'text',
                'css' => 'width:150px;',
                'default' => '6px 0',
                'desc' => __('CSS margin (e.g., "6px 0" or "10px")', 'monospace-sales'),
            ],
            [
                'title' => __('Border Radius', 'monospace-sales'),
                'id' => 'msd_hint_border_radius',
                'type' => 'number',
                'css' => 'width:80px;',
                'default' => '4',
                'desc' => __('Corner roundness in pixels (0 = square)', 'monospace-sales'),
                'custom_attributes' => [
                    'min' => '0',
                    'max' => '20',
                ],
            ],
            [
                'title' => __('Border Width', 'monospace-sales'),
                'id' => 'msd_hint_border_width',
                'type' => 'number',
                'css' => 'width:80px;',
                'default' => '1',
                'desc' => __('Border thickness in pixels (0 = no border)', 'monospace-sales'),
                'custom_attributes' => [
                    'min' => '0',
                    'max' => '5',
                ],
            ],
            [
                'type' => 'sectionend',
                'id' => 'msd_hint_style_end',
            ],

            // Text Templates
            [
                'title' => __('Discount Text Templates', 'monospace-sales'),
                'type' => 'title',
                'desc' => __('Customize messages for different discount types', 'monospace-sales'),
                'id' => 'msd_hint_templates_title',
            ],
            [
                'title' => __('Fixed Bundle Template', 'monospace-sales'),
                'id' => 'msd_hint_template_bundle',
                'type' => 'text',
                'default' => 'Special: Get {qty} for only ${total}!',
                'desc' => __('Use {qty} for quantity and {total} for price', 'monospace-sales'),
                'css' => 'width:400px;',
            ],
            [
                'title' => __('Buy X Get Y Template', 'monospace-sales'),
                'id' => 'msd_hint_template_bxgy',
                'type' => 'text',
                'default' => 'Buy {buy}, get {get} free!',
                'desc' => __('Use {buy} and {get} for quantities', 'monospace-sales'),
                'css' => 'width:400px;',
            ],
            [
                'title' => __('Percent Discount Template', 'monospace-sales'),
                'id' => 'msd_hint_template_percent',
                'type' => 'text',
                'default' => 'Save {percent}% on qualifying purchases!',
                'desc' => __('Use {percent} for discount percentage', 'monospace-sales'),
                'css' => 'width:400px;',
            ],
            [
                'title' => __('Secondary Text', 'monospace-sales'),
                'id' => 'msd_hint_secondary_text',
                'type' => 'text',
                'default' => '(Discount applied at checkout.)',
                'desc' => __('Small text shown below main message', 'monospace-sales'),
                'css' => 'width:400px;',
            ],
            [
                'type' => 'sectionend',
                'id' => 'msd_hint_templates_end',
            ],

            // Cart Messages
            [
                'title' => __('Cart Messages', 'monospace-sales'),
                'type' => 'title',
                'desc' => __('Messages shown in the shopping cart', 'monospace-sales'),
                'id' => 'msd_cart_messages_title',
            ],
            [
                'title' => __('Almost There Message', 'monospace-sales'),
                'desc' => __('When close to volume discount. Use {qty} and {amount}', 'monospace-sales'),
                'id' => 'msd_hint_almost',
                'type' => 'text',
                'default' => 'Add {qty} more to save {amount}!',
                'css' => 'width:400px;',
            ],
            [
                'title' => __('Discount Active Message', 'monospace-sales'),
                'id' => 'msd_hint_active',
                'type' => 'text',
                'default' => 'Volume discount applied!',
                'css' => 'width:400px;',
            ],
            [
                'type' => 'sectionend',
                'id' => 'msd_cart_messages_end',
            ],
        ];

        woocommerce_admin_fields($settings);

        // Live preview
        self::output_hint_preview();
    }

    /**
     * Output live preview of hint styling
     */
    private static function output_hint_preview() {
        ?>
        <div class="msd-hint-preview-wrap" style="margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
            <h4><?php _e('Live Preview', 'monospace-sales'); ?></h4>
            <div id="msd-hint-preview" class="special-discount">
                <span class="msd-hint-main">Special: Get 3 for only $99!</span><br>
                <span class="msd-hint-secondary">(Discount applied at checkout.)</span>
            </div>
        </div>
        <script>
        jQuery(document).ready(function($) {
            // Update preview when settings change
            function updatePreview() {
                var mainColor = $('#msd_hint_text_color').val() || '#33aa33';
                var secondaryColor = $('#msd_hint_secondary_color').val() || '#666666';
                var bgColor = $('#msd_hint_bg_color').val() || '#f0f9f0';
                var borderColor = $('#msd_hint_border_color').val() || '#33aa33';
                var textSize = $('#msd_hint_text_size').val() || '14';
                var secondarySize = $('#msd_hint_secondary_size').val() || '12';
                var fontWeight = $('#msd_hint_font_weight').val() || 'bold';
                var textAlign = $('#msd_hint_text_align').val() || 'left';
                var padding = $('#msd_hint_padding').val() || '10px 12px';
                var margin = $('#msd_hint_margin').val() || '6px 0';
                var borderRadius = $('#msd_hint_border_radius').val() || '4';
                var borderWidth = $('#msd_hint_border_width').val() || '1';

                var template = $('#msd_hint_template_bundle').val() || 'Special: Get {qty} for only ${total}!';
                var secondary = $('#msd_hint_secondary_text').val() || '(Discount applied at checkout.)';

                var mainText = template.replace('{qty}', '3').replace('{total}', '99');

                $('#msd-hint-preview').css({
                    'background-color': bgColor,
                    'border': borderWidth + 'px solid ' + borderColor,
                    'padding': padding,
                    'margin': margin,
                    'border-radius': borderRadius + 'px',
                    'text-align': textAlign
                });

                $('.msd-hint-main').css({
                    'color': mainColor,
                    'font-size': textSize + 'px',
                    'font-weight': fontWeight
                }).text(mainText);

                $('.msd-hint-secondary').css({
                    'color': secondaryColor,
                    'font-size': secondarySize + 'px'
                }).text(secondary);
            }

            // Bind to all styling inputs
            $('#msd_hint_text_color, #msd_hint_secondary_color, #msd_hint_bg_color, #msd_hint_border_color, #msd_hint_text_size, #msd_hint_secondary_size, #msd_hint_font_weight, #msd_hint_text_align, #msd_hint_padding, #msd_hint_margin, #msd_hint_border_radius, #msd_hint_border_width, #msd_hint_template_bundle, #msd_hint_secondary_text').on('change keyup', updatePreview);

            // Initial preview
            updatePreview();
        });
        </script>
        <?php
    }

    /**
     * Save the fields of one section
     */
    private static function save_section($section) {
        if ($section === 'messages') {
            MSD_Card_Messages::save();
            return;
        }

        $fields = self::get_section_fields($section);
        if (!empty($fields)) {
            woocommerce_update_options($fields);
        }
    }

    /**
     * Get fields for a specific section
     */
    private static function get_section_fields($section) {
        switch ($section) {
            case 'global':
                return self::get_global_fields();
            case 'volume_discounts':
                return self::get_volume_discounts_fields();
            case 'free_shipping':
                return self::get_free_shipping_fields();
            case 'badges':
                return self::get_badges_fields();
            default:
                return [];
        }
    }

    /**
     * Get global section fields
     */
    private static function get_global_fields() {
        return [
            ['title' => 'Global Sale Controls', 'type' => 'title', 'id' => 'msd_global_title'],
            ['title' => 'Enable Sales System', 'id' => 'msd_global_enable', 'type' => 'checkbox', 'default' => 'no'],
            ['title' => 'Show Original Price', 'id' => 'msd_show_original_price', 'type' => 'checkbox', 'default' => 'yes'],
            ['title' => 'Conflict Resolution Priority', 'id' => 'msd_priority', 'type' => 'select', 'options' => ['best' => 'Best for customer', 'product' => 'Product override', 'volume' => 'Volume discount'], 'default' => 'best'],
            ['type' => 'sectionend', 'id' => 'msd_global_end'],
        ];
    }



    /**
     * Get volume discounts fields
     */
    private static function get_volume_discounts_fields() {
        return [
            ['title' => 'Volume Discount Configuration', 'type' => 'title', 'id' => 'msd_volume_title'],
            ['title' => 'Enable Volume Discounts', 'id' => 'msd_volume_enable', 'type' => 'checkbox', 'default' => 'no'],
            ['title' => 'Rules JSON', 'id' => 'msd_volume_rules', 'type' => 'textarea'],
            ['type' => 'sectionend', 'id' => 'msd_volume_end'],
        ];
    }

    /**
     * Cart messages (free shipping notices in the cart). Shown and saved on the Messages screen.
     */
    public static function get_cart_message_fields() {
        return [
            [
                'title' => __('Cart', 'monospace-sales'),
                'type' => 'title',
                'desc' => __('Free shipping notices shown in the cart (rules: Free Shipping section)', 'monospace-sales'),
                'id' => 'msd_freeship_hints_title',
            ],
            [
                'title' => __('Enable Cart Messages', 'monospace-sales'),
                'id' => 'msd_freeship_hints_enable',
                'type' => 'checkbox',
                'default' => 'yes',
            ],
            [
                'title' => __('Active Message', 'monospace-sales'),
                'desc' => __('Shown when free shipping is applied', 'monospace-sales'),
                'id' => 'msd_freeship_hint_active',
                'type' => 'text',
                'default' => 'Free shipping applied! ✓',
                'css' => 'width:400px;',
            ],
            [
                'title' => __('Almost There (Amount)', 'monospace-sales'),
                'desc' => __('Use {amount} for remaining amount needed', 'monospace-sales'),
                'id' => 'msd_freeship_hint_almost_amount',
                'type' => 'text',
                'default' => 'Add {amount} more for free shipping',
                'css' => 'width:400px;',
            ],
            [
                'title' => __('Almost There (Quantity)', 'monospace-sales'),
                'desc' => __('Use {qty} for remaining items needed', 'monospace-sales'),
                'id' => 'msd_freeship_hint_almost_qty',
                'type' => 'text',
                'default' => 'Add {qty} more items for free shipping',
                'css' => 'width:400px;',
            ],
            [
                'type' => 'sectionend',
                'id' => 'msd_freeship_hints_end',
            ],
        ];
    }

    /**
     * Get free shipping fields
     */
    private static function get_free_shipping_fields() {
        return [
            ['title' => 'Free Shipping Configuration', 'type' => 'title', 'id' => 'msd_freeship_title'],
            ['title' => 'Enable Free Shipping Rules', 'id' => 'msd_freeship_enable', 'type' => 'checkbox', 'default' => 'no'],
            ['title' => 'Coupon Code', 'id' => 'msd_freeship_coupon', 'type' => 'text', 'default' => '_admin_freeshipping'],
            ['title' => 'Rules JSON', 'id' => 'msd_freeship_rules', 'type' => 'textarea'],
            ['type' => 'sectionend', 'id' => 'msd_freeship_basic_end'],
        ];
    }


    /**
     * Get badges fields
     */
    private static function get_badges_fields() {
        return [
            ['title' => 'Sale Badges & Messages', 'type' => 'title', 'id' => 'msd_badges_title'],
            ['title' => 'Enable Sale Badges', 'id' => 'msd_badges_enable', 'type' => 'checkbox', 'default' => 'yes'],
            ['title' => 'Badge Text', 'id' => 'msd_badge_text', 'type' => 'text', 'default' => 'SAVE {percent}%'],
            ['type' => 'sectionend', 'id' => 'msd_badges_basic_end'],

            ['title' => 'Volume Discount Hints', 'type' => 'title', 'id' => 'msd_hints_title'],
            ['title' => 'Show Discount Hints', 'id' => 'msd_hints_enable', 'type' => 'checkbox', 'default' => 'yes'],
            ['title' => 'Hint Position', 'id' => 'msd_hint_position', 'type' => 'select', 'options' => ['after_price' => 'After price', 'before_button' => 'Before button', 'after_button' => 'After button'], 'default' => 'after_button'],
            ['type' => 'sectionend', 'id' => 'msd_hints_basic_end'],

            ['title' => 'Discount Hint Styling', 'type' => 'title', 'id' => 'msd_hint_style_title'],
            ['title' => 'Main Text Color', 'id' => 'msd_hint_text_color', 'type' => 'color', 'default' => '#33aa33'],
            ['title' => 'Secondary Text Color', 'id' => 'msd_hint_secondary_color', 'type' => 'color', 'default' => '#666666'],
            ['title' => 'Background Color', 'id' => 'msd_hint_bg_color', 'type' => 'color', 'default' => '#f0f9f0'],
            ['title' => 'Border Color', 'id' => 'msd_hint_border_color', 'type' => 'color', 'default' => '#33aa33'],
            ['title' => 'Main Text Size', 'id' => 'msd_hint_text_size', 'type' => 'number', 'default' => '14'],
            ['title' => 'Secondary Text Size', 'id' => 'msd_hint_secondary_size', 'type' => 'number', 'default' => '12'],
            ['title' => 'Font Weight', 'id' => 'msd_hint_font_weight', 'type' => 'select', 'options' => ['normal' => 'Normal', 'bold' => 'Bold', '600' => 'Semi-bold', '500' => 'Medium'], 'default' => 'bold'],
            ['title' => 'Text Alignment', 'id' => 'msd_hint_text_align', 'type' => 'select', 'options' => ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'], 'default' => 'left'],
            ['title' => 'Padding', 'id' => 'msd_hint_padding', 'type' => 'text', 'default' => '10px 12px'],
            ['title' => 'Margin', 'id' => 'msd_hint_margin', 'type' => 'text', 'default' => '6px 0'],
            ['title' => 'Border Radius', 'id' => 'msd_hint_border_radius', 'type' => 'number', 'default' => '4'],
            ['title' => 'Border Width', 'id' => 'msd_hint_border_width', 'type' => 'number', 'default' => '1'],
            ['type' => 'sectionend', 'id' => 'msd_hint_style_end'],

            ['title' => 'Discount Text Templates', 'type' => 'title', 'id' => 'msd_hint_templates_title'],
            ['title' => 'Fixed Bundle Template', 'id' => 'msd_hint_template_bundle', 'type' => 'text', 'default' => 'Special: Get {qty} for only ${total}!'],
            ['title' => 'Buy X Get Y Template', 'id' => 'msd_hint_template_bxgy', 'type' => 'text', 'default' => 'Buy {buy}, get {get} free!'],
            ['title' => 'Percent Discount Template', 'id' => 'msd_hint_template_percent', 'type' => 'text', 'default' => 'Save {percent}% on qualifying purchases!'],
            ['title' => 'Secondary Text', 'id' => 'msd_hint_secondary_text', 'type' => 'text', 'default' => '(Discount applied at checkout.)'],
            ['type' => 'sectionend', 'id' => 'msd_hint_templates_end'],

            ['title' => 'Cart Messages', 'type' => 'title', 'id' => 'msd_cart_messages_title'],
            ['title' => 'Almost There Message', 'id' => 'msd_hint_almost', 'type' => 'text', 'default' => 'Add {qty} more to save {amount}!'],
            ['title' => 'Discount Active Message', 'id' => 'msd_hint_active', 'type' => 'text', 'default' => 'Volume discount applied!'],
            ['type' => 'sectionend', 'id' => 'msd_cart_messages_end'],
        ];
    }

    /**
     * Helper: get product categories
     */
    private static function get_product_categories() {
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);
        return wp_list_pluck($terms, 'name', 'term_id');
    }

    /**
     * Helper: get product tags
     */
    private static function get_product_tags() {
        $terms = get_terms([
            'taxonomy' => 'product_tag',
            'hide_empty' => false,
        ]);
        return wp_list_pluck($terms, 'name', 'slug');
    }

    /**
     * Default volume rules template
     */
    private static function get_default_volume_rules() {
        return json_encode([
            'miniature' => [
                ['type' => 'fixed_bundle', 'qty' => 2, 'total' => 69],
                ['type' => 'fixed_bundle', 'qty' => 3, 'total' => 99],
            ],
        ], JSON_PRETTY_PRINT);
    }
}
<?php
/**
 * Settings Manager - Unified admin interface
 */

if (!defined('ABSPATH')) exit;

class MSD_Settings {

    public static function init() {
        add_filter('woocommerce_settings_tabs_array', [__CLASS__, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_msd', [__CLASS__, 'output_settings']);
        add_action('woocommerce_update_options_msd', [__CLASS__, 'save_settings']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Add settings tab
     */
    public static function add_settings_tab($tabs) {
        $tabs['msd'] = __('Sales Dashboard', 'monospace-sales');
        return $tabs;
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_assets($hook) {
        if ('woocommerce_page_wc-settings' !== $hook) {
            return;
        }

        if (!isset($_GET['tab']) || $_GET['tab'] !== 'msd') {
            return;
        }

        wp_enqueue_style('msd-admin', MSD_PLUGIN_URL . 'assets/admin.css', [], MSD_VERSION);
        wp_enqueue_script('msd-admin', MSD_PLUGIN_URL . 'assets/admin.js', ['jquery'], MSD_VERSION, true);
    }

    /**
     * Output settings page with tabs
     */
    public static function output_settings() {
        $current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'global';

        $sections = [
            'global' => __('Global Controls', 'monospace-sales'),
            'price_adjustments' => __('Price Adjustments', 'monospace-sales'),
            'sale_rules' => __('Sale Rules', 'monospace-sales'),
            'volume_discounts' => __('Volume Discounts', 'monospace-sales'),
            'free_shipping' => __('Free Shipping', 'monospace-sales'),
            'scheduling' => __('Scheduling', 'monospace-sales'),
            'badges' => __('Badges & Messages', 'monospace-sales'),
            'bulk_editor' => __('Bulk Editor', 'monospace-sales'),
            'import_export' => __('Import/Export', 'monospace-sales'),
        ];

        echo '<div class="msd-settings-wrap">';

        // Show success/error messages
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'monospace-sales') . '</p></div>';
        }

        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Error saving settings. Please try again.', 'monospace-sales') . '</p></div>';
        }

        // Section tabs
        echo '<ul class="subsubsub msd-tabs">';
        $i = 0;
        foreach ($sections as $id => $label) {
            $class = ($id === $current_section) ? 'current' : '';
            $separator = ($i++ > 0) ? ' | ' : '';
            echo $separator . '<li><a href="' . admin_url('admin.php?page=wc-settings&tab=msd&section=' . $id) . '" class="' . $class . '">' . esc_html($label) . '</a></li>';
        }
        echo '</ul><br class="clear" />';

        // Output section content
        self::output_section($current_section);

        echo '</div>';
    }

    /**
     * Output specific section
     */
    private static function output_section($section) {
        switch ($section) {
            case 'global':
                self::output_global_section();
                break;
            case 'price_adjustments':
                self::output_price_adjustments_section();
                break;
            case 'sale_rules':
                self::output_sale_rules_section();
                break;
            case 'volume_discounts':
                self::output_volume_discounts_section();
                break;
            case 'free_shipping':
                self::output_free_shipping_section();
                break;
            case 'scheduling':
                self::output_scheduling_section();
                break;
            case 'badges':
                self::output_badges_section();
                break;
            case 'bulk_editor':
                MSD_Bulk_Editor::output_section();
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
     * Price Adjustments Section
     */
    private static function output_price_adjustments_section() {
        $settings = self::get_price_adjustments_fields();
        woocommerce_admin_fields($settings);
    }

    /**
     * Sale Rules Section
     */
    private static function output_sale_rules_section() {
        $settings = self::get_sale_rules_fields();
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

        echo '<hr style="margin:40px 0;">';

        // Cart Hints
        $hint_settings = [
            [
                'title' => __('Cart Messages', 'monospace-sales'),
                'type' => 'title',
                'desc' => __('Messages displayed in cart using WooCommerce notice styles', 'monospace-sales'),
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

        woocommerce_admin_fields($hint_settings);

        echo '</div>';
    }

    /**
     * Scheduling Section
     */
    private static function output_scheduling_section() {
        echo '<h3>' . __('Sale Scheduling', 'monospace-sales') . '</h3>';
        echo '<p>' . __('Schedule sales to start and end automatically.', 'monospace-sales') . '</p>';

        $settings = [
            [
                'title' => __('One-Time Schedule', 'monospace-sales'),
                'type' => 'title',
                'id' => 'msd_schedule_title',
            ],
            [
                'title' => __('Enable One-Time Schedule', 'monospace-sales'),
                'id' => 'msd_schedule_enable',
                'type' => 'checkbox',
                'default' => 'no',
            ],
            [
                'title' => __('Start Date', 'monospace-sales'),
                'id' => 'msd_schedule_start',
                'type' => 'text',
                'desc' => __('Format: YYYY-MM-DD HH:MM (leave empty for immediate)', 'monospace-sales'),
                'css' => 'width:200px;',
            ],
            [
                'title' => __('End Date', 'monospace-sales'),
                'id' => 'msd_schedule_end',
                'type' => 'text',
                'desc' => __('Format: YYYY-MM-DD HH:MM (leave empty for no end)', 'monospace-sales'),
                'css' => 'width:200px;',
            ],
            [
                'type' => 'sectionend',
                'id' => 'msd_schedule_onetime_end',
            ],
            [
                'title' => __('Recurring Schedule', 'monospace-sales'),
                'type' => 'title',
                'desc' => __('Automatically enable sales on specific days/times', 'monospace-sales'),
                'id' => 'msd_recurring_title',
            ],
            [
                'title' => __('Enable Recurring Schedule', 'monospace-sales'),
                'id' => 'msd_recurring_enable',
                'type' => 'checkbox',
                'default' => 'no',
            ],
            [
                'title' => __('Recurrence Pattern', 'monospace-sales'),
                'id' => 'msd_recurring_pattern',
                'type' => 'select',
                'options' => [
                    'daily' => __('Daily', 'monospace-sales'),
                    'weekends' => __('Every Weekend (Sat-Sun)', 'monospace-sales'),
                    'weekdays' => __('Weekdays Only (Mon-Fri)', 'monospace-sales'),
                    'weekly' => __('Specific Day Each Week', 'monospace-sales'),
                    'monthly_date' => __('Specific Date Each Month', 'monospace-sales'),
                    'monthly_day' => __('Specific Weekday Each Month', 'monospace-sales'),
                ],
                'default' => 'weekends',
            ],
            [
                'title' => __('Day of Week', 'monospace-sales'),
                'desc' => __('For "Weekly" pattern', 'monospace-sales'),
                'id' => 'msd_recurring_weekday',
                'type' => 'select',
                'options' => [
                    '1' => __('Monday', 'monospace-sales'),
                    '2' => __('Tuesday', 'monospace-sales'),
                    '3' => __('Wednesday', 'monospace-sales'),
                    '4' => __('Thursday', 'monospace-sales'),
                    '5' => __('Friday', 'monospace-sales'),
                    '6' => __('Saturday', 'monospace-sales'),
                    '0' => __('Sunday', 'monospace-sales'),
                ],
            ],
            [
                'title' => __('Day of Month', 'monospace-sales'),
                'desc' => __('For "Monthly Date" pattern (1-31)', 'monospace-sales'),
                'id' => 'msd_recurring_monthday',
                'type' => 'number',
                'css' => 'width:80px;',
                'custom_attributes' => [
                    'min' => '1',
                    'max' => '31',
                ],
                'default' => '1',
            ],
            [
                'title' => __('Week of Month', 'monospace-sales'),
                'desc' => __('For "Monthly Weekday" pattern', 'monospace-sales'),
                'id' => 'msd_recurring_week',
                'type' => 'select',
                'options' => [
                    'first' => __('First', 'monospace-sales'),
                    'second' => __('Second', 'monospace-sales'),
                    'third' => __('Third', 'monospace-sales'),
                    'fourth' => __('Fourth', 'monospace-sales'),
                    'last' => __('Last', 'monospace-sales'),
                ],
            ],
            [
                'title' => __('Start Time', 'monospace-sales'),
                'desc' => __('Format: HH:MM (24-hour)', 'monospace-sales'),
                'id' => 'msd_recurring_start_time',
                'type' => 'text',
                'css' => 'width:100px;',
                'default' => '00:00',
            ],
            [
                'title' => __('End Time', 'monospace-sales'),
                'desc' => __('Format: HH:MM (24-hour)', 'monospace-sales'),
                'id' => 'msd_recurring_end_time',
                'type' => 'text',
                'css' => 'width:100px;',
                'default' => '23:59',
            ],
            [
                'type' => 'sectionend',
                'id' => 'msd_recurring_end',
            ],
        ];

        woocommerce_admin_fields($settings);
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
     * Save settings
     */
    public static function save_settings() {
        // Verify we're on the right tab
        if (!isset($_GET['tab']) || $_GET['tab'] !== 'msd') {
            return;
        }

        $current_section = isset($_GET['section']) ? sanitize_text_field($_GET['section']) : 'global';

        // Skip for bulk editor and import/export (they handle their own saving)
        if (in_array($current_section, ['bulk_editor', 'import_export'])) {
            return;
        }

        try {
            // Get all settings fields for the current section
            $fields = self::get_section_fields($current_section);

            if (!empty($fields)) {
                woocommerce_update_options($fields);
            }
        } catch (Exception $e) {
            // Log error
            error_log('MSD Settings Save Error: ' . $e->getMessage());

            // Redirect with error
            wp_redirect(add_query_arg([
                'page' => 'wc-settings',
                'tab' => 'msd',
                'section' => $current_section,
                'error' => '1',
            ], admin_url('admin.php')));
            exit;
        }
    }

    /**
     * Get fields for a specific section
     */
    private static function get_section_fields($section) {
        switch ($section) {
            case 'global':
                return self::get_global_fields();
            case 'price_adjustments':
                return self::get_price_adjustments_fields();
            case 'sale_rules':
                return self::get_sale_rules_fields();
            case 'volume_discounts':
                return self::get_volume_discounts_fields();
            case 'free_shipping':
                return self::get_free_shipping_fields();
            case 'scheduling':
                return self::get_scheduling_fields();
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
            ['title' => 'Sale Mode', 'id' => 'msd_sale_mode', 'type' => 'select', 'options' => ['global' => 'Global', 'taxonomy' => 'Taxonomy'], 'default' => 'taxonomy'],
            ['title' => 'Show Original Price', 'id' => 'msd_show_original_price', 'type' => 'checkbox', 'default' => 'yes'],
            ['title' => 'Conflict Resolution Priority', 'id' => 'msd_priority', 'type' => 'select', 'options' => ['best' => 'Best for customer', 'product' => 'Product override', 'volume' => 'Volume discount'], 'default' => 'best'],
            ['type' => 'sectionend', 'id' => 'msd_global_end'],
        ];
    }

    /**
     * Get price adjustments fields
     */
    private static function get_price_adjustments_fields() {
        return [
            ['title' => 'Global Price Adjustments', 'type' => 'title', 'id' => 'msd_price_adj_title'],
            ['title' => 'Enable Price Adjustments', 'id' => 'msd_price_adj_enable', 'type' => 'checkbox', 'default' => 'no'],
            ['title' => 'Adjustment Percentage', 'id' => 'msd_price_adj_percentage', 'type' => 'number', 'default' => '25'],
            ['title' => 'Apply to Categories', 'id' => 'msd_price_adj_categories', 'type' => 'multiselect', 'class' => 'wc-enhanced-select', 'options' => self::get_product_categories()],
            ['title' => 'Apply to Tags', 'id' => 'msd_price_adj_tags', 'type' => 'multiselect', 'class' => 'wc-enhanced-select', 'options' => self::get_product_tags()],
            ['title' => 'Exclude Categories', 'id' => 'msd_price_adj_exclude_cats', 'type' => 'multiselect', 'class' => 'wc-enhanced-select', 'options' => self::get_product_categories()],
            ['title' => 'Exclude Tags', 'id' => 'msd_price_adj_exclude_tags', 'type' => 'multiselect', 'class' => 'wc-enhanced-select', 'options' => self::get_product_tags()],            ['title' => 'Rounding', 'id' => 'msd_rounding', 'type' => 'select', 'options' => ['none' => 'None', 'up' => 'Round up', 'down' => 'Round down', 'nearest' => 'Round to nearest'], 'default' => 'none'],
            ['title' => 'Charm Pricing', 'id' => 'msd_charm_pricing', 'type' => 'select', 'options' => ['none' => 'None', 'up' => 'Round up to .99', 'down' => 'Round down to .99'], 'default' => 'none'],
            ['type' => 'sectionend', 'id' => 'msd_price_adj_end'],
        ];
    }

    /**
     * Get sale rules fields
     */
    private static function get_sale_rules_fields() {
        return [
            ['title' => 'Taxonomy-Based Sale Rules', 'type' => 'title', 'id' => 'msd_sale_rules_title'],
            ['title' => 'Allowed Categories', 'id' => 'msd_sale_categories', 'type' => 'multiselect', 'class' => 'wc-enhanced-select', 'options' => self::get_product_categories()],
            ['title' => 'Allowed Tags', 'id' => 'msd_sale_tags', 'type' => 'multiselect', 'class' => 'wc-enhanced-select', 'options' => self::get_product_tags()],
            ['title' => 'Excluded Categories', 'id' => 'msd_sale_exclude_categories', 'type' => 'multiselect', 'class' => 'wc-enhanced-select', 'options' => self::get_product_categories()],
            ['title' => 'Excluded Tags', 'id' => 'msd_sale_exclude_tags', 'type' => 'multiselect', 'class' => 'wc-enhanced-select', 'options' => self::get_product_tags()],
            ['type' => 'sectionend', 'id' => 'msd_sale_rules_end'],
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
     * Get free shipping fields
     */
    private static function get_free_shipping_fields() {
        return [
            ['title' => 'Free Shipping Configuration', 'type' => 'title', 'id' => 'msd_freeship_title'],
            ['title' => 'Enable Free Shipping Rules', 'id' => 'msd_freeship_enable', 'type' => 'checkbox', 'default' => 'no'],
            ['title' => 'Coupon Code', 'id' => 'msd_freeship_coupon', 'type' => 'text', 'default' => '_admin_freeshipping'],
            ['title' => 'Rules JSON', 'id' => 'msd_freeship_rules', 'type' => 'textarea'],
            ['type' => 'sectionend', 'id' => 'msd_freeship_basic_end'],

            // Hints
            ['title' => 'Cart Messages', 'type' => 'title', 'desc' => 'Messages displayed in cart using WooCommerce notice styles', 'id' => 'msd_freeship_hints_title'],
            ['title' => 'Enable Cart Messages', 'id' => 'msd_freeship_hints_enable', 'type' => 'checkbox', 'default' => 'yes'],
            ['title' => 'Active Message', 'id' => 'msd_freeship_hint_active', 'type' => 'text', 'default' => 'Free shipping applied! ✓'],
            ['title' => 'Almost There (Amount)', 'id' => 'msd_freeship_hint_almost_amount', 'type' => 'text', 'default' => 'Add {amount} more for free shipping'],
            ['title' => 'Almost There (Quantity)', 'id' => 'msd_freeship_hint_almost_qty', 'type' => 'text', 'default' => 'Add {qty} more items for free shipping'],
            ['type' => 'sectionend', 'id' => 'msd_freeship_hints_end'],
        ];
    }

    /**
     * Get scheduling fields
     */
    private static function get_scheduling_fields() {
        return [
            ['title' => 'One-Time Schedule', 'type' => 'title', 'id' => 'msd_schedule_title'],
            ['title' => 'Enable One-Time Schedule', 'id' => 'msd_schedule_enable', 'type' => 'checkbox', 'default' => 'no'],
            ['title' => 'Start Date', 'id' => 'msd_schedule_start', 'type' => 'text'],
            ['title' => 'End Date', 'id' => 'msd_schedule_end', 'type' => 'text'],
            ['type' => 'sectionend', 'id' => 'msd_schedule_onetime_end'],

            ['title' => 'Recurring Schedule', 'type' => 'title', 'id' => 'msd_recurring_title'],
            ['title' => 'Enable Recurring Schedule', 'id' => 'msd_recurring_enable', 'type' => 'checkbox', 'default' => 'no'],
            ['title' => 'Recurrence Pattern', 'id' => 'msd_recurring_pattern', 'type' => 'select', 'options' => ['daily' => 'Daily', 'weekends' => 'Weekends', 'weekdays' => 'Weekdays', 'weekly' => 'Weekly', 'monthly_date' => 'Monthly Date', 'monthly_day' => 'Monthly Weekday'], 'default' => 'weekends'],
            ['title' => 'Day of Week', 'id' => 'msd_recurring_weekday', 'type' => 'select', 'options' => ['1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday', '0' => 'Sunday']],
            ['title' => 'Day of Month', 'id' => 'msd_recurring_monthday', 'type' => 'number', 'default' => '1'],
            ['title' => 'Week of Month', 'id' => 'msd_recurring_week', 'type' => 'select', 'options' => ['first' => 'First', 'second' => 'Second', 'third' => 'Third', 'fourth' => 'Fourth', 'last' => 'Last']],
            ['title' => 'Start Time', 'id' => 'msd_recurring_start_time', 'type' => 'text', 'default' => '00:00'],
            ['title' => 'End Time', 'id' => 'msd_recurring_end_time', 'type' => 'text', 'default' => '23:59'],
            ['type' => 'sectionend', 'id' => 'msd_recurring_end'],
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
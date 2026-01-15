<?php
/**
 * Sheet Renderer
 *
 * @package monospace-art-theme
 */

class Monospace_Sheet_Renderer {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function render_preview($product_ids, $options) {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Artwork Sheets</title></head><body>';

        $layout = $options['layout'];

        if ($layout === 'list') {
            $this->render_list_view($product_ids, $options);
        } else {
            $items_per_page = $this->get_items_per_page($layout);
            $chunks = array_chunk($product_ids, $items_per_page);

            foreach ($chunks as $chunk) {
                echo '<div style="page-break-after: always;">';

                if ($layout === 'single') {
                    $data = Monospace_Product_Data::instance()->gather_data($chunk[0], $options);
                    if ($data) {
                        $this->render_single_sheet($data, $options);
                    }
                } else {
                    $this->render_multi_sheet($chunk, $options, $layout);
                }

                echo '</div>';
            }
        }

        echo '</body></html>';
    }

    private function get_items_per_page($layout) {
        return match($layout) {
            'dual' => 2,
            'quad' => 4,
            default => 1
        };
    }

    public function render_single_sheet($data, $options) {
        $footer_content = Monospace_Sheet_Settings::get_footer_content($data);
        ?>
        <style>
            @media screen {
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                    max-width: 8.5in;
                    margin: 20px auto;
                    padding: 20px;
                    background: #f5f5f5;
                }
                .sheet {
                    background: white;
                    padding: 40px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
                    margin-bottom: 40px;
                }
            }

            @media print {
                body { margin: 0; padding: 0; }
                .sheet { padding: 0.5in; }
            }

            .sheet { font-size: 11pt; line-height: 1.4; }
            .header { border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 25px; }
            .title { font-size: 20pt; font-weight: bold; margin: 0 0 5px 0; }
            .sku { font-size: 10pt; color: #666; }
            .content {
                display: grid;
                grid-template-columns: <?php echo $options['include_image'] ? '1fr 1fr' : '1fr'; ?>;
                gap: 30px;
                margin-bottom: 30px;
            }
            .artwork-image { text-align: center; }
            .artwork-image img { max-width: 100%; max-height: 400px; border: 1px solid #ddd; }
            .detail-row { margin-bottom: 12px; }
            .detail-label { font-weight: bold; display: inline-block; min-width: 100px; }
            .status-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 3px;
                font-size: 9pt;
                font-weight: bold;
                text-transform: uppercase;
            }
            .status-available { background: #d4edda; color: #155724; }
            .status-sold { background: #f8d7da; color: #721c24; }
            .status-gallery { background: #fff3cd; color: #856404; }
            .status-private { background: #e2e3e5; color: #383d41; }
            .loan-section {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
            }
            .loan-section h3 { margin: 0 0 15px 0; font-size: 12pt; }
            .loan-field {
                margin-bottom: 15px;
                padding-bottom: 8px;
                border-bottom: 1px dotted #ccc;
            }
            .loan-field label {
                display: block;
                font-size: 9pt;
                color: #666;
                margin-bottom: 3px;
            }
            .footer {
                margin-top: 30px;
                padding-top: 15px;
                /* border-top: 1px solid #ddd; */
                font-size: 9pt;
                color: #666;
            }
            .generated-date {
                margin-top: 10px;
                font-size: 9pt;
                color: #999;
                font-style: italic;
            }
        </style>

        <div class="sheet">
            <div class="header">
                <h1 class="title"><?php echo esc_html($data['title']); ?></h1>
                <?php if ($data['sku']): ?>
                    <div class="sku">SKU: <?php echo esc_html($data['sku']); ?></div>
                <?php endif; ?>
            </div>

            <div class="content">
                <?php if ($options['include_image']): ?>
                    <div class="artwork-image">
                        <?php if ($data['image_url']): ?>
                            <img src="<?php echo esc_url($data['image_url']); ?>" alt="<?php echo esc_attr($data['title']); ?>">
                        <?php else: ?>
                            <div style="border:1px solid #ddd;padding:60px 20px;color:#999;">No image available</div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="details">
                    <?php foreach ($data['attributes'] as $label => $value): ?>
                        <div class="detail-row">
                            <span class="detail-label"><?php echo esc_html(ucfirst($label)); ?>:</span>
                            <span class="detail-value"><?php echo esc_html($value); ?></span>
                        </div>
                    <?php endforeach; ?>

                    <?php
// Only show price when: include_price is checked AND availability is included AND item is available
$show_price = !empty($options['include_price'])
    && $options['include_availability']
    && (empty($data['availability_status']) || $data['availability_status'] === 'available')
    && isset($data['price']);

if ($show_price):
?>
    <div class="detail-row">
        <span class="detail-label">Price:</span>
        <span class="detail-value"><?php echo wp_kses_post($data['price_formatted']); ?></span>
    </div>
<?php endif; ?>

<?php if ($options['include_availability']): ?>
    <?php echo $this->render_status_badge($data); ?>
    <?php if ($data['availability_status'] === 'gallery' && $data['gallery_name']): ?>
        <div class="detail-row">
            <span class="detail-label">Gallery:</span>
            <span class="detail-value"><?php echo esc_html($data['gallery_name']); ?></span>
        </div>
    <?php endif; ?>
<?php endif; ?>

                    <?php if (isset($data['wc_product_id'])): ?>
                        <div class="detail-row">
                            <span class="detail-label">Product ID:</span>
                            <span class="detail-value"><?php echo esc_html($data['wc_product_id']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($options['include_loan_section']): ?>
                <div class="loan-section">
                    <h3>Loan Information</h3>
                    <?php foreach (['Loaned To', 'Location', 'Date Out', 'Expected Return', 'Notes'] as $field): ?>
                        <div class="loan-field">
                            <label><?php echo $field; ?>:</label>
                            <div>&nbsp;</div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($options['include_footer'] && !empty($footer_content)): ?>
                <div class="footer">
                    <?php echo wp_kses_post(nl2br($footer_content)); ?>
                </div>
            <?php endif; ?>

            <?php if ($options['include_generated_date']): ?>
                <div class="generated-date">
                    Generated on <?php echo date('F j, Y'); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    private function render_status_badge($data) {
        $status_class = 'status-available';
        $status_text = 'Available';

        // Prioritize ACF field over WooCommerce stock status
        if ($data['availability_status'] === 'sold') {
            $status_class = 'status-sold';
            $status_text = 'Sold';
        } elseif ($data['availability_status'] === 'gallery') {
            $status_class = 'status-gallery';
            $status_text = 'On View at Gallery';
        } elseif ($data['availability_status'] === 'private') {
            $status_class = 'status-private';
            $status_text = 'Private Collection';
        } elseif (!$data['in_stock']) {
            // Only use WooCommerce stock if no ACF status is set
            $status_class = 'status-sold';
            $status_text = 'Sold';
        }
        ?>
        <div class="detail-row">
            <span class="detail-label">Status:</span>
            <span class="detail-value">
                <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
            </span>
        </div>
        <?php
    }

    private function render_status_badge_inline($data) {
        $status_text = 'Available';

        if ($data['availability_status'] === 'sold') {
            $status_text = 'Sold';
        } elseif ($data['availability_status'] === 'gallery') {
            $status_text = 'On View at Gallery';
        } elseif ($data['availability_status'] === 'private') {
            $status_text = 'Private Collection';
        } elseif (!$data['in_stock']) {
            $status_text = 'Sold';
        }

        return '<div class="detail"><strong>Status:</strong> ' . esc_html($status_text) . '</div>';
    }

    private function render_multi_sheet($product_ids, $options, $layout) {
        $grid_cols = ($layout === 'dual') ? 2 : 2;
        $font_size = '10pt';
        $heading_size = '12pt';
        ?>
        <style>
            .multi-grid {
                display: grid;
                grid-template-columns: repeat(<?php echo $grid_cols; ?>, 1fr);
                gap: 20px;
                padding: 20px;
            }
            .multi-item {
                border: 1px solid #ddd;
                padding: 15px;
                font-size: <?php echo $font_size; ?>;
            }
            .multi-item img {
                max-width: 100%;
                height: auto;
                margin-bottom: 10px;
            }
            .multi-item h3 {
                font-size: <?php echo $heading_size; ?>;
                margin: 0 0 8px 0;
            }
            .multi-item .detail {
                margin-bottom: 4px;
            }
        </style>
        <div class="multi-grid">
            <?php foreach ($product_ids as $product_id): ?>
                <?php $data = Monospace_Product_Data::instance()->gather_data($product_id, $options); ?>
                <?php if ($data): ?>
                    <div class="multi-item">
                        <h3><?php echo esc_html($data['title']); ?></h3>
                        <?php if ($data['sku']): ?>
                            <div class="detail"><strong>SKU:</strong> <?php echo esc_html($data['sku']); ?></div>
                        <?php endif; ?>

                        <?php if ($options['include_image'] && $data['image_url']): ?>
                            <img src="<?php echo esc_url($data['image_url']); ?>" alt="<?php echo esc_attr($data['title']); ?>">
                        <?php endif; ?>

                        <?php foreach ($data['attributes'] as $label => $value): ?>
                            <div class="detail"><strong><?php echo esc_html(ucfirst($label)); ?>:</strong> <?php echo esc_html($value); ?></div>
                        <?php endforeach; ?>

                        <?php
                        // Only show price when: include_price is checked AND availability is included AND status is available
                        $show_price = !empty($options['include_price'])
                            && $options['include_availability']
                            && (empty($data['availability_status']) || $data['availability_status'] === 'available')
                            && isset($data['price']);
                        if ($show_price):
                        ?>
                            <div class="detail"><strong>Price:</strong> <?php echo wp_kses_post($data['price_formatted']); ?></div>
                        <?php endif; ?>

                        <?php if ($options['include_availability']): ?>
                            <?php echo $this->render_status_badge_inline($data); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php
    }

    private function render_list_view($product_ids, $options) {
        ?>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                padding: 40px;
            }
            .list-table {
                width: 100%;
                border-collapse: collapse;
            }
            .list-table th {
                background: #f0f0f0;
                padding: 8px;
                text-align: left;
                border: 1px solid #ddd;
            }
            .list-table td {
                padding: 8px;
                border: 1px solid #ddd;
                font-size: 10pt;
            }
        </style>
        <h1>Artwork List</h1>
        <table class="list-table">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>SKU</th>
                    <th>Format</th>
                    <th>Medium</th>
                    <th>Surface</th>
                    <th>Size</th>
                    <?php if (!empty($options['include_price']) && $options['include_availability']): ?>
                        <th>Price</th>
                    <?php endif; ?>
                    <?php if ($options['include_availability']): ?>
                        <th>Status</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($product_ids as $product_id): ?>
                    <?php $data = Monospace_Product_Data::instance()->gather_data($product_id, $options); ?>
                    <?php if ($data): ?>
                        <?php
                        // Only show price if status is available
                        $is_available = empty($data['availability_status']) || $data['availability_status'] === 'available';
                        ?>
                        <tr>
                            <td><?php echo esc_html($data['title']); ?></td>
                            <td><?php echo esc_html($data['sku']); ?></td>
                            <td><?php echo esc_html($data['attributes']['format'] ?? ''); ?></td>
                            <td><?php echo esc_html($data['attributes']['medium'] ?? ''); ?></td>
                            <td><?php echo esc_html($data['attributes']['surface'] ?? ''); ?></td>
                            <td><?php echo esc_html($data['attributes']['size'] ?? ''); ?></td>
                            <?php if (!empty($options['include_price']) && $options['include_availability']): ?>
                                <td><?php echo $is_available ? wp_kses_post($data['price_formatted'] ?? '') : '—'; ?></td>
                            <?php endif; ?>
                            <?php if ($options['include_availability']): ?>
                                <td><?php echo esc_html(ucfirst($data['availability_status'] ?: 'available')); ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
}
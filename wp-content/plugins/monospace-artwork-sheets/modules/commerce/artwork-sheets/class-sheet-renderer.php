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
        $layout = $options['layout'];

        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Artwork Sheets</title>';

        // Output styles once in the head
        if (!empty($options['images_only'])) {
            // Images-only mode handles its own styles
        } elseif ($layout === 'list') {
            // List view handles its own styles
        } elseif ($layout === 'single') {
            $this->render_single_sheet_styles($options);
        } else {
            // Multi-sheet handles its own styles
        }

        echo '</head><body>';

        // Check if images-only mode is enabled
        if (!empty($options['images_only'])) {
            $this->render_images_only($product_ids, $options);
        } elseif ($layout === 'list') {
            $this->render_list_view($product_ids, $options);
        } else {
            $items_per_page = $this->get_items_per_page($layout);
            $chunks = array_chunk($product_ids, $items_per_page);
            $total_chunks = count($chunks);

            foreach ($chunks as $index => $chunk) {
                if ($layout === 'single') {
                    $data = Monospace_Product_Data::instance()->gather_data($chunk[0], $options);
                    if ($data) {
                        $is_last_chunk = ($index === $total_chunks - 1);
                        $this->render_single_sheet_content($data, $options, $is_last_chunk);
                    }
                } else {
                    $is_last_chunk = ($index === $total_chunks - 1);
                    $page_break_style = $is_last_chunk ? '' : 'page-break-after: always;';
                    echo '<div class="sheet-page" style="' . $page_break_style . '">';
                    $this->render_multi_sheet($chunk, $options, $layout);
                    echo '</div>';
                }
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

    private function render_single_sheet_styles($options) {
        ?>
        <style>
            @page {
                size: letter;
                margin: 0;
            }

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
                body {
                    margin: 0;
                    padding: 0;
                }
                .sheet {
                    padding: 0.4in 0.5in;
                    margin: 0;
                    min-height: 11in;
                    max-height: 11in;
                    box-sizing: border-box;
                    transform-origin: top left;
                }
                /* Auto-scale if content is too large */
                @supports (container-type: size) {
                    .sheet {
                        container-type: size;
                    }
                }
            }

            .sheet {
                font-size: 10.5pt;
                line-height: 1.35;
            }
            .header {
                border-bottom: 2px solid #333;
                padding-bottom: 12px;
                margin-bottom: 18px;
            }
            .title {
                font-size: 18pt;
                font-weight: bold;
                margin: 0 0 4px 0;
                line-height: 1.2;
            }
            .sku { font-size: 9.5pt; color: #666; }
            .content {
                display: grid;
                grid-template-columns: <?php echo $options['include_image'] ? '1fr 1fr' : '1fr'; ?>;
                gap: 24px;
                margin-bottom: 20px;
            }
            .artwork-image { text-align: center; }
            .artwork-image img {
                max-width: 100%;
                max-height: 340px;
                border: 1px solid #ddd;
            }
            .detail-row { margin-bottom: 10px; }
            .detail-label { font-weight: bold; display: inline-block; min-width: 100px; }
            .status-badge {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 3px;
                font-size: 8.5pt;
                font-weight: bold;
                text-transform: uppercase;
            }
            .status-available { background: #d4edda; color: #155724; }
            .status-sold { background: #f8d7da; color: #721c24; }
            .status-gallery { background: #fff3cd; color: #856404; }
            .status-private { background: #e2e3e5; color: #383d41; }
            .loan-section {
                margin-top: 20px;
                padding-top: 15px;
                border-top: 1px solid #ddd;
            }
            .loan-section h3 {
                margin: 0 0 12px 0;
                font-size: 11pt;
            }
            .loan-field {
                margin-bottom: 12px;
                padding-bottom: 6px;
                border-bottom: 1px dotted #ccc;
            }
            .loan-field label {
                display: block;
                font-size: 8.5pt;
                color: #666;
                margin-bottom: 2px;
            }
            .footer {
                margin-top: 20px;
                padding-top: 12px;
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
        <?php
    }

    private function render_single_sheet_content($data, $options, $is_last = false) {
        $footer_content = Monospace_Sheet_Settings::get_footer_content($data);
        $page_break_style = $is_last ? '' : 'page-break-after: always;';
        ?>
        <div class="sheet" style="<?php echo $page_break_style; ?>">
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
// Only show price when: include_price is checked AND availability is included AND item is available or at gallery
$show_price = !empty($options['include_price'])
    && $options['include_availability']
    && (empty($data['availability_status']) || $data['availability_status'] === 'available' || $data['availability_status'] === 'gallery')
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
                        // Only show price when: include_price is checked AND availability is included AND status is available or at gallery
                        $show_price = !empty($options['include_price'])
                            && $options['include_availability']
                            && (empty($data['availability_status']) || $data['availability_status'] === 'available' || $data['availability_status'] === 'gallery')
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
                        // Only show price if status is available or at gallery
                        $is_available = empty($data['availability_status']) || $data['availability_status'] === 'available' || $data['availability_status'] === 'gallery';
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

    private function render_images_only($product_ids, $options) {
        $layout = $options['layout'] ?? 'single';

        // Determine items per page based on layout
        $items_per_page = match($layout) {
            'single' => 1,
            'dual' => 2,
            'quad' => 4,
            'list' => 6,
            default => 4
        };

        ?>
        <style>
            @media screen {
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
                    margin: 0;
                    padding: 20px;
                    background: #f5f5f5;
                }
            }

            @media print {
                body {
                    margin: 0;
                    padding: 0;
                    background: white;
                }
                .page-break {
                    page-break-after: always;
                }
                <?php if ($layout === 'single'): ?>
                .images-grid {
                    padding: 0;
                    margin: 0;
                }
                .image-item {
                    padding: 0;
                }
                <?php endif; ?>
            }

            .images-grid {
                display: grid;
                grid-template-columns: repeat(<?php echo $layout === 'single' ? '1' : ($layout === 'dual' ? '2' : '2'); ?>, 1fr);
                gap: <?php echo $layout === 'single' ? '0' : '20px'; ?>;
                padding: <?php echo $layout === 'single' ? '0' : '20px'; ?>;
                background: white;
            }

            .image-item {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                text-align: center;
                <?php if ($layout === 'single'): ?>
                    min-height: 100vh;
                    box-sizing: border-box;
                    padding: 40px;
                <?php endif; ?>
            }

            .image-item img {
                max-width: 100%;
                max-height: <?php echo $layout === 'single' ? '85vh' : '400px'; ?>;
                width: auto;
                height: auto;
                object-fit: contain;
                border: 1px solid #ddd;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }

            .image-caption {
                margin-top: 15px;
                font-size: <?php echo $layout === 'single' ? '14pt' : '10pt'; ?>;
                color: #333;
            }

            .image-title {
                font-weight: bold;
                margin-bottom: 5px;
            }

            .image-sku {
                color: #666;
                font-size: <?php echo $layout === 'single' ? '11pt' : '9pt'; ?>;
            }

            .no-image {
                width: 100%;
                height: 300px;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #f0f0f0;
                border: 1px solid #ddd;
                color: #999;
                font-size: 14pt;
            }
        </style>

        <?php
        $chunks = array_chunk($product_ids, $items_per_page);
        $total_pages = count($chunks);

        foreach ($chunks as $page_index => $chunk):
            $is_last_page = ($page_index === $total_pages - 1);
            ?>
            <div class="images-grid <?php echo !$is_last_page ? 'page-break' : ''; ?>">
                <?php foreach ($chunk as $product_id): ?>
                    <?php $data = Monospace_Product_Data::instance()->gather_data($product_id, $options); ?>
                    <?php if ($data): ?>
                        <div class="image-item">
                            <?php if ($data['image_url']): ?>
                                <img src="<?php echo esc_url($data['image_url']); ?>" alt="<?php echo esc_attr($data['title']); ?>">
                            <?php else: ?>
                                <div class="no-image">No image available</div>
                            <?php endif; ?>

                            <div class="image-caption">
                                <div class="image-title"><?php echo esc_html($data['title']); ?></div>
                                <?php if ($data['sku']): ?>
                                    <div class="image-sku">SKU: <?php echo esc_html($data['sku']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <?php
    }
}
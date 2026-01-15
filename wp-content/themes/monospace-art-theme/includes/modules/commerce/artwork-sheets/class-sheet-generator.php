<?php
/**
 * Sheet Generator UI
 *
 * @package monospace-art-theme
 */

class Monospace_Sheet_Generator {
    private static $instance = null;

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function render_page() {
        ?>
        <div class="wrap">
            <h1>Artwork Sheet Generator</h1>

            <form id="artwork-sheet-form" method="post" target="_blank">
                <?php wp_nonce_field('monospace_artwork_sheet', 'artwork_sheet_nonce'); ?>

                <?php $this->render_output_options(); ?>
                <?php $this->render_filters(); ?>
                <?php $this->render_product_selector(); ?>
            </form>
        </div>

        <?php $this->render_styles(); ?>
        <?php $this->render_scripts(); ?>
        <?php
    }

    private function render_output_options() {
        ?>
        <div style="background:#fff;padding:20px;border:1px solid #ccc;margin-bottom:20px;">
            <h2 style="margin-top:0;">Output Options</h2>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:30px;margin-bottom:20px;">
                <!-- Left Column: Layout & Display -->
                <div>
                    <h3 style="margin-top:0;font-size:14px;border-bottom:1px solid #ddd;padding-bottom:8px;">Layout & Display</h3>
                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th style="width:140px;">Layout</th>
                            <td>
                                <label><input type="radio" name="layout" value="single" checked> One per sheet</label><br>
                                <label><input type="radio" name="layout" value="dual"> Two per sheet</label><br>
                                <label><input type="radio" name="layout" value="quad"> Four per sheet</label><br>
                                <label><input type="radio" name="layout" value="list"> List view</label>
                            </td>
                        </tr>
                        <tr>
                            <th>Show Image</th>
                            <td><input type="checkbox" name="include_image" value="1" checked></td>
                        </tr>
                        <tr>
                            <th>Show Availability</th>
                            <td><input type="checkbox" name="include_availability" id="include_availability" value="1" checked></td>
                        </tr>
                        <tr id="price-row">
                            <th style="padding-left:20px;">Show Price</th>
                            <td><input type="checkbox" name="include_price" id="include_price" value="1" checked></td>
                        </tr>
                    </table>
                </div>

                <!-- Right Column: Additional Info -->
                <div>
                    <h3 style="margin-top:0;font-size:14px;border-bottom:1px solid #ddd;padding-bottom:8px;">Additional Information</h3>
                    <table class="form-table" style="margin:0;">
                        <tr>
                            <th style="width:180px;">Loan Tracking Section</th>
                            <td><input type="checkbox" name="include_loan_section" value="1" checked></td>
                        </tr>
                        <tr>
                            <th>Footer</th>
                            <td><input type="checkbox" name="include_footer" value="1" checked></td>
                        </tr>
                        <tr>
                            <th>Generated Date</th>
                            <td><input type="checkbox" name="include_generated_date" value="1" checked></td>
                        </tr>
                        <tr>
                            <th>WooCommerce ID</th>
                            <td><input type="checkbox" name="include_wc_id" value="1"></td>
                        </tr>
                        <tr>
                            <th>WordPress Post ID</th>
                            <td><input type="checkbox" name="include_post_id" value="1"></td>
                        </tr>
                    </table>
                </div>
            </div>

            <div style="border-top:1px solid #ddd;padding-top:15px;display:flex;gap:30px;align-items:flex-start;">
                <div>
                    <h3 style="margin:0 0 10px 0;font-size:14px;">Export</h3>
                    <p style="margin:0;">
                        <button type="submit" name="action" value="preview" class="button button-primary">Preview (Print to PDF)</button>
                        <button type="submit" name="action" value="json" class="button">Export JSON</button>
                        <button type="submit" name="action" value="csv" class="button">Export CSV</button>
                    </p>
                </div>

                <div>
                    <h3 style="margin:0 0 10px 0;font-size:14px;">Import Selection</h3>
                    <p style="margin:0;">
                        <input type="file" id="import-json" accept=".json" style="display:none;">
                        <button type="button" id="import-json-btn" class="button">Import from JSON</button>
                        <span id="import-status" style="margin-left:10px;color:#666;font-size:12px;"></span>
                    </p>
                </div>

                <div>
                    <h3 style="margin:0 0 10px 0;font-size:14px;">Share</h3>
                    <p style="margin:0;">
                        <button type="button" id="generate-public-link" class="button">Generate Public Link</button>
                        <span id="public-link-status" style="margin-left:10px;color:#666;font-size:12px;"></span>
                    </p>
                    <div id="public-link-result" style="display:none;margin-top:10px;padding:10px;background:#f0f9ff;border:1px solid #0073aa;border-radius:3px;">
                        <input type="text" id="public-link-url" readonly style="width:100%;margin-bottom:5px;">
                        <button type="button" id="copy-public-link" class="button button-small">Copy Link</button>
                        <span id="copy-status" style="margin-left:5px;font-size:11px;color:#666;"></span>
                        <div style="margin-top:5px;font-size:11px;color:#666;">Expires: <span id="public-link-expires"></span></div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    private function render_filters() {
        $filter_options = Monospace_Product_Data::instance()->get_filter_options();
        ?>
        <h2>Filter Artwork</h2>
        <div style="background:#fff;padding:15px;border:1px solid #ccc;margin-bottom:20px;">
            <div class="filter-grid">
                <div class="filter-group">
                    <label for="filter-sku">SKU</label>
                    <input type="text" id="filter-sku" placeholder="Search SKU...">
                </div>

                <div class="filter-group">
                    <label for="filter-format">Format</label>
                    <select id="filter-format">
                        <option value="">All</option>
                        <?php foreach ($filter_options['formats'] as $val): ?>
                            <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($val); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filter-medium">Medium</label>
                    <select id="filter-medium">
                        <option value="">All</option>
                        <?php foreach ($filter_options['mediums'] as $val): ?>
                            <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($val); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filter-surface">Surface</label>
                    <select id="filter-surface">
                        <option value="">All</option>
                        <?php foreach ($filter_options['surfaces'] as $val): ?>
                            <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($val); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filter-size">Size</label>
                    <select id="filter-size">
                        <option value="">All</option>
                        <?php foreach ($filter_options['sizes'] as $val): ?>
                            <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($val); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filter-status">Availability</label>
                    <select id="filter-status">
                        <option value="">All</option>
                        <?php foreach ($filter_options['statuses'] as $key => $val): ?>
                            <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($val); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filter-price">Price</label>
                    <select id="filter-price">
                        <option value="">All</option>
                        <option value="with_price">With Price</option>
                        <option value="no_price">No Price</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="filter-date-from">Date From</label>
                    <input type="date" id="filter-date-from">
                </div>

                <div class="filter-group">
                    <label for="filter-date-to">Date To</label>
                    <input type="date" id="filter-date-to">
                </div>
            </div>
            <div style="margin-top:10px;">
                <button type="button" id="reset-filters" class="button">Reset All Filters</button>
            </div>
        </div>
        <?php
    }

    private function render_product_selector() {
        $products = Monospace_Product_Data::instance()->get_product_list();
        ?>
        <h2>Select Artwork <span id="selection-count" style="font-weight:normal;font-size:14px;color:#666;"></span></h2>
        <table class="artwork-selector-table">
            <thead>
                <tr>
                    <th style="width:60px;"></th>
                    <th class="sortable-header sorted-asc" data-column="title" style="width:25%">Title</th>
                    <th class="sortable-header" data-column="sku" style="width:12%">SKU</th>
                    <th class="sortable-header" data-column="format" style="width:10%">Format</th>
                    <th class="sortable-header" data-column="medium" style="width:10%">Medium</th>
                    <th class="sortable-header" data-column="date" style="width:10%">Date</th>
                    <th class="sortable-header" data-column="status" style="width:10%">Status</th>
                    <th class="sortable-header" data-column="id" style="width:8%">ID</th>
                    <th class="sortable-header" data-column="selected" style="width:7%;text-align:center;">Select</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $row): ?>
                    <tr data-id="<?php echo $row['id']; ?>"
                        data-selected="0"
                        data-title="<?php echo esc_attr(strtolower($row['title'])); ?>"
                        data-sku="<?php echo esc_attr(strtolower($row['sku'])); ?>"
                        data-format="<?php echo esc_attr($row['format']); ?>"
                        data-medium="<?php echo esc_attr($row['medium']); ?>"
                        data-surface="<?php echo esc_attr($row['surface']); ?>"
                        data-size="<?php echo esc_attr($row['size']); ?>"
                        data-date="<?php echo esc_attr($row['date']); ?>"
                        data-status="<?php echo esc_attr(strtolower($row['status'] ?: 'available')); ?>"
                        data-hasprice="<?php echo esc_attr($row['has_price']); ?>">
                        <td style="padding:4px;">
                            <?php if ($row['image_url']): ?>
                                <img src="<?php echo esc_url($row['image_url']); ?>"
                                     alt="<?php echo esc_attr($row['title']); ?>"
                                     style="width:50px;height:50px;object-fit:cover;border:1px solid #ddd;display:block;">
                            <?php else: ?>
                                <div style="width:50px;height:50px;background:#f0f0f0;border:1px solid #ddd;display:flex;align-items:center;justify-content:center;font-size:20px;color:#999;">?</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($row['related_post_id'])): ?>
                                <a href="<?php echo esc_url(get_edit_post_link($row['related_post_id'])); ?>"
                                   target="_blank"
                                   style="text-decoration:none;color:#2271b1;"
                                   title="Edit related post">
                                    <?php echo esc_html($row['title']); ?>
                                </a>
                            <?php else: ?>
                                <?php echo esc_html($row['title']); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($row['sku']); ?></td>
                        <td><?php echo esc_html($row['format']); ?></td>
                        <td><?php echo esc_html($row['medium']); ?></td>
                        <td><?php echo esc_html(date('Y-m-d', strtotime($row['date']))); ?></td>
                        <td><?php echo esc_html(ucfirst($row['status'] ?: 'available')); ?></td>
                        <td><?php echo $row['id']; ?></td>
                        <td style="text-align:center;">
                            <input type="checkbox" name="product_ids[]" value="<?php echo $row['id']; ?>">
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">Click column headers to sort. Use filters above to narrow results. Check items to include in export.</p>
        <?php
    }

    private function render_styles() {
        ?>
        <style>
            .filter-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin-bottom: 15px;
            }
            .filter-group {
                display: flex;
                flex-direction: column;
                width: 100%;
            }
            .filter-group label {
                font-weight: bold;
                margin-bottom: 4px;
                font-size: 12px;
            }
            .filter-group select,
            .filter-group input {
                width: 100%;
            }
            .artwork-selector-table {
                width: 100%;
                max-width: 1000px;
                border-collapse: collapse;
                margin: 10px 0;
            }
            .artwork-selector-table th {
                background: #f0f0f0;
                padding: 8px;
                text-align: left;
                border: 1px solid #ddd;
                cursor: pointer;
                user-select: none;
            }
            .artwork-selector-table th:hover {
                background: #e0e0e0;
            }
            .artwork-selector-table td {
                padding: 6px 8px;
                border: 1px solid #ddd;
            }
            .artwork-selector-table tr:hover {
                background: #f9f9f9;
            }
            .artwork-selector-table tr.filtered-out {
                display: none;
            }
            .sortable-header::after {
                content: ' ⇅';
                opacity: 0.3;
            }
            .sortable-header.sorted-asc::after {
                content: ' ↑';
                opacity: 1;
            }
            .sortable-header.sorted-desc::after {
                content: ' ↓';
                opacity: 1;
            }
        </style>
        <?php
    }

    private function render_scripts() {
        ?>
        <script>
        (function() {
            let sortColumn = 'title';
            let sortDirection = 'asc';

            function sortTable(column) {
                if (sortColumn === column) {
                    sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    sortColumn = column;
                    sortDirection = 'asc';
                }

                const table = document.querySelector('.artwork-selector-table tbody');
                const rows = Array.from(table.querySelectorAll('tr'));

                rows.sort((a, b) => {
                    let aVal = a.dataset[column] || '';
                    let bVal = b.dataset[column] || '';

                    if (column === 'id' || column === 'selected') {
                        aVal = parseInt(aVal);
                        bVal = parseInt(bVal);
                    } else if (column === 'date') {
                        aVal = new Date(aVal).getTime();
                        bVal = new Date(bVal).getTime();
                    }

                    if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
                    if (aVal > bVal) return sortDirection === 'asc' ? 1 : -1;
                    return 0;
                });

                rows.forEach(row => table.appendChild(row));

                document.querySelectorAll('.sortable-header').forEach(th => {
                    th.classList.remove('sorted-asc', 'sorted-desc');
                });
                document.querySelector(`[data-column="${column}"]`).classList.add(`sorted-${sortDirection}`);
            }

            function applyFilters() {
                const filters = {
                    sku: document.getElementById('filter-sku').value.toLowerCase(),
                    format: document.getElementById('filter-format').value,
                    medium: document.getElementById('filter-medium').value,
                    surface: document.getElementById('filter-surface').value,
                    size: document.getElementById('filter-size').value,
                    status: document.getElementById('filter-status').value,
                    price: document.getElementById('filter-price').value,
                    dateFrom: document.getElementById('filter-date-from').value,
                    dateTo: document.getElementById('filter-date-to').value,
                };

                const rows = document.querySelectorAll('.artwork-selector-table tbody tr');

                rows.forEach(row => {
                    let show = true;

                    // SKU filter uses simple contains for now
                    if (filters.sku && !row.dataset.sku.includes(filters.sku)) show = false;
                    if (filters.format && row.dataset.format !== filters.format) show = false;
                    if (filters.medium && row.dataset.medium !== filters.medium) show = false;
                    if (filters.surface && row.dataset.surface !== filters.surface) show = false;
                    if (filters.size && row.dataset.size !== filters.size) show = false;
                    if (filters.status && row.dataset.status !== filters.status) show = false;

                    if (filters.price) {
                        const hasPrice = row.dataset.hasprice === 'true';
                        if (filters.price === 'with_price' && !hasPrice) show = false;
                        if (filters.price === 'no_price' && hasPrice) show = false;
                    }

                    if (filters.dateFrom && new Date(row.dataset.date) < new Date(filters.dateFrom)) show = false;
                    if (filters.dateTo && new Date(row.dataset.date) > new Date(filters.dateTo)) show = false;

                    row.classList.toggle('filtered-out', !show);
                });

                updateSelectionCount();
            }

            function resetFilters() {
                ['filter-sku', 'filter-format', 'filter-medium', 'filter-surface',
                 'filter-size', 'filter-status', 'filter-price', 'filter-date-from', 'filter-date-to']
                    .forEach(id => document.getElementById(id).value = '');
                applyFilters();
            }

            function updateSelectionCount() {
                const checked = Array.from(document.querySelectorAll('.artwork-selector-table tbody input[type="checkbox"]:checked'))
                    .filter(cb => !cb.closest('tr').classList.contains('filtered-out'));

                const counter = document.getElementById('selection-count');
                counter.textContent = checked.length > 0 ? `(${checked.length} selected)` : '';
                counter.style.color = '#2271b1';
            }

            document.addEventListener('DOMContentLoaded', function() {
                // Price row visibility toggle
                const availabilityCheckbox = document.getElementById('include_availability');
                const priceRow = document.getElementById('price-row');
                const priceCheckbox = document.getElementById('include_price');

                function updatePriceRowVisibility() {
                    priceRow.style.display = availabilityCheckbox.checked ? '' : 'none';
                    if (!availabilityCheckbox.checked) {
                        priceCheckbox.checked = false;
                    }
                }

                availabilityCheckbox.addEventListener('change', updatePriceRowVisibility);
                updatePriceRowVisibility();

                // Sort handlers
                document.querySelectorAll('.sortable-header').forEach(th => {
                    th.addEventListener('click', () => sortTable(th.dataset.column));
                });

                // Filter handlers - SKU uses input event for live filtering
                document.getElementById('filter-sku').addEventListener('input', applyFilters);

                ['filter-format', 'filter-medium', 'filter-surface',
                 'filter-size', 'filter-status', 'filter-price', 'filter-date-from', 'filter-date-to']
                    .forEach(id => {
                        const el = document.getElementById(id);
                        el.addEventListener('change', applyFilters);
                    });

                document.getElementById('reset-filters').addEventListener('click', (e) => {
                    e.preventDefault();
                    resetFilters();
                });

                document.querySelectorAll('.artwork-selector-table tbody input[type="checkbox"]')
                    .forEach(cb => cb.addEventListener('change', function() {
                        // Update data-selected attribute for sorting
                        this.closest('tr').dataset.selected = this.checked ? '1' : '0';
                        updateSelectionCount();
                    }));

                // Import JSON functionality
                document.getElementById('import-json-btn').addEventListener('click', function() {
                    document.getElementById('import-json').click();
                });

                document.getElementById('import-json').addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (!file) return;

                    const reader = new FileReader();
                    reader.onload = function(event) {
                        try {
                            const data = JSON.parse(event.target.result);
                            if (!Array.isArray(data)) {
                                throw new Error('Invalid JSON format');
                            }

                            // Extract product IDs from JSON (check for wc_product_id or wp_post_id)
                            const productIds = data.map(item => item.wc_product_id || item.wp_post_id).filter(Boolean);

                            if (productIds.length === 0) {
                                throw new Error('No product IDs found in JSON');
                            }

                            // Uncheck all checkboxes first and reset data-selected
                            document.querySelectorAll('.artwork-selector-table tbody input[type="checkbox"]').forEach(cb => {
                                cb.checked = false;
                                cb.closest('tr').dataset.selected = '0';
                            });

                            // Check boxes for imported IDs and set data-selected
                            let matchedCount = 0;
                            productIds.forEach(id => {
                                const checkbox = document.querySelector(`.artwork-selector-table tbody input[value="${id}"]`);
                                if (checkbox) {
                                    checkbox.checked = true;
                                    checkbox.closest('tr').dataset.selected = '1';
                                    matchedCount++;
                                }
                            });

                            updateSelectionCount();

                            const status = document.getElementById('import-status');
                            status.textContent = `✓ Imported ${matchedCount} of ${productIds.length} artworks`;
                            status.style.color = '#46b450';

                            setTimeout(() => {
                                status.textContent = '';
                            }, 5000);

                        } catch (error) {
                            const status = document.getElementById('import-status');
                            status.textContent = '✗ Error: ' + error.message;
                            status.style.color = '#dc3232';
                        }
                    };
                    reader.readAsText(file);

                    // Reset file input
                    e.target.value = '';
                });

                document.getElementById('artwork-sheet-form').addEventListener('submit', function(e) {
                    const checked = Array.from(document.querySelectorAll('.artwork-selector-table tbody input[type="checkbox"]:checked'))
                        .filter(cb => !cb.closest('tr').classList.contains('filtered-out'));

                    if (checked.length === 0) {
                        e.preventDefault();
                        alert('Please select at least one artwork before exporting.');
                        return false;
                    }
                });

                // Generate Public Link
                document.getElementById('generate-public-link').addEventListener('click', function() {
                    const checked = Array.from(document.querySelectorAll('.artwork-selector-table tbody input[type="checkbox"]:checked'))
                        .filter(cb => !cb.closest('tr').classList.contains('filtered-out'));

                    if (checked.length === 0) {
                        alert('Please select at least one artwork first.');
                        return;
                    }

                    const status = document.getElementById('public-link-status');
                    status.textContent = 'Generating...';
                    status.style.color = '#666';

                    const form = document.getElementById('artwork-sheet-form');
                    const formData = new FormData();
                    formData.append('action', 'generate_public_sheet_link');
                    formData.append('nonce', form.querySelector('[name="artwork_sheet_nonce"]').value);

                    // Add selected product IDs
                    checked.forEach(cb => {
                        formData.append('product_ids[]', cb.value);
                    });

                    // Add options
                    formData.append('layout', form.querySelector('[name="layout"]:checked').value);
                    formData.append('include_image', form.querySelector('[name="include_image"]').checked);
                    formData.append('include_availability', form.querySelector('[name="include_availability"]').checked);
                    formData.append('include_price', form.querySelector('[name="include_price"]').checked);
                    formData.append('include_loan_section', form.querySelector('[name="include_loan_section"]').checked);
                    formData.append('include_footer', form.querySelector('[name="include_footer"]').checked);
                    formData.append('include_generated_date', form.querySelector('[name="include_generated_date"]').checked);
                    formData.append('include_wc_id', form.querySelector('[name="include_wc_id"]').checked);
                    formData.append('include_post_id', form.querySelector('[name="include_post_id"]').checked);

                    fetch(ajaxurl, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.success) {
                            status.textContent = '';
                            document.getElementById('public-link-result').style.display = 'block';
                            document.getElementById('public-link-url').value = result.data.url;
                            document.getElementById('public-link-expires').textContent = result.data.expires;
                        } else {
                            status.textContent = '✗ Error: ' + result.data;
                            status.style.color = '#dc3232';
                        }
                    })
                    .catch(error => {
                        status.textContent = '✗ Error: ' + error.message;
                        status.style.color = '#dc3232';
                    });
                });

                // Copy link to clipboard
                document.getElementById('copy-public-link').addEventListener('click', function() {
                    const urlInput = document.getElementById('public-link-url');
                    urlInput.select();
                    document.execCommand('copy');

                    const copyStatus = document.getElementById('copy-status');
                    copyStatus.textContent = 'Copied!';
                    copyStatus.style.color = '#46b450';
                    setTimeout(() => { copyStatus.textContent = ''; }, 2000);
                });
            });
        })();
        </script>
        <?php
    }
}
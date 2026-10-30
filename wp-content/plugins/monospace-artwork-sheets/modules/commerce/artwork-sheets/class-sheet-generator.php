<?php
/**
 * Sheet Generator UI
 *
 * Reworked 2026-10-28 to the admin UI style (CLAUDE.md): WordPress-native parts, folding panels with state chips
 * (the lm-* styles from Core's monospace_lm_area_assets()). Panels: Artworks (pick), Printable sheet,
 * Slideshow, Lists & Artwork Editor.
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
        $products = Monospace_Product_Data::instance()->get_product_list();
        ?>
        <div class="wrap lm-areas sg-wrap">
            <h1>Artwork Sheet Generator</h1>
            <p class="lm-where">Pick the artworks, then make a printable sheet, an export or a slideshow - or save the pick as a list.</p>

            <form id="artwork-sheet-form" method="post" target="_blank">
                <?php wp_nonce_field('monospace_artwork_sheet', 'artwork_sheet_nonce'); ?>

                <div class="lm-panels">
                    <?php $this->render_product_selector($products); ?>
                    <?php $this->render_sheet_panel(); ?>
                    <?php $this->render_slideshow_panel(); ?>
                    <?php $this->render_lists_panel(); ?>
                </div>
            </form>
        </div>

        <?php
        // After the markup: its script sets up the panels that exist by then
        if (function_exists('monospace_lm_area_assets')) {
            monospace_lm_area_assets();
        }
        $this->render_styles();
        $this->render_scripts();
        ?>
        <?php
    }

    /** A panel's chip: the number picked (updated by the script). */
    private function count_chip() {
        return '<span class="lm-chip is-empty sg-count">None picked</span>';
    }

    private function link_box($id) {
        ?>
        <div class="sg-link" id="<?php echo esc_attr($id); ?>" hidden>
            <input type="text" class="regular-text sg-link-url" readonly aria-label="Link">
            <button type="button" class="button sg-copy">Copy link</button>
            <button type="button" class="button button-link sg-open">Open</button>
            <span class="sg-copied"></span>
            <p class="description">Anyone with the link can see it. Expires <span class="sg-expires"></span>.</p>
        </div>
        <?php
    }

    private function render_product_selector($products) {
        $filter_options = Monospace_Product_Data::instance()->get_filter_options();
        $selects = [
            'format' => ['Format', $filter_options['formats']],
            'medium' => ['Medium', $filter_options['mediums']],
            'surface' => ['Surface', $filter_options['surfaces']],
            'size' => ['Size', $filter_options['sizes']],
        ];
        ?>
        <details class="lm-panel" data-panel="artworks">
            <summary>
                <span class="lm-t">Artworks</span>
                <span class="lm-chip is-empty" id="selection-chip">None picked</span>
                <span class="lm-sub"><?php echo count($products); ?> artworks - filter, sort by clicking a column, tick the ones you want</span>
            </summary>
            <div class="lm-body">
                <div class="sg-filters">
                    <div class="sg-field">
                        <label for="filter-sku">SKU</label>
                        <input type="text" id="filter-sku" placeholder="Search SKU...">
                    </div>
                    <?php foreach ($selects as $key => $def): ?>
                        <div class="sg-field">
                            <label for="filter-<?php echo $key; ?>"><?php echo esc_html($def[0]); ?></label>
                            <select id="filter-<?php echo $key; ?>">
                                <option value="">All</option>
                                <?php foreach ($def[1] as $val): ?>
                                    <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($val); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>
                    <div class="sg-field">
                        <label for="filter-status">Availability</label>
                        <select id="filter-status">
                            <option value="">All</option>
                            <?php foreach ($filter_options['statuses'] as $key => $val): ?>
                                <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($val); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sg-field">
                        <label for="filter-price">Price</label>
                        <select id="filter-price">
                            <option value="">All</option>
                            <option value="with_price">With price</option>
                            <option value="no_price">No price</option>
                        </select>
                    </div>
                    <div class="sg-field">
                        <label for="filter-date-from">Date from</label>
                        <input type="date" id="filter-date-from">
                    </div>
                    <div class="sg-field">
                        <label for="filter-date-to">Date to</label>
                        <input type="date" id="filter-date-to">
                    </div>
                </div>
                <p class="sg-bar">
                    <button type="button" id="reset-filters" class="button">Reset filters</button>
                    <button type="button" id="select-visible" class="button">Pick all shown</button>
                    <button type="button" id="clear-selection" class="button">Clear picks</button>
                </p>

                <div class="sg-scroll">
                <table class="wp-list-table widefat striped artwork-selector-table">
                    <thead>
                        <tr>
                            <th class="sg-thumb"></th>
                            <th class="sortable-header sorted-asc" data-column="title">Title</th>
                            <th class="sortable-header" data-column="sku">SKU</th>
                            <th class="sortable-header" data-column="format">Format</th>
                            <th class="sortable-header" data-column="medium">Medium</th>
                            <th class="sortable-header" data-column="date">Date</th>
                            <th class="sortable-header" data-column="status">Status</th>
                            <th class="sortable-header sg-pick" data-column="selected">Pick</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $row): $status = strtolower($row['status'] ?: 'available'); ?>
                            <tr data-id="<?php echo (int) $row['id']; ?>"
                                data-post="<?php echo (int) $row['related_post_id']; ?>"
                                data-selected="0"
                                data-title="<?php echo esc_attr(strtolower($row['title'])); ?>"
                                data-sku="<?php echo esc_attr(strtolower($row['sku'])); ?>"
                                data-format="<?php echo esc_attr($row['format']); ?>"
                                data-medium="<?php echo esc_attr($row['medium']); ?>"
                                data-surface="<?php echo esc_attr($row['surface']); ?>"
                                data-size="<?php echo esc_attr($row['size']); ?>"
                                data-date="<?php echo esc_attr($row['date']); ?>"
                                data-status="<?php echo esc_attr($status); ?>"
                                data-hasprice="<?php echo esc_attr($row['has_price']); ?>">
                                <td class="sg-thumb">
                                    <?php if ($row['image_url']): ?>
                                        <img src="<?php echo esc_url($row['image_url']); ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                        <span class="sg-noimg">?</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($row['related_post_id'])): ?>
                                        <a href="<?php echo esc_url(get_edit_post_link($row['related_post_id'])); ?>" target="_blank" title="Edit related post"><?php echo esc_html($row['title']); ?></a>
                                    <?php else: ?>
                                        <?php echo esc_html($row['title']); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($row['sku']); ?></td>
                                <td><?php echo esc_html($row['format']); ?></td>
                                <td><?php echo esc_html($row['medium']); ?></td>
                                <td><?php echo esc_html(date('Y-m-d', strtotime($row['date']))); ?></td>
                                <td><span class="sg-status <?php echo esc_attr($this->status_class($status)); ?>"><?php echo esc_html(ucfirst($status)); ?></span></td>
                                <td class="sg-pick">
                                    <input type="checkbox" name="product_ids[]" value="<?php echo (int) $row['id']; ?>" aria-label="Pick <?php echo esc_attr($row['title']); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <p class="description">The sheet and the slideshow follow the order of this table - sort a column to change it (or shuffle the slideshow).</p>
            </div>
        </details>
        <?php
    }

    /** The availability colors are fixed (CLAUDE.md). */
    private function status_class($status) {
        if (strpos($status, 'qr') !== false) return 'is-qr';
        if (strpos($status, 'gallery') !== false) return 'is-gallery';
        if (strpos($status, 'reserved') !== false) return 'is-reserved';
        if (strpos($status, 'sold') !== false) return 'is-sold';
        if (strpos($status, 'private') !== false) return 'is-private';
        return 'is-available';
    }

    private function render_sheet_panel() {
        ?>
        <details class="lm-panel" data-panel="sheet">
            <summary>
                <span class="lm-t">Printable sheet</span>
                <?php echo $this->count_chip(); ?>
                <span class="lm-sub">Preview to print or save as PDF, export the data, or share a link</span>
            </summary>
            <div class="lm-body">
                <div class="sg-cols">
                    <div>
                        <h3>Layout &amp; display</h3>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">Layout</th>
                                <td>
                                    <label><input type="radio" name="layout" value="single" checked> One per sheet</label><br>
                                    <label><input type="radio" name="layout" value="dual"> Two per sheet</label><br>
                                    <label><input type="radio" name="layout" value="quad"> Four per sheet</label><br>
                                    <label><input type="radio" name="layout" value="list"> List view</label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Show</th>
                                <td>
                                    <label><input type="checkbox" name="include_image" value="1" checked> Image</label><br>
                                    <label><input type="checkbox" name="include_availability" id="include_availability" value="1" checked> Availability</label><br>
                                    <label id="price-row" class="sg-indent"><input type="checkbox" name="include_price" id="include_price" value="1" checked> Price</label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Images only</th>
                                <td><label><input type="checkbox" name="images_only" id="images_only" value="1"> Nothing but the pictures</label></td>
                            </tr>
                        </table>
                    </div>
                    <div>
                        <h3>Additional information</h3>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">Add</th>
                                <td>
                                    <label><input type="checkbox" name="include_loan_section" value="1" checked> Loan tracking section</label><br>
                                    <label><input type="checkbox" name="include_footer" value="1" checked> Footer</label><br>
                                    <label><input type="checkbox" name="include_generated_date" value="1" checked> Generated date</label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <p class="sg-actions">
                    <button type="submit" name="action" value="preview" class="button button-primary">Preview (print to PDF)</button>
                    <button type="submit" name="action" value="json" class="button">Export JSON</button>
                    <button type="submit" name="action" value="csv" class="button">Export CSV</button>
                    <button type="submit" name="action" value="images" class="button">Export images (ZIP)</button>
                    <button type="button" id="generate-public-link" class="button">Get public link</button>
                    <span class="sg-status-line" id="public-link-status"></span>
                </p>
                <?php $this->link_box('sheet-link'); ?>
            </div>
        </details>
        <?php
    }

    private function render_slideshow_panel() {
        $artist = get_option('monospace_sheet_artist_name', 'Hens Breet');
        ?>
        <details class="lm-panel" data-panel="slideshow">
            <summary>
                <span class="lm-t">Slideshow</span>
                <?php echo $this->count_chip(); ?>
                <span class="lm-sub">Full screen, with transitions and Ken Burns - on any screen, landscape or portrait, no sound</span>
            </summary>
            <div class="lm-body">
                <div class="sg-cols">
                    <div>
                        <h3>Look &amp; speed</h3>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row"><label for="show_transition">Transition</label></th>
                                <td>
                                    <select name="show_transition" id="show_transition">
                                        <option value="fade">Fade</option>
                                        <option value="push">Push</option>
                                        <option value="wipe">Wipe</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="show_seconds">Each artwork</label></th>
                                <td><input type="number" name="show_seconds" id="show_seconds" value="5" min="1" max="60" step="0.5" class="small-text"> seconds</td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="show_trans_seconds">Transition takes</label></th>
                                <td><input type="number" name="show_trans_seconds" id="show_trans_seconds" value="1" min="0.2" max="5" step="0.1" class="small-text"> seconds</td>
                            </tr>
                            <tr>
                                <th scope="row">Movement</th>
                                <td><label><input type="checkbox" name="show_kenburns" value="1" checked> Ken Burns (slow zoom and drift)</label></td>
                            </tr>
                        </table>
                    </div>
                    <div>
                        <h3>On screen</h3>
                        <table class="form-table" role="presentation">
                            <tr>
                                <th scope="row">Picture size</th>
                                <td>
                                    <label><input type="radio" name="show_fit" value="contain" checked> Whole artwork (black bars if needed)</label><br>
                                    <label><input type="radio" name="show_fit" value="cover"> Fill the screen (may crop)</label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Add</th>
                                <td>
                                    <label><input type="checkbox" name="show_shuffle" value="1"> Shuffle (random order, new each round)</label><br>
                                    <label><input type="checkbox" name="show_captions" value="1"> Artwork titles</label><br>
                                    <label><input type="checkbox" name="show_loop" value="1" checked> Start over at the end</label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Title screen</th>
                                <td>
                                    <label><input type="checkbox" name="show_title_screen" id="show_title_screen" value="1"> Show one first</label>
                                    <div id="title-fields" hidden>
                                        <p><input type="text" name="show_title" value="<?php echo esc_attr($artist); ?>" class="regular-text" placeholder="Title" aria-label="Title"></p>
                                        <p><input type="text" name="show_subtitle" value="" class="regular-text" placeholder="Subtitle (optional)" aria-label="Subtitle"></p>
                                        <p><input type="number" name="show_title_seconds" value="4" min="1" max="30" step="0.5" class="small-text" aria-label="Seconds"> seconds</p>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <p class="sg-actions">
                    <button type="submit" name="action" value="slideshow" class="button button-primary">Play slideshow</button>
                    <button type="button" id="generate-slideshow-link" class="button">Get slideshow link</button>
                    <span class="sg-status-line" id="slideshow-link-status"></span>
                </p>
                <?php $this->link_box('slideshow-link'); ?>
                <p class="description">In the slideshow: space pauses, arrow keys or a swipe move on, F toggles full screen. It fits phones, tablets and wide screens, upright or on their side.</p>
            </div>
        </details>
        <?php
    }

    private function render_lists_panel() {
        ?>
        <details class="lm-panel" data-panel="lists">
            <summary>
                <span class="lm-t">Lists &amp; Artwork Editor</span>
                <?php echo $this->count_chip(); ?>
                <span class="lm-sub">Keep the pick as a file, load one, or carry it over to the Artwork Editor</span>
            </summary>
            <div class="lm-body">
                <p class="sg-actions">
                    <input type="file" id="import-json" accept=".json,application/json" hidden>
                    <button type="button" id="import-json-btn" class="button">Load list</button>
                    <button type="button" id="save-list-btn" class="button">Save list</button>
                    <button type="button" id="open-in-artwork-editor" class="button" data-url="<?php echo esc_url(admin_url('admin.php?page=monospace-artwork-editor')); ?>">Open in Artwork Editor</button>
                    <span class="sg-status-line" id="import-status"></span>
                </p>
                <p class="description">A list file holds which artworks are picked, nothing else - the same files as the Artwork Editor's. Opening in the Artwork Editor picks the same artworks there, ready to edit in bulk.</p>
            </div>
        </details>
        <?php
    }

    private function render_styles() {
        ?>
        <style>
            .sg-wrap { max-width: 1180px; }
            .sg-wrap .lm-body h3 { margin: 0 0 4px; font-size: 13px; }
            .sg-cols { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 32px; }
            @media (max-width: 900px) { .sg-cols { grid-template-columns: minmax(0, 1fr); } }
            .sg-wrap .form-table th { width: 130px; padding: 10px 10px 10px 0; font-weight: 600; }
            .sg-wrap .form-table td { padding: 8px 0; }
            .sg-wrap .form-table td p { margin: 0 0 6px; }
            .sg-indent { display: inline-block; margin-left: 22px; }
            #title-fields { margin-top: 8px; }

            .sg-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px 14px; }
            .sg-field { display: flex; flex-direction: column; }
            .sg-field label { font-weight: 600; font-size: 12px; margin-bottom: 3px; }
            .sg-field select, .sg-field input { width: 100%; max-width: none; }
            .sg-bar { margin: 12px 0; display: flex; flex-wrap: wrap; gap: 6px; }

            .sg-scroll { max-height: 520px; overflow: auto; border: 1px solid #c3c4c7; border-radius: 4px; }
            .artwork-selector-table { border: 0; margin: 0; }
            .artwork-selector-table thead th { position: sticky; top: 0; z-index: 1; background: #f6f7f7; border-bottom: 1px solid #c3c4c7; white-space: nowrap; }
            .artwork-selector-table td { vertical-align: middle; }
            .artwork-selector-table tr.filtered-out { display: none; }
            .artwork-selector-table tbody tr:has(input:checked) td { background: #e7f0fa; }
            .sg-thumb { width: 58px; padding: 4px 8px; }
            .sg-thumb img, .sg-noimg { display: block; width: 46px; height: 46px; border-radius: 3px; object-fit: cover; border: 1px solid #dcdcde; }
            .sg-noimg { background: #f0f0f1; color: #8c8f94; text-align: center; line-height: 44px; }
            .sg-pick { width: 60px; text-align: center; }
            .sortable-header { cursor: pointer; user-select: none; }
            .sortable-header:hover { background: #f0f0f1; }
            .sortable-header::after { content: ' ⇅'; opacity: .3; }
            .sortable-header.sorted-asc::after { content: ' ↑'; opacity: 1; }
            .sortable-header.sorted-desc::after { content: ' ↓'; opacity: 1; }

            .sg-status { display: inline-block; font-size: 11px; padding: 1px 8px; border-radius: 10px; background: #e6f4ea; color: #1e6b32; }
            .sg-status.is-reserved { background: #fdf3c8; color: #7a5f00; }
            .sg-status.is-sold { background: #e7f0fa; color: #135e96; }
            .sg-status.is-private { background: #fcf0f1; color: #8a2424; }
            .sg-status.is-gallery { background: #f1e9f9; color: #5b2d8a; }
            .sg-status.is-qr { background: #fdeedd; color: #9a4a00; }

            .sg-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 14px 0 6px; }
            .sg-status-line { font-size: 12px; color: #646970; margin-left: 4px; }
            .sg-status-line.is-ok { color: #1e6b32; }
            .sg-status-line.is-bad { color: #b32d2e; }
            .sg-link { margin: 10px 0 6px; padding: 10px 12px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; }
            .sg-link[hidden] { display: none; }
            .sg-link .sg-link-url { width: min(420px, 100%); margin-right: 4px; }
            .sg-link .description { margin: 6px 0 0; }
            .sg-copied { margin-left: 6px; font-size: 12px; color: #1e6b32; }
        </style>
        <?php
    }

    private function render_scripts() {
        ?>
        <script>
        (function() {
            let sortColumn = 'title';
            let sortDirection = 'asc';

            const $ = (sel, root) => (root || document).querySelector(sel);
            const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));
            const rowsAll = () => $$('.artwork-selector-table tbody tr');
            const checkedBoxes = () => $$('.artwork-selector-table tbody input[type="checkbox"]:checked')
                .filter(cb => !cb.closest('tr').classList.contains('filtered-out'));

            function say(el, text, kind) {
                el.textContent = text;
                el.className = 'sg-status-line' + (kind ? ' is-' + kind : '');
            }

            function sortTable(column) {
                if (sortColumn === column) {
                    sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    sortColumn = column;
                    sortDirection = 'asc';
                }

                const body = $('.artwork-selector-table tbody');
                const rows = Array.from(body.querySelectorAll('tr'));

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

                rows.forEach(row => body.appendChild(row));

                $$('.sortable-header').forEach(th => th.classList.remove('sorted-asc', 'sorted-desc'));
                $(`[data-column="${column}"]`).classList.add(`sorted-${sortDirection}`);
            }

            function applyFilters() {
                const f = {
                    sku: $('#filter-sku').value.toLowerCase(),
                    format: $('#filter-format').value,
                    medium: $('#filter-medium').value,
                    surface: $('#filter-surface').value,
                    size: $('#filter-size').value,
                    status: $('#filter-status').value,
                    price: $('#filter-price').value,
                    dateFrom: $('#filter-date-from').value,
                    dateTo: $('#filter-date-to').value,
                };

                rowsAll().forEach(row => {
                    let show = true;
                    if (f.sku && !row.dataset.sku.includes(f.sku)) show = false;
                    if (f.format && row.dataset.format !== f.format) show = false;
                    if (f.medium && row.dataset.medium !== f.medium) show = false;
                    if (f.surface && row.dataset.surface !== f.surface) show = false;
                    if (f.size && row.dataset.size !== f.size) show = false;
                    if (f.status && row.dataset.status !== f.status) show = false;

                    if (f.price) {
                        const hasPrice = row.dataset.hasprice === 'true';
                        if (f.price === 'with_price' && !hasPrice) show = false;
                        if (f.price === 'no_price' && hasPrice) show = false;
                    }

                    if (f.dateFrom && new Date(row.dataset.date) < new Date(f.dateFrom)) show = false;
                    if (f.dateTo && new Date(row.dataset.date) > new Date(f.dateTo)) show = false;

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

            // The chips say how many are picked (they are only written to, never watched)
            function updateSelectionCount() {
                const n = checkedBoxes().length;
                const text = n ? n + ' picked' : 'None picked';
                $$('#selection-chip, .sg-count').forEach(chip => {
                    chip.textContent = text;
                    chip.classList.toggle('is-on', n > 0);
                    chip.classList.toggle('is-empty', n === 0);
                });
            }

            function setPicked(row, on) {
                row.querySelector('input[type="checkbox"]').checked = on;
                row.dataset.selected = on ? '1' : '0';
            }

            function copyText(input, note) {
                const done = () => { note.textContent = 'Copied'; setTimeout(() => { note.textContent = ''; }, 2000); };
                input.select();
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(input.value).then(done, () => { document.execCommand('copy'); done(); });
                } else {
                    document.execCommand('copy');
                    done();
                }
            }

            // Asks the server for a link; `data` is a FormData with the action, nonce, picks and settings
            function getLink(data, box, status) {
                say(status, 'Making the link...', '');
                fetch(ajaxurl, { method: 'POST', body: data })
                    .then(r => r.json())
                    .then(result => {
                        if (!result.success) { say(status, 'Error: ' + result.data, 'bad'); return; }
                        say(status, '', '');
                        box.hidden = false;
                        $('.sg-link-url', box).value = result.data.url;
                        $('.sg-expires', box).textContent = result.data.expires;
                    })
                    .catch(err => say(status, 'Error: ' + err.message, 'bad'));
            }

            document.addEventListener('DOMContentLoaded', function() {
                const form = $('#artwork-sheet-form');
                const nonce = form.querySelector('[name="artwork_sheet_nonce"]').value;

                // Price only matters while availability is shown
                const availabilityCheckbox = $('#include_availability');
                const priceRow = $('#price-row');
                const priceCheckbox = $('#include_price');

                function updatePriceRowVisibility() {
                    priceRow.style.display = availabilityCheckbox.checked ? '' : 'none';
                    if (!availabilityCheckbox.checked) priceCheckbox.checked = false;
                }
                availabilityCheckbox.addEventListener('change', updatePriceRowVisibility);
                updatePriceRowVisibility();

                // Images only: the picture, and nothing else
                $('#images_only').addEventListener('change', function() {
                    if (!this.checked) return;
                    form.querySelector('input[name="include_image"]').checked = true;
                    availabilityCheckbox.checked = false;
                    priceCheckbox.checked = false;
                    ['include_loan_section', 'include_footer', 'include_generated_date']
                        .forEach(name => { const cb = form.querySelector(`input[name="${name}"]`); if (cb) cb.checked = false; });
                    updatePriceRowVisibility();
                });

                // Slideshow title screen fields
                const titleFields = $('#title-fields');
                const titleToggle = $('#show_title_screen');
                titleToggle.addEventListener('change', () => { titleFields.hidden = !titleToggle.checked; });

                // Sort and filter
                $$('.sortable-header').forEach(th => th.addEventListener('click', () => sortTable(th.dataset.column)));
                $('#filter-sku').addEventListener('input', applyFilters);
                ['filter-format', 'filter-medium', 'filter-surface', 'filter-size', 'filter-status', 'filter-price', 'filter-date-from', 'filter-date-to']
                    .forEach(id => document.getElementById(id).addEventListener('change', applyFilters));
                $('#reset-filters').addEventListener('click', resetFilters);

                $('#select-visible').addEventListener('click', () => {
                    rowsAll().filter(tr => !tr.classList.contains('filtered-out')).forEach(tr => setPicked(tr, true));
                    updateSelectionCount();
                });
                $('#clear-selection').addEventListener('click', () => {
                    rowsAll().forEach(tr => setPicked(tr, false));
                    updateSelectionCount();
                });

                $$('.artwork-selector-table tbody input[type="checkbox"]').forEach(cb => cb.addEventListener('change', function() {
                    this.closest('tr').dataset.selected = this.checked ? '1' : '0';
                    updateSelectionCount();
                }));

                // Load list
                const importStatus = $('#import-status');
                $('#import-json-btn').addEventListener('click', () => $('#import-json').click());
                $('#import-json').addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (!file) return;

                    const reader = new FileReader();
                    reader.onload = function(event) {
                        try {
                            const data = JSON.parse(event.target.result);
                            // An artwork list (Save list here or in the Artwork Editor), or an "Export JSON" file -
                            // only the IDs are read (an export's wc_product_id / wp_post_id both hold the product ID)
                            let productIds;
                            if (data && data.type === 'monospace-artwork-list' && Array.isArray(data.artworks)) {
                                const bySku = {};
                                rowsAll().forEach(tr => { bySku[tr.dataset.sku] = tr.dataset.id; });
                                productIds = data.artworks.map(a => a.product_id || bySku[String(a.sku || '').toLowerCase()]).filter(Boolean);
                            } else if (Array.isArray(data)) {
                                productIds = data.map(item => item.wc_product_id || item.wp_post_id).filter(Boolean);
                            } else {
                                throw new Error('Not an artwork list');
                            }
                            if (productIds.length === 0) throw new Error('No artworks found in this file');

                            rowsAll().forEach(tr => setPicked(tr, false));
                            let matched = 0;
                            productIds.forEach(id => {
                                const tr = $(`.artwork-selector-table tbody tr[data-id="${id}"]`);
                                if (tr) { setPicked(tr, true); matched++; }
                            });
                            updateSelectionCount();
                            say(importStatus, `Loaded ${matched} of ${productIds.length} artworks`, 'ok');
                            setTimeout(() => say(importStatus, '', ''), 5000);
                        } catch (error) {
                            say(importStatus, 'Error: ' + error.message, 'bad');
                        }
                    };
                    reader.readAsText(file);
                    e.target.value = '';
                });

                // Save list: which artworks are picked - nothing about them (the same format as the Artwork Editor's)
                $('#save-list-btn').addEventListener('click', function() {
                    const rows = $$('.artwork-selector-table tbody input[type="checkbox"]:checked').map(cb => cb.closest('tr'));
                    if (!rows.length) { say(importStatus, 'Pick at least one artwork first.', 'bad'); return; }
                    const list = { type: 'monospace-artwork-list', version: 1, saved: new Date().toISOString(), from: 'Artwork Sheets',
                        artworks: rows.map(tr => ({ product_id: +tr.dataset.id, post_id: +tr.dataset.post || null, sku: tr.children[2].textContent.trim() })) };
                    const a = document.createElement('a');
                    a.href = URL.createObjectURL(new Blob([JSON.stringify(list, null, 1)], { type: 'application/json' }));
                    a.download = 'artwork-list-' + new Date().toISOString().slice(0, 10) + '-' + rows.length + '.json';
                    document.body.appendChild(a); a.click(); a.remove();
                    say(importStatus, 'Saved a list of ' + rows.length, 'ok');
                });

                // Artwork Editor <-> Artwork Sheets (2026-10-19): the picks travel as product IDs in the address.
                // Arriving with ?select=...: pick those (the Artwork Editor's "Open in Artwork Sheets").
                const incoming = new URLSearchParams(location.search).get('select');
                if (incoming) {
                    const ids = incoming.split(',').map(Number).filter(Boolean);
                    let found = 0;
                    rowsAll().forEach(tr => {
                        const on = ids.indexOf(+tr.dataset.id) > -1;
                        setPicked(tr, on);
                        if (on) found++;
                    });
                    updateSelectionCount();
                    say(importStatus, `${found} of ${ids.length} artworks picked from the Artwork Editor`, 'ok');
                    $('.lm-panel[data-panel="lists"]').open = true;
                    // The picked ones first: sortTable flips the direction of the column already sorted on
                    if (found) { sortColumn = 'selected'; sortDirection = 'asc'; sortTable('selected'); }
                    history.replaceState(null, '', location.pathname + '?page=monospace-artwork-sheets');
                }
                $('#open-in-artwork-editor').addEventListener('click', function() {
                    const ids = checkedBoxes().map(cb => cb.value);
                    if (!ids.length) { say(importStatus, 'Pick at least one artwork first.', 'bad'); return; }
                    location.href = this.dataset.url + '&select=' + ids.join(',');
                });

                // Submitting: only what is shown and ticked goes along (the table hides filtered rows, it doesn't untick them)
                form.addEventListener('submit', function(e) {
                    if (checkedBoxes().length === 0) {
                        e.preventDefault();
                        alert('Pick at least one artwork first.');
                        return;
                    }
                    const hidden = $$('.artwork-selector-table tbody tr.filtered-out input:checked');
                    hidden.forEach(cb => { cb.disabled = true; });
                    setTimeout(() => hidden.forEach(cb => { cb.disabled = false; }), 0);
                });

                // Links
                function picked() {
                    const boxes = checkedBoxes();
                    if (!boxes.length) { alert('Pick at least one artwork first.'); return null; }
                    return boxes;
                }

                $('#generate-public-link').addEventListener('click', function() {
                    const boxes = picked();
                    if (!boxes) return;
                    const data = new FormData();
                    data.append('action', 'generate_public_sheet_link');
                    data.append('nonce', nonce);
                    boxes.forEach(cb => data.append('product_ids[]', cb.value));
                    data.append('layout', form.querySelector('[name="layout"]:checked').value);
                    ['include_image', 'include_availability', 'include_price', 'include_loan_section', 'include_footer',
                     'include_generated_date']
                        .forEach(name => data.append(name, form.querySelector(`[name="${name}"]`).checked));
                    getLink(data, $('#sheet-link'), $('#public-link-status'));
                });

                $('#generate-slideshow-link').addEventListener('click', function() {
                    const boxes = picked();
                    if (!boxes) return;
                    const data = new FormData(form);
                    data.delete('product_ids[]');
                    data.append('action', 'generate_slideshow_link');
                    data.append('nonce', nonce);
                    boxes.forEach(cb => data.append('product_ids[]', cb.value));
                    getLink(data, $('#slideshow-link'), $('#slideshow-link-status'));
                });

                $$('.sg-link').forEach(box => {
                    const url = $('.sg-link-url', box);
                    $('.sg-copy', box).addEventListener('click', () => copyText(url, $('.sg-copied', box)));
                    $('.sg-open', box).addEventListener('click', () => window.open(url.value, '_blank', 'noopener'));
                });

                updateSelectionCount();
            });
        })();
        </script>
        <?php
    }
}

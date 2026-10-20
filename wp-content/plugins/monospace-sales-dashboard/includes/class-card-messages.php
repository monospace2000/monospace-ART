<?php
/**
 * Messages - everything the shop says to customers, on one screen
 * (WooCommerce > Settings > Sales Dashboard > Messages).
 *
 * PRICE CARD: one list per card tab (Original / Framed Print / Unframed Print) for the strip at
 * the bottom of the price card. The card shows the first enabled message in a tab's list that
 * applies to the post (post categories + dates). Each message has a style: green, red, blue, gray.
 * Two built-in rows live in the Original list and are evaluated by the theme:
 *   - qr_note: gallery-reserved paintings (text editable, {gallery} = gallery name)
 *   - volume:  the volume-discount note; its wording templates are edited in its row
 * A post can override any tab in the Quick Product box (post meta _qp_card_message).
 * The theme's monospace_card_sales_message() renders the strip.
 *
 * FINE PRINT: one text per tab, always shown under the strip (not an offer - shipping, framing,
 * returns), option msd_card_fine_print; defaults in fine_print_defaults() until saved. Shipping costs
 * are never typed in - placeholders, worked out by the theme from the WooCommerce shipping zones:
 * {shipping} = the whole sentence ("Shipping: $16 to the US, Canada and Mexico, $39 elsewhere",
 * "Free shipping worldwide"); {shipping_na} / {shipping_world} = just the cost ("$16", "free").
 *
 * CART: the free shipping notices (MSD_Settings::get_cart_message_fields()).
 */

if (!defined('ABSPATH')) exit;

class MSD_Card_Messages {

    const OPTION     = 'msd_card_messages';
    const FINE_PRINT = 'msd_card_fine_print';

    public static function init() {
        add_action('wp_head', [__CLASS__, 'print_styles']);
    }

    public static function tabs() {
        return [
            'original' => __('Original', 'monospace-sales'),
            'framed'   => __('Framed Print', 'monospace-sales'),
            'unframed' => __('Unframed Print', 'monospace-sales'),
        ];
    }

    public static function styles() {
        return [
            'green' => __('Green', 'monospace-sales'),
            'red'   => __('Red', 'monospace-sales'),
            'blue'  => __('Blue', 'monospace-sales'),
            'gray'  => __('Gray', 'monospace-sales'),
        ];
    }

    /** The four message styles - printed on the site (wp_head) and on the admin screen */
    // Colored text only - no background or border (owner's choice, 2026-10-16)
    public static function css() {
        return '.card-message{padding:0;margin:6px 0;text-align:right;font-weight:bold;font-size:14px;line-height:1.4}'
             . '.card-message-sub{display:block;margin-top:4px;font-weight:normal;font-size:12px;color:#666}'
             . '.card-message--green{color:#33aa33}'
             . '.card-message--red{color:#960808}'
             . '.card-message--blue{color:#046bd2}'
             . '.card-message--gray{color:#555}'
             . '.card-fine-print{margin:6px 0 0;font-size:12px;line-height:1.5;font-weight:normal;color:#999;text-align:right}';
    }

    public static function print_styles() {
        echo '<style id="msd-card-message-styles">' . self::css() . '</style>';
    }

    /** Volume-discount wording, edited in the volume row */
    public static function volume_templates() {
        return [
            'msd_hint_template_bundle'  => ['label' => __('Fixed bundle', 'monospace-sales'),   'default' => 'Special: Get {qty} for only ${total}!', 'help' => '{qty}, {total}'],
            'msd_hint_template_bxgy'    => ['label' => __('Buy X get Y', 'monospace-sales'),    'default' => 'Buy {buy}, get {get} free!',            'help' => '{buy}, {get}'],
            'msd_hint_template_percent' => ['label' => __('Percent off', 'monospace-sales'),    'default' => 'Save {percent}% on qualifying purchases!', 'help' => '{percent}'],
            'msd_hint_secondary_text'   => ['label' => __('Second line', 'monospace-sales'),    'default' => '(Discount applied at checkout.)',      'help' => __('small text under the offer; empty = none', 'monospace-sales')],
        ];
    }

    /** What the card showed before this screen existed */
    public static function defaults() {
        return [
            'original' => [
                self::row('qr_note', 'This item can only be purchased through the QR code link at {gallery}', 'gray'),
                self::row('volume', '', 'green'),
            ],
            'framed'   => [],
            'unframed' => [],
        ];
    }

    /** Fine print per tab, before anything is saved */
    public static function fine_print_defaults() {
        // Costs are never typed in here: {shipping} is worked out from the WooCommerce shipping zones
        $prints = '{shipping}. Printed on demand by a print facility near you.';
        return [
            'original' => "Original artworks ship unframed. {shipping}. "
                        . 'Originals are final sale.',
            'framed'   => $prints,
            'unframed' => $prints,
        ];
    }

    /** Saved fine print, tab by tab (a tab never saved keeps its default; saved empty = none) */
    public static function get_fine_print() {
        $saved = get_option(self::FINE_PRINT, []);
        $out = [];
        foreach (self::fine_print_defaults() as $tab => $default) {
            $out[$tab] = is_array($saved) && array_key_exists($tab, $saved) ? (string) $saved[$tab] : $default;
        }
        return $out;
    }

    private static function row($type, $text, $style = 'green', $enabled = true, $cats = [], $start = '', $end = '') {
        return compact('type', 'text', 'style', 'enabled', 'cats', 'start', 'end');
    }

    /** Saved lists, or the defaults when nothing has been saved yet */
    public static function get() {
        $saved = get_option(self::OPTION, null);
        return is_array($saved) ? self::sanitize($saved) : self::defaults();
    }

    /**
     * Clean up a submitted/saved structure. Built-in rows live only in the Original list,
     * exactly once each (a missing one is appended switched off). Rows saved by the first
     * version ("for": category slug, no style) are converted.
     */
    public static function sanitize($data) {
        $clean = [];
        $seen  = [];
        $date  = function ($d) { return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d) ? $d : ''; };

        foreach (array_keys(self::tabs()) as $tab) {
            $clean[$tab] = [];
            foreach ((array) ($data[$tab] ?? []) as $row) {
                $type = in_array($row['type'] ?? '', ['custom', 'qr_note', 'volume'], true) ? $row['type'] : 'custom';
                if ($type !== 'custom' && ($tab !== 'original' || isset($seen[$type]))) {
                    continue;
                }
                $seen[$type] = true;

                $text = sanitize_text_field($row['text'] ?? '');
                if ($type === 'custom' && $text === '') {
                    continue;
                }

                // Post categories (term IDs); first-version rows had 'for' => slug or 'all'
                $cats = array_values(array_filter(array_map('intval', (array) ($row['cats'] ?? []))));
                if (! isset($row['cats']) && ! empty($row['for']) && $row['for'] !== 'all') {
                    $term = get_term_by('slug', $row['for'], 'category');
                    $cats = $term ? [$term->term_id] : [];
                }

                $default_style = $type === 'qr_note' ? 'gray' : 'green';
                $style = array_key_exists($row['style'] ?? '', self::styles()) ? $row['style'] : $default_style;

                $clean[$tab][] = self::row($type, $type === 'volume' ? '' : $text, $style, ! empty($row['enabled']), $cats, $date($row['start'] ?? ''), $date($row['end'] ?? ''));
            }
        }
        foreach (self::defaults()['original'] as $builtin) {
            if (! isset($seen[$builtin['type']])) {
                $builtin['enabled'] = false;
                $clean['original'][] = $builtin;
            }
        }
        return $clean;
    }

    /** Is the post in one of the row's categories (or a subcategory of one), and within its dates? */
    public static function applies($row, $post_id) {
        if (! empty($row['cats'])) {
            $post_cats = wp_get_post_categories($post_id);
            $match = false;
            foreach ($row['cats'] as $cat) {
                foreach ($post_cats as $post_cat) {
                    if ($post_cat === $cat || term_is_ancestor_of($cat, $post_cat, 'category')) {
                        $match = true;
                        break 2;
                    }
                }
            }
            if (! $match) return false;
        }
        $today = current_time('Y-m-d');
        if ($row['start'] && $today < $row['start']) return false;
        if ($row['end'] && $today > $row['end']) return false;
        return true;
    }

    public static function save() {
        $data = json_decode(wp_unslash($_POST['msd_card_messages_json'] ?? ''), true);
        if (is_array($data)) {
            update_option(self::OPTION, self::sanitize($data));

            foreach (self::volume_templates() as $key => $t) {
                if (isset($data['volume_templates'][$key])) {
                    update_option($key, sanitize_text_field($data['volume_templates'][$key]));
                }
            }
        }
        if (isset($_POST['msd_card_fine_print']) && is_array($_POST['msd_card_fine_print'])) {
            $fine = [];
            foreach (array_keys(self::tabs()) as $tab) {
                $fine[$tab] = sanitize_textarea_field(wp_unslash($_POST['msd_card_fine_print'][$tab] ?? ''));
            }
            update_option(self::FINE_PRINT, $fine);
        }
        woocommerce_update_options(MSD_Settings::get_cart_message_fields());
    }

    /* ------------------------------------------------------------------ admin screen */

    public static function output_section() {
        wp_enqueue_script('jquery-ui-sortable');

        $lists = self::get();
        $lists['volume_templates'] = [];
        foreach (self::volume_templates() as $key => $t) {
            $lists['volume_templates'][$key] = get_option($key, $t['default']);
        }

        $cats = array_map(function ($t) {
            return ['id' => $t->term_id, 'name' => html_entity_decode($t->name), 'parent' => $t->parent];
        }, get_terms(['taxonomy' => 'category', 'hide_empty' => false, 'orderby' => 'name']));
        ?>
        <h3><?php _e('Price card', 'monospace-sales'); ?></h3>
        <p><?php _e('The strip at the bottom of the price card shows one message per tab: the first one in the list that applies. Drag to reorder. A painting can override this in its Quick Artwork Editor.', 'monospace-sales'); ?></p>

        <input type="hidden" name="msd_card_messages_json" id="msd-cm-json" value="<?php echo esc_attr(wp_json_encode($lists)); ?>">

        <div id="msd-cm">
            <?php foreach (self::tabs() as $tab => $label) : ?>
                <div class="msd-cm-tab" data-tab="<?php echo esc_attr($tab); ?>">
                    <h4><?php echo esc_html($label); ?></h4>
                    <ul class="msd-cm-list"></ul>
                    <p class="msd-cm-empty"><?php _e('(no messages)', 'monospace-sales'); ?></p>
                    <button type="button" class="button msd-cm-add">+ <?php _e('Add message', 'monospace-sales'); ?></button>
                </div>
            <?php endforeach; ?>
        </div>

        <h3><?php _e('Fine print', 'monospace-sales'); ?></h3>
        <p><?php _e('Small text under the strip, always shown (also when a painting turns its message off): shipping, framing, returns. A line break starts a new line. Shipping costs come from the WooCommerce shipping zones - don’t type them in: {shipping} becomes the whole sentence (e.g. “Shipping: $16 to the US, Canada and Mexico, $39 elsewhere” or “Free shipping worldwide”); {shipping_na} and {shipping_world} just the cost to North America / elsewhere.', 'monospace-sales'); ?></p>
        <table class="form-table" id="msd-fine-print">
            <?php foreach (self::get_fine_print() as $tab => $text) : ?>
                <tr>
                    <th scope="row"><label for="msd-fp-<?php echo esc_attr($tab); ?>"><?php echo esc_html(self::tabs()[$tab]); ?></label></th>
                    <td><textarea name="msd_card_fine_print[<?php echo esc_attr($tab); ?>]" id="msd-fp-<?php echo esc_attr($tab); ?>" rows="3" class="large-text"><?php echo esc_textarea($text); ?></textarea></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <hr style="margin:30px 0 10px">
        <?php woocommerce_admin_fields(MSD_Settings::get_cart_message_fields()); ?>

        <style>
            <?php echo self::css(); ?>
            #msd-cm { max-width: 860px; }
            .msd-cm-tab { margin: 1.2em 0 1.8em; }
            .msd-cm-tab h4 { margin: 0 0 .5em; padding-bottom: .3em; border-bottom: 1px solid #ccd0d4; }
            .msd-cm-list { margin: 0; padding: 0; list-style: none; }
            .msd-cm-row { background: #fff; border: 1px solid #ccd0d4; margin: 0 0 6px; }
            .msd-cm-line { display: flex; align-items: center; gap: 10px; padding: 8px 10px; }
            .msd-cm-handle { cursor: move; color: #999; }
            .msd-cm-swatch { width: 12px; height: 12px; border-radius: 3px; border: 1px solid; flex: none; background: currentColor !important; }
            .msd-cm-text { flex: 1; }
            .msd-cm-row.is-off .msd-cm-text { color: #999; text-decoration: line-through; }
            .msd-cm-meta { color: #666; font-size: 12px; white-space: nowrap; max-width: 260px; overflow: hidden; text-overflow: ellipsis; }
            .msd-cm-auto { color: #2271b1; font-size: 12px; font-style: italic; white-space: nowrap; }
            .msd-cm-editor { border-top: 1px solid #eee; padding: 12px 12px 12px 40px; background: #f9f9f9; }
            .msd-cm-editor .f { display: flex; gap: 10px; margin: 0 0 10px; align-items: flex-start; }
            .msd-cm-editor .f > label { flex: 0 0 90px; font-weight: 600; padding-top: 4px; }
            .msd-cm-editor .f > div { flex: 1; }
            .msd-cm-editor input[type=text] { width: 100%; max-width: 560px; }
            .msd-cm-styles label { margin-right: 14px; white-space: nowrap; }
            .msd-cm-cats { max-height: 170px; overflow: auto; background: #fff; border: 1px solid #ccd0d4; padding: 6px 10px; max-width: 540px; }
            .msd-cm-cats label { display: block; line-height: 1.9; }
            .msd-cm-preview { max-width: 560px; }
            .msd-cm-empty { color: #999; font-style: italic; margin: 0 0 8px; }
            .ui-sortable-placeholder { height: 38px; border: 1px dashed #aaa; visibility: visible !important; margin: 0 0 6px; }
        </style>

        <script>
        jQuery(function ($) {
            var CATS   = <?php echo wp_json_encode($cats); ?>;
            var STYLES = <?php echo wp_json_encode(self::styles()); ?>;
            var VT     = <?php echo wp_json_encode(self::volume_templates()); ?>;
            var data   = JSON.parse($('#msd-cm-json').val());
            var catName = {}; CATS.forEach(function (c) { catName[c.id] = c.name; });

            function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
            function who(r) {
                var names = (r.cats || []).map(function (id) { return catName[id]; }).filter(Boolean);
                return names.length ? names.join(', ') : 'All items';
            }
            function when(r) { return (!r.start && !r.end) ? 'Always' : (r.start || '…') + ' – ' + (r.end || '…'); }
            function boxHtml(style, main, sub) {
                return '<div class="card-message card-message--' + style + '">' + esc(main) +
                    (sub ? '<span class="card-message-sub">' + esc(sub) + '</span>' : '') + '</div>';
            }
            function volumeExample() {
                return (data.volume_templates.msd_hint_template_bundle || '').replace('{qty}', '3').replace('{total}', '80');
            }
            function rowHtml(r) {
                var text = r.type === 'volume' ? 'Volume discount — e.g. “' + volumeExample() + '”' : r.text;
                var meta = r.type === 'custom' ? who(r) + ' · ' + when(r) : '';
                return '<li class="msd-cm-row' + (r.enabled ? '' : ' is-off') + '">' +
                    '<div class="msd-cm-line">' +
                        '<span class="msd-cm-handle dashicons dashicons-menu" title="Drag to reorder"></span>' +
                        '<input type="checkbox" class="msd-cm-on" title="On / off"' + (r.enabled ? ' checked' : '') + '>' +
                        '<span class="msd-cm-swatch card-message--' + r.style + '" title="' + esc(STYLES[r.style]) + '"></span>' +
                        '<span class="msd-cm-text">' + esc(text) + '</span>' +
                        (meta ? '<span class="msd-cm-meta" title="' + esc(meta) + '">' + esc(meta) + '</span>' : '') +
                        (r.type !== 'custom' ? '<span class="msd-cm-auto">automatic</span>' : '') +
                        '<button type="button" class="button-link msd-cm-edit">Edit</button>' +
                        (r.type === 'custom' ? '<button type="button" class="button-link button-link-delete msd-cm-del">Delete</button>' : '') +
                    '</div></li>';
            }
            function render() {
                $('.msd-cm-tab').each(function () {
                    var tab = $(this).data('tab'), $list = $(this).find('.msd-cm-list').empty();
                    data[tab].forEach(function (r) { $list.append($(rowHtml(r)).data('row', r)); });
                    $(this).find('.msd-cm-empty').toggle(!data[tab].length);
                });
                sync();
            }
            function sync() {
                $('.msd-cm-tab').each(function () {
                    var tab = $(this).data('tab');
                    data[tab] = $(this).find('.msd-cm-row').map(function () { return $(this).data('row'); }).get();
                });
                $('#msd-cm-json').val(JSON.stringify(data));
            }
            function catTree(selected) {
                var html = '';
                (function walk(parent, depth) {
                    CATS.filter(function (c) { return c.parent === parent; }).forEach(function (c) {
                        html += '<label style="padding-left:' + (depth * 18) + 'px"><input type="checkbox" value="' + c.id + '"' +
                            (selected.indexOf(c.id) > -1 ? ' checked' : '') + '> ' + esc(c.name) + '</label>';
                        walk(c.id, depth + 1);
                    });
                })(0, 0);
                return html;
            }
            function editor($row) {
                if ($row.find('.msd-cm-editor').length) { $row.find('.msd-cm-editor').remove(); return; }
                var r = $row.data('row'), h = '';
                if (r.type === 'volume') {
                    Object.keys(VT).forEach(function (k) {
                        h += '<div class="f"><label>' + esc(VT[k].label) + '</label><div><input type="text" class="msd-cm-vt" data-key="' + k + '" value="' + esc(data.volume_templates[k]) + '">' +
                             '<p class="description">' + esc(VT[k].help) + '</p></div></div>';
                    });
                } else {
                    h += '<div class="f"><label>Message</label><div><input type="text" class="msd-cm-f-text" value="' + esc(r.text) + '">' +
                         '<p class="description">{gallery} = the painting’s gallery</p></div></div>';
                }
                h += '<div class="f"><label>Style</label><div class="msd-cm-styles">' + Object.keys(STYLES).map(function (s) {
                        return '<label><input type="radio" name="msd-cm-style" value="' + s + '"' + (s === r.style ? ' checked' : '') + '> ' +
                               '<span class="msd-cm-swatch card-message--' + s + '" style="display:inline-block;vertical-align:-1px"></span> ' + esc(STYLES[s]) + '</label>';
                     }).join('') + '</div></div>';
                if (r.type === 'custom') {
                    h += '<div class="f"><label>For</label><div><div class="msd-cm-cats">' + catTree(r.cats || []) + '</div>' +
                         '<p class="description">Post categories; a category includes its subcategories. None ticked = all items.</p></div></div>' +
                         '<div class="f"><label>Dates</label><div><input type="date" class="msd-cm-f-start" value="' + esc(r.start) + '"> – ' +
                         '<input type="date" class="msd-cm-f-end" value="' + esc(r.end) + '"><p class="description">Leave empty for always</p></div></div>';
                }
                h += '<div class="f"><label>Preview</label><div class="msd-cm-preview"></div></div>' +
                     '<p style="margin:0"><button type="button" class="button button-primary msd-cm-done">Done</button></p>';

                var $ed = $('<div class="msd-cm-editor">' + h + '</div>');
                function preview() {
                    var style = $ed.find('[name=msd-cm-style]:checked').val(), main, sub = '';
                    if (r.type === 'volume') {
                        main = ($ed.find('[data-key=msd_hint_template_bundle]').val() || '').replace('{qty}', '3').replace('{total}', '80');
                        sub  = $ed.find('[data-key=msd_hint_secondary_text]').val();
                    } else {
                        main = ($ed.find('.msd-cm-f-text').val() || '').replace('{gallery}', 'Fork Hill Kitchen');
                    }
                    $ed.find('.msd-cm-preview').html(boxHtml(style, main, sub));
                }
                $ed.on('input change', 'input', preview);
                $row.append($ed); preview();
                $ed.find('input[type=text]').first().focus();
            }

            $('#msd-cm')
                .on('change', '.msd-cm-on', function () {
                    var $row = $(this).closest('.msd-cm-row');
                    $row.data('row').enabled = this.checked;
                    $row.toggleClass('is-off', !this.checked);
                    sync();
                })
                .on('click', '.msd-cm-edit', function () { editor($(this).closest('.msd-cm-row')); })
                .on('click', '.msd-cm-done', function () {
                    var $row = $(this).closest('.msd-cm-row'), r = $row.data('row'), $ed = $row.find('.msd-cm-editor');
                    r.style = $ed.find('[name=msd-cm-style]:checked').val() || r.style;
                    if (r.type === 'volume') {
                        $ed.find('.msd-cm-vt').each(function () { data.volume_templates[$(this).data('key')] = $.trim(this.value); });
                    } else {
                        r.text = $.trim($ed.find('.msd-cm-f-text').val());
                    }
                    if (r.type === 'custom') {
                        r.cats  = $ed.find('.msd-cm-cats input:checked').map(function () { return +this.value; }).get();
                        r.start = $ed.find('.msd-cm-f-start').val();
                        r.end   = $ed.find('.msd-cm-f-end').val();
                        if (!r.text) { $row.remove(); sync(); }
                    }
                    render();
                })
                .on('click', '.msd-cm-del', function () {
                    if (confirm('Delete this message?')) { $(this).closest('.msd-cm-row').remove(); sync(); render(); }
                })
                .on('click', '.msd-cm-add', function () {
                    var $tab = $(this).closest('.msd-cm-tab');
                    data[$tab.data('tab')].push({ type: 'custom', text: '', style: 'green', enabled: true, cats: [], start: '', end: '' });
                    render();
                    editor($tab.find('.msd-cm-row').last());
                });

            $('.msd-cm-list').sortable({ handle: '.msd-cm-handle', placeholder: 'ui-sortable-placeholder', update: sync });
            render();
        });
        </script>
        <?php
    }
}

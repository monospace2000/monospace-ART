<?php
/**
 * Campaigns - any number of price campaigns at the same time (2026-11-08), each with its own percentage, schedule, products
 * and "show original price" choice. Replaces the single Price Adjustments + Scheduling settings. Where several campaigns
 * reach the same product, the lowest price wins ("best for customer"). A sale price stored on a product is separate: it
 * always counts (see class-sale-control.php).
 *
 * Stored in the option msd_campaigns. While that option does not exist, the old single adjustment (msd_price_adj_*,
 * msd_schedule_*, msd_recurring_*) is read as one campaign, so nothing changes until the first save on the Campaigns screen.
 */

if (!defined('ABSPATH')) exit;

class MSD_Campaigns {

    const OPTION = 'msd_campaigns';

    private static $all = null;

    public static function init() {
        add_action('admin_init', [__CLASS__, 'maybe_save']);
        add_action('msd_price_schedule_flip', [__CLASS__, 'flip']);
        add_action('init', [__CLASS__, 'plan_flips'], 30);
    }

    /* ------------------------------------------------------------------ data */

    public static function defaults() {
        return [
            'id' => '', 'name' => '', 'enabled' => 'yes', 'percentage' => -10,
            'start' => '', 'end' => '',
            'pattern' => '', 'weekday' => '1', 'monthday' => '1', 'week' => 'first', 'start_time' => '00:00', 'end_time' => '23:59',
            'cats' => [], 'tags' => [], 'excl_cats' => [], 'excl_tags' => [],
            'show_original' => '',
        ];
    }

    /** All campaigns, in saved order */
    public static function all() {
        if (self::$all !== null) return self::$all;
        $saved = get_option(self::OPTION, false);
        if ($saved === false) {
            $saved = [];
            if (get_option('msd_price_adj_percentage', '') !== '') $saved[] = self::from_legacy();
        }
        self::$all = [];
        foreach ((array) $saved as $c) {
            if (is_array($c) && !empty($c['id'])) self::$all[] = array_merge(self::defaults(), $c);
        }
        return self::$all;
    }

    /** The old single adjustment as one campaign */
    private static function from_legacy() {
        $c = self::defaults();
        $c['id'] = 'legacy';
        $c['name'] = 'Price adjustment';
        $c['enabled'] = get_option('msd_price_adj_enable') === 'yes' ? 'yes' : 'no';
        $c['percentage'] = (float) get_option('msd_price_adj_percentage', 25);
        $c['cats'] = (array) get_option('msd_price_adj_categories', []);
        $c['tags'] = (array) get_option('msd_price_adj_tags', []);
        $c['excl_cats'] = (array) get_option('msd_price_adj_exclude_cats', []);
        $c['excl_tags'] = (array) get_option('msd_price_adj_exclude_tags', []);
        if (get_option('msd_schedule_enable') === 'yes') {
            $c['start'] = trim((string) get_option('msd_schedule_start', ''));
            $c['end'] = trim((string) get_option('msd_schedule_end', ''));
        }
        if (get_option('msd_recurring_enable') === 'yes') {
            $c['pattern'] = (string) get_option('msd_recurring_pattern', 'weekends');
            $c['weekday'] = (string) get_option('msd_recurring_weekday', '1');
            $c['monthday'] = (string) get_option('msd_recurring_monthday', '1');
            $c['week'] = (string) get_option('msd_recurring_week', 'first');
            $c['start_time'] = (string) get_option('msd_recurring_start_time', '00:00');
            $c['end_time'] = (string) get_option('msd_recurring_end_time', '23:59');
        }
        $c['show_original'] = '';
        return $c;
    }

    /* -------------------------------------------------------------- schedule */

    /** Is this campaign on right now (switched on, inside its dates, on its recurring days)? */
    public static function is_running($c) {
        if ($c['enabled'] !== 'yes') return false;
        $now = current_time('timestamp');
        if ($c['start'] !== '') {
            $t = strtotime($c['start']);
            if ($t && $now < $t) return false;
        }
        if ($c['end'] !== '') {
            $t = strtotime($c['end']);
            if ($t && $now > $t) return false;
        }
        return $c['pattern'] === '' || self::on_recurring_day($c, $now);
    }

    private static function on_recurring_day($c, $now) {
        $time = date('H:i', $now);
        if ($time < $c['start_time'] || $time > $c['end_time']) return false;
        $dow = (int) date('w', $now);
        switch ($c['pattern']) {
            case 'daily':       return true;
            case 'weekends':    return in_array($dow, [0, 6], true);
            case 'weekdays':    return $dow >= 1 && $dow <= 5;
            case 'weekly':      return $dow === (int) $c['weekday'];
            case 'monthly_date': return (int) date('j', $now) === (int) $c['monthday'];
            case 'monthly_day':
                if ($dow !== (int) $c['weekday']) return false;
                if ($c['week'] === 'last') return date('n', strtotime('+7 days', $now)) !== date('n', $now);
                $nth = (int) ceil((int) date('j', $now) / 7);
                return $nth === (['first' => 1, 'second' => 2, 'third' => 3, 'fourth' => 4][$c['week']] ?? 0);
        }
        return false;
    }

    /** The campaigns running now */
    public static function running() {
        return array_values(array_filter(self::all(), [__CLASS__, 'is_running']));
    }

    /** Changes whenever the set of running campaigns (or their percentages) changes - part of WooCommerce's variable price cache key */
    public static function signature() {
        $parts = [];
        foreach (self::running() as $c) $parts[] = $c['id'] . ':' . $c['percentage'];
        return $parts ? md5(implode('|', $parts)) : 0;
    }

    /* -------------------------------------------------------------- products */

    /** Does the campaign reach this product (post ID; a variation uses its product's)? */
    public static function applies($c, $product_id) {
        if (!empty($c['excl_cats']) && has_term($c['excl_cats'], 'product_cat', $product_id)) return false;
        if (!empty($c['excl_tags']) && has_term($c['excl_tags'], 'product_tag', $product_id)) return false;
        if (empty($c['cats']) && empty($c['tags'])) return true;
        return (!empty($c['cats']) && has_term($c['cats'], 'product_cat', $product_id))
            || (!empty($c['tags']) && has_term($c['tags'], 'product_tag', $product_id));
    }

    /** The running campaign that gives this price the lowest result, with that price: [price, campaign] - or [$price, null] */
    public static function best($price, $product_id, $apply) {
        $best = [$price, null];
        foreach (self::running() as $c) {
            if (!self::applies($c, $product_id)) continue;
            $p = $apply($price, (float) $c['percentage']);
            if ($best[1] === null || $p < $best[0]) $best = [$p, $c];
        }
        return $best;
    }

    /* ------------------------------------------------------------ cache flips */

    /** One cron event at each dated campaign's start and end (site time), once each */
    public static function plan_flips() {
        foreach (self::all() as $c) {
            if ($c['enabled'] !== 'yes') continue;
            foreach (['start', 'end'] as $k) {
                $date = $c[$k] !== '' ? date_create($c[$k], wp_timezone()) : false;
                if (!$date) continue;
                $ts = $date->getTimestamp();
                if ($ts > time() && !wp_next_scheduled('msd_price_schedule_flip', [$ts])) {
                    wp_schedule_single_event($ts + 5, 'msd_price_schedule_flip', [$ts]);
                }
            }
        }
    }

    /** A campaign just started or ended: show the right prices on cached pages */
    public static function flip() {
        if (function_exists('wc_delete_product_transients')) {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wc\_var\_prices\_%' OR option_name LIKE '\_transient\_timeout\_wc\_var\_prices\_%'");
        }
        if (function_exists('sg_cachepress_purge_cache')) sg_cachepress_purge_cache();
        wp_cache_flush();
    }

    /* ------------------------------------------------------------------ save */

    public static function maybe_save() {
        if (!isset($_POST['msd_campaigns_action']) || $_POST['msd_campaigns_action'] !== 'save') return;
        if (!current_user_can('manage_woocommerce') || !check_admin_referer('msd_campaigns', 'msd_campaigns_nonce')) return;

        $clean = [];
        foreach ((array) ($_POST['msd_c'] ?? []) as $row) {
            if (!is_array($row) || !empty($row['delete'])) continue;
            $row = wp_unslash($row);
            $c = self::defaults();
            $c['id'] = preg_match('/^[a-z0-9]{3,12}$/', (string) ($row['id'] ?? '')) ? $row['id'] : 'c' . wp_generate_password(6, false, false);
            $c['name'] = sanitize_text_field($row['name'] ?? '') ?: 'Campaign';
            $c['enabled'] = !empty($row['enabled']) ? 'yes' : 'no';
            $c['percentage'] = is_numeric($row['percentage'] ?? '') ? (float) $row['percentage'] : 0;
            foreach (['start', 'end'] as $k) {
                $v = str_replace('T', ' ', trim((string) ($row[$k] ?? '')));
                $c[$k] = preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2})?$/', $v) ? $v : '';
            }
            $c['pattern'] = in_array($row['pattern'] ?? '', ['daily', 'weekends', 'weekdays', 'weekly', 'monthly_date', 'monthly_day'], true) ? $row['pattern'] : '';
            $c['weekday'] = (string) min(6, max(0, (int) ($row['weekday'] ?? 1)));
            $c['monthday'] = (string) min(31, max(1, (int) ($row['monthday'] ?? 1)));
            $c['week'] = in_array($row['week'] ?? '', ['first', 'second', 'third', 'fourth', 'last'], true) ? $row['week'] : 'first';
            foreach (['start_time' => '00:00', 'end_time' => '23:59'] as $k => $def) {
                $c[$k] = preg_match('/^\d{2}:\d{2}$/', (string) ($row[$k] ?? '')) ? $row[$k] : $def;
            }
            $c['cats'] = array_values(array_filter(array_map('intval', (array) ($row['cats'] ?? []))));
            $c['excl_cats'] = array_values(array_filter(array_map('intval', (array) ($row['excl_cats'] ?? []))));
            $c['tags'] = array_values(array_filter(array_map('sanitize_title', (array) ($row['tags'] ?? []))));
            $c['excl_tags'] = array_values(array_filter(array_map('sanitize_title', (array) ($row['excl_tags'] ?? []))));
            $c['show_original'] = in_array($row['show_original'] ?? '', ['yes', 'no'], true) ? $row['show_original'] : '';
            $clean[] = $c;
        }
        update_option(self::OPTION, $clean, false);
        self::$all = null;
        self::flip();
        wp_safe_redirect(add_query_arg(['page' => MSD_Settings::PAGE, 'section' => 'campaigns', 'saved' => '1'], admin_url('admin.php')));
        exit;
    }

    /* ---------------------------------------------------------------- screen */

    /** "−13% · Oct 30 – Nov 1 · weekends" */
    public static function summary($c) {
        $out = [($c['percentage'] > 0 ? '+' : '') . rtrim(rtrim(number_format((float) $c['percentage'], 2, '.', ''), '0'), '.') . '%'];
        $fmt = fn($s) => date(strlen($s) > 10 ? 'M j, g:ia' : 'M j', strtotime($s));
        if ($c['start'] !== '' || $c['end'] !== '') {
            $out[] = ($c['start'] !== '' ? $fmt($c['start']) : 'now') . ' – ' . ($c['end'] !== '' ? $fmt($c['end']) : 'no end');
        }
        if ($c['pattern'] !== '') $out[] = self::patterns()[$c['pattern']];
        if ($c['start'] === '' && $c['end'] === '' && $c['pattern'] === '') $out[] = 'always';
        $n = count($c['cats']) + count($c['tags']);
        $out[] = $n ? $n . ' categor' . ($n === 1 ? 'y/tag' : 'ies/tags') : 'all products';
        return implode(' · ', $out);
    }

    private static function patterns() {
        return ['daily' => 'daily', 'weekends' => 'weekends', 'weekdays' => 'weekdays', 'weekly' => 'weekly', 'monthly_date' => 'monthly (date)', 'monthly_day' => 'monthly (weekday)'];
    }

    /** One campaign's edit form (inside the list); $i is its row key */
    private static function row($c, $i, $cats, $tags, $open) {
        $n = 'msd_c[' . $i . ']';
        $running = self::is_running($c);
        $chip = $c['enabled'] !== 'yes' ? ['Off', '#646970'] : ($running ? ['Running', '#00a32a'] : ['Waiting', '#dba617']);
        $sel = function ($name, $options, $chosen, $multi = false) use ($n) {
            $h = '<select name="' . $n . '[' . $name . ']' . ($multi ? '[]' : '') . '"' . ($multi ? ' multiple class="wc-enhanced-select" style="width:100%"' : '') . '>';
            foreach ($options as $v => $l) $h .= '<option value="' . esc_attr($v) . '"' . selected($multi ? in_array((string) $v, array_map('strval', (array) $chosen), true) : (string) $chosen === (string) $v, true, false) . '>' . esc_html($l) . '</option>';
            return $h . '</select>';
        };
        $dt = fn($v) => esc_attr(str_replace(' ', 'T', $v));
        ?>
        <li class="msd-camp" data-i="<?php echo esc_attr($i); ?>">
            <div class="msd-camp-head">
                <strong class="msd-camp-name"><?php echo esc_html($c['name'] ?: 'New campaign'); ?></strong>
                <span class="msd-camp-chip" style="background:<?php echo esc_attr($chip[1]); ?>"><?php echo esc_html($chip[0]); ?></span>
                <span class="msd-camp-sum"><?php echo esc_html(self::summary($c)); ?></span>
                <a href="#" class="msd-camp-edit"><?php _e('Edit', 'monospace-sales'); ?></a>
            </div>
            <div class="msd-camp-body"<?php echo $open ? '' : ' hidden'; ?>>
                <input type="hidden" name="<?php echo $n; ?>[id]" value="<?php echo esc_attr($c['id']); ?>">
                <table class="form-table">
                    <tr><th><?php _e('Name', 'monospace-sales'); ?></th><td><input type="text" class="regular-text msd-camp-name-in" name="<?php echo $n; ?>[name]" value="<?php echo esc_attr($c['name']); ?>"></td></tr>
                    <tr><th><?php _e('Switched on', 'monospace-sales'); ?></th><td><label><input type="checkbox" name="<?php echo $n; ?>[enabled]" value="1" <?php checked($c['enabled'], 'yes'); ?>> <?php _e('Prices follow this campaign while it is running', 'monospace-sales'); ?></label></td></tr>
                    <tr><th><?php _e('Percentage', 'monospace-sales'); ?></th><td><input type="number" step="0.01" name="<?php echo $n; ?>[percentage]" value="<?php echo esc_attr($c['percentage']); ?>" style="width:90px"> %
                        <p class="description"><?php _e('Negative takes that much off (-13 = 13% off), positive adds.', 'monospace-sales'); ?></p></td></tr>
                    <tr><th><?php _e('From', 'monospace-sales'); ?></th><td><input type="datetime-local" name="<?php echo $n; ?>[start]" value="<?php echo $dt($c['start']); ?>"> <?php _e('until', 'monospace-sales'); ?>
                        <input type="datetime-local" name="<?php echo $n; ?>[end]" value="<?php echo $dt($c['end']); ?>">
                        <p class="description"><?php _e('Leave empty for no start or no end.', 'monospace-sales'); ?></p></td></tr>
                    <tr><th><?php _e('Repeats', 'monospace-sales'); ?></th><td>
                        <?php echo $sel('pattern', ['' => 'Not recurring'] + self::patterns(), $c['pattern']); ?>
                        <?php echo $sel('weekday', ['1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday', '0' => 'Sunday'], $c['weekday']); ?>
                        <?php echo $sel('week', ['first' => 'First', 'second' => 'Second', 'third' => 'Third', 'fourth' => 'Fourth', 'last' => 'Last'], $c['week']); ?>
                        <input type="number" min="1" max="31" name="<?php echo $n; ?>[monthday]" value="<?php echo esc_attr($c['monthday']); ?>" style="width:60px" title="Day of month">
                        <br><?php _e('between', 'monospace-sales'); ?> <input type="time" name="<?php echo $n; ?>[start_time]" value="<?php echo esc_attr($c['start_time']); ?>"> <?php _e('and', 'monospace-sales'); ?> <input type="time" name="<?php echo $n; ?>[end_time]" value="<?php echo esc_attr($c['end_time']); ?>">
                        <p class="description"><?php _e('Weekday and week apply to Weekly and Monthly (weekday), day of month to Monthly (date). Together with From/until, both must fit.', 'monospace-sales'); ?></p></td></tr>
                    <tr><th><?php _e('Only these categories', 'monospace-sales'); ?></th><td><?php echo $sel('cats', $cats, $c['cats'], true); ?></td></tr>
                    <tr><th><?php _e('Only these tags', 'monospace-sales'); ?></th><td><?php echo $sel('tags', $tags, $c['tags'], true); ?><p class="description"><?php _e('Nothing chosen = all products.', 'monospace-sales'); ?></p></td></tr>
                    <tr><th><?php _e('Never these categories', 'monospace-sales'); ?></th><td><?php echo $sel('excl_cats', $cats, $c['excl_cats'], true); ?></td></tr>
                    <tr><th><?php _e('Never these tags', 'monospace-sales'); ?></th><td><?php echo $sel('excl_tags', $tags, $c['excl_tags'], true); ?></td></tr>
                    <tr><th><?php _e('Show original price', 'monospace-sales'); ?></th><td><?php echo $sel('show_original', ['' => 'Follow the global setting', 'yes' => 'Yes, crossed out', 'no' => 'No'], $c['show_original']); ?></td></tr>
                    <tr><th><?php _e('Remove', 'monospace-sales'); ?></th><td><label><input type="checkbox" name="<?php echo $n; ?>[delete]" value="1"> <?php _e('Delete this campaign when saving', 'monospace-sales'); ?></label></td></tr>
                </table>
            </div>
        </li>
        <?php
    }

    public static function output_section() {
        $GLOBALS['hide_save_button'] = true; // this screen has its own Save
        $terms = fn($tax, $key) => wp_list_pluck(get_terms(['taxonomy' => $tax, 'hide_empty' => false]) ?: [], 'name', $key);
        $cats = $terms('product_cat', 'term_id');
        $tags = $terms('product_tag', 'slug');
        $rows = self::all();
        $uses_legacy = get_option(self::OPTION, false) === false && $rows;

        if (isset($_GET['saved'])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Campaigns saved.', 'monospace-sales') . '</p></div>';
        ?>
        <style>
            .msd-camps { margin: 12px 0; max-width: 980px; }
            .msd-camp { background: #fff; border: 1px solid #c3c4c7; margin: 0 0 -1px; padding: 0; }
            .msd-camp-head { display: flex; gap: 12px; align-items: center; padding: 10px 14px; flex-wrap: wrap; }
            .msd-camp-chip { color: #fff; border-radius: 3px; padding: 1px 8px; font-size: 12px; }
            .msd-camp-sum { color: #646970; font-family: monospace; flex: 1; }
            .msd-camp-body { padding: 0 14px 10px; border-top: 1px solid #f0f0f1; }
            .msd-camp-body .form-table th { width: 190px; }
        </style>
        <h2><?php _e('Campaigns', 'monospace-sales'); ?></h2>
        <p><?php _e('Run as many price campaigns at once as you like, each with its own percentage, dates and products. Where two campaigns reach the same product, the lower price wins. A sale price you type on a product always shows, crossed out, whatever the campaigns do.', 'monospace-sales'); ?></p>
        <?php if (get_option('msd_global_enable') !== 'yes'): ?>
            <div class="notice notice-warning inline"><p><?php _e('The sales system is switched off (Global Controls), so no campaign changes any price.', 'monospace-sales'); ?></p></div>
        <?php endif; ?>
        <?php if ($uses_legacy): ?>
            <div class="notice notice-info inline"><p><?php _e('This is your earlier single price adjustment, shown as a campaign. It stays as it is until you press Save campaigns.', 'monospace-sales'); ?></p></div>
        <?php endif; ?>
        <form method="post" id="msd-campaigns-form">
            <input type="hidden" name="msd_campaigns_action" value="save">
            <?php wp_nonce_field('msd_campaigns', 'msd_campaigns_nonce'); ?>
            <ul class="msd-camps" id="msd-camps">
                <?php foreach ($rows as $i => $c) self::row($c, $i, $cats, $tags, false); ?>
            </ul>
            <template id="msd-camp-tpl"><?php self::row(array_merge(self::defaults(), ['id' => 'new', 'name' => '']), '__I__', $cats, $tags, true); ?></template>
            <p>
                <button type="button" class="button" id="msd-camp-add"><?php _e('Add campaign', 'monospace-sales'); ?></button>
                <button type="submit" class="button button-primary"><?php _e('Save campaigns', 'monospace-sales'); ?></button>
            </p>
        </form>
        <script>
        jQuery(function ($) {
            var next = <?php echo (int) count($rows) + 1000; ?>;
            $('#msd-camps').on('click', '.msd-camp-edit', function (e) {
                e.preventDefault();
                $(this).closest('.msd-camp').find('.msd-camp-body').prop('hidden', function (_, h) { return !h; });
            }).on('input', '.msd-camp-name-in', function () {
                $(this).closest('.msd-camp').find('.msd-camp-name').text(this.value || 'New campaign');
            });
            $('#msd-camp-add').on('click', function () {
                var html = $('#msd-camp-tpl').html().replace(/__I__/g, next++);
                var $li = $(html).appendTo('#msd-camps');
                $li.find('input[name$="[id]"]').val('');
                $(document.body).trigger('wc-enhanced-select-init');
                $li.find('.msd-camp-name-in').trigger('focus');
            });
        });
        </script>
        <?php
    }
}

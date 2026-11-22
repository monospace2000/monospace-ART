<?php
/**
 * Commissions: WooCommerce -> Price Estimator. A calculator for the quick estimate a customer asks for ("how much for
 * a 28x36 acrylic?"): medium + size in, a suggested price out. The formula (base + rate per square inch, times a factor
 * per medium, a phased-in surcharge for large pieces) is editable in a panel; the sums run in the browser, nothing is
 * written. NOT for portraits - those are always quoted individually (KB note commission-price-formula.md).
 *
 * @package monospace-commissions
 */

if (!defined('ABSPATH')) exit;

const MS_ESTIMATOR_OPTION = 'ms_commission_estimator';

add_action('admin_menu', function() {
    add_submenu_page('woocommerce', 'Price Estimator', 'Price Estimator', 'manage_woocommerce', 'ms-price-estimator', 'ms_estimator_page');
});

/**
 * The media the formula knows, key => label, in the order they are listed in the settings
 */
function ms_estimator_media($s = null) {
    $s = $s ?: ms_estimator_settings();
    $out = [];
    foreach ($s['media'] as $m) $out[$m['key']] = $m['label'];
    return $out;
}

/**
 * Fitted 2026-11-02 on the five order options: acrylic 4x4 $80 and 12x9 $280 give the line, the other media a factor
 */
function ms_estimator_defaults() {
    return [
        'base'       => 45,
        'rate'       => 2.15,
        // The media, in order: factor on the formula, the largest size offered ("12x16"; empty = no limit), and whether the
        // public table ([commission_price_guide]) shows it - a medium can be calculator-only
        'media'      => [
            ['key' => 'pen-ink',   'label' => 'Pen & Ink',   'factor' => 0.65, 'max' => '12x16', 'public' => 1],
            ['key' => 'line-wash', 'label' => 'Line & Wash', 'factor' => 0.8,  'max' => '12x16', 'public' => 1],
            ['key' => 'gouache',   'label' => 'Gouache',     'factor' => 1.1,  'max' => '12x16', 'public' => 1],
            ['key' => 'acrylic',   'label' => 'Acrylic',     'factor' => 1.0,  'max' => '',      'public' => 1],
        ],
        'large_from' => 320,   // square inches (20x16); the surcharge phases in from here to twice this
        'pct_low'    => 10,
        'pct_high'   => 15,
        'round_to'   => 5,
        'table_sizes' => '5x7,8x10,9x12,11x14,12x16,16x20,18x24',   // the public table's rows (inches)
    ];
}

function ms_estimator_settings() {
    $d = ms_estimator_defaults();
    $saved = (array) get_option(MS_ESTIMATOR_OPTION, []);

    // The first version kept factors and largest sizes in two arrays ("Casein / gouache" became "Gouache"): carry them over
    if (empty($saved['media'])) {
        $f = (array) ($saved['factors'] ?? []);
        if (isset($f['casein'])) {
            if (!isset($f['gouache'])) $f['gouache'] = $f['casein'];
            unset($f['casein']);
        }
        $mx = (array) ($saved['max'] ?? []);
        $list = $d['media'];
        foreach ($list as &$m) {
            if (isset($f[$m['key']])) $m['factor'] = (float) $f[$m['key']];
            if (isset($mx[$m['key']])) $m['max'] = (string) $mx[$m['key']];
        }
        unset($m);
        $saved['media'] = $list;
        unset($saved['factors'], $saved['max']);
    }

    $s = wp_parse_args($saved, $d);

    // Looked up by key: the factor, the largest size, and the keys the public table shows
    $s['factors'] = [];
    $s['max'] = [];
    $s['public'] = [];
    foreach ($s['media'] as $m) {
        $s['factors'][$m['key']] = (float) $m['factor'];
        $s['max'][$m['key']] = (string) $m['max'];
        if (!empty($m['public'])) $s['public'][] = $m['key'];
    }
    return $s;
}

add_action('admin_post_ms_estimator_save', function() {
    check_admin_referer('ms_estimator_save');
    if (!current_user_can('manage_woocommerce')) wp_die('Insufficient permissions.');

    $d = ms_estimator_defaults();
    $num = function($key, $min, $max, $fallback) {
        $v = isset($_POST[$key]) ? (float) wp_unslash($_POST[$key]) : $fallback;
        return max($min, min($max, $v));
    };

    $media = [];
    $seen = [];
    foreach ((array) ($_POST['media'] ?? []) as $row) {
        $row = (array) $row;
        $label = trim(sanitize_text_field(wp_unslash($row['label'] ?? '')));
        if ($label === '') continue;   // an empty name removes the row

        $key = sanitize_key(wp_unslash($row['key'] ?? ''));   // an existing medium keeps its key; a new one gets it from its name
        if ($key === '') $key = sanitize_title($label);
        if ($key === '') $key = 'medium';
        $base = $key;
        for ($n = 2; isset($seen[$key]); $n++) $key = $base . '-' . $n;
        $seen[$key] = true;

        $size = strtolower(trim((string) wp_unslash($row['max'] ?? '')));
        $size = preg_replace('/\s*[x×]\s*/u', 'x', $size);
        $factor = (float) wp_unslash($row['factor'] ?? 1);

        $media[] = [
            'key'    => $key,
            'label'  => $label,
            'factor' => max(0.1, min(5, $factor ?: 1)),
            'max'    => preg_match('/^\d+(?:\.\d+)?x\d+(?:\.\d+)?$/', $size) ? $size : '',
            'public' => empty($row['public']) ? 0 : 1,
        ];
    }
    if (!$media) $media = $d['media'];

    $sizes = [];
    foreach (explode(',', (string) wp_unslash($_POST['table_sizes'] ?? '')) as $size) {
        $size = preg_replace('/\s*[x×]\s*/u', 'x', strtolower(trim($size)));
        if (preg_match('/^\d+(?:\.\d+)?x\d+(?:\.\d+)?$/', $size) && !in_array($size, $sizes, true)) $sizes[] = $size;
    }
    $table_sizes = $sizes ? implode(',', array_slice($sizes, 0, 30)) : $d['table_sizes'];

    $low = $num('pct_low', 0, 100, $d['pct_low']);
    $high = $num('pct_high', 0, 100, $d['pct_high']);
    if ($low > $high) [$low, $high] = [$high, $low];

    $round = (int) ($_POST['round_to'] ?? $d['round_to']);
    if (!in_array($round, [1, 5, 10], true)) $round = $d['round_to'];

    update_option(MS_ESTIMATOR_OPTION, [
        'base'       => $num('base', 0, 10000, $d['base']),
        'rate'       => $num('rate', 0, 100, $d['rate']),
        'media'      => $media,
        'table_sizes' => $table_sizes,
        'large_from' => $num('large_from', 1, 100000, $d['large_from']),
        'pct_low'    => $low,
        'pct_high'   => $high,
        'round_to'   => $round,
    ], false);

    // The Places page shows these prices ([commission_price_guide]) from the page cache - clear it so the table follows
    if (function_exists('sg_cachepress_purge_cache')) sg_cachepress_purge_cache();

    wp_safe_redirect(add_query_arg(['page' => 'ms-price-estimator', 'saved' => 1], admin_url('admin.php')));
    exit;
});

/**
 * The order options (custom-order products) with their size, for the "Your current options" panel
 */
function ms_estimator_options() {
    if (!function_exists('wc_get_products')) return [];
    $out = [];
    foreach (wc_get_products(['category' => ['custom-order'], 'limit' => -1, 'status' => 'publish']) as $p) {
        $sizes = get_post_meta($p->get_id(), '_commission_allowed_sizes', true);
        $sizes = is_array($sizes) ? $sizes : [];
        $size = reset($sizes);
        if (!$size || !preg_match('/^(\d+(?:\.\d+)?)x(\d+(?:\.\d+)?)$/i', (string) $size, $m)) continue;

        $name = strtolower($p->get_name());
        $key = '';
        if (strpos($name, 'miniature') !== false || strpos($name, 'acrylic') !== false) $key = 'acrylic';
        elseif (strpos($name, 'gouache') !== false || strpos($name, 'casein') !== false) $key = 'gouache';
        elseif (strpos($name, 'line') !== false) $key = 'line-wash';
        elseif (strpos($name, 'pen') !== false) $key = 'pen-ink';

        $out[] = ['name' => preg_replace('/^Custom Order:\s*/i', '', $p->get_name()), 'w' => (float) $m[1], 'h' => (float) $m[2], 'price' => (float) $p->get_price(), 'key' => $key];
    }
    usort($out, function($a, $b) { return ($a['w'] * $a['h']) <=> ($b['w'] * $b['h']); });
    return $out;
}

/**
 * Common ready-made art surface sizes in inches (US stock: canvas panels, stretched canvas, boards, paper), grouped
 * for the size drop-down. Orientation doesn't change the price, so each is listed once.
 */
function ms_estimator_sizes() {
    return [
        'Small & miniature' => ['4x4', '5x5', '5x7', '6x6', '6x8', '8x8'],
        'Standard'          => ['8x10', '9x12', '10x10', '11x14', '12x12', '12x16', '14x18', '16x20', '18x24'],
        'Large'             => ['20x24', '24x30', '24x36', '30x40', '36x48'],
        'Square'            => ['16x16', '20x20', '24x24', '30x30', '36x36'],
        'Paper sheets'      => ['9x12', '11x15', '12x18', '14x17', '18x24', '19x25', '22x30'],
    ];
}

/**
 * The metric list (2026-11-15, owner): real standard sizes from the art supply store (European canvas and panel sizes,
 * watercolor paper, and the ISO A sizes A6-A1), NOT the inch sizes converted. Each one shows the closest inch size when
 * there is one. The estimate converts the real area (cm / 2.54) and uses the same formula.
 */
function ms_estimator_sizes_cm() {
    return [
        'Small & miniature'        => ['10x10', '10x15', '13x18', '15x15', '15x20', '18x24', '20x20'],
        'Canvas & panels'          => ['20x25', '20x30', '24x30', '25x25', '30x30', '30x40', '40x40', '40x50', '40x60', '50x50', '50x60', '50x70', '60x60'],
        'Large canvas'             => ['60x80', '60x90', '70x100', '80x80', '80x100', '90x120', '100x100', '100x120', '100x150'],
        'Paper sheets'             => ['24x32', '32x41', '36x51', '50x65', '56x76'],
        'A sizes (ISO paper)'      => ['10.5x14.8', '14.8x21', '21x29.7', '29.7x42', '42x59.4', '59.4x84.1'],
    ];
}

/** The A size names, by size in cm (A6 ... A1) */
function ms_estimator_a_names() {
    return ['10.5x14.8' => 'A6', '14.8x21' => 'A5', '21x29.7' => 'A4', '29.7x42' => 'A3', '42x59.4' => 'A2', '59.4x84.1' => 'A1'];
}

/** How far apart two sizes are (area and shape), for "closest": smaller is closer. $a, $b = [w, h] in the same unit */
function ms_estimator_size_distance($a, $b) {
    sort($a); sort($b);
    return abs(log(($b[0] * $b[1]) / ($a[0] * $a[1]))) + abs(log(($b[1] / $b[0]) / ($a[1] / $a[0])));
}

/** The closest standard metric size (canvas and panels, no paper) to an inch size ("10x8"), as "20x25" */
function ms_estimator_cm_of($size) {
    $p = array_map('floatval', explode('x', $size));
    if (count($p) !== 2 || $p[0] <= 0 || $p[1] <= 0) return $size;
    $want = [$p[0] * 2.54, $p[1] * 2.54];
    $best = null; $bd = 1e9;
    foreach (ms_estimator_sizes_cm() as $group => $list) {
        if ($group === 'A sizes (ISO paper)' || $group === 'Paper sheets') continue; // the order options are canvas / panels
        foreach ($list as $cm) {
            $q = array_map('floatval', explode('x', $cm));
            $d = ms_estimator_size_distance($want, $q);
            if ($d < $bd) { $bd = $d; $best = $cm; }
        }
    }
    return $best ?: $size;
}

/** The closest inch size in the inch list to a metric size, if it is really close ("12x16"), else '' */
function ms_estimator_inch_hint($cm) {
    $q = array_map('floatval', explode('x', $cm));
    $want = [$q[0] / 2.54, $q[1] / 2.54];
    $best = ''; $bd = 1e9;
    foreach (ms_estimator_sizes() as $list) foreach ($list as $in) {
        $p = array_map('floatval', explode('x', $in));
        $d = ms_estimator_size_distance($want, $p);
        if ($d < $bd) { $bd = $d; $best = $in; }
    }
    return $bd < 0.12 ? $best : '';
}

/**
 * The size drop-down's options (the order options on top, then the groups), in inches or centimeters
 */
function ms_estimator_size_options($own, $own_label, $metric, $selected) {
    $nice = function($size) { return str_replace('x', ' × ', $size); };
    $names = ms_estimator_a_names();
    $cm_label = function($cm) use ($nice, $names) {
        $hint = ms_estimator_inch_hint($cm);
        return (isset($names[$cm]) ? $names[$cm] . ' · ' : '') . $nice($cm) . ' cm' . ($hint && !isset($names[$cm]) ? '  ≈ ' . $nice($hint) . ' in' : '');
    };
    if ($own) {
        echo '<optgroup label="' . esc_attr($metric ? $own_label . ' (closest standard size)' : $own_label) . '">';
        foreach ($own as $size) {
            if ($metric) { $v = ms_estimator_cm_of($size); $text = $nice($v) . ' cm  ≈ ' . $nice($size) . ' in'; }
            else { $v = $size; $text = $nice($size); }
            echo '<option value="' . esc_attr($v) . '"' . selected($size, $selected, false) . '>' . esc_html($text) . '</option>';
        }
        echo '</optgroup>';
    }
    foreach (($metric ? ms_estimator_sizes_cm() : ms_estimator_sizes()) as $group => $sizes) {
        echo '<optgroup label="' . esc_attr($group) . '">';
        foreach ($sizes as $size) echo '<option value="' . esc_attr($size) . '">' . esc_html($metric ? $cm_label($size) : $nice($size)) . '</option>';
        echo '</optgroup>';
    }
    echo '<option value="custom">Custom size…</option>';
}

/**
 * The largest size offered in a medium as [short edge, long edge], or null for no limit. Orientation doesn't matter.
 */
function ms_estimator_max($key, $s = null) {
    $s = $s ?: ms_estimator_settings();
    if (!empty($s['max'][$key]) && preg_match('/^(\d+(?:\.\d+)?)x(\d+(?:\.\d+)?)$/', $s['max'][$key], $m)) {
        $a = [(float) $m[1], (float) $m[2]];
        sort($a);
        return $a;
    }
    return null;
}

function ms_estimator_fits($w, $h, $key, $s = null) {
    $m = ms_estimator_max($key, $s);
    return !$m || (min($w, $h) <= $m[0] && max($w, $h) <= $m[1]);
}

/**
 * What the calculator's script needs: the formula, the factors, the largest sizes and the media's names (the admin screen
 * and the Price Estimator page, /estimator/, both use it)
 */
function ms_estimator_config($s) {
    $media = ms_estimator_media($s);
    return [
        'base'      => (float) $s['base'],
        'rate'      => (float) $s['rate'],
        'factors'   => array_map('floatval', $s['factors']),
        'largeFrom' => (float) $s['large_from'],
        'pctLow'    => (float) $s['pct_low'],
        'pctHigh'   => (float) $s['pct_high'],
        'roundTo'   => (int) $s['round_to'],
        'labels'    => $media,
        'max'       => array_map(function($k) use ($s) { return ms_estimator_max($k, $s); }, array_combine(array_keys($media), array_keys($media))),
    ];
}

/**
 * The order options' own sizes ("10x8"), once each, for the top of a size drop-down
 */
function ms_estimator_own_sizes() {
    $own = [];
    foreach (ms_estimator_options() as $o) $own[($o['w'] + 0) . 'x' . ($o['h'] + 0)] = ($o['w'] + 0) . 'x' . ($o['h'] + 0);
    return array_values($own);
}

/**
 * One row of the media table in the settings (also the template for "Add a medium")
 */
function ms_estimator_media_row($i, $m) {
    $n = 'media[' . esc_attr($i) . ']'; ?>
    <tr>
        <td>
            <input type="hidden" name="<?php echo $n; ?>[key]" value="<?php echo esc_attr($m['key']); ?>">
            <input type="text" class="regular-text" name="<?php echo $n; ?>[label]" value="<?php echo esc_attr($m['label']); ?>" placeholder="Medium, such as Watercolor" aria-label="Medium name">
        </td>
        <td><input type="number" name="<?php echo $n; ?>[factor]" value="<?php echo esc_attr($m['factor']); ?>" min="0.1" max="5" step="0.05" style="width: 80px;" aria-label="Factor"></td>
        <td><input type="text" name="<?php echo $n; ?>[max]" value="<?php echo esc_attr($m['max']); ?>" placeholder="no limit" style="width: 100px;" aria-label="Largest size"></td>
        <td><label><input type="checkbox" name="<?php echo $n; ?>[public]" value="1" <?php checked(!empty($m['public'])); ?>> Show</label></td>
        <td style="white-space: nowrap;">
            <button type="button" class="button button-small" data-move="-1" aria-label="Move up">↑</button>
            <button type="button" class="button button-small" data-move="1" aria-label="Move down">↓</button>
            <button type="button" class="button button-small" data-remove>Remove</button>
        </td>
    </tr>
    <?php
}

function ms_estimator_page() {
    if (!current_user_can('manage_woocommerce')) return;

    $s = ms_estimator_settings();
    $media = ms_estimator_media($s);
    $options = ms_estimator_options();
    $action = esc_url(admin_url('admin-post.php'));
    $own = [];
    foreach ($options as $o) $own[($o['w'] + 0) . 'x' . ($o['h'] + 0)] = ($o['w'] + 0) . 'x' . ($o['h'] + 0);
    $own = array_values($own);

    $cfg = ms_estimator_config($s);

    // The panels start closed (Core's script); the calculator above them is the screen's main card
    if (function_exists('monospace_lm_area_assets')) monospace_lm_area_assets();
    ?>
    <style>
        .ms-est { max-width: 900px; }
        .ms-est-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; padding: 16px 18px; margin: 0 0 14px; }
        .ms-est-warn { border-left: 4px solid #d63638; background: #fff; border-radius: 0 6px 6px 0; padding: 10px 14px; margin: 12px 0 14px; border-top: 1px solid #c3c4c7; border-right: 1px solid #c3c4c7; border-bottom: 1px solid #c3c4c7; }
        .ms-est-inputs { display: flex; flex-wrap: wrap; gap: 12px 18px; align-items: flex-end; }
        .ms-est-inputs label { display: block; font-weight: 600; margin: 0 0 4px; }
        .ms-est-inputs input[type=number] { width: 90px; }
        .ms-est-inputs select { min-width: 190px; }
        .ms-est-inputs .ms-est-unit { display: flex; align-items: center; gap: 6px; font-weight: 600; min-height: 30px; }
        .ms-est-inputs [hidden] { display: none; }
        .ms-est-x { padding-bottom: 6px; color: #646970; }
        .ms-est-result { margin-top: 16px; padding-top: 14px; border-top: 1px solid #f0f0f1; }
        .ms-est-price { font-size: 34px; line-height: 1.1; font-weight: 600; color: #1d2327; }
        .ms-est-range { margin: 4px 0 0; color: #50575e; font-size: 14px; }
        .ms-est-math { margin: 10px 0 0; color: #646970; font-family: Menlo, Consolas, monospace; font-size: 12px; line-height: 1.6; }
        .ms-est-note { margin: 10px 0 0; color: #646970; }
        .ms-est-copy { margin-top: 12px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
        .ms-est-sentence { flex: 1 1 100%; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; padding: 8px 10px; margin: 0; }
        .ms-est-bad { color: #8a2424; }
        .ms-est table.widefat td, .ms-est table.widefat th { vertical-align: middle; }
        .ms-est .lm-body { padding: 14px; }
        .ms-est-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px 16px; }
        .ms-est-grid label { display: block; font-weight: 600; margin: 0 0 4px; }
        .ms-est-grid input { width: 100%; }
        .ms-est-hint { color: #646970; margin: 4px 0 0; font-weight: 400; }
    </style>

    <div class="wrap ms-est">
        <h1>Price Estimator</h1>
        <p class="description" style="max-width: 760px;">A quick estimate for a customer who asks for a specific medium and size that isn't one of the order options. It only calculates - nothing is saved or sent.</p>

        <?php if (!empty($_GET['saved'])) : ?>
            <div class="notice notice-success is-dismissible"><p>Formula saved.</p></div>
        <?php endif; ?>

        <div class="ms-est-warn"><strong>Not for portraits of any kind.</strong> Portraits are always quoted individually (subject, style, size, materials and scope) - never from this calculator.</div>

        <div class="ms-est-card" id="ms-est" data-config="<?php echo esc_attr(wp_json_encode($cfg)); ?>">
            <div class="ms-est-inputs">
                <div>
                    <label for="ms-est-medium">Medium</label>
                    <select id="ms-est-medium">
                        <?php foreach ($media as $key => $label) : ?>
                            <option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'acrylic'); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="ms-est-size" id="ms-est-size-label">Size (inches)</label>
                    <select id="ms-est-size"><?php ms_estimator_size_options($own, 'Your order options', false, '12x9'); ?></select>
                    <select id="ms-est-size-m" hidden><?php ms_estimator_size_options($own, 'Your order options', true, '12x9'); ?></select>
                </div>
                <div>
                    <label class="ms-est-unit"><input type="checkbox" id="ms-est-metric"> Metric (cm)</label>
                </div>
                <div class="ms-est-custom" hidden>
                    <label for="ms-est-w" class="ms-est-ul">Width (in)</label>
                    <input type="number" id="ms-est-w" value="12" min="1" max="200" step="0.5" inputmode="decimal">
                </div>
                <span class="ms-est-x ms-est-custom" hidden>×</span>
                <div class="ms-est-custom" hidden>
                    <label for="ms-est-h" class="ms-est-ul">Height (in)</label>
                    <input type="number" id="ms-est-h" value="9" min="1" max="200" step="0.5" inputmode="decimal">
                </div>
            </div>
            <p class="ms-est-note" style="margin-top: 8px;">Standard sizes as sold in the US; the metric list has the common sizes in centimeters (the price comes from the real area, the same formula). Portrait or landscape doesn't change the price. Choose "Custom size…" to type your own.</p>

            <div class="ms-est-result" aria-live="polite">
                <div class="ms-est-price" id="ms-est-price">-</div>
                <p class="ms-est-range" id="ms-est-range"></p>
                <p class="ms-est-math" id="ms-est-math"></p>
                <p class="ms-est-note" id="ms-est-note"></p>
                <div class="ms-est-copy">
                    <p class="ms-est-sentence" id="ms-est-sentence"></p>
                    <button type="button" class="button button-primary" id="ms-est-copy">Copy for the customer</button>
                    <span id="ms-est-copied" class="description" hidden>Copied.</span>
                </div>
            </div>
            <p class="ms-est-note">Frame and shipping are not included. Shipping comes from the WooCommerce shipping zones, never typed by hand.</p>
        </div>

        <div class="lm-areas">
            <div class="lm-panels">

                <details class="lm-panel" data-panel="options">
                    <summary>
                        <span class="lm-t">Your current options vs the formula</span>
                        <span class="lm-chip <?php echo $options ? 'is-on' : 'is-empty'; ?>"><?php echo $options ? count($options) . ' options' : 'None found'; ?></span>
                        <span class="lm-sub">The prices on the commissions page, and what the formula gives for the same size</span>
                    </summary>
                    <div class="lm-body">
                        <?php if ($options) : ?>
                            <table class="widefat striped" id="ms-est-options">
                                <thead><tr><th>Option</th><th>Size</th><th>Listed price</th><th>Formula</th><th>Difference</th></tr></thead>
                                <tbody>
                                <?php foreach ($options as $o) : ?>
                                    <tr data-w="<?php echo esc_attr($o['w']); ?>" data-h="<?php echo esc_attr($o['h']); ?>" data-key="<?php echo esc_attr($o['key']); ?>" data-price="<?php echo esc_attr($o['price']); ?>">
                                        <td><?php echo esc_html($o['name']); ?></td>
                                        <td><?php echo esc_html(($o['w'] + 0) . '×' . ($o['h'] + 0)); ?></td>
                                        <td>$<?php echo esc_html(number_format($o['price'], 0)); ?></td>
                                        <td class="ms-est-f">-</td>
                                        <td class="ms-est-d">-</td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p class="description">If the differences grow, the listed prices have drifted from the formula - adjust it below.</p>
                        <?php else : ?>
                            <p class="description">No custom-order products with a size were found.</p>
                        <?php endif; ?>
                    </div>
                </details>

                <details class="lm-panel" data-panel="formula">
                    <summary>
                        <span class="lm-t">The formula</span>
                        <span class="lm-chip is-on">In use</span>
                        <span class="lm-sub">$<?php echo esc_html(rtrim(rtrim(number_format($s['base'], 2), '0'), '.')); ?> + $<?php echo esc_html(rtrim(rtrim(number_format($s['rate'], 2), '0'), '.')); ?> per square inch, times the medium's factor</span>
                    </summary>
                    <div class="lm-body">
                        <p class="description" style="margin-top: 0;">price = ($base + $rate × width × height) × the medium's factor, rounded. Fitted on the acrylic options (4×4 and 12×9); the other media are a steady multiple of that line. Large pieces get a surcharge that phases in between the size below and twice that area.</p>
                        <form method="post" action="<?php echo $action; ?>">
                            <?php wp_nonce_field('ms_estimator_save'); ?>
                            <input type="hidden" name="action" value="ms_estimator_save">
                            <div class="ms-est-grid">
                                <div><label for="e-base">Base ($)</label><input type="number" id="e-base" name="base" value="<?php echo esc_attr($s['base']); ?>" min="0" step="1"></div>
                                <div><label for="e-rate">Per square inch ($)</label><input type="number" id="e-rate" name="rate" value="<?php echo esc_attr($s['rate']); ?>" min="0" step="0.01"></div>
                                <div><label for="e-round">Round to the nearest</label>
                                    <select id="e-round" name="round_to">
                                        <?php foreach ([1, 5, 10] as $r) : ?>
                                            <option value="<?php echo (int) $r; ?>" <?php selected((int) $s['round_to'], $r); ?>>$<?php echo (int) $r; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <h3>Media</h3>
                            <p class="description" style="margin-top: 0;">The factor multiplies the formula; the largest size is such as 12x16 (either way round), empty for no limit - bigger sizes get no price. Untick "Show" to keep a medium in the calculator but out of the table on the Places page. Clear a name to remove a medium. The order here is the order in the calculator and the table.</p>
                            <table class="widefat striped" id="ms-est-media">
                                <thead><tr><th>Medium</th><th>Factor</th><th>Largest size</th><th>Public table</th><th></th></tr></thead>
                                <tbody>
                                    <?php foreach ($s['media'] as $i => $m) ms_estimator_media_row($i, $m); ?>
                                </tbody>
                            </table>
                            <p><button type="button" class="button" id="ms-est-add">Add a medium</button></p>
                            <template id="ms-est-row"><?php ms_estimator_media_row('__N__', ['key' => '', 'label' => '', 'factor' => 1, 'max' => '', 'public' => 1]); ?></template>
                            <h3>Public table</h3>
                            <div class="ms-est-grid" style="grid-template-columns: minmax(0, 1fr);">
                                <div><label for="e-sizes">Sizes shown (rows), in inches</label><input type="text" id="e-sizes" name="table_sizes" value="<?php echo esc_attr($s['table_sizes']); ?>" placeholder="5x7, 8x10, 12x16"><p class="ms-est-hint">Comma separated, in the order they should appear, such as 5x7, 8x10, 12x16, 20x24. A size nobody can be offered (over every shown medium's largest size) is left out of the table.</p></div>
                            </div>
                            <h3>Large pieces</h3>
                            <div class="ms-est-grid">
                                <div><label for="e-large">Large from (square inches)</label><input type="number" id="e-large" name="large_from" value="<?php echo esc_attr($s['large_from']); ?>" min="1" step="1"><p class="ms-est-hint">320 = 20×16</p></div>
                                <div><label for="e-low">Surcharge, low (%)</label><input type="number" id="e-low" name="pct_low" value="<?php echo esc_attr($s['pct_low']); ?>" min="0" max="100" step="1"></div>
                                <div><label for="e-high">Surcharge, high (%)</label><input type="number" id="e-high" name="pct_high" value="<?php echo esc_attr($s['pct_high']); ?>" min="0" max="100" step="1"></div>
                            </div>
                            <p class="description">Large pieces cost more in materials, framing and time than a straight line predicts. The suggested price uses the middle of the low and high surcharge.</p>
                            <p><button type="submit" class="button button-primary">Save formula</button></p>
                        </form>
                    </div>
                </details>

            </div>
        </div>
    </div>

    <script>
    (function () {
        var root = document.getElementById('ms-est');
        if (!root) return;
        var c = JSON.parse(root.getAttribute('data-config'));
        var $ = function (id) { return document.getElementById(id); };
        var money = function (n) { return '$' + Math.round(n).toLocaleString('en-US'); };
        var rnd = function (n) { return Math.round(n / c.roundTo) * c.roundTo; };
        var trim = function (n) { return String(Math.round(n * 100) / 100); };

        function fits(w, h, key) {
            var m = c.max[key];
            return !m || (Math.min(w, h) <= m[0] && Math.max(w, h) <= m[1]);
        }

        function calc(w, h, key) {
            var area = w * h;
            var raw = (c.base + c.rate * area) * c.factors[key];
            var ramp = Math.min(1, Math.max(0, (area - c.largeFrom) / c.largeFrom));
            var low = raw * (1 + ramp * c.pctLow / 100);
            var high = raw * (1 + ramp * c.pctHigh / 100);
            return { area: area, raw: raw, ramp: ramp, low: rnd(low), high: rnd(high), price: rnd((low + high) / 2), beyond: !fits(w, h, key) };
        }

        function sizeText(w, h) { return trim(w) + '×' + trim(h); }

        // Metric switch (2026-11-15): the sums stay in inches; a size in cm is converted by its real area (cm / 2.54)
        var metric = false;
        try { metric = localStorage.getItem('ms_est_metric') === '1'; } catch (e) {}
        var CM = 2.54;
        function unitName() { return metric ? 'cm' : 'in'; }
        function dims(a, b) { return metric ? trim(a * CM) + ' × ' + trim(b * CM) + ' cm' : trim(a) + ' × ' + trim(b) + ' in'; }
        function applyUnit(fromCustom) {
            var inch = $('ms-est-size'), met = $('ms-est-size-m'), was = metric ? inch : met, now = metric ? met : inch;
            was.hidden = true; now.hidden = false;
            $('ms-est-metric').checked = metric;
            $('ms-est-size-label').textContent = metric ? 'Size (cm)' : 'Size (inches)';
            document.querySelectorAll('.ms-est-ul').forEach(function (l) { l.textContent = l.textContent.replace(/\((in|cm)\)/, '(' + unitName() + ')'); });
            ['ms-est-w', 'ms-est-h'].forEach(function (id) { $(id).step = metric ? 1 : 0.5; $(id).max = metric ? 500 : 200; });
            if (fromCustom === 'custom') {
                // custom values carry over as the same real size
                ['ms-est-w', 'ms-est-h'].forEach(function (id) { var v = parseFloat($(id).value); if (v > 0) $(id).value = trim(metric ? v * CM : v / CM); });
                now.value = 'custom';
            } else if (fromCustom) {
                // the nearest size in the other list, by area
                var q = fromCustom.split('x'), target = parseFloat(q[0]) * parseFloat(q[1]) * (metric ? CM * CM : 1 / (CM * CM)), best = null, bd = 1e9;
                Array.prototype.forEach.call(now.options, function (o) {
                    if (o.value === 'custom') return;
                    var p = o.value.split('x'), d = Math.abs(parseFloat(p[0]) * parseFloat(p[1]) - target);
                    if (d < bd) { bd = d; best = o.value; }
                });
                if (best) now.value = best;
            }
        }

        function update() {
            var sel = $(metric ? 'ms-est-size-m' : 'ms-est-size').value;
            var custom = sel === 'custom';
            document.querySelectorAll('.ms-est-custom').forEach(function (el) { el.hidden = !custom; });
            if (!custom) { var q = sel.split('x'); $('ms-est-w').value = q[0]; $('ms-est-h').value = q[1]; }
            var w = parseFloat($('ms-est-w').value), h = parseFloat($('ms-est-h').value), key = $('ms-est-medium').value;
            var shownW = w, shownH = h;
            if (metric) { w = w / CM; h = h / CM; }
            var price = $('ms-est-price');
            if (!(w > 0) || !(h > 0)) {
                price.textContent = '-';
                price.className = 'ms-est-price ms-est-bad';
                $('ms-est-range').textContent = 'Enter a width and a height.';
                $('ms-est-math').textContent = $('ms-est-note').textContent = $('ms-est-sentence').textContent = '';
                return;
            }
            price.className = 'ms-est-price';
            var r = calc(w, h, key), label = c.labels[key];
            price.textContent = money(r.price);

            var big = r.ramp > 0;
            $('ms-est-range').textContent = big ? 'Range ' + money(r.low) + ' – ' + money(r.high) + ' (with the large-piece surcharge)' : '';
            $('ms-est-math').textContent = (metric ? trim(shownW) + ' × ' + trim(shownH) + ' cm = ' + trim(w) + ' × ' + trim(h) + ' in → ' : '') + '($' + trim(c.base) + ' + $' + trim(c.rate) + ' × ' + trim(r.area) + ' in²) × ' + trim(c.factors[key]) + ' = ' + money(r.raw) +
                (big ? '  ·  large-piece surcharge ' + trim(r.ramp * c.pctLow) + '–' + trim(r.ramp * c.pctHigh) + '%' : '');
            // A size past the medium's largest offered one (or a size not on offer) is still estimated, by extending the same formula (owner, 2026-11-08: ballparks at markets)
            var mx = c.max[key];
            var beyond = r.beyond ? label + ' is only offered up to ' + dims(mx[0], mx[1]) + ' - this size is an extrapolation, a ballpark to talk from. ' : '';
            $('ms-est-note').textContent = beyond + (r.area > 1200 ? 'This is far beyond the sizes the formula was fitted on - treat it as a ballpark and decide the price yourself.' : '');
            $('ms-est-sentence').textContent = 'A ' + sizeText(shownW, shownH) + (metric ? ' cm ' : ' inch ') + label.toLowerCase() + ' piece would be about ' + money(r.price) + ', not including framing or shipping. This is an estimate; the final price is confirmed before anything is started.';
        }

        ['ms-est-w', 'ms-est-h', 'ms-est-medium', 'ms-est-size', 'ms-est-size-m'].forEach(function (id) { $(id).addEventListener('input', update); $(id).addEventListener('change', update); });
        $('ms-est-metric').addEventListener('change', function () {
            var prev = $(metric ? 'ms-est-size-m' : 'ms-est-size').value;
            metric = this.checked;
            try { localStorage.setItem('ms_est_metric', metric ? '1' : '0'); } catch (e) {}
            applyUnit(prev);
            update();
        });
        applyUnit(null);

        $('ms-est-copy').addEventListener('click', function () {
            var text = $('ms-est-sentence').textContent, done = function () { $('ms-est-copied').hidden = false; setTimeout(function () { $('ms-est-copied').hidden = true; }, 1800); };
            if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text).then(done);
            else { var t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); document.body.removeChild(t); done(); }
        });

        // The current options next to the formula
        document.querySelectorAll('#ms-est-options tbody tr').forEach(function (tr) {
            var key = tr.getAttribute('data-key');
            if (!c.factors[key]) return;
            var r = calc(parseFloat(tr.getAttribute('data-w')), parseFloat(tr.getAttribute('data-h')), key);
            if (r.over) return;
            var listed = parseFloat(tr.getAttribute('data-price')), diff = r.price - listed;
            tr.querySelector('.ms-est-f').textContent = money(r.price);
            tr.querySelector('.ms-est-d').textContent = (diff > 0 ? '+' : diff < 0 ? '−' : '') + money(Math.abs(diff));
        });

        update();
    })();

    // The media table: add, move and remove rows (nothing is kept until Save formula)
    (function () {
        var body = document.querySelector('#ms-est-media tbody'), tpl = document.getElementById('ms-est-row'), add = document.getElementById('ms-est-add');
        if (!body || !tpl || !add) return;
        var next = 1000;
        add.addEventListener('click', function () {
            var holder = document.createElement('tbody');
            holder.innerHTML = tpl.innerHTML.replace(/__N__/g, String(next++));
            var row = holder.querySelector('tr');
            body.appendChild(row);
            var name = row.querySelector('input[type=text]');
            if (name) name.focus();
        });
        body.addEventListener('click', function (e) {
            var b = e.target.closest('button');
            if (!b) return;
            var tr = b.closest('tr');
            if (b.hasAttribute('data-remove')) tr.remove();
            else if (b.getAttribute('data-move') === '-1' && tr.previousElementSibling) body.insertBefore(tr, tr.previousElementSibling);
            else if (b.getAttribute('data-move') === '1' && tr.nextElementSibling) body.insertBefore(tr.nextElementSibling, tr);
        });
    })();
    </script>
    <?php
}

/**
 * The estimate for one size and medium, the same sums as the admin page's calculator (phased-in surcharge for large
 * pieces, the middle of the low and high surcharge, rounded)
 */
function ms_estimator_price($w, $h, $key, $s = null) {
    $s = $s ?: ms_estimator_settings();
    if (!isset($s['factors'][$key]) || !ms_estimator_fits($w, $h, $key, $s)) return 0;   // 0 = not offered at this size
    $area = $w * $h;
    $raw = ($s['base'] + $s['rate'] * $area) * $s['factors'][$key];
    $ramp = max(0, min(1, ($area - $s['large_from']) / $s['large_from']));
    $mid = $raw * (1 + $ramp * ($s['pct_low'] + $s['pct_high']) / 2 / 100);
    $to = max(1, (int) $s['round_to']);
    return (int) (round($mid / $to) * $to);
}

/**
 * [commission_price_guide] - a table of typical prices for other sizes (the Places page, /services/custom-work/). Driven by
 * the Price Estimator's formula, so it follows the settings there. Only the table: any text around it (what a dash means,
 * estimates not quotes, frames and shipping, that portraits are quoted individually) is written in the Block Editor.
 *
 *   sizes="5x7,8x10,..."    the rows (inches; default: the sizes set in the Price Estimator; a row with no price is left out)        media="pen-ink,acrylic"    the columns (default: the media set to Show)
 */
add_shortcode('commission_price_guide', function($atts) {
    $a = shortcode_atts([
        'sizes' => '',   // empty = the sizes set in the Price Estimator
        'media' => '',   // empty = the media ticked "Show" in the settings, in their order
        'fold'  => 'no',  // fold="yes" folds the table itself (not used: the owner folds the whole card on the page, 2026-11-15)
    ], $atts, 'commission_price_guide');

    $s = ms_estimator_settings();
    $labels = ms_estimator_media($s);
    $keys = trim((string) $a['media']) !== '' ? array_map('trim', explode(',', $a['media'])) : $s['public'];
    $cols = array_values(array_filter($keys, function($k) use ($labels) { return isset($labels[$k]); }));
    $rows = [];
    $wanted = trim((string) $a['sizes']) !== '' ? $a['sizes'] : $s['table_sizes'];
    foreach (array_map('trim', explode(',', $wanted)) as $size) {
        if (!preg_match('/^(\d+(?:\.\d+)?)x(\d+(?:\.\d+)?)$/i', $size, $m)) continue;
        $w = (float) $m[1];
        $h = (float) $m[2];
        $prices = [];
        foreach ($cols as $k) $prices[$k] = ms_estimator_price($w, $h, $k, $s);
        if (array_filter($prices)) $rows[] = [$w, $h, $prices];   // a row with no price in any column is left out
    }
    if (!$cols || !$rows) return '';

    ob_start(); ?>
    <style>
        .ms-price-guide { margin: 1rem 0; }
        .ms-price-guide > summary { cursor: pointer; font-weight: 600; padding: 0.4em 0; }
        .ms-price-guide .ms-pg-scroll { overflow-x: auto; }
        .ms-price-guide table { width: 100%; border-collapse: collapse; font-family: inherit; text-align: center; }
        .ms-price-guide th, .ms-price-guide td { padding: 0.55em 0.7em; border-bottom: 1px solid color-mix(in srgb, currentColor 18%, transparent); white-space: nowrap; }
        .ms-price-guide thead th { font-weight: 600; border-bottom-width: 2px; }
        .ms-price-guide th:first-child, .ms-price-guide td:first-child { text-align: left; font-weight: 600; }
        .ms-price-guide tbody tr:last-child th, .ms-price-guide tbody tr:last-child td { border-bottom: 0; }
    </style>
    <?php $fold = strtolower(trim((string) $a['fold'])) === 'yes'; ?>
    <<?php echo $fold ? 'details' : 'div'; ?> class="ms-price-guide">
        <?php if ($fold) : ?><summary>Show the price table</summary><?php endif; ?>
        <div class="ms-pg-scroll">
            <table>
                <thead>
                    <tr>
                        <th scope="col">Size (inches)</th>
                        <?php foreach ($cols as $k) : ?><th scope="col"><?php echo esc_html($labels[$k]); ?></th><?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as [$w, $h, $prices]) : ?>
                        <tr>
                            <th scope="row"><?php echo esc_html(($w + 0) . ' × ' . ($h + 0)); ?></th>
                            <?php foreach ($cols as $k) : $price = $prices[$k]; ?><td><?php echo $price ? '$' . esc_html(number_format($price)) : '&ndash;'; ?></td><?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </<?php echo $fold ? 'details' : 'div'; ?>>
    <?php
    return ob_get_clean();
});

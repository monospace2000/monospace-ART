<?php
/**
 * Commissions: the Price Estimator page for markets (2026-11-02, owner; official title "Price Estimator") - [price_estimator], on the page art.monospace.com/estimator/.
 * A quick, non-configurable version of WooCommerce > Price Estimator for a phone at a market: a medium, a size, a price. It
 * only reads the estimator's formula, media and largest sizes (nothing to set here); the sums run in the browser and nothing
 * is written. Logged-in administrators only, like the Market Inventory Manager (a login form for visitors, "Access denied"
 * for anyone without manage_woocommerce); the page is noindex. NOT for portraits of any kind.
 *
 * Also a toolbar shortcut (ms-tb-calculator): icon-only on phones, placed and hidden in Settings > Admin toolbar like the
 * other ms-tb-* shortcuts.
 *
 * @package monospace-commissions
 */

if (!defined('ABSPATH')) exit;

/** The page with the calculator (the page with the role estimator, wherever it is), else '' (none) */
function ms_calculator_page_url() {
    static $url = null;
    if ($url !== null) return $url;
    $page = function_exists('monospace_role_page') ? monospace_role_page('estimator') : null;
    $url = ($page && $page->post_status === 'publish' && has_shortcode($page->post_content, 'price_estimator')) ? (string) get_permalink($page) : '';
    return $url;
}

// The page is for the owner, not for search engines
add_filter('wp_robots', function($robots) {
    if (is_singular('page') && has_shortcode((string) get_post_field('post_content', get_queried_object_id()), 'price_estimator')) {
        $robots = wp_robots_no_robots($robots);
    }
    return $robots;
});

add_action('admin_bar_menu', function($bar) {
    if (!current_user_can('manage_woocommerce') || !($url = ms_calculator_page_url())) return;
    $bar->add_node([
        'id'    => 'ms-tb-calculator',
        'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ms-tb-label">Price Estimator</span>',
        'href'  => $url,
        'meta'  => ['title' => 'Price Estimator'],
    ]);
}, 97);

// The icon (the money dashicon), front end and wp-admin
$ms_calculator_toolbar_css = function() {
    if (!is_admin_bar_showing() || !current_user_can('manage_woocommerce')) return;
    echo '<style id="ms-tb-calculator-css">#wpadminbar li#wp-admin-bar-ms-tb-calculator .ab-icon:before{content:"\f18e";top:2px}#wpadminbar li#wp-admin-bar-ms-tb-calculator .ms-tb-label{margin-left:4px}</style>' . "\n";
};
add_action('wp_head', $ms_calculator_toolbar_css);
add_action('admin_head', $ms_calculator_toolbar_css);

add_shortcode('price_estimator', function() {
    if (!is_user_logged_in()) {
        $redirect = get_permalink() ?: home_url('/');
        ob_start(); ?>
        <div class="ms-calc-login" style="max-width: 850px;">
            <p>Please log in to use the Price Estimator.</p>
            <?php wp_login_form(['echo' => true, 'redirect' => $redirect, 'label_username' => 'Username or Email', 'remember' => true]); ?>
        </div>
        <?php
        return ob_get_clean();
    }
    if (!current_user_can('manage_woocommerce')) return '<p>Access denied.</p>';

    $s = ms_estimator_settings();
    $media = ms_estimator_media($s);
    $own = ms_estimator_own_sizes();
    $cfg = ms_estimator_config($s);
    $first = array_key_exists('acrylic', $media) ? 'acrylic' : (string) array_key_first($media);

    ob_start(); ?>
    <style>
        .ms-calc { max-width: 560px; margin: 0 auto; font-family: inherit; }
        .ms-calc * { box-sizing: border-box; }
        .ms-calc-label { display: block; margin: 1rem 0 0.4rem; font-weight: 600; }
        .ms-calc-media { display: flex; flex-wrap: wrap; gap: 4px 22px; }
        .ms-calc-media label { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; margin: 0; cursor: pointer; }
        .ms-calc-media input[type="radio"] { flex: 0 0 auto; width: 20px; height: 20px; min-height: 0; margin: 0; padding: 0; accent-color: currentColor; cursor: pointer; }
        .ms-calc select, .ms-calc input[type="number"] { width: 100%; min-height: 48px; padding: 0.4em 0.7em; font: inherit; font-size: 16px; color: inherit; background: transparent; border: 1px solid color-mix(in srgb, currentColor 40%, transparent); border-radius: 8px; }
        .ms-calc select option { color: #1d2327; background: #fff; }
        .ms-calc-unit { display: inline-flex; align-items: center; gap: 8px; min-height: 44px; margin: 6px 0 0; font-weight: 600; cursor: pointer; }
        .ms-calc-unit input { width: 20px; height: 20px; margin: 0; accent-color: currentColor; }
        .ms-calc select[hidden] { display: none; }
        .ms-calc-custom { display: flex; gap: 10px; align-items: flex-end; margin-top: 10px; }
        .ms-calc-custom[hidden] { display: none; }
        .ms-calc-custom > div { flex: 1 1 0; }
        .ms-calc-custom .ms-calc-x { flex: 0 0 auto; padding-bottom: 12px; }
        .ms-calc-custom label { display: block; font-size: 0.85em; margin: 0 0 3px; }
        .ms-calc-result { margin-top: 1.4rem; padding: 1rem; text-align: center; border: 1px solid color-mix(in srgb, currentColor 30%, transparent); border-radius: 12px; }
        .ms-calc-price { font-size: 3rem; line-height: 1.1; font-weight: 700; }
        .ms-calc-price.is-bad { font-size: 1.8rem; color: #d63638; }
        .ms-calc-range, .ms-calc-math { margin: 0.4rem 0 0; font-size: 0.95em; opacity: 0.85; }
        .ms-calc-math { font-size: 0.8em; font-family: Menlo, Consolas, monospace; }
        .ms-calc-note { margin: 0.9rem 0 0; font-size: 0.85em; opacity: 0.8; }
    </style>

    <div class="ms-calc" id="ms-calc" data-config="<?php echo esc_attr(wp_json_encode($cfg)); ?>">
        <span class="ms-calc-label" id="ms-calc-medium-label">Medium</span>
        <div class="ms-calc-media" role="radiogroup" aria-labelledby="ms-calc-medium-label">
            <?php foreach ($media as $key => $label) : ?>
                <label><input type="radio" name="ms-calc-medium" value="<?php echo esc_attr($key); ?>" <?php checked($key, $first); ?>> <?php echo esc_html($label); ?></label>
            <?php endforeach; ?>
        </div>

        <label class="ms-calc-label" for="ms-calc-size" id="ms-calc-size-label">Size (inches)</label>
        <select id="ms-calc-size"><?php ms_estimator_size_options($own, 'Order options', false, '12x9'); ?></select>
        <select id="ms-calc-size-m" hidden><?php ms_estimator_size_options($own, 'Order options', true, '12x9'); ?></select>
        <label class="ms-calc-unit"><input type="checkbox" id="ms-calc-metric"> Metric (cm)</label>
        <div class="ms-calc-custom" id="ms-calc-custom" hidden>
            <div><label for="ms-calc-w">Width <span class="ms-calc-u">(in)</span></label><input type="number" id="ms-calc-w" value="12" min="1" max="200" step="0.5" inputmode="decimal"></div>
            <span class="ms-calc-x">×</span>
            <div><label for="ms-calc-h">Height <span class="ms-calc-u">(in)</span></label><input type="number" id="ms-calc-h" value="9" min="1" max="200" step="0.5" inputmode="decimal"></div>
        </div>

        <div class="ms-calc-result" aria-live="polite">
            <div class="ms-calc-price" id="ms-calc-price">-</div>
            <p class="ms-calc-range" id="ms-calc-range"></p>
            <p class="ms-calc-math" id="ms-calc-math"></p>
        </div>
        <p class="ms-calc-note">An estimate, not a quote. Frame and shipping are not included.</p>
        <p class="ms-calc-note" id="ms-calc-far"></p>
    </div>

    <script>
    (function () {
        var root = document.getElementById('ms-calc');
        if (!root) return;
        var c = JSON.parse(root.getAttribute('data-config'));
        var $ = function (id) { return document.getElementById(id); };
        var radios = root.querySelectorAll('.ms-calc-media input[type="radio"]');
        function medium() { var on = root.querySelector('.ms-calc-media input[type="radio"]:checked'); return on ? on.value : ''; }
        var money = function (n) { return '$' + Math.round(n).toLocaleString('en-US'); };
        var rnd = function (n) { return Math.round(n / c.roundTo) * c.roundTo; };
        var trim = function (n) { return String(Math.round(n * 100) / 100); };

        function fits(w, h, k) {
            var m = c.max[k];
            return !m || (Math.min(w, h) <= m[0] && Math.max(w, h) <= m[1]);
        }

        // Metric switch (2026-11-15): the sums stay in inches; a size in cm is converted by its real area (cm / 2.54)
        var metric = false;
        try { metric = localStorage.getItem('ms_est_metric') === '1'; } catch (e) {}
        var CM = 2.54;
        function dims(a, b) { return metric ? trim(a * CM) + ' × ' + trim(b * CM) + ' cm' : trim(a) + ' × ' + trim(b) + ' in'; }
        function applyUnit(prev) {
            var inch = $('ms-calc-size'), met = $('ms-calc-size-m'), now = metric ? met : inch;
            (metric ? inch : met).hidden = true; now.hidden = false;
            $('ms-calc-metric').checked = metric;
            $('ms-calc-size-label').textContent = metric ? 'Size (cm)' : 'Size (inches)';
            root.querySelectorAll('.ms-calc-u').forEach(function (u) { u.textContent = metric ? '(cm)' : '(in)'; });
            ['ms-calc-w', 'ms-calc-h'].forEach(function (id) { $(id).step = metric ? 1 : 0.5; $(id).max = metric ? 500 : 200; });
            if (prev === 'custom') {
                ['ms-calc-w', 'ms-calc-h'].forEach(function (id) { var v = parseFloat($(id).value); if (v > 0) $(id).value = trim(metric ? v * CM : v / CM); });
                now.value = 'custom';
            } else if (prev) {
                // the nearest size in the other list, by area
                var q = prev.split('x'), target = parseFloat(q[0]) * parseFloat(q[1]) * (metric ? CM * CM : 1 / (CM * CM)), best = null, bd = 1e9;
                Array.prototype.forEach.call(now.options, function (o) {
                    if (o.value === 'custom') return;
                    var p = o.value.split('x'), d = Math.abs(parseFloat(p[0]) * parseFloat(p[1]) - target);
                    if (d < bd) { bd = d; best = o.value; }
                });
                if (best) now.value = best;
            }
        }

        function update() {
            var key = medium();
            var sel = $(metric ? 'ms-calc-size-m' : 'ms-calc-size').value, custom = sel === 'custom';
            $('ms-calc-custom').hidden = !custom;
            if (!custom) { var q = sel.split('x'); $('ms-calc-w').value = q[0]; $('ms-calc-h').value = q[1]; }
            var w = parseFloat($('ms-calc-w').value), h = parseFloat($('ms-calc-h').value), shownW = w, shownH = h;
            if (metric) { w = w / CM; h = h / CM; }
            var price = $('ms-calc-price'), range = $('ms-calc-range'), math = $('ms-calc-math'), far = $('ms-calc-far');
            math.textContent = far.textContent = '';
            if (!(w > 0) || !(h > 0)) { price.textContent = '-'; price.className = 'ms-calc-price is-bad'; range.textContent = 'Enter a width and a height.'; return; }
            var area = w * h, raw = (c.base + c.rate * area) * c.factors[key];
            var ramp = Math.min(1, Math.max(0, (area - c.largeFrom) / c.largeFrom));
            var low = raw * (1 + ramp * c.pctLow / 100), high = raw * (1 + ramp * c.pctHigh / 100);
            price.className = 'ms-calc-price';
            price.textContent = money(rnd((low + high) / 2));
            range.textContent = ramp > 0 ? 'Range ' + money(rnd(low)) + ' – ' + money(rnd(high)) + ' (large piece)' : '';
            math.textContent = (metric ? trim(shownW) + ' × ' + trim(shownH) + ' cm = ' + trim(w) + ' × ' + trim(h) + ' in → ' : '') + '($' + trim(c.base) + ' + $' + trim(c.rate) + ' × ' + trim(area) + ' in²) × ' + trim(c.factors[key]);
            // Past the medium's largest offered size the same formula is extended: a ballpark, not an offer (owner, 2026-11-08)
            var mx = c.max[key];
            far.textContent = (!fits(w, h, key) ? c.labels[key] + ' is offered up to ' + dims(mx[0], mx[1]) + ' - this is an extrapolated ballpark. ' : '') +
                (area > 1200 ? 'Far beyond the sizes this was fitted on - a ballpark only.' : '');
        }

        radios.forEach(function (r) { r.addEventListener('change', update); });
        ['ms-calc-size', 'ms-calc-size-m', 'ms-calc-w', 'ms-calc-h'].forEach(function (id) { $(id).addEventListener('input', update); $(id).addEventListener('change', update); });
        $('ms-calc-metric').addEventListener('change', function () {
            var prev = $(metric ? 'ms-calc-size-m' : 'ms-calc-size').value;
            metric = this.checked;
            try { localStorage.setItem('ms_est_metric', metric ? '1' : '0'); } catch (e) {}
            applyUnit(prev);
            update();
        });
        applyUnit(null);
        update();
    })();
    </script>
    <?php
    return ob_get_clean();
});

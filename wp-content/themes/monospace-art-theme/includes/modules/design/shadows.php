<?php
/**
 * Design module: Shadows - one switch, strength and color for every drop shadow on the site (2026-10-22, owner: "Is
 * there a global dropshadow switch?" ... "also let me pick shadow color so it could be glow on dark mode").
 *
 *   On / off      off = strength 0 (the shadows are still there, invisible)
 *   Strength      0-200% of each shadow as drawn
 *   Color        black by default; a light or warm color in the dark look makes them glow
 *   Outline       a stroke around the pictures (2026-10-22, owner: "also add Outline/Stroke"): width 0-6px (0 = none),
 *                 color - --ms-stroke-w / --ms-stroke-c, read by the picture rule in _global.scss
 * Option monospace_shadows [ on yes|no, strength, color, stroke, stroke_color ]. Printed as --ms-shadow-rgb ("r g b") and --ms-shadow-k on
 * :root through monospace_look_style() (and monospace_look_all_css(), so the dark look has its own). The shadows read
 * them through ms-shadow() (_variables.scss) - artwork images, cards, the menu bar, home page boxes, captions, the
 * hover lift, the print preview, menus; the switches of the menu bar, home boxes, captions and print preview still
 * turn theirs off. Glass buttons keep their own shading (Look > Buttons).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_shadows_defaults() {
    return [ 'on' => 'yes', 'strength' => 100, 'color' => '#000000', 'stroke' => 0, 'stroke_color' => '#000000' ];
}

function monospace_shadows() {
    $s = get_option( 'monospace_shadows', [] );
    $s = is_array( $s ) ? $s : [];
    $d = monospace_shadows_defaults();
    return [
        'on'       => ( $s['on'] ?? 'yes' ) === 'no' ? 'no' : 'yes',
        'strength' => max( 0, min( 200, (int) ( $s['strength'] ?? $d['strength'] ) ) ),
        'color'    => sanitize_hex_color( $s['color'] ?? '' ) ?: $d['color'],
        'stroke'       => max( 0, min( 6, (int) ( $s['stroke'] ?? 0 ) ) ),
        'stroke_color' => sanitize_hex_color( $s['stroke_color'] ?? '' ) ?: $d['stroke_color'],
    ];
}

/** The variables - only what differs from black at 100% */
function monospace_shadows_vars() {
    $s = monospace_shadows();
    $v = [];
    if ( strtolower( $s['color'] ) !== '#000000' ) {
        $h = ltrim( $s['color'], '#' );
        $v['--ms-shadow-rgb'] = hexdec( substr( $h, 0, 2 ) ) . ' ' . hexdec( substr( $h, 2, 2 ) ) . ' ' . hexdec( substr( $h, 4, 2 ) );
    }
    $k = $s['on'] === 'no' ? 0 : $s['strength'] / 100;
    if ( $k != 1 ) $v['--ms-shadow-k'] = rtrim( rtrim( number_format( $k, 2, '.', '' ), '0' ), '.' ) ?: '0';
    if ( $s['stroke'] > 0 ) { $v['--ms-stroke-w'] = $s['stroke'] . 'px'; $v['--ms-stroke-c'] = $s['stroke_color']; }
    return $v;
}

add_action( 'wp_head', function () {
    $v = monospace_shadows_vars();
    if ( ! $v || ! function_exists( 'monospace_look_style' ) ) return;
    $c = ''; foreach ( $v as $k => $x ) $c .= $k . ':' . $x . ';';
    monospace_look_style( 'ms-shadows', ':root{' . $c . '}' ); // scoped when dark mode is on
}, 31 );

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['shadows'] = [
        'title'       => 'Shadows & outlines',
        'description' => 'Every drop shadow on the site at once - on or off, how strong, its color (a light one makes a glow in the dark look) - and an outline around the pictures.',
        'group'       => 'look',
        'order'       => 44,
        'summary'     => function () {
            $s = monospace_shadows();
            return ( $s['on'] === 'no' ? 'Shadows off' : $s['strength'] . '%' . ( strtolower( $s['color'] ) !== '#000000' ? ', ' . $s['color'] : '' ) ) . ( $s['stroke'] ? ' · outline ' . $s['stroke'] . 'px' : '' );
        },
        'render'      => 'monospace_render_shadows_settings',
        'save'        => 'monospace_save_shadows_settings',
    ];
    return $modules;
} );

function monospace_render_shadows_settings() {
    $s = monospace_shadows(); ?>
    <table class="form-table" role="presentation" id="ms-shadows">
        <tr><th scope="row">Shadows</th><td>
            <input type="hidden" name="ms_shadows[seen]" value="1">
            <label><input type="checkbox" name="ms_shadows[on]" value="yes" <?php checked( $s['on'], 'yes' ); ?>> On - artwork images, cards, the menu bar, home page boxes, captions, the hover lift, the print preview</label>
        </td></tr>
        <tr><th scope="row"><label for="ms-shadows-strength">Strength</label></th><td>
            <input type="range" id="ms-shadows-strength" name="ms_shadows[strength]" min="0" max="200" step="10" value="<?php echo (int) $s['strength']; ?>">
            <output><?php echo (int) $s['strength']; ?>%</output> <span class="description">100% = as designed</span>
        </td></tr>
        <tr><th scope="row"><label for="ms-shadows-color">Color</label></th><td>
            <input type="color" id="ms-shadows-color" name="ms_shadows[color]" value="<?php echo esc_attr( $s['color'] ); ?>">
            <button type="button" class="button-link" id="ms-shadows-black">Black</button>
            <p class="description">On the Dark look, a light or warm color (white, cream, gold) turns the shadows into a soft glow.</p>
        </td></tr>
        <tr><th scope="row"><label for="ms-shadows-stroke">Outline</label></th><td>
            <input type="range" id="ms-shadows-stroke" name="ms_shadows[stroke]" min="0" max="6" step="1" value="<?php echo (int) $s['stroke']; ?>">
            <output><?php echo $s['stroke'] ? (int) $s['stroke'] . ' px' : 'none'; ?></output>
            <input type="color" id="ms-shadows-stroke-color" name="ms_shadows[stroke_color]" value="<?php echo esc_attr( $s['stroke_color'] ); ?>" aria-label="Outline color">
            <p class="description">A line around the artwork pictures - posts, feeds, the category grid, related posts, the Art Filter (not the price card’s picture). Pictures with the “No shadow” style get none.</p>
        </td></tr>
        <tr><th scope="row">Sample</th><td><span id="ms-shadows-sample" style="display:inline-block;width:120px;height:70px;border-radius:6px;background:#8a9bb0"></span></td></tr>
    </table>
    <script>
    (function () {
        // The sample and the Look preview frame follow the fields
        var t = document.getElementById('ms-shadows'), iframe = document.getElementById('ms-spot-iframe');
        if (!t) return;
        var on = t.querySelector('[name="ms_shadows[on]"]'), st = document.getElementById('ms-shadows-strength'), col = document.getElementById('ms-shadows-color'), sample = document.getElementById('ms-shadows-sample');
        function rgb(h) { h = h.replace('#', ''); return parseInt(h.substr(0, 2), 16) + ' ' + parseInt(h.substr(2, 2), 16) + ' ' + parseInt(h.substr(4, 2), 16); }
        var sw = document.getElementById('ms-shadows-stroke'), sc = document.getElementById('ms-shadows-stroke-color');
        function apply() {
            var k = on.checked ? st.value / 100 : 0, w = +sw.value;
            st.nextElementSibling.textContent = st.value + '%';
            sw.nextElementSibling.textContent = w ? w + ' px' : 'none';
            sample.style.boxShadow = '0 0 0.8rem rgb(' + rgb(col.value) + ' / ' + (0.5 * k) + ')';
            sample.style.outline = w + 'px solid ' + sc.value;
            var doc; try { doc = iframe && iframe.contentDocument; } catch (e) {}
            if (doc && doc.documentElement) {
                var r = doc.documentElement.style;
                r.setProperty('--ms-shadow-rgb', rgb(col.value)); r.setProperty('--ms-shadow-k', k);
                r.setProperty('--ms-stroke-w', w + 'px'); r.setProperty('--ms-stroke-c', sc.value);
            }
        }
        document.getElementById('ms-shadows-black').addEventListener('click', function () { col.value = '#000000'; apply(); });
        t.addEventListener('input', apply); t.addEventListener('change', apply);
        if (iframe) iframe.addEventListener('load', apply);
        apply();
    })();
    </script>
    <?php
}

function monospace_save_shadows_settings() {
    if ( empty( $_POST['ms_shadows']['seen'] ) ) return;
    $in = wp_unslash( (array) $_POST['ms_shadows'] );
    $s  = [ 'on' => empty( $in['on'] ) ? 'no' : 'yes', 'strength' => max( 0, min( 200, (int) ( $in['strength'] ?? 100 ) ) ), 'color' => sanitize_hex_color( $in['color'] ?? '' ) ?: '#000000',
            'stroke' => max( 0, min( 6, (int) ( $in['stroke'] ?? 0 ) ) ), 'stroke_color' => sanitize_hex_color( $in['stroke_color'] ?? '' ) ?: '#000000' ];
    $s == monospace_shadows_defaults() ? delete_option( 'monospace_shadows' ) : update_option( 'monospace_shadows', $s );
}

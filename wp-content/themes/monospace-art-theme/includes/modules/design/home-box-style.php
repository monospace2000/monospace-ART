<?php
/**
 * Design module: Home page boxes - the style of the link boxes (and the boxed text boxes, like News) on the
 * home page (2026-10-21, owner: "black gloss, color gloss, solid color, outline, shadow/noshadow, font
 * size/family/color").
 *
 *   Title bar   Black gloss (the theme's own: black bar with the white highlight) / Color gloss (the same in a
 *               color) / Solid color (flat, no highlight) / Outline (no bar - a line in the color round the box)
 *               / Solid color + outline (2026-10-21: the solid bar with a line in the same color round the box)
 *   Color      the color of the bar or the outline
 *   Shadow      on / off
 *   Font        the font of the title and a link box's description (default: the headings font / the body font),
 *               the size (the description grows with the title), the title's color (default: white, or black
 *               on a light bar)
 *   Rounding    the corners (2026-10-21) - unset = site-wide (Look > Rounding, rounding.php), else the theme's 10px
 * All boxes: option monospace_home_box_style, edited in Appearance > Look (and the Layout Manager's Appearance &
 * Effects) through the design-module registry. One box: its 'style' in monospace_home_layout (Layout Manager >
 * Home Page, the box's Style fields) - only the parts set there; '' = as all boxes. Plain text boxes (Welcome)
 * have no bar or shadow and are left alone.
 *
 * Dark look per box (2026-10-26, owner: "link the configuration of the Home Page boxes to Light and Dark themes"): a box's
 * 'dark_style' = the same parts, only for the dark look, over the box's own 'style' (Layout Manager > Home Page, the box's
 * Dark mode section: "Same as light" or its own). Dark look = the dark global style < the box's 'style' < its 'dark_style'.
 *
 * Output (monospace_home_box_style_attrs(), used by home-boxes.php): classes hb-<bar> / hb-noshadow and the CSS
 * variables --hb-bar / --hb-line / --hb-font / --hb-size / --hb-desc / --hb-text / --hb-radius on the box - nothing at all for the theme's
 * own look, so the page is as before. The CSS is in _start_template.scss.
 *
 * Live preview (2026-10-21): in Appearance > Look's preview frame (?ms_spot_preview=1) each styled box also carries
 * data-hb-own (its own parts, JSON); the panel's script restyles the boxes there as the fields change - the same
 * rules as monospace_home_box_style_attrs(), in JS. Plain text boxes (no bar, no shadow) carry none and stay as they are.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_HOME_BOX_STYLE = 'monospace_home_box_style';

/** The title bar styles: key => label */
function monospace_home_box_bars() {
    return [ 'black-gloss' => 'Black gloss', 'color-gloss' => 'Color gloss', 'solid' => 'Solid color', 'solid-outline' => 'Solid color + outline', 'outline' => 'Outline' ];
}

/** Text sizes: key => [ label, the title's size, the description's ] (m = the theme's own) */
function monospace_home_box_sizes() {
    return [ 's' => [ 'Small', '1.1rem', '0.8rem' ], 'm' => [ 'Normal', '1.3rem', '0.9rem' ], 'l' => [ 'Large', '1.6rem', '1.05rem' ], 'xl' => [ 'Extra large', '2rem', '1.25rem' ] ];
}

function monospace_home_box_style_defaults() {
    return [ 'bar' => 'black-gloss', 'color' => '#2b5d8a', 'shadow' => 'on', 'font' => '', 'size' => 'm', 'text' => '', 'radius' => '' ];
}

/**
 * Clean up a style. $partial (one box): only the parts set, '' = as all boxes. Else (all boxes): every part,
 * defaults for anything missing ('font' and 'text' may stay '' = the theme's own).
 */
function monospace_home_box_style_sanitize( $in, $partial = false ) {
    $in    = (array) $in;
    $fonts = function_exists( 'monospace_customizer_fonts' ) ? monospace_customizer_fonts() : [];
    $out   = [
        'bar'    => isset( monospace_home_box_bars()[ $in['bar'] ?? '' ] ) ? $in['bar'] : '',
        'color'  => (string) sanitize_hex_color( $in['color'] ?? '' ),
        'shadow' => in_array( $in['shadow'] ?? '', [ 'on', 'off' ], true ) ? $in['shadow'] : '',
        'font'   => isset( $fonts[ $in['font'] ?? '' ] ) ? $in['font'] : '',
        'size'   => isset( monospace_home_box_sizes()[ $in['size'] ?? '' ] ) ? $in['size'] : '',
        'text'   => (string) sanitize_hex_color( $in['text'] ?? '' ),
        'radius' => isset( $in['radius'] ) && is_numeric( $in['radius'] ) ? (string) max( 0, min( 30, (int) $in['radius'] ) ) : '',
    ];
    if ( $partial ) return array_filter( $out, 'strlen' );
    $d = monospace_home_box_style_defaults();
    foreach ( [ 'bar', 'color', 'shadow', 'size' ] as $k ) if ( $out[ $k ] === '' ) $out[ $k ] = $d[ $k ];
    return $out;
}

/** The style of all boxes */
function monospace_home_box_style() {
    return monospace_home_box_style_sanitize( get_option( MONOSPACE_HOME_BOX_STYLE, [] ) );
}

/** One box's style: all boxes' style with the box's own parts over it */
function monospace_home_box_style_of( $box, $dark = false ) {
    return array_merge( monospace_home_box_style(), monospace_home_box_style_own( $box, $dark ) );
}

/** The parts a box sets itself; $dark: with its dark look's parts over them */
function monospace_home_box_style_own( $box, $dark = false ) {
    $own = monospace_home_box_style_sanitize( $box['style'] ?? [], true );
    return $dark ? array_merge( $own, monospace_home_box_style_sanitize( $box['dark_style'] ?? [], true ) ) : $own;
}

/** A box's classes (with a leading space), inline CSS variables - both '' for the theme's own look - and, in the Look
 *  screen's preview only, its own style for the live preview ('data', an attribute with a leading space) */
function monospace_home_box_style_attrs( $box ) {
    $a = monospace_home_box_style_attrs_one( $box );
    // Dark mode on: the dark look's style beside it - hbd-* classes, --hbd-* variables (dark-mode.php, _start_template.scss)
    if ( function_exists( 'monospace_dark_split' ) && monospace_dark_split() ) {
        $d = monospace_dark_with( fn() => monospace_home_box_style_attrs_one( $box, true ) );
        $a['class'] .= str_replace( ' hb-', ' hbd-', $d['class'] );
        $a['style'] .= str_replace( '--hb-', '--hbd-', $d['style'] );
    }
    return $a;
}

/** One look's classes and variables for a box (monospace_home_box_style_attrs() puts light and dark together) */
function monospace_home_box_style_attrs_one( $box, $dark = false ) {
    $dark  = $dark || ( function_exists( 'monospace_dark_preview' ) && monospace_dark_preview() ); // the Look screen's dark preview is made as the dark look
    $plain = ( $box['type'] ?? '' ) === 'content' && ( $box['look'] ?? '' ) === 'plain';
    $data  = isset( $_GET['ms_spot_preview'] ) && current_user_can( 'edit_theme_options' ) && ! $plain
        ? " data-hb-own='" . esc_attr( wp_json_encode( (object) monospace_home_box_style_own( $box, $dark ) ) ) . "'" : '';
    if ( $plain ) return [ 'class' => '', 'style' => '', 'data' => '' ];
    $s = monospace_home_box_style_of( $box, $dark );
    $d = monospace_home_box_style_defaults();
    $class = $style = '';
    if ( $s['bar'] !== 'black-gloss' ) {
        $class .= ' hb-' . $s['bar'];
        if ( $s['bar'] !== 'outline' ) $style .= '--hb-bar:' . ( $s['bar'] === 'color-gloss' ? monospace_home_box_rgba( $s['color'], .85 ) : $s['color'] ) . ';';
        if ( in_array( $s['bar'], [ 'outline', 'solid-outline' ], true ) ) $style .= '--hb-line:' . $s['color'] . ';';
    }
    if ( $s['shadow'] === 'off' ) $class .= ' hb-noshadow';
    $fonts = function_exists( 'monospace_customizer_fonts' ) ? monospace_customizer_fonts() : [];
    if ( $s['font'] !== '' && isset( $fonts[ $s['font'] ] ) ) $style .= '--hb-font:' . $fonts[ $s['font'] ][0] . ';';
    if ( $s['size'] !== $d['size'] ) $style .= '--hb-size:' . monospace_home_box_sizes()[ $s['size'] ][1] . ';--hb-desc:' . monospace_home_box_sizes()[ $s['size'] ][2] . ';';
    // Text color: your own, else black on a light bar (white everywhere else, the theme's own)
    $text = $s['text'] ?: ( in_array( $s['bar'], [ 'color-gloss', 'solid', 'solid-outline' ], true ) && function_exists( 'monospace_look_text_on' ) && monospace_look_text_on( $s['color'] ) !== '#fff' ? '#111' : '' );
    if ( $text !== '' ) $style .= '--hb-text:' . $text . ';';
    if ( $s['radius'] !== '' ) $style .= '--hb-radius:' . (int) $s['radius'] . 'px;';
    return [ 'class' => $class, 'style' => $style, 'data' => $data ];
}

function monospace_home_box_rgba( $hex, $alpha ) {
    $h = ltrim( $hex, '#' );
    return 'rgba(' . hexdec( substr( $h, 0, 2 ) ) . ',' . hexdec( substr( $h, 2, 2 ) ) . ',' . hexdec( substr( $h, 4, 2 ) ) . ',' . $alpha . ')';
}

// The Google Fonts a box's font needs (the theme's own fonts are loaded already) - on the home page only
add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_front_page() || ! function_exists( 'monospace_customizer_fonts' ) || ! function_exists( 'monospace_home_layout' ) ) return;
    $fonts = monospace_customizer_fonts();
    $need  = [];
    foreach ( monospace_home_layout() as $row ) foreach ( $row['boxes'] as $box ) {
        foreach ( [ false, true ] as $dark ) {
            $font = monospace_home_box_style_of( $box, $dark )['font'];
            if ( $font !== '' && ! empty( $fonts[ $font ][1] ) ) $need[ $font ] = $fonts[ $font ][1];
        }
    }
    if ( $need ) wp_enqueue_style( 'monospace-home-box-fonts', 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $need ) . '&display=swap', [], null );
}, 21 );

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['home-box-style'] = [
        'title'       => 'Home page boxes',
        'description' => 'The title bar, shadow and text of the home page link boxes - one box can differ (Layout Manager > Home Page).',
        'group'       => 'look',
        'order'       => 40,
        'summary'     => function () {
            $s = monospace_home_box_style();
            return monospace_home_box_bars()[ $s['bar'] ] . ( $s['shadow'] === 'off' ? ', no shadow' : '' ) . ( $s['radius'] !== '' ? ', rounding ' . $s['radius'] . 'px' : '' );
        },
        'render'      => 'monospace_render_home_box_style',
        'save'        => 'monospace_save_home_box_style',
    ];
    return $modules;
} );

function monospace_render_home_box_style() {
    $s     = monospace_home_box_style();
    $fonts = function_exists( 'monospace_customizer_fonts' ) ? array_keys( monospace_customizer_fonts() ) : [];
    $own   = $s['text'] !== ''; ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Title bar</th>
            <td>
                <?php foreach ( monospace_home_box_bars() as $key => $label ) : ?>
                    <label style="display:inline-block;margin:0 16px 6px 0"><input type="radio" name="hbox[bar]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['bar'], $key ); ?>> <?php echo esc_html( $label ); ?></label>
                <?php endforeach; ?>
                <p class="description">Black gloss is the theme's own. Outline: no bar - a line in the color around the box.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="ms-hbox-color">Color</label></th>
            <td><input type="color" id="ms-hbox-color" name="hbox[color]" value="<?php echo esc_attr( $s['color'] ); ?>">
                <span class="description">The bar's color, and the outline's</span></td>
        </tr>
        <tr>
            <th scope="row">Shadow</th>
            <td><label><input type="hidden" name="hbox[shadow]" value="off"><input type="checkbox" name="hbox[shadow]" value="on" <?php checked( $s['shadow'], 'on' ); ?>> A soft shadow under each box</label></td>
        </tr>
        <tr>
            <th scope="row"><label for="ms-hbox-font">Font</label></th>
            <td><select id="ms-hbox-font" name="hbox[font]">
                    <option value="">The theme's own (headings font)</option>
                    <?php foreach ( $fonts as $f ) : ?><option value="<?php echo esc_attr( $f ); ?>" <?php selected( $s['font'], $f ); ?>><?php echo esc_html( $f ); ?></option><?php endforeach; ?>
                </select></td>
        </tr>
        <tr>
            <th scope="row"><label for="ms-hbox-size">Text size</label></th>
            <td><select id="ms-hbox-size" name="hbox[size]">
                    <?php foreach ( monospace_home_box_sizes() as $key => [ $label ] ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['size'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                </select>
                <span class="description">The title; the description grows with it</span></td>
        </tr>
        <tr>
            <th scope="row">Title color</th>
            <td><label><input type="checkbox" id="ms-hbox-text-on" <?php checked( $own ); ?>> Own color</label>
                <input type="color" id="ms-hbox-text" value="<?php echo esc_attr( $s['text'] ?: '#ffffff' ); ?>">
                <input type="hidden" name="hbox[text]" id="ms-hbox-text-val" value="<?php echo esc_attr( $s['text'] ); ?>">
                <p class="description">Otherwise white - or black on a light bar.</p></td>
        </tr>
        <tr>
            <th scope="row"><label for="ms-hbox-radius">Rounding</label></th>
            <td><?php if ( function_exists( 'monospace_rounding_select' ) ) monospace_rounding_select( 'hbox[radius]', 'ms-hbox-radius', $s['radius'], 'Site-wide (Rounding panel)' ); ?>
                <span class="description">The corners; without the Rounding panel's site-wide value, the theme's 10px</span></td>
        </tr>
    </table>
    <script>
    (function () {
        var on = document.getElementById('ms-hbox-text-on'), pick = document.getElementById('ms-hbox-text'), val = document.getElementById('ms-hbox-text-val');
        function sync() { pick.disabled = !on.checked; val.value = on.checked ? pick.value : ''; }
        on.addEventListener('change', sync); pick.addEventListener('input', sync); sync();

        // Live preview: the Look screen's frame (none on the Layout Manager's tab) - the boxes restyled as the fields
        // change, each box's own parts over these (monospace_home_box_style_attrs(), the same rules)
        var iframe = document.getElementById('ms-spot-iframe'), table = on.closest('table');
        if (!iframe || !table) return;
        var FONTS = <?php echo wp_json_encode( function_exists( 'monospace_customizer_fonts' ) ? monospace_customizer_fonts() : new stdClass() ); ?>,
            SIZES = <?php echo wp_json_encode( monospace_home_box_sizes() ); ?>;
        function field(n) { return table.querySelector('[name="hbox[' + n + ']"]'); }
        function all() {
            var bar = table.querySelector('[name="hbox[bar]"]:checked');
            return { bar: bar ? bar.value : 'black-gloss', color: field('color').value, shadow: table.querySelector('[name="hbox[shadow]"][type=checkbox]').checked ? 'on' : 'off',
                     font: field('font').value, size: field('size').value, text: on.checked ? pick.value : '', radius: field('radius') ? field('radius').value : '' };
        }
        function rgba(hex, a) { var h = hex.replace('#', ''); return 'rgba(' + [0, 2, 4].map(function (i) { return parseInt(h.substr(i, 2), 16); }).join(',') + ',' + a + ')'; }
        function light(hex) { var h = hex.replace('#', ''); return (0.299 * parseInt(h.substr(0, 2), 16) + 0.587 * parseInt(h.substr(2, 2), 16) + 0.114 * parseInt(h.substr(4, 2), 16)) / 255 > 0.62; }
        function preview() {
            var doc; try { doc = iframe.contentDocument; } catch (e) { return; }
            if (!doc || !doc.body) return;
            var base = all();
            doc.querySelectorAll('[data-hb-own]').forEach(function (box) {
                var own = {}; try { own = JSON.parse(box.dataset.hbOwn) || {}; } catch (e) {}
                var s = Object.assign({}, base, own), st = box.style;
                box.className = box.className.split(/\s+/).filter(function (c) { return !/^hb-/.test(c); }).join(' ');
                ['--hb-bar', '--hb-line', '--hb-font', '--hb-size', '--hb-desc', '--hb-text', '--hb-radius'].forEach(function (v) { st.removeProperty(v); });
                if (s.bar !== 'black-gloss') {
                    box.classList.add('hb-' + s.bar);
                    if (s.bar !== 'outline') st.setProperty('--hb-bar', s.bar === 'color-gloss' ? rgba(s.color, .85) : s.color);
                    if (s.bar === 'outline' || s.bar === 'solid-outline') st.setProperty('--hb-line', s.color);
                }
                if (s.shadow === 'off') box.classList.add('hb-noshadow');
                var f = FONTS[s.font];
                if (f) {
                    st.setProperty('--hb-font', f[0]);
                    var id = 'ms-hb-font-' + s.font.replace(/\W/g, '');
                    if (f[1] && !doc.getElementById(id)) { var l = doc.createElement('link'); l.rel = 'stylesheet'; l.id = id; l.href = 'https://fonts.googleapis.com/css2?family=' + f[1] + '&display=swap'; doc.head.appendChild(l); }
                }
                if (s.size !== 'm' && SIZES[s.size]) { st.setProperty('--hb-size', SIZES[s.size][1]); st.setProperty('--hb-desc', SIZES[s.size][2]); }
                var text = s.text || (['color-gloss', 'solid', 'solid-outline'].indexOf(s.bar) > -1 && light(s.color) ? '#111' : '');
                if (text) st.setProperty('--hb-text', text);
                if (s.radius !== '' && s.radius != null) st.setProperty('--hb-radius', s.radius + 'px');
            });
        }
        table.addEventListener('input', preview);
        table.addEventListener('change', preview);
        iframe.addEventListener('load', preview);
        preview();
    })();
    </script>
    <?php
}

function monospace_save_home_box_style() {
    if ( ! isset( $_POST['hbox'] ) ) return;
    $s = monospace_home_box_style_sanitize( wp_unslash( (array) $_POST['hbox'] ) );
    $s == monospace_home_box_style_defaults() ? delete_option( MONOSPACE_HOME_BOX_STYLE ) : update_option( MONOSPACE_HOME_BOX_STYLE, $s );
}

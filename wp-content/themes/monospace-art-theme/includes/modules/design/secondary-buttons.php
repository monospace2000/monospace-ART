<?php
/**
 * Design module: Secondary buttons - the quieter buttons: Older / Newer on posts, the sidebar menus' links, "More
 * posts", the calendar's arrows (2026-10-22, owner: "There's a gray inverted/indented secondary button style as well,
 * also make that editable").
 *
 *   Style    inset (the theme's gray, pressed in - the default) | flat (a solid color) | outline (a colored ring)
 *   Color   empty = the theme's gray (inset) / a mid gray (flat, outline)
 *   Text     empty = automatic: black or white by contrast on Flat, the color itself on Inset and Outline
 * Option monospace_secondary_buttons [ style, color, text ]. Printed as --nb-text / --nb-bg / --nb-bg-hover /
 * --nb-bg-active / --nb-shadow on :root through monospace_look_style() (and monospace_look_all_css(), so the dark look
 * has its own - its default: lighter tints, _dark-mode.scss); read by the button-flat-nav mixin (_button-styles.scss).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_secondary_buttons() {
    $s = get_option( 'monospace_secondary_buttons', [] );
    $s = is_array( $s ) ? $s : [];
    return [
        'style' => in_array( $s['style'] ?? '', [ 'flat', 'outline' ], true ) ? $s['style'] : 'inset',
        'color' => (string) ( sanitize_hex_color( $s['color'] ?? '' ) ?: '' ),
        'text'  => (string) ( sanitize_hex_color( $s['text'] ?? '' ) ?: '' ),
    ];
}

/** The variables for these settings (none for the theme's own: inset, no colors) */
function monospace_secondary_buttons_vars( $s = null ) {
    $s = $s ?: monospace_secondary_buttons();
    $c = $s['color'];
    $v = [];
    if ( $s['style'] === 'flat' ) {
        $c = $c ?: '#6b6b6b';
        $v = [ '--nb-bg' => $c, '--nb-bg-hover' => "color-mix(in srgb, $c 85%, black)", '--nb-bg-active' => "color-mix(in srgb, $c 75%, black)", '--nb-shadow' => 'none',
               '--nb-text' => $s['text'] ?: ( function_exists( 'monospace_look_text_on' ) ? monospace_look_text_on( $c ) : '#fff' ) ];
    } elseif ( $s['style'] === 'outline' ) {
        $c = $c ?: '#777777';
        $v = [ '--nb-bg' => 'transparent', '--nb-bg-hover' => "color-mix(in srgb, $c 12%, transparent)", '--nb-bg-active' => "color-mix(in srgb, $c 20%, transparent)",
               '--nb-shadow' => "inset 0 0 0 1.5px $c", '--nb-text' => $s['text'] ?: $c ];
    } else {
        if ( $c ) $v = [ '--nb-bg' => "color-mix(in srgb, $c 9%, transparent)", '--nb-bg-hover' => "color-mix(in srgb, $c 17%, transparent)", '--nb-bg-active' => "color-mix(in srgb, $c 4%, transparent)", '--nb-text' => $c ];
        if ( $s['text'] ) $v['--nb-text'] = $s['text'];
    }
    return $v;
}

add_action( 'wp_head', function () {
    $v = monospace_secondary_buttons_vars();
    if ( ! $v || ! function_exists( 'monospace_look_style' ) ) return;
    $c = ''; foreach ( $v as $k => $x ) $c .= $k . ':' . $x . ';';
    monospace_look_style( 'ms-secondary-buttons', ':root{' . $c . '}' ); // scoped when dark mode is on
}, 31 );

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['secondary-buttons'] = [
        'title'       => 'Secondary buttons',
        'description' => 'The quieter buttons - Older / Newer, the sidebar menus, “More posts”: pressed in, flat or outlined, and their color.',
        'group'       => 'look',
        'order'       => 12,
        'summary'     => function () {
            $s = monospace_secondary_buttons();
            return [ 'inset' => 'Inset', 'flat' => 'Flat', 'outline' => 'Outline' ][ $s['style'] ] . ( $s['color'] ? ', ' . $s['color'] : '' );
        },
        'render'      => 'monospace_render_secondary_buttons',
        'save'        => 'monospace_save_secondary_buttons',
    ];
    return $modules;
} );

function monospace_render_secondary_buttons() {
    $s = monospace_secondary_buttons(); ?>
    <table class="form-table" role="presentation" id="ms-nb">
        <tr><th scope="row">Style</th><td>
            <input type="hidden" name="ms_nb[seen]" value="1">
            <?php foreach ( [ 'inset' => 'Inset - pressed in (the theme’s)', 'flat' => 'Flat - a solid color', 'outline' => 'Outline - a colored ring' ] as $k => $label ) : ?>
                <label style="display:block;margin-bottom:4px"><input type="radio" name="ms_nb[style]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['style'], $k ); ?>> <?php echo esc_html( $label ); ?></label>
            <?php endforeach; ?>
        </td></tr>
        <tr><th scope="row">Color</th><td>
            <input type="color" data-k="color" value="<?php echo esc_attr( $s['color'] ?: '#6b6b6b' ); ?>"><input type="hidden" name="ms_nb[color]" value="<?php echo esc_attr( $s['color'] ); ?>">
            <button type="button" class="button-link" data-unset="color">The theme’s gray</button>
        </td></tr>
        <tr><th scope="row">Text</th><td>
            <input type="color" data-k="text" value="<?php echo esc_attr( $s['text'] ?: '#444444' ); ?>"><input type="hidden" name="ms_nb[text]" value="<?php echo esc_attr( $s['text'] ); ?>">
            <button type="button" class="button-link" data-unset="text">Automatic</button>
        </td></tr>
        <tr><th scope="row">Sample</th><td>
            <div id="ms-nb-sample" style="display:inline-flex;gap:10px;padding:14px 18px;background:#f3efe6;border-radius:6px">
                <span class="ms-nb-btn">← Newer</span><span class="ms-nb-btn">Older →</span>
            </div>
        </td></tr>
    </table>
    <style>
        .ms-nb-btn { display: inline-block; padding: 7px 16px; border-radius: 9999px; font: 600 13px/1.4 "Open Sans", sans-serif;
                     color: var(--nb-text, rgba(0, 0, 0, 0.5)); background: var(--nb-bg, rgba(0, 0, 0, 0.05));
                     box-shadow: var(--nb-shadow, inset 0 1px 0 rgba(0, 0, 0, 0.4), inset 0 -1px 0 #fff); }
    </style>
    <script>
    (function () {
        // The sample and Look's preview frame follow the fields (the same values as monospace_secondary_buttons_vars())
        var t = document.getElementById('ms-nb'), iframe = document.getElementById('ms-spot-iframe'), sample = document.getElementById('ms-nb-sample');
        if (!t) return;
        var NAMES = ['--nb-text', '--nb-bg', '--nb-bg-hover', '--nb-bg-active', '--nb-shadow'];
        function textOn(hex) { var h = hex.replace('#', ''), l = (0.299 * parseInt(h.substr(0, 2), 16) + 0.587 * parseInt(h.substr(2, 2), 16) + 0.114 * parseInt(h.substr(4, 2), 16)) / 255; return l > 0.62 ? '#111' : '#fff'; }
        function hidden(k) { return t.querySelector('input[name="ms_nb[' + k + ']"]'); }
        function vars() {
            var style = (t.querySelector('[name="ms_nb[style]"]:checked') || {}).value, c = hidden('color').value, tx = hidden('text').value, v = {};
            if (style === 'flat') { c = c || '#6b6b6b'; v = { '--nb-bg': c, '--nb-bg-hover': 'color-mix(in srgb, ' + c + ' 85%, black)', '--nb-bg-active': 'color-mix(in srgb, ' + c + ' 75%, black)', '--nb-shadow': 'none', '--nb-text': tx || textOn(c) }; }
            else if (style === 'outline') { c = c || '#777777'; v = { '--nb-bg': 'transparent', '--nb-bg-hover': 'color-mix(in srgb, ' + c + ' 12%, transparent)', '--nb-bg-active': 'color-mix(in srgb, ' + c + ' 20%, transparent)', '--nb-shadow': 'inset 0 0 0 1.5px ' + c, '--nb-text': tx || c }; }
            else { if (c) v = { '--nb-bg': 'color-mix(in srgb, ' + c + ' 9%, transparent)', '--nb-bg-hover': 'color-mix(in srgb, ' + c + ' 17%, transparent)', '--nb-bg-active': 'color-mix(in srgb, ' + c + ' 4%, transparent)', '--nb-text': c }; if (tx) v['--nb-text'] = tx; }
            return v;
        }
        function put(el, v) { NAMES.forEach(function (n) { if (v[n]) el.style.setProperty(n, v[n]); else el.style.removeProperty(n); }); }
        function apply() {
            var v = vars(); put(sample, v);
            var doc; try { doc = iframe && iframe.contentDocument; } catch (e) {}
            if (doc && doc.documentElement) put(doc.documentElement, v);
        }
        t.querySelectorAll('input[type=color]').forEach(function (i) { i.addEventListener('input', function () { hidden(i.dataset.k).value = i.value; apply(); }); });
        t.querySelectorAll('[data-unset]').forEach(function (b) { b.addEventListener('click', function () { hidden(b.dataset.unset).value = ''; apply(); }); });
        t.addEventListener('change', apply);
        if (iframe) iframe.addEventListener('load', apply);
        apply();
    })();
    </script>
    <?php
}

function monospace_save_secondary_buttons() {
    if ( empty( $_POST['ms_nb']['seen'] ) ) return;
    $in = wp_unslash( (array) $_POST['ms_nb'] );
    $s  = [ 'style' => in_array( $in['style'] ?? '', [ 'flat', 'outline' ], true ) ? $in['style'] : 'inset',
            'color' => (string) ( sanitize_hex_color( $in['color'] ?? '' ) ?: '' ), 'text' => (string) ( sanitize_hex_color( $in['text'] ?? '' ) ?: '' ) ];
    $s === [ 'style' => 'inset', 'color' => '', 'text' => '' ] ? delete_option( 'monospace_secondary_buttons' ) : update_option( 'monospace_secondary_buttons', $s );
}

<?php
/**
 * Design module: Artwork captions - the pill with an artwork's title under its image (figcaption: on artwork pages, in
 * the Art Feed and on category pages; 2026-10-21, owner: "add styling options for the artwork titles/captions pill").
 *
 *   Shape     Like the cards (follows Look > Cards and Rounding - the theme's own) / Pill (round ends) / Square /
 *             No box (just the text)
 *   Fill      the cards' fill, or your color
 *   Outline   the cards' outline, none, or your color
 *   Text      color (theme: #666), font (theme: the body font), size, italic, bold
 *   Shadow    on / off (theme: off)
 * Option monospace_caption_style (only the parts changed are stored). Output: CSS variables --cap-* on :root
 * (<style id="ms-caption">, plus figcaption a's color when the text color is set - linked captions in the feeds),
 * read by the figcaption rule in _main-feed-images.scss with the theme's own as fallbacks;
 * a Google font loads on every page when chosen. Live preview in Appearance > Look: the panel sets the same variables
 * on the preview frame.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_caption_shapes() {
    return [ 'card' => 'Like the cards', 'pill' => 'Pill (round ends)', 'square' => 'Square corners', 'none' => 'No box - just the text' ];
}

/** Sizes: key => [ label, font size ] (m = the theme's own) */
function monospace_caption_sizes() {
    return [ 's' => [ 'Small', '0.875rem' ], 'm' => [ 'Normal', '1rem' ], 'l' => [ 'Large', '1.2rem' ], 'xl' => [ 'Extra large', '1.45rem' ] ];
}

function monospace_caption_defaults() {
    return [ 'shape' => 'card', 'fill' => '', 'outline' => '', 'text' => '', 'font' => '', 'size' => 'm', 'italic' => 'on', 'bold' => 'on', 'shadow' => 'off' ];
}

function monospace_caption_sanitize( $in ) {
    $in    = (array) $in;
    $d     = monospace_caption_defaults();
    $fonts = function_exists( 'monospace_customizer_fonts' ) ? monospace_customizer_fonts() : [];
    $onoff = fn( $k ) => in_array( $in[ $k ] ?? '', [ 'on', 'off' ], true ) ? $in[ $k ] : $d[ $k ];
    return [
        'shape'   => isset( monospace_caption_shapes()[ $in['shape'] ?? '' ] ) ? $in['shape'] : $d['shape'],
        'fill'    => (string) sanitize_hex_color( $in['fill'] ?? '' ),
        'outline' => ( $in['outline'] ?? '' ) === 'none' ? 'none' : (string) sanitize_hex_color( $in['outline'] ?? '' ),
        'text'    => (string) sanitize_hex_color( $in['text'] ?? '' ),
        'font'    => isset( $fonts[ $in['font'] ?? '' ] ) ? $in['font'] : '',
        'size'    => isset( monospace_caption_sizes()[ $in['size'] ?? '' ] ) ? $in['size'] : $d['size'],
        'italic'  => $onoff( 'italic' ),
        'bold'    => $onoff( 'bold' ),
        'shadow'  => $onoff( 'shadow' ),
    ];
}

function monospace_caption_style() {
    return monospace_caption_sanitize( get_option( 'monospace_caption_style', [] ) );
}

/** The CSS variables for the captions (none for the theme's own look) */
function monospace_caption_vars( $s = null ) {
    $s = $s ?? monospace_caption_style();
    $v = [];
    if ( $s['shape'] === 'pill' ) $v['--cap-radius'] = '999px';
    if ( $s['shape'] === 'square' ) $v['--cap-radius'] = '0';
    if ( $s['shape'] === 'none' ) { $v['--cap-bg'] = 'transparent'; $v['--cap-border-width'] = '0px'; $v['--cap-pad'] = '0'; }
    if ( $s['shape'] !== 'none' ) {
        if ( $s['fill'] !== '' ) $v['--cap-bg'] = $s['fill'];
        if ( $s['outline'] === 'none' ) $v['--cap-border-width'] = '0px';
        elseif ( $s['outline'] !== '' ) $v['--cap-border'] = $s['outline'];
        if ( $s['shadow'] === 'on' ) $v['--cap-shadow'] = '0 1px 6px rgb(var(--ms-shadow-rgb, 0 0 0) / calc(0.3 * var(--ms-shadow-k, 1)))'; // Look > Shadows' color
    }
    if ( $s['text'] !== '' ) $v['--cap-color'] = $s['text'];
    $fonts = function_exists( 'monospace_customizer_fonts' ) ? monospace_customizer_fonts() : [];
    if ( $s['font'] !== '' && isset( $fonts[ $s['font'] ] ) ) $v['--cap-font'] = $fonts[ $s['font'] ][0];
    if ( $s['size'] !== 'm' ) $v['--cap-size'] = monospace_caption_sizes()[ $s['size'] ][1];
    if ( $s['italic'] === 'off' ) $v['--cap-italic'] = 'normal';
    if ( $s['bold'] === 'off' ) $v['--cap-weight'] = 'normal';
    return $v;
}

add_action( 'wp_head', function () {
    $vars = monospace_caption_vars();
    if ( ! $vars ) return;
    $css = '';
    foreach ( $vars as $k => $val ) $css .= $k . ':' . $val . ';';
    // A linked caption (the feeds) has the link color - your text color goes on the link too
    $link = isset( $vars['--cap-color'] ) ? 'figcaption a{color:var(--cap-color)}' : '';
    if ( function_exists( 'monospace_look_style' ) ) monospace_look_style( 'ms-caption', ':root{' . $css . '}' . $link ); // scoped when dark mode is on
    else echo '<style id="ms-caption">:root{' . esc_html( $css ) . '}' . $link . "</style>\n";
}, 30 );

// A Google font for the captions (the theme's own fonts are loaded already)
add_action( 'wp_enqueue_scripts', function () {
    if ( ! function_exists( 'monospace_customizer_fonts' ) ) return;
    $font = monospace_caption_style()['font'];
    $spec = monospace_customizer_fonts()[ $font ][1] ?? '';
    if ( $spec !== '' ) wp_enqueue_style( 'monospace-caption-font', 'https://fonts.googleapis.com/css2?family=' . $spec . '&display=swap', [], null );
}, 21 );

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['caption-style'] = [
        'title'       => 'Artwork captions',
        'description' => 'The pill with an artwork’s title under its image - on its page, in the Art Feed and on category pages.',
        'group'       => 'look',
        'order'       => 42,
        'summary'     => function () {
            $s = monospace_caption_style();
            return monospace_caption_style() == monospace_caption_defaults() ? 'The theme’s own' : monospace_caption_shapes()[ $s['shape'] ];
        },
        'render'      => 'monospace_render_caption_style',
        'save'        => 'monospace_save_caption_style',
    ];
    return $modules;
} );

function monospace_render_caption_style() {
    $s     = monospace_caption_style();
    $fonts = function_exists( 'monospace_customizer_fonts' ) ? monospace_customizer_fonts() : [];
    // A color with an "own color" switch: hidden field = '' unless switched on
    $color = function ( $key, $label, $value, $fallback, $extra = '' ) { ?>
        <tr>
            <th scope="row"><?php echo esc_html( $label ); ?></th>
            <td class="ms-cap-color">
                <label><input type="checkbox" class="ms-cap-on" <?php checked( $value !== '' && $value !== 'none' ); ?>> Own color</label>
                <input type="color" class="ms-cap-pick" value="<?php echo esc_attr( $value !== '' && $value !== 'none' ? $value : $fallback ); ?>">
                <input type="hidden" class="ms-cap-val" name="caption[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>">
                <?php echo $extra; // phpcs:ignore -- fixed markup ?>
            </td>
        </tr>
    <?php }; ?>
    <table class="form-table" role="presentation" id="ms-caption-table">
        <tr>
            <th scope="row">Shape</th>
            <td><?php foreach ( monospace_caption_shapes() as $key => $label ) : ?>
                    <label style="display:inline-block;margin:0 16px 6px 0"><input type="radio" name="caption[shape]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['shape'], $key ); ?>> <?php echo esc_html( $label ); ?></label>
                <?php endforeach; ?>
                <p class="description">Like the cards = the theme's own: the Cards panel's fill, outline and rounding.</p></td>
        </tr>
        <?php $color( 'fill', 'Fill', $s['fill'], '#ffffff', '<span class="description">otherwise the cards’ fill</span>' );
        $color( 'outline', 'Outline', $s['outline'], '#cccccc', '<label style="margin-left:12px"><input type="checkbox" class="ms-cap-none" ' . checked( $s['outline'], 'none', false ) . '> No outline</label>' );
        $color( 'text', 'Text color', $s['text'], '#666666', '<span class="description">theme: gray #666</span>' ); ?>
        <tr>
            <th scope="row"><label for="ms-cap-font">Font</label></th>
            <td><select id="ms-cap-font" name="caption[font]"><option value="">The body font (theme’s own)</option>
                    <?php foreach ( array_keys( $fonts ) as $f ) : ?><option value="<?php echo esc_attr( $f ); ?>" <?php selected( $s['font'], $f ); ?>><?php echo esc_html( $f ); ?></option><?php endforeach; ?>
                </select></td>
        </tr>
        <tr>
            <th scope="row"><label for="ms-cap-size">Size</label></th>
            <td><select id="ms-cap-size" name="caption[size]">
                    <?php foreach ( monospace_caption_sizes() as $key => [ $label ] ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['size'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                </select></td>
        </tr>
        <tr>
            <th scope="row">Text</th>
            <td><?php foreach ( [ 'italic' => 'Italic', 'bold' => 'Bold' ] as $key => $label ) : ?>
                    <label style="margin-right:16px"><input type="hidden" name="caption[<?php echo $key; ?>]" value="off"><input type="checkbox" name="caption[<?php echo $key; ?>]" value="on" <?php checked( $s[ $key ], 'on' ); ?>> <?php echo $label; ?></label>
                <?php endforeach; ?></td>
        </tr>
        <tr>
            <th scope="row">Shadow</th>
            <td><label><input type="hidden" name="caption[shadow]" value="off"><input type="checkbox" name="caption[shadow]" value="on" <?php checked( $s['shadow'], 'on' ); ?>> A soft shadow under the caption</label></td>
        </tr>
    </table>
    <script>
    (function () {
        var table = document.getElementById('ms-caption-table');
        // Own color switches -> the hidden values
        function sync() {
            table.querySelectorAll('.ms-cap-color').forEach(function (td) {
                var on = td.querySelector('.ms-cap-on'), pick = td.querySelector('.ms-cap-pick'), val = td.querySelector('.ms-cap-val'), none = td.querySelector('.ms-cap-none');
                if (none && none.checked) on.checked = false;
                pick.disabled = !on.checked;
                val.value = none && none.checked ? 'none' : (on.checked ? pick.value : '');
            });
        }
        table.addEventListener('change', function (e) { if (e.target.classList.contains('ms-cap-on') && e.target.checked) { var n = e.target.closest('td').querySelector('.ms-cap-none'); if (n) n.checked = false; } sync(); });
        table.addEventListener('input', sync);
        sync();

        // Live preview: the Look screen's frame (none on the Layout Manager's tab) - the same variables as monospace_caption_vars()
        var iframe = document.getElementById('ms-spot-iframe');
        if (!iframe) return;
        var FONTS = <?php echo wp_json_encode( $fonts ?: new stdClass() ); ?>, SIZES = <?php echo wp_json_encode( monospace_caption_sizes() ); ?>,
            NAMES = ['--cap-radius', '--cap-bg', '--cap-border-width', '--cap-border', '--cap-pad', '--cap-shadow', '--cap-color', '--cap-font', '--cap-size', '--cap-italic', '--cap-weight'];
        function val(n) { var el = table.querySelector('[name="caption[' + n + ']"]:checked') || table.querySelector('[name="caption[' + n + ']"]:not([type=hidden]):not([type=checkbox]):not([type=radio])') || table.querySelector('.ms-cap-val[name="caption[' + n + ']"]'); return el ? el.value : ''; }
        function box(n) { var el = table.querySelector('[name="caption[' + n + ']"][type=checkbox]'); return el && el.checked ? 'on' : 'off'; }
        function preview() {
            var doc; try { doc = iframe.contentDocument; } catch (e) { return; }
            if (!doc || !doc.documentElement) return;
            var old = doc.getElementById('ms-caption'); if (old) old.remove();
            var root = doc.documentElement.style, v = {}, shape = val('shape'), fill = val('fill'), outline = val('outline'), text = val('text'), font = val('font'), size = val('size');
            if (shape === 'pill') v['--cap-radius'] = '999px';
            if (shape === 'square') v['--cap-radius'] = '0';
            if (shape === 'none') { v['--cap-bg'] = 'transparent'; v['--cap-border-width'] = '0px'; v['--cap-pad'] = '0'; }
            else {
                if (fill) v['--cap-bg'] = fill;
                if (outline === 'none') v['--cap-border-width'] = '0px'; else if (outline) v['--cap-border'] = outline;
                if (box('shadow') === 'on') v['--cap-shadow'] = '0 1px 6px rgb(var(--ms-shadow-rgb, 0 0 0) / calc(0.3 * var(--ms-shadow-k, 1)))';
            }
            if (text) v['--cap-color'] = text;
            if (FONTS[font]) {
                v['--cap-font'] = FONTS[font][0];
                var id = 'ms-cap-font-' + font.replace(/\W/g, '');
                if (FONTS[font][1] && !doc.getElementById(id)) { var l = doc.createElement('link'); l.rel = 'stylesheet'; l.id = id; l.href = 'https://fonts.googleapis.com/css2?family=' + FONTS[font][1] + '&display=swap'; doc.head.appendChild(l); }
            }
            if (size !== 'm' && SIZES[size]) v['--cap-size'] = SIZES[size][1];
            if (box('italic') === 'off') v['--cap-italic'] = 'normal';
            if (box('bold') === 'off') v['--cap-weight'] = 'normal';
            NAMES.forEach(function (n) { v[n] ? root.setProperty(n, v[n]) : root.removeProperty(n); });
            var live = doc.getElementById('ms-caption-live');
            if (!live) { live = doc.createElement('style'); live.id = 'ms-caption-live'; doc.head.appendChild(live); }
            live.textContent = text ? 'figcaption a{color:var(--cap-color)}' : '';
        }
        table.addEventListener('input', preview);
        table.addEventListener('change', preview);
        iframe.addEventListener('load', preview);
        preview();
    })();
    </script>
    <?php
}

function monospace_save_caption_style() {
    if ( ! isset( $_POST['caption'] ) ) return;
    $s = monospace_caption_sanitize( wp_unslash( (array) $_POST['caption'] ) );
    $s == monospace_caption_defaults() ? delete_option( 'monospace_caption_style' ) : update_option( 'monospace_caption_style', $s );
}

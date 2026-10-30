<?php
/**
 * Design module: Logo - the two-part logo (header.php: "monospace" + ART) and the tagline under it, set apart for
 * computers & tablets ("wide") and phones (640px and narrower) (2026-10-21, owner: "Logo and tagline/linebreaks and
 * stacking option should be separate for desktop and mobile"). For each:
 *   Logo            one line ("monospace" with ART raised at its right) or stacked (ART under "monospace"); stacked, ART
 *                   has its own size (% of the column) and alignment (owner: "when stacked allow me to set the size of the
 *                   word ART ... size and alignment") - option monospace_logo_art [ wide|phone => [ size, align ] ],
 *                   printed as --ms-art-w-* / --ms-art-align-* on .ms-logo (header.php)
 *   Tagline lines   as typed - a typed \n in Settings > General > Tagline starts a new line - or all on one line
 *   Alignment       of the shorter lines: left / center / right (the longest spans the logo's width)
 *   Color          (2026-10-26, owner: "allow me to change the color of the monospace ART logo") each part's own color, option
 *                   monospace_logo_colors [ word, art ] (hex; not set = black on the light look, white on the dark one). The logo is
 *                   a black picture, so the color is an SVG filter (feColorMatrix, one per color, printed after <body> opens:
 *                   monospace_logo_filters()) named by --ms-logo-word-f / --ms-logo-art-f; light and dark differ like any look
 *                   setting (the panel has the light / dark link; monospace_look_all_css()).
 * Options (not set = the first choice): monospace_logo_desktop / monospace_logo_mobile ('stack'),
 * monospace_tagline_breaks / monospace_tagline_breaks_mobile ('one' = one line), monospace_tagline_align /
 * monospace_tagline_align_mobile ('center' / 'right'; the phones' not set = as on wider screens).
 * header.php: .ms-logo gets is-stacked-wide / is-stacked; the tagline is one SVG (monospace_tagline_html()), or one for
 * each when they differ (.site-tagline--wide / --phone). CSS: _header.scss. Everywhere else (page titles, feeds, search
 * results) the \n reads as a space. Edited in Appearance > Look (live preview: classes and alignment switched in the
 * preview frame), with a small preview of its own in the panel - logo + tagline for both screen sizes - so it works on the
 * Layout Manager's old Appearance & Effects tab too (owner: "I need a live preview on that tab. Also let me change the
 * tagline there"). The panel's Tagline field saves the site's tagline (blogdescription - wording, not in saved looks).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Phones: stacked? (the first option - kept for the About page and older callers) */
function monospace_logo_stacked() {
    return get_option( 'monospace_logo_mobile', '' ) === 'stack';
}

/** The settings for one kind of screen: 'wide' (computers & tablets) or 'phone' */
function monospace_logo_settings( $device ) {
    $phone = $device === 'phone';
    $align = get_option( $phone ? 'monospace_tagline_align_mobile' : 'monospace_tagline_align', '' );
    $all   = (array) get_option( 'monospace_logo_art', [] );
    $art   = (array) ( $all[ $device ] ?? [] );
    if ( $phone && $align === '' ) $align = get_option( 'monospace_tagline_align', '' );
    return [
        'stack'  => get_option( $phone ? 'monospace_logo_mobile' : 'monospace_logo_desktop', '' ) === 'stack',
        'breaks' => get_option( $phone ? 'monospace_tagline_breaks_mobile' : 'monospace_tagline_breaks', '' ) !== 'one',
        'align'  => in_array( $align, [ 'center', 'right' ], true ) ? $align : 'left',
        // Stacked: ART's width (% of the column, 100 = as wide as "monospace") and where it sits
        'art_size'  => max( 20, min( 100, (int) ( $art['size'] ?? 100 ) ) ),
        'art_align' => in_array( $art['align'] ?? '', [ 'left', 'right' ], true ) ? $art['align'] : 'center',
    ];
}

/** ART's size and alignment when stacked, as the variables header.php puts on .ms-logo */
function monospace_logo_art_style() {
    $flex = [ 'left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end' ];
    $css  = '';
    foreach ( [ 'wide', 'phone' ] as $d ) {
        $s = monospace_logo_settings( $d );
        if ( $s['art_size'] !== 100 ) $css .= '--ms-art-w-' . $d . ':' . $s['art_size'] . '%;';
        if ( $s['art_align'] !== 'center' ) $css .= '--ms-art-align-' . $d . ':' . $flex[ $s['art_align'] ] . ';';
    }
    return $css;
}

/** The logo parts' own colors: [ 'word' => '#rrggbb' | '', 'art' => ... ] ('' = the default: black, white in the dark look) */
function monospace_logo_colors() {
    $c = (array) get_option( 'monospace_logo_colors', [] );
    return [ 'word' => sanitize_hex_color( $c['word'] ?? '' ) ?: '', 'art' => sanitize_hex_color( $c['art'] ?? '' ) ?: '' ];
}

function monospace_logo_filter_id( $hex ) {
    return 'ms-logo-c-' . ltrim( strtolower( $hex ), '#' );
}

/** The color variables (look CSS): --ms-logo-word-f / --ms-logo-art-f = url(#filter) for a part with its own color */
function monospace_logo_color_vars() {
    $v = [];
    foreach ( monospace_logo_colors() as $part => $hex ) if ( $hex !== '' ) $v[ '--ms-logo-' . $part . '-f' ] = 'url(#' . monospace_logo_filter_id( $hex ) . ')';
    return $v;
}

/** One filter: every pixel becomes $hex, the picture's transparency stays */
function monospace_logo_filter_def( $hex ) {
    $h = ltrim( $hex, '#' );
    $n = fn( $i ) => round( hexdec( substr( $h, $i, 2 ) ) / 255, 4 );
    return '<filter id="' . esc_attr( monospace_logo_filter_id( $hex ) ) . '" color-interpolation-filters="sRGB"><feColorMatrix type="matrix" values="0 0 0 0 ' . $n( 0 ) . ' 0 0 0 0 ' . $n( 2 ) . ' 0 0 0 0 ' . $n( 4 ) . ' 0 0 0 1 0"/></filter>';
}

/** The filters of the light and the dark look (the colors in use), as a hidden SVG */
function monospace_logo_filters() {
    $all = array_filter( monospace_logo_colors() );
    if ( function_exists( 'monospace_dark_split' ) && monospace_dark_split() ) $all = array_merge( $all, array_filter( monospace_dark_with( 'monospace_logo_colors' ) ) );
    $all = array_unique( array_map( 'strtolower', $all ) );
    if ( ! $all ) return '';
    return '<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><defs>' . implode( '', array_map( 'monospace_logo_filter_def', $all ) ) . '</defs></svg>' . "\n";
}

add_action( 'monospace_body_open', function () { echo monospace_logo_filters(); }, 20 );

add_action( 'wp_head', function () {
    $v = monospace_logo_color_vars();
    if ( ! $v || ! function_exists( 'monospace_look_style' ) ) return;
    $c = ''; foreach ( $v as $k => $x ) $c .= $k . ':' . $x . ';';
    monospace_look_style( 'ms-logo-color', ':root{' . $c . '}' ); // scoped when dark mode is on
}, 31 );

/** Alignment of the tagline's shorter lines (wide screens unless asked for phones) */
function monospace_tagline_align( $device = 'wide' ) {
    return monospace_logo_settings( $device )['align'];
}

/** The tagline's lines (typed \n = a new line); with $device, all on one line when that screen size says so */
function monospace_tagline_lines( $device = null ) {
    $GLOBALS['monospace_tagline_raw'] = true;
    $tagline = (string) get_option( 'blogdescription' );
    unset( $GLOBALS['monospace_tagline_raw'] );
    $lines = array_values( array_filter( array_map( 'trim', explode( '\n', $tagline ) ), 'strlen' ) );
    if ( $device && $lines && ! monospace_logo_settings( $device )['breaks'] ) $lines = [ implode( ' ', $lines ) ];
    return $lines;
}

/**
 * The tagline as SVG text, stretched to the logo's width: the viewBox uses the logo's own units (logo.svg, trimmed to
 * its letters: 0 to 1437.75) and the SVG takes the logo's box (_header.scss), so the text starts and ends where the
 * logo's letters do at any screen size. textLength locks the width. Font size from the longest line (Roboto 200 averages
 * 0.455em per character); the first / last letter's side bearings are allowed for. Shorter lines: the same letters
 * (textLength in proportion to their length), placed left / center / right (all three x's kept for Look's preview).
 */
function monospace_tagline_svg( $lines, $align, $class = '' ) {
    if ( ! $lines ) return '';
    $ink_w   = 1437.75;
    $longest = max( array_map( 'mb_strlen', $lines ) );
    $font    = $ink_w / ( max( 1, $longest ) * 0.455 );
    $pad     = 0.07 * $font;
    $leading = 1.2 * $font;
    $height  = ( 0.75 + 0.213 ) * $font + ( count( $lines ) - 1 ) * $leading + 2 * $pad;
    $full    = $ink_w + 0.132 * $font;
    $x0      = -0.095 * $font;
    $fmt     = fn( $n ) => rtrim( rtrim( number_format( $n, 1, '.', '' ), '0' ), '.' );
    $out     = '<svg class="site-tagline' . ( $class ? ' ' . esc_attr( $class ) : '' ) . '" viewBox="0 0 1437.75 ' . $fmt( $height ) . '" role="img" aria-label="' . esc_attr( implode( ' ', $lines ) ) . '">';
    foreach ( $lines as $i => $line ) {
        $len  = $full * mb_strlen( $line ) / $longest;
        $xs   = [ 'left' => $x0, 'center' => $x0 + ( $full - $len ) / 2, 'right' => $x0 + $full - $len ];
        $data = '';
        if ( $len < $full ) foreach ( $xs as $k => $x ) $data .= ' data-x-' . $k . '="' . $fmt( $x ) . '"';
        $out .= '<text x="' . $fmt( $xs[ $align ] ) . '" y="' . $fmt( $pad + 0.75 * $font + $i * $leading ) . '" font-size="' . $fmt( $font )
              . '" textLength="' . $fmt( $len ) . '" lengthAdjust="spacingAndGlyphs"' . $data . '>' . esc_html( $line ) . '</text>';
    }
    return $out . '</svg>';
}

/** The header's tagline: one SVG, or one for wide screens and one for phones when their lines or alignment differ */
function monospace_tagline_html() {
    $wide  = [ monospace_tagline_lines( 'wide' ), monospace_tagline_align( 'wide' ) ];
    $phone = [ monospace_tagline_lines( 'phone' ), monospace_tagline_align( 'phone' ) ];
    if ( $wide === $phone ) return monospace_tagline_svg( $wide[0], $wide[1] );
    return monospace_tagline_svg( $wide[0], $wide[1], 'site-tagline--wide' ) . monospace_tagline_svg( $phone[0], $phone[1], 'site-tagline--phone' );
}

// Outside the header the \n is a space - but not on the screens that edit it (Settings > General, Customize), or saving
// there would lose it
add_filter( 'option_blogdescription', function ( $v ) {
    if ( ! empty( $GLOBALS['monospace_tagline_raw'] ) || is_admin() || is_customize_preview() || strpos( (string) $v, '\n' ) === false ) return $v;
    return trim( preg_replace( '/\s*\\\\n\s*/', ' ', $v ) );
} );

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['logo-layout'] = [
        'title'       => 'Logo',
        'description' => 'How “monospace ART” and its tagline are laid out - on computers & tablets, and on phones.',
        'group'       => 'look',
        'order'       => 15,
        'summary'     => function () {
            $w = monospace_logo_settings( 'wide' ); $p = monospace_logo_settings( 'phone' );
            return 'Computers: ' . ( $w['stack'] ? 'stacked' : 'one line' ) . ' · Phones: ' . ( $p['stack'] ? 'stacked' : 'one line' ) . ( array_filter( monospace_logo_colors() ) ? ' · own color' : '' );
        },
        'render'      => 'monospace_render_logo_layout',
        'save'        => 'monospace_save_logo_layout',
    ];
    return $modules;
} );

/** A logo part's address: assets/images/logo/<name> - .svg, else .webp, else .png (as header.php) */
function monospace_logo_part_url( $name ) {
    foreach ( [ 'svg', 'webp', 'png' ] as $ext ) {
        $file = '/assets/images/logo/' . $name . '.' . $ext;
        if ( file_exists( get_template_directory() . $file ) ) return get_template_directory_uri() . $file . '?v=' . filemtime( get_template_directory() . $file );
    }
    return '';
}

function monospace_render_logo_layout() {
    $s = [ 'wide' => monospace_logo_settings( 'wide' ), 'phone' => monospace_logo_settings( 'phone' ) ];
    $GLOBALS['monospace_tagline_raw'] = true;
    $tagline = (string) get_option( 'blogdescription' );
    unset( $GLOBALS['monospace_tagline_raw'] );
    $word = monospace_logo_part_url( 'monospace' );
    $art  = monospace_logo_part_url( 'art' );
    $radios = function ( $name, $device, $choices, $now ) {
        foreach ( $choices as $v => $label ) {
            echo '<label style="display:block;margin-bottom:4px"><input type="radio" name="' . esc_attr( $name . '[' . $device . ']' ) . '" value="' . esc_attr( $v ) . '" ' . checked( $now, $v, false ) . '> ' . esc_html( $label ) . '</label>';
        }
    }; ?>
    <p class="ms-logo-tagline"><label for="ms-logo-tagline"><strong>Tagline</strong></label>
        <input type="text" id="ms-logo-tagline" name="logo_tagline" value="<?php echo esc_attr( $tagline ); ?>" class="large-text">
        <span class="description">Under the logo; type <code>\n</code> where a new line starts. Saved with this panel (the same as Settings &gt; General &gt; Tagline).</span></p>
    <?php if ( $word && $art ) : ?>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@200&amp;display=swap"><?php // the tagline's font, as on the site (core/enqueue.php) ?>
    <div class="ms-logo-minis" style="--ms-tagline-ink:<?php echo esc_attr( get_theme_mod( 'ms_color_tagline', '' ) ?: '#000' ); ?>">
        <?php foreach ( [ 'wide' => 'Computers & tablets', 'phone' => 'Phones' ] as $d => $label ) : ?>
            <figure class="ms-logo-mini" data-dev="<?php echo $d; ?>"><figcaption><?php echo esc_html( $label ); ?></figcaption>
                <span class="ms-mini-logo"><img class="ms-mini-word" src="<?php echo esc_url( $word ); ?>" alt=""><img class="ms-mini-art" src="<?php echo esc_url( $art ); ?>" alt=""></span>
                <span class="ms-mini-tagline"></span></figure>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php $lc = monospace_logo_colors(); ?>
    <p class="ms-logo-colors"><strong>Color</strong>
        <?php foreach ( [ 'word' => 'monospace', 'art' => 'ART' ] as $k => $label ) : ?>
            <span class="ms-lc" data-k="<?php echo $k; ?>"><label><?php echo esc_html( $label ); ?>
                <input type="color" value="<?php echo esc_attr( $lc[ $k ] ?: '#000000' ); ?>"></label>
                <input type="hidden" name="logo_color[<?php echo $k; ?>]" value="<?php echo esc_attr( $lc[ $k ] ); ?>">
                <button type="button" class="button-link ms-lc-reset"<?php echo $lc[ $k ] ? '' : ' hidden'; ?>>Default</button></span>
        <?php endforeach; ?>
        <span class="description" style="display:block">Each part of the logo has its own color. Default = black (white on the dark look). This panel follows the Light / Dark look you are editing.</span></p>
    <table class="widefat striped ms-logo-layout" id="ms-logo-layout">
        <thead><tr><th></th><th>Computers &amp; tablets</th><th>Phones</th></tr></thead>
        <tbody>
        <tr><th scope="row">Logo</th>
            <?php foreach ( $s as $d => $v ) : ?><td><?php $radios( 'logo_stack', $d, [ 'line' => 'One line', 'stack' => 'Stacked' ], $v['stack'] ? 'stack' : 'line' ); ?></td><?php endforeach; ?></tr>
        <tr class="ms-art-row"><th scope="row">ART, stacked</th>
            <?php foreach ( $s as $d => $v ) : ?><td>
                <label class="ms-art-size">Size <input type="range" name="logo_art_size[<?php echo $d; ?>]" min="20" max="100" step="5" value="<?php echo (int) $v['art_size']; ?>"> <output><?php echo (int) $v['art_size']; ?>%</output></label>
                <?php $radios( 'logo_art_align', $d, [ 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ], $v['art_align'] ); ?>
            </td><?php endforeach; ?></tr>
        <tr><th scope="row">Tagline lines</th>
            <?php foreach ( $s as $d => $v ) : ?><td><?php $radios( 'tagline_breaks', $d, [ 'typed' => 'As typed (\n)', 'one' => 'One line' ], $v['breaks'] ? 'typed' : 'one' ); ?></td><?php endforeach; ?></tr>
        <tr><th scope="row">Shorter lines</th>
            <?php foreach ( $s as $d => $v ) : ?><td><?php $radios( 'tagline_align', $d, [ 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ], $v['align'] ); ?></td><?php endforeach; ?></tr>
        </tbody>
    </table>
    <p class="description">Type <code>\n</code> in the tagline (<a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>">Settings &gt; General</a>) to start a new line; the longest line spans the logo. One line: ART raised at the right of “monospace”; stacked: ART under it.
        Phones = 640px wide and narrower - pick Phone above the preview to see them. Everything here shows in the preview as you change it.</p>
    <style>
        .ms-logo-layout { table-layout: fixed; width: 100%; }
        .ms-logo-layout thead th:first-child, .ms-logo-layout th[scope=row] { width: 64px; font-weight: 600; }
        .ms-logo-layout td, .ms-logo-layout th { vertical-align: top; padding: 8px 10px; }
        .ms-logo-layout .ms-art-size { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
        .ms-logo-layout .ms-art-size input { flex: 1; min-width: 60px; }
        .ms-logo-layout td.is-off { opacity: 0.45; }
        .ms-logo-tagline { margin: 0 0 10px; }
        .ms-logo-colors { margin: 0 0 12px; }
        .ms-logo-colors .ms-lc { margin-left: 14px; white-space: nowrap; }
        .ms-logo-colors input[type=color] { vertical-align: middle; margin-left: 4px; }
        .ms-logo-tagline input { margin: 4px 0; }
        /* The panel's own preview: the logo as _header.scss draws it (one line: the parts placed in logo.svg's box) */
        .ms-logo-minis { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 0 0 12px; }
        .ms-logo-mini { margin: 0; padding: 10px 12px 12px; background: #fff; border: 1px solid #dcdcde; border-radius: 4px; }
        .ms-logo-mini figcaption { font-size: 11px; color: #646970; margin-bottom: 6px; }
        .ms-logo-mini[data-dev=phone] .ms-mini-logo, .ms-logo-mini[data-dev=phone] .ms-mini-tagline { width: 72%; margin-left: auto; margin-right: auto; }
        .ms-mini-logo { position: relative; display: block; aspect-ratio: 1437.76 / 202.31; margin-bottom: 2%; }
        .ms-mini-logo img { position: absolute; height: auto; mix-blend-mode: multiply; }
        .ms-mini-word { left: 0; bottom: 0; width: 74.63%; }
        .ms-mini-art { left: 77.69%; top: 0; width: 22.33%; }
        .ms-mini-logo.is-stacked { display: flex; flex-direction: column; align-items: center; gap: 4px; aspect-ratio: auto; }
        .ms-mini-logo.is-stacked img { position: static; }
        .ms-mini-logo.is-stacked .ms-mini-word { width: 100%; }
        .ms-mini-tagline { display: block; }
        .ms-mini-tagline svg { display: block; width: 100%; height: auto; overflow: visible; }
        .ms-mini-tagline text { font-family: Roboto, sans-serif; font-weight: 200; fill: var(--ms-tagline-ink, #000); }
        @media (max-width: 1400px) { .ms-logo-minis { grid-template-columns: 1fr; } }
    </style>
    <script>
    (function () {
        // Live preview: the Look screen's frame (none on the Layout Manager's tab)
        var iframe = document.getElementById('ms-spot-iframe'), table = document.getElementById('ms-logo-layout');
        if (!table) return;
        function pick(name, d) { return (table.querySelector('[name="' + name + '[' + d + ']"]:checked') || {}).value; }
        var field = document.getElementById('ms-logo-tagline');
        function raw() { return (field ? field.value : '').split('\\n').map(function (l) { return l.trim(); }).filter(Boolean); }
        function lines(d) { var r = raw(); return r.length && pick('tagline_breaks', d) === 'one' ? [r.join(' ')] : r; }
        // The panel's own preview (works on the Layout Manager's tab too, where there's no preview frame)
        var flex = { left: 'flex-start', center: 'center', right: 'flex-end' };
        function minis() {
            document.querySelectorAll('.ms-logo-mini').forEach(function (m) {
                var d = m.dataset.dev, logo = m.querySelector('.ms-mini-logo'), a = m.querySelector('.ms-mini-art'), stacked = pick('logo_stack', d) === 'stack';
                logo.classList.toggle('is-stacked', stacked);
                a.style.width = stacked ? table.querySelector('[name="logo_art_size[' + d + ']"]').value + '%' : '';
                a.style.alignSelf = stacked ? flex[pick('logo_art_align', d) || 'center'] : '';
                m.querySelector('.ms-mini-tagline').innerHTML = svg(lines(d), pick('tagline_align', d) || 'left', '');
            });
        }
        // monospace_tagline_svg() in JS - the same numbers
        function svg(ls, align, cls) {
            if (!ls.length) return '';
            var esc = function (t) { return t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;'); },
                f = function (n) { return String(Math.round(n * 10) / 10); },
                longest = Math.max.apply(null, ls.map(function (l) { return l.length; })),
                font = 1437.75 / (Math.max(1, longest) * 0.455), pad = 0.07 * font, lead = 1.2 * font,
                h = (0.75 + 0.213) * font + (ls.length - 1) * lead + 2 * pad, full = 1437.75 + 0.132 * font, x0 = -0.095 * font,
                out = '<svg class="site-tagline ' + cls + '" viewBox="0 0 1437.75 ' + f(h) + '" role="img" aria-label="' + esc(ls.join(' ')) + '">';
            ls.forEach(function (l, i) {
                var len = full * l.length / longest, x = { left: x0, center: x0 + (full - len) / 2, right: x0 + full - len }[align];
                out += '<text x="' + f(x) + '" y="' + f(pad + 0.75 * font + i * lead) + '" font-size="' + f(font) + '" textLength="' + f(len) + '" lengthAdjust="spacingAndGlyphs">' + esc(l) + '</text>';
            });
            return out + '</svg>';
        }
        // Colors: a filter in the document that shows the logo (the panel's previews, the Look frame)
        var colors = {};
        function tint(doc, hex) {
            var id = 'ms-logo-c-' + hex.replace('#', '').toLowerCase();
            if (!doc.getElementById(id)) {
                var n = function (i) { return (parseInt(hex.substr(i, 2), 16) / 255).toFixed(4); };
                var host = doc.getElementById('ms-logo-live') || (function () {
                    var s = doc.createElementNS('http://www.w3.org/2000/svg', 'svg'); s.id = 'ms-logo-live'; s.setAttribute('width', 0); s.setAttribute('height', 0); s.style.position = 'absolute';
                    doc.body.appendChild(s); return s; })();
                host.insertAdjacentHTML('beforeend', '<filter xmlns="http://www.w3.org/2000/svg" id="' + id + '" color-interpolation-filters="sRGB"><feColorMatrix type="matrix" values="0 0 0 0 ' + n(1) + ' 0 0 0 0 ' + n(3) + ' 0 0 0 0 ' + n(5) + ' 0 0 0 1 0"/></filter>');
            }
            return 'url(#' + id + ')';
        }
        function readColors() {
            document.querySelectorAll('.ms-lc').forEach(function (s) { colors[s.dataset.k] = s.querySelector('input[type=hidden]').value; });
        }
        function colorPreview(doc) {
            ['word', 'art'].forEach(function (k) {
                var f = colors[k] ? tint(doc, colors[k]) : '';
                doc.querySelectorAll('.ms-mini-' + k + ', .ms-logo-' + k).forEach(function (img) {
                    if (img.classList.contains('ms-mini-' + k)) img.style.filter = f; // the panel's own previews
                    else if (f) { img.style.setProperty('--ms-logo-' + k + '-f', f); img.style.setProperty('--logo-invert', f); img.style.filter = f; }
                    else { img.style.removeProperty('--ms-logo-' + k + '-f'); img.style.removeProperty('--logo-invert'); img.style.filter = ''; }
                });
            });
        }
        document.querySelectorAll('.ms-lc').forEach(function (s) {
            var pick = s.querySelector('input[type=color]'), hid = s.querySelector('input[type=hidden]'), reset = s.querySelector('.ms-lc-reset');
            pick.addEventListener('input', function () { hid.value = pick.value; reset.hidden = false; preview(); });
            reset.addEventListener('click', function () { hid.value = ''; pick.value = '#000000'; reset.hidden = true; preview(); });
        });
        function preview() {
            readColors();
            colorPreview(document);
            minis();
            if (!iframe) return;
            var doc; try { doc = iframe.contentDocument; } catch (e) { return; }
            if (!doc) return;
            colorPreview(doc);
            var flex = { left: 'flex-start', center: 'center', right: 'flex-end' };
            doc.querySelectorAll('.ms-logo').forEach(function (l) {
                l.classList.toggle('is-stacked-wide', pick('logo_stack', 'wide') === 'stack');
                l.classList.toggle('is-stacked', pick('logo_stack', 'phone') === 'stack');
                ['wide', 'phone'].forEach(function (d) {
                    l.style.setProperty('--ms-art-w-' + d, table.querySelector('[name="logo_art_size[' + d + ']"]').value + '%');
                    l.style.setProperty('--ms-art-align-' + d, flex[pick('logo_art_align', d) || 'center']);
                });
            });
            // The tagline, redrawn as the form says - one for wide screens and one for phones (the CSS shows the right one)
            var old = doc.querySelectorAll('.site-tagline');
            if (old.length) {
                var at = old[0];
                at.insertAdjacentHTML('beforebegin', svg(lines('wide'), pick('tagline_align', 'wide') || 'left', 'site-tagline--wide') + svg(lines('phone'), pick('tagline_align', 'phone') || 'left', 'site-tagline--phone'));
                old.forEach(function (o) { o.remove(); });
            }
        }
        // The size's number, and ART's row faded where the logo is on one line
        function form() {
            table.querySelectorAll('.ms-art-size').forEach(function (l) { l.querySelector('output').textContent = l.querySelector('input').value + '%'; });
            table.querySelectorAll('.ms-art-row td').forEach(function (td, i) { td.classList.toggle('is-off', pick('logo_stack', i ? 'phone' : 'wide') !== 'stack'); });
        }
        table.addEventListener('input', function () { form(); preview(); });
        table.addEventListener('change', function () { form(); preview(); });
        if (field) field.addEventListener('input', preview);
        form();
        preview();
        if (iframe) iframe.addEventListener('load', preview);
        document.querySelectorAll('.ms-spot-bar [data-dev]').forEach(function (b) { b.addEventListener('click', function () { setTimeout(preview, 50); }); });
    })();
    </script>
    <?php
}

function monospace_save_logo_layout() {
    if ( ! isset( $_POST['logo_stack'] ) ) return;
    // The tagline (typed \n kept - the option_blogdescription filter leaves wp-admin alone)
    if ( isset( $_POST['logo_tagline'] ) ) {
        $tagline = sanitize_text_field( wp_unslash( $_POST['logo_tagline'] ) );
        $GLOBALS['monospace_tagline_raw'] = true;
        if ( $tagline !== (string) get_option( 'blogdescription' ) ) update_option( 'blogdescription', $tagline );
        unset( $GLOBALS['monospace_tagline_raw'] );
    }
    $in  = fn( $name, $d ) => sanitize_key( wp_unslash( $_POST[ $name ][ $d ] ?? '' ) );
    $set = fn( $option, $on, $value ) => $on ? update_option( $option, $value ) : delete_option( $option );
    $set( 'monospace_logo_desktop', $in( 'logo_stack', 'wide' ) === 'stack', 'stack' );
    $set( 'monospace_logo_mobile', $in( 'logo_stack', 'phone' ) === 'stack', 'stack' );
    $art = [];
    foreach ( [ 'wide', 'phone' ] as $d ) {
        $size  = max( 20, min( 100, (int) ( $_POST['logo_art_size'][ $d ] ?? 100 ) ) );
        $align = $in( 'logo_art_align', $d );
        if ( $size !== 100 || in_array( $align, [ 'left', 'right' ], true ) ) $art[ $d ] = [ 'size' => $size, 'align' => in_array( $align, [ 'left', 'right' ], true ) ? $align : 'center' ];
    }
    $set( 'monospace_logo_art', (bool) $art, $art );
    $colors = [];
    foreach ( [ 'word', 'art' ] as $k ) {
        $hex = sanitize_hex_color( wp_unslash( $_POST['logo_color'][ $k ] ?? '' ) );
        if ( $hex ) $colors[ $k ] = $hex;
    }
    $set( 'monospace_logo_colors', (bool) $colors, $colors );
    $set( 'monospace_tagline_breaks', $in( 'tagline_breaks', 'wide' ) === 'one', 'one' );
    $set( 'monospace_tagline_breaks_mobile', $in( 'tagline_breaks', 'phone' ) === 'one', 'one' );
    foreach ( [ 'wide' => 'monospace_tagline_align', 'phone' => 'monospace_tagline_align_mobile' ] as $d => $option ) {
        $a = $in( 'tagline_align', $d );
        $set( $option, in_array( $a, [ 'center', 'right' ], true ), $a );
    }
}

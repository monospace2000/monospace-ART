<?php
/**
 * Design module: Banners - how a Top content area looks (2026-10-21, owner: "The Top Content area in Layout Manager is
 * ideal for banners and announcements. But I need better styling options for it. Home Page also needs that area.").
 *
 * Styles: plain (just the text, as before) | card (Look > Cards' box) | filled (a color, text black or white by
 * contrast) | outline (a colored line) | stripe (a card with a colored stripe on the left). Plus a color (empty = the
 * buttons' color), alignment (left | center), size (compact | normal | large).
 *   Look > Banners       the site's default - option monospace_banner_style; its color as --banner-color /
 *                        --banner-text on :root (monospace_look_style, so the dark look can have its own)
 *   one style for the site (2026-10-22, owner: "One global banner style with option to override per post/page/category/
 *                        tag"): Look > Banners. The Layout Manager's panels and the Home Page keep only their Show from /
 *                        until (dates) and close button (options monospace_{type}_top_style, monospace_home_top_style -
 *                        a style kept there from before is ignored); a post / page with its own top content (post meta
 *                        _monospace_top_style - sidebar-override.php) or a category / tag (term meta _monospace_top_style -
 *                        term-areas.php) can have its own style, color, alignment and size - plus dates and close.
 * The Home Page's banner (2026-10-30, owner: "homepage banner should have all the styling options") is overridable like a post: its own style,
 * color, alignment, size (option monospace_home_top_style, Layout Manager > Home Page) - empty = as in Look.
 * Rendering: monospace_render_top_content() (sidebar-override.php) calls monospace_banner_wrap(). The close button hides
 * the banner in that browser until its text changes (localStorage ms-banner-<hash>); the dates are checked when the page
 * is made and again in the browser (pages are cached). The font: the site's UI font (Open Sans - Look > Fonts). The CSS: monospace_banner_css() - printed on the site and in the
 * admin's previews, so both look the same.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_banner_choices() {
    return [
        'style' => [ 'plain' => 'Plain - just the text', 'card' => 'Card - a box like the site’s cards', 'filled' => 'Filled - a colored banner', 'outline' => 'Outline - a colored line around', 'stripe' => 'Stripe - a card with a colored edge' ],
        'align' => [ 'left' => 'Left', 'center' => 'Centered' ],
        'size'  => [ 'compact' => 'Compact', 'normal' => 'Normal', 'large' => 'Large' ],
    ];
}

/** Look's default */
function monospace_banner_defaults() {
    $s = get_option( 'monospace_banner_style', [] );
    return monospace_banner_clean( is_array( $s ) ? $s : [], [ 'style' => 'plain', 'color' => '', 'text' => '', 'align' => 'left', 'size' => 'normal' ] );
}

/** Clean settings; anything missing or not allowed falls back to $base ('' = "as in Look" for an area) */
function monospace_banner_clean( $in, $base ) {
    $c   = monospace_banner_choices();
    $out = $base + [ 'text' => '' ]; // text = the text color ('' = automatic)
    foreach ( [ 'style', 'align', 'size' ] as $k ) if ( isset( $in[ $k ] ) && isset( $c[ $k ][ $in[ $k ] ] ) ) $out[ $k ] = $in[ $k ];
    foreach ( [ 'color', 'text' ] as $k ) if ( isset( $in[ $k ] ) ) $out[ $k ] = (string) ( sanitize_hex_color( $in[ $k ] ) ?: '' );
    foreach ( [ 'from', 'until' ] as $k ) if ( array_key_exists( $k, $base ) ) $out[ $k ] = isset( $in[ $k ] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $in[ $k ] ) ? $in[ $k ] : '';
    if ( array_key_exists( 'close', $base ) ) $out['close'] = ! empty( $in['close'] ) ? '1' : '';
    return $out;
}

/** An item's own settings ('' = as in Look) - an array: a post's or term's meta (its style, color, alignment, size, dates,
 *  close); an option name: a Layout Manager / Home Page area, whose dates and close are used but not its style (one style
 *  for the site, overridden on the item only) */
function monospace_banner_area( $option ) {
    $s = is_array( $option ) ? $option : get_option( $option, [] );
    $s = is_array( $s ) ? $s : [];
    if ( ! is_array( $option ) && $option !== 'monospace_home_top_style' ) foreach ( [ 'style', 'color', 'text', 'align', 'size' ] as $k ) $s[ $k ] = '';
    return monospace_banner_clean( $s, [ 'style' => '', 'color' => '', 'align' => '', 'size' => '', 'from' => '', 'until' => '', 'close' => '' ] );
}

/** The dates and close button of a Layout Manager / Home Page area (its style is Look > Banners') */
function monospace_banner_schedule_fields( $name, $values ) { ?>
    <div class="msb-schedule" style="margin:10px 0">
        <p style="margin:0 0 6px"><strong>Show from</strong> <input type="date" name="<?php echo esc_attr( $name . '[from]' ); ?>" value="<?php echo esc_attr( $values['from'] ); ?>">
            <strong style="margin-left:10px">until</strong> <input type="date" name="<?php echo esc_attr( $name . '[until]' ); ?>" value="<?php echo esc_attr( $values['until'] ); ?>"></p>
        <p style="margin:0 0 6px"><label><input type="checkbox" name="<?php echo esc_attr( $name . '[close]' ); ?>" value="1" <?php checked( $values['close'], '1' ); ?>> A close button (×) - closed, it stays away for that visitor until the text changes</label></p>
        <p class="description" style="margin:0">Empty dates = always. How it looks - one style for every banner: <a href="<?php echo esc_url( admin_url( 'themes.php?page=monospace-look' ) ); ?>">Appearance &rsaquo; Look &rsaquo; Banners</a>. A single post, page, category or tag can have its own style (its Top content).</p>
    </div>
<?php }

/** Look's color as variables (the dark look's in dark mode) */
function monospace_banner_vars() {
    $d = monospace_banner_defaults();
    $v = $d['color'] ? [ '--banner-color' => $d['color'], '--banner-text' => monospace_banner_text_on( $d['color'] ) ] : [];
    if ( $d['text'] ) $v['--banner-tc'] = $d['text'];
    return $v;
}

function monospace_banner_text_on( $hex ) {
    return function_exists( 'monospace_look_text_on' ) ? monospace_look_text_on( $hex ) : '#fff';
}

/** The banner CSS - the site and the admin previews */
function monospace_banner_css() {
    return '.ms-banner{--b-color:var(--banner-color,var(--btn-color,#1f6fb2));--b-text:var(--banner-text,#fff);position:relative;box-sizing:border-box;padding:.9rem 1.4rem;font-family:var(--ms-font-ui,"Open Sans",sans-serif);border-radius:var(--ms-card-radius,var(--ms-radius,8px))}'
         . '.ms-banner>:first-child{margin-top:0}.ms-banner>:last-child{margin-bottom:0}'
         . '.ms-banner.is-plain{padding:0;border-radius:0}'
         . '.ms-banner.is-card,.ms-banner.is-stripe{background:var(--ms-card-bg,#fff);border:solid var(--ms-card-border-width,1px) var(--ms-card-border,#ccc)}'
         . '.ms-banner.is-stripe{border-left:5px solid var(--b-color)}'
         . '.ms-banner.is-filled{background:var(--b-color);color:var(--b-text)}'
         . '.ms-banner.is-filled a,.ms-banner.is-filled :is(h1,h2,h3,h4,strong){color:inherit}'
         . '.ms-banner.is-filled a{text-decoration:underline}'
         . '.ms-banner.has-text,.ms-banner.has-text :is(h1,h2,h3,h4,h5,h6,p,li,strong,em,a){color:var(--b-tc,var(--banner-tc))}'
         . '.ms-banner.is-outline{border:2px solid var(--b-color)}'
         . '.ms-banner.is-center{text-align:center}'
         . '.ms-banner.is-compact{padding:.5rem 1rem;font-size:.92em}.ms-banner.is-compact.is-plain{padding:0}'
         . '.ms-banner.is-large{padding:1.6rem 2rem;font-size:1.15em}.ms-banner.is-large.is-plain{padding:0}'
         . '.ms-banner.has-close{padding-right:2.8rem}'
         // The close mark: a quiet ×, not a button (an <a>, so the site's glass / flat / outline button looks leave it alone)
         . '.ms-banner-close{position:absolute;top:.4rem;right:.6rem;display:block;width:1.5rem;height:1.5rem;color:inherit;font:300 1.35rem/1.5rem system-ui,sans-serif;text-align:center;text-decoration:none!important;opacity:.45;cursor:pointer}'
         . '.ms-banner-close:hover,.ms-banner-close:focus-visible{opacity:.9}'
         . '.ms-banner-close:focus-visible{outline:1px solid currentColor;outline-offset:1px;border-radius:3px}';
}

/** Wrap an area's content: its settings, else Look's */
function monospace_banner_wrap( $html, $area, $key ) {
    $d = monospace_banner_defaults();
    $s = [];
    foreach ( [ 'style', 'color', 'text', 'align', 'size' ] as $k ) $s[ $k ] = $area[ $k ] !== '' ? $area[ $k ] : $d[ $k ];
    $today = wp_date( 'Y-m-d' );
    if ( ( $area['from'] !== '' && $today < $area['from'] ) || ( $area['until'] !== '' && $today > $area['until'] ) ) return '';
    $class = 'top-content-area ms-banner is-' . $s['style'] . ( $s['align'] === 'center' ? ' is-center' : '' ) . ( $s['size'] !== 'normal' ? ' is-' . $s['size'] : '' ) . ( $s['text'] !== '' ? ' has-text' : '' ) . ( $area['close'] ? ' has-close' : '' );
    $style = $area['color'] !== '' ? '--b-color:' . $area['color'] . ';--b-text:' . monospace_banner_text_on( $area['color'] ) . ';' : '';
    if ( $area['text'] !== '' ) $style .= '--b-tc:' . $area['text'] . ';';
    $id    = 'ms-banner-' . substr( md5( $key . '|' . $html ), 0, 10 );
    $out   = '<div class="' . esc_attr( $class ) . '" id="' . esc_attr( $id ) . '"' . ( $style ? ' style="' . esc_attr( $style ) . '"' : '' )
           . ( $area['from'] ? ' data-from="' . esc_attr( $area['from'] ) . '"' : '' ) . ( $area['until'] ? ' data-until="' . esc_attr( $area['until'] ) . '"' : '' ) . '>'
           . $html . ( $area['close'] ? '<a href="#" class="ms-banner-close" role="button" aria-label="Close" title="Close">&times;</a>' : '' ) . '</div>';
    // Before it paints: closed in this browser, or outside its dates (the page may be cached from another day)
    $out  .= '<script>(function(){var b=document.getElementById(' . wp_json_encode( $id ) . '),d=new Date(),t=d.getFullYear()+"-"+String(d.getMonth()+1).padStart(2,"0")+"-"+String(d.getDate()).padStart(2,"0");'
           . 'try{if(localStorage.getItem(b.id)){b.remove();return}}catch(e){}'
           . 'if((b.dataset.from&&t<b.dataset.from)||(b.dataset.until&&t>b.dataset.until)){b.remove();return}'
           . 'var c=b.querySelector(".ms-banner-close");if(c)c.addEventListener("click",function(e){e.preventDefault();try{localStorage.setItem(b.id,"1")}catch(x){}b.remove()})})();</script>';
    return $out;
}

// The CSS on the site, and Look's color (dark look: its own - dark-mode.php adds it to monospace_look_all_css())
add_action( 'wp_head', function () {
    echo '<style id="ms-banner">' . monospace_banner_css() . "</style>\n";
    $vars = monospace_banner_vars();
    if ( $vars && function_exists( 'monospace_look_style' ) ) {
        $c = ''; foreach ( $vars as $k => $v ) $c .= $k . ':' . $v . ';';
        monospace_look_style( 'ms-banner-look', ':root{' . $c . '}' );
    }
}, 30 );

/** The fields, for Look (an area: with "As in Look", dates, close) - and a live preview of $textarea_id's text */
function monospace_banner_fields( $name, $values, $area = false, $textarea_id = '' ) {
    $c   = monospace_banner_choices();
    $d   = monospace_banner_defaults();
    $uid = 'msb-' . substr( md5( $name ), 0, 6 );
    $sel = function ( $k, $label ) use ( $c, $values, $area, $name, $d ) { ?>
        <label class="msb-f"><span><?php echo esc_html( $label ); ?></span>
            <select name="<?php echo esc_attr( $name . '[' . $k . ']' ); ?>" data-k="<?php echo esc_attr( $k ); ?>">
                <?php if ( $area ) : ?><option value="" data-look="<?php echo esc_attr( $d[ $k ] ); ?>">As in Look (<?php echo esc_html( strtok( $c[ $k ][ $d[ $k ] ], ' -' ) ); ?>)</option><?php endif; ?>
                <?php foreach ( $c[ $k ] as $v => $text ) : ?><option value="<?php echo esc_attr( $v ); ?>" <?php selected( $values[ $k ], $v ); ?>><?php echo esc_html( $text ); ?></option><?php endforeach; ?>
            </select></label>
    <?php }; ?>
    <div class="msb" id="<?php echo esc_attr( $uid ); ?>" data-text="<?php echo esc_attr( $textarea_id ); ?>" data-look="<?php echo esc_attr( wp_json_encode( $d ) ); ?>">
        <div class="msb-grid">
            <?php $sel( 'style', 'Style' ); ?>
            <label class="msb-f"><span>Banner color</span>
                <span><input type="color" data-k="color" value="<?php echo esc_attr( $values['color'] ?: ( $d['color'] ?: '#1f6fb2' ) ); ?>"<?php echo $values['color'] === '' ? ' data-unset="1"' : ''; ?>>
                <input type="hidden" name="<?php echo esc_attr( $name . '[color]' ); ?>" value="<?php echo esc_attr( $values['color'] ); ?>">
                <button type="button" class="button-link msb-unset"><?php echo $area ? 'As in Look' : 'The buttons’ color'; ?></button></span></label>
            <label class="msb-f"><span>Banner text color</span>
                <span><input type="color" data-k="text" value="<?php echo esc_attr( $values['text'] ?: ( $d['text'] ?: '#222222' ) ); ?>">
                <input type="hidden" name="<?php echo esc_attr( $name . '[text]' ); ?>" value="<?php echo esc_attr( $values['text'] ); ?>">
                <button type="button" class="button-link msb-unset"><?php echo $area ? 'As in Look' : 'Automatic'; ?></button></span></label>
            <?php $sel( 'align', 'Alignment' ); $sel( 'size', 'Size' ); ?>
            <?php if ( $area ) : ?>
                <label class="msb-f"><span>Show from</span><input type="date" name="<?php echo esc_attr( $name . '[from]' ); ?>" value="<?php echo esc_attr( $values['from'] ); ?>"></label>
                <label class="msb-f"><span>until</span><input type="date" name="<?php echo esc_attr( $name . '[until]' ); ?>" value="<?php echo esc_attr( $values['until'] ); ?>"></label>
                <label class="msb-f msb-check"><input type="checkbox" name="<?php echo esc_attr( $name . '[close]' ); ?>" value="1" <?php checked( $values['close'], '1' ); ?>> A close button (×) - closed, it stays away for that visitor until the text changes</label>
            <?php endif; ?>
        </div>
        <p class="msb-label">Preview</p>
        <div class="msb-preview"><div class="ms-banner"></div></div>
        <?php if ( $textarea_id ) : ?><p class="description">Formatting in the text: **bold**, *italic*, ~~strike~~, [a link](https://...), # heading, - list - or HTML.</p><?php endif; ?>
        <?php if ( $area ) : ?><p class="description">Empty dates = always. A banner shows from the start of its first day to the end of its last.</p><?php endif; ?>
    </div>
    <?php
    static $assets = false;
    if ( $assets ) return;
    $assets = true; ?>
    <style>
        <?php echo monospace_banner_css(); ?>
        .msb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 8px 16px; margin: 10px 0; }
        .msb-f { display: flex; flex-direction: column; gap: 3px; }
        .msb-f[hidden] { display: none; }
        .msb-f > span:first-child { font-weight: 600; }
        .msb-check { grid-column: 1 / -1; flex-direction: row; align-items: center; gap: 6px; }
        .msb-f input[type=color] { width: 48px; height: 30px; padding: 0 2px; vertical-align: middle; }
        .msb-label { margin: 10px 0 4px; font-weight: 600; }
        .msb-preview { padding: 14px; background: #f6f3ee; border: 1px dashed #c3c4c7; border-radius: 6px; }
        .msb-preview .ms-banner:empty::before { content: "Type the text above to see it here"; color: #8c8f94; font-style: italic; }
    </style>
    <script>
    // The previews: the fields and the text (wp.editor or a textarea) on a banner drawn with the site's CSS
    (function () {
        function textOn(hex) { var h = hex.replace('#', ''), l = (0.299 * parseInt(h.substr(0, 2), 16) + 0.587 * parseInt(h.substr(2, 2), 16) + 0.114 * parseInt(h.substr(4, 2), 16)) / 255; return l > 0.62 ? '#111' : '#fff'; }
        // The inline part of the site's Markdown (monospace_markdown_lite): bold, italic, strike, links
        function md(h) {
            return h.replace(/\*\*\s*(\S[\s\S]*?)\s*\*\*/g, '<strong>$1</strong>').replace(/~~\s*(\S[\s\S]*?)\s*~~/g, '<del>$1</del>')
                .replace(/(?<![*\w])\*(?=[^\s*])([\s\S]+?)(?<=[^\s*])\*(?![*\w])/g, '<em>$1</em>').replace(/(?<![_\w])_(?=[^\s_])([\s\S]+?)(?<=[^\s_])_(?![_\w])/g, '<em>$1</em>')
                .replace(/\[([^\]\n]+)\]\(((?:https?:\/\/|mailto:|\/|#)[^\s)]*)\)/g, '<a href="$2">$1</a>');
        }
        function setup(box) {
            var look = JSON.parse(box.dataset.look), area = !!box.querySelector('option[value=""]'), b = box.querySelector('.ms-banner'),
                text = box.dataset.text ? document.getElementById(box.dataset.text) : null,
                color = box.querySelector('input[data-k=color]'), hidden = color.nextElementSibling,
                tcolor = box.querySelector('input[data-k=text]'), thidden = tcolor.nextElementSibling;
            function val(k) { var s = box.querySelector('select[data-k=' + k + ']'); return s.value || look[k]; }
            function draw() {
                var s = val('style');
                color.closest('label').hidden = s === 'plain' || s === 'card'; // only filled, outline and stripe use a color
                b.className = 'ms-banner is-' + s + (val('align') === 'center' ? ' is-center' : '') + (val('size') !== 'normal' ? ' is-' + val('size') : '') + ((thidden.value || (area ? look.text : '')) ? ' has-text' : '');
                var c = hidden.value || (area ? look.color : '') ;
                if (c) { b.style.setProperty('--b-color', c); b.style.setProperty('--b-text', textOn(c)); } else { b.style.removeProperty('--b-color'); b.style.removeProperty('--b-text'); }
                var tc = thidden.value || (area ? look.text : '');
                if (tc) b.style.setProperty('--b-tc', tc); else b.style.removeProperty('--b-tc');
                var html = '';
                if (text) html = (window.tinymce && tinymce.get(text.id) && !tinymce.get(text.id).isHidden()) ? tinymce.get(text.id).getContent() : text.value;
                else html = '<p><strong>An announcement</strong> - the banner as it looks with these settings. <a href="#">A link</a>.</p>';
                b.innerHTML = md(html.replace(/\[[^\]]+\](?!\()/g, '<em>[shortcode]</em>'));
            }
            color.addEventListener('input', function () { hidden.value = color.value; draw(); });
            tcolor.addEventListener('input', function () { thidden.value = tcolor.value; draw(); });
            box.querySelectorAll('.msb-unset')[0].addEventListener('click', function () { hidden.value = ''; draw(); });
            box.querySelectorAll('.msb-unset')[1].addEventListener('click', function () { thidden.value = ''; draw(); });
            box.addEventListener('change', draw);
            if (text) { text.addEventListener('input', draw); setInterval(function () { if (window.tinymce && tinymce.get(text.id)) draw(); }, 1500); }
            draw();
        }
        function all() { document.querySelectorAll('.msb:not([data-ready])').forEach(function (box) { box.setAttribute('data-ready', ''); setup(box); }); }
        if (document.readyState !== 'loading') all(); else document.addEventListener('DOMContentLoaded', all);
    })();
    </script>
    <?php
}

// Look > Banners - the default
add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['banner-style'] = [
        'title'       => 'Banners',
        'description' => 'How every Top content banner looks - one style for the site (a single post, page, category or tag can override it in its own Top content).',
        'group'       => 'look',
        'order'       => 47,
        'summary'     => fn() => ucfirst( monospace_banner_defaults()['style'] ),
        'render'      => function () { monospace_banner_fields( 'banner_style', monospace_banner_defaults() ); },
        'save'        => function () {
            if ( ! isset( $_POST['banner_style'] ) || ! is_array( $_POST['banner_style'] ) ) return;
            $s = monospace_banner_clean( wp_unslash( $_POST['banner_style'] ), [ 'style' => 'plain', 'color' => '', 'text' => '', 'align' => 'left', 'size' => 'normal' ] );
            $s === [ 'style' => 'plain', 'color' => '', 'text' => '', 'align' => 'left', 'size' => 'normal' ] ? delete_option( 'monospace_banner_style' ) : update_option( 'monospace_banner_style', $s );
        },
    ];
    return $modules;
} );

/** Save an area's settings from $_POST[ $field ] into $option */
function monospace_banner_save_area( $field, $option ) {
    if ( ! isset( $_POST[ $field ] ) || ! is_array( $_POST[ $field ] ) ) return;
    $s = monospace_banner_clean( wp_unslash( $_POST[ $field ] ), [ 'style' => '', 'color' => '', 'align' => '', 'size' => '', 'from' => '', 'until' => '', 'close' => '' ] );
    array_filter( $s ) ? update_option( $option, $s ) : delete_option( $option );
}

<?php
/**
 * Dark mode (2026-10-21, owner: "use these controls to create Dark Mode" - automatic for dark devices, plus a switch in
 * the menu bar; edited in Appearance > Look with the same controls).
 *
 * The dark look is a snapshot like "my default look" (look-defaults.php): option monospace_look_dark; until it's saved,
 * monospace_dark_starter() - the light look with a designed dark background, colors, cards, captions and spotlight.
 * monospace_dark_with( $fn ) runs code as if the dark look were saved (pre_option_* filters, and the theme mods through
 * pre_option_theme_mods_<theme>), so every part prints its dark CSS with its own code:
 *   - the look's <style> blocks (ms-look, ms-customizer, ms-spotlight, ms-rounding, ms-caption) go through
 *     monospace_look_style(): with dark mode on, scoped to html:not(.ms-dark); the dark ones, made by the same functions,
 *     print once scoped to html.ms-dark (<style id="ms-dark">)
 *   - <body>'s look classes (bg-*, btn-*, appearance-*) swap: a script in <head> puts ms-dark / ms-dark-ui on <html> before
 *     anything paints (the visitor's choice - sessionStorage 'ms-theme' while Automatic is on, so the device decides each new visit;
 *     localStorage with Switch only -, else their device), and one right after <body>
 *     (hook monospace_body_open, header.php) swaps the classes
 *   - home page boxes: their dark style as hbd-* classes and --hbd-* variables next to the light ones (home-box-style.php,
 *     CSS in _start_template.scss)
 *   - fonts the dark look needs load too
 *   - things drawn in fixed colors get dark versions under html.ms-dark-ui (_dark-mode.scss): the logo (inverted), the
 *     Welcome text, the News box's panel
 * Editing: Appearance > Look has a Light / Dark switch (?mode=dark). In Dark the screen shows the dark values (the same
 * filters) and every panel (each linked to light or not - look-links.php); Apply Dark Mode saves as usual, keeps the result as the dark look and puts the light look
 * back. The preview frame shows the dark site (?ms_dark=1 - the whole page made under the dark look, html.ms-dark-ui).
 * Settings (option monospace_dark_mode): on for visitors, automatic (follow the device), show the switch - chosen as one of
 * Off / Switch only / Automatic + switch / Automatic only (monospace_dark_choices()) at the top of the Look screen, with its
 * own Save (2026-10-21: first a folded panel near the bottom, then three ticks - "On" unticked turned everything off).
 * Off = nothing printed.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_dark_settings() {
    $s = (array) get_option( 'monospace_dark_mode', [] );
    return [ 'on' => ! empty( $s['on'] ), 'auto' => ! isset( $s['auto'] ) || $s['auto'], 'switch' => ! isset( $s['switch'] ) || $s['switch'] ];
}

/** The look's parts that dark mode changes (the effects stay as they are) */
function monospace_dark_modules() {
    return [ 'home-box-style', 'caption-style', 'rounding', 'banner-style', 'shadows', 'secondary-buttons', 'logo-layout' ];
}

/** The starting dark look - the dark factory default: the one kept in monospace_look_factory_dark if there is one, else the
 *  factory default light look, with a dark background, light text, dark cards and captions, a
 *  dim spotlight - fixed: before 2026-10-22 it was made from the current light look, so it changed with it (owner: "Restore
 *  the Factory Default looks, they seem to be messed up") */
function monospace_dark_starter() {
    // The owner's own dark factory default (2026-10-24: "save the currently active looks as factory defaults"; scripts/save-looks-as-factory.php)
    $own = get_option( 'monospace_look_factory_dark' );
    if ( is_array( $own ) && ! empty( $own['options'] ) ) return $own;
    $s = ( function_exists( 'monospace_look_saved_snapshot' ) ? monospace_look_saved_snapshot( 'light', 'factory' ) : null ) ?: monospace_look_snapshot();
    $look = (array) ( $s['options']['monospace_look'] ?? [] );
    $s['options'] = array_merge( $s['options'], [
        'monospace_appearance'              => 'custom',
        'monospace_appearance_bg'           => 'color',
        'monospace_appearance_buttons'      => 'glass',
        'monospace_appearance_button_color' => 'blue',
        'monospace_look'                    => array_merge( $look, [ 'bg_color' => '#1e1c1a', 'bg_image' => 0, 'bg_fit' => 'cover',
            'card_bg' => '#2b2926', 'card_bg_a' => 94, 'card_border' => '#48443f', 'card_border_a' => 100, 'card_width' => 1, 'card_radius' => 8 ] ),
        'monospace_spotlight'               => [ 'on' => 1, 'pos' => [], 'colors' => [ '#5e564b', 40, '#000000', 55 ] ],
        'monospace_caption_style'           => [ 'shape' => 'card', 'fill' => '#2b2926', 'outline' => '#48443f', 'text' => '#d8d2c8', 'font' => '', 'size' => 'm', 'italic' => 'on', 'bold' => 'on', 'shadow' => 'off' ],
    ] );
    $s['mods'] = array_merge( $s['mods'], [
        'ms_color_text' => '#e4dfd6', 'ms_color_text_light' => '#a8a196', 'ms_color_heading' => '#f1ece4',
        'ms_color_link' => '#f0dcb0', 'ms_color_tagline' => '#e4dfd6',
    ] );
    return $s;
}

/** The dark look: saved, else the starting one */
function monospace_dark_snapshot() {
    static $snap = null; // asked for once per home box - and the starter reads the light look, so it's made before any filter
    if ( $snap === null ) {
        $s    = get_option( 'monospace_look_dark' );
        $snap = is_array( $s ) && ! empty( $s['options'] ) ? $s : monospace_dark_starter();
        // What dark mode can't change is the site's own (look-links.php) - never an old copy kept in the dark look
        if ( function_exists( 'monospace_look_link_overlay' ) ) $snap = monospace_look_link_overlay( $snap );
    }
    return $snap;
}

/** Run $fn as if the dark look were saved; returns what $fn returns */
function monospace_dark_with( $fn ) {
    $off = monospace_dark_apply( monospace_dark_snapshot() );
    try { return $fn(); } finally { $off(); }
}

/** Put a snapshot's values in front of the saved ones (filters only, nothing written); returns the function that takes them away */
function monospace_dark_apply( $snap ) {
    $added = [];
    foreach ( (array) $snap['options'] as $name => $value ) {
        if ( $value === null ) {
            // not set: the caller's default; with no default, false (option_* runs after a stored value is read)
            $added[] = [ "pre_option_$name", $f1 = fn( $pre, $opt, $default ) => $default !== false ? $default : false, 3 ];
            $added[] = [ "option_$name", $f2 = fn() => false, 1 ];
            add_filter( "pre_option_$name", $f1, 10, 3 ); add_filter( "option_$name", $f2, 10, 1 );
        } else {
            $added[] = [ "pre_option_$name", $f = fn() => $value, 0 ];
            add_filter( "pre_option_$name", $f, 10, 0 );
        }
    }
    $key  = 'theme_mods_' . get_option( 'stylesheet' );
    $mods = (array) get_option( $key, [] );
    foreach ( (array) ( $snap['mods'] ?? [] ) as $name => $value ) {
        if ( $value === null ) unset( $mods[ $name ] ); else $mods[ $name ] = $value;
    }
    $added[] = [ "pre_option_$key", $f = fn() => $mods, 0 ];
    add_filter( "pre_option_$key", $f, 10, 0 );
    return function () use ( $added ) { foreach ( $added as [ $hook, $fn ] ) remove_filter( $hook, $fn, 10 ); };
}

/** The Look screen in Dark (showing or saving the dark look) */
function monospace_dark_editing() {
    return is_admin() && ( ( ( $_GET['page'] ?? '' ) === 'monospace-look' && ( $_GET['mode'] ?? '' ) === 'dark' ) || ( $_POST['look_mode'] ?? '' ) === 'dark' );
}

/** The Look screen's preview frame showing the dark look */
function monospace_dark_preview() {
    return ! is_admin() && isset( $_GET['ms_spot_preview'], $_GET['ms_dark'] ) && current_user_can( 'edit_theme_options' );
}

/** Light and dark printed side by side (dark mode on, an ordinary page - not a preview frame) */
function monospace_dark_split() {
    static $split = null;
    if ( $split === null ) $split = ! is_admin() && monospace_dark_settings()['on'] && ! isset( $_GET['ms_spot_preview'] );
    return $split;
}

/** Scope generated look CSS to $scope ('html.ms-dark', 'html:not(.ms-dark)') - :root and html become the scope */
function monospace_dark_scope( $css, $scope ) {
    return preg_replace_callback( '/(^|[{}])([^{}@]+)\{/', function ( $m ) use ( $scope ) {
        $sels = array_map( function ( $s ) use ( $scope ) {
            $s = trim( $s );
            if ( strpos( $s, ':root' ) === 0 ) return $scope . substr( $s, 5 );
            if ( preg_match( '/^html\b/', $s ) ) return $scope . substr( $s, 4 );
            return $scope . ' ' . $s;
        }, explode( ',', $m[2] ) );
        return $m[1] . implode( ',', $sels ) . '{';
    }, $css );
}

/** Print one of the look's <style> blocks - the light one, scoped when dark mode is on */
function monospace_look_style( $id, $css ) {
    if ( $css === '' ) return;
    if ( monospace_dark_split() ) $css = monospace_dark_scope( $css, 'html:not(.ms-dark)' );
    echo '<style id="' . esc_attr( $id ) . '">' . wp_strip_all_tags( $css ) . "</style>\n";
}

/** All of the look's CSS, as the parts print it (under monospace_dark_with(): the dark look's) */
function monospace_look_all_css() {
    $root = function ( $vars ) { $c = ''; foreach ( (array) $vars as $k => $v ) $c .= $k . ':' . $v . ';'; return $c !== '' ? ':root{' . $c . '}' : ''; };
    $css  = function_exists( 'monospace_look_css' ) ? monospace_look_css() : '';
    if ( function_exists( 'monospace_customizer_vars' ) ) $css .= $root( monospace_customizer_vars() );
    if ( function_exists( 'monospace_spotlight_css' ) ) $css .= monospace_spotlight_css( monospace_spotlight_settings() );
    if ( function_exists( 'monospace_rounding_vars' ) ) $css .= $root( monospace_rounding_vars() );
    if ( function_exists( 'monospace_banner_vars' ) ) $css .= $root( monospace_banner_vars() );
    if ( function_exists( 'monospace_shadows_vars' ) ) $css .= $root( monospace_shadows_vars() );
    if ( function_exists( 'monospace_logo_color_vars' ) ) $css .= $root( monospace_logo_color_vars() );
    if ( function_exists( 'monospace_secondary_buttons_vars' ) ) $css .= $root( monospace_secondary_buttons_vars() );
    if ( function_exists( 'monospace_caption_vars' ) ) {
        $cap = monospace_caption_vars();
        $css .= $root( $cap ) . ( isset( $cap['--cap-color'] ) ? 'figcaption a{color:var(--cap-color)}' : '' );
    }
    return $css;
}

/** <body>'s look classes, light and dark */
function monospace_dark_body_classes() {
    $of = function () { [ $p, $bg, $st, $c ] = monospace_appearance(); return [ 'appearance-' . $p, 'bg-' . $bg, 'btn-' . $st, 'btn-color-' . $c ]; };
    return [ $of(), monospace_dark_with( $of ) ];
}

// The preview frame in Dark: the whole page under the dark look - once every module is loaded (the starting dark look
// needs look-defaults.php, which loads after this file: applied here it stopped the page, 2026-10-21)
add_action( 'wp_loaded', function () {
    if ( monospace_dark_preview() ) monospace_dark_apply( monospace_dark_snapshot() );
} );
// The Look screen in Dark: show the dark values
add_action( 'admin_init', function () {
    if ( monospace_dark_editing() && $_SERVER['REQUEST_METHOD'] !== 'POST' ) monospace_dark_apply( monospace_dark_snapshot() );
} );

// <head>: the mode, before anything paints
add_action( 'wp_head', function () {
    if ( monospace_dark_preview() ) { echo "<script>document.documentElement.classList.add('ms-dark-ui')</script>\n"; return; }
    if ( ! monospace_dark_split() ) return;
    [ $light, $dark ] = monospace_dark_body_classes();
    $cfg = [ 'auto' => monospace_dark_settings()['auto'], 'light' => array_values( array_diff( $light, $dark ) ), 'dark' => array_values( array_diff( $dark, $light ) ) ]; ?>
<script data-no-optimize="1" data-no-defer="1">
(function () {
    var d = document.documentElement, c = <?php echo wp_json_encode( $cfg ); ?>, mq = window.matchMedia ? matchMedia('(prefers-color-scheme: dark)') : null;
    // With Automatic on, the device decides on every new visit (owner, 2026-10-23: someone who switched to Light once still gets Dark
    // when their device is dark): the switch is that visitor's choice for this visit only (sessionStorage); an older choice kept in the
    // browser is forgotten. Without Automatic ("Switch only") there is no device rule, so the choice is theirs to keep (localStorage).
    function store() { try { return c.auto ? sessionStorage : localStorage; } catch (e) { return null; } }
    function pref() { try { var s = store(); return s ? s.getItem('ms-theme') : null; } catch (e) { return null; } }
    if (c.auto) { try { localStorage.removeItem('ms-theme'); } catch (e) {} }
    function want() { var p = pref(); return p === 'dark' || p === 'light' ? p === 'dark' : !!(c.auto && mq && mq.matches); }
    function body(dark) {
        var b = document.body; if (!b) return;
        (dark ? c.light : c.dark).forEach(function (x) { b.classList.remove(x); });
        (dark ? c.dark : c.light).forEach(function (x) { b.classList.add(x); });
    }
    function set(dark) {
        d.classList.toggle('ms-dark', dark); d.classList.toggle('ms-dark-ui', dark); body(dark);
        document.querySelectorAll('.ms-theme-toggle').forEach(function (t) { t.setAttribute('aria-pressed', dark ? 'true' : 'false'); });
    }
    window.msTheme = {
        body: function () { body(d.classList.contains('ms-dark')); },
        toggle: function () { var dark = !d.classList.contains('ms-dark'); try { store().setItem('ms-theme', dark ? 'dark' : 'light'); } catch (e) {} set(dark); }
    };
    if (want()) d.classList.add('ms-dark', 'ms-dark-ui');
    if (c.auto && mq && mq.addEventListener) mq.addEventListener('change', function (e) { if (!pref()) set(e.matches); });
    document.addEventListener('click', function (e) { if (e.target.closest && e.target.closest('.ms-theme-toggle')) { e.preventDefault(); window.msTheme.toggle(); } });
})();
</script>
<?php
}, 1 );

// Right after <body>: its look classes for the mode
add_action( 'monospace_body_open', function () {
    if ( monospace_dark_split() ) echo "<script data-no-optimize=\"1\">window.msTheme&&msTheme.body()</script>\n";
} );

// The dark look's CSS, after the light one's
add_action( 'wp_head', function () {
    if ( ! monospace_dark_split() ) return;
    $css = monospace_dark_with( 'monospace_look_all_css' );
    if ( $css !== '' ) echo '<style id="ms-dark">' . wp_strip_all_tags( monospace_dark_scope( $css, 'html.ms-dark' ) ) . "</style>\n";
}, 32 );

// The dark look's fonts
add_action( 'wp_enqueue_scripts', function () {
    if ( ! monospace_dark_split() || ! function_exists( 'monospace_customizer_fonts' ) ) return;
    $need = monospace_dark_with( function () {
        $f = function_exists( 'monospace_customizer_font_families' ) ? monospace_customizer_font_families() : [];
        if ( function_exists( 'monospace_caption_style' ) && ( $c = monospace_caption_style()['font'] ) && ! empty( monospace_customizer_fonts()[ $c ][1] ) ) $f[ $c ] = monospace_customizer_fonts()[ $c ][1];
        return $f;
    } );
    if ( $need ) wp_enqueue_style( 'monospace-dark-fonts', 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $need ) . '&display=swap', [], null );
}, 22 );

// The switch in the menu bar (hook in header.php)
add_action( 'monospace_menu_bar_end', function () {
    if ( ! monospace_dark_split() || ! monospace_dark_settings()['switch'] ) return; ?>
    <button type="button" class="ms-theme-toggle" aria-pressed="false" aria-label="Dark mode" title="Dark mode">
        <svg class="ms-moon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg>
        <svg class="ms-sun" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4.2"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22M4.9 4.9l1.8 1.8M17.3 17.3l1.8 1.8M4.9 19.1l1.8-1.8M17.3 6.7l1.8-1.8"/></svg>
    </button>
    <?php
} );

/* ---------- Dark versions of images (2026-10-21) ---------- */

/**
 * An image can have a version for dark mode (owner: an inverted copy of the sketch avatar on About Me): attachment meta
 * _ms_dark_version = a path under uploads/ (files in uploads/dark-versions/ - not in the media library, so the watermark
 * plugin leaves them alone). In post content, such an image is printed twice while dark mode is on - itself
 * (.ms-light-only) and the dark copy (.ms-dark-only, lazy, so it only loads when shown); _dark-mode.scss shows one.
 * The Look screen's dark preview shows the dark one directly.
 */
function monospace_dark_version_url( $attachment_id ) {
    $rel = (string) get_post_meta( (int) $attachment_id, '_ms_dark_version', true );
    if ( $rel === '' || strpos( $rel, '..' ) !== false ) return '';
    $up = wp_upload_dir( null, false );
    return file_exists( $up['basedir'] . '/' . $rel ) ? $up['baseurl'] . '/' . $rel : '';
}

add_filter( 'wp_content_img_tag', function ( $img, $context, $attachment_id ) {
    if ( ! $attachment_id || ! ( monospace_dark_split() || monospace_dark_preview() ) ) return $img;
    $dark = monospace_dark_version_url( $attachment_id );
    if ( $dark === '' ) return $img;
    $copy = preg_replace( '/\s(srcset|sizes)="[^"]*"/', '', $img );
    $copy = preg_replace( '/\ssrc="[^"]*"/', ' src="' . esc_url( $dark ) . '"', $copy );
    if ( monospace_dark_preview() ) return $copy; // the preview frame is dark already
    $copy = preg_replace( '/\s(loading|fetchpriority)="[^"]*"/', '', $copy );
    $copy = preg_replace( '/<img\b/', '<img loading="lazy"', $copy, 1 );
    $add  = fn( $tag, $class ) => preg_match( '/\sclass="/', $tag ) ? preg_replace( '/\sclass="/', ' class="' . $class . ' ', $tag, 1 ) : preg_replace( '/<img\b/', '<img class="' . $class . '"', $tag, 1 );
    return $add( $img, 'ms-light-only' ) . $add( $copy, 'ms-dark-only' );
}, 10, 3 );

/* ---------- Appearance > Look ---------- */

// Light / Dark, at the top of the screen
add_action( 'monospace_look_top', function () {
    $dark = monospace_dark_editing();
    $url  = admin_url( 'themes.php?page=monospace-look' );
    $s    = monospace_dark_settings(); ?>
    <?php // Part of the look's form (form="ms-look-form"): saved by its Save button, no Save of its own (2026-10-21) ?>
    <div class="ms-dark-settings">
        <strong>Dark mode for visitors:</strong>
        <?php $now = monospace_dark_choice( $s );
        foreach ( monospace_dark_choices() as $key => [ $label, , $tip ] ) : ?>
            <label title="<?php echo esc_attr( $tip ); ?>"><input type="radio" form="ms-look-form" name="dark_choice" value="<?php echo esc_attr( $key ); ?>" <?php checked( $now, $key ); ?>> <?php echo esc_html( $label ); ?></label>
        <?php endforeach; ?>
        <span class="description">Saved with the look</span>
    </div>
    <div class="ms-dark-tabs">
        <a class="button<?php echo $dark ? '' : ' button-primary'; ?>" href="<?php echo esc_url( $url ); ?>">☀ Light look</a><a class="button<?php echo $dark ? ' button-primary' : ''; ?>" href="<?php echo esc_url( $url . '&mode=dark' ); ?>">☾ Dark look</a>
        <?php if ( $dark ) : ?><span class="description">Editing the dark look - visitors see it when their device is dark<?php echo $s['switch'] ? ' or they pick it with the switch in the menu bar' : ''; ?>. Effects and the print preview are the same in both, so they're on the Light look.</span><?php endif; ?>
    </div>
    <style>
        .ms-dark-tabs { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; margin: 6px 0 14px; }
        .ms-dark-tabs .button:first-child { border-radius: 3px 0 0 3px; } .ms-dark-tabs .button:nth-child(2) { border-radius: 0 3px 3px 0; margin-left: -13px; }
        .ms-dark-settings { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; margin: 0 0 10px; padding: 10px 14px; background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; max-width: 1500px; box-sizing: border-box; }
        .ms-dark-settings label { display: inline-flex; align-items: center; gap: 5px; }
    </style>
    <?php if ( $dark ) : ?>
    <script>
    // The preview frame shows the dark site
    document.addEventListener('DOMContentLoaded', function () {
        var dark = function (u) { return u + (u.indexOf('?') < 0 ? '?' : '&') + 'ms_dark=1'; }, f = document.getElementById('ms-spot-iframe'), sel = document.getElementById('ms-spot-url');
        if (sel) Array.prototype.forEach.call(sel.options, function (o) { o.value = dark(o.value); });
        if (f) f.src = dark(f.getAttribute('src'));
    });
    </script>
    <?php endif;
} );

// (In Dark the screen showed only the look's panels until 2026-10-22; now every panel, each linked or not - look-links.php)

// In Dark the form says so (the Presets panel's Factory default is then the dark one - look-defaults.php)
add_action( 'monospace_look_save_buttons', function () {
    if ( monospace_dark_editing() ) echo '<input type="hidden" name="look_mode" value="dark">';
}, 5 );

// Dark mode for visitors (the choice at the top) - with Apply only (not the Presets' Load / Save), first
add_action( 'admin_post_monospace_look', function () {
    $choices = monospace_dark_choices();
    $choice  = sanitize_key( $_POST['dark_choice'] ?? '' );
    if ( ! isset( $choices[ $choice ] ) || ! current_user_can( 'edit_theme_options' ) || ! empty( $_POST['look_load'] ) || ! empty( $_POST['look_save'] ) ) return;
    check_admin_referer( 'monospace_look' );
    if ( monospace_dark_choice( monospace_dark_settings() ) !== $choice ) update_option( 'monospace_dark_mode', $choices[ $choice ][1] );
}, 0 );

// Saving in Dark: the light look is kept aside first, the form saved as usual, the result kept as the dark look, the light look put back
add_action( 'admin_post_monospace_look', function () {
    if ( ( $_POST['look_mode'] ?? '' ) !== 'dark' ) return;
    if ( ! current_user_can( 'edit_theme_options' ) ) wp_die( 'Not allowed.' );
    check_admin_referer( 'monospace_look' );
    if ( ! empty( $_POST['look_dark_reset'] ) ) {
        delete_option( 'monospace_look_dark' );
        if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache();
        wp_safe_redirect( admin_url( 'themes.php?page=monospace-look&mode=dark&look_msg=dark-reset' ) );
        exit;
    }
    $light = monospace_look_snapshot();
    add_action( 'monospace_look_saved', function () use ( $light ) {
        update_option( 'monospace_look_dark', monospace_look_snapshot(), false );
        monospace_look_restore( $light );
        add_filter( 'wp_redirect', fn( $to ) => add_query_arg( 'mode', 'dark', $to ) );
    }, 99 );
}, 2 );

add_action( 'admin_notices', function () {
    if ( ( $_GET['page'] ?? '' ) === 'monospace-look' && ( $_GET['look_msg'] ?? '' ) === 'dark-reset' ) echo '<div class="notice notice-success is-dismissible"><p>The starting dark look is applied - the site shows it now.</p></div>';
} );

/** The choices at the top of the Look screen: key => [ label, settings, what it means ] (one choice, not three ticks -
 *  "On" unticked with "Automatic" ticked turned it all off, 2026-10-21) */
function monospace_dark_choices() {
    return [
        'off'         => [ 'Off', [ 'on' => 0, 'auto' => 0, 'switch' => 0 ], 'Everyone sees the light look; no switch' ],
        'switch'      => [ 'Switch only', [ 'on' => 1, 'auto' => 0, 'switch' => 1 ], 'Light for everyone until they pick dark with the sun / moon switch in the menu bar' ],
        'auto-switch' => [ 'Automatic + switch', [ 'on' => 1, 'auto' => 1, 'switch' => 1 ], 'Dark when their phone or computer is set to dark, and the switch to change it' ],
        'auto'        => [ 'Automatic only', [ 'on' => 1, 'auto' => 1, 'switch' => 0 ], 'Dark when their phone or computer is set to dark; no switch' ],
    ];
}

/** Which choice the settings are */
function monospace_dark_choice( $s ) {
    if ( ! $s['on'] || ( ! $s['auto'] && ! $s['switch'] ) ) return 'off';
    return $s['auto'] ? ( $s['switch'] ? 'auto-switch' : 'auto' ) : 'switch';
}

// The choice's old form of its own (kept for a screen opened before 2026-10-21's change)
add_action( 'admin_post_monospace_dark_settings', function () {
    if ( ! current_user_can( 'edit_theme_options' ) ) wp_die( 'Not allowed.' );
    check_admin_referer( 'monospace_dark_settings' );
    $choices = monospace_dark_choices();
    $choice  = sanitize_key( $_POST['dark_choice'] ?? '' );
    if ( isset( $choices[ $choice ] ) ) update_option( 'monospace_dark_mode', $choices[ $choice ][1] );
    if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache();
    wp_safe_redirect( admin_url( 'themes.php?page=monospace-look' . ( ( $_POST['mode'] ?? '' ) === 'dark' ? '&mode=dark' : '' ) . '&look_msg=dark-settings' ) );
    exit;
} );

add_action( 'admin_notices', function () {
    if ( ( $_GET['page'] ?? '' ) === 'monospace-look' && ( $_GET['look_msg'] ?? '' ) === 'dark-settings' ) echo '<div class="notice notice-success is-dismissible"><p>Dark mode settings saved - the site uses them now.</p></div>';
} );

<?php
/**
 * Design module: Appearance
 *
 * The site's looks, collected from its stylesheets (current and earlier versions): page backgrounds,
 * button styles and button colors. Pick a preset, or "Custom" to combine the three freely.
 * Shown in Appearance > Look through the design-module registry
 * (design-modules.php).
 *
 * Front end: classes on <body> - bg-{background} btn-{style} btn-color-{color} appearance-{preset}.
 * The CSS for them: backgrounds in _global.scss (page texture / color and the fixed spotlight's
 * colors, as CSS variables), buttons in _components.scss + _button-styles.scss (the *-var mixins,
 * colored by --btn-color / --btn-text).
 *
 * Options: monospace_appearance (preset key or 'custom'), and for custom monospace_appearance_bg,
 * monospace_appearance_buttons, monospace_appearance_button_color.
 *
 * Since 2026-10-21 everything is edited in one place, Appearance > Look (look.php): the Looks, the background - also
 * your own color (bg 'color') or your own image, filling the screen or tiled (bg 'image') -, the buttons - also your
 * own color (button color 'custom') -, site colors, fonts and the spotlight. Their values: option monospace_look
 * [ bg_color, bg_image, bg_fit, btn_custom ]; the CSS for them: monospace_look_css() (wp_head, <style id="ms-look">).
 * The Layout Manager's Appearance card only shows the Look and links there.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['appearance'] = [
        'title'       => 'Appearance',
        'description' => 'Page background, button style and button color, site-wide.',
        'group'       => 'look',
        'order'       => 10,
        'summary'     => function () {
            list( $preset, $bg, $style, $color ) = monospace_appearance();
            $presets = monospace_appearance_presets();
            if ( isset( $presets[ $preset ] ) ) return preg_replace( '/ \(current\)$/', '', $presets[ $preset ][0] );
            return 'Custom: ' . monospace_appearance_backgrounds()[ $bg ][0] . ', ' . strtolower( monospace_appearance_button_styles()[ $style ][0] . ' ' . monospace_appearance_button_colors()[ $color ][0] ) . ' buttons';
        },
        'render'      => 'monospace_render_appearance_link',
        'save'        => '__return_null', // edited in Appearance > Look
    ];
    return $modules;
} );

/** Backgrounds: label, description, swatch (CSS background for the admin preview) */
function monospace_appearance_backgrounds() {
    $img = get_template_directory_uri() . '/assets/images/';
    return [
        'canvas'     => [ 'Canvas',           'Linen canvas texture under a warm spotlight',               "url({$img}canvas.jpeg) center / 160px" ],
        'paper'      => [ 'Paper',            'Smooth gray paper texture, softer brown spotlight',         "url({$img}paper.jpeg) center / 400px" ],
        'watercolor' => [ 'Watercolor paper','Rough white watercolor paper (an earlier version)',        'url(' . content_url( 'uploads/paper2.jpg' ) . ') center / 500px' ],
        'cream'      => [ 'Cream',            'Plain warm off-white, no texture',                           'rgb(241, 237, 231)' ],
        'studio'     => [ 'Studio',           'Dark taupe with a white spotlight - the very first look',   'radial-gradient(circle at 50% 40%, #fff 20%, rgb(110, 100, 90) 140%)' ],
        'color'      => [ 'Your color',      'A plain color you choose',                                  monospace_look_settings()['bg_color'] ],
        'image'      => [ 'Your image',       'A picture from the media library, filling the screen or tiled', ( $u = monospace_look_image_url() ) ? 'url(' . $u . ') center / cover' : '#ddd' ],
    ];
}

/** Your own background and button colors (Appearance > Look) */
function monospace_look_settings() {
    $s = get_option( 'monospace_look', [] );
    if ( ! is_array( $s ) ) $s = [];
    return [
        'bg_color'   => sanitize_hex_color( $s['bg_color'] ?? '' ) ?: '#b3a492',
        'bg_image'   => absint( $s['bg_image'] ?? 0 ),
        'bg_fit'     => ( $s['bg_fit'] ?? '' ) === 'tile' ? 'tile' : 'cover',
        'btn_custom' => sanitize_hex_color( $s['btn_custom'] ?? '' ) ?: '#046bd2',
    ] + monospace_look_card_settings( $s );
}

/** Cards - the white boxes with the rounded gray outline (sidebar boxes, "See also", the price card, shop boxes:
 *  the card-style mixin, _mixins.scss): fill color + opacity, outline color + opacity, thickness, rounding.
 *  Defaults = the theme's (white, #ccc 1px, 8px). */
function monospace_look_card_defaults() {
    return [ 'card_bg' => '#ffffff', 'card_bg_a' => 100, 'card_border' => '#cccccc', 'card_border_a' => 100, 'card_width' => 1, 'card_radius' => 8 ];
}
function monospace_look_card_settings( $s ) {
    $d = monospace_look_card_defaults();
    return [
        'card_bg'       => sanitize_hex_color( $s['card_bg'] ?? '' ) ?: $d['card_bg'],
        'card_bg_a'     => max( 0, min( 100, (int) ( $s['card_bg_a'] ?? $d['card_bg_a'] ) ) ),
        'card_border'   => sanitize_hex_color( $s['card_border'] ?? '' ) ?: $d['card_border'],
        'card_border_a' => max( 0, min( 100, (int) ( $s['card_border_a'] ?? $d['card_border_a'] ) ) ),
        'card_width'    => max( 0, min( 6, (int) ( $s['card_width'] ?? $d['card_width'] ) ) ),
        'card_radius'   => max( 0, min( 30, (int) ( $s['card_radius'] ?? $d['card_radius'] ) ) ),
    ];
}

/** #rrggbb + 0-100 → rgba() (the color itself at 100) */
function monospace_look_rgba( $hex, $alpha ) {
    $h = ltrim( (string) $hex, '#' );
    if ( (int) $alpha >= 100 ) return '#' . $h;
    return sprintf( 'rgba(%d, %d, %d, %s)', hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ), round( max( 0, (int) $alpha ) / 100, 2 ) );
}

/** Your background image's address ('' = none) */
function monospace_look_image_url() {
    $id = monospace_look_settings()['bg_image'];
    return $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : '';
}

/** Black or white text on a color - whichever reads better */
function monospace_look_text_on( $hex ) {
    $h = ltrim( (string) $hex, '#' );
    if ( strlen( $h ) !== 6 ) return '#fff';
    $l = ( 0.299 * hexdec( substr( $h, 0, 2 ) ) + 0.587 * hexdec( substr( $h, 2, 2 ) ) + 0.114 * hexdec( substr( $h, 4, 2 ) ) ) / 255;
    return $l > 0.62 ? '#111' : '#fff';
}

/** The CSS for your own color / image / button color - only what's in use. The Look screen's preview builds the same. */
function monospace_look_css() {
    [ , $bg, , $color ] = monospace_appearance();
    $s   = monospace_look_settings();
    $css = '';
    if ( $bg === 'color' ) $css .= 'body.bg-color{background-image:none;--page-color:' . $s['bg_color'] . '}';
    if ( $bg === 'image' && ( $u = monospace_look_image_url() ) ) {
        if ( $s['bg_fit'] === 'tile' ) {
            $css .= 'body.bg-image{background-image:url("' . esc_url_raw( $u ) . '");--page-color:' . $s['bg_color'] . ';background-repeat:repeat;background-size:auto}';
        } else {
            // "Fill the screen" stays put while the page scrolls. iOS Safari ignores background-attachment:fixed,
            // so the image sits on a fixed layer behind the page (html::before - body's own pseudos are the spotlight).
            // Sized to the large viewport (100lvh) so it doesn't rescale as iOS's toolbar collapses and expands
            $css .= 'body.bg-image{background-image:none;--page-color:' . $s['bg_color'] . '}'
                  . 'html:has(body.bg-image)::before{content:"";position:fixed;top:0;left:0;width:100%;height:100vh;height:100lvh;z-index:-1;pointer-events:none;background:url("' . esc_url_raw( $u ) . '") center/cover no-repeat}';
        }
    }
    if ( $color === 'custom' ) $css .= 'body.btn-color-custom{--btn-color:' . $s['btn_custom'] . ';--btn-text:' . monospace_look_text_on( $s['btn_custom'] ) . '}';
    // Cards: only what differs from the theme
    $d    = monospace_look_card_defaults();
    $vars = '';
    if ( [ $s['card_bg'], $s['card_bg_a'] ] !== [ $d['card_bg'], $d['card_bg_a'] ] ) $vars .= '--ms-card-bg:' . monospace_look_rgba( $s['card_bg'], $s['card_bg_a'] ) . ';';
    if ( [ $s['card_border'], $s['card_border_a'] ] !== [ $d['card_border'], $d['card_border_a'] ] ) $vars .= '--ms-card-border:' . monospace_look_rgba( $s['card_border'], $s['card_border_a'] ) . ';';
    if ( $s['card_width'] !== $d['card_width'] ) $vars .= '--ms-card-border-width:' . $s['card_width'] . 'px;';
    if ( $s['card_radius'] !== $d['card_radius'] ) $vars .= '--ms-card-radius:' . $s['card_radius'] . 'px;';
    if ( $vars !== '' ) $css .= ':root{' . $vars . '}';
    return $css;
}
add_action( 'wp_head', function () {
    $css = monospace_look_css();
    if ( function_exists( 'monospace_look_style' ) ) monospace_look_style( 'ms-look', $css ); // scoped when dark mode is on (dark-mode.php)
    elseif ( $css !== '' ) echo '<style id="ms-look">' . wp_strip_all_tags( $css ) . "</style>\n";
}, 29 );

/** Button styles */
function monospace_appearance_button_styles() {
    return [
        'glass'   => [ 'Glass',   'Glossy, with a highlight and a lift on hover' ],
        'flat'    => [ 'Flat',    'Solid color, no gloss' ],
        'outline' => [ 'Outline', 'Colored border and text on a faint tint' ],
    ];
}

/** Button colors: label, color */
function monospace_appearance_button_colors() {
    return [
        'blue'  => [ 'Blue',  'rgba(4, 107, 210, 0.9)' ],
        'black' => [ 'Black', 'rgba(0, 0, 0, 0.9)' ],
        'grey'  => [ 'Gray',  '#aaa' ],
        'custom' => [ 'Your color', monospace_look_settings()['btn_custom'] ],
    ];
}

/** Presets: label, background, button style, button color */
function monospace_appearance_presets() {
    return [
        'canvas'     => [ 'Canvas (current)', 'canvas',     'glass',   'blue' ],
        'paper'      => [ 'Paper',            'paper',      'glass',   'grey' ],
        'watercolor' => [ 'Watercolor',      'watercolor', 'glass',   'black' ],
        'gallery'    => [ 'Gallery',          'cream',      'outline', 'black' ],
        'studio'     => [ 'Studio',           'studio',     'flat',    'blue' ],
    ];
}

/** The appearance in use: [ preset, background, button style, button color ] */
function monospace_appearance() {
    $presets = monospace_appearance_presets();
    $preset  = get_option( 'monospace_appearance', 'canvas' );
    if ( isset( $presets[ $preset ] ) ) {
        return [ $preset, $presets[ $preset ][1], $presets[ $preset ][2], $presets[ $preset ][3] ];
    }
    $pick = function ( $option, $choices, $default ) {
        $value = get_option( $option, $default );
        return isset( $choices[ $value ] ) ? $value : $default;
    };
    return [
        'custom',
        $pick( 'monospace_appearance_bg', monospace_appearance_backgrounds(), 'canvas' ),
        $pick( 'monospace_appearance_buttons', monospace_appearance_button_styles(), 'glass' ),
        $pick( 'monospace_appearance_button_color', monospace_appearance_button_colors(), 'blue' ),
    ];
}

// Front end: the classes the CSS keys on
add_filter( 'body_class', function ( $classes ) {
    list( $preset, $bg, $style, $color ) = monospace_appearance();
    $classes[] = 'appearance-' . $preset;
    $classes[] = 'bg-' . $bg;
    $classes[] = 'btn-' . $style;
    $classes[] = 'btn-color-' . $color;
    return $classes;
} );

/** A small preview of a background + button, for the settings form */
function monospace_appearance_swatch( $bg, $style, $color ) {
    $backgrounds = monospace_appearance_backgrounds();
    $colors      = monospace_appearance_button_colors();
    $c           = $colors[ $color ][1] ?? $colors['blue'][1];
    $text        = $color === 'custom' ? monospace_look_text_on( $c ) : '#fff';
    return monospace_appearance_button_sample_css() . sprintf(
        '<span class="ms-swatch" style="background:%s">%s</span>',
        esc_attr( $backgrounds[ $bg ][2] ?? '#fff' ),
        monospace_appearance_button_sample( $style, $c, $text )
    );
}

/** A sample "Add to cart" button in a button style and color - drawn by the site's own rules (below) */
function monospace_appearance_button_sample( $style, $color, $text = '#fff', $attrs = '' ) {
    return '<span class="ms-btn-sample is-' . esc_attr( $style ) . '" style="--btn-color:' . esc_attr( $color ) . ';--btn-text:' . esc_attr( $text ) . '"' . $attrs . '>Add to cart</span>';
}

/**
 * The sample buttons' CSS, printed once per admin page: the site's button-glass-var / -flat-var / -outline-var mixins
 * (_button-styles.scss, with button-base from _mixins.scss), at a smaller size - incl. Glass's shine (::before), so the
 * samples look like the site's buttons. Keep in step with those mixins.
 */
function monospace_appearance_button_sample_css() {
    static $done = false;
    if ( $done ) return '';
    $done = true;
    return '<style id="ms-btn-sample-css">
        .ms-swatch { display: inline-flex; align-items: center; justify-content: center; width: 120px; height: 48px; border: 1px solid #ccc; border-radius: 4px; vertical-align: middle; box-sizing: border-box; }
        .ms-btn-sample { position: relative; display: inline-block; padding: 4px 11px; font: 600 11px/1.4 "Open Sans", -apple-system, sans-serif; color: var(--btn-text, #fff);
            border: none; border-radius: var(--ms-btn-radius, 9999px); text-align: center; white-space: nowrap; box-sizing: border-box; transition: transform .05s ease, box-shadow .15s ease; }
        .ms-btn-sample.is-glass { background-color: var(--btn-color);
            background-image: linear-gradient(to top, rgba(255,255,255,.35) 0%, rgba(255,255,255,.15) 45%, rgba(0,0,0,.05) 55%, rgba(0,0,0,.18) 100%);
            box-shadow: inset 0 1px 0 rgba(255,255,255,.55), inset 0 -1px 0 rgba(255,255,255,.3), 0 2px 3px rgba(0,0,0,.25); }
        .ms-btn-sample.is-glass::before { content: ""; position: absolute; top: 2px; left: 7px; right: 7px; height: 45%; border-radius: inherit;
            background: linear-gradient(to bottom, rgba(255,255,255,.7), rgba(255,255,255,0)); pointer-events: none; }
        .ms-btn-sample.is-flat { background-color: var(--btn-color); box-shadow: 0 2px 4px rgba(0,0,0,.1); }
        .ms-btn-sample.is-outline { color: var(--btn-color); background-color: color-mix(in srgb, var(--btn-color), transparent 94%); border: 2px solid var(--btn-color); padding: 2px 9px; }
        label:hover .ms-btn-sample.is-glass { background-color: color-mix(in srgb, var(--btn-color), white 12%); transform: scale(1.02);
            background-image: linear-gradient(to top, rgba(255,255,255,.55) 0%, rgba(255,255,255,.30) 45%, rgba(255,255,255,.05) 60%, rgba(0,0,0,.12) 100%); }
    </style>';
}

/** Layout Manager's Appearance card: the Look in use, and the way to the Look screen */
function monospace_render_appearance_link() {
    [ $preset, $bg, $style, $color ] = monospace_appearance(); ?>
    <p><?php echo monospace_appearance_swatch( $bg, $style, $color ); ?></p>
    <p>The background, buttons, site colors, fonts and the spotlight are set together, with a live preview, in
        <a class="button" href="<?php echo esc_url( admin_url( 'themes.php?page=monospace-look' ) ); ?>">Appearance &gt; Look</a></p>
    <?php
}

/** The earlier form (before Appearance > Look, 2026-10-21) - not shown anymore */
function monospace_render_appearance_settings() {
    list( $preset, $bg, $style, $color ) = monospace_appearance();
    $backgrounds = monospace_appearance_backgrounds();
    $styles      = monospace_appearance_button_styles();
    $colors      = monospace_appearance_button_colors();
    ?>
    <table class="form-table">
        <tr>
            <th scope="row">Appearance</th>
            <td>
                <fieldset id="ms-appearance">
                    <?php foreach ( monospace_appearance_presets() as $key => $p ) : ?>
                        <label style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
                            <input type="radio" name="appearance" value="<?php echo esc_attr( $key ); ?>" <?php checked( $preset, $key ); ?> />
                            <?php echo monospace_appearance_swatch( $p[1], $p[2], $p[3] ); ?>
                            <span><b><?php echo esc_html( $p[0] ); ?></b><br>
                                <span class="description"><?php echo esc_html( $backgrounds[ $p[1] ][0] . ' background · ' . $styles[ $p[2] ][0] . ' ' . strtolower( $colors[ $p[3] ][0] ) . ' buttons' ); ?></span></span>
                        </label>
                    <?php endforeach; ?>
                    <label style="display:flex;align-items:center;gap:12px">
                        <input type="radio" name="appearance" value="custom" <?php checked( $preset, 'custom' ); ?> />
                        <span><b>Custom</b> - combine a background, button style and color below</span>
                    </label>
                </fieldset>
            </td>
        </tr>
        <tr class="ms-appearance-custom">
            <th scope="row"><label for="appearance-bg">Background</label></th>
            <td>
                <select name="appearance_bg" id="appearance-bg">
                    <?php foreach ( $backgrounds as $key => $b ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $bg, $key ); ?>><?php echo esc_html( $b[0] . ' - ' . $b[1] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr class="ms-appearance-custom">
            <th scope="row"><label for="appearance-buttons">Buttons</label></th>
            <td>
                <select name="appearance_buttons" id="appearance-buttons">
                    <?php foreach ( $styles as $key => $b ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $style, $key ); ?>><?php echo esc_html( $b[0] . ' - ' . $b[1] ); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="appearance_button_color" id="appearance-button-color">
                    <?php foreach ( $colors as $key => $c ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $color, $key ); ?>><?php echo esc_html( $c[0] ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Every button that does something (Add to cart, checkout, forms, Subscribe). Prices and the "Add to cart" link color keep the site's blue.</p>
            </td>
        </tr>
    </table>
    <script>
    (function () {
        function sync() {
            var v = (document.querySelector('#ms-appearance input:checked') || {}).value;
            document.querySelectorAll('.ms-appearance-custom').forEach(function (r) { r.style.display = v === 'custom' ? '' : 'none'; });
        }
        document.querySelectorAll('#ms-appearance input').forEach(function (i) { i.addEventListener('change', sync); });
        sync();
    })();
    </script>
    <?php
}

function monospace_save_appearance_settings() {
    $presets = monospace_appearance_presets();
    $choice  = $_POST['appearance'] ?? 'canvas';
    update_option( 'monospace_appearance', isset( $presets[ $choice ] ) || $choice === 'custom' ? $choice : 'canvas' );

    foreach ( [
        'appearance_bg'           => monospace_appearance_backgrounds(),
        'appearance_buttons'      => monospace_appearance_button_styles(),
        'appearance_button_color' => monospace_appearance_button_colors(),
    ] as $field => $choices ) {
        if ( isset( $_POST[ $field ] ) && isset( $choices[ $_POST[ $field ] ] ) ) {
            update_option( 'monospace_' . $field, $_POST[ $field ] );
        }
    }
}

<?php
/**
 * Design module: Appearance
 *
 * The site's looks, collected from its stylesheets (current and earlier versions): page backgrounds,
 * button styles and button colours. Pick a preset, or "Custom" to combine the three freely.
 * Shown in Layout Manager > Appearance & Effects through the design-module registry
 * (design-modules.php).
 *
 * Front end: classes on <body> - bg-{background} btn-{style} btn-color-{colour} appearance-{preset}.
 * The CSS for them: backgrounds in _global.scss (page texture / colour and the fixed spotlight's
 * colours, as CSS variables), buttons in _components.scss + _button-styles.scss (the *-var mixins,
 * coloured by --btn-color / --btn-text).
 *
 * Options: monospace_appearance (preset key or 'custom'), and for custom monospace_appearance_bg,
 * monospace_appearance_buttons, monospace_appearance_button_color.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['appearance'] = [
        'title'       => 'Appearance',
        'description' => 'Page background, button style and button colour, site-wide.',
        'group'       => 'look',
        'order'       => 10,
        'summary'     => function () {
            list( $preset, $bg, $style, $color ) = monospace_appearance();
            $presets = monospace_appearance_presets();
            if ( isset( $presets[ $preset ] ) ) return preg_replace( '/ \(current\)$/', '', $presets[ $preset ][0] );
            return 'Custom: ' . monospace_appearance_backgrounds()[ $bg ][0] . ', ' . strtolower( monospace_appearance_button_styles()[ $style ][0] . ' ' . monospace_appearance_button_colors()[ $color ][0] ) . ' buttons';
        },
        'render'      => 'monospace_render_appearance_settings',
        'save'        => 'monospace_save_appearance_settings',
    ];
    return $modules;
} );

/** Backgrounds: label, description, swatch (CSS background for the admin preview) */
function monospace_appearance_backgrounds() {
    $img = get_template_directory_uri() . '/assets/images/';
    return [
        'canvas'     => [ 'Canvas',           'Linen canvas texture under a warm spotlight',               "url({$img}canvas.jpeg) center / 160px" ],
        'paper'      => [ 'Paper',            'Smooth grey paper texture, softer brown spotlight',         "url({$img}paper.jpeg) center / 400px" ],
        'watercolor' => [ 'Watercolour paper','Rough white watercolour paper (an earlier version)',        'url(' . content_url( 'uploads/paper2.jpg' ) . ') center / 500px' ],
        'cream'      => [ 'Cream',            'Plain warm off-white, no texture',                           'rgb(241, 237, 231)' ],
        'studio'     => [ 'Studio',           'Dark taupe with a white spotlight - the very first look',   'radial-gradient(circle at 50% 40%, #fff 20%, rgb(110, 100, 90) 140%)' ],
    ];
}

/** Button styles */
function monospace_appearance_button_styles() {
    return [
        'glass'   => [ 'Glass',   'Glossy, with a highlight and a lift on hover' ],
        'flat'    => [ 'Flat',    'Solid colour, no gloss' ],
        'outline' => [ 'Outline', 'Coloured border and text on a faint tint' ],
    ];
}

/** Button colours: label, colour */
function monospace_appearance_button_colors() {
    return [
        'blue'  => [ 'Blue',  'rgba(4, 107, 210, 0.9)' ],
        'black' => [ 'Black', 'rgba(0, 0, 0, 0.9)' ],
        'grey'  => [ 'Grey',  '#aaa' ],
    ];
}

/** Presets: label, background, button style, button colour */
function monospace_appearance_presets() {
    return [
        'canvas'     => [ 'Canvas (current)', 'canvas',     'glass',   'blue' ],
        'paper'      => [ 'Paper',            'paper',      'glass',   'grey' ],
        'watercolor' => [ 'Watercolour',      'watercolor', 'glass',   'black' ],
        'gallery'    => [ 'Gallery',          'cream',      'outline', 'black' ],
        'studio'     => [ 'Studio',           'studio',     'flat',    'blue' ],
    ];
}

/** The appearance in use: [ preset, background, button style, button colour ] */
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
    $button      = [
        'glass'   => "background:{$c} linear-gradient(to top,rgba(255,255,255,.35),rgba(0,0,0,.18));color:#fff;box-shadow:inset 0 1px 0 rgba(200,230,255,.9),0 2px 3px rgba(0,0,0,.25)",
        'flat'    => "background:{$c};color:#fff",
        'outline' => "border:2px solid {$c};color:{$c};background:rgba(255,255,255,.4)",
    ][ $style ] ?? '';
    return sprintf(
        '<span style="display:inline-flex;align-items:center;justify-content:center;width:120px;height:48px;border:1px solid #ccc;border-radius:4px;vertical-align:middle;background:%s">'
        . '<span style="font:600 11px/1 sans-serif;padding:5px 10px;border-radius:999px;%s">Add to cart</span></span>',
        esc_attr( $backgrounds[ $bg ][2] ?? '#fff' ),
        esc_attr( $button )
    );
}

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
                        <span><b>Custom</b> - combine a background, button style and colour below</span>
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
                <p class="description">Every button that does something (Add to cart, checkout, forms, Subscribe). Prices and the "Add to cart" link colour keep the site's blue.</p>
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

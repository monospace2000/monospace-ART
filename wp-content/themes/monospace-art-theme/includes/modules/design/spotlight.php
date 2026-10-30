<?php
/**
 * Appearance > Spotlight - the theme's signature light, with its own edit screen (2026-10-20, owner: "The spotlight
 * background effect is signature for this theme. It should have its own edit screen.").
 *
 * The spotlight is body::before in _global.scss: a fixed radial gradient from a light color (the bright core) to a
 * shade color (the edges), driven by CSS variables - --gradient-center-x / -y (where the light is), --gradient-start
 * (how big the bright core is), --gradient-radius (how far it reaches), --gradient-foreground / -background (light
 * and shade colors). The theme's values differ for computers, tablets (≤920px) and phones (≤640px), and each Look
 * (appearance.php) brings its own colors (Studio also a wider reach).
 *
 * Edited in Appearance > Look (look.php, since 2026-10-21 - with the background, buttons, colors and fonts; the
 * Spotlight screen's address redirects there): the site as a live preview, drag the light to move it, panels for
 * On / off, Position & size (per device) and Colors (follow the Look, or your own). Saved in the option
 * monospace_spotlight = [ on, pos => [ desktop|tablet|phone => [ x, y, core, reach ] ] or [], colors => [ light,
 * light_a, shade, shade_a ] or [] ]. Only what differs from the theme is printed (wp_head), on `html body[class]`
 * so it wins over the Looks' own values. Nothing set = the theme exactly as before.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The devices: key => [ label, media query ('' = all), preview width, preview height ] */
function monospace_spotlight_devices() {
    return [
        'desktop' => [ 'Computer', '',                   1280, 800 ],
        'tablet'  => [ 'Tablet',   '(max-width: 920px)', 820,  1080 ],
        'phone'   => [ 'Phone',    '(max-width: 640px)', 390,  780 ],
    ];
}

/** The theme's own spotlight for a Look's background (mirrors _global.scss): [ pos => [...], colors => [...] ] */
function monospace_spotlight_theme( $bg = null ) {
    if ( $bg === null ) $bg = function_exists( 'monospace_appearance' ) ? monospace_appearance()[1] : 'canvas';
    $pos = [ 'desktop' => [ 40, 40, 20, 50, 300 ], 'tablet' => [ 50, 40, 20, 80, 300 ], 'phone' => [ 50, 30, 20, 100, 300 ] ]; // x, y, core, reach, angle (300 = 10 o'clock)
    if ( $bg === 'studio' ) foreach ( $pos as &$p ) $p[3] = 120;
    unset( $p );
    $colors = [
        'canvas'     => [ '#ffffff', 80, '#532500', 20 ],
        'paper'      => [ '#ffffff', 50, '#321e00', 35 ],
        'watercolor' => [ '#ffffff', 50, '#321e00', 30 ],
        'cream'      => [ '#ffffff', 90, '#ffffff', 0 ],
        'studio'     => [ '#ffffff', 90, '#ffffff', 0 ],
    ];
    return [ 'pos' => $pos, 'colors' => $colors[ $bg ] ?? $colors['canvas'] ];
}

/** The saved settings, filled in */
function monospace_spotlight_settings() {
    $s = get_option( 'monospace_spotlight', [] );
    if ( ! is_array( $s ) ) $s = [];
    return [
        'on'     => ! isset( $s['on'] ) || $s['on'] ? 1 : 0,
        'pos'    => array_map( function ( $p ) { return array_pad( array_values( (array) $p ), 5, 300 ); }, is_array( $s['pos'] ?? null ) ? $s['pos'] : [] ), // saved before the angle existed: 4 values
        'colors' => is_array( $s['colors'] ?? null ) ? $s['colors'] : [],
    ];
}

/** #rrggbb + 0-100 → rgba() */
function monospace_spotlight_rgba( $hex, $alpha ) {
    $h = ltrim( (string) $hex, '#' );
    if ( ! preg_match( '/^[0-9a-f]{6}$/i', $h ) ) $h = 'ffffff';
    return sprintf( 'rgba(%d, %d, %d, %s)', hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ), round( max( 0, min( 100, (int) $alpha ) ) / 100, 2 ) );
}

/** The CSS for the settings ('' = the theme's own). The preview script builds the same (spot.css in the page JS). */
function monospace_spotlight_css( $s ) {
    $sel = 'html body[class]';
    $css = '';
    if ( ! $s['on'] ) return $sel . '::before,' . $sel . '::after{display:none}';
    if ( $s['colors'] ) {
        [ $l, $la, $d, $da ] = $s['colors'];
        $css .= $sel . '{--gradient-foreground:' . monospace_spotlight_rgba( $l, $la ) . ';--gradient-background:' . monospace_spotlight_rgba( $d, $da ) . '}';
    }
    foreach ( monospace_spotlight_devices() as $dev => [ , $media ] ) {
        if ( empty( $s['pos'][ $dev ] ) ) continue;
        [ $x, $y, $core, $reach, $angle ] = array_map( 'intval', $s['pos'][ $dev ] );
        $rule = $sel . "{--gradient-center-x:{$x}vw;--gradient-center-y:{$y}vh;--gradient-start:{$core}%;--gradient-radius:{$reach}vw;--beam-angle:{$angle}deg}";
        $css .= $media ? "@media $media{" . $rule . '}' : $rule;
    }
    return $css;
}

// Front end
add_action( 'wp_head', function () {
    $css = monospace_spotlight_css( monospace_spotlight_settings() );
    if ( function_exists( 'monospace_look_style' ) ) monospace_look_style( 'ms-spotlight', $css ); // scoped when dark mode is on
    elseif ( $css !== '' ) echo '<style id="ms-spotlight">' . wp_strip_all_tags( $css ) . "</style>\n";
}, 31 );

// The preview frame (Appearance > Look): the page without the admin toolbar
add_filter( 'show_admin_bar', function ( $show ) {
    return isset( $_GET['ms_spot_preview'] ) && current_user_can( 'edit_theme_options' ) ? false : $show;
} );

/**
 * The spotlight settings from the Look screen's form (look.php): what differs from the theme's own for that background.
 * $in = the form's spot[...] fields, $bg = the background being saved.
 */
function monospace_spotlight_from_input( $in, $bg ) {
    $theme = monospace_spotlight_theme( $bg );
    $s     = [ 'on' => empty( $in['on'] ) ? 0 : 1, 'pos' => [], 'colors' => [] ];
    // Position & size: kept per device only where it differs from the theme's own
    foreach ( array_keys( monospace_spotlight_devices() ) as $dev ) {
        $p = array_map( 'intval', array_slice( array_values( (array) ( $in['pos'][ $dev ] ?? [] ) ), 0, 5 ) );
        if ( count( $p ) !== 5 ) continue;
        $p = [ max( 0, min( 100, $p[0] ) ), max( 0, min( 100, $p[1] ) ), max( 0, min( 100, $p[2] ) ), max( 10, min( 250, $p[3] ) ), ( ( $p[4] % 360 ) + 360 ) % 360 ];
        if ( $p !== $theme['pos'][ $dev ] ) $s['pos'][ $dev ] = $p;
    }
    // Colors: only when "My own colors" is chosen
    if ( ( $in['color_mode'] ?? '' ) === 'custom' ) {
        $s['colors'] = [
            sanitize_hex_color( $in['light'] ?? '' ) ?: '#ffffff', max( 0, min( 100, (int) ( $in['light_a'] ?? 80 ) ) ),
            sanitize_hex_color( $in['shade'] ?? '' ) ?: '#532500', max( 0, min( 100, (int) ( $in['shade_a'] ?? 20 ) ) ),
        ];
    }
    return $s;
}

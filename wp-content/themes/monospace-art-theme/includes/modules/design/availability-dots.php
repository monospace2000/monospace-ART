<?php
/**
 * Design module: Availability dots (2026-11-17, owner: "add a toggle to add dots to all Artworks in all possible views,
 * configurable, including red for unavailable" - "red is sold, purple is private").
 *
 * The small glossy dot on an artwork's picture. Until now it showed in the grid view only, for Available (green), At gallery (blue)
 * and Reserved (yellow). Appearance > Look > Availability dots:
 *   - "On every artwork": the dot in every view - the feed's list and detail views, the Art Filter's list rows, [artwork] and the
 *     related posts under it - and for Sold (red) and Private (purple) too. Off = the grid view as before.
 *   - Which states get a dot, and each state's color (empty = the default one).
 *
 * Option monospace_avail_dots [ all => '0'|'1', states => [ state => [ on => '0'|'1', color => '#rrggbb' | '' ] ] ].
 * Printed as --ms-dot-<state>-1/-2/-3 (dark to light) and --ms-dot-<state>-rgb on :root (only for a changed color), plus a rule that hides
 * a state that is switched off - through monospace_look_style() and monospace_look_all_css(), so the dark look can have its own.
 * The dot itself is the mixin ms-dot-badge (_mixins.scss); the Art Filter's CSS repeats the defaults. The body class ms-dots-all turns
 * the dot on in every view (_archives.scss); PHP adds the markup for Sold / Private only when it is on (clickable-feed-image.php).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The states, in the order they are listed: key => [ label, default color (the mid tone of the gradient) ] */
function monospace_dots_states() {
    return [
        'available' => [ 'Available',   '#27c327' ],
        'reserved'  => [ 'Reserved',    '#f0b800' ],
        'gallery'   => [ 'At a gallery', '#2f86f0' ],
        'sold'      => [ 'Sold',        '#e03131' ],
        'private'   => [ 'Private',     '#8a4fd1' ],
    ];
}

function monospace_dots_settings() {
    $s   = get_option( 'monospace_avail_dots', [] );
    $s   = is_array( $s ) ? $s : [];
    $out = [ 'all' => ( $s['all'] ?? '0' ) === '1', 'states' => [] ];
    foreach ( monospace_dots_states() as $k => $def ) {
        $st = (array) ( $s['states'][ $k ] ?? [] );
        $out['states'][ $k ] = [
            'on'    => ( $st['on'] ?? '1' ) !== '0',
            'color' => (string) ( sanitize_hex_color( $st['color'] ?? '' ) ?: '' ),
        ];
    }
    return $out;
}

/** "On every artwork" - the dot in every view, Sold and Private included */
function monospace_dots_all() {
    return monospace_dots_settings()['all'];
}

/** The state a status stands for: available | reserved | gallery | sold | private ("At gallery (QR)" is At a gallery; anything else counts as sold) */
function monospace_dots_state( $status ) {
    $status = strtolower( trim( (string) $status ) );
    if ( in_array( $status, [ 'gallery', 'gallery-reserved' ], true ) ) return 'gallery';
    if ( in_array( $status, [ 'available', 'reserved', 'private' ], true ) ) return $status;
    return 'sold';
}

/** Is a state's dot switched on (Look > Availability dots)? */
function monospace_dots_state_on( $state ) {
    $s = monospace_dots_settings();
    return ! isset( $s['states'][ $state ] ) || $s['states'][ $state ]['on'];
}

/** #rrggbb mixed with black / white: $t 0..1 */
function monospace_dots_mix( $hex, $with, $t ) {
    $h = ltrim( $hex, '#' );
    if ( strlen( $h ) === 3 ) $h = $h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2];
    $rgb = [ hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ) ];
    $to  = $with === 'white' ? 255 : 0;
    return sprintf( '#%02x%02x%02x', ...array_map( fn( $c ) => (int) round( $c + ( $to - $c ) * $t ), $rgb ) );
}

/** The CSS: the colors that were changed, and the states that are switched off */
function monospace_dots_css() {
    $s   = monospace_dots_settings();
    $vars = '';
    $hide = [];
    foreach ( $s['states'] as $k => $st ) {
        if ( $st['color'] !== '' ) {
            $c = $st['color'];
            if ( strlen( $c ) === 4 ) $c = '#' . $c[1] . $c[1] . $c[2] . $c[2] . $c[3] . $c[3];
            $vars .= '--ms-dot-' . $k . '-1:' . monospace_dots_mix( $c, 'black', 0.28 ) . ';--ms-dot-' . $k . '-2:' . $c . ';--ms-dot-' . $k . '-3:' . monospace_dots_mix( $c, 'white', 0.35 )
                  . ';--ms-dot-' . $k . '-rgb:' . hexdec( substr( $c, 1, 2 ) ) . ',' . hexdec( substr( $c, 3, 2 ) ) . ',' . hexdec( substr( $c, 5, 2 ) ) . ';';
        }
        if ( ! $st['on'] ) {
            $hide[] = '.ms-availability-badge.ms-' . $k;
            $hide[] = '.ms-availability-dot.is-' . $k;
        }
    }
    return ( $vars !== '' ? ':root{' . $vars . '}' : '' ) . ( $hide ? implode( ',', $hide ) . '{display:none!important}' : '' );
}

add_action( 'wp_head', function () {
    if ( function_exists( 'monospace_look_style' ) ) monospace_look_style( 'ms-avail-dots', monospace_dots_css() );
}, 31 );

add_filter( 'body_class', function ( $classes ) {
    if ( monospace_dots_all() ) $classes[] = 'ms-dots-all';
    return $classes;
} );

/** The legend of the dots in use, for the Art Filter: a dot and its state, the ones switched on (Sold and Private only when every artwork has one) */
function monospace_dots_legend() {
    $s   = monospace_dots_settings();
    $out = [];
    foreach ( monospace_dots_states() as $k => [ $label ] ) {
        if ( ! $s['states'][ $k ]['on'] ) continue;
        if ( ! $s['all'] && in_array( $k, [ 'sold', 'private' ], true ) ) continue;
        $out[] = '<span class="ms-availability-dot is-' . $k . '"></span> ' . esc_html( strtolower( $k === 'gallery' ? 'at a gallery' : $label ) );
    }
    return implode( ' &nbsp;·&nbsp; ', $out );
}

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['availability-dots'] = [
        'title'       => 'Availability dots',
        'description' => 'The small dot on an artwork’s picture: which views show it, which states get one, and their colors.',
        'group'       => 'look',
        'order'       => 95,
        'summary'     => function () {
            $s = monospace_dots_settings();
            $n = count( array_filter( $s['states'], fn( $x ) => $x['on'] ) );
            return ( $s['all'] ? 'Every artwork, every view' : 'Grid view' ) . ' · ' . $n . ' state' . ( $n === 1 ? '' : 's' );
        },
        'render'      => 'monospace_render_availability_dots',
        'save'        => 'monospace_save_availability_dots',
    ];
    return $modules;
} );

function monospace_render_availability_dots() {
    $s = monospace_dots_settings(); ?>
    <table class="form-table" role="presentation" id="ms-dots">
        <tr>
            <th scope="row">Where</th>
            <td>
                <input type="hidden" name="ms_dots[seen]" value="1">
                <label><input type="checkbox" name="ms_dots[all]" value="1" <?php checked( $s['all'] ); ?>> A dot on every artwork, in every view</label>
                <p class="description">On: the dot also shows in the list and detail views, in the Art Filter’s list, on the pictures from [artwork] and in the related posts, and Sold and Private pieces get one too. Off: only the grid view and the Art Filter’s grid have dots (Available, Reserved and At a gallery).</p>
            </td>
        </tr>
        <tr>
            <th scope="row">States</th>
            <td>
                <?php foreach ( monospace_dots_states() as $k => [ $label, $default ] ) :
                    $st = $s['states'][ $k ]; ?>
                    <p style="margin:0 0 8px;display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <label style="min-width:130px"><input type="checkbox" name="ms_dots[states][<?php echo esc_attr( $k ); ?>][on]" value="1" <?php checked( $st['on'] ); ?>> <?php echo esc_html( $label ); ?></label>
                        <input type="color" class="ms-dots-pick" data-state="<?php echo esc_attr( $k ); ?>" data-default="<?php echo esc_attr( $default ); ?>" value="<?php echo esc_attr( $st['color'] ?: $default ); ?>">
                        <input type="hidden" name="ms_dots[states][<?php echo esc_attr( $k ); ?>][color]" value="<?php echo esc_attr( $st['color'] ); ?>">
                        <button type="button" class="button-link ms-dots-reset">Default color</button>
                        <?php if ( in_array( $k, [ 'sold', 'private' ], true ) ) : ?><span class="description">only with “every artwork” on</span><?php endif; ?>
                    </p>
                <?php endforeach; ?>
                <p class="description">An unticked state gets no dot at all. The dot is always a glossy ball; the color you pick is its middle tone.</p>
            </td>
        </tr>
    </table>
    <script>
    (function () {
        var wrap = document.getElementById('ms-dots'); if (!wrap) return;
        wrap.querySelectorAll('.ms-dots-pick').forEach(function (pick) {
            var hid = pick.nextElementSibling, reset = hid.nextElementSibling;
            pick.addEventListener('input', function () { hid.value = pick.value; });
            reset.addEventListener('click', function () { hid.value = ''; pick.value = pick.getAttribute('data-default'); });
        });
    })();
    </script>
    <?php
}

function monospace_save_availability_dots() {
    if ( empty( $_POST['ms_dots']['seen'] ) ) return;
    $in  = wp_unslash( $_POST['ms_dots'] );
    $out = [ 'all' => ! empty( $in['all'] ) ? '1' : '0', 'states' => [] ];
    foreach ( monospace_dots_states() as $k => $def ) {
        $st = (array) ( $in['states'][ $k ] ?? [] );
        $out['states'][ $k ] = [ 'on' => ! empty( $st['on'] ) ? '1' : '0', 'color' => sanitize_hex_color( $st['color'] ?? '' ) ?: '' ];
    }
    update_option( 'monospace_avail_dots', $out );
}

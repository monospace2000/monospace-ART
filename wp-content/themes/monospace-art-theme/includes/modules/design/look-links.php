<?php
/**
 * Look: light and dark linked, panel by panel (2026-10-22, owner: "Dark Mode Look edit is missing the logo box. Both edit
 * screens need to have the same boxes" ... "add link toggles to the edit boxes so when editing say, font sizes in dark it
 * automatically changes settings in light mode as well").
 *
 * Both Look screens (Light, Dark) show every panel. Each panel has a link:
 *   - panels dark mode can change (background, buttons, cards, colors, menu bar, fonts, spotlight, home boxes,
 *     captions, rounding, banners, shadows, secondary buttons): a toggle, "Same in light and dark" - when on, Apply on
 *     either screen also writes that panel's settings into the other look. Option monospace_look_links = [ panel id ]
 *     (not part of a look: it isn't saved with the looks).
 *   - the rest (logo, price card, share, print preview, sidebar, the effects): always linked - dark mode only swaps
 *     colors and styles in the browser, the page itself is the same. The dark look never keeps its own copy of these:
 *     they are copied on every Apply, and read from the site whenever the dark look is (monospace_dark_snapshot()).
 * Which settings each panel holds: monospace_look_link_map() - options (or some keys of one) and theme mods. A setting
 * in the saved looks (monospace_look_snapshot_names()) that no panel there claims counts as always linked.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The panels light and dark can differ in: panel id => parts [ 'o' | 'm', name, keys (a list, a prefix, or none) ] */
function monospace_look_link_map() {
    $colors = function_exists( 'monospace_customizer_colors' ) ? array_keys( monospace_customizer_colors() ) : [];
    $menu   = [ 'ms_color_menu_bg', 'ms_color_menu_link', 'ms_color_menu_link_hover' ];
    $fonts  = function_exists( 'monospace_customizer_font_roles' ) ? array_keys( monospace_customizer_font_roles() ) : [];
    $mods   = fn( $names ) => array_map( fn( $n ) => [ 'm', $n ], array_values( $names ) );
    $map = [
        'looks'   => [ [ 'o', 'monospace_appearance' ], [ 'o', 'monospace_appearance_bg' ], [ 'o', 'monospace_appearance_buttons' ], [ 'o', 'monospace_appearance_button_color' ] ], // a combination: background + buttons
        'bg'      => [ [ 'o', 'monospace_appearance_bg' ], [ 'o', 'monospace_look', [ 'bg_color', 'bg_image', 'bg_fit' ] ] ],
        'buttons' => [ [ 'o', 'monospace_appearance_buttons' ], [ 'o', 'monospace_appearance_button_color' ], [ 'o', 'monospace_look', [ 'btn_custom' ] ] ],
        'cards'   => [ [ 'o', 'monospace_look', 'card_' ] ],
        'colors'  => $mods( array_diff( $colors, $menu ) ),
        'menu'    => $mods( array_merge( array_intersect( $colors, $menu ), [ 'ms_menu_bg_opacity', 'ms_menu_shadow', 'ms_menu_bevel' ] ) ),
        'fonts'   => $mods( array_merge( $fonts, [ 'ms_font_size', 'ms_line_height' ] ) ),
        'on'      => [ [ 'o', 'monospace_spotlight', [ 'on' ] ] ],
        'pos'     => [ [ 'o', 'monospace_spotlight', [ 'pos' ] ] ],
        'scolors' => [ [ 'o', 'monospace_spotlight', [ 'colors' ] ] ],
    ];
    foreach ( [ 'home-box-style' => 'monospace_home_box_style', 'caption-style' => 'monospace_caption_style', 'rounding' => 'monospace_rounding',
                'banner-style' => 'monospace_banner_style', 'shadows' => 'monospace_shadows', 'secondary-buttons' => 'monospace_secondary_buttons' ] as $key => $option ) {
        $map[ 'm-' . $key ] = [ [ 'o', $option ] ];
    }
    $map['m-logo-layout'] = [ [ 'o', 'monospace_logo_colors' ] ]; // the logo's colors (its layout stays the same in both looks)
    return $map;
}

/** The settings always the same in light and dark: [ 'options' => [], 'mods' => [] ] */
function monospace_look_link_shared() {
    $claimed = [ 'o' => [], 'm' => [] ];
    foreach ( monospace_look_link_map() as $parts ) foreach ( $parts as $p ) $claimed[ $p[0] ][] = $p[1];
    $names = monospace_look_snapshot_names();
    return [ 'options' => array_values( array_diff( $names['options'], $claimed['o'] ) ), 'mods' => array_values( array_diff( $names['mods'], $claimed['m'] ) ) ];
}

/** The panels linked by the owner */
function monospace_look_links() {
    $l = get_option( 'monospace_look_links', [] );
    return is_array( $l ) ? array_values( array_intersect( $l, array_keys( monospace_look_link_map() ) ) ) : [];
}

/** $to with the linked panels' settings (and the always-shared ones) taken from $from - both snapshots */
function monospace_look_link_copy( array $from, array $to, $panels ) {
    $map = monospace_look_link_map();
    foreach ( $panels as $id ) {
        foreach ( $map[ $id ] ?? [] as $p ) {
            $k   = $p[0] === 'o' ? 'options' : 'mods';
            $src = $from[ $k ][ $p[1] ] ?? null;
            if ( ! isset( $p[2] ) ) { $to[ $k ][ $p[1] ] = $src; continue; }
            // Some keys of one option: those keys from $from, the rest of $to's as they are
            $cur  = is_array( $to[ $k ][ $p[1] ] ?? null ) ? $to[ $k ][ $p[1] ] : [];
            $mine = fn( $key ) => is_array( $p[2] ) ? in_array( $key, $p[2], true ) : strpos( (string) $key, $p[2] ) === 0;
            foreach ( array_keys( $cur ) as $key ) if ( $mine( $key ) ) unset( $cur[ $key ] );
            foreach ( (array) $src as $key => $v ) if ( $mine( $key ) ) $cur[ $key ] = $v;
            $to[ $k ][ $p[1] ] = $cur ?: null;
        }
    }
    $shared = monospace_look_link_shared();
    foreach ( [ 'options', 'mods' ] as $k ) foreach ( $shared[ $k ] as $n ) $to[ $k ][ $n ] = $from[ $k ][ $n ] ?? null;
    // The preset name follows its background and buttons (one linked, the other not: "custom" unless they match one)
    if ( function_exists( 'monospace_appearance_presets' ) ) {
        $o = $to['options']; $preset = 'custom';
        foreach ( monospace_appearance_presets() as $key => $pr ) {
            if ( [ $pr[1], $pr[2], $pr[3] ] === [ $o['monospace_appearance_bg'] ?? 'canvas', $o['monospace_appearance_buttons'] ?? 'glass', $o['monospace_appearance_button_color'] ?? 'blue' ] ) { $preset = $key; break; }
        }
        if ( ( $o['monospace_appearance'] ?? null ) !== null || $preset !== 'custom' ) $to['options']['monospace_appearance'] = $preset;
    }
    return $to;
}

/** The dark look as read anywhere: the always-shared settings are the site's (dark-mode.php, monospace_dark_snapshot()) */
function monospace_look_link_overlay( array $dark ) {
    $live   = monospace_look_snapshot();
    $shared = monospace_look_link_shared();
    foreach ( [ 'options', 'mods' ] as $k ) foreach ( $shared[ $k ] as $n ) $dark[ $k ][ $n ] = $live[ $k ][ $n ] ?? null;
    return $dark;
}

// Apply (not the Presets' Load / Save): the links as ticked, then - after the save - the other look gets the linked
// panels and the shared settings
add_action( 'admin_post_monospace_look', function () {
    if ( ! current_user_can( 'edit_theme_options' ) || ! empty( $_POST['look_load'] ) || ! empty( $_POST['look_save'] ) || empty( $_POST['look_link_seen'] ) ) return;
    check_admin_referer( 'monospace_look' );
    $ids = array_values( array_intersect( array_keys( (array) ( $_POST['look_link'] ?? [] ) ), array_keys( monospace_look_link_map() ) ) );
    $ids ? update_option( 'monospace_look_links', $ids, false ) : delete_option( 'monospace_look_links' );
    if ( ! function_exists( 'monospace_dark_snapshot' ) ) return;

    if ( ( $_POST['look_mode'] ?? '' ) === 'dark' ) {
        // After dark-mode.php (99) kept the dark look and put the light one back: the light look gets them
        add_action( 'monospace_look_saved', function () use ( $ids ) {
            $dark = get_option( 'monospace_look_dark' );
            if ( is_array( $dark ) && ! empty( $dark['options'] ) ) monospace_look_restore( monospace_look_link_copy( $dark, monospace_look_snapshot(), $ids ) );
        }, 100 );
    } else {
        // The dark look gets them (an unsaved one - the starting dark look - only when a panel is linked)
        add_action( 'monospace_look_saved', function () use ( $ids ) {
            $dark = get_option( 'monospace_look_dark' );
            $has  = is_array( $dark ) && ! empty( $dark['options'] );
            if ( ! $has && ! $ids ) return;
            $base = $has ? monospace_look_snapshot_complete( $dark ) : monospace_dark_starter();
            update_option( 'monospace_look_dark', monospace_look_link_copy( monospace_look_snapshot(), $base, $ids ), false );
        }, 50 );
    }
}, 1 );

// The screen: in each panel, its link - a toggle, or "always the same" - and a chip in its title line
add_filter( 'monospace_look_panels', function ( $out ) {
    $map   = monospace_look_link_map();
    $links = monospace_look_links();
    foreach ( $out as $id => $html ) {
        $can = isset( $map[ $id ] );
        $on  = ! $can || in_array( $id, $links, true );
        $chip = '<span class="lm-chip ms-link-chip' . ( $on ? '' : ' is-empty' ) . '"' . ( $on ? '' : ' hidden' ) . ' title="Same in light and dark">🔗 Light &amp; dark</span>';
        $row  = $can
            ? '<label class="ms-link-row"><input type="checkbox" name="look_link[' . esc_attr( $id ) . ']" value="1"' . checked( $on, true, false ) . '> 🔗 <strong>Same in light and dark</strong>'
              . ' <span class="description">- Apply on either screen sets this panel in both looks</span></label>'
            : '<p class="ms-link-row is-fixed">🔗 <strong>Always the same in light and dark</strong> <span class="description">- dark mode changes colors, not this</span></p>';
        $html = preg_replace( '#(<span class="lm-t">.*?</span>)#s', '$1 ' . $chip, $html, 1 );
        $html = preg_replace( '#<div class="lm-body">#', '<div class="lm-body">' . $row, $html, 1 );
        $out[ $id ] = $html;
    }
    return $out;
} );

add_action( 'monospace_look_save_buttons', function () {
    echo '<input type="hidden" name="look_link_seen" value="1">'; ?>
    <style>
        .ms-link-row { display: block; margin: 0 0 12px; padding: 6px 10px; background: #f6f7f7; border-radius: 4px; }
        .ms-link-row.is-fixed { color: #646970; }
        .ms-link-chip { margin-left: 4px; }
    </style>
    <script>
    // The chip follows the toggle
    document.addEventListener('change', function (e) {
        var t = e.target; if (!t.name || t.name.indexOf('look_link[') !== 0) return;
        var chip = t.closest('details') && t.closest('details').querySelector('.ms-link-chip');
        if (chip) chip.hidden = !t.checked;
    });
    </script>
    <?php
}, 6 );

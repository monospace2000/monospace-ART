<?php
/**
 * Appearance > Look: your default look and the factory default (2026-10-21, owner: "Save Look As Default and Restore
 * Default Look / Restore Factory Default (which is the current look at this very moment)").
 *
 * A snapshot = every setting the Look screen covers, each with its value or "not set" (null): background and buttons,
 * site colors, menu bar, cards, fonts & text, spotlight, and the panels' modules (print preview, price card, home page
 * boxes, rounding, scroll effect, rollover effect) - monospace_look_snapshot_names(); more through the filter
 * monospace_look_snapshot_names. Restoring writes each back (not set = deleted / removed), then empties the page cache.
 *   Factory default           one per look: light = monospace_look_factory (taken once, never overwritten), dark =
 *                             monospace_look_factory_dark if set, else monospace_dark_starter() (made from the light one).
 *                             Both were set to the then-active looks on 2026-10-24 (scripts/save-looks-as-factory.php)
 *   Presets                   three per look (monospace_look_presets())
 * All in the Presets panel above the Save bar (hook monospace_look_saved_looks in look.php): Factory default and the three
 * presets of the look on the screen. Each row has Load (puts it on the screen as the Current Look, nothing written) and
 * Save (keeps the Current Look there, the site unchanged) - see "Load / Save" below. Only Apply Light Mode / Apply Dark
 * Mode changes the site. (2026-10-22, owner: "The Save Presets is needlessly confusing")
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** What a snapshot holds: [ 'options' => [ option names ], 'mods' => [ theme mod names ] ] */
function monospace_look_snapshot_names() {
    $mods = [ 'ms_menu_bg_opacity', 'ms_menu_shadow', 'ms_menu_bevel', 'ms_font_size', 'ms_line_height' ];
    if ( function_exists( 'monospace_customizer_colors' ) ) $mods = array_merge( $mods, array_keys( monospace_customizer_colors() ) );
    if ( function_exists( 'monospace_customizer_font_roles' ) ) $mods = array_merge( $mods, array_keys( monospace_customizer_font_roles() ) );
    return apply_filters( 'monospace_look_snapshot_names', [
        'options' => [
            // Background, buttons, cards (appearance.php), spotlight
            'monospace_appearance', 'monospace_appearance_bg', 'monospace_appearance_buttons', 'monospace_appearance_button_color',
            'monospace_look', 'monospace_spotlight',
            // The panels' modules
            'monospace_print_preview', 'monospace_placeholder_image',
            'monospace_price_card_page', 'monospace_price_card_phone', 'monospace_price_card_feed', 'monospace_price_card_tab', 'monospace_price_card_badge_align',
            'monospace_price_card_standalone',
            'monospace_home_box_style', 'monospace_rounding', 'monospace_caption_style', 'monospace_logo_mobile', 'monospace_tagline_align',
            'monospace_logo_desktop', 'monospace_logo_art', 'monospace_logo_colors', 'monospace_share', 'monospace_banner_style', 'monospace_shadows', 'monospace_secondary_buttons', 'monospace_sidebar_position', 'monospace_tagline_align_mobile', 'monospace_tagline_breaks', 'monospace_tagline_breaks_mobile',
            'monospace_scroll_effect', 'monospace_scroll_focus', 'monospace_rubber_smooth', 'monospace_rubber_stagger', 'monospace_rubber_touch',
            'monospace_slide_bounce', 'monospace_slide_bounce_phone', 'monospace_slide_distance', 'monospace_slide_distance_phone',
            'monospace_slide_duration', 'monospace_slide_settle', 'monospace_slide_settle_phone',
            'monospace_image_fade', 'monospace_hover_effect', 'monospace_hover_strength', 'monospace_hover_targets', 'monospace_press_targets', 'monospace_press_touch',
        ],
        'mods' => array_values( array_unique( $mods ) ),
    ] );
}

/** The look as it is saved now */
function monospace_look_snapshot() {
    $names = monospace_look_snapshot_names();
    $none  = new stdClass();
    $snap  = [ 'taken' => time(), 'options' => [], 'mods' => [] ];
    foreach ( $names['options'] as $n ) { $v = get_option( $n, $none ); $snap['options'][ $n ] = $v === $none ? null : $v; }
    foreach ( $names['mods'] as $n ) { $v = get_theme_mod( $n, $none ); $snap['mods'][ $n ] = $v === $none ? null : $v; }
    return $snap;
}

/** Put a snapshot back: each setting as it was, not set = removed */
function monospace_look_restore( $snap ) {
    if ( ! is_array( $snap ) || empty( $snap['options'] ) ) return false;
    foreach ( (array) $snap['options'] as $n => $v ) $v === null ? delete_option( $n ) : update_option( $n, $v );
    foreach ( (array) ( $snap['mods'] ?? [] ) as $n => $v ) $v === null ? remove_theme_mod( $n ) : set_theme_mod( $n, $v );
    if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache();
    return true;
}

/** The factory default: the look at the moment it's first taken - never overwritten */
function monospace_look_snapshot_factory() {
    return add_option( 'monospace_look_factory', monospace_look_snapshot(), '', false );
}

/* ---------- My looks: saved looks, one list for the light and the dark look (2026-10-22, owner: "Dark Mode and Light
 * Mode should share the same Saved Settings files (when on Dark Mode Edit screen it just saves as Dark Mode)"; three
 * each for light and dark from 2026-10-21) ----------
 * Option monospace_look_slots = [ 1..n => [ 'name', 'snap', 'taken', 'mode' => light | dark ] ] - 'mode' = which look was
 * on the screen when it was saved (shown on its row). Saved from the Dark look screen, a row holds the dark look; loaded
 * there, it becomes what the dark look is edited from - and the same on the Light look screen. The old format (a list
 * per mode) reads as one list, light first. My default look (monospace_look_default) is shared the same way.
 */
function monospace_look_slots() {
    $s = get_option( 'monospace_look_slots', [] );
    if ( ! is_array( $s ) ) return [];
    if ( isset( $s['light'] ) || isset( $s['dark'] ) ) { // before 2026-10-22: a list per mode
        $out = [];
        foreach ( [ 'light', 'dark' ] as $m ) {
            foreach ( (array) ( $s[ $m ] ?? [] ) as $n => $slot ) {
                if ( ! is_array( $slot ) || empty( $slot['snap'] ) ) continue;
                $k = max( 1, (int) $n );
                while ( isset( $out[ $k ] ) ) $k++;
                $out[ $k ] = $slot + [ 'mode' => $m ];
            }
        }
        ksort( $out );
        return $out;
    }
    return $s;
}

/* ---------- Presets: three per look (2026-10-22, owner: "Create a single Factory Default for Dark, and one for Light ...
 * allow me to load and save three different presets per mode") ----------
 * Option monospace_look_presets = [ 'light' => [ 1..3 => [ 'name', 'snap', 'taken' ] ], 'dark' => [ 1..3 => ... ] ]. Until
 * a preset is first saved the option doesn't exist and the old data (monospace_look_slots - one list for both looks, each
 * row with its 'mode' - and monospace_look_default) is read in its place, in memory: each look's rows in order, then My
 * default look, the first three of each. The old options are left as they are. */
function monospace_look_presets( $mode = null ) {
    $all = get_option( 'monospace_look_presets', null );
    if ( ! is_array( $all ) ) {
        $lists = [ 'light' => [], 'dark' => [] ];
        foreach ( monospace_look_slots() as $slot ) {
            if ( ! is_array( $slot ) || empty( $slot['snap'] ) ) continue;
            $lists[ ( $slot['mode'] ?? 'light' ) === 'dark' ? 'dark' : 'light' ][] = [ 'name' => (string) ( $slot['name'] ?? '' ), 'snap' => $slot['snap'], 'taken' => (int) ( $slot['taken'] ?? 0 ) ];
        }
        $d = get_option( 'monospace_look_default' );
        if ( is_array( $d ) && ! empty( $d['options'] ) ) $lists[ ( $d['mode'] ?? 'light' ) === 'dark' ? 'dark' : 'light' ][] = [ 'name' => 'My default look', 'snap' => $d, 'taken' => (int) ( $d['taken'] ?? 0 ) ];
        $all = [];
        foreach ( $lists as $m => $list ) { $list = array_slice( $list, 0, 3 ); $all[ $m ] = $list ? array_combine( range( 1, count( $list ) ), $list ) : []; }
    }
    $all += [ 'light' => [], 'dark' => [] ];
    return $mode ? (array) $all[ $mode === 'dark' ? 'dark' : 'light' ] : $all;
}

/* ---------- Load / Save (2026-10-21, owner: "Only the big blue Apply button at bottom applies the look. [Load] just loads
 * the settings") ----------
 * Load    shows a saved look's settings on the Look screen and in its preview - nothing written: the screen is opened
 *         with &load=<key> and the snapshot put in front of the saved settings (monospace_dark_apply() - filters only).
 *         The form carries look_loaded=<key>, so Apply writes the whole snapshot first, then the form.
 * Save    keeps what's on the screen in a row without changing the site: the form is saved as usual, the result kept,
 *         then the site's look put back as it was.
 * Keys: factory (each look's own), 1-3 (that look's presets).
 */

/** The current mode of the Look screen / its form */
function monospace_look_mode() {
    return function_exists( 'monospace_dark_editing' ) && monospace_dark_editing() ? 'dark' : 'light';
}

/** A saved look's snapshot (null = nothing kept there) */
function monospace_look_saved_snapshot( $mode, $key ) {
    if ( $key === 'factory' || $key === 'start' ) { // the Factory default of that look
        if ( $mode === 'dark' ) return function_exists( 'monospace_dark_starter' ) ? monospace_dark_starter() : null;
        $snap = get_option( 'monospace_look_factory' );
    } else {
        $snap = monospace_look_presets( $mode )[ (int) $key ]['snap'] ?? null;
    }
    return is_array( $snap ) && ! empty( $snap['options'] ) ? monospace_look_snapshot_complete( $snap ) : null;
}

/** A snapshot with every look setting in it: one added to the Look after it was saved (shadows, secondary buttons ...)
 *  comes back as not set - its default, as the site looked then - instead of keeping whatever the site has now
 *  (2026-10-22, owner: "A restored Look is missing settings for newer additions") */
function monospace_look_snapshot_complete( $snap ) {
    $names = monospace_look_snapshot_names();
    foreach ( [ 'options', 'mods' ] as $k ) {
        $snap[ $k ] = (array) ( $snap[ $k ] ?? [] );
        foreach ( $names[ $k ] as $n ) if ( ! array_key_exists( $n, $snap[ $k ] ) ) $snap[ $k ][ $n ] = null;
    }
    return $snap;
}

/** The look loaded on this request (?load= on the screen, look_loaded in its form, ms_look_load in the preview): [ mode, key ] or null */
function monospace_look_loaded() {
    if ( ! current_user_can( 'edit_theme_options' ) ) return null;
    if ( isset( $_GET['ms_spot_preview'], $_GET['ms_look_load'] ) ) {
        [ $mode, $key ] = array_pad( explode( ':', sanitize_text_field( wp_unslash( $_GET['ms_look_load'] ) ) ), 2, '' );
        $mode = $mode === 'dark' ? 'dark' : 'light';
    } else {
        $key  = sanitize_key( $_POST['look_loaded'] ?? ( is_admin() && ( $_GET['page'] ?? '' ) === 'monospace-look' ? ( $_GET['load'] ?? '' ) : '' ) );
        $mode = monospace_look_mode();
    }
    return $key !== '' && monospace_look_saved_snapshot( $mode, $key ) ? [ $mode, $key ] : null;
}

// Load: back to the screen with the look loaded (nothing saved; unsaved changes on the screen are dropped)
add_action( 'admin_post_monospace_look', function () {
    $key = sanitize_key( $_POST['look_load'] ?? '' );
    if ( $key === '' ) return;
    if ( ! current_user_can( 'edit_theme_options' ) ) wp_die( 'Not allowed.' );
    check_admin_referer( 'monospace_look' );
    $mode = monospace_look_mode();
    $ok   = (bool) monospace_look_saved_snapshot( $mode, $key );
    wp_safe_redirect( admin_url( 'themes.php?page=monospace-look' . ( $mode === 'dark' ? '&mode=dark' : '' ) . ( $ok ? '&load=' . $key . '&look_msg=loaded' : '&look_msg=none' ) ) );
    exit;
}, 1 );

// The screen (after the dark look is put in front - admin_init 10) and its preview frame (after the dark preview - wp_loaded 10)
add_action( 'admin_init', function () {
    if ( $_SERVER['REQUEST_METHOD'] === 'POST' || ! ( $l = monospace_look_loaded() ) || ! function_exists( 'monospace_dark_apply' ) ) return;
    monospace_dark_apply( monospace_look_saved_snapshot( $l[0], $l[1] ) );
}, 20 );
add_action( 'wp_loaded', function () {
    if ( is_admin() || ! isset( $_GET['ms_spot_preview'] ) || ! ( $l = monospace_look_loaded() ) || ! function_exists( 'monospace_dark_apply' ) ) return;
    monospace_dark_apply( monospace_look_saved_snapshot( $l[0], $l[1] ) );
}, 20 );

// Apply / Save with a look loaded: the loaded look is written first (after dark mode has set the light look
// aside - priority 2), then the form on top of it (priority 10)
add_action( 'admin_post_monospace_look', function () {
    if ( ! ( $l = monospace_look_loaded() ) ) return;
    check_admin_referer( 'monospace_look' );
    monospace_look_restore( monospace_look_saved_snapshot( $l[0], $l[1] ) );
}, 3 );

// Save (a row): keep the site's look aside first ...
add_action( 'admin_post_monospace_look', function () {
    $key = sanitize_key( $_POST['look_save'] ?? '' );
    if ( ! in_array( $key, [ '1', '2', '3' ], true ) || ! current_user_can( 'edit_theme_options' ) ) return;
    check_admin_referer( 'monospace_look' );
    $mode   = monospace_look_mode();
    $before = $mode === 'dark' ? get_option( 'monospace_look_dark' ) : monospace_look_snapshot();
    // ... and after the save (and dark mode's, priority 99): keep the result in the row, put the site's look back
    add_action( 'monospace_look_saved', function () use ( $key, $mode, $before ) {
        $snap = $mode === 'dark' ? get_option( 'monospace_look_dark' ) : monospace_look_snapshot();
        if ( is_array( $snap ) && ! empty( $snap['options'] ) ) {
            $all   = monospace_look_presets(); // the old data becomes the new option here, the first time
            $names = (array) ( $_POST['look_slot_name'] ?? [] );
            $all[ $mode ][ (int) $key ] = [ 'name' => sanitize_text_field( wp_unslash( $names[ $key ] ?? '' ) ) ?: 'Preset ' . $key, 'snap' => $snap, 'taken' => time() ];
            ksort( $all[ $mode ] );
            update_option( 'monospace_look_presets', $all, false );
        }
        if ( $mode === 'dark' ) {
            is_array( $before ) ? update_option( 'monospace_look_dark', $before, false ) : delete_option( 'monospace_look_dark' );
        } else {
            monospace_look_restore( $before );
        }
        // Back to the screen showing what was just kept (the site's look is unchanged)
        add_filter( 'wp_redirect', fn( $to ) => add_query_arg( [ 'look_msg' => 'kept', 'kept' => $key, 'load' => $key, 'updated' => false ], $to ) );
    }, 200 );
}, 1 );

// The Presets panel, above the Save bar (2026-10-22, owner: "The Save Presets is needlessly confusing ... 'Current Look' is the
// settings that's currently active for either Dark or Light. Restoring factory default will load it into the Current Look
// for that mode. Then allow me to load and save three different presets per mode. Save will save the Current Look in that
// slot, load will load the selected look into Current."): the rows of the look on the screen - its Factory default (Load
// only) and three presets (Load, Save). Load puts a look on this screen as the Current Look, nothing written; Save keeps the
// Current Look in that slot; only Apply Light Mode / Apply Dark Mode changes the site.
add_action( 'monospace_look_saved_looks', function () {
    $mode   = monospace_look_mode();
    $dark   = $mode === 'dark';
    $name   = $dark ? 'Dark' : 'Light';
    $slots  = monospace_look_presets( $mode );
    $loaded = monospace_look_loaded();
    $date   = fn( $t ) => $t ? wp_date( get_option( 'date_format' ) . ', ' . get_option( 'time_format' ), $t ) : '';
    $count  = count( array_filter( $slots, fn( $l ) => ! empty( $l['snap'] ) ) );
    $open   = $loaded || in_array( sanitize_key( $_GET['look_msg'] ?? '' ), [ 'kept', 'loaded', 'none' ], true );
    $load   = function ( $key, $label, $enabled ) use ( $name ) {
        return '<button type="submit" class="button" name="look_load" value="' . esc_attr( $key ) . '"' . disabled( ! $enabled, true, false )
             . ' onclick="return confirm(\'Load ' . esc_js( $label ) . ' as the Current ' . esc_js( $name ) . ' Look? What is on this screen and not yet applied is replaced. The site changes only when you press Apply ' . esc_js( $name ) . ' Mode.\')">Load</button>';
    };
    $save   = function ( $key, $label, $replace ) use ( $name ) {
        return '<button type="submit" class="button" name="look_save" value="' . esc_attr( $key ) . '"'
             . ( $replace ? ' onclick="return confirm(\'Save the Current ' . esc_js( $name ) . ' Look as ' . esc_js( $label ) . ', in place of the one kept there?\')"' : '' ) . '>Save</button>';
    };
    $row    = fn( $key ) => $loaded && $loaded[1] === (string) $key ? ' class="ms-sl-loaded"' : ''; ?>
    <?php if ( $loaded ) : ?><input type="hidden" name="look_loaded" value="<?php echo esc_attr( $loaded[1] ); ?>"><?php endif; ?>
    <details class="lm-panel ms-saved-looks" data-panel="saved-looks"<?php echo $open ? ' open' : ''; ?>>
        <summary><span class="lm-t">Presets: <?php echo esc_html( $name ); ?> look</span>
            <span class="lm-chip <?php echo $count ? 'is-on' : 'is-empty'; ?>"><?php echo $count ? $count . ' of 3 saved' : 'None saved'; ?></span>
            <span class="lm-sub">Factory default and three presets - Load puts one on this screen, Save keeps this screen in one</span></summary>
        <div class="lm-body">
            <p class="description">The <strong>Current <?php echo esc_html( $name ); ?> Look</strong> is what is on this screen - what the site shows in <?php echo $dark ? 'dark' : 'light'; ?> mode,
                with the changes you have made and not yet applied. <strong>Load</strong> puts a look here as the Current <?php echo esc_html( $name ); ?> Look - the Factory default
                too, to start over. <strong>Save</strong> keeps the Current <?php echo esc_html( $name ); ?> Look in that preset. Neither changes the site - only
                <strong>Apply <?php echo esc_html( $name ); ?> Mode</strong> below does. The <?php echo $dark ? 'Light' : 'Dark'; ?> look has its own presets: switch at the top.</p>
            <table class="widefat striped">
                <tbody>
                    <tr<?php echo $row( 'factory' ); ?>><td><strong>Factory default</strong></td><td class="ms-sl-when">The theme's own <?php echo $dark ? 'dark' : 'light'; ?> look - never changes</td>
                        <td><?php echo $load( 'factory', 'the ' . $name . ' factory default', true ); ?></td></tr>
                <?php for ( $n = 1; $n <= 3; $n++ ) : $slot = $slots[ $n ] ?? null; ?>
                    <tr<?php echo $row( $n ); ?>><td><input type="text" name="look_slot_name[<?php echo $n; ?>]" value="<?php echo esc_attr( $slot['name'] ?? '' ); ?>" placeholder="Preset <?php echo $n; ?>" aria-label="Name of preset <?php echo $n; ?>"></td>
                        <td class="ms-sl-when"><?php echo $slot ? 'Saved ' . esc_html( $date( $slot['taken'] ?? 0 ) ) : 'Empty'; ?></td>
                        <td><?php echo $load( (string) $n, $slot['name'] ?? 'preset ' . $n, (bool) $slot ) . ' ' . $save( (string) $n, $slot['name'] ?? 'preset ' . $n, (bool) $slot ); ?></td></tr>
                <?php endfor; ?>
                </tbody>
            </table>
            <p class="description">The name is kept when you Save.</p>
        </div>
    </details>
    <?php if ( $loaded ) : ?>
    <script>
    // The preview frame shows the loaded look too (wp_loaded above); after dark mode's own address change
    document.addEventListener('DOMContentLoaded', function () {
        var add = function (u) { return u + (u.indexOf('?') < 0 ? '?' : '&') + 'ms_look_load=' + <?php echo wp_json_encode( $loaded[0] . ':' . $loaded[1] ); ?>; },
            f = document.getElementById('ms-spot-iframe'), sel = document.getElementById('ms-spot-url');
        if (sel) Array.prototype.forEach.call(sel.options, function (o) { o.value = add(o.value); });
        if (f) f.src = add(f.getAttribute('src'));
    });
    </script>
    <?php endif;
} );

/** A row's name, for the notices */
function monospace_look_row_name( $mode, $key ) {
    if ( $key === 'factory' || $key === 'start' ) return 'the ' . ( $mode === 'dark' ? 'dark' : 'light' ) . ' factory default';
    return monospace_look_presets( $mode )[ (int) $key ]['name'] ?? 'preset ' . (int) $key;
}

add_action( 'admin_notices', function () {
    if ( ( $_GET['page'] ?? '' ) !== 'monospace-look' ) return;
    $mode   = monospace_look_mode();
    $loaded = monospace_look_loaded();
    $m      = sanitize_key( $_GET['look_msg'] ?? '' );
    if ( $m === 'kept' ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( 'Saved as ' . monospace_look_row_name( $mode, sanitize_key( $_GET['kept'] ?? '' ) ) . ' - the site is unchanged. Apply ' . ( $mode === 'dark' ? 'Dark' : 'Light' ) . ' Mode puts the Current Look on the site.' ) . '</p></div>';
    } elseif ( $m === 'none' ) {
        echo '<div class="notice notice-warning is-dismissible"><p>Nothing to load - that preset is empty.</p></div>';
    } elseif ( $loaded ) {
        echo '<div class="notice notice-info"><p>' . esc_html( ucfirst( monospace_look_row_name( $mode, $loaded[1] ) ) . ' is loaded as the Current ' . ( $mode === 'dark' ? 'Dark' : 'Light' ) . ' Look, on this screen and in the preview - the site is unchanged until you press Apply ' . ( $mode === 'dark' ? 'Dark' : 'Light' ) . ' Mode.' ) . '</p></div>';
    }
} );

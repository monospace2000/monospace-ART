<?php
/**
 * Design module: Rollover effect (2026-10-15)
 *
 * Feedback under the mouse, per place (2026-10-19: a mode for each):
 *   Lift       - the item grows a little and its shadow deepens as if it lifted off the page, and
 *                shrinks a touch while pressed
 *   Press only - no hover-grow; it only shrinks a touch while pressed (a click you can feel)
 *   Off
 * Places: home page link boxes, artwork images in feeds / category pages, "See also" (related posts)
 * thumbnails, the artwork image on a post page (the one that opens the lightbox; press only), and the
 * site logo + tagline (site-wide). Strength: subtle (2% / 1.5%) or medium (4% / 3%).
 * Shown in Layout Manager > Appearance & Effects through the design-module registry.
 *
 * Touchscreens (phones, tablets) can't hover: one switch, Press on tap (2026-10-19) - every place that
 * isn't Off presses while tapped. Body class press-touch; the footer script marks the tapped item
 * .is-pressed (phones apply :active late or not at all).
 *
 * Output: body classes hover-lift (the switch; carries the CSS variables), hover-medium, hover-<place>
 * for Lift, press-<place> for Press only, press-touch; the CSS is in _hover-effects.scss. It uses the CSS `scale`
 * property, not `transform`, so it combines with the scroll effects (Elastic scroll / Slide-in move the
 * same elements with inline transforms). Only on devices that can hover (computers), and never for
 * visitors who ask for reduced motion.
 *
 * Options: monospace_hover_effect (off | lift = the switch), monospace_hover_strength (subtle | medium),
 * monospace_hover_targets (places on Lift), monospace_press_targets (places on Press only),
 * monospace_press_touch ('1' = press on tap on touchscreens).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_HOVER_TARGETS = [ 'home', 'feed', 'related', 'logo' ];
const MONOSPACE_PRESS_TARGETS = [ 'home', 'feed', 'related', 'logo', 'post' ];

/** The places: label, the modes they offer */
function monospace_hover_places() {
    return [
        'home'    => [ 'Home page link boxes', [ 'lift', 'press', 'off' ] ],
        'feed'    => [ 'Artwork images in the Art Feed and on category / tag pages (list and grid view)', [ 'lift', 'press', 'off' ] ],
        'related' => [ '“See also” thumbnails under an artwork (related posts)', [ 'lift', 'press', 'off' ] ],
        'post'    => [ 'The artwork image on a post page (opens the lightbox)', [ 'press', 'off' ] ],
        'logo'    => [ 'The site logo and tagline, on every page', [ 'lift', 'press', 'off' ] ],
    ];
}

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['rollover-effect'] = [
        'title'       => 'Rollover effect',
        'description' => 'Feedback under the mouse: lift on hover and press on click, or just the press.',
        'group'       => 'motion',
        'order'       => 20,
        'summary'     => function () {
            $s = monospace_hover_settings();
            $lift = count( $s['targets'] ); $press = count( $s['press'] );
            if ( $s['effect'] === 'off' || ! ( $lift + $press ) ) return 'Off';
            return ucfirst( $s['strength'] ) . ': lift ' . $lift . ', press only ' . $press . ( $s['touch'] ? ', press on tap' : '' );
        },
        'render'      => 'monospace_render_hover_settings',
        'save'        => 'monospace_save_hover_settings',
    ];
    return $modules;
} );

/** Current settings, with defaults (on, subtle, home boxes + artworks + related posts lift) */
function monospace_hover_settings() {
    $targets = get_option( 'monospace_hover_targets', [ 'home', 'feed', 'related' ] );
    $press   = get_option( 'monospace_press_targets', [] );
    $targets = is_array( $targets ) ? array_values( array_intersect( $targets, MONOSPACE_HOVER_TARGETS ) ) : [];
    return [
        'effect'   => get_option( 'monospace_hover_effect', 'lift' ) === 'off' ? 'off' : 'lift',
        'strength' => get_option( 'monospace_hover_strength', 'subtle' ) === 'medium' ? 'medium' : 'subtle',
        'targets'  => $targets,
        'press'    => is_array( $press ) ? array_values( array_diff( array_intersect( $press, MONOSPACE_PRESS_TARGETS ), $targets ) ) : [],
        'touch'    => get_option( 'monospace_press_touch', '0' ) === '1',
    ];
}

/** A place's mode: lift | press | off */
function monospace_hover_mode( $s, $place ) {
    return in_array( $place, $s['targets'], true ) ? 'lift' : ( in_array( $place, $s['press'], true ) ? 'press' : 'off' );
}

function monospace_render_hover_settings() {
    $s     = monospace_hover_settings();
    $modes = [ 'lift' => 'Lift + press', 'press' => 'Press only', 'off' => 'Off' ]; ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Effect</th>
            <td>
                <label style="margin-right:18px"><input type="radio" name="hover_effect" value="off" <?php checked( $s['effect'], 'off' ); ?>> Off everywhere</label>
                <label><input type="radio" name="hover_effect" value="lift" <?php checked( $s['effect'], 'lift' ); ?>> On, as set per place below</label>
                <p class="description"><b>Lift</b>: grows a little with a deeper shadow under the mouse, shrinks a touch while clicked. <b>Press only</b>: no growing - it just shrinks a touch while clicked.</p>
            </td>
        </tr>
        <tr class="ms-hover-on">
            <th scope="row">Strength</th>
            <td>
                <label style="margin-right:18px"><input type="radio" name="hover_strength" value="subtle" <?php checked( $s['strength'], 'subtle' ); ?>> Subtle (grows 2%, presses 1.5%)</label>
                <label><input type="radio" name="hover_strength" value="medium" <?php checked( $s['strength'], 'medium' ); ?>> Medium (4% / 3%)</label>
            </td>
        </tr>
        <tr class="ms-hover-on">
            <th scope="row">Places</th>
            <td>
                <table class="widefat striped" style="max-width:720px">
                    <tbody>
                    <?php foreach ( monospace_hover_places() as $place => [ $label, $allowed ] ) :
                        $mode = monospace_hover_mode( $s, $place ); ?>
                        <tr>
                            <td><?php echo esc_html( $label ); ?></td>
                            <td style="white-space:nowrap">
                                <?php foreach ( $allowed as $m ) : ?>
                                    <label style="margin-right:12px"><input type="radio" name="hover_place[<?php echo esc_attr( $place ); ?>]" value="<?php echo esc_attr( $m ); ?>" <?php checked( $mode, $m ); ?>> <?php echo esc_html( $modes[ $m ] ); ?></label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description">Lift and Press only are for computers with a mouse or trackpad. Off for visitors who have asked their system for reduced motion.</p>
            </td>
        </tr>
        <tr class="ms-hover-on">
            <th scope="row">Phones &amp; tablets</th>
            <td>
                <label><input type="hidden" name="press_touch" value="0"><input type="checkbox" name="press_touch" value="1" <?php checked( $s['touch'] ); ?>> Press on tap - every place above that isn't Off shrinks a touch while it's tapped (touchscreens can't hover, so no lift)</label>
            </td>
        </tr>
    </table>
    <script>
    (function () {
        function sync() {
            var on = (document.querySelector('[name="hover_effect"]:checked') || {}).value === 'lift';
            document.querySelectorAll('.ms-hover-on').forEach(function (r) { r.style.display = on ? '' : 'none'; });
        }
        document.querySelectorAll('[name="hover_effect"]').forEach(function (i) { i.addEventListener('change', sync); });
        sync();
    })();
    </script>
    <?php
}

function monospace_save_hover_settings() {
    if ( ! isset( $_POST['hover_effect'] ) ) return;
    update_option( 'monospace_hover_effect', $_POST['hover_effect'] === 'off' ? 'off' : 'lift' );
    update_option( 'monospace_hover_strength', ( $_POST['hover_strength'] ?? '' ) === 'medium' ? 'medium' : 'subtle' );
    $lift = $press = [];
    foreach ( monospace_hover_places() as $place => [ , $allowed ] ) {
        $mode = (string) ( $_POST['hover_place'][ $place ] ?? 'off' );
        if ( ! in_array( $mode, $allowed, true ) ) continue;
        if ( $mode === 'lift' ) $lift[] = $place;
        if ( $mode === 'press' ) $press[] = $place;
    }
    update_option( 'monospace_hover_targets', $lift );
    update_option( 'monospace_press_targets', $press );
    update_option( 'monospace_press_touch', ( $_POST['press_touch'] ?? '0' ) === '1' ? '1' : '0' );
}

add_filter( 'body_class', function ( $classes ) {
    $s = monospace_hover_settings();
    if ( $s['effect'] !== 'lift' || ! ( $s['targets'] || $s['press'] ) ) return $classes;
    $classes[] = 'hover-lift';
    if ( $s['strength'] === 'medium' ) $classes[] = 'hover-medium';
    foreach ( $s['targets'] as $t ) $classes[] = 'hover-' . $t;
    foreach ( $s['press'] as $t ) $classes[] = 'press-' . $t;
    if ( $s['touch'] ) $classes[] = 'press-touch';
    return $classes;
} );

// Press on tap: phones apply :active late or not at all (iOS needs a touch listener, Android waits to rule
// out a scroll), so the pressed item gets the class is-pressed from touchstart until the finger lifts
// (held at least 120ms, so a quick tap still shows) - and loses it as soon as the finger moves (a scroll)
add_action( 'wp_footer', function () {
    $s = monospace_hover_settings();
    if ( $s['effect'] !== 'lift' || ! $s['touch'] ) return; ?>
    <script>
    (function () {
        var sel = 'a.ms-lightbox-link, a.ms-archive-image-link, .monospace-related-link, .container > .box.link, .site-logo a';
        var el = null, x = 0, y = 0, since = 0, timer = 0;
        function off() { clearTimeout(timer); if (el) el.classList.remove('is-pressed'); el = null; }
        document.addEventListener('touchstart', function (e) {
            off();
            var t = e.target.closest && e.target.closest(sel);
            if (!t || e.touches.length > 1) return;
            el = t; x = e.touches[0].clientX; y = e.touches[0].clientY; since = Date.now();
            el.classList.add('is-pressed');
        }, { passive: true });
        document.addEventListener('touchmove', function (e) {
            if (el && (Math.abs(e.touches[0].clientX - x) > 10 || Math.abs(e.touches[0].clientY - y) > 10)) off();
        }, { passive: true });
        document.addEventListener('touchend', function () {
            if (el) timer = setTimeout(off, Math.max(0, 120 - (Date.now() - since)));
        }, { passive: true });
        document.addEventListener('touchcancel', off, { passive: true });
    })();
    </script>
    <?php
} );

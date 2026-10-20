<?php
/**
 * Design module: Scroll effect
 *
 * A site-wide scroll effect, one of: Off / Slide-in (assets/scripts/scroll-slide.js) / Elastic scroll
 * (assets/scripts/rubber-scroll.js on GSAP ScrollSmoother, assets/vendor/gsap). Settings, their form
 * and saving, and the front-end output (body classes, scripts, settings printed in the head).
 * Shown in Layout Manager > Appearance & Effects through the design-module registry
 * (design-modules.php).
 *
 * Options: monospace_scroll_focus (on/off), monospace_scroll_effect (slide | elastic),
 * monospace_slide_* (slide-in tuning, desktop and _phone), monospace_rubber_* (elastic scroll tuning).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['scroll-effect'] = [
        'title'       => 'Scroll effect',
        'description' => 'How the page moves as it scrolls.',
        'group'       => 'motion',
        'order'       => 10,
        'summary'     => function () { return [ 'off' => 'Off', 'slide' => 'Slide-in', 'elastic' => 'Elastic scroll' ][ monospace_scroll_effect() ]; },
        'render'      => 'monospace_render_effects_settings',
        'save'        => 'monospace_save_effects_settings',
    ];
    return $modules;
} );

/** Save the form (Layout Manager > Appearance & Effects) */
function monospace_save_effects_settings() {
    $effect = in_array( $_POST['scroll_effect'] ?? '', [ 'slide', 'elastic' ], true ) ? $_POST['scroll_effect'] : 'off';
    update_option( 'monospace_scroll_focus', $effect === 'off' ? '0' : '1' );
    if ( $effect !== 'off' ) update_option( 'monospace_scroll_effect', $effect );
    update_option( 'monospace_rubber_smooth', min( 3, max( 0.2, round( floatval( $_POST['rubber_smooth'] ?? 1.2 ), 2 ) ) ) );
    update_option( 'monospace_rubber_touch', min( 3, max( 0, round( floatval( $_POST['rubber_touch'] ?? 0.8 ), 2 ) ) ) );
    update_option( 'monospace_rubber_stagger', min( 1.5, max( 0, round( floatval( $_POST['rubber_stagger'] ?? 0.35 ), 2 ) ) ) );
    update_option( 'monospace_slide_distance', min( 400, max( 0, intval( $_POST['slide_distance'] ?? 120 ) ) ) );
    update_option( 'monospace_slide_settle', min( 100, max( 5, intval( $_POST['slide_settle'] ?? 40 ) ) ) );
    update_option( 'monospace_slide_duration', min( 3, max( 0.2, round( floatval( $_POST['slide_duration'] ?? 0.9 ), 2 ) ) ) );
    update_option( 'monospace_slide_bounce', array_key_exists( $_POST['slide_bounce'] ?? '', monospace_slide_bounces() ) ? $_POST['slide_bounce'] : 'hint' );
    update_option( 'monospace_slide_distance_phone', min( 400, max( 0, intval( $_POST['slide_distance_phone'] ?? 60 ) ) ) );
    update_option( 'monospace_slide_settle_phone', min( 100, max( 5, intval( $_POST['slide_settle_phone'] ?? 30 ) ) ) );
    update_option( 'monospace_slide_bounce_phone', array_key_exists( $_POST['slide_bounce_phone'] ?? '', monospace_slide_bounces() ) ? $_POST['slide_bounce_phone'] : 'none' );
}

/**
 * The scroll effect in use: 'off', 'slide' (scroll-slide.js) or 'elastic' (rubber-scroll.js, GSAP ScrollSmoother).
 * monospace_scroll_focus is the on/off switch (kept from before), monospace_scroll_effect which one.
 */
function monospace_scroll_effect() {
    if ( get_option( 'monospace_scroll_focus', '0' ) !== '1' ) return 'off';
    return get_option( 'monospace_scroll_effect', 'slide' ) === 'elastic' ? 'elastic' : 'slide';
}

/** Elastic scroll tuning (seconds) */
function monospace_elastic_settings() {
    return [
        'smooth'  => (float) get_option( 'monospace_rubber_smooth', 1.2 ),    // page catch-up
        'touch'   => (float) get_option( 'monospace_rubber_touch', 0.8 ),     // same, touch screens
        'stagger' => (float) get_option( 'monospace_rubber_stagger', 0.35 ),  // extra lag between rows / columns
    ];
}

/** Elastic scroll runs here: the effect is on, and this isn't a cart / checkout / account page */
function monospace_rubber_scroll_active() {
    if ( is_admin() || monospace_scroll_effect() !== 'elastic' ) return false;
    if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) return false;
    return true;
}

/** Scroll slide-in tuning: option => default */
function monospace_slide_settings() {
    return [
        'distance' => (int) get_option( 'monospace_slide_distance', 120 ),
        'settle'   => (int) get_option( 'monospace_slide_settle', 40 ),
        'duration' => (float) get_option( 'monospace_slide_duration', 0.9 ),
        'bounce'   => get_option( 'monospace_slide_bounce', 'hint' ),
        // Phones (single column, up to 640px - $breakpoint-mobile)
        'phone'    => [
            'distance' => (int) get_option( 'monospace_slide_distance_phone', 60 ),
            'settle'   => (int) get_option( 'monospace_slide_settle_phone', 30 ),
            'bounce'   => get_option( 'monospace_slide_bounce_phone', 'none' ),
        ],
    ];
}

/** Bounce choices: label, overshoot of the CSS curve (home page entrance), overshoot of the scroll-driven curve */
function monospace_slide_bounces() {
    return [
        'none'   => [ 'None',   1.0,  0 ],
        'hint'   => [ 'Hint',   1.08, 0.6 ],
        'gentle' => [ 'Gentle', 1.2,  1.2 ],
        'strong' => [ 'Strong', 1.4,  1.8 ],
    ];
}

function monospace_render_effects_settings() {
    $effect  = monospace_scroll_effect();
    $slide   = monospace_slide_settings();
    $elastic = monospace_elastic_settings();
    ?>
    <table class="form-table">
        <tr>
            <th scope="row">Scroll effect</th>
            <td>
                <fieldset id="ms-scroll-effect">
                    <label><input type="radio" name="scroll_effect" value="off" <?php checked( $effect, 'off' ); ?> /> Off</label><br>
                    <label><input type="radio" name="scroll_effect" value="slide" <?php checked( $effect, 'slide' ); ?> /> <b>Slide-in</b> - post previews slide into place as they come into view, from below when scrolling down, from above when scrolling up</label><br>
                    <label><input type="radio" name="scroll_effect" value="elastic" <?php checked( $effect, 'elastic' ); ?> /> <b>Elastic scroll</b> - the page follows the scroll with a soft delay and every post row trails by a slightly different amount, so the rows stretch apart and bunch up as you scroll (GSAP ScrollSmoother, after Codrops' Elastic Grid Scroll). Not on the cart, checkout and account pages</label>
                </fieldset>
                <p class="description">Feed, category and search pages (list and grid view), the home page boxes, and any block with the CSS class <code>scroll-fade</code>. Visitors who prefer reduced motion see the page normally.</p>
            </td>
        </tr>
        <tr class="ms-effect-elastic">
            <th scope="row"><label for="rubber-smooth">Smoothness</label></th>
            <td>
                <input type="number" name="rubber_smooth" id="rubber-smooth" value="<?php echo esc_attr( $elastic['smooth'] ); ?>" min="0.2" max="3" step="0.1" class="small-text"> seconds
                <p class="description">How long the page takes to catch up with the scroll - longer = floatier. Default 1.2.</p>
            </td>
        </tr>
        <tr class="ms-effect-elastic">
            <th scope="row"><label for="rubber-touch">Smoothness on touch screens</label></th>
            <td>
                <input type="number" name="rubber_touch" id="rubber-touch" value="<?php echo esc_attr( $elastic['touch'] ); ?>" min="0" max="3" step="0.1" class="small-text"> seconds
                <p class="description">The same, for phones and tablets. Default 0.8.</p>
            </td>
        </tr>
        <tr class="ms-effect-elastic">
            <th scope="row"><label for="rubber-stagger">Stretch</label></th>
            <td>
                <input type="number" name="rubber_stagger" id="rubber-stagger" value="<?php echo esc_attr( $elastic['stagger'] ); ?>" min="0" max="1.5" step="0.05" class="small-text"> seconds
                <p class="description">How much more some rows (and outer grid columns) trail than others - more = more rubbery, rows drift further apart. 0 = the whole page moves as one. Default 0.35.</p>
            </td>
        </tr>
        <tr class="ms-effect-slide">
            <th scope="row"><label for="slide-distance">Distance</label></th>
            <td>
                <input type="number" name="slide_distance" id="slide-distance" value="<?php echo esc_attr( $slide['distance'] ); ?>" min="0" max="400" step="10" class="small-text"> px
                <p class="description">How far a post travels as it slides in. Default 120.</p>
            </td>
        </tr>
        <tr class="ms-effect-slide">
            <th scope="row"><label for="slide-settle">Settle distance</label></th>
            <td>
                <input type="number" name="slide_settle" id="slide-settle" value="<?php echo esc_attr( $slide['settle'] ); ?>" min="5" max="100" step="5" class="small-text"> % of the screen height
                <p class="description">The slide follows your scrolling: a post is in place once you've scrolled this far after it appears. Smaller = snappier, larger = more gradual. Default 40.</p>
            </td>
        </tr>
        <tr class="ms-effect-slide">
            <th scope="row"><label for="slide-duration">Page-load entrance</label></th>
            <td>
                <input type="number" name="slide_duration" id="slide-duration" value="<?php echo esc_attr( $slide['duration'] ); ?>" min="0.2" max="3" step="0.05" class="small-text"> seconds
                <p class="description">Whatever is on screen when a page opens slides up into place (there's no scrolling to follow yet) - this is how long that takes. Default 0.9.</p>
            </td>
        </tr>
        <tr class="ms-effect-slide">
            <th scope="row"><label for="slide-bounce">Bounce</label></th>
            <td>
                <select name="slide_bounce" id="slide-bounce">
                    <?php foreach ( monospace_slide_bounces() as $key => $b ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $slide['bounce'], $key ); ?>><?php echo esc_html( $b[0] ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">How far it overshoots its spot before settling. Default Hint.</p>
            </td>
        </tr>
        <tr class="ms-effect-slide">
            <th colspan="2"><h4 style="margin: 1em 0 0;">Phones <span style="font-weight: normal; color: #646970;">(screens up to 640px wide - single column)</span></h4></th>
        </tr>
        <tr class="ms-effect-slide">
            <th scope="row"><label for="slide-distance-phone">Distance</label></th>
            <td>
                <input type="number" name="slide_distance_phone" id="slide-distance-phone" value="<?php echo esc_attr( $slide['phone']['distance'] ); ?>" min="0" max="400" step="10" class="small-text"> px
                <p class="description">Default 60.</p>
            </td>
        </tr>
        <tr class="ms-effect-slide">
            <th scope="row"><label for="slide-settle-phone">Settle distance</label></th>
            <td>
                <input type="number" name="slide_settle_phone" id="slide-settle-phone" value="<?php echo esc_attr( $slide['phone']['settle'] ); ?>" min="5" max="100" step="5" class="small-text"> % of the screen height
                <p class="description">Default 30.</p>
            </td>
        </tr>
        <tr class="ms-effect-slide">
            <th scope="row"><label for="slide-bounce-phone">Bounce</label></th>
            <td>
                <select name="slide_bounce_phone" id="slide-bounce-phone">
                    <?php foreach ( monospace_slide_bounces() as $key => $b ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $slide['phone']['bounce'], $key ); ?>><?php echo esc_html( $b[0] ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Default None.</p>
            </td>
        </tr>
    </table>
    <script>
    (function () {
        function sync() {
            var v = (document.querySelector('#ms-scroll-effect input:checked') || {}).value;
            document.querySelectorAll('.ms-effect-slide').forEach(function (r) { r.style.display = v === 'slide' ? '' : 'none'; });
            document.querySelectorAll('.ms-effect-elastic').forEach(function (r) { r.style.display = v === 'elastic' ? '' : 'none'; });
        }
        document.querySelectorAll('#ms-scroll-effect input').forEach(function (i) { i.addEventListener('change', sync); });
        sync();
    })();
    </script>
    <?php
}

// Elastic scroll: GSAP (self-hosted, assets/vendor/gsap), its script and settings, and a class for
// the page wrapper (#smooth-wrapper / #smooth-content, printed by header.php / footer.php)
add_action( 'wp_enqueue_scripts', function () {
    if ( ! monospace_rubber_scroll_active() ) return;
    $uri = get_template_directory_uri() . '/assets/vendor/gsap/';
    wp_enqueue_script( 'gsap', $uri . 'gsap.min.js', [], '3.15.0', true );
    wp_enqueue_script( 'gsap-scrolltrigger', $uri . 'ScrollTrigger.min.js', [ 'gsap' ], '3.15.0', true );
    wp_enqueue_script( 'gsap-scrollsmoother', $uri . 'ScrollSmoother.min.js', [ 'gsap', 'gsap-scrolltrigger' ], '3.15.0', true );
    $file = '/assets/scripts/rubber-scroll.js';
    wp_enqueue_script( 'monospace-rubber-scroll', get_template_directory_uri() . $file, [ 'gsap-scrollsmoother' ], filemtime( get_template_directory() . $file ), true );
    $e = monospace_elastic_settings();
    wp_add_inline_script( 'monospace-rubber-scroll', sprintf( 'window.monospaceRubber={smooth:%s,touch:%s,stagger:%s};', $e['smooth'], $e['touch'], $e['stagger'] ), 'before' );
} );

// Slide-in turned on: a body class, and the script that slides items in (assets/scripts/scroll-slide.js)
add_filter( 'body_class', function ( $classes ) {
    if ( monospace_scroll_effect() === 'slide' ) {
        $classes[] = 'scroll-focus';
    }
    return $classes;
} );
add_action( 'wp_enqueue_scripts', function () {
    if ( monospace_scroll_effect() !== 'slide' ) return;
    $file = '/assets/scripts/scroll-slide.js';
    wp_enqueue_script( 'monospace-scroll-slide', get_template_directory_uri() . $file, [], filemtime( get_template_directory() . $file ), true );
} );

// The Effects tab's tuning, as CSS variables for .slide-from-below / -above (_archives.scss)
add_action( 'wp_head', function () {
    if ( monospace_scroll_effect() !== 'slide' ) return;
    $slide  = monospace_slide_settings();
    $bounces = monospace_slide_bounces();
    $bounce  = $bounces[ $slide['bounce'] ] ?? $bounces['hint'];
    $phone   = $slide['phone'];
    $bounce_phone = $bounces[ $phone['bounce'] ] ?? $bounces['none'];
    // Mark the page ready for the slide only when it will run (scroll-slide.js bails out without
    // IntersectionObserver or for reduced motion) - CSS that pre-shifts items is scoped to this
    echo "<script>if('IntersectionObserver' in window&&!(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches))document.documentElement.classList.add('slide-ready')</script>";
    // --slide-scroll-curve: the scroll-driven slide (CSS, where supported) - a cubic ease-out plus the
    // bounce, as the script's own; -rev is it mirrored, for coming from above (timeline runs backwards)
    $curves = function ( $distance, $settle, $b ) {
        $y = round( 0.33 * $b[2], 2 );
        return sprintf( '--slide-distance:%dpx;--slide-settle:%dvh;--slide-curve:cubic-bezier(0.22,%s,0.36,1);--slide-scroll-curve:cubic-bezier(0.33,%s,0.68,1);--slide-scroll-curve-rev:cubic-bezier(0.32,0,0.67,%s)',
            $distance, $settle, $b[1], 1 + $y, $y ? -$y : 0 );
    };
    printf(
        '<style id="monospace-slide-settings">:root{%s;--slide-duration:%ss}@media (max-width:640px){:root{%s}}</style>',
        $curves( $slide['distance'], $slide['settle'], $bounce ),
        rtrim( rtrim( number_format( $slide['duration'], 2, '.', '' ), '0' ), '.' ),
        $curves( $phone['distance'], $phone['settle'], $bounce_phone )
    );
    // For the scroll-driven slide (scroll-slide.js); "phone" applies up to 640px wide
    printf(
        '<script>window.monospaceSlide={distance:%d,settle:%d,overshoot:%s,phone:{distance:%d,settle:%d,overshoot:%s}}</script>',
        $slide['distance'],
        $slide['settle'],
        $bounce[2],
        $phone['distance'],
        $phone['settle'],
        $bounce_phone[2]
    );
} );

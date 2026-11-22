<?php
/**
 * Design module: Image fade-in (2026-10-30, owner: liked the fade on the custom-order product pages - "set that as a
 * site-wide option").
 *
 * Content images start clear and ease in as each one finishes loading - on every page, in the feeds and in what the
 * infinite scroll adds. Not the logo, the menu or the lightbox (outside #main-content), and not the product gallery
 * (WooCommerce fades that itself, the effect the owner liked). Off for visitors who ask for reduced motion.
 *
 * Option monospace_image_fade [ 'on' => '1' | '0' (default on), 'time' => seconds 0.2-1.2 (default 0.4) ].
 * Output (wp_head, <style id="ms-image-fade"> + a tiny script): the script adds html.ms-img-fade, then marks each
 * #main-content img .ms-img-in when its load (or error) event fires; the CSS holds an unmarked image at opacity 0 and
 * transitions it in. No script = no class = images show as they always did. An effect, so dark mode leaves it alone.
 * Only the *unmarked* images are hidden - a "show" rule would lose to the hiding rule's :not() specificity (the bug that
 * left every image invisible on the first try, 2026-10-30).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The durations to choose from, seconds */
function monospace_image_fade_times() {
    return [ '0.2', '0.3', '0.4', '0.6', '0.8', '1.2' ];
}

function monospace_image_fade() {
    $s    = (array) get_option( 'monospace_image_fade', [] );
    $time = (string) ( $s['time'] ?? '0.4' );
    return [ 'on' => ( $s['on'] ?? '1' ) !== '0', 'time' => in_array( $time, monospace_image_fade_times(), true ) ? $time : '0.4' ];
}

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['image-fade'] = [
        'title'       => 'Image fade-in',
        'description' => 'Images ease in as they finish loading, instead of appearing all at once.',
        'group'       => 'motion',
        'order'       => 30,
        'summary'     => function () {
            $s = monospace_image_fade();
            return $s['on'] ? 'Fade in, ' . $s['time'] . 's' : 'Off';
        },
        'render'      => 'monospace_render_image_fade',
        'save'        => 'monospace_save_image_fade',
    ];
    return $modules;
} );

function monospace_render_image_fade() {
    $s = monospace_image_fade(); ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Images</th>
            <td>
                <label style="margin-right:18px"><input type="radio" name="image_fade[on]" value="0" <?php checked( ! $s['on'] ); ?>> Appear as they load</label>
                <label><input type="radio" name="image_fade[on]" value="1" <?php checked( $s['on'] ); ?>> Fade in</label>
                <p class="description">Every picture on a page, in the feeds and as more load while scrolling. Not the logo, the menu, the lightbox or the product gallery (which fades by itself). Off for visitors who have asked their system for reduced motion.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="ms-image-fade-time">Speed</label></th>
            <td><select name="image_fade[time]" id="ms-image-fade-time">
                <?php foreach ( monospace_image_fade_times() as $t ) echo '<option value="' . esc_attr( $t ) . '"' . selected( $s['time'], $t, false ) . '>' . esc_html( $t ) . ' seconds' . ( $t === '0.4' ? ' (default)' : '' ) . '</option>'; ?>
                </select></td>
        </tr>
    </table>
    <?php
}

function monospace_save_image_fade() {
    if ( ! isset( $_POST['image_fade'] ) ) return;
    $in   = wp_unslash( (array) $_POST['image_fade'] );
    $time = (string) ( $in['time'] ?? '0.4' );
    update_option( 'monospace_image_fade', [
        'on'   => ( $in['on'] ?? '1' ) === '0' ? '0' : '1',
        'time' => in_array( $time, monospace_image_fade_times(), true ) ? $time : '0.4',
    ] );
}

add_action( 'wp_head', function () {
    $s = monospace_image_fade();
    if ( ! $s['on'] || is_admin() ) return; ?>
<style id="ms-image-fade">html.ms-img-fade #main-content img{transition:opacity <?php echo esc_html( $s['time'] ); ?>s ease-out}html.ms-img-fade #main-content img:not(.ms-img-in):not(.woocommerce-product-gallery img){opacity:0}</style>
<script id="ms-image-fade-js">
(function (d) {
    if (matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    d.documentElement.classList.add('ms-img-fade');
    function show(i) { i.offsetWidth; i.classList.add('ms-img-in'); } // the reflow lets a cached image still fade
    function done(e) { if (e.target.tagName === 'IMG') show(e.target); }
    d.addEventListener('load', done, true);
    d.addEventListener('error', done, true);
    function sweep() { [].forEach.call(d.querySelectorAll('#main-content img'), function (i) { if (i.complete) show(i); }); }
    d.addEventListener('DOMContentLoaded', sweep);
    addEventListener('load', function () { sweep(); setTimeout(sweep, 3000); });
})(document);
</script>
<?php }, 1 );

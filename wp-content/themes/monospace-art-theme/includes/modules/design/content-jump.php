<?php
/**
 * Design module: Open at the content (2026-10-20)
 *
 * Posts and pages open scrolled past the logo, tagline and search, with the content (.two-column-layout -
 * the post / page and the sidebar) just below the fixed menu bar - the same landing as the home page's
 * # links (_start_template.scss: menu bar + 16px; logged in, WordPress adds the admin bar's height itself).
 * The header is still there: scroll up to see it.
 *
 * Only on a fresh visit: not when the page is reloaded or reached with Back / Forward (the browser puts the
 * visitor back where they were), not when the address has a #, not once the visitor has scrolled, and never
 * on the home page. With Elastic scroll on, it goes through ScrollSmoother (rubber-scroll.js).
 *
 * Options: monospace_content_jump_posts, monospace_content_jump_pages ('1' default).
 * Shown in Layout Manager > Appearance & Effects through the design-module registry.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['content-jump'] = [
        'title'       => 'Open at the content',
        'description' => 'Posts and pages open scrolled past the logo and search, with the content just below the menu bar.',
        'group'       => 'links',
        'order'       => 25,
        'summary'     => function () {
            $s  = monospace_content_jump_settings();
            $on = array_keys( array_filter( $s ) );
            return $on ? ucfirst( implode( ' and ', $on ) ) : 'Off';
        },
        'render'      => 'monospace_render_content_jump_settings',
        'save'        => 'monospace_save_content_jump_settings',
    ];
    return $modules;
} );

/** [ 'posts' => bool, 'pages' => bool ] */
function monospace_content_jump_settings() {
    return [
        'posts' => get_option( 'monospace_content_jump_posts', '1' ) === '1',
        'pages' => get_option( 'monospace_content_jump_pages', '1' ) === '1',
    ];
}

/** Does this page open at its content? */
function monospace_content_jump_here() {
    if ( is_admin() || is_front_page() || is_home() || ! is_singular() ) return false;
    $s = monospace_content_jump_settings();
    return is_page() ? $s['pages'] : ( is_singular( 'post' ) ? $s['posts'] : false );
}

function monospace_render_content_jump_settings() {
    $s = monospace_content_jump_settings(); ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Open at the content</th>
            <td>
                <input type="hidden" name="content_jump_posts" value="0">
                <label style="margin-right:18px"><input type="checkbox" name="content_jump_posts" value="1" <?php checked( $s['posts'] ); ?>> Posts</label>
                <input type="hidden" name="content_jump_pages" value="0">
                <label><input type="checkbox" name="content_jump_pages" value="1" <?php checked( $s['pages'] ); ?>> Pages</label>
                <p class="description">Not on the home page, not after a reload or the Back button, and not when the link goes to a # on the page.</p>
            </td>
        </tr>
    </table>
    <?php
}

function monospace_save_content_jump_settings() {
    if ( ! isset( $_POST['content_jump_posts'] ) ) return;
    update_option( 'monospace_content_jump_posts', $_POST['content_jump_posts'] === '1' ? '1' : '0' );
    update_option( 'monospace_content_jump_pages', ( $_POST['content_jump_pages'] ?? '0' ) === '1' ? '1' : '0' );
}

// The landing spot: the same room above it as the home page's # links
add_action( 'wp_head', function () {
    if ( ! monospace_content_jump_here() ) return;
    echo '<style id="ms-content-jump-css">.two-column-layout{scroll-margin-top:calc(54px + 16px)}</style>' . "\n";
}, 20 );

add_action( 'wp_footer', function () {
    if ( ! monospace_content_jump_here() ) return; ?>
    <script id="ms-content-jump">
    (function () {
        // A fresh visit only: no #, not a reload / Back / Forward, not scrolled yet
        var nav = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || {};
        if (location.hash || (nav.type && nav.type !== 'navigate') || window.scrollY > 10) return;
        var el = document.querySelector('.two-column-layout') || document.getElementById('main-content');
        if (!el) return;
        if (window.monospaceSmoother) {   // Elastic scroll: through ScrollSmoother, below the fixed menu bar
            var bar = document.querySelector('.primary-menu-bar');
            window.monospaceSmoother.scrollTo(el, false, 'top ' + Math.round((bar ? bar.getBoundingClientRect().bottom : 0) + 16) + 'px');
        } else {
            el.scrollIntoView({ block: 'start' });
        }
    })();
    </script>
    <?php
}, 100 ); // after the footer scripts (rubber-scroll.js creates the smoother there)

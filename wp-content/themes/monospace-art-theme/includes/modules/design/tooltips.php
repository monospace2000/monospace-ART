<?php
/**
 * Design module: tooltips and the lightbox's alt text (2026-11-14, owner: "a global toggle to turn tooltips on or off,
 * and a separate independent toggle for alt text on open lightboxes").
 *
 * Two independent switches in Appearance > Look > Tooltips & alt text, both on by default:
 *   monospace_tooltips      '1' | '0'  - every hover hint on the site (the title attribute: images' alt-text hints,
 *                                        the home boxes' hover text, the menu bar icons, buttons ...). Off = a small script
 *                                        (printed in the head) removes every title attribute as the page loads and
 *                                        whenever a script sets one later, so it covers hints added by any module or script.
 *                                        Names stay for screen readers (aria-label / alt are untouched).
 *   monospace_lightbox_alt  '1' | '0'  - the image's full alt text printed at the bottom of an open lightbox
 *                                        (frontend/content/lightbox.php sets data-alt only when on; ms-lightbox.js prints it).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_tooltips_on() { return get_option( 'monospace_tooltips', '1' ) !== '0'; }
function monospace_lightbox_alt_on() { return get_option( 'monospace_lightbox_alt', '1' ) !== '0'; }

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['tooltips-alt'] = [
        'title'       => 'Tooltips & alt text',
        'description' => 'The hints that show when the mouse rests on something, and the alt text shown under an open picture.',
        'group'       => 'look',
        'order'       => 90,
        'summary'     => function () {
            return 'Tooltips ' . ( monospace_tooltips_on() ? 'on' : 'off' ) . ' · alt text in the lightbox ' . ( monospace_lightbox_alt_on() ? 'on' : 'off' );
        },
        'render'      => 'monospace_render_tooltips_settings',
        'save'        => 'monospace_save_tooltips_settings',
    ];
    return $modules;
} );

function monospace_render_tooltips_settings() { ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Tooltips</th>
            <td>
                <input type="hidden" name="tooltips_on" value="0">
                <label><input type="checkbox" name="tooltips_on" value="1" <?php checked( monospace_tooltips_on() ); ?>> Show hover hints</label>
                <p class="description">Everywhere on the site: pictures (their alt text), the home page boxes, the menu bar icons and buttons. Off removes all of them.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Alt text in the lightbox</th>
            <td>
                <input type="hidden" name="lightbox_alt_on" value="0">
                <label><input type="checkbox" name="lightbox_alt_on" value="1" <?php checked( monospace_lightbox_alt_on() ); ?>> Show the picture’s alt text at the bottom of an open lightbox</label>
                <p class="description">Independent of the tooltips above.</p>
            </td>
        </tr>
    </table>
    <?php
}

function monospace_save_tooltips_settings() {
    if ( ! isset( $_POST['tooltips_on'] ) ) return;
    update_option( 'monospace_tooltips', ( $_POST['tooltips_on'] ?? '1' ) === '0' ? '0' : '1' );
    update_option( 'monospace_lightbox_alt', ( $_POST['lightbox_alt_on'] ?? '1' ) === '0' ? '0' : '1' );
}

// Off: no title attributes on the site. Printed first in the head so it sees the whole page; the observer also catches
// hints that scripts set later (header toggle, elastic scroll button ...). Frames keep their title (accessibility).
add_action( 'wp_head', function () {
    if ( monospace_tooltips_on() ) return;
    echo '<script data-no-optimize="1">(function(){var S="[title]:not(iframe):not(svg):not(#wpadminbar *)";function s(r){if(r.matches&&r.matches(S))r.removeAttribute("title");if(r.querySelectorAll)r.querySelectorAll(S).forEach(function(e){e.removeAttribute("title")})}'
       . 'new MutationObserver(function(m){m.forEach(function(x){if(x.type==="attributes")s(x.target);else x.addedNodes.forEach(function(n){if(n.nodeType===1)s(n)})})}).observe(document.documentElement,{subtree:true,childList:true,attributes:true,attributeFilter:["title"]});s(document)})()</script>' . "\n";
}, 1 );

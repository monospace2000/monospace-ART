<?php
/**
 * Real Media Library: the media dialog's "Menu" pulldown on phones and tablets (2026-10-27, owner's bug report).
 *
 * Up to 900px WordPress hides the dialog's side menu behind a "Menu" button (`.media-menu` is display:none and
 * the button adds `.visible`). Real Media Library sets `.media-frame.rml-media-modal .media-menu { display:flex }`
 * outside any media query, which beats that rule, so the menu (with the folder tree) stays open over the media
 * and the Menu button does nothing. This puts WordPress's rule back for small screens - closed until the button
 * opens it - and keeps the plugin's own flex layout for the open menu. Remove it when the plugin fixes this.
 *
 * @package monospace-admin-tools
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'admin_head', function () {
    ?>
    <style id="ms-rml-media-menu-fix">
        @media only screen and (max-width: 900px) {
            .media-frame.rml-media-modal:not(.hide-menu) .media-menu:not(.visible) { display: none; }
            .media-frame.rml-media-modal:not(.hide-menu) .media-menu.visible { display: flex; }
        }
    </style>
    <?php
} );

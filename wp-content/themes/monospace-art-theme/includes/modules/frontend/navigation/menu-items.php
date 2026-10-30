<?php
/**
 * Menu headers, labels and dividers (Appearance > Menus), for the primary and footer menus.
 *
 * - Header: a Custom Link with URL "#" that has sub-items - printed as <button class="menu-header">,
 *   which opens its dropdown (hover / keyboard focus on desktop, tap on phones - menu.js). No page.
 * - Label: a Custom Link with URL "#" and no sub-items - printed as <span class="menu-label">, a small
 *   caption inside a dropdown.
 * - Divider: any item with the CSS class "menu-divider" (Screen Options > CSS Classes) - printed as an
 *   empty <li role="separator">, a thin line. Its label is never shown.
 * - Highlight: the CSS class "menu-highlight" colors an item (Places, as Custom Orders was).
 * - Footer menu: a "#" item with sub-items is a column heading (<h2 class="footer-heading">) above
 *   its links. Icons: the Icon field on each item (menu-icons.php).
 * Styles: _menu.scss (primary), _global.scss (.footer-menu).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_menu_item_is_divider( $item ) {
    return in_array( 'menu-divider', (array) $item->classes, true );
}

function monospace_menu_item_is_header( $item ) {
    return $item->type === 'custom' && in_array( trim( (string) $item->url ), [ '', '#' ], true );
}

// The item's inner markup: nothing for a divider, a button / label instead of a link to "#"
add_filter( 'walker_nav_menu_start_el', function ( $output, $item, $depth, $args ) {
    if ( monospace_menu_item_is_divider( $item ) ) return '';
    if ( ! monospace_menu_item_is_header( $item ) ) return $output;

    $title = apply_filters( 'nav_menu_item_title', apply_filters( 'the_title', $item->title, $item->ID ), $item, $args, $depth );
    if ( isset( $args->theme_location ) && $args->theme_location === 'footer' ) {
        return '<h2 class="footer-heading">' . $title . '</h2>';
    }
    if ( in_array( 'menu-item-has-children', (array) $item->classes, true ) ) {
        return '<button type="button" class="menu-header" aria-haspopup="true" aria-expanded="false">' . $title . '</button>';
    }
    return '<span class="menu-label">' . $title . '</span>';
}, 10, 4 );

// The divider's <li>
add_filter( 'nav_menu_item_attributes', function ( $atts, $item ) {
    if ( monospace_menu_item_is_divider( $item ) ) $atts['role'] = 'separator';
    return $atts;
}, 10, 2 );

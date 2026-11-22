<?php
/**
 * Appearance > Menus opens on the menus the site uses (2026-10-21, owner: "nav-menus.php does not match my actual menus").
 *
 * WordPress opens the menu edited last; for the owner that was "Pages", the menu bar's menu from before the 2026 redesign
 * (not shown anywhere since 2026-10-15). Without a ?menu= in the address, a last-edited menu that isn't in a menu location
 * gives way to the menu bar's (location primary). Picking another menu in "Select a menu to edit" works as before.
 * A note at the top says which menu is where, and which aren't used on the site.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'get_user_option_nav_menu_recently_edited', function ( $menu_id ) {
    global $pagenow;
    if ( ! is_admin() || $pagenow !== 'nav-menus.php' || isset( $_REQUEST['menu'] ) || isset( $_REQUEST['action'] ) ) return $menu_id;
    $locations = array_map( 'intval', get_nav_menu_locations() );
    if ( $menu_id && in_array( (int) $menu_id, $locations, true ) ) return $menu_id;
    return ! empty( $locations['primary'] ) ? $locations['primary'] : $menu_id;
} );

add_action( 'admin_notices', function () {
    global $pagenow;
    if ( $pagenow !== 'nav-menus.php' || ( $_GET['action'] ?? '' ) === 'locations' ) return;
    $places    = get_registered_nav_menus();
    $locations = get_nav_menu_locations();
    $in_use    = [];
    foreach ( $locations as $loc => $id ) {
        if ( $id && ( $menu = wp_get_nav_menu_object( $id ) ) ) $in_use[ $id ][] = $places[ $loc ] ?? $loc;
    }
    $parts = $unused = [];
    foreach ( wp_get_nav_menus() as $menu ) {
        $link = '<a href="' . esc_url( admin_url( 'nav-menus.php?menu=' . $menu->term_id ) ) . '">' . esc_html( $menu->name ) . '</a>';
        if ( isset( $in_use[ $menu->term_id ] ) ) $parts[] = $link . ' (' . esc_html( implode( ', ', $in_use[ $menu->term_id ] ) ) . ')';
        else $unused[] = $link;
    }
    if ( ! $parts ) return;
    echo '<div class="notice notice-info"><p><strong>On the site:</strong> ' . implode( ' · ', $parts )
       . ( $unused ? ' &nbsp;—&nbsp; <strong>not used anywhere:</strong> ' . implode( ', ', $unused ) : '' ) . '</p></div>';
} );

<?php
/**
 * View As on the Users screen: a pale red View As button on every row (not on administrators - they can not be viewed).
 * Admin UI style: a WordPress button in its own column.
 *
 * @package monospace-view-as
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'manage_users_columns', function ( $columns ) {
    if ( ms_view_as_setting( 'users_column' ) && current_user_can( ms_view_as_cap() ) ) $columns['ms_view_as'] = 'View As';
    return $columns;
} );

add_filter( 'manage_users_custom_column', function ( $out, $column, $user_id ) {
    if ( $column !== 'ms_view_as' ) return $out;
    $user = get_userdata( $user_id );
    if ( ! $user || (int) $user_id === get_current_user_id() || ! ms_view_as_can_view( $user ) ) return '';
    return '<a class="button ms-view-as-btn" href="' . esc_url( ms_view_as_start_url( $user_id ) ) . '" title="See the site as ' . esc_attr( $user->display_name ) . ' sees it (read-only)">View As</a>';
}, 10, 3 );

add_action( 'admin_head-users.php', function () {
    echo '<style>.column-ms_view_as{width:96px}.ms-view-as-btn.button{background:#fcebea;border-color:#e6b3b1;color:#a52a2a}'
       . '.ms-view-as-btn.button:hover,.ms-view-as-btn.button:focus{background:#f8d6d4;border-color:#d9918e;color:#8a1f1f}</style>';
} );

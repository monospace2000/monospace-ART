<?php
/**
 * Admin Post Navigation
 *
 * "← Newer" and "Older →" in the admin bar on a post's or product's edit screen (post.php): open the one above or
 * below it in **your All Posts / All Products list, in the order the list shows it** (owner, 2026-10-21) - with the
 * filters, search and sorting it had when you last opened it (status, category, Artworks / Other posts, sort by
 * title...), across all its pages.
 *
 * How: when the list loads (edit.php), its own database query - after WordPress, WooCommerce and the site's filters
 * have shaped it - is run once more without the page limit, and the IDs in that order are kept for you (transient
 * ms_list_{user}_{post type}, one day). On the edit screen the buttons are the IDs before and after this one.
 * A post that isn't in that list (opened from elsewhere, or filtered out) - or no list seen yet - uses the list's
 * default: every status but trash, newest first.
 * Labels: the list sorted by date, newest first (its default) - "← Newer" / "Older →"; oldest first - "← Older" /
 * "Newer →"; sorted any other way - "← Previous" / "Next →". The tooltip names the post and its place in the list.
 *
 * Earlier the same day: by date instead of ID (backdated posts went to the wrong neighbor), and the order on the
 * bar fixed. Node ids stay prev_post (the one above) / next_post (the one below) - Settings > Admin toolbar has them
 * saved by id.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The post types with the buttons */
function monospace_admin_nav_types() {
    return [ 'post', 'product' ];
}

// The list: remember its order (all its pages) as it loads
add_filter( 'posts_request', function ( $sql, $query ) {
    global $pagenow, $wpdb;
    if ( ! is_admin() || $pagenow !== 'edit.php' || ! $query->is_main_query() ) return $sql;
    $type = (string) ( $query->get( 'post_type' ) ?: 'post' );
    if ( ! in_array( $type, monospace_admin_nav_types(), true ) ) return $sql;
    $all = preg_replace( '/\s+LIMIT\s+\d+(\s*,\s*\d+)?\s*$/i', '', str_replace( 'SQL_CALC_FOUND_ROWS', '', $sql ) );
    $ids = array_map( 'intval', (array) $wpdb->get_col( $all . ' LIMIT 5000' ) ); // the first column is the ID
    set_transient( 'ms_list_' . get_current_user_id() . '_' . $type, [
        'ids'     => $ids,
        'orderby' => (string) $query->get( 'orderby' ),
        'order'   => strtoupper( (string) $query->get( 'order' ) ),
    ], DAY_IN_SECONDS );
    return $sql;
}, 999, 2 );

/** The list's default order: every status but trash, newest first */
function monospace_admin_nav_default_list( $type ) {
    return [
        'ids'     => get_posts( [ 'post_type' => $type, 'post_status' => 'any', 'orderby' => 'date', 'order' => 'DESC',
                                  'fields' => 'ids', 'posts_per_page' => 5000, 'no_found_rows' => true, 'suppress_filters' => true ] ),
        'orderby' => 'date',
        'order'   => 'DESC',
    ];
}

add_action( 'admin_bar_menu', function ( $admin_bar ) {
    global $post, $pagenow;
    if ( $pagenow !== 'post.php' || empty( $post ) || ! in_array( $post->post_type, monospace_admin_nav_types(), true ) ) return;

    $list = get_transient( 'ms_list_' . get_current_user_id() . '_' . $post->post_type );
    $from = 'your list';
    if ( ! is_array( $list ) || ! in_array( $post->ID, (array) ( $list['ids'] ?? [] ), true ) ) {
        $list = monospace_admin_nav_default_list( $post->post_type );
        $from = 'the list (all, newest first)';
    }
    $ids = array_values( array_map( 'intval', $list['ids'] ) );
    $at  = array_search( (int) $post->ID, $ids, true );
    if ( $at === false ) return;
    $above = $ids[ $at - 1 ] ?? 0;
    $below = $ids[ $at + 1 ] ?? 0;

    // The words: by date (the default sort, or orderby=date) - Newer / Older; otherwise Previous / Next
    $by_date = in_array( $list['orderby'], [ '', 'date', 'post_date' ], true );
    $asc     = $list['order'] === 'ASC';
    [ $up, $down ] = ! $by_date ? [ 'Previous', 'Next' ] : ( $asc ? [ 'Older', 'Newer' ] : [ 'Newer', 'Older' ] );
    $total = count( $ids );
    $tip   = fn( $id, $what, $n ) => $what . ' in ' . $from . ': ' . wp_strip_all_tags( get_the_title( $id ) )
                                     . ' (' . get_the_date( 'M j, Y', $id ) . ') - ' . $n . ' of ' . $total;

    // The right end of the bar is laid out from the right: the first one added sits rightmost - so the one below
    // first, and the bar reads "← Newer  Older →" (when they stay on the right - Settings > Admin toolbar)
    if ( $below ) {
        $admin_bar->add_node( [
            'id'     => 'next_post',
            'title'  => esc_html( $down ) . ' →',
            'href'   => get_edit_post_link( $below ),
            'parent' => 'top-secondary',
            'meta'   => [ 'class' => 'next-post-link', 'title' => $tip( $below, $down, $at + 2 ) ],
        ] );
    }
    if ( $above ) {
        $admin_bar->add_node( [
            'id'     => 'prev_post',
            'title'  => '← ' . esc_html( $up ),
            'href'   => get_edit_post_link( $above ),
            'parent' => 'top-secondary',
            'meta'   => [ 'class' => 'prev-post-link', 'title' => $tip( $above, $up, $at ) ],
        ] );
    }
}, 200 ); // late: after the other items on the right

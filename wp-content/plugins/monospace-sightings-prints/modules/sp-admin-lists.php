<?php
/**
 * Sightings prints: kept out of the admin's Posts, Products and Media lists (owner, 2026-11-24). Display only - nothing is changed.
 * A "Sightings prints" link above each list (Media: list view) shows just these; a media folder, the category filter and the Trash show them too.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** [ id => [ 'status', 'author' ] ] of the stand-in posts, their products or their pictures */
function monospace_sp_admin_items( $type ) {
    static $cache = [];
    if ( isset( $cache[ $type ] ) ) return $cache[ $type ];
    global $wpdb;
    if ( $type === 'post' ) {
        $join = "JOIN $wpdb->postmeta m ON m.post_id = p.ID AND m.meta_key = '_ms_sp_key'";
    } elseif ( $type === 'product' ) {
        $join = "JOIN $wpdb->postmeta m ON m.post_id = p.ID AND m.meta_key = '_related_post_id'
                 JOIN $wpdb->postmeta k ON k.post_id = m.meta_value AND k.meta_key = '_ms_sp_key'";
    } else {
        $join = "JOIN $wpdb->postmeta m ON m.post_id = p.ID AND m.meta_key = '_ms_sp_composite'";
    }
    $cache[ $type ] = [];
    $rows = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT p.ID, p.post_status, p.post_author FROM $wpdb->posts p $join WHERE p.post_type = %s", $type ) );
    foreach ( $rows as $r ) $cache[ $type ][ (int) $r->ID ] = [ 'status' => $r->post_status, 'author' => (int) $r->post_author ];
    return $cache[ $type ];
}

/** Is this list asking for the Sightings prints themselves? */
function monospace_sp_admin_showing() {
    return ! empty( $_GET['ms_sp'] );
}

/** Is a media folder (Real Media Library) open? A folder always shows what is in it */
function monospace_sp_admin_in_folder() {
    return (int) ( $_REQUEST['rml_folder'] ?? $_REQUEST['query']['rml_folder'] ?? 0 ) > 0;
}

/** The Posts, Products and Media (list view) screens */
add_action( 'pre_get_posts', function ( $q ) {
    global $pagenow;
    if ( ! is_admin() || ! $q->is_main_query() || ! in_array( $pagenow, [ 'edit.php', 'upload.php' ], true ) ) return;
    $type = $pagenow === 'upload.php' ? 'attachment' : ( $q->get( 'post_type' ) ?: 'post' );
    if ( ! in_array( $type, [ 'post', 'product', 'attachment' ], true ) ) return;
    $ids = array_keys( monospace_sp_admin_items( $type ) );
    if ( ! $ids ) return;
    if ( monospace_sp_admin_showing() ) { $q->set( 'post__in', $ids ); return; }
    // asked for by name: a media folder, the category filter, the Trash
    if ( $type === 'attachment' && monospace_sp_admin_in_folder() ) return;
    if ( $q->get( 'post_status' ) === 'trash' || $q->get( 'category_name' ) === 'sightings-prints' ) return;
    $cat = get_term_by( 'slug', 'sightings-prints', 'category' );
    if ( $cat && (int) $q->get( 'cat' ) === (int) $cat->term_id ) return;
    $q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), $ids ) );
} );

/** The media grid and the "choose a picture" window */
add_filter( 'ajax_query_attachments_args', function ( $args ) {
    if ( monospace_sp_admin_in_folder() ) return $args;
    $ids = array_keys( monospace_sp_admin_items( 'attachment' ) );
    if ( $ids ) $args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? [] ), $ids );
    return $args;
} );

/** "All (n)", "Private (n)", "Published (n)" without them */
add_filter( 'wp_count_posts', function ( $counts, $type ) {
    global $pagenow;
    if ( ! is_admin() || $pagenow !== 'edit.php' || ! in_array( $type, [ 'post', 'product' ], true ) ) return $counts;
    $counts = clone $counts;
    foreach ( monospace_sp_admin_items( $type ) as $item ) {
        $s = $item['status'];
        if ( $s !== 'trash' && ! empty( $counts->$s ) ) $counts->$s--;
    }
    return $counts;
}, 20, 2 );

add_filter( 'wp_count_attachments', function ( $counts ) {
    global $pagenow;
    if ( ! is_admin() || $pagenow !== 'upload.php' ) return $counts;
    $hidden = count( array_filter( monospace_sp_admin_items( 'attachment' ), function ( $i ) { return $i['status'] !== 'trash'; } ) );
    $counts = clone $counts;
    if ( ! empty( $counts->{'image/jpeg'} ) ) $counts->{'image/jpeg'} = max( 0, $counts->{'image/jpeg'} - $hidden );
    return $counts;
} );

/** The "Sightings prints (n)" link; also corrects "Mine", which WordPress counts on its own */
function monospace_sp_admin_views( $views, $type, $url ) {
    $items = array_filter( monospace_sp_admin_items( $type ), function ( $i ) { return $i['status'] !== 'trash'; } );
    if ( ! $items ) return $views;
    if ( isset( $views['mine'] ) ) {
        $me = get_current_user_id();
        $mine = count( array_filter( $items, function ( $i ) use ( $me ) { return $i['author'] === $me; } ) );
        $views['mine'] = preg_replace_callback( '#(<span class="count">\()([\d,.]+)(\)</span>)#', function ( $x ) use ( $mine ) {
            return $x[1] . number_format_i18n( max( 0, (int) preg_replace( '/\D/', '', $x[2] ) - $mine ) ) . $x[3];
        }, $views['mine'] );
    }
    $current = monospace_sp_admin_showing();
    if ( $current ) $views = array_map( function ( $v ) { return str_replace( [ ' class="current"', ' aria-current="page"' ], '', $v ); }, $views );
    $views['ms_sp'] = sprintf( '<a href="%s"%s>Sightings prints <span class="count">(%s)</span></a>',
        esc_url( admin_url( $url ) ), $current ? ' class="current" aria-current="page"' : '', number_format_i18n( count( $items ) ) );
    return $views;
}
add_filter( 'views_edit-post', function ( $v ) { return monospace_sp_admin_views( $v, 'post', 'edit.php?ms_sp=1' ); }, 50 );
add_filter( 'views_edit-product', function ( $v ) { return monospace_sp_admin_views( $v, 'product', 'edit.php?post_type=product&ms_sp=1' ); }, 50 );
add_filter( 'views_upload', function ( $v ) { return monospace_sp_admin_views( $v, 'attachment', 'upload.php?mode=list&ms_sp=1' ); }, 50 );

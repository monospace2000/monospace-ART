<?php
/**
 * Pages in the Post Feed
 *
 * A "Show in feed" box on the page editor puts a WordPress page into the main post feed
 * (the Art Feed - is_home()), never on category, tag or search archives:
 *
 *   - By date: the page joins the feed's query and appears wherever its publish date falls,
 *     on whichever feed page that is (change its date to move it).
 *   - Pinned:  the page is inserted on the first feed page at a fixed position - at the top,
 *     or after N posts - regardless of dates. Not repeated on later (infinite scroll) pages.
 *
 * Either way it renders like any feed item (index.php loop): first image, caption, text up to
 * the "more" break. Settings: post meta _ms_feed_mode ('date' | 'pinned') and
 * _ms_feed_position (posts before it; 0 = top).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The main feed's own query, on the front end */
function monospace_is_main_feed( $query ) {
    return ! is_admin() && $query->is_main_query() && $query->is_home();
}

/** IDs of published pages with the given feed mode */
function monospace_feed_page_ids( $mode ) {
    return get_posts( [
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'meta_key'       => '_ms_feed_mode',
        'meta_value'     => $mode,
        'orderby'        => 'menu_order date',
    ] );
}

/* ------------------------------------------------------------------ by date */

add_action( 'pre_get_posts', function ( $query ) {
    if ( ! monospace_is_main_feed( $query ) ) return;
    $ids = monospace_feed_page_ids( 'date' );
    if ( ! $ids ) return;

    $query->set( 'post_type', [ 'post', 'page' ] );
    $query->set( 'ms_feed_page_ids', $ids );
} );

// Of all pages, only the "by date" ones
add_filter( 'posts_where', function ( $where, $query ) {
    $ids = $query->get( 'ms_feed_page_ids' );
    if ( ! $ids ) return $where;

    global $wpdb;
    $in = implode( ',', array_map( 'intval', $ids ) );
    return $where . " AND ( {$wpdb->posts}.post_type <> 'page' OR {$wpdb->posts}.ID IN ($in) )";
}, 10, 2 );

/* ------------------------------------------------------------------ pinned */

add_filter( 'the_posts', function ( $posts, $query ) {
    if ( ! monospace_is_main_feed( $query ) || $query->is_paged() ) return $posts;
    // A filter / sort from the viewing toolbar: a fixed position means nothing there
    if ( function_exists( 'monospace_archive_tools_active' ) && monospace_archive_tools_active() ) return $posts;

    $pinned = monospace_feed_page_ids( 'pinned' );
    if ( ! $pinned ) return $posts;

    // Lowest position first, so later inserts don't shift earlier ones out of place
    $pages = array_map( 'get_post', $pinned );
    usort( $pages, function ( $a, $b ) {
        return intval( get_post_meta( $a->ID, '_ms_feed_position', true ) ) <=> intval( get_post_meta( $b->ID, '_ms_feed_position', true ) );
    } );

    $offset = 0;
    foreach ( $pages as $page ) {
        $position = min( max( 0, intval( get_post_meta( $page->ID, '_ms_feed_position', true ) ) ), count( $posts ) - $offset );
        array_splice( $posts, $position + $offset, 0, [ $page ] );
        $offset++;
    }
    $query->post_count = count( $posts );
    return $posts;
}, 10, 2 );

/* ------------------------------------------------------------------ page editor box */

add_action( 'add_meta_boxes_page', function () {
    add_meta_box( 'ms-feed-page', __( 'Show in feed', 'monospace' ), 'monospace_feed_page_box', 'page', 'side' );
} );

function monospace_feed_page_box( $post ) {
    $mode     = get_post_meta( $post->ID, '_ms_feed_mode', true );
    $position = intval( get_post_meta( $post->ID, '_ms_feed_position', true ) );
    wp_nonce_field( 'ms_feed_page', 'ms_feed_page_nonce' );
    ?>
    <p style="margin-top:0">
        <label><input type="radio" name="ms_feed_mode" value="" <?php checked( $mode, '' ); ?>> Not in the feed</label><br>
        <label><input type="radio" name="ms_feed_mode" value="date" <?php checked( $mode, 'date' ); ?>> By date</label><br>
        <label><input type="radio" name="ms_feed_mode" value="pinned" <?php checked( $mode, 'pinned' ); ?>> Pinned</label>
    </p>
    <p class="ms-feed-position" style="<?php echo $mode === 'pinned' ? '' : 'display:none'; ?>">
        <label for="ms-feed-position">Position</label><br>
        <select name="ms_feed_position" id="ms-feed-position">
            <option value="0" <?php selected( $position, 0 ); ?>>At the top</option>
            <?php for ( $n = 1; $n <= 19; $n++ ) : ?>
                <option value="<?php echo $n; ?>" <?php selected( $position, $n ); ?>>After post <?php echo $n; ?></option>
            <?php endfor; ?>
        </select>
    </p>
    <p class="description">Main feed only. <em>By date</em> follows this page's publish date; <em>Pinned</em> stays at a fixed spot on the first feed page.</p>
    <script>
        document.querySelectorAll('[name=ms_feed_mode]').forEach(function (r) {
            r.addEventListener('change', function () {
                document.querySelector('.ms-feed-position').style.display = document.querySelector('[name=ms_feed_mode]:checked').value === 'pinned' ? '' : 'none';
            });
        });
    </script>
    <?php
}

add_action( 'save_post_page', function ( $post_id ) {
    if ( ! isset( $_POST['ms_feed_page_nonce'] ) || ! wp_verify_nonce( $_POST['ms_feed_page_nonce'], 'ms_feed_page' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_page', $post_id ) ) return;

    $mode = in_array( $_POST['ms_feed_mode'] ?? '', [ 'date', 'pinned' ], true ) ? $_POST['ms_feed_mode'] : '';
    if ( $mode ) {
        update_post_meta( $post_id, '_ms_feed_mode', $mode );
        update_post_meta( $post_id, '_ms_feed_position', max( 0, intval( $_POST['ms_feed_position'] ?? 0 ) ) );
    } else {
        delete_post_meta( $post_id, '_ms_feed_mode' );
        delete_post_meta( $post_id, '_ms_feed_position' );
    }
} );

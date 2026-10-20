<?php
/**
 * Archive View Toggle (List / Grid)
 *
 * Per-term option, set on the term edit screen under "Archive View":
 *   ''     - List only, no toggle (default)
 *   'list' - Toggle shown, starts in list view
 *   'grid' - Toggle shown, starts in grid view
 *
 * A visitor's choice is remembered in localStorage and applies to every archive
 * that has the toggle. The view is a class on <html> (archive-view-grid), set by
 * a small script in <head> so the page never flashes the wrong layout.
 *
 * The toggle renders only on page 1 — infinite scroll appends page 2+ markup, and
 * those posts inherit the view from the <html> class.
 *
 * The same toolbar has "Original available" (?available=1), "Prints available" and a sort menu (2026-10-16). Unlike the
 * view, these are URL parameters - ?available=1, ?prints=1, ?sort=oldest|price-asc|price-desc|size-asc|size-desc - applied
 * to the archive query, so infinite scroll's next-page links (get_pagenum_link keeps the query
 * string) carry them, and a filtered view can be shared. Canonical stays the bare term link.
 * Available = what the price card sells (monospace_for_sale_post_ids(), available-originals.php);
 * prints available = a linked print product on sale (monospace_prints_for_sale_post_ids(), same file).
 * Price = the original's product price; size = the area of its pa_size (a set "2 × 4" × 4"" = 32).
 * Works without a price / size go last.
 *
 * Related Files:
 * - feed-headers.php - Other per-term archive fields; defines MONOSPACE_ARCHIVE_TAXONOMIES
 * - available-originals.php - The for-sale list
 * - _archives.scss - Toggle and grid styles
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * TERM META FIELD
 * ============================================================ */

add_action( 'admin_init', function () {
    foreach ( MONOSPACE_ARCHIVE_TAXONOMIES as $taxonomy ) {
        // The field is drawn by editor/term-areas.php (2026-10-20, Layout Manager style); saved here
        add_action( "edited_{$taxonomy}", 'monospace_save_archive_view_field' );
    }
});

function monospace_render_archive_view_field( $term ) {
    $view = get_term_meta( $term->term_id, '_monospace_archive_view', true );
    ?>
    <tr class="form-field">
        <th colspan="2"><h3 style="margin: 1.5em 0 0.5em;">Archive View</h3></th>
    </tr>

    <tr class="form-field">
        <th scope="row"><label for="monospace-archive-view">List / Grid Toggle</label></th>
        <td>
            <select name="monospace_archive_view" id="monospace-archive-view">
                <option value="" <?php selected( $view, '' ); ?>>Off (list only)</option>
                <option value="list" <?php selected( $view, 'list' ); ?>>On, start in list view</option>
                <option value="grid" <?php selected( $view, 'grid' ); ?>>On, start in grid view</option>
            </select>
            <p class="description">Shows list/grid icons at the top of this archive. A visitor's own choice overrides the starting view.</p>
        </td>
    </tr>
    <?php
}

function monospace_save_archive_view_field( $term_id ) {
    if ( ! isset( $_POST['monospace_archive_view'] ) ) return;

    $view = sanitize_key( $_POST['monospace_archive_view'] );
    update_term_meta( $term_id, '_monospace_archive_view', in_array( $view, [ 'list', 'grid' ], true ) ? $view : '' );
}

/* ============================================================
 * HELPERS
 * ============================================================ */

/**
 * The starting view for the current archive ('list' or 'grid'), or '' when the toolbar isn't shown
 * here: Layout Manager > Appearance & Effects > Viewing toolbar (design/archive-toolbar.php) switches
 * it for the Art Feed and, as a whole, for term archives; each term then has its own setting.
 */
function monospace_get_archive_view_default() {
    $settings = function_exists( 'monospace_archive_toolbar_settings' ) ? monospace_archive_toolbar_settings() : [ 'terms' => true, 'feed' => false, 'feed_view' => 'list' ];
    if ( is_home() ) return $settings['feed'] ? $settings['feed_view'] : '';
    if ( ! $settings['terms'] || ! ( is_category() || is_tag() || is_tax() ) ) return '';

    $term = get_queried_object();
    if ( ! $term || ! isset( $term->taxonomy ) || ! in_array( $term->taxonomy, MONOSPACE_ARCHIVE_TAXONOMIES, true ) ) return '';

    $view = get_term_meta( $term->term_id, '_monospace_archive_view', true );
    return in_array( $view, [ 'list', 'grid' ], true ) ? $view : '';
}

const MONOSPACE_ARCHIVE_SORTS = [
    'newest'     => 'Newest first',
    'oldest'     => 'Oldest first',
    'price-asc'  => 'Price: low to high',
    'price-desc' => 'Price: high to low',
    'size-asc'   => 'Size: small to large',
    'size-desc'  => 'Size: large to small',
];

/** Is the Prints plugin on? (its "Prints available" filter is left out while it's off) */
function monospace_archive_prints_on() {
    return function_exists( 'monospace_feature' ) && monospace_feature( 'prints' );
}

/** The toolbar's filters and sort from the URL: [ 'available' => bool, 'prints' => bool, 'sort' => key of MONOSPACE_ARCHIVE_SORTS ] */
function monospace_archive_tools_state() {
    $sort = sanitize_key( $_GET['sort'] ?? '' );
    return [
        // Each filter is left out where the page already is nothing else (Available Originals / Prints)
        'available' => ! empty( $_GET['available'] ) && ! is_category( MONOSPACE_AVAILABLE_SLUG ),
        'prints'    => ! empty( $_GET['prints'] ) && ! is_category( 'print' ) && monospace_archive_prints_on(),
        'sort'      => isset( MONOSPACE_ARCHIVE_SORTS[ $sort ] ) ? $sort : 'newest',
    ];
}

/** A filter or a sort other than newest first is in use (feed-pages.php then leaves out pinned pages) */
function monospace_archive_tools_active() {
    if ( ! monospace_get_archive_view_default() ) return false;
    $tools = monospace_archive_tools_state();
    return $tools['available'] || $tools['prints'] || $tools['sort'] !== 'newest';
}

/** Page 1 of this archive with the current state, changed by $change (defaults left out of the URL) */
function monospace_archive_tools_url( $change = [] ) {
    $url = is_home()
        ? ( get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' ) ) // the Art Feed
        : get_term_link( get_queried_object() );
    if ( is_wp_error( $url ) || ! $url ) return '';
    $state = array_merge( monospace_archive_tools_state(), $change );
    return add_query_arg( array_filter( [
        'available' => $state['available'] ? 1 : null,
        'prints'    => $state['prints'] ? 1 : null,
        'sort'      => $state['sort'] !== 'newest' ? $state['sort'] : null,
    ] ), $url );
}

/**
 * Sort post IDs (given newest first) by their original's price or size, ascending or descending.
 * Posts without a value keep their order at the end.
 */
function monospace_archive_sort_ids( $ids, $sort ) {
    list( $by, $dir ) = explode( '-', $sort );
    update_meta_cache( 'post', $ids );
    $products = [];
    foreach ( $ids as $id ) $products[ $id ] = (int) get_post_meta( $id, '_qp_product_id', true );
    $product_ids = array_values( array_filter( $products ) );
    update_meta_cache( 'post', $product_ids );
    if ( $by === 'size' ) update_object_term_cache( $product_ids, 'product' );

    $known = $unknown = [];
    foreach ( $ids as $i => $id ) {
        $pid = $products[ $id ];
        $value = null;
        if ( $pid && $by === 'price' ) {
            $price = get_post_meta( $pid, '_price', true );
            if ( is_numeric( $price ) && $price > 0 ) $value = (float) $price;
        } elseif ( $pid ) {
            $terms = get_the_terms( $pid, 'pa_size' );
            // "10" × 8"" = 80, "2 × 4" × 4"" = 32; "Other" / "Sketchbook page" have no numbers
            if ( $terms && ! is_wp_error( $terms ) && preg_match_all( '/\d+(?:\.\d+)?/', $terms[0]->name, $m ) && count( $m[0] ) >= 2 ) {
                $value = array_product( array_map( 'floatval', $m[0] ) );
            }
        }
        if ( $value === null ) $unknown[] = $id;
        else $known[] = [ $value, $i, $id ];
    }
    usort( $known, function ( $a, $b ) use ( $dir ) {
        $c = $dir === 'desc' ? $b[0] <=> $a[0] : $a[0] <=> $b[0];
        return $c ?: $a[1] <=> $b[1]; // ties: newest first
    } );
    return array_merge( array_column( $known, 2 ), $unknown );
}

/* ============================================================
 * QUERY - filter and sort (after available-originals.php's own query at priority 10)
 * ============================================================ */

add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() || ! $query->is_main_query() || ! monospace_get_archive_view_default() ) return;
    $tools = monospace_archive_tools_state();

    // Filters narrow the archive's own posts (both ticked = both must hold)
    foreach ( [ 'available' => 'monospace_for_sale_post_ids', 'prints' => 'monospace_prints_for_sale_post_ids' ] as $filter => $list ) {
        if ( ! $tools[ $filter ] ) continue;
        $ids = $list();
        if ( $in = $query->get( 'post__in' ) ) $ids = array_intersect( $in, $ids );
        $query->set( 'post__in', array_values( $ids ) ?: [ 0 ] );
    }

    if ( $tools['sort'] === 'oldest' ) {
        $query->set( 'orderby', 'date' );
        $query->set( 'order', 'ASC' );
    } elseif ( $tools['sort'] !== 'newest' ) {
        // Price / size aren't post fields: list the whole archive, sort it here, page through that order
        $all = get_posts( array_merge( $query->query_vars, [
            'fields' => 'ids', 'posts_per_page' => -1, 'nopaging' => true, 'paged' => 0,
            'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true,
            'suppress_filters' => false, // the Art Feed's posts_where (feed-pages.php: only "by date" pages)
        ] ) );
        $query->set( 'post__in', $all ? monospace_archive_sort_ids( $all, $tools['sort'] ) : [ 0 ] );
        $query->set( 'orderby', 'post__in' );
    }
}, 20 );

/**
 * No posts left after a filter: the loop never starts, so print the header and toolbar (both on
 * loop_start) here, with a way back. Called by index.php; true when it printed.
 */
function monospace_archive_tools_empty() {
    $tools = monospace_archive_tools_state();
    if ( ! monospace_get_archive_view_default() || ! ( $tools['available'] || $tools['prints'] ) ) return false;
    do_action( 'loop_start', $GLOBALS['wp_query'] );
    printf( '<p class="archive-tools-empty">Nothing here matches right now. <a href="%s">Show all work</a></p>',
        esc_url( monospace_archive_tools_url( [ 'available' => false, 'prints' => false ] ) ) );
    return true;
}

/* ============================================================
 * FRONTEND OUTPUT
 * ============================================================ */

// Set the view class on <html> before the body renders, and handle toggle clicks
add_action( 'wp_head', function () {
    $default = monospace_get_archive_view_default();
    if ( ! $default ) return;
    ?>
    <script>
    (function () {
        var KEY = 'monospace_archive_view';
        var root = document.documentElement;
        var view = '<?php echo esc_js( $default ); ?>';
        try { view = localStorage.getItem(KEY) || view; } catch (e) {}

        function apply(v) {
            root.classList.toggle('archive-view-grid', v === 'grid');
            document.querySelectorAll('.archive-view-toggle button').forEach(function (btn) {
                btn.setAttribute('aria-pressed', btn.dataset.view === v ? 'true' : 'false');
            });
        }

        apply(view);
        document.addEventListener('DOMContentLoaded', function () { apply(view); });

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.archive-view-toggle button');
            if (!btn) return;
            view = btn.dataset.view;
            try { localStorage.setItem(KEY, view); } catch (e) {}
            apply(view);
        });

        // Filters / sort: each choice carries its page URL
        document.addEventListener('change', function (e) {
            var el = e.target.closest('.archive-tools [data-href], .archive-tools select');
            if (!el) return;
            location.href = el.tagName === 'SELECT' ? el.value : el.dataset.href;
        });
    })();
    </script>
    <?php
} );

// Toggle icons at the top of the feed, after the archive header (priority 5)
add_action( 'loop_start', function ( $query ) {
    if ( ! $query->is_main_query() || is_paged() ) return;
    if ( ! monospace_get_archive_view_default() ) return;

    static $rendered = false;
    if ( $rendered ) return;
    $rendered = true;
    $tools = monospace_archive_tools_state();
    ?>
    <nav class="archive-view-toggle" aria-label="View">
        <div class="archive-tools">
            <span class="archive-tools-filters">
            <?php if ( ! is_category( MONOSPACE_AVAILABLE_SLUG ) ) : ?>
            <label class="archive-tools-available">
                <input type="checkbox" <?php checked( $tools['available'] ); ?>
                       data-href="<?php echo esc_url( monospace_archive_tools_url( [ 'available' => ! $tools['available'] ] ) ); ?>">
                Original available
            </label>
            <?php endif; ?>
            <?php if ( ! is_category( 'print' ) && monospace_archive_prints_on() ) : ?>
            <label class="archive-tools-prints">
                <input type="checkbox" <?php checked( $tools['prints'] ); ?>
                       data-href="<?php echo esc_url( monospace_archive_tools_url( [ 'prints' => ! $tools['prints'] ] ) ); ?>">
                Prints available
            </label>
            <?php endif; ?>
            </span>
            <label class="archive-tools-sort">
                <span class="screen-reader-text">Sort</span>
                <select>
                    <?php foreach ( MONOSPACE_ARCHIVE_SORTS as $key => $label ) : ?>
                    <option value="<?php echo esc_url( monospace_archive_tools_url( [ 'sort' => $key ] ) ); ?>" <?php selected( $tools['sort'], $key ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <span class="archive-view-label" aria-hidden="true">View as</span>
        <button type="button" data-view="list" aria-label="List view" title="List view">
            <svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true"><rect x="2" y="3" width="16" height="3" rx="1"/><rect x="2" y="8.5" width="16" height="3" rx="1"/><rect x="2" y="14" width="16" height="3" rx="1"/></svg>
        </button>
        <button type="button" data-view="grid" aria-label="Grid view" title="Grid view">
            <svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true"><rect x="2" y="2" width="7" height="7" rx="1"/><rect x="11" y="2" width="7" height="7" rx="1"/><rect x="2" y="11" width="7" height="7" rx="1"/><rect x="11" y="11" width="7" height="7" rx="1"/></svg>
        </button>
    </nav>
    <?php
}, 6 );

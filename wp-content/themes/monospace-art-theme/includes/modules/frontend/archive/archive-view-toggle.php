<?php
/**
 * Archive views and the viewing toolbar
 *
 * A category / tag / place page and the Art Feed each show in one VIEW - list, grid, detail list (picture on the left,
 * details on the right) or price card - and may have the viewing TOOLBAR (Original available, Prints available, sort and
 * the list / grid / detail buttons). Both are set once for the whole site (Layout Manager > Links & toolbar > Viewing
 * toolbar: toolbar on / off, default view) and may be overridden per page (2026-10-23, owner: "set toolbar on/off globally
 * and a global default; price card still a viewing option but hidden from the user, only settable by admin"):
 *   term meta _monospace_archive_view  '' (same as everywhere) | list | grid | detail | cards
 *   term meta _monospace_archive_bar   '' (same as everywhere) | show | hide
 * The Art Feed has the same two choices (options monospace_archive_feed_view / _feed_bar). Price card is never a button: a
 * page that starts in it has no toolbar - except that the Art Feed's toolbar may offer a Price card button (option
 * monospace_archive_feed_cards, 2026-10-28). monospace_archive_page_state() works out [ 'view', 'bar' ] for the page being shown.
 * Shop categories and tags (products have no row) only take part when a term chose a view.
 *
 * A visitor's choice (with the toolbar) is remembered in localStorage and applies to every page that has the toolbar. The
 * view is a class on <html> (archive-view-grid, -detail, -cards), set by a small script in <head> so the page never flashes
 * the wrong layout.
 *
 * Price card view (2026-10-21, owner): each artwork as a standalone price card (Core's [price_card_standalone]: the card with
 * its picture, the print mockup on a print tab). Each artwork then carries its card (.archive-sa-card, added to its content -
 * infinite scroll's pages too); in cards view it is all an artwork shows (_archives.scss), blog posts stay as they are.
 *
 * The toggle renders only on page 1 — infinite scroll appends page 2+ markup, and
 * those posts inherit the view from the <html> class.
 *
 * The same toolbar has "Original available" (?available=1), "Prints available" and a sort menu (2026-10-16). Unlike the
 * view, these are URL parameters - ?available=1, ?prints=1, ?sort=oldest|price-asc|price-desc|size-asc|size-desc|random - applied
 * to the archive query, so infinite scroll's next-page links (get_pagenum_link keeps the query
 * string) carry them, and a filtered view can be shared. Canonical stays the bare term link.
 * Available = what the price card sells (monospace_for_sale_post_ids(), available-originals.php);
 * prints available = a linked print product on sale (monospace_prints_for_sale_post_ids(), same file).
 * Price = the original's product price; size = the area of its pa_size (a set "2 × 4" × 4"" = 32).
 * Works without a price / size go last. Random (2026-10-21): a shuffle fixed by ?seed= (the menu adds a fresh one when
 * it's chosen; the next pages keep it, so infinite scroll neither repeats nor skips) - no seed: the day's date.
 * Starting sort (2026-10-21): a term's _monospace_archive_sort (any sort above; empty = newest first) applies while the URL
 * names none - so the URL names a sort only when it differs from the page's starting one (newest included).
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

function monospace_save_archive_view_field( $term_id ) {
    if ( ! isset( $_POST['monospace_archive_view'] ) ) return;

    $view = sanitize_key( $_POST['monospace_archive_view'] );
    update_term_meta( $term_id, '_monospace_archive_view', isset( monospace_archive_views()[ $view ] ) ? $view : '' );
    $bar = sanitize_key( $_POST['monospace_archive_bar'] ?? '' );
    update_term_meta( $term_id, '_monospace_archive_bar', in_array( $bar, [ 'show', 'hide' ], true ) ? $bar : '' );
    if ( isset( $_POST['monospace_archive_sort'] ) ) {
        $sort = sanitize_key( $_POST['monospace_archive_sort'] );
        isset( MONOSPACE_ARCHIVE_SORTS[ $sort ] ) && $sort !== 'newest' ? update_term_meta( $term_id, '_monospace_archive_sort', $sort ) : delete_term_meta( $term_id, '_monospace_archive_sort' );
    }
}

/* ============================================================
 * HELPERS
 * ============================================================ */

/** The views a page can show (key => name). Price card needs Core's standalone card; it is never a visitor's button. */
function monospace_archive_views() {
    $views = [ 'list' => 'List', 'grid' => 'Grid', 'detail' => 'Detail list' ];
    if ( function_exists( 'monospace_pc_standalone_settings' ) ) $views['cards'] = 'Price card';
    return $views;
}

/** Terms whose pages take part without choosing a view (shop categories and tags list products, which have no row) */
const MONOSPACE_ARCHIVE_DEFAULT_TAXONOMIES = [ 'category', 'post_tag' ];

/** The site-wide choices (design/archive-toolbar.php): [ 'bar' => bool, 'view' => key, 'feed_bar' => '' | show | hide, 'feed_view' => '' | key, 'detail_chars' => int ] */
function monospace_archive_global() {
    return function_exists( 'monospace_archive_toolbar_settings' ) ? monospace_archive_toolbar_settings()
        : [ 'bar' => true, 'view' => 'detail', 'feed_bar' => 'hide', 'feed_view' => '', 'detail_chars' => 200 ];
}

/**
 * What a page shows given its own choices ('' = same as everywhere): [ 'view' => key, 'bar' => bool ], or null for a shop
 * term that chose nothing. Price card has no toolbar, whatever was chosen.
 */
function monospace_archive_resolve( $own_view, $own_bar, $taxonomy = 'category' ) {
    $views = monospace_archive_views();
    $g     = monospace_archive_global();
    if ( $own_view === 'off' ) { $own_view = 'list'; $own_bar = $own_bar ?: 'hide'; } // an early value (2026-10-23): list only, no toolbar
    if ( $own_view === '' && ! in_array( $taxonomy, MONOSPACE_ARCHIVE_DEFAULT_TAXONOMIES, true ) ) return null;
    $view = isset( $views[ $own_view ] ) ? $own_view : ( isset( $views[ $g['view'] ] ) ? $g['view'] : 'list' );
    $bar  = $view === 'cards' ? false : ( $own_bar === 'show' ? true : ( $own_bar === 'hide' ? false : (bool) $g['bar'] ) );
    return [ 'view' => $view, 'bar' => $bar ];
}

/** The current page's view and toolbar ([ 'view', 'bar' ]), or null when it has neither (not the Art Feed or a category / tag / place page) */
function monospace_archive_page_state() {
    $g = monospace_archive_global();
    if ( is_home() ) return monospace_archive_resolve( $g['feed_view'], $g['feed_bar'] );
    if ( ! ( is_category() || is_tag() || is_tax() ) ) return null;
    $term = get_queried_object();
    if ( ! $term || ! isset( $term->taxonomy ) || ! in_array( $term->taxonomy, MONOSPACE_ARCHIVE_TAXONOMIES, true ) ) return null;
    return monospace_archive_resolve(
        (string) get_term_meta( $term->term_id, '_monospace_archive_view', true ),
        (string) get_term_meta( $term->term_id, '_monospace_archive_bar', true ),
        $term->taxonomy
    );
}

/** The view this archive starts in ('list', 'grid', 'detail' or 'cards'), '' when the page has no view of its own */
function monospace_get_archive_view_default() {
    $state = monospace_archive_page_state();
    return $state ? $state['view'] : '';
}

/** Does this archive show the viewing toolbar? */
function monospace_archive_toolbar_shown() {
    $state = monospace_archive_page_state();
    return $state && $state['bar'];
}

/** Is this archive in price card view? (an admin's choice; the Art Feed's toolbar may also offer it as a button) */
function monospace_archive_cards_on() {
    return monospace_get_archive_view_default() === 'cards';
}

/** Does the toolbar carry a Price card button? Only the Art Feed's, when Layout Manager > Links & toolbar > Views & viewing toolbar says so (2026-10-28) */
function monospace_archive_cards_button() {
    return is_home() && monospace_archive_toolbar_shown() && ! empty( monospace_archive_global()['feed_cards'] ) && isset( monospace_archive_views()['cards'] );
}

// Cards view: each artwork brings its standalone price card (shown only in cards view - _archives.scss). After the
// archive price card (Core, priority 20). Opens on the term's own "Opens on" tab, like the archive's cards.
add_filter( 'the_content', function ( $content ) {
    if ( is_admin() || is_feed() || ! in_the_loop() || ! is_main_query() || ! ( is_home() || is_archive() ) ) return $content;
    if ( ! ( monospace_archive_cards_on() || monospace_archive_cards_button() ) || ! shortcode_exists( 'price_card_standalone' ) ) return $content;
    $post_id = (int) get_the_ID();
    if ( ! function_exists( 'monospace_is_artwork' ) || ! monospace_is_artwork( $post_id ) ) return $content;
    $tab  = ( is_category() || is_tag() || is_tax() ) ? (string) get_term_meta( (int) get_queried_object_id(), '_ms_price_card_tab', true ) : '';
    $card = do_shortcode( '[price_card_standalone post="' . $post_id . '"' . ( $tab !== '' ? ' tab="' . esc_attr( $tab ) . '"' : '' ) . ']' );
    return $card !== '' ? $content . '<div class="archive-sa-card">' . $card . '</div>' : $content;
}, 30 );

// Detail list view (2026-10-23): each artwork as a row - picture on the left, title / SKU number / medium / availability /
// price on the right (Core's monospace_artwork_row_html()); a blog post or page gets its picture, title and first characters.
// The row rides along (.archive-detail-row) while the page starts in detail view or has the toolbar (a visitor may switch to
// it) and shows only in detail view (_archives.scss). Search results always show it (html.archive-view-detail, set below).
add_filter( 'the_content', function ( $content ) {
    if ( is_admin() || is_feed() || ! in_the_loop() || ! is_main_query() || ! ( is_home() || is_archive() || is_search() ) ) return $content;
    if ( ! is_search() ) {
        $state = monospace_archive_page_state();
        if ( ! $state || ( $state['view'] !== 'detail' && ! $state['bar'] ) ) return $content;
    }
    if ( ! function_exists( 'monospace_artwork_row_html' ) ) return $content;
    $row = monospace_artwork_row_html( (int) get_the_ID() );
    // Not an artwork (a blog post, a page): its featured image, title and the first characters of its text
    if ( $row === '' && function_exists( 'monospace_post_row_html' ) ) $row = monospace_post_row_html( (int) get_the_ID(), monospace_archive_global()['detail_chars'] );
    return $row !== '' ? $content . '<div class="archive-detail-row">' . $row . '</div>' : $content;
}, 31 );

// Search results: detail view from the first paint (no script, so no flash)
add_filter( 'language_attributes', function ( $output ) {
    return is_search() ? $output . ' class="archive-view-detail"' : $output;
} );

/* ---- the choices an admin makes (term edit screen, Layout Manager > Categories, the Art Feed) ---- */

/** A page's view choice: '' = same as everywhere (and what that is now) */
function monospace_archive_view_choices() {
    $g       = monospace_archive_global();
    $views   = monospace_archive_views();
    $choices = [ '' => 'Same as everywhere (' . strtolower( $views[ $g['view'] ] ?? 'list' ) . ')' ];
    foreach ( $views as $key => $name ) $choices[ $key ] = $name . ( $key === 'cards' ? ' (no toolbar)' : '' );
    return $choices;
}

/** A page's toolbar choice */
function monospace_archive_bar_choices() {
    return [ '' => 'Same as everywhere (' . ( monospace_archive_global()['bar'] ? 'shown' : 'hidden' ) . ')', 'show' => 'Show', 'hide' => 'Hide' ];
}

const MONOSPACE_ARCHIVE_SORTS = [
    'newest'     => 'Newest first',
    'oldest'     => 'Oldest first',
    'price-asc'  => 'Price: low to high',
    'price-desc' => 'Price: high to low',
    'size-asc'   => 'Size: small to large',
    'size-desc'  => 'Size: large to small',
    'random'     => 'Random',
];

/** Is the Prints plugin on? (its "Prints available" filter is left out while it's off) */
function monospace_archive_prints_on() {
    return function_exists( 'monospace_feature' ) && monospace_feature( 'prints' );
}

/** The sort a page starts with: its term's own (category / tag / place edit screen, Layout Manager > Categories), else newest */
function monospace_archive_default_sort() {
    if ( ! ( is_category() || is_tag() || is_tax() ) ) return 'newest';
    $sort = (string) get_term_meta( (int) get_queried_object_id(), '_monospace_archive_sort', true );
    return isset( MONOSPACE_ARCHIVE_SORTS[ $sort ] ) ? $sort : 'newest';
}

/** The toolbar's filters and sort from the URL: [ 'available' => bool, 'prints' => bool, 'sort' => key of MONOSPACE_ARCHIVE_SORTS ] */
function monospace_archive_tools_state() {
    $sort = sanitize_key( $_GET['sort'] ?? '' );
    return [
        // Each filter is left out where the page already is nothing else (Available Originals / Prints)
        'available' => ! empty( $_GET['available'] ) && ! is_category( MONOSPACE_AVAILABLE_SLUG ),
        'prints'    => ! empty( $_GET['prints'] ) && ! is_category( 'print' ) && monospace_archive_prints_on(),
        'sort'      => isset( MONOSPACE_ARCHIVE_SORTS[ $sort ] ) ? $sort : monospace_archive_default_sort(),
        'seed'      => absint( $_GET['seed'] ?? 0 ), // random's shuffle
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
        'sort'      => $state['sort'] !== monospace_archive_default_sort() ? $state['sort'] : null, // the page's starting sort needs no mention
        'seed'      => $state['sort'] === 'random' && $state['seed'] ? $state['seed'] : null, // the same shuffle with a filter changed
    ] ), $url );
}

/**
 * Sort post IDs (given newest first) by their original's price or size, ascending or descending.
 * Posts without a value keep their order at the end.
 */
function monospace_archive_sort_ids( $ids, $sort ) {
    if ( $sort === 'random' ) {
        // A shuffle fixed by the seed: each post's place = a hash of seed + ID (the same on every page of the list)
        $seed = monospace_archive_tools_state()['seed'] ?: (int) wp_date( 'Ymd' );
        usort( $ids, fn( $a, $b ) => strcmp( md5( $seed . '-' . $a ), md5( $seed . '-' . $b ) ) );
        return $ids;
    }
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
        var view = '<?php echo esc_js( $default ); ?>', views = <?php echo wp_json_encode( monospace_archive_toolbar_shown() ? array_merge( [ 'list', 'grid', 'detail' ], monospace_archive_cards_button() ? [ 'cards' ] : [] ) : [] ); ?>;
        try { var saved = localStorage.getItem(KEY); if (views.indexOf(saved) > -1) view = saved; } catch (e) {} // a visitor's pick only where the toolbar is

        function apply(v) {
            root.classList.toggle('archive-view-grid', v === 'grid');
            root.classList.toggle('archive-view-cards', v === 'cards');
            root.classList.toggle('archive-view-detail', v === 'detail');
            document.querySelectorAll('.archive-view-toggle button:not(.ms-view-btn)').forEach(function (btn) { // not the Art Filter's
                btn.setAttribute('aria-pressed', btn.dataset.view === v ? 'true' : 'false');
            });
        }

        apply(view);
        document.addEventListener('DOMContentLoaded', function () { apply(view); });

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.archive-view-toggle button:not(.ms-view-btn)');
            if (!btn) return;
            view = btn.dataset.view;
            try { localStorage.setItem(KEY, view); } catch (e) {}
            apply(view);
        });

        // Filters / sort: each choice carries its page URL
        document.addEventListener('change', function (e) {
            var el = e.target.closest('.archive-tools [data-href], .archive-tools select');
            if (!el) return;
            var to = el.tagName === 'SELECT' ? el.value : el.dataset.href;
            // Random: a fresh shuffle each time it's chosen (its seed then rides along to the next pages)
            if (el.tagName === 'SELECT' && /[?&]sort=random(&|$)/.test(to) && !/[?&]seed=/.test(to)) to += '&seed=' + (1 + Math.floor(Math.random() * 999999));
            location.href = to;
        });
    })();
    </script>
    <?php
} );

// Toggle icons at the top of the feed, after the archive header (priority 5)
add_action( 'loop_start', function ( $query ) {
    if ( ! $query->is_main_query() || is_paged() ) return;
    if ( ! monospace_archive_toolbar_shown() ) return;

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
        <button type="button" data-view="detail" aria-label="Detail list view" title="Detail list view - picture on the left, details on the right">
            <svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true"><rect x="2" y="2.5" width="5" height="5" rx="1"/><rect x="9" y="3" width="9" height="1.6" rx=".8"/><rect x="9" y="6" width="6" height="1.4" rx=".7"/><rect x="2" y="12.5" width="5" height="5" rx="1"/><rect x="9" y="13" width="9" height="1.6" rx=".8"/><rect x="9" y="16" width="6" height="1.4" rx=".7"/></svg>
        </button>
        <?php if ( monospace_archive_cards_button() ) : ?>
        <button type="button" data-view="cards" aria-label="Price card view" title="Price card view - each artwork as a price card">
            <svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true"><path fill-rule="evenodd" d="M4 2h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2zm1 2v6h10V4H5zm0 8v1.6h6V12H5zm0 3v1.4h4V15H5z"/></svg>
        </button>
        <?php endif; ?>
    </nav>
    <?php
}, 6 );

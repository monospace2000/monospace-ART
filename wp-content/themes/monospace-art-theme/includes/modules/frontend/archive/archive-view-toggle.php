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
 * Related Files:
 * - feed-headers.php - Other per-term archive fields; defines MONOSPACE_ARCHIVE_TAXONOMIES
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
        add_action( "{$taxonomy}_edit_form_fields", 'monospace_render_archive_view_field', 11, 1 );
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
 * The starting view for the current archive ('list' or 'grid'), or '' when the
 * toggle is off or this isn't a term archive.
 */
function monospace_get_archive_view_default() {
    if ( ! ( is_category() || is_tag() || is_tax() ) ) return '';

    $term = get_queried_object();
    if ( ! $term || ! isset( $term->taxonomy ) || ! in_array( $term->taxonomy, MONOSPACE_ARCHIVE_TAXONOMIES, true ) ) return '';

    $view = get_term_meta( $term->term_id, '_monospace_archive_view', true );
    return in_array( $view, [ 'list', 'grid' ], true ) ? $view : '';
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
    ?>
    <nav class="archive-view-toggle" aria-label="View">
        <button type="button" data-view="list" aria-label="List view" title="List view">
            <svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true"><rect x="2" y="3" width="16" height="3" rx="1"/><rect x="2" y="8.5" width="16" height="3" rx="1"/><rect x="2" y="14" width="16" height="3" rx="1"/></svg>
        </button>
        <button type="button" data-view="grid" aria-label="Grid view" title="Grid view">
            <svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true"><rect x="2" y="2" width="7" height="7" rx="1"/><rect x="11" y="2" width="7" height="7" rx="1"/><rect x="2" y="11" width="7" height="7" rx="1"/><rect x="11" y="11" width="7" height="7" rx="1"/></svg>
        </button>
    </nav>
    <?php
}, 6 );

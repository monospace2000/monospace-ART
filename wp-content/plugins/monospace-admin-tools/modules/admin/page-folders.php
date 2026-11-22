<?php
/**
 * Pages as folders (2026-11-16, owner: "I'll set parent pages to draft or private myself in the Pages list").
 *
 * A parent page that is a Draft or Private has no public page, but its children keep their addresses (/services/custom-work/):
 * WordPress builds a child's address from its parents' names whatever their status (get_page_uri, get_page_by_path - checked in
 * 7.1.4). Two things get in the way in wp-admin, and are put right here:
 *   - every Parent picker (the block editor's, Quick Edit's, the classic Page Attributes box) lists published pages only, so a
 *     draft folder could not be chosen for a new child. They now list Draft and Private pages too;
 *   - nothing says a draft page is a folder: the Pages list labels an unpublished page that has child pages "Folder · parent of N pages".
 * The bare address of a folder is a 404 for visitors: nothing forwards it (owner: old links don't matter). Published pages that forward
 * are Core's retired-pages.php list (labeled "Forwarding page").
 *
 * The Pages list reads as a tree (2026-11-16, owner: "indentation or sorting so the structure becomes visually distinct"). WordPress already
 * lists children under their parent in tree order (only in the default order - sorting by a column flattens it) and puts "— " before a
 * child's title; this module indents each row by its level (the rows carry level-N classes) with a connector, tints and marks a folder row
 * (class ms-folder), and turns every label into a colored chip: Folder, Utility page, Forwarding page, Used by the site (the Core labels print
 * <span class="ms-pg-chip ms-pg-...">), Private / Draft / Front Page ... (wrapped by the small script below). The script also removes
 * WordPress's plain-text dashes and commas around the title and labels, which only exist to separate things CSS now separates.
 * A **Parent** column (sortable: orderby=parent) and the page's **path** under its title (/services/commissions/places/; built in the page from each row's
 * hidden inline data - slug and parent - so a Quick Edit that moves a page shows its new path at once).
 *
 * @package monospace-admin-tools
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// The block editor's Parent dropdown: the only caller that sends a parent_exclude (the parameter's default is an empty list, so it
// has to be non-empty - every other pages request is left alone)
add_filter( 'rest_page_query', function ( $args, $request ) {
    if ( ! empty( $request->get_param( 'parent_exclude' ) ) && current_user_can( 'edit_pages' ) ) $args['post_status'] = [ 'publish', 'draft', 'private' ];
    return $args;
}, 10, 2 );

// Quick Edit / Bulk Edit in the Pages list, and the classic editor's Page Attributes box
$monospace_parent_statuses = function ( $args ) {
    $args['post_status'] = [ 'publish', 'draft', 'private' ];
    return $args;
};
add_filter( 'quick_edit_dropdown_pages_args', $monospace_parent_statuses );
add_filter( 'page_attributes_dropdown_pages_args', $monospace_parent_statuses );

// The Pages list: an unpublished page with child pages is a folder
add_filter( 'display_post_states', function ( $states, $post ) {
    if ( ! is_object( $post ) || ( $post->post_type ?? '' ) !== 'page' || in_array( $post->post_status, [ 'publish', 'trash', 'auto-draft' ], true ) ) return $states;
    $kids = count( get_posts( [ 'post_type' => 'page', 'post_parent' => $post->ID, 'post_status' => [ 'publish', 'draft', 'private' ], 'numberposts' => -1, 'fields' => 'ids' ] ) );
    if ( $kids ) $states['ms_folder'] = '<span class="ms-pg-chip ms-pg-folder">Folder · parent of ' . $kids . ( $kids === 1 ? ' page' : ' pages' ) . '</span>';
    return $states;
}, 10, 2 );

/** IDs of the pages that have child pages (any status but trash): one query per request */
function monospace_page_parent_ids() {
    static $ids = null;
    if ( $ids === null ) {
        global $wpdb;
        $ids = array_fill_keys( array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT post_parent FROM {$wpdb->posts} WHERE post_type = 'page' AND post_parent > 0 AND post_status NOT IN ('trash','auto-draft')" ) ), true );
    }
    return $ids;
}

// An unpublished page with child pages: its row gets the class ms-folder (the Pages list's rows are built with get_post_class)
add_filter( 'post_class', function ( $classes, $class, $post_id ) {
    if ( ! is_admin() ) return $classes;
    $post = get_post( $post_id );
    if ( $post && $post->post_type === 'page' && ! in_array( $post->post_status, [ 'publish', 'trash', 'auto-draft' ], true ) && isset( monospace_page_parent_ids()[ (int) $post_id ] ) ) $classes[] = 'ms-folder';
    return $classes;
}, 10, 3 );

// The Parent column, right after the title: the page's immediate parent (a link to it), a dash for a top-level page
add_filter( 'manage_pages_columns', function ( $columns ) {
    $out = [];
    foreach ( $columns as $key => $label ) {
        $out[ $key ] = $label;
        if ( $key === 'title' ) $out['ms_parent'] = __( 'Parent', 'monospace-art-core' );
    }
    return $out;
} );
add_action( 'manage_pages_custom_column', function ( $column, $post_id ) {
    if ( $column !== 'ms_parent' ) return;
    $parent = (int) wp_get_post_parent_id( $post_id );
    if ( ! $parent ) { echo '<span class="ms-pg-none" aria-label="No parent">—</span>'; return; }
    $title = trim( wp_strip_all_tags( (string) get_post_field( 'post_title', $parent ) ) );
    printf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $parent ) ), esc_html( $title !== '' ? $title : __( '(no title)', 'monospace-art-core' ) ) );
}, 10, 2 );
add_filter( 'manage_edit-page_sortable_columns', function ( $columns ) {
    $columns['ms_parent'] = 'parent';
    return $columns;
} );

// The Pages list's look and its small script
add_action( 'admin_head-edit.php', function () {
    if ( ( $_GET['post_type'] ?? '' ) !== 'page' ) return; ?>
    <style id="ms-pages-tree">
        /* WordPress's "— " before a child's title (a decorative span) is replaced by the indentation and connector below */
        .post-type-page .wp-list-table .column-title > strong > span[aria-hidden="true"] { display: none; }
        /* Indentation by level: the rows carry level-0 ... level-N; a connector marks a child */
        .post-type-page .wp-list-table .column-title > strong { position: relative; display: block; }
        .post-type-page .wp-list-table tr.level-1 .column-title > strong { padding-left: 28px; }
        .post-type-page .wp-list-table tr.level-2 .column-title > strong { padding-left: 56px; }
        .post-type-page .wp-list-table tr.level-3 .column-title > strong { padding-left: 84px; }
        .post-type-page .wp-list-table tr.level-4 .column-title > strong { padding-left: 112px; }
        .post-type-page .wp-list-table tr:not(.level-0) .column-title > strong::before { content: "└"; position: absolute; top: 0; color: #a7aaad; font-weight: 400; }
        .post-type-page .wp-list-table tr.level-1 .column-title > strong::before { left: 8px; }
        .post-type-page .wp-list-table tr.level-2 .column-title > strong::before { left: 36px; }
        .post-type-page .wp-list-table tr.level-3 .column-title > strong::before { left: 64px; }
        .post-type-page .wp-list-table tr.level-4 .column-title > strong::before { left: 92px; }
        /* A top-level page starts a group; children are lighter */
        .post-type-page .wp-list-table tbody tr.level-0 > * { border-top: 1px solid #c3c4c7; }
        .post-type-page .wp-list-table tbody tr:not(.level-0) a.row-title { font-weight: 400; }
        /* A folder: tinted row, a folder icon before its name */
        .post-type-page .wp-list-table tbody tr.ms-folder > * { background: #fffbeb; }
        .post-type-page .wp-list-table tbody tr.ms-folder a.row-title::before { content: "\f318"; font-family: dashicons; font-weight: 400; color: #b45309; margin-right: 5px; vertical-align: -2px; text-decoration: none; display: inline-block; }
        /* The Parent column and the path under a title (addresses are set in monospace) */
        .post-type-page .wp-list-table th.column-ms_parent, .post-type-page .wp-list-table td.column-ms_parent { width: 13%; }
        .post-type-page .wp-list-table .column-ms_parent .ms-pg-none { color: #a7aaad; }
        .ms-pg-path { display: block; margin-top: 2px; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; font-weight: 400; color: #646970; word-break: break-all; }
        /* The labels as chips */
        .post-type-page .wp-list-table .post-state { margin-left: 6px; font-weight: 400; }
        .ms-pg-chip { display: inline-block; font-size: 11px; line-height: 1.7; padding: 0 8px; border-radius: 10px; background: #f0f0f1; color: #50575e; vertical-align: 1px; }
        .ms-pg-chip.ms-pg-folder { background: #fef3c7; color: #92400e; }
        .ms-pg-chip.ms-pg-utility { background: #e7f0fa; color: #135e96; }
        .ms-pg-chip.ms-pg-forwarding { background: #fff; color: #50575e; border: 1px dashed #a7aaad; }
        .ms-pg-chip.ms-pg-role { background: #edfaef; color: #1e6b2e; }
        .ms-pg-chip.ms-pg-private, .ms-pg-chip.ms-pg-draft { background: #fff; color: #50575e; border: 1px solid #c3c4c7; }
    </style>
    <?php
} );

add_action( 'admin_footer-edit.php', function () {
    if ( ( $_GET['post_type'] ?? '' ) !== 'page' ) return;
    // Every page's slug and parent, to build a path from (a row's own hidden inline data corrects it after a Quick Edit)
    $pages = [];
    foreach ( get_posts( [ 'post_type' => 'page', 'post_status' => [ 'publish', 'private', 'draft', 'pending', 'future' ], 'numberposts' => -1 ] ) as $p ) $pages[ $p->ID ] = [ $p->post_name, (int) $p->post_parent ]; ?>
    <script>
    (function () {
        var PAGES = <?php echo wp_json_encode( $pages ); ?>;
        function pathOf(id) {
            var parts = [], guard = 0;
            while (id && PAGES[id] && guard++ < 12) { parts.unshift(PAGES[id][0]); id = PAGES[id][1]; }
            return '/' + parts.join('/') + '/';
        }
        function tidy(root) {
            (root || document).querySelectorAll('.wp-list-table tbody tr[id^="post-"]').forEach(function (row) {
                var id = row.id.replace('post-', ''), inline = document.getElementById('inline_' + id), strong = row.querySelector('.column-title > strong');
                if (inline) {                                                                                         // fresh from the page or from Quick Edit
                    var slug = inline.querySelector('.post_name'), parent = inline.querySelector('.post_parent');
                    if (slug && parent) PAGES[id] = [slug.textContent, parseInt(parent.textContent, 10) || 0];
                }
                if (!strong || !PAGES[id]) return;
                var path = strong.querySelector('.ms-pg-path');
                if (!path) { path = document.createElement('span'); path.className = 'ms-pg-path'; strong.appendChild(path); }
                path.textContent = pathOf(id);
            });
            (root || document).querySelectorAll('.wp-list-table .column-title > strong').forEach(function (strong) {
                // WordPress's "— " before a child and " — " before the labels: plain text nodes directly in the title cell
                Array.prototype.slice.call(strong.childNodes).forEach(function (n) { if (n.nodeType === 3) strong.removeChild(n); });
                strong.querySelectorAll('.post-state').forEach(function (s) {
                    var last = s.lastChild;
                    if (last && last.nodeType === 3) last.nodeValue = last.nodeValue.replace(/,\s*$/, '');          // the comma that separates labels
                    if (!s.querySelector('.ms-pg-chip')) {                                                           // a plain label: wrap it as a chip
                        var text = s.textContent.trim(), chip = document.createElement('span');
                        chip.className = 'ms-pg-chip ms-pg-' + text.toLowerCase().replace(/[^a-z]+/g, '-');
                        chip.textContent = text; s.textContent = ''; s.appendChild(chip);
                    }
                });
            });
        }
        tidy();
        // Quick Edit and Bulk Edit put a fresh row in
        if (window.jQuery) jQuery(document).ajaxComplete(function () { tidy(); });
    })();
    </script>
    <?php
} );

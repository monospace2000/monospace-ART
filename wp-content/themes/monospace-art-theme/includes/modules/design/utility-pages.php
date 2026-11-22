<?php
/**
 * Utility pages - the Layout Manager tab (2026-11-16, owner: "utility page toggles should be part of Layout Manager", then "make
 * it its own tab"; the file stays in design/ where it was first written as a design module - it is no longer one).
 *
 * A utility page (a tool or a legal text: the Terminal, Inventory Manager, Price Estimator, the legal pages, the commission
 * text pages) leaves out parts of the site's frame and keeps the menu bar. Core does it (modules/frontend/utility-pages.php:
 * the automatic answer, each page's own setting, the CSS, the page editor's "Utility page" panel); this tab - Layout Manager >
 * Utility pages - is where it is set for the whole site:
 *   - what a utility page leaves out: the logo and tagline, the footer, the sharing icons, the Ko-fi button (option
 *     monospace_utility_pages [ 'hide' => [ part => 1|0 ] ]); a page can choose its own parts in its editor sidebar;
 *   - which pages are utility pages: every published page with a tick, saved on the page itself (post meta _monospace_utility,
 *     the same switch as the page editor's panel). Nothing is automatic (owner, 2026-11-16): a page is one only when ticked.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The published pages with what the tab shows: [ [ page, on, has its own parts ], ... ] - the utility pages first */
function monospace_utility_pages_rows() {
    $rows = [];
    foreach ( get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) as $page ) {
        $rows[] = [ $page, monospace_utility_page_is( $page->ID ), monospace_utility_page_has_own_hide( $page->ID ) ];
    }
    usort( $rows, fn( $a, $b ) => [ ! $a[1] ] <=> [ ! $b[1] ] );   // stable: on first, titles as fetched
    return $rows;
}

/** The tab's content (inside Layout Manager's form, which prints the Save button) */
function monospace_render_utility_pages_settings() {
    if ( ! function_exists( 'monospace_utility_settings' ) ) { echo '<p>Core’s utility page module is missing.</p>'; return; }
    $settings = monospace_utility_settings();
    $rows     = monospace_utility_pages_rows(); ?>
    <style>
        .ms-up h2 { margin: 22px 0 6px; font-size: 16px; }
        .ms-up-card { max-width: 780px; padding: 6px 18px 14px; background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; }
        .ms-up-list { margin: 6px 0 0; padding: 0; list-style: none; border: 1px solid #dcdcde; border-radius: 4px; background: #fff; }
        .ms-up-list li { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; margin: 0; padding: 7px 12px; border-top: 1px solid #f0f0f1; }
        .ms-up-list li:first-child { border-top: 0; }
        .ms-up-list label { font-weight: 600; }
        .ms-up-list code { font-size: 12px; color: #646970; }
        .ms-up-list .ms-up-edit { margin-left: auto; font-size: 12px; }
        .ms-up-chip { display: inline-block; padding: 1px 9px; border-radius: 999px; background: #f0f0f1; color: #3c434a; font-size: 12px; }
        .ms-up-chip.is-hand { background: #2271b1; color: #fff; }
        .ms-up-chip.is-off { background: transparent; border: 1px solid #c3c4c7; color: #646970; }
    </style>
    <div class="ms-up">
        <p class="description" style="max-width:760px">Tool pages and legal texts that are not part of the gallery: no logo or tagline, no footer, no sharing icons, no Ko-fi button. The menu bar always stays. A page can also be set on its own edit screen (sidebar, “Utility page”), including which parts it leaves out.</p>

        <h2>A utility page leaves out</h2>
        <div class="ms-up-card">
            <?php foreach ( monospace_utility_parts() as $key => [ $label ] ) : ?>
                <label style="display:block;margin:8px 0 0"><input type="checkbox" name="utility_hide[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $settings['hide'][ $key ] ); ?>> <?php echo esc_html( $label ); ?></label>
            <?php endforeach; ?>
            <p class="description" style="margin-bottom:0">For every utility page that doesn’t choose its own parts. Only utility pages are affected - everywhere else these show as the Look and Links settings say.</p>
        </div>

        <h2>Which pages</h2>
        <div class="ms-up-card">
            <p class="description" style="margin-top:8px">Tick a page to make it a utility page. Nothing is automatic: a page is one only when ticked here or switched on in its own edit screen (a new legal page or tool page needs its tick).</p>
            <?php if ( count( $rows ) > 7 ) : ?>
                <p><input type="search" id="ms-up-find" class="regular-text" placeholder="Find a page…" aria-label="Find a page"></p>
            <?php endif; ?>
            <input type="hidden" name="utility_pages_listed" value="<?php echo esc_attr( implode( ',', array_map( fn( $r ) => $r[0]->ID, $rows ) ) ); ?>">
            <ul class="ms-up-list" id="ms-up-list">
                <?php foreach ( $rows as [ $page, $on, $parts ] ) : ?>
                    <li data-find="<?php echo esc_attr( strtolower( $page->post_title . ' ' . get_page_uri( $page ) ) ); ?>">
                        <label><input type="checkbox" name="utility_pages[<?php echo (int) $page->ID; ?>]" value="1" <?php checked( $on ); ?>> <?php echo esc_html( get_the_title( $page ) ); ?></label>
                        <code>/<?php echo esc_html( get_page_uri( $page ) ); ?>/</code>
                        <?php if ( $on && $parts ) echo '<span class="ms-up-chip" title="This page chooses its own parts in its edit screen">Own parts</span>'; ?>
                        <?php if ( $link = get_edit_post_link( $page->ID ) ) echo '<a class="ms-up-edit" href="' . esc_url( $link ) . '">Edit page</a>'; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <script>
    (function () {
        var find = document.getElementById('ms-up-find'); if (!find) return;
        find.addEventListener('input', function () {
            var q = find.value.trim().toLowerCase();
            document.querySelectorAll('#ms-up-list li').forEach(function (li) { li.hidden = q !== '' && li.dataset.find.indexOf(q) < 0; });
        });
    })();
    </script>
    <?php
}

/** Save the tab: the site-wide parts, and a page's own switch where the tick differs from what the page is now (on = '1', off = no meta) */
function monospace_save_utility_pages_settings() {
    if ( ! isset( $_POST['utility_pages_listed'] ) || ! function_exists( 'monospace_utility_settings' ) ) return;
    $hide = [];
    $sent = (array) ( $_POST['utility_hide'] ?? [] );
    foreach ( array_keys( monospace_utility_parts() ) as $key ) $hide[ $key ] = isset( $sent[ $key ] ) ? 1 : 0;
    update_option( 'monospace_utility_pages', [ 'hide' => $hide ] );

    $listed = array_filter( array_map( 'absint', explode( ',', (string) wp_unslash( $_POST['utility_pages_listed'] ) ) ) );
    $ticked = (array) ( $_POST['utility_pages'] ?? [] );
    foreach ( $listed as $id ) {
        if ( get_post_type( $id ) !== 'page' ) continue;
        $on = isset( $ticked[ $id ] );
        if ( $on === monospace_utility_page_is( $id ) ) continue;                 // unchanged: leave the page's setting alone
        $on ? update_post_meta( $id, '_monospace_utility', '1' ) : delete_post_meta( $id, '_monospace_utility' );
    }
    // The page cache holds the old pages
    if ( function_exists( 'sg_cachepress_purge_everything' ) ) sg_cachepress_purge_everything();
}

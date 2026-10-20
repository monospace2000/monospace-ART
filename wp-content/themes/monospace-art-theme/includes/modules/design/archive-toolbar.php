<?php
/**
 * Design module: Viewing toolbar (2026-10-16)
 *
 * Where the archive toolbar (Original available / Prints available / sort / list-grid, archive-view-toggle.php)
 * appears:
 *   - Category, tag and place pages: a master switch. On = as each term's own "List / Grid Toggle"
 *     setting says (Off there = no toolbar on that term); off = no toolbar on any of them (list view).
 *   - Art Feed (is_home): the toolbar there too, starting in list or grid view.
 * Shown in Layout Manager > Appearance & Effects through the design-module registry.
 *
 * Options: monospace_archive_toolbar_terms ('1' default), monospace_archive_toolbar_feed ('0' default),
 * monospace_archive_toolbar_feed_view ('list' | 'grid').
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['archive-toolbar'] = [
        'title'       => 'Viewing toolbar',
        'description' => 'The bar above category pages and the Art Feed: Original available, Prints available, sorting, list / grid.',
        'group'       => 'links',
        'order'       => 30,
        'summary'     => function () {
            $s = monospace_archive_toolbar_settings();
            return 'Category pages ' . ( $s['terms'] ? 'on' : 'off' ) . ' · Art Feed ' . ( $s['feed'] ? 'on (' . $s['feed_view'] . ')' : 'off' );
        },
        'render'      => 'monospace_render_archive_toolbar_settings',
        'save'        => 'monospace_save_archive_toolbar_settings',
    ];
    return $modules;
} );

/** [ 'terms' => bool, 'feed' => bool, 'feed_view' => 'list' | 'grid' ] */
function monospace_archive_toolbar_settings() {
    return [
        'terms'     => get_option( 'monospace_archive_toolbar_terms', '1' ) === '1',
        'feed'      => get_option( 'monospace_archive_toolbar_feed', '0' ) === '1',
        'feed_view' => get_option( 'monospace_archive_toolbar_feed_view', 'list' ) === 'grid' ? 'grid' : 'list',
    ];
}

function monospace_render_archive_toolbar_settings() {
    $s = monospace_archive_toolbar_settings(); ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Category pages</th>
            <td>
                <input type="hidden" name="archive_toolbar_terms" value="0">
                <label><input type="checkbox" name="archive_toolbar_terms" value="1" <?php checked( $s['terms'] ); ?>> Show on category, tag and place pages</label>
                <p class="description">Each one still follows its own “List / Grid Toggle” setting (Posts &gt; Categories / Tags &gt; edit). Unticked: no toolbar on any of them, and they show as a list.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Art Feed</th>
            <td>
                <input type="hidden" name="archive_toolbar_feed" value="0">
                <label style="margin-right:18px"><input type="checkbox" name="archive_toolbar_feed" value="1" <?php checked( $s['feed'] ); ?>> Show on the Art Feed</label>
                starting in
                <label style="margin-left:8px"><input type="radio" name="archive_toolbar_feed_view" value="list" <?php checked( $s['feed_view'], 'list' ); ?>> list</label>
                <label style="margin-left:8px"><input type="radio" name="archive_toolbar_feed_view" value="grid" <?php checked( $s['feed_view'], 'grid' ); ?>> grid</label> view
                <p class="description">While a filter or sort is in use, pinned feed pages (“Show in feed”) are left out.</p>
            </td>
        </tr>
    </table>
    <?php
}

function monospace_save_archive_toolbar_settings() {
    if ( ! isset( $_POST['archive_toolbar_terms'] ) ) return;
    update_option( 'monospace_archive_toolbar_terms', $_POST['archive_toolbar_terms'] === '1' ? '1' : '0' );
    update_option( 'monospace_archive_toolbar_feed', ( $_POST['archive_toolbar_feed'] ?? '0' ) === '1' ? '1' : '0' );
    update_option( 'monospace_archive_toolbar_feed_view', ( $_POST['archive_toolbar_feed_view'] ?? '' ) === 'grid' ? 'grid' : 'list' );
}

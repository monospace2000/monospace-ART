<?php
/**
 * Design module: Views & viewing toolbar (2026-10-16; one place for all of it 2026-10-23)
 *
 * The site-wide choices for the Art Feed and the category, tag and place pages (archive-view-toggle.php has the logic):
 *   - Viewing toolbar on / off: the bar with Original available / Prints available / sort / the list, grid and detail list
 *     buttons. Off = nobody gets the bar and every page shows its view.
 *   - Default view: list, grid, detail list or price card - what a page shows unless it chose its own, and the only view when
 *     the toolbar is off. Price card is never a visitor's button; a page that starts in it has no toolbar.
 *   - Art Feed: its own toolbar and view choice, or the same as everywhere - and (2026-10-28) whether its toolbar offers a
 *     Price card button (visitors can then switch to cards there; anywhere else price card stays an admin's choice).
 *   - Text in the detail list: how many characters of a blog post's or page's text its row shows.
 * Each category, tag and place has the same two choices (View, Viewing toolbar) on its edit screen and in Layout Manager >
 * Categories; they come from the same lists (monospace_archive_view_choices() / _bar_choices(), archive-view-toggle.php).
 * Shown in Layout Manager > Links & toolbar through the design-module registry.
 *
 * Options: monospace_archive_bar ('1' | '0'), monospace_archive_default_view (list | grid | detail | cards),
 * monospace_archive_feed_bar ('' | show | hide), monospace_archive_feed_view ('' | a view), monospace_archive_feed_cards ('1' | '0'),
 * monospace_archive_toolbar_detail_chars (200).
 * Until this screen is saved the older options still decide (the same result as before): monospace_archive_toolbar_terms,
 * _feed, _feed_view, _feed_detail, _cards.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['archive-toolbar'] = [
        'title'       => 'Views & viewing toolbar',
        'description' => 'How category pages and the Art Feed are shown - list, grid, detail list or price card - and whether visitors get the bar to change it (Original available, Prints available, sorting).',
        'group'       => 'links',
        'order'       => 30,
        'summary'     => function () {
            $s     = monospace_archive_toolbar_settings();
            $views = monospace_archive_views();
            $feed  = ( $s['feed_bar'] === '' ? '' : ( $s['feed_bar'] === 'show' ? 'toolbar on' : 'toolbar off' ) ) . ( $s['feed_view'] !== '' ? ( $s['feed_bar'] === '' ? '' : ', ' ) . strtolower( $views[ $s['feed_view'] ] ?? '' ) : '' );
            return 'Toolbar ' . ( $s['bar'] ? 'on' : 'off' ) . ' · default view ' . strtolower( $views[ $s['view'] ] ?? 'list' ) . ' · Art Feed ' . ( $feed !== '' ? $feed : 'as everywhere' );
        },
        'render'      => 'monospace_render_archive_toolbar_settings',
        'save'        => 'monospace_save_archive_toolbar_settings',
    ];
    return $modules;
} );

/** [ 'bar' => bool, 'view' => key, 'feed_bar' => '' | 'show' | 'hide', 'feed_view' => '' | key, 'feed_cards' => bool, 'detail_chars' => int ] */
function monospace_archive_toolbar_settings() {
    $views = function_exists( 'monospace_archive_views' ) ? monospace_archive_views() : [ 'list' => 'List', 'grid' => 'Grid', 'detail' => 'Detail list' ];

    // The older options (before 2026-10-23) stand in until this screen is saved
    $feed_on = get_option( 'monospace_archive_toolbar_feed', '0' ) === '1';
    $old     = (string) get_option( 'monospace_archive_toolbar_feed_view', 'list' );
    if ( ! isset( $views[ $old ] ) || ( $old === 'cards' && get_option( 'monospace_archive_toolbar_cards', '0' ) !== '1' ) ) $old = 'list';

    $bar = get_option( 'monospace_archive_bar', null );
    if ( $bar === null ) $bar = get_option( 'monospace_archive_toolbar_terms', '1' );
    $view = (string) get_option( 'monospace_archive_default_view', 'detail' );
    $fbar = get_option( 'monospace_archive_feed_bar', null );
    if ( $fbar === null ) $fbar = $feed_on ? 'show' : 'hide';
    $fview = get_option( 'monospace_archive_feed_view', null );
    if ( $fview === null ) $fview = $feed_on ? $old : ( get_option( 'monospace_archive_toolbar_feed_detail', '0' ) === '1' ? 'detail' : 'list' );

    return [
        'bar'          => $bar === '1',
        'view'         => isset( $views[ $view ] ) ? $view : 'detail',
        'feed_bar'     => in_array( $fbar, [ 'show', 'hide' ], true ) ? $fbar : '',
        'feed_view'    => isset( $views[ $fview ] ) ? $fview : '',
        'feed_cards'   => isset( $views['cards'] ) && get_option( 'monospace_archive_feed_cards', '0' ) === '1',
        'detail_chars' => max( 0, min( 1000, (int) get_option( 'monospace_archive_toolbar_detail_chars', 200 ) ) ),
    ];
}

/** Grid view columns (2026-10-26, owner: "max columns on mobile should be three (like instagram)"): computers & tablets 2-6 (3), phones 1-4 (3) */
function monospace_archive_grid_columns() {
    return [
        'wide'  => max( 2, min( 6, (int) get_option( 'monospace_archive_grid_cols', 3 ) ) ),
        'phone' => max( 1, min( 4, (int) get_option( 'monospace_archive_grid_cols_phone', 3 ) ) ),
    ];
}

// The CSS reads --grid-cols / --grid-cols-m (_archives.scss, 3 and 3 when not set)
add_action( 'wp_head', function () {
    $c = monospace_archive_grid_columns();
    $v = ( $c['wide'] !== 3 ? '--grid-cols:' . $c['wide'] . ';' : '' ) . ( $c['phone'] !== 3 ? '--grid-cols-m:' . $c['phone'] . ';' : '' );
    if ( $v !== '' ) echo '<style id="ms-grid-cols">:root{' . $v . "}</style>\n";
}, 31 );

function monospace_render_archive_toolbar_settings() {
    $s     = monospace_archive_toolbar_settings();
    $views = monospace_archive_views();
    ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Viewing toolbar</th>
            <td>
                <input type="hidden" name="archive_toolbar_bar" value="0">
                <label><input type="checkbox" name="archive_toolbar_bar" value="1" <?php checked( $s['bar'] ); ?>> Show the bar above the artworks - Original available, Prints available, sort and the list / grid / detail list buttons</label>
                <p class="description">On category, tag and place pages and the Art Feed. Unticked: nobody gets the bar and every page just shows its view (below). A page can differ - its own “Viewing toolbar” choice (edit the category, or Layout Manager → Categories), and the Art Feed below.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Default view</th>
            <td>
                <?php foreach ( $views as $key => $name ) : ?>
                <label style="margin-right:18px"><input type="radio" name="archive_default_view" value="<?php echo esc_attr( $key ); ?>" <?php checked( $s['view'], $key ); ?>> <?php echo esc_html( strtolower( $name ) ); ?></label>
                <?php endforeach; ?>
                <p class="description">What a page shows unless it has its own view - and the only view when the toolbar is off. With the bar on, a visitor’s own pick wins.
                    <b>Detail list</b>: picture on the left, title, number, medium, availability and price on the right; blog posts and pages get a row too.
                    <?php if ( isset( $views['cards'] ) ) : ?><b>Price card</b> is an admin’s choice only: it has no button for visitors (except the Art Feed’s, below), and a page in it has no bar (the Print category, for example). Every artwork brings its card along, so pages are heavier.<?php endif; ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row">Grid view columns</th>
            <td>
                <?php $gc = monospace_archive_grid_columns(); ?>
                <label>Computers &amp; tablets <select name="archive_grid_cols"><?php for ( $n = 2; $n <= 6; $n++ ) echo '<option value="' . $n . '" ' . selected( $gc['wide'], $n, false ) . '>' . $n . '</option>'; ?></select></label>
                <label style="margin-left:18px">Phones <select name="archive_grid_cols_phone"><?php for ( $n = 1; $n <= 4; $n++ ) echo '<option value="' . $n . '" ' . selected( $gc['phone'], $n, false ) . '>' . $n . '</option>'; ?></select></label>
                <p class="description">How many square tiles side by side in the grid view (the Art Feed, categories, tags and places). Three on phones, like Instagram.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Art Feed</th>
            <td>
                <label>Toolbar
                    <select name="archive_feed_bar">
                        <?php foreach ( monospace_archive_bar_choices() as $key => $name ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['feed_bar'], $key ); ?>><?php echo esc_html( $name ); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label style="margin-left:18px">View
                    <select name="archive_feed_view">
                        <?php foreach ( monospace_archive_view_choices() as $key => $name ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $s['feed_view'], $key ); ?>><?php echo esc_html( $name ); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <?php if ( isset( $views['cards'] ) ) : ?>
                <p style="margin:10px 0 0">
                    <input type="hidden" name="archive_feed_cards" value="0">
                    <label><input type="checkbox" name="archive_feed_cards" value="1" <?php checked( $s['feed_cards'] ); ?>> Offer <b>Price card</b> as a view button in the Art Feed’s toolbar</label></p>
                <p class="description">Visitors can then switch the Art Feed to price cards; it still opens in the view chosen above. Only while the Art Feed has its toolbar - a feed that <i>starts</i> in price card has none. Every artwork then brings its card along, so the page is heavier.</p>
                <?php endif; ?>
                <p class="description">The front page of posts. While a filter or sort is in use, pinned feed pages (“Show in feed”) are left out.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Text in the detail list</th>
            <td>
                <label>Blog posts and pages show their first <input type="number" name="archive_toolbar_detail_chars" value="<?php echo (int) $s['detail_chars']; ?>" min="0" max="1000" step="10" style="width:5em"> characters</label>
                <p class="description">In the detail list a blog post or page is a row too: its featured image (else the first picture in it), its title and this much of its text. 0 = no text. Artworks show their medium and availability instead. Search results always use the detail list.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">One category</th>
            <td>
                <p class="description" style="margin-top:0">Every category, tag and place has the same two choices - <b>View</b> and <b>Viewing toolbar</b> (“Same as everywhere” unless set) - on its edit screen (Viewing toolbar panel) and, all at once, in <b>Layout Manager → Categories → Views &amp; price cards</b>.</p>
            </td>
        </tr>
    </table>
    <?php
}

function monospace_save_archive_toolbar_settings() {
    if ( ! isset( $_POST['archive_toolbar_bar'] ) ) return;
    $views = monospace_archive_views();
    $view  = (string) ( $_POST['archive_default_view'] ?? '' );
    $fbar  = (string) ( $_POST['archive_feed_bar'] ?? '' );
    $fview = (string) ( $_POST['archive_feed_view'] ?? '' );
    update_option( 'monospace_archive_bar', $_POST['archive_toolbar_bar'] === '1' ? '1' : '0' );
    update_option( 'monospace_archive_default_view', isset( $views[ $view ] ) ? $view : 'detail' );
    update_option( 'monospace_archive_feed_bar', in_array( $fbar, [ 'show', 'hide' ], true ) ? $fbar : '' );
    update_option( 'monospace_archive_feed_view', isset( $views[ $fview ] ) ? $fview : '' );
    update_option( 'monospace_archive_feed_cards', ( $_POST['archive_feed_cards'] ?? '0' ) === '1' ? '1' : '0' );
    update_option( 'monospace_archive_grid_cols', max( 2, min( 6, (int) ( $_POST['archive_grid_cols'] ?? 3 ) ) ) );
    update_option( 'monospace_archive_grid_cols_phone', max( 1, min( 4, (int) ( $_POST['archive_grid_cols_phone'] ?? 3 ) ) ) );
    update_option( 'monospace_archive_toolbar_detail_chars', max( 0, min( 1000, (int) ( $_POST['archive_toolbar_detail_chars'] ?? 200 ) ) ) );
}

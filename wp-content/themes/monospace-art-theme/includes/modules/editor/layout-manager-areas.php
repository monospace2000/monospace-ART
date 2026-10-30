<?php
/**
 * Layout Manager > Headers & Sidebars: one page type's areas (2026-10-20, owner's wish: easier to scan and edit,
 * unused panels collapsed, show where each item goes).
 *
 * Left, a small map of the page - menu bar, logo, top content, header, content, sidebar - each area marked
 * In use / Empty / Not shown, with the header drawn where it goes (above the content or in the sidebar).
 * Right, one panel per area: its title line says where it goes and shows a preview of what's in it; only
 * panels in use start open. "Same on phones & tablets" ticked = the phone editor stays folded away.
 * Hovering a panel lights its area on the map; clicking an area opens its panel.
 *
 * The fields and their names are the ones layout-manager.php has always saved (monospace_save_layout_settings()
 * is unchanged): top_content, sidebar_visibility_field / show_sidebar, header_title, header_desc_desktop,
 * header_desc_mobile, mirror_header, header_location, sidebar_desktop, sidebar_mobile, mirror_sidebar.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** What each page type has: label, where it is, header (true / 'terms' / false), sidebar (true / 'terms'), top (false = no top
 *  content here), override (true = each area can use the Site default or its own - layout-inherit.php), notes */
function monospace_lm_area_types() {
    $override = 'Each %s can change its top content and sidebar in its own <b>This page’s layout</b> box.';
    return [
        'site'       => [ 'label' => 'Site default', 'where' => 'Used on every page that has no override of its own - the Art Feed, categories, posts, pages, search results, the 404 page. Product pages never show a sidebar.',
                          'header' => true, 'location' => true, 'title_help' => '{name} = the category’s name, {search_term} = what was searched for. Empty = a title made from the kind of page.', 'sidebar' => true,
                          'note' => 'Categories have no override of their own: they use this, and each category can have its own (Posts &rarr; Categories &rarr; edit one).' ],
        'blog-feed'  => [ 'label' => 'Art Feed', 'where' => 'The Art Feed - every post, newest first.',
                          'header' => true, 'location' => true, 'title_help' => 'Leave empty for no header.', 'sidebar' => true, 'override' => true ],
        'categories' => [ 'label' => 'Categories', 'where' => 'Category, tag and place pages: they use the Site default, and each can have its own top content, header and sidebar.',
                          'header' => 'terms', 'sidebar' => 'terms', 'top' => false ],
        'search'     => [ 'label' => 'Search Results', 'where' => 'The search results page.', 'header' => true, 'location' => false,
                          'title_default' => 'Search Results', 'title_help' => '{search_term} = what was searched for, e.g. “Results for {search_term}”.', 'sidebar' => true, 'override' => true ],
        '404'        => [ 'label' => '404 Page', 'where' => 'The “page not found” page. Its own text is in the theme’s 404.php.', 'header' => false, 'sidebar' => true, 'override' => true ],
        'posts'      => [ 'label' => 'Posts', 'where' => 'Every post - artworks and blog posts.', 'header' => false, 'sidebar' => true, 'override' => true, 'note' => sprintf( $override, 'post' ) ],
        'pages'      => [ 'label' => 'Pages', 'where' => 'Every page except the home page.', 'header' => false, 'sidebar' => true, 'override' => true, 'note' => sprintf( $override, 'page' ) ],
    ];
}

/** A short, readable preview of a content area: text, with shortcodes shown by name */
function monospace_lm_preview( $html ) {
    $names = [ 'search_form' => 'Search form', 'tag_cloud' => 'Tag cloud', 'recent_posts' => 'Recent posts', 'category_list' => 'Category list',
               'archives_list' => 'Archives', 'nav_menu' => 'Menu', 'archive_header' => 'Feed header', 'price_card' => 'Price card' ];
    $text = preg_replace_callback( '/\[([a-z_]+)[^\]]*\]/', function ( $m ) use ( $names ) {
        return '⟨' . ( $names[ $m[1] ] ?? $m[1] ) . '⟩';
    }, (string) $html );
    $text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' ) ) );
    return mb_strlen( $text ) > 90 ? mb_substr( $text, 0, 88 ) . '…' : $text;
}

/** One page type's screen: the map and the panels */
function monospace_lm_render_areas( $tab ) {
    $types = monospace_lm_area_types();
    if ( ! isset( $types[ $tab ] ) ) return;
    $t   = $types[ $tab ];
    $p   = 'monospace_' . str_replace( '-', '_', $tab ) . '_';
    $id  = 'lm_' . str_replace( '-', '_', $tab ) . '_';
    $opt = fn( $k, $d = '' ) => (string) get_option( $p . $k, $d );

    // Use the Site default or a custom one, per area (the page types that override it - layout-inherit.php)
    $override = ! empty( $t['override'] );
    $mode     = fn( $area ) => $override ? monospace_layout_area_mode( $tab, $area ) : 'custom';
    $has_top  = ( $t['top'] ?? true ) !== false;

    // Top content
    $top       = $opt( 'top_content' );
    $top_state = trim( $top ) !== '' ? 'on' : 'empty';
    if ( $mode( 'top' ) === 'default' ) $top_state = 'terms'; // "terms" = the chip that says where it comes from

    // Header
    $header_state = null;
    if ( $t['header'] === true ) {
        $h_title    = $opt( 'header_title', $t['title_default'] ?? '' );
        $h_desk     = $opt( 'header_desc_desktop' );
        $h_mobile   = $opt( 'header_desc_mobile' );
        $h_mirror   = $opt( 'mirror_header', '1' ) === '1';
        $h_location = ! empty( $t['location'] ) ? $opt( 'header_location', 'top' ) : 'top';
        $header_state = $h_location === 'none' ? 'off' : ( trim( $h_title . $h_desk ) !== '' ? 'on' : 'empty' );
        if ( $mode( 'header' ) === 'default' ) $header_state = 'terms';
    } elseif ( $t['header'] === 'terms' ) {
        $h_location   = 'top';
        $header_state = 'terms';
    }

    // Sidebar
    $show = $tab === 'categories' ? monospace_sidebar_default_visible( 'categories' ) : get_option( $p . 'show_sidebar', '1' ) === '1'; // the type's own (categories: the Site default's)
    if ( $t['sidebar'] === 'terms' ) {
        $s_state = $show ? 'terms' : 'off';
    } else {
        $s_desk   = $opt( 'sidebar_desktop' );
        $s_mobile = $opt( 'sidebar_mobile' );
        $s_mirror = $opt( 'mirror_sidebar', '1' ) === '1';
        $s_state  = ! $show ? 'off' : ( trim( $s_desk ) !== '' ? 'on' : 'empty' );
        if ( $mode( 'sidebar' ) === 'default' ) $s_state = 'terms';
    }

    $words = [ 'on' => 'In use', 'empty' => 'Empty', 'off' => 'Not shown', 'terms' => $override ? 'Site default' : 'Per category' ];
    $chip  = fn( $state ) => '<span class="lm-chip is-' . esc_attr( $state ) . '">' . esc_html( $words[ $state ] ) . '</span>';
    $open  = fn( $state ) => $state === 'on' || ( $state === 'terms' && ! $override ) ? ' open' : '';
    $editor = function ( $content, $editor_id, $name, $rows ) {
        wp_editor( $content, $editor_id, [ 'textarea_name' => $name, 'media_buttons' => true, 'textarea_rows' => $rows, 'teeny' => false, 'quicktags' => true ] );
    };
    // "Use the site default / Custom for ..." at the top of an area's panel; what the site default has, when it is used
    $site_preview = [
        'top'     => fn() => monospace_lm_preview( (string) get_option( 'monospace_site_top_content', '' ) ),
        'header'  => fn() => monospace_lm_preview( trim( get_option( 'monospace_site_header_title', '' ) . ' - ' . get_option( 'monospace_site_header_desc_desktop', '' ), ' -' ) ),
        'sidebar' => fn() => get_option( 'monospace_site_show_sidebar', '1' ) === '1' ? monospace_lm_preview( (string) get_option( 'monospace_site_sidebar_desktop', '' ) ) : 'no sidebar',
    ];
    $mode_row = function ( $area ) use ( $override, $mode, $t ) {
        if ( ! $override ) return;
        $m = $mode( $area );
        echo '<p class="lm-mode"><label><input type="radio" name="' . esc_attr( $area ) . '_mode" value="default" ' . checked( $m, 'default', false ) . '> <b>Use the site default</b></label>'
           . '<label style="margin-left:18px"><input type="radio" name="' . esc_attr( $area ) . '_mode" value="custom" ' . checked( $m, 'custom', false ) . '> <b>Custom for ' . esc_html( $t['label'] ) . '</b></label></p>';
    };
    $site_link = admin_url( 'admin.php?page=monospace-layout-manager&tab=site' );
    $custom_open = function ( $area ) use ( $override, $mode ) { if ( $override ) echo '<div class="lm-custom" data-area="' . esc_attr( $area ) . '"' . ( $mode( $area ) === 'default' ? ' hidden' : '' ) . '>'; };
    $custom_close = function ( $area ) use ( $override, $mode, $site_preview, $site_link ) {
        if ( ! $override ) return;
        echo '</div><p class="description lm-inherit" data-area="' . esc_attr( $area ) . '"' . ( $mode( $area ) === 'default' ? '' : ' hidden' ) . '>Using the <a href="' . esc_url( $site_link ) . '">Site default</a>'
           . ( ( $pv = $site_preview[ $area ]() ) !== '' ? ': ' . esc_html( $pv ) : ' (empty)' ) . '. Your own settings for this are kept if you switch back to Custom.</p>';
    };
    $header_in_sidebar = $header_state && $header_state !== 'off' && ( $h_location ?? 'top' ) === 'sidebar';
    ?>
    <div class="lm-areas">
        <p class="lm-where"><b><?php echo esc_html( $t['label'] ); ?>:</b> <?php echo esc_html( $t['where'] ); ?> <?php echo isset( $t['note'] ) ? wp_kses_post( $t['note'] ) : ''; ?></p>

        <div class="lm-grid">
            <!-- The page, as a map: where each area goes -->
            <figure class="lm-map" aria-label="Where each area goes on the page">
                <div class="lm-r lm-fixed">Menu bar</div>
                <div class="lm-r lm-fixed lm-logo">Logo &amp; tagline</div>
                <?php if ( $has_top ) : ?><button type="button" class="lm-r lm-area is-<?php echo esc_attr( $top_state ); ?>" data-panel="top">Top content</button><?php endif; ?>
                <?php if ( function_exists( 'monospace_sidebar_position' ) && monospace_sidebar_position()['desktop'] === 'left' ) : // Look > Sidebar: on the left ?>
                    <style>.lm-cols.is-left { grid-template-columns: 64px 1fr; } .lm-cols.is-left > .lm-side { order: -1; }</style>
                <?php endif; ?>
                <div class="lm-cols<?php echo function_exists( 'monospace_sidebar_position' ) && monospace_sidebar_position()['desktop'] === 'left' ? ' is-left' : ''; ?>">
                    <div class="lm-main">
                        <?php if ( $header_state && ! $header_in_sidebar ) : ?>
                            <button type="button" class="lm-r lm-area is-<?php echo esc_attr( $header_state ); ?>" data-panel="header">Header</button>
                        <?php endif; ?>
                        <div class="lm-r lm-fixed lm-content">Content</div>
                    </div>
                    <button type="button" class="lm-r lm-area lm-side is-<?php echo esc_attr( $s_state ); ?>" data-panel="sidebar">
                        <?php if ( $header_in_sidebar ) : ?><span class="lm-in-side is-<?php echo esc_attr( $header_state ); ?>" data-panel="header">Header</span><?php endif; ?>
                        Sidebar
                    </button>
                </div>
                <figcaption><?php $sbp = function_exists( 'monospace_sidebar_position' ) ? monospace_sidebar_position() : [ 'desktop' => 'right', 'phone' => 'above' ]; ?>
                    Sidebar: <?php echo esc_html( $sbp['desktop'] ); ?> on computers, <?php echo esc_html( $sbp['phone'] ); ?> the content on phones (<a href="<?php echo esc_url( admin_url( 'themes.php?page=monospace-look' ) ); ?>">Look &rsaquo; Sidebar</a>).<br>
                    <span class="lm-key is-on"></span> in use <span class="lm-key is-empty"></span> empty <span class="lm-key is-off"></span> not shown</figcaption>
            </figure>

            <div class="lm-panels">
                <?php if ( $has_top ) : ?>
                <!-- TOP CONTENT -->
                <details class="lm-panel" data-panel="top"<?php echo $open( $top_state ); ?>>
                    <summary><span class="lm-t">Top content</span> <?php echo $chip( $top_state ); ?>
                        <span class="lm-sub">Full width, under the logo and tagline<?php echo $top_state === 'on' ? ' · ' . esc_html( monospace_lm_preview( $top ) ) : ''; ?></span></summary>
                    <div class="lm-body">
                        <?php $mode_row( 'top' ); $custom_open( 'top' ); ?>
                        <?php $editor( $top, $id . 'top_content', 'top_content', 6 ); ?>
                        <p class="description">Leave empty for none. HTML and shortcodes allowed.</p>
                        <?php // Its dates and close button (design/banner-style.php); how it looks is the site's one banner style (Look > Banners)
                        if ( function_exists( 'monospace_banner_schedule_fields' ) ) monospace_banner_schedule_fields( 'top_style', monospace_banner_area( $p . 'top_style' ) ); ?>
                        <?php $custom_close( 'top' ); ?>
                    </div>
                </details>
                <?php endif; ?>

                <?php if ( $t['header'] === true ) : ?>
                <!-- HEADER -->
                <details class="lm-panel" data-panel="header"<?php echo $open( $header_state ); ?>>
                    <summary><span class="lm-t">Header</span> <?php echo $chip( $header_state ); ?>
                        <span class="lm-sub"><?php echo esc_html( [ 'top' => 'Above the content', 'sidebar' => 'In the sidebar ([archive_header])', 'none' => 'Not shown' ][ $h_location ] ?? 'Above the content' ); ?><?php
                            echo $header_state === 'on' ? ' · ' . esc_html( monospace_lm_preview( ( $h_title !== '' ? $h_title . ' - ' : '' ) . $h_desk ) ) : ''; ?></span></summary>
                    <div class="lm-body">
                        <?php $mode_row( 'header' ); $custom_open( 'header' ); ?>
                        <p><label class="lm-label" for="<?php echo esc_attr( $id ); ?>header_title">Title</label>
                            <input type="text" name="header_title" id="<?php echo esc_attr( $id ); ?>header_title" value="<?php echo esc_attr( $h_title ); ?>" class="regular-text">
                            <span class="description"><?php echo esc_html( $t['title_help'] ?? '' ); ?></span></p>
                        <?php if ( ! empty( $t['location'] ) ) : ?>
                        <p><label class="lm-label" for="<?php echo esc_attr( $id ); ?>header_location">Where</label>
                            <select name="header_location" id="<?php echo esc_attr( $id ); ?>header_location">
                                <option value="top" <?php selected( $h_location, 'top' ); ?>>Above the content</option>
                                <option value="sidebar" <?php selected( $h_location, 'sidebar' ); ?>>In the sidebar (put [archive_header] in the sidebar)</option>
                                <option value="none" <?php selected( $h_location, 'none' ); ?>>Don’t show</option>
                            </select></p>
                        <?php endif; ?>
                        <p class="lm-label">Description</p>
                        <?php $editor( $h_desk, $id . 'header_desc_desktop', 'header_desc_desktop', 5 ); ?>
                        <label class="lm-mirror"><input type="checkbox" name="mirror_header" value="1" <?php checked( $h_mirror ); ?>> Same on phones &amp; tablets</label>
                        <div class="lm-mobile"<?php echo $h_mirror ? ' hidden' : ''; ?>>
                            <p class="lm-label">Description on phones &amp; tablets <span class="description">(empty = the one above)</span></p>
                            <?php $editor( $h_mobile, $id . 'header_desc_mobile', 'header_desc_mobile', 5 ); ?>
                        </div>
                        <?php $custom_close( 'header' ); ?>
                    </div>
                </details>
                <?php elseif ( $t['header'] === 'terms' ) : ?>
                <details class="lm-panel" data-panel="header" open>
                    <summary><span class="lm-t">Header</span> <?php echo $chip( 'terms' ); ?> <span class="lm-sub">Each category sets its own, else the Site default's</span></summary>
                    <div class="lm-body">
                        <p>Each category, tag or place can have its own header title, description (desktop / phones), where it goes, and its view (list / grid / price card - all of them in the table below):
                            <b>Posts → Categories</b> (or Tags) → edit one. One with nothing of its own uses the <a href="<?php echo esc_url( admin_url( 'admin.php?page=monospace-layout-manager&tab=site' ) ); ?>">Site default</a>’s header.</p>
                        <p><a class="button" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=category' ) ); ?>">Edit categories</a>
                            <a class="button" href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=post_tag' ) ); ?>">Edit tags</a></p>
                    </div>
                </details>
                <?php endif; ?>

                <!-- SIDEBAR -->
                <details class="lm-panel" data-panel="sidebar"<?php echo $open( $s_state ); ?>>
                    <summary><span class="lm-t">Sidebar</span> <?php echo $chip( $s_state ); ?>
                        <span class="lm-sub">Right of the content (phones: above it)<?php
                            echo $s_state === 'on' ? ' · ' . esc_html( monospace_lm_preview( $s_desk ) ) : ''; ?></span></summary>
                    <div class="lm-body">
                        <?php if ( $t['sidebar'] === 'terms' ) : ?>
                            <p>Categories use the <a href="<?php echo esc_url( admin_url( 'admin.php?page=monospace-layout-manager&tab=site' ) ); ?>">Site default</a>’s sidebar (<?php echo $show ? 'shown' : 'hidden'; ?>);
                                a category with its own sidebar (edit the category) shows that instead.</p>
                        <?php else : ?>
                        <?php $mode_row( 'sidebar' ); $custom_open( 'sidebar' ); ?>
                        <input type="hidden" name="sidebar_visibility_field" value="1">
                        <p><label><input type="checkbox" name="show_sidebar" value="1" class="lm-show-sidebar" <?php checked( $show ); ?>> Show the sidebar</label></p>
                        <div class="lm-sidebar-fields"<?php echo $show ? '' : ' hidden'; ?>>
                            <?php $editor( $s_desk, $id . 'sidebar_desktop', 'sidebar_desktop', 8 ); ?>
                            <p class="description">HTML and shortcodes allowed - see <a href="<?php echo esc_url( admin_url( 'admin.php?page=monospace-layout-manager&tab=shortcodes' ) ); ?>">Shortcodes</a>.</p>
                            <label class="lm-mirror"><input type="checkbox" name="mirror_sidebar" value="1" <?php checked( $s_mirror ); ?>> Same on phones &amp; tablets</label>
                            <div class="lm-mobile"<?php echo $s_mirror ? ' hidden' : ''; ?>>
                                <p class="lm-label">Sidebar on phones &amp; tablets <span class="description">(empty = the one above)</span></p>
                                <?php $editor( $s_mobile, $id . 'sidebar_mobile', 'sidebar_mobile', 8 ); ?>
                            </div>
                        </div>
                        <?php $custom_close( 'sidebar' ); ?>
                        <?php endif; ?>
                    </div>
                </details>

                <?php if ( $tab === 'categories' ) monospace_lm_render_term_views(); ?>
            </div>
        </div>
    </div>

    <script>
    // Use the site default / Custom: shows the matching part of the panel
    document.addEventListener('change', function (e) {
        var t = e.target; if (!t.name || !/_mode$/.test(t.name) || t.type !== 'radio') return;
        var area = t.name.replace(/_mode$/, '');
        document.querySelectorAll('[data-area="' + area + '"]').forEach(function (el) {
            if (el.classList.contains('lm-custom')) el.hidden = t.value !== 'custom';
            if (el.classList.contains('lm-inherit')) el.hidden = t.value === 'custom';
        });
        window.dispatchEvent(new Event('resize')); // the editors in a part that was hidden find their size
    });
    </script>
    <?php
    monospace_lm_area_assets();
}

/**
 * Categories tab: "Views & price cards" (2026-10-21, owner: "update the Layout Manager / Categories with these changes") -
 * every category and place in one table: the view it shows (list / grid / detail list / price card), the viewing toolbar, the price
 * card under each artwork, the tab cards open on. The same term meta as each one's edit screen (_monospace_archive_view,
 * _monospace_archive_bar, _ms_price_cards, _ms_price_card_tab) - saved by monospace_save_term_views() (layout-manager.php). Above it: the
 * site-wide parts (the Price card button, the sort menu's Random), with where they're set.
 */
function monospace_lm_render_term_views() {
    $terms = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => false, 'orderby' => 'name' ] );
    $places = get_terms( [ 'taxonomy' => 'post_tag', 'hide_empty' => false, 'orderby' => 'name', 'meta_query' => [ 'relation' => 'OR',
        [ 'key' => '_monospace_header_title', 'compare' => 'EXISTS' ], [ 'key' => '_monospace_archive_view', 'compare' => 'EXISTS' ], [ 'key' => '_monospace_archive_bar', 'compare' => 'EXISTS' ], [ 'key' => '_monospace_share_image', 'compare' => 'EXISTS' ] ] ] );
    $rows = array_merge( is_array( $terms ) ? $terms : [], is_array( $places ) ? $places : [] );
    $tb   = function_exists( 'monospace_archive_toolbar_settings' ) ? monospace_archive_toolbar_settings() : [ 'bar' => true, 'view' => 'detail' ];
    $view_choices = function_exists( 'monospace_archive_view_choices' ) ? monospace_archive_view_choices() : [ '' => 'Same as everywhere' ];
    $bar_choices  = function_exists( 'monospace_archive_bar_choices' ) ? monospace_archive_bar_choices() : [ '' => 'Same as everywhere' ];
    $presets = function_exists( 'monospace_price_card_presets' ) ? monospace_price_card_presets() : [];
    $tabs    = function_exists( 'monospace_price_card_tabs' ) ? monospace_price_card_tabs() : [];
    $site_card = function_exists( 'monospace_price_card_setting' ) ? monospace_price_card_setting( 'feed' ) : 'off';
    $site_tab  = function_exists( 'monospace_price_card_tab_setting' ) ? monospace_price_card_tab_setting() : '';
    $n_cards = 0;
    foreach ( $rows as $t ) if ( get_term_meta( $t->term_id, '_monospace_archive_view', true ) === 'cards' ) $n_cards++;
    $look = admin_url( 'themes.php?page=monospace-look' ); ?>
    <details class="lm-panel" data-panel="views" open>
        <summary><span class="lm-t">Views &amp; price cards</span> <span class="lm-chip is-terms">Per category</span>
            <span class="lm-sub">The view, the viewing toolbar, the price card under each artwork, the tab it opens on<?php echo $n_cards ? ' · ' . (int) $n_cards . ' in price card view' : ''; ?></span></summary>
        <div class="lm-body">
            <ul class="lm-views-site">
                <li><b>Everywhere</b>: toolbar <b><?php echo ! empty( $tb['bar'] ) ? 'on' : 'off'; ?></b>, default view <b><?php echo esc_html( strtolower( $view_choices[ $tb['view'] ] ?? $tb['view'] ) ); ?></b> - “Same as everywhere” below follows these.
                    Set in <a href="<?php echo esc_url( admin_url( 'admin.php?page=monospace-layout-manager&tab=links' ) ); ?>">Links &amp; toolbar → Views &amp; viewing toolbar</a>. A page in <b>price card</b> view never has the toolbar.</li>
                <li><b>Sort menu</b>: newest, oldest, price, size - and <b>Random</b> (a fresh shuffle each time it’s chosen; scrolling on keeps it). Each page can <b>start</b> in any of them (“Starts sorted” below).</li>
                <li><b>Price card view</b> shows each artwork as a standalone price card - its look: <a href="<?php echo esc_url( admin_url( 'themes.php?page=monospace-price-card' ) ); ?>">Appearance &gt; Price card</a>. A visitor’s own choice of view wins over where a page starts.</li>
            </ul>
            <table class="widefat striped lm-views">
                <thead><tr><th>Category / place</th><th>View</th><th>Viewing toolbar</th><th>Starts sorted</th><th>Price card under each artwork</th><th>Opens on</th><th>Share image</th></tr></thead>
                <tbody>
                <?php foreach ( $rows as $t ) :
                    $id   = (int) $t->term_id;
                    $view = (string) get_term_meta( $id, '_monospace_archive_view', true );
                    $card = function_exists( 'monospace_price_card_term_style' ) ? monospace_price_card_term_style( $id ) : '';
                    $otab = (string) get_term_meta( $id, '_ms_price_card_tab', true );
                    $name = $t->taxonomy === 'post_tag' ? esc_html( html_entity_decode( get_term_meta( $id, '_monospace_header_title', true ) ?: $t->name ) ) . ' <span class="description">(place)</span>'
                          : esc_html( ( $t->parent ? '— ' : '' ) . $t->name ); ?>
                    <tr>
                        <td><a href="<?php echo esc_url( get_edit_term_link( $id, $t->taxonomy ) ); ?>"><?php echo $name; // escaped above ?></a>
                            <span class="description">(<?php echo (int) $t->count; ?>)</span>
                            <a class="lm-views-open" href="<?php echo esc_url( get_term_link( $t ) ); ?>" target="_blank" title="Open the page">↗</a>
                            <input type="hidden" name="lm_terms[<?php echo $id; ?>][seen]" value="1"></td>
                        <td><select name="lm_terms[<?php echo $id; ?>][view]">
                            <?php foreach ( $view_choices as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $view, $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                        </select></td>
                        <td><select name="lm_terms[<?php echo $id; ?>][bar]">
                            <?php $tbar = (string) get_term_meta( $id, '_monospace_archive_bar', true );
                            foreach ( $bar_choices as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $tbar, $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                        </select></td>
                        <td><select name="lm_terms[<?php echo $id; ?>][sort]">
                            <?php $tsort = (string) get_term_meta( $id, '_monospace_archive_sort', true ) ?: 'newest';
                            foreach ( defined( 'MONOSPACE_ARCHIVE_SORTS' ) ? MONOSPACE_ARCHIVE_SORTS : [ 'newest' => 'Newest first' ] as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $tsort, $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                        </select></td>
                        <td><select name="lm_terms[<?php echo $id; ?>][cards]">
                            <option value="" <?php selected( $card, '' ); ?>>Site setting (<?php echo esc_html( $site_card === 'off' ? 'off' : ( $presets[ $site_card ] ?? $site_card ) ); ?>)</option>
                            <option value="hide" <?php selected( $card, 'hide' ); ?>>Hide</option>
                            <?php foreach ( $presets as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $card, $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                        </select></td>
                        <td><select name="lm_terms[<?php echo $id; ?>][tab]">
                            <option value="" <?php selected( $otab, '' ); ?>>Site setting (<?php echo esc_html( $tabs[ $site_tab ] ?? 'Original' ); ?>)</option>
                            <?php foreach ( $tabs as $k => $label ) : if ( $k === '' ) continue; // '' = the site setting, above ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $otab, $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                        </select></td>
                        <?php $sid = (int) get_term_meta( $id, '_monospace_share_image', true ); $sthumb = $sid ? (string) wp_get_attachment_image_url( $sid, 'thumbnail' ) : ''; ?>
                        <td class="lm-share-cell"><input type="hidden" name="lm_terms[<?php echo $id; ?>][share]" value="<?php echo $sthumb ? $sid : ''; ?>">
                            <button type="button" class="button-link lm-share-pick" title="Choose the picture shown when this page is shared"><img class="lm-share-thumb" src="<?php echo esc_url( $sthumb ); ?>" alt=""<?php echo $sthumb ? '' : ' hidden'; ?>><span<?php echo $sthumb ? ' hidden' : ''; ?>>Newest artwork - choose…</span></button>
                            <button type="button" class="button-link lm-share-clear" title="Use the newest artwork"<?php echo $sthumb ? '' : ' hidden'; ?>>×</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="description">The same settings as each category’s own edit screen. Places = tags with their own page header.</p>
        </div>
    </details>
    <script>
    jQuery(function ($) {
        var frame, cell;
        $(document).on('click', '.lm-share-pick', function (e) {
            e.preventDefault();
            cell = $(this).closest('.lm-share-cell');
            if (!frame) {
                frame = wp.media({ title: 'Share image', library: { type: 'image' }, button: { text: 'Use this image' }, multiple: false });
                frame.on('select', function () {
                    var a = frame.state().get('selection').first().toJSON(), s = a.sizes || {};
                    cell.find('input').val(a.id);
                    cell.find('.lm-share-thumb').attr('src', (s.thumbnail || a).url).prop('hidden', false);
                    cell.find('.lm-share-pick span').prop('hidden', true);
                    cell.find('.lm-share-clear').prop('hidden', false);
                });
            }
            frame.open();
        });
        $(document).on('click', '.lm-share-clear', function () {
            var c = $(this).closest('.lm-share-cell');
            c.find('input').val('');
            c.find('.lm-share-thumb').prop('hidden', true);
            c.find('.lm-share-pick span').prop('hidden', false);
            $(this).prop('hidden', true);
        });
    });
    </script>
    <style>
        .lm-share-cell { white-space: nowrap; } .lm-share-pick { text-align: left; font-size: 12px; } .lm-share-clear { margin-left: 4px; font-size: 16px; text-decoration: none; }
        .lm-share-thumb { width: 64px; height: 34px; object-fit: cover; border: 1px solid #c3c4c7; border-radius: 3px; vertical-align: middle; } .lm-share-thumb[hidden], .lm-share-pick span[hidden], .lm-share-clear[hidden] { display: none; }
        .lm-views-site { margin: 4px 0 12px; padding-left: 18px; list-style: disc; } .lm-views-site li { margin-bottom: 4px; }
        .lm-views td, .lm-views th { vertical-align: middle; } .lm-views select { max-width: 100%; }
        .lm-views-open { text-decoration: none; margin-left: 4px; }
    </style>
    <?php
}

// The look and behavior (monospace_lm_area_assets()) moved to Core on 2026-10-20 - mu-plugins/monospace-art-core/
// modules/admin/admin-ui.php - so plugins (Prints, Sales Dashboard) can use the same admin UI.

// The share image's picker (WordPress's media window) on Layout Manager > Categories
add_action( 'admin_enqueue_scripts', function () {
    if ( ( $_GET['page'] ?? '' ) === 'monospace-layout-manager' && ( $_GET['tab'] ?? '' ) === 'categories' ) wp_enqueue_media();
} );

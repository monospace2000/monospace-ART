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

/** What each page type has: label, where it is, header (true / 'terms' / false), sidebar (true / 'terms'), notes */
function monospace_lm_area_types() {
    $override = 'Each %s can change its top content and sidebar in its own <b>Sidebar Override</b> box.';
    return [
        'blog-feed'  => [ 'label' => 'Art Feed', 'where' => 'The Art Feed - every post, newest first.',
                          'header' => true, 'location' => true, 'title_help' => 'Leave empty for no header.', 'sidebar' => true ],
        'categories' => [ 'label' => 'Categories', 'where' => 'Category, tag and place pages.',
                          'header' => 'terms', 'sidebar' => 'terms' ],
        'search'     => [ 'label' => 'Search Results', 'where' => 'The search results page.', 'header' => true, 'location' => false,
                          'title_default' => 'Search Results', 'title_help' => '{search_term} = what was searched for, e.g. “Results for {search_term}”.', 'sidebar' => true ],
        '404'        => [ 'label' => '404 Page', 'where' => 'The “page not found” page. Its own text is in the theme’s 404.php.', 'header' => false, 'sidebar' => true ],
        'posts'      => [ 'label' => 'Posts', 'where' => 'Every post - artworks and blog posts.', 'header' => false, 'sidebar' => true, 'note' => sprintf( $override, 'post' ) ],
        'pages'      => [ 'label' => 'Pages', 'where' => 'Every page except the home page.', 'header' => false, 'sidebar' => true, 'note' => sprintf( $override, 'page' ) ],
        'products'   => [ 'label' => 'Products', 'where' => 'WooCommerce pages (shop, product pages - most redirect to their artwork).', 'header' => false, 'sidebar' => true, 'note' => sprintf( $override, 'product' ) ],
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

    // Top content
    $top       = $opt( 'top_content' );
    $top_state = trim( $top ) !== '' ? 'on' : 'empty';

    // Header
    $header_state = null;
    if ( $t['header'] === true ) {
        $h_title    = $opt( 'header_title', $t['title_default'] ?? '' );
        $h_desk     = $opt( 'header_desc_desktop' );
        $h_mobile   = $opt( 'header_desc_mobile' );
        $h_mirror   = $opt( 'mirror_header', '1' ) === '1';
        $h_location = ! empty( $t['location'] ) ? $opt( 'header_location', 'top' ) : 'top';
        $header_state = $h_location === 'none' ? 'off' : ( trim( $h_title . $h_desk ) !== '' ? 'on' : 'empty' );
    } elseif ( $t['header'] === 'terms' ) {
        $h_location   = 'top';
        $header_state = 'terms';
    }

    // Sidebar
    $show = monospace_sidebar_default_visible( $tab );
    if ( $t['sidebar'] === 'terms' ) {
        $s_state = $show ? 'terms' : 'off';
    } else {
        $s_desk   = $opt( 'sidebar_desktop' );
        $s_mobile = $opt( 'sidebar_mobile' );
        $s_mirror = $opt( 'mirror_sidebar', '1' ) === '1';
        $s_state  = ! $show ? 'off' : ( trim( $s_desk ) !== '' ? 'on' : 'empty' );
    }

    $words = [ 'on' => 'In use', 'empty' => 'Empty', 'off' => 'Not shown', 'terms' => 'Per category' ];
    $chip  = fn( $state ) => '<span class="lm-chip is-' . esc_attr( $state ) . '">' . esc_html( $words[ $state ] ) . '</span>';
    $open  = fn( $state ) => in_array( $state, [ 'on', 'terms' ], true ) ? ' open' : '';
    $editor = function ( $content, $editor_id, $name, $rows ) {
        wp_editor( $content, $editor_id, [ 'textarea_name' => $name, 'media_buttons' => true, 'textarea_rows' => $rows, 'teeny' => false, 'quicktags' => true ] );
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
                <button type="button" class="lm-r lm-area is-<?php echo esc_attr( $top_state ); ?>" data-panel="top">Top content</button>
                <div class="lm-cols">
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
                <figcaption>Phones: the sidebar shows above the content.<br>
                    <span class="lm-key is-on"></span> in use <span class="lm-key is-empty"></span> empty <span class="lm-key is-off"></span> not shown</figcaption>
            </figure>

            <div class="lm-panels">
                <!-- TOP CONTENT -->
                <details class="lm-panel" data-panel="top"<?php echo $open( $top_state ); ?>>
                    <summary><span class="lm-t">Top content</span> <?php echo $chip( $top_state ); ?>
                        <span class="lm-sub">Full width, under the logo and tagline<?php echo $top_state === 'on' ? ' · ' . esc_html( monospace_lm_preview( $top ) ) : ''; ?></span></summary>
                    <div class="lm-body">
                        <?php $editor( $top, $id . 'top_content', 'top_content', 6 ); ?>
                        <p class="description">Leave empty for none. HTML and shortcodes allowed.</p>
                    </div>
                </details>

                <?php if ( $t['header'] === true ) : ?>
                <!-- HEADER -->
                <details class="lm-panel" data-panel="header"<?php echo $open( $header_state ); ?>>
                    <summary><span class="lm-t">Header</span> <?php echo $chip( $header_state ); ?>
                        <span class="lm-sub"><?php echo esc_html( [ 'top' => 'Above the content', 'sidebar' => 'In the sidebar ([archive_header])', 'none' => 'Not shown' ][ $h_location ] ?? 'Above the content' ); ?><?php
                            echo $header_state === 'on' ? ' · ' . esc_html( monospace_lm_preview( ( $h_title !== '' ? $h_title . ' - ' : '' ) . $h_desk ) ) : ''; ?></span></summary>
                    <div class="lm-body">
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
                    </div>
                </details>
                <?php elseif ( $t['header'] === 'terms' ) : ?>
                <details class="lm-panel" data-panel="header" open>
                    <summary><span class="lm-t">Header</span> <?php echo $chip( 'terms' ); ?> <span class="lm-sub">Above the content, or in the sidebar - set on each category</span></summary>
                    <div class="lm-body">
                        <p>Each category, tag or place has its own header title, description (desktop / phones), where it goes, and its list / grid view:
                            <b>Posts → Categories</b> (or Tags) → edit one.</p>
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
                        <input type="hidden" name="sidebar_visibility_field" value="1">
                        <p><label><input type="checkbox" name="show_sidebar" value="1" class="lm-show-sidebar" <?php checked( $show ); ?>> Show the sidebar</label></p>
                        <?php if ( $t['sidebar'] === 'terms' ) : ?>
                            <p class="description">What's in it: the category's own sidebar (edit the category), else the Art Feed's.</p>
                        <?php else : ?>
                        <div class="lm-sidebar-fields"<?php echo $show ? '' : ' hidden'; ?>>
                            <?php $editor( $s_desk, $id . 'sidebar_desktop', 'sidebar_desktop', 8 ); ?>
                            <p class="description">HTML and shortcodes allowed - see <a href="<?php echo esc_url( admin_url( 'admin.php?page=monospace-layout-manager&tab=shortcodes' ) ); ?>">Shortcodes</a>.</p>
                            <label class="lm-mirror"><input type="checkbox" name="mirror_sidebar" value="1" <?php checked( $s_mirror ); ?>> Same on phones &amp; tablets</label>
                            <div class="lm-mobile"<?php echo $s_mirror ? ' hidden' : ''; ?>>
                                <p class="lm-label">Sidebar on phones &amp; tablets <span class="description">(empty = the one above)</span></p>
                                <?php $editor( $s_mobile, $id . 'sidebar_mobile', 'sidebar_mobile', 8 ); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </details>
            </div>
        </div>
    </div>

    <?php
    monospace_lm_area_assets();
}

// The look and behaviour (monospace_lm_area_assets()) moved to Core on 2026-10-20 - mu-plugins/monospace-art-core/
// modules/admin/admin-ui.php - so plugins (Prints, Sales Dashboard) can use the same admin UI.

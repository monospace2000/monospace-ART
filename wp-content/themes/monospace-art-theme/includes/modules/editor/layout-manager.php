<?php
/**
 * Layout Manager Admin Interface
 *
 * Provides admin UI for configuring default headers and sidebars across the site.
 * Settings are saved as WordPress options and retrieved by feed-headers.php and sidebar-override.php.
 *
 * Three sections (2026-10-19, owner's wish), the same tab row on each - monospace_layout_manager_nav():
 * - Home Page            - the home page boxes (home-boxes-admin.php, its own page: monospace-home-boxes)
 * - Headers & Sidebars   - this page, a second row for the page type (tab = blog-feed | categories |
 *                          search | 404 | posts | pages | products, plus the shortcodes reference)
 * - Appearance & Effects - this page, tab = effects
 * Clicking "Layout Manager" (no tab) opens Home Page; the admin menu lists the same three.
 *
 * Headers & Sidebars (layout-manager-areas.php draws each page type: a map of the page and a panel per area):
 * - Art Feed (blog-feed) - Home page header/sidebar
 * - Categories - Per-category customization (done in category edit screen)
 * - Search - Search results header/sidebar
 * - Posts/Pages/Products - Default sidebars for each post type
 * - Appearance & Effects - drawn by the design modules (includes/modules/design/, registry in
 *   design-modules.php): Appearance, Scroll effect
 *
 * Mirror Checkbox:
 * When checked: Desktop content displays on all devices
 * When unchecked: Separate mobile content displays below breakpoint (controlled by CSS)
 *
 * Related Files:
 * - feed-headers.php - Retrieves and displays feed/category headers
 * - sidebar-override.php - Retrieves sidebar content for all contexts
 * - sidebar.php - Theme template that displays sidebar content
 * - _layout.scss - Responsive CSS for .content-desktop/.content-mobile visibility
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * ADMIN MENU
 * ============================================================ */

add_action( 'admin_menu', function () {
    add_menu_page(
        'Layout Manager',
        'Layout Manager',
        'manage_options',
        'monospace-layout-manager',
        'monospace_layout_manager_page',
        'dashicons-layout',
        30
    );
});

/** The three sections: key => [ label, admin URL ] */
function monospace_layout_manager_sections() {
    return [
        'home'    => [ 'Home Page',            'admin.php?page=monospace-home-boxes' ],
        'headers' => [ 'Headers & Sidebars',   'admin.php?page=monospace-layout-manager&tab=blog-feed' ],
        'effects' => [ 'Appearance & Effects', 'admin.php?page=monospace-layout-manager&tab=effects' ],
    ];
}

/** Headers & Sidebars: the page types (tab slug => label) */
function monospace_layout_manager_page_types() {
    return [
        'blog-feed'  => 'Art Feed',
        'categories' => 'Categories',
        'search'     => 'Search Results',
        '404'        => '404 Page',
        'posts'      => 'Posts',
        'pages'      => 'Pages',
        'products'   => 'Products',
        'shortcodes' => 'Shortcodes (reference)',
    ];
}

// The admin menu under Layout Manager = the three sections, in order (the Home Page item is added by
// home-boxes-admin.php at priority 20; WordPress's own first item becomes Headers & Sidebars)
add_action( 'admin_menu', function () {
    global $submenu;
    if ( empty( $submenu['monospace-layout-manager'] ) ) return;
    $items = [];
    foreach ( $submenu['monospace-layout-manager'] as $item ) $items[ $item[2] ] = $item;
    $sections = monospace_layout_manager_sections();
    $menu = [];
    if ( isset( $items['monospace-home-boxes'] ) ) $menu[] = $items['monospace-home-boxes'];
    foreach ( [ 'headers', 'effects' ] as $key ) {
        $menu[] = [ $sections[ $key ][0], 'manage_options', $sections[ $key ][1], $sections[ $key ][0] ];
    }
    $submenu['monospace-layout-manager'] = $menu;
}, 30 );

// The menu item to highlight on this page
add_filter( 'submenu_file', function ( $file ) {
    if ( ( $_GET['page'] ?? '' ) !== 'monospace-layout-manager' ) return $file;
    $sections = monospace_layout_manager_sections();
    return ( $_GET['tab'] ?? '' ) === 'effects' ? $sections['effects'][1] : $sections['headers'][1];
} );

// "Layout Manager" itself (no tab) opens the first section, Home Page
add_action( 'admin_init', function () {
    if ( ( $_GET['page'] ?? '' ) === 'monospace-layout-manager' && empty( $_GET['tab'] ) && $_SERVER['REQUEST_METHOD'] === 'GET' ) {
        wp_safe_redirect( admin_url( monospace_layout_manager_sections()['home'][1] ) );
        exit;
    }
} );

/**
 * The page title and the section tabs - on Layout Manager and on the Home Page editor.
 * $section: home | headers | effects; $tab: the page type, on Headers & Sidebars.
 */
function monospace_layout_manager_nav( $section, $tab = '' ) {
    $intros = [
        'home'    => '',
        'headers' => 'The header above the content and the sidebar, for each kind of page. A post, page or product can override them in its Sidebar Override box.',
        'effects' => 'How the whole site looks and moves - background, buttons, effects, a few links. Changes show on the site as soon as you save.',
    ];
    echo '<h1>Layout Manager</h1>';
    echo '<nav class="nav-tab-wrapper wp-clearfix" style="margin-bottom:12px">';
    foreach ( monospace_layout_manager_sections() as $key => [ $label, $url ] ) {
        printf( '<a href="%s" class="nav-tab%s">%s</a>', esc_url( admin_url( $url ) ), $key === $section ? ' nav-tab-active' : '', esc_html( $label ) );
    }
    echo '</nav>';
    if ( $section === 'headers' ) {
        $types = monospace_layout_manager_page_types();
        $links = [];
        foreach ( $types as $slug => $label ) {
            $links[] = sprintf( '<li><a href="%s"%s>%s</a></li>', esc_url( admin_url( 'admin.php?page=monospace-layout-manager&tab=' . $slug ) ), $slug === $tab ? ' class="current" aria-current="page"' : '', esc_html( $label ) );
        }
        echo '<ul class="subsubsub" style="float:none;margin:0 0 8px">' . implode( ' | ', $links ) . '</ul>';
    }
    if ( ! empty( $intros[ $section ] ) ) echo '<p class="description" style="max-width:760px">' . esc_html( $intros[ $section ] ) . '</p>';
}

/* ============================================================
 * SETTINGS PAGE WITH TABS
 * ============================================================ */

function monospace_layout_manager_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Get active tab
    $active_tab = sanitize_key( $_GET['tab'] ?? '' );
    if ( $active_tab !== 'effects' && ! isset( monospace_layout_manager_page_types()[ $active_tab ] ) ) $active_tab = 'blog-feed';

    // Handle form submission
    if ( isset( $_POST['monospace_layout_manager_submit'] ) ) {
        check_admin_referer( 'monospace_layout_manager_settings' );
        monospace_save_layout_settings( $active_tab );
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    ?>
    <div class="wrap">
        <?php monospace_layout_manager_nav( $active_tab === 'effects' ? 'effects' : 'headers', $active_tab ); ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'monospace_layout_manager_settings' ); ?>

            <?php
            if ( $active_tab === 'effects' ) {
                monospace_render_design_modules();   // includes/modules/design/
            } elseif ( $active_tab === 'shortcodes' ) {
                monospace_render_shortcodes_reference();
            } else {
                // A page type: the map of its areas and a panel for each (layout-manager-areas.php)
                monospace_lm_render_areas( $active_tab );
            }
            ?>

            <?php if ( $active_tab !== 'shortcodes' ): ?>
            <p class="submit">
                <input type="submit" name="monospace_layout_manager_submit" class="button button-primary" value="Save Settings" />
            </p>
            <?php endif; ?>
        </form>
    </div>
    <?php
}

/* ============================================================
 * SIDEBAR VISIBILITY (every tab with a sidebar)
 * Default per page type; posts, pages and products can override it in their Sidebar Override
 * box. Read by monospace_sidebar_visible() (sidebar-override.php).
 * ============================================================ */

function monospace_sidebar_tabs() {
    return [
        'blog-feed'  => 'the blog feed',
        'categories' => 'category and archive pages',
        'search'     => 'search results',
        '404'        => 'the 404 page',
        'posts'      => 'single posts',
        'pages'      => 'pages',
        'products'   => 'product pages',
    ];
}

// The sidebar visibility and top content fields are panels of layout-manager-areas.php (2026-10-20)

/* ============================================================
 * TAB: SHORTCODES REFERENCE
 * ============================================================ */

function monospace_render_shortcodes_reference() {
    ?>
    <h3>Available Shortcodes</h3>
    <p>Use these shortcodes in your sidebar content areas throughout the Layout Manager.</p>

    <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin: 20px 0;">

        <h4>🔍 Search Form</h4>
        <code>[search_form]</code>
        <p>Displays a search form.</p>
        <p><strong>Options:</strong></p>
        <ul>
            <li><code>[search_form placeholder="Search posts..." button_text="Go"]</code></li>
        </ul>
        <hr>

        <h4>🏷️ Tag Cloud</h4>
        <code>[tag_cloud]</code>
        <p>Displays a tag cloud.</p>
        <p><strong>Options:</strong></p>
        <ul>
            <li><code>[tag_cloud number="30"]</code> - Show 30 tags</li>
            <li><code>[tag_cloud taxonomy="category"]</code> - Show categories instead</li>
            <li><code>[tag_cloud smallest="10" largest="20"]</code> - Size range in pt</li>
        </ul>
        <hr>

        <h4>📝 Recent Posts</h4>
        <code>[recent_posts]</code>
        <p>Displays a list of recent posts.</p>
        <p><strong>Options:</strong></p>
        <ul>
            <li><code>[recent_posts number="10"]</code> - Show 10 posts</li>
            <li><code>[recent_posts show_date="true"]</code> - Include dates</li>
            <li><code>[recent_posts show_excerpt="true" excerpt_length="15"]</code> - Show excerpts</li>
        </ul>
        <hr>

        <h4>📁 Category List</h4>
        <code>[category_list]</code>
        <p>Displays a list of categories.</p>
        <p><strong>Options:</strong></p>
        <ul>
            <li><code>[category_list show_count="true"]</code> - Show post counts</li>
            <li><code>[category_list hide_empty="false"]</code> - Show empty categories</li>
        </ul>
        <hr>

        <h4>🗂️ Archives List</h4>
        <code>[archives_list]</code>
        <p>Displays post archives by month.</p>
        <p><strong>Options:</strong></p>
        <ul>
            <li><code>[archives_list type="yearly"]</code> - Group by year</li>
            <li><code>[archives_list limit="12"]</code> - Show only last 12</li>
            <li><code>[archives_list show_count="true"]</code> - Show post counts</li>
        </ul>
        <hr>

        <h4>🧭 Navigation Menu</h4>
        <code>[nav_menu menu="menu-slug"]</code>
        <p>Displays a WordPress menu.</p>
        <p><strong>Options:</strong></p>
        <ul>
            <li><code>[nav_menu menu="primary"]</code> - Show primary menu</li>
            <li><code>[nav_menu menu="footer-menu" menu_class="custom-menu"]</code> - Custom CSS class</li>
        </ul>
        <hr>

        <h4>📰 Archive Header</h4>
        <code>[archive_header]</code>
        <p>Displays the feed header (only works on archive pages when set to "In sidebar" mode).</p>

    </div>

    <div style="background: #f0f0f1; padding: 15px; border-left: 4px solid #72aee6; margin: 20px 0;">
        <h4>💡 Pro Tips</h4>
        <ul>
            <li>Combine multiple shortcodes in the same sidebar</li>
            <li>Add HTML around shortcodes for custom styling</li>
            <li>Use headings like <code>&lt;h3&gt;Recent Posts&lt;/h3&gt;</code> before shortcodes</li>
            <li>Shortcodes work in both desktop and mobile/tablet content areas</li>
        </ul>
    </div>
    <?php
}

/* ============================================================
 * PAGE TYPES (Art Feed, Categories, Search, 404, Posts, Pages, Products)
 * Drawn by monospace_lm_render_areas() (layout-manager-areas.php, 2026-10-20): a map of the page and a panel
 * per area - top content, header, sidebar - with the same fields and names as before, saved below.
 * ============================================================ */

/* ============================================================
 * SAVE SETTINGS
 * ============================================================ */
function monospace_save_layout_settings( $tab ) {
    // Appearance & Effects tab: the design modules save their own site-wide options (design-modules.php)
    if ( $tab === 'effects' ) {
        monospace_save_design_modules();
        return;
    }

    $prefix = 'monospace_' . str_replace( '-', '_', $tab ) . '_';

    // Top content (every page-type tab)
    if ( isset( $_POST['top_content'] ) ) {
        update_option( $prefix . 'top_content', stripslashes( wp_kses_post( $_POST['top_content'] ) ) );
    }

    // Sidebar visibility (only when the tab showed the field)
    if ( isset( $_POST['sidebar_visibility_field'] ) ) {
        update_option( $prefix . 'show_sidebar', isset( $_POST['show_sidebar'] ) ? '1' : '0' );
    }
    // Categories has nothing else to save
    if ( $tab === 'categories' ) {
        return;
    }


    // Save header settings (if applicable)
    if ( isset( $_POST['header_title'] ) ) {
        update_option( $prefix . 'header_title', sanitize_text_field( $_POST['header_title'] ) );
    }
    if ( isset( $_POST['header_desc_desktop'] ) ) {
        update_option( $prefix . 'header_desc_desktop', stripslashes( wp_kses_post( $_POST['header_desc_desktop'] ) ) );
    }
    if ( isset( $_POST['header_desc_mobile'] ) ) {
        update_option( $prefix . 'header_desc_mobile', stripslashes( wp_kses_post( $_POST['header_desc_mobile'] ) ) );
    }
    if ( isset( $_POST['header_location'] ) ) {
        update_option( $prefix . 'header_location', sanitize_text_field( $_POST['header_location'] ) );
    }

    // Save mirror checkboxes
    $mirror_header_value = isset( $_POST['mirror_header'] ) ? '1' : '0';
    $mirror_sidebar_value = isset( $_POST['mirror_sidebar'] ) ? '1' : '0';

    update_option( $prefix . 'mirror_header', $mirror_header_value );
    update_option( $prefix . 'mirror_sidebar', $mirror_sidebar_value );


    // Save sidebar settings
    if ( isset( $_POST['sidebar_desktop'] ) ) {
        update_option( $prefix . 'sidebar_desktop', stripslashes( wp_kses_post( $_POST['sidebar_desktop'] ) ) );
    }
    if ( isset( $_POST['sidebar_mobile'] ) ) {
        update_option( $prefix . 'sidebar_mobile', stripslashes( wp_kses_post( $_POST['sidebar_mobile'] ) ) );
    }
}
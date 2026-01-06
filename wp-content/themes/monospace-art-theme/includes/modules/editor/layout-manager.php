<?php
/**
 * Layout Manager Admin Interface
 *
 * Provides admin UI for configuring default headers and sidebars across the site.
 * Settings are saved as WordPress options and retrieved by feed-headers.php and sidebar-override.php.
 *
 * Configuration Tabs:
 * - Blog Feed - Home page header/sidebar
 * - Categories - Per-category customization (done in category edit screen)
 * - Search - Search results header/sidebar
 * - Posts/Pages/Products - Default sidebars for each post type
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

/* ============================================================
 * SETTINGS PAGE WITH TABS
 * ============================================================ */

function monospace_layout_manager_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Get active tab
    $active_tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'blog-feed';

    // Handle form submission
    if ( isset( $_POST['monospace_layout_manager_submit'] ) ) {
        check_admin_referer( 'monospace_layout_manager_settings' );
        monospace_save_layout_settings( $active_tab );
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>Layout Manager</h1>
        <p>Configure default headers and sidebars for different content types across your site.</p>

        <h2 class="nav-tab-wrapper">
            <a href="?page=monospace-layout-manager&tab=blog-feed" class="nav-tab <?php echo $active_tab === 'blog-feed' ? 'nav-tab-active' : ''; ?>">Blog Feed</a>
            <a href="?page=monospace-layout-manager&tab=categories" class="nav-tab <?php echo $active_tab === 'categories' ? 'nav-tab-active' : ''; ?>">Categories</a>
            <a href="?page=monospace-layout-manager&tab=search" class="nav-tab <?php echo $active_tab === 'search' ? 'nav-tab-active' : ''; ?>">Search Results</a>
            <a href="?page=monospace-layout-manager&tab=posts" class="nav-tab <?php echo $active_tab === 'posts' ? 'nav-tab-active' : ''; ?>">Posts</a>
            <a href="?page=monospace-layout-manager&tab=pages" class="nav-tab <?php echo $active_tab === 'pages' ? 'nav-tab-active' : ''; ?>">Pages</a>
            <a href="?page=monospace-layout-manager&tab=products" class="nav-tab <?php echo $active_tab === 'products' ? 'nav-tab-active' : ''; ?>">Products</a>
            <a href="?page=monospace-layout-manager&tab=shortcodes" class="nav-tab <?php echo $active_tab === 'shortcodes' ? 'nav-tab-active' : ''; ?>">📖 Shortcodes</a>
        </h2>

        <form method="post" action="">
            <?php wp_nonce_field( 'monospace_layout_manager_settings' ); ?>

            <?php
            switch ( $active_tab ) {
                case 'blog-feed':
                    monospace_render_blog_feed_settings();
                    break;
                case 'categories':
                    monospace_render_categories_settings();
                    break;
                case 'search':
                    monospace_render_search_settings();
                    break;
                case 'posts':
                    monospace_render_posts_settings();
                    break;
                case 'pages':
                    monospace_render_pages_settings();
                    break;
                case 'products':
                    monospace_render_products_settings();
                    break;
                case 'shortcodes':
                    monospace_render_shortcodes_reference();
                    break;
            }
            ?>

            <?php if ( $active_tab !== 'shortcodes' && $active_tab !== 'categories' ): ?>
            <p class="submit">
                <input type="submit" name="monospace_layout_manager_submit" class="button button-primary" value="Save Settings" />
            </p>
            <?php endif; ?>
        </form>
    </div>
    <?php
}

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
 * TAB: BLOG FEED
 * ============================================================ */

function monospace_render_blog_feed_settings() {
    $header_title = get_option( 'monospace_blog_feed_header_title', '' );
    $header_desc_desktop = get_option( 'monospace_blog_feed_header_desc_desktop', '' );
    $header_desc_mobile = get_option( 'monospace_blog_feed_header_desc_mobile', '' );
    $header_location = get_option( 'monospace_blog_feed_header_location', 'top' );
    $sidebar_desktop = get_option( 'monospace_blog_feed_sidebar_desktop', '' );
    $sidebar_mobile = get_option( 'monospace_blog_feed_sidebar_mobile', '' );

    // Mirror checkboxes - default to checked (1) if not set
    $mirror_header = get_option( 'monospace_blog_feed_mirror_header', '1' );
    $mirror_sidebar = get_option( 'monospace_blog_feed_mirror_sidebar', '1' );
    ?>

    <h3>Blog Feed (Home Page)</h3>
    <p>Configure the header and sidebar that appears on your main blog feed.</p>

    <table class="form-table">
        <!-- HEADER SECTION -->
        <tr>
            <th colspan="2"><h4 style="margin: 1em 0 0.5em;">Feed Header</h4></th>
        </tr>
        <tr>
            <th scope="row"><label for="header-title">Header Title</label></th>
            <td>
                <input type="text" name="header_title" id="header-title" value="<?php echo esc_attr( $header_title ); ?>" class="regular-text" />
                <p class="description">Leave empty to hide the header.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="header-desc-desktop">Header Description (Desktop)</label></th>
            <td>
                <?php
                wp_editor( $header_desc_desktop, 'header_desc_desktop', [
                    'textarea_name' => 'header_desc_desktop',
                    'media_buttons' => true,
                    'textarea_rows' => 6,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">HTML and shortcodes allowed.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="header-desc-mobile">Header Description (Mobile/Tablet)</label></th>
            <td>
                <?php
                wp_editor( $header_desc_mobile, 'header_desc_mobile', [
                    'textarea_name' => 'header_desc_mobile',
                    'media_buttons' => true,
                    'textarea_rows' => 6,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">If empty, desktop version will be used. HTML and shortcodes allowed.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"></th>
            <td>
                <label>
                    <input type="checkbox" name="mirror_header" value="1" <?php checked( $mirror_header, '1' ); ?> />
                    Mirror desktop content to mobile (ignore mobile field above)
                </label>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="header-location">Header Location</label></th>
            <td>
                <select name="header_location" id="header-location">
                    <option value="top" <?php selected( $header_location, 'top' ); ?>>Above content</option>
                    <option value="sidebar" <?php selected( $header_location, 'sidebar' ); ?>>In sidebar (use [archive_header] shortcode)</option>
                    <option value="none" <?php selected( $header_location, 'none' ); ?>>Don't display</option>
                </select>
            </td>
        </tr>

        <!-- SIDEBAR SECTION -->
        <tr>
            <th colspan="2"><h4 style="margin: 1em 0 0.5em;">Feed Sidebar</h4></th>
        </tr>
        <tr>
            <th scope="row"><label for="sidebar-desktop">Sidebar Content (Desktop)</label></th>
            <td>
                <?php
                wp_editor( $sidebar_desktop, 'sidebar_desktop', [
                    'textarea_name' => 'sidebar_desktop',
                    'media_buttons' => true,
                    'textarea_rows' => 10,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">Custom sidebar content. HTML and shortcodes allowed. See Shortcodes tab for available options.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="sidebar-mobile">Sidebar Content (Mobile/Tablet)</label></th>
            <td>
                <?php
                wp_editor( $sidebar_mobile, 'sidebar_mobile', [
                    'textarea_name' => 'sidebar_mobile',
                    'media_buttons' => true,
                    'textarea_rows' => 10,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">If empty, desktop version will be used. HTML and shortcodes allowed.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"></th>
            <td>
                <label>
                    <input type="checkbox" name="mirror_sidebar" value="1" <?php checked( $mirror_sidebar, '1' ); ?> />
                    Mirror desktop content to mobile (ignore mobile field above)
                </label>
            </td>
        </tr>
    </table>

    <p style="background: #f0f0f1; padding: 12px; border-left: 4px solid #72aee6;">
        <strong>Tip:</strong> Check the Shortcodes tab to see available shortcodes for search, tags, recent posts, and menus.
    </p>
    <?php
}

/* ============================================================
 * TAB: CATEGORIES
 * ============================================================ */

function monospace_render_categories_settings() {
    ?>
    <h3>Categories & Archives</h3>
    <p>Category-specific headers are configured individually when editing each category. Sidebars use the same content as the blog feed.</p>

    <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin-top: 20px;">
        <h4>How to Customize Categories</h4>
        <ol>
            <li>Go to <strong>Posts → Categories</strong></li>
            <li>Click on a category to edit it</li>
            <li>Scroll down to find:
                <ul>
                    <li><strong>Archive Header Title</strong></li>
                    <li><strong>Desktop/Mobile Header Descriptions</strong></li>
                    <li><strong>Header Location</strong> (above content or in sidebar)</li>
                </ul>
            </li>
            <li>Save the category</li>
        </ol>

        <p><a href="<?php echo admin_url( 'edit-tags.php?taxonomy=category' ); ?>" class="button button-secondary">Edit Categories</a></p>
    </div>

    <hr style="margin: 30px 0;">

    <h4>Category Sidebars</h4>
    <p>Category archive pages use the same sidebar content as the <strong>Blog Feed</strong> tab. To customize category sidebars, edit the Blog Feed sidebar settings.</p>
    <?php
}

/* ============================================================
 * TAB: SEARCH RESULTS
 * ============================================================ */

function monospace_render_search_settings() {
    $header_title = get_option( 'monospace_search_header_title', 'Search Results' );
    $header_desc_desktop = get_option( 'monospace_search_header_desc_desktop', '' );
    $header_desc_mobile = get_option( 'monospace_search_header_desc_mobile', '' );
    $sidebar_desktop = get_option( 'monospace_search_sidebar_desktop', '' );
    $sidebar_mobile = get_option( 'monospace_search_sidebar_mobile', '' );

    // Mirror checkboxes - default to checked (1) if not set
    $mirror_header = get_option( 'monospace_search_mirror_header', '1' );
    $mirror_sidebar = get_option( 'monospace_search_mirror_sidebar', '1' );
    ?>

    <h3>Search Results</h3>
    <p>Configure the header and sidebar for search results pages.</p>

    <table class="form-table">
        <!-- HEADER SECTION -->
        <tr>
            <th colspan="2"><h4 style="margin: 1em 0 0.5em;">Search Header</h4></th>
        </tr>
        <tr>
            <th scope="row"><label for="search-header-title">Header Title</label></th>
            <td>
                <input type="text" name="header_title" id="search-header-title" value="<?php echo esc_attr( $header_title ); ?>" class="regular-text" />
                <p class="description">Use {search_term} to display the search query. Example: "Results for {search_term}"</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="search-header-desc-desktop">Header Description (Desktop)</label></th>
            <td>
                <?php
                wp_editor( $header_desc_desktop, 'search_header_desc_desktop', [
                    'textarea_name' => 'header_desc_desktop',
                    'media_buttons' => true,
                    'textarea_rows' => 6,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">HTML and shortcodes allowed.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="search-header-desc-mobile">Header Description (Mobile/Tablet)</label></th>
            <td>
                <?php wp_editor( $header_desc_mobile, "search_header_desc_mobile", ["textarea_name" => "header_desc_mobile", "media_buttons" => true, "textarea_rows" => 6, "teeny" => false, "quicktags" => true]); ?>
                <p class="description">If empty, desktop version will be used. HTML and shortcodes allowed.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"></th>
            <td>
                <label>
                    <input type="checkbox" name="mirror_header" value="1" <?php checked( $mirror_header, '1' ); ?> />
                    Mirror desktop content to mobile (ignore mobile field above)
                </label>
            </td>
        </tr>

        <!-- SIDEBAR SECTION -->
        <tr>
            <th colspan="2"><h4 style="margin: 1em 0 0.5em;">Search Sidebar</h4></th>
        </tr>
        <tr>
            <th scope="row"><label for="search-sidebar-desktop">Sidebar Content (Desktop)</label></th>
            <td>
                <?php
                wp_editor( $sidebar_desktop, 'search_sidebar_desktop', [
                    'textarea_name' => 'sidebar_desktop',
                    'media_buttons' => true,
                    'textarea_rows' => 10,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">Custom sidebar content. HTML and shortcodes allowed.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="search-sidebar-mobile">Sidebar Content (Mobile/Tablet)</label></th>
            <td>
                <?php
                wp_editor( $sidebar_mobile, 'search_sidebar_mobile', [
                    'textarea_name' => 'sidebar_mobile',
                    'media_buttons' => true,
                    'textarea_rows' => 10,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">If empty, desktop version will be used. HTML and shortcodes allowed.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"></th>
            <td>
                <label>
                    <input type="checkbox" name="mirror_sidebar" value="1" <?php checked( $mirror_sidebar, '1' ); ?> />
                    Mirror desktop content to mobile (ignore mobile field above)
                </label>
            </td>
        </tr>
    </table>
    <?php
}

/* ============================================================
 * TAB: POSTS
 * ============================================================ */

function monospace_render_posts_settings() {
    $sidebar_desktop = get_option( 'monospace_posts_sidebar_desktop', '' );
    $sidebar_mobile = get_option( 'monospace_posts_sidebar_mobile', '' );

    // Mirror checkbox - default to checked (1) if not set
    $mirror_sidebar = get_option( 'monospace_posts_mirror_sidebar', '1' );
    ?>

    <h3>Single Posts</h3>
    <p>Default sidebar content for all blog posts. Individual posts can override this in the Sidebar Override meta box.</p>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="posts-sidebar-desktop">Sidebar Content (Desktop)</label></th>
            <td>
                <?php
                wp_editor( $sidebar_desktop, 'posts_sidebar_desktop', [
                    'textarea_name' => 'sidebar_desktop',
                    'media_buttons' => true,
                    'textarea_rows' => 15,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">Default sidebar for all posts. HTML and shortcodes allowed.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="posts-sidebar-mobile">Sidebar Content (Mobile/Tablet)</label></th>
            <td>
                <?php
                wp_editor( $sidebar_mobile, 'posts_sidebar_mobile', [
                    'textarea_name' => 'sidebar_mobile',
                    'media_buttons' => true,
                    'textarea_rows' => 15,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">If empty, desktop version will be used. HTML and shortcodes allowed.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"></th>
            <td>
                <label>
                    <input type="checkbox" name="mirror_sidebar" value="1" <?php checked( $mirror_sidebar, '1' ); ?> />
                    Mirror desktop content to mobile (ignore mobile field above)
                </label>
            </td>
        </tr>
    </table>

    <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin-top: 20px;">
        <h4>Priority Order</h4>
        <ol>
            <li><strong>Post-specific content</strong> (set in Sidebar Override meta box) – highest priority</li>
            <li><strong>These default settings</strong> (if no override enabled)</li>
        </ol>
    </div>
    <?php
}

/* ============================================================
 * TAB: PAGES
 * ============================================================ */

function monospace_render_pages_settings() {
    $sidebar_desktop = get_option( 'monospace_pages_sidebar_desktop', '' );
    $sidebar_mobile = get_option( 'monospace_pages_sidebar_mobile', '' );

    // Mirror checkbox - default to checked (1) if not set
    $mirror_sidebar = get_option( 'monospace_pages_mirror_sidebar', '1' );
    ?>

    <h3>Pages</h3>
    <p>Default sidebar content for all pages. Individual pages can override this in the Sidebar Override meta box.</p>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="pages-sidebar-desktop">Sidebar Content (Desktop)</label></th>
            <td>
                <?php
                wp_editor( $sidebar_desktop, 'pages_sidebar_desktop', [
                    'textarea_name' => 'sidebar_desktop',
                    'media_buttons' => true,
                    'textarea_rows' => 15,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">Default sidebar for all pages. HTML and shortcodes allowed.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="pages-sidebar-mobile">Sidebar Content (Mobile/Tablet)</label></th>
            <td>
                <?php
                wp_editor( $sidebar_mobile, 'pages_sidebar_mobile', [
                    'textarea_name' => 'sidebar_mobile',
                    'media_buttons' => true,
                    'textarea_rows' => 15,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">If empty, desktop version will be used. HTML and shortcodes allowed.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"></th>
            <td>
                <label>
                    <input type="checkbox" name="mirror_sidebar" value="1" <?php checked( $mirror_sidebar, '1' ); ?> />
                    Mirror desktop content to mobile (ignore mobile field above)
                </label>
            </td>
        </tr>
    </table>

    <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin-top: 20px;">
        <h4>Priority Order</h4>
        <ol>
            <li><strong>Page-specific content</strong> (set in Sidebar Override meta box) – highest priority</li>
            <li><strong>These default settings</strong> (if no override enabled)</li>
        </ol>
    </div>
    <?php
}

/* ============================================================
 * TAB: PRODUCTS
 * ============================================================ */

function monospace_render_products_settings() {
    $sidebar_desktop = get_option( 'monospace_products_sidebar_desktop', '' );
    $sidebar_mobile = get_option( 'monospace_products_sidebar_mobile', '' );

    // Mirror checkbox - default to checked (1) if not set
    $mirror_sidebar = get_option( 'monospace_products_mirror_sidebar', '1' );
    ?>

    <h3>Products (WooCommerce)</h3>
    <p>Default sidebar content for all product pages. Individual products can override this in the Sidebar Override meta box.</p>

    <table class="form-table">
        <tr>
            <th scope="row"><label for="products-sidebar-desktop">Sidebar Content (Desktop)</label></th>
            <td>
                <?php
                wp_editor( $sidebar_desktop, 'products_sidebar_desktop', [
                    'textarea_name' => 'sidebar_desktop',
                    'media_buttons' => true,
                    'textarea_rows' => 15,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">Default sidebar for all products. HTML and shortcodes allowed.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="products-sidebar-mobile">Sidebar Content (Mobile/Tablet)</label></th>
            <td>
                <?php
                wp_editor( $sidebar_mobile, 'products_sidebar_mobile', [
                    'textarea_name' => 'sidebar_mobile',
                    'media_buttons' => true,
                    'textarea_rows' => 15,
                    'teeny'         => false,
                    'quicktags'     => true,
                ]);
                ?>
                <p class="description">If empty, desktop version will be used. HTML and shortcodes allowed.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"></th>
            <td>
                <label>
                    <input type="checkbox" name="mirror_sidebar" value="1" <?php checked( $mirror_sidebar, '1' ); ?> />
                    Mirror desktop content to mobile (ignore mobile field above)
                </label>
            </td>
        </tr>
    </table>

    <div style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin-top: 20px;">
        <h4>Priority Order</h4>
        <ol>
            <li><strong>Product-specific content</strong> (set in Sidebar Override meta box) – highest priority</li>
            <li><strong>These default settings</strong> (if no override enabled)</li>
        </ol>
    </div>
    <?php
}

/* ============================================================
 * SAVE SETTINGS
 * ============================================================ */
function monospace_save_layout_settings( $tab ) {
    $prefix = 'monospace_' . str_replace( '-', '_', $tab ) . '_';

    // DEBUG: Log what we're saving
    if ( current_user_can( 'manage_options' ) ) {
        error_log( 'SAVING TAB: ' . $tab );
        error_log( 'PREFIX: ' . $prefix );
        error_log( 'mirror_header in POST: ' . ( isset( $_POST['mirror_header'] ) ? 'YES' : 'NO' ) );
        error_log( 'mirror_sidebar in POST: ' . ( isset( $_POST['mirror_sidebar'] ) ? 'YES' : 'NO' ) );
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

    // DEBUG: Verify what was saved
    if ( current_user_can( 'manage_options' ) ) {
        error_log( 'SAVED mirror_header as: ' . $mirror_header_value . ' to key: ' . $prefix . 'mirror_header' );
        error_log( 'SAVED mirror_sidebar as: ' . $mirror_sidebar_value . ' to key: ' . $prefix . 'mirror_sidebar' );
        error_log( 'VERIFY mirror_sidebar: ' . get_option( $prefix . 'mirror_sidebar', 'NOT FOUND' ) );
    }

    // Save sidebar settings
    if ( isset( $_POST['sidebar_desktop'] ) ) {
        update_option( $prefix . 'sidebar_desktop', stripslashes( wp_kses_post( $_POST['sidebar_desktop'] ) ) );
    }
    if ( isset( $_POST['sidebar_mobile'] ) ) {
        update_option( $prefix . 'sidebar_mobile', stripslashes( wp_kses_post( $_POST['sidebar_mobile'] ) ) );
    }
}
<?php
/**
 * Layout Manager Admin Interface
 *
 * Provides admin UI for configuring default headers and sidebars across the site.
 * Settings are saved as WordPress options and retrieved by feed-headers.php and sidebar-override.php.
 *
 * Four sections (three from 2026-10-19, owner's wish; Shortcodes added 2026-10-24), the same tab row on each - monospace_layout_manager_nav():
 * - Home Page            - the home page boxes (home-boxes-admin.php, its own page: monospace-home-boxes)
 * - Headers & Sidebars   - this page, a second row for the page type (tab = blog-feed | categories |
 *                          search | 404 | posts | pages | products)
 * - Links & toolbar      - this page, tab = links: the logo link, the shop link, open at the content, the viewing
 *                          toolbar (2026-10-22 - was Appearance & Effects; the look and motion are in Appearance > Look)
 * - Shortcodes          - this page, tab = shortcodes: the reference of every shortcode (2026-10-24: was a page type's tab)
 * Clicking "Layout Manager" (no tab) opens Home Page; the admin menu lists the same four.
 *
 * Headers & Sidebars (layout-manager-areas.php draws each page type: a map of the page and a panel per area):
 * - Art Feed (blog-feed) - Home page header/sidebar
 * - Categories - Per-category customization (done in category edit screen)
 * - Search - Search results header/sidebar
 * - Posts/Pages/Products - Default sidebars for each post type
 * - Links & toolbar - drawn by the design modules of the links group (includes/modules/design/, registry in
 *   design-modules.php)
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

/** The four sections: key => [ label, admin URL ] */
function monospace_layout_manager_sections() {
    return [
        'home'    => [ 'Home Page',            'admin.php?page=monospace-home-boxes' ],
        'headers' => [ 'Headers & Sidebars',   'admin.php?page=monospace-layout-manager&tab=site' ],
        'links'   => [ 'Links & toolbar',      'admin.php?page=monospace-layout-manager&tab=links' ], // was Appearance & Effects (2026-10-22: the look is in Appearance > Look)
        'shortcodes' => [ 'Shortcodes',        'admin.php?page=monospace-layout-manager&tab=shortcodes' ], // the reference - a section of its own since 2026-10-24
    ];
}

/** Headers & Sidebars: the page types (tab slug => label) */
function monospace_layout_manager_page_types() {
    // The Site default first, then what overrides it (layout-inherit.php); product pages never show a sidebar - no tab (2026-10-22)
    return [
        'site'       => 'Site default',
        'blog-feed'  => 'Art Feed',
        'categories' => 'Categories',
        'posts'      => 'Posts',
        'pages'      => 'Pages',
        'search'     => 'Search Results',
        '404'        => '404 Page',
    ];
}

// The admin menu under Layout Manager = the sections, in order (the Home Page item is added by
// home-boxes-admin.php at priority 20; WordPress's own first item becomes Headers & Sidebars)
add_action( 'admin_menu', function () {
    global $submenu;
    if ( empty( $submenu['monospace-layout-manager'] ) ) return;
    $items = [];
    foreach ( $submenu['monospace-layout-manager'] as $item ) $items[ $item[2] ] = $item;
    $sections = monospace_layout_manager_sections();
    $menu = [];
    if ( isset( $items['monospace-home-boxes'] ) ) $menu[] = $items['monospace-home-boxes'];
    foreach ( [ 'headers', 'links', 'shortcodes' ] as $key ) {
        $menu[] = [ $sections[ $key ][0], 'manage_options', $sections[ $key ][1], $sections[ $key ][0] ];
    }
    $submenu['monospace-layout-manager'] = $menu;
}, 30 );

// The menu item to highlight on this page
add_filter( 'submenu_file', function ( $file ) {
    if ( ( $_GET['page'] ?? '' ) !== 'monospace-layout-manager' ) return $file;
    $sections = monospace_layout_manager_sections();
    $tab = $_GET['tab'] ?? '';
    return isset( $sections[ $tab ] ) && in_array( $tab, [ 'links', 'shortcodes' ], true ) ? $sections[ $tab ][1] : $sections['headers'][1];
} );

// The old Appearance & Effects tab: its look and motion settings are in Appearance > Look (2026-10-22) - an old link
// goes there (the four link settings are on Links & toolbar, linked from Look's intro)
add_action( 'admin_init', function () {
    if ( ( $_GET['page'] ?? '' ) === 'monospace-layout-manager' && ( $_GET['tab'] ?? '' ) === 'effects' && $_SERVER['REQUEST_METHOD'] === 'GET' ) {
        wp_safe_redirect( admin_url( 'themes.php?page=monospace-look' ) );
        exit;
    }
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
 * $section: home | headers | links | shortcodes; $tab: the page type, on Headers & Sidebars.
 */
function monospace_layout_manager_nav( $section, $tab = '' ) {
    $intros = [
        'home'    => '',
        'headers' => 'One Site default for the top content, the header and the sidebar - and what overrides it: the Art Feed, posts, pages, search results and the 404 page, each area set to “Use the site default” or “Custom”. A single category, post or page can override its page type in its own screen.',
        'shortcodes' => '',
        'links'   => 'Where the logo and the empty cart’s shop button lead, whether posts open at their content, and the viewing toolbar on category pages. How the site looks and moves: Appearance > Look.',
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
    if ( ! in_array( $active_tab, [ 'links', 'shortcodes' ], true ) && ! isset( monospace_layout_manager_page_types()[ $active_tab ] ) ) $active_tab = 'site';

    // Handle form submission
    if ( isset( $_POST['monospace_layout_manager_submit'] ) ) {
        check_admin_referer( 'monospace_layout_manager_settings' );
        monospace_save_layout_settings( $active_tab );
        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    ?>
    <div class="wrap">
        <?php monospace_layout_manager_nav( in_array( $active_tab, [ 'links', 'shortcodes' ], true ) ? $active_tab : 'headers', $active_tab ); ?>

        <form method="post" action="">
            <?php wp_nonce_field( 'monospace_layout_manager_settings' ); ?>

            <?php
            if ( $active_tab === 'links' ) {
                monospace_render_design_modules( 'links' );   // includes/modules/design/ - the links group only
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
    ];
}

// The sidebar visibility and top content fields are panels of layout-manager-areas.php (2026-10-20)

/* ============================================================
 * TAB: SHORTCODES REFERENCE
 * ============================================================ */

/** Every shortcode of the site, for the reference: group => [ tag => [ what it does, [ [ option, values, default ] ],
 *  [ example => what it shows ] ] ]. A shortcode that isn't there (its feature plugin switched off) is left out. */
function monospace_shortcodes_reference() {
    $pc = admin_url( 'themes.php?page=monospace-price-card' );
    return [
        'Sidebars & page areas' => [
            'search_form'   => [ 'A search box.', [
                [ 'placeholder', 'text', 'Search...' ], [ 'button_text', 'text', 'Search' ] ],
                [ '[search_form]' => '', '[search_form placeholder="Search artworks..." button_text="Go"]' => '' ] ],
            'tag_cloud'     => [ 'The tags (or categories, places…) as a cloud - the most used tags, bigger the more they’re used. It always takes the top tags by use, then sorts them as asked.', [
                [ 'taxonomy', 'post_tag | category | place (any taxonomy)', 'post_tag' ],
                [ 'number', 'how many (0 = all)', '45' ],
                [ 'orderby', 'count | name', 'count' ],
                [ 'order', 'DESC | ASC | RAND (random order)', 'DESC' ],
                [ 'smallest / largest', 'the size of the least / most used', '8 / 22' ],
                [ 'unit', 'pt | px | em | %', 'pt' ] ],
                [ '[tag_cloud]' => 'the 45 most used tags, most used first', '[tag_cloud orderby="name" order="ASC"]' => 'alphabetical', '[tag_cloud order="RAND"]' => 'random order, different on every page load', '[tag_cloud number="20" smallest="10" largest="18"]' => 'the top 20, a narrower size range', '[tag_cloud taxonomy="category"]' => 'categories instead of tags' ] ],
            'recent_posts'  => [ 'The latest posts as a list of links.', [
                [ 'number', 'how many', '5' ], [ 'show_date', 'yes | no', 'no' ], [ 'show_excerpt', 'yes | no', 'no' ], [ 'excerpt_length', 'words', '20' ] ],
                [ '[recent_posts number="10" show_date="yes"]' => '', '[recent_posts show_excerpt="yes" excerpt_length="15"]' => '' ] ],
            'category_list' => [ 'The categories as a list of links.', [
                [ 'show_count', 'yes | no', 'no' ], [ 'hide_empty', 'yes | no', 'yes' ], [ 'orderby', 'name | count | id | slug', 'name' ], [ 'order', 'ASC | DESC', 'ASC' ] ],
                [ '[category_list show_count="yes"]' => '', '[category_list orderby="count" order="DESC"]' => 'the fullest first', '[category_list hide_empty="no"]' => 'empty ones too' ] ],
            'archives_list' => [ 'Links to the posts by month (or year…).', [
                [ 'type', 'monthly | yearly | weekly | daily', 'monthly' ], [ 'limit', 'how many', 'all' ], [ 'show_count', 'yes | no', 'no' ] ],
                [ '[archives_list type="yearly" show_count="yes"]' => '', '[archives_list limit="12"]' => 'the last 12 months' ] ],
            'nav_menu'      => [ 'A menu from Appearance > Menus.', [
                [ 'menu', 'the menu’s name, slug or ID (required)', '-' ], [ 'menu_class', 'a CSS class for the list', 'shortcode-menu' ], [ 'container', 'nav | div | (empty = none)', 'nav' ] ],
                [ '[nav_menu menu="Footer"]' => '' ] ],
            'archive_header' => [ 'A category’s feed header, in its sidebar - only on a category / tag / place page whose header is set to “In sidebar”.', [], [ '[archive_header]' => '' ] ],
        ],
        'Shop' => [
            'price_card'    => [ 'An artwork’s price card - tabs, options, the button. In a post it’s that post’s artwork.', [
                [ 'sku', 'the artwork’s number (444) or full SKU', 'this post’s' ], [ 'post', 'the artwork’s post ID', 'this post' ], [ 'style', 'full | compact | line | badge', 'the artwork’s own' ], [ 'tab', 'original | prints | framed | unframed', 'Layout Manager’s' ], [ 'align', 'left | center | right - the Badge’s place on its row', 'Look › Price card' ], [ 'id', 'old: a product ID', '-' ] ],
                [ '[price_card sku="444"]' => '', '[price_card sku="444" style="compact" tab="prints"]' => '', '[price_card sku="444" style="badge" align="center"]' => 'a centered badge' ], $pc ],
            'price_card_standalone' => [ 'The price card with the artwork’s title and picture in it (the print’s mockup on a print tab); clicking the picture opens the artwork.', [
                [ 'sku / post', 'the artwork (number or post ID)', 'this post’s' ], [ 'layout', 'side | stacked', 'Appearance > Price card' ], [ 'title / image / preview / link', 'yes | no', 'Appearance > Price card' ], [ 'sizes', 'relative | same (print mockups)', 'Appearance > Price card' ], [ 'style', 'full | compact', 'full' ], [ 'tab', 'original | prints | framed | unframed', 'Layout Manager’s' ] ],
                [ '[price_card_standalone sku="444"]' => '', '[price_card_standalone sku="444" layout="stacked" title="no"]' => '' ], $pc ],
            'custom_order_card' => [ 'A Custom Order card: the product’s picture, title and short description, its medium / surface / size and price (with the upfront payment), and a button to its order page, where the reference photos are added (Commissions plugin). For a portrait agreed in conversation: make the product, then put its card on the person’s own page. The product’s slug must start with custom-artwork- or visitors are sent away from the order page.', [
                [ 'id / slug', 'the Custom Order product (its ID, or its slug)', '-' ], [ 'button', 'the button’s words', 'Add to Cart' ], [ 'title / image', 'yes | no', 'yes' ], [ 'layout', 'side | stacked (picture on top)', 'side' ], [ 'note', 'a message in the bottom row (sales, announcements, notes); no note, no row', '-' ] ],
                [ '[custom_order_card id="3745"]' => '', '[custom_order_card slug="custom-artwork-smith-portrait" button="Order this portrait"]' => 'a card made for one person', '[custom_order_card id="3745" layout="stacked" note="Booking now for December"]' => 'picture on top, with a note' ] ],
            'commission_policies' => [ 'The commission terms as a list - turnaround, revisions, deposit and refund, the customer’s photos, shipping. One source: the order page and the emails show the same text, so a change in one place changes all of them (Commissions plugin).', [], [ '[commission_policies]' => 'put it under a “Good to know” heading' ] ],
            'artwork' => [ 'An artwork’s picture, anywhere: its longest edge fills the box it is in (or a square thumbnail), with the site’s picture look (outline, shadow, rounding, rollover); clicking it opens the artwork. Add the title and the info lines under it - the same lines, in the same type, as the list and detail views.', [
                [ 'sku / post', 'the artwork (number or post ID)', 'this post’s' ], [ 'title', 'yes | no (the title under it)', 'no' ], [ 'info', 'yes | no (medium on surface, dimensions, Miniature tag)', 'no' ], [ 'availability', 'yes | no (availability, #number, price while for sale)', 'no' ], [ 'prints', 'yes | no (Prints available)', 'no' ], [ 'badges', 'yes | no (the grid view’s green dot - available - and “M” - miniature - on the picture)', 'no' ], [ 'square', 'yes | no (a square thumbnail, the first picture of a set)', 'no' ], [ 'link', 'yes | no', 'yes' ], [ 'align', 'left | center | right - the picture and the text under it', 'center' ] ],
                [ '[artwork sku="234"]' => '', '[artwork sku="234" title="yes" availability="yes"]' => 'picture, title, status', '[artwork sku="234" title="yes" info="yes" align="left"]' => 'picture, title, medium and size, all left', '[artwork sku="234" square="yes" title="yes" info="yes" availability="yes" prints="yes"]' => 'a square thumbnail with the list / detail view’s info lines' ] ],
            'copy_text' => [ 'A small Copy link (an icon and a word) that copies the text of part of the page - its paragraphs and headings, without the link itself - and says “Copied” for a moment. Used under the Bio on the About Me page.', [
                [ 'target', 'a CSS selector for the part to copy, e.g. .ms-bio-text (give a block that class: Advanced > Additional CSS class)', '(required)' ], [ 'label', 'the words next to the icon', 'Copy' ] ],
                [ '[copy_text target=".ms-bio-text" label="Copy bio"]' => '' ] ],
            'product_price' => [ 'A product’s price, in a sentence (the sale price struck through when there is one).', [
                [ 'id', 'the product ID (required)', '-' ] ], [ '[product_price id="12161"]' => '' ] ],
            'cart_contents' => [ 'What’s in the visitor’s cart - filled in by the visitor’s browser (pages are cached for everyone) and updated when something is added or removed.', [
                [ 'style', 'list | count | total', 'list' ], [ 'images', 'yes | no', 'yes' ], [ 'buttons', 'yes | no (View cart, Checkout)', 'yes' ], [ 'empty', 'the text when the cart is empty', 'Your cart is empty.' ] ],
                [ '[cart_contents]' => 'the items, the subtotal, View cart / Checkout', '[cart_contents style="count"]' => '“2 items”', '[cart_contents style="total"]' => 'the subtotal alone', '[cart_contents images="no" buttons="no" empty="Nothing yet."]' => '' ] ],
            'monospace_cart' => [ 'The cart icon with its count bubble (as in the menu bar), linking to the cart.', [], [ '[monospace_cart]' => '' ] ],
            'painting_buy_button' => [ 'The old name of [price_card] - still works; use [price_card] for new pages.', [ [ 'id', 'the product ID', '-' ] ], [] ],
        ],
        'Navigation & links' => [
            'surprise_me'   => [ 'A link to a random artwork (art.monospace.com/random-artwork) - a different one each visit.', [
                [ 'text', 'the link’s words', 'Surprise me' ], [ 'style', 'button | link', 'button' ], [ 'category', 'a category slug', 'all' ], [ 'tag', 'a tag / place slug', 'all' ], [ 'available', 'yes = only originals for sale', 'no' ] ],
                [ '[surprise_me]' => '', '[surprise_me text="Show me a print" category="print" style="link"]' => '', '[surprise_me available="yes"]' => '' ] ],
            'monospace_home' => [ 'The home icon (as in the menu bar), linking to the home page.', [], [ '[monospace_home]' => '' ] ],
            'post_nav_buttons' => [ 'Newer / Older buttons - on a single post only.', [], [ '[post_nav_buttons]' => '' ] ],
            'related_posts' => [ 'Posts that share tags with this one, as a grid or a stack of artworks (each one the [artwork] shortcode) - on a single post only. The defaults are set in Appearance > Look > Related posts.', [
                [ 'limit', 'how many posts (1-24)', 'the Look panel’s' ], [ 'min_tags', 'tags a post must share', 'the Look panel’s' ], [ 'fill', 'yes | no (fill up with random posts when too few match)', 'yes' ], [ 'layout', 'grid (picture, text under it) | stack (picture on the left, text on the right)', 'the Look panel’s' ], [ 'columns', '1-6 (tablets and phones: at most 3). In a sidebar it is 1 unless set here', 'the Look panel’s' ], [ 'title', 'the heading - or yes | no for the items’ titles', 'the Look panel’s' ], [ 'info / availability / prints / badges / square / align', 'as [artwork]', 'the Look panel’s' ] ],
                [ '[related_posts]' => '', '[related_posts limit="6" layout="stack" title="More like this"]' => 'in a sidebar: six, one under the other', '[related_posts limit="6" columns="2" badges="yes"]' => '3 rows of two, with the green dot and M' ] ],
        ],
        'Forms & content' => [
            'monospace_mailpoet_form' => [ 'The newsletter sign-up form (MailPoet).', [ [ 'list_id', 'the MailPoet list', '3' ] ], [ '[monospace_mailpoet_form]' => '' ] ],
            'external_html' => [ 'HTML from another address, put into the page as it is - only from sources you trust.', [
                [ 'url', 'the full address (required)', '-' ], [ 'class', 'a CSS class for the box', 'external-html-container' ] ],
                [ '[external_html url="https://example.com/snippet.html"]' => '' ] ],
        ],
        'Whole pages' => [
            'monospace_art_filter' => [ 'The Art Filter: find artworks by format, medium, surface, size, availability and price, sorted and shown as a grid, list or price cards (Art Filter plugin).', [], [ '[monospace_art_filter]' => '' ] ],
            'market_inventory_terminal' => [ 'The Market Inventory Manager: find a piece by its number and sell it at a market (Market terminal plugin; for shop managers).', [], [ '[market_inventory_terminal]' => '' ] ],
        ],
    ];
}

function monospace_render_shortcodes_reference() {
    $groups = monospace_shortcodes_reference();
    if ( function_exists( 'monospace_lm_area_assets' ) ) monospace_lm_area_assets(); ?>
    <div class="lm-areas ms-sc-ref">
        <p class="lm-where">Every shortcode on the site - type one into a post, a page or a content area of the Layout Manager (sidebars,
            above the artworks…). Options go inside the brackets: <code>[tag_cloud number="20"]</code>; options left out use the default.
            Click an example to copy it.</p>
        <p><input type="search" id="ms-sc-find" class="regular-text" placeholder="Find a shortcode…" aria-label="Find a shortcode"></p>
        <?php foreach ( $groups as $group => $codes ) :
            $codes = array_filter( $codes, fn( $tag ) => shortcode_exists( $tag ), ARRAY_FILTER_USE_KEY );
            if ( ! $codes ) continue; ?>
            <h2 class="ms-sc-group"><?php echo esc_html( $group ); ?></h2>
            <div class="lm-panels">
            <?php foreach ( $codes as $tag => $sc ) :
                [ $what, $options, $examples ] = $sc;
                $more = $sc[3] ?? ''; ?>
                <details class="lm-panel ms-sc" data-find="<?php echo esc_attr( strtolower( $tag . ' ' . $what . ' ' . $group ) ); ?>">
                    <summary><span class="lm-t"><code>[<?php echo esc_html( $tag ); ?>]</code></span>
                        <?php if ( $options ) : ?><span class="lm-chip is-on"><?php echo count( $options ); ?> option<?php echo count( $options ) === 1 ? '' : 's'; ?></span><?php else : ?><span class="lm-chip">no options</span><?php endif; ?>
                        <span class="lm-sub"><?php echo esc_html( $what ); ?></span></summary>
                    <div class="lm-body">
                        <?php if ( $options ) : ?>
                        <table class="widefat striped ms-sc-options"><thead><tr><th>Option</th><th>Values</th><th>Default</th></tr></thead><tbody>
                            <?php foreach ( $options as [ $name, $values, $default ] ) : ?>
                                <tr><td><code><?php echo esc_html( $name ); ?></code></td><td><?php echo esc_html( $values ); ?></td><td><?php echo esc_html( $default ); ?></td></tr>
                            <?php endforeach; ?>
                        </tbody></table>
                        <?php endif; ?>
                        <?php if ( $examples ) : ?>
                        <ul class="ms-sc-examples">
                            <?php foreach ( $examples as $code => $note ) : ?>
                                <li><button type="button" class="ms-sc-copy" title="Copy"><code><?php echo esc_html( $code ); ?></code></button><?php echo $note !== '' ? ' <span class="description">- ' . esc_html( $note ) . '</span>' : ''; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                        <?php if ( $more ) : ?><p><a href="<?php echo esc_url( $more ); ?>">Every option, with a live preview: Appearance &gt; Price card</a></p><?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <p class="description">Shortcodes from other plugins (WooCommerce, MailPoet, Jetpack…) work too; their own help lists them.</p>
    </div>
    <style>
        .ms-sc-ref { max-width: 1100px; }
        .ms-sc-group { margin: 22px 0 8px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #646970; }
        .ms-sc .lm-t code { background: none; padding: 0; font-size: 14px; }
        .ms-sc-options { margin: 0 0 12px; }
        .ms-sc-options td:first-child { white-space: nowrap; }
        .ms-sc-examples { margin: 0; }
        .ms-sc-examples li { margin: 0 0 6px; }
        .ms-sc-copy { border: 0; background: none; padding: 0; cursor: copy; }
        .ms-sc-copy code { display: inline-block; padding: 3px 6px; }
        .ms-sc-copy:hover code { background: #dcdcde; }
        .ms-sc-copy.is-copied code::after { content: "  ✓ copied"; color: #00a32a; font-family: -apple-system, sans-serif; }
        .ms-sc[hidden] { display: none; }
    </style>
    <script>
    (function () {
        // Copy an example
        document.querySelectorAll('.ms-sc-copy').forEach(function (b) {
            b.addEventListener('click', function () {
                var t = b.textContent, done = function () { b.classList.add('is-copied'); setTimeout(function () { b.classList.remove('is-copied'); }, 1500); };
                if (navigator.clipboard) navigator.clipboard.writeText(t).then(done, function () {});
                else { var r = document.createRange(); r.selectNodeContents(b); getSelection().removeAllRanges(); getSelection().addRange(r); }
            });
        });
        // Find: shows the matching ones, opened; an empty box shows all, folded
        var find = document.getElementById('ms-sc-find');
        find.addEventListener('input', function () {
            var q = find.value.trim().toLowerCase();
            document.querySelectorAll('.ms-sc').forEach(function (d) { var hit = !q || d.dataset.find.indexOf(q) > -1; d.hidden = !hit; d.open = !!q && hit; });
            document.querySelectorAll('.ms-sc-group').forEach(function (h) { var p = h.nextElementSibling; h.hidden = !p.querySelector('.ms-sc:not([hidden])'); });
        });
    })();
    </script>
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
    // Links & toolbar tab: its design modules save their own site-wide options (design-modules.php) - that group only
    if ( $tab === 'links' ) {
        monospace_save_design_modules( 'links' );
        return;
    }

    $prefix = 'monospace_' . str_replace( '-', '_', $tab ) . '_';

    // Use the Site default or a custom one, per area (the page types that override it - layout-inherit.php)
    foreach ( monospace_layout_override_types()[ $tab ] ?? [] as $area ) {
        if ( isset( $_POST[ $area . '_mode' ] ) ) update_option( $prefix . $area . '_mode', $_POST[ $area . '_mode' ] === 'default' ? 'default' : 'custom' );
    }

    // Top content (every page-type tab)
    if ( isset( $_POST['top_content'] ) ) {
        update_option( $prefix . 'top_content', stripslashes( wp_kses_post( $_POST['top_content'] ) ) );
    }
    // ...and how it looks (design/banner-style.php)
    if ( function_exists( 'monospace_banner_save_area' ) ) monospace_banner_save_area( 'top_style', $prefix . 'top_style' );

    // Sidebar visibility (only when the tab showed the field)
    if ( isset( $_POST['sidebar_visibility_field'] ) ) {
        update_option( $prefix . 'show_sidebar', isset( $_POST['show_sidebar'] ) ? '1' : '0' );
    }
    // Categories: the Views & price cards table (layout-manager-areas.php), nothing else
    if ( $tab === 'categories' ) {
        monospace_save_term_views();
        return;
    }


    // Save header settings (if applicable)
    if ( isset( $_POST['header_title'] ) ) {
        update_option( $prefix . 'header_title', monospace_title_clean( $_POST['header_title'] ) );
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

/**
 * Categories tab > Views & price cards: each listed term's view bar, price card and Opens on - the same term meta as its
 * edit screen (_monospace_archive_view, _ms_price_cards, _ms_price_card_tab); only what changed is written.
 */
function monospace_save_term_views() {
    if ( empty( $_POST['lm_terms'] ) || ! is_array( $_POST['lm_terms'] ) ) return;
    $presets = function_exists( 'monospace_price_card_presets' ) ? monospace_price_card_presets() : [];
    $tabs    = function_exists( 'monospace_price_card_tabs' ) ? monospace_price_card_tabs() : [];
    $changed = false;
    foreach ( wp_unslash( $_POST['lm_terms'] ) as $id => $in ) {
        $id = absint( $id );
        if ( ! $id || ! is_array( $in ) || ! get_term( $id ) ) continue;
        $want = [
            '_monospace_archive_view' => function_exists( 'monospace_archive_views' ) && isset( monospace_archive_views()[ $in['view'] ?? '' ] ) ? $in['view'] : '',
            '_monospace_archive_bar'  => in_array( $in['bar'] ?? '', [ 'show', 'hide' ], true ) ? $in['bar'] : '',
            '_monospace_share_image'  => ( $sid = absint( $in['share'] ?? 0 ) ) && wp_attachment_is_image( $sid ) ? (string) $sid : '',
            '_ms_price_cards'         => ( $in['cards'] ?? '' ) === 'hide' || isset( $presets[ $in['cards'] ?? '' ] ) ? $in['cards'] : '',
            '_ms_price_card_tab'      => ( $in['tab'] ?? '' ) !== '' && isset( $tabs[ $in['tab'] ] ) ? $in['tab'] : '',
            '_monospace_archive_sort' => defined( 'MONOSPACE_ARCHIVE_SORTS' ) && isset( MONOSPACE_ARCHIVE_SORTS[ $in['sort'] ?? '' ] ) && $in['sort'] !== 'newest' ? $in['sort'] : '',
        ];
        foreach ( $want as $key => $value ) {
            if ( (string) get_term_meta( $id, $key, true ) === $value ) continue;
            $value === '' ? delete_term_meta( $id, $key ) : update_term_meta( $id, $key, $value );
            $changed = true;
        }
    }
    if ( $changed && function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache();
}

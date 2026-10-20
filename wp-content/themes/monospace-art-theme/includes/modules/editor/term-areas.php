<?php
/**
 * The monospace ART section on the category / tag edit screens (2026-10-20, owner's wish: the same UI as
 * Layout Manager > Headers & Sidebars - layout-manager-areas.php, whose look and script it shares).
 *
 * Below WordPress's own fields: a map of this category's page (menu bar, logo, top content, viewing toolbar,
 * header - above the content or in the sidebar -, content with its price cards, sidebar), each area In use /
 * Empty / Not shown, next to one folding panel per area - Header, Viewing toolbar, Price cards, Sidebar. Panels
 * in use start open; the phone text box stays folded while "Same on phones & tablets" is ticked.
 *
 * Same fields and names as before, saved by their own code: header and sidebar - monospace_save_term_fields()
 * (feed-headers.php); list / grid - monospace_save_archive_view_field() (archive-view-toggle.php); price cards -
 * monospace_price_card_term_save() (Core, price-card-presets.php). Text boxes, not the rich editor (that rewrites
 * the HTML on save). Taxonomies: MONOSPACE_ARCHIVE_TAXONOMIES (categories, tags, product categories / tags).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_init', function () {
    if ( ! defined( 'MONOSPACE_ARCHIVE_TAXONOMIES' ) ) return;
    foreach ( MONOSPACE_ARCHIVE_TAXONOMIES as $taxonomy ) {
        // Core's price card rows move into this section
        remove_action( "{$taxonomy}_edit_form_fields", 'monospace_price_card_term_field', 20 );
        add_action( "{$taxonomy}_edit_form", 'monospace_render_term_areas', 10, 2 );
    }
}, 20 );

function monospace_render_term_areas( $term, $taxonomy ) {
    $meta = fn( $k ) => (string) get_term_meta( $term->term_id, $k, true );
    $tax  = get_taxonomy( $taxonomy );
    $kind = strtolower( $tax ? $tax->labels->singular_name : 'category' );
    $lm   = fn( $tab ) => admin_url( 'admin.php?page=monospace-layout-manager&tab=' . $tab );

    // Top content: the Layout Manager's, for every category page
    $top_state = trim( (string) get_option( 'monospace_categories_top_content', '' ) ) !== '' ? 'on' : 'empty';

    // Header
    $h_title    = $meta( '_monospace_header_title' );
    $h_default  = ( $taxonomy === 'post_tag' || $taxonomy === 'product_tag' ? 'Items tagged with: ' : 'Items filed under: ' ) . $term->name;
    $h_desk     = $meta( '_monospace_header_desc_desktop' );
    $h_mobile   = $meta( '_monospace_header_desc_mobile' );
    $h_mirror   = $meta( '_monospace_mirror_desc' ) !== '0';
    $h_location = $meta( '_monospace_header_location' ) ?: 'top';
    $h_state    = $h_location === 'none' ? 'off' : 'on';

    // Viewing toolbar (list / grid)
    $view       = $meta( '_monospace_archive_view' );
    $bar_master = function_exists( 'monospace_archive_toolbar_settings' ) ? monospace_archive_toolbar_settings()['terms'] : true;
    $v_state    = ( $view === '' || ! $bar_master ) ? 'off' : 'on';

    // Price cards (Core)
    $has_cards = function_exists( 'monospace_price_card_presets' );
    if ( $has_cards ) {
        $c_own   = monospace_price_card_term_style( $term->term_id );
        $c_feed  = monospace_price_card_setting( 'feed' );
        $c_style = $c_own === '' ? ( $c_feed === 'off' ? '' : $c_feed ) : ( $c_own === 'hide' ? '' : $c_own );
        $c_state = $c_style === '' ? 'off' : 'on';
        $c_tab   = $meta( '_ms_price_card_tab' );
        $presets = monospace_price_card_presets();
        $tabs    = monospace_price_card_tabs();
    }

    // Sidebar: shown on category pages or not (Layout Manager), this one's own content, else the Art Feed's
    $show     = function_exists( 'monospace_sidebar_default_visible' ) ? monospace_sidebar_default_visible( 'categories' ) : true;
    $s_desk   = $meta( '_monospace_sidebar_desktop' );
    $s_mobile = $meta( '_monospace_sidebar_mobile' );
    $s_mirror = $meta( '_monospace_mirror_sidebar' ) !== '0';
    $s_state  = ! $show ? 'off' : ( trim( $s_desk . $s_mobile ) !== '' ? 'on' : 'terms' );

    $chip = function ( $state, $text = null ) {
        $words = [ 'on' => 'In use', 'empty' => 'Empty', 'off' => 'Not shown', 'terms' => 'Art Feed’s' ];
        return '<span class="lm-chip is-' . esc_attr( $state ) . '">' . esc_html( $text ?? $words[ $state ] ) . '</span>';
    };
    $open  = fn( $state ) => in_array( $state, [ 'on', 'terms' ], true ) ? ' open' : '';
    $preview = function_exists( 'monospace_lm_preview' ) ? 'monospace_lm_preview' : fn( $t ) => wp_strip_all_tags( $t );
    $header_in_sidebar = $h_location === 'sidebar';
    ?>
    <h2 class="lm-term-title">monospace ART - this <?php echo esc_html( $kind ); ?>’s page</h2>
    <div class="lm-areas">
        <input type="hidden" name="monospace_term_fields" value="1">
        <p class="lm-where"><b><?php echo esc_html( $term->name ); ?>:</b> what its page shows around the artworks.
            <a href="<?php echo esc_url( get_term_link( $term ) ); ?>" target="_blank">View the page ↗</a></p>

        <div class="lm-grid">
            <figure class="lm-map" aria-label="Where each area goes on the page">
                <div class="lm-r lm-fixed">Menu bar</div>
                <div class="lm-r lm-fixed lm-logo">Logo &amp; tagline</div>
                <a class="lm-r lm-area is-<?php echo esc_attr( $top_state ); ?>" href="<?php echo esc_url( $lm( 'categories' ) ); ?>" title="Set for every category page in the Layout Manager">Top content ↗</a>
                <div class="lm-cols">
                    <div class="lm-main">
                        <button type="button" class="lm-r lm-area is-<?php echo esc_attr( $v_state ); ?>" data-panel="view">Viewing toolbar</button>
                        <?php if ( ! $header_in_sidebar ) : ?>
                            <button type="button" class="lm-r lm-area is-<?php echo esc_attr( $h_state ); ?>" data-panel="header">Header</button>
                        <?php endif; ?>
                        <div class="lm-r lm-fixed lm-content">Artworks
                            <?php if ( $has_cards ) : ?><span class="lm-in-side is-<?php echo esc_attr( $c_state ); ?>" data-panel="cards" style="margin-top:8px">Price cards</span><?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="lm-r lm-area lm-side is-<?php echo esc_attr( $s_state ); ?>" data-panel="sidebar">
                        <?php if ( $header_in_sidebar ) : ?><span class="lm-in-side is-on" data-panel="header">Header</span><?php endif; ?>
                        Sidebar
                    </button>
                </div>
                <figcaption>Phones: the sidebar shows above the artworks.<br>
                    <span class="lm-key is-on"></span> in use <span class="lm-key is-empty"></span> empty <span class="lm-key is-off"></span> not shown</figcaption>
            </figure>

            <div class="lm-panels">
                <!-- HEADER -->
                <details class="lm-panel" data-panel="header"<?php echo $open( $h_state ); ?>>
                    <summary><span class="lm-t">Header</span> <?php echo $chip( $h_state ); ?>
                        <span class="lm-sub"><?php echo esc_html( [ 'top' => 'Above the artworks', 'sidebar' => 'In the sidebar ([archive_header])', 'none' => 'Not shown' ][ $h_location ] ?? 'Above the artworks' );
                            echo $h_state === 'on' ? ' · ' . esc_html( $preview( ( $h_title ?: $h_default ) . ( $h_desk !== '' ? ' - ' . $h_desk : '' ) ) ) : ''; ?></span></summary>
                    <div class="lm-body">
                        <p><label class="lm-label" for="monospace-header-title">Title</label>
                            <input type="text" name="monospace_header_title" id="monospace-header-title" value="<?php echo esc_attr( $h_title ); ?>" class="regular-text" placeholder="<?php echo esc_attr( $h_default ); ?>">
                            <span class="description">Empty = “<?php echo esc_html( $h_default ); ?>”.</span></p>
                        <p><label class="lm-label" for="monospace-header-location">Where</label>
                            <select name="monospace_header_location" id="monospace-header-location">
                                <option value="top" <?php selected( $h_location, 'top' ); ?>>Above the artworks</option>
                                <option value="sidebar" <?php selected( $h_location, 'sidebar' ); ?>>In the sidebar (put [archive_header] in the sidebar)</option>
                                <option value="none" <?php selected( $h_location, 'none' ); ?>>Don’t show</option>
                            </select></p>
                        <p class="lm-label"><label for="monospace-header-desc-desktop">Description</label></p>
                        <textarea name="monospace_header_desc_desktop" id="monospace-header-desc-desktop" rows="5" class="large-text"><?php echo esc_textarea( $h_desk ); ?></textarea>
                        <p class="description">HTML and shortcodes allowed.</p>
                        <label class="lm-mirror"><input type="checkbox" name="monospace_mirror_desc" value="1" <?php checked( $h_mirror ); ?>> Same on phones &amp; tablets</label>
                        <div class="lm-mobile"<?php echo $h_mirror ? ' hidden' : ''; ?>>
                            <p class="lm-label"><label for="monospace-header-desc-mobile">Description on phones &amp; tablets</label> <span class="description">(empty = the one above)</span></p>
                            <textarea name="monospace_header_desc_mobile" id="monospace-header-desc-mobile" rows="5" class="large-text"><?php echo esc_textarea( $h_mobile ); ?></textarea>
                        </div>
                    </div>
                </details>

                <!-- VIEWING TOOLBAR -->
                <details class="lm-panel" data-panel="view"<?php echo $open( $v_state ); ?>>
                    <summary><span class="lm-t">Viewing toolbar</span> <?php echo $chip( $v_state ); ?>
                        <span class="lm-sub">Above the artworks: Original available · Prints available · sort · list / grid<?php
                            echo $v_state === 'on' ? ' · starts in ' . esc_html( $view ) . ' view' : ''; ?></span></summary>
                    <div class="lm-body">
                        <p><label class="lm-label" for="monospace-archive-view">Show</label>
                            <select name="monospace_archive_view" id="monospace-archive-view">
                                <option value="" <?php selected( $view, '' ); ?>>Off (list only)</option>
                                <option value="list" <?php selected( $view, 'list' ); ?>>On, start in list view</option>
                                <option value="grid" <?php selected( $view, 'grid' ); ?>>On, start in grid view</option>
                            </select></p>
                        <p class="description">A visitor’s own list / grid choice wins over the starting view.
                            <?php if ( ! $bar_master ) : ?><b>Switched off for every category page</b> in
                            <a href="<?php echo esc_url( $lm( 'effects' ) ); ?>">Layout Manager → Appearance &amp; Effects → Viewing toolbar</a>.<?php endif; ?></p>
                    </div>
                </details>

                <?php if ( $has_cards ) : ?>
                <!-- PRICE CARDS -->
                <details class="lm-panel" data-panel="cards"<?php echo $open( $c_state ); ?>>
                    <summary><span class="lm-t">Price cards</span> <?php echo $chip( $c_state, $c_state === 'on' ? $presets[ $c_style ] : null ); ?>
                        <span class="lm-sub">Under each artwork, list view<?php echo $c_own === '' ? ' · the site setting' : ''; ?></span></summary>
                    <div class="lm-body">
                        <p><label class="lm-label" for="ms-price-cards">Show</label>
                            <select name="ms_price_cards" id="ms-price-cards">
                                <option value="" <?php selected( $c_own, '' ); ?>>Site setting (<?php echo esc_html( $c_feed === 'off' ? 'none' : $presets[ $c_feed ] ); ?>)</option>
                                <option value="hide" <?php selected( $c_own, 'hide' ); ?>>Hide</option>
                                <?php foreach ( $presets as $k => $label ) : ?>
                                    <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $c_own, $k ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select></p>
                        <p><label class="lm-label" for="ms-price-card-tab">Opens on</label>
                            <select name="ms_price_card_tab" id="ms-price-card-tab">
                                <option value="" <?php selected( isset( $tabs[ $c_tab ] ) ? $c_tab : '', '' ); ?>>Site setting (<?php echo esc_html( $tabs[ monospace_price_card_tab_setting() ] ); ?>)</option>
                                <?php foreach ( $tabs as $k => $label ) : if ( $k === '' ) continue; ?>
                                    <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $c_tab, $k ); ?>><?php echo esc_html( $label ); ?></option>
                                <?php endforeach; ?>
                            </select></p>
                        <p class="description">Site settings: <a href="<?php echo esc_url( $lm( 'effects' ) ); ?>">Layout Manager → Appearance &amp; Effects → Price card</a>.</p>
                    </div>
                </details>
                <?php endif; ?>

                <!-- SIDEBAR -->
                <details class="lm-panel" data-panel="sidebar"<?php echo $open( $s_state ); ?>>
                    <summary><span class="lm-t">Sidebar</span> <?php echo $chip( $s_state, $s_state === 'on' ? 'Its own' : null ); ?>
                        <span class="lm-sub">Right of the artworks (phones: above them)<?php
                            echo $s_state === 'on' ? ' · ' . esc_html( $preview( $s_desk ) ) : ( $s_state === 'terms' ? ' · empty here, so the Art Feed’s sidebar' : '' ); ?></span></summary>
                    <div class="lm-body">
                        <?php if ( ! $show ) : ?>
                            <p class="description"><b>The sidebar is off for every category page</b> -
                                <a href="<?php echo esc_url( $lm( 'categories' ) ); ?>">Layout Manager → Headers &amp; Sidebars → Categories</a>. What you write here shows once it's on.</p>
                        <?php endif; ?>
                        <textarea name="monospace_sidebar_desktop" id="monospace-sidebar-desktop" rows="7" class="large-text" placeholder="Empty = the Art Feed’s sidebar"><?php echo esc_textarea( $s_desk ); ?></textarea>
                        <p class="description">HTML and shortcodes allowed - see <a href="<?php echo esc_url( $lm( 'shortcodes' ) ); ?>">Shortcodes</a>.</p>
                        <label class="lm-mirror"><input type="checkbox" name="monospace_mirror_sidebar" value="1" <?php checked( $s_mirror ); ?>> Same on phones &amp; tablets</label>
                        <div class="lm-mobile"<?php echo $s_mirror ? ' hidden' : ''; ?>>
                            <p class="lm-label"><label for="monospace-sidebar-mobile">Sidebar on phones &amp; tablets</label> <span class="description">(empty = the one above)</span></p>
                            <textarea name="monospace_sidebar_mobile" id="monospace-sidebar-mobile" rows="7" class="large-text"><?php echo esc_textarea( $s_mobile ); ?></textarea>
                        </div>
                    </div>
                </details>
            </div>
        </div>
    </div>
    <style>
        .lm-term-title { margin: 28px 0 6px; }
        .lm-areas a.lm-area { text-decoration: none; }
        .lm-content .lm-in-side { cursor: pointer; }
    </style>
    <?php
    if ( function_exists( 'monospace_lm_area_assets' ) ) monospace_lm_area_assets();
}

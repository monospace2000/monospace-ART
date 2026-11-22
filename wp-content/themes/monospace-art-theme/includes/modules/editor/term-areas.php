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
 * Top content (2026-10-21, owner: "how do I set banners per category?"): Same as all category pages (Layout Manager) /
 * Its own (text + a banner's style, dates, close button - design/banner-style.php) / None on this one - term meta
 * _monospace_top_mode ('' | custom | none), _monospace_top_content, _monospace_top_style; saved below.
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
        add_action( "edited_{$taxonomy}", 'monospace_save_term_top_content' );
        add_action( "edited_{$taxonomy}", 'monospace_save_term_share_image' );
        add_action( "edited_{$taxonomy}", 'monospace_save_term_share_override' ); // the sharing icons' Show / Hide (design/share-button.php)
    }
}, 20 );

// The share image's picker (WordPress's media window) on the category / tag edit screens
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( in_array( $hook, [ 'term.php', 'edit-tags.php' ], true ) && defined( 'MONOSPACE_ARCHIVE_TAXONOMIES' ) && in_array( $_GET['taxonomy'] ?? '', MONOSPACE_ARCHIVE_TAXONOMIES, true ) ) wp_enqueue_media();
} );

function monospace_render_term_areas( $term, $taxonomy ) {
    $meta = fn( $k ) => (string) get_term_meta( $term->term_id, $k, true );
    $tax  = get_taxonomy( $taxonomy );
    $kind = strtolower( $tax ? $tax->labels->singular_name : 'category' );
    $lm   = fn( $tab ) => admin_url( 'admin.php?page=monospace-layout-manager&tab=' . $tab );

    // Top content: the Layout Manager's for every category page, or this one's own / none
    $top_shared  = trim( (string) get_option( 'monospace_site_top_content', '' ) ) !== '';
    $top_mode    = $meta( '_monospace_top_mode' );
    $top_own     = $meta( '_monospace_top_content' );
    $top_state   = $top_mode === 'none' ? 'off' : ( $top_mode === 'custom' ? ( trim( $top_own ) !== '' ? 'on' : 'empty' ) : ( $top_shared ? 'on' : 'empty' ) );

    // Header
    $h_title    = $meta( '_monospace_header_title' );
    $h_default  = ( $taxonomy === 'post_tag' || $taxonomy === 'product_tag' ? 'Items tagged with: ' : 'Items filed under: ' ) . $term->name;
    $h_desk     = $meta( '_monospace_header_desc_desktop' );
    $h_mobile   = $meta( '_monospace_header_desc_mobile' );
    $h_mirror   = $meta( '_monospace_mirror_desc' ) !== '0';
    $h_location = $meta( '_monospace_header_location' ) ?: 'top';
    $h_state    = $h_location === 'none' ? 'off' : 'on';

    // View and viewing toolbar (the page's own choices; '' = same as everywhere - archive-view-toggle.php)
    $view       = $meta( '_monospace_archive_view' );
    $bar        = $meta( '_monospace_archive_bar' );
    $v_now      = function_exists( 'monospace_archive_resolve' ) ? monospace_archive_resolve( $view, $bar, $taxonomy ) : null; // what visitors get
    $v_state    = $v_now && $v_now['bar'] ? 'on' : 'off';

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

    // Sidebar: shown on category pages or not (Layout Manager), this one's own content, else the Site default's
    $show     = function_exists( 'monospace_sidebar_default_visible' ) ? monospace_sidebar_default_visible( 'categories' ) : true;
    $s_desk   = $meta( '_monospace_sidebar_desktop' );
    $s_mobile = $meta( '_monospace_sidebar_mobile' );
    $s_mirror = $meta( '_monospace_mirror_sidebar' ) !== '0';
    $s_state  = ! $show ? 'off' : ( trim( $s_desk . $s_mobile ) !== '' ? 'on' : 'terms' );

    // Share image (2026-10-23): the picture shown when this page is shared; none chosen = its newest artwork (head-tags.php)
    $sh_id    = (int) $meta( '_monospace_share_image' );
    $sh_src   = $sh_id ? (string) wp_get_attachment_image_url( $sh_id, 'medium_large' ) : '';
    $sh_state = $sh_src ? 'on' : 'empty';

    $chip = function ( $state, $text = null ) {
        $words = [ 'on' => 'In use', 'empty' => 'Empty', 'off' => 'Not shown', 'terms' => 'Site default' ];
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
            <a href="<?php echo esc_url( get_term_link( $term ) ); ?>" target="_blank">View the page ↗︎</a></p>

        <div class="lm-grid">
            <figure class="lm-map" aria-label="Where each area goes on the page">
                <div class="lm-r lm-fixed">Menu bar</div>
                <div class="lm-r lm-fixed lm-logo">Logo &amp; tagline</div>
                <button type="button" class="lm-r lm-area is-<?php echo esc_attr( $top_state ); ?>" data-panel="top">Top content</button>
                <?php if ( function_exists( 'monospace_sidebar_position' ) && monospace_sidebar_position()['desktop'] === 'left' ) : // Look > Sidebar: on the left ?>
                    <style>.lm-cols.is-left { grid-template-columns: 64px 1fr; } .lm-cols.is-left > .lm-side { order: -1; }</style>
                <?php endif; ?>
                <div class="lm-cols<?php echo function_exists( 'monospace_sidebar_position' ) && monospace_sidebar_position()['desktop'] === 'left' ? ' is-left' : ''; ?>">
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
                <figcaption><?php $sbp = function_exists( 'monospace_sidebar_position' ) ? monospace_sidebar_position() : [ 'desktop' => 'right', 'phone' => 'above' ]; ?>
                    Sidebar: <?php echo esc_html( $sbp['desktop'] ); ?> on computers, <?php echo esc_html( $sbp['phone'] ); ?> the artworks on phones (<a href="<?php echo esc_url( admin_url( 'themes.php?page=monospace-look' ) ); ?>">Look &rsaquo; Sidebar</a>).<br>
                    <span class="lm-key is-on"></span> in use <span class="lm-key is-empty"></span> empty <span class="lm-key is-off"></span> not shown</figcaption>
            </figure>

            <div class="lm-panels">
                <!-- TOP CONTENT -->
                <details class="lm-panel" data-panel="top"<?php echo $top_mode === 'custom' ? ' open' : ''; ?>>
                    <summary><span class="lm-t">Top content</span> <?php echo $chip( $top_state ); ?>
                        <span class="lm-sub"><?php echo esc_html( $top_mode === 'none' ? 'None on this ' . $kind : ( $top_mode === 'custom' ? 'Its own banner' . ( trim( $top_own ) !== '' && function_exists( 'monospace_lm_preview' ) ? ' · ' . monospace_lm_preview( $top_own ) : '' ) : 'Same as all category pages' ) ); ?></span></summary>
                    <div class="lm-body ms-term-top">
                        <input type="hidden" name="monospace_top_field" value="1">
                        <label style="display:block;margin-bottom:4px"><input type="radio" name="monospace_top_mode" value="" <?php checked( $top_mode, '' ); ?>> The site default - <a href="<?php echo esc_url( $lm( 'site' ) ); ?>">Layout Manager &gt; Site default</a><?php echo $top_shared ? '' : ' (empty now)'; ?></label>
                        <label style="display:block;margin-bottom:4px"><input type="radio" name="monospace_top_mode" value="custom" <?php checked( $top_mode, 'custom' ); ?>> Its own - a banner just for this <?php echo esc_html( $kind ); ?></label>
                        <label style="display:block;margin-bottom:10px"><input type="radio" name="monospace_top_mode" value="none" <?php checked( $top_mode, 'none' ); ?>> None on this <?php echo esc_html( $kind ); ?></label>
                        <div class="ms-term-top-own"<?php echo $top_mode === 'custom' ? '' : ' hidden'; ?>>
                            <textarea name="monospace_top_content" id="monospace-top-content" rows="4" class="large-text" placeholder="The banner’s text - Markdown (**bold**, *italic*, [link](url)), HTML and shortcodes allowed"><?php echo esc_textarea( $top_own ); ?></textarea>
                            <?php if ( function_exists( 'monospace_banner_fields' ) ) {
                                $top_style = get_term_meta( $term->term_id, '_monospace_top_style', true );
                                monospace_banner_fields( 'monospace_top_style', monospace_banner_area( is_array( $top_style ) ? $top_style : [] ), true, 'monospace-top-content' );
                            } ?>
                        </div>
                        <script>
                        (function () {
                            var box = document.currentScript.closest('.ms-term-top');
                            box.addEventListener('change', function (e) {
                                if (e.target.name === 'monospace_top_mode') box.querySelector('.ms-term-top-own').hidden = e.target.value !== 'custom';
                            });
                        })();
                        </script>
                    </div>
                </details>

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

                <!-- VIEW + VIEWING TOOLBAR -->
                <?php $views = function_exists( 'monospace_archive_views' ) ? monospace_archive_views() : [ 'list' => 'List' ]; ?>
                <details class="lm-panel" data-panel="view"<?php echo $open( $v_state ); ?>>
                    <summary><span class="lm-t">Viewing toolbar</span> <?php echo $chip( $v_state ); ?>
                        <span class="lm-sub"><?php
                            echo $v_now ? 'Shows as ' . esc_html( strtolower( $views[ $v_now['view'] ] ?? 'list' ) ) . ' · toolbar ' . ( $v_now['bar'] ? 'shown' : 'hidden' ) : 'Not used on shop pages unless a view is chosen'; ?></span></summary>
                    <div class="lm-body">
                        <p><label class="lm-label" for="monospace-archive-view">View</label>
                            <select name="monospace_archive_view" id="monospace-archive-view">
                                <?php foreach ( monospace_archive_view_choices() as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $view, $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                            </select>
                            <span class="description">How the artworks are shown when the page opens.</span></p>
                        <p><label class="lm-label" for="monospace-archive-bar">Viewing toolbar</label>
                            <select name="monospace_archive_bar" id="monospace-archive-bar">
                                <?php foreach ( monospace_archive_bar_choices() as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $bar, $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                            </select>
                            <span class="description">Original available · Prints available · sort · list / grid / detail list buttons. A visitor’s own pick of view wins while it is shown.</span></p>
                        <?php if ( defined( 'MONOSPACE_ARCHIVE_SORTS' ) ) : $sort = $meta( '_monospace_archive_sort' ) ?: 'newest'; ?>
                        <p><label class="lm-label" for="monospace-archive-sort">Starts sorted</label>
                            <select name="monospace_archive_sort" id="monospace-archive-sort">
                                <?php foreach ( MONOSPACE_ARCHIVE_SORTS as $k => $label ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $sort, $k ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                            </select>
                            <span class="description">The order the page opens in; visitors can still pick another in the sort menu.</span></p>
                        <?php endif; ?>
                        <p class="description"><b>Price card</b> shows each artwork as a standalone price card; it has no button for visitors and no toolbar, so it is only set here.
                            “Same as everywhere” follows <a href="<?php echo esc_url( $lm( 'links' ) ); ?>">Layout Manager → Links &amp; toolbar → Views &amp; viewing toolbar</a>.
                            Every category at once: Layout Manager → Headers &amp; Sidebars → Categories.</p>
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
                        <p class="description">Site settings: <a href="<?php echo esc_url( admin_url( 'themes.php?page=monospace-price-card' ) ); ?>">Appearance → Price card</a>.</p>
                    </div>
                </details>
                <?php endif; ?>

                <!-- SHARE IMAGE -->
                <details class="lm-panel" data-panel="share"<?php echo $open( $sh_state ); ?>>
                    <summary><span class="lm-t">Share image</span> <?php echo $chip( $sh_state, $sh_state === 'on' ? 'Its own' : 'Newest artwork' ); ?>
                        <span class="lm-sub">The picture shown when this <?php echo esc_html( $kind ); ?>’s page is shared</span></summary>
                    <div class="lm-body">
                        <input type="hidden" name="monospace_share_field" value="1">
                        <input type="hidden" name="monospace_share_image" id="ms-share-id" value="<?php echo $sh_src ? (int) $sh_id : ''; ?>">
                        <div class="ms-share">
                            <div class="ms-share-card"><img id="ms-share-img" src="<?php echo esc_url( $sh_src ); ?>" alt=""<?php echo $sh_src ? '' : ' hidden'; ?>>
                                <span id="ms-share-none"<?php echo $sh_src ? ' hidden' : ''; ?>>its newest artwork</span></div>
                            <div>
                                <button type="button" class="button" id="ms-share-choose">Choose image…</button>
                                <button type="button" class="button-link" id="ms-share-reset"<?php echo $sh_src ? '' : ' hidden'; ?>>Use the newest artwork</button>
                                <p class="description">Facebook, Instagram, Bluesky, iMessage and others show this picture when the page is shared. Best 1200 × 630 px (landscape) -
                                    most apps crop to about that shape, so keep the important part in the middle. Without one, the page shares its newest artwork
                                    (as sorted on the page). Apps keep the old picture for a while after a change.</p>
                            </div>
                        </div>
                        <?php if ( function_exists( 'monospace_share_override_field' ) ) : ?>
                        <p class="lm-label" style="margin-top:16px"><label for="ms-share-override">Sharing icons</label></p>
                        <p><?php echo str_replace( '<select ', '<select id="ms-share-override" ', monospace_share_override_field( get_term_meta( $term->term_id, '_monospace_share', true ), monospace_share_visible( monospace_share_term_kind( $term ) ) ) ); ?></p>
                        <p class="description">The share icon beside this <?php echo esc_html( $kind ); ?>’s title. Appearance → Look → Sharing icons sets it for all; switched off there, it is off everywhere.</p>
                        <?php endif; ?>
                        <style>
                            .ms-share { display: flex; gap: 18px; align-items: flex-start; }
                            .ms-share-card { flex: 0 0 240px; aspect-ratio: 1200 / 630; border: 1px solid #c3c4c7; border-radius: 6px; overflow: hidden; background: #f0f0f1; display: flex; align-items: center; justify-content: center; color: #646970; font-size: 12px; }
                            .ms-share-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
                            .ms-share-card img[hidden], .ms-share-card span[hidden] { display: none; }
                        </style>
                        <script>
                        jQuery(function ($) {
                            var frame;
                            $('#ms-share-choose').on('click', function (e) {
                                e.preventDefault();
                                if (!frame) {
                                    frame = wp.media({ title: 'Share image', library: { type: 'image' }, button: { text: 'Use this image' }, multiple: false });
                                    frame.on('select', function () {
                                        var a = frame.state().get('selection').first().toJSON(), s = a.sizes || {};
                                        $('#ms-share-id').val(a.id);
                                        $('#ms-share-img').attr('src', (s.medium_large || s.large || a).url).prop('hidden', false);
                                        $('#ms-share-none').prop('hidden', true);
                                        $('#ms-share-reset').prop('hidden', false);
                                    });
                                }
                                frame.open();
                            });
                            $('#ms-share-reset').on('click', function () {
                                $('#ms-share-id').val('');
                                $('#ms-share-img').prop('hidden', true);
                                $('#ms-share-none').prop('hidden', false);
                                $(this).prop('hidden', true);
                            });
                        });
                        </script>
                    </div>
                </details>

                <!-- SIDEBAR -->
                <details class="lm-panel" data-panel="sidebar"<?php echo $open( $s_state ); ?>>
                    <summary><span class="lm-t">Sidebar</span> <?php echo $chip( $s_state, $s_state === 'on' ? 'Its own' : null ); ?>
                        <span class="lm-sub">Right of the artworks (phones: above them)<?php
                            echo $s_state === 'on' ? ' · ' . esc_html( $preview( $s_desk ) ) : ( $s_state === 'terms' ? ' · empty here, so the Site default’s sidebar' : '' ); ?></span></summary>
                    <div class="lm-body">
                        <?php if ( ! $show ) : ?>
                            <p class="description"><b>The sidebar is off for every category page</b> -
                                <a href="<?php echo esc_url( $lm( 'site' ) ); ?>">Layout Manager → Headers &amp; Sidebars → Site default</a>. What you write here shows once it's on.</p>
                        <?php endif; ?>
                        <textarea name="monospace_sidebar_desktop" id="monospace-sidebar-desktop" rows="7" class="large-text" placeholder="Empty = the Site default’s sidebar"><?php echo esc_textarea( $s_desk ); ?></textarea>
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

/** Save a category's share image (an attachment ID; empty = its newest artwork) */
function monospace_save_term_share_image( $term_id ) {
    if ( empty( $_POST['monospace_share_field'] ) || ! current_user_can( 'manage_categories' ) ) return;
    $id = absint( $_POST['monospace_share_image'] ?? 0 );
    $id && wp_attachment_is_image( $id ) ? update_term_meta( $term_id, '_monospace_share_image', $id ) : delete_term_meta( $term_id, '_monospace_share_image' );
    if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache();
}

/** Save a category's top content: Same as all ('' - nothing stored) / its own / none */
function monospace_save_term_top_content( $term_id ) {
    if ( empty( $_POST['monospace_top_field'] ) || ! current_user_can( 'manage_categories' ) ) return;
    $mode = sanitize_key( wp_unslash( $_POST['monospace_top_mode'] ?? '' ) );
    in_array( $mode, [ 'custom', 'none' ], true ) ? update_term_meta( $term_id, '_monospace_top_mode', $mode ) : delete_term_meta( $term_id, '_monospace_top_mode' );
    $content = trim( wp_kses_post( wp_unslash( $_POST['monospace_top_content'] ?? '' ) ) );
    $content !== '' ? update_term_meta( $term_id, '_monospace_top_content', $content ) : delete_term_meta( $term_id, '_monospace_top_content' );
    if ( isset( $_POST['monospace_top_style'] ) && is_array( $_POST['monospace_top_style'] ) && function_exists( 'monospace_banner_clean' ) ) {
        $style = monospace_banner_clean( wp_unslash( $_POST['monospace_top_style'] ), [ 'style' => '', 'color' => '', 'align' => '', 'size' => '', 'from' => '', 'until' => '', 'close' => '' ] );
        array_filter( $style ) ? update_term_meta( $term_id, '_monospace_top_style', $style ) : delete_term_meta( $term_id, '_monospace_top_style' );
    }
    if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache();
}

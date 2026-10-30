<?php
/**
 * Appearance > About monospace ART - the theme's own page (2026-10-20, owner: "Create an About page for the theme").
 * The menu item is registered in functions.php (since the start; the page itself was never written).
 *
 * What the theme is, where each part of the look is changed (with what's set now), the current colors and fonts,
 * and which parts of the site are on. Read-only - every setting lives on its own screen. Admin UI style (panels +
 * chips, ARCHITECTURE.md 6b).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_art_child_about_page() {
    $theme = wp_get_theme( get_template() );
    $lm    = function_exists( 'monospace_layout_manager_sections' ) ? monospace_layout_manager_sections() : [];
    $url   = fn( $path ) => esc_url( admin_url( $path ) );

    // What's set now
    $look = function_exists( 'monospace_appearance' ) ? monospace_appearance() : null;
    $look_name = '';
    if ( $look ) {
        $presets = monospace_appearance_presets();
        $look_name = $look[0] === 'custom'
            ? 'Custom: ' . monospace_appearance_backgrounds()[ $look[1] ][0] . ', ' . strtolower( monospace_appearance_button_styles()[ $look[2] ][0] . ' ' . monospace_appearance_button_colors()[ $look[3] ][0] ) . ' buttons'
            : $presets[ $look[0] ][0];
    }
    $spot = function_exists( 'monospace_spotlight_settings' ) ? monospace_spotlight_settings() : null;
    $spot_state = ! $spot ? '' : ( ! $spot['on'] ? [ 'Off', 'is-off' ] : ( $spot['pos'] || $spot['colors'] ? [ 'Your own', 'is-on' ] : [ 'The theme’s own', '' ] ) );
    $colors = function_exists( 'monospace_customizer_colors' ) ? monospace_customizer_colors() : [];
    $roles  = function_exists( 'monospace_customizer_font_roles' ) ? monospace_customizer_font_roles() : [];
    $fonts  = function_exists( 'monospace_customizer_fonts' ) ? monospace_customizer_fonts() : [];
    $menu_mods      = [ 'ms_color_menu_bg', 'ms_color_menu_link', 'ms_color_menu_link_hover' ];
    $changed_colors = count( array_filter( array_diff( array_keys( $colors ), $menu_mods ), fn( $m ) => (string) get_theme_mod( $m, '' ) !== '' ) );
    $changed_menu   = count( array_filter( $menu_mods, fn( $m ) => (string) get_theme_mod( $m, '' ) !== '' ) ) + ( (string) get_theme_mod( 'ms_menu_bg_opacity', '' ) !== '' ? 1 : 0 ) + count( array_filter( [ 'ms_menu_shadow', 'ms_menu_bevel' ], fn( $m ) => get_theme_mod( $m, '' ) === 'off' ) );
    $card           = function_exists( 'monospace_look_settings' ) ? monospace_look_settings() : null;
    $card_own       = $card && function_exists( 'monospace_look_card_defaults' ) && array_intersect_key( $card, monospace_look_card_defaults() ) != monospace_look_card_defaults();
    $look_screen    = $url( 'themes.php?page=monospace-look' );
    $effects        = function_exists( 'monospace_look_modules' ) ? implode( ', ', array_map( fn( $m ) => strtolower( $m['title'] ), monospace_look_modules() ) ) : 'scroll and rollover effects, the print preview, the price card';
    $changed_fonts  = count( array_filter( $roles, fn( $r, $m ) => ! in_array( (string) get_theme_mod( $m, '' ), [ '', $r[2] ], true ), ARRAY_FILTER_USE_BOTH ) );
    $menus     = get_nav_menu_locations();
    $menu_name = fn( $loc ) => ! empty( $menus[ $loc ] ) && ( $m = wp_get_nav_menu_object( $menus[ $loc ] ) ) ? $m->name : '';
    $customize = fn( $what ) => $url( 'customize.php?autofocus[' . $what . ']' );

    $saved_looks = function_exists( 'monospace_look_presets' ) ? count( monospace_look_presets( 'light' ) ) . ' light · ' . count( monospace_look_presets( 'dark' ) ) . ' dark' : ''; // presets kept, per look

    // Where to change what: [ title, what it covers, state chip [ text, class ], links [ label => url ] ]
    // In the Look screen's order (Appearance > Look - the one place for the look), then the rest
    $places = [
        [ 'Background & buttons', 'The page background - canvas, paper, watercolor, cream, studio, your color or your image - and the buttons\' style and color; one of the five Looks, or your own mix.',
          $look_name ? [ $look_name, 'is-on' ] : null, [ 'Look' => $look_screen ] ],
        [ 'Site colors', 'Text, secondary text, headings, links and the tagline under the logo.',
          $colors ? ( $changed_colors ? [ $changed_colors . ' changed', 'is-on' ] : [ 'The theme’s own', '' ] ) : null,
          [ 'Look' => $look_screen, 'Customize' => $customize( 'section]=ms_colors' ) ] ],
        [ 'Menu bar', 'The bar at the top: which icons it has (Home, Menu, Search, Dark mode, Cart) and in what order, its color and opacity, its links and their hover color, its shadow and bevel.',
          $colors ? ( $changed_menu ? [ $changed_menu . ' changed', 'is-on' ] : [ 'The theme’s own', '' ] ) : null,
          [ 'Look' => $look_screen, 'Customize' => $customize( 'section]=ms_colors' ) ] ],
        [ 'Cards', 'The boxes with a rounded outline - sidebar boxes, “See also”, the price card, the shop’s boxes: fill, outline, thickness, rounding.',
          $card ? ( $card_own ? [ 'Your own', 'is-on' ] : [ 'The theme’s own', '' ] ) : null, [ 'Look' => $look_screen ] ],
        [ 'Dark mode', 'A dark version of the site - automatic when a visitor’s device is dark, and a sun / moon switch in the menu bar. Off / Switch only / Automatic + switch / Automatic only: the choice at the top of Look; the dark look is edited with the same controls (the Dark look button). An image can have a dark version too (the sketch on the About page).',
          function_exists( 'monospace_dark_settings' ) ? ( monospace_dark_settings()['on'] ? [ 'On for visitors', 'is-on' ] : [ 'Off for visitors', 'is-off' ] ) : null, [ 'Look' => $look_screen, 'Dark look' => $look_screen . '&amp;mode=dark' ] ],
        [ 'Presets', 'The light look and the dark look each have a Factory default (the theme’s own - to start over) and three presets of your own. In Look’s Presets panel: Load puts one on the screen as the Current Look, Save keeps the Current Look in a preset - only Apply Light Mode / Apply Dark Mode changes the site.',
          [ $saved_looks . ' saved', $saved_looks !== '0 light · 0 dark' && $saved_looks !== '' ? 'is-on' : 'is-empty' ], [ 'Look' => $look_screen ] ],
        [ 'Logo', '“monospace ART” in two parts, each with its own color (separately for the light and the dark look) (the theme’s assets/images/logo/ - an .svg there replaces the image): one line or stacked (ART’s own size and place when stacked), and the tagline’s lines - each set apart for computers & tablets and for phones (type \\n in the tagline for a new line).',
          function_exists( 'monospace_logo_stacked' ) ? [ monospace_logo_stacked() ? 'Phones: stacked' : 'Phones: one line', '' ] : null, [ 'Look' => $look_screen ] ],
        [ 'Artwork captions', 'The pill with an artwork’s title under its image: shape (like the cards, pill, square, no box), fill, outline, text color, font, size, italic, bold, shadow.',
          function_exists( 'monospace_caption_style' ) ? ( monospace_caption_style() == monospace_caption_defaults() ? [ 'The theme’s own', '' ] : [ 'Your own', 'is-on' ] ) : null, [ 'Look' => $look_screen ] ],
        [ 'Banners', 'How the Top content areas look - banners and announcements: plain, card, filled, outline or stripe, color, alignment, size. One style for the whole site; a post, page, category or tag can override it in its own Top content, with dates and a close button (the Home Page’s banner too; the Layout Manager’s areas keep only the dates and close button).',
          function_exists( 'monospace_banner_defaults' ) ? [ ucfirst( monospace_banner_defaults()['style'] ), monospace_banner_defaults()['style'] === 'plain' ? '' : 'is-on' ] : null, [ 'Look' => $look_screen ] ],
        [ 'Sharing icons', 'The share icon on the right of an artwork’s caption, a page’s title, and a category, tag or Art Feed header: the device’s share sheet, or Copy link / Pinterest / Facebook / Bluesky / Email, plus own Facebook / Instagram / Bluesky buttons on computers. A master switch, which services, which kinds of page - and a post, page, category or tag can show or hide it itself (Use the Look setting / Show / Hide).',
          function_exists( 'monospace_share_summary' ) ? ( monospace_share_summary() === 'Off' ? [ 'Off', 'is-off' ] : [ monospace_share_summary(), 'is-on' ] ) : null, [ 'Look' => $look_screen ] ],
        [ 'Placeholder picture', 'The picture shown wherever a post, artwork or product has no picture of its own - grids, detail rows, “See also”, the Art Filter, price cards and WooCommerce’s product lists and cart. Choose it here; until then it is art_placeholder.png from the media library.',
          function_exists( 'monospace_placeholder_image_id' ) ? ( (int) get_option( 'monospace_placeholder_image', 0 ) ? [ 'Your own', 'is-on' ] : [ 'The default', '' ] ) : null, [ 'Look' => $look_screen ] ],
        [ 'Rounding', 'How round the corners are - one site-wide value for cards, home page boxes and images, and images on their own (cards and home page boxes can have their own in their panels).',
          function_exists( 'monospace_rounding' ) ? ( monospace_rounding()['site'] !== '' || monospace_rounding()['images'] !== '' ? [ 'Your own', 'is-on' ] : [ 'The theme’s own', '' ] ) : null, [ 'Look' => $look_screen ] ],
        [ 'Secondary buttons', 'The quieter buttons - Older / Newer, the sidebar menus, “More posts”: pressed in, flat or outlined, and their color.',
          function_exists( 'monospace_secondary_buttons' ) ? [ ucfirst( monospace_secondary_buttons()['style'] ), monospace_secondary_buttons_vars() ? 'is-on' : '' ] : null, [ 'Look' => $look_screen ] ],
        [ 'Sidebar', 'Where the sidebar goes on every page: left or right of the content on computers, above or below it on phones and tablets (below: none on the pages that load more as you scroll).',
          function_exists( 'monospace_sidebar_position' ) ? ( monospace_sidebar_position() === [ 'desktop' => 'right', 'phone' => 'above' ] ? [ 'Right · above', '' ] : [ ucfirst( monospace_sidebar_position()['desktop'] ) . ' · ' . monospace_sidebar_position()['phone'], 'is-on' ] ) : null, [ 'Look' => $look_screen ] ],
        [ 'Shadows', 'Every drop shadow at once - on or off, how strong, and its color; a light color in the dark look makes a glow.',
          function_exists( 'monospace_shadows' ) ? ( monospace_shadows()['on'] === 'no' ? [ 'Off', 'is-off' ] : [ monospace_shadows()['strength'] . '%', monospace_shadows_vars() ? 'is-on' : '' ] ) : null, [ 'Look' => $look_screen ] ],
        [ 'Fonts & text size', 'Body text, headings, menus and buttons, prices and numbers; text size and line spacing.',
          $roles ? ( $changed_fonts || get_theme_mod( 'ms_font_size' ) || get_theme_mod( 'ms_line_height' ) ? [ 'Changed', 'is-on' ] : [ 'The theme’s own', '' ] ) : null,
          [ 'Look' => $look_screen, 'Customize' => $customize( 'section]=ms_fonts' ) ] ],
        [ 'Spotlight', 'The soft light behind every page - where it falls, how far it reaches, its colors, per device.',
          $spot_state ?: null, [ 'Look' => $look_screen ] ],
        [ 'Effects', ucfirst( $effects ) . '.',
          null, [ 'Look' => $look_screen ] ],
        [ 'Home page', 'The rows of link boxes and text boxes, their order on phones, the # links. A box can have its own style in dark mode too (Layout Manager, the box’s ☾ Dark mode section). A text box in the windowed look (like News) can have a link too, and then reacts to the hover effect like the link boxes; plain text (like Welcome) cannot. A box’s picture can be fixed, automatic (newest artwork behind its link) or random - a different picture each visit. The boxes\' style - title bar (black gloss, color gloss, solid color, solid color + outline, outline), shadow, font, text size and color, rounding - in Look; one box can have its own in the Layout Manager.',
          function_exists( 'monospace_home_box_style' ) ? [ monospace_home_box_bars()[ monospace_home_box_style()['bar'] ] . ( monospace_home_box_style()['shadow'] === 'off' ? ', no shadow' : '' ), monospace_home_box_style() == monospace_home_box_style_defaults() ? '' : 'is-on' ] : null,
          [ 'Look' => $look_screen, 'Layout Manager' => isset( $lm['home'] ) ? $url( $lm['home'][1] ) : '' ] ],
        [ 'Related posts', 'The “See also” pictures under an artwork: the heading, how many (and how many tags they must share), grid or stack (picture beside its text), and what each one shows (title, info, availability, prints, the green dot / M badges) - the same display options as the [artwork] shortcode. They follow the global Rollover effect (Art Feed).',
          function_exists( 'monospace_related_settings' ) ? [ monospace_related_settings()['limit'] . ' · ' . ( monospace_related_settings()['layout'] === 'stack' ? 'stack' : monospace_related_settings()['columns'] . ' columns' ), get_option( 'monospace_related' ) ? 'is-on' : '' ] : null, [ 'Look' => $look_screen ] ],
        [ 'Price card', 'The card that sells an artwork - artwork pages, phones, the tab it opens on, where the Badge sits (left, center, right), archive & feed - and the standalone card (title and picture in it, the print’s mockup on a print tab, a link to the artwork), with a live preview and the shortcode reference. Cards view on category pages: Look > Viewing toolbar.',
          function_exists( 'monospace_pc_standalone_settings' ) ? [ 'Own screen', 'is-on' ] : null, [ 'Price card' => $url( 'themes.php?page=monospace-price-card' ) ] ],
        [ 'Menus', 'What the menu bar at the top and the footer link to. Each link can have an icon (Instagram, Bluesky, Facebook, YouTube, Website, Email or your own image), drawn in the text color so it suits dark mode too. A Custom Link to /random-artwork opens a random artwork ([surprise_me] makes the button).',
          [ ( $menu_name( 'primary' ) ?: 'No menu bar menu' ) . ' · ' . ( $menu_name( 'footer' ) ?: 'no footer menu' ), $menu_name( 'primary' ) ? 'is-on' : 'is-warn' ],
          [ 'Menus' => $url( 'nav-menus.php' ), 'Customize' => $customize( 'panel]=nav_menus' ) ] ],
        [ 'Headers & sidebars', 'What sits above the artworks and in the sidebar, for each kind of page; a post or page can override it in its "This page’s layout" box. Each category’s view, starting sort, price cards and share image: Categories.',
          null, [ 'Layout Manager' => isset( $lm['headers'] ) ? $url( $lm['headers'][1] ) : '' ] ],
        [ 'Links & toolbar', 'The logo link, the empty cart’s shop link, views and the viewing toolbar (list, grid, detail list, price card: toolbar on or off, the default view, the grid view’s columns (computers and phones - three on phones, like Instagram), the Art Feed’s own, text in the detail list - each category can differ), opening posts at the content.',
          null, [ 'Layout Manager' => isset( $lm['links'] ) ? $url( $lm['links'][1] ) : '' ] ],
        [ 'Shortcodes', 'Every shortcode of the site with its options and copyable examples - a section of the Layout Manager next to Home Page, Headers & Sidebars and Links & toolbar.',
          null, [ 'Layout Manager' => isset( $lm['shortcodes'] ) ? $url( $lm['shortcodes'][1] ) : '' ] ],
        [ 'Ko-fi buttons', 'The floating Ko-fi button looks like the scroll-to-top button (bottom left, the text slides out on hover); [kofi] and the Ko-fi block use the site’s button style. Each opens Ko-fi’s donation page in a panel on the page. Which pages show the floating button, the texts and the Ko-fi page: the Ko-fi plugin’s settings.',
          function_exists( 'monospace_kofi_settings' ) && class_exists( 'Ko_Fi' ) ? ( ( get_option( 'ko_fi_options' )['coffee_floating_button_display'] ?? '' ) === 'all' ? [ 'Floating: all pages', 'is-on' ] : [ 'Floating: chosen pages', '' ] ) : null,
          [ 'Ko-fi' => $url( 'options-general.php?page=ko_fi_options' ) ] ],
        [ 'Site title, tagline & icon', 'The tagline under the logo, the browser tab icon. The logo itself: Logo, on the Look screen.',
          [ get_bloginfo( 'name' ), '' ], [ 'Customize' => $customize( 'section]=title_tagline' ) ] ],
        [ 'Extra CSS', 'Small style fixes on top of the theme, kept when the theme is updated.',
          trim( wp_get_custom_css() ) !== '' ? [ 'In use', 'is-on' ] : [ 'Empty', 'is-empty' ], [ 'Customize' => $customize( 'section]=custom_css' ) ] ],
    ];
    $on_look = array_search( 'Price card', array_column( $places, 0 ), true ); // the rows before the Price card are on the Look screen

    // The site's parts: the theme is appearance only; the rest is Core + the feature plugins
    if ( ! function_exists( 'get_plugins' ) ) require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $parts = [];
    foreach ( get_plugins() as $file => $p ) {
        if ( strpos( $file, 'monospace-' ) !== 0 ) continue;
        $parts[] = [ $p['Name'], $p['Description'], is_plugin_active( $file ) ];
    }
    if ( function_exists( 'monospace_lm_area_assets' ) ) monospace_lm_area_assets();
    ?>
    <div class="wrap ms-about lm-areas">
        <div class="ms-about-head">
            <?php if ( $theme->get_screenshot() ) : ?><img src="<?php echo esc_url( $theme->get_screenshot() ); ?>" alt="" width="240"><?php endif; ?>
            <div>
                <h1><?php echo esc_html( $theme->get( 'Name' ) ); ?> <span class="ms-about-ver">theme <?php echo esc_html( $theme->get( 'Version' ) ); ?></span></h1>
                <p class="ms-about-lead">The look of <a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></a>:
                    paintings on a canvas-textured page under a soft spotlight, a dark glass menu bar, the hand-drawn logo, and
                    type that reads like a gallery label - Oswald headings, Merriweather text, Courier for prices and numbers.</p>
                <p class="description">By <?php echo esc_html( $theme->get( 'Author' ) ); ?>. The theme is appearance only: artworks, prices, prints and
                    the editors are the site's own plugins (below), so the look can change without touching them.</p>
                <p><a class="button button-primary" href="<?php echo $url( 'themes.php?page=monospace-look' ); ?>">Change the look</a>
                   <a class="button" href="<?php echo $url( 'customize.php' ); ?>">Customize</a>
                   <?php if ( isset( $lm['home'] ) ) : ?><a class="button" href="<?php echo $url( $lm['home'][1] ); ?>">Layout Manager</a><?php endif; ?></p>
            </div>
        </div>

        <div class="lm-panels">
            <details class="lm-panel" open>
                <summary><span class="lm-t">Where to change what</span> <span class="lm-chip is-on"><?php echo count( $places ); ?> places</span>
                    <span class="lm-sub">Every part of the look, what it is set to now, and the screen that changes it - the look itself all in Appearance &gt; Look</span></summary>
                <div class="lm-body">
                    <table class="widefat striped ms-about-places">
                        <tbody>
                        <tr class="ms-about-group"><td colspan="3">On the Look screen - <a href="<?php echo $look_screen; ?>">Appearance &gt; Look</a>, with a live preview</td></tr>
                        <?php foreach ( $places as $n => [ $title, $desc, $state, $links ] ) : ?>
                            <?php if ( $n === $on_look ) : ?><tr class="ms-about-group"><td colspan="3">Elsewhere</td></tr><?php endif; ?>
                            <tr>
                                <td><strong><?php echo esc_html( $title ); ?></strong><br><span class="description"><?php echo esc_html( $desc ); ?></span></td>
                                <td><?php if ( $state ) : ?><span class="lm-chip <?php echo esc_attr( $state[1] ); ?>"><?php echo esc_html( $state[0] ); ?></span><?php endif; ?></td>
                                <td class="ms-about-links"><?php foreach ( array_filter( $links ) as $label => $href ) echo '<a href="' . $href . '">' . esc_html( $label ) . '</a> '; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>

            <?php if ( $colors && $roles ) : ?>
            <details class="lm-panel" open>
                <summary><span class="lm-t">The style now</span> <span class="lm-chip is-on"><?php echo esc_html( $look_name ?: 'The theme’s own' ); ?></span>
                    <span class="lm-sub">The background, buttons, a card, the colors and fonts in use - the block editor offers the same colors and fonts</span></summary>
                <div class="lm-body ms-about-style">
                    <?php if ( $look && function_exists( 'monospace_appearance_swatch' ) ) :
                        $cv = $card ? [ monospace_look_rgba( $card['card_bg'], $card['card_bg_a'] ), monospace_look_rgba( $card['card_border'], $card['card_border_a'] ), $card['card_width'], $card['card_radius'] ] : [ '#fff', '#ccc', 1, 8 ]; ?>
                        <div class="ms-about-look">
                            <figure><?php echo monospace_appearance_swatch( $look[1], $look[2], $look[3] ); ?><figcaption>Background &amp; buttons</figcaption></figure>
                            <figure><span class="ms-about-card" style="background-color:<?php echo esc_attr( $cv[0] ); ?>;border:<?php echo (int) $cv[2]; ?>px solid <?php echo esc_attr( $cv[1] ); ?>;border-radius:<?php echo (int) $cv[3]; ?>px">A card</span><figcaption>Cards</figcaption></figure>
                            <p><a class="button" href="<?php echo $look_screen; ?>">Change the look</a></p>
                        </div>
                    <?php endif; ?>
                    <div class="ms-about-swatches">
                        <?php foreach ( $colors as $mod => [ $label, , $default ] ) :
                            $c = (string) get_theme_mod( $mod, '' ) ?: $default; ?>
                            <figure><span style="background:<?php echo esc_attr( $c ); ?>"></span><figcaption><?php echo esc_html( $label ); ?><br><code><?php echo esc_html( $c ); ?></code></figcaption></figure>
                        <?php endforeach; ?>
                    </div>
                    <div class="ms-about-fonts">
                        <?php
                        $samples = [ 'ms_font_heading' => 'Hudson River, Evening', 'ms_font_body' => 'Gouache on paper, painted on location in Jersey City.',
                                     'ms_font_ui' => 'Shop · Work · Commissions · Add to cart', 'ms_font_mono' => '$450.00 · MNSPC-2026-P-0426' ];
                        $need = [];
                        foreach ( $roles as $mod => [ $label, , $default ] ) {
                            $name = (string) get_theme_mod( $mod, '' ) ?: $default;
                            if ( ! empty( $fonts[ $name ][1] ) ) $need[] = $fonts[ $name ][1];
                        }
                        $base = [ 'Courier+Prime:wght@400;700', 'Merriweather:wght@400;700', 'Open+Sans:wght@400;600', 'Oswald:wght@400;600' ]; ?>
                        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=<?php echo esc_attr( implode( '&family=', array_merge( $base, $need ) ) ); ?>&display=swap">
                        <?php foreach ( $roles as $mod => [ $label, , $default ] ) :
                            $name = (string) get_theme_mod( $mod, '' ) ?: $default; ?>
                            <div class="ms-about-font">
                                <span class="description"><?php echo esc_html( $label ); ?> · <?php echo esc_html( $name ); ?></span>
                                <span class="ms-about-sample" style="font-family:<?php echo esc_attr( $fonts[ $name ][0] ?? 'inherit' ); ?>"><?php echo esc_html( $samples[ $mod ] ?? $name ); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </details>
            <?php endif; ?>

            <details class="lm-panel">
                <summary><span class="lm-t">The site's parts</span> <span class="lm-chip is-on"><?php echo count( array_filter( array_column( $parts, 2 ) ) ); ?> of <?php echo count( $parts ); ?> on</span>
                    <span class="lm-sub">The theme, monospace ART - Core (always on) and the feature plugins - each one switches its feature on or off site-wide</span></summary>
                <div class="lm-body">
                    <table class="widefat striped">
                        <tbody>
                            <tr><td><strong>monospace ART theme</strong><br><span class="description">The look - this page, Customize, Spotlight, Layout Manager.</span></td><td><span class="lm-chip is-on">On</span></td></tr>
                            <tr><td><strong>monospace ART - Core</strong><br><span class="description">Artworks (a post + its product), SKUs, availability, the price card, the artwork editors.</span></td><td><span class="lm-chip is-on">Always on</span></td></tr>
                            <?php foreach ( $parts as [ $name, $desc, $on ] ) : ?>
                                <tr><td><strong><?php echo esc_html( $name ); ?></strong><br><span class="description"><?php echo esc_html( wp_strip_all_tags( $desc ) ); ?></span></td>
                                    <td><span class="lm-chip <?php echo $on ? 'is-on' : 'is-off'; ?>"><?php echo $on ? 'On' : 'Off'; ?></span></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p><a href="<?php echo $url( 'plugins.php' ); ?>">Plugins</a> to switch a feature on or off.</p>
                </div>
            </details>

            <details class="lm-panel">
                <summary><span class="lm-t">Under the hood</span>
                    <span class="lm-sub">For whoever works on the theme's code</span></summary>
                <div class="lm-body">
                    <ul class="ul-disc">
                        <li><strong>Styles:</strong> SCSS in <code>assets/scss/</code>, compiled to <code>assets/css/style.css</code>. Colors and fonts are CSS variables (<code>--ms-text</code>, <code>--ms-heading</code>, <code>--ms-font-body</code> …) with the theme's values as defaults, so Customize changes them without a rebuild.</li>
                        <li><strong>Modules:</strong> one file each in <code>includes/modules/</code> (design, editor, front end), loaded in path order; a <code>disabled/</code> folder is skipped.</li>
                        <li><strong>The look in one place:</strong> Appearance &gt; Look (<code>design/look.php</code>) edits the settings where they live - the Look options, Customize's theme mods, the spotlight, the card variables, the design modules.</li>
                        <li><strong>The spotlight:</strong> <code>body::before</code> (the pool of light) and <code>body::after</code> (the shade with the beam cut out) in <code>_global.scss</code>; <code>spotlight.php</code> keeps the theme's values in <code>monospace_spotlight_theme()</code>.</li>
                        <li><strong>The logo:</strong> <code>assets/images/logo.svg</code>, drawn with <code>mix-blend-mode: multiply</code> - body keeps <code>z-index: 0</code> (Safari).</li>
                        <li><strong>The whole site</strong> is described in <code>ARCHITECTURE.md</code> (next to the site's folder, not on the web).</li>
                    </ul>
                </div>
            </details>
        </div>
    </div>
    <style>
        .ms-about { max-width: 1100px; }
        .ms-about-head { display: flex; gap: 24px; align-items: flex-start; margin: 10px 0 20px; }
        .ms-about-head img { flex: none; border: 1px solid #c3c4c7; border-radius: 6px; height: auto; }
        .ms-about-head h1 { padding-top: 0; }
        .ms-about-ver { font-size: 13px; font-weight: 400; color: #646970; margin-left: 6px; }
        .ms-about-lead { font-size: 15px; max-width: 720px; }
        @media (max-width: 782px) { .ms-about-head { flex-direction: column; } }
        .ms-about-places td { vertical-align: middle; }
        .ms-about-places td:nth-child(2) { width: 1%; white-space: nowrap; }
        .ms-about-links { width: 1%; white-space: nowrap; text-align: right; }
        .ms-about-links a + a { margin-left: 10px; }
        .ms-about-style { display: grid; gap: 18px; }
        .ms-about-swatches { display: flex; flex-wrap: wrap; gap: 14px; }
        .ms-about-look { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 18px; }
        .ms-about-look figure { margin: 0; font-size: 12px; text-align: center; }
        .ms-about-look figcaption { margin-top: 4px; color: #646970; }
        .ms-about-card { display: inline-flex; align-items: center; justify-content: center; width: 120px; height: 48px; box-sizing: border-box; font-size: 12px; background-image: none; }
        .ms-about-places .ms-about-group td { background: #f6f7f7; font-weight: 600; color: #50575e; padding-top: 6px; padding-bottom: 6px; }
        .ms-about-swatches figure { margin: 0; width: 92px; font-size: 12px; }
        .ms-about-swatches figure span { display: block; height: 40px; border-radius: 4px; border: 1px solid #c3c4c7; margin-bottom: 4px; }
        .ms-about-font { display: grid; gap: 2px; padding: 8px 0; border-top: 1px solid #f0f0f1; }
        .ms-about-sample { font-size: 20px; color: #1d2327; }
    </style>
    <?php
}

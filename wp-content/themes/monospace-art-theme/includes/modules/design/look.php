<?php
/**
 * Appearance > Look - the whole look of the site in one place, with a live preview (2026-10-21, owner: "a module that
 * allows me to select a background color/image/pattern, a color and/or style for the buttons, and the settings for
 * the spotlight, all in one place" + "also add fonts and site colors/link colors to that screen").
 *
 * Left: the site itself in a frame (computer / tablet / phone, a choice of pages), changing as the form changes;
 * drag in it to move the spotlight. Right: panels -
 *   Looks        the five ready-made looks (appearance.php presets) - picking one fills Background and Buttons
 *   Background   Canvas / Paper / Watercolor paper (textures), Cream / Studio (plain), your color, your image
 *                (media library; filling the screen or tiled)
 *   Buttons      Glass / Flat / Outline, and Blue / Black / Gray or your color (label black or white, whichever reads)
 *   Site colors text, secondary text, headings, links - each "Default" until changed
 *   Menu bar     its color and opacity, its links and their hover color, its shadow and bevel on / off (theme mods
 *                ms_menu_shadow, ms_menu_bevel)
 *   Cards        the white boxes with the rounded outline (sidebar boxes, "See also", the price card, shop boxes):
 *                fill color + opacity, outline color + opacity, thickness, rounding (the card-style mixin's
 *                variables --ms-card-*, appearance.php)
 *   Fonts & text body, headings, interface, prices & numbers; text size, line spacing
 *   Spotlight    on / off, position & size per device, colors (spotlight.php)
 *   Effects      the design modules of the Layout Manager's Look and Motion groups (price card, print preview,
 *                scroll effect, rollover effect) - their own forms and saving, shown here too (owner: "UI effects
 *                should be there as well"); they show on the site after saving (the preview reloads)
 * The parts keep their own home and code (owner: "keep the underlying idea - layout manager, spotlight, cards");
 * this screen is the one place to change the look.
 * One Save. What it writes - the same settings the other screens read, so nothing is stored twice:
 *   monospace_appearance (a Look's key when Background + Buttons match it, else 'custom'), monospace_appearance_bg /
 *   _buttons / _button_color, monospace_look (your color / image / button color, appearance.php), the theme mods of
 *   Customize (ms_color_*, ms_menu_bg_opacity, ms_font_*, ms_font_size, ms_line_height - customizer.php) and
 *   monospace_spotlight. Customize still edits colors and fonts too (same values); the Layout Manager's Appearance
 *   card links here.
 * The preview: body classes bg-* / btn-* / btn-color-* / appearance-* swapped in the frame, the CSS of
 * monospace_look_css() and monospace_spotlight_css() rebuilt in JS, and the color / font variables set on its
 * <html> (the saved #ms-customizer / #ms-look / #ms-spotlight styles are taken out first).
 * Replaces the Spotlight screen (2026-10-20) - its address redirects here.
 * Above the Save bar: the Presets panel - the look's Factory default and three presets: Load / Save (2026-10-22) - only the Save bar's
 * Apply Light Mode / Apply Dark Mode changes the site
 * (look-defaults.php, through the hooks monospace_look_saved_looks and monospace_look_saved).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
    $hook = add_theme_page( 'Look', 'Look', 'edit_theme_options', 'monospace-look', 'monospace_look_page' );
    add_action( "admin_print_scripts-$hook", 'wp_enqueue_media' );
} );

// The Spotlight screen's old address
add_action( 'admin_init', function () {
    if ( ( $_GET['page'] ?? '' ) === 'monospace-spotlight' ) {
        wp_safe_redirect( admin_url( 'themes.php?page=monospace-look' ) );
        exit;
    }
} );

// Saving
add_action( 'admin_post_monospace_look', function () {
    if ( ! current_user_can( 'edit_theme_options' ) ) wp_die( 'Not allowed.' );
    check_admin_referer( 'monospace_look' );
    $in = wp_unslash( $_POST );

    // Background and buttons
    $bgs    = monospace_appearance_backgrounds();
    $styles = monospace_appearance_button_styles();
    $colors = monospace_appearance_button_colors();
    $l      = (array) ( $in['look'] ?? [] );
    $look   = [
        'bg_color'   => sanitize_hex_color( $l['bg_color'] ?? '' ) ?: '#b3a492',
        'bg_image'   => absint( $l['bg_image'] ?? 0 ),
        'bg_fit'     => ( $l['bg_fit'] ?? '' ) === 'tile' ? 'tile' : 'cover',
        'btn_custom' => sanitize_hex_color( $l['btn_custom'] ?? '' ) ?: '#046bd2',
    ] + monospace_look_card_settings( $l );
    update_option( 'monospace_look', $look );
    $bg    = isset( $bgs[ $l['bg'] ?? '' ] ) ? $l['bg'] : 'canvas';
    if ( $bg === 'image' && ! $look['bg_image'] ) $bg = 'canvas'; // no image chosen
    $style = isset( $styles[ $l['btn_style'] ?? '' ] ) ? $l['btn_style'] : 'glass';
    $color = isset( $colors[ $l['btn_color'] ?? '' ] ) ? $l['btn_color'] : 'blue';
    $preset = 'custom';
    foreach ( monospace_appearance_presets() as $key => $p ) {
        if ( [ $p[1], $p[2], $p[3] ] === [ $bg, $style, $color ] ) { $preset = $key; break; }
    }
    update_option( 'monospace_appearance', $preset );
    update_option( 'monospace_appearance_bg', $bg );
    update_option( 'monospace_appearance_buttons', $style );
    update_option( 'monospace_appearance_button_color', $color );

    // Site colors (Customize's theme mods): kept only when changed from the theme's own
    if ( function_exists( 'monospace_customizer_colors' ) ) {
        foreach ( monospace_customizer_colors() as $mod => [ , , $default ] ) {
            $v = sanitize_hex_color( $in['colors'][ $mod ] ?? '' );
            if ( ! empty( $in['colors_set'][ $mod ] ) && $v && strtolower( $v ) !== strtolower( $default ) ) set_theme_mod( $mod, $v );
            else remove_theme_mod( $mod );
        }
        $op = max( 0, min( 100, (int) ( $in['colors']['ms_menu_bg_opacity'] ?? 85 ) ) );
        $op === 85 ? remove_theme_mod( 'ms_menu_bg_opacity' ) : set_theme_mod( 'ms_menu_bg_opacity', $op );
        ( $in['colors']['ms_menu_shadow'] ?? 'on' ) === 'off' ? set_theme_mod( 'ms_menu_shadow', 'off' ) : remove_theme_mod( 'ms_menu_shadow' );
        ( $in['colors']['ms_menu_bevel'] ?? 'on' ) === 'off' ? set_theme_mod( 'ms_menu_bevel', 'off' ) : remove_theme_mod( 'ms_menu_bevel' );
    }
    // Menu bar: its items and their order (design/menu-bar-items.php)
    if ( function_exists( 'monospace_save_menu_bar_items' ) ) monospace_save_menu_bar_items();
    // Fonts & text
    if ( function_exists( 'monospace_customizer_font_roles' ) ) {
        $fonts = monospace_customizer_fonts();
        foreach ( monospace_customizer_font_roles() as $mod => [ , , $default ] ) {
            $v = (string) ( $in['fonts'][ $mod ] ?? '' );
            isset( $fonts[ $v ] ) && $v !== $default ? set_theme_mod( $mod, $v ) : remove_theme_mod( $mod );
        }
        foreach ( [ 'ms_font_size' => monospace_customizer_sizes(), 'ms_line_height' => monospace_customizer_line_heights() ] as $mod => $choices ) {
            $v = (string) ( $in['fonts'][ $mod ] ?? '' );
            $v !== '' && isset( $choices[ $v ] ) ? set_theme_mod( $mod, $v ) : remove_theme_mod( $mod );
        }
    }
    // Spotlight
    if ( function_exists( 'monospace_spotlight_from_input' ) ) {
        update_option( 'monospace_spotlight', monospace_spotlight_from_input( (array) ( $in['spot'] ?? [] ), $bg ), true );
    }

    // Effects: each design module shown here saves its own fields
    foreach ( monospace_look_modules() as $module ) {
        if ( ! empty( $module['save'] ) && is_callable( $module['save'] ) ) call_user_func( $module['save'] );
    }

    do_action( 'monospace_look_saved' ); // e.g. Save as my default look (look-defaults.php)
    if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache();
    wp_safe_redirect( admin_url( 'themes.php?page=monospace-look&updated=1' ) );
    exit;
} );

/** The design modules shown on this screen: the Layout Manager's Look group (but Appearance - this screen is it) and
 *  its Motion group */
// The preview frame through a redirect (2026-10-21): a page that redirects - the retired /about/ to /about/about-me/,
// canonical fixes - lost ?ms_spot_preview, so the frame showed the ordinary page (its saved look, dark when the device is
// dark) and the unsaved changes looked wrong. The preview's own parameters go along to the new address on this site.
add_filter( 'wp_redirect', function ( $location ) {
    if ( is_admin() || empty( $_GET['ms_spot_preview'] ) || ! current_user_can( 'edit_theme_options' ) ) return $location;
    $host = wp_parse_url( $location, PHP_URL_HOST );
    if ( $host && $host !== wp_parse_url( home_url(), PHP_URL_HOST ) ) return $location;
    $keep = array_intersect_key( wp_unslash( $_GET ), array_flip( [ 'ms_spot_preview', 'ms_dark', 'ms_look_load' ] ) );
    return add_query_arg( array_map( 'rawurlencode', array_map( 'sanitize_text_field', $keep ) ), $location );
}, 99 );

function monospace_look_modules() {
    if ( ! function_exists( 'monospace_design_modules' ) ) return [];
    return array_filter( monospace_design_modules(), fn( $m, $key ) => $key !== 'appearance' && in_array( $m['group'] ?? 'look', [ 'look', 'motion' ], true ), ARRAY_FILTER_USE_BOTH );
}

function monospace_look_page() {
    // Light and Dark side by side, with the save buttons (look-shell.php); ?classic=1 = this single screen
    if ( function_exists( 'monospace_look_use_shell' ) && monospace_look_use_shell() ) { monospace_look_shell(); return; }
    [ $preset, $bg, $style, $color ] = monospace_appearance();
    $look     = monospace_look_settings();
    $presets  = monospace_appearance_presets();
    $bgs      = monospace_appearance_backgrounds();
    $styles   = monospace_appearance_button_styles();
    $bcolors  = monospace_appearance_button_colors();
    $img_url  = monospace_look_image_url();
    $img_thumb = $look['bg_image'] ? (string) wp_get_attachment_image_url( $look['bg_image'], 'medium' ) : '';

    $spot     = monospace_spotlight_settings();
    $devices  = monospace_spotlight_devices();
    $themes   = [];
    foreach ( array_keys( $bgs ) as $k ) $themes[ $k ] = monospace_spotlight_theme( $k );
    $theme    = $themes[ $bg ];
    $pos      = [];
    foreach ( $devices as $dev => $d ) $pos[ $dev ] = $spot['pos'][ $dev ] ?? $theme['pos'][ $dev ];
    $scolors  = $spot['colors'] ?: $theme['colors'];

    $site_colors = function_exists( 'monospace_customizer_colors' ) ? monospace_customizer_colors() : [];
    $roles       = function_exists( 'monospace_customizer_font_roles' ) ? monospace_customizer_font_roles() : [];
    $fonts       = function_exists( 'monospace_customizer_fonts' ) ? monospace_customizer_fonts() : [];

    // Pages to preview on
    $pages = [ home_url( '/' ) => 'Home page' ];
    $cat = get_term_by( 'slug', 'available', 'category' );
    if ( $cat ) $pages[ get_term_link( $cat ) ] = 'Category: ' . $cat->name;
    $recent = get_posts( [ 'numberposts' => 1, 'post_status' => 'publish', 'meta_key' => '_qp_product_id' ] );
    if ( $recent ) $pages[ get_permalink( $recent[0] ) ] = 'Artwork: ' . $recent[0]->post_title;
    $about = get_page_by_path( 'about' );
    if ( $about ) $pages[ get_permalink( $about ) ] = 'Page: ' . $about->post_title;
    $shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'cart' ) : '';
    if ( $shop ) $pages[ $shop ] = 'Cart (buttons)';

    if ( function_exists( 'monospace_lm_area_assets' ) ) monospace_lm_area_assets();
    // Each panel is kept aside as it's drawn, then all are printed in groups (2026-10-21, owner: "make the order more
    // intuitive") - see $groups below
    $out   = [];
    $panel_id = ''; // not $cur - the fonts panel uses that name
    // Every card starts closed (owner, 2026-10-23: "the Look page should always open with all the panels / cards closed") - $open is
    // kept so the calls don't change, but nothing opens a card until it is clicked
    $panel = function ( $id, $title, $sub, $open = false ) use ( &$panel_id ) {
        $panel_id = $id;
        ob_start();
        echo '<details class="lm-panel" data-panel="' . esc_attr( $id ) . '"><summary><span class="lm-t">' . esc_html( $title )
           . '</span> <span class="lm-chip" id="ms-look-chip-' . esc_attr( $id ) . '"></span><span class="lm-sub">' . esc_html( $sub ) . '</span></summary><div class="lm-body">';
    };
    $end = function () use ( &$out, &$panel_id ) { echo '</div></details>'; $out[ $panel_id ] = ob_get_clean(); };
    ?>
    <div class="wrap ms-spot ms-look">
        <h1>Look</h1>
        <?php do_action( 'monospace_look_top' ); // Light / Dark look (dark-mode.php) ?>
        <p class="lm-where">The look of the whole site in one place - background, buttons, colors, fonts and the spotlight - with the site itself as a live
            preview. The preview is the working site: scroll and click through it. Nothing changes on the site until you Apply (Light Mode / Dark Mode, at the bottom). A 🔗 panel is the same in light and dark.
            Where the logo and the shop button lead, and the viewing toolbar: <a href="<?php echo esc_url( admin_url( 'admin.php?page=monospace-layout-manager&tab=links' ) ); ?>">Layout Manager &gt; Links &amp; toolbar</a>.</p>
        <?php if ( isset( $_GET['updated'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p>The look is applied - the site shows it now.</p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="lm-areas" id="ms-look-form">
            <input type="hidden" name="action" value="monospace_look">
            <?php wp_nonce_field( 'monospace_look' ); ?>
            <div class="ms-spot-grid">
                <div class="ms-spot-preview">
                    <div class="ms-spot-bar">
                        <span class="button-group" role="group" aria-label="Device">
                            <?php foreach ( $devices as $dev => $d ) : ?>
                                <button type="button" class="button<?php echo $dev === 'desktop' ? ' button-primary' : ''; ?>" data-dev="<?php echo esc_attr( $dev ); ?>"><?php echo esc_html( $d[0] ); ?></button>
                            <?php endforeach; ?>
                        </span>
                        <select id="ms-spot-url" aria-label="Page to preview">
                            <?php foreach ( $pages as $url => $label ) : ?>
                                <option value="<?php echo esc_url( add_query_arg( 'ms_spot_preview', 1, $url ) ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="ms-spot-stage">
                        <div class="ms-spot-frame">
                            <iframe id="ms-spot-iframe" title="Preview" src="<?php echo esc_url( add_query_arg( 'ms_spot_preview', 1, home_url( '/' ) ) ); ?>"></iframe>
                            <div class="ms-spot-drag" hidden title="Drag to move the spotlight"><span class="ms-spot-dot"></span></div>
                        </div>
                    </div>
                    <p class="description">The preview works like the site, with your unsaved changes: scroll, hover and click through it (nothing is added to a cart or sent). To place the spotlight by dragging, open the Spotlight panel and tick “Move the spotlight by dragging”.</p>
                </div>

                <div class="lm-panels">
                    <?php ob_start(); // anything drawn between the panels (printed after them) ?>
                    <?php $panel( 'looks', 'Looks', 'Ready-made combinations - pick one, then change anything below' ); ?>
                        <div class="ms-look-grid">
                            <?php foreach ( $presets as $key => $p ) : ?>
                                <label class="ms-look-card"><input type="radio" name="look_preset" value="<?php echo esc_attr( $key ); ?>" <?php checked( $preset, $key ); ?>
                                    data-bg="<?php echo esc_attr( $p[1] ); ?>" data-style="<?php echo esc_attr( $p[2] ); ?>" data-color="<?php echo esc_attr( $p[3] ); ?>">
                                    <?php echo monospace_appearance_swatch( $p[1], $p[2], $p[3] ); ?><span><?php echo esc_html( preg_replace( '/ \(current\)$/', '', $p[0] ) ); ?></span></label>
                            <?php endforeach; ?>
                            <label class="ms-look-card"><input type="radio" name="look_preset" value="custom" <?php checked( $preset, 'custom' ); ?>><span class="ms-look-custom">Your own<br>mix</span><span>Custom</span></label>
                        </div>
                    <?php $end(); ?>

                    <?php $panel( 'bg', 'Background', 'Behind every page - the spotlight shines on it' ); ?>
                        <div class="ms-look-grid">
                            <?php foreach ( $bgs as $key => [ $label, $desc, $swatch ] ) : ?>
                                <label class="ms-look-card" title="<?php echo esc_attr( $desc ); ?>"><input type="radio" name="look[bg]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $bg, $key ); ?>>
                                    <span class="ms-look-sw" id="ms-look-sw-<?php echo esc_attr( $key ); ?>" style="background:<?php echo esc_attr( $swatch ); ?>"></span><span><?php echo esc_html( $label ); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <div class="ms-look-sub" data-for-bg="color image">
                            <label>Color <input type="color" name="look[bg_color]" id="ms-look-bg-color" value="<?php echo esc_attr( $look['bg_color'] ); ?>"></label>
                            <span class="description" data-for-bg="image">- shows while the image loads, and around a tiled one</span>
                        </div>
                        <div class="ms-look-sub" data-for-bg="image">
                            <input type="hidden" name="look[bg_image]" id="ms-look-bg-image" value="<?php echo (int) $look['bg_image']; ?>" data-url="<?php echo esc_attr( $img_url ); ?>">
                            <span class="ms-look-thumb" id="ms-look-thumb"<?php echo $img_thumb ? ' style="background-image:url(' . esc_url( $img_thumb ) . ')"' : ''; ?>></span>
                            <button type="button" class="button" id="ms-look-pick"><?php echo $img_thumb ? 'Change image' : 'Choose image'; ?></button>
                            <label><input type="radio" name="look[bg_fit]" value="cover" <?php checked( $look['bg_fit'], 'cover' ); ?>> Fill the screen</label>
                            <label><input type="radio" name="look[bg_fit]" value="tile" <?php checked( $look['bg_fit'], 'tile' ); ?>> Tile (a pattern)</label>
                        </div>
                    <?php $end(); ?>

                    <?php $panel( 'buttons', 'Buttons', 'Add to cart, checkout, forms, Subscribe - every button that does something' ); ?>
                        <p class="lm-label">Style</p>
                        <div class="ms-look-grid">
                            <?php foreach ( $styles as $key => [ $label, $desc ] ) : ?>
                                <label class="ms-look-card" title="<?php echo esc_attr( $desc ); ?>"><input type="radio" name="look[btn_style]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $style, $key ); ?>>
                                    <span class="ms-look-btn-wrap"><?php echo monospace_appearance_button_sample( $key, $bcolors[ $color ][1], $color === 'custom' ? monospace_look_text_on( $bcolors[ $color ][1] ) : '#fff', ' data-style="' . esc_attr( $key ) . '"' ); ?></span><span><?php echo esc_html( $label ); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                        <p class="lm-label">Color</p>
                        <div class="ms-look-colors">
                            <?php foreach ( $bcolors as $key => [ $label, $c ] ) : ?>
                                <label><input type="radio" name="look[btn_color]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $color, $key ); ?>>
                                    <?php if ( $key === 'custom' ) : ?>
                                        <input type="color" name="look[btn_custom]" id="ms-look-btn-custom" value="<?php echo esc_attr( $look['btn_custom'] ); ?>" aria-label="Your button color">
                                    <?php else : ?>
                                        <span class="ms-look-dot" style="background:<?php echo esc_attr( $c ); ?>"></span>
                                    <?php endif; ?>
                                    <?php echo esc_html( $label ); ?></label>
                            <?php endforeach; ?>
                        </div>
                    <?php $end(); ?>

                    <?php
                    $menu_mods = [ 'ms_color_menu_bg', 'ms_color_menu_link', 'ms_color_menu_link_hover' ];
                    foreach ( [ 'colors' => [ 'Site colors', 'Text, headings and links', false ], 'menu' => [ 'Menu bar', 'The bar at the top: its color, see-through-ness and links', true ] ] as $pid => [ $ptitle, $psub, $is_menu ] ) :
                    if ( ! $site_colors ) break;
                    $panel( $pid, $ptitle, $psub, false ); ?>
                        <?php foreach ( $site_colors as $mod => [ $label, $var, $default ] ) :
                            if ( in_array( $mod, $menu_mods, true ) !== $is_menu ) continue;
                            $saved = (string) get_theme_mod( $mod, '' );
                            $label = $is_menu ? ( [ 'ms_color_menu_bg' => 'Color', 'ms_color_menu_link' => 'Links', 'ms_color_menu_link_hover' => 'Links on hover' ][ $mod ] ?? $label ) : $label; ?>
                            <div class="ms-look-crow">
                                <label for="ms-c-<?php echo esc_attr( $mod ); ?>"><?php echo esc_html( $label ); ?></label>
                                <input type="color" id="ms-c-<?php echo esc_attr( $mod ); ?>" name="colors[<?php echo esc_attr( $mod ); ?>]" value="<?php echo esc_attr( $saved ?: $default ); ?>" data-mod="<?php echo esc_attr( $mod ); ?>" data-var="<?php echo esc_attr( $var ); ?>" data-default="<?php echo esc_attr( $default ); ?>">
                                <input type="hidden" name="colors_set[<?php echo esc_attr( $mod ); ?>]" value="<?php echo $saved !== '' ? 1 : 0; ?>">
                                <button type="button" class="button-link ms-look-default">Default</button>
                                <span class="description ms-look-state"></span>
                            </div>
                            <?php if ( $mod === 'ms_color_menu_bg' ) : $op = (int) get_theme_mod( 'ms_menu_bg_opacity', 85 ); ?>
                                <div class="ms-look-crow">
                                    <label for="ms-c-opacity">Opacity</label>
                                    <input type="range" id="ms-c-opacity" name="colors[ms_menu_bg_opacity]" min="0" max="100" step="5" value="<?php echo $op; ?>">
                                    <output for="ms-c-opacity"><?php echo $op; ?>%</output>
                                    <span class="description">theme: 85%</span>
                                </div>
                                <div class="ms-look-crow">
                                    <span>Shadow</span>
                                    <label style="grid-column:2 / 5"><input type="hidden" name="colors[ms_menu_shadow]" value="off"><input type="checkbox" id="ms-c-shadow" name="colors[ms_menu_shadow]" value="on" <?php checked( get_theme_mod( 'ms_menu_shadow', '' ) !== 'off' ); ?>> A soft shadow under the bar and its drop-down menus</label>
                                </div>
                                <div class="ms-look-crow">
                                    <span>Bevel</span>
                                    <label style="grid-column:2 / 5"><input type="hidden" name="colors[ms_menu_bevel]" value="off"><input type="checkbox" id="ms-c-bevel" name="colors[ms_menu_bevel]" value="on" <?php checked( get_theme_mod( 'ms_menu_bevel', '' ) !== 'off' ); ?>> A glassy highlight along the bar’s top and bottom edges - off: a flat bar</label>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ( $is_menu && function_exists( 'monospace_render_menu_bar_items_panel' ) ) monospace_render_menu_bar_items_panel(); ?>
                    <?php $end(); endforeach; ?>

                    <?php $c = $look; $cd = monospace_look_card_defaults(); $panel( 'cards', 'Cards', 'The boxes with a rounded outline - sidebar boxes, “See also”, the price card, the shop’s boxes', false ); ?>
                        <div class="ms-look-crow">
                            <label for="ms-card-bg">Fill</label>
                            <input type="color" id="ms-card-bg" name="look[card_bg]" value="<?php echo esc_attr( $c['card_bg'] ); ?>">
                            <input type="range" id="ms-card-bg-a" name="look[card_bg_a]" min="0" max="100" step="5" value="<?php echo (int) $c['card_bg_a']; ?>" aria-label="Fill opacity">
                            <output for="ms-card-bg-a"></output>
                        </div>
                        <div class="ms-look-crow">
                            <label for="ms-card-border">Outline</label>
                            <input type="color" id="ms-card-border" name="look[card_border]" value="<?php echo esc_attr( $c['card_border'] ); ?>">
                            <input type="range" id="ms-card-border-a" name="look[card_border_a]" min="0" max="100" step="5" value="<?php echo (int) $c['card_border_a']; ?>" aria-label="Outline opacity">
                            <output for="ms-card-border-a"></output>
                        </div>
                        <div class="ms-look-crow">
                            <label for="ms-card-width">Outline thickness</label>
                            <input type="range" id="ms-card-width" name="look[card_width]" min="0" max="6" value="<?php echo (int) $c['card_width']; ?>" data-unit="px">
                            <output for="ms-card-width"></output>
                        </div>
                        <div class="ms-look-crow">
                            <label for="ms-card-radius">Corner rounding</label>
                            <input type="range" id="ms-card-radius" name="look[card_radius]" min="0" max="30" value="<?php echo (int) $c['card_radius']; ?>" data-unit="px">
                            <output for="ms-card-radius"></output>
                        </div>
                        <p><span class="ms-look-card-demo" id="ms-card-demo">A card</span>
                           <button type="button" class="button-link" id="ms-card-reset" data-defaults="<?php echo esc_attr( wp_json_encode( $cd ) ); ?>">The theme’s own</button></p>
                        <p class="description">Opacity: 100% = solid; lower lets the background and spotlight show through. Rounding left at the theme's 8px follows the Rounding panel's site-wide value.</p>
                    <?php $end(); ?>

                    <?php if ( $roles ) : $panel( 'fonts', 'Fonts & text', 'Google Fonts beyond the theme’s own load only when chosen', false ); ?>
                        <?php foreach ( $roles as $mod => [ $label, , $default ] ) :
                            $cur = (string) get_theme_mod( $mod, '' ) ?: $default; ?>
                            <div class="ms-look-crow">
                                <label for="ms-f-<?php echo esc_attr( $mod ); ?>"><?php echo esc_html( $label ); ?></label>
                                <select id="ms-f-<?php echo esc_attr( $mod ); ?>" name="fonts[<?php echo esc_attr( $mod ); ?>]" data-mod="<?php echo esc_attr( $mod ); ?>" data-default="<?php echo esc_attr( $default ); ?>">
                                    <?php foreach ( $fonts as $name => $f ) : ?>
                                        <option value="<?php echo esc_attr( $name ); ?>" <?php selected( $cur, $name ); ?>><?php echo esc_html( $name . ( $name === $default ? ' (theme)' : '' ) ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                        <?php foreach ( [ 'ms_font_size' => [ 'Text size', monospace_customizer_sizes() ], 'ms_line_height' => [ 'Line spacing', monospace_customizer_line_heights() ] ] as $mod => [ $label, $choices ] ) : ?>
                            <div class="ms-look-crow">
                                <label for="ms-f-<?php echo esc_attr( $mod ); ?>"><?php echo esc_html( $label ); ?></label>
                                <select id="ms-f-<?php echo esc_attr( $mod ); ?>" name="fonts[<?php echo esc_attr( $mod ); ?>]">
                                    <?php foreach ( $choices as $v => $l ) : ?><option value="<?php echo esc_attr( $v ); ?>" <?php selected( (string) get_theme_mod( $mod, '' ), (string) $v ); ?>><?php echo esc_html( $l ); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    <?php $end(); endif; ?>

                    <?php $panel( 'on', 'Spotlight', 'The soft light behind every page - it stays put while the page scrolls' ); ?>
                        <label><input type="checkbox" name="spot[on]" value="1" id="ms-spot-on" <?php checked( $spot['on'] ); ?>> Show the spotlight</label>
                        <p class="description">Off: the plain background without the light.</p>
                        <p><label><input type="checkbox" id="ms-spot-dragmode"> Move the spotlight by dragging in the preview</label><br>
                        <span class="description">While ticked, the preview can't be scrolled or clicked - drag to place the light, then untick. It switches off when this panel is closed.</span></p>
                    <?php $end(); ?>

                    <?php $panel( 'pos', 'Spotlight: position & size', 'Separate for computers, tablets and phones - pick one above the preview', (bool) $spot['pos'] ); ?>
                        <?php foreach ( $devices as $dev => $d ) : ?>
                            <fieldset class="ms-spot-dev" data-dev="<?php echo esc_attr( $dev ); ?>"<?php echo $dev === 'desktop' ? '' : ' hidden'; ?>>
                                <p class="lm-label"><?php echo esc_html( $d[0] ); ?><?php echo $d[1] ? ' <span class="description">(' . esc_html( str_replace( [ '(max-width: ', ')' ], [ 'up to ', ' wide' ], $d[1] ) ) . ')</span>' : ''; ?></p>
                                <?php
                                $fields = [ [ 'Across', 0, 100, 'from the left edge of the screen' ], [ 'Down', 0, 100, 'from the top of the screen' ],
                                            [ 'Bright core', 0, 100, 'how much stays at full light' ], [ 'Reach', 10, 250, 'where the edges reach the shade' ],
                                            [ 'Light from', 0, 359, 'the direction the beam comes from, clockwise from straight above: 270 = left, 300 = 10 o’clock, 0 = above' ] ];
                                foreach ( $fields as $i => [ $label, $min, $max, $help ] ) :
                                    $id = "ms-spot-{$dev}-{$i}"; ?>
                                    <div class="ms-spot-row">
                                        <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
                                        <input type="range" id="<?php echo esc_attr( $id ); ?>" name="spot[pos][<?php echo esc_attr( $dev ); ?>][<?php echo $i; ?>]" min="<?php echo $min; ?>" max="<?php echo $max; ?>" value="<?php echo (int) $pos[ $dev ][ $i ]; ?>" data-dev="<?php echo esc_attr( $dev ); ?>" data-i="<?php echo $i; ?>">
                                        <output for="<?php echo esc_attr( $id ); ?>"></output>
                                        <span class="description"><?php echo esc_html( $help ); ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <p><button type="button" class="button-link ms-spot-theme-pos" data-dev="<?php echo esc_attr( $dev ); ?>">Use the theme’s own for <?php echo esc_html( strtolower( $d[0] ) ); ?>s</button></p>
                            </fieldset>
                        <?php endforeach; ?>
                    <?php $end(); ?>

                    <?php $panel( 'scolors', 'Spotlight: colors', 'The light in the middle and the shade at the edges', (bool) $spot['colors'] ); ?>
                        <p><label><input type="radio" name="spot[color_mode]" value="look" <?php checked( ! $spot['colors'] ); ?>> Follow the background <span class="description">(each texture has its own)</span></label><br>
                           <label><input type="radio" name="spot[color_mode]" value="custom" <?php checked( (bool) $spot['colors'] ); ?>> My own colors</label></p>
                        <div id="ms-spot-colors"<?php echo $spot['colors'] ? '' : ' hidden'; ?>>
                            <div class="ms-spot-row ms-spot-crow">
                                <label for="ms-spot-light">Light</label>
                                <input type="color" id="ms-spot-light" name="spot[light]" value="<?php echo esc_attr( $scolors[0] ); ?>">
                                <input type="range" id="ms-spot-light-a" name="spot[light_a]" min="0" max="100" value="<?php echo (int) $scolors[1]; ?>" aria-label="Light strength">
                                <output for="ms-spot-light-a"></output>
                            </div>
                            <div class="ms-spot-row ms-spot-crow">
                                <label for="ms-spot-shade">Shade</label>
                                <input type="color" id="ms-spot-shade" name="spot[shade]" value="<?php echo esc_attr( $scolors[2] ); ?>">
                                <input type="range" id="ms-spot-shade-a" name="spot[shade_a]" min="0" max="100" value="<?php echo (int) $scolors[3]; ?>" aria-label="Shade strength">
                                <output for="ms-spot-shade-a"></output>
                            </div>
                            <p class="description">The percentage is how strong each color is over the background.</p>
                        </div>
                    <?php $end(); ?>

                    <?php foreach ( monospace_look_modules() as $key => $module ) :
                        if ( empty( $module['render'] ) || ! is_callable( $module['render'] ) ) continue;
                        $summary = is_callable( $module['summary'] ?? null ) ? (string) call_user_func( $module['summary'] ) : ''; ?>
                        <?php ob_start(); ?>
                        <details class="lm-panel ms-look-module" data-panel="m-<?php echo esc_attr( $key ); ?>">
                            <summary><span class="lm-t"><?php echo esc_html( $module['title'] ); ?></span>
                                <?php if ( $summary !== '' ) : ?><span class="lm-chip<?php echo stripos( $summary, 'off' ) === 0 ? ' is-off' : ' is-on'; ?>"><?php echo esc_html( $summary ); ?></span><?php endif; ?>
                                <span class="lm-sub"><?php echo esc_html( $module['description'] ?? '' ); ?></span></summary>
                            <div class="lm-body"><?php call_user_func( $module['render'] ); ?>
                            </div>
                        </details>
                        <?php $out[ 'm-' . $key ] = ob_get_clean(); ?>
                    <?php endforeach; ?>

                    <?php
                    $between = ob_get_clean();
                    // The panels in groups: the starting point, the page as a whole, its top, the spotlight, the artworks,
                    // movement. A panel not named here (a new module) goes under More.
                    $out = apply_filters( 'monospace_look_panels', $out ); // each panel's light / dark link (look-links.php)
                    $groups = [
                        'Start from'      => [ 'looks' ],
                        'The page'        => [ 'bg', 'colors', 'fonts', 'buttons', 'm-secondary-buttons', 'cards', 'm-rounding', 'm-shadows', 'm-sidebar-position' ],
                        'Top of the page' => [ 'm-logo-layout', 'menu', 'm-banner-style' ],
                        'Spotlight'       => [ 'on', 'pos', 'scolors' ],
                        'Artworks'        => [ 'm-home-box-style', 'm-caption-style', 'm-share', 'm-print-preview', 'm-price-card', 'm-related-posts' ],
                        'More'            => [],
                        'Effects'         => [ 'm-scroll-effect', 'm-rollover-effect', 'm-image-fade' ],
                    ];
                    $groups['More'] = array_values( array_diff( array_keys( $out ), array_merge( ...array_values( $groups ) ) ) );
                    foreach ( $groups as $heading => $ids ) {
                        $html = '';
                        foreach ( $ids as $id ) $html .= $out[ $id ] ?? '';
                        if ( $html !== '' ) echo '<h2 class="ms-look-group">' . esc_html( $heading ) . '</h2>' . $html;
                    }
                    echo $between;
                    if ( has_action( 'monospace_look_saved_looks' ) ) echo '<h2 class="ms-look-group">Presets</h2>';
                    ?>

                    <?php do_action( 'monospace_look_saved_looks' ); // Presets: Factory default + three presets of the look on the screen (look-defaults.php) ?>

                    <p class="submit ms-look-save"><?php
                        $dark_edit = function_exists( 'monospace_dark_editing' ) && monospace_dark_editing();
                        submit_button( $dark_edit ? 'Apply Dark Mode' : 'Apply Light Mode', 'primary', 'submit', false ); // 2026-10-22, owner
                        do_action( 'monospace_look_save_buttons' ); // hidden fields (dark-mode.php) ?>
                        <span class="description">Everything on this screen<?php echo function_exists( 'monospace_dark_settings' ) ? ' (dark mode for visitors too)' : ''; ?> goes live on the site straight away. To keep a copy to come back to: Presets, above.</span></p>
                </div>
            </div>
        </form>
    </div>

    <style>
        .ms-spot-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(340px, 440px); gap: 24px; align-items: start; max-width: 1500px; }
        @media (max-width: 1100px) { .ms-spot-grid { grid-template-columns: minmax(0, 1fr); } }
        .ms-spot-preview { position: sticky; top: 46px; }
        @media (max-width: 1100px) { .ms-spot-preview { position: static; } }
        /* Room under the last panel, to see it open or close */
        .ms-look { padding-bottom: 60px; }
        .ms-spot-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; margin-bottom: 8px; }
        .ms-spot-stage { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; padding: 10px; overflow: hidden; }
        .ms-spot-frame { position: relative; margin: 0 auto; overflow: hidden; border-radius: 3px; box-shadow: 0 0 0 1px #dcdcde; }
        .ms-spot-frame iframe { position: absolute; top: 0; left: 0; border: 0; transform-origin: 0 0; background: #fff; }
        .ms-spot-drag { position: absolute; inset: 0; cursor: crosshair; touch-action: none; }
        .ms-spot-dot { position: absolute; width: 22px; height: 22px; margin: -11px 0 0 -11px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 2px #2271b1, 0 1px 4px rgba(0,0,0,.4); background: rgba(34,113,177,.25); pointer-events: none; }
        .ms-spot-dev[hidden], #ms-spot-colors[hidden], .ms-look-sub[hidden] { display: none; }
        .ms-spot-dev { border: 0; padding: 0; margin: 0; }
        .ms-spot-row { display: grid; grid-template-columns: 90px minmax(0, 1fr) 52px; gap: 2px 10px; align-items: center; margin: 6px 0; }
        .ms-spot-row .description { grid-column: 2 / 4; margin-top: -4px; font-size: 12px; }
        .ms-spot-crow { grid-template-columns: 90px 44px minmax(0, 1fr) 44px; }
        .ms-look input[type=color] { width: 40px; height: 28px; padding: 0 2px; vertical-align: middle; }
        .ms-spot-row output, .ms-look-crow output { font-variant-numeric: tabular-nums; color: #50575e; }
        .ms-look-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(92px, 1fr)); gap: 8px; }
        .ms-look-card { position: relative; display: grid; gap: 4px; justify-items: center; text-align: center; font-size: 12px; cursor: pointer; padding: 6px; border: 2px solid transparent; border-radius: 6px; }
        .ms-look-card:has(input:checked) { border-color: #2271b1; background: #f0f6fc; }
        .ms-look-card input { position: absolute; opacity: 0; }
        .ms-look-card:has(input:focus-visible) { outline: 2px solid #2271b1; outline-offset: 1px; }
        .ms-look-card > span:first-of-type > span, .ms-look-card > span[style*="width:120px"] { max-width: 100%; }
        .ms-look-card > span[style] { width: 100% !important; height: 44px !important; }
        .ms-look-sw, .ms-look-custom { display: block; width: 100%; height: 44px; border: 1px solid #c3c4c7; border-radius: 4px; box-sizing: border-box; background-size: cover; }
        .ms-look-custom { display: flex; align-items: center; justify-content: center; color: #646970; line-height: 1.2; background: repeating-linear-gradient(135deg, #f6f7f7 0 6px, #fff 6px 12px); }
        .ms-look-sub { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; margin: 12px 0 0; }
        .ms-look-thumb { width: 64px; height: 44px; border: 1px solid #c3c4c7; border-radius: 4px; background: #f6f7f7 center / cover; }
        .ms-look-btn-wrap { display: flex; align-items: center; justify-content: center; width: 100%; height: 44px; border: 1px solid #dcdcde; border-radius: 4px; background: #f6f7f7; box-sizing: border-box; }
        .ms-look-colors { display: flex; flex-wrap: wrap; gap: 8px 18px; align-items: center; }
        .ms-look-colors label { display: inline-flex; align-items: center; gap: 6px; }
        .ms-look-dot { width: 20px; height: 20px; border-radius: 50%; border: 1px solid rgba(0,0,0,.2); }
        .ms-look-crow { display: grid; grid-template-columns: 130px auto auto minmax(0, 1fr); gap: 4px 10px; align-items: center; margin: 7px 0; }
        .ms-look-crow select { max-width: 100%; grid-column: 2 / 5; }
        .ms-look-state { font-size: 12px; }
        .ms-look-crow input[type=range] { width: 100%; }
        .ms-look-card-demo { display: inline-block; padding: 14px 22px; margin-right: 14px; background: #fff; border: 1px solid #ccc; border-radius: 8px; font-size: 12px; }
        #ms-card-demo { background-image: url(<?php echo esc_url( get_template_directory_uri() . '/assets/images/paper.jpeg' ); ?>); background-blend-mode: normal; }
        /* One look for every panel (2026-10-22, owner: "unify the UI/UX for all the panels on the Look page"): labels in a
           120px column on the left with a thin line between rows, the same sizes for color swatches, sliders (with their
           value beside them) and drop-downs, one style for reset links and the gray notes */
        .ms-look .lm-body .form-table { margin: 0; }
        .ms-look .lm-body .form-table th { width: 120px; padding: 10px 12px 10px 0; font-weight: 600; vertical-align: top; }
        .ms-look .lm-body .form-table td { padding: 8px 0; vertical-align: top; }
        .ms-look .lm-body .form-table tr + tr > th, .ms-look .lm-body .form-table tr + tr > td { border-top: 1px solid #f0f0f1; }
        .ms-look .lm-body .description { margin: 4px 0 0; font-size: 12px; line-height: 1.5; color: #646970; }
        .ms-look .lm-body .form-table label { line-height: 1.7; }
        .ms-look .lm-body input[type=color] { width: 44px; height: 28px; padding: 2px; border: 1px solid #c3c4c7; border-radius: 4px; background: #fff; vertical-align: middle; cursor: pointer; }
        .ms-look .lm-body input[type=range] { width: 180px; max-width: 100%; vertical-align: middle; }
        .ms-look .lm-body output { display: inline-block; min-width: 3.2em; margin-left: 6px; font-variant-numeric: tabular-nums; color: #3c434a; }
        .ms-look .lm-body select { min-width: 200px; max-width: 100%; }
        .ms-look .lm-body .button-link:not(.button-link-delete) { margin-left: 6px; font-size: 12px; color: #2271b1; text-decoration: underline; vertical-align: middle; }
        /* Banners (banner-style.php, shared with the Layout Manager): the same label column here */
        .ms-look .msb-grid { display: block; margin: 0; }
        .ms-look .msb-f { display: grid; grid-template-columns: 120px minmax(0, 1fr); align-items: center; gap: 0 12px; padding: 8px 0; }
        .ms-look .msb-f + .msb-f { border-top: 1px solid #f0f0f1; }
        .ms-look .msb-f > span:first-child { font-weight: 600; }
        .ms-look .msb-check { grid-template-columns: 1fr; }
        .ms-look .msb-label { margin-top: 12px; }
        .ms-look-save { position: sticky; bottom: 0; background: #f0f0f1; padding: 10px 0 !important; margin: 0; z-index: 5; }
        .ms-look-save .description { margin-left: 10px; }
        .ms-look-group { margin: 18px 0 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #646970; }
        .ms-look-group:first-child { margin-top: 0; }
        /* Presets: each row wraps in the narrow panel column - name, then when, then the buttons (on the right) */
        .ms-saved-looks table, .ms-saved-looks tbody { display: block; width: 100%; box-sizing: border-box; }
        .ms-saved-looks tr { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; padding: 8px 10px; }
        .ms-saved-looks td { display: block; padding: 0; min-width: 0; }
        .ms-saved-looks td:first-child { flex: 1 1 140px; }
        .ms-saved-looks td.ms-sl-when { flex: 1 1 150px; color: #646970; font-size: 12px; }
        .ms-saved-looks td:last-child { flex: 0 0 auto; margin-left: auto; white-space: nowrap; }
        .ms-saved-looks input[type=text] { width: 100%; max-width: 200px; }
        .ms-saved-looks tr.ms-sl-loaded { background: #f0f6fc; box-shadow: inset 3px 0 #2271b1; } /* the look loaded on the screen */
    </style>
    <script>
    (function () {
        var devices = <?php echo wp_json_encode( $devices ); ?>,
            themes  = <?php echo wp_json_encode( $themes ); ?>,
            fonts   = <?php echo wp_json_encode( $fonts ); ?>,
            bcolors = <?php echo wp_json_encode( array_map( fn( $c ) => $c[1], $bcolors ) ); ?>,
            bgnames = <?php echo wp_json_encode( array_map( fn( $b ) => $b[0], $bgs ) ); ?>,
            snames  = <?php echo wp_json_encode( array_map( fn( $b ) => $b[0], $styles ) ); ?>,
            cnames  = <?php echo wp_json_encode( array_map( fn( $b ) => $b[0], $bcolors ) ); ?>,
            form    = document.getElementById('ms-look-form'),
            iframe  = document.getElementById('ms-spot-iframe'),
            frame   = document.querySelector('.ms-spot-frame'),
            stage   = document.querySelector('.ms-spot-stage'),
            drag    = document.querySelector('.ms-spot-drag'),
            dot     = document.querySelector('.ms-spot-dot'),
            dev     = 'desktop';

        function $(id) { return document.getElementById(id); }
        function val(name) { var el = form.querySelector('[name="' + name + '"]:checked'); return el ? el.value : ''; }
        function pos(d) { return [0, 1, 2, 3, 4].map(function (i) { return +$('ms-spot-' + d + '-' + i).value; }); }
        function rgba(hex, a) { var h = hex.replace('#', ''); return 'rgba(' + parseInt(h.substr(0, 2), 16) + ', ' + parseInt(h.substr(2, 2), 16) + ', ' + parseInt(h.substr(4, 2), 16) + ', ' + Math.round(a) / 100 + ')'; }
        function textOn(hex) { var h = hex.replace('#', ''), l = (0.299 * parseInt(h.substr(0, 2), 16) + 0.587 * parseInt(h.substr(2, 2), 16) + 0.114 * parseInt(h.substr(4, 2), 16)) / 255; return l > 0.62 ? '#111' : '#fff'; }
        function customSpot() { return val('spot[color_mode]') === 'custom'; }
        function theme() { return themes[val('look[bg]')] || themes.canvas; }
        function same(a, b) { return a.join() === b.join(); }
        function chip(id, text, cls) { var c = $('ms-look-chip-' + id); if (c) { c.textContent = text; c.className = 'lm-chip' + (cls ? ' ' + cls : ''); } }

        // The same CSS as monospace_look_css() and monospace_spotlight_css()
        function lookCss() {
            var bg = val('look[bg]'), out = '', img = $('ms-look-bg-image').dataset.url, c = $('ms-look-bg-color').value, b = $('ms-look-btn-custom').value;
            if (bg === 'color') out += 'body.bg-color{background-image:none;--page-color:' + c + '}';
            if (bg === 'image' && img) out += 'body.bg-image{background-image:url("' + img + '");--page-color:' + c + ';' + (val('look[bg_fit]') === 'tile' ? 'background-repeat:repeat;background-size:auto' : 'background-repeat:no-repeat;background-size:cover;background-position:center;background-attachment:fixed') + '}';
            if (val('look[btn_color]') === 'custom') out += 'body.btn-color-custom{--btn-color:' + b + ';--btn-text:' + textOn(b) + '}';
            return out;
        }
        function spotCss() {
            var sel = 'html body[class]', out = '';
            if (!$('ms-spot-on').checked) return sel + '::before,' + sel + '::after{display:none}';
            if (customSpot()) out += sel + '{--gradient-foreground:' + rgba($('ms-spot-light').value, $('ms-spot-light-a').value) + ';--gradient-background:' + rgba($('ms-spot-shade').value, $('ms-spot-shade-a').value) + '}';
            Object.keys(devices).forEach(function (d) {
                var p = pos(d), t = theme().pos[d];
                if (same(p, t)) return; // the theme's own: the background's (Studio reaches wider)
                var rule = sel + '{--gradient-center-x:' + p[0] + 'vw;--gradient-center-y:' + p[1] + 'vh;--gradient-start:' + p[2] + '%;--gradient-radius:' + p[3] + 'vw;--beam-angle:' + p[4] + 'deg}';
                out += devices[d][1] ? '@media ' + devices[d][1] + '{' + rule + '}' : rule;
            });
            return out;
        }

        // Cards: the card-style variables (appearance.php monospace_look_css(): the same values)
        function rgbaOr(hex, a) { return +a >= 100 ? hex : rgba(hex, a); }
        function cardVars() {
            return [['--ms-card-bg', rgbaOr($('ms-card-bg').value, $('ms-card-bg-a').value)], ['--ms-card-border', rgbaOr($('ms-card-border').value, $('ms-card-border-a').value)],
                    ['--ms-card-border-width', $('ms-card-width').value + 'px'],
                    // the theme's 8px = not set, so the cards follow Rounding's site-wide value (as on the site); '' removes it
                    ['--ms-card-radius', +$('ms-card-radius').value === +JSON.parse($('ms-card-reset').dataset.defaults).card_radius ? '' : $('ms-card-radius').value + 'px']];
        }

        function apply() {
            var bg = val('look[bg]'), style = val('look[btn_style]'), color = val('look[btn_color]'), preset = val('look_preset') || 'custom';
            try {
                var doc = iframe.contentDocument;
                if (doc && doc.head && doc.body) {
                    ['ms-customizer', 'ms-look', 'ms-spotlight'].forEach(function (id) { var s = doc.getElementById(id); if (s) s.remove(); });
                    doc.body.className = doc.body.className.split(/\s+/).filter(function (c) { return !/^(appearance|bg|btn)-/.test(c); })
                        .concat(['appearance-' + preset, 'bg-' + bg, 'btn-' + style, 'btn-color-' + color]).join(' ');
                    var st = doc.getElementById('ms-look-live');
                    if (!st) { st = doc.createElement('style'); st.id = 'ms-look-live'; doc.head.appendChild(st); }
                    st.textContent = lookCss() + spotCss();
                    // Site colors and fonts: the variables on <html>
                    var root = doc.documentElement.style, op = +$('ms-c-opacity').value;
                    form.querySelectorAll('input[type=color][data-var]').forEach(function (i) {
                        root.setProperty(i.dataset.var, i.dataset.mod === 'ms_color_menu_bg' ? rgba(i.value, op) : i.value);
                    });
                    form.querySelectorAll('select[data-mod]').forEach(function (s) {
                        var f = fonts[s.value], v = { ms_font_body: '--ms-font-body', ms_font_heading: '--ms-font-heading', ms_font_ui: '--ms-font-ui', ms_font_mono: '--ms-font-mono' }[s.dataset.mod];
                        if (!f || !v) return;
                        if (f[1] && !doc.getElementById('ms-font-' + s.value.replace(/\W/g, ''))) {
                            var l = doc.createElement('link'); l.rel = 'stylesheet'; l.id = 'ms-font-' + s.value.replace(/\W/g, '');
                            l.href = 'https://fonts.googleapis.com/css2?family=' + f[1] + '&display=swap'; doc.head.appendChild(l);
                        }
                        root.setProperty(v, f[0]);
                    });
                    cardVars().forEach(function (v) { root.setProperty(v[0], v[1]); });
                    [['ms-f-ms_font_size', '--ms-font-size'], ['ms-f-ms_line_height', '--ms-line-height']].forEach(function (p) { var v = $(p[0]).value; v ? root.setProperty(p[1], v) : root.removeProperty(p[1]); });
                    $('ms-c-shadow').checked ? root.removeProperty('--ms-menu-shadow') : root.setProperty('--ms-menu-shadow', 'none');
                    $('ms-c-bevel').checked ? root.removeProperty('--ms-menu-bevel') : root.setProperty('--ms-menu-bevel', 'none');
                }
            } catch (e) {}

            // The form: which sub-fields show, the swatches, the chips
            document.querySelectorAll('[data-for-bg]').forEach(function (el) { el.hidden = el.dataset.forBg.split(' ').indexOf(bg) < 0; });
            $('ms-look-sw-color').style.background = $('ms-look-bg-color').value;
            var bc = color === 'custom' ? $('ms-look-btn-custom').value : bcolors[color];
            // The style samples in the chosen color - the site's own button rules draw them (appearance.php)
            document.querySelectorAll('.ms-look-btn-wrap .ms-btn-sample').forEach(function (b) {
                b.style.setProperty('--btn-color', bc); b.style.setProperty('--btn-text', color === 'custom' ? textOn(bc) : '#fff');
            });
            var pl = form.querySelector('[name=look_preset]:checked');
            chip('looks', preset === 'custom' ? 'Custom' : pl.closest('label').lastElementChild.textContent.trim(), 'is-on');
            chip('bg', bgnames[bg] || bg, 'is-on');
            chip('buttons', snames[style] + ' · ' + cnames[color], 'is-on');
            var nc = 0, nm = 0;
            form.querySelectorAll('input[type=color][data-var]').forEach(function (i) {
                var on = i.value.toLowerCase() !== i.dataset.default.toLowerCase();
                i.nextElementSibling.value = on ? 1 : 0;
                i.parentNode.querySelector('.ms-look-state').textContent = on ? 'changed' : 'theme’s own';
                if (on) { if (/menu/.test(i.dataset.mod)) nm++; else nc++; }
            });
            if (+$('ms-c-opacity').value !== 85) nm++;
            if (!$('ms-c-shadow').checked) nm++;
            if (!$('ms-c-bevel').checked) nm++;
            chip('colors', nc ? nc + ' changed' : 'The theme’s own', nc ? 'is-on' : '');
            chip('menu', nm ? nm + ' changed' : 'The theme’s own', nm ? 'is-on' : '');
            var nf = 0;
            form.querySelectorAll('select[data-mod]').forEach(function (s) { if (s.value !== s.dataset.default) nf++; });
            if ($('ms-f-ms_font_size').value || $('ms-f-ms_line_height').value) nf++;
            chip('fonts', nf ? 'Changed' : 'The theme’s own', nf ? 'is-on' : '');
            var cv = cardVars(), demo = $('ms-card-demo'), cd = JSON.parse($('ms-card-reset').dataset.defaults);
            demo.style.backgroundColor = cv[0][1]; demo.style.border = cv[2][1] + ' solid ' + cv[1][1]; demo.style.borderRadius = cv[3][1];
            demo.style.backgroundImage = +$('ms-card-bg-a').value < 100 ? '' : 'none';
            var cardOwn = $('ms-card-bg').value !== cd.card_bg || +$('ms-card-bg-a').value !== cd.card_bg_a || $('ms-card-border').value !== cd.card_border
                       || +$('ms-card-border-a').value !== cd.card_border_a || +$('ms-card-width').value !== cd.card_width || +$('ms-card-radius').value !== cd.card_radius;
            chip('cards', cardOwn ? 'Your own' : 'The theme’s own', cardOwn ? 'is-on' : '');
            var on = $('ms-spot-on').checked, custom = Object.keys(devices).filter(function (d) { return !same(pos(d), theme().pos[d]); });
            chip('on', on ? 'On' : 'Off', on ? 'is-on' : 'is-off');
            chip('pos', custom.length ? 'Your own: ' + custom.map(function (d) { return devices[d][0].toLowerCase(); }).join(', ') : 'The theme’s own', custom.length ? 'is-on' : '');
            chip('scolors', customSpot() ? 'Your own' : 'From the background', customSpot() ? 'is-on' : '');
            $('ms-spot-colors').hidden = !customSpot();
            var p = pos(dev); dot.style.left = p[0] + '%'; dot.style.top = p[1] + '%'; dot.hidden = !on;
            form.querySelectorAll('input[type=range]').forEach(function (r) {
                var o = form.querySelector('output[for="' + r.id + '"]'); if (o && /^ms-(spot|card|c-)/.test(r.id)) o.textContent = r.value + (r.dataset.unit || (r.dataset.i === undefined ? '%' : ['vw', 'vh', '%', 'vw', '°'][r.dataset.i]));
            });
        }

        // A Look fills Background and Buttons; changing those picks the Look they match (else Custom)
        var lastBg = val('look[bg]');
        form.addEventListener('change', function (e) {
            var t = e.target;
            if (t.name === 'look_preset' && t.value !== 'custom') {
                form.querySelector('[name="look[bg]"][value="' + t.dataset.bg + '"]').checked = true;
                form.querySelector('[name="look[btn_style]"][value="' + t.dataset.style + '"]').checked = true;
                form.querySelector('[name="look[btn_color]"][value="' + t.dataset.color + '"]').checked = true;
            } else if (/^look\[(bg|btn_style|btn_color)\]$/.test(t.name)) {
                var match = Array.prototype.find.call(form.querySelectorAll('[name=look_preset][data-bg]'), function (r) {
                    return r.dataset.bg === val('look[bg]') && r.dataset.style === val('look[btn_style]') && r.dataset.color === val('look[btn_color]');
                });
                (match || form.querySelector('[name=look_preset][value=custom]')).checked = true;
            }
            // A new background: positions still at the old one's own follow the new one's own (Studio reaches wider)
            var bg = val('look[bg]');
            if (bg !== lastBg) {
                Object.keys(devices).forEach(function (d) {
                    if (same(pos(d), (themes[lastBg] || themes.canvas).pos[d])) theme().pos[d].forEach(function (v, i) { $('ms-spot-' + d + '-' + i).value = v; });
                });
                lastBg = bg;
            }
            apply();
        });
        form.addEventListener('input', apply);
        $('ms-look-btn-custom').addEventListener('input', function () { form.querySelector('[name="look[btn_color]"][value=custom]').checked = true; form.dispatchEvent(new Event('change')); });
        $('ms-look-bg-color').addEventListener('click', function () { if (val('look[bg]') !== 'image') { form.querySelector('[name="look[bg]"][value=color]').checked = true; form.dispatchEvent(new Event('change')); } });
        document.querySelectorAll('.ms-look-default').forEach(function (b) {
            b.addEventListener('click', function () { var i = b.parentNode.querySelector('input[type=color]'); i.value = i.dataset.default; apply(); });
        });
        $('ms-card-reset').addEventListener('click', function () {
            var d = JSON.parse(this.dataset.defaults);
            $('ms-card-bg').value = d.card_bg; $('ms-card-bg-a').value = d.card_bg_a; $('ms-card-border').value = d.card_border;
            $('ms-card-border-a').value = d.card_border_a; $('ms-card-width').value = d.card_width; $('ms-card-radius').value = d.card_radius; apply();
        });
        document.querySelectorAll('.ms-spot-theme-pos').forEach(function (b) {
            b.addEventListener('click', function () { theme().pos[b.dataset.dev].forEach(function (v, i) { $('ms-spot-' + b.dataset.dev + '-' + i).value = v; }); apply(); });
        });

        // Your image: the media library
        $('ms-look-pick').addEventListener('click', function () {
            if (!window.wp || !wp.media) return;
            var media = wp.media({ title: 'Background image', library: { type: 'image' }, multiple: false, button: { text: 'Use as background' } });
            media.on('select', function () {
                var a = media.state().get('selection').first().toJSON(), h = $('ms-look-bg-image');
                h.value = a.id; h.dataset.url = a.url;
                var th = (a.sizes && (a.sizes.medium || a.sizes.thumbnail) || a).url;
                $('ms-look-thumb').style.backgroundImage = 'url(' + th + ')';
                $('ms-look-sw-image').style.background = 'url(' + th + ') center / cover';
                $('ms-look-pick').textContent = 'Change image';
                form.querySelector('[name="look[bg]"][value=image]').checked = true;
                form.dispatchEvent(new Event('change'));
            });
            media.open();
        });

        // The frame: the device's size, scaled to fit
        function size() {
            var d = devices[dev], w = d[2], h = d[3], room = stage.clientWidth - 20,
                maxH = Math.max(320, window.innerHeight - 200), k = Math.min(1, room / w, maxH / h);
            iframe.style.width = w + 'px'; iframe.style.height = h + 'px'; iframe.style.transform = 'scale(' + k + ')';
            frame.style.width = Math.round(w * k) + 'px'; frame.style.height = Math.round(h * k) + 'px';
        }
        document.querySelectorAll('.ms-spot-bar [data-dev]').forEach(function (b) {
            b.addEventListener('click', function () {
                dev = b.dataset.dev;
                document.querySelectorAll('.ms-spot-bar [data-dev]').forEach(function (x) { x.classList.toggle('button-primary', x === b); });
                document.querySelectorAll('.ms-spot-dev').forEach(function (f) { f.hidden = f.dataset.dev !== dev; });
                size(); apply();
            });
        });
        $('ms-spot-url').addEventListener('change', function () { iframe.src = this.value; });
        iframe.addEventListener('load', function () { apply(); wire(); });

        // The preview works like the site: links stay in the frame (keeping the preview's own parameters), and
        // nothing is written - no add to cart, no form sent
        function wire() {
            try {
                var doc = iframe.contentDocument, cur = new URL(iframe.contentWindow.location.href);
                doc.addEventListener('click', function (e) {
                    var a = e.target.closest && e.target.closest('a[href]');
                    if (!a || e.defaultPrevented || e.button) return;
                    var u = new URL(a.href, cur.href);
                    if (/^(javascript|mailto|tel):/i.test(a.getAttribute('href'))) return;
                    if (u.origin === cur.origin && u.pathname === cur.pathname && u.search === cur.search && u.hash) return; // same page anchor
                    e.preventDefault();
                    if (u.origin !== cur.origin || /^\/wp-(admin|login)/.test(u.pathname) || u.searchParams.has('add-to-cart') || u.searchParams.has('remove_item')) return;
                    ['ms_spot_preview', 'ms_dark', 'ms_look_load'].forEach(function (k) { if (cur.searchParams.has(k)) u.searchParams.set(k, cur.searchParams.get(k)); });
                    iframe.src = u.href;
                }, true);
                doc.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var f = e.target;
                    if (f.tagName === 'FORM' && (f.method || 'get').toLowerCase() === 'get' && f.querySelector('[name=s]')) {
                        var u = new URL(f.action || cur.href, cur.href); u.searchParams.set('s', f.querySelector('[name=s]').value);
                        ['ms_spot_preview', 'ms_dark', 'ms_look_load'].forEach(function (k) { if (cur.searchParams.has(k)) u.searchParams.set(k, cur.searchParams.get(k)); });
                        iframe.src = u.href;
                    }
                }, true);
            } catch (x) {}
        }

        // Dragging the spotlight is a mode of the Spotlight panel only: it covers the frame while ticked
        var dragMode = $('ms-spot-dragmode'), dragPanel = dragMode.closest('details');
        dragMode.addEventListener('change', function () { drag.hidden = !dragMode.checked; });
        if (dragPanel) dragPanel.addEventListener('toggle', function () { if (!dragPanel.open && dragMode.checked) { dragMode.checked = false; drag.hidden = true; } });

        // Drag the spotlight (drag mode); the wheel still scrolls the page in the frame
        var down = false;
        function move(e) {
            var r = drag.getBoundingClientRect();
            $('ms-spot-' + dev + '-0').value = Math.round(Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)) * 100);
            $('ms-spot-' + dev + '-1').value = Math.round(Math.max(0, Math.min(1, (e.clientY - r.top) / r.height)) * 100);
            if (!$('ms-spot-on').checked) return;
            apply();
        }
        drag.addEventListener('pointerdown', function (e) { if (!$('ms-spot-on').checked) return; down = true; drag.setPointerCapture(e.pointerId); move(e); });
        drag.addEventListener('pointermove', function (e) { if (down) move(e); });
        drag.addEventListener('pointerup', function () { down = false; });
        drag.addEventListener('wheel', function (e) { try { iframe.contentWindow.scrollBy(0, e.deltaY); e.preventDefault(); } catch (x) {} }, { passive: false });

        window.addEventListener('resize', size);
        size(); apply();
    })();
    </script>
    <?php
}

<?php
/**
 * Appearance > Customize - WordPress's standard theme editor (2026-10-20, owner's wish: "a standard WP Appearance
 * Editor integration - menu editor, fonts, colors, styles", next to the Layout Manager).
 *
 * WordPress's own panels: Site Identity (title, tagline, icon; the logo there is only a fallback - the theme's
 * SVG logo, assets/images/logo.svg, comes first, header.php), Menus (Primary and Footer, live), Additional CSS. Ours, in a "monospace ART" panel:
 *   Colors   text, secondary text, headings, links, menu bar (color + opacity), menu links, menu links hover
 *   Fonts    body text, headings, interface (menus, buttons, labels), prices & numbers - a curated Google Fonts list
 *   Styles   text size, line spacing (and a pointer to Appearance > Look, 2026-10-21 - the background, buttons,
 *            cards, spotlight and effects, with these colors and fonts too: the one place for the look)
 *
 * How it reaches the page: the theme's SCSS uses CSS variables with today's values as defaults (_variables.scss:
 * --ms-text, --ms-heading, --ms-link, --ms-text-light, --ms-menu-bg, --ms-menu-link, --ms-menu-link-hover,
 * --ms-font-body / -heading / -ui / -mono, --ms-font-size, --ms-line-height); this prints only the ones that are set,
 * plus the Google Fonts a chosen font needs. Colors and fonts preview live (postMessage), the rest reloads.
 * The same colors and fonts are the block editor's palette and font list (wp_theme_json_data_theme).
 * Theme mods: ms_color_*, ms_menu_bg_opacity, ms_font_*, ms_font_size, ms_line_height.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The colors: theme mod => [ label, CSS variable, default ] */
function monospace_customizer_colors() {
    return [
        'ms_color_text'            => [ 'Text',               '--ms-text',            '#333333' ],
        'ms_color_text_light'      => [ 'Secondary text',     '--ms-text-light',      '#666666' ],
        'ms_color_heading'         => [ 'Headings',           '--ms-heading',         '#444444' ],
        'ms_color_link'            => [ 'Links',              '--ms-link',            '#000000' ],
        'ms_color_tagline'         => [ 'Tagline',            '--ms-tagline',         '#000000' ], // under the logo (2026-10-21: black, was #444)
        'ms_color_menu_bg'         => [ 'Menu bar',           '--ms-menu-bg',         '#000000' ],
        'ms_color_menu_link'       => [ 'Menu links',         '--ms-menu-link',       '#cccccc' ],
        'ms_color_menu_link_hover' => [ 'Menu links (hover)', '--ms-menu-link-hover', '#ffffff' ],
    ];
}

/** The font roles: theme mod => [ label, CSS variable, default font ] */
function monospace_customizer_font_roles() {
    return [
        'ms_font_body'    => [ 'Body text',                          '--ms-font-body',    'Merriweather' ],
        'ms_font_heading' => [ 'Headings',                           '--ms-font-heading', 'Oswald' ],
        'ms_font_ui'      => [ 'Interface (menus, buttons, labels)', '--ms-font-ui',      'Open Sans' ],
        'ms_font_mono'    => [ 'Prices & numbers',                   '--ms-font-mono',    'Courier Prime' ],
    ];
}

/** The fonts to choose from: name => [ CSS stack, Google Fonts family spec ('' = loaded already / system) ] */
function monospace_customizer_fonts() {
    return [
        // Loaded already (enqueue.php)
        'Merriweather'      => [ "'Merriweather', serif", '' ],
        'Oswald'            => [ "'Oswald', sans-serif", '' ],
        'Open Sans'         => [ "'Open Sans', sans-serif", '' ],
        'Courier Prime'     => [ "'Courier Prime', monospace", '' ],
        'Montserrat'        => [ "'Montserrat', sans-serif", '' ],
        'Roboto'            => [ "'Roboto', sans-serif", '' ],
        // Serif
        'Lora'              => [ "'Lora', serif", 'Lora:ital,wght@0,400..700;1,400..700' ],
        'Libre Baskerville' => [ "'Libre Baskerville', serif", 'Libre+Baskerville:ital,wght@0,400;0,700;1,400' ],
        'EB Garamond'       => [ "'EB Garamond', serif", 'EB+Garamond:ital,wght@0,400..800;1,400..800' ],
        'Source Serif 4'    => [ "'Source Serif 4', serif", 'Source+Serif+4:ital,opsz,wght@0,8..60,200..900;1,8..60,200..900' ],
        'Playfair Display'  => [ "'Playfair Display', serif", 'Playfair+Display:ital,wght@0,400..900;1,400..900' ],
        'DM Serif Display'  => [ "'DM Serif Display', serif", 'DM+Serif+Display:ital@0;1' ],
        // Sans
        'Inter'             => [ "'Inter', sans-serif", 'Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900' ],
        'Work Sans'         => [ "'Work Sans', sans-serif", 'Work+Sans:ital,wght@0,100..900;1,100..900' ],
        'Lato'              => [ "'Lato', sans-serif", 'Lato:ital,wght@0,300;0,400;0,700;1,400' ],
        'Bebas Neue'        => [ "'Bebas Neue', sans-serif", 'Bebas+Neue' ],
        // Monospace
        'IBM Plex Mono'     => [ "'IBM Plex Mono', monospace", 'IBM+Plex+Mono:ital,wght@0,400;0,600;1,400' ],
        'Space Mono'        => [ "'Space Mono', monospace", 'Space+Mono:ital,wght@0,400;0,700;1,400' ],
        // No download
        'System sans-serif' => [ 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', '' ],
        'Georgia'           => [ 'Georgia, "Times New Roman", serif', '' ],
    ];
}

/** Text sizes and line spacings: value => label ('' = the theme's) */
function monospace_customizer_sizes() {
    return [ '' => 'Default (1.1rem)', '1rem' => 'Smaller (1rem)', '1.2rem' => 'Larger (1.2rem)', '1.3rem' => 'Largest (1.3rem)' ];
}
function monospace_customizer_line_heights() {
    return [ '' => 'Default (1.6)', '1.45' => 'Tighter (1.45)', '1.75' => 'Looser (1.75)', '1.9' => 'Loosest (1.9)' ];
}

/** #rrggbb + 0-100 → rgba() */
function monospace_customizer_rgba( $hex, $opacity ) {
    $hex = ltrim( (string) $hex, '#' );
    if ( strlen( $hex ) === 3 ) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    if ( ! preg_match( '/^[0-9a-f]{6}$/i', $hex ) ) return '';
    return sprintf( 'rgba(%d, %d, %d, %s)', hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ), rtrim( rtrim( number_format( max( 0, min( 100, (int) $opacity ) ) / 100, 2 ), '0' ), '.' ) ?: '0' );
}

/** The CSS variables that are set: [ --name => value ] */
function monospace_customizer_vars() {
    $vars = [];
    if ( get_theme_mod( 'ms_menu_shadow', '' ) === 'off' ) $vars['--ms-menu-shadow'] = 'none'; // Look > Menu bar, 2026-10-21
    if ( get_theme_mod( 'ms_menu_bevel', '' ) === 'off' ) $vars['--ms-menu-bevel'] = 'none';   // the same, its bevel
    foreach ( monospace_customizer_colors() as $mod => [ , $var ] ) {
        $value = (string) get_theme_mod( $mod, '' );
        if ( $value === '' ) continue;
        if ( $mod === 'ms_color_menu_bg' ) $value = monospace_customizer_rgba( $value, get_theme_mod( 'ms_menu_bg_opacity', 85 ) );
        if ( $value !== '' ) $vars[ $var ] = $value;
    }
    // Opacity alone (the color left as it is): the theme's black at that opacity
    if ( ! isset( $vars['--ms-menu-bg'] ) && (string) get_theme_mod( 'ms_menu_bg_opacity', '' ) !== '' && (int) get_theme_mod( 'ms_menu_bg_opacity' ) !== 85 ) {
        $vars['--ms-menu-bg'] = monospace_customizer_rgba( '#000000', get_theme_mod( 'ms_menu_bg_opacity' ) );
    }
    $fonts = monospace_customizer_fonts();
    foreach ( monospace_customizer_font_roles() as $mod => [ , $var, $default ] ) {
        $font = (string) get_theme_mod( $mod, '' );
        if ( $font !== '' && $font !== $default && isset( $fonts[ $font ] ) ) $vars[ $var ] = $fonts[ $font ][0];
    }
    if ( isset( monospace_customizer_sizes()[ $s = (string) get_theme_mod( 'ms_font_size', '' ) ] ) && $s !== '' ) $vars['--ms-font-size'] = $s;
    if ( isset( monospace_customizer_line_heights()[ $l = (string) get_theme_mod( 'ms_line_height', '' ) ] ) && $l !== '' ) $vars['--ms-line-height'] = $l;
    return $vars;
}

/** The Google Fonts families the chosen fonts need beyond the theme's own */
function monospace_customizer_font_families() {
    $fonts = monospace_customizer_fonts();
    $need  = [];
    foreach ( array_keys( monospace_customizer_font_roles() ) as $mod ) {
        $font = (string) get_theme_mod( $mod, '' );
        if ( isset( $fonts[ $font ] ) && $fonts[ $font ][1] !== '' ) $need[ $font ] = $fonts[ $font ][1];
    }
    return $need;
}

// Front end: the chosen fonts, then the variables
add_action( 'wp_enqueue_scripts', function () {
    $families = monospace_customizer_font_families();
    if ( $families ) {
        wp_enqueue_style( 'monospace-customizer-fonts', 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $families ) . '&display=swap', [], null );
    }
}, 20 );
add_action( 'wp_head', function () {
    $vars = monospace_customizer_vars();
    if ( ! $vars ) return;
    $css = ':root{';
    foreach ( $vars as $k => $v ) $css .= $k . ':' . $v . ';';
    if ( function_exists( 'monospace_look_style' ) ) monospace_look_style( 'ms-customizer', $css . '}' ); // scoped when dark mode is on
    else echo '<style id="ms-customizer">' . wp_strip_all_tags( $css . '}' ) . "</style>\n";
}, 30 );

// The Customizer
add_action( 'customize_register', function ( $wp ) {
    // The site's logo is the theme's two-part logo (header.php: assets/images/logo/monospace + art) - this one is used
    // only without those files
    if ( $c = $wp->get_control( 'custom_logo' ) ) {
        $c->description = 'Not used: the site shows the theme’s own logo, “monospace” + “ART” (wp-content/themes/monospace-art-theme/assets/images/logo/ - an .svg there replaces the .png). A logo chosen here only shows if those files are removed.';
    }
    $wp->add_panel( 'monospace', [ 'title' => 'monospace ART', 'priority' => 25,
        'description' => 'Colors, fonts and text for the whole site. Everything about the look in one place - background, buttons, cards, the spotlight, effects, and these colors and fonts too: <a href="' . esc_url( admin_url( 'themes.php?page=monospace-look' ) ) . '">Appearance &gt; Look</a>. Layout, headers, sidebars and effects: <a href="' . esc_url( admin_url( 'admin.php?page=monospace-layout-manager' ) ) . '" target="_blank">Layout Manager</a>.' ] );

    // Colors
    $wp->add_section( 'ms_colors', [ 'title' => 'Colors', 'panel' => 'monospace', 'description' => 'Leave a color on Default to keep the theme’s own.' ] );
    foreach ( monospace_customizer_colors() as $mod => [ $label, , $default ] ) {
        $wp->add_setting( $mod, [ 'default' => '', 'sanitize_callback' => 'sanitize_hex_color', 'transport' => 'postMessage' ] );
        $wp->add_control( new WP_Customize_Color_Control( $wp, $mod, [ 'label' => $label, 'section' => 'ms_colors', 'description' => 'Theme default: ' . $default ] ) );
        if ( $mod === 'ms_color_menu_bg' ) {
            $wp->add_setting( 'ms_menu_bg_opacity', [ 'default' => 85, 'sanitize_callback' => 'absint', 'transport' => 'postMessage' ] );
            $wp->add_control( 'ms_menu_bg_opacity', [ 'label' => 'Menu bar opacity (%)', 'section' => 'ms_colors', 'type' => 'range',
                'input_attrs' => [ 'min' => 0, 'max' => 100, 'step' => 5 ], 'description' => 'Theme default: 85' ] );
        }
    }

    // Fonts
    $wp->add_section( 'ms_fonts', [ 'title' => 'Fonts', 'panel' => 'monospace', 'description' => 'Google Fonts beyond the theme’s own are loaded only when chosen.' ] );
    $choices = array_combine( array_keys( monospace_customizer_fonts() ), array_keys( monospace_customizer_fonts() ) );
    foreach ( monospace_customizer_font_roles() as $mod => [ $label, , $default ] ) {
        $wp->add_setting( $mod, [ 'default' => $default, 'sanitize_callback' => fn( $v ) => isset( monospace_customizer_fonts()[ $v ] ) ? $v : $default, 'transport' => 'postMessage' ] );
        $wp->add_control( $mod, [ 'label' => $label, 'section' => 'ms_fonts', 'type' => 'select', 'choices' => $choices, 'description' => 'Theme default: ' . $default ] );
    }

    // Styles
    // The background, buttons, cards, spotlight and effects: Appearance > Look (look.php) - the one place for the look
    $wp->add_section( 'ms_styles', [ 'title' => 'Styles', 'panel' => 'monospace',
        'description' => 'Background, buttons, cards, the spotlight and effects: <a href="' . esc_url( admin_url( 'themes.php?page=monospace-look' ) ) . '">Appearance &gt; Look</a> - the whole look in one place.' ] );
    $wp->add_setting( 'ms_font_size', [ 'default' => '', 'sanitize_callback' => fn( $v ) => isset( monospace_customizer_sizes()[ $v ] ) ? $v : '', 'transport' => 'postMessage' ] );
    $wp->add_control( 'ms_font_size', [ 'label' => 'Text size', 'section' => 'ms_styles', 'type' => 'select', 'choices' => monospace_customizer_sizes() ] );
    $wp->add_setting( 'ms_line_height', [ 'default' => '', 'sanitize_callback' => fn( $v ) => isset( monospace_customizer_line_heights()[ $v ] ) ? $v : '', 'transport' => 'postMessage' ] );
    $wp->add_control( 'ms_line_height', [ 'label' => 'Line spacing', 'section' => 'ms_styles', 'type' => 'select', 'choices' => monospace_customizer_line_heights() ] );

} );

// Live preview of colors, fonts and sizes
add_action( 'customize_preview_init', function () {
    $colors = []; foreach ( monospace_customizer_colors() as $mod => [ , $var, ] ) $colors[ $mod ] = $var;
    $fonts  = []; foreach ( monospace_customizer_fonts() as $name => [ $stack, $spec ] ) $fonts[ $name ] = [ $stack, $spec ];
    $roles  = []; foreach ( monospace_customizer_font_roles() as $mod => [ , $var, ] ) $roles[ $mod ] = $var;
    wp_enqueue_script( 'customize-preview' );
    wp_add_inline_script( 'customize-preview', 'window.msCustomizer=' . wp_json_encode( [ 'colors' => $colors, 'fonts' => $fonts, 'roles' => $roles ] ) . ';' . <<<'JS'
(function (api, C) {
    var root = document.documentElement.style;
    function rgba(hex, op) { var h = (hex || '#000000').replace('#', ''); if (h.length === 3) h = h.replace(/./g, '$&$&'); return 'rgba(' + parseInt(h.substr(0, 2), 16) + ',' + parseInt(h.substr(2, 2), 16) + ',' + parseInt(h.substr(4, 2), 16) + ',' + (op / 100) + ')'; }
    function menuBg() { var c = api('ms_color_menu_bg')(), o = api('ms_menu_bg_opacity')(); if (!c && +o === 85) root.removeProperty('--ms-menu-bg'); else root.setProperty('--ms-menu-bg', rgba(c, o)); }
    Object.keys(C.colors).forEach(function (mod) {
        api(mod, function (v) { v.bind(function (val) { if (mod === 'ms_color_menu_bg') return menuBg(); val ? root.setProperty(C.colors[mod], val) : root.removeProperty(C.colors[mod]); }); });
    });
    api('ms_menu_bg_opacity', function (v) { v.bind(menuBg); });
    Object.keys(C.roles).forEach(function (mod) {
        api(mod, function (v) { v.bind(function (name) {
            var f = C.fonts[name]; if (!f) return;
            if (f[1] && !document.getElementById('ms-font-' + name.replace(/\W/g, ''))) {
                var l = document.createElement('link'); l.rel = 'stylesheet'; l.id = 'ms-font-' + name.replace(/\W/g, '');
                l.href = 'https://fonts.googleapis.com/css2?family=' + f[1] + '&display=swap'; document.head.appendChild(l);
            }
            root.setProperty(C.roles[mod], f[0]);
        }); });
    });
    api('ms_font_size', function (v) { v.bind(function (s) { s ? root.setProperty('--ms-font-size', s) : root.removeProperty('--ms-font-size'); }); });
    api('ms_line_height', function (v) { v.bind(function (s) { s ? root.setProperty('--ms-line-height', s) : root.removeProperty('--ms-line-height'); }); });
})(wp.customize, window.msCustomizer);
JS
    );
} );

// The block editor: the site's colors and fonts as its palette and font list. Slugs start with ms-: a plain
// "text" or "link" would make .has-text-color / .has-link-color, class names WordPress already uses on blocks.
add_filter( 'wp_theme_json_data_theme', function ( $theme_json ) {
    $palette = [];
    // Every text color (not the menu bar's background). The value is the CSS variable, so a block set to one follows
    // Appearance > Look and dark mode on the site, not only the color as it was when the page was saved; the current
    // color is the fallback (the editor itself is not WYSIWYG on purpose, it doesn't print the variables)
    foreach ( monospace_customizer_colors() as $mod => [ $label, $var, $default ] ) {
        if ( $mod === 'ms_color_menu_bg' ) continue;
        $palette[] = [ 'slug' => 'ms-' . str_replace( '_', '-', substr( $mod, 9 ) ), 'name' => $label, 'color' => 'var(' . $var . ', ' . ( (string) get_theme_mod( $mod, '' ) ?: $default ) . ')' ];
    }
    if ( function_exists( 'monospace_appearance' ) && function_exists( 'monospace_appearance_button_colors' ) ) {
        $btn = monospace_appearance_button_colors()[ monospace_appearance()[3] ][1] ?? '';
        if ( $btn ) $palette[] = [ 'slug' => 'ms-button', 'name' => 'Buttons', 'color' => $btn ];
    }
    $fonts = monospace_customizer_fonts(); $families = [];
    foreach ( monospace_customizer_font_roles() as $mod => [ $label, , $default ] ) {
        $name = (string) get_theme_mod( $mod, '' ) ?: $default;
        if ( isset( $fonts[ $name ] ) ) $families[] = [ 'slug' => 'ms-' . str_replace( '_', '-', substr( $mod, 8 ) ), 'name' => $label . ': ' . $name, 'fontFamily' => $fonts[ $name ][0] ];
    }
    // Appearance > Design > Styles (the Site Editor's Styles panel) shows the theme's own presets instead of WordPress's
    // (2026-10-26, owner: "reflect all the theme's settings"). Settings only, no `styles`: those would print into the page
    // and into the editor canvas, which is not WYSIWYG on purpose (editor.css) - the front end is styled by the SCSS.
    // Kept: WordPress's own colors (the white card blocks use has-white-background-color) and spacing sizes (spacers
    // use them); small and medium keep their sizes (19 + 1 posts use them). Gone, unused: the other default sizes,
    // fonts, gradients, duotones and shadows.
    $sizes = [
        [ 'slug' => 'small',      'name' => 'Small',                'size' => '13px' ],
        [ 'slug' => 'medium',     'name' => 'Medium',               'size' => '20px' ],
        [ 'slug' => 'ms-side',    'name' => 'Sidebar (0.9rem)',     'size' => '0.9rem' ],
        [ 'slug' => 'ms-text',    'name' => 'Site text size',       'size' => 'var(--ms-font-size, 1.1rem)' ],
        [ 'slug' => 'ms-title',   'name' => 'Box title (1.3rem)',   'size' => '1.3rem' ],
    ];
    $shadow = 'rgb(var(--ms-shadow-rgb, 0 0 0) / calc(%s * var(--ms-shadow-k, 1)))';
    $shadows = [
        [ 'slug' => 'ms-card',   'name' => 'Cards and boxes',    'shadow' => '0 0 0.5rem ' . sprintf( $shadow, '0.5' ) ],
        [ 'slug' => 'ms-lifted', 'name' => 'Lifted (on hover)',  'shadow' => '0 0.2rem 1.2rem ' . sprintf( $shadow, '0.4' ) ],
    ];
    $data = [ 'version' => 2, 'settings' => [
        'color'      => [ 'palette' => $palette, 'defaultGradients' => false, 'defaultDuotone' => false ],
        'typography' => [ 'fontFamilies' => $families, 'defaultFontFamilies' => false, 'fontSizes' => $sizes, 'defaultFontSizes' => false ],
        'shadow'     => [ 'presets' => $shadows, 'defaultPresets' => false ],
    ] ];
    if ( monospace_customizer_in_site_editor() ) $data['styles'] = monospace_customizer_site_editor_styles();
    return $theme_json->update_with( $data );
} );

/** True while the Site Editor (Appearance > Design) or its Styles requests are being served - not the post editor or the site */
function monospace_customizer_in_site_editor() {
    global $pagenow;
    if ( is_admin() && $pagenow === 'site-editor.php' ) return true;
    return defined( 'REST_REQUEST' ) && REST_REQUEST && strpos( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), 'global-styles' ) !== false;
}

/**
 * The look as the Site Editor's Styles screen shows it (2026-10-27, owner: "reflect the actual look of the site"):
 * the page background, text, headings, links and buttons with the values in use (Look, Customize), as plain values - the
 * editor frame doesn't have the site's CSS variables. Only handed to the Site Editor (see above): in the post editor
 * `styles` would print into the canvas, which stays plain on purpose (editor.css).
 */
function monospace_customizer_site_editor_styles() {
    $mod = function ( $name, $default ) { return (string) get_theme_mod( $name, '' ) ?: $default; };
    $colors = [];
    foreach ( monospace_customizer_colors() as $m => [ , , $default ] ) $colors[ $m ] = $mod( $m, $default );

    $styles = [
        'color'      => [ 'text' => $colors['ms_color_text'] ],
        'typography' => [
            'fontFamily' => 'var(--wp--preset--font-family--ms-body)',
            'fontSize'   => $mod( 'ms_font_size', '1.1rem' ),
            'lineHeight' => $mod( 'ms_line_height', '1.6' ),
        ],
        'elements'   => [
            'link'    => [ 'color' => [ 'text' => $colors['ms_color_link'] ] ],
            'heading' => [ 'color' => [ 'text' => $colors['ms_color_heading'] ], 'typography' => [ 'fontFamily' => 'var(--wp--preset--font-family--ms-heading)', 'fontWeight' => '400' ] ],
        ],
    ];

    if ( function_exists( 'monospace_appearance' ) && function_exists( 'monospace_appearance_backgrounds' ) ) {
        list( , $bg, $btn_style, $btn_color ) = monospace_appearance();
        $swatch = monospace_appearance_backgrounds()[ $bg ][2] ?? '';
        if ( preg_match( '/^url\((.+?)\)(?: center \/ (\S+))?$/', $swatch, $m ) ) {
            $styles['background'] = [ 'backgroundImage' => [ 'url' => set_url_scheme( trim( $m[1], '\'"' ), 'https' ) ], 'backgroundPosition' => 'center' ];
            if ( ( $m[2] ?? 'cover' ) === 'cover' ) $styles['background']['backgroundSize'] = 'cover';
            else { $styles['background']['backgroundSize'] = $m[2]; $styles['background']['backgroundRepeat'] = 'repeat'; }
            $styles['color']['background'] = '#f1ede7';
        } elseif ( strpos( $swatch, 'gradient' ) !== false ) {
            $styles['color']['gradient'] = $swatch;
        } elseif ( $swatch !== '' ) {
            $styles['color']['background'] = $swatch;
        }
        $fill = monospace_appearance_button_colors()[ $btn_color ][1] ?? '';
        if ( $fill ) {
            $styles['elements']['button'] = $btn_style === 'outline'
                ? [ 'color' => [ 'text' => $fill, 'background' => 'transparent' ], 'border' => [ 'color' => $fill, 'width' => '1px', 'style' => 'solid' ] ]
                : [ 'color' => [ 'text' => '#ffffff', 'background' => $fill ] ];
            $styles['elements']['button']['typography'] = [ 'fontFamily' => 'var(--wp--preset--font-family--ms-ui)' ];
        }
    }
    return $styles;
}

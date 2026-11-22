<?php
/**
 * Utility pages (2026-11-15, owner): a page that is a tool or a legal text, not part of the gallery, leaves out the logo
 * and tagline (both sit in .site-logo-wrapper), the footer, the sharing icons and the floating Ko-fi button (.ms-kofi-float,
 * its panel .ms-kofi-panel). The menu bar stays. Only that page is affected; the theme's own settings (Look > Sharing
 * icons, the footer) stay as they are.
 *
 * Which pages (2026-11-16, owner: "remove the automatic, it's too fuzzy - I'll set what is a utility page myself"): only the pages the
 * owner has switched on. The switch is post meta _monospace_utility = '1' (anything else = a normal page), set in the page editor's
 * sidebar (the theme's "This page's layout" box, panel "Utility page", saved by sidebar-override.php) or by a tick on Layout Manager >
 * Utility pages. There is no automatic answer: the legal pages (Footer menu > Legal) and the plugins' tool pages used to count by
 * themselves (a filter, hard to follow); on 2026-11-16 they were each switched on once and the filter was removed.
 *
 * What a utility page leaves out - the parts below (logo, footer, share, kofi):
 *   1. the page's own choice, post meta _monospace_utility_hide: '' = follow Layout Manager, 'none' = leave nothing out,
 *      else a comma list of the parts left out ('logo,share');
 *   2. Layout Manager > Utility pages > "A utility page leaves out" (option monospace_utility_pages [ 'hide' => [ part => 1|0 ] ],
 *      all four by default).
 * Layout Manager > Utility pages (theme, design/utility-pages.php) draws and saves the site-wide part and the page ticks;
 * Core owns the logic and the meta.
 *
 * @package monospace-art-core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The parts a utility page can leave out: key => [ label, CSS selectors ] */
function monospace_utility_parts() {
    return [
        'logo'   => [ 'Logo and tagline',  '.site-logo-wrapper' ],
        'footer' => [ 'Footer',            '#site-footer' ],
        'share'  => [ 'Sharing icons',     '.ms-share,.ms-share-btn,.ms-share-menu' ],
        'kofi'   => [ 'Ko-fi button',      '.ms-kofi-float,.ms-kofi-panel' ],
    ];
}

/** The site-wide switches, with their defaults: [ 'hide' => [ part => 1|0 ] ] */
function monospace_utility_settings() {
    $s    = get_option( 'monospace_utility_pages', [] );
    $s    = is_array( $s ) ? $s : [];
    $hide = array_fill_keys( array_keys( monospace_utility_parts() ), 1 );
    foreach ( (array) ( $s['hide'] ?? [] ) as $k => $on ) if ( isset( $hide[ $k ] ) ) $hide[ $k ] = (int) (bool) $on;
    return [ 'hide' => $hide ];
}

// The page's own settings are post meta, edited in the "This page's layout" box (theme) and on Layout Manager > Utility pages; registered here for its sanitizing
// (Core's modules load while init is running, so register now - an init callback added from here would come too late)
$monospace_register_utility_meta = function () {
    foreach ( [ '_monospace_utility', '_monospace_utility_hide' ] as $key ) {
        register_post_meta( 'page', $key, [
            'type'              => 'string',
            'single'            => true,
            'default'           => '',
            'show_in_rest'      => false,
            'sanitize_callback' => function ( $v ) use ( $key ) {
                $v = sanitize_text_field( (string) $v );
                if ( $key === '_monospace_utility' ) return $v === '1' ? '1' : '';
                if ( $v === 'none' || $v === '' ) return $v;
                return implode( ',', array_values( array_intersect( array_keys( monospace_utility_parts() ), array_map( 'trim', explode( ',', $v ) ) ) ) );
            },
            'auth_callback'     => function ( $allowed, $meta_key, $post_id ) { return current_user_can( 'edit_post', $post_id ); },
        ] );
    }
};
did_action( 'init' ) ? $monospace_register_utility_meta() : add_action( 'init', $monospace_register_utility_meta );

/** Is this page a utility page: only when its own switch is on */
function monospace_utility_page_is( $page_id ) {
    return (string) get_post_meta( (int) $page_id, '_monospace_utility', true ) === '1';
}

/** The parts this page leaves out, as keys: its own choice (meta), else the site-wide switches. Has its own: see monospace_utility_page_has_own_hide() */
function monospace_utility_hide_for( $page_id ) {
    $raw = (string) get_post_meta( (int) $page_id, '_monospace_utility_hide', true );
    if ( $raw === '' ) return array_keys( array_filter( monospace_utility_settings()['hide'] ) );
    if ( $raw === 'none' ) return [];
    return array_values( array_intersect( array_keys( monospace_utility_parts() ), array_map( 'trim', explode( ',', $raw ) ) ) );
}

/** Has the page chosen its own parts (not following Layout Manager)? */
function monospace_utility_page_has_own_hide( $page_id ) {
    return (string) get_post_meta( (int) $page_id, '_monospace_utility_hide', true ) !== '';
}

/** The page being shown is a utility page */
function monospace_is_utility_page() {
    if ( ! is_singular( 'page' ) ) return false;
    return monospace_utility_page_is( get_queried_object_id() );
}

// The Pages list in wp-admin: a label next to the title of every utility page (2026-11-16, owner), and "own parts" when it chose its own
add_filter( 'display_post_states', function ( $states, $post ) {
    if ( ! is_object( $post ) || ( $post->post_type ?? '' ) !== 'page' || ! monospace_utility_page_is( $post->ID ) ) return $states;
    $states['ms_utility'] = '<span class="ms-pg-chip ms-pg-utility">Utility page' . ( monospace_utility_page_has_own_hide( $post->ID ) ? ' · own parts' : '' ) . '</span>';
    return $states;
}, 10, 2 );

add_action( 'wp_head', function () {
    if ( ! monospace_is_utility_page() ) return;
    $parts = monospace_utility_parts();
    $css   = array_map( fn( $key ) => $parts[ $key ][1], monospace_utility_hide_for( get_queried_object_id() ) );
    if ( $css ) echo '<style id="ms-utility-page">' . implode( ',', $css ) . '{display:none !important}</style>' . "\n";
}, 20 );

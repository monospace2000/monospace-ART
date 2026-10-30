<?php
/**
 * Headers & sidebars: one Site default, a few overrides of it (2026-10-22, owner: "I want a default sidebar and header for the
 * entire site, with overrides for post, pages, search results, 404 ... custom overrides for these overrides for individual
 * categories, posts and pages ... and a custom override for Art Feed. Product pages should never show [a sidebar]").
 *
 * Three areas - Top content, Header (the title and intro above a list), Sidebar (shown or not + its content) - each decided
 * from the most specific place that has its own:
 *   an individual category / tag / place, post or page (their edit screens)
 *   -> the page type's override: Posts, Pages, Search Results, 404 Page, Art Feed - only for an area set to "Custom"
 *   -> the Site default (Layout Manager > Headers & Sidebars > Site default)
 * Categories have no type of their own: a category with nothing of its own uses the Site default. Product pages never show a
 * sidebar. The home page has its own (Layout Manager > Home Page).
 *
 * Storage is what it always was: monospace_<type>_<field> options (monospace_site_<field> for the default), fields top_content,
 * top_style, header_title, header_desc_desktop / _mobile, mirror_header, header_location, sidebar_desktop / _mobile, mirror_sidebar,
 * show_sidebar - plus, new, monospace_<type>_<area>_mode = 'custom' | 'default' (top | header | sidebar). No mode saved = custom,
 * which is how every type behaved before, so nothing changed when this came.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The page types that can override the Site default, with the areas each has: context => [ areas ] */
function monospace_layout_override_types() {
    return [
        'blog-feed' => [ 'top', 'header', 'sidebar' ],
        'search'    => [ 'top', 'header', 'sidebar' ],
        '404'       => [ 'top', 'sidebar' ],
        'posts'     => [ 'top', 'sidebar' ],
        'pages'     => [ 'top', 'sidebar' ],
    ];
}

/** 'custom' (the type's own) or 'default' (the Site default) for one area of a page type */
function monospace_layout_area_mode( $context, $area ) {
    $types = monospace_layout_override_types();
    if ( ! isset( $types[ $context ] ) || ! in_array( $area, $types[ $context ], true ) ) return 'default';
    return get_option( 'monospace_' . str_replace( '-', '_', $context ) . '_' . $area . '_mode', 'custom' ) === 'default' ? 'default' : 'custom';
}

/** The option prefix ('monospace_posts_') that supplies $area for a page context */
function monospace_layout_prefix( $context, $area ) {
    if ( $context === 'home' ) return 'monospace_home_';
    if ( monospace_layout_area_mode( $context, $area ) === 'custom' ) return 'monospace_' . str_replace( '-', '_', $context ) . '_';
    return 'monospace_site_';
}

/** An option of the settings that supply $area for $context */
function monospace_layout_option( $context, $area, $field, $default = '' ) {
    return get_option( monospace_layout_prefix( $context, $area ) . $field, $default );
}

/**
 * Basic Markdown in the same fields (owner, 2026-10-30): **bold**, *italic* or _italic_, ~~strike~~, [text](https://link),
 * "# / ## / ###" at the start of a line = a heading, "- " or "* " at the start of lines = a list. HTML tags and [shortcodes]
 * are set aside first, so they are never touched; an underscore inside a word (snake_case, a URL) is not italic.
 */
function monospace_markdown_lite( $text ) {
    $text = (string) $text;
    if ( $text === '' || ! preg_match( '/[*_~#\[-]/', $text ) ) return $text;
    $keep = [];
    $hold = function ( $m ) use ( &$keep ) { $keep[] = $m[0]; return "\x01" . ( count( $keep ) - 1 ) . "\x02"; };
    $text = preg_replace_callback( '/<[^>]+>|\[[^\]\n]+\](?!\()/', $hold, $text ); // tags and shortcodes
    $text = preg_replace_callback( '/\[([^\]\n]+)\]\(((?:https?:\/\/|mailto:|\/|#)[^\s)]*)\)/', function ( $m ) use ( &$keep ) {
        $keep[] = '<a href="' . esc_url( $m[2] ) . '">' . $m[1] . '</a>';
        return "\x01" . ( count( $keep ) - 1 ) . "\x02";
    }, $text );
    $text = preg_replace( '/\*\*\s*(\S.*?)\s*\*\*/s', '<strong>$1</strong>', $text ); // spaces inside the marks are fine
    $text = preg_replace( '/~~\s*(\S.*?)\s*~~/s', '<del>$1</del>', $text );
    $text = preg_replace( '/(?<![*\w])\*(?=[^\s*])(.+?)(?<=[^\s*])\*(?![*\w])/s', '<em>$1</em>', $text );
    $text = preg_replace( '/(?<![_\w])_(?=[^\s_])(.+?)(?<=[^\s_])_(?![_\w])/s', '<em>$1</em>', $text );
    $text = preg_replace_callback( '/^(#{1,3}) +(.+)$/m', fn( $m ) => '<h' . ( strlen( $m[1] ) + 1 ) . '>' . trim( $m[2] ) . '</h' . ( strlen( $m[1] ) + 1 ) . '>', $text );
    $text = preg_replace_callback( '/(?:^[-*] +.+(?:\r?\n|$))+/m', function ( $m ) {
        $items = array_map( fn( $l ) => '<li>' . trim( preg_replace( '/^[-*] +/', '', $l ) ) . '</li>', preg_split( '/\r?\n/', trim( $m[0] ) ) );
        return '<ul>' . implode( '', $items ) . "</ul>\n";
    }, $text );
    return preg_replace_callback( '/\x01(\d+)\x02/', fn( $m ) => $keep[ (int) $m[1] ], $text );
}

/**
 * Line breaks in text that allows HTML (sidebars, feed header descriptions): Enter, or a typed \n, becomes <br>.
 * Run on the saved text before do_shortcode, so a shortcode's own output is never touched. A line break next to a
 * tag or beside a line that is only a shortcode is left alone, so lists and tables written over several lines stay as they are.
 */
function monospace_line_breaks( $text ) {
    $text = monospace_markdown_lite( $text );
    if ( $text === '' || ( strpos( $text, "\n" ) === false && strpos( $text, '\n' ) === false ) ) return $text;
    $lines = preg_split( '/\r\n|\r|\n|\\\\n/', $text );
    $out   = array_shift( $lines );
    $prev  = $out;
    foreach ( $lines as $line ) {
        $a = rtrim( $prev );
        $b = ltrim( $line );
        $skip = substr( $a, -1 ) === '>' || ( $b !== '' && $b[0] === '<' )
             || preg_match( '/^\[[^\]]+\]$/', trim( $a ) ) || preg_match( '/^\[[^\]]+\]$/', trim( $b ) );
        $out .= ( $skip ? "\n" : '<br>' . "\n" ) . $line;
        $prev = $line;
    }
    return $out;
}

/** The inline HTML a title may carry */
function monospace_title_tags() {
    return [ 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'del' => [], 'u' => [], 'br' => [] ];
}

/** A title as saved: plain text with basic inline HTML */
function monospace_title_clean( $title ) {
    return trim( wp_kses( wp_unslash( (string) $title ), monospace_title_tags() ) );
}

/** A single-line title with a typed \n shown as a line break; **bold**, *italic* and basic HTML work */
function monospace_title_html( $title ) {
    $t = str_replace( '\n', '<br>', wp_kses( (string) $title, monospace_title_tags() ) ); // basic HTML, or **bold** / *italic*
    $t = preg_replace( '/\*\*\s*(\S.*?)\s*\*\*/', '<strong>$1</strong>', $t );
    return preg_replace( '/(?<![*\w])\*(?=[^\s*])(.+?)(?<=[^\s*])\*(?![*\w])/', '<em>$1</em>', $t );
}

/** A sidebar's content from a settings prefix: the desktop text, or both with the classes that show one per screen size */
function monospace_sidebar_from_options( $prefix ) {
    $desktop = get_option( $prefix . 'sidebar_desktop', '' );
    $mobile  = get_option( $prefix . 'sidebar_mobile', '' );
    $mirror  = get_option( $prefix . 'mirror_sidebar', '' );
    $desktop = monospace_line_breaks( $desktop );
    $mobile  = monospace_line_breaks( $mobile );
    if ( $mirror === '' ) $mirror = '1'; // same on phones, unless unticked

    if ( $mirror === '1' ) return do_shortcode( $desktop );

    $output = '';
    if ( $desktop ) $output .= '<div class="content-desktop">' . do_shortcode( $desktop ) . '</div>';
    if ( $mobile )  $output .= '<div class="content-mobile">' . do_shortcode( $mobile ) . '</div>';
    return $output;
}

<?php
/**
 * Straight quotes in shortcodes (2026-10-23, owner: "shortcodes always automatically convert smart punctuation to plain",
 * "when entering too"). A smart-quote keyboard (an iPad) types info=“yes”; WordPress then reads the value as the text “yes”
 * and the option is silently ignored. Only a quote pair that wraps a value inside a registered shortcode's tag is changed
 * (title=“A name” -> title="A name"); a curly quote inside a straight-quoted value, or anywhere outside a tag, stays.
 *
 *   When entered - post content and excerpt, any post meta (the page layout boxes), options (widgets, the Layout Manager's
 *                  texts) and term descriptions are cleaned as they are saved, so what is stored is plain.
 *   When shown   - a shortcode that still has them (text saved before this, or by a plugin) is run with them cleaned.
 * This is a filter, not a change to WordPress itself: an update would wipe that.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_QUOTE_CHARS = '“”„‟″‘’‚‛′';

/** One tag's attributes: the quote pairs that wrap a value become straight ones */
function monospace_straight_attr_quotes( $atts ) {
    return preg_replace( '/(^|[\s=])[' . MONOSPACE_QUOTE_CHARS . ']([^' . MONOSPACE_QUOTE_CHARS . '"]*)[' . MONOSPACE_QUOTE_CHARS . '](?=\s|$)/u', '$1"$2"', $atts );
}

/** A text with shortcodes in it, cleaned inside their tags */
function monospace_straight_shortcode_quotes( $text ) {
    global $shortcode_tags;
    if ( ! is_string( $text ) || ! $shortcode_tags || strpos( $text, '[' ) === false || ! preg_match( '/[' . MONOSPACE_QUOTE_CHARS . ']/u', $text ) ) return $text;
    $tags = implode( '|', array_map( 'preg_quote', array_keys( $shortcode_tags ) ) );
    return preg_replace_callback( '/\[(' . $tags . ')(\s[^\]]*)\]/u', fn( $m ) => '[' . $m[1] . monospace_straight_attr_quotes( $m[2] ) . ']', $text );
}

/** The same through arrays (widget and Layout Manager options keep their texts in them) */
function monospace_straight_shortcode_quotes_deep( $value ) {
    if ( is_string( $value ) ) return monospace_straight_shortcode_quotes( $value );
    if ( is_array( $value ) ) return array_map( 'monospace_straight_shortcode_quotes_deep', $value );
    return $value;
}

// Entering: post content and excerpt (WordPress hands them over slashed)
add_filter( 'wp_insert_post_data', function ( $data ) {
    foreach ( [ 'post_content', 'post_excerpt' ] as $field ) {
        if ( isset( $data[ $field ] ) ) $data[ $field ] = wp_slash( monospace_straight_shortcode_quotes( wp_unslash( $data[ $field ] ) ) );
    }
    return $data;
} );

// Entering: options and term descriptions
add_filter( 'pre_update_option', fn( $value ) => monospace_straight_shortcode_quotes_deep( $value ) );
add_filter( 'pre_term_description', fn( $value ) => monospace_straight_shortcode_quotes( $value ) );

// Entering: post meta - the value is cleaned and saved again (once), the first save stands down
foreach ( [ 'add', 'update' ] as $op ) {
    add_filter( "{$op}_post_metadata", function ( $check, $post_id, $key, $value, $extra = null ) use ( $op ) {
        static $busy = false;
        if ( $busy || $check !== null || ! is_string( $value ) ) return $check;
        $clean = monospace_straight_shortcode_quotes( $value );
        if ( $clean === $value ) return $check;
        $busy = true;
        $op === 'add' ? add_post_metadata( $post_id, $key, wp_slash( $clean ), (bool) $extra ) : update_post_metadata( $post_id, $key, wp_slash( $clean ), $extra );
        $busy = false;
        return true;
    }, 1, 5 );
}

// Shown: a shortcode that still has them runs with them cleaned
add_filter( 'pre_do_shortcode_tag', function ( $return, $tag, $attr, $m ) {
    global $shortcode_tags;
    if ( $return !== false || empty( $m[3] ) || ! preg_match( '/[' . MONOSPACE_QUOTE_CHARS . ']/u', $m[3] ) ) return $return;
    $fixed = monospace_straight_attr_quotes( $m[3] );
    if ( $fixed === $m[3] || ! isset( $shortcode_tags[ $tag ] ) ) return $return;
    $atts   = shortcode_parse_atts( $fixed );
    $output = $m[1] . call_user_func( $shortcode_tags[ $tag ], $atts, $m[5] ?? null, $tag ) . $m[6];
    return apply_filters( 'do_shortcode_tag', $output, $tag, $atts, $m );
}, 10, 4 );

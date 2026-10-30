<?php
/**
 * Ko-fi buttons in the site's own look (2026-10-22, owner: "Override the Kofi button styles so it matches my site. For the
 * floating button, match the look of the scroll to top button. For other button locations, shortcode, use the site wide
 * button style" - "make sure the kofi code and button doesn't otherwise interfere").
 *
 * The Ko-fi plugin (ko-fi-button) keeps its settings - Settings > Ko-fi: the page name, the texts, which pages show the
 * floating button (and each page's own choice). Only what it draws is replaced:
 *   - Floating button   Ko-fi's own is a picture in a frame from ko-fi.com (its overlay script, jQuery) that no style
 *                       can reach: it is switched off (the kofi_display_floating_button filter) and ours is drawn where
 *                       it would have been - a round button like scroll-to-top, bottom left, a cup; the text slides out
 *                       on hover.
 *   - [kofi] / the Ko-fi Donate Button block   a link with the site's button style (a.button - Look > Buttons) instead
 *                       of Ko-fi's script-built button (widget_2.js, its own font and colors). [kofi type="panel"]
 *                       stays Ko-fi's.
 * Each opens Ko-fi's donation page in a small panel on the page (its embed, loaded on the first click - nothing from
 * ko-fi.com before that); without the script, or with a modifier key, the link opens ko-fi.com in a new tab.
 * The look: _kofi.scss; the script: assets/scripts/kofi.js (only on pages with a Ko-fi button).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The plugin's settings that matter here: [ code, text, floating text ] ('' code = no Ko-fi page set) */
function monospace_kofi_settings() {
    $o = (array) get_option( 'ko_fi_options', [] );
    return [
        'code'     => preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $o['coffee_code'] ?? '' ) ),
        'text'     => wp_strip_all_tags( (string) ( $o['coffee_text'] ?? '' ) ) ?: 'Support me on Ko-fi',
        'floating' => wp_strip_all_tags( (string) ( $o['coffee_floating_button_text'] ?? '' ) ) ?: 'Support me on Ko-fi',
    ];
}

/** The cup (Ko-fi's mark, drawn in the text color) */
function monospace_kofi_icon() {
    return '<svg class="ms-kofi-cup" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
         . '<path d="M3.5 8h13v6.5a4.5 4.5 0 0 1-4.5 4.5H8a4.5 4.5 0 0 1-4.5-4.5z"/><path d="M16.5 9.5h1.5a2.5 2.5 0 0 1 0 5h-1.5"/>'
         . '<path d="M10 15.5c-1.6-1.1-2.6-2-2.6-3 0-.7.5-1.2 1.2-1.2.6 0 1 .3 1.4.8.4-.5.8-.8 1.4-.8.7 0 1.2.5 1.2 1.2 0 1-1 1.9-2.6 3z" fill="currentColor" stroke="none"/></svg>';
}

function monospace_kofi_enqueue() {
    wp_enqueue_script( 'ms-kofi', get_template_directory_uri() . '/assets/scripts/kofi.js', [], (string) filemtime( get_template_directory() . '/assets/scripts/kofi.js' ), true );
}

/** A Ko-fi link button: the site's button style; $align left | center | right | '' */
function monospace_kofi_button( $code, $text, $title = '', $align = '' ) {
    if ( $code === '' ) return '';
    monospace_kofi_enqueue();
    $align = [ 'centre' => 'center' ][ $align ] ?? $align;
    return '<p class="ms-kofi' . ( in_array( $align, [ 'left', 'center', 'right' ], true ) ? ' ms-kofi-' . $align : '' ) . '">'
         . '<a class="button ms-kofi-button" href="' . esc_url( 'https://ko-fi.com/' . $code ) . '" target="_blank" rel="noopener" data-ms-kofi="' . esc_attr( $code ) . '"'
         . ( $title !== '' ? ' title="' . esc_attr( $title ) . '"' : '' ) . '>' . monospace_kofi_icon() . '<span>' . esc_html( $text ) . '</span></a></p>';
}

// [kofi] (type button - the default): ours
add_filter( 'pre_do_shortcode_tag', function ( $out, $tag, $atts ) {
    if ( $tag !== 'kofi' || ! class_exists( 'Ko_Fi' ) ) return $out;
    $atts = (array) $atts;
    if ( ( $atts['type'] ?? 'button' ) !== 'button' ) return $out;
    $s    = monospace_kofi_settings();
    $code = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $atts['code'] ?? '' ) ) ?: $s['code'];
    return monospace_kofi_button( $code, wp_strip_all_tags( (string) ( $atts['text'] ?? '' ) ) ?: $s['text'], (string) ( $atts['title'] ?? '' ) );
}, 10, 3 );

// The Ko-fi Donate Button block: ours
add_filter( 'render_block_ko-fi-button/kofi-button', function ( $html, $block ) {
    $a    = (array) ( $block['attrs'] ?? [] );
    $s    = monospace_kofi_settings();
    $code = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $a['code'] ?? '' ) ) ?: $s['code'];
    return monospace_kofi_button( $code, wp_strip_all_tags( (string) ( $a['text'] ?? '' ) ) ?: $s['text'], (string) ( $a['title'] ?? '' ), (string) ( $a['button_alignment'] ?? '' ) ) ?: $html;
}, 10, 2 );

// The floating button: the plugin decides where (its settings, each page's own) - we draw it
add_filter( 'kofi_display_floating_button', function ( $show ) {
    if ( $show && ! is_admin() && monospace_kofi_settings()['code'] !== '' ) $GLOBALS['monospace_kofi_floating'] = true;
    return false; // never Ko-fi's (its overlay script and frame)
}, 99 );

add_action( 'wp_footer', function () {
    if ( empty( $GLOBALS['monospace_kofi_floating'] ) ) return;
    $s = monospace_kofi_settings();
    monospace_kofi_enqueue();
    printf( '<a class="ms-kofi-float" href="%1$s" target="_blank" rel="noopener" data-ms-kofi="%2$s" aria-label="%3$s">%4$s<span>%3$s</span></a>' . "\n",
        esc_url( 'https://ko-fi.com/' . $s['code'] ), esc_attr( $s['code'] ), esc_attr( $s['floating'] ), monospace_kofi_icon() );
}, 5 ); // before the footer scripts (20)

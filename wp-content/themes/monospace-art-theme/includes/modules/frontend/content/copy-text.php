<?php
/**
 * Copy link (2026-10-23, owner: "a Copy icon/link on just the Bio section of the About Me page").
 *
 *   [copy_text target=".ms-bio-text" label="Copy bio"]
 *
 * A small icon + word that copies the text of the element the CSS selector names (its paragraphs and headings, one blank line
 * between them - the Copy link itself left out) and says "Copied" for a moment. Only where the shortcode is used: the script
 * (assets/scripts/copy.js) is loaded then. Drawn like the share icon - a link with role="button", so the site's button style
 * (Look > Buttons) leaves it alone - quiet until hovered; the look: _share.scss.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'copy_text', function ( $atts ) {
    $a      = shortcode_atts( [ 'target' => '', 'label' => 'Copy' ], (array) $atts );
    $target = trim( (string) $a['target'] );
    $label  = trim( wp_strip_all_tags( (string) $a['label'] ) ) ?: 'Copy';
    if ( $target === '' ) return '';
    if ( function_exists( 'monospace_is_editor_context' ) && monospace_is_editor_context() ) {
        return '<div class="painting-buy-placeholder" style="padding:6px;border:1px dashed #aaa;background:#f9f9f9;">Copy link: ' . esc_html( $label ) . ' (' . esc_html( $target ) . ')</div>';
    }
    wp_enqueue_script( 'ms-copy', get_template_directory_uri() . '/assets/scripts/copy.js', [], (string) filemtime( get_template_directory() . '/assets/scripts/copy.js' ), true );
    $icon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>';
    return '<a href="#" class="ms-copy" role="button" data-copy-target="' . esc_attr( $target ) . '" title="' . esc_attr( $label ) . '">' . $icon . '<span class="ms-copy-label" role="status">' . esc_html( $label ) . '</span></a>';
} );

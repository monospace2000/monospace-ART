<?php
/**
 * Alt text as a hover hint (2026-10-19, owner's wish)
 *
 * An image's alt text written in the block editor shows as a hint when the mouse rests on it: the
 * image gets a title attribute with the same text (unless it has a title of its own). Everywhere block
 * content is shown - posts, pages, feeds (the_content) and block widgets (widget_block_content).
 *
 * Only alt text that is in the content itself: the_content priority 10 runs after the blocks are
 * rendered (9) but before shortcodes (11 - [related_posts], [price_card]) and before the generated alts
 * of seo/headings-alt.php (25: "Title, acrylic painting by Hens Breet"), so those never become hints.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The hover hint for an alt text: long ones (the described artworks, 2026-11-12) are cut at 120 characters, at a word, with an ellipsis. */
function monospace_alt_hint_text( $alt ) {
    if ( mb_strlen( $alt ) <= 120 ) return $alt;
    $cut = mb_substr( $alt, 0, 120 );
    $sp  = mb_strrpos( $cut, ' ' );
    return rtrim( $sp > 60 ? mb_substr( $cut, 0, $sp ) : $cut, " ,.;:-" ) . '…';
}

function monospace_alt_hints( $content ) {
    if ( stripos( (string) $content, '<img' ) === false || ! class_exists( 'WP_HTML_Tag_Processor' ) ) return $content;
    $html = new WP_HTML_Tag_Processor( $content );
    while ( $html->next_tag( 'img' ) ) {
        $alt = trim( (string) $html->get_attribute( 'alt' ) );
        if ( $alt === '' || $html->get_attribute( 'title' ) !== null ) continue;
        $html->set_attribute( 'title', monospace_alt_hint_text( $alt ) );
    }
    return $html->get_updated_html();
}
add_filter( 'the_content', 'monospace_alt_hints', 10 );
add_filter( 'widget_block_content', 'monospace_alt_hints' );

/**
 * Thumbnails too (2026-11-12): images printed by WordPress itself - the feed, the archives, search results, related
 * posts, the featured image - get the hint from the Media library alt text. Only a real alt text: the generated
 * "Title, painting by Hens Breet" (monospace-seo) is not shown as a hint.
 */
add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
    if ( is_admin() || ! empty( $attr['title'] ) ) return $attr;
    $alt = trim( (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) );
    if ( $alt !== '' ) $attr['title'] = monospace_alt_hint_text( $alt );
    return $attr;
}, 10, 2 );

/**
 * The [artwork] shortcode (Core, price-card-standalone.php; the related posts' thumbnails are made with it) prints its
 * own <img> with the post title as alt, so the filter above never sees it: its picture gets the hint of the image's
 * Media library alt text here.
 */
add_filter( 'do_shortcode_tag', function ( $output, $tag, $attr ) {
    if ( $tag !== 'artwork' || stripos( (string) $output, '<img' ) === false || ! class_exists( 'WP_HTML_Tag_Processor' ) ) return $output;
    $post_id = isset( $attr['post'] ) ? (int) $attr['post'] : 0;
    if ( ! $post_id ) return $output;
    $image_id = function_exists( 'monospace_row_image_id' ) ? monospace_row_image_id( $post_id ) : (int) get_post_thumbnail_id( $post_id );
    $alt = $image_id ? trim( (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ) : '';
    if ( $alt === '' ) return $output;
    $html = new WP_HTML_Tag_Processor( $output );
    while ( $html->next_tag( 'img' ) ) {
        if ( $html->get_attribute( 'title' ) === null ) $html->set_attribute( 'title', monospace_alt_hint_text( $alt ) );
    }
    return $html->get_updated_html();
}, 10, 3 );

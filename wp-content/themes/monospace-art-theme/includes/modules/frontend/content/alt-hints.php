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

function monospace_alt_hints( $content ) {
    if ( stripos( (string) $content, '<img' ) === false || ! class_exists( 'WP_HTML_Tag_Processor' ) ) return $content;
    $html = new WP_HTML_Tag_Processor( $content );
    while ( $html->next_tag( 'img' ) ) {
        $alt = trim( (string) $html->get_attribute( 'alt' ) );
        if ( $alt === '' || $html->get_attribute( 'title' ) !== null ) continue;
        $html->set_attribute( 'title', $alt );
    }
    return $html->get_updated_html();
}
add_filter( 'the_content', 'monospace_alt_hints', 10 );
add_filter( 'widget_block_content', 'monospace_alt_hints' );

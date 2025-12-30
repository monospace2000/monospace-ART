<?php
/**
 * Make the first image and its caption clickable on listing/archive pages,
 * except when the first image is already linked.
 */
function clickable_first_image( $content ) {

    // Only modify listing/archive pages
    if ( is_home() || is_search() || is_tag() || is_category() ) {

        /*
         * If the first image is already inside a link, bail early.
         * This checks for <a ...><img ...></a> appearing before any standalone <img>.
         */
        $linked_img_pos = preg_match(
            '/<a[^>]*>\s*<img[^>]+>\s*<\/a>/i',
            $content,
            $linked_match,
            PREG_OFFSET_CAPTURE
        );

        $img_pos = preg_match(
            '/<img[^>]+>/i',
            $content,
            $img_match,
            PREG_OFFSET_CAPTURE
        );

        // If no image at all, or first image is already linked, do nothing
        if ( ! $img_pos || ( $linked_img_pos && $linked_match[0][1] <= $img_match[0][1] ) ) {
            return $content;
        }

        // Check if the first image is inside a gallery
        $gallery_pos = preg_match(
            '/<figure[^>]*wp-block-gallery[^>]*>/i',
            $content,
            $gallery_match,
            PREG_OFFSET_CAPTURE
        );

        // If we found a gallery containing the first image
        if ( $gallery_pos && $gallery_match[0][1] <= $img_match[0][1] ) {

            // Wrap the first img tag in a link
            $content = preg_replace(
                '/(<img[^>]+>)/i',
                '<a href="' . esc_url( get_the_permalink() ) . '">$1</a>',
                $content,
                1
            );

            // Find and wrap the first gallery caption using blocks-gallery-caption class
            $content = preg_replace(
                '/(<figcaption[^>]*class="[^"]*blocks-gallery-caption[^"]*"[^>]*>)(.*?)(<\/figcaption>)/is',
                '$1<a  class="caption-link" href="' . esc_url( get_the_permalink() ) . '">$2</a>$3',
                $content,
                1
            );

        } else {
            // Check if the first image is inside a regular figure with figcaption
            $figure_pos = preg_match(
                '/<figure[^>]*>.*?<img[^>]+>.*?(?:<figcaption[^>]*>.*?<\/figcaption>)?.*?<\/figure>/is',
                $content,
                $figure_match,
                PREG_OFFSET_CAPTURE
            );

            // If we found a figure containing the first image
            if ( $figure_pos && $figure_match[0][1] <= $img_match[0][1] ) {
                $figure_html = $figure_match[0][0];

                // Wrap the img tag in a link
                $linked_figure = preg_replace(
                    '/(<img[^>]+>)/i',
                    '<a href="' . esc_url( get_the_permalink() ) . '">$1</a>',
                    $figure_html,
                    1
                );

                // Also wrap the figcaption in a link if it exists
                $linked_figure = preg_replace(
                    '/<figcaption([^>]*)>(.*?)<\/figcaption>/is',
                    '<figcaption$1><a class="caption-link" href="' . esc_url( get_the_permalink() ) . '">$2</a></figcaption>',
                    $linked_figure,
                    1
                );

                // Replace the entire figure
                $content = str_replace( $figure_html, $linked_figure, $content );
            } else {
                // No figure, just wrap the standalone image
                $first_img = $img_match[0][0];
                $linked_img = '<a href="' . esc_url( get_the_permalink() ) . '">' . $first_img . '</a>';
                $content = preg_replace( '/<img[^>]+>/i', $linked_img, $content, 1 );
            }
        }
    }

    return $content;
}
add_filter( 'the_content', 'clickable_first_image' );
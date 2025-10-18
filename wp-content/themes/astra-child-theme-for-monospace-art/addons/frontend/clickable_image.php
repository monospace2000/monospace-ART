<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */



/**
 * Make the first image in post content clickable on listing/archive pages.
 *
 * This function searches the post content for the first <img> tag and wraps 
 * it in a link to the post's single page. It only applies to home, search, 
 * tag, and category archive pages. Other pages or single post views are left unchanged.
 *
 * @since 1.0.0
 *
 * @param string $content The post content.
 * @return string Modified post content with the first image linked, or original content.
 */
function clickable_first_image( $content ) {

    // Only modify listing/archive pages
    if ( is_home() || is_search() || is_tag() || is_category() ) {

        // Use regex to find the first <img> tag
        if ( preg_match( '/<img[^>]+>/i', $content, $matches ) ) {
            $first_img = $matches[0];

            // Replace the first image with a linked version
            $linked_img = '<a href="' . esc_url( get_the_permalink() ) . '">' . $first_img . '</a>';

            // Replace only the first occurrence
            $content = preg_replace( '/<img[^>]+>/i', $linked_img, $content, 1 );
        }

        return $content;
    }

    // Other pages/posts: leave content unchanged
    return $content;
}
add_filter( 'the_content', 'clickable_first_image' );



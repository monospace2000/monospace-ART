<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */




/**
 * Replace image URLs in post content with full-size versions
 * 
 * This removes the WordPress-generated dimension suffix (e.g., -300x200)
 * from <img> tags so that the full-size image is used in the content.
 *
 * @param string $content The post content.
 * @return string Modified post content with full-size image URLs.
 */
add_filter( 'the_content', function( $content ) {

    // Use preg_replace_callback to handle all <img> tags with dimension suffix
    $content = preg_replace_callback(
        '/(<img[^>]+src=["\'])([^"\']+?)-\d+x\d+(\.\w+)(["\'][^>]*>)/i',
        function( $matches ) {
            // $matches[1] = opening <img> tag up to src="
            // $matches[2] = image filename without size suffix
            // $matches[3] = file extension
            // $matches[4] = closing part of the <img> tag
            return $matches[1] . $matches[2] . $matches[3] . $matches[4];
        },
        $content
    );

    return $content;
} );



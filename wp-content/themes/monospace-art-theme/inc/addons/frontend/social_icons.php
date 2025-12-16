<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */




/**
 * Override Astra social icon URLs
 *
 * This filter replaces the default link for a given social icon.
 * In this example, the "email" icon is redirected to a custom newsletter URL.
 *
 * @param string $output HTML output of the social icon link.
 * @param array  $icon   Array containing icon data ('id', 'icon', etc.).
 * @return string Modified HTML output for the social icon.
 */
add_filter( 'astra_get_social_icon', function( $output, $icon ) {

    // Check if the icon is the "email" icon
    if ( 'email' === $icon['id'] ) {
        // Custom URL to replace the default mailto link
        $custom_url = 'https://monospace.com/art/blog/newsletter/'; // Change to your desired URL

        // Build replacement anchor tag using the existing SVG icon
        $output  = '<a href="' . esc_url( $custom_url ) . '" target="_blank" rel="noopener">';
        $output .= $icon['icon']; // Use Astra's provided SVG
        $output .= '</a>';
    }

    return $output;
}, 10, 2 );



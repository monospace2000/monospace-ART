<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */


add_action( 'template_redirect', function() {
    if ( is_page_template( 'start_template.php' ) ) {
        remove_action( 'astra_content_before', 'astra_primary_content_top' );
        remove_action( 'astra_content_after', 'astra_primary_content_bottom' );
        remove_action( 'astra_entry_content_before', 'astra_entry_top' );
        remove_action( 'astra_entry_content_after', 'astra_entry_bottom' );
    }
});



add_filter( 'astra_logo', 'custom_astra_logo_link' );
function custom_astra_logo_link( $html ) {
    // Replace the default logo link with your custom link
    $custom_url = '/art-feed/';
    $html = preg_replace( '/href="([^"]*)"/', 'href="' . esc_url( $custom_url ) . '"', $html );
    return $html;
}


add_action( 'template_redirect', function() {
    if ( is_customize_preview() && is_front_page() ) {
        // Redirect Customizer preview away from your start template
        $fallback_url = home_url( '/recent-work/' ); // a normal page you want to preview instead
        wp_safe_redirect( $fallback_url );
        exit;
    }
});

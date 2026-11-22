<?php
/**
 * The Art Filter page is for visitors who know it, not for search engines (owner, 2026-10-29): its many sort / view /
 * filter addresses (?sort=, ?seed=, ...) look like copies of one page. The page stays open to everyone; it carries
 * noindex and is left out of Jetpack's sitemap. Any page with [monospace_art_filter] counts.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_art_filter_has_shortcode( $post ) {
    $post = get_post( $post );
    return $post && has_shortcode( (string) $post->post_content, 'monospace_art_filter' );
}

add_filter( 'wp_robots', function ( $robots ) {
    if ( is_singular() && monospace_art_filter_has_shortcode( get_queried_object_id() ) ) {
        $robots['noindex'] = true;
        $robots['follow']  = true;
    }
    return $robots;
} );

add_filter( 'jetpack_sitemap_skip_post', function ( $skip, $post ) {
    return $skip || ( is_object( $post ) && monospace_art_filter_has_shortcode( $post ) );
}, 10, 2 );

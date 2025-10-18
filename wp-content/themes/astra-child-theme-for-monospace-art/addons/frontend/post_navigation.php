<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */



// Remove Astra's default single post navigation
remove_action( 'astra_entry_content_after', 'astra_post_navigation' );

/**
 * Custom single post navigation: NEWER ← on left, OLDER → on right
 *
 * @since 1.0.0
 */
function monospace_custom_post_navigation() {
return; ////////////////////////////////////////////// ///// TEMPORARILY DISABLED

    if ( ! is_singular( 'post' ) ) {
        return;
    }

    $older = get_previous_post(); // OLDER
    $newer = get_next_post();     // NEWER

    // Exit if no posts
    if ( ! $older && ! $newer ) {
        return;
    }

    echo '<nav class="post-navigation" aria-label="Post navigation">';
    echo '<div class="nav-links" style="display:flex; justify-content:space-between; align-items:center;">';

    // NEWER on the left
    if ( $newer ) {
        echo '<div class="nav-newer">';
        echo '<a href="' . esc_url( get_permalink( $newer->ID ) ) . '" rel="next">';
        echo '<span class="nav-arrow" aria-hidden="true">←</span>';
        echo '<span class="nav-label">NEWER</span>';
        echo '</a>';
        echo '</div>';
    } else {
        echo '<div class="nav-newer nav-disabled">';
        echo '<span class="nav-arrow" aria-hidden="true">←</span>';
        echo '<span class="nav-label">NEWER</span>';
        echo '</div>';
    }

    // OLDER on the right
    if ( $older ) {
        echo '<div class="nav-older">';
        echo '<a href="' . esc_url( get_permalink( $older->ID ) ) . '" rel="prev">';
        echo '<span class="nav-label">OLDER</span>';
        echo '<span class="nav-arrow" aria-hidden="true">→</span>';
        echo '</a>';
        echo '</div>';
    } else {
        echo '<div class="nav-older nav-disabled">';
        echo '<span class="nav-label">OLDER</span>';
        echo '<span class="nav-arrow" aria-hidden="true">→</span>';
        echo '</div>';
    }

    echo '</div>';
    echo '</nav>';
}




















/**
 * Customize Astra/WordPress single post navigation labels
 * Previous = OLDER → , Next = ← NEWER
 */


// Next post link
add_filter('next_post_link', function($output) {
    if (preg_match('/href=[\'"](.*?)[\'"].*?>(.*?)<\/a>/', $output, $matches)) {
        $url = $matches[1];
        $output = '<a class="nav-left" href="' . esc_url($url) . '" rel="next"></span><< NEWER</a>';
    }
    return $output;
});

// Previous post link
add_filter('previous_post_link', function($output) {
    // Grab the post URL and wrap it with custom label
    if (preg_match('/href=[\'"](.*?)[\'"].*?>(.*?)<\/a>/', $output, $matches)) {
        $url = $matches[1];
        $output = '<a class="nav-right" href="' . esc_url($url) . '" rel="prev">OLDER >></a>';
    }
    return $output;
});




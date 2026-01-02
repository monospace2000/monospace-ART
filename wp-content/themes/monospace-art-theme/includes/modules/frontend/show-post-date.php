<?php

/**
 * Show publication date for posts in the "blog" category:
 * - single blog posts
 * - blog category archive
 * - main posts feed (home/blog index)
 */
add_filter( 'the_content', 'ms_show_date_on_blog_posts_everywhere' );
function ms_show_date_on_blog_posts_everywhere( $content ) {

    // We only add dates to normal posts
    if ( get_post_type() !== 'post' ) {
        return $content;
    }

    $is_blog_post = has_category( 'blog' );

    // --- SINGLE POST ---
    if ( is_singular( 'post' ) && $is_blog_post ) {
        $date_html = '<p class="post-date">' . get_the_date() . '</p>';
        return $date_html . $content;
    }

    // --- BLOG CATEGORY ARCHIVE ---
    if ( is_category( 'blog' ) && is_main_query() ) {
        $date_html = '<p class="post-date">' . get_the_date() . '</p>';
        return $date_html . $content;
    }

    // --- MAIN FEED (home / posts page) ---
    if ( ( is_home() || is_archive() ) && is_main_query() && $is_blog_post ) {
        $date_html = '<p class="post-date">' . get_the_date() . '</p>';
        return $date_html . $content;
    }

    return $content;
}
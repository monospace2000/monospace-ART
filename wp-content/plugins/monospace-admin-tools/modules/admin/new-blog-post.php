<?php
/**
 * New blog post starts from the Blog Post pattern (owner, 2026-11-12).
 *
 * "+ New > Post" in the admin toolbar (post-new.php) opens an empty post; here it opens with the contents of the
 * synced pattern titled "Blog Post" (Post Date block, text, Blog Footer) already in it. The pattern is looked up by
 * its title, so editing the pattern changes what the next new post starts with. Only the plain new-post screen:
 * artworks (New artwork), pages, products and the dashboard's Quick Draft are left alone, and a post that already
 * has content is never touched.
 */

add_filter( 'default_content', 'monospace_new_blog_post_content', 10, 2 );

function monospace_new_blog_post_content( $content, $post ) {
    global $pagenow;

    if ( $pagenow !== 'post-new.php' || $content !== '' || ! $post || $post->post_type !== 'post' ) {
        return $content;
    }

    $pattern = get_posts( [
        'post_type'     => 'wp_block',
        'title'         => 'Blog Post',
        'post_status'   => 'publish',
        'numberposts'   => 1,
        'no_found_rows' => true,
    ] );

    return $pattern ? $pattern[0]->post_content : $content;
}

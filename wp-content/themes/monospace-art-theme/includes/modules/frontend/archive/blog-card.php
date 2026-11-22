<?php
/**
 * Blog cards in the feed (2026-11-01, owner: blog posts "look nicely integrated with the Artwork Posts but also recognizable
 * as blog posts").
 *
 * In the Art Feed and the archives a post with no linked product - a blog post, a newsletter - is drawn as the same card an
 * artwork gets: the picture (featured image, else the first picture in the text, else the art placeholder) linked to the post,
 * the title in the caption pill, and an info line on the right. What marks it as a post: a small "Blog" / "Newsletter" label on
 * the picture, "Blog · date" as its info line, and a short excerpt with "Read more" beneath. The full text (and a newsletter's
 * embed) is on the post's own page. Grid view keeps only the square picture and its label; search results and the detail list
 * have their own rows (Core's monospace_post_row_html()).
 *
 * Captions: the title pill is for artwork only (owner, 2026-11-01). Every post with no linked product gets the class
 * ms-plain-post (post_class below), and its picture captions - in the feed, on its own page, in a blog post's photos that
 * are not artwork - are plain text (_main-feed-images.scss). A post is an artwork when it is linked to a product (Quick
 * Artwork Editor); nothing else to choose. The card's title is a plain bold line (figcaption.ms-blog-title).
 *
 * HTML: figure.ms-blog-card > .ms-archive-image-wrapper (+ span.ms-post-label) + figcaption + .ms-feed-info, then p.ms-blog-excerpt.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ms-plain-post: a post that is not an artwork (no linked product) - its captions are text, not the artwork pill
add_filter( 'post_class', function ( $classes, $class, $post_id ) {
    if ( function_exists( 'monospace_get_post_product_id' ) && get_post_type( $post_id ) === 'post' && ! monospace_get_post_product_id( $post_id ) ) {
        $classes[] = 'ms-plain-post';
    }
    return $classes;
}, 10, 3 );

// After the date line (priority 10) and the grid thumbnail fallback (20): the card replaces the whole content
add_filter( 'the_content', 'monospace_blog_card_content', 25 );

function monospace_blog_card_content( $content ) {
    if ( is_admin() || is_feed() || ! in_the_loop() || ! is_main_query() || ! ( is_home() || is_archive() ) ) return $content;
    if ( ! function_exists( 'monospace_get_post_product_id' ) || ! function_exists( 'monospace_row_image_id' ) ) return $content;

    $post = get_post();
    if ( ! $post || $post->post_type !== 'post' || monospace_get_post_product_id( $post->ID ) ) return $content;

    $permalink = get_permalink( $post );
    $title     = get_the_title( $post );
    $label     = has_category( 'newsletter', $post ) ? 'Newsletter' : 'Blog';
    $label     = apply_filters( 'monospace_blog_card_label', $label, $post );

    $image_id = monospace_row_image_id( $post->ID );
    $img      = $image_id ? wp_get_attachment_image( $image_id, 'large', false, [ 'alt' => $title, 'loading' => 'lazy', 'decoding' => 'async' ] ) : '';
    $badge    = '<span class="ms-post-label">' . esc_html( $label ) . '</span>';

    $figure = '<figure class="ms-blog-card">';
    if ( $img !== '' ) {
        $figure .= '<div class="ms-archive-image-wrapper ms-standard-image"><a href="' . esc_url( $permalink ) . '" class="ms-archive-image-link"><div class="ms-image-container">' . $img . $badge . '</div></a></div>';
    }
    $figure .= '<figcaption class="ms-blog-title"><a href="' . esc_url( $permalink ) . '" class="caption-link">' . esc_html( $title ) . '</a></figcaption>';
    $figure .= '<div class="ms-feed-info"><div class="ms-info"><div class="ms-info-made"><span class="ms-info-part">' . esc_html( $label ) . '</span> &middot; <span class="ms-info-part">' . esc_html( get_the_date( '', $post ) ) . '</span></div></div></div>';
    $figure .= '</figure>';

    $excerpt = '';
    $text    = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
    $text    = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( $text ) ) ) ) );
    if ( $text !== '' ) {
        $chars   = max( 60, (int) get_option( 'monospace_archive_toolbar_detail_chars', 200 ) );
        $excerpt = '<p class="ms-blog-excerpt">' . esc_html( mb_strlen( $text ) > $chars ? rtrim( mb_substr( $text, 0, $chars ) ) . '…' : $text ) . ' ';
    } else {
        $excerpt = '<p class="ms-blog-excerpt">';
    }
    $excerpt .= '<a href="' . esc_url( $permalink ) . '" class="ms-blog-more">Read more &rarr;</a></p>';

    return $figure . $excerpt;
}

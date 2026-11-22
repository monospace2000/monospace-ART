<?php
/**
 * Plain captions inside artwork posts (2026-11-01, owner: "I have Artwork posts with photos of my studio" - the first image in
 * an artwork post is the artwork, nearly always).
 *
 * The title pill is for the artwork: in a post linked to a product only the caption of the post's first picture keeps it. The
 * caption of any later picture (the studio, the easel, a detail) is plain text - it gets the class ms-plain-caption, styled with
 * the plain captions of the posts that are not artwork (_main-feed-images.scss). A post with no product needs no help: its
 * class ms-plain-post (archive/blog-card.php) already makes every caption plain.
 *
 * To override one picture, give its block the additional CSS class
 *   ms-artwork-caption   the pill, although it isn't the first picture
 *   ms-plain-caption     plain text, although it is the first picture
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// After the feed's first-image processing (10), which keeps the first picture inside its figure
add_filter( 'the_content', 'monospace_plain_captions', 30 );

function monospace_plain_captions( $content ) {
    if ( is_admin() || is_feed() || ! in_the_loop() || stripos( $content, '<figcaption' ) === false ) return $content;
    if ( ! function_exists( 'monospace_get_post_product_id' ) ) return $content;

    $post = get_post();
    if ( ! $post || $post->post_type !== 'post' || ! monospace_get_post_product_id( $post->ID ) ) return $content;

    if ( ! preg_match_all( '/<figcaption\b([^>]*)>/i', $content, $caps, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) return $content;

    // Back to front, so the offsets of the captions still to do stay right
    foreach ( array_reverse( $caps ) as $cap ) {
        [ $open, $pos ] = $cap[0];
        $attrs  = $cap[1][0];
        $before = substr( $content, 0, $pos );
        $figure = strrpos( $before, '<figure' );
        $tag    = $figure === false ? '' : substr( $before, $figure, strcspn( $before . '>', '>', $figure ) );
        $first  = $figure === false || stripos( substr( $before, 0, $figure ), '<img' ) === false;

        if ( stripos( $tag, 'ms-artwork-caption' ) !== false ) continue;
        if ( $first && stripos( $tag, 'ms-plain-caption' ) === false ) continue;

        $new = preg_match( '/\bclass=(["\'])(.*?)\1/i', $attrs, $c )
            ? '<figcaption' . str_replace( $c[0], 'class=' . $c[1] . $c[2] . ' ms-plain-caption' . $c[1], $attrs ) . '>'
            : '<figcaption class="ms-plain-caption"' . $attrs . '>';
        $content = substr_replace( $content, $new, $pos, strlen( $open ) );
    }
    return $content;
}

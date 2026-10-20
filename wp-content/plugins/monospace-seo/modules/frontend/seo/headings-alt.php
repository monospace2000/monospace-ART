<?php
/**
 * Headings and image alt text.
 *
 * - Artwork post pages: the title shown under the image is the first figcaption (index.php prints
 *   no title on posts), so that caption's text becomes the page's <h1 class="artwork-title">, styled
 *   to look the same (_main-feed-images.scss). Posts with no caption, or whose first caption is
 *   something else (news posts), get a visually hidden <h1> with the post title.
 * - Feeds: pages shown in a feed (feed-pages.php) can carry their own <h1> block, which would compete
 *   with the feed's <h1> (feed-headers.php). In a feed it is printed as <h2 data-heading="h1">, same size.
 * - Alt text: nearly every image was uploaded without one. An empty alt is filled with the post's
 *   title (artworks: "Title, acrylic painting by Hens Breet"). An alt set in the media library wins.
 *   Lightbox captions (which fall back to the alt) still see the image's own alt only.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
 * ARTWORK POST <h1>
 * ============================================================ */

add_filter( 'the_content', function ( $content ) {
    if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id() ) {
        return $content;
    }

    $title = get_the_title();
    $norm  = function ( $s ) {
        return mb_strtolower( trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $s ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) );
    };

    if ( preg_match( '#(<figcaption[^>]*>)(.*?)(</figcaption>)#is', $content, $m, PREG_OFFSET_CAPTURE ) ) {
        $caption = $m[2][0];
        $is_title = ( function_exists( 'monospace_get_post_product_id' ) && monospace_get_post_product_id() )
            || $norm( $caption ) === $norm( $title );
        if ( $is_title && trim( wp_strip_all_tags( $caption ) ) !== '' ) {
            $h1 = $m[1][0] . '<h1 class="artwork-title">' . $caption . '</h1>' . $m[3][0];
            return substr_replace( $content, $h1, $m[0][1], strlen( $m[0][0] ) );
        }
    }

    return '<h1 class="screen-reader-text">' . esc_html( wp_strip_all_tags( $title ) ) . '</h1>' . $content;
}, 30 );

/* ============================================================
 * ARCHIVE <h1> FALLBACK
 * The feed header (feed-headers.php, loop_start 5) prints the archive's <h1> - unless the header is
 * set to the sidebar or hidden. Then a visually hidden one stands in.
 * ============================================================ */

add_action( 'loop_start', function ( $query ) {
    static $done = false;
    if ( $done || ! $query->is_main_query() || is_singular() || ! empty( $GLOBALS['monospace_feed_h1'] ) ) return;
    $done = true;
    if ( ! ( is_home() || is_archive() || is_search() ) || ( function_exists( 'is_shop' ) && is_shop() ) ) return;

    $title = function_exists( 'monospace_seo_title' ) ? monospace_seo_title() : '';
    $title = preg_replace( '/ \| .*$/', '', $title );
    if ( $title ) echo '<h1 class="screen-reader-text">' . esc_html( $title ) . '</h1>';
}, 6 );

/* ============================================================
 * PAGES' OWN <h1> IN FEEDS
 * ============================================================ */

add_filter( 'the_content', function ( $content ) {
    if ( is_singular() || stripos( $content, '<h1' ) === false ) return $content;
    $content = preg_replace( '/<h1(?=[\s>])/i', '<h2 data-heading="h1"', $content );
    return preg_replace( '#</h1>#i', '</h2>', $content );
}, 30 );

/* ============================================================
 * ALT TEXT FALLBACK
 * ============================================================ */

/** "Title, acrylic painting by Hens Breet" for artworks, else the post's title. */
function monospace_alt_for_post( $post_id ) {
    static $cache = [];
    if ( isset( $cache[ $post_id ] ) ) return $cache[ $post_id ];

    $alt  = wp_strip_all_tags( get_the_title( $post_id ) );
    $kind = function_exists( 'monospace_seo_artwork' ) ? monospace_seo_artwork_kind( monospace_seo_artwork( $post_id ) ) : '';
    if ( $alt && $kind ) $alt .= ', ' . mb_strtolower( $kind ) . ' by Hens Breet';
    return $cache[ $post_id ] = $alt;
}

/** Media library alt, else the post's. */
function monospace_alt_for_image( $attachment_id, $post_id ) {
    $alt = $attachment_id ? trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) : '';
    if ( $alt ) return $alt;
    return $post_id ? monospace_alt_for_post( $post_id ) : '';
}

// Images in post content. Runs after the lightbox (lightbox.php, 20), which uses an image's alt as
// its caption: a generated alt there would put a caption under every lightbox image.
add_filter( 'the_content', function ( $content ) {
    if ( stripos( $content, '<img' ) === false ) return $content;
    $post_id = get_the_ID();

    return preg_replace_callback( '/<img\b[^>]*>/i', function ( $img ) use ( $post_id ) {
        $tag = $img[0];
        if ( preg_match( '/\salt=("|\')(.*?)\1/is', $tag, $m ) && trim( $m[2] ) !== '' ) return $tag;

        $attachment_id = preg_match( '/wp-image-(\d+)/', $tag, $id ) ? (int) $id[1] : 0;
        $alt = monospace_alt_for_image( $attachment_id, $post_id );
        if ( ! $alt ) return $tag;

        $attr = ' alt="' . esc_attr( $alt ) . '"';
        return isset( $m[0] ) ? str_replace( $m[0], $attr, $tag ) : preg_replace( '/^<img/i', '<img' . $attr, $tag );
    }, $content );
}, 25 );

// Post thumbnails (related posts, ...) take their own post's title - begin/end_fetch_post_thumbnail_html
// bracket the call. Other wp_get_attachment_image() calls (Miniature Set block) are left to the
// content filter above, after the lightbox.
$GLOBALS['monospace_alt_thumbnail_post'] = 0;
add_action( 'begin_fetch_post_thumbnail_html', function ( $post_id ) { $GLOBALS['monospace_alt_thumbnail_post'] = (int) $post_id; } );
add_action( 'end_fetch_post_thumbnail_html', function () { $GLOBALS['monospace_alt_thumbnail_post'] = 0; } );

add_filter( 'wp_get_attachment_image_attributes', function ( $attr, $attachment ) {
    if ( ! $GLOBALS['monospace_alt_thumbnail_post'] ) return $attr;
    if ( isset( $attr['alt'] ) && trim( $attr['alt'] ) !== '' ) return $attr;
    $alt = monospace_alt_for_image( $attachment->ID, $GLOBALS['monospace_alt_thumbnail_post'] );
    if ( $alt ) $attr['alt'] = $alt;
    return $attr;
}, 10, 2 );

<?php
/**
 * Image Sizes Hint
 *
 * Artwork images are inserted at "Full size", so WordPress's default `sizes`
 * attribute claims the image is as wide as the original upload (often 2000px+)
 * and browsers download the multi-MB original even though a smaller copy is
 * already listed in the srcset.
 *
 * This replaces the hint with the real maximum width of the content column:
 * - Feeds, categories, search, tags (non-singular): images show at most 660px
 * - Single posts and pages: images show at most 1000px
 * Below that width they fill the screen (100vw). High-density screens still
 * pick a sharper copy (1320 / 1536 / 2048w) from the srcset by themselves.
 *
 * The lightbox keeps opening the full-size original (see lightbox.php).
 *
 * Also turns off WordPress's `sizes="auto"` prefix for lazy images, which
 * browsers did not honor here (the original was still downloaded).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function monospace_content_image_max_width() {
    return is_singular() ? 1000 : 660;
}

function monospace_image_sizes_hint( $sizes, $size ) {
    if ( is_admin() ) {
        return $sizes;
    }

    $width = is_array( $size ) ? (int) $size[0] : 0;
    $max   = monospace_content_image_max_width();

    // Smaller images already have an accurate hint
    if ( $width && $width <= $max ) {
        return $sizes;
    }

    return sprintf( '(max-width: %1$dpx) 100vw, %2$dpx', $max + 40, $max );
}
add_filter( 'wp_calculate_image_sizes', 'monospace_image_sizes_hint', 10, 2 );

add_filter( 'wp_img_tag_add_auto_sizes', '__return_false' );

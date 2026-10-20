<?php
/**
 * The lightbox for images in posts (the site's own, assets/scripts/ms-lightbox.js; GLightbox until 2026-10-19).
 *
 * Automatically wraps <img> tags (that are not already links)
 * in <a> tags with data attributes for GLightbox.
 *
 * Features:
 * - Blog posts: every image opens in the lightbox, except images inside a link, images with the
 *   class `nolightbox` (or `data-nolightbox`) or the data-nolightbox attribute, and the related posts
 *   block ([related_posts]).
 * - Opt-in, any page or post: the class `lightbox` on an image (Image block > Advanced > Additional
 *   CSS class(es) - it lands on the block's <figure>, which counts too) opens it in the lightbox, even
 *   if it's linked (the link then opens the full-size image in the lightbox instead). The lightbox
 *   script only loads on pages that use the class (monospace_lightbox_mode()).
 * - Optional captions from WordPress image data or alt text.
 *
 * @package monospace-art-theme
 */

// =========================================================
//  CONFIGURATION
// =========================================================

// Global flag: include captions from image data or alt text
define( 'LIGHTBOX_USE_CAPTIONS', true ); // Set false to disable captions


// =========================================================
//  WHERE THE LIGHTBOX RUNS
// =========================================================

/**
 * 'all' on blog posts (every image), 'opt-in' on any other page whose content has an image with the
 * class `lightbox` (only those images), '' elsewhere.
 */
function monospace_lightbox_mode() {
    if ( is_single() ) return 'all';
    if ( is_singular() ) {
        $post = get_post();
        if ( $post && preg_match( '/class="[^"]*\blightbox\b/', $post->post_content ) ) return 'opt-in';
    }
    return '';
}

/** The image, or its block's <figure>, has the class `lightbox` */
function monospace_lightbox_opted_in( $img ) {
    for ( $node = $img, $depth = 0; $node && $node->nodeType === XML_ELEMENT_NODE && $depth < 4; $node = $node->parentNode, $depth++ ) {
        if ( preg_match( '/(^|\s)lightbox(\s|$)/', (string) $node->getAttribute( 'class' ) ) ) return true;
        if ( $node->nodeName === 'figure' ) break;
    }
    return false;
}


// =========================================================
//  FRONT-END SCRIPT ENQUEUE
// =========================================================

/**
 * The site's own lightbox (assets/scripts/ms-lightbox.js, 2026-10-19 - replaced GLightbox, which loaded
 * ~60 KB from a CDN and left faded ghost images when swiping on phones). Registered everywhere - the
 * price card's "See a preview" uses it too (print-order-shortcode.php enqueues it) - and loaded here on
 * blog posts and pages that opt images in.
 */
function monospace_enqueue_lightbox_assets() {
    $file = get_template_directory() . '/assets/scripts/ms-lightbox.js';
    wp_register_script( 'ms-lightbox', get_template_directory_uri() . '/assets/scripts/ms-lightbox.js', [], filemtime( $file ), true );
    if ( monospace_lightbox_mode() ) wp_enqueue_script( 'ms-lightbox' );
}
add_action( 'wp_enqueue_scripts', 'monospace_enqueue_lightbox_assets' );


// =========================================================
//  FILTER POST CONTENT TO ADD LIGHTBOX LINKS
// =========================================================

function monospace_wrap_images_for_glightbox( $content ) {

    // Blog posts (all images), and pages that opt images in (only those)
    $mode = monospace_lightbox_mode();
    if ( ! $mode ) {
        return $content;
    }

    // Safely load post content into a DOMDocument
    libxml_use_internal_errors( true );
    $dom = new DOMDocument();
    $dom->loadHTML( '<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    libxml_clear_errors();

    $images = $dom->getElementsByTagName( 'img' );

    foreach ( $images as $img ) {

        // --- Skip Conditions ---

        $opted_in = monospace_lightbox_opted_in( $img );

        // 1. Pages: only images with the class `lightbox`
        if ( $mode === 'opt-in' && ! $opted_in ) {
            continue;
        }

        // 2. Image inside a link (directly or deeper, e.g. the related posts' <a><div><img>): left
        //    alone, unless opted in - then a link right around it opens the lightbox instead (set up
        //    below); a link further up is never touched (no link inside a link)
        $existing_link = $img->parentNode->nodeName === 'a' ? $img->parentNode : null;
        $inside_link   = false;
        for ( $node = $img->parentNode; $node && $node->nodeType === XML_ELEMENT_NODE; $node = $node->parentNode ) {
            if ( $node->nodeName === 'a' ) { $inside_link = true; break; }
        }
        if ( $inside_link && ! ( $opted_in && $existing_link ) ) {
            continue;
        }

        // 3. Image explicitly opted out: class nolightbox (or the older data-nolightbox class), the
        //    data-nolightbox attribute, or anything in the related posts block ([related_posts])
        $classes = $img->getAttribute( 'class' );
        if ( preg_match( '/(^|\s)(data-)?nolightbox(\s|$)/', $classes ) || $img->hasAttribute( 'data-nolightbox' ) ) {
            continue;
        }
        for ( $node = $img->parentNode, $in_related = false; $node && $node->nodeType === XML_ELEMENT_NODE; $node = $node->parentNode ) {
            if ( preg_match( '/(^|\s)monospace-related-posts(\s|$)/', (string) $node->getAttribute( 'class' ) ) ) { $in_related = true; break; }
        }
        if ( $in_related ) {
            continue;
        }

        // --- Proceed to wrap image in <a> ---

        $src = $img->getAttribute( 'src' );

        // Try to get full-resolution image URL
        $full_src = $src; // Fallback to current src

        // Method 1: Extract attachment ID from wp-image-* class
        $classes = $img->getAttribute( 'class' );
        if ( preg_match( '/wp-image-(\d+)/i', $classes, $matches ) ) {
            $attachment_id = intval( $matches[1] );
            $full_image = wp_get_attachment_image_src( $attachment_id, 'full' );
            if ( $full_image ) {
                $full_src = $full_image[0];
            }
        }
        // Method 2: If no class, try to find attachment by URL
        else {
            global $wpdb;
            $attachment_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE guid LIKE %s OR guid LIKE %s",
                '%' . basename( $src ),
                $src
            ) );
            if ( $attachment_id ) {
                $full_image = wp_get_attachment_image_src( $attachment_id, 'full' );
                if ( $full_image ) {
                    $full_src = $full_image[0];
                }
            }
        }

        $link = $existing_link ?: $dom->createElement( 'a' );
        $link->setAttribute( 'href', $full_src );
        $link->setAttribute( 'data-gallery', 'post-gallery' );
        $link->setAttribute( 'class', trim( $link->getAttribute( 'class' ) . ' ms-lightbox-link' ) );
        $link->removeAttribute( 'target' );

        // Add caption if enabled
        if ( defined( 'LIGHTBOX_USE_CAPTIONS' ) && LIGHTBOX_USE_CAPTIONS ) {
            $caption = $img->getAttribute( 'data-caption' );

            // Fallback: use alt text if caption is missing
            if ( empty( $caption ) ) {
                $caption = $img->getAttribute( 'alt' );
            }

            if ( ! empty( $caption ) ) {
                $link->setAttribute( 'data-title', esc_attr( $caption ) );
            }
        }

        // Clone and wrap image (an existing link already wraps it)
        if ( ! $existing_link ) {
            $link->appendChild( $img->cloneNode( true ) );
            $img->parentNode->replaceChild( $link, $img );
        }
    }

    // Convert DOM back to HTML
    $new_content = $dom->saveHTML();

    // Clean up wrapper tags added by DOMDocument
    $new_content = preg_replace( '/^<!DOCTYPE.+?>/', '', $new_content );
    $new_content = str_replace( array( '<?xml encoding="UTF-8">', '<html>', '</html>', '<body>', '</body>' ), '', $new_content );

    return $new_content;
}
add_filter( 'the_content', 'monospace_wrap_images_for_glightbox', 20 );





// =========================================================
//  STYLES
// =========================================================

/**
 * Post images: the zoom cursor. The lightbox itself (black, the image as large as the screen allows,
 * no captions) is ms-lightbox.js's base look - the post's links open it with skin ms-lb-post.
 */
add_action( 'wp_head', function () {
    if ( ! monospace_lightbox_mode() ) return; ?>
    <style>
    a.ms-lightbox-link img { cursor: zoom-in; }
    /* Images left out of the lightbox: no zoom cursor - the cursor of what's around them (the hand inside a
       link, e.g. the related posts' thumbnails; the normal arrow elsewhere) */
    img.no-lightbox, img.nolightbox, img[data-nolightbox] { cursor: inherit !important; }
    </style>
    <?php
} );

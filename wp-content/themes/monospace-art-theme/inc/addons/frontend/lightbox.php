<?php
/**
 * Enable GLightbox for images in single posts.
 *
 * Automatically wraps <img> tags (that are not already links)
 * in <a> tags with data attributes for GLightbox.
 * 
 * Features:
 * - Active only on single post pages (not archives or pages).
 * - Skips images already inside <a> tags.
 * - Skips images with `data-nolightbox` or class `nolightbox`.
 * - Optional captions from WordPress image data or alt text.
 *
 * @package astra-child-theme-for-monospace-art
 */

// =========================================================
//  CONFIGURATION
// =========================================================

// Global flag: include captions from image data or alt text
define( 'LIGHTBOX_USE_CAPTIONS', true ); // Set false to disable captions


// =========================================================
//  FRONT-END SCRIPT ENQUEUE
// =========================================================

function monospace_enqueue_glightbox_assets() {

    // Only enqueue on single blog posts
    if ( ! is_single() ) {
        return;
    }

    // Load GLightbox (from CDN)
    wp_enqueue_style(
        'glightbox',
        'https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css',
        array(),
        null
    );

    wp_enqueue_script(
        'glightbox',
        'https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js',
        array(),
        null,
        true
    );


}
add_action( 'wp_enqueue_scripts', 'monospace_enqueue_glightbox_assets' );


// =========================================================
//  FILTER POST CONTENT TO ADD LIGHTBOX LINKS
// =========================================================

function monospace_wrap_images_for_glightbox( $content ) {

    // Only run on single posts
    if ( ! is_single() ) {
        return $content;
    }

    // Safely load post content into a DOMDocument
    libxml_use_internal_errors( true );
    $dom = new DOMDocument();
    $dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) );
    libxml_clear_errors();

    $images = $dom->getElementsByTagName( 'img' );

    foreach ( $images as $img ) {

        // --- Skip Conditions ---

        // 1. Image is already wrapped in a link
        if ( $img->parentNode->nodeName === 'a' ) {
            continue;
        }

        // 2. Image explicitly opted out
        $classes = $img->getAttribute( 'class' );
        if ( strpos( $classes, 'nolightbox' ) !== false || $img->hasAttribute( 'data-nolightbox' ) ) {
            continue;
        }

        // --- Proceed to wrap image in <a> ---

        $src = $img->getAttribute( 'src' );
        $link = $dom->createElement( 'a' );
        $link->setAttribute( 'href', $src );
        $link->setAttribute( 'data-gallery', 'post-gallery' );
        $link->setAttribute( 'class', 'glightbox' );

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

        // Clone and wrap image
        $link->appendChild( $img->cloneNode( true ) );
        $img->parentNode->replaceChild( $link, $img );
    }

    // Convert DOM back to HTML
    $new_content = $dom->saveHTML();

    // Clean up wrapper tags added by DOMDocument
    $new_content = preg_replace( '/^<!DOCTYPE.+?>/', '', $new_content );
    $new_content = str_replace( array( '<html>', '</html>', '<body>', '</body>' ), '', $new_content );

    return $new_content;
}
add_filter( 'the_content', 'monospace_wrap_images_for_glightbox', 20 );





// =========================================================
//  CUSTOM LIGHTBOX STYLES
// =========================================================

function monospace_glightbox_custom_styles() {
    if ( ! is_single() ) {
        return;
    }

    $custom_css = "
        /* ----------------------------------------
         * GLightbox Custom Minimalist Style
         * ---------------------------------------- */

        /* Cursor behavior for lightbox-enabled images */
        a.glightbox img {
            cursor: zoom-in;
            transition: transform 0.15s ease;
        }

        /* When GLightbox is active, show zoom-out cursor */
        .glightbox-open {
            cursor: auto !important;
        }

        /* Override GLightbox container for full immersion */
        .glightbox-container {
            background-color: black !important;
            padding: 0 !important;
            margin: 0 !important;
        }

        /* Remove borders, shadows, and any styling */
        .gslide-image img {
            box-shadow: none !important;
            border: none !important;
            background: transparent !important;
        }

        /* Ensure image fills the screen while maintaining aspect ratio */
        .gslide-image img {
            max-width: 100vw !important;
            max-height: 100vh !important;
            width: auto !important;
            height: auto !important;
            object-fit: contain !important;
        }

        img.no-lightbox,
        img[data-nolightbox] {
           cursor: auto !important;
        }

        /* Disable all GLightbox animations */
        .gslide,
        .gslide-media {
            transition: none !important;
            animation: none !important;
        }

        /* Hide captions unless you want them (optional) */
        .gdesc-inner {
            display: none !important;
        }
        .glightbox-open {
            cursor: zoom-out !important;
        }
    ";

    wp_add_inline_style( 'glightbox', $custom_css );

    // Inline script: configure GLightbox behavior
    wp_add_inline_script( 'glightbox', "
        document.addEventListener('DOMContentLoaded', function() {
            const lightbox = GLightbox({
                selector: 'a[data-gallery=\"post-gallery\"]',
                loop: true,
                autoplayVideos: false,
                openEffect: 'none',
                closeEffect: 'none',
                slideEffect: 'none',
                zoomable: false,
                touchNavigation: false,    //  touch swipe
                dragAutoSlide: false,      // desktop drag behavior
                closeButton: true,         // show X button
                closeOnOutsideClick: true  // click background to close
            });

           
       

            lightbox.on('open', () => {
                // Use a slight delay to ensure the slide is fully rendered
                setTimeout(() => {
                const slideImage = document.querySelector('.gslide-image img');
                if (slideImage) {
                    slideImage.style.cursor = 'zoom-out';
                    slideImage.addEventListener('click', () => {
                    lightbox.close();
                    });
                }
                }, 100);
            });

         });

    " );
}
add_action( 'wp_enqueue_scripts', 'monospace_glightbox_custom_styles', 20 );









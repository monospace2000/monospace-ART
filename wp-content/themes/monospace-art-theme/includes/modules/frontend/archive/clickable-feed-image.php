<?php
/**
 * Archive Image Enhancements
 *
 * This module is the single source of truth for processing images on archive/feed/search pages.
 * It handles everything from basic clickability to complex miniature layouts with badges.
 *
 * FUNCTIONALITY:
 *
 * 1. MINIATURE COVER LAYOUT
 *    - Posts in "miniature" category get special cover-style treatment
 *    - Background image with overlay and centered miniature image
 *    - Visual styling: .ms-miniature-cover, .ms-miniature-background, .ms-miniature-overlay
 *
 * 2. CLICKABLE IMAGES
 *    - Makes first image clickable (links to post)
 *    - Also wraps figure captions in links
 *    - Exception: Posts in "blog" category are NOT made clickable
 *    - Handles miniature-set blocks (multiple images) specially
 *
 * 3. INFO LINES (under the caption)
 *    - Small right-aligned lines, one item each: original availability, medium on surface, size, prints
 *    - e.g. "Original available" / "Casein on paper" / "10" × 8"" / "Prints available"
 *    - Availability uses the price card's labels and colors (.status-label)
 *    - Visual styling: .ms-feed-info (hidden in grid view)
 *
 * 4. AVAILABILITY BADGES
 *    - Only used as the grid view's green dot (Available / Gallery); hidden in list view
 *    - Visual styling: .ms-availability-badge .ms-available or .ms-gallery
 *
 * 5. CATEGORY EXCLUSION
 *    - Automatically excludes 'miniature' and 'custom-artwork' from painting archive
 *    - Keeps painting category feed clean and focused
 *
 * HTML STRUCTURE CREATED:
 *
 * Standard Images:
 * <div class="ms-archive-image-wrapper ms-standard-image">
 *   <a href="[post-url]" class="ms-archive-image-link">
 *     <div class="ms-image-container">
 *       <img...>
 *       <div class="ms-availability-badge ms-available">Available</div>
 *     </div>
 *   </a>
 * </div>
 *
 * Miniature Images:
 * <div class="ms-archive-image-wrapper ms-miniature-cover">
 *   <a href="[post-url]" class="ms-archive-image-link">
 *     <div class="ms-miniature-background" style="background-image:url(...)">
 *       <div class="ms-miniature-overlay"></div>
 *       <div class="ms-miniature-image-container">
 *         <img...>
 *       </div>
 *       <div class="ms-availability-badge ms-available">Available</div>
 *     </div>
 *   </a>
 * </div>
 *
 * REPLACES:
 * - clickable-image.php (disabled) - Old basic clickability
 * - image-labels.php (disabled) - Old badge system with inline styles
 *
 * STYLING:
 * Custom CSS should target the classes above. All badges use CSS classes (not inline styles)
 * for consistent theming across the site.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * MAIN FILTER - Process images on archive pages
 * ============================================================ */

add_filter('the_content', 'monospace_process_archive_images', 10);
// add_filter('the_excerpt', 'monospace_process_archive_images', 10);

function monospace_process_archive_images($content) {
    // Only run on main feed pages
    if (!is_home() && !is_archive() && !is_search()) return $content;

    global $post;
    if (!$post) return $content;

    // Check if this is a miniature
    $is_miniature = has_category('miniature', $post);

    // Check if it's a blog post (no linking for blog posts)
    $is_blog = has_category('blog', $post);

    // Get product info for labels
    $product_info = monospace_get_product_info($post->ID);

    // Check if first image is in a miniature-set block
    $has_miniature_set = preg_match('/<div[^>]*class="[^"]*miniature-set[^"]*"[^>]*>/', $content);

    if ($has_miniature_set) {
        // Process miniature-set images
        $content = monospace_process_miniature_set($content, $is_blog);
    } else {
        // Process single first image
        $content = monospace_process_first_image($content, $is_miniature, $is_blog, $product_info);
    }

    return $content;
}

/* ============================================================
 * FALLBACK - a thumbnail for every post (grid view)
 * ============================================================ */

/**
 * The grid shows each post as a square tile - its first figure. A post whose first figure holds no picture (a video embed
 * first, a text post, pictures only inside columns) would be an empty spot (2026-10-23, owner: "make sure that all posts
 * listed in an archive view have a thumbnail"): it gets a figure of its own, first in the content - its featured image, else the
 * first picture in its text, else the art placeholder (Core's monospace_row_image_id()). Shown only in grid view (_archives.scss);
 * every other view shows the post as it is.
 */
add_filter('the_content', 'monospace_archive_thumb_fallback', 20);

function monospace_archive_thumb_fallback($content) {
    if (is_admin() || is_feed() || !in_the_loop() || !is_main_query() || !(is_home() || is_archive() || is_search())) return $content;
    if (!function_exists('monospace_row_image_id')) return $content;
    // The tile has its picture when the post's first figure holds one and sits at the top level of the content (a figure inside
    // columns or a group isn't the tile - the grid takes the post's own first figure)
    $pos = stripos($content, '<figure');
    if ($pos !== false && preg_match('/<figure\b.*?<\/figure>/is', $content, $figure, 0, $pos) && stripos($figure[0], '<img') !== false) {
        $depth = 0;
        if (preg_match_all('/<(\/?)(?:div|section|aside|ul|ol|blockquote|table|details)\b[^>]*>/i', substr($content, 0, $pos), $tags, PREG_SET_ORDER)) {
            foreach ($tags as $tag) $depth += $tag[1] === '/' ? -1 : 1;
        }
        if ($depth <= 0) return $content;
    }

    $post_id = (int) get_the_ID();
    $image_id = monospace_row_image_id($post_id);
    $img = $image_id ? wp_get_attachment_image($image_id, 'large', false, ['alt' => get_the_title($post_id), 'loading' => 'lazy', 'decoding' => 'async']) : '';
    if ($img === '') return $content;
    return '<figure class="ms-thumb-fallback">' . monospace_build_standard_image($img, ['status' => '', 'miniature' => false], get_the_permalink($post_id)) . '</figure>' . $content;
}

/* ============================================================
 * HELPER - Get product information
 * ============================================================ */

function monospace_get_product_info($post_id) {
    $info = [
        'size' => '',
        'medium' => '',
        'type' => '',
        'status' => '',
        'label_text' => '',
        'info_line' => '',
        'miniature' => false,
    ];

    // The post's linked product (saved by the Quick Product editor)
    $product_id = monospace_get_post_product_id($post_id);
    if (!$product_id) {
        return $info;
    }

    $product = wc_get_product($product_id);
    if (!$product) return $info;

    // Get size
    $size_terms = get_the_terms($product_id, 'pa_size');
    if ($size_terms && !is_wp_error($size_terms)) {
        $info['size'] = $size_terms[0]->slug;
    }

    // Get medium
    $medium_terms = get_the_terms($product_id, 'pa_medium');
    if ($medium_terms && !is_wp_error($medium_terms)) {
        $info['medium'] = $medium_terms[0]->slug;
    }

    // Get type (painting or drawing)
    global $post;
    if (has_category('drawing', $post)) {
        $info['type'] = 'drawing';
    } elseif (has_category('painting', $post)) {
        $info['type'] = 'painting';
    }

    // Build label text
    $parts = array_filter([$info['size'], $info['medium']]);
    if (has_category('miniature', $post)) {
        $parts[] = 'miniature';
    }
    if ($info['type']) {
        $parts[] = $info['type'];
    }

    $info['label_text'] = !empty($parts) ? implode(' ', $parts) :
        ($info['type'] ? 'full size ' . $info['type'] : '');

    $info['miniature'] = function_exists('monospace_is_miniature') ? (bool) monospace_is_miniature($post_id) : has_category('miniature', $post_id);

    // Get availability status
    $acf_status = get_post_meta($product_id, 'painting_availability_status', true);

    // Check WooCommerce stock status FIRST - if out of stock, treat as sold
    // This ensures display is correct even if auto-sync hasn't run yet
    if (!$product->is_in_stock() && $acf_status !== 'private') {
        $acf_status = 'sold';
    }

    // Set badge based on status (sold first, then others)
    if ($acf_status === 'sold' || $acf_status === 'private') {
        // No badge for sold/private items
        $info['status'] = '';
    } elseif ($acf_status === 'gallery' || $acf_status === 'gallery-reserved') {
        $info['status'] = 'Gallery';
    } elseif ($acf_status === 'reserved') {
        $info['status'] = 'Reserved';
    } elseif ($acf_status === 'available') {
        $info['status'] = 'Available';
    }

    $info['info_line'] = monospace_build_feed_info($post_id, $product, $acf_status);

    return $info;
}

/* ============================================================
 * BUILD - Info line under the caption
 * ============================================================ */

/**
 * The info under the feed image's caption (list view): Core's three lines, the same as the detail list's
 * (monospace_artwork_info_html(): "medium on surface, dimensions" / availability #number price / prints available), all in
 * the UI font. The type is _archives.scss' (.ms-info); the wrapper's place and alignment are _main-feed-images.scss'.
 * (2026-10-23, owner: "the info should be formatted the same on both list and detail view")
 */
function monospace_build_feed_info($post_id, $product, $status) {
    if (!function_exists('monospace_artwork_info_html')) return '';
    return '<div class="ms-feed-info">' . monospace_artwork_info_html($post_id, $product) . '</div>';
}

/* ============================================================
 * PROCESS - Miniature-set blocks
 * ============================================================ */

function monospace_process_miniature_set($content, $is_blog) {
    if ($is_blog) return $content;

    global $post;
    $permalink = get_the_permalink();
    $product_info = monospace_get_product_info($post->ID);

    // Find miniature-set grid (div inside figure)
    $pattern = '/(<div[^>]*class="[^"]*miniature-set-grid[^"]*"[^>]*>)(.*?)(<\/div>)/is';

    $content = preg_replace_callback($pattern, function($matches) use ($permalink, $product_info) {
        $grid_open = $matches[1];
        $grid_content = $matches[2];
        $grid_close = $matches[3];

        // Make each image clickable
        $grid_content = preg_replace_callback('/<img([^>]+)>/i', function($img_match) use ($permalink) {
            $img = $img_match[0];
            return '<a href="' . esc_url($permalink) . '" class="ms-miniature-link">' . $img . '</a>';
        }, $grid_content);

        // Build labels
        $labels = monospace_build_labels($product_info);

        // Wrap grid in positioned container with labels overlaying
        return '<div class="ms-miniature-set-wrapper" style="position: relative; display: inline-block; width: 100%;">'
            . $grid_open
            . $grid_content
            . $grid_close
            . $labels
            . '</div>';
    }, $content, 1);

    // Info lines (availability, materials, size, prints) after the set's caption, like single images
    if ($product_info['info_line']) {
        $content = preg_replace('/(<figure[^>]*monospace-miniature-set.*?)(<\/figure>)/s', '$1' . str_replace('$', '\\$', $product_info['info_line']) . '$2', $content, 1);
    }
    return $content;
}

/* ============================================================
 * PROCESS - First image (standard or miniature cover style)
 * ============================================================ */

function monospace_process_first_image($content, $is_miniature, $is_blog, $product_info) {
    // Check if first image is already linked
    if (preg_match('/<a[^>]*>\s*<img[^>]+>\s*<\/a>/i', $content, $linked_match, PREG_OFFSET_CAPTURE)) {
        $linked_pos = $linked_match[0][1];
    } else {
        $linked_pos = PHP_INT_MAX;
    }

    // Find first image
    if (!preg_match('/<img[^>]+>/i', $content, $img_match, PREG_OFFSET_CAPTURE)) {
        return $content;
    }

    $img_pos = $img_match[0][1];
    $img_tag = $img_match[0][0];

    // If first image is already linked, don't process
    if ($linked_pos <= $img_pos) {
        return $content;
    }

    // Extract image src
    if (!preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $src_match)) {
        return $content;
    }
    $src = $src_match[1];

    $permalink = get_the_permalink();

    // Check if image is inside a figure with figcaption
    if (preg_match('/<figure[^>]*>(.*?<img[^>]+>.*?)(?:<figcaption[^>]*>(.*?)<\/figcaption>)?(.*?)<\/figure>/is', $content, $figure_match, PREG_OFFSET_CAPTURE)) {
        if ($figure_match[0][1] <= $img_pos) {
            // Image is in a figure
            $figure_html = $figure_match[0][0];
            $caption_text = isset($figure_match[2]) ? $figure_match[2][0] : '';

            // Build wrapper with labels
            if ($is_miniature) {
                $wrapper = monospace_build_miniature_cover($src, $img_tag, $product_info, $is_blog ? '' : $permalink);
            } else {
                $wrapper = monospace_build_standard_image($img_tag, $product_info, $is_blog ? '' : $permalink);
            }

            // Rebuild figure, keeping block style classes (e.g. is-style-no-shadow)
            preg_match_all('/\bis-style-[\w-]+/', substr($figure_html, 0, strpos($figure_html, '>')), $style_classes);
            $new_figure = $style_classes[0] ? '<figure class="' . esc_attr(implode(' ', $style_classes[0])) . '">' : '<figure>';
            $new_figure .= $wrapper;

            if ($caption_text) {
                if ($is_blog) {
                    // No link for blog posts
                    $new_figure .= '<figcaption>' . $caption_text . '</figcaption>';
                } else {
                    // Wrap caption in link
                    $new_figure .= '<figcaption><a href="' . esc_url($permalink) . '" class="caption-link">' . $caption_text . '</a></figcaption>';
                }
            }

            $new_figure .= $product_info['info_line'];
            $new_figure .= '</figure>';

            return str_replace($figure_html, $new_figure, $content);
        }
    }

    // No figure, process standalone image
    if ($is_miniature) {
        $wrapper = monospace_build_miniature_cover($src, $img_tag, $product_info, $is_blog ? '' : $permalink);
    } else {
        $wrapper = monospace_build_standard_image($img_tag, $product_info, $is_blog ? '' : $permalink);
    }

    // Replace first image
    return preg_replace('/<img[^>]+>/i', $wrapper . $product_info['info_line'], $content, 1);
}

/* ============================================================
 * BUILD - Miniature cover wrapper
 * ============================================================ */

// The blurred background only needs a resized copy - the original upload can be several MB.
// Falls back to the given URL when it isn't a media library image.
function monospace_cover_background_url($src, $size) {
    $attachment_id = attachment_url_to_postid(preg_replace('/-\d+x\d+(?=\.\w+$)/', '', $src));
    if (!$attachment_id) return $src;
    return wp_get_attachment_image_url($attachment_id, $size) ?: $src;
}

function monospace_build_miniature_cover($src, $img_tag, $product_info, $permalink) {
    $labels = monospace_build_labels($product_info);

    $link_open = $permalink ? '<a href="' . esc_url($permalink) . '" class="ms-archive-image-link">' : '';
    $link_close = $permalink ? '</a>' : '';

    return '
    <div class="ms-archive-image-wrapper ms-miniature-cover">
        ' . $link_open . '
        <div class="ms-miniature-background" style="background-image:url(' . esc_url(monospace_cover_background_url($src, 'medium_large')) . ');">
            <div class="ms-miniature-overlay"></div>
            <div class="ms-miniature-image-container">
                ' . $img_tag . '
            </div>
            ' . $labels . '
        </div>
        ' . $link_close . '
    </div>';
}

/* ============================================================
 * SINGLE POST - Miniature cover on the post page too
 * ============================================================ */

// A miniature's post page shows its first image as in the feed: centered on a blurred, darkened copy
// of itself (the cover above, minus the badge and post link). Desktop only - on phones CSS undoes the
// cover (.ms-miniature-cover-single, _main-feed-images.scss) so the image shows as before. Runs
// before the lightbox (lightbox.php, priority 20), which still wraps the image, so clicking it opens
// the lightbox as usual. Posts whose first image is a miniature-set block are left alone.
add_filter('the_content', 'monospace_single_miniature_cover', 10);

function monospace_single_miniature_cover($content) {
    if (!is_single() || !in_the_loop() || !is_main_query()) return $content;

    global $post;
    if (!$post || !has_category('miniature', $post)) return $content;
    if (preg_match('/<div[^>]*class="[^"]*miniature-set[^"]*"[^>]*>/', $content)) return $content;

    if (!preg_match('/<img[^>]+>/i', $content, $img_match, PREG_OFFSET_CAPTURE)) return $content;
    list($img_tag, $img_pos) = $img_match[0];

    // Already linked (e.g. to a custom URL): leave it
    if (preg_match('/<a\b[^>]*>\s*$/i', substr($content, 0, $img_pos))) return $content;
    if (!preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $src_match)) return $content;

    $cover = '<div class="ms-archive-image-wrapper ms-miniature-cover ms-miniature-cover-single">'
           . '<div class="ms-miniature-background" style="background-image:url(' . esc_url(monospace_cover_background_url($src_match[1], 'large')) . ');">'
           . '<div class="ms-miniature-overlay"></div>'
           . '<div class="ms-miniature-image-container">' . $img_tag . '</div>'
           . '</div></div>';

    return substr_replace($content, $cover, $img_pos, strlen($img_tag));
}

/* ============================================================
 * BUILD - Standard image wrapper
 * ============================================================ */

function monospace_build_standard_image($img_tag, $product_info, $permalink) {
    $labels = monospace_build_labels($product_info);

    $link_open = $permalink ? '<a href="' . esc_url($permalink) . '" class="ms-archive-image-link">' : '';
    $link_close = $permalink ? '</a>' : '';

    return '
    <div class="ms-archive-image-wrapper ms-standard-image">
        ' . $link_open . '
        <div class="ms-image-container">
            ' . $img_tag . '
            ' . $labels . '
        </div>
        ' . $link_close . '
    </div>';
}

/* ============================================================
 * BUILD - Labels (attributes + availability)
 * ============================================================ */

function monospace_build_labels($product_info) {
    $html = '';

    // Size / medium now live in the info line under the caption (monospace_build_feed_info()).
    // The availability badge stays only for the grid view's green dot - list view hides it.
    if ($product_info['status']) {
        $badge_class = 'ms-' . strtolower($product_info['status']);
        if ($product_info['status'] === 'Available') {
            $badge_class = 'ms-available';
        } elseif ($product_info['status'] === 'Gallery') {
            $badge_class = 'ms-gallery';
        } elseif ($product_info['status'] === 'Reserved') {
            $badge_class = 'ms-reserved';
        }
        $html .= '<div class="ms-availability-badge ' . $badge_class . '">' . esc_html($product_info['status']) . '</div>';
    }

    // "M" for a miniature or a miniature set, opposite the dot (grid view only - _main-feed-images.scss)
    if (!empty($product_info['miniature'])) {
        $html .= '<span class="ms-miniature-mark" title="Miniature" aria-label="Miniature">M</span>';
    }

    return $html;
}

/* ============================================================
 * EXCLUDE - Categories from main painting category
 * ============================================================ */

function monospace_exclude_from_paintings($query) {
    if (!is_admin() && $query->is_main_query() && is_category('painting')) {
        // Categories to exclude from the painting archive
        $excluded_categories = ['miniature', 'custom-artwork'];

        // Get all posts from excluded categories
        $excluded_posts = get_posts([
            'post_type' => 'post',
            'posts_per_page' => -1,
            'category_name' => implode(',', $excluded_categories),
            'fields' => 'ids',
        ]);

        if (!empty($excluded_posts)) {
            $query->set('post__not_in', $excluded_posts);
        }
    }
}
add_action('pre_get_posts', 'monospace_exclude_from_paintings');
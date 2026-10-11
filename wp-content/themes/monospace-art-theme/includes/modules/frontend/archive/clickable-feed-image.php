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
 * 3. PRODUCT ATTRIBUTE LABELS (Bottom-Right)
 *    - Displays size (e.g., "10x8"), medium (e.g., "casein"), and type (painting/drawing)
 *    - Example: "10x8 casein painting" or "4x4 miniature drawing"
 *    - Pulls data from WooCommerce product attributes (pa_size, pa_medium)
 *    - Visual styling: .ms-attribute-label
 *
 * 4. AVAILABILITY BADGES (Bottom-Left)
 *    - Shows "Available" (yellow) or "Gallery" (white) based on product status
 *    - Only shown when WooCommerce product has stock > 0
 *    - Respects ACF field 'painting_availability_status'
 *    - No badge for sold/private items
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
 *       <div class="ms-attribute-label">10x8 casein painting</div>
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
 *       <div class="ms-attribute-label">4x4 miniature painting</div>
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
 * HELPER - Get product information
 * ============================================================ */

function monospace_get_product_info($post_id) {
    $info = [
        'size' => '',
        'medium' => '',
        'type' => '',
        'status' => '',
        'label_text' => '',
    ];

    // Look for product ID in painting_buy_button shortcode
    $post_content = get_post_field('post_content', $post_id);
    if (!preg_match('/\[painting_buy_button[^\]]*id=["\']?(\d+)["\']?[^\]]*\]/i', $post_content, $m)) {
        return $info;
    }

    $product_id = intval($m[1]);
    if (!$product_id || !function_exists('wc_get_product')) {
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
    } elseif ($acf_status === 'gallery') {
        $info['status'] = 'Gallery';
    } elseif ($acf_status === 'reserved') {
        $info['status'] = 'Reserved';
    } elseif ($acf_status === 'available') {
        $info['status'] = 'Available';
    }

    return $info;
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

    return preg_replace_callback($pattern, function($matches) use ($permalink, $product_info) {
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
        return '<div style="position: relative; display: inline-block; width: 100%;">'
            . $grid_open
            . $grid_content
            . $grid_close
            . $labels
            . '</div>';
    }, $content);
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

            // Rebuild figure
            $new_figure = '<figure>';
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
    return preg_replace('/<img[^>]+>/i', $wrapper, $content, 1);
}

/* ============================================================
 * BUILD - Miniature cover wrapper
 * ============================================================ */

function monospace_build_miniature_cover($src, $img_tag, $product_info, $permalink) {
    $labels = monospace_build_labels($product_info);

    $link_open = $permalink ? '<a href="' . esc_url($permalink) . '" class="ms-archive-image-link">' : '';
    $link_close = $permalink ? '</a>' : '';

    return '
    <div class="ms-archive-image-wrapper ms-miniature-cover">
        ' . $link_open . '
        <div class="ms-miniature-background" style="background-image:url(' . esc_url($src) . ');">
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

    // Attribute label (bottom-right)
    if ($product_info['label_text']) {
        $html .= '<div class="ms-attribute-label">' . esc_html($product_info['label_text']) . '</div>';
    }

    // Availability badge (bottom-left)
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
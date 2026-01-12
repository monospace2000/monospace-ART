<?php
/**
 * Archive Image Enhancements
 *
 * Handles special styling and clickability for images on archive/feed/search pages:
 * - Miniature cover layout for posts in "miniature" category
 * - Attribute labels (size, medium, type)
 * - Availability badges
 * - Clickable images (except for "blog" category)
 *
 * Related Files:
 * - Custom CSS can target .ms-archive-image-wrapper and child elements
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * MAIN FILTER - Process images on archive pages
 * ============================================================ */

add_filter('the_content', 'monospace_process_archive_images', 10);
add_filter('the_excerpt', 'monospace_process_archive_images', 10);

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
    if ($product->get_stock_quantity() > 0) {
        $acf_status = get_post_meta($product_id, 'painting_availability_status', true);

        if ($acf_status === 'gallery') {
            $info['status'] = 'Gallery';
        } elseif ($acf_status !== 'sold' && $acf_status !== 'private') {
            $info['status'] = 'Available';
        }
    } else {
        // Check post meta as fallback
        $acf_status = get_post_meta($post->ID, 'painting_availability_status', true);
        if ($acf_status === 'gallery') {
            $info['status'] = 'Gallery';
        } elseif ($acf_status === 'available') {
            $info['status'] = 'Available';
        }
    }

    return $info;
}

/* ============================================================
 * PROCESS - Miniature-set blocks
 * ============================================================ */

function monospace_process_miniature_set($content, $is_blog) {
    if ($is_blog) return $content;

    // Make all images in miniature-set clickable
    $permalink = get_the_permalink();

    // Find miniature-set wrapper
    $pattern = '/(<div[^>]*class="[^"]*miniature-set[^"]*"[^>]*>)(.*?)(<\/div>)/is';

    return preg_replace_callback($pattern, function($matches) use ($permalink) {
        $wrapper_open = $matches[1];
        $inner_content = $matches[2];
        $wrapper_close = $matches[3];

        // Add wrapper class for styling
        $wrapper_open = str_replace('class="', 'class="ms-miniature-set-clickable ', $wrapper_open);

        // Wrap each image in a link if not already linked
        $inner_content = preg_replace_callback('/<img([^>]+)>/i', function($img_match) use ($permalink) {
            $img = $img_match[0];

            // Check if this img is already inside an <a> tag
            // This is a simple check - assumes no complex nesting
            static $in_link = false;

            return '<a href="' . esc_url($permalink) . '" class="ms-miniature-link">' . $img . '</a>';
        }, $inner_content);

        return $wrapper_open . $inner_content . $wrapper_close;
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
        $badge_class = $product_info['status'] === 'Available' ? 'ms-available' : 'ms-gallery';
        $html .= '<div class="ms-availability-badge ' . $badge_class . '">' . esc_html($product_info['status']) . '</div>';
    }

    return $html;
}

/* ============================================================
 * EXCLUDE - Miniatures from main painting category
 * ============================================================ */

function monospace_exclude_miniatures_from_paintings($query) {
    if (!is_admin() && $query->is_main_query() && is_category('painting')) {
        $miniature_posts = get_posts([
            'post_type' => 'post',
            'posts_per_page' => -1,
            'category_name' => 'miniature',
            'fields' => 'ids',
        ]);

        if (!empty($miniature_posts)) {
            $query->set('post__not_in', $miniature_posts);
        }
    }
}
add_action('pre_get_posts', 'monospace_exclude_miniatures_from_paintings');
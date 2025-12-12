<?php

/**
 * Helper: determine if a post is a 4x4 miniature by checking the linked product's pa_size term.
 */
function monospace_is_miniature($post_id) {
    // Get post content
    $content = get_post_field('post_content', $post_id);
    if (!$content) return false;

    // Flexible regex to match the shortcode even with other attributes:
    if (!preg_match('/\[painting_buy_button[^\]]*id=["\']?(\d+)["\']?[^\]]*\]/i', $content, $m)) {
        return false;
    }

    $product_id = intval($m[1]);
    if (!$product_id) return false;

    // Get pa_size terms for the product
    $size_terms = get_the_terms($product_id, 'pa_size');
    if (is_wp_error($size_terms) || empty($size_terms)) return false;

    foreach ($size_terms as $term) {
        if (isset($term->slug) && $term->slug === '4x4') {
            return true;
        }
    }

    return false;
}

/**
 * Display miniatures with special cover-style layout on archive pages
 */
add_filter('the_content', 'monospace_miniature_cover_fullwidth', 10);
add_filter('the_excerpt', 'monospace_miniature_cover_fullwidth', 10);

function monospace_miniature_cover_fullwidth($content) {
    // Only apply on home or archive pages
    if (!is_home() && !is_archive()) return $content;

    global $post;
    if (!$post) return $content;

    // Only apply to miniature (4x4) posts
    if (!monospace_is_miniature($post->ID)) return $content;

    // Find the first image tag
    if (!preg_match('/<img[^>]+>/i', $content, $match)) return $content;

    $img_tag = $match[0];

    // Extract src
    if (!preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $src_match)) return $content;

    $src = $src_match[1];

    // =====================
    // CONFIGURABLE OPTIONS
    // =====================
    $overlay_opacity = 0.5;   // 0 = transparent, 1 = black
    $mini_scale      = 0.95;  // 1 = full size, 0.5 = 50%
    $cover_height    = '60vh';

    // Wrapper mimicking WP Cover
    $replacement = '
    <div style="
        position:relative;
        width:100%;
        height:' . esc_attr($cover_height) . ';
        background-image:url(' . esc_url($src) . ');
        background-size:cover;
        background-position:center;
        overflow:hidden;
        margin:1em 0;
        box-shadow: 0px 0px 8px rgba(0, 0, 0, 0.6);
    ">
        <!-- Dark overlay -->
        <div style="
            position:absolute;
            inset:0;
            background:rgba(0,0,0,' . esc_attr($overlay_opacity) . ');
        "></div>

        <!-- Centered mini image -->
        <div style="
            position:absolute;
            top:50%;
            left:50%;
            transform:translate(-50%, -50%) scale(' . esc_attr($mini_scale) . ');
            transform-origin:center;
        ">
            ' . $img_tag . '
        </div>
    </div>
    ';

    return preg_replace('/<img[^>]+>/i', $replacement, $content, 1);
}

/**
 * Add product attribute label (size, medium, painting/drawing) to images on archive pages
 */
add_filter('the_content', 'monospace_add_image_label', 20);
add_filter('the_excerpt', 'monospace_add_image_label', 20);

function monospace_add_image_label($content) {
    global $post;
    if (!$post) return $content;

    // Only apply on home or archive pages
    if (!is_home() && !is_archive()) return $content;

    // Must be painting OR drawing
    if (!has_category(['painting', 'drawing'], $post)) return $content;

    // Check if miniature
    $is_miniature = monospace_is_miniature($post->ID);

    // Find first image - for miniatures, it's already wrapped in special div
    if ($is_miniature) {
        // For miniatures, find the cover div wrapper
        if (!preg_match('/(<div[^>]*background-image[^>]*>.*?<\/div>)/is', $content, $match)) return $content;
        $wrapper = $match[0];
    } else {
        // For full-size, find first image
        if (!preg_match('/<img[^>]+>/i', $content, $match)) return $content;
        $img_tag = $match[0];
    }

    // Default label text
    $label_text = has_category('drawing', $post)
        ? 'full size drawing'
        : 'full size painting';

    // Look for product ID via shortcode
    $post_content = get_post_field('post_content', $post->ID);
    if (preg_match('/\[painting_buy_button\s+id=["\']?(\d+)["\']?\]/i', $post_content, $shortcode_match)) {

        $product_id = intval($shortcode_match[1]);

        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);

            if ($product) {
                $parts = [];

                $size_terms = get_the_terms($product_id, 'pa_size');
                if ($size_terms && !is_wp_error($size_terms)) {
                    $parts[] = $size_terms[0]->slug;
                }

                $medium_terms = get_the_terms($product_id, 'pa_medium');
                if ($medium_terms && !is_wp_error($medium_terms)) {
                    $parts[] = $medium_terms[0]->slug;
                }

                // Add "miniature" if it's a 4x4
                if ($is_miniature) {
                    $parts[] = 'miniature';
                }

                $parts[] = has_category('drawing', $post) ? 'drawing' : 'painting';

                if (!empty($parts)) {
                    $label_text = implode(' ', $parts);
                }
            }
        }
    }

    // Label HTML
    $label_html = '
        <div style="
            position:absolute;
            bottom:10px;
            right:10px;
            padding:4px 8px;
            font-size:12px;
            letter-spacing:0.5px;
            background:rgba(0,0,0,0.6);
            color:white;
            border-radius:4px;
            font-family:inherit;
            z-index:5;
        ">
            ' . esc_html($label_text) . '
        </div>
    ';

    if ($is_miniature) {
        // For miniatures, insert label before closing </div> of the cover wrapper
        $replacement = str_replace('</div>', $label_html . '</div>', $wrapper);
        return str_replace($wrapper, $replacement, $content);
    } else {
        // For full-size, wrap image in relative div with label
        $replacement = '
        <div style="position:relative; display:inline-block; width:100%;">
            ' . $img_tag . $label_html . '
        </div>
        ';
        return preg_replace('/<img[^>]+>/i', $replacement, $content, 1);
    }
}

/**
 * Add availability badge (bottom-left) inside existing image wrappers on archive pages.
 */
add_filter('the_content', 'monospace_add_availability_badge', 30);
add_filter('the_excerpt', 'monospace_add_availability_badge', 30);

function monospace_add_availability_badge($content) {
    if (!is_archive() && !is_home()) return $content;
    global $post;
    if (!$post) return $content;

    $status = ''; // default: no badge

    // Try to get product ID from shortcode
    $product_id = null;
    if (preg_match('/painting_buy_button\s+id=["\']?(\d+)["\']?\]/i', $post->post_content, $m)) {
        $product_id = intval($m[1]);
    }

    if ($product_id && function_exists('wc_get_product')) {
        $product = wc_get_product($product_id);

        if ($product && $product->get_stock_quantity() > 0) {
            // WooCommerce stock is available

            // Get availability status from PRODUCT meta (not post meta)
            $acf_status = get_post_meta($product_id, 'painting_availability_status', true);

            if ($acf_status === 'sold' || $acf_status === 'private') {
                // No badge for sold or private
                $status = '';
            } elseif ($acf_status === 'gallery') {
                // Show Gallery badge
                $status = 'Gallery';
            } else {
                // Show Available badge (this covers 'available' or any other value)
                $status = 'Available';
            }
        } else {
            // WooCommerce stock is 0 or product doesn't exist → no badge
            $status = '';
        }
    } else {
        // No WooCommerce product linked → check post meta as fallback
        $acf_status = get_post_meta($post->ID, 'painting_availability_status', true);

        if ($acf_status === 'gallery') {
            $status = 'Gallery';
        } elseif ($acf_status === 'available') {
            $status = 'Available';
        }
        // sold/private get no badge
    }

    // Only insert badge if status is set
    if (empty($status)) return $content;

    $badge_color = ($status === 'Available') ? '#fc0' : '#fff';

    $badge_html = '
        <div class="ms-availability-badge" style="
            position:absolute;
            bottom:10px;
            left:10px;
            padding:4px 8px;
            font-size:12px;
            letter-spacing:0.5px;
            background:rgba(0,0,0,0.6);
            color:' . esc_attr($badge_color) . ';
            border-radius:4px;
            font-family:inherit;
            z-index:5;
        ">' . esc_html($status) . '</div>
    ';

    // Insert badge inside the first wrapper with position:relative
    $pattern = '/(<div[^>]*position:relative[^>]*>)/i';
    return preg_replace($pattern, '$1' . $badge_html, $content, 1);
}

// exclude miniatures from paintings page
function monospace_exclude_miniatures_from_paintings($query) {
    if (!is_admin() && $query->is_main_query() && is_category('painting')) {

        // Find all posts in category 'painting' that are miniature by product attribute
        $exclude_ids = [];

        $posts = get_posts([
            'post_type' => 'post',
            'posts_per_page' => -1,
            'category' => get_cat_ID('painting'),
        ]);

        foreach ($posts as $p) {
            if (monospace_is_miniature($p->ID)) {
                $exclude_ids[] = $p->ID;
            }
        }

        if (!empty($exclude_ids)) {
            $query->set('post__not_in', $exclude_ids);
        }
    }
}
add_action('pre_get_posts', 'monospace_exclude_miniatures_from_paintings');
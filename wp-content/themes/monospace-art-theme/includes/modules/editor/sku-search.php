<?php
/**
 * WooCommerce SKU Search for Relevanssi
 * Redirects to related post when SKU is found
 * Searches last 4 digits of SKU (matches 1-4 digits at the END)
 */

/**
 * Add WooCommerce SKU to Relevanssi index
 */
add_filter('relevanssi_index_custom_fields', function($fields) {
    $fields[] = '_sku';
    return $fields;
});

/**
 * Add SKU matches to search results (last 4 digits only)
 */
add_filter('the_posts', function($posts, $query) {
    // Only run on main search query
    if (!$query->is_main_query() || !$query->is_search()) {
        return $posts;
    }

    global $wpdb;

    $search_term = get_search_query();

    // Check if search term is 1-4 digits
    if (preg_match('/^\d{1,4}$/', $search_term)) {
        // Search for SKUs ending with these exact digits
        // Pattern: MNSPC-2026-Q-0196 where we match "-0196" at the end
        $sku_posts = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key = '_sku'
            AND meta_value REGEXP %s",
            '-0*' . $search_term . '$'
        ));

        // Add SKU matches to posts
        foreach ($sku_posts as $sku_post) {
            $post = get_post($sku_post->post_id);
            if ($post && $post->post_status === 'publish') {
                $posts[] = $post;
            }
        }
    }

    // Now replace products with their related posts
    $modified_posts = [];

    foreach ($posts as $post) {
        // If it's a product, swap it for the related post
        if ($post->post_type === 'product') {
            $related_post_id = get_post_meta($post->ID, '_related_post_id', true);

            if ($related_post_id) {
                $related_post = get_post($related_post_id);
                if ($related_post && $related_post->post_status === 'publish') {
                    $modified_posts[] = $related_post;
                    continue;
                }
            }
            // Skip products without related posts
            continue;
        }

        // Keep non-product posts
        $modified_posts[] = $post;
    }

    // Remove duplicates (in case related post is also in results)
    $unique_posts = [];
    $seen_ids = [];
    foreach ($modified_posts as $post) {
        if (!in_array($post->ID, $seen_ids)) {
            $unique_posts[] = $post;
            $seen_ids[] = $post->ID;
        }
    }

    return $unique_posts;
}, 999, 2);
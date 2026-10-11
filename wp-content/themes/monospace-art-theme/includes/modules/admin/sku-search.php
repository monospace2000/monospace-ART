<?php
/**
 * SKU Search with Smart Redirect
 *
 * Enhances WooCommerce product search by allowing users to search by SKU numbers.
 * Automatically redirects to the related blog post when a SKU is found.
 *
 * FUNCTIONALITY:
 * - Adds WooCommerce SKU field to Relevanssi search index
 * - Searches the last 1-4 digits of SKU codes
 * - When SKU match is found, redirects to the related blog post (not the product)
 * - Useful for finding artwork posts by their product SKU
 *
 * HOW IT WORKS:
 * 1. User searches for "1234" (last 4 digits of SKU)
 * 2. Relevanssi finds product with matching SKU
 * 3. System looks up related blog post via custom field 'related_blog_post_id'
 * 4. Redirects user to the artwork blog post instead of product page
 *
 * REQUIREMENTS:
 * - Relevanssi search plugin must be installed and active
 * - Products must have SKU codes set
 * - Blog posts must have 'related_blog_post_id' field linking to product
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
<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */





/**
 * Display related posts for the current post.
 *
 * Features:
 * - Uses transient caching for performance
 * - Single optimized query based on shared tags
 * - Sorted by relevance (shared tag count)
 * - Fallback posts if not enough related items
 * - Filterable settings for customization
 *
 * Shortcode: [related_posts]
 *
 * @since 1.0.0
 *
 * @return string HTML markup of related posts or empty string.
 */
function monospace_related_posts_simple() {
    global $post;

    if ( ! is_singular( 'post' ) || ! $post ) {
        return '';
    }

    $post_id = $post->ID;

    // Check cache first
    $cache_key = 'monospace_related_' . $post_id;
    $cached_output = get_transient( $cache_key );

    if ( false !== $cached_output ) {
        return $cached_output;
    }

    // Get settings (filterable)
    $settings = apply_filters( 'monospace_related_posts_settings', array(
        'limit'              => 4,
        'min_tags'           => 5,
        'fallback_image_url' => get_stylesheet_directory_uri() . '/images/fallback-thumb.jpg',
        'cache_time'         => 12 * HOUR_IN_SECONDS,
        'exclude_categories' => array( 'blog' ),
    ), $post_id );

    // Get related posts
    $related_posts = monospace_get_related_posts( $post_id, $settings );

    if ( empty( $related_posts ) ) {
        $output = apply_filters(
            'monospace_related_posts_empty',
            '<p>No related items found.</p>',
            $post_id
        );
        set_transient( $cache_key, $output, $settings['cache_time'] );
        return $output;
    }

    // Build HTML output
    $output = monospace_build_related_posts_html( $related_posts, $settings );

    // Cache the output
    set_transient( $cache_key, $output, $settings['cache_time'] );

    return $output;
}


/**
 * Get IDs of posts related to the given post.
 *
 * Relatedness is determined by shared tags and optional fallback posts.
 *
 * @since 1.0.0
 *
 * @param int   $post_id The current post ID.
 * @param array $settings Settings array:
 *                        - limit (int) number of posts to return
 *                        - min_tags (int) minimum shared tags required
 *                        - exclude_categories (array) category slugs to exclude
 * @return int[] Array of related post IDs.
 */
function monospace_get_related_posts( $post_id, $settings ) {
    global $wpdb;

    $limit    = absint( $settings['limit'] );
    $min_tags = absint( $settings['min_tags'] );

    $tags = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) );

    if ( empty( $tags ) ) {
        return monospace_get_fallback_posts( $post_id, $settings, $limit );
    }

    $exclude_cat_ids = monospace_get_excluded_category_ids( $settings['exclude_categories'] );

    $tag_ids_str = implode( ',', array_map( 'absint', $tags ) );
    $exclude_cats_str = ! empty( $exclude_cat_ids )
        ? implode( ',', array_map( 'absint', $exclude_cat_ids ) )
        : '0';

    $sql = "
        SELECT p.ID, COUNT(tr.term_taxonomy_id) as shared_tags
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND p.ID != %d
            AND tt.taxonomy = 'post_tag'
            AND tt.term_id IN ($tag_ids_str)
    ";

    if ( ! empty( $exclude_cat_ids ) ) {
        $sql .= "
            AND p.ID NOT IN (
                SELECT object_id FROM {$wpdb->term_relationships} tr2
                INNER JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
                WHERE tt2.taxonomy = 'category' AND tt2.term_id IN ($exclude_cats_str)
            )
        ";
    }

    $sql .= "
        GROUP BY p.ID
        HAVING shared_tags >= %d
        ORDER BY shared_tags DESC, RAND()
        LIMIT %d
    ";

    $results = $wpdb->get_results(
        $wpdb->prepare( $sql, $post_id, $min_tags, $limit ),
        ARRAY_A
    );

    $related_ids = $results ? wp_list_pluck( $results, 'ID' ) : array();

    $remaining = $limit - count( $related_ids );
    if ( $remaining > 0 ) {
        $fallback_ids = monospace_get_fallback_posts(
            $post_id,
            $settings,
            $remaining,
            $related_ids
        );
        $related_ids = array_merge( $related_ids, $fallback_ids );
    }

    return array_slice( $related_ids, 0, $limit );
}


/**
 * Get random fallback posts if not enough related posts found.
 *
 * @since 1.0.0
 *
 * @param int   $post_id Current post ID to exclude.
 * @param array $settings Related posts settings.
 * @param int   $limit Number of posts to return.
 * @param array $exclude Optional array of post IDs to exclude.
 * @return int[] Array of post IDs.
 */
function monospace_get_fallback_posts( $post_id, $settings, $limit, $exclude = array() ) {
    $exclude_cat_ids = monospace_get_excluded_category_ids( $settings['exclude_categories'] );
    $exclude = array_merge( array( $post_id ), $exclude );

    $args = array(
        'post_type'      => 'post',
        'posts_per_page' => $limit,
        'post__not_in'   => $exclude,
        'orderby'        => 'rand',
        'fields'         => 'ids',
    );

    if ( ! empty( $exclude_cat_ids ) ) {
        $args['category__not_in'] = $exclude_cat_ids;
    }

    $query = new WP_Query( $args );
    return $query->posts;
}


/**
 * Convert category slugs to term IDs for exclusion.
 *
 * @since 1.0.0
 *
 * @param string[] $category_slugs Array of category slugs.
 * @return int[] Array of category term IDs.
 */
function monospace_get_excluded_category_ids( $category_slugs ) {
    if ( empty( $category_slugs ) ) {
        return array();
    }

    $cat_ids = array();
    foreach ( (array) $category_slugs as $slug ) {
        $cat = get_category_by_slug( $slug );
        if ( $cat ) {
            $cat_ids[] = $cat->term_id;
        }
    }

    return $cat_ids;
}


/**
 * Build HTML for related posts.
 *
 * @since 1.0.0
 *
 * @param int[] $related_ids Array of related post IDs.
 * @param array $settings Settings array including fallback image URL.
 * @return string HTML markup for related posts.
 */
function monospace_build_related_posts_html( $related_ids, $settings ) {
    $output = '<div class="related-items">';
    $output .= apply_filters( 'monospace_related_posts_title', '<h3>Related:</h3>' );

    foreach ( $related_ids as $related_id ) {
        $link  = get_permalink( $related_id );
        $title = get_the_title( $related_id );
        $thumb = monospace_get_post_thumbnail( $related_id, $title, $settings['fallback_image_url'] );

        $output .= '<div class="related-item">';
        $output .= sprintf(
            '<a href="%s">%s<span class="related-title">%s</span></a>',
            esc_url( $link ),
            $thumb,
            esc_html( $title )
        );
        $output .= '</div>';
    }

    $output .= '</div>';

    return apply_filters( 'monospace_related_posts_html', $output, $related_ids );
}


/**
 * Get post thumbnail or fallback image.
 *
 * @since 1.0.0
 *
 * @param int    $post_id Post ID.
 * @param string $title Post title for alt attribute.
 * @param string $fallback_url URL of fallback image.
 * @return string HTML <img> tag or empty string.
 */
function monospace_get_post_thumbnail( $post_id, $title, $fallback_url ) {
    if ( has_post_thumbnail( $post_id ) ) {
        return get_the_post_thumbnail( $post_id, 'thumbnail' );
    }

    $content_image = monospace_get_first_content_image( $post_id );
    if ( $content_image ) {
        return sprintf(
            '<img src="%s" alt="%s" loading="lazy" style="box-shadow: 0px 0px 8px rgba(0, 0, 0, 0.3);border-radius: 5px;" />',
            esc_url( $content_image ),
            esc_attr( $title )
        );
    }

    if ( file_exists( str_replace( get_stylesheet_directory_uri(), get_stylesheet_directory(), $fallback_url ) ) ) {
        return sprintf(
            '<img src="%s" alt="No image" loading="lazy" style="box-shadow: 0px 0px 8px rgba(0, 0, 0, 0.3);border-radius: 5px;" />',
            esc_url( $fallback_url )
        );
    }

    return '';
}


/**
 * Get first image in post content.
 *
 * Uses DOMDocument if available, otherwise falls back to regex.
 *
 * @since 1.0.0
 *
 * @param int $post_id Post ID.
 * @return string|false Image URL or false if none found.
 */
function monospace_get_first_content_image( $post_id ) {
    $content = get_post_field( 'post_content', $post_id );
    if ( empty( $content ) ) {
        return false;
    }

    if ( class_exists( 'DOMDocument' ) ) {
        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        $dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) );
        libxml_clear_errors();

        $images = $dom->getElementsByTagName( 'img' );
        if ( $images->length > 0 ) {
            $src = $images->item(0)->getAttribute( 'src' );
            if ( $src ) {
                return $src;
            }
        }
    } else {
        preg_match( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );
        if ( ! empty( $matches[1] ) ) {
            return $matches[1];
        }
    }

    return false;
}


/**
 * Clear cached related posts when a post is updated or deleted.
 *
 * @since 1.0.0
 *
 * @param int $post_id Post ID.
 * @return void
 */
function monospace_clear_related_posts_cache( $post_id ) {
    if ( get_post_type( $post_id ) !== 'post' ) {
        return;
    }

    // Clear cache for this post
    delete_transient( 'monospace_related_' . $post_id );

    // Clear all related posts transients
    global $wpdb;
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_monospace_related_%'
         OR option_name LIKE '_transient_timeout_monospace_related_%'"
    );
}
add_action( 'save_post', 'monospace_clear_related_posts_cache' );
add_action( 'deleted_post', 'monospace_clear_related_posts_cache' );

// Register shortcode
add_shortcode( 'related_posts', 'monospace_related_posts_simple' );




<?php
/**
 * Related Posts Module
 *
 * Displays related posts based on shared tags, without caching.
 * Shortcode: [related_posts]
 *
 * @package astra-child-theme-for-monospace-art
 */

/**
 * Display related posts for the current post.
 *
 * @since 1.0.0
 * @return string HTML markup of related posts or empty string.
 */
function monospace_related_posts_display() {
    global $post;

    if ( ! is_singular( 'post' ) || ! $post ) {
        return '';
    }

    $post_id = $post->ID;

    // Get settings
    $settings = apply_filters( 'monospace_related_posts_settings', array(
        'limit'              => 4,
        'min_tags'           => 5,
        'fallback_image_url' => get_stylesheet_directory_uri() . '/images/fallback-thumb.jpg',
        'exclude_categories' => array( 'blog' ),
    ), $post_id );

    // Get related posts
    $related_ids = monospace_related_get_post_ids( $post_id, $settings );

    if ( empty( $related_ids ) ) {
        return '';
    }

    // Build HTML output
    return monospace_related_build_html( $related_ids, $settings );
}

/**
 * Get IDs of related posts.
 */
function monospace_related_get_post_ids( $post_id, $settings ) {
    global $wpdb;

    $limit    = absint( $settings['limit'] );
    $min_tags = absint( $settings['min_tags'] );

    $tags = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) );

    if ( empty( $tags ) ) {
        return monospace_related_get_fallback_posts( $post_id, $settings, $limit );
    }

    $exclude_cat_ids = monospace_related_get_excluded_category_ids( $settings['exclude_categories'] );

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
        $fallback_ids = monospace_related_get_fallback_posts( $post_id, $settings, $remaining, $related_ids );
        $related_ids = array_merge( $related_ids, $fallback_ids );
    }

    return array_slice( $related_ids, 0, $limit );
}

/**
 * Get random fallback posts.
 */
function monospace_related_get_fallback_posts( $post_id, $settings, $limit, $exclude = array() ) {
    $exclude_cat_ids = monospace_related_get_excluded_category_ids( $settings['exclude_categories'] );
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
 * Convert category slugs to term IDs.
 */
function monospace_related_get_excluded_category_ids( $category_slugs ) {
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
 */
function monospace_related_build_html( $related_ids, $settings ) {
    $output = '<div class="monospace-related-posts">';
    $output .= '<h3 class="monospace-related-heading">See also:</h3>';
    $output .= '<div class="monospace-related-grid">';

    foreach ( $related_ids as $related_id ) {
        $link  = get_permalink( $related_id );
        $title = get_the_title( $related_id );
        $thumb = monospace_related_get_thumbnail( $related_id, $title, $settings['fallback_image_url'] );

        $output .= '<div class="monospace-related-item">';
        $output .= sprintf( '<a href="%s" class="monospace-related-link">', esc_url( $link ) );
        $output .= '<div class="monospace-related-thumb">' . $thumb . '</div>';
        $output .= sprintf( '<span class="monospace-related-title">%s</span>', esc_html( $title ) );
        $output .= '</a>';
        $output .= '</div>';
    }

    $output .= '</div></div>';

    return apply_filters( 'monospace_related_posts_html', $output, $related_ids );
}

/**
 * Get post thumbnail or fallback.
 */
function monospace_related_get_thumbnail( $post_id, $title, $fallback_url ) {
    if ( has_post_thumbnail( $post_id ) ) {
        return get_the_post_thumbnail( $post_id, 'medium', array( 'class' => 'monospace-related-img data-nolightbox' ) );
    }

    $content_image = monospace_related_get_first_content_image( $post_id );
    if ( $content_image ) {
        return sprintf(
            '<img src="%s" alt="%s" class="monospace-related-img data-nolightbox" loading="lazy" />',
            esc_url( $content_image ),
            esc_attr( $title )
        );
    }

    return sprintf(
        '<img src="%s" alt="No image" class="monospace-related-img data-nolightbox" loading="lazy" />',
        esc_url( $fallback_url )
    );
}

/**
 * Get first image from post content.
 */
function monospace_related_get_first_content_image( $post_id ) {
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



// Shortcode
add_shortcode( 'related_posts', 'monospace_related_posts_display' );

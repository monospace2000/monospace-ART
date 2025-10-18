<?php

/**
 * Astra Child Theme for monospace | art Theme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Astra Child Theme for monospace | art
 * @since 1.0.0
 */

/**
 * Define Constants
 */
define( 'CHILD_THEME_ASTRA_CHILD_THEME_FOR_MONOSPACE_ART_VERSION', '1.0.0' );





/**
 * Enqueue the child theme stylesheet.
 *
 * This function loads the child theme's main stylesheet (`style.css`) 
 * after the parent Astra theme's stylesheet. The version is set using 
 * a defined constant to ensure proper cache busting when the child theme 
 * is updated.
 *
 * @since 1.0.0
 *
 * @return void
 */
function child_enqueue_styles() {

	wp_enqueue_style(
		'astra-child-theme-for-monospace-art-theme-css', // Handle for the child theme stylesheet
		get_stylesheet_directory_uri() . '/style.css',   // Path to the child theme stylesheet
		array('astra-theme-css'),                        // Dependencies (load after parent theme CSS)
		CHILD_THEME_ASTRA_CHILD_THEME_FOR_MONOSPACE_ART_VERSION, // Version number for cache busting
		'all'                                           // Media type
	);

}
add_action( 'wp_enqueue_scripts', 'child_enqueue_styles', 15 );


























/**
 * Make the first image in post content clickable on listing/archive pages.
 *
 * This function searches the post content for the first <img> tag and wraps 
 * it in a link to the post's single page. It only applies to home, search, 
 * tag, and category archive pages. Other pages or single post views are left unchanged.
 *
 * @since 1.0.0
 *
 * @param string $content The post content.
 * @return string Modified post content with the first image linked, or original content.
 */
function clickable_first_image( $content ) {

    // Only modify listing/archive pages
    if ( is_home() || is_search() || is_tag() || is_category() ) {

        // Use regex to find the first <img> tag
        if ( preg_match( '/<img[^>]+>/i', $content, $matches ) ) {
            $first_img = $matches[0];

            // Replace the first image with a linked version
            $linked_img = '<a href="' . esc_url( get_the_permalink() ) . '">' . $first_img . '</a>';

            // Replace only the first occurrence
            $content = preg_replace( '/<img[^>]+>/i', $linked_img, $content, 1 );
        }

        return $content;
    }

    // Other pages/posts: leave content unchanged
    return $content;
}
add_filter( 'the_content', 'clickable_first_image' );



























/**
 * Determine if the current request is in an editor, REST API, or admin context.
 *
 * This is used to decide whether to render a placeholder instead of full front-end
 * content, e.g., when rendering shortcodes in the block editor or during REST calls.
 *
 * @since 1.0.0
 *
 * @return bool True if in admin, REST API, or AJAX context; false otherwise.
 */
function monospace_is_editor_context() {
    if ( is_admin() ) return true;
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return true;
    if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) return true;
    return false;
}


/**
 * Shortcode: [painting_buy_button id="123"]
 *
 * Renders a "Buy Painting" button row for a given WooCommerce product ID.
 * - In editor/REST context, returns a placeholder div so saving works correctly.
 * - On the front-end, fetches product details, attributes, and renders the buy button
 *   or relevant status (sold, gallery, private, coming soon, etc.).
 *
 * @since 1.0.0
 *
 * @param array $atts Shortcode attributes. Expected:
 *                    - id (int) Product ID of the painting.
 * @return string HTML markup for the painting buy button row or a placeholder.
 */
function monospace_custom_add_to_cart_shortcode( $atts ) {
    $atts = shortcode_atts(
        array( 'id' => null ),
        $atts,
        'painting_buy_button'
    );

    $product_id = intval( $atts['id'] );
    if ( ! $product_id ) {
        return '';
    }

    // --- Editor/REST placeholder ---
    if ( monospace_is_editor_context() ) {
        return '<div class="painting-buy-placeholder" style="padding:6px;border:1px dashed #aaa;background:#f9f9f9;">'
             . 'Painting Buy Button (Product ID ' . esc_html( $product_id ) . ')'
             . '</div>';
    }

    // --- Front-end logic ---
    if ( ! function_exists( 'wc_get_product' ) ) return '';
    $product = wc_get_product( $product_id );
    if ( ! $product ) return '';

    $status       = function_exists( 'get_field' ) ? get_field( 'painting_availability_status', $product_id ) : '';
    $gallery_url  = function_exists( 'get_field' ) ? get_field( 'painting_gallery_url', $product_id ) : '';
    $gallery_name = function_exists( 'get_field' ) ? get_field( 'painting_gallery_name', $product_id ) : '';

    $attr_list = monospace_render_product_attributes( $product, $product_id );
    $button    = monospace_render_buy_button( $product, $status, $gallery_url, $gallery_name );

    $status_slug  = $status ? sanitize_title( $status ) : 'default';
    $status_class = ' status-' . $status_slug;

    return sprintf(
        '<div class="painting-buy-row%s" data-status="%s">
            <div class="painting-attrs">%s</div>
            <div class="painting-action">%s</div>
        </div>',
        esc_attr( $status_class ),
        esc_attr( $status_slug ),
        $attr_list,
        $button
    );
}
add_shortcode( 'painting_buy_button', 'monospace_custom_add_to_cart_shortcode' );


/**
 * Render product attributes in a fixed order with fallback to remaining attributes.
 *
 * Attributes are rendered in the order: format, medium, surface, size.
 * Any remaining attributes are appended in no particular order.
 *
 * @since 1.0.0
 *
 * @param WC_Product $product The WooCommerce product object.
 * @param int        $product_id The product ID.
 * @return string HTML markup for all attributes.
 */
function monospace_render_product_attributes( $product, $product_id ) {
    $order      = array( 'format', 'medium', 'surface', 'size' );
    $attributes = $product->get_attributes();
    $output     = array();

    $render_attr = function( $attribute, $product_id ) {
        $label = wc_attribute_label( $attribute->get_name() );
        if ( $attribute->is_taxonomy() ) {
            $terms = wp_get_post_terms( $product_id, $attribute->get_name(), array( 'fields' => 'names' ) );
            $value = implode( ', ', $terms );
        } else {
            $value = $attribute->get_options() ? implode( ', ', $attribute->get_options() ) : '';
        }
        return $value ? '<div class="painting-attr"><b>' . esc_html( $label ) . '</b>: ' . esc_html( $value ) . '</div>' : '';
    };

    foreach ( $order as $attr_name ) {
        foreach ( $attributes as $key => $attribute ) {
            if ( strtolower( wc_attribute_label( $attribute->get_name() ) ) === strtolower( $attr_name ) ) {
                $output[] = $render_attr( $attribute, $product_id );
                unset( $attributes[ $key ] );
                break;
            }
        }
    }

    foreach ( $attributes as $attribute ) {
        $output[] = $render_attr( $attribute, $product_id );
    }

    return implode( '', $output );
}


/**
 * Render the buy button or status label for a product.
 *
 * Handles the following statuses:
 * - 'private'      => Artist’s Private Collection
 * - 'gallery'      => Link to gallery if URL available, otherwise a label
 * - 'sold'         => Private Collection
 * - No price       => Coming Soon button
 * - In stock & in cart => Already in Cart button
 * - Otherwise     => Standard WooCommerce add to cart button
 *
 * @since 1.0.0
 *
 * @param WC_Product $product      WooCommerce product object.
 * @param string     $status       Custom availability status ('private', 'gallery', 'sold', etc.).
 * @param string     $gallery_url  URL to the gallery (optional).
 * @param string     $gallery_name Name of the gallery (optional).
 * @return string HTML markup for the buy button or status label.
 */
function monospace_render_buy_button( $product, $status, $gallery_url, $gallery_name ) {
    $gallery_label = $gallery_name ? 'Available at ' . esc_html( $gallery_name ) : 'Available at Gallery';

    switch ( $status ) {
        case 'private':
            return '<span class="sold-label status-private">Artist’s Private Collection</span>';

        case 'gallery':
            return $gallery_url
                ? '<a class="button gallery-button status-gallery" href="' . esc_url( $gallery_url ) . '" target="_blank" rel="noopener">' . $gallery_label . '</a>'
                : '<span class="sold-label status-gallery">' . $gallery_label . '</span>';

        case 'sold':
        case !$product->is_in_stock():
            return '<span class="sold-label status-sold">Private Collection</span>';

        default:
            if ( ! $product->get_price() ) {
                return '<a class="button no-price status-coming-soon" href="#">Coming Soon</a>';
            }

            // Only check cart on front-end
            $in_cart = false;
            if ( ! is_admin() && function_exists( 'WC' ) && WC()->cart ) {
                $cart_id = WC()->cart->generate_cart_id( $product->get_id() );
                $in_cart = WC()->cart->find_product_in_cart( $cart_id );
            }

            if ( $product->get_stock_quantity() === 1 && $in_cart ) {
                return '<button class="button disabled status-in-cart" disabled>Already in Cart</button>';
            }

            return do_shortcode( '[add_to_cart id="' . $product->get_id() . '"]' );
    }
}
























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
            '<img src="%s" alt="%s" loading="lazy" />',
            esc_url( $content_image ),
            esc_attr( $title )
        );
    }

    if ( file_exists( str_replace( get_stylesheet_directory_uri(), get_stylesheet_directory(), $fallback_url ) ) ) {
        return sprintf(
            '<img src="%s" alt="No image" loading="lazy" />',
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













// Remove Astra's default single post navigation
remove_action( 'astra_entry_content_after', 'astra_post_navigation' );

/**
 * Custom single post navigation: NEWER ← on left, OLDER → on right
 *
 * @since 1.0.0
 */
function monospace_custom_post_navigation() {
return;

    if ( ! is_singular( 'post' ) ) {
        return;
    }

    $older = get_previous_post(); // OLDER
    $newer = get_next_post();     // NEWER

    // Exit if no posts
    if ( ! $older && ! $newer ) {
        return;
    }

    echo '<nav class="post-navigation" aria-label="Post navigation">';
    echo '<div class="nav-links" style="display:flex; justify-content:space-between; align-items:center;">';

    // NEWER on the left
    if ( $newer ) {
        echo '<div class="nav-newer">';
        echo '<a href="' . esc_url( get_permalink( $newer->ID ) ) . '" rel="next">';
        echo '<span class="nav-arrow" aria-hidden="true">←</span>';
        echo '<span class="nav-label">NEWER</span>';
        echo '</a>';
        echo '</div>';
    } else {
        echo '<div class="nav-newer nav-disabled">';
        echo '<span class="nav-arrow" aria-hidden="true">←</span>';
        echo '<span class="nav-label">NEWER</span>';
        echo '</div>';
    }

    // OLDER on the right
    if ( $older ) {
        echo '<div class="nav-older">';
        echo '<a href="' . esc_url( get_permalink( $older->ID ) ) . '" rel="prev">';
        echo '<span class="nav-label">OLDER</span>';
        echo '<span class="nav-arrow" aria-hidden="true">→</span>';
        echo '</a>';
        echo '</div>';
    } else {
        echo '<div class="nav-older nav-disabled">';
        echo '<span class="nav-label">OLDER</span>';
        echo '<span class="nav-arrow" aria-hidden="true">→</span>';
        echo '</div>';
    }

    echo '</div>';
    echo '</nav>';
}




















/**
 * Customize Astra/WordPress single post navigation labels
 * Previous = OLDER → , Next = ← NEWER
 */

// Previous post link
add_filter('previous_post_link', function($output) {
    // Grab the post URL and wrap it with custom label
    if (preg_match('/href=[\'"](.*?)[\'"].*?>(.*?)<\/a>/', $output, $matches)) {
        $url = $matches[1];
        $output = '<a href="' . esc_url($url) . '" rel="prev">OLDER <span aria-hidden="true">→</span></a>';
    }
    return $output;
});

// Next post link
add_filter('next_post_link', function($output) {
    if (preg_match('/href=[\'"](.*?)[\'"].*?>(.*?)<\/a>/', $output, $matches)) {
        $url = $matches[1];
        $output = '<a href="' . esc_url($url) . '" rel="next"><span aria-hidden="true">←</span> NEWER</a>';
    }
    return $output;
});












/**
 * Add a custom "Related Blog Post ID" field to WooCommerce products.
 *
 * This field allows you to link a WooCommerce product to a specific blog post.
 *
 * @since 1.0.0
 */
add_action( 'woocommerce_product_options_general_product_data', function() {
    woocommerce_wp_text_input( array(
        'id'          => '_related_post_id',
        'label'       => __( 'Related Blog Post ID', 'your-textdomain' ),
        'desc_tip'    => true,
        'description' => __( 'Enter the ID of the blog post to link from this product.', 'your-textdomain' ),
        'type'        => 'number',
    ));
});

/**
 * Save the "Related Blog Post ID" field when the product is saved.
 *
 * @since 1.0.0
 *
 * @param WC_Product $product The WooCommerce product object being saved.
 */
add_action( 'woocommerce_admin_process_product_object', function( $product ) {
    if ( isset( $_POST['_related_post_id'] ) ) {
        $product->update_meta_data( '_related_post_id', sanitize_text_field( $_POST['_related_post_id'] ) );
    }
});

/**
 * Display a link to the related blog post on the single product page.
 *
 * Adds a button below the product summary that links to the specified blog post.
 *
 * @since 1.0.0
 */
add_action( 'woocommerce_after_single_product_summary', function() {
    global $product;

    $post_id = $product->get_meta( '_related_post_id' );

    if ( $post_id ) {
        $url   = get_permalink( $post_id );
        $title = get_the_title( $post_id );

        if ( $url && $title ) {
            echo '<div class="related-blog-link">';
            echo '<a href="' . esc_url( $url ) . '" class="button related-blog-button">';
            echo 'Read more: ' . esc_html( $title );
            echo '</a>';
            echo '</div>';
        }
    }
}, 25 );















///////////////////////////// Body Classes for Cart/Checkout

/**
 * Add custom classes to the body for cart and checkout pages.
 *
 * @since 1.0.0
 *
 * @param array $classes Existing body classes.
 * @return array Modified body classes.
 */
add_filter( 'body_class', function( $classes ) {
    if ( is_cart() || is_checkout() ) {
        $classes[] = 'hide-cart-icon';
    }
    return $classes;
});


///////////////////////////// Custom WooCommerce Cart Icon

/**
 * Register a widget area for the custom cart.
 *
 * @since 1.0.0
 */
add_action( 'widgets_init', 'monospace_register_cart_widget' );
function monospace_register_cart_widget() {
    register_sidebar( array(
        'name'          => 'Custom Cart Area',
        'id'            => 'monospace-cart-area',
        'description'   => 'Add the custom cart widget here',
        'before_widget' => '<div class="monospace-cart-widget">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
}

/**
 * Display the custom cart icon with AJAX-updated count.
 *
 * @since 1.0.0
 */
function monospace_custom_cart() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    $cart_url   = wc_get_cart_url();
    $cart_count = WC()->cart->get_cart_contents_count();

    echo '<div class="monospace-cart-wrapper">';
    echo '<a href="' . esc_url( $cart_url ) . '" class="monospace-cart-link" aria-label="View shopping cart">';
    echo '<div class="monospace-cart-icon">';

    // Shopping cart SVG icon
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
    echo '<circle cx="9" cy="21" r="1"></circle>';
    echo '<circle cx="20" cy="21" r="1"></circle>';
    echo '<path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>';
    echo '</svg>';

    // Cart count bubble
    echo '<span class="monospace-cart-count-wrapper">';
    if ( $cart_count > 0 ) {
        echo '<span class="monospace-cart-count">' . esc_html( $cart_count ) . '</span>';
    }
    echo '</span>';

    echo '</div>';
    echo '</a>';
    echo '</div>';
}
add_shortcode( 'monospace_cart', 'monospace_custom_cart' );


/**
 * AJAX fragment for updating cart count dynamically.
 *
 * @since 1.0.0
 *
 * @param array $fragments Existing WooCommerce fragments.
 * @return array Updated fragments.
 */
add_filter( 'woocommerce_add_to_cart_fragments', 'monospace_cart_fragment' );
function monospace_cart_fragment( $fragments ) {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return $fragments;
    }

    $cart_count = WC()->cart->get_cart_contents_count();

    ob_start();
    echo '<span class="monospace-cart-count-wrapper">';
    if ( $cart_count > 0 ) {
        echo '<span class="monospace-cart-count">' . esc_html( $cart_count ) . '</span>';
    }
    echo '</span>';

    $fragments['.monospace-cart-count-wrapper'] = ob_get_clean();

    return $fragments;
}


/**
 * Inline styles for custom cart icon.
 *
 * @since 1.0.0
 */
add_action( 'wp_head', 'monospace_cart_styles', 999 );
function monospace_cart_styles() {
    echo '<style>
        /* Cart wrapper */
        .monospace-cart-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        /* Cart link */
        .monospace-cart-link {
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            padding: 10px;
            position: relative;
        }

        /* Cart icon container */
        .monospace-cart-icon {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }

        /* White cart SVG */
        .monospace-cart-icon svg {
            stroke: #ffffff;
            fill: none;
            width: 24px;
            height: 24px;
            transition: transform 0.2s ease;
        }

        /* Hover effect */
        .monospace-cart-link:hover .monospace-cart-icon svg {
            transform: scale(1.1);
        }

        /* Count wrapper (for AJAX updates) */
        .monospace-cart-count-wrapper {
            position: absolute;
            top: 0;
            right: 0;
            pointer-events: none;
        }

        /* Red bubble with white text */
        .monospace-cart-count {
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #ff0000;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            min-width: 20px;
            height: 20px;
            border-radius: 50%;
            padding: 0 5px;
            line-height: 1;
            border: 2px solid #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        /* Bounce animation when item added */
        @keyframes monospaceCartBounce {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.3); }
        }

        .monospace-cart-count.updated {
            animation: monospaceCartBounce 0.4s ease;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .monospace-cart-link { padding: 8px; }
            .monospace-cart-icon { width: 36px; height: 36px; }
            .monospace-cart-icon svg { width: 20px; height: 20px; }
            .monospace-cart-count { font-size: 10px; min-width: 18px; height: 18px; }
        }
    </style>';
}


/**
 * Add JS to trigger bounce animation on cart updates.
 *
 * @since 1.0.0
 */
add_action( 'wp_footer', 'monospace_cart_animation_script' );
function monospace_cart_animation_script() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    echo '<script>
    jQuery(document).ready(function($) {
        $(document.body).on("added_to_cart", function() {
            $(".monospace-cart-count").addClass("updated");
            setTimeout(function() {
                $(".monospace-cart-count").removeClass("updated");
            }, 400);
        });
    });
    </script>';
}








/**
 * Custom Home Button
 * Adds a clickable home button with an SVG icon and responsive styling.
 * Can be inserted via shortcode [monospace_home].
 *
 * @since 1.0.0
 */

/**
 * Output the home button HTML.
 */
function monospace_custom_home() {
    echo '<div class="monospace-home-wrapper">';
    echo '<a href="https://www.monospace.com/art" class="monospace-home-link" aria-label="Go to home page">';
    echo '<div class="monospace-home-icon">';
    
    // Home icon SVG
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
    echo '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>';
    echo '<polyline points="9 22 9 12 15 12 15 22"></polyline>';
    echo '</svg>';
    
    echo '</div>';
    echo '</a>';
    echo '</div>';
}
add_shortcode( 'monospace_home', 'monospace_custom_home' );


/**
 * Add inline styles for the home button.
 */
add_action( 'wp_head', 'monospace_home_styles', 999 );
function monospace_home_styles() {
    echo '<style>
        /* Home button wrapper */
        .monospace-home-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        /* Home link */
        .monospace-home-link {
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            padding: 10px;
            position: relative;
        }

        /* Home icon container */
        .monospace-home-icon {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }

        /* White home SVG */
        .monospace-home-icon svg {
            stroke: #ffffff;
            fill: none;
            width: 24px;
            height: 24px;
            transition: transform 0.2s ease;
        }

        /* Hover effect */
        .monospace-home-link:hover .monospace-home-icon svg {
            transform: scale(1.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .monospace-home-link {
                padding: 8px;
            }
            
            .monospace-home-icon {
                width: 36px;
                height: 36px;
            }
            
            .monospace-home-icon svg {
                width: 20px;
                height: 20px;
            }
        }
    </style>';
}







/* 

function custom_media_library_items_per_page( $query ) {
    if ( isset( $query['post_type'] ) && $query['post_type'] === 'attachment' ) {
        $query['posts_per_page'] = 200; // change to your preferred number
    }
    return $query;
}
add_filter( 'ajax_query_attachments_args', 'custom_media_library_items_per_page' );



 */















/**
 * WooCommerce: Change Add-to-Cart button text and disable purchase
 * if the product is already in the cart and stock is exactly 1.
 *
 * Adds a body class for styling purposes as well.
 *
 * @since 1.0.0
 */

/**
 * Modify single product add-to-cart button text.
 *
 * @param string $text Original button text.
 * @return string Modified button text if product is in cart and stock is 1.
 */
add_filter( 'woocommerce_product_single_add_to_cart_text', function( $text ) {
    global $product;

    if ( $product instanceof WC_Product && $product->managing_stock() && $product->get_stock_quantity() === 1 ) {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( $cart_item['product_id'] === $product->get_id() ) {
                return __( 'Already in cart', 'your-textdomain' );
            }
        }
    }

    return $text;
} );

/**
 * Disable purchasing of product if already in cart and stock is 1.
 *
 * @param bool        $purchasable Whether the product is purchasable.
 * @param WC_Product  $product The current product object.
 * @return bool Modified purchasable status.
 */
add_filter( 'woocommerce_is_purchasable', function( $purchasable, $product ) {
    if ( $product instanceof WC_Product && $product->managing_stock() && $product->get_stock_quantity() === 1 ) {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            if ( $cart_item['product_id'] === $product->get_id() ) {
                return false; // Disable purchase
            }
        }
    }
    return $purchasable;
}, 10, 2 );

/**
 * Add a body class to the product page when the product is in cart and stock is 1.
 *
 * @param array $classes Existing body classes.
 * @return array Modified body classes.
 */
add_filter( 'body_class', function( $classes ) {
    global $product;

    if ( is_product() && $product instanceof WC_Product ) {
        if ( $product->managing_stock() && $product->get_stock_quantity() === 1 ) {
            foreach ( WC()->cart->get_cart() as $cart_item ) {
                if ( $cart_item['product_id'] === $product->get_id() ) {
                    $classes[] = 'already-in-cart-single-stock';
                }
            }
        }
    }

    return $classes;
} );








/**
 * Replace image URLs in post content with full-size versions
 * 
 * This removes the WordPress-generated dimension suffix (e.g., -300x200)
 * from <img> tags so that the full-size image is used in the content.
 *
 * @param string $content The post content.
 * @return string Modified post content with full-size image URLs.
 */
add_filter( 'the_content', function( $content ) {

    // Use preg_replace_callback to handle all <img> tags with dimension suffix
    $content = preg_replace_callback(
        '/(<img[^>]+src=["\'])([^"\']+?)-\d+x\d+(\.\w+)(["\'][^>]*>)/i',
        function( $matches ) {
            // $matches[1] = opening <img> tag up to src="
            // $matches[2] = image filename without size suffix
            // $matches[3] = file extension
            // $matches[4] = closing part of the <img> tag
            return $matches[1] . $matches[2] . $matches[3] . $matches[4];
        },
        $content
    );

    return $content;
} );











/**
 * Add next/previous edit links to the WordPress admin bar
 * 
 * Features:
 * - Shows on single post/product edit screens
 * - Adds "Previous" and "Next" edit links aligned to the right
 * - Supports both posts and WooCommerce products
 */
add_action( 'admin_bar_menu', function( $admin_bar ) {
    global $post, $wpdb, $pagenow;

    // Only run on post edit screen
    if ( $pagenow !== 'post.php' || empty( $post ) ) return;

    $post_types = [ 'post', 'product' ];
    if ( ! in_array( $post->post_type, $post_types ) ) return;

    $current_id = $post->ID;

    // Get previous post/product ID (by ID)
    $prev_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type = %s
           AND post_status IN ('publish','private')
           AND ID < %d
         ORDER BY ID DESC
         LIMIT 1",
        $post->post_type,
        $current_id
    ) );

    // Get next post/product ID (by ID)
    $next_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type = %s
           AND post_status IN ('publish','private')
           AND ID > %d
         ORDER BY ID ASC
         LIMIT 1",
        $post->post_type,
        $current_id
    ) );

 
    // Add "Next" link (points to newer ID)
    if ( $next_id ) {
        $admin_bar->add_node([
            'id'     => 'prev_post', // visually newer
            'title'  => '← Previous',
            'href'   => get_edit_post_link( $next_id ),
            'parent' => 'top-secondary', // align right
            'meta'   => [ 'class' => 'prev-post-link' ]
        ]);
    }
   // Add "Previous" link (points to older ID)
    if ( $prev_id ) {
        $admin_bar->add_node([
            'id'     => 'next_post', // visually older
            'title'  => 'Next →',
            'href'   => get_edit_post_link( $prev_id ),
            'parent' => 'top-secondary', // align right
            'meta'   => [ 'class' => 'next-post-link' ]
        ]);
    }

}, 200 ); // Run late to avoid conflicts with other admin bar items






/**
 * Add keyboard shortcut (⌘S / Ctrl+S) to save posts/products in WP admin
 *
 * Features:
 * - Works on post and product edit screens
 * - Overrides browser default save (prevent page save dialog)
 * - Triggers the "Publish" or "Update" button
 */
add_action( 'admin_footer-post.php', 'monospace_save_shortcut' );
add_action( 'admin_footer-post-new.php', 'monospace_save_shortcut' );

function monospace_save_shortcut() {
    ?>
    <script>
    (function($){
        $(document).on('keydown', function(e) {
            // Detect ⌘+S (Mac) or Ctrl+S (Windows/Linux)
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
                e.preventDefault(); // prevent browser "Save Page" dialog

                // Trigger the "Publish" / "Update" button if it exists
                var $button = $('#publish');
                if ($button.length) {
                    $button.trigger('click');
                }
            }
        });
    })(jQuery);
    </script>
    <?php
}



/**
 * Override Astra social icon URLs
 *
 * This filter replaces the default link for a given social icon.
 * In this example, the "email" icon is redirected to a custom newsletter URL.
 *
 * @param string $output HTML output of the social icon link.
 * @param array  $icon   Array containing icon data ('id', 'icon', etc.).
 * @return string Modified HTML output for the social icon.
 */
add_filter( 'astra_get_social_icon', function( $output, $icon ) {

    // Check if the icon is the "email" icon
    if ( 'email' === $icon['id'] ) {
        // Custom URL to replace the default mailto link
        $custom_url = 'https://monospace.com/art/blog/newsletter/'; // Change to your desired URL

        // Build replacement anchor tag using the existing SVG icon
        $output  = '<a href="' . esc_url( $custom_url ) . '" target="_blank" rel="noopener">';
        $output .= $icon['icon']; // Use Astra's provided SVG
        $output .= '</a>';
    }

    return $output;
}, 10, 2 );














/**
 * GDPR-compliant cookie consent banner
 *
 * Displays a banner at the bottom of the site asking users to accept or reject cookies.
 * If accepted, it loads Google Analytics (or any tracking script) dynamically.
 */
add_action('wp_footer', function() {
    echo '
    <div id="cookie-consent-banner" style="
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #222;
        color: #fff;
        padding: 15px;
        font-size: 14px;
        text-align: center;
        z-index: 9999;
        display: none;
    ">
        This website uses cookies to enhance your browsing experience. 
        See our <a href="/privacy-policy" style="color:#4eaaff;text-decoration:underline;">Privacy Policy</a>.
        <div style="margin-top:10px;">
            <button id="accept-cookies" style="margin:0 5px; padding:6px 12px; background:#4caf50; color:#fff; border:none; cursor:pointer;">
                Accept
            </button>
            <button id="reject-cookies" style="margin:0 5px; padding:6px 12px; background:#f44336; color:#fff; border:none; cursor:pointer;">
                Reject
            </button>
        </div>
    </div>

    <script>
    (function(){
        // Show banner if consent not set
        if (!localStorage.getItem("cookieConsent")) {
            document.getElementById("cookie-consent-banner").style.display = "block";
        } else if (localStorage.getItem("cookieConsent") === "accepted") {
            loadAnalytics();
        }

        // Accept cookies
        document.getElementById("accept-cookies").addEventListener("click", function(){
            localStorage.setItem("cookieConsent", "accepted");
            document.getElementById("cookie-consent-banner").style.display = "none";
            loadAnalytics();
        });

        // Reject cookies
        document.getElementById("reject-cookies").addEventListener("click", function(){
            localStorage.setItem("cookieConsent", "rejected");
            document.getElementById("cookie-consent-banner").style.display = "none";
        });

        /**
         * Dynamically load Google Analytics script after consent
         */
        function loadAnalytics() {
            if (window.gaLoaded) return; // Avoid loading multiple times
            window.gaLoaded = true;

            var s = document.createElement("script");
            s.async = true;
            s.src = "https://www.googletagmanager.com/gtag/js?id=G-MSX5SP3W71"; // Replace with your GA4 ID
            document.head.appendChild(s);

            s.onload = function(){
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag("js", new Date());
                gtag("config", "G-MSX5SP3W71"); // Replace with your GA4 ID
            };
        }
    })();
    </script>
    ';
});





/**
 * Force Google for WooCommerce to use root domain for Merchant API connection
 */
add_filter( 'woocommerce_google_product_api_base_url', function( $url ) {
    // Replace with your store root domain
    return 'https://monospace.com';
});
/**
 * Force Google for WooCommerce to use root domain for Merchant connection
 */
add_filter( 'woocommerce_google_product_connection_url', function( $url ) {
    // Force root domain for connection
    return 'https://monospace.com';
});















// ---------- Echo-only Commission / Custom Order support for WooCommerce (functions.php) ----------
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Echo-only commission system
 * - Uploads saved under wp-content/uploads/custom-orders/
 * - Allowed file types: jpg, jpeg, png, pdf
 * - Max file size per file: 5MB
 */

/**
 * 1) Admin metabox: mark product as commission + deposit % + special order type
 */
// 1) Register meta box for commission products
add_action( 'add_meta_boxes', 'register_commission_product_meta_box_echo' );
function register_commission_product_meta_box_echo() {
    add_meta_box(
        'commission_product_meta',
        'Custom Order Settings',
        'render_commission_product_meta_box_echo',
        'product',
        'side',
        'default'
    );
}

function render_commission_product_meta_box_echo( $post ) {
    wp_nonce_field( 'save_commission_product_meta', 'commission_product_meta_nonce' );

    $is_commission = get_post_meta( $post->ID, '_is_commission_product', true );
    $deposit_percent = get_post_meta( $post->ID, '_commission_deposit_percent', true );
    $special_type = get_post_meta( $post->ID, '_special_order_type', true );

    if ( $deposit_percent === '' ) $deposit_percent = 30;
    if ( $special_type === '' ) $special_type = 'painting';

    $checked = ( $is_commission === 'yes' ) ? 'checked' : '';
    $deposit_esc = esc_attr( $deposit_percent );

    // Precompute select options to avoid ternary in heredoc
    $selected_painting = ($special_type=='painting') ? 'selected' : '';
    $selected_sketch  = ($special_type=='sketch') ? 'selected' : '';

    echo <<<HTML
<p>
    <label>
        <input type="checkbox" name="is_commission_product" value="yes" {$checked} />
        This is a Custom Artwork order
    </label>
</p>
<p>
    <label for="commission_deposit_percent">Deposit percent (%)</label><br/>
    <input type="number" name="commission_deposit_percent" id="commission_deposit_percent" value="{$deposit_esc}" min="0" max="100" style="width:100%;" />
    <small class="description">Percent of artwork price charged as deposit (e.g. 30).</small>
</p>
<p>
    <label for="special_order_type">Special Order Type</label><br/>
    <select name="special_order_type" id="special_order_type" style="width:100%;">
        <option value="painting" {$selected_painting}>Painting</option>
        <option value="sketch" {$selected_sketch}>Sketch</option>
    </select>
</p>
HTML;
}


// 2) Save commission product meta
add_action( 'save_post_product', 'save_commission_product_meta_echo' );
function save_commission_product_meta_echo( $post_id ) {
    if ( ! isset( $_POST['commission_product_meta_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['commission_product_meta_nonce'], 'save_commission_product_meta' ) ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    if ( isset( $_POST['is_commission_product'] ) && $_POST['is_commission_product'] === 'yes' ) {
        update_post_meta( $post_id, '_is_commission_product', 'yes' );
    } else {
        delete_post_meta( $post_id, '_is_commission_product' );
    }

    $dp = isset( $_POST['commission_deposit_percent'] ) ? floatval( $_POST['commission_deposit_percent'] ) : 30;
    $dp = max(0,min(100,$dp));
    update_post_meta( $post_id, '_commission_deposit_percent', $dp );

    $type = isset( $_POST['special_order_type'] ) && in_array($_POST['special_order_type'],['painting','sketch']) ? $_POST['special_order_type'] : 'painting';
    update_post_meta( $post_id, '_special_order_type', $type );
}


/**
 * 2) Show custom fields on product page
 */
add_action( 'woocommerce_before_add_to_cart_button', 'show_commission_fields_on_product_echo' );
function show_commission_fields_on_product_echo() {
    global $product;
    if ( ! $product || ! is_product() ) return;

    $product_id = $product->get_id();
    $is_commission = get_post_meta( $product_id, '_is_commission_product', true );
    if ( $is_commission !== 'yes' ) return;

    $special_type = get_post_meta( $product_id, '_special_order_type', true );
    if ( $special_type === '' ) $special_type = 'painting';

    echo '<div class="commission-fields" style="margin:1em 0; padding:.8em; border:1px solid #eee; background:#fafafa;">';
    echo '<h3 style="margin:0 0 .5em;">Custom Order Details</h3>';

    // Medium options
    echo '<p><strong>Medium</strong></p>';
    if ( $special_type === 'painting' ) {
        echo '<label><input type="radio" name="commission_medium" value="acrylic_mdf" checked> Acrylic on MDF</label><br/>';
        echo '<label><input type="radio" name="commission_medium" value="casein_paper"> Casein on paper</label>';
    } else { // sketch / pen & ink
        echo '<label><input type="radio" name="commission_medium" value="pen_ink_paper" checked> Pen &amp; Ink on paper</label>';
    }

    // Size
    echo '<p><label><strong>Size</strong></label><br/>';
    echo '<select name="commission_size">';
    echo '<option value="10x8" selected>10" × 8" (standard)</option>';
    echo '<option value="other">Other (special request)</option>';
    echo '</select></p>';

    // Reference files
    echo '<p><label><strong>Reference image(s) - required</strong></label><br/>';
    echo '<input type="file" name="commission_reference_files[]" accept=".jpg,.jpeg,.png,.pdf" multiple required />';
    echo '<br/><small>Allowed: jpg, png, pdf. Max size per file: 5MB.</small></p>';

    // Preferred date
    echo '<p><label><strong>Preferred completion date</strong></label><br/>';
    echo '<input type="date" name="commission_preferred_date" /></p>';

    // Special request
    echo '<p><label><strong>Special request</strong></label><br/>';
    echo '<textarea name="commission_special_request" rows="3" placeholder="Remove people, change season, color changes, etc."></textarea></p>';

    // Other notes
    echo '<p><label><strong>Other notes</strong></label><br/>';
    echo '<textarea name="commission_other_notes" rows="2" placeholder="Any additional notes for this custom order."></textarea></p>';

    echo '<input type="hidden" name="is_commission_request" value="1" />';
    echo '</div>';
}


/**
 * 3) Validate fields on add to cart
 */
add_filter( 'woocommerce_add_to_cart_validation', 'validate_commission_fields_on_add_to_cart_echo', 10, 3 );
function validate_commission_fields_on_add_to_cart_echo( $passed, $product_id, $quantity ) {
    $is_commission = get_post_meta( $product_id, '_is_commission_product', true );
    if ( $is_commission !== 'yes' ) return $passed;

    // Enforce admin-defined medium and type
    $special_type = get_post_meta( $product_id, '_special_order_type', true );
    if ( $special_type === '' ) $special_type = 'painting';

    if ( $special_type === 'painting' ) {
        if ( empty( $_REQUEST['commission_medium'] ) || ! in_array($_REQUEST['commission_medium'], ['acrylic_mdf','casein_paper']) ) {
            wc_add_notice( 'Please select a valid medium for this painting.', 'error' );
            return false;
        }
    } else { // sketch / pen & ink
        $_REQUEST['commission_medium'] = 'pen_ink_paper'; // force medium
    }


    if ( empty( $_FILES['commission_reference_files'] ) ) {
        wc_add_notice( 'Please upload at least one reference image (jpg, png or pdf).', 'error' );
        return false;
    }

    $files = $_FILES['commission_reference_files'];
    $count = 0;
    for ( $i = 0; $i < count( $files['name'] ); $i++ ) {
        if ( ! empty( $files['name'][$i] ) ) {
            $count++;
            if ( $files['size'][$i] > 5 * 1024 * 1024 ) {
                wc_add_notice( 'Each reference file must be under 5MB.', 'error' );
                return false;
            }
            $ext = strtolower( pathinfo( $files['name'][$i], PATHINFO_EXTENSION ) );
            if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'pdf' ) ) ) {
                wc_add_notice( 'Allowed reference file types: jpg, png, pdf.', 'error' );
                return false;
            }
        }
    }

    if ( $count === 0 ) {
        wc_add_notice( 'Please upload at least one reference file.', 'error' );
        return false;
    }

    if ( ! empty( $_REQUEST['commission_preferred_date'] ) ) {
        $d = $_REQUEST['commission_preferred_date'];
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) ) {
            wc_add_notice( 'Preferred completion date is invalid.', 'error' );
            return false;
        }
    }

    return $passed;
}

/**
 * 4) Process uploads and add custom data to cart item
 */
add_filter( 'woocommerce_add_cart_item_data', 'add_commission_data_to_cart_item_echo', 20, 3 );
function add_commission_data_to_cart_item_echo( $cart_item_data, $product_id, $variation_id ) {
    $is_commission = get_post_meta( $product_id, '_is_commission_product', true );
    if ( $is_commission !== 'yes' ) return $cart_item_data;

    $custom = array();

    // Collect commission fields
    $custom['commission_type']            = isset($_REQUEST['commission_type']) ? sanitize_text_field(wp_unslash($_REQUEST['commission_type'])) : '';
    $custom['commission_medium']          = isset($_REQUEST['commission_medium']) ? sanitize_text_field(wp_unslash($_REQUEST['commission_medium'])) : '';
    $custom['commission_size']            = isset($_REQUEST['commission_size']) ? sanitize_text_field(wp_unslash($_REQUEST['commission_size'])) : '';
    $custom['commission_preferred_date']  = isset($_REQUEST['commission_preferred_date']) ? sanitize_text_field(wp_unslash($_REQUEST['commission_preferred_date'])) : '';
    $custom['commission_special_request'] = isset($_REQUEST['commission_special_request']) ? sanitize_textarea_field(wp_unslash($_REQUEST['commission_special_request'])) : '';
    $custom['commission_other_notes']     = isset($_REQUEST['commission_other_notes']) ? sanitize_textarea_field(wp_unslash($_REQUEST['commission_other_notes'])) : '';

    // Handle file uploads
    $custom['commission_reference_uploads'] = array();
    if ( ! empty( $_FILES['commission_reference_files'] ) ) {
    $files = $_FILES['commission_reference_files'];
    $uploads = array();

    require_once( ABSPATH . 'wp-admin/includes/file.php' );

    for ( $i = 0; $i < count( $files['name'] ); $i++ ) {
        if ( empty( $files['name'][$i] ) ) continue;

        $file = array(
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i]
        );

        // Temporary filter to force custom folder
        $filter = function( $dirs ) {
            $dirs['subdir']  = '/custom-orders';
            $dirs['path']    = $dirs['basedir'] . $dirs['subdir'];
            $dirs['url']     = $dirs['baseurl'] . $dirs['subdir'];

            if ( ! file_exists( $dirs['path'] ) ) wp_mkdir_p( $dirs['path'] );

            return $dirs;
        };
        add_filter( 'upload_dir', $filter );

        $movefile = wp_handle_upload( $file, array( 'test_form' => false ) );

        remove_filter( 'upload_dir', $filter );

        if ( $movefile && ! isset( $movefile['error'] ) ) {
            $uploads[] = array(
                'url'  => $movefile['url'],
                'file' => $movefile['file'],
                'name' => basename( $movefile['file'] )
            );
        } else {
            wc_add_notice( 'Error uploading reference file: ' . ( isset( $movefile['error'] ) ? esc_html( $movefile['error'] ) : 'unknown' ), 'error' );
        }
    }

    if ( ! empty( $uploads ) ) $custom['commission_reference_uploads'] = $uploads;
}


    // Store pricing info
    $custom['commission_full_price']     = floatval( wc_get_price_to_display( wc_get_product($product_id) ) );
    $custom['unique_commission_key']     = uniqid('commission_', true);

    $cart_item_data['commission_data'] = $custom;

    return $cart_item_data;
}



/**
 * 5) Restore cart item data from session
 */
add_filter( 'woocommerce_get_cart_item_from_session', 'restore_commission_cart_item_from_session_echo', 20, 2 );
function restore_commission_cart_item_from_session_echo( $cart_item, $values ) {
    if ( isset( $values['commission_data'] ) && is_array( $values['commission_data'] ) ) {
        $cart_item['commission_data'] = $values['commission_data'];
    }
    return $cart_item;
}

/**
 * 6) Apply deposit as negative fee (shipping calculated on full price)
 */
add_action( 'woocommerce_cart_calculate_fees', 'apply_commission_deposit_fee_echo', 20 );
function apply_commission_deposit_fee_echo( $cart ) {
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

    $deposit_total = 0;

    foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
        if ( isset( $cart_item['commission_data'] ) ) {
            $product_id = $cart_item['product_id'];
            $deposit_percent = get_post_meta( $product_id, '_commission_deposit_percent', true );
            if ( $deposit_percent === '' ) $deposit_percent = 30;

            $full_price = floatval( $cart_item['commission_data']['commission_full_price'] );
            $deposit_price = round( ( $deposit_percent / 100 ) * $full_price, wc_get_price_decimals() );

            // store for display
            $cart_item['commission_data']['applied_deposit_price'] = $deposit_price;
            WC()->cart->cart_contents[$cart_item_key] = $cart_item;

            // calculate total deposit difference
            $deposit_total += $full_price - $deposit_price;
        }
    }

    if ( $deposit_total > 0 ) {
        $cart->add_fee( 'Deposit Reduction', -$deposit_total, false ); 
        // negative fee reduces subtotal, but shipping stays based on full price
    }
}


/**
 * 7) Display commission details in cart & checkout line items (cleaned, no duplicates)
 */
add_filter( 'woocommerce_get_item_data', 'display_commission_item_data_in_cart_echo', 10, 2 );
function display_commission_item_data_in_cart_echo( $item_data, $cart_item ) {

    if ( isset( $cart_item['commission_data'] ) ) {
        $cd = $cart_item['commission_data'];

        // Standard fields mapping (exclude price fields)
        $mapping = array(
            'commission_type'           => 'Type',
            'commission_medium'         => 'Medium',
            'commission_size'           => 'Size',
            'commission_preferred_date' => 'Preferred completion date',
            'commission_special_request'=> 'Special request',
            'commission_other_notes'    => 'Other notes',
            'special_order_type'        => 'Special order type',
        );

        foreach ( $mapping as $key => $label ) {
            if ( ! empty( $cd[ $key ] ) ) {
                $item_data[] = array(
                    'key'   => $label,
                    'value' => wp_kses_post( nl2br( esc_html( $cd[ $key ] ) ) )
                );
            }
        }

        // Reference files
        if ( ! empty( $cd['commission_reference_uploads'] ) && is_array( $cd['commission_reference_uploads'] ) ) {
            $links = array();
            foreach ( $cd['commission_reference_uploads'] as $u ) {
                $links[] = '<a href="' . esc_url($u['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($u['name']) . '</a>';
            }
            $item_data[] = array(
                'key'   => 'Reference files',
                'value' => implode( ', ', $links )
            );
        }

        // Deposit / Balance info
        if ( isset( $cd['commission_full_price'] ) && isset( $cd['applied_deposit_price'] ) ) {
            $deposit_percent = get_post_meta( $cart_item['product_id'], '_commission_deposit_percent', true );
            if ( $deposit_percent === '' ) $deposit_percent = 30;

            $deposit = wc_price( $cd['applied_deposit_price'] );
            $balance = wc_price( $cd['commission_full_price'] - $cd['applied_deposit_price'] );

            $item_data[] = array(
                'key'   => 'Deposit paid',
                'value' => "{$deposit} ({$deposit_percent}%)"
            );

            $item_data[] = array(
                'key'   => 'Balance due',
                'value' => $balance
            );
        }
    }

    return $item_data;
}


/**
 * 7b) Show full price & deposit with percentage in cart/checkout
 */
add_filter( 'woocommerce_get_item_data', 'display_commission_item_data_clean', 10, 2 );
function display_commission_item_data_clean( $item_data, $cart_item ) {

    // Only modify commission items
    if ( empty( $cart_item['commission_data'] ) ) return $item_data;

    $cd = $cart_item['commission_data'];

    // Start fresh — remove default attributes
    $item_data = array();

    // Map fields to display labels
    $fields = array(
        'commission_type'            => 'Custom Artwork',
        'commission_medium'          => 'Medium',
        'commission_size'            => 'Size',
        'commission_special_request' => 'Special request',
        'commission_other_notes'     => 'Other notes',
        'special_order_type'         => 'Special order type',
    );

    foreach ( $fields as $key => $label ) {
        if ( ! empty( $cd[ $key ] ) ) {
            $item_data[] = array(
                'key'   => $label,
                'value' => esc_html( $cd[ $key ] )
            );
        }
    }

    // Reference files (clickable links)
    if ( ! empty( $cd['commission_reference_uploads'] ) && is_array( $cd['commission_reference_uploads'] ) ) {
        $links = array();
        foreach ( $cd['commission_reference_uploads'] as $u ) {
            $links[] = '<a href="' . esc_url($u['url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($u['name']) . '</a>';
        }
        $item_data[] = array(
            'key'   => 'Reference files',
            'value' => implode( ', ', $links )
        );
    }

    // Deposit and balance
    if ( isset( $cd['commission_full_price'] ) && isset( $cd['applied_deposit_price'] ) ) {
        $deposit_percent = get_post_meta( $cart_item['product_id'], '_commission_deposit_percent', true );
        if ( $deposit_percent === '' ) $deposit_percent = 30;

        $deposit = wc_price( $cd['applied_deposit_price'] );
        $balance = wc_price( $cd['commission_full_price'] - $cd['applied_deposit_price'] );

        $item_data[] = array(
            'key'   => 'Deposit due now',
            'value' => "{$deposit} ({$deposit_percent}%)"
        );
        $item_data[] = array(
            'key'   => 'Balance due on completion',
            'value' => $balance
        );
    }

    return $item_data;
}

/**
* 7C) Hide shipping when cart contains only commission deposits
*/
add_filter( 'woocommerce_cart_shipping_packages', function( $packages ) {
    $deposit_only = true;

    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( empty( $cart_item['commission_data'] ) ) {
            $deposit_only = false;
            break;
        }
    }

    if ( $deposit_only ) {
        return array(); // no shipping packages
    }

    return $packages;
});

/**
* 7D) Display notice to customer about shipping on final invoice
 */
add_action( 'woocommerce_before_cart', function() {
    $deposit_only = true;

    foreach ( WC()->cart->get_cart() as $cart_item ) {
        if ( empty( $cart_item['commission_data'] ) ) {
            $deposit_only = false;
            break;
        }
    }

    if ( $deposit_only && ! WC()->cart->is_empty() ) {
        wc_print_notice(
            'Shipping will be calculated and added to your final invoice once your custom artwork is ready for delivery.',
            'notice'
        );
    }
});

/**
 * 8) Pass commission data into order items when order is created
 */
add_action( 'woocommerce_checkout_create_order_line_item', 'add_commission_data_to_order_items_echo', 10, 4 );
function add_commission_data_to_order_items_echo( $item, $cart_item_key, $values, $order ) {
    if ( isset( $values['commission_data'] ) ) {
        $cd = $values['commission_data'];

        $fields = array(
            'commission_type' => 'Type',
            'commission_medium' => 'Medium',
            'commission_size' => 'Size',
            'commission_preferred_date' => 'Preferred completion date',
            'commission_special_request' => 'Special request',
            'commission_other_notes' => 'Other notes',
            'special_order_type' => 'Special order type'
        );

        foreach ( $fields as $key => $label ) {
            if ( ! empty( $cd[ $key ] ) ) {
                $item->add_meta_data( $label, $cd[ $key ], true );
            }
        }

        $product_id = $values['product_id'];
        $deposit_percent = get_post_meta( $product_id, '_commission_deposit_percent', true );
        if ( $deposit_percent === '' ) $deposit_percent = 30;
        $item->add_meta_data( 'Deposit percent', $deposit_percent . '%', true );

        $full_price = isset( $cd['commission_full_price'] ) ? $cd['commission_full_price'] : '';
        if ( $full_price !== '' ) $item->add_meta_data( 'Full price', wc_price( $full_price ), true );

        if ( ! empty( $cd['commission_reference_uploads'] ) && is_array( $cd['commission_reference_uploads'] ) ) {
            $links = array();
            foreach ( $cd['commission_reference_uploads'] as $u ) {
                $links[] = array( 'name' => $u['name'], 'url' => $u['url'] );
            }
            $item->add_meta_data( 'Reference files (json)', wp_json_encode( $links ), true );
            $item->add_meta_data( 'Reference files', implode( ', ', wp_list_pluck( $links, 'name' ) ), true );
        }
    }
}

/**
 * 9) Make uploaded file links clickable in admin order view
 */
add_filter( 'woocommerce_display_item_meta', 'commission_display_item_meta_links_echo', 10, 3 );
function commission_display_item_meta_links_echo( $html, $item, $args ) {
    foreach ( $item->get_formatted_meta_data() as $meta_id => $meta ) {
        // Detect our JSON-stored reference files
        if ( $meta->key === 'Reference files (json)' ) {
            $links = json_decode( wp_unslash( $meta->value ), true );
            if ( is_array( $links ) ) {
                $link_html = '<div class="commission-ref-files" style="margin-top:.5em;">';
                foreach ( $links as $file ) {
                    $url  = esc_url( $file['url'] );
                    $name = esc_html( $file['name'] );
                    $link_html .= "<div><a href='{$url}' target='_blank' rel='noopener noreferrer'>{$name}</a></div>";
                }
                $link_html .= '</div>';

                // Replace the plain JSON output with clickable links
                $html .= $link_html;
            }
        }
    }
    return $html;
}


/**
 * 10) Optional: Add admin order note when commission created
 */
add_action( 'woocommerce_checkout_order_created', function( $order ) {
    foreach ( $order->get_items() as $item_id => $item ) {
        if ( $item->get_meta( 'Type', true ) ) {
            $order->add_order_note( 'Custom order created for item: ' . $item->get_name() );
            break;
        }
    }
});

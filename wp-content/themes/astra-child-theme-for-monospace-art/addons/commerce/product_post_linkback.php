<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */




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

        $styles = 'style="
                display: block;
                clear: both;
                float: none;
                text-align: left;
        "';
        
        if ( $url && $title ) {
            echo '<div class="related-blog-link" '.$styles.'>';
            echo '<a href="' . esc_url( $url ) . '" class="button related-blog-button">';
            echo 'Read more: ' . esc_html( $title );
            echo '</a>';
            echo '</div>';
        }
    }
}, 25 );


// Disable WooCommerce product image zoom, lightbox, and slider
add_action( 'wp', function() {
    if ( class_exists( 'WooCommerce' ) ) {
        remove_theme_support( 'wc-product-gallery-zoom' );
        remove_theme_support( 'wc-product-gallery-lightbox' );
        remove_theme_support( 'wc-product-gallery-slider' );
    }
});

// Remove link from WooCommerce product gallery images
add_filter( 'woocommerce_single_product_image_thumbnail_html', 'remove_gallery_image_link', 10, 2 );

function remove_gallery_image_link( $html, $attachment_id ) {
    // Get the image HTML
    $image = wp_get_attachment_image( $attachment_id, 'full' );

    // Return just the image without the link
    return $image;
}

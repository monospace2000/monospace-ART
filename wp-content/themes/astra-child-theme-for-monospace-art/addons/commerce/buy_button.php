<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */



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
    // Display order priority
    $order = array( 'format', 'medium', 'surface', 'size' );

    // Attributes to exclude (by label, case-insensitive)
    $exclude = array( 'year' );

    $attributes = $product->get_attributes();
    $output     = array();

    // Helper: render one attribute as HTML
    $render_attr = function( $attribute, $product_id ) {
        $label = wc_attribute_label( $attribute->get_name() );

        if ( $attribute->is_taxonomy() ) {
            $terms = wp_get_post_terms( $product_id, $attribute->get_name(), array( 'fields' => 'names' ) );
            $value = implode( ', ', $terms );
        } else {
            $value = $attribute->get_options() ? implode( ', ', $attribute->get_options() ) : '';
        }

        return $value
            ? '<div class="painting-attr"><b>' . esc_html( $label ) . '</b>: ' . esc_html( $value ) . '</div>'
            : '';
    };

    // Normalize exclude list to lowercase
    $exclude = array_map( 'strtolower', $exclude );

    // 1️⃣ Render attributes in preferred order
    foreach ( $order as $attr_name ) {
        foreach ( $attributes as $key => $attribute ) {
            $label = strtolower( wc_attribute_label( $attribute->get_name() ) );

            // Skip excluded attributes
            if ( in_array( $label, $exclude, true ) ) {
                unset( $attributes[ $key ] );
                continue;
            }

            if ( $label === strtolower( $attr_name ) ) {
                $output[] = $render_attr( $attribute, $product_id );
                unset( $attributes[ $key ] );
                break;
            }
        }
    }

    // 2️⃣ Render remaining attributes (excluding those listed)
    foreach ( $attributes as $attribute ) {
        $label = strtolower( wc_attribute_label( $attribute->get_name() ) );
        if ( in_array( $label, $exclude, true ) ) {
            continue;
        }
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






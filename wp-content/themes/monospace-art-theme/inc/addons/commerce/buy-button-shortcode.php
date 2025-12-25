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

    // --- Handle placeholder ID "xxxx" ---
    if ( isset( $atts['id'] ) && $atts['id'] === 'xxxx' ) {

        $status_slug  = 'coming-soon';
        $status_class = ' status-' . $status_slug;

        // Optional: placeholder attributes (can say "TBA" or leave empty)
        $attr_list = '<div class="painting-attr">Attributes: TBA</div>';

        // Coming Soon button
        $button = '<a class="button no-price status-coming-soon" href="#">Coming Soon</a>';

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

    // --- Check if product has size 4x4 (by slug) ---
    $show_special = false;
    $attributes = $product->get_attributes();

    // Only show special offer if product is actually available for purchase
    // Don't show if sold, private, or at gallery
    $is_available = !in_array($status, ['sold', 'private', 'gallery']) && $product->is_in_stock();

    if ($is_available) {
        foreach ( $attributes as $attribute ) {
            $label = wc_attribute_label( $attribute->get_name() );
            if ( strtolower($label) === 'size' ) {

                if ( $attribute->is_taxonomy() ) {
                    $terms = wp_get_post_terms( $product_id, $attribute->get_name() );
                    foreach ( $terms as $term ) {
                        if ( $term->slug === '4x4' ) { // check slug instead of name
                            $show_special = true;
                            break 2;
                        }
                    }
                } else {
                    // Custom attribute (not taxonomy) — optionally check value
                    foreach ( $attribute->get_options() as $value ) {
                        if ( strtolower(str_replace(' ', '', $value)) === '4x4' ) {
                            $show_special = true;
                            break 2;
                        }
                    }
                }

            }
        }
    }


    $special_text = $show_special
        ? '<div class="special-discount"> <span style="margin-top:6px;font-size:14px;font-weight:bold;color:#3a3;">Special: Get 3 miniatures for only $69!</span><br><span style="margin-top:6px;font-size:12px;">(Discount applied at checkout.)</span></div>'
        : '';


    $status_slug  = $status ? sanitize_title( $status ) : 'default';
    $status_class = ' status-' . $status_slug;

    $sku = $product->get_sku();

    // Button is present only for purchasable / coming soon / in-cart / gallery statuses
    $has_button = ! in_array( $status_slug, ['private', 'artist-private-collection'], true );

    if ( $sku ) {
        if ( $has_button ) {
            // SKU when a button is present
            $sku_html = '<div class="painting-sku" style="font-family:sans-serif;font-size:0.7em;color:#999;text-align:right;margin: 5px 2px 0 0;">'
                . esc_html( $sku ) .
            '</div>';
        } else {
            // SKU when no button is present (label-only states)
            $sku_html = '<div class="painting-sku" style="font-family:sans-serif;font-size:0.7em;color:#999;text-align:right;margin:0;">'
                . esc_html( $sku ) .
            '</div>';
        }
    } else {
        $sku_html = '';
    }


    // Generate price HTML ONLY if purchasable
    $price_html = '';

    $non_purchasable_statuses = [
        'gallery',
        'private',
        'sold',
        'coming-soon',
        'artist-private-collection',
        'private-collection'
    ];

    $status_slug = $status ? sanitize_title( $status ) : '';

    if (
        $product->get_price() &&
        $product->is_in_stock() &&
        ! in_array( $status_slug, $non_purchasable_statuses, true )
    ) {
        $price_display = $product->get_price_html();
        $add_to_cart_btn = do_shortcode( '[add_to_cart id="' . $product->get_id() . '" show_price="false"]' );

        $price_html  = '<div class="painting-price-row" style="width:100%;display:flex;justify-content:flex-end;align-items:center;gap:15px;margin-bottom:4px;">';
        $price_html .= '<div class="painting-price" style="font-size:1.2em;color:#333;">' . $price_display . '</div>';
        $price_html .= '<div class="painting-cart-button">' . $add_to_cart_btn . '</div>';
        $price_html .= '</div>';
    }


    return sprintf(
        '<div class="painting-buy-row%s" data-status="%s">
            <div class="painting-attrs">%s</div>
            <div class="painting-action">
                %s  <!-- Price (if purchasable) -->
                %s  <!-- Button or status label -->
                %s  <!-- SKU (always below status) -->
                %s  <!-- Special text -->
            </div>
        </div>',
        esc_attr( $status_class ),
        esc_attr( $status_slug ),
        $attr_list,
        $price_html,
        $button,
        $sku_html,
        $special_text
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
    $exclude = array();

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
 * - 'private'      => Artist's Private Collection
 * - 'gallery'      => Link to gallery if URL available, otherwise a label
 * - 'sold'         => Private Collection
 * - No price       => Coming Soon button
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

    $gallery_label = $gallery_name ? 'On view at ' . esc_html( $gallery_name ) : 'Available at Gallery';

    switch ( $status ) {
        case 'private':
            return '<span class="sold-label status-private">Artist\'s Private Collection</span>';

        case 'gallery':
            return $gallery_url
                ? '<a class="button gallery-button status-gallery" href="' . esc_url( $gallery_url ) . '" target="_blank" rel="noopener">' . $gallery_label . '</a>'
                : '<span class="sold-label status-gallery">' . $gallery_label . '</span>';

        case 'sold':
            return '<span class="sold-label status-sold">Private Collection</span>';

        default:
            // Check if out of stock
            if ( ! $product->is_in_stock() ) {
                return '<span class="sold-label status-sold">Private Collection</span>';
            }

            if ( ! $product->get_price() ) {
                return '<a class="button no-price status-coming-soon" href="#">Coming Soon</a>';
            }

            // Return empty string - the button is already rendered in $price_html
            return '';
    }
}


/**
 * Change "Read More" button text to "Already in Cart" when product is in cart
 */
add_filter( 'woocommerce_product_add_to_cart_text', 'monospace_change_in_cart_button_text', 10, 2 );
function monospace_change_in_cart_button_text( $text, $product ) {

    // Check if product is already in cart
    if ( function_exists( 'WC' ) && WC()->cart ) {
        $cart_id = WC()->cart->generate_cart_id( $product->get_id() );
        $in_cart = WC()->cart->find_product_in_cart( $cart_id );

        // If product is in cart, change text
        if ( $in_cart ) {
            return 'Already in Cart';
        }
    }

    return $text;
}


/**
 * Add custom class to "Already in Cart" button for styling
 */
add_filter( 'woocommerce_loop_add_to_cart_link', 'monospace_add_in_cart_class', 10, 2 );
function monospace_add_in_cart_class( $button, $product ) {

    // Check if product is already in cart
    if ( function_exists( 'WC' ) && WC()->cart ) {
        $cart_id = WC()->cart->generate_cart_id( $product->get_id() );
        $in_cart = WC()->cart->find_product_in_cart( $cart_id );

        // If product is in cart, add custom class
        if ( $in_cart ) {
            $button = str_replace( 'class="button', 'class="button already-in-cart', $button );
        }
    }

    return $button;
}


/**
 * Enqueue custom styles for "Already in Cart" button
 */
add_action( 'wp_head', 'monospace_in_cart_button_styles' );
function monospace_in_cart_button_styles() {
    ?>
    <style>
        .button.already-in-cart,
        .button.already-in-cart:hover {
            background-color: #999 !important;
            color: #fff !important;
            cursor: default !important;
            pointer-events: none !important;
        }
    </style>
    <?php
}
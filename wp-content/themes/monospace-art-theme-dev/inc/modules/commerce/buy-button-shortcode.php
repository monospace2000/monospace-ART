<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */

function monospace_is_editor_context() {
    if ( is_admin() ) return true;
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return true;
    if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) return true;
    return false;
}

function monospace_custom_add_to_cart_shortcode( $atts ) {

    $atts = shortcode_atts(
        array( 'id' => null ),
        $atts,
        'painting_buy_button'
    );

    if ( isset( $atts['id'] ) && $atts['id'] === 'xxxx' ) {

        $status_slug  = 'coming-soon';
        $status_class = ' status-' . $status_slug;

        $attr_list = '<div class="painting-attr">Attributes: TBA</div>';
        $button = '<a class="painting-buy-button status-coming-soon" href="#">Coming Soon</a>';

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
    if ( ! $product_id ) return '';

    if ( monospace_is_editor_context() ) {
        return '<div class="painting-buy-placeholder" style="padding:6px;border:1px dashed #aaa;background:#f9f9f9;">'
             . 'Painting Buy Button (Product ID ' . esc_html( $product_id ) . ')'
             . '</div>';
    }

    if ( ! function_exists( 'wc_get_product' ) ) return '';
    $product = wc_get_product( $product_id );
    if ( ! $product ) return '';

    $status       = function_exists( 'get_field' ) ? get_field( 'painting_availability_status', $product_id ) : '';
    $gallery_url  = function_exists( 'get_field' ) ? get_field( 'painting_gallery_url', $product_id ) : '';
    $gallery_name = function_exists( 'get_field' ) ? get_field( 'painting_gallery_name', $product_id ) : '';

    $attr_list = monospace_render_product_attributes( $product, $product_id );
    $button    = monospace_render_buy_button( $product, $status, $gallery_url, $gallery_name );

    $show_special = false;
    $attributes = $product->get_attributes();

    $is_available = !in_array($status, ['sold', 'private', 'gallery']) && $product->is_in_stock();

    if ($is_available) {
        foreach ( $attributes as $attribute ) {
            $label = wc_attribute_label( $attribute->get_name() );
            if ( strtolower($label) === 'size' ) {

                if ( $attribute->is_taxonomy() ) {
                    $terms = wp_get_post_terms( $product_id, $attribute->get_name() );
                    foreach ( $terms as $term ) {
                        if ( $term->slug === '4x4' ) {
                            $show_special = true;
                            break 2;
                        }
                    }
                } else {
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
        ? '<div class="special-discount"><span style="margin-top:6px;font-size:14px;font-weight:bold;color:#3a3;">Special: Get 3 miniatures for only $69!</span><br><span style="margin-top:6px;font-size:12px;">(Discount applied at checkout.)</span></div>'
        : '';

    $status_slug  = $status ? sanitize_title( $status ) : 'default';
    $status_class = ' status-' . $status_slug;

    $sku = $product->get_sku();
    $has_button = ! in_array( $status_slug, ['private', 'artist-private-collection'], true );

    $sku_html = $sku
        ? '<div class="painting-sku" style="font-family:sans-serif;font-size:0.7em;color:#999;text-align:right;margin:5px 2px 0 0;">'
            . esc_html( $sku ) .
          '</div>'
        : '';

    $price_html = '';
    $non_purchasable_statuses = [
        'gallery','private','sold','coming-soon','artist-private-collection','private-collection'
    ];

    $status_slug = $status ? sanitize_title( $status ) : '';

    /**
     * CLEAN ADD-TO-CART LINK + CART REDIRECT
     */
    if (
        $product->get_price() &&
        $product->is_in_stock() &&
        ! in_array( $status_slug, $non_purchasable_statuses, true )
    ) {

        $price_display = $product->get_price_html();
        $already_in_cart = false;

        if ( function_exists( 'WC' ) && WC()->cart ) {
            $CartId = WC()->cart->generate_cart_id( $product->get_id() );
            $already_in_cart = WC()->cart->find_product_in_cart( $CartId );
        }

        if ( $already_in_cart ) {

            $add_to_cart_btn =
                '<a class="painting-buy-button already-in-cart disabled" aria-disabled="true" tabindex="-1">Already in Cart</a>';

        } else {

            // Add to cart → redirect to cart
            $add_to_cart_btn =
                '<a class="painting-buy-button" href="' .
                esc_url(
                    add_query_arg(
                        array(
                            'add-to-cart'      => $product->get_id(),
                            'ms_redirect_cart' => 1
                        )
                    )
                ) .
                '">Add to cart</a>';
        }

        $price_html  =
            '<div class="painting-price-row" style="width:100%;display:flex;justify-content:flex-end;align-items:center;gap:15px;margin-bottom:4px;">' .
                '<div class="painting-price" style="font-size:1.2em;color:#333;">' . $price_display . '</div>' .
                '<div class="painting-cart-button">' . $add_to_cart_btn . '</div>' .
            '</div>';
    }

    return sprintf(
        '<div class="painting-buy-row%s" data-status="%s">
            <div class="painting-attrs">%s</div>
            <div class="painting-action">
                %s
                %s
                %s
                %s
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

function monospace_render_product_attributes( $product, $product_id ) {
    $order = array( 'format', 'medium', 'surface', 'size' );
    $exclude = array();
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

        return $value
            ? '<div class="painting-attr"><b>' . esc_html( $label ) . '</b>: ' . esc_html( $value ) . '</div>'
            : '';
    };

    foreach ( $order as $attr_name ) {
        foreach ( $attributes as $key => $attribute ) {
            $label = strtolower( wc_attribute_label( $attribute->get_name() ) );

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

    foreach ( $attributes as $attribute ) {
        $label = strtolower( wc_attribute_label( $attribute->get_name() ) );
        if ( in_array( $label, $exclude, true ) ) continue;
        $output[] = $render_attr( $attribute, $product_id );
    }

    return implode( '', $output );
}

function monospace_render_buy_button( $product, $status, $gallery_url, $gallery_name ) {

    $gallery_label = $gallery_name ? 'On view at ' . esc_html( $gallery_name ) : 'Available at Gallery';

    switch ( $status ) {
        case 'private':
            return '<span class="sold-label status-private">Artist\'s Private Collection</span>';

        case 'gallery':
            return $gallery_url
                ? '<a class="gallery-button status-gallery" href="' . esc_url( $gallery_url ) . '" target="_blank" rel="noopener">' . $gallery_label . '</a>'
                : '<span class="sold-label status-gallery">' . $gallery_label . '</span>';

        case 'sold':
            return '<span class="sold-label status-sold">Private Collection</span>';

        default:
            if ( ! $product->is_in_stock() ) {
                return '<span class="sold-label status-sold">Private Collection</span>';
            }

            if ( ! $product->get_price() ) {
                return '<a class="painting-buy-button status-coming-soon" href="#">Coming Soon</a>';
            }

            return '';
    }
}





/**
 * Redirect to cart for our custom add-to-cart links
 */
add_filter( 'woocommerce_add_to_cart_redirect', function( $url ) {
    if ( isset( $_REQUEST['ms_redirect_cart'] ) ) {
        return wc_get_cart_url();
    }
    return $url;
}, 99 );

/**
 * Ensure our custom parameter persists through WooCommerce processing
 */
add_filter( 'woocommerce_add_to_cart_validation', function( $passed, $product_id, $quantity ) {
    if ( isset( $_REQUEST['ms_redirect_cart'] ) ) {
        // Store in session so redirect filter can see it
        WC()->session->set( 'ms_redirect_cart', true );
    }
    return $passed;
}, 10, 3 );

/**
 * Check session for redirect flag
 */
add_filter( 'woocommerce_add_to_cart_redirect', function( $url ) {
    if ( WC()->session->get( 'ms_redirect_cart' ) ) {
        WC()->session->__unset( 'ms_redirect_cart' );
        return wc_get_cart_url();
    }
    return $url;
}, 100 );
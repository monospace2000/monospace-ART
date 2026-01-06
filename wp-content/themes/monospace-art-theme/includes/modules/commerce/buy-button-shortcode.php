<?php
/**
 * Painting Buy Button Shortcode
 * Integrated with monospace Sales Dashboard
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

    // Get dynamic discount hint from Sales Dashboard
    $discount_hint = monospace_get_volume_discount_hint( $product, $product_id, $status );

    $status_slug  = $status ? sanitize_title( $status ) : 'default';
    $status_class = ' status-' . $status_slug;

    $sku = $product->get_sku();

    $sku_html = $sku
        ? '<div class="painting-sku">'
            . esc_html( $sku ) .
          '</div>'
        : '';

    $price_html = '';
    $non_purchasable_statuses = [
        'gallery','private','sold','coming-soon','artist-private-collection','private-collection'
    ];

    $status_slug = $status ? sanitize_title( $status ) : '';

    /**
     * RESPONSIVE ADD-TO-CART LINK + CART REDIRECT
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
            '<div class="painting-price-row">' .
                '<div class="painting-price">' . $price_display . '</div>' .
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
        $discount_hint
    );
}
add_shortcode( 'painting_buy_button', 'monospace_custom_add_to_cart_shortcode' );

/**
 * Get volume discount hint from Sales Dashboard
 * Returns formatted HTML if product qualifies for volume discount
 */
function monospace_get_volume_discount_hint( $product, $product_id, $status ) {

    // Only show for available products
    $is_available = !in_array($status, ['sold', 'private', 'gallery']) && $product->is_in_stock();
    if ( ! $is_available ) {
        return '';
    }

    // Check if Sales Dashboard volume discounts are enabled
    if ( get_option('msd_volume_enable') !== 'yes' ) {
        return '';
    }

    // Get volume rules from Sales Dashboard
    $rules_json = get_option('msd_volume_rules', '');
    if ( empty( $rules_json ) ) {
        return '';
    }

    $volume_rules = json_decode( $rules_json, true );
    if ( ! is_array( $volume_rules ) ) {
        return '';
    }

    // Check if this product matches any volume discount rules
    foreach ( $volume_rules as $rule_key => $rules ) {

        if ( ! monospace_product_matches_volume_rule( $product_id, $rule_key ) ) {
            continue;
        }

        // Found a matching rule - get the best offer to display
        $hint = monospace_format_volume_hint( $rules, $rule_key, $product_id );
        if ( $hint ) {
            return '<div class="special-discount">' . $hint . '</div>';
        }
    }



    return '';
}

/**
 * Check if product matches a volume discount rule key
 */
function monospace_product_matches_volume_rule( $product_id, $rule_key ) {

    // Check if it's a product category
    if (has_term($rule_key, 'product_cat', $product_id)) {
        return true;
    }

    // Check if it's a product tag
    if (has_term($rule_key, 'product_tag', $product_id)) {
        return true;
    }

    // Attribute format: attr:pa_taxonomy=slug (advanced)
    if ( strpos( $rule_key, 'attr:' ) === 0 ) {
        $payload = substr( $rule_key, 5 );
        if ( strpos( $payload, '=' ) !== false ) {
            list( $tax, $slug ) = explode( '=', $payload, 2 );
            $tax = sanitize_text_field( trim( $tax ) );
            $slug = sanitize_text_field( trim( $slug ) );
            if ( $tax && $slug ) {
                return monospace_product_has_attribute( $product_id, $tax, $slug );
            }
        }
    }

    return false;
}

/**
 * Check if product has specific attribute term
 */
function monospace_product_has_attribute( $product_id, $taxonomy, $term_slug ) {
    $terms = wp_get_post_terms( $product_id, $taxonomy, ['fields' => 'slugs'] );
    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return false;
    }
    return in_array( $term_slug, (array) $terms, true );
}

/**
 * Format volume discount hint for display
 */
function monospace_format_volume_hint( $rules, $rule_key, $product_id ) {

    // Check if hints are enabled
    if ( get_option('msd_hints_enable') !== 'yes' ) {
        return '';
    }

    // Find the most attractive offer (typically the largest bundle)
    $best_offer = null;

    foreach ( $rules as $rule ) {
        if ( empty( $rule['type'] ) ) continue;

        switch ( $rule['type'] ) {
            case 'fixed_bundle':
                if ( isset( $rule['qty'], $rule['total'] ) ) {
                    // Prefer larger bundles
                    if ( ! $best_offer || $rule['qty'] > $best_offer['qty'] ) {
                        $best_offer = $rule;
                    }
                }
                break;

            case 'buy_x_get_y':
                if ( isset( $rule['buy'], $rule['get'] ) ) {
                    $best_offer = $best_offer ?: $rule;
                }
                break;

            case 'percent_discount':
                if ( isset( $rule['percent'] ) ) {
                    $best_offer = $best_offer ?: $rule;
                }
                break;
        }
    }

    if ( ! $best_offer ) {
        return '';
    }

    // Get styling from Sales Dashboard
    $styles = monospace_get_hint_styles();

    // Get templates from Sales Dashboard
    $secondary_text = get_option('msd_hint_secondary_text', '(Discount applied at checkout.)');

    // Format based on rule type
    $main_text = '';

    switch ( $best_offer['type'] ) {
        case 'fixed_bundle':
            $template = get_option('msd_hint_template_bundle', 'Special: Get {qty} for only ${total}!');
            $main_text = str_replace(
                ['{qty}', '{total}'],
                [$best_offer['qty'], number_format($best_offer['total'], 0)],
                $template
            );
            break;

        case 'buy_x_get_y':
            $template = get_option('msd_hint_template_bxgy', 'Buy {buy}, get {get} free!');
            $main_text = str_replace(
                ['{buy}', '{get}'],
                [$best_offer['buy'], $best_offer['get']],
                $template
            );
            break;

        case 'percent_discount':
            $template = get_option('msd_hint_template_percent', 'Save {percent}% on qualifying purchases!');
            $percent = rtrim(rtrim(number_format($best_offer['percent'], 1, '.', ''), '0'), '.');
            $main_text = str_replace('{percent}', $percent, $template);
            break;
    }

    if ( ! $main_text ) {
        return '';
    }

    // Make product-specific by inserting product type after quantity
    $main_text = monospace_make_hint_product_specific($main_text, $product_id, $best_offer);

    // Build HTML with inline styles
    return sprintf(
        '<span class="msd-hint-main" style="%s">%s</span><br><span class="msd-hint-secondary" style="%s;display:block;margin-top:4px;">%s</span>',
        esc_attr($styles['main']),
        esc_html($main_text),
        esc_attr($styles['secondary']),
        esc_html($secondary_text)
    );
}

/**
 * Get hint styling from Sales Dashboard settings
 */
function monospace_get_hint_styles() {

    // Container styles
    $bg_color = get_option('msd_hint_bg_color', '#f0f9f0');
    $border_color = get_option('msd_hint_border_color', '#33aa33');
    $border_width = get_option('msd_hint_border_width', '1');
    $border_radius = get_option('msd_hint_border_radius', '4');
    $padding = get_option('msd_hint_padding', '10px 12px');
    $margin = get_option('msd_hint_margin', '6px 0');
    $text_align = get_option('msd_hint_text_align', 'left');

    $container = sprintf(
        'background-color:%s;border:%spx solid %s;padding:%s;margin:%s;border-radius:%spx;text-align:%s;display:block;',
        esc_attr($bg_color),
        esc_attr($border_width),
        esc_attr($border_color),
        esc_attr($padding),
        esc_attr($margin),
        esc_attr($border_radius),
        esc_attr($text_align)
    );

    // Main text styles
    $main_color = get_option('msd_hint_text_color', '#33aa33');
    $main_size = get_option('msd_hint_text_size', '14');
    $font_weight = get_option('msd_hint_font_weight', 'bold');

    $main = sprintf(
        'color:%s;font-size:%spx;font-weight:%s;margin-top:0;',
        esc_attr($main_color),
        esc_attr($main_size),
        esc_attr($font_weight)
    );

    // Secondary text styles
    $secondary_color = get_option('msd_hint_secondary_color', '#666666');
    $secondary_size = get_option('msd_hint_secondary_size', '12');

    $secondary = sprintf(
        'color:%s;font-size:%spx;margin-top:6px;',
        esc_attr($secondary_color),
        esc_attr($secondary_size)
    );

    return [
        'container' => $container,
        'main' => $main,
        'secondary' => $secondary,
    ];
}

/**
 * Output hint styles as inline CSS
 */
function monospace_output_hint_styles() {
    if ( get_option('msd_hints_enable') !== 'yes' ) {
        return;
    }

    $styles = monospace_get_hint_styles();

    echo '<style type="text/css">';
    echo '.special-discount { ' . $styles['container'] . ' }';
    echo '</style>';
}
add_action('wp_head', 'monospace_output_hint_styles');

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
                ? '<a class="gallery-button button status-gallery" href="' . esc_url( $gallery_url ) . '" target="_blank" rel="noopener">' . $gallery_label . '</a>'
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
 * Make hint text product-specific
 * Inserts product type after quantity (e.g., "Get 3 miniatures for...")
 */
function monospace_make_hint_product_specific($text, $product_id, $offer) {
    // Get product type name
    $type_name = monospace_get_product_type_name($product_id);

    if (!$type_name) {
        return $text; // No specific type, keep as is
    }

    // Determine quantity for pluralization
    $qty = 1;
    if (isset($offer['qty'])) {
        $qty = $offer['qty'];
    } elseif (isset($offer['buy'])) {
        $qty = $offer['buy'];
    } elseif (isset($offer['get'])) {
        $qty = $offer['get'];
    }

    $term = ($qty == 1) ? $type_name['singular'] : $type_name['plural'];

    // Pattern matching for different templates
    // "Get 3 for" -> "Get 3 miniatures for"
    $text = preg_replace('/\b(\d+)\s+(for|at|@)\b/i', '$1 ' . $term . ' $2', $text);

    // "Buy 2, get" -> "Buy 2 miniatures, get"
    $text = preg_replace('/\bBuy\s+(\d+),/i', 'Buy $1 ' . $term . ',', $text);

    // "get 1 free" -> "get 1 miniature free"
    $text = preg_replace('/\bget\s+(\d+)\s+free\b/i', 'get $1 ' . $term . ' free', $text);

    return $text;
}

/**
 * Get product type name (singular and plural)
 */
function monospace_get_product_type_name($product_id) {
    // Check product tags first (more specific)
    $tags = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'all']);
    if (!is_wp_error($tags) && !empty($tags)) {
        $tag = $tags[0];
        $name = strtolower($tag->name);
        $singular = (substr($name, -1) === 's') ? substr($name, 0, -1) : $name;
        $plural = (substr($name, -1) === 's') ? $name : $name . 's';
        return ['singular' => $singular, 'plural' => $plural];
    }

    // Check categories
    $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'all']);
    if (!is_wp_error($categories) && !empty($categories)) {
        $cat = $categories[0];
        $name = strtolower($cat->name);
        $singular = (substr($name, -1) === 's') ? substr($name, 0, -1) : $name;
        $plural = (substr($name, -1) === 's') ? $name : $name . 's';
        return ['singular' => $singular, 'plural' => $plural];
    }

    return null;
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
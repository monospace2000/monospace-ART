<?php
/**
 * WooCommerce: Hide other shipping options when free shipping is available
 * Using JavaScript as a fallback when PHP filters don't work
 */

// Try PHP filter first (highest priority)
add_filter( 'woocommerce_package_rates', 'force_hide_shipping_when_free', PHP_INT_MAX, 2 );

function force_hide_shipping_when_free( $rates, $package ) {

    if ( empty( $rates ) ) {
        return $rates;
    }

    $free = array();

    foreach ( $rates as $rate_id => $rate ) {
        if ( strpos( $rate->method_id, 'free_shipping' ) !== false ) {
            $free[ $rate_id ] = $rate;
        }
    }

    return ! empty( $free ) ? $free : $rates;
}

// Make sure free shipping is selected by default when available
add_filter( 'woocommerce_shipping_chosen_method', 'set_free_shipping_as_default', 10, 3 );

function set_free_shipping_as_default( $chosen_method, $available_methods, $package ) {

    // Check if free shipping is available
    foreach ( $available_methods as $rate_id => $rate ) {
        if ( strpos( $rate->method_id, 'free_shipping' ) !== false ) {
            return $rate_id; // Return the free shipping rate ID
        }
    }

    return $chosen_method; // Return default if no free shipping
}

// CSS solution - hide non-free shipping when free shipping exists
add_action( 'wp_head', 'hide_shipping_options_css' );

function hide_shipping_options_css() {

    // Only run on cart and checkout pages
    if ( ! is_cart() && ! is_checkout() ) {
        return;
    }
    ?>
    <style type="text/css">
        /* Hide radio button for Free Shipping when it's the only option */
        .woocommerce-shipping-methods input[type="radio"]:only-of-type {
            display: none;
        }

        /* Hide all shipping methods by default when free shipping exists */
        #shipping_method:has(input[value*="free_shipping"]) li:not(:has(input[value*="free_shipping"])) {
            display: none !important;
        }

        /* Make free shipping label bold and green */
        #shipping_method li:has(input[value*="free_shipping"]) label {
            font-weight: bold !important;
            color: #28a745 !important;
        }
    </style>
    <?php
}



/**
 * Show a disabled quantity field for "sold individually" products in the cart
 */
add_filter('woocommerce_cart_item_quantity', function($product_quantity, $cart_item_key, $cart_item) {

    $product = $cart_item['data'];

    if ($product->is_sold_individually()) {
        // Output a disabled input with value 1
        $product_quantity = '<div style="width: 100%;font-size: 1.1em;"><code>1</code></div>';
    }

    return $product_quantity;

}, 20, 3);



// Show "Update Cart" button when cart contains a print product.
// Uses JS (wp_footer) to bypass CSS specificity battles and remove WooCommerce's
// default `disabled` attribute, which the button has on initial page load.
add_action( 'wp_footer', function() {
    if ( ! is_cart() || ! WC()->cart ) {
        return;
    }
    if ( ! function_exists( 'monospace_get_post_for_print_product' ) ) {
        return;
    }
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $product_id = $cart_item['product_id'] ?? 0;
        if ( $product_id && monospace_get_post_for_print_product( $product_id ) ) {
            ?>
            <script>
            document.addEventListener( 'DOMContentLoaded', function () {
                var btn = document.querySelector( 'button[name="update_cart"]' );
                if ( btn ) {
                    btn.removeAttribute( 'disabled' );
                    btn.style.setProperty( 'display',       'inline-block', 'important' );
                    btn.style.setProperty( 'visibility',    'visible',      'important' );
                    btn.style.setProperty( 'opacity',       '1',            'important' );
                    btn.style.setProperty( 'pointer-events','auto',         'important' );
                    btn.style.setProperty( 'position',      'relative',     'important' );
                    btn.style.setProperty( 'width',         'auto',         'important' );
                    btn.style.setProperty( 'height',        'auto',         'important' );
                    btn.style.setProperty( 'left',          'auto',         'important' );
                }
            } );
            </script>
            <?php
            break;
        }
    }
} );


// Show "Free shipping" under the line item name for print products
add_filter( 'woocommerce_cart_item_name', function( $name, $cart_item, $cart_item_key ) {
    $product_id = $cart_item['product_id'] ?? 0;
    if ( $product_id && function_exists( 'monospace_get_post_for_print_product' ) ) {
        if ( monospace_get_post_for_print_product( $product_id ) ) {
            $name .= '<br><small style="color:#28a745;font-weight:600;">Free shipping</small>';
        }
    }
    return $name;
}, 10, 3 );

// replace"Product" with "Item" on client facing pages
add_filter( 'gettext', 'monospace_frontend_replace_product_label', 20, 3 );
function monospace_frontend_replace_product_label( $translated, $text, $domain ) {

    // Do NOT affect admin
    if ( is_admin() ) {
        return $translated;
    }

    // Only WooCommerce strings
    if ( $domain !== 'woocommerce' ) {
        return $translated;
    }

    // Exact replacement
    if ( $text === 'Product' ) {
        return 'Item';
    }

    return $translated;
}


/**
 * Show a disabled quantity field for "sold individually" products in the cart
 */
add_filter('woocommerce_cart_item_quantity', function($product_quantity, $cart_item_key, $cart_item) {

    $product = $cart_item['data'];

    if ($product->is_sold_individually()) {
        // Output a disabled input with value 1
        $product_quantity = '<div style="width: 100%;text-align:center;font-size: 1.1em;"><code>1</code></div>';
    }

    return $product_quantity;

}, 20, 3);


// Remove related products from single product pages
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );







// Usage: [product_price id="123"]
function ms_product_price_shortcode( $atts ) {
    $atts = shortcode_atts([ 'id' => null ], $atts);
    if ( ! $atts['id'] ) return '';

    $product = wc_get_product( intval($atts['id']) );
    if ( ! $product ) return '';

    $price_html = $product->get_price_html();
    if ( ! $price_html ) return '';

    // return inline element (no CSS) so it won't cause block margin collapse
    return '<span class="ms-product-price">' . wp_kses_post( $price_html ) . '</span>';
}
add_shortcode( 'product_price', 'ms_product_price_shortcode' );






add_filter( 'woocommerce_return_to_shop_redirect', function () {
    return home_url( '/art-feed/' ); // change to your desired URL
} );


/**
 * Display price on single product pages
 * Uses same hook as commission products for consistency
 */
add_action( 'woocommerce_before_add_to_cart_button', function() {
    global $product;

    if ( ! $product ) {
        return;
    }

    // Skip if this is a commission product (they handle their own price display)
    $product_id = $product->get_id();
    if ( get_post_meta( $product_id, '_is_commission_product', true ) === 'yes' ) {
        return;
    }

    // Get the price
    $price = $product->get_price();

    if ( ! $price || $price <= 0 ) {
        return;
    }

    // Display the price with same styling as commission products
    echo '<p class="product-price-display" style="margin-bottom: 0em !important; font-size: 1.3em; font-weight: 600; color: #333;">';
    echo '<strong>Price:</strong> ' . wc_price( $price );
    echo '</p>';
    echo '<div style="margin-bottom: 1.5em;"></div>';
}, 5 );



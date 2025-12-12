<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */




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


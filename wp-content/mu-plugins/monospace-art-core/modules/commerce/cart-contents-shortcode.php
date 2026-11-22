<?php
/**
 * [cart_contents] - what's in the visitor's cart, anywhere (2026-10-21, owner: "shortcode for cart contents").
 *
 *   [cart_contents]                          the items (picture, name, options, quantity, price), the subtotal, View cart
 *                                            and Checkout
 *   style="list | count | total"             the list, "2 items", or the subtotal alone
 *   images="no"  buttons="no"  empty="Your cart is empty."
 *
 * Pages are cached for everyone, so the shortcode prints an empty box and the visitor's browser fills it from WooCommerce
 * (?wc-ajax=ms_cart_contents - WooCommerce's own uncached endpoint, with the visitor's cart); it refreshes when something
 * is added to or removed from the cart (WooCommerce's added_to_cart / removed_from_cart / wc_fragments_refreshed). The
 * look: the theme's _woocommerce.scss (.ms-cart-contents).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_cart_contents_atts( $atts ) {
    $a = shortcode_atts( [ 'style' => 'list', 'images' => 'yes', 'buttons' => 'yes', 'empty' => 'Your cart is empty.' ], (array) $atts );
    return [
        'style'   => in_array( $a['style'], [ 'list', 'count', 'total' ], true ) ? $a['style'] : 'list',
        'images'  => $a['images'] === 'no' ? 'no' : 'yes',
        'buttons' => $a['buttons'] === 'no' ? 'no' : 'yes',
        'empty'   => sanitize_text_field( $a['empty'] ),
    ];
}

/** The box's contents for the current visitor's cart */
function monospace_cart_contents_html( $a ) {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) return '';
    $cart  = WC()->cart;
    $count = (int) $cart->get_cart_contents_count();
    if ( $a['style'] === 'count' ) return esc_html( sprintf( _n( '%d item', '%d items', $count ), $count ) );
    if ( $a['style'] === 'total' ) return $count ? wp_kses_post( $cart->get_cart_subtotal() ) : '';
    if ( ! $count ) return '<p class="ms-cart-empty">' . esc_html( $a['empty'] ) . '</p>';

    $out = '<ul class="ms-cart-items">';
    foreach ( $cart->get_cart() as $item ) {
        $product = $item['data'] ?? null;
        if ( ! $product ) continue;
        $name = $product->get_name();
        $vars = ! empty( $item['variation'] ) ? wc_get_formatted_cart_item_data( $item, true ) : '';
        $link = $product->is_visible() ? get_permalink( $product->get_parent_id() ?: $product->get_id() ) : '';
        $out .= '<li class="ms-cart-item">'
              . ( $a['images'] === 'yes' ? '<span class="ms-cart-thumb">' . $product->get_image( 'thumbnail' ) . '</span>' : '' )
              . '<span class="ms-cart-name">' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>' : esc_html( $name ) )
              . ( $vars !== '' ? '<span class="ms-cart-vars">' . esc_html( trim( preg_replace( '/\s+/', ' ', $vars ) ) ) . '</span>' : '' ) . '</span>'
              . '<span class="ms-cart-qty">× ' . (int) $item['quantity'] . '</span>'
              . '<span class="ms-cart-price">' . wp_kses_post( $cart->get_product_subtotal( $product, $item['quantity'] ) ) . '</span>'
              . '</li>';
    }
    $out .= '</ul><p class="ms-cart-subtotal"><span>Subtotal</span> <strong>' . wp_kses_post( $cart->get_cart_subtotal() ) . '</strong></p>';
    if ( $a['buttons'] === 'yes' ) {
        $out .= '<p class="ms-cart-buttons"><a class="button" href="' . esc_url( wc_get_cart_url() ) . '">View cart</a> <a class="button" href="' . esc_url( wc_get_checkout_url() ) . '">Checkout</a></p>';
    }
    return $out;
}

add_shortcode( 'cart_contents', function ( $atts ) {
    if ( ! function_exists( 'WC' ) ) return '';
    $a = monospace_cart_contents_atts( $atts );
    monospace_cart_contents_script();
    $tag = $a['style'] === 'list' ? 'div' : 'span';
    return '<' . $tag . ' class="ms-cart-contents ms-cart-' . esc_attr( $a['style'] ) . '" data-ms-cart="' . esc_attr( wp_json_encode( $a ) ) . '" aria-live="polite"></' . $tag . '>';
} );

// The visitor's cart, for the box (WooCommerce's ajax endpoint: no page cache, the visitor's session)
add_action( 'wc_ajax_ms_cart_contents', function () {
    nocache_headers();
    $a = monospace_cart_contents_atts( json_decode( wp_unslash( $_GET['a'] ?? '{}' ), true ) ?: [] );
    wp_send_json_success( [ 'html' => monospace_cart_contents_html( $a ) ] );
} );

function monospace_cart_contents_script() {
    static $done = false;
    if ( $done ) return;
    $done = true;
    add_action( 'wp_footer', function () { ?>
    <script id="ms-cart-contents">
    (function () {
        var url = <?php echo wp_json_encode( class_exists( 'WC_AJAX' ) ? WC_AJAX::get_endpoint( 'ms_cart_contents' ) : '' ); ?>;
        function fill() {
            document.querySelectorAll('.ms-cart-contents[data-ms-cart]').forEach(function (box) {
                fetch(url.replace('%%endpoint%%', 'ms_cart_contents') + '&a=' + encodeURIComponent(box.dataset.msCart), { credentials: 'same-origin', cache: 'no-store' })
                    .then(function (r) { return r.json(); })
                    .then(function (r) { if (r && r.success) box.innerHTML = r.data.html; })
                    .catch(function () {});
            });
        }
        fill();
        if (window.jQuery) jQuery(document.body).on('added_to_cart removed_from_cart wc_fragments_refreshed updated_cart_totals', fill);
    })();
    </script>
    <?php }, 30 );
}

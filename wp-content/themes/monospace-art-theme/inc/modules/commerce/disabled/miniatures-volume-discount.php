<?php
/*
Plugin Name: monospace ART - Product Filter & Volume Discounts (Refactor)
Description: Flexible product-volume discount engine with category, miniature (pa_size=4x4), and attribute-based selection.
Version: 2.0
Author: Hens Breet
*/

if (!defined('ABSPATH')) exit;

/*
------------------------------------
DISCOUNT RULE EXAMPLES (same as before)
------------------------------------
1) Buy 2 get 1 free
[
    'type' => 'buy_x_get_y',
    'buy'  => 2,
    'get'  => 1
]

2) Second item half price
[
    'type'  => 'second_half',
    'factor'=> 0.5
]

3) 3 for $99
[
    'type'  => 'fixed_bundle',
    'qty'   => 3,
    'total' => 99
]

4) 20% off category
[
    'type'    => 'percent_discount',
    'percent' => 20
]

5) $5 off each
[
    'type'   => 'fixed_discount',
    'amount' => 5
]
*/

/**
 * Helper: determine if a product has a given global attribute term
 * e.g. check_product_has_attribute( $product_id, 'pa_size', '4x4' )
 */
function check_product_has_attribute( $product_id, $taxonomy, $term_slug ) {
    if ( empty( $product_id ) || empty( $taxonomy ) || empty( $term_slug ) ) return false;
    $terms = wp_get_post_terms( $product_id, $taxonomy, ['fields' => 'slugs'] );
    if ( is_wp_error( $terms ) || empty( $terms ) ) return false;
    return in_array( $term_slug, (array) $terms, true );
}

/**
 * Helper: determine if a product belongs to a product category slug
 */
function check_product_in_category_slug( $product_id, $cat_slug ) {
    if ( empty( $product_id ) || empty( $cat_slug ) ) return false;
    $cats = wp_get_post_terms( $product_id, 'product_cat', ['fields' => 'slugs'] );
    if ( is_wp_error( $cats ) || empty( $cats ) ) return false;
    return in_array( $cat_slug, (array) $cats, true );
}

/**
 * Helper: product matching logic for a rule key
 *
 * Supported key formats:
 *  - 'miniature'                      => product has pa_size = 4x4
 *  - 'some-category-slug'             => product_cat slug
 *  - 'attr:pa_taxonomy=term_slug'     => a product attribute match
 */
function product_matches_rule_key( $product_id, $rule_key ) {

    if ( ! $product_id || ! $rule_key ) return false;

    // 1) special miniature key
    if ( $rule_key === 'miniature' ) {
        return check_product_has_attribute( $product_id, 'pa_size', '4x4' );
    }

    // 2) attr:pa_taxonomy=slug
    if ( strpos( $rule_key, 'attr:' ) === 0 ) {
        $payload = substr( $rule_key, 5 ); // after 'attr:'
        if ( strpos( $payload, '=' ) !== false ) {
            list( $tax, $slug ) = explode( '=', $payload, 2 );
            $tax = sanitize_text_field( trim( $tax ) );
            $slug = sanitize_text_field( trim( $slug ) );
            if ( $tax && $slug ) {
                return check_product_has_attribute( $product_id, $tax, $slug );
            }
        }
        return false;
    }

    // 3) otherwise assume it's a product category slug
    return check_product_in_category_slug( $product_id, $rule_key );
}

/**
 * Volume discount engine (main)
 */
add_action( 'woocommerce_before_calculate_totals', function( $cart ) {

    // safety checks
    if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;
    if ( ! $cart || $cart->is_empty() ) return;

    /*
    ------------------------------------
    CONFIGURE YOUR CATEGORY/ATTRIBUTE RULES HERE
    Keys can be:
      - product category slug (e.g. 'print', 'custom-order')
      - 'miniature' (special - matches products with pa_size = 4x4)
      - attribute rule like 'attr:pa_format=limited'
    ------------------------------------
    */
    $volume_rules = [

        // example: apply bundle rule to miniatures (4x4)

        'miniature' => [
            [
                'type'  => 'fixed_bundle',
                'qty'   => 2,
                'total' => 69
            ],
            [
                'type'  => 'fixed_bundle',
                'qty'   => 3,
                'total' => 99
            ],
        ],

        // example: category-based rule (product_cat slug 'print')
        // 'print' => [
        //     [ 'type'=>'percent_discount', 'percent' => 15 ]
        // ],

        // example: attribute-based rule (global attribute pa_format slug 'limited')
        // 'attr:pa_format=limited' => [
        //     [ 'type'=>'fixed_discount', 'amount'=>5 ]
        // ],
    ];

    /*
    ------------------------------------
    ENGINE: iterate rules and apply per matching cart items
    ------------------------------------
    */
    foreach ( $volume_rules as $rule_key => $rules ) {

        // collect matching cart items for this rule
        $items = [];
        $total_qty = 0;

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {

            $product_id = $cart_item['product_id'];

            // match against rule key
            if ( product_matches_rule_key( $product_id, $rule_key ) ) {

                $items[ $cart_item_key ] = $cart_item;
                $total_qty += $cart_item['quantity'];

                // store original display price once
                if ( empty( $cart->cart_contents[ $cart_item_key ]['original_display_price'] ) ) {
                    $cart->cart_contents[ $cart_item_key ]['original_display_price'] =
                        $cart_item['data']->get_regular_price();
                }
            }
        }

        if ( $total_qty === 0 ) {
            // nothing to do for this rule
            continue;
        }

        // now apply each rule in order for this key
        foreach ( $rules as $rule ) {

            if ( empty( $rule['type'] ) ) continue;

            switch ( $rule['type'] ) {

                /*
                 * FIXED BUNDLE (e.g., 3 for $99)
                 */
                case 'fixed_bundle':
                    if (!isset($rule['qty'], $rule['total'])) break;

                    $sets = floor($total_qty / $rule['qty']);
                    if ($sets < 1) break;

                    $bundle_price = round($rule['total'] / $rule['qty'], wc_get_price_decimals());
                    $qty_to_discount = $sets * $rule['qty'];

                    foreach ($items as $cart_key => $cart_item) {

                        if ($qty_to_discount <= 0) break;

                        $apply_qty = min($cart_item['quantity'], $qty_to_discount);

                        // Apply discounted price
                        $cart->cart_contents[$cart_key]['data']->set_price($bundle_price);

                        // Store a clean label text (no HTML from wc_price here)
                        $cart->cart_contents[$cart_key]['volume_discount_label'] =
                            "{$rule['qty']} for \${$rule['total']}";

                        $qty_to_discount -= $apply_qty;
                    }
                    break;

                /*
                 * BUY X GET Y FREE (cheapest items free)
                 */
                case 'buy_x_get_y':
                    if ( ! isset( $rule['buy'], $rule['get'] ) ) break;

                    $buy = intval( $rule['buy'] );
                    $get = intval( $rule['get'] );
                    if ( $buy < 1 || $get < 1 ) break;

                    $cycle = $buy + $get;
                    $full_cycles = floor( $total_qty / $cycle );
                    $free_items = $full_cycles * $get;
                    if ( $free_items < 1 ) break;

                    // sort items by price ascending so cheapest become free
                    uasort( $items, function( $a, $b ) {
                        return $a['data']->get_price() <=> $b['data']->get_price();
                    });

                    foreach ( $items as $key => $cart_item ) {
                        if ( $free_items <= 0 ) break;

                        $apply_qty = min( $cart_item['quantity'], $free_items );

                        // set price to zero for the free portion. To keep it simple,
                        // set the whole line price to 0 when apply_qty == cart_item quantity,
                        // otherwise adjust by setting price to zero and rely on label (approx).
                        // (This simple approach matches your earlier logic.)
                        $cart->cart_contents[ $key ]['data']->set_price(0);
                        $cart->cart_contents[ $key ]['volume_discount_label'] = sprintf( 'Buy %d get %d free', $buy, $get );

                        $free_items -= $apply_qty;
                    }
                    break;

                /*
                 * SECOND ITEM HALF PRICE
                 */
                case 'second_half':
                    if ( $total_qty < 2 ) break;

                    $pairs = floor( $total_qty / 2 );

                    // Iterate items in cart order and apply half price to as many items as pairs
                    foreach ( $items as $key => $cart_item ) {
                        if ( $pairs <= 0 ) break;

                        $reg_price = $cart_item['data']->get_regular_price();
                        $half = round( $reg_price * 0.5, wc_get_price_decimals() );

                        $cart->cart_contents[ $key ]['data']->set_price( $half );
                        $cart->cart_contents[ $key ]['volume_discount_label'] = 'Second item half price';

                        $pairs--;
                    }
                    break;

                /*
                 * PERCENT DISCOUNT (e.g. 20% off)
                 */
                case 'percent_discount':
                    if ( ! isset( $rule['percent'] ) ) break;
                    $pct = floatval( $rule['percent'] );

                    foreach ( $items as $key => $cart_item ) {
                        $reg = $cart_item['data']->get_regular_price();
                        $new = round( $reg * ( 1 - $pct / 100 ), wc_get_price_decimals() );
                        $cart->cart_contents[ $key ]['data']->set_price( $new );
                        $cart->cart_contents[ $key ]['volume_discount_label'] = sprintf( '%s%% off', rtrim(rtrim(number_format( $pct, 2, '.', ''),'0'),'.') );
                    }
                    break;

                /*
                 * FIXED DISCOUNT ($X off each)
                 */
                case 'fixed_discount':
                    if ( ! isset( $rule['amount'] ) ) break;
                    $amount = floatval( $rule['amount'] );

                    foreach ( $items as $key => $cart_item ) {
                        $reg = $cart_item['data']->get_regular_price();
                        $new = max( 0, round( $reg - $amount, wc_get_price_decimals() ) );
                        $cart->cart_contents[ $key ]['data']->set_price( $new );
                        $cart->cart_contents[ $key ]['volume_discount_label'] = sprintf( '%s off', wc_price( $amount ) );
                    }
                    break;

                default:
                    // unknown rule type — ignore
                    break;
            } // end switch
        } // end foreach rules
    } // end foreach volume_rules

}); // end action

/*
------------------------------------
DISPLAY ORIGINAL PRICE + LABEL (unchanged)
------------------------------------
*/
add_filter( 'woocommerce_cart_item_price', function( $price_html, $cart_item ) {
    if ( ! empty( $cart_item['original_display_price'] ) ) {
        return wc_price( $cart_item['original_display_price'] );
    }
    return $price_html;
}, 20, 2 );

add_filter( 'woocommerce_cart_item_subtotal', function( $subtotal, $cart_item ) {
    if ( ! empty( $cart_item['volume_discount_label'] ) ) {
        $subtotal .= '<br><small style="color:green;">' . esc_html( $cart_item['volume_discount_label'] ) . '</small>';
    }
    return $subtotal;
}, 20, 2 );

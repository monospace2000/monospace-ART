<?php
/*
------------------------------------
DISCOUNT RULE EXAMPLES
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


add_action('woocommerce_before_calculate_totals', function($cart){

    if (is_admin() && !defined('DOING_AJAX')) return;
    if (!$cart || $cart->is_empty()) return;

    /*
    ------------------------------------
    CONFIGURE YOUR CATEGORY RULES HERE
    ------------------------------------
    */

    $volume_rules = [

        'miniature' => [
            [
                'type'  => 'fixed_bundle',
                'qty'   => 3,
                'total' => 99
            ],
            /*
            [
                'type' => 'buy_x_get_y',
                'buy'  => 2,
                'get'  => 1
            ]
            */
        ],

        /*
        'print' => [
            [
                'type'   => 'second_half',
                'factor' => 0.5
            ]
        ]
        */
    ];

    /*
    ------------------------------------
    DISCOUNT ENGINE — DO NOT EDIT BELOW
    ------------------------------------
    */

    foreach ($volume_rules as $category_slug => $rules) {

        $items      = [];
        $total_qty  = 0;

        foreach ($cart->get_cart() as $key => $cart_item) {

            $pid  = $cart_item['product_id'];
            $cats = wp_get_post_terms($pid, 'product_cat', ['fields' => 'slugs']);

            if (!is_array($cats)) $cats = [];

            if (in_array($category_slug, $cats)) {

                $items[$key] = $cart_item;
                $total_qty  += $cart_item['quantity'];

                // store original display price once
                if (empty($cart->cart_contents[$key]['original_display_price'])) {
                    $cart->cart_contents[$key]['original_display_price'] =
                        $cart_item['data']->get_regular_price();
                }
            }
        }

        if ($total_qty === 0) continue;


        /*
        ------------------------------------
        APPLY EACH RULE IN THIS CATEGORY
        ------------------------------------
        */

        foreach ($rules as $rule) {

            switch ($rule['type']) {

                /*
                -------------------------------
                FIXED BUNDLE (3 for 99)
                ------------------------------- */
                case 'fixed_bundle':

                    if (!isset($rule['qty'], $rule['total'])) break;

                    $sets = floor($total_qty / $rule['qty']);
                    if ($sets < 1) break;

                    $bundle_price    = round($rule['total'] / $rule['qty'], wc_get_price_decimals());
                    $qty_to_discount = $sets * $rule['qty'];

                    foreach ($items as $key => $cart_item) {

                        if ($qty_to_discount <= 0) break;

                        $apply_qty = min($cart_item['quantity'], $qty_to_discount);
                        $cart->cart_contents[$key]['data']->set_price($bundle_price);
                        $cart->cart_contents[$key]['volume_discount_label'] = "{$rule['qty']} for {$rule['total']}";

                        $qty_to_discount -= $apply_qty;
                    }
                    break;


                /*
                -------------------------------
                BUY X GET Y FREE
                ------------------------------- */
                case 'buy_x_get_y':

                    if (!isset($rule['buy'], $rule['get'])) break;

                    $cycle       = $rule['buy'] + $rule['get'];
                    $full_cycles = floor($total_qty / $cycle);
                    $free_items  = $full_cycles * $rule['get'];

                    if ($free_items < 1) break;

                    // Sort cheapest first
                    uasort($items, function($a, $b){
                        return $a['data']->get_price() <=> $b['data']->get_price();
                    });

                    foreach ($items as $key => $cart_item) {

                        if ($free_items <= 0) break;

                        $apply_qty = min($cart_item['quantity'], $free_items);

                        $cart->cart_contents[$key]['data']->set_price(0);
                        $cart->cart_contents[$key]['volume_discount_label'] =
                            "Buy {$rule['buy']} get {$rule['get']} free";

                        $free_items -= $apply_qty;
                    }
                    break;


                /*
                -------------------------------
                SECOND ONE HALF PRICE
                ------------------------------- */
                case 'second_half':

                    if ($total_qty < 2) break;

                    $pairs = floor($total_qty / 2);

                    foreach ($items as $key => $cart_item) {

                        if ($pairs <= 0) break;

                        $reg_price = $cart_item['data']->get_regular_price();
                        $half      = round($reg_price * 0.5, wc_get_price_decimals());

                        $cart->cart_contents[$key]['data']->set_price($half);
                        $cart->cart_contents[$key]['volume_discount_label'] = "Second item half price";

                        $pairs--;
                    }
                    break;


                /*
                -------------------------------
                PERCENT DISCOUNT
                ------------------------------- */
                case 'percent_discount':

                    if (!isset($rule['percent'])) break;

                    foreach ($items as $key => $cart_item) {

                        $reg = $cart_item['data']->get_regular_price();
                        $new = round($reg * (1 - $rule['percent']/100), wc_get_price_decimals());

                        $cart->cart_contents[$key]['data']->set_price($new);
                        $cart->cart_contents[$key]['volume_discount_label'] =
                            "{$rule['percent']}% off";
                    }
                    break;


                /*
                -------------------------------
                FIXED DISCOUNT
                ------------------------------- */
                case 'fixed_discount':

                    if (!isset($rule['amount'])) break;

                    foreach ($items as $key => $cart_item) {

                        $reg = $cart_item['data']->get_regular_price();
                        $new = max(0, $reg - $rule['amount']);

                        $cart->cart_contents[$key]['data']->set_price($new);
                        $cart->cart_contents[$key]['volume_discount_label'] =
                            "{$rule['amount']} off";
                    }
                    break;
            }
        }
    }
});


/*
------------------------------------
DISPLAY ORIGINAL PRICE + LABEL
------------------------------------
*/

add_filter('woocommerce_cart_item_price', function($price_html, $cart_item) {

    if (!empty($cart_item['original_display_price'])) {
        return wc_price($cart_item['original_display_price']);
    }
    return $price_html;
}, 20, 2);


add_filter('woocommerce_cart_item_subtotal', function($subtotal, $cart_item) {

    if (!empty($cart_item['volume_discount_label'])) {
        $subtotal .= '<br><small style="color:green;">'
            . esc_html($cart_item['volume_discount_label'])
            . '</small>';
    }
    return $subtotal;
}, 20, 2);


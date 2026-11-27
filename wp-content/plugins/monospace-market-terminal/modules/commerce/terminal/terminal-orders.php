<?php
/**
 * Market terminal, step 2 (2026-11-14): from a sale to an order. Used by terminal-shortcode.php ([market_terminal], /terminal/).
 *
 * Choosing how the customer pays creates the order at once - status Pending payment, label in-person-sale, created_via
 * "ms-terminal" (WooCommerce's unpaid-order cleanup only touches "checkout" orders, so it is left alone) - and sets its pieces
 * to Reserved, so nobody buys them online meanwhile. While such an order exists the page shows its payment screen, however
 * often it is reloaded or left: that is the "unfinished sale". Paid completes the order (stock goes, the pieces are Sold, a
 * receipt goes to the email if one was given); Not paid deletes the never-paid order, frees the pieces and puts the list back.
 *
 * Settings: WooCommerce > Market terminal (terminal-settings.php), option ms_terminal_settings.
 *
 * @package monospace-market-terminal
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The terminal's settings with their defaults */
function ms_term_settings() {
    $s = get_option( 'ms_terminal_settings' );
    $s = is_array( $s ) ? $s : [];
    return [
        'methods'       => array_values( array_intersect( [ 'Square', 'Cash', 'Venmo', 'PayPal' ], (array) ( $s['methods'] ?? [ 'Square', 'Cash', 'Venmo', 'PayPal' ] ) ) ),
        'venmo'         => (string) ( $s['venmo'] ?? 'Hens-Breet' ),
        'paypal'        => (string) ( $s['paypal'] ?? 'monospace' ),
        'paypal_email'  => (string) ( $s['paypal_email'] ?? 'hens@monospace.com' ), // the account's address: PayPal's checkout link for the QR code
        'square_app_id' => (string) ( $s['square_app_id'] ?? '' ),
    ];
}

/** The unfinished sale of the current user: an in-person order still waiting for payment (null = none) */
function ms_term_pending_order() {
    $orders = wc_get_orders( [
        'limit' => 1, 'status' => 'pending', 'orderby' => 'date', 'order' => 'DESC',
        'meta_key' => '_ms_term_user', 'meta_value' => get_current_user_id(),
    ] );
    return $orders ? $orders[0] : null;
}

/** The product ids of an order's pieces */
function ms_term_order_products( $order ) {
    $ids = [];
    foreach ( $order->get_items() as $item ) $ids[] = (int) $item->get_product_id();
    return $ids;
}

/** $total split over lines in proportion to their amounts, in whole cents, the leftover cents to the largest remainders */
function ms_term_apportion( array $amounts, $total ) {
    $sum = array_sum( $amounts );
    if ( $sum <= 0 || $total <= 0 ) return array_fill( 0, count( $amounts ), 0 );
    $shares = [];
    $rest   = [];
    foreach ( $amounts as $i => $a ) {
        $exact       = $total * $a / $sum;
        $shares[ $i ] = (int) floor( $exact );
        $rest[ $i ]   = $exact - $shares[ $i ];
    }
    $left = $total - array_sum( $shares );
    arsort( $rest );
    foreach ( array_keys( $rest ) as $i ) {
        if ( $left <= 0 ) break;
        $shares[ $i ]++;
        $left--;
    }
    return $shares;
}

/** Where the customer pays Venmo / PayPal (the address behind the QR code), with the amount filled in */
function ms_term_pay_url( $method, $amount, $note ) {
    $s   = ms_term_settings();
    $amt = number_format( (float) $amount, 2, '.', '' );
    if ( $method === 'Venmo' && $s['venmo'] !== '' ) {
        return 'https://venmo.com/' . rawurlencode( ltrim( $s['venmo'], '@' ) ) . '?txn=pay&audience=private&amount=' . $amt . '&note=' . rawurlencode( $note );
    }
    if ( $method === 'PayPal' && ( $s['paypal'] !== '' || is_email( $s['paypal_email'] ) ) ) {
        // Through this site (ms_term_paypal_bounce() below), not straight to paypal.me: a camera opening paypal.me goes to the PayPal
        // phone app, which shows the profile and drops the amount; opened in Safari the amount is kept, and a redirect from our own
        // address stays in Safari
        return home_url( '/?pp=' . $amt . '&n=' . rawurlencode( $note ) );
    }
    return '';
}

/** A short note for the payment: "monospace ART #426 #427" */
function ms_term_pay_note( $order ) {
    $numbers = [];
    foreach ( ms_term_order_products( $order ) as $id ) {
        $n = function_exists( 'monospace_sku_number' ) ? monospace_sku_number( get_post_meta( $id, '_sku', true ) ) : '';
        if ( $n !== '' ) $numbers[] = '#' . $n;
    }
    return trim( 'monospace ART ' . implode( ' ', $numbers ) );
}

/** What each line of a sale was paid (after the automatic discount and its share of the discount on the spot), to the cent */
function ms_term_paid_prices( $calc ) {
    $mult  = pow( 10, wc_get_price_decimals() );
    $cents = array_map( function ( $l ) use ( $mult ) { return (int) round( $l['price'] * $mult ); }, $calc['lines'] );
    $share = ms_term_apportion( $cents, (int) round( $calc['discount'] * $mult ) );
    $paid  = [];
    foreach ( $cents as $i => $c ) $paid[ $i ] = ( $c - $share[ $i ] ) / $mult;
    return $paid;
}

/* ------------------------------
   Practice mode (per user): the whole flow, nothing recorded - no order, no Reserved or Sold, no email; the QR codes ask $0.01
-------------------------------*/

function ms_term_practice() {
    return get_user_meta( get_current_user_id(), 'ms_terminal_practice', true ) === '1';
}

/** The practice sale waiting for "Paid" (null = none): method, total, lines [ product id, paid ] */
function ms_term_practice_pending() {
    $p = get_user_meta( get_current_user_id(), 'ms_terminal_practice_pending', true );
    return is_array( $p ) && ! empty( $p['lines'] ) ? $p : null;
}

/** An order held in memory only (never saved) for the payment screen of a practice sale */
function ms_term_practice_order( $pending ) {
    $order = new WC_Order();
    foreach ( $pending['lines'] as $line ) {
        $product = wc_get_product( (int) $line[0] );
        if ( ! $product ) continue;
        $item = new WC_Order_Item_Product();
        $item->set_product( $product );
        $item->set_quantity( 1 );
        $item->set_total( (float) $line[1] );
        $order->add_item( $item );
    }
    if ( ! empty( $pending['tip'] ) ) $order->add_item( ms_term_tip_item( (float) $pending['tip'] ) );
    $order->set_payment_method( (string) $pending['method'] );
    $order->set_total( (float) $pending['total'] );
    if ( ! empty( $pending['note'] ) ) $order->update_meta_data( '_ms_term_note', (string) $pending['note'] ); // in memory only, for the screen
    return $order;
}

/** A tip as its own untaxed line on the order, never part of a piece's price */
function ms_term_tip_item( $amount ) {
    $fee = new WC_Order_Item_Fee();
    $fee->set_name( 'Tip' );
    $fee->set_tax_status( 'none' );
    $fee->set_amount( (string) $amount );
    $fee->set_total( (string) $amount );
    return $fee;
}

/**
 * Turn the sale into a pending order and reserve its pieces. Returns the order, or a WP_Error with what to tell the owner.
 * Each line keeps its listed price (subtotal) and what was paid for it (total: after the automatic discount and its share
 * of the discount given on the spot), so the order shows the discounts.
 */
function ms_term_start_order( $method, $sale, $email = '' ) {
    if ( ! in_array( $method, ms_term_settings()['methods'], true ) ) return new WP_Error( 'method', 'That way of paying is not switched on.' );
    $calc = ms_term_calc( $sale );
    if ( ! $calc['lines'] ) return new WP_Error( 'empty', 'Add a piece to the sale first.' );
    foreach ( $calc['lines'] as $line ) {
        if ( ! ms_term_is_available( $line['id'] ) ) return new WP_Error( 'gone', $line['product']->get_name() . ' is no longer available.' );
    }

    $paid  = ms_term_paid_prices( $calc );

    $order = wc_create_order( [ 'status' => 'pending', 'created_via' => 'ms-terminal' ] );
    if ( is_wp_error( $order ) ) return $order;

    foreach ( $calc['lines'] as $i => $line ) {
        $item = new WC_Order_Item_Product();
        $item->set_product( $line['product'] );
        $item->set_quantity( 1 );
        $item->set_subtotal( $line['listed'] );
        $item->set_total( $paid[ $i ] );
        if ( $line['label'] ) $item->add_meta_data( '_ms_deal', $line['label'] );
        $order->add_item( $item );
    }
    if ( $calc['tip'] > 0 ) $order->add_item( ms_term_tip_item( $calc['tip'] ) );
    $order->set_payment_method( $method );
    $order->set_payment_method_title( $method );
    if ( is_email( $email ) ) $order->set_billing_email( $email );
    $order->update_meta_data( 'order_label', 'in-person-sale' ); // Orders list > Label (Core, order-labels.php)
    $order->update_meta_data( '_ms_term_user', get_current_user_id() );
    $order->update_meta_data( '_ms_term_state', wp_generate_password( 24, false ) ); // comes back from Square unchanged: a return completes only this sale, once
    $order->update_meta_data( '_ms_term_sale', [ 'ids' => $sale['ids'], 'disc' => $sale['disc'], 'tip' => $calc['tip'], 'email' => is_email( $email ) ? $email : '', 'note' => $sale['note'] ] );
    if ( $sale['note'] !== '' ) $order->update_meta_data( '_ms_term_note', $sale['note'] );
    $order->calculate_totals( false ); // no tax line on in-person sales (yet: parked bookkeeping question)
    if ( abs( (float) $order->get_total() - $calc['total'] ) > 0.005 ) { // never charge a different amount than the screen says
        $order->add_order_note( sprintf( 'The total came to %s; the terminal says %s. Set to the terminal total.', wc_format_decimal( $order->get_total() ), wc_format_decimal( $calc['total'] ) ) );
        $order->set_total( $calc['total'] );
    }
    $order->save();

    $note = sprintf( 'In-person sale at the Market terminal. Listed %s', wp_strip_all_tags( wc_price( array_sum( wp_list_pluck( $calc['lines'], 'listed' ) ) ) ) );
    $auto = array_sum( wp_list_pluck( $calc['lines'], 'listed' ) ) - $calc['subtotal'];
    if ( $auto > 0.004 ) $note .= sprintf( ', automatic discount %s', wp_strip_all_tags( wc_price( $auto ) ) );
    if ( $calc['discount'] > 0 ) $note .= sprintf( ', discount on the spot %s (%s)', wp_strip_all_tags( wc_price( $calc['discount'] ) ), ms_term_discount_text( $sale['disc'] ) );
    if ( $calc['tip'] > 0 ) $note .= sprintf( ', tip %s', wp_strip_all_tags( wc_price( $calc['tip'] ) ) );
    $order->add_order_note( $note . sprintf( ', total %s, %s.', wp_strip_all_tags( wc_price( $calc['total'] ) ), $method ) );
    if ( $sale['note'] !== '' ) $order->add_order_note( 'Note from the sale: ' . $sale['note'] ); // a private note: the Order notes box on the order screen, never emailed

    foreach ( ms_term_order_products( $order ) as $id ) update_post_meta( $id, 'painting_availability_status', 'reserved' );
    ms_term_save_sale( [ 'ids' => [], 'disc' => [] ] );
    return $order;
}

/** "Sold. $80.00, Cash (order #123). Receipt sent to ..." - what the owner reads after a sale is paid (taken before it is completed) */
function ms_term_paid_message( $order ) {
    $mail = is_email( $order->get_billing_email() ) ? ' Receipt sent to ' . $order->get_billing_email() . '.' : '';
    return sprintf( 'Sold. %s, %s (order #%d).%s', wp_strip_all_tags( wc_price( $order->get_total() ) ), $order->get_payment_method(), $order->get_id(), $mail );
}

/**
 * The address that opens the Square Point of Sale app with this sale's total (Square's Point of Sale API, mobile web: a
 * square-commerce-v1:// address with the sale as JSON). '' until the Square application ID is set. Card (Tap to Pay or a reader)
 * only; Square adds no fees or taxes of its own (clear_default_fees); it switches back to the page by itself after a payment
 * (auto_return) and answers in the address (ms_term_handle_square_return()). The callback address must be the one registered
 * for the application in Square's developer console: the terminal page, exactly.
 */
function ms_term_square_url( $order ) {
    $app = ms_term_settings()['square_app_id'];
    $page = ms_term_page_url();
    if ( $app === '' || $page === '' ) return '';
    $state = (string) $order->get_meta( '_ms_term_state' );
    if ( $state === '' ) { // a sale made before the code existed
        $state = wp_generate_password( 24, false );
        $order->update_meta_data( '_ms_term_state', $state );
        $order->save();
    }
    $data = [
        'amount_money' => [ 'amount' => (string) (int) round( (float) $order->get_total() * 100 ), 'currency_code' => get_woocommerce_currency() ],
        'callback_url' => $page,
        'client_id'    => $app,
        'version'      => '1.3',
        'notes'        => ms_term_pay_note( $order ),
        'state'        => $state,
        'options'      => [ 'supported_tender_types' => [ 'CREDIT_CARD' ], 'clear_default_fees' => true, 'auto_return' => true ],
    ];
    return 'square-commerce-v1://payment/create?data=' . rawurlencode( wp_json_encode( $data ) );
}

/**
 * Square's answer (the "data" in the address when it switches back): status ok + transaction_id (absent when it was taken offline:
 * then client_transaction_id) or an error_code; "state" is the one-time code of the sale. Completes the waiting sale of this
 * user when the code matches, otherwise says what happened and changes nothing. Returns [ type, text ] for the page.
 */
function ms_term_handle_square_return( $d ) {
    $order  = ms_term_pending_order();
    $state  = is_array( $d ) ? (string) ( $d['state'] ?? '' ) : '';
    if ( ! $order || $state === '' || ! hash_equals( (string) $order->get_meta( '_ms_term_state' ), $state ) ) {
        return [ 'warn', 'Square answered, but no matching sale is waiting here (it may already be finished). Nothing was changed.' ];
    }
    $status = (string) ( $d['status'] ?? '' );
    if ( $status === 'ok' ) {
        $txn    = sanitize_text_field( (string) ( $d['transaction_id'] ?? '' ) );
        $client = sanitize_text_field( (string) ( $d['client_transaction_id'] ?? '' ) );
        if ( $txn !== '' ) $order->set_transaction_id( $txn );
        if ( $client !== '' ) $order->update_meta_data( '_ms_term_square_client_txn', $client );
        $order->add_order_note( $txn !== '' ? 'Paid in Square (transaction ' . $txn . ').' : 'Paid in Square, taken offline (device reference ' . $client . '); the transaction id comes later in Square.' );
        $message = ms_term_paid_message( $order );
        ms_term_finish_order( $order );
        return [ 'ok', $message ];
    }
    $why = [
        'payment_canceled'     => 'The payment was canceled in Square.',
        'no_network_connection' => 'Square had no connection.',
        'could_not_perform'    => 'Square could not take it: an earlier payment is probably still open in the Square app.',
        'not_logged_in'        => 'Square is not signed in.',
        'user_not_active'      => 'Card payments are not activated for this Square account.',
        'amount_too_small'     => 'The total is below Square\'s minimum.',
        'amount_too_large'     => 'The total is above Square\'s maximum.',
    ];
    $code = sanitize_key( (string) ( $d['error_code'] ?? '' ) );
    return [ 'error', ( $why[ $code ] ?? ( 'Square answered with an error' . ( $code !== '' ? ' (' . $code . ')' : '' ) . '.' ) ) . ' The sale is still waiting: open Square again, or go Back.' ];
}

/** Paid: complete the order (WooCommerce takes the stock and sends the emails), the pieces are Sold */
function ms_term_finish_order( $order ) {
    $order->delete_meta_data( '_ms_term_user' );
    $order->set_date_paid( time() );
    $order->update_status( 'completed', 'In-person sale paid.' );
    foreach ( ms_term_order_products( $order ) as $id ) update_post_meta( $id, 'painting_availability_status', 'sold' );
}

/** Not paid: free the pieces, put the list back as it was and delete the never-paid order */
function ms_term_drop_order( $order ) {
    $saved = $order->get_meta( '_ms_term_sale' );
    foreach ( ms_term_order_products( $order ) as $id ) {
        if ( get_post_meta( $id, 'painting_availability_status', true ) === 'reserved' ) update_post_meta( $id, 'painting_availability_status', 'available' );
    }
    if ( is_array( $saved ) ) ms_term_save_sale( [ 'ids' => (array) $saved['ids'], 'disc' => (array) $saved['disc'], 'tip' => (float) ( $saved['tip'] ?? 0 ), 'email' => (string) ( $saved['email'] ?? '' ), 'note' => (string) ( $saved['note'] ?? '' ) ] );
    $order->delete( true );
}

/* ------------------------------
   Today's sales and Undo
-------------------------------*/

/** Today's completed in-person sales (this terminal's and the Inventory Manager's), oldest first */
function ms_term_today_orders() {
    $start = (int) ( new DateTimeImmutable( 'today', wp_timezone() ) )->getTimestamp(); // midnight on the site's clock
    return wc_get_orders( [
        'limit' => -1, 'status' => 'completed', 'orderby' => 'date', 'order' => 'ASC',
        'date_created' => '>=' . $start,
        'meta_key' => 'order_label', 'meta_value' => 'in-person-sale',
    ] );
}

/** The piece numbers of an order, "#753 #752" */
function ms_term_order_numbers( $order ) {
    $numbers = [];
    foreach ( ms_term_order_products( $order ) as $id ) {
        $n = function_exists( 'monospace_sku_number' ) ? monospace_sku_number( get_post_meta( $id, '_sku', true ) ) : '';
        if ( $n !== '' ) $numbers[] = '#' . $n;
    }
    return implode( ' ', $numbers );
}

/**
 * Undo a sale: the order is cancelled (WooCommerce puts the stock back) and its pieces are Available again. The money is not
 * returned by this - that is done in the Square, Venmo or PayPal app.
 */
function ms_term_undo_order( $order ) {
    $order->update_status( 'cancelled', 'Market terminal: sale undone.' );
    foreach ( ms_term_order_products( $order ) as $id ) {
        if ( get_post_meta( $id, 'painting_availability_status', true ) === 'sold' ) update_post_meta( $id, 'painting_availability_status', 'available' );
    }
}

/* ------------------------------
   The receipt (WooCommerce's own "completed order" email, reworded for a sale at a market)
-------------------------------*/

function ms_term_is_terminal_order( $order ) {
    return $order instanceof WC_Order && $order->get_created_via() === 'ms-terminal';
}

add_filter( 'woocommerce_email_subject_customer_completed_order', function ( $subject, $order = null ) {
    return ms_term_is_terminal_order( $order ) ? sprintf( 'Your receipt from monospace ART (order #%s)', $order->get_order_number() ) : $subject;
}, 10, 2 );

add_filter( 'woocommerce_email_heading_customer_completed_order', function ( $heading, $order = null ) {
    return ms_term_is_terminal_order( $order ) ? 'Thank you!' : $heading;
}, 10, 2 );

// No billing address block ("N/A") on a receipt for a sale in person
add_action( 'woocommerce_email_customer_details', function ( $order ) {
    if ( ! ms_term_is_terminal_order( $order ) ) return;
    remove_action( 'woocommerce_email_customer_details', [ WC()->mailer(), 'email_addresses' ], 20 );
    add_action( 'woocommerce_email_customer_details', function () { add_action( 'woocommerce_email_customer_details', [ WC()->mailer(), 'email_addresses' ], 20, 3 ); }, 21 );
}, 1 );

/* ------------------------------
   The PayPal code's address: art.monospace.com/?pp=52.50 sends the customer on to paypal.me/<name>/52.50 (open to anyone with the
   code - it only ever leads to the owner's own PayPal.Me page, with an amount). No currency code on it: the account's own applies.
-------------------------------*/

function ms_term_paypal_bounce() {
    if ( ! isset( $_GET['pp'] ) || is_admin() || wp_doing_ajax() ) return;
    $amount = (string) wp_unslash( $_GET['pp'] );
    $name   = ms_term_settings()['paypal'];
    nocache_headers();
    header( 'X-Robots-Tag: noindex' );
    $email  = ms_term_settings()['paypal_email'];
    if ( ( $name === '' && ! is_email( $email ) ) || ! preg_match( '/^\d{1,5}(\.\d{1,2})?$/', $amount ) || (float) $amount <= 0 ) {
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }
    $amount = number_format( (float) $amount, 2, '.', '' );
    if ( is_email( $email ) ) {
        // PayPal's checkout link (a "Pay now" page for the owner's account): the PayPal phone app takes a paypal.me link over and shows the
        // profile without the amount (2026-11-14); this address is not one it claims
        $note = mb_substr( sanitize_text_field( wp_unslash( $_GET['n'] ?? 'monospace ART' ) ), 0, 80 );
        $url  = 'https://www.paypal.com/cgi-bin/webscr?' . http_build_query( [ 'cmd' => '_xclick', 'business' => $email, 'item_name' => $note, 'amount' => $amount, 'currency_code' => get_woocommerce_currency(), 'no_shipping' => 1, 'no_note' => 1 ], '', '&', PHP_QUERY_RFC3986 ); // (add_query_arg would leave the # of "#753" unencoded)
    } else {
        $url = 'https://paypal.me/' . rawurlencode( $name ) . '/' . $amount;
    }
    wp_redirect( $url, 302 ); // phpcs:ignore WordPress.Security.SafeRedirect -- always the owner's own PayPal account, with an amount
    exit;
}
ms_term_paypal_bounce(); // this module is loaded on init, before anything is sent

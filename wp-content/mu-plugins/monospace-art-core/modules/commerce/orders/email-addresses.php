<?php
/**
 * Which mailbox WooCommerce emails use (2026-11-10, owner: "Anything commissions related should use the commissions mailbox so it's all
 * kept in one place. Same with any non-commissions sales related email, it should use the sales mailbox. For account specific stuff, use
 * support@").
 *
 *   Commissions   the commission emails and any WooCommerce email about a commission order (deposit or balance): the commissions mailbox
 *   Account       password reset, new account, email verification: the support mailbox (a forwarder)
 *   Sales         every other email to a customer (order received, completed, refunded, a note from you, return label...): the sales mailbox
 *
 * What that changes: the From address of the emails to customers - and with it the address a reply goes to (WooCommerce's reply-to is the
 * From address). The name stays "monospace ART LLC" (WooCommerce > Settings > Emails). Emails to you are left as they were, except that
 * the order emails to you (New order, Cancelled, Failed) about a commission order go to the commissions mailbox, the rest to the
 * sales mailbox (their recipients are set under WooCommerce > Settings > Emails).
 * Addresses: options ms_mail_sales / ms_mail_support, and ms_commission_admin_email() (the commissions plugin) - or the filters
 * ms_mail_address ( $address, $kind ), kinds: sales, support, commissions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** The mailbox of a kind: 'sales' | 'support' | 'commissions' */
function ms_mail_address( $kind ) {
    $defaults = [ 'sales' => 'sales@monospace.com', 'support' => 'support@monospace.com', 'commissions' => 'commissions@monospace.com' ];
    if ( $kind === 'commissions' && function_exists( 'ms_commission_admin_email' ) ) {
        $address = ms_commission_admin_email();
    } else {
        $address = get_option( 'ms_mail_' . $kind ) ?: ( $defaults[ $kind ] ?? $defaults['sales'] );
    }
    return apply_filters( 'ms_mail_address', is_email( $address ) ? $address : $defaults['sales'], $kind );
}

/** Is this order a commission (a deposit order or a balance order)? Only when the commissions plugin is on */
function ms_mail_is_commission_order( $order ) {
    if ( ! $order instanceof WC_Order ) return false;
    if ( function_exists( 'ms_commission_order_items' ) && ms_commission_order_items( $order ) ) return true;
    return function_exists( 'ms_commission_final_items' ) && ms_commission_final_items( $order );
}

/** The kind of mailbox for one email: 'commissions' | 'support' | 'sales' */
function ms_mail_kind( $email ) {
    if ( ! is_object( $email ) ) return 'sales';
    $id = (string) ( $email->id ?? '' );
    if ( strpos( $id, 'commission' ) !== false || ms_mail_is_commission_order( $email->object ?? null ) ) return 'commissions';
    if ( in_array( $id, [ 'customer_reset_password', 'customer_new_account', 'customer_verify_email' ], true ) ) return 'support';
    return 'sales';
}

// The From address (and so the reply-to) of the emails to customers
add_filter( 'woocommerce_email_from_address', function ( $address, $email = null ) {
    if ( ! $email instanceof WC_Email || ! $email->is_customer_email() ) return $address;
    return ms_mail_address( ms_mail_kind( $email ) );
}, 20, 2 );

// The order emails to you: a commission order's go to the commissions mailbox, the rest keep their recipient (the sales mailbox)
foreach ( [ 'new_order', 'cancelled_order', 'failed_order' ] as $ms_mail_id ) {
    add_filter( 'woocommerce_email_recipient_' . $ms_mail_id, function ( $recipient, $order = null ) {
        return ms_mail_is_commission_order( $order ) ? ms_mail_address( 'commissions' ) : $recipient;
    }, 20, 2 );
}
unset( $ms_mail_id );

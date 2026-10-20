<?php
/**
 * Printful API client for the theme's print tools (create-prints.php).
 *
 * Uses its own private token, option monospace_printful_token (scopes: store products + files,
 * read and write - created 2026-10-15 at developers.printful.com, stored with
 * `wp option update monospace_printful_token … --autoload=no`). The WooCommerce plugin's own key
 * (woocommerce_printful_settings) can only read products, so it is used for the store ID only.
 *
 * The store is a WooCommerce-type store: products can't be created through the API. A product made
 * in WooCommerce reaches Printful through the "product.updated" webhook, and only while "Import not
 * synced products from WooCommerce" is on in Printful (My products). Its variants are then linked
 * with PUT /sync/variant/@<WooCommerce variation ID>.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Token and store present? */
function monospace_printful_ready() {
    $settings = get_option( 'woocommerce_printful_settings' );
    return get_option( 'monospace_printful_token' ) && ! empty( $settings['printful_store_id'] );
}

/**
 * One API call: [ 'ok' => bool, 'code' => int, 'result' => mixed, 'error' => string ].
 * $endpoint starts with "/" (e.g. "/sync/products/@123").
 */
function monospace_printful( $method, $endpoint, $body = null, $timeout = 40 ) {
    $settings = get_option( 'woocommerce_printful_settings' );
    $args = [
        'method'  => $method,
        'timeout' => $timeout,
        'headers' => [
            'Authorization' => 'Bearer ' . get_option( 'monospace_printful_token' ),
            'X-PF-Store-Id' => $settings['printful_store_id'] ?? '',
            'Content-Type'  => 'application/json',
        ],
    ];
    if ( $body !== null ) $args['body'] = wp_json_encode( $body );

    $response = wp_remote_request( 'https://api.printful.com' . $endpoint, $args );
    if ( is_wp_error( $response ) ) {
        return [ 'ok' => false, 'code' => 0, 'result' => null, 'error' => $response->get_error_message() ];
    }
    $json = json_decode( wp_remote_retrieve_body( $response ), true );
    $code = (int) ( $json['code'] ?? wp_remote_retrieve_response_code( $response ) );
    $ok   = $code >= 200 && $code < 300;
    $error = $ok ? '' : ( is_string( $json['result'] ?? null ) ? $json['result'] : ( $json['error']['message'] ?? 'HTTP ' . $code ) );
    return [ 'ok' => $ok, 'code' => $code, 'result' => $json['result'] ?? null, 'error' => $error ];
}

/** A Printful catalog product's description (the text the manual prints carry), cached a week */
function monospace_printful_catalog_description( $catalog_product_id ) {
    $key  = 'ms_pf_desc_' . (int) $catalog_product_id;
    $text = get_transient( $key );
    if ( $text === false ) {
        $r = monospace_printful( 'GET', '/products/' . (int) $catalog_product_id );
        $text = $r['ok'] ? (string) ( $r['result']['product']['description'] ?? '' ) : '';
        if ( $r['ok'] ) set_transient( $key, $text, WEEK_IN_SECONDS );
    }
    return $text;
}

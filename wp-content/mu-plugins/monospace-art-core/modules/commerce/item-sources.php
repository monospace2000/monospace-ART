<?php
/**
 * Item sources: which monospace property a cart item comes from (Sightings today, other sites later).
 *
 * The art site's own artworks and prints carry no label. A product sold here for another property gets a
 * "From: <property>" line under its name in the cart, the mini-cart and checkout (classic and block), and the
 * same line on the order: thank-you page, My Account, order emails and the admin order screen.
 *
 * A property registers itself with the monospace_item_sources filter (see sp-checkout.php for Sightings):
 *   $sources['sightings'] = [
 *       'label' => 'Sightings',                          // what the line says
 *       'url'   => 'https://sightings.monospace.com/',   // optional: the label links there in the cart
 *       'match' => fn( $product_id ) => bool,            // is this product one of ours?
 *   ];
 *
 * The source is written on each new order line (hidden meta _ms_source = the key), so an order keeps saying where an
 * item came from even if the product changes or the property's plugin is switched off. Orders placed before this
 * module have no _ms_source and look exactly as they did.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Every registered source: key => [ label, url, match ] */
function monospace_item_sources() {
    static $sources = null;
    if ( $sources === null ) {
        $sources = [];
        foreach ( (array) apply_filters( 'monospace_item_sources', [] ) as $key => $s ) {
            if ( ! empty( $s['label'] ) && isset( $s['match'] ) && is_callable( $s['match'] ) ) $sources[ sanitize_key( $key ) ] = $s;
        }
    }
    return $sources;
}

/** The source key of a product ('' = the art site's own), cached per request */
function monospace_item_source( $product_id ) {
    static $seen = [];
    $product_id = (int) $product_id;
    if ( ! $product_id ) return '';
    if ( ! isset( $seen[ $product_id ] ) ) {
        $seen[ $product_id ] = '';
        foreach ( monospace_item_sources() as $key => $s ) {
            if ( call_user_func( $s['match'], $product_id ) ) { $seen[ $product_id ] = $key; break; }
        }
    }
    return $seen[ $product_id ];
}

/** The words for a source key, or '' (an unknown key from an old order still reads sensibly) */
function monospace_item_source_label( $key ) {
    $sources = monospace_item_sources();
    return isset( $sources[ $key ] ) ? (string) $sources[ $key ]['label'] : ( $key ? ucwords( str_replace( [ '-', '_' ], ' ', $key ) ) : '' );
}

// Cart, mini-cart, checkout (classic and block: the Store API sends item_data too)
add_filter( 'woocommerce_get_item_data', function ( $data, $cart_item ) {
    $key = monospace_item_source( $cart_item['product_id'] ?? 0 );
    if ( ! $key ) return $data;
    $s       = monospace_item_sources()[ $key ];
    $label   = esc_html( $s['label'] );
    $data[]  = [
        'key'     => 'From',
        'value'   => $s['label'],
        'display' => ! empty( $s['url'] ) && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST )
            ? '<a class="ms-item-source" href="' . esc_url( $s['url'] ) . '">' . $label . '</a>'
            : $label,
    ];
    return $data;
}, 20, 2 );

// New orders: remember the source on the line
add_action( 'woocommerce_checkout_create_order_line_item', function ( $item, $cart_key, $values ) {
    $key = monospace_item_source( $values['product_id'] ?? 0 );
    if ( $key ) $item->add_meta_data( '_ms_source', $key, true );
}, 20, 3 );

// Orders (thank-you page, My Account, emails, admin): show the hidden source as a "From" line
add_filter( 'woocommerce_order_item_get_formatted_meta_data', function ( $meta, $item ) {
    $key = is_callable( [ $item, 'get_meta' ] ) ? (string) $item->get_meta( '_ms_source', true ) : '';
    if ( ! $key ) return $meta;
    $label = monospace_item_source_label( $key );
    $meta[ 'ms_source_' . $item->get_id() ] = (object) [
        'key'           => '_ms_source',
        'value'         => $key,
        'display_key'   => 'From',
        'display_value' => esc_html( $label ),
    ];
    return $meta;
}, 20, 2 );

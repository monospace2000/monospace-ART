<?php
/**
 * The two print products the shop offers, as the manually made ones are built (2026-10-15):
 *   unframed = Printful "Enhanced Matte Paper Poster (in)" (catalog product 1), sizes 5x7 / 8x10 / 11x14
 *   framed   = "Enhanced Matte Paper Framed Poster (in)" (catalog product 2), Black / Red Oak / White
 *              in 8x10 and 11x14
 * Each variant: the WooCommerce attribute terms (the existing pa_size / pa_color terms Printful
 * created - never rename them, Printful matches them by name), the Printful catalog variant and the
 * default price (the owner's prices). Sizes are short side × long side in inches; the painting's
 * orientation decides how they're shown and which mockup is made.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Start of every print product's name (and so its variations'), e.g. "Print: Bird Box, DeKorte Park" */
const MONOSPACE_PRINT_NAME_PREFIX = 'Print: ';

function monospace_print_templates() {
    $frames = [ 'black' => 'Black', 'red-oak' => 'Red Oak', 'white' => 'White' ];
    $framed = [];
    $sizes = [ [ '8x10', '8x10-2', [ 8, 10 ], 50, [ 'black' => 4651, 'red-oak' => 15021, 'white' => 10754 ] ],
               [ '11x14', '11x14-2', [ 11, 14 ], 60, [ 'black' => 14292, 'red-oak' => 15023, 'white' => 14293 ] ] ];
    foreach ( $frames as $color => $label ) { // grouped by frame: Black 8x10, Black 11x14, Red Oak 8x10, …
        foreach ( $sizes as [ $size, $term, $inches, $price, $catalog ] ) {
            $framed[ $color . '-' . $size ] = [
                'label' => "$label, $size", 'size' => $size, 'inches' => $inches, 'price' => $price,
                'attributes' => [ 'pa_color' => $color, 'pa_size' => $term ], 'catalog' => $catalog[ $color ],
            ];
        }
    }
    $templates = [
        'unframed' => [
            'label'           => 'Unframed print',
            'meta_key'        => '_print_product_id',
            'catalog_product' => 1,
            'attributes'      => [ 'pa_size' ],
            'variants'        => [
                '5x7'   => [ 'label' => '5x7',   'size' => '5x7',   'inches' => [ 5, 7 ],   'price' => 20, 'attributes' => [ 'pa_size' => '5x7-2' ],   'catalog' => 16364 ],
                '8x10'  => [ 'label' => '8x10',  'size' => '8x10',  'inches' => [ 8, 10 ],  'price' => 30, 'attributes' => [ 'pa_size' => '8x10-2' ],  'catalog' => 4463 ],
                '11x14' => [ 'label' => '11x14', 'size' => '11x14', 'inches' => [ 11, 14 ], 'price' => 40, 'attributes' => [ 'pa_size' => '11x14-2' ], 'catalog' => 14125 ],
            ],
        ],
        'framed' => [
            'label'           => 'Framed print',
            'meta_key'        => '_framed_print_product_id',
            'catalog_product' => 2,
            'attributes'      => [ 'pa_color', 'pa_size' ],
            'variants'        => $framed,
        ],
    ];

    // Default prices saved on Prints > New prints replace the ones above (new prints only)
    foreach ( monospace_print_default_prices() as $type => $prices ) {
        foreach ( $prices as $key => $price ) {
            if ( isset( $templates[ $type ]['variants'][ $key ] ) ) $templates[ $type ]['variants'][ $key ]['price'] = $price;
        }
    }
    return $templates;
}

/** Saved default prices: [ type => [ variant key => price ] ] (option monospace_print_default_prices) */
function monospace_print_default_prices() {
    return array_filter( (array) get_option( 'monospace_print_default_prices', [] ), 'is_array' );
}

/** The template variant a WooCommerce variation matches (by its attribute terms), or null */
function monospace_print_variant_for( $type, $variation ) {
    $attrs = $variation->get_attributes();
    foreach ( monospace_print_templates()[ $type ]['variants'] as $key => $v ) {
        $match = true;
        foreach ( $v['attributes'] as $tax => $slug ) {
            if ( ( $attrs[ $tax ] ?? '' ) !== $slug ) { $match = false; break; }
        }
        if ( $match ) return [ 'key' => $key ] + $v;
    }
    return null;
}

/** Which template a WooCommerce print product follows: framed if it has frame colors */
function monospace_print_type_of( $product ) {
    return isset( $product->get_attributes()['pa_color'] ) ? 'framed' : 'unframed';
}

/** An image's pixel size: [ width, height ] of the original upload (not WordPress's -scaled copy) */
function monospace_print_image_size( $attachment_id ) {
    $path = wp_get_original_image_path( $attachment_id );
    $size = $path && file_exists( $path ) ? @getimagesize( $path ) : false;
    if ( $size ) return [ (int) $size[0], (int) $size[1] ];
    $meta = wp_get_attachment_metadata( $attachment_id );
    return [ (int) ( $meta['width'] ?? 0 ), (int) ( $meta['height'] ?? 0 ) ];
}

/** dpi of an image printed at [ short, long ] inches (sides matched to the image's orientation) */
function monospace_print_dpi( $px, $inches ) {
    if ( ! $px[0] || ! $px[1] ) return 0;
    return (int) floor( min( min( $px ) / $inches[0], max( $px ) / $inches[1] ) );
}

/**
 * dpi as Printful rates it: [ label, color ]. Printful calls ~136 dpi "good"; ours used to say
 * "low" under 150 - the owner noticed the mismatch (2026-10-15).
 */
function monospace_print_dpi_rating( $dpi ) {
    if ( $dpi >= 150 ) return [ 'great', '#1a7f37' ];
    if ( $dpi >= 100 ) return [ 'good', '#1a7f37' ];
    if ( $dpi >= 75 )  return [ 'low', '#9a6700' ];
    return [ 'too low', '#b32d2e' ];
}

/** Share of the image trimmed off the edges at that size (the print fills the paper), 0..1 */
function monospace_print_crop( $px, $inches ) {
    if ( ! $px[0] || ! $px[1] ) return 0;
    $image = min( $px ) / max( $px );
    $paper = $inches[0] / $inches[1];
    return round( 1 - min( $image, $paper ) / max( $image, $paper ), 3 );
}

<?php
/**
 * Custom Order card (2026-10-29, owner: "a new Custom Order card ... take the product image and the short description from
 * the product, plus size, materials, etc. Button text should be a parameter, Add to Cart being the default" - for a
 * custom portrait agreed in conversation: make the product, then a page for that person with this card on it).
 *
 *   [custom_order_card id="3745"]                       the product's picture, title, short description, medium / surface / size,
 *                                                       the price and the upfront payment, and a button to the order page
 *   [custom_order_card slug="custom-artwork-paint"]     the same, the product found by its slug
 *   button="Add to Cart"   the button's text (the default)      title="yes | no"      image="yes | no"
 *   note="..."   a message in the bottom row (sales, announcements, notes); the filter ms_commission_card_offer fills it too
 *   layout="side | stacked"   picture beside the details (a row on top on phones) or always on top
 *
 * Everything comes from the product, so a change there shows on every page that has the card: the picture (featured image),
 * the short description, and the "Custom Order" settings box (medium, surface, size - ms_render_commission_product_meta_box()).
 * The button opens the product's page: that is where the reference photos and notes are added (a commission can't be
 * bought without them - commissions-core.php), so the card has no add-to-cart of its own. A product page only opens for a
 * visitor when its slug starts with custom-artwork- (product-redirects.php); the card tells an administrator when it doesn't.
 * Looks like the standalone price card (.pc-standalone, _components.scss) - its own bits: .ms-order-card.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The product behind [custom_order_card id="" / slug=""], or null */
function ms_commission_card_product( array $atts ) {
    $id = ctype_digit( (string) ( $atts['id'] ?? '' ) ) ? (int) $atts['id'] : 0;
    if ( ! $id && ! empty( $atts['slug'] ) ) {
        $post = get_page_by_path( sanitize_title( $atts['slug'] ), OBJECT, 'product' );
        $id   = $post ? (int) $post->ID : 0;
    }
    $product = $id ? wc_get_product( $id ) : null;
    if ( ! $product ) return null;
    // A draft or private product is only for those who can edit it (to look at the page before it goes out)
    if ( $product->get_status() !== 'publish' && ! current_user_can( 'edit_post', $id ) ) return null;
    return $product;
}

/** "Medium: Acrylic" style lines for the product's allowed mediums, surfaces and sizes ('' lines left out) */
function ms_commission_card_details( $product_id ) {
    $options = ms_get_commission_options();
    $lines   = [];
    foreach ( [ 'Medium' => [ '_commission_allowed_mediums', 'mediums' ], 'Surface' => [ '_commission_allowed_surfaces', 'surfaces' ], 'Size' => [ '_commission_allowed_sizes', 'sizes' ] ] as $label => [ $meta, $set ] ) {
        $keys = array_filter( (array) get_post_meta( $product_id, $meta, true ) );
        if ( ! $keys ) continue;
        $names = array_map( fn( $k ) => $options[ $set ][ $k ] ?? $k, $keys );
        $lines[] = '<div class="painting-attr"><b>' . esc_html( $label ) . '</b><span class="pa-sep">: </span>' . esc_html( implode( ', ', $names ) ) . '</div>';
    }
    return implode( '', $lines );
}

add_shortcode( 'custom_order_card', function ( $atts ) {
    $atts = shortcode_atts( [ 'id' => '', 'slug' => '', 'button' => 'Add to Cart', 'title' => 'yes', 'image' => 'yes', 'layout' => 'side', 'note' => '' ], (array) $atts, 'custom_order_card' );

    $product = ms_commission_card_product( $atts );
    if ( ! $product ) {
        return current_user_can( 'edit_pages' )
            ? '<div class="painting-buy-placeholder" style="padding:6px;border:1px dashed #c00;background:#f9f9f9;">Custom Order card: no product with id / slug "' . esc_html( $atts['id'] . $atts['slug'] ) . '"</div>'
            : '';
    }
    $pid = $product->get_id();

    $yes     = fn( $v ) => strtolower( (string) $v ) !== 'no';
    $stacked = strtolower( (string) $atts['layout'] ) === 'stacked';
    $url     = get_permalink( $pid );
    $name    = esc_html( $product->get_name() );

    // The picture: the product's featured image, in its own shape (as the standalone price card)
    $media = '';
    $thumb = $yes( $atts['image'] ) ? (int) $product->get_image_id() : 0;
    $src   = $thumb ? wp_get_attachment_image_src( $thumb, 'large' ) : null;
    if ( $src ) {
        $img = sprintf( '<img class="pc-sa-img" src="%s" srcset="%s" sizes="(max-width: 640px) 100vw, 420px" width="%d" height="%d" alt="%s" loading="lazy" decoding="async">',
            esc_url( $src[0] ), esc_attr( (string) wp_get_attachment_image_srcset( $thumb, 'large' ) ), (int) $src[1], (int) $src[2], esc_attr( $product->get_name() ) );
        $ratio = $src[2] ? $src[1] / $src[2] : 1;
        $box   = sprintf( 'aspect-ratio:%d / %d;max-width:min(100%%, calc(70vh * %s))', $src[1], $src[2], round( $ratio, 4 ) );
        $media = '<div class="pc-sa-media" style="' . esc_attr( $box ) . '"><a href="' . esc_url( $url ) . '" class="pc-sa-media-link">' . $img . '</a></div>';
    }

    $title = $yes( $atts['title'] ) ? '<h3 class="pc-sa-title"><a href="' . esc_url( $url ) . '">' . $name . '</a></h3>' : '';

    // The short description (beside the picture), then medium / surface / size (under the picture)
    $short   = trim( (string) $product->get_short_description() );
    $desc    = $short !== '' ? '<div class="ms-order-desc">' . wp_kses_post( wpautop( $short ) ) . '</div>' : '';
    $details = ms_commission_card_details( $pid );

    // Price and the upfront payment (as the order page shows them) sit beside the details, the button on its own row under them
    $price    = (float) $product->get_price();
    $percent  = (float) ( get_post_meta( $pid, '_commission_deposit_percent', true ) ?: 30 );
    $price_html = '';
    if ( $price > 0 ) {
        $price_html .= '<div class="painting-price">' . $product->get_price_html() . '</div>';
        if ( ms_is_commission_product( $pid ) && $percent > 0 && $percent < 100 ) {
            $price_html .= '<div class="ms-order-deposit">Upfront payment ' . wc_price( $price * $percent / 100 ) . ' (' . (int) $percent . '%)</div>';
        }
    }
    $buy = '<a class="painting-buy-button" href="' . esc_url( $url ) . '">' . esc_html( $atts['button'] !== '' ? $atts['button'] : 'Add to Cart' ) . '</a>';

    // The bottom row is kept for sales, announcements and notes: note="..." (a message strip, as the price card's), and the
    // filter for a feature to add its own (an offer from the Sales Dashboard, an announcement) - the row is left out when empty
    $offer = trim( (string) $atts['note'] ) !== '' && function_exists( 'monospace_render_card_message' ) ? monospace_render_card_message( trim( (string) $atts['note'] ), 'gray' ) : '';
    $offer = (string) apply_filters( 'ms_commission_card_offer', $offer, $product );

    // For an administrator: the order page only opens for a visitor with a custom-artwork-… slug
    $note = '';
    if ( current_user_can( 'manage_options' ) && function_exists( 'monospace_wc_is_exempt_product' ) && ! monospace_wc_is_exempt_product( $pid ) ) {
        $note = '<div class="painting-buy-placeholder" style="padding:6px;border:1px dashed #c00;background:#f9f9f9;font-size:.8em;">Only you see this: the slug "' . esc_html( get_post_field( 'post_name', $pid ) ) . '" does not start with custom-artwork-, so visitors are redirected away from the button\'s page.</div>';
    }

    // Three rows, two columns: title / picture + description / details + price, then the button on its own row, right, then the offer / note row (when there is one) (.ms-order-card, _components.scss)
    return sprintf( '<div class="pc-standalone ms-order-card pc-sa-%s%s%s">%s%s%s%s%s<div class="ms-order-buy">%s</div>%s%s</div>',
        $stacked ? 'stacked' : 'side', $media ? '' : ' pc-sa-no-image', $offer !== '' ? ' has-offer' : '', $title, $media, $desc,
        $details !== '' ? '<div class="ms-order-details">' . $details . '</div>' : '',
        $price_html !== '' ? '<div class="ms-order-price">' . $price_html . '</div>' : '', $buy,
        $offer !== '' ? '<div class="ms-order-offer">' . $offer . '</div>' : '', $note );
} );

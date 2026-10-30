<?php
/**
 * The artwork row (2026-10-23, owner: "image preview on the left, pertinent info on the right - for search results, the
 * Art Feed, other areas"). One artwork as a row: its picture, and beside it the title and the info lines.
 *
 *   monospace_artwork_info_html( $post, $product, $mini_suffix )  the three info lines - the list view uses them too
 *   monospace_artwork_row_html( $post_id )   the row for an artwork post ('' for anything else)
 *   monospace_post_row_html( $post_id, $chars )  the same row for any other post or page: its picture (featured image,
 *                                            else the first one in it),  title and the first $chars characters of its text
 *
 * Used by the "Detail list" view - the theme's archive toolbar, search results (archive-view-toggle.php) and the Art
 * Filter. The look is the theme's (.ms-artwork-row, _archives.scss); this only draws the markup. The picture's link is
 * an .ms-archive-image-link, so Look > Shadows & outlines, Rounding and Rollover effect apply to it as to feed pictures.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The site's placeholder picture - shown wherever a post, artwork or product has no picture of its own (2026-10-23, owner: "a place
 * where I can select a placeholder image for the whole site"). The one chosen in Appearance > Look > Placeholder picture (option
 * monospace_placeholder_image, an attachment ID), else the media library file art_placeholder.png. Its ID, 0 when there is neither.
 */
function monospace_placeholder_image_id() {
    $chosen = (int) get_option( 'monospace_placeholder_image', 0 );
    if ( $chosen && wp_attachment_is_image( $chosen ) ) return $chosen;
    $id = wp_cache_get( 'placeholder_image_id', 'monospace' );
    if ( $id === false ) {
        global $wpdb;
        $id = (int) $wpdb->get_var( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE '%art\\_placeholder.%' ORDER BY post_id DESC LIMIT 1" );
        wp_cache_set( 'placeholder_image_id', $id, 'monospace', HOUR_IN_SECONDS );
    }
    return (int) $id;
}

// WooCommerce's own placeholder (products with no picture: shop lists, the cart, emails, the admin) is this one too
add_filter( 'pre_option_woocommerce_placeholder_image', function ( $pre ) {
    $id = monospace_placeholder_image_id();
    return $id ?: $pre;
} );

/**
 * The placeholder's address carries its file's save time (?v=...): static files are cached by browsers and the CDN for a year under
 * the same address, so a placeholder replaced in the media library (same file name) would keep showing its old picture.
 */
function monospace_placeholder_version() {
    static $v = null;
    if ( $v === null ) {
        $id   = monospace_placeholder_image_id();
        $file = $id ? get_attached_file( $id ) : '';
        $v    = $file && file_exists( $file ) ? (int) filemtime( $file ) : 0;
    }
    return $v;
}

add_filter( 'wp_get_attachment_image_src', function ( $image, $id ) {
    if ( $image && (int) $id === monospace_placeholder_image_id() && ( $v = monospace_placeholder_version() ) ) $image[0] = add_query_arg( 'v', $v, $image[0] );
    return $image;
}, 10, 2 );

add_filter( 'wp_calculate_image_srcset', function ( $sources, $size_array, $src, $meta, $id ) {
    if ( is_array( $sources ) && (int) $id === monospace_placeholder_image_id() && ( $v = monospace_placeholder_version() ) ) {
        foreach ( $sources as $width => $source ) $sources[ $width ]['url'] = add_query_arg( 'v', $v, $source['url'] );
    }
    return $sources;
}, 10, 5 );

/**
 * The picture a post stands for in a grid, a detail row, a card: its featured image, else the first picture in its text - a 2- or
 * 3-piece set (diptych, triptych) shows only its first, whether that is an ordinary image or the first of a Miniature Set block -,
 * else the art placeholder (2026-10-23, owner: "all posts in an archive view have a thumbnail"). 0 only when there is no placeholder either.
 */
function monospace_row_image_id( $post_id ) {
    $id = (int) get_post_thumbnail_id( $post_id );
    if ( $id ) return $id;
    $content = (string) get_post_field( 'post_content', $post_id );
    $best = 0;
    $pos  = PHP_INT_MAX;
    if ( preg_match( '/wp-image-(\d+)/', $content, $m, PREG_OFFSET_CAPTURE ) ) { $best = (int) $m[1][0]; $pos = $m[1][1]; }
    if ( preg_match( '/wp:monospace\/miniature-set\s*\{[^}]*?"id":(\d+)/', $content, $m, PREG_OFFSET_CAPTURE ) && $m[0][1] < $pos ) $best = (int) $m[1][0];
    return $best ?: monospace_placeholder_image_id();
}

/**
 * A post's thumbnail <img>, wherever one is needed (2026-10-23, owner: "anywhere a thumbnail is needed but missing"): its featured
 * image, else the first picture in it, else the art placeholder - monospace_row_image_id(). '' only when there is no placeholder either.
 */
function monospace_thumbnail_html( $post_id, $size = 'medium', $attr = [] ) {
    $id = monospace_row_image_id( $post_id );
    return $id ? wp_get_attachment_image( $id, $size, false, $attr ) : '';
}

/** The picture's <img> (cropped square by the theme's CSS: same width, same shape in every row), '' when there is none */
function monospace_row_thumb( $post_id ) {
    $id = monospace_row_image_id( $post_id );
    return $id ? wp_get_attachment_image( $id, 'medium_large', false, [
        'sizes' => '(max-width: 600px) 110px, 180px', 'alt' => get_the_title( $post_id ), 'loading' => 'lazy', 'decoding' => 'async',
    ] ) : '';
}

/** Is this post a miniature - or a miniature set (diptych, triptych)? 4" × 4" and its 2- / 3-piece sets, or the miniature category */
function monospace_is_miniature( $post_id ) {
    if ( has_category( 'miniature', $post_id ) ) return true;
    $pid = function_exists( 'monospace_get_post_product_id' ) ? (int) monospace_get_post_product_id( $post_id ) : 0;
    return $pid && defined( 'MONOSPACE_MINIATURE_SIZES' ) && array_intersect( (array) wp_get_post_terms( $pid, 'pa_size', [ 'fields' => 'slugs' ] ), MONOSPACE_MINIATURE_SIZES );
}

/** The small "Miniature" tag. Beside the dimensions in the info lines; beside the title of a post that has no info lines. */
function monospace_row_miniature_tag( $post_id ) {
    return monospace_is_miniature( $post_id ) ? '&nbsp;<span class="ms-info-mini">Miniature</span>' : ''; // &nbsp;: stays with the word before it
}

/** The first $chars characters of a post's text (its excerpt, else its content), cut at a word, '' when it has none */
function monospace_post_row_text( $post_id, $chars ) {
    $post = get_post( $post_id );
    if ( ! $post || $chars < 1 || post_password_required( $post ) ) return '';
    $text = $post->post_excerpt !== '' ? $post->post_excerpt : $post->post_content;
    $text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( excerpt_remove_blocks( $text ) ) ) ) );
    $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
    if ( mb_strlen( $text ) <= $chars ) return esc_html( $text );
    $cut = mb_substr( $text, 0, $chars );
    if ( ( $space = mb_strrpos( $cut, ' ' ) ) !== false && $space > $chars * 0.6 ) $cut = mb_substr( $cut, 0, $space );
    return esc_html( rtrim( $cut, " ,.;:-–—" ) ) . '…';
}

function monospace_post_row_html( $post_id, $chars = 200 ) {
    $post_id = (int) $post_id;
    if ( get_post_status( $post_id ) !== 'publish' || in_array( get_post_type( $post_id ), [ 'product', 'attachment' ], true ) ) return '';
    $url   = esc_url( get_permalink( $post_id ) );
    $title = esc_html( get_the_title( $post_id ) );

    $thumb = monospace_row_thumb( $post_id );
    $text = monospace_post_row_text( $post_id, (int) $chars );

    return '<div class="ms-artwork-row ms-artwork-row-post' . ( $thumb ? '' : ' ms-artwork-row-no-pic' ) . '">'
        . ( $thumb ? '<a class="ms-archive-image-link ms-artwork-row-pic" href="' . $url . '" tabindex="-1" aria-hidden="true">' . $thumb . '</a>' : '' )
        . '<div class="ms-artwork-row-text">'
        . ( $title !== '' ? '<h3 class="ms-artwork-row-title"><a href="' . $url . '">' . $title . '</a>' . monospace_row_miniature_tag( $post_id ) . '</h3>' : '' )
        . ( get_post_type( $post_id ) === 'post' ? '<div class="ms-artwork-row-meta"><span class="ms-artwork-row-date">' . esc_html( get_the_date( '', $post_id ) ) . '</span></div>' : '' )
        . ( $text !== '' ? '<p class="ms-artwork-row-excerpt">' . $text . ( $title === '' ? ' <a href="' . $url . '">Read more</a>' : '' ) . '</p>' : '' )
        . '</div></div>';
}

/**
 * The info under an artwork's title - the same three lines in the list view (under the caption) and the detail list
 * (2026-10-23, owner: "the info should be formatted the same on both"), all in the UI font (_archives.scss .ms-info):
 *   Medium on surface, dimensions
 *   Availability #number  price (the price only while it is for sale)
 *   Prints available
 * The availability is the price card's wording and colors (monospace_render_buy_button()); the number is the SKU's, small and
 * quiet; "Prints available" is the archive toolbar's test (a linked print product, published and in stock). A miniature gets
 * the small "Miniature" tag right after its dimensions (owner, 2026-10-23). $lines picks which of them to draw - 'made',
 * 'status', 'prints' (the [artwork] shortcode's info / availability / prints options); the rows draw all three.
 */
function monospace_artwork_info_html( $post_id, $product, $lines = [ 'made', 'status', 'prints' ] ) {
    $post_id = (int) $post_id;
    $pid     = $product->get_id();
    $names   = function ( $tax ) use ( $pid ) {
        $n = wp_get_post_terms( $pid, $tax, [ 'fields' => 'names' ] );
        return is_wp_error( $n ) ? '' : implode( ', ', array_diff( $n, $tax === 'pa_size' ? [ 'Other' ] : [] ) ); // "Other" says nothing
    };

    // Line 1: "Casein on paper, 10" × 8"" - each part never breaks inside; the line wraps only at the comma
    $medium  = $names( 'pa_medium' );
    $surface = $names( 'pa_surface' );
    $size    = $names( 'pa_size' );
    $out     = [];
    $made    = [];
    if ( $medium || $surface ) {
        $surface_lc = preg_match( '/^[A-Z]{2}/', $surface ) ? $surface : lcfirst( $surface ); // "Paper" -> "paper", "MDF board" stays
        $made[] = $medium && $surface ? $medium . ' on ' . $surface_lc : ( $medium ?: $surface );
    }
    if ( $size ) $made[] = $size;
    if ( $made && in_array( 'made', $lines, true ) ) {
        // The tag belongs to the last part (the dimensions, else the material) so they wrap together
        $tag   = monospace_row_miniature_tag( $post_id );
        $parts = array_map( fn( $m ) => esc_html( $m ), $made );
        $parts[ count( $parts ) - 1 ] .= $tag;
        $out[] = '<div class="ms-info-made">' . implode( ', ', array_map( fn( $m ) => '<span class="ms-info-part">' . $m . '</span>', $parts ) ) . '</div>';
    }

    // Line 2: availability, the SKU's number, the price while it can be bought
    if ( in_array( 'status', $lines, true ) ) {
        $status = monospace_artwork_status( $pid );
        $avail  = monospace_render_buy_button( $product, $status, get_post_meta( $pid, 'painting_gallery_url', true ), get_post_meta( $pid, 'painting_gallery_name', true ) );
        if ( $avail === '' ) $avail = '<span class="status-label status-available">Original available</span>';
        $number = function_exists( 'monospace_sku_number' ) ? monospace_sku_number( $product->get_sku() ) : '';
        $price  = $status === 'available' ? (float) $product->get_price() : 0;
        $out[] = '<div class="ms-info-status">' . $avail
            . ( $number !== '' ? '&nbsp;<span class="ms-info-number">#' . esc_html( $number ) . '</span>' : '' )
            . ( $price > 0 ? ' <span class="ms-info-price">' . wp_kses_post( wc_price( $price ) ) . '</span>' : '' ) . '</div>';
    }

    // Line 3
    if ( in_array( 'prints', $lines, true ) && function_exists( 'monospace_prints_for_sale_post_ids' ) && in_array( $post_id, monospace_prints_for_sale_post_ids(), true ) ) $out[] = '<div class="ms-info-prints">Prints available</div>';

    return $out ? '<div class="ms-info">' . implode( '', $out ) . '</div>' : '';
}

function monospace_artwork_row_html( $post_id ) {
    $post_id = (int) $post_id;
    $pid     = function_exists( 'monospace_get_post_product_id' ) ? (int) monospace_get_post_product_id( $post_id ) : 0;
    if ( ! $pid || ! function_exists( 'wc_get_product' ) || get_post_status( $post_id ) !== 'publish' ) return '';
    $product = wc_get_product( $pid );
    if ( ! $product ) return '';

    $url   = esc_url( get_permalink( $post_id ) );
    $title = esc_html( get_the_title( $post_id ) );

    $thumb = monospace_row_thumb( $post_id );

    return '<div class="ms-artwork-row">'
        . ( $thumb ? '<a class="ms-archive-image-link ms-artwork-row-pic" href="' . $url . '" tabindex="-1" aria-hidden="true">' . $thumb . '</a>' : '' )
        . '<div class="ms-artwork-row-text">'
        . '<h3 class="ms-artwork-row-title"><a href="' . $url . '">' . $title . '</a></h3>'
        . monospace_artwork_info_html( $post_id, $product )
        . '</div></div>';
}

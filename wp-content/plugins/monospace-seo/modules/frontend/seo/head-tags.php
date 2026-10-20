<?php
/**
 * SEO head tags - the one place that writes the title, meta description, canonical, Open Graph /
 * Twitter tags and JSON-LD.
 *
 * Who prints what:
 * - <title>: WordPress core (theme support 'title-tag'), text from monospace_seo_title() via
 *   pre_get_document_title. A custom SEO title set in Jetpack's editor panel still wins.
 * - Canonical: core's rel_canonical() on single posts / pages; monospace_seo_canonical() prints it
 *   for archives, the Art Feed and the shop (core prints none there).
 * - Description, Open Graph, Twitter, JSON-LD: this file (wp_head, priority 1).
 * Jetpack SEO Tools' own meta tags are off (jetpack_seo_meta_tags_enabled in functions.php - it
 * reads the filter before modules load). Its editor fields are still read here: the SEO description
 * (post meta advanced_seo_description), the front page description (option
 * advanced_seo_front_page_description) and "Hide page from search engines" (jetpack_seo_noindex).
 *
 * Descriptions, first match wins: Jetpack SEO description > artwork facts (from the linked product)
 * plus the excerpt or the post's own text > page text > child-page list (menu hub pages) > site
 * description. Archives: the feed header description (Layout Manager / term edit screen) > term
 * description > generated - except tags (local landing pages), where the term description comes first.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MONOSPACE_SEO_BRAND', 'monospace ART by Hens Breet' );
define( 'MONOSPACE_SEO_ABOUT', 'Hens Breet is a landscape artist and urban sketcher based in Hudson County, New Jersey, offering original art, prints, commissions, and classes.' );

/* ============================================================
 * HELPERS
 * ============================================================ */

/** Plain text: no tags, shortcodes, block comments, headings, captions or extra whitespace. */
function monospace_seo_plain( $html ) {
    $text = strip_shortcodes( (string) $html );
    $text = preg_replace( '#<(script|style|figcaption|h[1-6])[^>]*>.*?</\1>#is', ' ', $text );
    $text = preg_replace( '#<br\s*/?>|</(p|div|li)>#i', ' ', $text ); // line breaks keep words apart
    $text = wp_strip_all_tags( $text, true );
    $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    return trim( preg_replace( '/\s+/u', ' ', $text ) );
}

/** Decoded text - term names and titles come with entities ("Ink &amp; Wash", "&#8217;"). */
function monospace_seo_decode( $text ) {
    return html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
}

/** Shorten to $max characters on a word boundary. */
function monospace_seo_trim( $text, $max = 158 ) {
    $text = trim( $text );
    if ( mb_strlen( $text ) <= $max ) return $text;
    $cut = mb_substr( $text, 0, $max - 1 );
    $space = mb_strrpos( $cut, ' ' );
    if ( $space > $max * 0.6 ) $cut = mb_substr( $cut, 0, $space );
    return rtrim( $cut, " ,;:.–-" ) . '…';
}

/** "$120" / "$12.50" in the shop currency. */
function monospace_seo_price( $amount ) {
    $amount = (float) $amount;
    $symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) : '$';
    return $symbol . ( floor( $amount ) == $amount ? number_format( $amount, 0 ) : number_format( $amount, 2 ) );
}

/** Lowercase the first letter, unless the word looks like a name or starts with a digit. */
function monospace_seo_lc( $text ) {
    return preg_match( '/^[A-Z][a-z]/', $text ) ? mb_strtolower( mb_substr( $text, 0, 1 ) ) . mb_substr( $text, 1 ) : $text;
}

/**
 * Facts about an artwork post, from its linked product (monospace_get_post_product_id). Status
 * logic matches the price card (buy-button-shortcode.php): out of stock = sold, unless 'private'.
 * Returns null for posts with no product.
 */
function monospace_seo_artwork( $post_id ) {
    static $cache = [];
    if ( array_key_exists( $post_id, $cache ) ) return $cache[ $post_id ];

    $cache[ $post_id ] = null;
    if ( get_post_type( $post_id ) !== 'post' || ! function_exists( 'monospace_get_post_product_id' ) ) return null;
    $product_id = monospace_get_post_product_id( $post_id );
    if ( ! $product_id ) return null;
    $product = wc_get_product( $product_id );
    if ( ! $product ) return null;

    $term = function ( $taxonomy ) use ( $product_id ) {
        $names = wp_get_post_terms( $product_id, $taxonomy, [ 'fields' => 'names' ] );
        return is_wp_error( $names ) || ! $names ? '' : monospace_seo_decode( implode( ', ', $names ) );
    };

    $status = (string) get_post_meta( $product_id, 'painting_availability_status', true );
    if ( ! $product->is_in_stock() && $status !== 'private' ) $status = 'sold';

    $price       = $product->get_price();
    $purchasable = $price && $product->is_in_stock() && in_array( $status, [ '', 'available' ], true );

    // Prints: the lowest in-stock price of the linked Printful products (none while the Prints plugin is off)
    $prints_from = null;
    foreach ( monospace_feature( 'prints' ) ? [ '_print_product_id', '_framed_print_product_id' ] : [] as $key ) {
        $print = wc_get_product( (int) get_post_meta( $post_id, $key, true ) );
        if ( ! $print || ! $print->is_in_stock() ) continue;
        $p = $print->is_type( 'variable' ) ? $print->get_variation_price( 'min', true ) : $print->get_price();
        if ( $p && ( $prints_from === null || $p < $prints_from ) ) $prints_from = (float) $p;
    }

    return $cache[ $post_id ] = [
        'product'      => $product,
        'format'       => $term( 'pa_format' ),
        'medium'       => $term( 'pa_medium' ),
        'surface'      => $term( 'pa_surface' ),
        'size'         => $term( 'pa_size' ),
        'year'         => $term( 'pa_year-created' ),
        'status'       => $status,
        'price'        => $price,
        'purchasable'  => $purchasable,
        'prints_from'  => $prints_from,
        'gallery_name' => (string) get_post_meta( $product_id, 'painting_gallery_name', true ),
    ];
}

/** "Acrylic painting" / "Ink & Wash drawing" / "Painting" - '' when unknown. */
function monospace_seo_artwork_kind( $art ) {
    if ( ! $art ) return '';
    if ( $art['medium'] && $art['format'] ) return $art['medium'] . ' ' . mb_strtolower( $art['format'] );
    return $art['medium'] ?: $art['format'];
}

/** The WooCommerce shop (Art Shop) - its archive, or its page queried directly. */
function monospace_seo_is_shop() {
    if ( ! function_exists( 'is_shop' ) ) return false;
    return is_shop() || ( is_page() && get_queried_object_id() === wc_get_page_id( 'shop' ) );
}

/** The feed header title of a term (term edit screen), else its name. */
function monospace_seo_term_title( $term ) {
    $title = get_term_meta( $term->term_id, '_monospace_header_title', true );
    return monospace_seo_decode( $title ?: ucfirst( $term->name ) );
}

/* ============================================================
 * TITLE
 * ============================================================ */

function monospace_seo_title() {
    $suffix = ' | ' . MONOSPACE_SEO_BRAND;
    $page   = max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
    $paged  = $page > 1 ? ' – Page ' . $page : '';

    if ( is_front_page() ) {
        return MONOSPACE_SEO_BRAND . ' | Landscape Paintings & Urban Sketches';
    }
    if ( is_singular() ) {
        $id    = get_queried_object_id();
        $title = monospace_seo_decode( get_the_title( $id ) );
        $kind  = monospace_seo_artwork_kind( monospace_seo_artwork( $id ) );
        if ( $kind ) $title .= ' – ' . $kind;
        return $title . $paged . $suffix;
    }
    if ( is_home() ) {
        return ( get_option( 'monospace_blog_feed_header_title' ) ?: 'Art Feed' ) . $paged . $suffix;
    }
    if ( monospace_seo_is_shop() ) {
        return monospace_seo_decode( get_the_title( wc_get_page_id( 'shop' ) ) ) . $paged . $suffix;
    }
    if ( is_tag() ) {
        return single_tag_title( '', false ) . ' – Paintings & Drawings' . $paged . $suffix;
    }
    if ( is_category() || is_tax() ) {
        return monospace_seo_term_title( get_queried_object() ) . $paged . $suffix;
    }
    if ( is_search() ) {
        return 'Search: ' . get_search_query( false ) . $paged . $suffix;
    }
    if ( is_404() ) {
        return 'Page Not Found' . $suffix;
    }
    if ( is_archive() ) {
        return wp_strip_all_tags( get_the_archive_title() ) . $paged . $suffix;
    }
    return MONOSPACE_SEO_BRAND;
}

// After Jetpack's custom-title filter (10): a title set in its editor panel comes through as $title
add_filter( 'pre_get_document_title', function ( $title ) {
    return $title !== '' ? $title : monospace_seo_title();
}, 20 );

/* ============================================================
 * DESCRIPTION
 * ============================================================ */

function monospace_seo_description() {
    $site = get_option( 'advanced_seo_front_page_description' ) ?: MONOSPACE_SEO_ABOUT;

    if ( is_front_page() ) {
        return monospace_seo_trim( $site );
    }

    if ( is_singular() && ! monospace_seo_is_shop() ) {
        $post = get_queried_object();

        $custom = get_post_meta( $post->ID, 'advanced_seo_description', true );
        if ( $custom ) return monospace_seo_trim( monospace_seo_plain( $custom ) );

        $own = $post->post_excerpt ? monospace_seo_plain( $post->post_excerpt ) : monospace_seo_plain( $post->post_content );

        $art = monospace_seo_artwork( $post->ID );
        if ( $art ) {
            $kind  = monospace_seo_artwork_kind( $art );
            $facts = $kind ? $kind : 'Artwork';
            if ( $art['surface'] ) $facts .= ' on ' . monospace_seo_lc( $art['surface'] );
            $facts .= ' by Hens Breet';
            if ( $art['size'] ) $facts .= ', ' . monospace_seo_lc( $art['size'] );
            if ( $art['year'] ) $facts .= ', ' . $art['year'];
            $facts .= '.';

            switch ( $art['status'] ) {
                case 'reserved':
                case 'gallery-reserved':          $facts .= ' Original reserved.'; break;
                case 'gallery':                   $facts .= ' On view at ' . ( $art['gallery_name'] ?: 'a gallery' ) . '.'; break;
                case 'sold':
                case 'private-collection':        $facts .= ' Original in a private collection.'; break;
                case 'private':
                case 'artist-private-collection': $facts .= " From the artist's private collection."; break;
                default:
                    if ( $art['purchasable'] ) $facts .= ' Original available, ' . monospace_seo_price( $art['price'] ) . '.';
            }
            if ( $art['prints_from'] ) $facts .= ' Prints from ' . monospace_seo_price( $art['prints_from'] ) . '.';

            return monospace_seo_trim( $own ? $facts . ' ' . $own : $facts );
        }

        // Pages that are only a list of links (the menu's hub pages): name the sub-pages instead
        if ( mb_strlen( $own ) < 80 && $post->post_type === 'page' ) {
            $children = get_pages( [ 'parent' => $post->ID, 'sort_column' => 'menu_order' ] );
            if ( $children ) {
                $names = wp_list_pluck( $children, 'post_title' );
                return monospace_seo_trim( monospace_seo_decode( get_the_title( $post ) ) . ': ' . monospace_seo_decode( implode( ', ', $names ) ) . '. ' . $site );
            }
        }
        if ( mb_strlen( $own ) < 50 ) {
            $name = monospace_seo_decode( get_the_title( $post ) );
            return monospace_seo_trim( $name . ( $own && $own !== $name ? ' – ' . $own : '' ) . '. ' . $site );
        }
        return monospace_seo_trim( $own );
    }

    if ( is_home() && function_exists( 'monospace_get_feed_header_description' ) ) {
        $desc = monospace_seo_plain( monospace_get_feed_header_description( 'blog-feed' ) );
        return monospace_seo_trim( $desc ?: 'All new artwork by Hens Breet, newest first. ' . $site );
    }

    if ( monospace_seo_is_shop() ) {
        return monospace_seo_trim( 'Buy original paintings, drawings, miniatures and prints by Hens Breet, landscape painter and urban sketcher in Hudson County, New Jersey.' );
    }

    if ( is_category() || is_tag() || is_tax() ) {
        $term = get_queried_object();
        // Tags (the local landing pages): the tag's own description is written for search, the feed
        // header intro for visitors. Categories: the header intro, else the description.
        $desc = is_tag() ? monospace_seo_plain( term_description( $term ) ) : '';
        if ( ! $desc && function_exists( 'monospace_get_feed_header_description' ) ) {
            $desc = monospace_seo_plain( monospace_get_feed_header_description( 'categories', $term->term_id ) );
        }
        if ( ! $desc ) $desc = monospace_seo_plain( term_description( $term ) );
        if ( ! $desc ) {
            $desc = is_tag()
                ? 'Paintings and drawings of ' . $term->name . ' by Hens Breet, landscape painter and urban sketcher in Hudson County, New Jersey.'
                : monospace_seo_term_title( $term ) . ' by Hens Breet, landscape painter and urban sketcher in Hudson County, New Jersey.';
        }
        return monospace_seo_trim( $desc );
    }

    return monospace_seo_trim( $site );
}

/* ============================================================
 * CANONICAL (archives - core does single posts / pages)
 * ============================================================ */

function monospace_seo_canonical() {
    if ( is_singular() ) return wp_get_canonical_url( get_queried_object_id() ) ?: '';

    $url = '';
    if ( is_home() ) {
        $url = get_option( 'page_for_posts' ) ? get_permalink( get_option( 'page_for_posts' ) ) : home_url( '/' );
    } elseif ( is_post_type_archive() ) {
        $url = get_post_type_archive_link( get_query_var( 'post_type' ) ?: 'product' );
    } elseif ( is_category() || is_tag() || is_tax() ) {
        $link = get_term_link( get_queried_object() );
        $url  = is_wp_error( $link ) ? '' : $link;
    }
    if ( ! $url ) return '';

    $paged = (int) get_query_var( 'paged' );
    return $paged > 1 ? trailingslashit( $url ) . user_trailingslashit( 'page/' . $paged, 'paged' ) : $url;
}

/* ============================================================
 * SHARE IMAGE
 * ============================================================ */

/** The image for Open Graph / JSON-LD: [url, width, height, type, alt] or null. */
function monospace_seo_attachment_image( $attachment_id, $alt_fallback ) {
    // 1536px (or the original, if smaller) - big enough for every share card, smaller than originals
    $src = $attachment_id ? wp_get_attachment_image_src( $attachment_id, '1536x1536' ) : false;
    if ( ! $src ) return null;
    $alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
    return [
        'url'    => $src[0],
        'width'  => (int) $src[1],
        'height' => (int) $src[2],
        'type'   => get_post_mime_type( $attachment_id ) ?: 'image/jpeg',
        'alt'    => $alt ?: $alt_fallback,
    ];
}

function monospace_seo_post_image( $post_id ) {
    $alt = monospace_seo_decode( get_the_title( $post_id ) );
    $image = monospace_seo_attachment_image( get_post_thumbnail_id( $post_id ), $alt );
    if ( $image ) return $image;

    // No featured image: the first image in the content
    $content = get_post_field( 'post_content', $post_id );
    if ( preg_match( '/wp-image-(\d+)/', $content, $m ) ) {
        $image = monospace_seo_attachment_image( (int) $m[1], $alt );
    } elseif ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $m ) ) {
        $image = monospace_seo_attachment_image( attachment_url_to_postid( $m[1] ), $alt );
    }
    return $image;
}

function monospace_seo_image() {
    $image = null;
    if ( is_front_page() ) {
        // Layout Manager > Home Page > Social media preview (option monospace_home_share_image)
        $image = monospace_seo_attachment_image( (int) get_option( 'monospace_home_share_image', 0 ), MONOSPACE_SEO_BRAND );
    } elseif ( is_singular() ) {
        $image = monospace_seo_post_image( get_queried_object_id() );
    } elseif ( is_category() || is_tag() || is_tax() ) {
        // An archive shares its newest artwork
        global $wp_query;
        if ( ! empty( $wp_query->posts[0] ) ) $image = monospace_seo_post_image( $wp_query->posts[0]->ID );
    }
    return $image ?: [
        'url'    => MONOSPACE_SEO_URL . 'assets/site-preview.png',
        'width'  => 1200,
        'height' => 630,
        'type'   => 'image/png',
        'alt'    => MONOSPACE_SEO_BRAND,
    ];
}

/* ============================================================
 * JSON-LD
 * ============================================================ */

/** [width, height] in inches from a size like 12" × 9" - null for anything else ("Sketchbook page", sets). */
function monospace_seo_dimensions( $size ) {
    if ( preg_match_all( '/\d+(?:\.\d+)?/', $size, $m ) && count( $m[0] ) === 2 ) return $m[0];
    return null;
}

function monospace_seo_json_ld( $description, $image, $canonical ) {
    $home   = home_url( '/' );
    $person = [
        '@type'        => 'Person',
        '@id'          => $home . '#person',
        'name'         => 'Hens Breet',
        'url'          => home_url( '/about/about-me/' ),
        'jobTitle'     => 'Artist',
        'homeLocation' => [ '@type' => 'Place', 'name' => 'Hudson County, New Jersey' ],
        'sameAs'       => [ // the footer's Elsewhere column
            'https://www.instagram.com/hens.breet/',
            'https://bsky.app/profile/monospace.com',
            'https://www.facebook.com/hens.breet',
            'https://monospace.com',
        ],
    ];
    $graph = [
        [
            '@type'     => 'WebSite',
            '@id'       => $home . '#website',
            'url'       => $home,
            'name'      => 'monospace ART',
            'publisher' => [ '@id' => $home . '#person' ],
        ],
        $person,
    ];

    // Artwork posts: VisualArtwork, and a Product with an Offer while the original is for sale
    if ( is_singular( 'post' ) ) {
        $id  = get_queried_object_id();
        $art = monospace_seo_artwork( $id );
        if ( $art ) {
            $node = [
                '@type'       => $art['purchasable'] ? [ 'VisualArtwork', 'Product' ] : 'VisualArtwork',
                '@id'         => $canonical . '#artwork',
                'name'        => monospace_seo_decode( get_the_title( $id ) ),
                'url'         => $canonical,
                'description' => $description,
                'creator'     => [ '@id' => $home . '#person' ],
            ];
            if ( $image && $image['url'] ) $node['image'] = $image['url'];
            if ( $art['format'] )  $node['artform']        = $art['format'];
            if ( $art['medium'] )  $node['artMedium']      = $art['medium'];
            if ( $art['surface'] ) $node['artworkSurface'] = $art['surface'];
            if ( $art['year'] )    $node['dateCreated']    = $art['year'];
            $dims = monospace_seo_dimensions( $art['size'] );
            if ( $dims ) {
                $node['width']  = [ '@type' => 'Distance', 'name' => $dims[0] . ' in' ];
                $node['height'] = [ '@type' => 'Distance', 'name' => $dims[1] . ' in' ];
            }
            if ( $art['purchasable'] ) {
                $node['sku']    = $art['product']->get_sku();
                $node['brand']  = [ '@type' => 'Brand', 'name' => 'monospace ART' ];
                $node['offers'] = [
                    '@type'         => 'Offer',
                    'url'           => $canonical,
                    'price'         => (string) $art['price'],
                    'priceCurrency' => get_woocommerce_currency(),
                    'availability'  => 'https://schema.org/InStock',
                    'itemCondition' => 'https://schema.org/NewCondition',
                    'seller'        => [ '@id' => $home . '#person' ],
                ];
            }
            $graph[] = $node;
        }
    }

    return [ '@context' => 'https://schema.org', '@graph' => $graph ];
}

/* ============================================================
 * OUTPUT
 * ============================================================ */

/**
 * The home page's share card text (Layout Manager > Home Page > Social media preview): the owner's own
 * title / description (options monospace_home_share_title / _description), else these defaults.
 * Only the share card (og:, and through it Twitter / Bluesky) - not the <title> or meta description.
 */
function monospace_home_share_defaults() {
    return [
        'title'       => MONOSPACE_SEO_BRAND,
        'description' => monospace_seo_trim( get_option( 'advanced_seo_front_page_description' ) ?: MONOSPACE_SEO_ABOUT ),
    ];
}

add_action( 'wp_head', function () {
    $title       = is_front_page() ? MONOSPACE_SEO_BRAND : wp_get_document_title();
    $description = monospace_seo_description();
    $share_title = $title;
    $share_description = $description;
    if ( is_front_page() ) {
        $share_title       = trim( (string) get_option( 'monospace_home_share_title', '' ) ) ?: $title;
        $share_description = trim( (string) get_option( 'monospace_home_share_description', '' ) ) ?: $description;
    }
    $canonical   = monospace_seo_canonical();
    $image       = monospace_seo_image();
    $url         = $canonical ?: home_url( add_query_arg( [] ) );

    $type = 'website';
    $art  = is_singular( 'post' ) ? monospace_seo_artwork( get_queried_object_id() ) : null;
    if ( $art && $art['purchasable'] ) $type = 'product';
    elseif ( is_singular( 'post' ) )   $type = 'article';

    $tags = [
        [ 'name', 'description', $description ],
        [ 'property', 'og:type', $type ],
        [ 'property', 'og:site_name', 'monospace ART' ],
        [ 'property', 'og:locale', 'en_US' ],
        [ 'property', 'og:title', $share_title ],
        [ 'property', 'og:description', $share_description ],
        [ 'property', 'og:url', $url ],
        [ 'property', 'og:image', $image['url'] ],
        [ 'property', 'og:image:width', $image['width'] ],
        [ 'property', 'og:image:height', $image['height'] ],
        [ 'property', 'og:image:type', $image['type'] ],
        [ 'property', 'og:image:alt', $image['alt'] ],
    ];
    if ( $type === 'product' ) {
        $tags[] = [ 'property', 'product:price:amount', $art['price'] ];
        $tags[] = [ 'property', 'product:price:currency', get_woocommerce_currency() ];
    } elseif ( $type === 'article' ) {
        $tags[] = [ 'property', 'article:published_time', get_the_date( 'c', get_queried_object_id() ) ];
        $tags[] = [ 'property', 'article:modified_time', get_the_modified_date( 'c', get_queried_object_id() ) ];
    }
    $tags[] = [ 'name', 'twitter:card', 'summary_large_image' ];

    echo "\n<!-- SEO (theme: frontend/seo/head-tags.php) -->\n";
    foreach ( $tags as [ $attr, $key, $value ] ) {
        printf( '<meta %s="%s" content="%s">' . "\n", $attr, esc_attr( $key ), esc_attr( $value ) );
    }
    if ( $canonical && ! is_singular() ) {
        echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
    }
    echo '<script type="application/ld+json">'
        . wp_json_encode( monospace_seo_json_ld( $description, $image, $canonical ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
        . "</script>\n";
}, 1 );

// Jetpack's "Hide page from search engines" (editor panel) - its own output is off
add_filter( 'wp_robots', function ( $robots ) {
    if ( is_singular() && get_post_meta( get_queried_object_id(), 'jetpack_seo_noindex', true ) ) {
        $robots['noindex'] = true;
    }
    return $robots;
} );

// Jetpack added excerpts to pages along with its meta tags; keep them, a page's excerpt is its description
add_post_type_support( 'page', 'excerpt' );

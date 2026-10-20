<?php
/**
 * Price Card Shortcode
 *
 * Displays a styled purchase card for artwork with size, medium, price, and buy button.
 *
 * SHORTCODE USAGE:
 * [price_card]
 *
 * The product is the post's saved product ID (_qp_product_id, set by the Quick Product
 * editor - see monospace_get_post_product_id()). Before a product is linked the card
 * shows "Coming Soon".
 *
 * LEGACY: [painting_buy_button id="123"] renders the same card. An explicit numeric id
 * wins; id="xxxx" or no id falls back to the saved product ID.
 *
 * DISPLAY ELEMENTS:
 * - Product attributes (size, medium, price)
 * - Availability status ("Available", "Sold", "Gallery", "Private")
 * - Add to cart button (if available for purchase)
 * - SKU code (if provided)
 *
 * AVAILABILITY STATES:
 * - Available: Yellow badge, shows buy button
 * - Gallery: White badge, shows buy button
 * - Sold: Red text, no button
 * - Private: Red text, no button
 *
 * PRINTS:
 * - If the post has print products set in the Quick Product editor, the card gets
 *   tabs Original | Framed Print | Unframed Print, opening on Original
 *   (monospace_render_painting_card(); panes come from print-order-shortcode.php)
 *
 * STYLING:
 * - Uses .painting-buy-row CSS class
 * - Responsive: stacks on mobile
 * - Card-style with shadow and border
 *
 * INTEGRATION:
 * - WooCommerce product data (price, stock, attributes)
 * - ACF field 'painting_availability_status' for status
 * - Related to clickable-feed-image.php for archive badges
 *
 * @package astra-child-theme-for-monospace-art
 */

function monospace_is_editor_context() {
    if ( is_admin() ) return true;
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return true;
    if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) return true;
    return false;
}

/**
 * Generate reserved purchase key for a product
 * Must match the logic in monospace-quick-product plugin
 */
function monospace_generate_reserved_key( $product_id ) {
    $secret = defined('MONOSPACE_RESERVED_SECRET') ? MONOSPACE_RESERVED_SECRET : 'ms-art-reserved-2024';
    return substr( md5( $product_id . $secret ), 0, 16 );
}

/**
 * Validate reserved purchase key from URL
 */
function monospace_validate_reserved_key( $product_id ) {
    if ( ! isset( $_GET['reserved_key'] ) ) {
        return false;
    }

    $provided_key = sanitize_text_field( $_GET['reserved_key'] );
    $expected_key = monospace_generate_reserved_key( $product_id );

    return hash_equals( $expected_key, $provided_key );
}








/**
 * The WooCommerce product linked to a post (its saved _qp_product_id), or 0 when
 * none is linked or the product no longer exists.
 */
function monospace_get_post_product_id( $post_id = null ) {
    $post_id    = $post_id ?: get_the_ID();
    $product_id = $post_id ? intval( get_post_meta( $post_id, '_qp_product_id', true ) ) : 0;
    if ( ! $product_id || ! function_exists( 'wc_get_product' ) || ! wc_get_product( $product_id ) ) {
        return 0;
    }
    return $product_id;
}

/**
 * [price_card] - attributes (2026-10-19, all optional):
 *   style="full | compact | line | badge"   a preset (price-card-presets.php); default: the artwork's own
 *                                           (Quick Artwork Editor / Artwork Editor), else Layout Manager >
 *                                           Appearance & Effects > Price card (artwork pages / phones)
 *   sku="444"                               another artwork's card, by its SKU's number (or the whole SKU)
 *   post="123"                              another artwork's card, by its post ID
 *   tab="original | prints | framed | unframed"  the tab it opens on (prints = the first print it has); default:
 *                                           Layout Manager > Price card > Opens on
 *   id="123"                                legacy: a product ID
 */
function monospace_custom_add_to_cart_shortcode( $atts ) {

    $atts = shortcode_atts( array( 'id' => null, 'style' => '', 'sku' => '', 'post' => '', 'tab' => '' ), $atts );

    // Which artwork: sku="" / post="", else the post the shortcode is in
    $post_id = function_exists( 'monospace_price_card_target' ) ? monospace_price_card_target( (string) $atts['sku'], (string) $atts['post'] ) : (int) get_the_ID();
    if ( $post_id === false ) {
        return monospace_is_editor_context()
            ? '<div class="painting-buy-placeholder" style="padding:6px;border:1px dashed #c00;background:#f9f9f9;">Price Card: no artwork with SKU / post "' . esc_html( $atts['sku'] . $atts['post'] ) . '"</div>'
            : '';
    }

    // Legacy [painting_buy_button id="123"]: an explicit numeric id wins
    $product_id = ctype_digit( (string) $atts['id'] ) ? intval( $atts['id'] ) : monospace_get_post_product_id( $post_id );

    if ( monospace_is_editor_context() ) {
        return '<div class="painting-buy-placeholder" style="padding:6px;border:1px dashed #aaa;background:#f9f9f9;">'
             . 'Price Card (' . ( $product_id ? 'Product ID ' . esc_html( $product_id ) : 'Coming Soon - no product linked yet' )
             . ( $atts['style'] !== '' ? ', ' . esc_html( $atts['style'] ) : '' ) . ')'
             . '</div>';
    }

    $render = function () use ( $product_id, $atts ) {
        $card = monospace_price_card_build( $product_id );
        if ( ! $card ) return '';
        $card['tab'] = function_exists( 'monospace_price_card_tab_setting' ) ? monospace_price_card_tab_setting( (string) $atts['tab'] ) : '';
        return function_exists( 'monospace_render_price_card_styles' )
            ? monospace_render_price_card_styles( $card, monospace_price_card_styles( (string) $atts['style'], (int) get_the_ID() ) )
            : monospace_render_painting_card( monospace_render_card_slots( ...$card['parts'] ), $card['status_class'], $card['status_slug'] );
    };
    return function_exists( 'monospace_price_card_with_post' ) ? monospace_price_card_with_post( $post_id, $render ) : $render();
}

/**
 * The price card's content for a product - built once, laid out by any preset (price-card-presets.php):
 *   parts         [ info, variants, buy, offer, sku ] - monospace_render_card_slots()'s arguments (the full card)
 *   status_class  ' status-<slug>'      status_slug  the availability ('' = for sale)
 *   price         the price HTML when it's shown (for sale, or reserved), else ''
 *   label         the availability label HTML ('' when it's simply for sale)
 *   cart          the Add to cart (or Already in Cart) button HTML, '' when it can't be bought
 * The current post is the artwork (monospace_price_card_with_post() sets it). null: no WooCommerce / product gone.
 */
function monospace_price_card_build( $product_id ) {
    // No product linked yet
    if ( ! $product_id ) {

        $status_slug  = 'coming-soon';
        $status_class = ' status-' . $status_slug;

        $attr_list = '<div class="painting-attr">Attributes: TBA</div>';
        $button = '<span class="status-label status-coming-soon">Coming Soon</span>';

        return [
            'parts'        => [ $attr_list, '', $button, '', '' ],
            'status_class' => $status_class,
            'status_slug'  => $status_slug,
            'price'        => '',
            'label'        => $button,
            'cart'         => '',
        ];
    }

    if ( ! function_exists( 'wc_get_product' ) ) return null;
    $product = wc_get_product( $product_id );
    if ( ! $product ) return null;

    $status       = get_post_meta( $product_id, 'painting_availability_status', true );
    $gallery_url  = get_post_meta( $product_id, 'painting_gallery_url', true );
    $gallery_name = get_post_meta( $product_id, 'painting_gallery_name', true );

    // Check WooCommerce stock status FIRST - if out of stock, override to sold
    // This ensures display is correct even if auto-sync hasn't run yet
    // Note: gallery items also override to sold when out of stock (item was purchased)
    if ( ! $product->is_in_stock() && $status !== 'private' ) {
        $status = 'sold';
    }

    // Check for valid reserved purchase key - bypass reserved/gallery-reserved gate if valid
    $has_valid_reserved_key = false;
    if ( ( $status === 'reserved' || $status === 'gallery-reserved' ) && monospace_validate_reserved_key( $product_id ) ) {
        $has_valid_reserved_key = true;
    }

    $attr_list = monospace_render_product_attributes( $product, $product_id );
    $button    = monospace_render_buy_button( $product, $status, $gallery_url, $gallery_name );

    if ( $has_valid_reserved_key ) {
        $button = '';
    }


    $status_slug  = $status ? sanitize_title( $status ) : 'default';
    $status_class = ' status-' . $status_slug;

    $sku = $product->get_sku();

    $price_html = $add_to_cart_btn = '';
    $non_purchasable_statuses = [
        'gallery','gallery-reserved','private','sold','reserved','coming-soon','artist-private-collection','private-collection'
    ];

    // Remove gate statuses from non-purchasable list if valid key is present
    if ( $has_valid_reserved_key ) {
        $non_purchasable_statuses = array_diff( $non_purchasable_statuses, ['reserved', 'gallery-reserved'] );
    }

    $status_slug = $status ? sanitize_title( $status ) : '';

    /**
     * RESPONSIVE ADD-TO-CART LINK + CART REDIRECT
     */
    if (
        $product->get_price() &&
        $product->is_in_stock() &&
        ! in_array( $status_slug, $non_purchasable_statuses, true )
    ) {

        $price_display = $product->get_price_html();
        $already_in_cart = false;

        if ( function_exists( 'WC' ) && WC()->cart ) {
            $CartId = WC()->cart->generate_cart_id( $product->get_id() );
            $already_in_cart = WC()->cart->find_product_in_cart( $CartId );
        }

        if ( $already_in_cart ) {

            $add_to_cart_btn =
                '<a class="painting-buy-button already-in-cart disabled" aria-disabled="true" tabindex="-1">Already in Cart</a>';

        } else {

            // Add to cart → redirect to cart
            $add_to_cart_btn =
                '<a class="painting-buy-button" href="' .
                esc_url(
                    add_query_arg(
                        array(
                            'add-to-cart'      => $product->get_id(),
                            'ms_redirect_cart' => 1
                        )
                    )
                ) .
                '">Add to cart</a>';
        }

        $price_html = '<div class="painting-price">' . $price_display . '</div>';
    }

    // Show price for reserved/gallery-reserved items (without purchase button, unless valid key present)
    if ( ( $status === 'reserved' || $status === 'gallery-reserved' ) && ! $has_valid_reserved_key && $product->get_price() && $product->is_in_stock() ) {
        $price_display = $product->get_price_html();
        $price_html = '<div class="painting-price">' . $price_display . '</div>';
    }

    return [
        'parts'        => [
            $attr_list,
            $price_html,
            $button . $add_to_cart_btn,   // status label and/or button, on the SKU's row
            monospace_card_sales_message( 'original', $product, get_the_ID(), [
                'status'      => $status,
                'qr_only'     => $status === 'gallery-reserved' && ! $has_valid_reserved_key,
                'purchasable' => $add_to_cart_btn !== '', // Add to cart (or Already in Cart) shown
            ] ),
            (string) $sku,
        ],
        'status_class' => $status_class,
        'status_slug'  => $status_slug,
        'price'        => $price_html !== '' ? $price_display : '',
        'label'        => $button,
        'cart'         => $add_to_cart_btn,
    ];
}
add_shortcode( 'price_card', 'monospace_custom_add_to_cart_shortcode' );
add_shortcode( 'painting_buy_button', 'monospace_custom_add_to_cart_shortcode' ); // legacy name

/**
 * Wrap the original painting's card contents. When features add tabs (monospace_price_card_panes - Prints adds
 * Framed / Unframed print for the post's _print_product_id / _framed_print_product_id), the card
 * gets tabs: Original | Framed Print | Unframed Print, opening on Original - unless the original
 * has sold: then it opens on the first print tab (Framed, else Unframed) and "Original" is struck
 * through, still clickable. Sold = 'sold' (also set when out of stock) or 'private-collection';
 * the artist's own collection ('private') isn't sold and opens on Original as before.
 */
function monospace_render_painting_card( $inner, $status_class, $status_slug, $tab = '' ) {
    $post_id = get_the_ID();
    // Tabs features add (Prints: Framed / Unframed print) - [ key => [ 'label', 'html' ] ], in tab order
    $prints  = $post_id ? (array) apply_filters( 'monospace_price_card_panes', [], $post_id ) : [];

    if ( ! $prints ) {
        return sprintf(
            '<div class="painting-buy-row card-panel%s" data-status="%s">%s</div>',
            esc_attr( $status_class ),
            esc_attr( $status_slug ),
            $inner
        );
    }

    $sold   = in_array( $status_slug, [ 'sold', 'private-collection' ], true );
    $panes  = [ 'original' => [ 'label' => 'Original', 'html' => $inner, 'struck' => $sold ] ] + $prints;
    // The tab it opens on: asked for (price-card-presets.php - "Opens on"), else Original, or the first print when sold
    $active = function_exists( 'monospace_price_card_active_tab' ) ? monospace_price_card_active_tab( array_keys( $panes ), $sold, $tab )
            : ( $sold ? array_key_first( $prints ) : 'original' );
    $n = function_exists( 'monospace_price_card_instance' ) ? '-' . monospace_price_card_instance() : '';
    return monospace_render_tabbed_card( 'artwork-card-' . $post_id . $n, $panes, $active, $status_class, $status_slug );
}

/**
 * What the card shows under the buy row for a tab: the offer strip (monospace_card_offer_message)
 * and the tab's fine print (Sales Dashboard > Messages > Fine print) - the fine print (shipping,
 * returns) only while the item can be bought: $context['purchasable'] (the original's card: an
 * Add to cart button is shown), else the product is purchasable and in stock (print tabs).
 */
function monospace_card_sales_message( $tab, $product, $post_id, $context = [] ) {
    $purchasable = array_key_exists( 'purchasable', $context )
        ? (bool) $context['purchasable']
        : ( $product && $product->is_purchasable() && $product->is_in_stock() );

    return monospace_card_offer_message( $tab, $product, $post_id, $context )
         . ( $purchasable ? monospace_card_fine_print( $tab, $product ) : '' );
}

/**
 * The tab's fine print, placeholders filled in from the WooCommerce shipping zones (costs are never
 * typed into the text): {shipping} = the whole sentence (monospace_card_shipping_sentence),
 * {shipping_na} / {shipping_world} = this item's cost to North America / elsewhere.
 */
function monospace_card_fine_print( $tab, $product ) {
    if ( ! class_exists( 'MSD_Card_Messages' ) || ! method_exists( 'MSD_Card_Messages', 'get_fine_print' ) ) return '';
    $text = trim( MSD_Card_Messages::get_fine_print()[ $tab ] ?? '' );
    if ( $text === '' ) return '';

    if ( strpos( $text, '{shipping}' ) !== false ) {
        $text = str_replace( '{shipping}', monospace_card_shipping_sentence( $product ), $text );
    }
    foreach ( [ 'na' => '{shipping_na}', 'world' => '{shipping_world}' ] as $where => $placeholder ) {
        if ( strpos( $text, $placeholder ) !== false ) {
            $text = str_replace( $placeholder, monospace_card_shipping_cost( $product, $where ), $text );
        }
    }
    return '<p class="card-fine-print">' . nl2br( esc_html( $text ) ) . '</p>';
}

/**
 * "Shipping: $16 to the US, Canada and Mexico, $39 elsewhere" / "Free shipping worldwide" /
 * "Shipping: free to the US, Canada and Mexico, $25 elsewhere" - costs from the zones, and the
 * North American zone's countries from its regions.
 */
function monospace_card_shipping_sentence( $product ) {
    $na    = monospace_card_shipping_cost( $product, 'na' );
    $world = monospace_card_shipping_cost( $product, 'world' );
    if ( $na === $world ) {
        return $na === 'free' ? 'Free shipping worldwide' : 'Shipping: ' . $na . ' worldwide';
    }
    return 'Shipping: ' . $na . ' to ' . monospace_card_shipping_region() . ', ' . $world . ' elsewhere';
}

/** The countries of the zone a US address falls in: "the US, Canada and Mexico" */
function monospace_card_shipping_region() {
    static $region = null;
    if ( $region !== null ) return $region;

    $zone  = WC_Shipping_Zones::get_zone_matching_package( [ 'destination' => [ 'country' => 'US', 'state' => 'NJ', 'postcode' => '07030' ] ] );
    $all   = WC()->countries->get_countries();
    $names = [];
    foreach ( $zone->get_zone_locations() as $location ) {
        if ( $location->type !== 'country' ) continue;
        $names[] = $location->code === 'US' ? 'the US'
            : ( $location->code === 'GB' ? 'the UK' : html_entity_decode( preg_replace( '/\s*\(.*\)$/', '', $all[ $location->code ] ?? $location->code ) ) );
    }
    if ( ! $names ) return $region = 'North America';
    $last = array_pop( $names );
    return $region = $names ? implode( ', ', $names ) . ' and ' . $last : $last;
}

/**
 * One item's shipping cost from the WooCommerce zones - 'na' (the zone a US address falls in) or
 * 'world' (Locations not covered): "$16", "free", or "see checkout" when the rate isn't a plain
 * number. Flat rate = its cost + the item's shipping-class cost; a free-shipping method that needs
 * nothing (no coupon / minimum) makes it free. The cheapest wins.
 */
function monospace_card_shipping_cost( $product, $where ) {
    static $cache = [];
    if ( ! function_exists( 'WC' ) || ! $product ) return 'see checkout';

    $class_id = $product->get_shipping_class_id();
    if ( ! $class_id && $product->get_parent_id() && ( $parent = wc_get_product( $product->get_parent_id() ) ) ) {
        $class_id = $parent->get_shipping_class_id();
    }
    $key = $where . ':' . $class_id;
    if ( isset( $cache[ $key ] ) ) return $cache[ $key ];

    $zone = $where === 'na'
        ? WC_Shipping_Zones::get_zone_matching_package( [ 'destination' => [ 'country' => 'US', 'state' => 'NJ', 'postcode' => '07030' ] ] )
        : new WC_Shipping_Zone( 0 );

    $best = null;
    foreach ( $zone->get_shipping_methods( true ) as $method ) {
        if ( $method->id === 'free_shipping' && $method->get_option( 'requires' ) === '' ) {
            $best = 0.0;
            break;
        }
        if ( $method->id !== 'flat_rate' ) continue;
        $base  = $method->get_option( 'cost' );
        $class = $class_id ? $method->get_option( 'class_cost_' . $class_id, $method->get_option( 'no_class_cost' ) ) : $method->get_option( 'no_class_cost' );
        $base  = $base === '' ? '0' : $base;
        $class = $class === '' ? '0' : $class;
        if ( ! is_numeric( $base ) || ! is_numeric( $class ) ) continue;
        $cost = (float) $base + (float) $class;
        if ( $best === null || $cost < $best ) $best = $cost;
    }

    if ( $best === null ) return $cache[ $key ] = 'see checkout';
    return $cache[ $key ] = $best <= 0 ? 'free' : html_entity_decode( get_woocommerce_currency_symbol() ) . ( floor( $best ) == $best ? number_format( $best, 0 ) : number_format( $best, 2 ) );
}

/**
 * The price card's message strip for one tab: 'original', 'framed' or 'unframed'.
 *
 * The only thing that writes the strip. In order:
 *   1. the post's own override from the Quick Product box (_qp_card_message[$tab]):
 *      'custom' shows its text, 'none' shows nothing, 'site' (default) falls through
 *   2. that tab's list from Sales Dashboard > Messages (MSD_Card_Messages): the first enabled
 *      row that applies to the post (post categories + dates)
 *        - qr_note: only when $context['qr_only'] (gallery-reserved, no valid QR key)
 *        - volume:  the volume-discount note, when the product qualifies
 *        - custom:  the message text
 * Each message is a box in one of four styles (green / red / blue / gray; CSS in the plugin).
 * {gallery} is replaced with the original painting's gallery name.
 */
function monospace_card_offer_message( $tab, $product, $post_id, $context = [] ) {
    $gallery = function () use ( $post_id ) {
        return get_post_meta( monospace_get_post_product_id( $post_id ), 'painting_gallery_name', true ) ?: 'the gallery';
    };

    $override = ( (array) get_post_meta( $post_id, '_qp_card_message', true ) )[ $tab ] ?? [];
    $mode     = $override['mode'] ?? 'site';
    if ( $mode === 'none' ) {
        return '';
    }
    if ( $mode === 'custom' && ( $override['text'] ?? '' ) !== '' ) {
        return monospace_render_card_message( str_replace( '{gallery}', $gallery(), $override['text'] ), $override['style'] ?? 'green' );
    }

    $lists = class_exists( 'MSD_Card_Messages' )
        ? MSD_Card_Messages::get()
        : [ 'original' => [ [ 'type' => 'qr_note', 'enabled' => true, 'cats' => [], 'start' => '', 'end' => '', 'style' => 'gray',
                              'text' => 'This item can only be purchased through the QR code link at {gallery}' ] ] ];

    foreach ( $lists[ $tab ] ?? [] as $row ) {
        if ( empty( $row['enabled'] ) ) continue;
        if ( class_exists( 'MSD_Card_Messages' ) && ! MSD_Card_Messages::applies( $row, $post_id ) ) continue;

        if ( $row['type'] === 'volume' ) {
            $hint = monospace_get_volume_discount_hint( $product, $product->get_id(), $context['status'] ?? '' );
            if ( $hint ) return monospace_render_card_message( $hint['main'], $row['style'], $hint['sub'] );
            continue;
        }
        if ( $row['type'] === 'qr_note' && empty( $context['qr_only'] ) ) continue;

        return monospace_render_card_message( str_replace( '{gallery}', $gallery(), $row['text'] ), $row['style'] );
    }
    return '';
}

/**
 * One message box: main text, optional small second line, in a style (green / red / blue / gray).
 */
function monospace_render_card_message( $text, $style = 'green', $sub = '' ) {
    $style = in_array( $style, [ 'green', 'red', 'blue', 'gray' ], true ) ? $style : 'green';
    return '<div class="card-message card-message--' . $style . '">' . esc_html( $text )
         . ( $sub !== '' ? '<span class="card-message-sub">' . esc_html( $sub ) . '</span>' : '' )
         . '</div>';
}

/**
 * Get volume discount hint from Sales Dashboard
 * Returns formatted HTML if product qualifies for volume discount
 */
function monospace_get_volume_discount_hint( $product, $product_id, $status ) {

    // Check if reserved key is valid
    $has_valid_reserved_key = ( ( $status === 'reserved' || $status === 'gallery-reserved' ) && monospace_validate_reserved_key( $product_id ) );

    // Only show for available products (or reserved/gallery-reserved with valid key)
    $is_available = !in_array($status, ['sold', 'private', 'gallery', 'gallery-reserved']) && $product->is_in_stock();
    if ( ( $status === 'reserved' || $status === 'gallery-reserved' ) && ! $has_valid_reserved_key ) {
        $is_available = false;
    }
    if ( ! $is_available ) {
        return '';
    }

    // Check if Sales Dashboard volume discounts are enabled (and the Sales Dashboard is on - it gives the discount)
    if ( ! monospace_feature( 'sales' ) || get_option('msd_volume_enable') !== 'yes' ) {
        return '';
    }

    // Get volume rules from Sales Dashboard
    $rules_json = get_option('msd_volume_rules', '');
    if ( empty( $rules_json ) ) {
        return '';
    }

    $volume_rules = json_decode( $rules_json, true );
    if ( ! is_array( $volume_rules ) ) {
        return '';
    }

    // Check if this product matches any volume discount rules
    foreach ( $volume_rules as $rule_key => $rules ) {

        if ( ! monospace_product_matches_volume_rule( $product_id, $rule_key ) ) {
            continue;
        }

        // Found a matching rule - get the best offer to display
        $hint = monospace_format_volume_hint( $rules, $rule_key, $product_id );
        if ( $hint ) {
            return $hint;
        }
    }



    return '';
}

/**
 * Check if product matches a volume discount rule key
 */
function monospace_product_matches_volume_rule( $product_id, $rule_key ) {

    // Check if it's a product category
    if (has_term($rule_key, 'product_cat', $product_id)) {
        return true;
    }

    // Check if it's a product tag
    if (has_term($rule_key, 'product_tag', $product_id)) {
        return true;
    }

    // Attribute format: attr:pa_taxonomy=slug (advanced)
    if ( strpos( $rule_key, 'attr:' ) === 0 ) {
        $payload = substr( $rule_key, 5 );
        if ( strpos( $payload, '=' ) !== false ) {
            list( $tax, $slug ) = explode( '=', $payload, 2 );
            $tax = sanitize_text_field( trim( $tax ) );
            $slug = sanitize_text_field( trim( $slug ) );
            if ( $tax && $slug ) {
                return monospace_product_has_attribute( $product_id, $tax, $slug );
            }
        }
    }

    return false;
}

/**
 * Check if product has specific attribute term
 */
function monospace_product_has_attribute( $product_id, $taxonomy, $term_slug ) {
    $terms = wp_get_post_terms( $product_id, $taxonomy, ['fields' => 'slugs'] );
    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return false;
    }
    return in_array( $term_slug, (array) $terms, true );
}

/**
 * Format volume discount hint for display
 */
function monospace_format_volume_hint( $rules, $rule_key, $product_id ) {

    // Find the most attractive offer (typically the largest bundle)
    $best_offer = null;

    foreach ( $rules as $rule ) {
        if ( empty( $rule['type'] ) ) continue;

        switch ( $rule['type'] ) {
            case 'fixed_bundle':
                if ( isset( $rule['qty'], $rule['total'] ) ) {
                    // Prefer larger bundles
                    if ( ! $best_offer || $rule['qty'] > $best_offer['qty'] ) {
                        $best_offer = $rule;
                    }
                }
                break;

            case 'buy_x_get_y':
                if ( isset( $rule['buy'], $rule['get'] ) ) {
                    $best_offer = $best_offer ?: $rule;
                }
                break;

            case 'percent_discount':
                if ( isset( $rule['percent'] ) ) {
                    $best_offer = $best_offer ?: $rule;
                }
                break;
        }
    }

    if ( ! $best_offer ) {
        return '';
    }

    // Get templates from Sales Dashboard
    $secondary_text = get_option('msd_hint_secondary_text', '(Discount applied at checkout.)');

    // Format based on rule type
    $main_text = '';

    switch ( $best_offer['type'] ) {
        case 'fixed_bundle':
            $template = get_option('msd_hint_template_bundle', 'Special: Get {qty} for only ${total}!');
            $main_text = str_replace(
                ['{qty}', '{total}'],
                [$best_offer['qty'], number_format($best_offer['total'], 0)],
                $template
            );
            break;

        case 'buy_x_get_y':
            $template = get_option('msd_hint_template_bxgy', 'Buy {buy}, get {get} free!');
            $main_text = str_replace(
                ['{buy}', '{get}'],
                [$best_offer['buy'], $best_offer['get']],
                $template
            );
            break;

        case 'percent_discount':
            $template = get_option('msd_hint_template_percent', 'Save {percent}% on qualifying purchases!');
            $percent = rtrim(rtrim(number_format($best_offer['percent'], 1, '.', ''), '0'), '.');
            $main_text = str_replace('{percent}', $percent, $template);
            break;
    }

    if ( ! $main_text ) {
        return '';
    }

    // Make product-specific by inserting product type after quantity
    $main_text = monospace_make_hint_product_specific($main_text, $product_id, $best_offer);

    // Text only - the card's message box (monospace_render_card_message()) styles it
    return [ 'main' => $main_text, 'sub' => (string) $secondary_text ];
}


function monospace_render_product_attributes( $product, $product_id ) {
    $order = array( 'format', 'medium', 'surface', 'size' );
    $exclude = array();
    $attributes = $product->get_attributes();
    $output     = array();

    $render_attr = function( $attribute, $product_id ) {
        $label = wc_attribute_label( $attribute->get_name() );

        if ( $attribute->is_taxonomy() ) {
            $terms = wp_get_post_terms( $product_id, $attribute->get_name(), array( 'fields' => 'names' ) );
            $value = implode( ', ', $terms );
        } else {
            $value = $attribute->get_options() ? implode( ', ', $attribute->get_options() ) : '';
        }

        return $value
            ? '<div class="painting-attr"><b>' . esc_html( $label ) . '</b><span class="pa-sep">: </span>' . esc_html( $value ) . '</div>'
            : '';
    };

    foreach ( $order as $attr_name ) {
        foreach ( $attributes as $key => $attribute ) {
            $label = strtolower( wc_attribute_label( $attribute->get_name() ) );

            if ( in_array( $label, $exclude, true ) ) {
                unset( $attributes[ $key ] );
                continue;
            }

            if ( $label === strtolower( $attr_name ) ) {
                $output[] = $render_attr( $attribute, $product_id );
                unset( $attributes[ $key ] );
                break;
            }
        }
    }

    foreach ( $attributes as $attribute ) {
        $label = strtolower( wc_attribute_label( $attribute->get_name() ) );
        if ( in_array( $label, $exclude, true ) ) continue;
        $output[] = $render_attr( $attribute, $product_id );
    }

    return implode( '', $output );
}

/**
 * Availability status as a text label for the card. Every status uses the same
 * .status-label style; only the color varies (status-<slug>). Gallery statuses
 * link to the gallery when a URL is set. Returns '' when the item is for sale.
 */
function monospace_render_buy_button( $product, $status, $gallery_url, $gallery_name ) {

    $label = function ( $slug, $text, $url = '' ) {
        return $url
            ? '<a class="status-label status-' . $slug . '" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . $text . ' ↗</a>'
            : '<span class="status-label status-' . $slug . '">' . $text . '</span>';
    };

    $gallery_label = $gallery_name ? 'On view at ' . esc_html( $gallery_name ) : 'Available at Gallery';

    switch ( $status ) {
        case 'private':
            return $label( 'private', 'Artist\'s Private Collection' );

        case 'gallery':
        case 'gallery-reserved':
            return $label( $status, $gallery_label, $gallery_url );

        case 'reserved':
            return $label( 'reserved', 'Reserved' );

        case 'sold':
            return $label( 'sold', 'Private Collection' );

        default:
            if ( ! $product->is_in_stock() ) {
                return $label( 'sold', 'Private Collection' );
            }

            if ( ! $product->get_price() ) {
                return $label( 'coming-soon', 'Coming Soon' );
            }

            return '';
    }
}

/**
 * Make hint text product-specific
 * Inserts product type after quantity (e.g., "Get 3 miniatures for...")
 */
function monospace_make_hint_product_specific($text, $product_id, $offer) {
    // Get product type name
    $type_name = monospace_get_product_type_name($product_id);

    if (!$type_name) {
        return $text; // No specific type, keep as is
    }

    // Determine quantity for pluralization
    $qty = 1;
    if (isset($offer['qty'])) {
        $qty = $offer['qty'];
    } elseif (isset($offer['buy'])) {
        $qty = $offer['buy'];
    } elseif (isset($offer['get'])) {
        $qty = $offer['get'];
    }

    $term = ($qty == 1) ? $type_name['singular'] : $type_name['plural'];

    // Pattern matching for different templates
    // "Get 3 for" -> "Get 3 miniatures for"
    $text = preg_replace('/\b(\d+)\s+(for|at|@)\b/i', '$1 ' . $term . ' $2', $text);

    // "Buy 2, get" -> "Buy 2 miniatures, get"
    $text = preg_replace('/\bBuy\s+(\d+),/i', 'Buy $1 ' . $term . ',', $text);

    // "get 1 free" -> "get 1 miniature free"
    $text = preg_replace('/\bget\s+(\d+)\s+free\b/i', 'get $1 ' . $term . ' free', $text);

    return $text;
}

/**
 * Get product type name (singular and plural)
 */
function monospace_get_product_type_name($product_id) {
    // Check product tags first (more specific)
    $tags = wp_get_post_terms($product_id, 'product_tag', ['fields' => 'all']);
    if (!is_wp_error($tags) && !empty($tags)) {
        $tag = $tags[0];
        $name = strtolower($tag->name);
        $singular = (substr($name, -1) === 's') ? substr($name, 0, -1) : $name;
        $plural = (substr($name, -1) === 's') ? $name : $name . 's';
        return ['singular' => $singular, 'plural' => $plural];
    }

    // Check categories
    $categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'all']);
    if (!is_wp_error($categories) && !empty($categories)) {
        $cat = $categories[0];
        $name = strtolower($cat->name);
        $singular = (substr($name, -1) === 's') ? substr($name, 0, -1) : $name;
        $plural = (substr($name, -1) === 's') ? $name : $name . 's';
        return ['singular' => $singular, 'plural' => $plural];
    }

    return null;
}

/**
 * Redirect to cart for our custom add-to-cart links
 */
add_filter( 'woocommerce_add_to_cart_redirect', function( $url ) {
    if ( isset( $_REQUEST['ms_redirect_cart'] ) ) {
        return wc_get_cart_url();
    }
    return $url;
}, 99 );

/**
 * Ensure our custom parameter persists through WooCommerce processing
 */
add_filter( 'woocommerce_add_to_cart_validation', function( $passed, $product_id, $quantity ) {
    if ( isset( $_REQUEST['ms_redirect_cart'] ) ) {
        WC()->session->set( 'ms_redirect_cart', true );
    }
    return $passed;
}, 10, 3 );

/**
 * Check session for redirect flag
 */
add_filter( 'woocommerce_add_to_cart_redirect', function( $url ) {
    if ( WC()->session->get( 'ms_redirect_cart' ) ) {
        WC()->session->__unset( 'ms_redirect_cart' );
        return wc_get_cart_url();
    }
    return $url;
}, 100 );
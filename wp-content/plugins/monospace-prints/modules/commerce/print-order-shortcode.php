<?php
/**
 * Print Order Card
 *
 * Print ordering panes for Printful variable WooCommerce products, chosen via
 * size radio options; the framed pane adds frame-color options.
 *
 * The panes are tabs on the price card ([price_card], see monospace_render_painting_card()), read from the
 * post's _print_product_id / _framed_print_product_id meta (set in the Quick Artwork Editor). The separate
 * print-only card (on the Print category, and [print_order_button]) was retired on 2026-10-20 - the price card
 * can open on a print tab instead ("Opens on", Core's price-card-presets.php).
 *
 * "See a preview" (2026-10-16): each print pane has a text link above its Add to cart button (at the
 * bottom of the options column, so the SKU / button row is untouched). It opens the selected
 * variation's mockup in the site's own lightbox (no GLightbox since 2026-10-19) - framed: every frame in
 * the selected size, swipe between them.
 * Mockup = the variation's mockup drawn on the site (prints/print-mockups.php, transparent), shown on
 * a blur of the page behind it by default - set in Layout Manager > Appearance & Effects > Print preview
 * (design/print-preview.php, 2026-10-19: blur / image / pattern / solid colour and more).
 *
 * LEGACY SHORTCODE [print_order_button] (no post uses it): nothing in a post that has a [price_card], else
 * the post's price card opening on its prints.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'print_order_button', function () {
    $post = get_post();
    if ( $post && ( has_shortcode( $post->post_content, 'price_card' ) || has_shortcode( $post->post_content, 'painting_buy_button' ) ) ) return '';
    return do_shortcode( '[price_card tab="prints"]' );
} );


/**
 * Normalize a size string from any format to W" × H".
 * Optionally swap W and H for portrait orientation.
 *
 * Handles: "16x20", "16×20", "16 x 20", "16"×20"", "16″×20″", "16 × 20"
 * Returns: '16" × 20"' or '20" × 16"' depending on $portrait flag.
 */
function monospace_normalize_print_size( $raw, $portrait = false ) {
    // Strip all quote/inch characters and collapse whitespace (names may hold "&times;")
    $cleaned = preg_replace( '/["""″\'`]/u', '', html_entity_decode( $raw, ENT_QUOTES, 'UTF-8' ) );
    $cleaned = trim( $cleaned );

    // Extract the two numbers (integers or decimals). /u: × is multi-byte - without it Printful's
    // newer size names ("8×10") didn't parse and showed bare
    if ( ! preg_match( '/^(\d+(?:\.\d+)?)\s*[×xX]\s*(\d+(?:\.\d+)?)$/u', $cleaned, $m ) ) {
        // Can't parse — return original with inch marks added if missing
        return $raw;
    }

    $a = (float) $m[1];
    $b = (float) $m[2];

    // Ensure W × H is correct for orientation:
    // landscape = wider first (a >= b), portrait = taller first (b > a)
    if ( $portrait ) {
        $w = min( $a, $b );
        $h = max( $a, $b );
    } else {
        $w = max( $a, $b );
        $h = min( $a, $b );
    }

    // Format: strip trailing zeros from decimals
    $fmt = function( $n ) {
        return rtrim( rtrim( number_format( $n, 1, '.', '' ), '0' ), '.' );
    };

    return $fmt( $w ) . '" × ' . $fmt( $h ) . '"';
}



/**
 * Inch marks on any bare size in a piece of text or HTML, written as on the price card - in the
 * painting's orientation (wider side first unless $portrait): "8×10" / "8x10" -> 10" × 8".
 * Sizes that already have marks (originals, e.g. 9" × 12"), and anything inside tags (links,
 * image URLs), are left alone.
 */
function monospace_inch_sizes( $text, $portrait = false ) {
    if ( ! is_string( $text ) || ! preg_match( '/\d\s*(?:×|&times;|x)\s*\d/u', $text ) ) return $text;
    $parts = preg_split( '/(<[^>]*>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
    foreach ( $parts as $i => $part ) {
        if ( $part === '' || $part[0] === '<' ) continue;
        $parts[ $i ] = preg_replace_callback(
            '/(?<![\d.″"])(\d{1,3}(?:\.\d+)?)\s*(?:×|&times;|x)\s*(\d{1,3}(?:\.\d+)?)(?![\d.″"])/u',
            function ( $m ) use ( $portrait ) { return monospace_normalize_print_size( $m[1] . 'x' . $m[2], $portrait ); },
            $part
        );
    }
    return implode( '', $parts );
}

// Print sizes with inch marks in the cart, checkout, order pages and emails - Printful's variation
// titles and size names come without them ("Big Brook Park #2 – 8×10") - oriented like the painting
add_filter( 'woocommerce_cart_item_name', function ( $name, $cart_item = [] ) {
    return monospace_inch_sizes( $name, monospace_product_is_portrait( $cart_item['product_id'] ?? 0 ) );
}, 20, 2 );
add_filter( 'woocommerce_get_item_data', function ( $item_data, $cart_item = [] ) {
    $portrait = monospace_product_is_portrait( $cart_item['product_id'] ?? 0 );
    foreach ( (array) $item_data as $k => $d ) {
        foreach ( [ 'value', 'display' ] as $f ) {
            if ( isset( $d[ $f ] ) ) $item_data[ $k ][ $f ] = monospace_inch_sizes( $d[ $f ], $portrait );
        }
    }
    return $item_data;
}, 20, 2 );
add_filter( 'woocommerce_order_item_name', function ( $name, $item = null ) {
    return monospace_inch_sizes( $name, $item && is_callable( [ $item, 'get_product_id' ] ) ? monospace_product_is_portrait( $item->get_product_id() ) : false );
}, 20, 2 );
add_filter( 'woocommerce_order_item_display_meta_value', function ( $value, $meta = null, $item = null ) {
    return monospace_inch_sizes( $value, $item && is_callable( [ $item, 'get_product_id' ] ) ? monospace_product_is_portrait( $item->get_product_id() ) : false );
}, 20, 3 );

/**
 * In-stock variations of a Printful product, as card options.
 * Size comes from pa_size (or the first attribute); any other attribute,
 * e.g. pa_color on framed prints, becomes the 'option' (frame color).
 */
function monospace_get_print_options( $product, $is_portrait ) {
    $options = [];
    foreach ( $product->get_available_variations() as $variation ) {
        $var_obj = wc_get_product( $variation['variation_id'] );
        if ( ! $var_obj || ! $var_obj->is_in_stock() ) continue;

        $size_key = isset( $variation['attributes']['attribute_pa_size'] )
            ? 'attribute_pa_size'
            : array_key_first( $variation['attributes'] );

        $size = $size_slug = $option = $option_slug = '';
        foreach ( $variation['attributes'] as $attr_key => $attr_value ) {
            $term  = get_term_by( 'slug', $attr_value, str_replace( 'attribute_', '', $attr_key ) );
            $label = $term ? $term->name : $attr_value;
            if ( $attr_key === $size_key ) {
                $size      = monospace_normalize_print_size( $label, $is_portrait );
                $size_slug = $attr_value;
            } else {
                $option      = $label;
                $option_slug = $attr_value;
            }
        }

        $options[] = [
            'id'          => $variation['variation_id'],
            'attributes'  => $variation['attributes'],
            'size'        => $size,
            'size_slug'   => $size_slug,
            'option'      => $option,
            'option_slug' => $option_slug,
            'price'       => (float) $var_obj->get_price(),
            'price_html'  => $var_obj->get_price_html(),
        ];
    }

    // Smaller (cheaper) first
    usort( $options, fn( $a, $b ) => $a['price'] <=> $b['price'] );
    return $options;
}

/**
 * Label and spec list for each print type, in tab order.
 */
function monospace_print_types() {
    return [
        'framed' => [
            'label'    => 'Framed Print',
            'specs'    => [
                'Service'  => 'Print on Demand',
                'Product'  => 'Framed matte poster',
                'Paper'    => '10.3 mil, 189 g/m²',
                'Frame'    => '¾″ ayous wood',
                'Front'    => 'Acrylite protector',
                'Hardware' => 'Hanging hardware included',
            ],
        ],
        'unframed' => [
            'label'    => 'Unframed Print',
            'specs'    => [
                'Service'         => 'Print on Demand',
                'Product'         => 'Museum-quality matte poster',
                'Paper thickness' => '10.3 mil',
                'Paper weight'    => '189 g/m²',
                'Frame'           => 'Unframed',
            ],
        ],
    ];
}

/**
 * Print panes for the given Printful products, keyed 'framed' / 'unframed' in tab order.
 * A type is left out when its ID is empty, not a variable product, or has nothing in stock.
 * Each entry: [ 'label' => ..., 'html' => pane contents ].
 */
// The price card's Framed / Unframed print tabs (Core's monospace_price_card_panes, 2026-10-19)
add_filter( 'monospace_price_card_panes', function ( $panes, $post_id ) {
    return $panes + monospace_get_print_panes(
        intval( get_post_meta( $post_id, '_print_product_id', true ) ),
        intval( get_post_meta( $post_id, '_framed_print_product_id', true ) ),
        monospace_post_is_portrait( $post_id )   // print sizes in the painting's orientation
    );
}, 10, 2 );

function monospace_get_print_panes( $unframed_id, $framed_id, $is_portrait ) {
    if ( ! function_exists( 'wc_get_product' ) ) return [];

    $ids   = [ 'framed' => $framed_id, 'unframed' => $unframed_id ];
    $panes = [];
    foreach ( monospace_print_types() as $key => $type ) {
        $product = $ids[ $key ] ? wc_get_product( $ids[ $key ] ) : null;
        if ( ! $product || ! $product->is_type( 'variable' ) ) continue;

        $options = monospace_get_print_options( $product, $is_portrait );
        if ( ! $options ) continue;

        // 'parts' / 'from': for the price card's presets (Core, price-card-presets.php) - the one-line card lays the
        // tab out from its parts, the badge says "Prints from" the lowest price (options are cheapest first)
        $parts = monospace_print_pane_parts( $ids[ $key ], $options, $type['specs'], $product->get_sku(),
            monospace_card_sales_message( $key, $product, get_the_ID() ) );
        $panes[ $key ] = [
            'label' => $type['label'],
            'html'  => monospace_render_card_slots( $parts['info'], $parts['variants'], $parts['buy'], $parts['offer'], $parts['sku'] ),
            'parts' => $parts,
            'from'  => $options[0]['price_html'],
            'from_price' => $options[0]['price'],
        ];
    }
    return $panes;
}



/**
 * Contents of one print pane: spec list, size/frame form and order button for a
 * single Printful product.
 */
function monospace_render_print_pane( $product_id, $options, $specs, $sku = '', $offer = '' ) {
    $p = monospace_print_pane_parts( $product_id, $options, $specs, $sku, $offer );
    return monospace_render_card_slots( $p['info'], $p['variants'], $p['buy'], $p['offer'], $p['sku'] );
}

/**
 * A print pane's pieces: [ info, variants, buy, offer, sku, price ] - price = the selected option's price
 * (.pc-sel-price, kept up to date by the card script), for the price card's one-line preset.
 */
function monospace_print_pane_parts( $product_id, $options, $specs, $sku = '', $offer = '' ) {
    // Unique per card on the page: the same print can be on it twice (desktop + phone presets, a shortcode)
    static $form_count = 0;
    $form_id = 'print-order-' . $product_id . '-' . ( ++$form_count );

    // Sizes and frame options in display order, each listed once
    $sizes = $frames = [];
    foreach ( $options as $opt ) {
        $sizes[ $opt['size_slug'] ] = $opt;
        if ( $opt['option_slug'] !== '' ) $frames[ $opt['option_slug'] ] = $opt['option'];
    }

    $post_id = (int) get_the_ID();
    $variations = array_map( fn( $o ) => [
        'id'         => $o['id'],
        'attributes' => $o['attributes'],
        'size'       => $o['size_slug'],
        'option'     => $o['option_slug'],
        'price_html' => $o['price_html'],
        'preview'    => monospace_print_preview_url( $o['id'], $post_id ),
        'scale'      => function_exists( 'monospace_print_mockup_scale' ) ? monospace_print_mockup_scale( $o['id'] ) : 1,
        'label'      => trim( ( $o['option'] !== '' ? $o['option'] . ' frame, ' : '' ) . $o['size'] ),
    ], $options );

    $info = '';
    foreach ( $specs as $label => $value ) {
        $info .= '<div class="painting-attr"><b>' . esc_html( $label ) . '</b><span class="pa-sep">: </span>' . esc_html( $value ) . '</div>';
    }

    // Selected at first: 8" × 10" (either orientation; with the first frame it comes in), else the
    // first (cheapest) option
    $default = $options[0];
    foreach ( $options as $opt ) {
        preg_match_all( '/\d+(?:\.\d+)?/', $opt['size'], $n );
        $dims = array_map( 'floatval', $n[0] );
        sort( $dims );
        if ( $dims === [ 8.0, 10.0 ] ) { $default = $opt; break; }
    }
    // Each size shows its price in the selected frame (framed prints), until the visitor picks another
    foreach ( $options as $opt ) {
        if ( $opt['option_slug'] === $default['option_slug'] ) $sizes[ $opt['size_slug'] ] = $opt;
    }

    ob_start();
    ?>
            <form class="print-order-form" id="<?php echo esc_attr( $form_id ); ?>" method="get" action="<?php echo esc_url( wc_get_cart_url() ); ?>"
                  data-variations="<?php echo esc_attr( wp_json_encode( $variations ) ); ?>">
                <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product_id ); ?>">
                <input type="hidden" name="quantity" value="1">
                <input type="hidden" name="ms_redirect_cart" value="1">
                <?php // Pre-filled with the default option, so the form works before any JS runs ?>
                <input type="hidden" name="variation_id" value="<?php echo esc_attr( $default['id'] ); ?>">
                <?php foreach ( $default['attributes'] as $attr_key => $attr_value ) : ?>
                    <input type="hidden" name="<?php echo esc_attr( $attr_key ); ?>" value="<?php echo esc_attr( $attr_value ); ?>">
                <?php endforeach; ?>

                <div class="print-size-options">
                    <?php foreach ( $sizes as $slug => $opt ) : ?>
                        <label class="print-size-option">
                            <input type="radio" name="_print_size_ui" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $slug, $default['size_slug'] ); ?>>
                            <span class="print-size-label">
                                <span class="print-size-name"><?php echo esc_html( $opt['size'] ); ?></span>
                                <span class="print-size-price"><?php echo $opt['price_html']; ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <?php if ( $frames ) : ?>
                    <div class="print-frame-options">
                        <?php foreach ( $frames as $slug => $name ) : ?>
                            <label class="print-size-option">
                                <input type="radio" name="_print_frame_ui" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $slug, $default['option_slug'] ); ?>>
                                <span class="print-size-label"><span class="print-size-name"><?php echo esc_html( $name ); ?></span></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </form>
            <?php // Mockup of the selected size / frame - the card script swaps the link and hides it when there's none
            $default_preview = monospace_print_preview_url( $default['id'], $post_id ); ?>
            <a class="print-preview-link" href="<?php echo esc_url( $default_preview ?: '#' ); ?>" role="button"<?php echo $default_preview ? '' : ' hidden'; ?>>See a preview</a>
    <?php
    $variants = ob_get_clean();

    // The button sits in its own slot, outside the form, tied to it by form=""
    return [
        'info'     => $info,
        'variants' => $variants,
        'buy'      => '<button type="submit" form="' . esc_attr( $form_id ) . '" class="painting-buy-button print-order-submit">Add to cart</button>',
        'offer'    => $offer, // from Sales Dashboard > Price Card Messages (monospace_card_sales_message())
        'sku'      => (string) $sku,
        'price'    => '<span class="pc-sel-price">' . $default['price_html'] . '</span>',
    ];
}

/** The mockup to preview for a print variation (drawn on the site, prints/print-mockups.php), or '' */
function monospace_print_preview_url( $variation_id, $post_id = 0 ) {
    return function_exists( 'monospace_print_mockup_url' ) ? monospace_print_mockup_url( $variation_id ) : '';
}

/**
 * Card behaviour, printed once in the footer of any page that rendered a card.
 */
add_action( 'wp_footer', function () {
    if ( empty( $GLOBALS['monospace_print_card_rendered'] ) ) return;
    wp_enqueue_script( 'ms-lightbox' ); // registered in content/lightbox.php; printed with the footer scripts
    ?>
    <style>
    /* "See a preview": its look is set in Layout Manager > Appearance & Effects > Print preview
       (design/print-preview.php) - background (blur / image / pattern / solid colour), tint, spotlight,
       size, shadow, caption */
    <?php echo function_exists( 'monospace_print_preview_css' ) ? monospace_print_preview_css() : ''; ?>
    </style>
    <script>
    // One delegated listener serves every card on the page, including cards
    // appended later by infinite scroll (whose inline scripts don't run).
    document.addEventListener('change', function(e) {
        var sw = e.target.closest('.print-type-switch');
        if (sw) {
            var chosen = e.target.value, from = sw.closest('.print-order-pane');
            sw.closest('.print-order-row').querySelectorAll('.print-order-pane').forEach(function(pane) {
                pane.hidden = pane.dataset.pane !== chosen;
            });
            // One-line card (price card presets): every tab has its own dropdown - the old one goes back to its
            // own tab, focus moves to the one now shown
            if (e.target.tagName === 'SELECT' && from) {
                var shown = sw.closest('.print-order-row').querySelector('.print-order-pane[data-pane="' + chosen + '"] .pc-switch select');
                e.target.value = from.dataset.pane;
                if (shown) { shown.value = chosen; shown.focus(); }
            }
            return;
        }

        // Pick the variation matching the chosen size (and frame), fill the
        // form's hidden fields, and show each size's price for that frame.
        var form = e.target.closest('.print-order-form[data-variations]');
        if (!form) return;
        var variations = JSON.parse(form.dataset.variations);
        var size  = form.querySelector('[name="_print_size_ui"]:checked');
        var frame = form.querySelector('[name="_print_frame_ui"]:checked');
        var opt   = frame ? frame.value : '';

        form.querySelectorAll('[name="_print_size_ui"]').forEach(function(radio) {
            var v = variations.find(function(x) { return x.size === radio.value && x.option === opt; });
            radio.disabled = !v;
            if (v) radio.closest('label').querySelector('.print-size-price').innerHTML = v.price_html;
        });

        var match = size && variations.find(function(x) { return x.size === size.value && x.option === opt; });
        form.querySelector('[name="variation_id"]').value = match ? match.id : '';
        form.closest('.print-order-pane').querySelector('.print-order-submit').disabled = !match;
        if (match) for (var key in match.attributes) {
            var input = form.querySelector('[name="' + key + '"]');
            if (input) input.value = match.attributes[key];
        }
        // The one-line card's price (price card presets): the selected option's
        var selPrice = form.closest('.print-order-pane').querySelector('.pc-sel-price');
        if (selPrice && match) selPrice.innerHTML = match.price_html;

        // "See a preview" follows the selection; no mockup (yet) = no link
        var link = form.closest('.print-order-pane').querySelector('.print-preview-link');
        if (link) {
            link.hidden = !(match && match.preview);
            if (match && match.preview) link.href = match.preview;
        }
    });

    // "See a preview": the selected variation's mockup in the site's lightbox (assets/scripts/ms-lightbox.js,
    // since 2026-10-19; GLightbox before); framed prints swipe through the other frames in the same size.
    // Its look: the Print preview design module (design/print-preview.php) - CSS above, options here.
    (function () {
        var look = <?php echo wp_json_encode( function_exists( 'monospace_print_preview_js_options' ) ? monospace_print_preview_js_options() : [ 'open' => 'none', 'caption' => false, 'relative' => true ] ); ?>;
        document.addEventListener('click', function (e) {
            var link = e.target.closest('.print-preview-link');
            if (!link || !window.msLightbox) return; // no lightbox: the link opens the image itself
            var form = link.closest('.print-order-pane').querySelector('.print-order-form[data-variations]');
            if (!form) return;
            e.preventDefault();
            var variations = JSON.parse(form.dataset.variations);
            var size = form.querySelector('[name="_print_size_ui"]:checked');
            var current = link.getAttribute('href'), seen = {}, list = [], start = 0;
            variations.forEach(function (v) {
                if (!v.preview || seen[v.preview] || (size && v.size !== size.value)) return;
                seen[v.preview] = true;
                if (v.preview === current) start = list.length;
                list.push({ src: v.preview, label: v.label, scale: look.relative ? v.scale : 1 });
            });
            if (!list.length) list.push({ src: current, label: '' });
            msLightbox.open(list, start, { skin: 'ms-pp', open: look.open, caption: look.caption, zoomBackground: !!look.zoomBackground });
        });
    })();
    </script>
    <?php
} );

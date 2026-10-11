<?php
/**
 * Print Order Card
 *
 * Print ordering panes for Printful variable WooCommerce products, chosen via
 * size radio options; the framed pane adds frame-color options.
 *
 * On blog posts the panes are tabs on the painting card ([painting_buy_button],
 * see monospace_render_painting_card()), read from the post's _print_product_id /
 * _framed_print_product_id meta (set in the Quick Product Editor). On the print
 * category archive a print-only card (Framed Print | Unframed Print) is appended.
 *
 * LEGACY SHORTCODE:
 * [print_order_button id="PRODUCT_ID"]
 * Renders nothing in a post that has a [painting_buy_button] (that card already
 * shows the prints); otherwise a print-only card.
 *
 * PARAMETERS:
 * - id (required): WooCommerce variable product ID for the unframed print
 * - framed (optional): variable product ID for the framed print. Defaults to the
 *   post's _framed_print_product_id meta.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'print_order_button', function ( $atts ) {
    $post = get_post();
    if ( $post && has_shortcode( $post->post_content, 'painting_buy_button' ) ) return '';
    return monospace_print_order_shortcode( $atts );
} );


/**
 * Normalize a size string from any format to W" × H".
 * Optionally swap W and H for portrait orientation.
 *
 * Handles: "16x20", "16×20", "16 x 20", "16"×20"", "16″×20″", "16 × 20"
 * Returns: '16" × 20"' or '20" × 16"' depending on $portrait flag.
 */
function monospace_normalize_print_size( $raw, $portrait = false ) {
    // Strip all quote/inch characters and collapse whitespace
    $cleaned = preg_replace( '/["""″\'`]/u', '', $raw );
    $cleaned = trim( $cleaned );

    // Extract the two numbers (integers or decimals)
    if ( ! preg_match( '/^(\d+(?:\.\d+)?)\s*[×xX]\s*(\d+(?:\.\d+)?)$/', $cleaned, $m ) ) {
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
 * Label, spec list and shipping note for each print type, in tab order.
 */
function monospace_print_types() {
    return [
        'framed' => [
            'label'    => 'Framed Print',
            'shipping' => false,
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
            'shipping' => true,
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
function monospace_get_print_panes( $unframed_id, $framed_id, $is_portrait ) {
    if ( ! function_exists( 'wc_get_product' ) ) return [];

    $ids   = [ 'framed' => $framed_id, 'unframed' => $unframed_id ];
    $panes = [];
    foreach ( monospace_print_types() as $key => $type ) {
        $product = $ids[ $key ] ? wc_get_product( $ids[ $key ] ) : null;
        if ( ! $product || ! $product->is_type( 'variable' ) ) continue;

        $options = monospace_get_print_options( $product, $is_portrait );
        if ( ! $options ) continue;

        $panes[ $key ] = [
            'label' => $type['label'],
            'html'  => monospace_render_print_pane( $ids[ $key ], $options, $type['specs'], $type['shipping'], $product->get_sku() ),
        ];
    }
    return $panes;
}

/**
 * A card made of stacked panes with a segmented switch on top (only when there's
 * more than one pane). $panes: key => [ 'label', 'html' ]; $active is shown first.
 * Hidden panes keep their size, so the card is as tall as its tallest pane and
 * nothing jumps when switching.
 */
function monospace_render_tabbed_card( $name, $panes, $active, $row_class = '', $status = null ) {
    ob_start();
    ?>
    <div class="painting-buy-row print-order-row<?php echo esc_attr( $row_class ); ?>"<?php if ( $status !== null ) echo ' data-status="' . esc_attr( $status ) . '"'; ?>>
        <?php if ( count( $panes ) > 1 ) : ?>
            <div class="print-type-switch" role="radiogroup" aria-label="Purchase options">
                <?php foreach ( $panes as $key => $pane ) : ?>
                    <label><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $key, $active ); ?>><span><?php echo esc_html( $pane['label'] ); ?></span></label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php foreach ( $panes as $key => $pane ) : ?>
            <div class="print-order-pane card-panel" data-pane="<?php echo esc_attr( $key ); ?>"<?php echo $key === $active ? '' : ' hidden'; ?>>
                <?php echo $pane['html']; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    $GLOBALS['monospace_print_card_rendered'] = true;
    return ob_get_clean();
}

/**
 * The slots of a card pane, laid out by .card-panel:
 *   info | variants
 *   sku  | buy
 *   offer (full width)
 * The offer slot is always present (empty when there's nothing to say), so the
 * button sits at the same height in every pane of a tabbed card.
 */
function monospace_render_card_slots( $info, $variants, $buy, $offer, $sku = '' ) {
    return '<div class="painting-attrs">' . $info . '</div>'
         . '<div class="card-variants">' . $variants . '</div>'
         . ( $sku !== '' ? '<div class="painting-sku">' . esc_html( $sku ) . '</div>' : '' )
         . '<div class="card-buy">' . $buy . '</div>'
         . '<div class="card-offer">' . $offer . '</div>';
}

/**
 * Contents of one print pane: spec list, size/frame form and order button for a
 * single Printful product.
 */
function monospace_render_print_pane( $product_id, $options, $specs, $show_shipping, $sku = '' ) {
    $form_id = 'print-order-' . $product_id;

    // Sizes and frame options in display order, each listed once
    $sizes = $frames = [];
    foreach ( $options as $opt ) {
        $sizes[ $opt['size_slug'] ] = $opt;
        if ( $opt['option_slug'] !== '' ) $frames[ $opt['option_slug'] ] = $opt['option'];
    }

    $variations = array_map( fn( $o ) => [
        'id'         => $o['id'],
        'attributes' => $o['attributes'],
        'size'       => $o['size_slug'],
        'option'     => $o['option_slug'],
        'price_html' => $o['price_html'],
    ], $options );

    $info = '';
    foreach ( $specs as $label => $value ) {
        $info .= '<div class="painting-attr"><b>' . esc_html( $label ) . '</b>: ' . esc_html( $value ) . '</div>';
    }

    ob_start();
    ?>
            <form class="print-order-form" id="<?php echo esc_attr( $form_id ); ?>" method="get" action="<?php echo esc_url( wc_get_cart_url() ); ?>"
                  data-variations="<?php echo esc_attr( wp_json_encode( $variations ) ); ?>">
                <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product_id ); ?>">
                <input type="hidden" name="quantity" value="1">
                <input type="hidden" name="ms_redirect_cart" value="1">
                <?php // Pre-filled with the default (first) option, so the form works before any JS runs ?>
                <input type="hidden" name="variation_id" value="<?php echo esc_attr( $options[0]['id'] ); ?>">
                <?php foreach ( $options[0]['attributes'] as $attr_key => $attr_value ) : ?>
                    <input type="hidden" name="<?php echo esc_attr( $attr_key ); ?>" value="<?php echo esc_attr( $attr_value ); ?>">
                <?php endforeach; ?>

                <div class="print-size-options">
                    <?php $i = 0; foreach ( $sizes as $slug => $opt ) : ?>
                        <label class="print-size-option">
                            <input type="radio" name="_print_size_ui" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $i++, 0 ); ?>>
                            <span class="print-size-label">
                                <span class="print-size-name"><?php echo esc_html( $opt['size'] ); ?></span>
                                <span class="print-size-price"><?php echo $opt['price_html']; ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <?php if ( $frames ) : ?>
                    <div class="print-frame-options">
                        <?php $i = 0; foreach ( $frames as $slug => $name ) : ?>
                            <label class="print-size-option">
                                <input type="radio" name="_print_frame_ui" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $i++, 0 ); ?>>
                                <span class="print-size-label"><span class="print-size-name"><?php echo esc_html( $name ); ?></span></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </form>
    <?php
    $variants = ob_get_clean();

    // The button sits in its own slot, outside the form, tied to it by form=""
    return monospace_render_card_slots(
        $info,
        $variants,
        '<button type="submit" form="' . esc_attr( $form_id ) . '" class="painting-buy-button print-order-submit">Add to cart</button>',
        $show_shipping ? '<div class="print-free-shipping">Free shipping!</div>' : '',
        (string) $sku
    );
}

function monospace_print_order_shortcode( $atts ) {
    $atts = shortcode_atts( [ 'id' => 0, 'framed' => null, 'orientation' => 'landscape' ], $atts, 'print_order_button' );
    $product_id  = intval( $atts['id'] );
    $is_portrait = ( strtolower( $atts['orientation'] ) === 'portrait' );

    if ( ! $product_id ) return '';

    if ( monospace_is_editor_context() ) {
        return '<div class="painting-buy-placeholder" style="padding:6px;border:1px dashed #aaa;background:#f9f9f9;">'
             . 'Print Order Button (Product ID ' . esc_html( $product_id ) . ')'
             . '</div>';
    }

    // Framed version: explicit attribute, else the post's meta
    $framed_id = $atts['framed'] !== null ? intval( $atts['framed'] ) : intval( get_post_meta( get_the_ID(), '_framed_print_product_id', true ) );

    $panes = monospace_get_print_panes( $product_id, $framed_id, $is_portrait );
    if ( ! isset( $panes['unframed'] ) ) return '';

    return monospace_render_tabbed_card( 'print-type-' . $product_id, $panes, 'unframed' );
}

/**
 * Card behaviour, printed once in the footer of any page that rendered a card.
 */
add_action( 'wp_footer', function () {
    if ( empty( $GLOBALS['monospace_print_card_rendered'] ) ) return;
    ?>
    <script>
    // One delegated listener serves every card on the page, including cards
    // appended later by infinite scroll (whose inline scripts don't run).
    document.addEventListener('change', function(e) {
        var sw = e.target.closest('.print-type-switch');
        if (sw) {
            sw.closest('.print-order-row').querySelectorAll('.print-order-pane').forEach(function(pane) {
                pane.hidden = pane.dataset.pane !== e.target.value;
            });
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
    });
    </script>
    <?php
} );


/**
 * Append the print order card on the print category archive page.
 * Fires on the_content filter (same pattern as clickable-feed-image.php).
 */
add_filter( 'the_content', 'monospace_append_print_card_on_archive', 20 );

function monospace_append_print_card_on_archive( $content ) {
    if ( ! is_category( 'print' ) ) return $content;
    if ( is_admin() ) return $content;

    global $post;
    if ( ! $post ) return $content;

    $print_product_id = intval( get_post_meta( $post->ID, '_print_product_id', true ) );
    if ( ! $print_product_id ) return $content;

    $card = monospace_print_order_shortcode( [ 'id' => $print_product_id, 'orientation' => 'portrait' ] );
    if ( ! $card ) return $content;

    return $content . $card;
}

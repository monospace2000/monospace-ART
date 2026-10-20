<?php
/**
 * The price card's layout and the artwork's orientation (2026-10-19: split out of the Prints module, where they
 * had grown up - the price card is Core, and its tabs work with or without Prints).
 *
 * - monospace_post_is_portrait() / monospace_product_is_portrait(): the original's orientation, from its size
 *   (print sizes and the size line follow it)
 * - monospace_render_tabbed_card(): a card made of panes with a switch on top - the Original pane plus any tabs
 *   features add through the monospace_price_card_panes filter (buy-button-shortcode.php)
 * - monospace_render_card_slots(): the slots of one pane
 * The tab switch's script lives with the feature that adds tabs (Prints: print-order-shortcode.php's footer),
 * as a card only has a switch when it has more than one pane.
 *
 * @package monospace-art-core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Whether a post's painting is portrait (taller than wide), from its original's size, e.g. 8" × 10".
 * Print sizes are written in the same orientation as the painting, so they match the original's.
 */
function monospace_post_is_portrait( $post_id ) {
    static $cache = [];
    $post_id = (int) $post_id;
    if ( ! $post_id ) return false;
    if ( isset( $cache[ $post_id ] ) ) return $cache[ $post_id ];

    $portrait   = false;
    $product_id = function_exists( 'monospace_get_post_product_id' ) ? monospace_get_post_product_id( $post_id ) : (int) get_post_meta( $post_id, '_qp_product_id', true );
    $names      = $product_id ? wp_get_object_terms( $product_id, 'pa_size', [ 'fields' => 'names' ] ) : [];
    // Exactly two numbers (W × H) - a miniature set ("3 × 4" × 4"") or "Other" says nothing
    if ( $names && ! is_wp_error( $names )
        && preg_match( '/^\D*(\d+(?:\.\d+)?)\D+(\d+(?:\.\d+)?)\D*$/u', html_entity_decode( $names[0], ENT_QUOTES, 'UTF-8' ), $m ) ) {
        $portrait = (float) $m[1] < (float) $m[2];
    }
    return $cache[ $post_id ] = $portrait;
}

/** Same, for a product (a print or an original) - via the post it belongs to (_related_post_id) */
function monospace_product_is_portrait( $product_id ) {
    $product_id = (int) $product_id;
    $post_id    = $product_id ? (int) get_post_meta( $product_id, '_related_post_id', true ) : 0;
    return $post_id ? monospace_post_is_portrait( $post_id ) : false;
}

/**
 * A card made of stacked panes with a segmented switch on top (only when there's
 * more than one pane). $panes: key => [ 'label', 'html', 'struck' (optional: label struck
 * through, e.g. a sold original) ]; $active is shown first.
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
                    <label><input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( $key, $active ); ?>><span><?php echo empty( $pane['struck'] ) ? esc_html( $pane['label'] ) : '<s>' . esc_html( $pane['label'] ) . '</s>'; ?></span></label>
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

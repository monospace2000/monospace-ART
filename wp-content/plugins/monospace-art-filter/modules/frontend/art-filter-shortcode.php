<?php
/**
 * [monospace_art_filter] - the Art Filter page: dropdowns for the artworks' details (format, medium, surface, size) and
 * availability, each with EXCLUDE; a price range while "Available" is chosen; the viewing toolbar (sort, and grid /
 * list / price card view - the icons as on category pages); the results, loaded by ajax (art-filter-results.php).
 *
 * The look and the script are assets/art-filter.css and .js, loaded only where the shortcode is (registered here,
 * enqueued in <head> when the page's content has the shortcode, else by the shortcode itself).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', function () {
    $file = fn( $f ) => MONOSPACE_ART_FILTER_DIR . '/assets/' . $f;
    wp_register_style( 'ms-art-filter', MONOSPACE_ART_FILTER_URL . 'assets/art-filter.css', [], (string) filemtime( $file( 'art-filter.css' ) ) );
    wp_register_script( 'ms-art-filter', MONOSPACE_ART_FILTER_URL . 'assets/art-filter.js', [ 'jquery' ], (string) filemtime( $file( 'art-filter.js' ) ), true );
    wp_localize_script( 'ms-art-filter', 'msArtFilter', [ 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ] );
    if ( is_singular() && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'monospace_art_filter' ) ) {
        wp_enqueue_style( 'ms-art-filter' );
    }
} );

/** The products the filter works on: published originals linked to a PUBLISHED artwork post (no prints, no custom orders) */
function monospace_art_filter_product_ids() {
    static $ids = null;
    if ( $ids !== null ) return $ids;
    $ids = get_posts( [
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'tax_query'      => [ [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => [ 'custom-order', 'print' ], 'operator' => 'NOT IN' ] ],
        'meta_query'     => [ [ 'key' => '_related_post_id', 'value' => [ '', '0' ], 'compare' => 'NOT IN' ] ],
    ] );

    // The artwork is the post: a product whose post is a draft (or private, trashed) stays out (owner, 2026-11-12: drafts showed up)
    if ( $ids ) {
        global $wpdb;
        $in        = implode( ',', array_map( 'intval', $ids ) );
        $published = array_map( 'intval', $wpdb->get_col(
            "SELECT m.post_id FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} a ON a.ID = CAST( m.meta_value AS UNSIGNED )
             WHERE m.meta_key = '_related_post_id' AND a.post_status = 'publish' AND m.post_id IN ($in)"
        ) );
        $ids = array_values( array_intersect( $ids, $published ) );
    }
    return $ids;
}

/** The availability choices: '' = all, then the statuses (Core's names) */
function monospace_art_filter_availabilities() {
    $names = function_exists( 'monospace_artwork_statuses' ) ? monospace_artwork_statuses() : [];
    $out   = [ '' => 'All Availability' ];
    foreach ( [ 'available', 'reserved', 'sold', 'private', 'gallery' ] as $key ) $out[ $key ] = $names[ $key ] ?? ucfirst( $key );
    return $out;
}

add_shortcode( 'monospace_art_filter', 'ms_product_filter_shortcode' );
function ms_product_filter_shortcode() {
    wp_enqueue_style( 'ms-art-filter' ); // printed in the footer when <head> didn't see the shortcode
    wp_enqueue_script( 'ms-art-filter' );

    // The price range: the originals' prices (as sold - get_price() runs the Sales Dashboard's filters)
    $prices = [];
    foreach ( monospace_art_filter_product_ids() as $product_id ) {
        $product = wc_get_product( $product_id );
        if ( $product && $product->get_price() ) $prices[] = (float) $product->get_price();
    }
    $min_price = $prices ? (int) floor( min( $prices ) ) : 0;
    $max_price = $prices ? (int) ceil( max( $prices ) ) : 1000;

    // Price card view: the cards' scripts on this page (results come in later, by ajax) - the tab switch and print
    // options (Prints plugin, printed once a card was rendered) and the standalone card's picture
    $cards = shortcode_exists( 'price_card_standalone' );
    if ( $cards ) {
        $GLOBALS['monospace_print_card_rendered'] = true;
        if ( function_exists( 'monospace_pc_standalone_script' ) ) monospace_pc_standalone_script();
    }

    // A dropdown with its EXCLUDE box
    $select = function ( $name, $label, $options ) { ?>
        <label><?php echo esc_html( $label ); ?>:</label>
        <select name="<?php echo esc_attr( $name ); ?>" class="ms-filter-select" data-not-checkbox="<?php echo esc_attr( $name ); ?>_not_checkbox">
            <?php foreach ( $options as $value => $text ) : ?>
                <option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $text ); ?></option>
            <?php endforeach; ?>
        </select>
        <label>
            <input type="checkbox" id="<?php echo esc_attr( $name ); ?>_not_checkbox" name="<?php echo esc_attr( $name ); ?>_not" class="ms-filter-not" disabled>
            &nbsp;&nbsp;EXCLUDE
        </label>
    <?php };

    ob_start(); ?>
<div class="ms-product-filter-container">

    <div class="ms-product-filter-form">
        <div class="ms-filter-spinner"><span class="woocommerce-spinner ms-big-spinner"></span></div>

        <?php foreach ( [ 'pa_format', 'pa_medium', 'pa_surface', 'pa_size' ] as $tax ) {
            $terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => true ] );
            if ( ! $terms || is_wp_error( $terms ) ) continue;
            // Size: only sizes an original has (prints have their own)
            if ( $tax === 'pa_size' ) {
                $terms = array_filter( $terms, function ( $term ) {
                    foreach ( get_objects_in_term( $term->term_id, 'pa_size' ) as $pid ) {
                        if ( ! has_term( 'print', 'product_cat', $pid ) ) return true;
                    }
                    return false;
                } );
            }
            if ( ! $terms ) continue;
            $label = ucfirst( str_replace( 'pa_', '', $tax ) );
            $select( $tax, $label, [ '' => 'All ' . $label . 's' ] + wp_list_pluck( $terms, 'name', 'slug' ) );
        }
        $select( 'availability', 'Availability', monospace_art_filter_availabilities() ); ?>
    </div>

    <?php // The price range, while "Available" is chosen: one slider, two handles (the two inputs laid over one track) ?>
    <div id="ms-price-range-container" class="ms-price-range" hidden>
        <div class="ms-range-head"><span class="ms-range-title">Price Range:</span>
            <span class="ms-range-values"><span id="ms-price-min-display">$<?php echo $min_price; ?></span> – <span id="ms-price-max-display">$<?php echo $max_price; ?></span></span></div>
        <div class="ms-range">
            <div class="ms-range-track"></div><div class="ms-range-fill"></div>
            <input type="range" id="ms-price-min" min="<?php echo $min_price; ?>" max="<?php echo $max_price; ?>" value="<?php echo $min_price; ?>" aria-label="Lowest price">
            <input type="range" id="ms-price-max" min="<?php echo $min_price; ?>" max="<?php echo $max_price; ?>" value="<?php echo $max_price; ?>" aria-label="Highest price">
        </div>
    </div>

    <div class="ms-filter-legend"><?php echo function_exists( 'monospace_dots_legend' ) ? monospace_dots_legend() . '.' : 'A green dot <span class="ms-availability-dot"></span> indicates item availability; a blue dot <span class="ms-availability-dot is-gallery"></span> an item at a gallery; a yellow dot <span class="ms-availability-dot is-reserved"></span> a reserved item.'; ?> <span class="ms-miniature-mark">M</span> marks a miniature or a miniature set.</div>

    <div class="ms-filter-actions">
        <div id="ms-filter-count">Found 0 items</div>
        <?php // The viewing toolbar: sort, and the view - grid / list / price card (as on category pages) ?>
        <div class="ms-filter-toolbar">
            <label class="ms-filter-sort"><span class="screen-reader-text">Sort</span>
                <select id="ms-sort">
                    <option value="newest">Newest first</option><option value="oldest">Oldest first</option>
                    <option value="price-asc">Price: low to high</option><option value="price-desc">Price: high to low</option>
                    <option value="size-asc">Size: small to large</option><option value="size-desc">Size: large to small</option>
                    <option value="random">Random</option>
                </select></label>
            <?php // Inside .archive-view-toggle, so the site's button look (glass / flat / outline) leaves the icons alone and
                  // the theme's icon style applies (_archives.scss) ?>
            <span class="archive-view-toggle ms-view-group">
            <span class="archive-view-label ms-view-label" aria-hidden="true">View as</span>
            <button type="button" class="ms-view-btn" data-view="grid" aria-label="Grid view" title="Grid view"><svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true"><rect x="2" y="2" width="7" height="7" rx="1"/><rect x="11" y="2" width="7" height="7" rx="1"/><rect x="2" y="11" width="7" height="7" rx="1"/><rect x="11" y="11" width="7" height="7" rx="1"/></svg></button>
            <button type="button" class="ms-view-btn" data-view="list" aria-label="List view" title="List view"><svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true"><rect x="2" y="3" width="16" height="3" rx="1"/><rect x="2" y="8.5" width="16" height="3" rx="1"/><rect x="2" y="14" width="16" height="3" rx="1"/></svg></button>
            <button type="button" class="ms-view-btn" data-view="detail" aria-label="Detail list view" title="Detail list view - picture on the left, details on the right"><svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true"><rect x="2" y="2.5" width="5" height="5" rx="1"/><rect x="9" y="3" width="9" height="1.6" rx=".8"/><rect x="9" y="6" width="6" height="1.4" rx=".7"/><rect x="2" y="12.5" width="5" height="5" rx="1"/><rect x="9" y="13" width="9" height="1.6" rx=".8"/><rect x="9" y="16" width="6" height="1.4" rx=".7"/></svg></button>
            <?php if ( $cards ) : ?>
            <button type="button" class="ms-view-btn" data-view="cards" aria-label="Price card view" title="Price card view - each artwork with its price card"><svg viewBox="0 0 20 20" width="20" height="20" aria-hidden="true"><rect x="2" y="3" width="7" height="6" rx="1"/><rect x="11" y="3" width="7" height="1.6" rx=".8"/><rect x="11" y="6" width="5" height="1.6" rx=".8"/><rect x="2" y="11" width="7" height="6" rx="1"/><rect x="11" y="11" width="7" height="1.6" rx=".8"/><rect x="11" y="14" width="5" height="1.6" rx=".8"/></svg></button>
            <?php endif; ?>
            </span>
        </div>
        <button type="button" id="ms-reset-filters" class="ms-filter-btn">Reset All</button>
    </div>

    <div class="ms-product-grid"></div>
    <p class="ms-load-more-wrap" hidden><button type="button" id="ms-load-more" class="ms-filter-btn">Show more</button></p>

</div>
    <?php
    return ob_get_clean();
}

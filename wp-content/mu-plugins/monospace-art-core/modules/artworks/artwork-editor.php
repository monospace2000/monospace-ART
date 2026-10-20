<?php
/**
 * Artworks > Artwork Editor (2026-10-19, owner's wish) - every artwork in one table, edited in place.
 *
 * One row per artwork post with a linked product (_qp_product_id): thumbnail, the SKU's number (big - how
 * the owner identifies a piece), title (= product name), price, availability, format, medium, surface,
 * size, year, and the post's categories and tags (a small editor opens from the cell); prints and the
 * post's status are shown, not edited. Search (title, number, SKU, tags), filters, sorting, 50 per page;
 * select rows for bulk changes ("Apply to selected": marked, sent by Save; the Prints… menu's "Run now" acts at once) (availability, a detail, price: set / ± % / ± $, add / remove a category or
 * tags; Sales: sale price % off / $ off / set to $ / remove, …). Changed rows are highlighted, and Save
 * sends only those. Format / medium / size set their categories on their own (artwork-categories.php,
 * every screen). The categories the site manages itself
 * - print (prints linked / shown) and available (never assigned) - aren't offered and are kept as they
 * are (MONOSPACE_ARTWORK_EDITOR_LOCKED_CATS).
 *
 * Saving goes through the Quick Artwork Editor's own ProductSyncer (QuickProduct\ProductSyncer), as its
 * button does: the row's complete current data (as the box loads it, incl. print links and gallery
 * fields) with the edited fields replaced - ProductSyncer clears whatever it isn't given, e.g. the print
 * links. Each saved post's page cache is purged.
 *
 * Needs attention (2026-10-19): a filter for rows with a post title that differs from the product name
 * (fix either way, or "Meant to differ" - post meta _ms_title_differs_ok), a missing format / medium /
 * year, no price on an available / reserved / Gallery QR piece (sold, private and gallery ones may have none - "--"), or no image; plus a note listing Original products whose artwork post is gone.
 *
 * Sales view (2026-10-19, owner's wish - quick sales edits here, the Sales Dashboard stays for the rest):
 * the WooCommerce sale price, the dashboard's per-product overrides (_msd_enable_sale: follow the rules /
 * always / never, _msd_additional_percentage: extra %, _msd_exclude_price_adj: skip the global adjustment)
 * and the shop price - what a customer pays, with the dashboard's rules applied. That price only exists
 * outside the admin screen (MSD_Price_Adjustments skips is_admin() unless DOING_AJAX), so the rows load by
 * AJAX (monospace_artwork_editor_rows). A line above the table shows the dashboard's global settings,
 * linked to its sections (WooCommerce > Settings > Sales Dashboard, tab msd).
 *
 * Prints (2026-10-19): Show prints / Hide prints right in the row (the main print control - owner's
 * wish: more important than print-job progress, which the Prints screens show in full); the F/U chips open
 * a panel - both prints at once first, then each print on its own: Show / Hide framed print, Add framed
 * print (a background job: every size / frame at the default prices, sizes the image is too small for left
 * out - as New artwork does) / Remove framed print (monospace_prints_remove(): out of Printful, product to
 * the Trash, unlinked), the same for unframed. Hide = prints-pause.php: off the price card and the site,
 * nothing deleted, per print or both. A running / stopped job: "Being made →" / "Print job stopped →",
 * linked to Prints > All prints. Also in bulk. These happen at once, not on Save
 * (AJAX monospace_artwork_editor_prints).
 * Naming, the same on every screen: Show prints / Hide prints, Show framed print / Hide framed print,
 * Add framed print / Remove framed print (… unframed print); states Shown / Hidden / Being made / No print.
 *
 * Artwork Sheets (2026-10-19): "Open in Artwork Sheets" sends the selection to the Artwork Sheets generator
 * (admin.php?page=monospace-artwork-sheets&select=<product IDs>), and the generator's "Open in Artwork
 * Editor" comes back the same way (&select=<product IDs>): those rows are selected and shown (filter
 * "Selected only"), ready for bulk changes. Product IDs - the generator lists products.
 *
 * Detail dropdowns (format, medium, surface, size, year - in the rows and the bulk "Set a detail") start with
 * a "Recent & frequent" group (2026-10-19, owner's wish): the values picked last in this editor (kept in the
 * browser, localStorage msAeRecent), then those of the newest artworks, then the most used - up to six -
 * followed by the full list.
 *
 * Lists: "Save list" downloads the selection, "Load list" selects a saved one (filter "Selected only").
 * Artwork list files (2026-10-19, owner's wish: lists only, never properties) - shared by Artworks > Artwork
 * Editor and Artwork Sheets: { "type": "monospace-artwork-list", "version": 1, "saved": <ISO date>, "from": <tool>,
 * "artworks": [ { "product_id", "post_id", "sku" } ] } - which artworks, nothing about them. Loading also accepts
 * Artwork Sheets' "Export JSON" files (an array of rows: their wc_product_id / wp_post_id both hold the product
 * ID) and reads only the IDs from them.
 *
 * AJAX: monospace_artwork_editor_rows (all rows), monospace_artwork_editor_save (rows: post_id => changes),
 * which returns the fresh rows.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_ARTWORK_EDITOR_ATTRS = [ 'pa_format', 'pa_medium', 'pa_surface', 'pa_size', 'pa_year-created' ];
const MONOSPACE_ARTWORK_EDITOR_LOCKED_CATS = [ 'print', 'available' ];

/** Ids of the categories the site manages itself */
function monospace_artwork_editor_locked_cats() {
    return array_values( array_filter( array_map( function ( $slug ) { $c = get_category_by_slug( $slug ); return $c ? (int) $c->term_id : 0; }, MONOSPACE_ARTWORK_EDITOR_LOCKED_CATS ) ) );
}

/** Categories to offer: [ id, label ] - parents before their children ("Parent › Child"), locked ones left out */
function monospace_artwork_editor_categories() {
    $all = get_categories( [ 'hide_empty' => false ] );
    $by = []; foreach ( $all as $c ) $by[ $c->term_id ] = $c;
    $locked = monospace_artwork_editor_locked_cats();
    $out = [];
    foreach ( $all as $c ) {
        if ( in_array( (int) $c->term_id, $locked, true ) ) continue;
        $name = html_entity_decode( $c->name, ENT_QUOTES, 'UTF-8' );
        for ( $p = $c->parent; $p && isset( $by[ $p ] ); $p = $by[ $p ]->parent ) $name = html_entity_decode( $by[ $p ]->name, ENT_QUOTES, 'UTF-8' ) . ' › ' . $name;
        $out[] = [ (int) $c->term_id, $name ];
    }
    usort( $out, fn( $a, $b ) => strcasecmp( $a[1], $b[1] ) );
    return $out;
}

add_action( 'admin_menu', function () {
    if ( ! class_exists( '\QuickProduct\ProductSyncer' ) ) return;
    add_submenu_page( 'monospace-artworks', 'Artwork Editor', 'Artwork Editor', 'edit_products', 'monospace-artwork-editor', 'monospace_artwork_editor_page' );
}, 20 );

/* ============================================================
 * DATA
 * ============================================================ */

/** Rows for the given artwork posts (all artworks when null) */
function monospace_artwork_editor_rows( $post_ids = null ) {
    $posts = get_posts( [
        'post_type'   => 'post',
        'post_status' => [ 'publish', 'draft', 'pending', 'private', 'future' ],
        'numberposts' => -1,
        'post__in'    => $post_ids ?: [],
        'meta_key'    => '_qp_product_id',
        'orderby'     => 'date',
        'order'       => 'DESC',
    ] );
    $products = [];
    foreach ( $posts as $post ) {
        $pid = (int) get_post_meta( $post->ID, '_qp_product_id', true );
        if ( $pid && get_post_type( $pid ) === 'product' ) $products[ $post->ID ] = $pid;
    }
    if ( ! $products ) return [];

    // Prime the caches: product meta, attribute terms, thumbnails - one query each, not one per row
    update_meta_cache( 'post', array_values( $products ) );
    _prime_post_caches( array_values( $products ), false, false );
    $terms = [];
    $found = wp_get_object_terms( array_values( $products ), MONOSPACE_ARTWORK_EDITOR_ATTRS, [ 'fields' => 'all_with_object_id' ] );
    if ( ! is_wp_error( $found ) ) foreach ( $found as $t ) $terms[ $t->object_id ][ $t->taxonomy ] = $t->slug;
    $post_terms = [];
    $found = wp_get_object_terms( array_keys( $products ), [ 'category', 'post_tag' ], [ 'fields' => 'all_with_object_id' ] );
    if ( ! is_wp_error( $found ) ) foreach ( $found as $t ) $post_terms[ $t->object_id ][ $t->taxonomy ][] = $t->taxonomy === 'category' ? (int) $t->term_id : html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' );
    $thumbs = array_filter( array_map( 'get_post_thumbnail_id', array_keys( $products ) ) );
    if ( $thumbs ) { _prime_post_caches( $thumbs, false, true ); }

    $rows = [];
    foreach ( $posts as $post ) {
        if ( ! isset( $products[ $post->ID ] ) ) continue;
        $pid   = $products[ $post->ID ];
        $sku   = (string) get_post_meta( $pid, '_sku', true );
        $stock = get_post_meta( $pid, '_stock', true );
        // As the Quick Artwork Editor loads it: no stock = sold, else the saved status
        $availability = ( $stock === '' || (int) $stock <= 0 ) ? 'sold' : ( get_post_meta( $pid, 'painting_availability_status', true ) ?: 'available' );
        $attrs = [];
        foreach ( MONOSPACE_ARTWORK_EDITOR_ATTRS as $tax ) $attrs[ $tax ] = $terms[ $pid ][ $tax ] ?? '';
        $rows[] = [
            'post_id'      => $post->ID,
            'product_id'   => $pid,
            'status'       => $post->post_status,
            'date'         => get_the_date( 'Y-m-d', $post ),
            'title'        => html_entity_decode( get_the_title( $pid ), ENT_QUOTES, 'UTF-8' ),
            'post_title'   => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
            'title_ok'     => get_post_meta( $post->ID, '_ms_title_differs_ok', true ) ? '1' : '',
            'price'        => (string) get_post_meta( $pid, '_regular_price', true ),
            'sku'          => $sku,
            'num'          => preg_match( '/(\d+)\D*$/', $sku, $m ) ? (int) $m[1] : null,
            'availability' => $availability,
            'attributes'   => $attrs,
            'categories'   => monospace_artwork_editor_sorted( $post_terms[ $post->ID ]['category'] ?? [], true ),
            'tags'         => monospace_artwork_editor_sorted( $post_terms[ $post->ID ]['post_tag'] ?? [], false ),
            'sale'         => (string) get_post_meta( $pid, '_sale_price', true ),
            'sale_mode'    => (string) get_post_meta( $pid, '_msd_enable_sale', true ),
            'extra'        => (string) get_post_meta( $pid, '_msd_additional_percentage', true ),
            'no_adj'       => get_post_meta( $pid, '_msd_exclude_price_adj', true ) === 'yes' ? '1' : '',
            'shop'         => monospace_artwork_editor_shop_price( $pid ),
            'prints'       => monospace_artwork_editor_prints_state( $post->ID ),
            'card'         => function_exists( 'monospace_price_card_post_style' ) ? monospace_price_card_post_style( $post->ID ) : '', // '' = site setting
            'thumb'        => (string) get_the_post_thumbnail_url( $post, 'thumbnail' ),
            'preview'      => (string) get_the_post_thumbnail_url( $post, 'medium_large' ), // the rollover preview
            'edit'         => get_edit_post_link( $post->ID, 'raw' ),
            'view'         => get_permalink( $post ),
        ];
    }
    return $rows;
}

/** An artwork's prints: ids (0 = none), hidden (per print), job (a running / failed print job) */
function monospace_artwork_editor_prints_state( $post_id ) {
    $job = function_exists( 'monospace_prints_get_job' ) && monospace_prints_get_job( $post_id ) ? monospace_prints_status_core( $post_id ) : null;
    return [
        'unframed' => (int) get_post_meta( $post_id, '_print_product_id', true ),
        'framed'   => (int) get_post_meta( $post_id, '_framed_print_product_id', true ),
        'hidden'   => [ 'framed' => function_exists( 'monospace_prints_is_hidden' ) && monospace_prints_is_hidden( $post_id, 'framed' ),
                        'unframed' => function_exists( 'monospace_prints_is_hidden' ) && monospace_prints_is_hidden( $post_id, 'unframed' ) ],
        'job'      => $job ? [ 'state' => $job['state'], 'text' => $job['text'] ] : null,
    ];
}

/** Original products whose artwork post is gone: [ id, title, sku, edit ] */
function monospace_artwork_editor_orphans() {
    $out = [];
    $ids = get_posts( [ 'post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids',
                        'tax_query' => [ [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => [ 'original' ] ] ] ] );
    foreach ( $ids as $pid ) {
        $post = (int) get_post_meta( $pid, '_related_post_id', true );
        if ( $post && get_post_type( $post ) === 'post' && get_post_status( $post ) !== 'trash' ) continue;
        $out[] = [ $pid, html_entity_decode( get_the_title( $pid ), ENT_QUOTES, 'UTF-8' ), (string) get_post_meta( $pid, '_sku', true ), get_edit_post_link( $pid, 'raw' ) ];
    }
    return $out;
}

/** What a customer pays, with the Sales Dashboard's rules applied: [ price, regular, on_sale ] (null outside AJAX) */
function monospace_artwork_editor_shop_price( $pid ) {
    if ( ! wp_doing_ajax() ) return null;
    $p = wc_get_product( $pid );
    if ( ! $p ) return null;
    return [ 'price' => (string) $p->get_price(), 'regular' => (string) $p->get_regular_price(), 'on_sale' => $p->is_on_sale() ];
}

/** The Sales Dashboard's global settings, in a few words, and where to change them */
function monospace_artwork_editor_sales_summary() {
    if ( ! class_exists( 'MSD_Price_Adjustments' ) ) return null;
    $url = fn( $section ) => admin_url( 'admin.php?page=wc-settings&tab=msd&section=' . $section );
    $on  = get_option( 'msd_global_enable' ) === 'yes';
    $pct = (float) get_option( 'msd_price_adj_percentage', 25 );
    $adj = $on && get_option( 'msd_price_adj_enable' ) === 'yes' && $pct != 0;
    $schedule = class_exists( 'MSD_Sale_Control' ) && method_exists( 'MSD_Sale_Control', 'check_schedule' ) ? MSD_Sale_Control::check_schedule() : true;
    return [
        'items' => [
            [ $on ? 'Sales Dashboard on' : 'Sales Dashboard off - sale prices and adjustments are ignored', $url( 'global' ) ],
            [ $adj ? 'Global price adjustment ' . ( $pct > 0 ? '+' : '' ) . rtrim( rtrim( number_format( $pct, 2 ), '0' ), '.' ) . '%' : 'No global price adjustment', $url( 'price_adjustments' ) ],
            [ 'Sales: ' . ( get_option( 'msd_sale_mode', 'taxonomy' ) === 'global' ? 'every artwork with a sale price' : 'only the chosen categories / tags' ) . ( $schedule ? '' : ' - outside the schedule now' ), $url( 'sale_rules' ) ],
        ],
        'dashboard' => $url( 'dashboard' ),
    ];
}

/** Categories by id, tags by name (case-insensitive) - the same order the editor page compares in */
function monospace_artwork_editor_sorted( array $list, $numeric ) {
    $list = array_values( array_unique( $list ) );
    $numeric ? sort( $list, SORT_NUMERIC ) : usort( $list, 'strcasecmp' );
    return $list;
}

/** The select options: taxonomy => [ label, [ [ slug, name ], … ] ] - originals' values, as New artwork offers them */
function monospace_artwork_editor_options() {
    $out = [];
    foreach ( monospace_new_artwork_attributes() as $tax => [ $label, $required, $options ] ) {
        if ( ! in_array( $tax, MONOSPACE_ARTWORK_EDITOR_ATTRS, true ) ) continue;
        // [ slug, name, group ] - sizes come grouped Landscape / Portrait / Square / Other (MetaBox::size_group())
        $out[ $tax ] = [ $label, $required, array_map( fn( $o ) => [ $o[1], $o[2], $o[0] ], $options ) ];
    }
    return $out;
}

/**
 * Save one artwork: its complete current data (as the Quick Artwork Editor loads it) with the changed
 * fields replaced, through ProductSyncer. Returns true or an error message.
 */
function monospace_artwork_editor_save_row( $post_id, array $changes ) {
    if ( get_post_type( $post_id ) !== 'post' || ! current_user_can( 'edit_post', $post_id ) ) return 'Not an artwork you can edit.';
    $pid     = (int) get_post_meta( $post_id, '_qp_product_id', true );
    $product = $pid ? wc_get_product( $pid ) : null;
    if ( ! $product ) return 'Its product is gone.';

    $attrs = [];
    foreach ( $product->get_attributes() as $attribute ) {
        if ( ! $attribute->is_taxonomy() ) continue;
        $t = wp_get_post_terms( $pid, $attribute->get_taxonomy(), [ 'fields' => 'slugs' ] );
        if ( ! is_wp_error( $t ) && $t ) $attrs[ $attribute->get_taxonomy() ] = $t[0];
    }
    $stock = $product->get_stock_quantity() ?? 0;
    $data = [
        'product_id'              => $pid,
        'product_name'            => $product->get_name(),
        'product_price'           => $product->get_regular_price(),
        'sku'                     => $product->get_sku(),
        'attributes'              => $attrs,
        'categories'              => wp_get_post_terms( $pid, 'product_cat', [ 'fields' => 'ids' ] ),
        'availability'            => $stock <= 0 ? 'sold' : ( get_post_meta( $pid, 'painting_availability_status', true ) ?: 'available' ),
        'gallery_name'            => (string) get_post_meta( $pid, 'painting_gallery_name', true ),
        'gallery_url'             => (string) get_post_meta( $pid, 'painting_gallery_url', true ),
        'print_product_id'        => (int) get_post_meta( $post_id, '_print_product_id', true ),
        'framed_print_product_id' => (int) get_post_meta( $post_id, '_framed_print_product_id', true ),
    ];
    if ( is_wp_error( $data['categories'] ) || ! $data['categories'] ) {
        $original = get_term_by( 'name', 'Original', 'product_cat' );
        $data['categories'] = $original ? [ (int) $original->term_id ] : [];
    }
    $before = $data['attributes'];
    $product_changed = (bool) array_intersect( array_keys( $changes ), [ 'title', 'price', 'availability', 'attributes' ] );

    // Sales fields, checked before anything is saved
    if ( array_key_exists( 'sale', $changes ) ) {
        $sale = preg_replace( '/[^\d.]/', '', (string) $changes['sale'] );
        $base = (float) ( array_key_exists( 'price', $changes ) ? preg_replace( '/[^\d.]/', '', (string) $changes['price'] ) : $data['product_price'] );
        if ( $sale !== '' && ( ! is_numeric( $sale ) || (float) $sale <= 0 ) ) return 'The sale price isn’t a number above 0.';
        if ( $sale !== '' && (float) $sale >= $base ) return 'The sale price must be below the price.';
        $changes['sale'] = $sale;
    }
    if ( array_key_exists( 'sale_mode', $changes ) && ! in_array( $changes['sale_mode'], [ '', 'yes', 'no' ], true ) ) return 'Unknown sale setting.';
    if ( array_key_exists( 'extra', $changes ) ) {
        $extra = trim( (string) $changes['extra'] );
        if ( $extra !== '' && ( ! is_numeric( $extra ) || $extra < -100 || $extra > 1000 ) ) return 'Extra % must be a number from -100 to 1000.';
        $changes['extra'] = $extra;
    }

    // The changes, checked
    if ( array_key_exists( 'title', $changes ) ) {
        $title = sanitize_text_field( $changes['title'] );
        if ( $title === '' ) return 'The title is empty.';
        $data['product_name'] = $title;
    }
    if ( array_key_exists( 'price', $changes ) ) {
        $price = preg_replace( '/[^\d.]/', '', (string) $changes['price'] );
        // Empty is allowed for sold / private / gallery (checked below, with the availability as saved)
        if ( $price !== '' && ( ! is_numeric( $price ) || (float) $price <= 0 ) ) return 'The price isn’t a number above 0.';
        $data['product_price'] = $price;
    }
    if ( array_key_exists( 'availability', $changes ) ) {
        $a = sanitize_key( $changes['availability'] );
        if ( ! array_key_exists( $a, \QuickProduct\MetaBox::availability_options() ) ) return 'Unknown availability.';
        $data['availability'] = $a;
    }
    // Available, reserved and Gallery QR pieces need a price; sold, private and gallery ones may have none
    // (monospace_price_required()) - checked when either changes, so other edits never trip over an old gap
    if ( ( array_key_exists( 'price', $changes ) || array_key_exists( 'availability', $changes ) )
        && trim( (string) $data['product_price'] ) === '' && monospace_price_required( $data['availability'] ) ) {
        $names = \QuickProduct\MetaBox::availability_options();
        return ( $names[ $data['availability'] ] ?? $data['availability'] ) . ' needs a price.';
    }
    foreach ( (array) ( $changes['attributes'] ?? [] ) as $tax => $slug ) {
        if ( ! in_array( $tax, MONOSPACE_ARTWORK_EDITOR_ATTRS, true ) ) continue;
        $slug = sanitize_title( $slug );
        if ( $slug === '' ) { unset( $data['attributes'][ $tax ] ); continue; }
        if ( ! term_exists( $slug, $tax ) ) return 'Unknown value for ' . $tax . '.';
        $data['attributes'][ $tax ] = $slug;
    }
    foreach ( [ 'pa_format' => 'Format', 'pa_medium' => 'Medium', 'pa_year-created' => 'Year' ] as $tax => $label ) {
        if ( empty( $data['attributes'][ $tax ] ) ) return $label . ' is required.';
    }

    // Categories and tags of the post. The site's own categories (print, available) stay as they are.
    if ( array_key_exists( 'categories', $changes ) ) {
        $locked = monospace_artwork_editor_locked_cats();
        $ids = [];
        foreach ( (array) $changes['categories'] as $id ) {
            $id = (int) $id;
            if ( $id && ! in_array( $id, $locked, true ) && term_exists( $id, 'category' ) ) $ids[] = $id;
        }
        $keep = array_intersect( wp_get_post_categories( $post_id ), $locked );
        wp_set_post_categories( $post_id, array_values( array_unique( array_merge( $ids, $keep ) ) ) );
    }
    if ( array_key_exists( 'tags', $changes ) ) {
        wp_set_post_tags( $post_id, array_values( array_filter( array_map( 'sanitize_text_field', (array) $changes['tags'] ) ) ), false );
    }

    // Post title (Needs attention: a title that differs from the product name), or "meant to differ"
    if ( array_key_exists( 'post_title', $changes ) ) {
        $t = sanitize_text_field( $changes['post_title'] );
        if ( $t === '' ) return 'The post title is empty.';
        wp_update_post( [ 'ID' => $post_id, 'post_title' => $t ] );
    }
    if ( array_key_exists( 'title_ok', $changes ) ) {
        $changes['title_ok'] ? update_post_meta( $post_id, '_ms_title_differs_ok', 1 ) : delete_post_meta( $post_id, '_ms_title_differs_ok' );
    }
    // The price card's preset for this artwork ('' = the site's setting) - price-card-presets.php
    if ( array_key_exists( 'card', $changes ) && function_exists( 'monospace_price_card_set_post_style' ) ) {
        monospace_price_card_set_post_style( $post_id, sanitize_key( (string) $changes['card'] ) );
    }

    // The product - the details' categories follow on their own (artwork-categories.php)
    if ( $product_changed ) {
        $result = ( new \QuickProduct\ProductSyncer( $post_id, $data ) )->sync();
        if ( is_wp_error( $result ) ) return $result->get_error_message();
    }

    // ProductSyncer only adds attributes: one cleared here is removed from the product too
    foreach ( MONOSPACE_ARTWORK_EDITOR_ATTRS as $tax ) {
        if ( ! $product_changed ) break;
        if ( ! empty( $before[ $tax ] ) && empty( $data['attributes'][ $tax ] ) ) {
            wp_set_object_terms( $pid, [], $tax );
            $p = wc_get_product( $pid );
            $list = $p->get_attributes();
            unset( $list[ $tax ] );
            $p->set_attributes( $list );
            $p->save();
        }
    }

    // Sales: the WooCommerce sale price, and the Sales Dashboard's per-product overrides (as its own
    // product fields save them)
    if ( array_key_exists( 'sale', $changes ) ) {
        $p = wc_get_product( $pid );
        $p->set_sale_price( $changes['sale'] );
        $p->save();
    }
    if ( array_key_exists( 'sale_mode', $changes ) ) update_post_meta( $pid, '_msd_enable_sale', $changes['sale_mode'] );
    if ( array_key_exists( 'extra', $changes ) ) {
        $changes['extra'] === '' ? delete_post_meta( $pid, '_msd_additional_percentage' ) : update_post_meta( $pid, '_msd_additional_percentage', floatval( $changes['extra'] ) );
    }
    if ( array_key_exists( 'no_adj', $changes ) ) update_post_meta( $pid, '_msd_exclude_price_adj', $changes['no_adj'] ? 'yes' : 'no' );
    if ( array_intersect( array_keys( $changes ), [ 'sale', 'sale_mode', 'extra', 'no_adj' ] ) ) wc_delete_product_transients( $pid );

    if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache( get_permalink( $post_id ) );
    return true;
}

add_action( 'wp_ajax_monospace_artwork_editor_rows', function () {
    check_ajax_referer( 'monospace_artwork_editor' );
    if ( ! current_user_can( 'edit_products' ) ) wp_send_json_error( 'Not allowed.' );
    wp_send_json_success( monospace_artwork_editor_rows() );
} );

// Prints: op = hide | show | add | remove, types = framed / unframed (hide / show: none = both) - at once, per artwork
add_action( 'wp_ajax_monospace_artwork_editor_prints', function () {
    check_ajax_referer( 'monospace_artwork_editor' );
    if ( ! monospace_feature( 'prints' ) ) wp_send_json_error( 'The Prints plugin is off.' );
    if ( ! current_user_can( 'edit_products' ) ) wp_send_json_error( 'Not allowed.' );
    $op    = sanitize_key( $_POST['op'] ?? '' );
    $types = array_values( array_intersect( (array) ( $_POST['types'] ?? [] ), [ 'framed', 'unframed' ] ) );
    $ids   = array_slice( array_map( 'absint', (array) ( $_POST['post_ids'] ?? [] ) ), 0, 10 );
    if ( ! in_array( $op, [ 'hide', 'show', 'add', 'remove' ], true ) || ! $ids ) wp_send_json_error( 'Nothing to do.' );
    if ( in_array( $op, [ 'add', 'remove' ], true ) && ! $types ) wp_send_json_error( 'Choose framed and / or unframed.' );
    $done = $errors = [];
    foreach ( $ids as $post_id ) {
        try {
            if ( get_post_type( $post_id ) !== 'post' || ! current_user_can( 'edit_post', $post_id ) ) throw new Exception( 'Not an artwork you can edit.' );
            switch ( $op ) {
                case 'hide':
                case 'show':
                    monospace_prints_set_hidden( $post_id, $op === 'hide', $types ?: null );
                    break;
                case 'add':
                    $request = [ 'min_dpi' => 100 ];
                    foreach ( monospace_print_templates() as $type => $tpl ) {
                        if ( ! in_array( $type, $types, true ) || get_post_meta( $post_id, $tpl['meta_key'], true ) ) continue;
                        $request[ $type ] = [ 'variants' => array_map( fn( $v ) => $v['price'], $tpl['variants'] ) ];
                    }
                    if ( count( $request ) === 1 ) throw new Exception( 'It already has ' . ( count( $types ) > 1 ? 'both prints' : 'that print' ) . '.' );
                    monospace_prints_queue( $post_id, $request );
                    break;
                case 'remove':
                    $any = false;
                    foreach ( $types as $type ) {
                        if ( ! get_post_meta( $post_id, monospace_print_templates()[ $type ]['meta_key'], true ) ) continue;
                        monospace_prints_remove( $post_id, $type );
                        $any = true;
                    }
                    if ( ! $any ) throw new Exception( 'It has no ' . ( count( $types ) > 1 ? 'prints' : 'such print' ) . ' to remove.' );
                    break;
            }
            $done[] = $post_id;
        } catch ( Exception $e ) {
            $errors[ $post_id ] = $e->getMessage();
        }
    }
    wp_send_json_success( [ 'rows' => monospace_artwork_editor_rows( $ids ), 'done' => $done, 'errors' => $errors ] );
} );

add_action( 'wp_ajax_monospace_artwork_editor_save', function () {
    check_ajax_referer( 'monospace_artwork_editor' );
    if ( ! current_user_can( 'edit_products' ) ) wp_send_json_error( 'Not allowed.' );
    $rows = json_decode( wp_unslash( $_POST['rows'] ?? '' ), true );
    if ( ! is_array( $rows ) ) wp_send_json_error( 'Nothing to save.' );
    $saved = $errors = [];
    foreach ( array_slice( $rows, 0, 25, true ) as $post_id => $changes ) {
        $r = monospace_artwork_editor_save_row( (int) $post_id, (array) $changes );
        if ( $r === true ) $saved[] = (int) $post_id; else $errors[ (int) $post_id ] = $r;
    }
    wp_send_json_success( [ 'rows' => $saved ? monospace_artwork_editor_rows( $saved ) : [], 'errors' => $errors ] );
} );

/* ============================================================
 * PAGE
 * ============================================================ */

function monospace_artwork_editor_page() {
    if ( ! current_user_can( 'edit_products' ) ) return;
    $config = [
        'sales'        => monospace_artwork_editor_sales_summary(),
        'orphans'      => monospace_artwork_editor_orphans(),
        'options'      => monospace_artwork_editor_options(),
        'availability' => \QuickProduct\MetaBox::availability_options(),
        'price_optional' => monospace_price_optional_statuses(), // these may have no price ("--")
        'categories'   => monospace_artwork_editor_categories(),
        'tags'         => array_values( array_map( fn( $t ) => html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ), get_tags( [ 'hide_empty' => false ] ) ) ),
        'ajax'         => admin_url( 'admin-ajax.php' ),
        'nonce'        => wp_create_nonce( 'monospace_artwork_editor' ),
        'prints'       => admin_url( 'admin.php?page=monospace-prints-all' ), // print jobs in full: Prints > All prints
        'sheets'       => admin_url( 'admin.php?page=monospace-artwork-sheets' ),
        // Feature plugins that are on - their parts of the page show only then (2026-10-19)
        'features'     => [ 'prints' => monospace_feature( 'prints' ), 'sales' => monospace_feature( 'sales' ), 'sheets' => monospace_feature( 'sheets' ) ],
        // Price card presets for the "Price card" column: [ [ value, label ] ], '' = the site's setting
        'cards'        => function_exists( 'monospace_price_card_presets' )
            ? array_merge( [ [ '', 'Site setting' ] ], array_map( null, array_keys( monospace_price_card_presets() ), array_values( monospace_price_card_presets() ) ) )
            : [],
    ];
    ?>
    <div class="wrap<?php foreach ( [ 'prints', 'sales', 'sheets' ] as $f ) if ( ! monospace_feature( $f ) ) echo ' ae-no-' . $f; ?>" id="ae">
        <h1 class="wp-heading-inline">Artwork Editor</h1>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=monospace-artworks' ) ); ?>" class="page-title-action">New artwork</a>
        <p class="description">Every artwork, edited in place. Changed rows turn yellow; <b>Save changes</b> sends them, the same way the Quick Artwork Editor's button does. Select rows to change several at once: <b>Apply to selected</b> marks them (then Save), <b>Run now</b> (prints) acts right away.</p>

        <div class="ae-views" role="tablist">
            <button type="button" class="button is-on" data-view="details" role="tab">Details</button>
            <button type="button" class="button ae-f-sales" data-view="sales" role="tab">Sales</button>
        </div>
        <p class="ae-sales-line" id="ae-sales-line" hidden></p>

        <div class="ae-tools">
            <input type="search" id="ae-search" placeholder="Search title, number or tag…">
            <select id="ae-f-availability"><option value="">Any availability</option></select>
            <select id="ae-f-pa_format"><option value="">Any format</option></select>
            <select id="ae-f-pa_medium"><option value="">Any medium</option></select>
            <select id="ae-f-pa_year-created"><option value="">Any year</option></select>
            <select id="ae-f-category"><option value="">Any category</option></select>
            <select id="ae-f-status"><option value="">Published and drafts</option><option value="publish">Published</option><option value="draft">Drafts</option><option value="sale">With a sale price</option><option value="prints" class="ae-f-prints">With prints</option><option value="noprints" class="ae-f-prints">Without prints</option><option value="hidden" class="ae-f-prints">Prints hidden</option><option value="changed">Changed, not saved</option><option value="attention">Needs attention</option><option value="selected">Selected only</option></select>
            <input type="file" id="ae-list-file" accept=".json,application/json" hidden>
            <button type="button" class="button" id="ae-list-load" title="Select the artworks of a saved list (Artwork Editor or Artwork Sheets)">Load list</button>
            <span class="ae-count" id="ae-count"></span>
        </div>

        <p class="ae-attention-note" id="ae-orphans" hidden></p>

        <div class="ae-bulk" id="ae-bulk" hidden>
            <b id="ae-bulk-n"></b>
            <span class="ae-bulk-group">
                <select id="ae-b-availability"><option value="">Set availability…</option></select>
            </span>
            <span class="ae-bulk-group">
                <select id="ae-b-tax"><option value="">Set a detail…</option></select>
                <select id="ae-b-value" hidden></select>
            </span>
            <span class="ae-bulk-group">
                <select id="ae-b-price-mode"><option value="">Price…</option><option value="set">Set to $</option><option value="up">Raise by %</option><option value="down">Lower by %</option><option value="up-usd">Raise by $</option><option value="down-usd">Lower by $</option></select>
                <input type="number" id="ae-b-price" min="0" step="1" hidden>
            </span>
            <span class="ae-bulk-group">
                <select id="ae-b-terms"><option value="">Categories &amp; tags…</option><option value="cat-add">Add category</option><option value="cat-remove">Remove category</option><option value="tag-add">Add tags</option><option value="tag-remove">Remove tags</option></select>
                <select id="ae-b-cat" hidden></select>
                <input type="text" id="ae-b-tags" list="ae-taglist" placeholder="tag, another tag" hidden>
            </span>
            <span class="ae-bulk-group">
                <select id="ae-b-card"><option value="">Price card…</option></select>
            </span>
            <span class="ae-bulk-group ae-f-sales">
                <select id="ae-b-sale"><option value="">Sale…</option>
                    <option value="pct">Sale price: % off the price</option><option value="usd">Sale price: $ off the price</option><option value="set">Sale price: set to $</option><option value="clear">Sale price: remove</option>
                    <option value="mode-">Sale: follow the dashboard's rules</option><option value="mode-yes">Sale: always on</option><option value="mode-no">Sale: never</option>
                    <option value="extra">Extra %: set</option><option value="extra-clear">Extra %: remove</option>
                    <option value="adj-off">Skip the global adjustment</option><option value="adj-on">Use the global adjustment</option></select>
                <input type="number" id="ae-b-sale-v" step="1" hidden>
            </span>
            <span class="ae-bulk-group ae-f-prints">
                <select id="ae-b-prints"><option value="">Prints… (at once)</option>
                    <option value="show">Show prints</option><option value="hide">Hide prints</option>
                    <option value="show:framed">Show framed print</option><option value="hide:framed">Hide framed print</option>
                    <option value="show:unframed">Show unframed print</option><option value="hide:unframed">Hide unframed print</option>
                    <option value="add:framed,unframed">Add framed + unframed print (where missing)</option><option value="add:framed">Add framed print</option><option value="add:unframed">Add unframed print</option>
                    <option value="remove:framed">Remove framed print</option><option value="remove:unframed">Remove unframed print</option><option value="remove:framed,unframed">Remove framed + unframed print</option></select>
                <button type="button" class="button" id="ae-b-prints-go">Run now</button>
            </span>
            <button type="button" class="button" id="ae-b-apply">Apply to selected</button>
            <button type="button" class="button" id="ae-b-list-save" title="Download the selection as a list file - which artworks, nothing about them">Save list</button>
            <button type="button" class="button ae-f-sheets" id="ae-b-sheets" title="The Artwork Sheets generator, with these artworks selected">Open in Artwork Sheets</button>
            <button type="button" class="button-link" id="ae-b-clear">Clear selection</button>
        </div>

        <table class="widefat ae-table" id="ae-table" data-view="details">
            <thead><tr>
                <th class="ae-c-check"><input type="checkbox" id="ae-check-all" title="Select all that match"></th>
                <th class="ae-c-thumb"></th>
                <th data-sort="num" class="ae-sort">#</th>
                <th data-sort="title" class="ae-sort">Title</th>
                <th data-sort="price" class="ae-sort">Price</th>
                <th data-sort="availability" class="ae-sort">Availability</th>
                <th class="v-d">Format</th><th class="v-d">Medium</th><th class="v-d">Surface</th><th class="v-d">Size</th>
                <th data-sort="pa_year-created" class="ae-sort v-d">Year</th>
                <th class="v-d">Categories &amp; tags</th>
                <th class="v-d ae-f-prints">Prints</th>
                <th class="v-d" title="This artwork's price card: Full / Compact / One line / Badge, or the site's setting (Layout Manager > Appearance &amp; Effects > Price card)">Price card</th>
                <th data-sort="sale" class="ae-sort v-s">Sale price</th>
                <th class="v-s" title="The Sales Dashboard's sale override for this artwork">Sale</th>
                <th class="v-s" title="Extra % on top of the global price adjustment">Extra %</th>
                <th class="v-s" title="Skip the Sales Dashboard's global price adjustment">Skip global</th>
                <th data-sort="shop" class="ae-sort v-s" title="What a customer pays, with the Sales Dashboard's rules applied">Shop price</th>
                <th data-sort="date" class="ae-sort">Post</th>
            </tr></thead>
            <tbody id="ae-body"></tbody>
        </table>
        <div class="ae-pages" id="ae-pages"></div>
        <datalist id="ae-taglist"></datalist>
        <div class="ae-zoom" id="ae-zoom" hidden><img alt=""><span></span></div>

        <div class="ae-pop" id="ae-ppop" hidden>
            <div class="ae-pop-head"><b id="ae-ppop-title"></b></div>
            <div id="ae-ppop-body"></div>
            <p class="description" style="margin-top:10px">These happen at once - no need to Save. Hide takes a print off the price card and the site, nothing deleted. Add runs in the background (a few minutes - follow it on Prints &gt; All prints). Remove takes the print out of Printful and puts its product in the Trash.</p>
            <p class="ae-pop-actions"><button type="button" class="button-link" id="ae-ppop-close">Close</button> <span id="ae-ppop-msg"></span></p>
        </div>

        <div class="ae-pop" id="ae-pop" hidden>
            <div class="ae-pop-head"><b id="ae-pop-title"></b></div>
            <p class="ae-pop-label">Categories</p>
            <input type="search" id="ae-pop-filter" placeholder="Find a category…">
            <div class="ae-pop-cats" id="ae-pop-cats"></div>
            <p class="ae-pop-label">Tags <span class="description">- separated by commas</span></p>
            <input type="text" id="ae-pop-tags" list="ae-taglist">
            <p class="ae-pop-actions"><button type="button" class="button button-primary" id="ae-pop-done">Done</button> <button type="button" class="button-link" id="ae-pop-cancel">Cancel</button></p>
        </div>

        <div class="ae-savebar">
            <button type="button" class="button button-primary" id="ae-save" disabled>Save changes</button>
            <button type="button" class="button" id="ae-discard" disabled>Discard</button>
            <span id="ae-dirty"></span>
            <span id="ae-msg" role="status"></span>
        </div>
    </div>

    <style>
        #ae .ae-tools, #ae .ae-bulk { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 14px 0 10px; }
        #ae #ae-search { min-width: 240px; }
        #ae .ae-count { margin-left: auto; color: #50575e; }
        #ae .ae-bulk { padding: 8px 12px; background: #f0f6fc; border: 1px solid #c5d9ed; border-radius: 4px; }
        #ae .ae-bulk-group { display: inline-flex; gap: 4px; }
        #ae .ae-table { table-layout: auto; }
        #ae .ae-table th, #ae .ae-table td { vertical-align: middle; padding: 6px 6px; }
        #ae .ae-sort { cursor: pointer; white-space: nowrap; }
        #ae .ae-sort::after { content: " ↕"; color: #a7aaad; }
        #ae .ae-sort.is-asc::after { content: " ↑"; color: #1d2327; }
        #ae .ae-sort.is-desc::after { content: " ↓"; color: #1d2327; }
        #ae .ae-c-check { width: 24px; }
        #ae .ae-c-thumb { width: 48px; }
        #ae .ae-thumb { width: 44px; height: 44px; object-fit: cover; display: block; border-radius: 3px; background: #f0f0f1; cursor: zoom-in; }
        /* Rollover preview of the artwork, beside the thumbnail */
        #ae .ae-zoom { position: fixed; z-index: 100; padding: 6px; background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; box-shadow: 0 8px 28px rgba(0,0,0,.25); pointer-events: none; }
        #ae .ae-zoom img { display: block; max-width: min(420px, 40vw); max-height: min(420px, 70vh); width: auto; height: auto; background: #f0f0f1; }
        #ae .ae-zoom span { display: block; margin-top: 5px; font-size: 12px; color: #50575e; max-width: min(420px, 40vw); }
        #ae .ae-num { font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
        #ae .ae-sku { display: block; font-size: 11px; font-weight: 400; color: #8c8f94; }
        #ae .ae-table input[type=text] { width: 100%; min-width: 160px; }
        #ae .ae-table input.ae-price { width: 80px; min-width: 0; }
        #ae .ae-table select { max-width: 150px; }
        #ae tr.is-dirty td { background: #fcf9e8; }
        #ae tr.is-error td { background: #fcf0f1; }
        #ae .ae-field.is-changed { box-shadow: 0 0 0 2px #dba617; }
        #ae .ae-err { display: block; color: #8a2424; font-size: 12px; }
        #ae .ae-issues { display: block; margin-top: 3px; font-size: 12px; color: #6e4e00; line-height: 1.6; }
        #ae .ae-issues button { font-size: 11px; padding: 0 6px; min-height: 0; line-height: 18px; margin-left: 3px; }
        #ae .ae-attention-note { margin: 10px 0 0; padding: 6px 10px; background: #fcf9e8; border-left: 4px solid #dba617; }
        #ae .ae-av[data-status] { border-left: 4px solid var(--st-bd); background-color: var(--st-bg); color: var(--st-fg); }
        #ae [data-status="available"] { --st-bg: #edfaef; --st-bd: #00a32a; --st-fg: #00631f; }
        #ae [data-status="reserved"] { --st-bg: #fcf9e8; --st-bd: #dba617; --st-fg: #6e4e00; }
        #ae [data-status="sold"] { --st-bg: #f0f6fc; --st-bd: #2271b1; --st-fg: #135e96; }
        #ae [data-status="private"] { --st-bg: #fcf0f1; --st-bd: #d63638; --st-fg: #8a2424; }
        #ae [data-status="gallery"] { --st-bg: #f5effc; --st-bd: #8a4fd1; --st-fg: #5b2a99; }
        #ae [data-status="gallery-reserved"] { --st-bg: #fdf1e7; --st-bd: #e26f12; --st-fg: #8a3f00; }
        #ae .ae-terms { min-width: 180px; max-width: 260px; font-size: 12px; line-height: 1.7; cursor: pointer; border-radius: 3px; }
        #ae .ae-terms:hover { background: #f6f7f7; outline: 1px solid #c3c4c7; }
        #ae .ae-terms.is-changed { box-shadow: 0 0 0 2px #dba617; }
        #ae .ae-chip { display: inline-block; padding: 0 6px; margin: 0 3px 2px 0; border-radius: 9px; background: #f0f0f1; color: #3c434a; white-space: nowrap; }
        #ae .ae-chip.tag { background: #fff; border: 1px solid #dcdcde; }
        #ae .ae-terms-empty { color: #a7aaad; }
        #ae .ae-pop { position: absolute; z-index: 50; width: 360px; padding: 10px 12px; background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; box-shadow: 0 6px 24px rgba(0,0,0,.18); }
        #ae .ae-pop-label { margin: 8px 0 4px; font-weight: 600; }
        #ae .ae-pop input[type=search], #ae .ae-pop input[type=text] { width: 100%; }
        #ae .ae-pop-cats { max-height: 220px; overflow: auto; margin-top: 4px; padding: 4px 6px; border: 1px solid #dcdcde; border-radius: 3px; }
        #ae .ae-pop-cats label { display: block; padding: 1px 0; }
        #ae .ae-pop-actions { margin: 10px 0 0; }
        #ae .ae-views { display: flex; gap: 4px; margin: 14px 0 0; }
        #ae .ae-views .is-on { background: #2271b1; border-color: #2271b1; color: #fff; }
        #ae .ae-sales-line { margin: 10px 0 0; padding: 6px 10px; background: #fff; border-left: 4px solid #8a4fd1; }
        #ae .ae-sales-line a { text-decoration: none; }
        #ae table[data-view="details"] .v-s, #ae table[data-view="sales"] .v-d { display: none; }
        #ae .ae-table input.ae-pct { width: 64px; min-width: 0; }
        #ae .ae-shop { white-space: nowrap; }
        #ae .ae-shop del { color: #8c8f94; margin-left: 4px; }
        #ae .ae-shop .ae-onsale { color: #b32d2e; font-weight: 600; }
        #ae .ae-shop-later { color: #8c8f94; font-style: italic; }
        #ae .ae-loading { padding: 30px; text-align: center; color: #50575e; }
        #ae .ae-prints { white-space: nowrap; font-size: 12px; }
        #ae .ae-prints span { display: inline-block; padding: 0 5px; margin-right: 2px; border-radius: 3px; background: #f0f0f1; color: #50575e; }
        #ae .ae-prints span.on { background: #edfaef; color: #00631f; }
        #ae .ae-prints span.hidden { background: #fcf9e8; color: #6e4e00; text-decoration: line-through; }
        #ae .ae-prints-cell { white-space: nowrap; }
        /* A feature plugin that's off: its parts of the page go (the wrap's ae-no-<feature> classes) */
        #ae.ae-no-prints .ae-f-prints, #ae.ae-no-prints .ae-prints-cell, #ae.ae-no-sales .ae-f-sales, #ae.ae-no-sheets .ae-f-sheets { display: none !important; }
        #ae .ae-job-link { font-size: 12px; text-decoration: none; }
        #ae .ae-job-link.is-bad { color: #b32d2e; }
        #ae .ae-pp-toggle { margin-left: 6px; }
        #ae .ae-pp-main { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 8px 10px; margin: 6px 0 4px; border-radius: 4px; background: #edfaef; }
        #ae .ae-pp-main.is-hidden { background: #fcf9e8; }
        #ae .ae-pp-row .ae-pp-btns { display: inline-flex; gap: 4px; }
        #ae .ae-prints span.job { background: #f0f6fc; color: #135e96; }
        #ae .ae-prints span.err { background: #fcf0f1; color: #8a2424; }
        #ae .ae-prints { cursor: pointer; }
        #ae .ae-prints:hover span { outline: 1px solid #c3c4c7; }
        #ae .ae-pp-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 0; border-bottom: 1px solid #f0f0f1; }
        #ae .ae-pp-state { color: #50575e; }
        #ae .ae-post { white-space: nowrap; font-size: 12px; }
        #ae .ae-draft { color: #b26200; font-weight: 600; }
        #ae .ae-pages { display: flex; gap: 4px; flex-wrap: wrap; margin: 10px 0 70px; }
        #ae .ae-pages button.is-on { background: #2271b1; border-color: #2271b1; color: #fff; }
        #ae .ae-savebar { position: sticky; bottom: 0; z-index: 20; display: flex; align-items: center; gap: 10px; margin: 0 -20px 0 -10px; padding: 10px 20px; background: #f0f0f1; border-top: 1px solid #dcdcde; }
        #ae #ae-dirty { color: #b26200; font-weight: 600; }
        #ae #ae-msg.is-error { color: #8a2424; }
    </style>

    <script>
    (function () {
        var C = <?php echo wp_json_encode( $config ); ?>;
        var ATTRS = <?php echo wp_json_encode( MONOSPACE_ARTWORK_EDITOR_ATTRS ); ?>;
        var PER_PAGE = 50;
        var rows = [], byId = {}, edits = {}, errors = {}, ticked = {}, page = 0, sort = { key: 'num', dir: -1 }, view = 'details';
        var $ = function (id) { return document.getElementById(id); };
        function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

        // Current value of a field: the edit if there is one, else the saved value
        function get(r, f) {
            var e = edits[r.post_id];
            if (ATTRS.indexOf(f) > -1) return e && e.attributes && f in e.attributes ? e.attributes[f] : r.attributes[f];
            return e && f in e ? e[f] : r[f];
        }
        function set(id, f, v) {
            var r = byId[id], e = edits[id] || (edits[id] = {});
            if (ATTRS.indexOf(f) > -1) {
                e.attributes = e.attributes || {};
                if (v === r.attributes[f]) delete e.attributes[f]; else e.attributes[f] = v;
                if (!Object.keys(e.attributes).length) delete e.attributes;
            } else if (Array.isArray(v) ? norm(f, v).join('\u0001') === r[f].join('\u0001') : String(v) === String(r[f])) delete e[f];
            else e[f] = Array.isArray(v) ? norm(f, v) : v;
            if (!Object.keys(e).length) delete edits[id];
            delete errors[id];
        }
        // Categories by id, tags by name - sorted, so a list compares equal to the saved one
        function norm(f, list) {
            var seen = {}, out = [];
            list.forEach(function (x) { if (f === 'categories') x = +x; else x = String(x).trim(); var k = String(x).toLowerCase(); if (x !== '' && !seen[k]) { seen[k] = 1; out.push(x); } });
            return f === 'categories' ? out.sort(function (a, b) { return a - b; }) : out.sort(function (a, b) { return a.toLowerCase() < b.toLowerCase() ? -1 : a.toLowerCase() > b.toLowerCase() ? 1 : 0; });
        }
        var catName = {}; C.categories.forEach(function (c) { catName[c[0]] = c[1]; });
        function changed(r, f) {
            var e = edits[r.post_id];
            if (!e) return false;
            return ATTRS.indexOf(f) > -1 ? !!(e.attributes && f in e.attributes) : f in e;
        }
        function label(tax, slug) {
            var o = (C.options[tax] || [, , []])[2].filter(function (x) { return x[0] === slug; })[0];
            return o ? o[1] : slug;
        }

        // Filters / bulk selects
        function fill(sel, pairs) { pairs.forEach(function (p) { var o = document.createElement('option'); o.value = p[0]; o.textContent = p[1]; sel.appendChild(o); }); }
        var avPairs = Object.keys(C.availability).map(function (k) { return [k, C.availability[k]]; });
        fill($('ae-f-availability'), avPairs);
        fill($('ae-b-availability'), avPairs);
        ['pa_format', 'pa_medium', 'pa_year-created'].forEach(function (t) { if (C.options[t]) fill($('ae-f-' + t), C.options[t][2]); });
        fill($('ae-f-category'), C.categories);
        fill($('ae-b-cat'), C.categories);
        $('ae-taglist').innerHTML = C.tags.map(function (t) { return '<option value="' + esc(t) + '">'; }).join('');
        fill($('ae-b-tax'), ATTRS.filter(function (t) { return C.options[t]; }).map(function (t) { return [t, C.options[t][0]]; }));

        function matches() {
            var q = $('ae-search').value.trim().toLowerCase(), st = $('ae-f-status').value;
            return rows.filter(function (r) {
                if (q && (get(r, 'title') || '').toLowerCase().indexOf(q) < 0 && String(r.num || '').indexOf(q.replace(/^#/, '')) !== 0 && (r.sku || '').toLowerCase().indexOf(q) < 0 &&
                    !get(r, 'tags').some(function (t) { return t.toLowerCase().indexOf(q) > -1; })) return false;
                if ($('ae-f-category').value && get(r, 'categories').indexOf(+$('ae-f-category').value) < 0) return false;
                if ($('ae-f-availability').value && get(r, 'availability') !== $('ae-f-availability').value) return false;
                for (var i = 0, t; (t = ['pa_format', 'pa_medium', 'pa_year-created'][i]); i++) if ($('ae-f-' + t).value && get(r, t) !== $('ae-f-' + t).value) return false;
                if (st === 'changed') return !!edits[r.post_id];
                if (st === 'attention') return issues(r).length > 0;
                if (st === 'selected') return !!ticked[r.post_id];
                if (st === 'sale') return !!get(r, 'sale');
                if (st === 'prints') return !!(r.prints.framed || r.prints.unframed);
                if (st === 'noprints') return !(r.prints.framed || r.prints.unframed);
                if (st === 'hidden') return !!(r.prints.hidden.framed || r.prints.hidden.unframed);
                if (st && r.status !== st) return false;
                return true;
            }).sort(function (a, b) {
                var k = sort.key, x, y;
                if (k === 'num' || k === 'price' || k === 'sale') { x = parseFloat(get(a, k)) || 0; y = parseFloat(get(b, k)) || 0; }
                else if (k === 'shop') { x = a.shop ? parseFloat(a.shop.price) || 0 : 0; y = b.shop ? parseFloat(b.shop.price) || 0 : 0; }
                else if (k === 'availability') { x = Object.keys(C.availability).indexOf(get(a, k)); y = Object.keys(C.availability).indexOf(get(b, k)); }
                else if (ATTRS.indexOf(k) > -1) { x = label(k, get(a, k)); y = label(k, get(b, k)); }
                else { x = String(get(a, k) || '').toLowerCase(); y = String(get(b, k) || '').toLowerCase(); }
                return (x < y ? -1 : x > y ? 1 : 0) * sort.dir;
            });
        }

        // "Recent & frequent" at the top of the detail dropdowns: picked last here (kept in the browser), the
        // newest artworks' values, the most used - up to six, no repeats
        var TOP_N = 6, topCache = {};
        function recentPicks(tax) {
            try { var all = JSON.parse(localStorage.getItem('msAeRecent') || '{}'); return Array.isArray(all[tax]) ? all[tax] : []; } catch (e) { return []; }
        }
        function recordPick(tax, slug) {
            if (!slug) return;
            try {
                var all = JSON.parse(localStorage.getItem('msAeRecent') || '{}');
                all[tax] = [slug].concat((all[tax] || []).filter(function (x) { return x !== slug; })).slice(0, 3);
                localStorage.setItem('msAeRecent', JSON.stringify(all));
            } catch (e) {}
            delete topCache[tax];
        }
        function topValues(tax) {
            if (topCache[tax]) return topCache[tax];
            var known = {}, counts = {}, out = [];
            ((C.options[tax] || [, , []])[2]).forEach(function (o) { known[o[0]] = o[1]; });
            var add = function (slug) { if (slug && known[slug] != null && out.indexOf(slug) < 0 && out.length < TOP_N) out.push(slug); };
            recentPicks(tax).forEach(add);
            // The newest artworks (rows come newest first): their values, up to three of them
            var fromNew = [];
            for (var i = 0; i < rows.length && fromNew.length < 3; i++) { var v = rows[i].attributes[tax]; if (v && fromNew.indexOf(v) < 0) fromNew.push(v); }
            fromNew.forEach(add);
            rows.forEach(function (r) { var v = r.attributes[tax]; if (v) counts[v] = (counts[v] || 0) + 1; });
            Object.keys(counts).sort(function (a, b) { return counts[b] - counts[a]; }).forEach(add);
            return (topCache[tax] = out.map(function (slug) { return [slug, known[slug]]; }));
        }
        function optionsHtml(tax, cur) {
            var opts = (C.options[tax] || [, , []])[2], top = topValues(tax), has = false, html = '', sel = false;
            var o = function (v, l) { var on = !sel && v === cur; if (on) { sel = true; has = true; } return '<option value="' + esc(v) + '"' + (on ? ' selected' : '') + '>' + esc(l) + '</option>'; };
            var grouped = opts.some(function (t) { return t[2]; }); // sizes: Landscape / Portrait / Square / Other
            if (top.length) html += '<optgroup label="Recent &amp; frequent">' + top.map(function (t) { return o(t[0], t[1]); }).join('') + '</optgroup>' + (grouped ? '' : '<optgroup label="All">');
            if (grouped) {
                var open = null;
                opts.forEach(function (t) {
                    if (t[2] !== open) { if (open !== null) html += '</optgroup>'; open = t[2]; html += '<optgroup label="' + esc(open) + '">'; }
                    html += o(t[0], t[1]);
                });
                if (open !== null) html += '</optgroup>';
            } else {
                html += opts.map(function (t) { return o(t[0], t[1]); }).join('');
                if (top.length) html += '</optgroup>';
            }
            if (cur && !has) html += '<option value="' + esc(cur) + '" selected>' + esc(cur) + '</option>'; // a value outside the list stays as it is
            return html;
        }
        function select(r, tax) {
            return '<select class="ae-field' + (changed(r, tax) ? ' is-changed' : '') + '" data-f="' + tax + '"><option value="">—</option>' + optionsHtml(tax, get(r, tax)) + '</select>';
        }

        // Prints: green = Shown, crossed out = Hidden, blue = Being made, red = a print job stopped (details: Prints > All prints)
        function printState(r, type) {
            var P = r.prints;
            if (P.job && P.job.state === 'error') return ['err', 'Print job stopped'];
            if (!P[type]) return P.job ? ['job', 'Being made'] : ['', 'No print'];
            return P.hidden[type] ? ['hidden', 'Hidden - off the price card and the site'] : ['on', 'Shown'];
        }
        function printChip(r, type, letter) {
            var st = printState(r, type);
            return '<span class="' + st[0] + '" title="' + (type === 'framed' ? 'Framed' : 'Unframed') + ' print: ' + esc(st[1]) + '">' + letter + '</span>';
        }
        // A running / stopped print job: a link to Prints > All prints, where it shows in full
        // Every linked print hidden?
        function allHidden(r) {
            var P = r.prints, types = ['framed', 'unframed'].filter(function (t) { return P[t]; });
            return types.length > 0 && types.every(function (t) { return P.hidden[t]; });
        }
        // Wording, the same everywhere: "prints" (both) or "framed print" / "unframed print"
        function visLabel(types) { return types && types.length === 1 ? types[0] + ' print' : 'prints'; }
        function visQ(op, types) {
            var l = visLabel(types);
            return op === 'hide' ? 'Hide ' + l + '? Off the price card and the site. Nothing is deleted - Show ' + l + ' puts ' + (l === 'prints' ? 'them' : 'it') + ' back.'
                                 : 'Show ' + l + '? Back on sale on the price card and the site.';
        }
        function visDone(op, types) { var l = visLabel(types); return l.charAt(0).toUpperCase() + l.slice(1) + (op === 'hide' ? ' hidden.' : ' shown.'); }
        function jobLink(r) {
            var J = r.prints.job; if (!J) return '';
            var bad = J.state === 'error';
            return ' <a class="ae-job-link' + (bad ? ' is-bad' : '') + '" href="' + esc(C.prints) + '" target="_blank" title="' + esc(J.text) + '">' + (bad ? 'Print job stopped' : 'Being made') + ' →</a>';
        }
        function money(v) { var n = parseFloat(v); return isNaN(n) ? '' : '$' + n.toLocaleString('en-US', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 }); }
        function shopHtml(r) {
            if (['price', 'sale', 'sale_mode', 'extra', 'no_adj'].some(function (f) { return changed(r, f); })) return '<span class="ae-shop-later">after saving</span>';
            if (!r.shop) return '–';
            return r.shop.on_sale && r.shop.regular !== r.shop.price
                ? '<span class="ae-onsale">' + money(r.shop.price) + '</span><del>' + money(r.shop.regular) + '</del>'
                : money(r.shop.price);
        }

        // Needs attention: what's wrong with a row, as [ text, fix buttons ]
        function issues(r) {
            var out = [];
            if (get(r, 'title') !== get(r, 'post_title') && !get(r, 'title_ok'))
                out.push(['Post title: “' + esc(get(r, 'post_title')) + '”',
                    '<button type="button" class="button" data-fix="use-post">Use post title</button>' +
                    '<button type="button" class="button" data-fix="rename-post">Rename post to match</button>' +
                    '<button type="button" class="button" data-fix="ok">Meant to differ</button>']);
            var miss = [['pa_format', 'format'], ['pa_medium', 'medium'], ['pa_year-created', 'year']].filter(function (a) { return !get(r, a[0]); }).map(function (a) { return a[1]; });
            if (miss.length) out.push(['No ' + miss.join(', '), '']);
            // Sold / private / gallery pieces may have no price - available, reserved and Gallery QR ones need it
            if (!(parseFloat(get(r, 'price')) > 0) && C.price_optional.indexOf(get(r, 'availability')) < 0) out.push(['No price', '']);
            if (!r.thumb) out.push(['No image - set a featured image on the post', '']);
            return out;
        }
        function termsHtml(r) {
            var c = get(r, 'categories').filter(function (id) { return catName[id]; }).map(function (id) { return '<span class="ae-chip">' + esc(catName[id].split(' › ').pop()) + '</span>'; });
            var t = get(r, 'tags').map(function (n) { return '<span class="ae-chip tag">#' + esc(n) + '</span>'; });
            return c.concat(t).join('') || '<span class="ae-terms-empty">none</span>';
        }

        function rowHtml(r) {
            var av = get(r, 'availability'), id = r.post_id;
            var avSel = '<select class="ae-field ae-av' + (changed(r, 'availability') ? ' is-changed' : '') + '" data-f="availability" data-status="' + esc(av) + '">' +
                avPairs.map(function (p) { return '<option value="' + p[0] + '"' + (p[0] === av ? ' selected' : '') + '>' + esc(p[1]) + '</option>'; }).join('') + '</select>';
            var pr = printChip(r, 'framed', 'F') + printChip(r, 'unframed', 'U');
            return '<tr data-id="' + id + '" class="' + (edits[id] ? 'is-dirty ' : '') + (errors[id] ? 'is-error' : '') + '">' +
                '<td><input type="checkbox" class="ae-tick"' + (ticked[id] ? ' checked' : '') + '></td>' +
                '<td>' + (r.thumb ? '<img class="ae-thumb" loading="lazy" src="' + esc(r.thumb) + '" data-preview="' + esc(r.preview || r.thumb) + '" alt="">' : '<span class="ae-thumb"></span>') + '</td>' +
                '<td class="ae-num">' + (r.num != null ? r.num : '–') + '<span class="ae-sku">' + esc(r.sku) + '</span></td>' +
                '<td><input type="text" class="ae-field' + (changed(r, 'title') ? ' is-changed' : '') + '" data-f="title" value="' + esc(get(r, 'title')) + '">' + (errors[id] ? '<span class="ae-err">' + esc(errors[id]) + '</span>' : '') +
                    issues(r).map(function (i) { return '<span class="ae-issues">⚠ ' + i[0] + i[1] + '</span>'; }).join('') + '</td>' +
                '<td><input type="text" inputmode="decimal" class="ae-field ae-price' + (changed(r, 'price') ? ' is-changed' : '') + '" data-f="price" value="' + esc(get(r, 'price')) + '" placeholder="' + (C.price_optional.indexOf(get(r, 'availability')) > -1 ? '--' : 'price') + '"></td>' +
                '<td>' + avSel + '</td>' +
                ATTRS.map(function (t) { return '<td class="v-d">' + select(r, t) + '</td>'; }).join('') +
                '<td class="v-d"><div class="ae-terms' + (changed(r, 'categories') || changed(r, 'tags') ? ' is-changed' : '') + '" tabindex="0" title="Edit categories and tags">' + termsHtml(r) + '</div></td>' +
                '<td class="v-d ae-prints-cell"><span class="ae-prints" tabindex="0" title="Prints - click to add or remove">' + pr + '</span>' +
                    jobLink(r) +
                    ((r.prints.framed || r.prints.unframed) ? '<button type="button" class="button button-small ae-pp-toggle" data-op="' + (allHidden(r) ? 'show' : 'hide') + '">' + (allHidden(r) ? 'Show prints' : 'Hide prints') + '</button>' : '') + '</td>' +
                '<td class="v-d">' + (C.cards.length ? '<select class="ae-field' + (changed(r, 'card') ? ' is-changed' : '') + '" data-f="card">' +
                    C.cards.map(function (o) { return '<option value="' + o[0] + '"' + (o[0] === get(r, 'card') ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select>' : '') + '</td>' +
                '<td class="v-s"><input type="text" inputmode="decimal" placeholder="none" class="ae-field ae-price' + (changed(r, 'sale') ? ' is-changed' : '') + '" data-f="sale" value="' + esc(get(r, 'sale')) + '"></td>' +
                '<td class="v-s"><select class="ae-field' + (changed(r, 'sale_mode') ? ' is-changed' : '') + '" data-f="sale_mode">' +
                    [['', 'Follow the rules'], ['yes', 'Always on'], ['no', 'Never']].map(function (o) { return '<option value="' + o[0] + '"' + (o[0] === get(r, 'sale_mode') ? ' selected' : '') + '>' + o[1] + '</option>'; }).join('') + '</select></td>' +
                '<td class="v-s"><input type="number" step="0.01" placeholder="–" class="ae-field ae-pct' + (changed(r, 'extra') ? ' is-changed' : '') + '" data-f="extra" value="' + esc(get(r, 'extra')) + '"></td>' +
                '<td class="v-s"><input type="checkbox" class="ae-field' + (changed(r, 'no_adj') ? ' is-changed' : '') + '" data-f="no_adj"' + (get(r, 'no_adj') ? ' checked' : '') + '></td>' +
                '<td class="v-s ae-shop">' + shopHtml(r) + '</td>' +
                '<td class="ae-post">' + (r.status !== 'publish' ? '<span class="ae-draft">' + esc(r.status === 'draft' ? 'Draft' : r.status) + '</span> · ' : '') +
                    '<a href="' + esc(r.edit) + '" target="_blank">Edit</a> · <a href="' + esc(r.view) + '" target="_blank">View</a><br>' + esc(r.date) + '</td>' +
                '</tr>';
        }

        function render() {
            topCache = {}; // saved values may have changed the counts
            var list = matches(), pages = Math.max(1, Math.ceil(list.length / PER_PAGE));
            page = Math.min(page, pages - 1);
            $('ae-body').innerHTML = list.slice(page * PER_PAGE, (page + 1) * PER_PAGE).map(rowHtml).join('') ||
                '<tr><td colspan="20">No artworks match.</td></tr>';
            $('ae-count').textContent = list.length + ' of ' + rows.length + ' artworks';
            var att = rows.filter(function (x) { return issues(x).length; }).length, opt = document.querySelector('#ae-f-status option[value=attention]');
            if (opt) opt.textContent = 'Needs attention (' + att + ')';
            var ph = '';
            if (pages > 1) for (var i = 0; i < pages; i++) ph += '<button type="button" class="button' + (i === page ? ' is-on' : '') + '" data-page="' + i + '">' + (i + 1) + '</button>';
            $('ae-pages').innerHTML = ph;
            document.querySelectorAll('#ae .ae-sort').forEach(function (th) {
                th.classList.toggle('is-asc', th.dataset.sort === sort.key && sort.dir === 1);
                th.classList.toggle('is-desc', th.dataset.sort === sort.key && sort.dir === -1);
            });
            var tk = Object.keys(ticked).length;
            $('ae-bulk').hidden = !tk;
            $('ae-bulk-n').textContent = tk + ' selected:';
            $('ae-check-all').checked = list.length > 0 && list.every(function (r) { return ticked[r.post_id]; });
            status();
        }
        function status() {
            var n = Object.keys(edits).length;
            $('ae-save').disabled = $('ae-discard').disabled = !n;
            $('ae-dirty').textContent = n ? n + (n === 1 ? ' artwork' : ' artworks') + ' changed, not saved' : '';
        }

        // Editing in the table
        $('ae-body').addEventListener('input', function (e) {
            var f = e.target.dataset.f; if (!f) return;
            if (ATTRS.indexOf(f) > -1) recordPick(f, e.target.value);
            var tr = e.target.closest('tr'), id = +tr.dataset.id;
            set(id, f, e.target.type === 'checkbox' ? (e.target.checked ? '1' : '') : e.target.value);
            var shop = tr.querySelector('.ae-shop'); if (shop) shop.innerHTML = shopHtml(byId[id]);
            e.target.classList.toggle('is-changed', changed(byId[id], f));
            if (f === 'availability') {
                e.target.dataset.status = e.target.value;
                var pr = tr.querySelector('[data-f=price]'); if (pr) pr.placeholder = C.price_optional.indexOf(e.target.value) > -1 ? '--' : 'price';
            }
            tr.classList.toggle('is-dirty', !!edits[id]);
            tr.classList.remove('is-error');
            status();
        });
        $('ae-body').addEventListener('change', function (e) {
            if (e.target.classList.contains('ae-tick')) {
                var id = +e.target.closest('tr').dataset.id;
                if (e.target.checked) ticked[id] = 1; else delete ticked[id];
                render();
            }
        });
        $('ae-check-all').addEventListener('change', function () {
            var on = this.checked;
            matches().forEach(function (r) { if (on) ticked[r.post_id] = 1; else delete ticked[r.post_id]; });
            render();
        });
        ['ae-f-category', 'ae-search', 'ae-f-availability', 'ae-f-pa_format', 'ae-f-pa_medium', 'ae-f-pa_year-created', 'ae-f-status'].forEach(function (id) {
            $(id).addEventListener(id === 'ae-search' ? 'input' : 'change', function () { page = 0; render(); });
        });
        document.querySelectorAll('#ae .ae-sort').forEach(function (th) {
            th.addEventListener('click', function () {
                sort = { key: th.dataset.sort, dir: sort.key === th.dataset.sort ? -sort.dir : (th.dataset.sort === 'title' ? 1 : -1) };
                render();
            });
        });
        $('ae-pages').addEventListener('click', function (e) { if (e.target.dataset.page) { page = +e.target.dataset.page; render(); window.scrollTo(0, $('ae').offsetTop); } });

        // Prints: at once (not on Save) - op hide | show | add | remove, for some artworks
        function printsOp(ids, op, types) {
            var body = new FormData();
            body.append('action', 'monospace_artwork_editor_prints');
            body.append('_ajax_nonce', C.nonce);
            body.append('op', op);
            (types || []).forEach(function (t) { body.append('types[]', t); });
            ids.forEach(function (id) { body.append('post_ids[]', id); });
            return fetch(C.ajax, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
                if (!res.success) throw new Error(res.data || 'Failed');
                res.data.rows.forEach(function (fresh) {
                    var old = byId[fresh.post_id], i = rows.indexOf(old);
                    // Keep unsaved edits; the categories may have changed (print), so the saved list is the fresh one
                    if (i > -1) rows[i] = fresh;
                    byId[fresh.post_id] = fresh;
                });
                return res.data;
            });
        }
        var ppId = null;
        function openPrints(cell) {
            var r = byId[+cell.closest('tr').dataset.id], pop = $('ae-ppop');
            ppId = r.post_id;
            drawPrints();
            pop.hidden = false;
            var b = cell.getBoundingClientRect(), host = $('ae').getBoundingClientRect();
            pop.style.top = (b.bottom - host.top + 4) + 'px';
            pop.style.left = Math.max(0, Math.min(b.left - host.left - 200, host.width - pop.offsetWidth)) + 'px';
        }
        function drawPrints() {
            var r = byId[ppId], P = r.prints, has = P.framed || P.unframed;
            $('ae-ppop-title').textContent = (r.num != null ? r.num + ' · ' : '') + get(r, 'title');
            var html = '';
            // The main control first: both prints at once
            if (has) {
                var all = allHidden(r), some = P.hidden.framed || P.hidden.unframed;
                html += '<div class="ae-pp-main' + (all ? ' is-hidden' : '') + '"><span><b>Prints</b> ' + (all ? 'Hidden - off the price card and the site' : some ? 'Partly hidden' : 'Shown') + '</span><span class="ae-pp-btns">' +
                    (some ? '<button type="button" class="button button-primary" data-pp="show">Show prints</button>' : '') +
                    (!all ? '<button type="button" class="button' + (some ? '' : ' button-primary') + '" data-pp="hide">Hide prints</button>' : '') + '</span></div>';
            }
            html += [['framed', 'Framed print'], ['unframed', 'Unframed print']].map(function (t) {
                var st = printState(r, t[0]);
                var lc = t[1].toLowerCase(), btn;
                if (P[t[0]]) btn = '<span class="ae-pp-btns"><button type="button" class="button button-small" data-pp="' + (P.hidden[t[0]] ? 'show' : 'hide') + '" data-type="' + t[0] + '">' + (P.hidden[t[0]] ? 'Show ' : 'Hide ') + lc + '</button>' +
                                   '<button type="button" class="button button-small" data-pp="remove" data-type="' + t[0] + '">Remove ' + lc + '</button></span>';
                else btn = P.job ? '' : '<button type="button" class="button button-small" data-pp="add" data-type="' + t[0] + '">Add ' + lc + '</button>';
                var stateHtml = (st[0] === 'job' || st[0] === 'err') ? '<a href="' + esc(C.prints) + '" target="_blank">' + esc(st[1]) + ' →</a>' : esc(st[1]);
                return '<div class="ae-pp-row"><span><b>' + t[1] + '</b> <span class="ae-pp-state">' + stateHtml + '</span></span>' + btn + '</div>';
            }).join('');
            $('ae-ppop-body').innerHTML = html;
            $('ae-ppop-msg').textContent = '';
        }
        $('ae-ppop-body').addEventListener('click', function (e) {
            var b = e.target.closest('[data-pp]'); if (!b || ppId == null) return;
            var op = b.dataset.pp, type = b.dataset.type, r = byId[ppId];
            if (op === 'remove' && !confirm('Remove ' + type + ' print of “' + get(r, 'title') + '”?\n\nIt leaves Printful and its product goes to the Trash.')) return;
            if ((op === 'hide' || op === 'show') && !confirm(visQ(op, type ? [type] : []))) return;
            b.disabled = true; $('ae-ppop-msg').textContent = 'Working…';
            printsOp([ppId], op, type ? [type] : []).then(function (d) {
                if (d.errors[ppId]) throw new Error(d.errors[ppId]);
                drawPrints(); render();
                $('ae-ppop-msg').textContent = op === 'add' ? 'Print being made - for sale in a few minutes.' : op === 'remove' ? 'Print removed.' : visDone(op, type ? [type] : []);
            }).catch(function (err) { b.disabled = false; $('ae-ppop-msg').textContent = err.message; });
        });
        $('ae-ppop-close').addEventListener('click', function () { $('ae-ppop').hidden = true; ppId = null; });
        $('ae-body').addEventListener('click', function (e) { var c = e.target.closest('.ae-prints'); if (c) openPrints(c); });
        // Show prints / Hide prints right in the row (both at once)
        $('ae-body').addEventListener('click', function (e) {
            var b = e.target.closest('.ae-pp-toggle'); if (!b) return;
            var id = +b.closest('tr').dataset.id, op = b.dataset.op;
            if (!confirm(visQ(op))) return;
            b.disabled = true; b.textContent = op === 'hide' ? 'Hiding…' : 'Showing…';
            printsOp([id], op).then(function (d) {
                if (d.errors[id]) throw new Error(d.errors[id]);
                $('ae-msg').className = ''; $('ae-msg').textContent = visDone(op);
                render();
            }).catch(function (err) { $('ae-msg').className = 'is-error'; $('ae-msg').textContent = err.message; render(); });
        });
        document.addEventListener('mousedown', function (e) { if (ppId != null && !e.target.closest('#ae-ppop') && !e.target.closest('.ae-prints')) { $('ae-ppop').hidden = true; ppId = null; } });

        $('ae-b-prints-go').addEventListener('click', function () {
            var v = $('ae-b-prints').value; if (!v) { $('ae-msg').textContent = 'Pick a prints action first.'; return; }
            var parts = v.split(':'), op = parts[0], types = parts[1] ? parts[1].split(',') : [], ids = Object.keys(ticked).map(Number);
            var words = $('ae-b-prints').options[$('ae-b-prints').selectedIndex].text;
            if (!confirm(words + ' - for ' + ids.length + ' selected artwork(s), right now?' + (op === 'remove' ? '\n\nRemoved prints leave Printful and their products go to the Trash.' : ''))) return;
            var done = 0, failed = 0, btn = this; btn.disabled = true;
            (function next() {
                if (!ids.length) { btn.disabled = false; $('ae-msg').className = failed ? 'is-error' : ''; $('ae-msg').textContent = words + ': ' + done + ' done' + (failed ? ', ' + failed + ' not - see the red rows.' : '.'); render(); return; }
                var batch = ids.splice(0, 5);
                $('ae-msg').textContent = words + '… ' + done + ' done';
                printsOp(batch, op, types).then(function (d) {
                    done += d.done.length;
                    Object.keys(d.errors).forEach(function (id) { errors[id] = d.errors[id]; failed++; });
                    next();
                }).catch(function (err) { batch.forEach(function (id) { errors[id] = err.message; failed++; }); next(); });
            })();
        });

        // Rollover preview: a bigger image beside the thumbnail, kept on screen
        var zoom = $('ae-zoom'), zoomImg = zoom.querySelector('img');
        $('ae-body').addEventListener('mouseover', function (e) {
            var t = e.target.closest('.ae-thumb[data-preview]'); if (!t) return;
            var r = byId[+t.closest('tr').dataset.id];
            zoomImg.src = t.dataset.preview;
            zoom.querySelector('span').textContent = (r.num != null ? r.num + ' · ' : '') + get(r, 'title');
            zoom.hidden = false;
            function place() {
                var b = t.getBoundingClientRect(), w = zoom.offsetWidth, h = zoom.offsetHeight;
                zoom.style.left = Math.min(b.right + 10, window.innerWidth - w - 10) + 'px';
                zoom.style.top = Math.max(10, Math.min(b.top + b.height / 2 - h / 2, window.innerHeight - h - 10)) + 'px';
            }
            place();
            zoomImg.onload = place; // its size is known once it has loaded
        });
        $('ae-body').addEventListener('mouseout', function (e) { if (e.target.closest('.ae-thumb')) zoom.hidden = true; });
        window.addEventListener('scroll', function () { zoom.hidden = true; }, { passive: true });

        // Needs attention fixes (they mark the row changed; Save sends them)
        $('ae-body').addEventListener('click', function (e) {
            var b = e.target.closest('[data-fix]'); if (!b) return;
            var id = +b.closest('tr').dataset.id, r = byId[id];
            if (b.dataset.fix === 'use-post') set(id, 'title', get(r, 'post_title'));
            if (b.dataset.fix === 'rename-post') set(id, 'post_title', get(r, 'title'));
            if (b.dataset.fix === 'ok') set(id, 'title_ok', '1');
            render();
        });

        // Categories & tags: a small editor under the cell
        var popId = null;
        function openPop(cell) {
            var tr = cell.closest('tr'), r = byId[+tr.dataset.id], cats = get(r, 'categories'), pop = $('ae-pop');
            popId = r.post_id;
            $('ae-pop-title').textContent = (r.num != null ? r.num + ' · ' : '') + get(r, 'title');
            $('ae-pop-cats').innerHTML = C.categories.map(function (c) {
                return '<label><input type="checkbox" value="' + c[0] + '"' + (cats.indexOf(c[0]) > -1 ? ' checked' : '') + '> ' + esc(c[1]) + '</label>';
            }).join('');
            $('ae-pop-filter').value = '';
            $('ae-pop-tags').value = get(r, 'tags').join(', ');
            pop.hidden = false;
            var b = cell.getBoundingClientRect(), host = $('ae').getBoundingClientRect();
            pop.style.top = (b.bottom - host.top + 4) + 'px';
            pop.style.left = Math.max(0, Math.min(b.left - host.left, host.width - pop.offsetWidth)) + 'px';
            $('ae-pop-filter').focus();
        }
        function closePop() { $('ae-pop').hidden = true; popId = null; }
        $('ae-body').addEventListener('click', function (e) { var c = e.target.closest('.ae-terms'); if (c) openPop(c); });
        $('ae-body').addEventListener('keydown', function (e) { var c = e.target.closest('.ae-terms'); if (c && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openPop(c); } });
        $('ae-pop-filter').addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            $('ae-pop-cats').querySelectorAll('label').forEach(function (l) { l.hidden = q && l.textContent.toLowerCase().indexOf(q) < 0; });
        });
        $('ae-pop-done').addEventListener('click', function () {
            if (popId == null) return;
            var cats = Array.prototype.map.call($('ae-pop-cats').querySelectorAll('input:checked'), function (i) { return +i.value; });
            var tags = $('ae-pop-tags').value.split(',');
            // Categories the editor doesn't list (print, available) are kept by the server, not sent
            set(popId, 'categories', cats.concat(byId[popId].categories.filter(function (id) { return !catName[id]; })));
            set(popId, 'tags', tags);
            closePop(); render();
        });
        $('ae-pop-cancel').addEventListener('click', closePop);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && popId != null) closePop(); });
        document.addEventListener('mousedown', function (e) { if (popId != null && !e.target.closest('#ae-pop') && !e.target.closest('.ae-terms')) closePop(); });

        // Bulk changes: they only mark the rows changed - Save still sends them
        $('ae-b-tax').addEventListener('change', function () {
            var v = $('ae-b-value'), t = this.value;
            v.innerHTML = ''; v.hidden = !t;
            if (t) v.innerHTML = optionsHtml(t, '');
        });
        $('ae-b-price-mode').addEventListener('change', function () { $('ae-b-price').hidden = !this.value; });
        // Bulk "Price card…": the presets, and back to the site's setting
        C.cards.forEach(function (o) {
            var opt = document.createElement('option');
            opt.value = o[0] === '' ? 'site' : o[0];
            opt.textContent = 'Price card: ' + (o[0] === '' ? 'the site setting' : o[1]);
            $('ae-b-card').appendChild(opt);
        });
        if (!C.cards.length) $('ae-b-card').closest('.ae-bulk-group').hidden = true;
        $('ae-b-sale').addEventListener('change', function () {
            var v = $('ae-b-sale-v');
            v.hidden = ['pct', 'usd', 'set', 'extra'].indexOf(this.value) < 0;
            v.placeholder = { pct: '% off', usd: '$ off', set: '$', extra: 'extra %' }[this.value] || '';
        });
        $('ae-b-terms').addEventListener('change', function () {
            $('ae-b-cat').hidden = this.value.indexOf('cat-') !== 0;
            $('ae-b-tags').hidden = this.value.indexOf('tag-') !== 0;
        });
        $('ae-b-apply').addEventListener('click', function () {
            var ids = Object.keys(ticked).map(Number), av = $('ae-b-availability').value, tax = $('ae-b-tax').value,
                val = $('ae-b-value').value, pm = $('ae-b-price-mode').value, pv = parseFloat($('ae-b-price').value),
                tm = $('ae-b-terms').value, cat = +$('ae-b-cat').value, tg = norm('tags', $('ae-b-tags').value.split(','));
            var termsOk = (tm.indexOf('cat-') === 0 && cat) || (tm.indexOf('tag-') === 0 && tg.length);
            var sm = $('ae-b-sale').value, sv = parseFloat($('ae-b-sale-v').value);
            var card = $('ae-b-card').value; // '' = untouched; 'site' = back to the site's setting
            var saleOk = sm && (['pct', 'usd', 'set', 'extra'].indexOf(sm) < 0 || !isNaN(sv)) && !(sm === 'pct' && (sv <= 0 || sv >= 100)) && !((sm === 'usd' || sm === 'set') && sv <= 0);
            if (tax && val) recordPick(tax, val);
            if (!av && !tax && !(pm && pv >= 0) && !termsOk && !saleOk && !card) { $('ae-msg').textContent = 'Pick something to change first.'; return; }
            ids.forEach(function (id) {
                var r = byId[id];
                if (av) set(id, 'availability', av);
                if (tax) set(id, tax, val);
                if (card) set(id, 'card', card === 'site' ? '' : card);
                if (pm && pv >= 0) {
                    var cur = parseFloat(get(r, 'price')) || 0;
                    var p = pm === 'set' ? pv : pm === 'up-usd' ? cur + pv : pm === 'down-usd' ? cur - pv : Math.round(cur * (pm === 'up' ? 1 + pv / 100 : 1 - pv / 100));
                    if (p > 0) set(id, 'price', String(p));
                }
                if (saleOk) {
                    var base = parseFloat(get(r, 'price')) || 0;
                    if (sm === 'pct' && base > 0) set(id, 'sale', String(Math.round(base * (1 - sv / 100))));
                    // A sale price at or above the price isn't a sale: those rows are left as they are
                    if (sm === 'usd' && base - sv > 0) set(id, 'sale', String(Math.round((base - sv) * 100) / 100));
                    if (sm === 'set' && sv < base) set(id, 'sale', String(sv));
                    if (sm === 'clear') set(id, 'sale', '');
                    if (sm.indexOf('mode-') === 0) set(id, 'sale_mode', sm.slice(5));
                    if (sm === 'extra') set(id, 'extra', String(sv));
                    if (sm === 'extra-clear') set(id, 'extra', '');
                    if (sm === 'adj-off') set(id, 'no_adj', '1');
                    if (sm === 'adj-on') set(id, 'no_adj', '');
                }
                if (termsOk) {
                    if (tm === 'cat-add') set(id, 'categories', get(r, 'categories').concat([cat]));
                    if (tm === 'cat-remove') set(id, 'categories', get(r, 'categories').filter(function (c) { return c !== cat; }));
                    var low = tg.map(function (t) { return t.toLowerCase(); });
                    if (tm === 'tag-add') set(id, 'tags', get(r, 'tags').concat(tg));
                    if (tm === 'tag-remove') set(id, 'tags', get(r, 'tags').filter(function (t) { return low.indexOf(t.toLowerCase()) < 0; }));
                }
            });
            $('ae-msg').textContent = 'Applied to ' + ids.length + ' selected - press Save changes to send them.';
            render();
        });
        $('ae-b-clear').addEventListener('click', function () { ticked = {}; render(); });
        // Lists: which artworks, nothing about them - the same files as Artwork Sheets' Save list / Load list
        $('ae-b-list-save').addEventListener('click', function () {
            var list = Object.keys(ticked).map(function (id) { return byId[id]; }).filter(Boolean);
            if (!list.length) return;
            var file = { type: 'monospace-artwork-list', version: 1, saved: new Date().toISOString(), from: 'Artwork Editor',
                artworks: list.map(function (r) { return { product_id: r.product_id, post_id: r.post_id, sku: r.sku }; }) };
            var a = document.createElement('a');
            a.href = URL.createObjectURL(new Blob([JSON.stringify(file, null, 1)], { type: 'application/json' }));
            a.download = 'artwork-list-' + new Date().toISOString().slice(0, 10) + '-' + list.length + '.json';
            document.body.appendChild(a); a.click(); a.remove();
            $('ae-msg').className = ''; $('ae-msg').textContent = 'Saved a list of ' + list.length + ' artworks.';
        });
        $('ae-list-load').addEventListener('click', function () { $('ae-list-file').click(); });
        $('ae-list-file').addEventListener('change', function () {
            var f = this.files[0]; this.value = ''; if (!f) return;
            f.text().then(function (txt) {
                var data = JSON.parse(txt), wanted = [];
                if (data && data.type === 'monospace-artwork-list' && Array.isArray(data.artworks)) wanted = data.artworks;
                // An Artwork Sheets "Export JSON" file: only its IDs (wc_product_id / wp_post_id both hold the product ID)
                else if (Array.isArray(data)) wanted = data.map(function (x) { return { product_id: +(x.wc_product_id || x.wp_post_id) || 0 }; });
                else throw new Error('Not an artwork list');
                var byProduct = {}, bySku = {};
                rows.forEach(function (r) { byProduct[r.product_id] = r; if (r.sku) bySku[r.sku.toLowerCase()] = r; });
                ticked = {}; var found = 0;
                wanted.forEach(function (w) {
                    var r = byProduct[+w.product_id] || byId[+w.post_id] || bySku[String(w.sku || '').toLowerCase()];
                    if (r && !ticked[r.post_id]) { ticked[r.post_id] = 1; found++; }
                });
                $('ae-f-status').value = 'selected'; page = 0;
                $('ae-msg').className = found < wanted.length ? 'is-error' : '';
                $('ae-msg').textContent = 'Loaded “' + f.name + '”: ' + found + ' of ' + wanted.length + ' artworks selected' +
                    (found < wanted.length ? ' (the rest aren’t artworks here - prints, or gone)' : '') + '.';
                render();
            }).catch(function (err) { $('ae-msg').className = 'is-error'; $('ae-msg').textContent = 'Couldn’t load the list: ' + err.message; });
        });

        // To the Artwork Sheets generator, with the selection (as product IDs)
        $('ae-b-sheets').addEventListener('click', function () {
            var ids = Object.keys(ticked).map(function (id) { return byId[id] && byId[id].product_id; }).filter(Boolean);
            if (ids.length) location.href = C.sheets + '&select=' + ids.join(',');
        });

        // Save: changed rows, 10 at a time
        $('ae-save').addEventListener('click', function () {
            var ids = Object.keys(edits), done = 0, failed = 0, btn = this;
            btn.disabled = $('ae-discard').disabled = true;
            $('ae-msg').className = '';
            function next() {
                if (!ids.length) {
                    $('ae-msg').className = failed ? 'is-error' : '';
                    $('ae-msg').textContent = 'Saved ' + done + (failed ? ', ' + failed + ' not saved - see the red rows (filter: Changed, not saved).' : '.');
                    render(); return;
                }
                var batch = ids.splice(0, 10), payload = {};
                batch.forEach(function (id) { payload[id] = edits[id]; });
                $('ae-msg').textContent = 'Saving… ' + done + ' of ' + (done + failed + batch.length + ids.length);
                var body = new FormData();
                body.append('action', 'monospace_artwork_editor_save');
                body.append('_ajax_nonce', C.nonce);
                body.append('rows', JSON.stringify(payload));
                fetch(C.ajax, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
                    if (!res.success) throw new Error(res.data || 'Save failed');
                    res.data.rows.forEach(function (fresh) {
                        var i = rows.indexOf(byId[fresh.post_id]);
                        if (i > -1) rows[i] = fresh;
                        byId[fresh.post_id] = fresh;
                        delete edits[fresh.post_id]; delete errors[fresh.post_id];
                        done++;
                    });
                    Object.keys(res.data.errors).forEach(function (id) { errors[id] = res.data.errors[id]; failed++; });
                    next();
                }).catch(function (err) {
                    batch.forEach(function (id) { errors[id] = err.message; failed++; });
                    next();
                });
            }
            next();
        });
        $('ae-discard').addEventListener('click', function () {
            if (!confirm('Discard every change that isn’t saved?')) return;
            edits = {}; errors = {}; $('ae-msg').textContent = ''; render();
        });
        window.addEventListener('beforeunload', function (e) { if (Object.keys(edits).length) { e.preventDefault(); e.returnValue = ''; } });

        // Details / Sales
        document.querySelectorAll('#ae .ae-views button').forEach(function (b) {
            b.addEventListener('click', function () {
                view = b.dataset.view;
                document.querySelectorAll('#ae .ae-views button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
                $('ae-table').dataset.view = view;
                $('ae-sales-line').hidden = view !== 'sales' || !C.sales;
                try { localStorage.setItem('msArtworkEditorView', view); } catch (e) {}
            });
        });
        if (C.sales) {
            $('ae-sales-line').innerHTML = C.sales.items.map(function (i) { return '<a href="' + esc(i[1]) + '">' + esc(i[0]) + '</a>'; }).join(' · ') +
                ' · <a href="' + esc(C.sales.dashboard) + '"><b>Open the Sales Dashboard →</b></a>';
        }
        try { var v = localStorage.getItem('msArtworkEditorView'); if (v === 'sales') document.querySelector('#ae .ae-views [data-view=sales]').click(); } catch (e) {}

        // The rows load after the page: the shop price needs the Sales Dashboard's rules, which only run outside the admin screen
        $('ae-body').innerHTML = '<tr><td colspan="20" class="ae-loading">Loading every artwork…</td></tr>';
        var body = new FormData();
        body.append('action', 'monospace_artwork_editor_rows');
        body.append('_ajax_nonce', C.nonce);
        fetch(C.ajax, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (res) {
            if (!res.success) throw new Error(res.data || 'Could not load the artworks');
            rows = res.data;
            rows.forEach(function (r) { byId[r.post_id] = r; });
            // From the Artwork Sheets generator (?select=<product IDs>): those, selected and shown
            var incoming = new URLSearchParams(location.search).get('select');
            if (incoming) {
                var want = incoming.split(',').map(Number), found = 0;
                rows.forEach(function (r) { if (want.indexOf(r.product_id) > -1) { ticked[r.post_id] = 1; found++; } });
                $('ae-f-status').value = 'selected';
                $('ae-msg').textContent = found + ' of ' + want.length + ' selected from Artwork Sheets' + (found < want.length ? ' (the rest are prints or products without an artwork post)' : '') + ' - ready for bulk changes.';
                history.replaceState(null, '', location.pathname + '?page=monospace-artwork-editor');
            }
            if (C.orphans.length) {
                $('ae-orphans').hidden = false;
                $('ae-orphans').innerHTML = '⚠ ' + (C.orphans.length === 1 ? '1 Original product has' : C.orphans.length + ' Original products have') + ' no artwork post (it was deleted), so it isn’t in this table: ' +
                    C.orphans.map(function (o) { return '<a href="' + esc(o[3]) + '" target="_blank">' + esc(o[1]) + '</a> (' + esc(o[2] || '#' + o[0]) + ')'; }).join(', ') +
                    '. Restore its post from the Trash, or delete the product if the piece is gone.';
            }
            render();
        }).catch(function (err) { $('ae-body').innerHTML = '<tr><td colspan="20" class="ae-loading">' + esc(err.message) + '</td></tr>'; });
    })();
    </script>
    <?php
}

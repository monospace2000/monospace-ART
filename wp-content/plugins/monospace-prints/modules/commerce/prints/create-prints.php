<?php
/**
 * Print products, the building blocks (used by the background job, print-jobs.php):
 *   monospace_prints_create_product() - a WooCommerce variable product for an artwork
 *     (print-templates.php): title, Printful catalog description, category print, R SKU, attribute
 *     terms, prices; saved twice so the product.updated webhook tells Printful (needs "Import not
 *     synced products" on in Printful)
 *   monospace_prints_sync_product()   - once Printful lists it: every variation linked to its catalog
 *     variant + the original image as print file (PUT /sync/variant/@<variation>)
 *   monospace_prints_link()           - links a print to its artwork (_print_product_id /
 *     _framed_print_product_id + Quick Product's link function): the price card's tabs appear
 * Mockups are drawn on the site: print-mockups.php.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
 * HELPERS
 * ============================================================ */

/** Next MNSPC SKU for a print - the Quick Product rule: highest MNSPC number + 1, type R */
function monospace_prints_next_sku() {
    global $wpdb;
    $next = 1;
    foreach ( $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE 'MNSPC-%'" ) as $sku ) {
        if ( preg_match( '/^MNSPC-\d{4}-[A-Z]-(\d{4})$/', $sku, $m ) && (int) $m[1] >= $next ) $next = (int) $m[1] + 1;
    }
    return sprintf( 'MNSPC-%s-R-%04d', date( 'Y' ), $next );
}

/** Print products made for this artwork but not linked to it yet (e.g. the 2026-10-15 test print) */
function monospace_prints_unlinked( $post_id ) {
    $ids = get_posts( [
        'post_type' => 'product', 'post_status' => 'any', 'numberposts' => 10, 'fields' => 'ids',
        'meta_query' => [ [ 'key' => '_related_post_id', 'value' => (string) $post_id ] ],
        'tax_query'  => [ [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'print' ] ],
    ] );
    $linked = [ (int) get_post_meta( $post_id, '_print_product_id', true ), (int) get_post_meta( $post_id, '_framed_print_product_id', true ) ];
    $out = [];
    foreach ( $ids as $id ) {
        $p = wc_get_product( $id );
        if ( ! $p || ! $p->is_type( 'variable' ) || in_array( $id, $linked, true ) ) continue;
        $out[ monospace_print_type_of( $p ) ][] = $id;
    }
    return $out;
}

/**
 * The image an artwork's prints are made from: its own print file (post meta _ms_print_file, set by
 * "Replace print file" - e.g. a higher-resolution scan) or else the featured image
 */
function monospace_prints_file_for( $post_id ) {
    $file = (int) get_post_meta( $post_id, '_ms_print_file', true );
    return $file && wp_attachment_is_image( $file ) ? $file : (int) get_post_thumbnail_id( $post_id );
}

/* ------------------------------------------------------------------------------------------------
 * Watermarks (2026-10-17). The Image Watermark plugin stamps the original upload in place (meta
 * iw-is-watermarked) and keeps the clean original in uploads/iw-backup/ - a print made from a stamped
 * file carries the watermark. So every file that goes to Printful passes monospace_prints_usable_file():
 * a stamped image is swapped for a clean copy made from its backup (once - reused after), and with no
 * backup the action is refused. Clean copies: "<name>-print.<ext>", meta _ms_print_file_clean (+
 * _ms_print_file_clean_of = the stamped image). The website keeps its watermarked images.
 * ------------------------------------------------------------------------------------------------ */

/** The image carries the watermark (and isn't one of the clean print-file copies) */
function monospace_prints_is_watermarked( $image_id ) {
    return $image_id && get_post_meta( $image_id, 'iw-is-watermarked', true ) && ! get_post_meta( $image_id, '_ms_print_file_clean', true );
}

/** Path of the plugin's clean backup of a stamped image, or '' */
function monospace_prints_watermark_backup( $image_id ) {
    $base = wp_upload_dir()['basedir'];
    $paths = array_unique( array_filter( [
        ltrim( str_replace( $base, '', (string) wp_get_original_image_path( $image_id ) ), '/' ),
        (string) get_post_meta( $image_id, '_wp_attached_file', true ),
    ] ) );
    foreach ( $paths as $rel ) {
        if ( file_exists( "$base/iw-backup/$rel" ) ) return "$base/iw-backup/$rel";
    }
    return '';
}

/** For the screens: 'clean' | 'watermarked' (a clean copy will be used) | 'blocked' (no clean copy) */
function monospace_prints_file_state( $post_id ) {
    $image = monospace_prints_file_for( $post_id );
    if ( ! monospace_prints_is_watermarked( $image ) ) return 'clean';
    return monospace_prints_clean_copy_of( $image ) || monospace_prints_watermark_backup( $image ) ? 'watermarked' : 'blocked';
}

/** A clean copy made earlier from this stamped image, or 0 */
function monospace_prints_clean_copy_of( $image_id ) {
    $ids = get_posts( [ 'post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'fields' => 'ids',
        'meta_query' => [ [ 'key' => '_ms_print_file_clean_of', 'value' => (int) $image_id ] ] ] );
    return $ids ? (int) $ids[0] : 0;
}

/**
 * The file to print for an artwork: $image_id (a chosen one), else its print file - swapped for a clean
 * copy when it's watermarked; the copy then becomes the artwork's print file. Throws when a stamped
 * image has no clean backup.
 */
function monospace_prints_usable_file( $post_id, $image_id = 0 ) {
    $image_id = (int) ( $image_id ?: monospace_prints_file_for( $post_id ) );
    if ( ! monospace_prints_is_watermarked( $image_id ) ) return $image_id;

    $clean = monospace_prints_clean_copy_of( $image_id );
    if ( ! $clean ) {
        $backup = monospace_prints_watermark_backup( $image_id );
        if ( ! $backup ) throw new Exception( 'The print file is watermarked and there is no clean copy on the server - use “Replace print file” with an unwatermarked photo first.' );
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $base = wp_upload_dir()['basedir'];
        $info = pathinfo( $backup );
        $name = wp_unique_filename( $base, $info['filename'] . '-print.' . $info['extension'] );
        if ( ! copy( $backup, "$base/$name" ) ) throw new Exception( 'Could not copy the clean original of the print file.' );
        $title = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );
        // Registered directly - an upload filter (the watermark plugin's) never sees it
        $clean = wp_insert_attachment( [ 'post_mime_type' => wp_check_filetype( $name )['type'], 'post_title' => "$title - print file",
                                         'post_status' => 'inherit', 'post_parent' => $post_id ], "$base/$name", $post_id, true );
        if ( is_wp_error( $clean ) ) { @unlink( "$base/$name" ); throw new Exception( $clean->get_error_message() ); }
        wp_update_attachment_metadata( $clean, wp_generate_attachment_metadata( $clean, "$base/$name" ) );
        update_post_meta( $clean, '_wp_attachment_image_alt', "$title, print file" );
        update_post_meta( $clean, '_ms_print_file_clean', 1 );
        update_post_meta( $clean, '_ms_print_file_clean_of', $image_id );
    }
    if ( $image_id === (int) monospace_prints_file_for( $post_id ) ) update_post_meta( $post_id, '_ms_print_file', $clean );
    return (int) $clean;
}

/** "vertical" / "horizontal" - the image's orientation (kept in the print job) */
function monospace_prints_orientation( $px ) {
    return $px[1] > $px[0] ? 'vertical' : 'horizontal';
}

/* ============================================================
 * STEPS
 * ============================================================ */

/** Create one print product. $prices: template variant key => price */
function monospace_prints_create_product( $post_id, $type, $prices, $image_id ) {
    $tpl = monospace_print_templates()[ $type ];

    // Attribute terms the chosen variants use
    $terms = [];
    foreach ( array_keys( $prices ) as $key ) {
        foreach ( $tpl['variants'][ $key ]['attributes'] as $tax => $slug ) {
            $t = get_term_by( 'slug', $slug, $tax );
            if ( ! $t ) throw new Exception( "Attribute term $tax/$slug is missing" );
            $terms[ $tax ][ $t->term_id ] = $t->term_id;
        }
    }

    $product = new WC_Product_Variable();
    // "Print: " tells it apart from the original in the product list, cart and orders (owner's wish, 2026-10-16)
    $product->set_name( MONOSPACE_PRINT_NAME_PREFIX . html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' ) );
    $product->set_status( 'publish' );
    $product->set_catalog_visibility( 'visible' );
    $product->set_description( monospace_printful_catalog_description( $tpl['catalog_product'] ) );
    $product->set_category_ids( [ get_term_by( 'slug', 'print', 'product_cat' )->term_id ] );
    $product->set_image_id( $image_id );
    $product->set_sku( monospace_prints_next_sku() );
    $attributes = [];
    $position = 0;
    foreach ( $tpl['attributes'] as $tax ) {
        $a = new WC_Product_Attribute();
        $a->set_id( wc_attribute_taxonomy_id_by_name( $tax ) );
        $a->set_name( $tax );
        $a->set_options( array_values( $terms[ $tax ] ) );
        $a->set_position( $position++ );
        $a->set_visible( true );
        $a->set_variation( true );
        $attributes[] = $a;
    }
    $product->set_attributes( $attributes );
    $product->update_meta_data( '_related_post_id', $post_id );
    $pid = $product->save();
    foreach ( $terms as $tax => $ids ) wp_set_object_terms( $pid, array_values( $ids ), $tax );

    foreach ( $prices as $key => $price ) {
        $v = $tpl['variants'][ $key ];
        $variation = new WC_Product_Variation();
        $variation->set_parent_id( $pid );
        $variation->set_attributes( $v['attributes'] );
        $variation->set_regular_price( (string) $price );
        $variation->set_stock_status( 'instock' );
        $variation->set_sku( $product->get_sku() . '-' . $key );
        $variation->update_meta_data( '_ms_printful_catalog_variant', $v['catalog'] );
        $variation->save();
    }

    // Save again: woocommerce_update_product -> the "product.updated" webhook to Printful
    WC_Product_Variable::sync( $pid );
    $product = wc_get_product( $pid );
    $product->save();
    do_action( 'woocommerce_update_product', $pid, $product );
    return $pid;
}

/**
 * Link a product's variations in Printful. Returns 'waiting' (Printful doesn't list it yet), 'linked',
 * [ 'wait' => seconds ] (rate-limited; already-linked variations are skipped next time) or an error
 * text. $force: put the print file on every variation, linked or not (new print file).
 */
function monospace_prints_sync_product( $type, $pid, $image_id, $force = false ) {
    $r = monospace_printful( 'GET', '/sync/products/@' . $pid );
    if ( $r['code'] === 404 ) return 'waiting';
    if ( $wait = monospace_printful_wait( $r ) ) return [ 'wait' => $wait ];
    if ( ! $r['ok'] ) return 'Printful: ' . $r['error'];

    $synced = [];
    foreach ( $r['result']['sync_variants'] as $sv ) $synced[ (int) $sv['external_id'] ] = ! empty( $sv['synced'] );
    foreach ( wc_get_product( $pid )->get_children() as $vid ) {
        if ( ! array_key_exists( $vid, $synced ) ) return 'waiting'; // a new variation Printful hasn't picked up yet
    }
    $file = wp_get_original_image_url( $image_id );

    foreach ( wc_get_product( $pid )->get_children() as $vid ) {
        if ( ! $force && ! empty( $synced[ $vid ] ) ) continue;
        $v = monospace_print_variant_for( $type, wc_get_product( $vid ) );
        if ( ! $v ) return "Variation $vid doesn't match a print size / frame";
        $put = monospace_printful( 'PUT', '/sync/variant/@' . $vid, [ 'variant_id' => $v['catalog'], 'files' => [ [ 'type' => 'default', 'url' => $file ] ] ] );
        if ( $wait = monospace_printful_wait( $put ) ) return [ 'wait' => $wait ]; // rate limit: the job picks up here later
        if ( ! $put['ok'] ) return "Printful refused variation $vid: " . $put['error'];
    }
    return 'linked';
}

/** Seconds Printful asks us to wait (rate limit), or 0 when the reply isn't a rate limit */
function monospace_printful_wait( $r ) {
    if ( $r['ok'] ) return 0;
    if ( $r['code'] !== 429 && stripos( $r['error'], 'too many requests' ) === false ) return 0;
    return preg_match( '/after (\d+) second/i', $r['error'], $m ) ? (int) $m[1] + 2 : 62;
}

/** Link to the artwork - the price card's print tabs appear */
function monospace_prints_link( $post_id, $type, $pid ) {
    update_post_meta( $post_id, monospace_print_templates()[ $type ]['meta_key'], $pid );
    // When - so a save from an editor opened before this keeps the link (print-jobs.php)
    $stamps = (array) get_post_meta( $post_id, '_ms_print_linked_at', true );
    $stamps[ $type ] = [ 'at' => time(), 'id' => (int) $pid ];
    update_post_meta( $post_id, '_ms_print_linked_at', $stamps );
    if ( function_exists( '\QuickProduct\monospace_sync_print_product_link' ) ) {
        \QuickProduct\monospace_sync_print_product_link( $post_id, $pid );
    } else {
        update_post_meta( $pid, '_related_post_id', $post_id );
        wp_set_object_terms( $pid, 'print', 'product_cat' );
    }
    $product = wc_get_product( $pid );
    if ( $product && trim( (string) $product->get_sku() ) === '' ) { $product->set_sku( monospace_prints_next_sku() ); $product->save(); }
}

/* ============================================================
 * SIZES AND FRAMES ON EXISTING PRINTS (Prints > All prints)
 * ============================================================ */

/** Save a variable product so WooCommerce's "product.updated" webhook tells Printful */
function monospace_prints_resave( $pid ) {
    WC_Product_Variable::sync( $pid );
    $product = wc_get_product( $pid );
    $product->save();
    do_action( 'woocommerce_update_product', $pid, $product );
}

/** The template keys a print product has: [ key => variation ID ] */
function monospace_prints_variant_keys( $pid, $type ) {
    $keys = [];
    foreach ( wc_get_product( $pid )->get_children() as $vid ) {
        $m = monospace_print_variant_for( $type, wc_get_product( $vid ) );
        if ( $m ) $keys[ $m['key'] ] = $vid;
    }
    return $keys;
}

/** Add sizes / frames ($prices: key => price) a print product lacks. Returns the new variation IDs. */
function monospace_prints_add_variants( $pid, $type, $prices ) {
    $tpl = monospace_print_templates()[ $type ];
    $product = wc_get_product( $pid );
    $have = monospace_prints_variant_keys( $pid, $type );
    $attributes = $product->get_attributes();
    $new = [];
    foreach ( $prices as $key => $price ) {
        if ( isset( $have[ $key ] ) || empty( $tpl['variants'][ $key ] ) ) continue;
        $v = $tpl['variants'][ $key ];
        foreach ( $v['attributes'] as $tax => $slug ) {
            $term = get_term_by( 'slug', $slug, $tax );
            if ( ! $term ) throw new Exception( "Attribute term $tax/$slug is missing" );
            if ( isset( $attributes[ $tax ] ) && ! in_array( $term->term_id, $attributes[ $tax ]->get_options(), true ) ) {
                $attributes[ $tax ]->set_options( array_merge( $attributes[ $tax ]->get_options(), [ $term->term_id ] ) );
            }
            wp_set_object_terms( $pid, [ $term->term_id ], $tax, true );
        }
        $variation = new WC_Product_Variation();
        $variation->set_parent_id( $pid );
        $variation->set_attributes( $v['attributes'] );
        $variation->set_regular_price( (string) $price );
        $variation->set_stock_status( 'instock' );
        $variation->set_sku( $product->get_sku() . '-' . $key );
        $variation->update_meta_data( '_ms_printful_catalog_variant', $v['catalog'] );
        $new[] = $variation->save();
    }
    if ( $new ) {
        $product->set_attributes( $attributes );
        $product->save();
        monospace_prints_resave( $pid );
    }
    return $new;
}

/** Remove sizes / frames from a print product, in WooCommerce and Printful. Never the last one. Returns how many. */
function monospace_prints_remove_variants( $pid, $type, $keys ) {
    $have = monospace_prints_variant_keys( $pid, $type );
    $drop = array_intersect_key( $have, array_flip( $keys ) );
    if ( ! $drop ) return 0;
    if ( count( $drop ) >= count( wc_get_product( $pid )->get_children() ) ) throw new Exception( 'that would remove every variant' );

    $product_image = (int) wc_get_product( $pid )->get_image_id();
    foreach ( $drop as $vid ) {
        $r = monospace_printful( 'DELETE', '/sync/variant/@' . $vid );
        if ( ! $r['ok'] && $r['code'] !== 404 ) throw new Exception( 'Printful: ' . $r['error'] );
        $variation = wc_get_product( $vid );
        $image = (int) $variation->get_image_id();
        $variation->delete( true );
        if ( $image && $image !== $product_image && get_post_meta( $image, '_ms_print_mockup', true ) ) wp_delete_attachment( $image, true );
    }

    // Attribute options: only the terms the remaining variations use
    $product = wc_get_product( $pid );
    $used = [];
    foreach ( $product->get_children() as $vid ) {
        foreach ( wc_get_product( $vid )->get_attributes() as $tax => $slug ) {
            $term = get_term_by( 'slug', $slug, $tax );
            if ( $term ) $used[ $tax ][ $term->term_id ] = $term->term_id;
        }
    }
    $attributes = $product->get_attributes();
    foreach ( $attributes as $tax => $attribute ) {
        if ( empty( $used[ $tax ] ) ) continue;
        $attribute->set_options( array_values( $used[ $tax ] ) );
        wp_set_object_terms( $pid, array_values( $used[ $tax ] ), $tax );
    }
    $product->set_attributes( $attributes );
    $product->save();
    monospace_prints_resave( $pid );
    return count( $drop );
}

/* ============================================================
 * REMOVE AN ARTWORK'S PRINT (Artworks > Artwork Editor, 2026-10-19)
 * ============================================================ */

/**
 * Take one of an artwork's prints away for good ($type: unframed | framed): out of Printful, the
 * WooCommerce product to the Trash (restorable there for 30 days - but Printful has forgotten it then,
 * so a restored print needs "Re-link in Printful" on Prints > All prints), unlinked from the artwork.
 * No prints left = the post leaves the "print" category and nothing stays hidden.
 */
function monospace_prints_remove( $post_id, $type ) {
    $templates = monospace_print_templates();
    if ( empty( $templates[ $type ] ) ) throw new Exception( 'Unknown print type.' );
    if ( monospace_prints_get_job( $post_id ) ) throw new Exception( 'A print job for this artwork is running - wait until it\'s done.' );
    $key = $templates[ $type ]['meta_key'];
    $pid = (int) get_post_meta( $post_id, $key, true );
    if ( ! $pid ) throw new Exception( 'This artwork has no ' . strtolower( $templates[ $type ]['label'] ) . '.' );

    $product = wc_get_product( $pid );
    if ( $product ) {
        // Printful first: if that fails, nothing has changed yet
        $r = monospace_printful( 'DELETE', '/sync/products/@' . $pid );
        if ( ! $r['ok'] && $r['code'] !== 404 ) throw new Exception( 'Printful: ' . $r['error'] );
        // Hidden: it leaves the hidden list
        if ( function_exists( 'monospace_prints_hidden' ) ) {
            $hidden = monospace_prints_hidden( $post_id );
            unset( $hidden[ $type ] );
            monospace_prints_save_hidden( $post_id, $hidden );
        }
        $product->delete( false ); // the Trash
    }

    delete_post_meta( $post_id, $key );
    $stamps = (array) get_post_meta( $post_id, '_ms_print_linked_at', true );
    unset( $stamps[ $type ] );
    $stamps ? update_post_meta( $post_id, '_ms_print_linked_at', $stamps ) : delete_post_meta( $post_id, '_ms_print_linked_at' );

    if ( ! monospace_prints_of_artwork( $post_id ) ) {
        delete_post_meta( $post_id, '_ms_prints_paused' );
        $print_cat = get_term_by( 'slug', 'print', 'category' );
        if ( $print_cat ) wp_remove_object_terms( $post_id, (int) $print_cat->term_id, 'category' );
    }
    delete_transient( 'ms_pf_index' );
    if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache( get_permalink( $post_id ) );
    return $pid;
}

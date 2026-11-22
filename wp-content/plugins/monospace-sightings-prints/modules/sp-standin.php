<?php
/**
 * Sightings prints: from a Sightings drawing to hidden, sellable print products.
 *
 * The art site's print machinery (monospace-prints) works on a post: its print file, its title, its Printful products. So each
 * drawing + layout + canvas gets a private STAND-IN POST here (category "Sightings prints", meta _ms_sp_key) whose print file is
 * the composed drawing-and-caption (sp-composite.php). monospace_prints_queue() then does the rest unchanged: WooCommerce
 * products, Printful sync, mockups. Nothing is created unless monospace_sp_queue() is called (the owner's "no automatic prints" rule).
 *
 * The Sightings site is read through its public REST API (the drawings' order, captions, picture) and straight from its folder on
 * the same hosting account (the clean originals in uploads/iw-backup, never the watermarked copies).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_SP_SITE = 'https://sightings.monospace.com';

/** Sightings' uploads folder on this account */
function monospace_sp_uploads_dir() {
    return dirname( dirname( rtrim( ABSPATH, '/' ) ) ) . '/sightings.monospace.com/public_html/wp-content/uploads';
}

function monospace_sp_get( $path ) {
    $r = wp_remote_get( MONOSPACE_SP_SITE . '/wp-json/wp/v2/' . $path, [ 'timeout' => 20 ] );
    if ( is_wp_error( $r ) || wp_remote_retrieve_response_code( $r ) !== 200 ) throw new Exception( 'Could not read the Sightings site (' . $path . ').' );
    $data = json_decode( wp_remote_retrieve_body( $r ), true );
    if ( ! is_array( $data ) ) throw new Exception( 'The Sightings site answered with something unexpected (' . $path . ').' );
    return $data;
}

/** A caption from a post's HTML: plain text, the straight quotes curled, no picture */
function monospace_sp_caption_text( $html ) {
    $html = preg_replace( '#<figure.*?</figure>#s', '', $html );
    $text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) );
    return html_entity_decode( wptexturize( $text ), ENT_QUOTES, 'UTF-8' );
}

/**
 * The Sightings drawings in publish order: [ post id => [ 'id', 'number', 'date', 'link', 'text', 'media' ] ]. "No. N" is the place in
 * publish-date order (oldest first, ties by post id), owner 2026-11-19 - until the Sightings site stamps its own permanent number (sn_number,
 * 2026-11-20), which wins. Cached an hour.
 */
function monospace_sp_drawings( $refresh = false ) {
    $drawings = $refresh ? false : get_transient( 'ms_sp_drawings' );
    if ( is_array( $drawings ) ) return $drawings;
    $posts = monospace_sp_get( 'posts?per_page=100&orderby=date&order=asc&_fields=id,date,link,content,meta' );
    usort( $posts, function ( $a, $b ) { return [ $a['date'], $a['id'] ] <=> [ $b['date'], $b['id'] ]; } );
    $drawings = [];
    foreach ( $posts as $i => $p ) {
        $html = (string) ( $p['content']['rendered'] ?? '' );
        $drawings[ (int) $p['id'] ] = [
            'id' => (int) $p['id'], 'number' => (int) ( $p['meta']['sn_number'] ?? 0 ) ?: $i + 1, 'date' => $p['date'], 'link' => $p['link'],
            'text' => monospace_sp_caption_text( $html ), 'media' => preg_match( '/wp-image-(\d+)/', $html, $m ) ? (int) $m[1] : 0,
        ];
    }
    set_transient( 'ms_sp_drawings', $drawings, HOUR_IN_SECONDS );
    return $drawings;
}

function monospace_sp_drawing( $sightings_post_id ) {
    $d = monospace_sp_drawings()[ (int) $sightings_post_id ] ?? null;
    if ( ! $d || ! $d['media'] || $d['text'] === '' ) throw new Exception( "Sightings post $sightings_post_id has no picture or caption." );
    return $d;
}

/** Path of the clean original of a Sightings media item: the watermark plugin's backup, else the upload itself */
function monospace_sp_clean_source( $media_id ) {
    $m = monospace_sp_get( 'media/' . (int) $media_id . '?_fields=source_url' );
    if ( ! preg_match( '#/wp-content/uploads/(.+)$#', (string) ( $m['source_url'] ?? '' ), $x ) ) throw new Exception( "No file for Sightings media $media_id." );
    $base = monospace_sp_uploads_dir();
    foreach ( [ "$base/iw-backup/{$x[1]}", "$base/{$x[1]}" ] as $path ) {
        if ( is_readable( $path ) ) return $path;
    }
    throw new Exception( 'The original file is not on this server: ' . $x[1] );
}

/** "sightings-no-22-dark-4x5" */
function monospace_sp_key( $sightings_post_id, $layout, $canvas ) {
    return (int) $sightings_post_id . '-' . $layout . '-' . $canvas;
}

/** The stand-in post for a drawing + layout + canvas, or 0 */
function monospace_sp_standin_for( $sightings_post_id, $layout, $canvas = '4x5' ) {
    $ids = get_posts( [ 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
        'meta_query' => [ [ 'key' => '_ms_sp_key', 'value' => monospace_sp_key( $sightings_post_id, $layout, $canvas ) ] ] ] );
    return $ids ? (int) $ids[0] : 0;
}

function monospace_sp_is_standin( $post_id ) {
    return (bool) get_post_meta( $post_id, '_ms_sp_key', true );
}

/** The hidden post category the stand-ins live in (made once) */
function monospace_sp_category() {
    $t = get_term_by( 'slug', 'sightings-prints', 'category' );
    if ( $t ) return (int) $t->term_id;
    $new = wp_insert_term( 'Sightings prints', 'category', [ 'slug' => 'sightings-prints' ] );
    if ( is_wp_error( $new ) ) throw new Exception( $new->get_error_message() );
    return (int) $new['term_id'];
}

/**
 * Make the stand-in post (private) with its composed print file. Returns the post ID. Does not touch Printful.
 * An existing stand-in for the same drawing + layout + canvas is returned as it is.
 */
function monospace_sp_create_standin( $sightings_post_id, $layout, $canvas = '4x5' ) {
    $existing = monospace_sp_standin_for( $sightings_post_id, $layout, $canvas );
    if ( $existing ) return $existing;

    $d = monospace_sp_drawing( $sightings_post_id );
    $source = monospace_sp_clean_source( $d['media'] );
    $label = monospace_sp_layouts()[ $layout ]['label'];
    $title = sprintf( 'Sightings No. %d (%s)', $d['number'], $label );

    $up = wp_upload_dir();
    $dir = $up['basedir'] . '/sightings-prints';
    wp_mkdir_p( $dir );
    $name = wp_unique_filename( $dir, sprintf( 'sightings-no-%d-%s-%s.jpg', $d['number'], str_replace( ' ', '-', $label ), $canvas ) );
    $file = "$dir/$name";
    monospace_sp_render( $source, $d['text'], $layout, $canvas, $file );
    $upright_file = null;
    if ( $canvas === '2x3' ) { // the postcard: Printful gets the picture turned sideways; the upright one is what buyers see
        $upright_file = $file;
        $file = monospace_sp_card_file_name( $dir, $d['number'], $label );
        monospace_sp_turn_for_card( $upright_file, $file );
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $post_id = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'private', 'post_title' => $title, 'post_content' => $d['text'],
        'post_category' => [ monospace_sp_category() ] ], true );
    if ( is_wp_error( $post_id ) ) { @unlink( $file ); throw new Exception( $post_id->get_error_message() ); }
    // Registered directly (no upload filter): the art site's own watermark must never touch a print file
    $att = wp_insert_attachment( [ 'post_mime_type' => 'image/jpeg', 'post_title' => "$title - print file", 'post_status' => 'inherit', 'post_parent' => $post_id ],
        $file, $post_id, true );
    if ( is_wp_error( $att ) ) { @unlink( $file ); wp_delete_post( $post_id, true ); throw new Exception( $att->get_error_message() ); }
    wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $file ) );
    update_post_meta( $att, '_wp_attachment_image_alt', "Sightings No. {$d['number']}: " . $d['text'] );
    update_post_meta( $att, '_ms_print_file_clean', 1 );
    update_post_meta( $att, '_ms_sp_composite', 1 );
    set_post_thumbnail( $post_id, $att );
    update_post_meta( $post_id, '_ms_print_file', $att );
    if ( $upright_file ) {
        $up_att = monospace_sp_register_upright( $upright_file, $title, $post_id, $d );
        set_post_thumbnail( $post_id, $up_att );
        update_post_meta( $post_id, '_ms_sp_upright_file', $up_att );
    }
    update_post_meta( $post_id, '_ms_sp_key', monospace_sp_key( $sightings_post_id, $layout, $canvas ) );
    update_post_meta( $post_id, '_ms_sp_source', [ 'post' => (int) $sightings_post_id, 'number' => $d['number'], 'layout' => $layout, 'canvas' => $canvas, 'link' => $d['link'] ] );
    return (int) $post_id;
}

/**
 * Draw a drawing's print file again (new caption typesetting, a changed caption...) as a NEW file, so Printful fetches fresh. A stand-in
 * whose prints are on sale gets the file put on every Printful variant (monospace_prints_queue_refresh: no orders, the mockups follow);
 * one without products yet just gets the file. Refused while a job runs for it. Returns [ 'old' => id, 'new' => id, 'refreshed' => bool ].
 */
function monospace_sp_regenerate( $sightings_post_id, $layout, $canvas = '4x5' ) {
    $post_id = monospace_sp_standin_for( $sightings_post_id, $layout, $canvas );
    if ( ! $post_id ) throw new Exception( 'No stand-in for this drawing and layout yet.' );
    if ( monospace_prints_get_job( $post_id ) ) throw new Exception( 'A print job is running for it; try again when it is done.' );
    $d = monospace_sp_drawing( $sightings_post_id );
    $source = monospace_sp_clean_source( $d['media'] );
    $label = monospace_sp_layouts()[ $layout ]['label'];
    $title = sprintf( 'Sightings No. %d (%s)', $d['number'], $label );
    $up = wp_upload_dir();
    $dir = $up['basedir'] . '/sightings-prints';
    wp_mkdir_p( $dir );
    $file = $dir . '/' . wp_unique_filename( $dir, sprintf( 'sightings-no-%d-%s-%s.jpg', $d['number'], str_replace( ' ', '-', $label ), $canvas ) );
    monospace_sp_render( $source, $d['text'], $layout, $canvas, $file );
    $upright_file = null;
    if ( $canvas === '2x3' ) { // the postcard: see monospace_sp_create_standin
        $upright_file = $file;
        $file = monospace_sp_card_file_name( $dir, $d['number'], $label );
        monospace_sp_turn_for_card( $upright_file, $file );
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $att = wp_insert_attachment( [ 'post_mime_type' => 'image/jpeg', 'post_title' => "$title - print file", 'post_status' => 'inherit', 'post_parent' => $post_id ],
        $file, $post_id, true );
    if ( is_wp_error( $att ) ) { @unlink( $file ); throw new Exception( $att->get_error_message() ); }
    wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $file ) );
    update_post_meta( $att, '_wp_attachment_image_alt', "Sightings No. {$d['number']}: " . $d['text'] );
    update_post_meta( $att, '_ms_print_file_clean', 1 );
    update_post_meta( $att, '_ms_sp_composite', 1 );
    $old = (int) get_post_meta( $post_id, '_ms_print_file', true );
    set_post_thumbnail( $post_id, $att );
    update_post_meta( $post_id, '_ms_print_file', $att );
    if ( $upright_file ) {
        $up_att = monospace_sp_register_upright( $upright_file, $title, $post_id, $d );
        set_post_thumbnail( $post_id, $up_att );
        update_post_meta( $post_id, '_ms_sp_upright_file', $up_att );
    }
    update_post_meta( $post_id, '_ms_sp_source', [ 'post' => (int) $sightings_post_id, 'number' => $d['number'], 'layout' => $layout, 'canvas' => $canvas, 'link' => $d['link'] ] );
    $on_sale = (int) get_post_meta( $post_id, '_print_product_id', true ) || (int) get_post_meta( $post_id, '_framed_print_product_id', true ) || (int) get_post_meta( $post_id, '_postcard_product_id', true );
    if ( $on_sale ) monospace_prints_queue_refresh( $post_id, $att );
    delete_transient( 'ms_sp_feed_v2' );
    return [ 'old' => $old, 'new' => (int) $att, 'refreshed' => (bool) $on_sale ];
}

/** What each canvas sells: print type => [ template variant key => price ] (the art store's own default prices) */
function monospace_sp_request( $canvas ) {
    $t = monospace_print_templates();
    if ( $canvas === '4x5' ) {
        $out = [ 'unframed' => [ 'variants' => [] ], 'framed' => [ 'variants' => [] ] ];
        foreach ( [ '8x10', '11x14' ] as $key ) $out['unframed']['variants'][ $key ] = $t['unframed']['variants'][ $key ]['price'];
        foreach ( $t['framed']['variants'] as $key => $v ) $out['framed']['variants'][ $key ] = $v['price'];
        return $out;
    }
    if ( $canvas === '2x3' ) { // the 4x6 postcard: the one size, no frame (print type 'postcard' is not in the shop's list, hence the "all" helper)
        $postcard = monospace_print_all_templates()['postcard'];
        return [ 'postcard' => [ 'variants' => [ '4x6' => $postcard['variants']['4x6']['price'] ] ] ];
    }
    throw new Exception( "Canvas $canvas is not set up yet." );
}

/** File name for a postcard's (turned) print file */
function monospace_sp_card_file_name( $dir, $number, $label ) {
    return $dir . '/' . wp_unique_filename( $dir, sprintf( 'sightings-no-%d-%s-postcard-print.jpg', $number, str_replace( ' ', '-', $label ) ) );
}

/** Register a postcard's upright picture (what buyers see; never sent to Printful) as an attachment of the stand-in. Returns its ID. */
function monospace_sp_register_upright( $file, $title, $post_id, $d ) {
    $att = wp_insert_attachment( [ 'post_mime_type' => 'image/jpeg', 'post_title' => "$title - postcard, upright", 'post_status' => 'inherit', 'post_parent' => $post_id ],
        $file, $post_id, true );
    if ( is_wp_error( $att ) ) throw new Exception( $att->get_error_message() );
    wp_update_attachment_metadata( $att, wp_generate_attachment_metadata( $att, $file ) );
    update_post_meta( $att, '_wp_attachment_image_alt', "Sightings No. {$d['number']}: " . $d['text'] );
    update_post_meta( $att, '_ms_print_file_clean', 1 );
    update_post_meta( $att, '_ms_sp_composite', 1 );
    return (int) $att;
}

/** The postcard's mockup and preview show the upright picture, not the sideways file Printful prints */
add_filter( 'monospace_print_mockup_image', function ( $image, $post_id, $type ) {
    $up = $type === 'postcard' ? (int) get_post_meta( $post_id, '_ms_sp_upright_file', true ) : 0;
    return $up && wp_attachment_is_image( $up ) ? $up : $image;
}, 10, 3 );

/** Make the stand-in and queue its prints (the background job in monospace-prints). Returns the stand-in post ID. */
function monospace_sp_queue( $sightings_post_id, $layout, $canvas = '4x5' ) {
    $post_id = monospace_sp_create_standin( $sightings_post_id, $layout, $canvas );
    monospace_prints_queue( $post_id, monospace_sp_request( $canvas ), (int) get_post_meta( $post_id, '_ms_print_file', true ) );
    return $post_id;
}

<?php
/**
 * The AJAX calls behind the buttons. None of them writes anything: they send an answer back to the screen, and the owner
 * edits it there before the artwork is saved. Nonce 'ms_ai', capability edit_products.
 *
 * @package monospace-ai-alt-text
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The screen's facts about the artwork, cleaned: title, medium, format, category names, tags it already has */
function ms_ai_facts() {
    $in    = wp_unslash( $_POST );
    $names = function ( $key ) use ( $in ) { return array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $in[ $key ] ?? [] ) ), 'strlen' ) ); };
    $f = [
        'image_id'   => absint( $in['image_id'] ?? 0 ),
        'title'      => sanitize_text_field( $in['title'] ?? '' ),
        'medium'     => sanitize_text_field( $in['medium'] ?? '' ),
        'format'     => sanitize_text_field( $in['format'] ?? '' ),
        'categories' => $names( 'categories' ),
        'tags'       => $names( 'tags' ),
    ];
    // From an artwork's edit screen (Quick Artwork Editor) the facts come from the saved post and its linked product
    $post_id = absint( $in['post_id'] ?? 0 );
    if ( $post_id && current_user_can( 'edit_post', $post_id ) ) {
        $post = get_post( $post_id );
        if ( $post ) {
            if ( $f['title'] === '' ) $f['title'] = $post->post_title;
            $f['categories'] = array_values( array_unique( array_merge( $f['categories'], wp_list_pluck( get_the_category( $post_id ), 'name' ) ) ) );
            $f['tags']       = array_values( array_unique( array_merge( $f['tags'], wp_list_pluck( wp_get_post_tags( $post_id ), 'name' ) ) ) );
            $product = function_exists( 'wc_get_product' ) ? wc_get_product( (int) get_post_meta( $post_id, '_qp_product_id', true ) ) : null;
            if ( $product ) {
                if ( $f['medium'] === '' ) $f['medium'] = (string) $product->get_attribute( 'pa_medium' );
                if ( $f['format'] === '' ) $f['format'] = (string) $product->get_attribute( 'pa_format' );
            }
        }
    }
    return $f;
}

function ms_ai_ajax_guard() {
    check_ajax_referer( 'ms_ai' );
    if ( ! current_user_can( 'edit_products' ) ) wp_send_json_error( 'Not allowed.' );
}

add_action( 'wp_ajax_ms_ai_alt', function () {
    ms_ai_ajax_guard();
    $f   = ms_ai_facts();
    $img = ms_ai_image_block( $f['image_id'] );
    if ( is_wp_error( $img ) ) wp_send_json_error( $img->get_error_message() );

    $opener = ms_ai_opener( $f['medium'], $f['categories'] );
    $lines  = [];
    if ( $f['title'] !== '' )  $lines[] = 'Title: ' . $f['title'];
    if ( $f['medium'] !== '' ) $lines[] = 'Medium: ' . $f['medium'];
    if ( $f['format'] !== '' ) $lines[] = 'Format: ' . $f['format'];
    if ( $f['categories'] )    $lines[] = 'Categories: ' . implode( ', ', $f['categories'] );
    $lines[] = $opener !== '' ? 'Opening phrase: ' . $opener : 'No opening phrase is known: start with "Painting of" or "Drawing of" as the picture shows.';
    $lines[] = 'Write the alt text for this picture.';

    $text = ms_ai_request( ms_ai_alt_system(), [ $img, [ 'type' => 'text', 'text' => implode( "\n", $lines ) ] ], 400 );
    if ( is_wp_error( $text ) ) wp_send_json_error( $text->get_error_message() );
    $data = ms_ai_json( $text );
    $alt  = is_array( $data ) ? trim( (string) ( $data['alt'] ?? '' ) ) : '';
    if ( $alt === '' ) wp_send_json_error( 'Claude answered, but not in a form I could use. Try again.' );
    wp_send_json_success( [ 'alt' => $alt, 'uncertain' => ! empty( $data['uncertain'] ), 'opener' => $opener ] );
} );

add_action( 'wp_ajax_ms_ai_tags', function () {
    ms_ai_ajax_guard();
    $f   = ms_ai_facts();
    $img = ms_ai_image_block( $f['image_id'] );
    if ( is_wp_error( $img ) ) wp_send_json_error( $img->get_error_message() );

    // The site's own tag list, most used first
    $vocab = get_terms( [ 'taxonomy' => 'post_tag', 'orderby' => 'count', 'order' => 'DESC', 'number' => 0, 'hide_empty' => true, 'fields' => 'names' ] );
    $vocab = array_values( array_map( function ( $n ) { return html_entity_decode( $n, ENT_QUOTES, 'UTF-8' ); }, is_array( $vocab ) ? $vocab : [] ) );
    $known = []; foreach ( $vocab as $v ) $known[ strtolower( $v ) ] = $v;

    $lines = [];
    if ( $f['title'] !== '' )  $lines[] = 'Title: ' . $f['title'];
    if ( $f['medium'] !== '' ) $lines[] = 'Medium: ' . $f['medium'];
    if ( $f['categories'] )    $lines[] = 'Categories (do not repeat): ' . implode( ', ', $f['categories'] );
    if ( $f['tags'] )          $lines[] = 'Tags it already has (do not repeat): ' . implode( ', ', $f['tags'] );
    $lines[] = 'The site\'s tag list: ' . implode( ', ', $vocab );
    $lines[] = 'Suggest tags for this picture.';

    $text = ms_ai_request( ms_ai_tags_system(), [ $img, [ 'type' => 'text', 'text' => implode( "\n", $lines ) ] ], 300 );
    if ( is_wp_error( $text ) ) wp_send_json_error( $text->get_error_message() );
    $data = ms_ai_json( $text );
    if ( ! is_array( $data ) ) wp_send_json_error( 'Claude answered, but not in a form I could use. Try again.' );

    $skip = array_map( 'strtolower', array_merge( $f['tags'], $f['categories'] ) );
    $tags = $new = [];
    foreach ( (array) ( $data['tags'] ?? [] ) as $t ) {
        $t = trim( sanitize_text_field( (string) $t ) );
        if ( $t === '' || in_array( strtolower( $t ), $skip, true ) ) continue;
        if ( isset( $known[ strtolower( $t ) ] ) ) $tags[ strtolower( $t ) ] = $known[ strtolower( $t ) ];
        else $new[ strtolower( $t ) ] = $t;                      // not in the list: treated as new, whatever Claude called it
    }
    foreach ( (array) ( $data['new'] ?? [] ) as $t ) {
        $t = trim( sanitize_text_field( (string) $t ) );
        if ( $t === '' || in_array( strtolower( $t ), $skip, true ) || isset( $tags[ strtolower( $t ) ] ) ) continue;
        if ( isset( $known[ strtolower( $t ) ] ) ) $tags[ strtolower( $t ) ] = $known[ strtolower( $t ) ];
        else $new[ strtolower( $t ) ] = $t;
    }
    $tags = array_slice( array_values( $tags ), 0, 8 );
    $new  = array_slice( array_values( $new ), 0, 2 );
    // The ids of the suggested tags that exist, for the block editor (an artwork's edit screen sets them on the post)
    $ids = [];
    foreach ( $tags as $t ) { $term = get_term_by( 'name', $t, 'post_tag' ); if ( $term ) $ids[] = (int) $term->term_id; }
    wp_send_json_success( [ 'tags' => $tags, 'new' => $new, 'ids' => $ids ] );
} );

add_action( 'wp_ajax_ms_ai_test', function () {
    ms_ai_ajax_guard();
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Not allowed.' );
    $text = ms_ai_request( 'Answer with JSON only: {"ok": true}', [ [ 'type' => 'text', 'text' => 'ping' ] ], 20 );
    if ( is_wp_error( $text ) ) wp_send_json_error( $text->get_error_message() );
    wp_send_json_success( 'Connected - model ' . ms_ai_settings()['model'] . '.' );
} );

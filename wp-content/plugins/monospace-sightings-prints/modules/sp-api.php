<?php
/**
 * Sightings prints: the small API the Sightings controls page (sightings.monospace.com/controls/) uses to see which drawings have
 * prints and to make prints for a drawing. Guarded by a shared secret (option monospace_sp_api_key, also kept in the Sightings
 * controls-data folder, never in git): header X-SP-Key. Wrong keys are counted and locked out for an hour after ten.
 *   GET  /wp-json/monospace/v1/sightings-admin/status
 *   POST /wp-json/monospace/v1/sightings-admin/make   { post: <sightings post id>, layouts: [dark|overlay|light], canvas: 4x5 (prints, default) | 2x3 (4x6 postcard) }
 *   POST /wp-json/monospace/v1/sightings-admin/rollout { canvas: 2x3 }   start the paced background job for every drawing (sp-rollout.php)
 * Making prints is still opt-in: nothing runs unless the owner presses the button on the controls page.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_sp_api_authorized( WP_REST_Request $request ) {
    $key = (string) get_option( 'monospace_sp_api_key', '' );
    if ( $key === '' ) return new WP_Error( 'sp_no_key', 'Not set up.', [ 'status' => 403 ] );
    $bad = (int) get_transient( 'ms_sp_api_bad' );
    if ( $bad >= 10 ) return new WP_Error( 'sp_locked', 'Too many wrong keys. Try again in an hour.', [ 'status' => 429 ] );
    if ( ! hash_equals( $key, (string) $request->get_header( 'x-sp-key' ) ) ) {
        set_transient( 'ms_sp_api_bad', $bad + 1, HOUR_IN_SECONDS );
        sleep( 1 );
        return new WP_Error( 'sp_forbidden', 'Not allowed.', [ 'status' => 403 ] );
    }
    return true;
}

/** One drawing x layout: [ state: none | making | error | on_sale, products, message ] */
function monospace_sp_layout_state( $sightings_post_id, $layout, $canvas = '4x5' ) {
    $post = monospace_sp_standin_for( $sightings_post_id, $layout, $canvas );
    if ( ! $post ) return [ 'state' => 'none' ];
    $job = monospace_prints_get_job( $post );
    if ( $job ) return $job['error'] !== '' ? [ 'state' => 'error', 'message' => (string) $job['error'] ] : [ 'state' => 'making' ];
    $products = $canvas === '2x3' ? array_filter( [ (int) get_post_meta( $post, '_postcard_product_id', true ) ] )
        : array_filter( [ (int) get_post_meta( $post, '_print_product_id', true ), (int) get_post_meta( $post, '_framed_print_product_id', true ) ] );
    $file = (int) get_post_meta( $post, '_ms_sp_upright_file', true ) ?: (int) get_post_meta( $post, '_ms_print_file', true );
    return $products ? [ 'state' => 'on_sale', 'products' => array_values( $products ), 'preview' => (string) wp_get_attachment_image_url( $file, 'large' ) ] : [ 'state' => 'none' ];
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'monospace/v1', '/sightings-admin/status', [
        'methods' => 'GET', 'permission_callback' => 'monospace_sp_api_authorized',
        'callback' => function () {
            $out = [];
            foreach ( monospace_sp_drawings() as $id => $d ) {
                $row = [ 'id' => $id, 'number' => $d['number'], 'text' => $d['text'], 'layouts' => [] ];
                foreach ( array_keys( monospace_sp_layouts() ) as $layout ) {
                    $row['layouts'][ $layout ] = monospace_sp_layout_state( $id, $layout );
                    $row['postcards'][ $layout ] = monospace_sp_layout_state( $id, $layout, '2x3' );
                }
                $out[] = $row;
            }
            $ro = function_exists( 'monospace_sp_rollout_status' ) ? monospace_sp_rollout_status() : null;
            return rest_ensure_response( [ 'drawings' => $out, 'rollout_running' => $ro && empty( $ro['done'] ) && ( $ro['canvas'] ?? '' ) === '2x3', 'admin' => admin_url( 'edit.php?category_name=sightings-prints&post_status=private' ) ] );
        },
    ] );

    register_rest_route( 'monospace/v1', '/sightings-admin/make', [
        'methods' => 'POST', 'permission_callback' => 'monospace_sp_api_authorized',
        'callback' => function ( WP_REST_Request $request ) {
            $id = (int) $request->get_param( 'post' );
            $canvas = $request->get_param( 'canvas' ) === '2x3' ? '2x3' : '4x5';
            $layouts = array_values( array_intersect( (array) $request->get_param( 'layouts' ), array_keys( monospace_sp_layouts() ) ) );
            if ( ! isset( monospace_sp_drawings()[ $id ] ) || ! $layouts ) return new WP_Error( 'sp_bad', 'Choose a drawing and at least one layout.', [ 'status' => 400 ] );
            $made = (int) get_transient( 'ms_sp_api_made' );
            if ( $made + count( $layouts ) > 12 ) return new WP_Error( 'sp_rate', 'That is a lot at once. Wait ten minutes.', [ 'status' => 429 ] );
            set_transient( 'ms_sp_api_made', $made + count( $layouts ), 10 * MINUTE_IN_SECONDS );
            $result = [];
            foreach ( $layouts as $layout ) {
                if ( monospace_sp_layout_state( $id, $layout, $canvas )['state'] !== 'none' ) { $result[ $layout ] = 'already there'; continue; }
                try { monospace_sp_queue( $id, $layout, $canvas ); $result[ $layout ] = 'started'; }
                catch ( Throwable $e ) { $result[ $layout ] = 'error: ' . $e->getMessage(); }
            }
            delete_transient( 'ms_sp_feed' ); delete_transient( 'ms_sp_feed_v2' );
            return rest_ensure_response( [ 'result' => $result ] );
        },
    ] );

    register_rest_route( 'monospace/v1', '/sightings-admin/rollout', [
        'methods' => 'POST', 'permission_callback' => 'monospace_sp_api_authorized',
        'callback' => function ( WP_REST_Request $request ) {
            if ( $request->get_param( 'canvas' ) !== '2x3' ) return new WP_Error( 'sp_bad', 'Only the postcard canvas can be rolled out here.', [ 'status' => 400 ] );
            try { monospace_sp_rollout_start( '2x3' ); } catch ( Throwable $e ) { return new WP_Error( 'sp_fail', $e->getMessage(), [ 'status' => 500 ] ); }
            return rest_ensure_response( [ 'started' => true ] );
        },
    ] );
} );

<?php
/**
 * Print jobs - creating an artwork's prints runs in the background, so several paintings can be
 * queued in a row without waiting (owner's wish, 2026-10-15).
 *
 * monospace_prints_queue() creates the WooCommerce products right away (create-prints.php) and
 * schedules the job; Action Scheduler (WooCommerce's queue, group "monospace-prints") then runs
 * monospace_prints_tick() for that post until it's done, each run doing what's possible and
 * rescheduling itself:
 *   1. waiting - checks every minute until Printful lists each product, then links its variations
 *                (gives up after 45 minutes with an error: "Import not synced products" off?)
 *   2. linking - links the prints to the artwork at once: on sale now, with the painting as image
 *   3. mockups - drawn ahead on the site (print-mockups.php - not media-library images; they'd
 *                also be drawn on first view) and set as the variations' image in Meta's catalog
 *   4. done    - job removed, summary kept in post meta _ms_print_status
 * Job state: post meta _ms_print_job. Errors stop the job (Retry / Cancel in the UI).
 *
 * Save safeguard: a post editor opened before the job linked the prints still has empty Quick
 * Product print-ID fields, and saving would unlink them (Quick Product re-saves those fields). The
 * prints box posts the page's load time (ms_prints_loaded_at); a link made after that is put back.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_PRINTS_HOOK  = 'monospace_prints_tick';
const MONOSPACE_PRINTS_GROUP = 'monospace-prints';

/** An artwork's job, with defaults for fields older jobs don't have (or null) */
function monospace_prints_get_job( $post_id ) {
    $job = get_post_meta( $post_id, '_ms_print_job', true );
    return is_array( $job ) ? $job + [ 'waited' => 0, 'attached' => false, 'error' => '', 'log' => [], 'products' => [] ] : null;
}

function monospace_prints_schedule( $post_id, $delay = 5 ) {
    if ( ! function_exists( 'as_schedule_single_action' ) ) return;
    as_unschedule_all_actions( MONOSPACE_PRINTS_HOOK, [ 'post_id' => (int) $post_id ], MONOSPACE_PRINTS_GROUP );
    as_schedule_single_action( time() + max( 1, (int) $delay ), MONOSPACE_PRINTS_HOOK, [ 'post_id' => (int) $post_id ], MONOSPACE_PRINTS_GROUP );
}

function monospace_prints_log( &$job, $message ) {
    $job['log'][] = [ time(), $message ];
    $job['log'] = array_slice( $job['log'], -30 );
}

/**
 * Queue an artwork's prints. $request: [ 'unframed' => [ 'variants' => [ key => price ] ]
 * or [ 'existing' => product ID ], 'framed' => …, 'min_dpi' => skip sizes below this ].
 * Creates the products now.
 */
function monospace_prints_queue( $post_id, $request, $image_id = 0 ) {
    if ( ! monospace_printful_ready() ) throw new Exception( 'The Printful token is missing (option monospace_printful_token).' );
    if ( is_array( get_post_meta( $post_id, '_ms_print_job', true ) ) ) throw new Exception( 'A print job for this artwork is already running.' );
    if ( ! ( $image_id ?: monospace_prints_file_for( $post_id ) ) ) throw new Exception( 'No image to print (set a featured image).' );
    $image_id = monospace_prints_usable_file( $post_id, $image_id ); // never a watermarked file (create-prints.php)
    if ( ! $image_id || ! wp_attachment_is_image( $image_id ) ) throw new Exception( 'No image to print (set a featured image).' );

    $templates = monospace_print_templates();
    $job = [ 'image_id' => $image_id, 'orientation' => monospace_prints_orientation( monospace_print_image_size( $image_id ) ),
             'products' => [], 'started' => time(), 'waited' => 0, 'attached' => false, 'error' => '', 'log' => [] ];

    foreach ( $templates as $type => $tpl ) {
        $want = $request[ $type ] ?? null;
        if ( ! $want || get_post_meta( $post_id, $tpl['meta_key'], true ) ) continue;

        if ( ! empty( $want['existing'] ) ) {
            $pid = absint( $want['existing'] );
            $p = wc_get_product( $pid );
            if ( ! $p || (int) $p->get_meta( '_related_post_id' ) !== (int) $post_id || monospace_print_type_of( $p ) !== $type ) throw new Exception( "Product $pid isn't this artwork's " . strtolower( $tpl['label'] ) . '.' );
            $job['products'][ $type ] = [ 'id' => $pid, 'linked' => false ];
            monospace_prints_log( $job, "{$tpl['label']}: using the existing #$pid." );
            continue;
        }
        $prices = [];
        $min_dpi = (int) ( $request['min_dpi'] ?? 0 ); // the Prints page skips sizes this image is too small for
        $px = monospace_print_image_size( $image_id );
        foreach ( (array) ( $want['variants'] ?? [] ) as $key => $price ) {
            if ( ! isset( $tpl['variants'][ $key ] ) || (float) $price <= 0 ) continue;
            if ( $min_dpi && monospace_print_dpi( $px, $tpl['variants'][ $key ]['inches'] ) < $min_dpi ) continue;
            $prices[ $key ] = round( (float) $price, 2 );
        }
        if ( ! $prices ) continue;
        $pid = monospace_prints_create_product( $post_id, $type, $prices, $image_id );
        $job['products'][ $type ] = [ 'id' => $pid, 'linked' => false ];
        monospace_prints_log( $job, "{$tpl['label']}: created #$pid (" . count( $prices ) . ' variants), Printful notified.' );
    }
    if ( ! $job['products'] ) throw new Exception( 'Nothing to do - choose at least one size or frame.' );

    update_post_meta( $post_id, '_ms_print_job', $job );
    delete_post_meta( $post_id, '_ms_print_status' );
    monospace_prints_schedule( $post_id, 20 );
    return $job;
}

/**
 * A new print file for an artwork's existing prints ($file_id: put on every Printful variant,
 * remembered as the artwork's print file; the mockups follow it).
 */
function monospace_prints_queue_refresh( $post_id, $file_id ) {
    if ( ! monospace_printful_ready() ) throw new Exception( 'The Printful token is missing.' );
    if ( monospace_prints_get_job( $post_id ) ) throw new Exception( 'A print job for this artwork is already running.' );
    if ( ! $file_id || ! wp_attachment_is_image( $file_id ) ) throw new Exception( 'That file is not an image.' );

    $image_id = $file_id = monospace_prints_usable_file( $post_id, $file_id ); // a clean copy if it's watermarked
    $job = [ 'image_id' => $image_id, 'orientation' => monospace_prints_orientation( monospace_print_image_size( $image_id ) ),
             'products' => [], 'started' => time(), 'waited' => 0, 'attached' => true, 'error' => '', 'log' => [] ];
    foreach ( monospace_print_templates() as $type => $tpl ) {
        $pid = (int) get_post_meta( $post_id, $tpl['meta_key'], true );
        if ( ! $pid || ! wc_get_product( $pid ) ) continue;
        $job['products'][ $type ] = [ 'id' => $pid, 'linked' => false, 'force' => true ];
    }
    if ( ! $job['products'] ) throw new Exception( 'This artwork has no prints yet.' );

    update_post_meta( $post_id, '_ms_print_file', $file_id );
    monospace_prints_log( $job, 'New print file: ' . basename( wp_get_original_image_path( $file_id ) ?: '' ) . '.' );
    update_post_meta( $post_id, '_ms_print_job', $job );
    delete_post_meta( $post_id, '_ms_print_status' );
    monospace_prints_schedule( $post_id, 5 );
    return $job;
}

/**
 * New sizes / frames on an existing print ($new_vids, just created): the job waits until Printful
 * has picked them up, links them, then draws their mockups. Joins nothing - a running job
 * for the artwork makes this fail (the caller reports it).
 */
function monospace_prints_queue_variants( $post_id, $type, $pid, $new_vids ) {
    if ( monospace_prints_get_job( $post_id ) ) throw new Exception( 'A print job for this artwork is already running.' );
    $image_id = monospace_prints_usable_file( $post_id );
    $job = [ 'image_id' => $image_id, 'orientation' => monospace_prints_orientation( monospace_print_image_size( $image_id ) ),
             'products' => [ $type => [ 'id' => $pid, 'linked' => false, 'force' => false ] ],
             'started' => time(), 'waited' => 0, 'attached' => true, 'error' => '', 'log' => [] ];
    monospace_prints_log( $job, monospace_print_templates()[ $type ]['label'] . ': ' . count( $new_vids ) . ' new variant(s) added, Printful notified.' );
    update_post_meta( $post_id, '_ms_print_job', $job );
    delete_post_meta( $post_id, '_ms_print_status' );
    monospace_prints_schedule( $post_id, 30 );
    return $job;
}

/** One background run for an artwork's job (Action Scheduler) */
add_action( MONOSPACE_PRINTS_HOOK, 'monospace_prints_tick' );
function monospace_prints_tick( $post_id ) {
    $job = monospace_prints_get_job( $post_id );
    if ( ! $job || $job['error'] !== '' ) return;
    $templates = monospace_print_templates();
    $save = function () use ( $post_id, &$job ) { update_post_meta( $post_id, '_ms_print_job', $job ); };

    try {
        if ( ! monospace_printful_ready() ) throw new Exception( 'The Printful token is missing.' );

        // 1. Wait until Printful lists the products, link their variations
        $waiting = false;
        foreach ( $job['products'] as $type => &$p ) {
            if ( $p['linked'] ) continue;
            $result = monospace_prints_sync_product( $type, $p['id'], $job['image_id'], ! empty( $p['force'] ) );
            if ( $result === 'waiting' ) { $waiting = true; continue; }
            if ( is_array( $result ) ) { // rate limit - not counted toward the 45-minute wait
                unset( $p );
                $save();
                monospace_prints_schedule( $post_id, $result['wait'] );
                return;
            }
            if ( $result !== 'linked' ) throw new Exception( $result );
            monospace_prints_log( $job, "{$templates[$type]['label']}: " . ( ! empty( $p['force'] ) ? 'new print file on all variants in Printful.' : 'all variants linked in Printful.' ) );
            $p['linked'] = true;
            $p['force'] = false;
        }
        unset( $p );
        if ( $waiting ) {
            $job['waited'] += 60;
            if ( $job['waited'] > 45 * 60 ) {
                throw new Exception( 'Printful still hasn’t listed the product after 45 minutes. Check that “Import not synced products from WooCommerce” is on in Printful (My products), then Retry.' );
            }
            $save();
            monospace_prints_schedule( $post_id, 60 );
            return;
        }

        // 2. Link to the artwork - on sale from now on
        if ( empty( $job['attached'] ) ) {
            foreach ( $job['products'] as $type => $p ) monospace_prints_link( $post_id, $type, $p['id'] );
            $job['attached'] = true;
            monospace_prints_log( $job, 'Linked to the artwork - the prints are on sale.' );
            if ( function_exists( 'sg_cachepress_purge_everything' ) ) sg_cachepress_purge_everything();
        }

        // 3. Mockups: drawn ahead of the first visitor (they're also drawn on demand), Meta's catalog gets them
        if ( empty( $job['mockups_done'] ) ) {
            $made = monospace_print_mockups_warm( $post_id );
            $meta = monospace_print_mockups_to_meta( $post_id );
            $job['mockups_done'] = true;
            monospace_prints_log( $job, "Mockups: $made drawn" . ( $meta ? ", $meta sent to the Facebook / Instagram catalog" : '' ) . '.' );
            $save();
            monospace_prints_schedule( $post_id, 2 );
            return;
        }

        // 4. Done
        monospace_prints_log( $job, 'Done.' );
        update_post_meta( $post_id, '_ms_print_status', [ 'done' => time(), 'products' => wp_list_pluck( $job['products'], 'id' ), 'log' => $job['log'] ] );
        delete_post_meta( $post_id, '_ms_print_job' );
    } catch ( Exception $e ) {
        $job['error'] = $e->getMessage();
        monospace_prints_log( $job, 'Stopped: ' . $e->getMessage() );
        $save();
    }
}

/**
 * Where an artwork's prints stand: [ 'state' => none | linked | waiting | linking | mockups | error | done,
 * 'text' => one line, 'log' => [ [ time, message ], … ], 'ids' => the linked print products ]
 */
function monospace_prints_status( $post_id ) {
    $status = monospace_prints_status_core( $post_id );
    $status['ids'] = [ 'unframed' => (int) get_post_meta( $post_id, '_print_product_id', true ), 'framed' => (int) get_post_meta( $post_id, '_framed_print_product_id', true ) ];
    return $status;
}

function monospace_prints_status_core( $post_id ) {
    $job = monospace_prints_get_job( $post_id );
    if ( $job ) {
        $log = $job['log'];
        if ( $job['error'] !== '' ) return [ 'state' => 'error', 'text' => $job['error'], 'log' => $log ];
        foreach ( $job['products'] as $p ) {
            if ( ! empty( $p['force'] ) ) return [ 'state' => 'waiting', 'text' => 'Putting the new print file on the Printful variants…', 'log' => $log ];
            if ( ! $p['linked'] ) return [ 'state' => 'waiting', 'text' => 'Waiting for Printful to pick up the product (' . round( $job['waited'] / 60 ) . ' min)…', 'log' => $log ];
        }
        if ( empty( $job['attached'] ) ) return [ 'state' => 'linking', 'text' => 'Linked in Printful - linking to the artwork…', 'log' => $log ];
        return [ 'state' => 'mockups', 'text' => 'On sale. Drawing the mockups…', 'log' => $log ];
    }
    $status = get_post_meta( $post_id, '_ms_print_status', true );
    if ( is_array( $status ) ) return [ 'state' => 'done', 'text' => 'Done ' . human_time_diff( $status['done'] ) . ' ago.', 'log' => $status['log'] ?? [] ];
    if ( get_post_meta( $post_id, '_print_product_id', true ) || get_post_meta( $post_id, '_framed_print_product_id', true ) ) return [ 'state' => 'linked', 'text' => 'Prints linked.', 'log' => [] ];
    return [ 'state' => 'none', 'text' => '', 'log' => [] ];
}

/* ============================================================
 * AJAX - op = queue | refresh | preview_file | status | retry | cancel | save_prices (prints box and Prints pages)
 * ============================================================ */

add_action( 'wp_ajax_monospace_prints', function () {
    check_ajax_referer( 'monospace_prints' );
    $post_id = absint( $_POST['post_id'] ?? 0 );
    $op = sanitize_key( $_POST['op'] ?? '' );
    if ( ! current_user_can( 'edit_products' ) ) wp_send_json_error( 'Not allowed' );

    try {
        if ( $op === 'status' ) {
            $out = [];
            foreach ( array_map( 'absint', (array) ( $_POST['post_ids'] ?? [ $post_id ] ) ) as $id ) {
                if ( $id && current_user_can( 'edit_post', $id ) ) $out[ $id ] = monospace_prints_status( $id );
            }
            wp_send_json_success( $out );
        }
        if ( $op === 'save_prices' ) { // New prints page: default prices for new prints; existing ones keep theirs
            $templates = monospace_print_templates();
            $saved = [];
            foreach ( (array) json_decode( wp_unslash( $_POST['prices'] ?? '' ), true ) as $type => $prices ) {
                foreach ( (array) $prices as $key => $price ) {
                    if ( ! isset( $templates[ $type ]['variants'][ $key ] ) || (float) $price <= 0 ) continue;
                    $saved[ $type ][ $key ] = round( (float) $price, 2 );
                }
            }
            if ( ! $saved ) throw new Exception( 'No prices to save.' );
            update_option( 'monospace_print_default_prices', $saved, false );
            wp_send_json_success( 'Saved - new prints start at these prices. Prints that already exist keep theirs (All prints > Change prices).' );
        }
        if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) throw new Exception( 'Not allowed' );

        switch ( $op ) {
            case 'queue':
                $request = json_decode( wp_unslash( $_POST['request'] ?? '' ), true );
                monospace_prints_queue( $post_id, (array) $request, absint( $_POST['image_id'] ?? 0 ) );
                wp_send_json_success( monospace_prints_status( $post_id ) );
            case 'refresh':
                monospace_prints_queue_refresh( $post_id, absint( $_POST['file'] ?? 0 ) );
                wp_send_json_success( monospace_prints_status( $post_id ) );

            case 'preview_file':
                // Quality of each existing print size / frame with this image
                $file = absint( $_POST['file'] ?? 0 );
                if ( ! wp_attachment_is_image( $file ) ) throw new Exception( 'That file is not an image.' );
                $px = monospace_print_image_size( $file );
                $rows = [];
                foreach ( monospace_print_templates() as $type => $tpl ) {
                    $p = wc_get_product( (int) get_post_meta( $post_id, $tpl['meta_key'], true ) );
                    if ( ! $p ) continue;
                    foreach ( $p->get_children() as $vid ) {
                        $v = monospace_print_variant_for( $type, wc_get_product( $vid ) );
                        if ( ! $v || isset( $rows[ $v['size'] ] ) ) continue;
                        $dpi = monospace_print_dpi( $px, $v['inches'] );
                        $rows[ $v['size'] ] = [ 'size' => $v['size'], 'dpi' => $dpi, 'rating' => monospace_print_dpi_rating( $dpi )[0], 'trim' => round( monospace_print_crop( $px, $v['inches'] ) * 100 ) ];
                    }
                }
                wp_send_json_success( [ 'px' => $px, 'name' => basename( wp_get_original_image_path( $file ) ?: '' ), 'rows' => array_values( $rows ) ] );

            case 'retry':
                $job = monospace_prints_get_job( $post_id );
                if ( ! $job ) throw new Exception( 'No print job for this artwork.' );
                $job['error'] = ''; $job['waited'] = 0;
                monospace_prints_log( $job, 'Retrying.' );
                update_post_meta( $post_id, '_ms_print_job', $job );
                monospace_prints_schedule( $post_id, 2 );
                wp_send_json_success( monospace_prints_status( $post_id ) );
            case 'cancel':
                if ( function_exists( 'as_unschedule_all_actions' ) ) as_unschedule_all_actions( MONOSPACE_PRINTS_HOOK, [ 'post_id' => $post_id ], MONOSPACE_PRINTS_GROUP );
                delete_post_meta( $post_id, '_ms_print_job' );
                wp_send_json_success( monospace_prints_status( $post_id ) );
        }
        throw new Exception( 'Unknown operation' );
    } catch ( Exception $e ) {
        wp_send_json_error( $e->getMessage() );
    }
} );

/* ============================================================
 * SAVE SAFEGUARD
 * ============================================================ */

// After Quick Product's save (save_post_post, 10): put back links the job made after this editor opened
add_action( 'save_post_post', function ( $post_id ) {
    if ( ! isset( $_POST['ms_prints_loaded_at'] ) || wp_is_post_revision( $post_id ) ) return;
    $loaded = (int) $_POST['ms_prints_loaded_at'];
    $stamps = (array) get_post_meta( $post_id, '_ms_print_linked_at', true );
    foreach ( monospace_print_templates() as $type => $tpl ) {
        $field = 'qp' . $tpl['meta_key']; // qp_print_product_id / qp_framed_print_product_id
        $stamp = $stamps[ $type ] ?? null;
        if ( ! is_array( $stamp ) || $stamp['at'] < $loaded || ! isset( $_POST[ $field ] ) || (int) $_POST[ $field ] ) continue;
        if ( get_post_meta( $post_id, $tpl['meta_key'], true ) || ! wc_get_product( $stamp['id'] ) ) continue;
        update_post_meta( $post_id, $tpl['meta_key'], $stamp['id'] );
        if ( function_exists( '\QuickProduct\monospace_sync_print_product_link' ) ) \QuickProduct\monospace_sync_print_product_link( $post_id, $stamp['id'] );
    }
}, 20 );

/* ============================================================
 * ADMIN BAR - "Prints: 2 running" while jobs run (anywhere in wp-admin), links to the Prints page
 * ============================================================ */

add_action( 'admin_bar_menu', function ( $bar ) {
    if ( ! is_admin() || ! current_user_can( 'edit_products' ) ) return;
    $ids = get_posts( [ 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 50, 'fields' => 'ids', 'meta_key' => '_ms_print_job' ] );
    if ( ! $ids ) return;
    $errors = 0;
    foreach ( $ids as $id ) if ( monospace_prints_status( $id )['state'] === 'error' ) $errors++;
    $running = count( $ids ) - $errors;
    $label = 'Prints: ' . ( $running ? $running . ' running' : '' ) . ( $running && $errors ? ', ' : '' ) . ( $errors ? $errors . ' stopped' : '' );
    $bar->add_node( [
        'id' => 'ms-prints-jobs', 'href' => admin_url( 'admin.php?page=monospace-prints' ),
        'title' => '<span class="ab-icon dashicons dashicons-format-gallery" style="top:2px"></span><span class="ab-label">' . esc_html( $label ) . '</span>',
        'meta' => [ 'title' => 'Print jobs in the background - open the Prints page', 'class' => $errors ? 'ms-prints-has-errors' : '' ],
    ] );
}, 90 );

add_action( 'admin_head', function () {
    echo '<style>#wpadminbar .ms-prints-has-errors > .ab-item { background: #b32d2e !important; }</style>';
} );

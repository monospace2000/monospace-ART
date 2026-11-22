<?php
/**
 * Artworks > Remove artwork (2026-11-06, owner's wish) - take an artwork off the site completely, whatever the reason:
 * a double entry, a messed-up entry, a test. One screen finds it by SKU number, shows everything that goes with
 * it, and removes it in one go: the Art Post, the product, and whatever features have attached to it (the Prints
 * plugin's print products, Short links, ... - they answer the filter and action below, Core never calls them).
 *
 * Two modes: Trash (reversible, the default) or Delete permanently (nothing left; type the SKU number to confirm).
 * An artwork that has been ordered can never be removed - the orders still point at it. Hide it (private / draft)
 * instead. Images are never deleted: a double entry usually shares its photo with the original.
 *
 * SKU numbers are never reused: every removed SKU is kept in the log (option monospace_artwork_removals) and
 * the Quick Artwork Editor's generate_sku() counts it, even after a permanent delete.
 *
 * Extension points (features hook in; Core stays independent of them):
 *   filter monospace_artwork_removal_parts ( $parts, $post_id, $product_id )
 *       what else goes with the artwork: [ [ 'kind' => 'Print product', 'label' => 'Framed print (#123)', 'product' => 123 ] ]
 *       ('product' = a WooCommerce product ID, so the orders check covers it too; leave it out when there is none)
 *   action monospace_artwork_removing ( $post_id, $product_id, $mode )   mode: trash | delete
 *       do your part of the removal; throw an Exception to stop everything (nothing has been removed yet then)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_ARTWORK_REMOVALS_OPTION = 'monospace_artwork_removals';

add_action( 'admin_menu', function () {
    add_submenu_page( 'monospace-artworks', 'Remove artwork', 'Remove artwork', 'delete_products', 'monospace-artwork-remove', 'monospace_artwork_remove_page' );
}, 30 );

/** "Remove artwork…" next to Edit / Trash in the Posts and Products lists (only for an artwork or its product) */
add_filter( 'post_row_actions', function ( $actions, $post ) {
    if ( ! current_user_can( 'delete_products' ) ) return $actions;
    $arg = '';
    if ( $post->post_type === 'post' && get_post_meta( $post->ID, '_qp_product_id', true ) ) $arg = 'post=' . $post->ID;
    elseif ( $post->post_type === 'product' ) $arg = 'product=' . $post->ID;
    if ( $arg ) $actions['monospace_remove'] = '<a href="' . esc_url( admin_url( 'admin.php?page=monospace-artwork-remove&' . $arg ) ) . '">Remove artwork…</a>';
    return $actions;
}, 10, 2 );

/* ============================================================
 * DATA (read-only)
 * ============================================================ */

/** The log, newest first: [ [ 'sku', 'skus' => [ every SKU removed with it ], 'title', 'mode', 'date', 'user', 'reason' ] ] */
function monospace_artwork_removals() {
    $log = get_option( MONOSPACE_ARTWORK_REMOVALS_OPTION, [] );
    return is_array( $log ) ? $log : [];
}

/** The highest SKU number among removed SKUs (so generate_sku() never hands one out again) */
function monospace_artwork_removed_max_number() {
    $max = 0;
    foreach ( monospace_artwork_removals() as $entry ) {
        foreach ( (array) ( $entry['skus'] ?? [] ) as $sku ) {
            if ( preg_match( '/MNSPC-\d{4}-[A-Z]-(\d{4})$/', (string) $sku, $m ) ) $max = max( $max, (int) $m[1] );
        }
    }
    return $max;
}

/**
 * What a search box entry points at: [ 'post' => ID|0, 'product' => ID|0 ] (both 0 = nothing found).
 * Accepts a SKU number (728), a full SKU, or an explicit post=ID / product=ID.
 */
function monospace_artwork_remove_find( $input ) {
    global $wpdb;
    $found = [ 'post' => 0, 'product' => 0 ];
    $post  = (int) ( $input['post'] ?? 0 );
    $prod  = (int) ( $input['product'] ?? 0 );
    $sku   = trim( (string) ( $input['sku'] ?? '' ) );

    if ( $sku !== '' && ! $post && ! $prod ) {
        if ( ctype_digit( $sku ) ) {
            $num = (int) $sku;
            $prod = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_sku' AND p.post_type = 'product' AND pm.meta_value REGEXP %s
                 ORDER BY pm.post_id ASC LIMIT 1", '^MNSPC-[0-9]{4}-[A-Z]-0*' . $num . '$' ) );
        } else {
            $prod = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = '_sku' AND p.post_type = 'product' AND pm.meta_value = %s LIMIT 1", $sku ) );
        }
    }

    if ( $prod && get_post_type( $prod ) !== 'product' ) $prod = 0;
    if ( $post && get_post_type( $post ) !== 'post' ) $post = 0;
    if ( $prod && ! $post ) {
        $post = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_qp_product_id' AND meta_value = %d LIMIT 1", $prod ) );
    }
    if ( $post && ! $prod ) {
        $linked = (int) get_post_meta( $post, '_qp_product_id', true );
        if ( $linked && get_post_type( $linked ) === 'product' ) $prod = $linked;
    }
    return [ 'post' => $post, 'product' => $prod ];
}

/**
 * Everything that goes with the artwork: [ [ 'kind', 'label', 'url', 'product' => ID (optional) ] ].
 * The post and the product first, then whatever the features add.
 */
function monospace_artwork_remove_parts( $post_id, $product_id ) {
    $parts = [];
    if ( $post_id ) {
        $parts[] = [ 'kind' => 'Art Post', 'label' => get_the_title( $post_id ) . ' (' . get_post_status( $post_id ) . ', #' . $post_id . ')', 'url' => get_edit_post_link( $post_id, 'raw' ) ];
    }
    if ( $product_id ) {
        $parts[] = [ 'kind' => 'Product', 'label' => get_the_title( $product_id ) . ' (SKU ' . monospace_artwork_remove_sku( $product_id ) . ', ' . get_post_status( $product_id ) . ', #' . $product_id . ')', 'url' => get_edit_post_link( $product_id, 'raw' ), 'product' => $product_id ];
    }
    $parts = apply_filters( 'monospace_artwork_removal_parts', $parts, $post_id, $product_id );
    return is_array( $parts ) ? $parts : [];
}

function monospace_artwork_remove_sku( $product_id ) {
    $sku = (string) get_post_meta( $product_id, '_sku', true );
    return $sku !== '' ? $sku : '(none)';
}

/** Every SKU that goes away with the artwork - the product's and its prints' (variation SKUs share their parent's number) */
function monospace_artwork_remove_skus( $parts ) {
    $skus = [];
    foreach ( $parts as $part ) {
        if ( empty( $part['product'] ) ) continue;
        $sku = (string) get_post_meta( (int) $part['product'], '_sku', true );
        if ( $sku !== '' ) $skus[] = $sku;
    }
    return array_values( array_unique( $skus ) );
}

/** Order IDs that contain any of these products (or their variations): the reason a piece can't be removed */
function monospace_artwork_remove_orders( $parts ) {
    global $wpdb;
    $ids = [];
    foreach ( $parts as $part ) {
        if ( empty( $part['product'] ) ) continue;
        $ids[] = (int) $part['product'];
        $product = wc_get_product( (int) $part['product'] );
        if ( $product ) foreach ( $product->get_children() as $child ) $ids[] = (int) $child;
    }
    $ids = array_values( array_unique( array_filter( $ids ) ) );
    if ( ! $ids ) return [];
    $in = implode( ',', array_map( 'intval', $ids ) );
    return array_map( 'intval', $wpdb->get_col(
        "SELECT DISTINCT oi.order_id FROM {$wpdb->prefix}woocommerce_order_items oi
         JOIN {$wpdb->prefix}woocommerce_order_itemmeta im ON im.order_item_id = oi.order_item_id
         WHERE im.meta_key IN ('_product_id', '_variation_id') AND im.meta_value IN ($in)" ) );
}

/* ============================================================
 * THE REMOVAL (writes - only from the confirm button)
 * ============================================================ */

add_action( 'admin_post_monospace_artwork_remove', function () {
    if ( ! current_user_can( 'delete_products' ) ) wp_die( 'You are not allowed to do this.' );
    check_admin_referer( 'monospace_artwork_remove' );

    $post_id    = (int) ( $_POST['post'] ?? 0 );
    $product_id = (int) ( $_POST['product'] ?? 0 );
    $mode       = ( $_POST['mode'] ?? 'trash' ) === 'delete' ? 'delete' : 'trash';
    $reason     = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
    $back       = admin_url( 'admin.php?page=monospace-artwork-remove' );
    $fail       = function ( $msg ) use ( $back, $post_id, $product_id ) {
        set_transient( 'monospace_artwork_remove_notice_' . get_current_user_id(), [ 'error', $msg ], 60 );
        wp_safe_redirect( add_query_arg( array_filter( [ 'post' => $post_id, 'product' => $product_id ] ), $back ) );
        exit;
    };

    $found = monospace_artwork_remove_find( [ 'post' => $post_id, 'product' => $product_id ] );
    if ( ! $found['post'] && ! $found['product'] ) $fail( 'Nothing to remove - it may already be gone.' );
    [ $post_id, $product_id ] = [ $found['post'], $found['product'] ];

    $parts = monospace_artwork_remove_parts( $post_id, $product_id );
    $orders = monospace_artwork_remove_orders( $parts );
    if ( $orders ) $fail( 'This artwork has been ordered (order ' . implode( ', ', array_map( function ( $o ) { return '#' . $o; }, array_slice( $orders, 0, 5 ) ) ) . '), so it can\'t be removed. Hide it instead.' );

    $sku    = $product_id ? monospace_artwork_remove_sku( $product_id ) : '';
    $number = function_exists( 'monospace_sku_number' ) ? monospace_sku_number( $sku ) : '';
    if ( $mode === 'delete' ) {
        $typed = trim( (string) wp_unslash( $_POST['confirm'] ?? '' ) );
        if ( $number === '' || $typed !== (string) $number ) $fail( 'To delete permanently, type the SKU number (' . ( $number !== '' ? $number : 'this entry has none' ) . ') in the box.' );
    }

    $title = $post_id ? get_the_title( $post_id ) : get_the_title( $product_id );
    $skus  = monospace_artwork_remove_skus( $parts );

    // Features first: if one refuses (a print job is running, Printful says no), nothing has been removed yet
    try {
        do_action( 'monospace_artwork_removing', $post_id, $product_id, $mode );
    } catch ( Exception $e ) {
        $fail( 'Stopped, nothing was removed: ' . $e->getMessage() );
    }

    // The log first - the SKUs stay retired even if the rest fails halfway
    $log = monospace_artwork_removals();
    array_unshift( $log, [ 'sku' => $sku, 'skus' => $skus, 'title' => $title, 'mode' => $mode, 'date' => current_time( 'Y-m-d H:i' ), 'user' => wp_get_current_user()->user_login, 'reason' => $reason ] );
    update_option( MONOSPACE_ARTWORK_REMOVALS_OPTION, $log, false );

    if ( $product_id ) {
        $product = wc_get_product( $product_id );
        if ( $product ) $product->delete( $mode === 'delete' );
    }
    if ( $post_id ) {
        $mode === 'delete' ? wp_delete_post( $post_id, true ) : wp_trash_post( $post_id );
    }

    if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache();
    set_transient( 'monospace_artwork_remove_notice_' . get_current_user_id(), [ 'success',
        ( $mode === 'delete' ? 'Deleted permanently: ' : 'Moved to the Trash: ' ) . $title . ( $sku !== '' ? ' (SKU ' . $sku . ' is retired and won\'t be used again).' : '.' ) ], 60 );
    wp_safe_redirect( $back );
    exit;
} );

/* ============================================================
 * THE SCREEN
 * ============================================================ */

function monospace_artwork_remove_page() {
    if ( ! current_user_can( 'delete_products' ) ) return;

    $notice = get_transient( 'monospace_artwork_remove_notice_' . get_current_user_id() );
    if ( $notice ) delete_transient( 'monospace_artwork_remove_notice_' . get_current_user_id() );

    $input = [
        'sku'     => isset( $_GET['sku'] ) ? sanitize_text_field( wp_unslash( $_GET['sku'] ) ) : '',
        'post'    => isset( $_GET['post'] ) ? (int) $_GET['post'] : 0,
        'product' => isset( $_GET['product'] ) ? (int) $_GET['product'] : 0,
    ];
    $searched = $input['sku'] !== '' || $input['post'] || $input['product'];
    $found    = $searched ? monospace_artwork_remove_find( $input ) : [ 'post' => 0, 'product' => 0 ];
    $has      = $found['post'] || $found['product'];
    $parts    = $has ? monospace_artwork_remove_parts( $found['post'], $found['product'] ) : [];
    $orders   = $has ? monospace_artwork_remove_orders( $parts ) : [];
    $sku      = $found['product'] ? monospace_artwork_remove_sku( $found['product'] ) : '';
    $number   = ( $sku !== '' && function_exists( 'monospace_sku_number' ) ) ? monospace_sku_number( $sku ) : '';
    $title    = $found['post'] ? get_the_title( $found['post'] ) : ( $found['product'] ? get_the_title( $found['product'] ) : '' );
    $thumb    = $found['post'] ? get_the_post_thumbnail( $found['post'], [ 96, 96 ] ) : '';
    ?>
    <div class="wrap">
        <h1>Remove artwork</h1>
        <p class="description" style="max-width:760px">For double entries, messed-up entries and tests. It takes the artwork off the site completely: the Art Post, the product and everything attached to it. An artwork that has been ordered can't be removed - hide it instead. Images stay in the Media Library.</p>

        <?php if ( $notice ) : ?>
            <div class="notice notice-<?php echo $notice[0] === 'error' ? 'error' : 'success'; ?>"><p><?php echo esc_html( $notice[1] ); ?></p></div>
        <?php endif; ?>

        <form method="get" style="margin:16px 0">
            <input type="hidden" name="page" value="monospace-artwork-remove">
            <label for="ms-rm-sku"><strong>SKU number</strong></label>
            <input type="text" id="ms-rm-sku" name="sku" value="<?php echo esc_attr( $input['sku'] ); ?>" class="regular-text" style="max-width:180px;font-family:monospace" placeholder="728" autofocus>
            <button class="button button-primary">Find</button>
        </form>

        <?php if ( $searched && ! $has ) : ?>
            <div class="notice notice-warning inline"><p>No artwork with that SKU number. Try the number at the end of the SKU (MNSPC-2026-P-<strong>0728</strong> is 728).</p></div>
        <?php endif; ?>

        <?php if ( $has ) : ?>
            <div style="background:#fff;border:1px solid #c3c4c7;border-radius:6px;padding:16px;max-width:860px">
                <div style="display:flex;gap:16px;align-items:center">
                    <?php if ( $thumb ) echo '<div>' . $thumb . '</div>'; ?>
                    <div>
                        <div style="font-size:34px;font-weight:700;line-height:1;font-family:monospace"><?php echo esc_html( $number !== '' ? $number : '—' ); ?></div>
                        <div style="font-size:15px;margin-top:4px"><?php echo esc_html( $title ); ?></div>
                        <?php if ( $sku !== '' ) echo '<div class="description" style="font-family:monospace">' . esc_html( $sku ) . '</div>'; ?>
                    </div>
                </div>

                <h2 style="font-size:14px;margin:18px 0 6px">What goes with it</h2>
                <ul style="margin:0 0 0 18px;list-style:disc">
                    <?php foreach ( $parts as $part ) : ?>
                        <li><strong><?php echo esc_html( $part['kind'] ); ?>:</strong>
                            <?php echo ! empty( $part['url'] ) ? '<a href="' . esc_url( $part['url'] ) . '">' . esc_html( $part['label'] ) . '</a>' : esc_html( $part['label'] ); ?></li>
                    <?php endforeach; ?>
                </ul>

                <?php if ( $orders ) : ?>
                    <div class="notice notice-error inline" style="margin:16px 0 0"><p><strong>It has been ordered</strong> (<?php echo esc_html( implode( ', ', array_map( function ( $o ) { return '#' . $o; }, array_slice( $orders, 0, 8 ) ) ) ); ?><?php echo count( $orders ) > 8 ? ' …' : ''; ?>), so it can't be removed. Set it to private or draft in the Artwork Editor instead.</p></div>
                <?php else : ?>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:18px" id="ms-rm-form">
                        <input type="hidden" name="action" value="monospace_artwork_remove">
                        <input type="hidden" name="post" value="<?php echo (int) $found['post']; ?>">
                        <input type="hidden" name="product" value="<?php echo (int) $found['product']; ?>">
                        <?php wp_nonce_field( 'monospace_artwork_remove' ); ?>
                        <p>
                            <label><input type="radio" name="mode" value="trash" checked> <strong>Move to the Trash</strong> - the page and product disappear from the site; you can restore them from the Trash for 30 days.</label><br>
                            <label><input type="radio" name="mode" value="delete"> <strong>Delete permanently</strong> - nothing is left, and this can't be undone.</label>
                        </p>
                        <p id="ms-rm-confirm" hidden>
                            <label for="ms-rm-typed">Type the SKU number (<strong><?php echo esc_html( $number !== '' ? $number : 'none' ); ?></strong>) to confirm:</label>
                            <input type="text" id="ms-rm-typed" name="confirm" autocomplete="off" style="width:120px;font-family:monospace">
                        </p>
                        <p>
                            <label for="ms-rm-reason">Why (for the log, optional):</label>
                            <input type="text" id="ms-rm-reason" name="reason" class="regular-text" placeholder="double entry of …">
                        </p>
                        <p>
                            <button class="button button-primary" style="background:#b32d2e;border-color:#b32d2e">Remove artwork</button>
                            <span class="description"><?php echo $sku !== '' ? 'SKU ' . esc_html( $sku ) . ' is retired and won\'t be handed out again.' : ''; ?></span>
                        </p>
                    </form>
                    <script>
                    (function () {
                        var form = document.getElementById('ms-rm-form'), box = document.getElementById('ms-rm-confirm');
                        form.addEventListener('change', function () { box.hidden = form.elements.mode.value !== 'delete'; });
                        form.addEventListener('submit', function (e) {
                            var del = form.elements.mode.value === 'delete';
                            if (!confirm(del ? 'Delete this artwork permanently? This can\'t be undone.' : 'Move this artwork to the Trash?')) e.preventDefault();
                        });
                    })();
                    </script>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php $log = monospace_artwork_removals(); if ( $log ) : ?>
            <h2 style="margin-top:28px">Removed artworks</h2>
            <p class="description">These SKU numbers are retired and won't be used again.</p>
            <table class="widefat striped" style="max-width:860px">
                <tbody>
                <?php foreach ( array_slice( $log, 0, 50 ) as $e ) : ?>
                    <tr>
                        <td style="font-family:monospace;white-space:nowrap"><?php echo esc_html( $e['sku'] ?: '—' ); ?></td>
                        <td><?php echo esc_html( $e['title'] ); ?><?php echo ! empty( $e['reason'] ) ? ' <span class="description">- ' . esc_html( $e['reason'] ) . '</span>' : ''; ?></td>
                        <td style="white-space:nowrap"><?php echo ( $e['mode'] ?? '' ) === 'delete' ? 'Deleted' : 'Trashed'; ?></td>
                        <td style="white-space:nowrap" class="description"><?php echo esc_html( $e['date'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}

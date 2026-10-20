<?php
/**
 * Prints > All prints - the master list of print products (owner's wish, 2026-10-15: the website,
 * not Printful's dashboard, is the master list; Printful only mirrors the print products to make
 * them). One row per artwork with its unframed / framed print, prices per size / frame and the
 * Printful state in plain words, plus the problem lists:
 *   - print products not linked to an artwork (→ "Link to artwork" when it knows its artwork)
 *   - artworks pointing at a print product that no longer exists
 *   - products Printful has synced that aren't print products here
 * Actions (AJAX monospace_prints_manage): bulk price per print type / size / frame (all, or the
 * ticked artworks); re-link a product's unsynced variants in Printful; link a print to its artwork.
 * Printful's state comes from one list call (GET /sync/products, paged), cached 10 minutes
 * (transient ms_pf_index, cleared by "Refresh from Printful" and after every action).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
    add_submenu_page( 'monospace-prints', 'All prints', 'All prints', 'edit_products', 'monospace-prints-all', 'monospace_prints_overview_page' );
}, 20 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'prints_page_monospace-prints-all' ) return;
    wp_enqueue_script( 'jquery' );
    wp_enqueue_media();
    wp_add_inline_script( 'jquery', monospace_prints_file_script() . monospace_prints_overview_script() );
} );

/** Printful's synced products, keyed by WooCommerce product ID: [ id, name, variants, synced, ignored ] */
function monospace_printful_index( $refresh = false ) {
    $index = $refresh ? false : get_transient( 'ms_pf_index' );
    if ( is_array( $index ) ) return $index;
    $index = []; $offset = 0;
    do {
        $r = monospace_printful( 'GET', '/sync/products?limit=100&offset=' . $offset );
        if ( ! $r['ok'] ) return [ '_error' => $r['error'] ];
        foreach ( $r['result'] as $p ) {
            $index[ (int) $p['external_id'] ] = [ 'id' => $p['id'], 'name' => $p['name'], 'variants' => (int) $p['variants'], 'synced' => (int) $p['synced'], 'ignored' => ! empty( $p['is_ignored'] ) ];
        }
        $offset += 100;
    } while ( count( $r['result'] ) === 100 );
    set_transient( 'ms_pf_index', $index, 10 * MINUTE_IN_SECONDS );
    return $index;
}

/** A print product, described: type, SKU, variants [ label => price ], Printful state */
function monospace_prints_describe( $pid, $pf ) {
    $p = wc_get_product( $pid );
    if ( ! $p ) return null;
    $type = monospace_print_type_of( $p );
    $variants = [];
    foreach ( $p->get_children() as $vid ) {
        $v = wc_get_product( $vid );
        if ( ! $v ) continue;
        $match = monospace_print_variant_for( $type, $v );
        $label = $match ? $match['label'] : implode( ' / ', array_map( function ( $s ) { return preg_replace( '/-\d+$/', '', $s ); }, $v->get_attributes() ) );
        $variants[ $match ? $match['key'] : 'v' . $vid ] = [ 'label' => $label, 'price' => $v->get_regular_price(), 'id' => $vid, 'known' => (bool) $match ];
    }
    // In the template's order (smallest first, frames grouped), anything non-standard last
    $order = array_flip( array_keys( monospace_print_templates()[ $type ]['variants'] ) );
    uksort( $variants, function ( $a, $b ) use ( $order ) { return ( $order[ $a ] ?? 99 ) <=> ( $order[ $b ] ?? 99 ); } );
    $count = count( $variants );
    $f = $pf[ $pid ] ?? null;
    if ( ! $f )                        $state = [ 'problem', 'Not in Printful' ];
    elseif ( $f['ignored'] )           $state = [ 'problem', 'Ignored in Printful' ];
    elseif ( $f['synced'] < $f['variants'] ) $state = [ 'problem', ( $f['variants'] - $f['synced'] ) . ' of ' . $f['variants'] . ' not linked in Printful' ];
    elseif ( $f['variants'] < $count ) $state = [ 'problem', ( $count - $f['variants'] ) . ' variant(s) missing in Printful' ];
    else                               $state = [ 'ready', 'Ready to sell' ];
    return [ 'id' => $pid, 'type' => $type, 'name' => $p->get_name(), 'sku' => $p->get_sku(), 'status' => $p->get_status(),
             'variants' => $variants, 'state' => $state, 'post' => (int) $p->get_meta( '_related_post_id' ) ];
}

/** Everything the page shows */
function monospace_prints_overview_data( $refresh = false ) {
    global $wpdb;
    $pf = monospace_printful_index( $refresh );
    $error = $pf['_error'] ?? '';
    unset( $pf['_error'] );

    $prints = get_posts( [ 'post_type' => 'product', 'post_status' => [ 'publish', 'private', 'draft' ], 'numberposts' => -1, 'fields' => 'ids',
        'tax_query' => [ [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => 'print' ] ] ] );

    // Artwork -> print links
    $links = [];
    foreach ( $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key IN ('_print_product_id','_framed_print_product_id') AND meta_value <> ''" ) as $m ) {
        if ( get_post_type( $m->post_id ) !== 'post' || get_post_status( $m->post_id ) === 'trash' ) continue;
        $links[ (int) $m->post_id ][ $m->meta_key === '_print_product_id' ? 'unframed' : 'framed' ] = (int) $m->meta_value;
    }
    $linked_ids = [];
    $artworks = []; $broken = [];
    foreach ( $links as $post_id => $types ) {
        foreach ( $types as $type => $pid ) {
            $linked_ids[ $pid ] = true;
            $d = monospace_prints_describe( $pid, $pf );
            if ( ! $d ) { $broken[] = [ 'post' => $post_id, 'type' => $type, 'id' => $pid ]; continue; }
            $artworks[ $post_id ][ $type ] = $d;
        }
    }
    // Newest prints first
    uasort( $artworks, function ( $a, $b ) { return max( wp_list_pluck( $b, 'id' ) ) <=> max( wp_list_pluck( $a, 'id' ) ); } );

    $orphans = [];
    foreach ( $prints as $pid ) {
        if ( empty( $linked_ids[ $pid ] ) && ( $d = monospace_prints_describe( $pid, $pf ) ) ) $orphans[] = $d;
    }
    $printful_only = [];
    foreach ( $pf as $ext => $f ) {
        if ( $f['ignored'] || ! $f['synced'] ) continue;
        if ( ! in_array( $ext, $prints, true ) ) $printful_only[] = [ 'ext' => $ext, 'name' => $f['name'], 'wc' => get_post_type( $ext ) ? get_post_status( $ext ) : 'deleted' ];
    }
    return compact( 'artworks', 'orphans', 'broken', 'printful_only', 'error', 'prints' );
}

/* ============================================================
 * ACTIONS
 * ============================================================ */

add_action( 'wp_ajax_monospace_prints_manage', function () {
    check_ajax_referer( 'monospace_prints_manage' );
    if ( ! current_user_can( 'edit_products' ) ) wp_send_json_error( 'Not allowed' );
    $op = sanitize_key( $_POST['op'] ?? '' );
    try {
        switch ( $op ) {
            case 'price':
                $type  = sanitize_key( $_POST['type'] ?? '' );
                $keys  = array_map( 'sanitize_text_field', (array) ( $_POST['keys'] ?? [] ) );
                $price = round( (float) ( $_POST['price'] ?? 0 ), 2 );
                $only  = array_map( 'absint', (array) ( $_POST['products'] ?? [] ) );
                $tpl   = monospace_print_templates()[ $type ] ?? null;
                if ( ! $tpl || ! $keys || $price <= 0 ) throw new Exception( 'Choose a print type, at least one size / frame and a price.' );
                $changed = 0; $products = 0;
                foreach ( monospace_prints_overview_data()['prints'] as $pid ) {
                    if ( $only && ! in_array( $pid, $only, true ) ) continue;
                    $p = wc_get_product( $pid );
                    if ( ! $p || monospace_print_type_of( $p ) !== $type ) continue;
                    $touched = false;
                    foreach ( $p->get_children() as $vid ) {
                        $v = wc_get_product( $vid );
                        $m = $v ? monospace_print_variant_for( $type, $v ) : null;
                        if ( ! $m || ! in_array( $m['key'], $keys, true ) || (float) $v->get_regular_price() === $price ) continue;
                        $v->set_regular_price( (string) $price );
                        $v->save();
                        $changed++; $touched = true;
                    }
                    if ( $touched ) { WC_Product_Variable::sync( $pid ); $products++; }
                }
                if ( function_exists( 'sg_cachepress_purge_everything' ) ) sg_cachepress_purge_everything();
                wp_send_json_success( "Changed $changed price(s) on $products product(s)." );

            case 'relink':
                $pid = absint( $_POST['product'] ?? 0 );
                $p = wc_get_product( $pid );
                if ( ! $p ) throw new Exception( 'No such product.' );
                $post_id = (int) $p->get_meta( '_related_post_id' );
                if ( ! $post_id || ! monospace_prints_file_for( $post_id ) ) throw new Exception( 'The product’s artwork has no featured image to print.' );
                $image = monospace_prints_usable_file( $post_id ); // its print file - never a watermarked one (was the featured image)
                $result = monospace_prints_sync_product( monospace_print_type_of( $p ), $pid, $image );
                delete_transient( 'ms_pf_index' );
                if ( $result === 'waiting' ) throw new Exception( 'Printful doesn’t list this product yet. Is “Import not synced products from WooCommerce” on in Printful? Saving the product in WooCommerce makes Printful look again.' );
                if ( is_array( $result ) ) throw new Exception( "Printful is busy - try again in {$result['wait']} seconds." );
                if ( $result !== 'linked' ) throw new Exception( $result );
                wp_send_json_success( 'All variants linked in Printful.' );

            case 'attach':
                $pid = absint( $_POST['product'] ?? 0 );
                $p = wc_get_product( $pid );
                $post_id = $p ? (int) $p->get_meta( '_related_post_id' ) : 0;
                if ( ! $p || ! $post_id || get_post_type( $post_id ) !== 'post' ) throw new Exception( 'This print doesn’t know its artwork - link it in the artwork’s Quick Artwork Editor.' );
                $type = monospace_print_type_of( $p );
                $meta = monospace_print_templates()[ $type ]['meta_key'];
                $current = (int) get_post_meta( $post_id, $meta, true );
                if ( $current && wc_get_product( $current ) ) throw new Exception( 'That artwork already has another ' . $type . ' print (#' . $current . ').' );
                monospace_prints_link( $post_id, $type, $pid );
                if ( function_exists( 'sg_cachepress_purge_everything' ) ) sg_cachepress_purge_everything();
                wp_send_json_success( 'Linked to “' . get_the_title( $post_id ) . '”.' );

            case 'add_variants':
            case 'remove_variants':
                $pid = absint( $_POST['product'] ?? 0 );
                $p = wc_get_product( $pid );
                if ( ! $p || ! has_term( 'print', 'product_cat', $pid ) ) throw new Exception( 'Not a print product.' );
                $type = monospace_print_type_of( $p );
                $keys = array_values( array_intersect( array_map( 'sanitize_text_field', (array) ( $_POST['keys'] ?? [] ) ), array_keys( monospace_print_templates()[ $type ]['variants'] ) ) );
                if ( ! $keys ) throw new Exception( 'Nothing to do for this print.' );
                delete_transient( 'ms_pf_index' );

                if ( $op === 'remove_variants' ) {
                    try { $n = monospace_prints_remove_variants( $pid, $type, $keys ); }
                    catch ( Exception $e ) { throw new Exception( 'Skipped: ' . $e->getMessage() . '.' ); }
                    wp_send_json_success( $n ? "Removed $n variant(s)." : 'Had none of them.' );
                }

                $post_id = (int) $p->get_meta( '_related_post_id' );
                if ( ! $post_id ) throw new Exception( 'This print doesn’t know its artwork.' );
                if ( monospace_prints_get_job( $post_id ) ) throw new Exception( 'Skipped: a print job for this artwork is running - try again when it’s done.' );
                try { monospace_prints_usable_file( $post_id ); } // before any variation is made
                catch ( Exception $e ) { throw new Exception( 'Skipped: ' . $e->getMessage() ); }
                $tpl = monospace_print_templates()[ $type ];
                $have = monospace_prints_variant_keys( $pid, $type );
                $px = monospace_print_image_size( monospace_prints_file_for( $post_id ) );
                $prices = []; $small = [];
                foreach ( $keys as $key ) {
                    if ( isset( $have[ $key ] ) ) continue;
                    $price = (float) ( $_POST['prices'][ $key ] ?? $tpl['variants'][ $key ]['price'] );
                    if ( $price <= 0 ) continue;
                    if ( monospace_print_dpi( $px, $tpl['variants'][ $key ]['inches'] ) < (int) ( $_POST['min_dpi'] ?? 0 ) ) { $small[] = $tpl['variants'][ $key ]['label']; continue; }
                    $prices[ $key ] = round( $price, 2 );
                }
                $msg = $small ? ' Skipped (image too small): ' . implode( ', ', $small ) . '.' : '';
                if ( ! $prices ) wp_send_json_success( ( $small ? 'Nothing added.' : 'Already has them.' ) . $msg );
                $new = monospace_prints_add_variants( $pid, $type, $prices );
                monospace_prints_queue_variants( $post_id, $type, $pid, $new );
                wp_send_json_success( 'Added ' . count( $new ) . ' variant(s) - linking in Printful in the background.' . $msg );

            case 'purge':
                if ( function_exists( 'sg_cachepress_purge_everything' ) ) sg_cachepress_purge_everything();
                wp_send_json_success( 'Purged.' );

            case 'refresh':
                delete_transient( 'ms_pf_index' );
                wp_send_json_success( 'Refreshed.' );
        }
        throw new Exception( 'Unknown operation' );
    } catch ( Exception $e ) {
        wp_send_json_error( $e->getMessage() );
    }
} );

/* ============================================================
 * PAGE
 * ============================================================ */

function monospace_prints_overview_cell( $d ) {
    if ( ! $d ) return '<span class="description">-</span>';
    $ok = $d['state'][0] === 'ready';
    $out = '<a href="' . esc_url( get_edit_post_link( $d['id'] ) ) . '">#' . (int) $d['id'] . '</a> <span class="description">' . esc_html( $d['sku'] ) . '</span>';
    if ( $d['status'] !== 'publish' ) $out .= ' <em>(' . esc_html( $d['status'] ) . ')</em>';
    $out .= '<div class="ms-pv">' . implode( ' · ', array_map( function ( $v ) {
        return '<span' . ( $v['known'] ? '' : ' title="Not one of the standard sizes / frames" style="color:#9a6700"' ) . '>' . esc_html( $v['label'] ) . ' $' . esc_html( rtrim( rtrim( number_format( (float) $v['price'], 2, '.', '' ), '0' ), '.' ) ) . '</span>';
    }, $d['variants'] ) ) . '</div>';
    $out .= '<div class="ms-ps ' . ( $ok ? 'is-ok' : 'is-bad' ) . '">' . ( $ok ? '✓ ' : '⚠ ' ) . esc_html( $d['state'][1] );
    if ( ! $ok ) $out .= ' <button type="button" class="button-link ms-relink" data-product="' . (int) $d['id'] . '">Re-link in Printful</button>';
    return $out . '</div>';
}

function monospace_prints_overview_page() {
    if ( ! monospace_printful_ready() ) { echo '<div class="wrap"><h1>All prints</h1><p>The Printful token is missing (option <code>monospace_printful_token</code>).</p></div>'; return; }
    $data = monospace_prints_overview_data();
    $problems_only = ! empty( $_GET['problems'] );
    $n_products = 0; $n_ready = 0;
    foreach ( $data['artworks'] as $types ) foreach ( $types as $d ) { $n_products++; if ( $d['state'][0] === 'ready' ) $n_ready++; }
    $n_problems = ( $n_products - $n_ready ) + count( $data['orphans'] ) + count( $data['broken'] ) + count( $data['printful_only'] );
    $templates = monospace_print_templates();
    ?>
    <div class="wrap" id="ms-prints-all" data-nonce="<?php echo esc_attr( wp_create_nonce( 'monospace_prints_manage' ) ); ?>" data-jobnonce="<?php echo esc_attr( wp_create_nonce( 'monospace_prints' ) ); ?>" data-pausenonce="<?php echo esc_attr( wp_create_nonce( 'monospace_prints_pause' ) ); ?>">
        <h1 class="wp-heading-inline">All prints</h1>
        <button type="button" class="page-title-action ms-refresh">Refresh from Printful</button>
        <p class="description" style="max-width:860px">Your master list of print products. WooCommerce holds the products, prices and links to the artworks; Printful only mirrors them to make them - its own product list doesn't need looking after.</p>
        <?php if ( $data['error'] ) echo '<div class="notice notice-error inline"><p>Printful couldn’t be reached: ' . esc_html( $data['error'] ) . '</p></div>'; ?>

        <div class="ms-summary">
            <div><strong><?php echo count( $data['artworks'] ); ?></strong> artworks with prints</div>
            <div><strong><?php echo $n_products; ?></strong> print products</div>
            <div class="is-ok"><strong><?php echo $n_ready; ?></strong> ready to sell</div>
            <div class="<?php echo $n_problems ? 'is-bad' : 'is-ok'; ?>"><strong><?php echo $n_problems; ?></strong> to look at</div>
        </div>
        <div class="notice inline ms-result" style="display:none"><p></p></div>

        <div class="lm-areas" style="max-width:none"><div class="lm-panels" style="margin:16px 0 8px">
        <?php if ( $data['broken'] || $data['orphans'] || $data['printful_only'] ) : ?>
            <details class="lm-panel" open>
            <summary><span class="lm-t">To look at</span> <span class="lm-chip is-warn"><?php echo (int) ( count( $data['broken'] ) + count( $data['orphans'] ) + count( $data['printful_only'] ) ); ?></span>
                <span class="lm-sub">Broken links, unlinked print products, products only Printful knows</span></summary>
            <div class="lm-body">
            <?php if ( $data['broken'] ) : ?>
                <h3>Artworks pointing at a print that no longer exists</h3>
                <ul class="ms-list"><?php foreach ( $data['broken'] as $b ) echo '<li><a href="' . esc_url( get_edit_post_link( $b['post'] ) ) . '">' . esc_html( get_the_title( $b['post'] ) ) . '</a>: ' . esc_html( $b['type'] ) . ' print #' . (int) $b['id'] . ' is gone - clear or replace it in the artwork’s Quick Artwork Editor.</li>'; ?></ul>
            <?php endif; ?>
            <?php if ( $data['orphans'] ) : ?>
                <h3>Print products not linked to an artwork</h3>
                <table class="widefat striped ms-table"><tbody>
                <?php foreach ( $data['orphans'] as $d ) : ?>
                    <tr><td style="width:280px"><strong><?php echo esc_html( $d['name'] ); ?></strong><br><span class="description"><?php echo esc_html( ucfirst( $d['type'] ) ); ?> print<?php if ( $d['post'] ) echo ' of <a href="' . esc_url( get_edit_post_link( $d['post'] ) ) . '">' . esc_html( get_the_title( $d['post'] ) ) . '</a>'; ?></span></td>
                        <td><?php echo monospace_prints_overview_cell( $d ); ?></td>
                        <td style="width:150px"><?php if ( $d['post'] ) echo '<button type="button" class="button ms-attach" data-product="' . (int) $d['id'] . '">Link to artwork</button>'; ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
            <?php if ( $data['printful_only'] ) : ?>
                <h3>Synced in Printful, but not a print product here</h3>
                <ul class="ms-list"><?php foreach ( $data['printful_only'] as $x ) echo '<li>' . esc_html( $x['name'] ) . ' (WooCommerce #' . (int) $x['ext'] . ', ' . esc_html( $x['wc'] ) . ') - Printful would make it if it were ordered.</li>'; ?></ul>
            <?php endif; ?>
            </div></details>
        <?php endif; ?>

        <details class="lm-panel">
        <summary><span class="lm-t">Change prices</span> <span class="lm-chip is-empty">Bulk</span>
            <span class="lm-sub">A new price for chosen sizes - on all prints, or only the artworks ticked below</span></summary>
        <div class="lm-body">
        <div class="ms-price-form">
            <select class="ms-price-type"><?php foreach ( $templates as $type => $tpl ) echo '<option value="' . esc_attr( $type ) . '">' . esc_html( $tpl['label'] ) . '</option>'; ?></select>
            <?php foreach ( $templates as $type => $tpl ) : ?>
                <span class="ms-price-keys" data-type="<?php echo esc_attr( $type ); ?>" <?php if ( $type !== 'unframed' ) echo 'style="display:none"'; ?>>
                    <?php foreach ( $tpl['variants'] as $key => $v ) echo '<label><input type="checkbox" value="' . esc_attr( $key ) . '"> ' . esc_html( $v['label'] ) . '</label> '; ?>
                </span>
            <?php endforeach; ?>
            → $ <input type="number" class="ms-price-value small-text" min="1" step="1">
            <select class="ms-price-scope"><option value="all">on all prints</option><option value="ticked">only on the ticked artworks</option></select>
            <button type="button" class="button button-primary ms-price-go">Apply</button>
        </div>
        </div></details>

        <details class="lm-panel">
        <summary><span class="lm-t">Sizes and frames</span> <span class="lm-chip is-empty">Bulk</span>
            <span class="lm-sub">Add or remove sizes and frames - on the ticked artworks, or on all prints</span></summary>
        <div class="lm-body">
        <div class="ms-price-form ms-var-form">
            <select class="ms-var-mode"><option value="add">Add</option><option value="remove">Remove</option></select>
            <select class="ms-var-type"><?php foreach ( $templates as $type => $tpl ) echo '<option value="' . esc_attr( $type ) . '">' . esc_html( $tpl['label'] ) . '</option>'; ?></select>:
            <?php foreach ( $templates as $type => $tpl ) : ?>
                <span class="ms-var-keys" data-type="<?php echo esc_attr( $type ); ?>" <?php if ( $type !== 'unframed' ) echo 'style="display:none"'; ?>>
                    <?php foreach ( $tpl['variants'] as $key => $v ) : ?>
                        <label><input type="checkbox" value="<?php echo esc_attr( $key ); ?>"> <?php echo esc_html( $v['label'] ); ?><span class="ms-var-price"> $<input type="number" class="small-text" min="1" step="1" value="<?php echo esc_attr( $v['price'] ); ?>" style="width:58px"></span></label>
                    <?php endforeach; ?>
                </span>
            <?php endforeach; ?>
            <select class="ms-var-scope"><option value="ticked">on the ticked artworks</option><option value="all">on all prints</option></select>
            <button type="button" class="button button-primary ms-var-go">Apply</button>
            <br><span class="description">Adding skips sizes an artwork’s print file is too small for (under 100 dpi); the new variants go on sale once Printful has linked them (background). Removing deletes them in WooCommerce and Printful at once; a print always keeps at least one.</span>
        </div>
        </div></details>
        </div></div>
        <?php if ( function_exists( 'monospace_lm_area_assets' ) ) monospace_lm_area_assets(); // the admin UI kit (Core) ?>

        <h2>Prints by artwork</h2>
        <p class="subsubsub" style="float:none">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=monospace-prints-all' ) ); ?>" <?php if ( ! $problems_only ) echo 'class="current"'; ?>>All</a> |
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=monospace-prints-all&problems=1' ) ); ?>" <?php if ( $problems_only ) echo 'class="current"'; ?>>With problems</a>
        </p>
        <table class="widefat striped ms-table">
            <thead><tr><td class="check-column"><input type="checkbox" class="ms-all"></td><th style="width:60px"></th><th>Artwork</th><th>Unframed print</th><th>Framed print</th></tr></thead>
            <tbody>
            <?php foreach ( $data['artworks'] as $post_id => $types ) :
                $bad = array_filter( $types, function ( $d ) { return $d['state'][0] !== 'ready'; } );
                if ( $problems_only && ! $bad ) continue;
                $ids = implode( ',', wp_list_pluck( $types, 'id' ) ); ?>
                <?php $job = monospace_prints_status( $post_id ); $busy = in_array( $job['state'], [ 'waiting', 'linking', 'mockups', 'error' ], true ); ?>
                <tr data-products="<?php echo esc_attr( $ids ); ?>" data-post="<?php echo (int) $post_id; ?>"
                    data-unframed="<?php echo (int) ( $types['unframed']['id'] ?? 0 ); ?>" data-framed="<?php echo (int) ( $types['framed']['id'] ?? 0 ); ?>">
                    <th class="check-column"><input type="checkbox" class="ms-pick"></th>
                    <td><?php echo get_the_post_thumbnail( $post_id, [ 50, 50 ] ); ?></td>
                    <td><a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>"><strong><?php echo esc_html( get_the_title( $post_id ) ); ?></strong></a><br>
                        <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" class="description">view</a> ·
                        <button type="button" class="button-link ms-file">Replace print file</button> ·
                        <?php $paused = function_exists( 'monospace_prints_is_paused' ) && monospace_prints_is_paused( $post_id ); ?>
                        <button type="button" class="button-link ms-pause" data-paused="<?php echo $paused ? 1 : 0; ?>"><?php echo $paused ? 'Show prints' : 'Hide prints'; ?></button>
                        <span class="ms-paused-tag"<?php echo $paused ? '' : ' hidden'; ?>>Hidden</span><?php
                        if ( ! $paused && function_exists( 'monospace_prints_hidden' ) && monospace_prints_hidden( $post_id ) ) echo '<span class="ms-paused-tag">' . esc_html( monospace_prints_hidden_words( $post_id ) ) . '</span>'; ?>
                        <div class="ms-job <?php echo $job['state'] === 'error' ? 'is-bad' : ''; ?>"><?php if ( $busy ) echo esc_html( $job['text'] ); ?></div>
                        <div class="ms-preview" style="display:none"></div></td>
                    <td><?php echo monospace_prints_overview_cell( $types['unframed'] ?? null ); ?></td>
                    <td><?php echo monospace_prints_overview_cell( $types['framed'] ?? null ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <style>
        #ms-prints-all .ms-summary { display: flex; gap: 12px; margin: 14px 0; flex-wrap: wrap; }
        #ms-prints-all .ms-summary > div { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 10px 16px; min-width: 150px; }
        #ms-prints-all .ms-summary strong { display: block; font-size: 22px; line-height: 1.3; }
        #ms-prints-all .is-ok strong, #ms-prints-all .ms-ps.is-ok { color: #1a7f37; }
        #ms-prints-all .is-bad strong, #ms-prints-all .ms-ps.is-bad { color: #b32d2e; }
        #ms-prints-all .ms-table { max-width: 1250px; }
        #ms-prints-all .ms-table td { vertical-align: top; }
        #ms-prints-all .ms-pv { margin: 3px 0; font-size: 12px; }
        #ms-prints-all .ms-ps { font-size: 12px; font-weight: 600; }
        #ms-prints-all .ms-price-form { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 12px 14px; max-width: 1220px; line-height: 2.4; }
        #ms-prints-all .ms-price-form label { margin-right: 8px; white-space: nowrap; }
        #ms-prints-all .ms-list li { margin-left: 18px; list-style: disc; }
        #ms-prints-all .ms-job { font-size: 12px; color: #2271b1; margin-top: 4px; }
        #ms-prints-all .ms-job.is-bad { color: #b32d2e; }
        #ms-prints-all .ms-paused-tag { margin-left: 6px; background: #646970; color: #fff; border-radius: 3px; padding: 0 6px; font-size: 11px; }
        #ms-prints-all .ms-paused-tag[hidden] { display: none; }
        #ms-prints-all tr.is-paused td:not(:first-child) { opacity: .6; }
    </style>
    <?php
}

function monospace_prints_overview_script() {
    return <<<'JS'
jQuery(function ($) {
    var $page = $('#ms-prints-all');
    if (!$page.length) return;
    var nonce = $page.data('nonce');
    function call(op, data) {
        return $.post(window.ajaxurl || '/wp-admin/admin-ajax.php', $.extend({ action: 'monospace_prints_manage', op: op, _ajax_nonce: nonce }, data));
    }
    function result(r, reload) {
        var $n = $page.find('.ms-result').removeClass('notice-success notice-error').addClass(r.success ? 'notice-success' : 'notice-error').show();
        $n.find('p').text(r.data || (r.success ? 'Done.' : 'Error'));
        $('html, body').animate({ scrollTop: $n.offset().top - 60 }, 200);
        if (r.success && reload) setTimeout(function () { location.reload(); }, 1200);
    }
    $page.on('change', '.ms-all', function () { $page.find('.ms-pick').prop('checked', this.checked); });
    $page.on('change', '.ms-price-type', function () {
        $page.find('.ms-price-keys').hide().filter('[data-type="' + this.value + '"]').show();
    });
    $page.on('click', '.ms-price-go', function () {
        var type = $page.find('.ms-price-type').val();
        var keys = $page.find('.ms-price-keys[data-type="' + type + '"] input:checked').map(function () { return this.value; }).get();
        var price = $page.find('.ms-price-value').val();
        var scope = $page.find('.ms-price-scope').val();
        var products = [];
        if (scope === 'ticked') {
            $page.find('.ms-pick:checked').closest('tr').each(function () { products = products.concat(String($(this).data('products')).split(',')); });
            if (!products.length) { alert('Tick the artworks first, or choose “on all prints”.'); return; }
        }
        if (!keys.length || !(price > 0)) { alert('Choose at least one size / frame and a price.'); return; }
        if (!confirm('Set ' + keys.join(', ') + ' to $' + price + ' ' + (scope === 'all' ? 'on all ' + type + ' prints' : 'on the ticked artworks') + '? The new prices are live at once.')) return;
        $(this).prop('disabled', true);
        call('price', { type: type, keys: keys, price: price, products: products }).done(function (r) { result(r, true); }).always(function () { $page.find('.ms-price-go').prop('disabled', false); });
    });
    // Pause / resume an artwork's prints (prints-pause.php): out of stock + out of the Print category
    function markPaused($tr, paused) {
        $tr.toggleClass('is-paused', paused);
        $tr.find('.ms-pause').data('paused', paused ? 1 : 0).text(paused ? 'Show prints' : 'Hide prints');
        $tr.find('.ms-paused-tag').not(':first').remove(); // a "Framed print hidden" tag is out of date now
        $tr.find('.ms-paused-tag').prop('hidden', !paused);
    }
    $page.find('.ms-pause').each(function () { if (+$(this).data('paused')) $(this).closest('tr').addClass('is-paused'); });
    $page.on('click', '.ms-pause', function () {
        var $b = $(this), $tr = $b.closest('tr'), pause = !+$b.data('paused');
        if (!confirm(pause ? 'Hide prints? They come off the price card and the site. Nothing is deleted - Show prints puts them back.' : 'Show prints? They go back on sale on the price card and the site.')) return;
        $b.prop('disabled', true);
        $.post(window.ajaxurl, { action: 'monospace_prints_pause', op: pause ? 'hide' : 'show', post_id: $tr.data('post'), _ajax_nonce: $page.data('pausenonce') })
            .done(function (r) {
                if (r.success) { markPaused($tr, r.data.paused); $tr.find('.ms-job').removeClass('is-bad').text(r.data.message); }
                else $tr.find('.ms-job').addClass('is-bad').text(r.data || 'Error');
            })
            .fail(function (x) { $tr.find('.ms-job').addClass('is-bad').text('Request failed (' + x.status + ')'); })
            .always(function () { $b.prop('disabled', false); });
    });

    $page.on('click', '.ms-relink', function () {
        $(this).prop('disabled', true).text('Linking…');
        call('relink', { product: $(this).data('product') }).done(function (r) { result(r, r.success); });
    });
    $page.on('click', '.ms-attach', function () {
        if (!confirm('Link this print to its artwork? Its print tab appears on the artwork’s price card.')) return;
        call('attach', { product: $(this).data('product') }).done(function (r) { result(r, r.success); });
    });
    var jobnonce = $page.data('jobnonce');
    $page.on('change', '.ms-var-type', function () { $page.find('.ms-var-keys').hide().filter('[data-type="' + this.value + '"]').show(); });
    $page.on('change', '.ms-var-mode', function () { var add = this.value === 'add'; $page.find('.ms-var-price').toggle(add); });
    $page.on('click', '.ms-var-go', function () {
        var mode = $page.find('.ms-var-mode').val(), type = $page.find('.ms-var-type').val(), scope = $page.find('.ms-var-scope').val();
        var $keys = $page.find('.ms-var-keys[data-type="' + type + '"] input[type=checkbox]:checked');
        if (!$keys.length) { alert('Tick at least one size / frame.'); return; }
        var keys = $keys.map(function () { return this.value; }).get(), prices = {};
        $keys.each(function () { prices[this.value] = $(this).closest('label').find('input[type=number]').val(); });
        var $rows = (scope === 'all' ? $page.find('tr[data-post]') : $page.find('.ms-pick:checked').closest('tr')).filter(function () { return +$(this).data(type) > 0; });
        if (!$rows.length) { alert(scope === 'all' ? 'No ' + type + ' prints.' : 'Tick artworks that have a ' + type + ' print.'); return; }
        var what = (mode === 'add' ? 'Add ' : 'Remove ') + keys.join(', ') + (mode === 'add' ? ' to ' : ' from ') + 'the ' + type + ' print of ' + $rows.length + ' artwork(s)?';
        if (!confirm(what + (mode === 'remove' ? ' They disappear from the shop and from Printful right away.' : ' They go on sale once Printful has linked them.'))) return;
        var queue = $rows.get(), ok = 0, $btn = $(this).prop('disabled', true);
        (function next() {
            if (!queue.length) {
                call('purge', {});
                $btn.prop('disabled', false);
                result({ success: true, data: 'Done: ' + ok + ' of ' + $rows.length + ' print(s) changed - see each row. ' + (mode === 'add' ? 'New variants link in the background (“Prints: running” in the admin bar).' : '') }, false);
                return;
            }
            var $tr = $(queue.shift());
            $tr.find('.ms-job').removeClass('is-bad').text((mode === 'add' ? 'Adding' : 'Removing') + '…');
            call(mode === 'add' ? 'add_variants' : 'remove_variants', { product: $tr.data(type), keys: keys, prices: prices, min_dpi: 100 }).done(function (r) {
                if (r.success) { ok++; $tr.find('.ms-job').text(r.data); } else $tr.find('.ms-job').addClass('is-bad').text(r.data || 'Error');
            }).fail(function (x) { $tr.find('.ms-job').addClass('is-bad').text('Request failed (' + x.status + ')'); }).always(next);
        })();
    });
    function job(op, post, data) {
        return $.post(window.ajaxurl || '/wp-admin/admin-ajax.php', $.extend({ action: 'monospace_prints', op: op, post_id: post, _ajax_nonce: jobnonce }, data));
    }
    $page.on('click', '.ms-file', function () {
        var $tr = $(this).closest('tr');
        window.msPrintsReplaceFile($tr.data('post'), jobnonce, $tr.find('.ms-preview'), function (st) { $tr.find('.ms-job').text(st.text); });
    });
    $page.on('click', '.ms-refresh', function () {
        $(this).prop('disabled', true).text('Refreshing…');
        call('refresh', {}).done(function () { location.reload(); });
    });
});
JS;
}

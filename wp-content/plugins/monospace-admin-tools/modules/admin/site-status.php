<?php
/**
 * Dashboard: "monospace ART - Status" (2026-10-20, owner's wish: an overview of the site on the WP Dashboard).
 *
 * One box, at the top of the Dashboard (drag it elsewhere and WordPress remembers), in sections:
 *   Artworks        how many per availability (Available · Reserved · Gallery · Gallery QR · Sold · Private), drafts
 *   Needs attention the Artwork Editor's list - no price where one is needed, missing details, no image, title
 *                   differs from the post - plus Original products whose artwork is gone
 *   Orders          waiting to be sent, the last 30 days (count, total), the latest order
 *   Prints          (Prints plugin) artworks with prints, hidden, jobs running / stopped, watermarked files
 *   Sales           (Sales Dashboard) its global settings in a few words
 *   Newest          the five newest artworks
 *   Site            feature plugins switched off, price card presets, scroll / open-at-content settings
 * Each count links to where it's handled; artwork counts open the Artwork Editor with those artworks selected
 * (&select=<product IDs>).
 *
 * Needs Core (monospace_artwork_editor_rows() …); the Prints and Sales parts only show while those plugins are on.
 * The numbers are kept for 10 minutes (transient ms_site_status) - "Refresh" counts again.
 *
 * @package monospace-admin-tools
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_dashboard_setup', function () {
    if ( ! current_user_can( 'edit_products' ) || ! function_exists( 'monospace_artwork_editor_rows' ) ) return;
    wp_add_dashboard_widget( 'ms_site_status', 'monospace ART - Status', 'monospace_site_status_widget' );
    // First in the left column (a Dashboard nobody has arranged yet)
    global $wp_meta_boxes;
    $normal = &$wp_meta_boxes['dashboard']['normal']['core'];
    if ( isset( $normal['ms_site_status'] ) ) $normal = [ 'ms_site_status' => $normal['ms_site_status'] ] + $normal;
} );

// An arranged Dashboard (saved order) puts a new box last: put this one first until it's part of the saved
// order - once it's dragged anywhere, WordPress saves that and this leaves it alone. Nothing is written here.
add_filter( 'get_user_option_meta-box-order_dashboard', function ( $order ) {
    if ( ! is_array( $order ) || strpos( implode( ',', $order ), 'ms_site_status' ) !== false ) return $order;
    $order['normal'] = 'ms_site_status' . ( ! empty( $order['normal'] ) ? ',' . $order['normal'] : '' );
    return $order;
} );

/** Everything the box shows, counted (kept 10 minutes) */
function monospace_site_status_data( $fresh = false ) {
    $data = $fresh ? false : get_transient( 'ms_site_status' );
    if ( is_array( $data ) ) return $data;

    $rows   = monospace_artwork_editor_rows();
    $names  = class_exists( '\QuickProduct\MetaBox' ) ? \QuickProduct\MetaBox::availability_options() : [];
    $by     = [];  // availability => [ product ids ]
    $drafts = [];
    $attn   = [ 'price' => [], 'details' => [], 'image' => [], 'title' => [] ];
    foreach ( $rows as $r ) {
        $by[ $r['availability'] ][] = $r['product_id'];
        if ( $r['status'] !== 'publish' ) $drafts[] = $r['product_id'];
        if ( ! ( (float) $r['price'] > 0 ) && ( ! function_exists( 'monospace_price_required' ) || monospace_price_required( $r['availability'] ) ) ) $attn['price'][] = $r['product_id'];
        if ( empty( $r['attributes']['pa_format'] ) || empty( $r['attributes']['pa_medium'] ) || empty( $r['attributes']['pa_year-created'] ) ) $attn['details'][] = $r['product_id'];
        if ( ! $r['thumb'] ) $attn['image'][] = $r['product_id'];
        if ( $r['title'] !== $r['post_title'] && ! $r['title_ok'] ) $attn['title'][] = $r['product_id'];
    }
    $any = array_values( array_unique( array_merge( ...array_values( $attn ) ) ) );

    // Newest five
    usort( $rows, function ( $a, $b ) { return strcmp( $b['date'], $a['date'] ) ?: $b['post_id'] <=> $a['post_id']; } );
    $newest = array_map( function ( $r ) {
        return [ 'title' => $r['title'], 'num' => $r['num'], 'availability' => $r['availability'], 'status' => $r['status'], 'date' => $r['date'],
                 'thumb' => $r['thumb'], 'edit' => $r['edit'], 'view' => $r['view'] ];
    }, array_slice( $rows, 0, 5 ) );

    // Orders
    $orders = null;
    if ( function_exists( 'wc_get_orders' ) ) {
        $waiting = wc_get_orders( [ 'status' => [ 'processing', 'on-hold' ], 'limit' => -1, 'return' => 'ids' ] );
        $recent  = wc_get_orders( [ 'status' => [ 'processing', 'completed', 'on-hold' ], 'date_created' => '>' . ( time() - 30 * DAY_IN_SECONDS ), 'limit' => -1 ] );
        $total   = 0.0;
        foreach ( $recent as $o ) $total += (float) $o->get_total() - (float) $o->get_total_refunded();
        $last    = wc_get_orders( [ 'limit' => 1, 'orderby' => 'date', 'order' => 'DESC', 'status' => array_keys( wc_get_order_statuses() ) ] );
        $orders  = [ 'waiting' => count( $waiting ), 'recent' => count( $recent ), 'total' => $total,
                     'last' => $last ? [ 'number' => $last[0]->get_order_number(), 'when' => $last[0]->get_date_created() ? $last[0]->get_date_created()->getTimestamp() : 0,
                                         'status' => wc_get_order_status_name( $last[0]->get_status() ), 'edit' => $last[0]->get_edit_order_url() ] : null ];
    }

    // Prints
    $prints = null;
    if ( function_exists( 'monospace_feature' ) && monospace_feature( 'prints' ) ) {
        $with = $hidden = $running = $stopped = [];
        foreach ( $rows as $r ) {
            $p = $r['prints'];
            if ( $p['framed'] || $p['unframed'] ) {
                $with[] = $r['product_id'];
                if ( ( ! $p['framed'] || $p['hidden']['framed'] ) && ( ! $p['unframed'] || $p['hidden']['unframed'] ) ) $hidden[] = $r['product_id'];
            }
            if ( $p['job'] && $p['job']['state'] === 'error' ) $stopped[] = $r['product_id'];
            elseif ( $p['job'] ) $running[] = $r['product_id'];
        }
        $marks = function_exists( 'monospace_prints_watermark_review' ) ? monospace_prints_watermark_review() : null;
        $prints = [ 'with' => $with, 'hidden' => $hidden, 'running' => $running, 'stopped' => $stopped,
                    'watermarked' => $marks ? count( $marks['print'] ) : null ];
    }

    $data = [
        'time'    => time(),
        'total'   => count( $rows ),
        'by'      => $by,
        'names'   => $names,
        'drafts'  => $drafts,
        'attn'    => $attn,
        'any'     => $any,
        'orphans' => function_exists( 'monospace_artwork_editor_orphans' ) ? count( monospace_artwork_editor_orphans() ) : 0,
        'newest'  => $newest,
        'orders'  => $orders,
        'prints'  => $prints,
    ];
    set_transient( 'ms_site_status', $data, 10 * MINUTE_IN_SECONDS );
    return $data;
}

function monospace_site_status_widget() {
    $fresh = isset( $_GET['ms_status_refresh'] ) && wp_verify_nonce( $_GET['ms_status_refresh'], 'ms_status_refresh' );
    $d     = monospace_site_status_data( $fresh );
    $ae    = admin_url( 'admin.php?page=monospace-artwork-editor' );
    // The Artwork Editor with these artworks selected (a plain link when there are too many for an address)
    $sel   = function ( $ids ) use ( $ae ) { return count( $ids ) && count( $ids ) <= 150 ? $ae . '&select=' . implode( ',', $ids ) : $ae; };
    $n     = function ( $count, $url, $label, $class = '' ) {
        return '<a class="ms-st-n' . ( $class ? ' ' . $class : '' ) . '" href="' . esc_url( $url ) . '"><b>' . number_format_i18n( $count ) . '</b> ' . esc_html( $label ) . '</a>';
    };
    $order = [ 'available', 'reserved', 'gallery', 'gallery-reserved', 'sold', 'private' ];
    $short = [ 'available' => 'Available', 'reserved' => 'Reserved', 'gallery' => 'Gallery', 'gallery-reserved' => 'Gallery QR', 'sold' => 'Sold', 'private' => 'Private' ];
    ?>
    <div class="ms-st">
        <!-- ARTWORKS -->
        <section>
            <h3><a href="<?php echo esc_url( $ae ); ?>">Artworks</a> <span><?php echo number_format_i18n( $d['total'] ); ?></span></h3>
            <p class="ms-st-chips">
                <?php foreach ( array_merge( $order, array_diff( array_keys( $d['by'] ), $order ) ) as $st ) :
                    if ( empty( $d['by'][ $st ] ) ) continue;
                    echo $n( count( $d['by'][ $st ] ), $sel( $d['by'][ $st ] ), $short[ $st ] ?? ( $d['names'][ $st ] ?? $st ), 'is-' . sanitize_html_class( $st ) );
                endforeach;
                if ( $d['drafts'] ) echo $n( count( $d['drafts'] ), $sel( $d['drafts'] ), count( $d['drafts'] ) === 1 ? 'draft' : 'drafts', 'is-draft' ); ?>
            </p>
        </section>

        <!-- NEEDS ATTENTION -->
        <section>
            <h3>Needs attention <?php echo $d['any'] || $d['orphans'] ? '<span class="is-warn">' . number_format_i18n( count( $d['any'] ) + $d['orphans'] ) . '</span>' : '<span class="is-ok">✓ none</span>'; ?></h3>
            <?php if ( $d['any'] || $d['orphans'] ) : ?>
            <ul class="ms-st-list">
                <?php foreach ( [ 'price' => 'no price (available, reserved or Gallery QR)', 'details' => 'missing format, medium or year', 'image' => 'no image', 'title' => 'product name differs from the post title' ] as $k => $label ) :
                    if ( $d['attn'][ $k ] ) echo '<li>' . $n( count( $d['attn'][ $k ] ), $sel( $d['attn'][ $k ] ), $label ) . '</li>';
                endforeach;
                if ( $d['orphans'] ) echo '<li>' . $n( $d['orphans'], $ae, $d['orphans'] === 1 ? 'original product without its artwork post' : 'original products without their artwork post' ) . '</li>'; ?>
            </ul>
            <?php endif; ?>
        </section>

        <?php if ( $d['orders'] ) : $o = $d['orders']; ?>
        <!-- ORDERS -->
        <section>
            <h3><a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders' ) ); ?>">Orders</a></h3>
            <p class="ms-st-chips">
                <?php echo $n( $o['waiting'], admin_url( 'admin.php?page=wc-orders&status=wc-processing' ), 'waiting to be sent', $o['waiting'] ? 'is-warn' : '' ); ?>
                <span class="ms-st-n"><b><?php echo number_format_i18n( $o['recent'] ); ?></b> in the last 30 days<?php echo $o['recent'] ? ' · ' . wp_kses_post( wc_price( $o['total'] ) ) : ''; ?></span>
            </p>
            <?php if ( $o['last'] ) : ?>
                <p class="ms-st-note">Latest: <a href="<?php echo esc_url( $o['last']['edit'] ); ?>">#<?php echo esc_html( $o['last']['number'] ); ?></a>,
                    <?php echo esc_html( human_time_diff( $o['last']['when'] ) ); ?> ago (<?php echo esc_html( $o['last']['status'] ); ?>)</p>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ( $d['prints'] ) : $p = $d['prints']; $all = admin_url( 'admin.php?page=monospace-prints-all' ); ?>
        <!-- PRINTS -->
        <section>
            <h3><a href="<?php echo esc_url( $all ); ?>">Prints</a></h3>
            <p class="ms-st-chips">
                <?php echo $n( count( $p['with'] ), $sel( $p['with'] ), 'artworks with prints' );
                if ( $p['hidden'] ) echo $n( count( $p['hidden'] ), $sel( $p['hidden'] ), 'hidden', 'is-draft' );
                if ( $p['running'] ) echo $n( count( $p['running'] ), $all, 'being made' );
                if ( $p['stopped'] ) echo $n( count( $p['stopped'] ), $all, 'stopped - see the error', 'is-warn' );
                if ( $p['watermarked'] ) echo $n( $p['watermarked'], admin_url( 'admin.php?page=monospace-prints-watermarks' ), 'print files still watermarked', 'is-warn' ); ?>
            </p>
        </section>
        <?php endif; ?>

        <?php $sales = function_exists( 'monospace_feature' ) && monospace_feature( 'sales' ) && function_exists( 'monospace_artwork_editor_sales_summary' ) ? monospace_artwork_editor_sales_summary() : null;
        if ( $sales ) : ?>
        <!-- SALES (live, not kept) -->
        <section>
            <h3><a href="<?php echo esc_url( $sales['dashboard'] ); ?>">Sales</a></h3>
            <ul class="ms-st-list">
                <?php foreach ( $sales['items'] as [ $text, $url ] ) echo '<li><a href="' . esc_url( $url ) . '">' . esc_html( $text ) . '</a></li>'; ?>
            </ul>
        </section>
        <?php endif; ?>

        <!-- NEWEST -->
        <section>
            <h3>Newest artworks <a class="ms-st-more" href="<?php echo esc_url( admin_url( 'admin.php?page=monospace-artworks' ) ); ?>">+ New artwork</a></h3>
            <ul class="ms-st-new">
                <?php foreach ( $d['newest'] as $r ) : ?>
                    <li>
                        <?php echo $r['thumb'] ? '<img src="' . esc_url( $r['thumb'] ) . '" alt="" width="36" height="36" loading="lazy">' : '<span class="ms-st-noimg"></span>'; ?>
                        <span class="ms-st-num"><?php echo $r['num'] !== null ? '#' . (int) $r['num'] : ''; ?></span>
                        <a href="<?php echo esc_url( $r['edit'] ); ?>"><?php echo esc_html( $r['title'] ); ?></a>
                        <span class="ms-st-dot is-<?php echo esc_attr( sanitize_html_class( $r['availability'] ) ); ?>"><?php echo esc_html( $short[ $r['availability'] ] ?? $r['availability'] ); ?></span>
                        <?php if ( $r['status'] !== 'publish' ) echo '<span class="ms-st-dot is-draft">Draft</span>'; ?>
                        <span class="ms-st-date"><?php echo esc_html( date_i18n( 'M j', strtotime( $r['date'] ) ) ); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- SITE (live, not kept) -->
        <?php
        $features = [ 'prints' => 'Prints', 'sales' => 'Sales Dashboard', 'commissions' => 'Commissions', 'sheets' => 'Artwork Sheets',
                      'terminal' => 'Market terminal', 'art-filter' => 'Art Filter', 'seo' => 'SEO', 'privacy' => 'Privacy' ];
        $off = function_exists( 'monospace_feature' ) ? array_values( array_filter( $features, fn( $k ) => ! monospace_feature( $k ), ARRAY_FILTER_USE_KEY ) ) : [];
        $lm  = admin_url( 'admin.php?page=monospace-layout-manager&tab=effects' );
        $card = function_exists( 'monospace_design_modules' ) && isset( monospace_design_modules()['price-card'] ) ? monospace_design_modules()['price-card']['summary']() : '';
        ?>
        <section>
            <h3>Site</h3>
            <ul class="ms-st-list">
                <li><a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>"><?php echo $off ? 'Switched off: ' . esc_html( implode( ', ', $off ) ) : 'All feature plugins on'; ?></a></li>
                <?php if ( $card ) : ?><li><a href="<?php echo esc_url( $lm ); ?>">Price card: <?php echo esc_html( $card ); ?></a></li><?php endif; ?>
                <?php if ( function_exists( 'monospace_scroll_effect' ) ) : ?><li><a href="<?php echo esc_url( $lm ); ?>">Scroll effect: <?php echo esc_html( ucfirst( monospace_scroll_effect() ) ); ?></a></li><?php endif; ?>
            </ul>
        </section>

        <p class="ms-st-foot">Counted <?php echo esc_html( human_time_diff( $d['time'] ) ); ?> ago ·
            <a href="<?php echo esc_url( add_query_arg( 'ms_status_refresh', wp_create_nonce( 'ms_status_refresh' ), admin_url( 'index.php' ) ) ); ?>">Refresh</a></p>
    </div>

    <style>
        .ms-st section { padding: 10px 0; border-bottom: 1px solid #f0f0f1; }
        .ms-st section:first-child { padding-top: 0; }
        .ms-st h3 { display: flex; align-items: baseline; gap: 8px; margin: 0 0 8px !important; padding: 0 !important; font-size: 14px !important; font-weight: 600; }
        .ms-st h3 a { text-decoration: none; }
        .ms-st h3 > span { font-weight: 400; color: #646970; }
        .ms-st h3 .is-warn { color: #b32d2e; font-weight: 600; }
        .ms-st h3 .is-ok { color: #00a32a; }
        .ms-st-more { margin-left: auto; font-weight: 400; font-size: 13px; }
        .ms-st-chips { display: flex; flex-wrap: wrap; gap: 6px; margin: 0; }
        .ms-st-n { display: inline-block; padding: 3px 10px; border-radius: 12px; background: #f0f0f1; color: #1d2327; text-decoration: none; border-left: 3px solid #c3c4c7; }
        a.ms-st-n:hover { background: #e7f0fa; color: #135e96; }
        .ms-st-n.is-available { border-left-color: #00a32a; } .ms-st-n.is-reserved { border-left-color: #dba617; }
        .ms-st-n.is-gallery { border-left-color: #8a4fd1; } .ms-st-n.is-gallery-reserved { border-left-color: #e26f12; }
        .ms-st-n.is-sold { border-left-color: #2271b1; } .ms-st-n.is-private { border-left-color: #d63638; }
        .ms-st-n.is-draft { border-left-color: #8c8f94; color: #50575e; }
        .ms-st-n.is-warn { background: #fcf0f1; border-left-color: #d63638; color: #8a2424; }
        .ms-st-list { margin: 0; padding-left: 0; list-style: none; }
        .ms-st-list li { margin: 4px 0; }
        .ms-st-list .ms-st-n { padding: 1px 8px; }
        .ms-st-note { margin: 6px 0 0; color: #50575e; }
        .ms-st-new { margin: 0; }
        .ms-st-new li { display: flex; align-items: center; gap: 8px; margin: 0 0 6px; }
        .ms-st-new img, .ms-st-noimg { width: 36px; height: 36px; object-fit: cover; border-radius: 3px; flex: none; background: #f0f0f1; }
        .ms-st-num { font-weight: 700; min-width: 34px; font-variant-numeric: tabular-nums; }
        .ms-st-new a { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .ms-st-dot { font-size: 11px; padding: 0 6px; border-radius: 8px; background: #f0f0f1; color: #50575e; white-space: nowrap; }
        .ms-st-dot.is-available { background: #edfaef; color: #00631f; } .ms-st-dot.is-reserved { background: #fcf9e8; color: #6e4e00; }
        .ms-st-dot.is-sold { background: #f0f6fc; color: #135e96; } .ms-st-dot.is-private { background: #fcf0f1; color: #8a2424; }
        .ms-st-dot.is-gallery { background: #f5effc; color: #5b2a99; } .ms-st-dot.is-gallery-reserved { background: #fdf1e7; color: #8a3f00; }
        .ms-st-date { color: #8c8f94; font-size: 12px; white-space: nowrap; }
        .ms-st-foot { margin: 10px 0 0; color: #8c8f94; font-size: 12px; }
    </style>
    <?php
}

// The counts change when artworks or orders do: count again next time
foreach ( [ 'save_post_post', 'save_post_product', 'woocommerce_new_order', 'woocommerce_order_status_changed' ] as $hook ) {
    add_action( $hook, function () { delete_transient( 'ms_site_status' ); } );
}

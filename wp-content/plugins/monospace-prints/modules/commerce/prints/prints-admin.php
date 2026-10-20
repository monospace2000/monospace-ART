<?php
/**
 * Prints (wp-admin menu) - the start of the Printful <-> WooCommerce control center (owner's wish,
 * 2026-10-15: one place, on the site, through the API).
 *
 * "New prints": artworks missing a print (none, or only unframed / framed), newest first - tick several, one click queues them all
 * (print-jobs.php: products created now, Printful linking + mockups in the background). Defaults
 * (sizes, frames, prices) at the top - "Save as default prices" stores the prices (option
 * monospace_print_default_prices, read by monospace_print_templates()); sizes an artwork's image is too small for (< 100 dpi)
 * are left out for that artwork. Running / failed jobs are listed above with their status. Watermarked images
 * (2026-10-17): a clean copy is used; with none the artwork can't be ticked (monospace_prints_file_state()).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
    add_menu_page( 'Prints', 'Prints', 'edit_products', 'monospace-prints', 'monospace_prints_admin_page', 'dashicons-format-gallery', 56 );
    add_submenu_page( 'monospace-prints', 'New prints', 'New prints', 'edit_products', 'monospace-prints', 'monospace_prints_admin_page' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'toplevel_page_monospace-prints' ) return;
    wp_enqueue_script( 'jquery' );
    wp_add_inline_script( 'jquery', monospace_prints_request_script() . monospace_prints_admin_script() );
} );

/** Artworks (posts with an original product) and their prints / job state, for the list */
function monospace_prints_admin_rows( $args ) {
    $q = [
        'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 40, 'paged' => $args['paged'],
        'orderby' => 'date', 'order' => 'DESC', 'fields' => 'ids',
        // Missing at least one print type (the queue only creates the missing one)
        'meta_query' => [ 'relation' => 'AND',
            [ 'key' => '_qp_product_id', 'compare' => 'EXISTS' ],
            [ 'relation' => 'OR',
                [ 'key' => '_print_product_id', 'compare' => 'NOT EXISTS' ], [ 'key' => '_print_product_id', 'value' => '' ],
                [ 'key' => '_framed_print_product_id', 'compare' => 'NOT EXISTS' ], [ 'key' => '_framed_print_product_id', 'value' => '' ] ],
        ],
    ];
    if ( $args['hide_minis'] ) $q['category__not_in'] = array_filter( [ (int) ( get_category_by_slug( 'miniature' )->term_id ?? 0 ) ] );
    if ( $args['available'] && function_exists( 'monospace_available_post_ids' ) ) $q['post__in'] = monospace_available_post_ids() ?: [ 0 ];
    return new WP_Query( $q );
}

function monospace_prints_admin_page() {
    if ( ! monospace_printful_ready() ) { echo '<div class="wrap"><h1>Prints</h1><p>The Printful token is missing (option <code>monospace_printful_token</code>).</p></div>'; return; }
    $args = [ 'paged' => max( 1, (int) ( $_GET['paged'] ?? 1 ) ), 'hide_minis' => ! isset( $_GET['minis'] ), 'available' => ! empty( $_GET['available'] ) ];
    $query = monospace_prints_admin_rows( $args );

    // Jobs running or stopped, anywhere
    $jobs = get_posts( [ 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 50, 'fields' => 'ids', 'meta_key' => '_ms_print_job' ] );
    $url  = function ( $extra ) use ( $args ) {
        return add_query_arg( array_merge( [ 'page' => 'monospace-prints', 'available' => $args['available'] ? 1 : null, 'minis' => $args['hide_minis'] ? null : 1 ], $extra ), admin_url( 'admin.php' ) );
    };
    ?>
    <div class="wrap" id="ms-prints-admin" data-nonce="<?php echo esc_attr( wp_create_nonce( 'monospace_prints' ) ); ?>">
        <h1>New prints</h1>
        <p class="description" style="max-width:820px">Tick the artworks to make prints of and press <strong>Create prints for selected</strong>. The products are created right away; Printful linking and mockups run in the background - you can leave this page. Each print goes on sale as soon as Printful has linked it.
            Sizes an artwork's image is too small for (under 100 dpi) are left out for that artwork. Needs “Import not synced products from WooCommerce” on in Printful.</p>

        <?php if ( $jobs ) : ?>
            <h2>In progress</h2>
            <table class="widefat striped ms-prints-jobs" style="max-width:1000px">
                <tbody>
                <?php foreach ( $jobs as $id ) : $st = monospace_prints_status( $id ); ?>
                    <tr data-post="<?php echo (int) $id; ?>">
                        <td style="width:60px"><?php echo get_the_post_thumbnail( $id, [ 50, 50 ] ); ?></td>
                        <td><a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></td>
                        <td class="ms-prints-row-status" data-state="<?php echo esc_attr( $st['state'] ); ?>"><?php echo esc_html( $st['text'] ); ?></td>
                        <td style="width:150px"><?php if ( $st['state'] === 'error' ) echo '<button type="button" class="button ms-prints-retry">Retry</button> '; ?>
                            <button type="button" class="button-link button-link-delete ms-prints-cancel">Cancel</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php // Admin UI style (2026-10-20): the defaults are set once - a folded panel
        $variant_count = array_map( fn( $tpl ) => count( $tpl['variants'] ), monospace_print_templates() ); ?>
        <div class="lm-areas" style="max-width:none">
        <details class="lm-panel ms-prints-defaults-panel" style="margin:18px 0 6px">
            <summary><span class="lm-t">Default sizes &amp; prices</span>
                <span class="lm-chip is-on"><?php echo esc_html( implode( ' · ', array_map( fn( $type, $n ) => monospace_print_templates()[ $type ]['label'] . ': ' . $n, array_keys( $variant_count ), $variant_count ) ) ); ?></span>
                <span class="lm-sub">What new prints start with - here, in an artwork's Prints box and when adding sizes. Untick a size or a whole type to leave it out of this batch.</span></summary>
            <div class="lm-body">
        <div class="ms-prints-defaults">
            <?php foreach ( monospace_print_templates() as $type => $tpl ) : ?>
                <div class="ms-prints-type" data-type="<?php echo esc_attr( $type ); ?>">
                    <h4><label><input type="checkbox" class="ms-prints-type-on" checked> <?php echo esc_html( $tpl['label'] ); ?></label></h4>
                    <?php echo monospace_prints_options_table( $type ); ?>
                </div>
            <?php endforeach; ?>
            <p style="flex-basis:100%;margin-top:0">
                <button type="button" class="button" id="ms-prints-save-prices">Save as default prices</button>
                <span class="description">The prices above become the starting prices for new prints - here, in an artwork's Prints box and when adding sizes. Prints that already exist keep theirs (All prints &gt; Change prices).</span>
                <span class="ms-prints-save-prices-note" style="display:none;margin-left:6px"></span>
            </p>
        </div>
            </div>
        </details>
        </div>
        <?php if ( function_exists( 'monospace_lm_area_assets' ) ) monospace_lm_area_assets(); // the admin UI kit (Core) ?>

        <h2>Artworks missing prints</h2>
        <p class="subsubsub" style="float:none">
            <a href="<?php echo esc_url( $url( [ 'available' => null, 'paged' => null ] ) ); ?>" <?php if ( ! $args['available'] ) echo 'class="current"'; ?>>All</a> |
            <a href="<?php echo esc_url( $url( [ 'available' => 1, 'paged' => null ] ) ); ?>" <?php if ( $args['available'] ) echo 'class="current"'; ?>>Originals for sale</a> ·
            <a href="<?php echo esc_url( $url( [ 'minis' => $args['hide_minis'] ? 1 : null, 'paged' => null ] ) ); ?>"><?php echo $args['hide_minis'] ? 'Show miniatures' : 'Hide miniatures'; ?></a>
            · <?php echo (int) $query->found_posts; ?> artworks
        </p>
        <table class="widefat striped ms-prints-list" style="max-width:1100px">
            <thead><tr><td class="check-column"><input type="checkbox" class="ms-prints-all"></td><th></th><th>Artwork</th><th>Original</th><th>Image</th><th>Has</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ( $query->posts as $id ) :
                $img = monospace_prints_file_for( $id ); // its print file (else the featured image)
                $wm  = $img ? monospace_prints_file_state( $id ) : 'clean'; // watermark check, create-prints.php
                $px  = $img ? monospace_print_image_size( $img ) : [ 0, 0 ];
                $dpi = $px[0] ? monospace_print_dpi( $px, [ 11, 14 ] ) : 0;
                $art = function_exists( 'monospace_seo_artwork' ) ? monospace_seo_artwork( $id ) : null;
                $st  = monospace_prints_status( $id );
                $busy = in_array( $st['state'], [ 'waiting', 'linking', 'mockups', 'error' ], true );
                $unlinked = monospace_prints_unlinked( $id ); ?>
                <tr data-post="<?php echo (int) $id; ?>">
                    <th class="check-column"><?php if ( $img && ! $busy && ! $unlinked && $wm !== 'blocked' ) : ?><input type="checkbox" class="ms-prints-pick"><?php endif; ?></th>
                    <td style="width:60px"><?php echo get_the_post_thumbnail( $id, [ 50, 50 ] ); ?></td>
                    <td><a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a>
                        <?php if ( $art ) echo '<br><span class="description">' . esc_html( monospace_seo_artwork_kind( $art ) . ( $art['size'] ? ', ' . $art['size'] : '' ) ) . '</span>'; ?></td>
                    <td><?php echo $art ? esc_html( $art['purchasable'] ? 'for sale' : ( $art['status'] ?: 'not for sale' ) ) : ''; ?></td>
                    <td><?php if ( $img ) : ?><?php echo (int) $px[0]; ?> × <?php echo (int) $px[1]; ?><br>
                            <?php [ $rating, $colour ] = monospace_print_dpi_rating( $dpi ); ?>
                            <span style="color:<?php echo esc_attr( $colour ); ?>">11×14: <?php echo (int) $dpi; ?> dpi - <?php echo esc_html( $rating ); ?></span>
                            <?php if ( $wm === 'watermarked' ) echo '<br><span style="color:#9a6700">watermarked - a clean copy is used</span>'; ?>
                        <?php else : ?><span style="color:#b32d2e">no featured image</span><?php endif; ?></td>
                    <td><?php
                        $has = array_filter( [ get_post_meta( $id, '_print_product_id', true ) ? 'unframed' : '', get_post_meta( $id, '_framed_print_product_id', true ) ? 'framed' : '' ] );
                        echo $has ? esc_html( implode( ' + ', $has ) ) . ' <span class="description">(only the missing one is made)</span>' : '<span class="description">none</span>'; ?></td>
                    <td class="ms-prints-row-status" data-state="<?php echo esc_attr( $st['state'] ); ?>"><?php
                        if ( $wm === 'blocked' ) echo '<span style="color:#b32d2e;font-weight:600">Image is watermarked, no clean copy - add a clean photo (artwork’s Prints box: Replace print file)</span>';
                        else echo $unlinked ? 'Has an unlinked print - use the artwork’s Prints box' : esc_html( $st['text'] ); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ( ! $query->posts ) echo '<tr><td colspan="7">No artworks missing prints here.</td></tr>'; ?>
            </tbody>
        </table>
        <p>
            <button type="button" class="button button-primary button-large" id="ms-prints-batch">Create prints for selected</button>
        </p>
        <div class="notice notice-info inline ms-prints-batch-note" style="display:none;max-width:1060px"><p></p></div>
        <?php
        $pages = (int) $query->max_num_pages;
        if ( $pages > 1 ) {
            echo '<p>' . paginate_links( [ 'base' => $url( [ 'paged' => '%#%' ] ), 'format' => '', 'current' => $args['paged'], 'total' => $pages ] ) . '</p>';
        }
        ?>
    </div>
    <style>
        #ms-prints-admin .ms-prints-defaults { display: flex; gap: 30px; flex-wrap: wrap; }
        #ms-prints-admin .ms-prints-defaults .ms-prints-type { min-width: 320px; }
        #ms-prints-admin .ms-prints-table { max-width: 420px; margin-bottom: 6px; }
        #ms-prints-admin td, #ms-prints-admin th { vertical-align: middle; }
        #ms-prints-admin [data-state="error"] { color: #b32d2e; font-weight: 600; }
        #ms-prints-admin [data-state="done"], #ms-prints-admin [data-state="mockups"] { color: #1a7f37; }
        #ms-prints-admin .ms-prints-type.is-off .ms-prints-table { opacity: .4; }
    </style>
    <?php
}

function monospace_prints_admin_script() {
    return <<<'JS'
jQuery(function ($) {
    var $page = $('#ms-prints-admin');
    if (!$page.length) return;
    var nonce = $page.data('nonce'), timer = null;
    function call(op, data) {
        return $.post(window.ajaxurl || '/wp-admin/admin-ajax.php', $.extend({ action: 'monospace_prints', op: op, _ajax_nonce: nonce }, data));
    }
    function setStatus(id, st) {
        $page.find('tr[data-post="' + id + '"] .ms-prints-row-status').attr('data-state', st.state).text(st.text);
    }
    function poll() {
        var ids = $page.find('.ms-prints-row-status').filter(function () { return ['waiting', 'linking', 'mockups'].indexOf($(this).attr('data-state')) > -1; })
            .closest('tr').map(function () { return $(this).data('post'); }).get();
        if (!ids.length) return;
        call('status', { post_ids: ids }).done(function (r) {
            if (r.success) Object.keys(r.data).forEach(function (id) { setStatus(id, r.data[id]); });
            timer = setTimeout(poll, 15000);
        });
    }
    timer = setTimeout(poll, 5000);

    $page.on('change', '.ms-prints-all', function () { $page.find('.ms-prints-pick').prop('checked', this.checked); });
    $page.on('change', '.ms-prints-type-on', function () { $(this).closest('.ms-prints-type').toggleClass('is-off', !this.checked); });

    $('#ms-prints-batch').on('click', function () {
        var $rows = $page.find('.ms-prints-pick:checked').closest('tr');
        if (!$rows.length) { alert('Tick the artworks to make prints of.'); return; }
        var request = window.msPrintsRequest($page.find('.ms-prints-defaults'));
        $page.find('.ms-prints-defaults .ms-prints-type').each(function () { // print types switched off
            if (!$(this).find('.ms-prints-type-on').is(':checked')) delete request[$(this).data('type')];
        });
        if (!Object.keys(request).length) { alert('Choose at least one size or frame in the defaults.'); return; }
        request.min_dpi = 100;
        if (!confirm('Create prints for ' + $rows.length + ' artwork(s)? They go on sale as soon as Printful has linked them.')) return;

        var $btn = $(this).prop('disabled', true).text('Creating…'), $note = $page.find('.ms-prints-batch-note'), done = 0, failed = 0;
        var note = function (cls, text) { $note.removeClass('notice-info notice-success notice-error').addClass(cls).show().find('p').text(text); };
        var queue = $rows.map(function () { return $(this).data('post'); }).get();
        (function next() {
            if (!queue.length) {
                $btn.prop('disabled', false).text('Create prints for selected');
                note(failed ? 'notice-error' : 'notice-success', (done ? '✓ ' + done + ' artwork' + (done > 1 ? 's' : '') + ' queued - running in the background. Each goes on sale as soon as Printful has linked it; the Status column updates by itself, and “Prints: running” shows in the admin bar.' : '') + (failed ? ' ' + failed + ' could not be queued - see their Status.' : ''));
                clearTimeout(timer); timer = setTimeout(poll, 5000); return;
            }
            var id = queue.shift();
            note('notice-info', 'Creating products… ' + (done + failed + 1) + ' of ' + $rows.length + ' - keep this page open until this says done.');
            setStatus(id, { state: 'waiting', text: 'Creating products…' });
            call('queue', { post_id: id, request: JSON.stringify(request) }).done(function (r) {
                if (r.success) { done++; setStatus(id, r.data); $page.find('tr[data-post="' + id + '"] .ms-prints-pick').remove(); }
                else { failed++; setStatus(id, { state: 'error', text: r.data || 'Error' }); }
            }).fail(function (x) { failed++; setStatus(id, { state: 'error', text: 'Request failed (' + x.status + ')' }); }).always(next);
        })();
    });

    // Every size / frame's price in the defaults, ticked or not
    $('#ms-prints-save-prices').on('click', function () {
        var prices = {}, $btn = $(this), $note = $page.find('.ms-prints-save-prices-note');
        $page.find('.ms-prints-defaults .ms-prints-table').each(function () {
            var type = $(this).data('type');
            $(this).find('tbody tr').each(function () {
                var key = $(this).find('.ms-prints-on').data('key'), price = $(this).find('.ms-prints-price').val();
                if (key && price > 0) (prices[type] = prices[type] || {})[key] = price;
            });
        });
        $btn.prop('disabled', true);
        call('save_prices', { prices: JSON.stringify(prices) }).done(function (r) {
            $note.css('color', r.success ? '#1a7f37' : '#b32d2e').text(r.success ? '✓ ' + r.data : (r.data || 'Error')).show();
        }).fail(function (x) { $note.css('color', '#b32d2e').text('Request failed (' + x.status + ')').show(); })
          .always(function () { $btn.prop('disabled', false); });
    });

    $page.on('click', '.ms-prints-retry', function () {
        var id = $(this).closest('tr').data('post');
        call('retry', { post_id: id }).done(function (r) { if (r.success) { setStatus(id, r.data); clearTimeout(timer); timer = setTimeout(poll, 5000); } });
    });
    $page.on('click', '.ms-prints-cancel', function () {
        var id = $(this).closest('tr').data('post');
        if (confirm('Cancel this print job? Products already created stay in WooCommerce; links already made stay.')) call('cancel', { post_id: id }).done(function () { location.reload(); });
    });
});
JS;
}

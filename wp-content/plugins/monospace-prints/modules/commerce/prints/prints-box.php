<?php
/**
 * "Prints (Printful)" box on an artwork's edit screen (main column - in the block editor that's the
 * collapsible "Meta Boxes" panel; Quick Product gets a "Create / link prints" link that opens it).
 * Queues the artwork's prints as a background job (print-jobs.php) and shows its progress; the
 * size / frame / price table is shared with the Prints page (prints-admin.php).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Size / frame / price rows of one print type. $px = the image's pixel size ([0,0] = unknown: no
 * dpi column, as on the Prints page's defaults). Inputs carry data-key; the script reads them.
 */
function monospace_prints_options_table( $type, $px = [ 0, 0 ] ) {
    $tpl = monospace_print_templates()[ $type ];
    $known = $px[0] && $px[1];
    ob_start(); ?>
    <table class="widefat striped ms-prints-table" data-type="<?php echo esc_attr( $type ); ?>">
        <thead><tr><th style="width:24px"></th><th><?php echo $type === 'framed' ? 'Frame, size' : 'Size'; ?></th><th>Price</th><?php if ( $known ) echo '<th>Print quality (as Printful rates it)</th>'; ?></tr></thead>
        <tbody>
        <?php foreach ( $tpl['variants'] as $key => $v ) :
            $dpi  = $known ? monospace_print_dpi( $px, $v['inches'] ) : 0;
            $crop = $known ? monospace_print_crop( $px, $v['inches'] ) : 0;
            $size = $known && $px[0] > $px[1] ? $v['inches'][1] . '×' . $v['inches'][0] : $v['inches'][0] . '×' . $v['inches'][1];
            $label = ( $type === 'framed' ? preg_replace( '/,.*/', '', $v['label'] ) . ', ' : '' ) . $size; ?>
            <tr>
                <td><input type="checkbox" class="ms-prints-on" data-key="<?php echo esc_attr( $key ); ?>" <?php checked( ! $known || $dpi >= 100 ); ?>></td>
                <td><?php echo esc_html( $label ); ?>″</td>
                <td>$ <input type="number" class="ms-prints-price small-text" min="1" step="1" value="<?php echo esc_attr( $v['price'] ); ?>"></td>
                <?php if ( $known ) : ?>
                    <?php [ $rating, $color ] = monospace_print_dpi_rating( $dpi ); ?>
                    <td><span style="color:<?php echo esc_attr( $color ); ?>"><?php echo (int) $dpi; ?> dpi - <?php echo esc_html( $rating ); ?></span>
                        <?php if ( $crop >= 0.02 ) : ?><span style="color:#666" title="The painting's proportions differ from the paper's: the print fills the paper, and this much of the image is trimmed off the edges (half at each end)."> · <?php echo round( $crop * 100 ); ?>% trimmed off the edges</span><?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

add_action( 'add_meta_boxes_post', function () {
    add_meta_box( 'monospace-prints', 'Prints (Printful)', 'monospace_prints_box', 'post', 'normal', 'default' );
} );

function monospace_prints_box( $post ) {
    if ( ! monospace_printful_ready() ) { echo '<p>The Printful token is missing (option <code>monospace_printful_token</code>, see CLAUDE.md).</p>'; return; }
    $image_id = monospace_prints_file_for( $post->ID );
    if ( ! $image_id ) { echo '<p>Set a featured image first - prints are made from it.</p>'; return; }
    // Watermark check (create-prints.php): print actions use a clean copy, or refuse when there's none
    $wm_state = monospace_prints_file_state( $post->ID );
    $own_file = (int) get_post_meta( $post->ID, '_ms_print_file', true ) === $image_id;

    $templates = monospace_print_templates();
    $px        = monospace_print_image_size( $image_id );
    $status    = monospace_prints_status( $post->ID );
    $running   = in_array( $status['state'], [ 'waiting', 'linking', 'mockups', 'error' ], true );
    $unlinked  = monospace_prints_unlinked( $post->ID );
    $file_name = basename( wp_get_original_image_path( $image_id ) ?: '' );
    // "Replace print file" changes existing prints' file - only once there are some (as before)
    $has_prints = false;
    foreach ( $templates as $tpl ) if ( get_post_meta( $post->ID, $tpl['meta_key'], true ) ) $has_prints = true;
    $chip      = fn( $state, $text ) => '<span class="lm-chip is-' . esc_attr( $state ) . '">' . esc_html( $text ) . '</span>';
    // Admin UI style (2026-10-20): the job status on top, then a panel for the print file and each print type
    ?>
    <div id="ms-prints" class="lm-areas" data-post="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'monospace_prints' ) ); ?>" data-state="<?php echo esc_attr( $status['state'] ); ?>">
        <input type="hidden" name="ms_prints_loaded_at" value="<?php echo (int) time(); ?>">

        <div class="ms-prints-status" <?php if ( ! $running && $status['state'] !== 'done' ) echo 'style="display:none"'; ?>>
            <p><strong class="ms-prints-status-text"><?php echo esc_html( $status['text'] ); ?></strong>
                <button type="button" class="button ms-prints-retry" <?php if ( $status['state'] !== 'error' ) echo 'style="display:none"'; ?>>Retry</button>
                <button type="button" class="button-link button-link-delete ms-prints-cancel" style="margin-left:10px;<?php if ( ! $running ) echo 'display:none'; ?>">Cancel job</button></p>
            <details class="ms-prints-logbox"<?php echo $status['state'] === 'error' ? ' open' : ''; ?>><summary>Steps</summary>
                <ol class="ms-prints-log"><?php foreach ( $status['log'] as $l ) echo '<li>' . esc_html( wp_date( 'H:i', $l[0] ) . ' - ' . $l[1] ) . '</li>'; ?></ol>
            </details>
        </div>

        <div class="lm-panels">
            <!-- PRINT FILE -->
            <details class="lm-panel" data-panel="file"<?php echo $wm_state === 'blocked' ? ' open' : ''; ?>>
                <summary><span class="lm-t">Print file</span>
                    <?php echo $wm_state === 'blocked' ? $chip( 'warn', 'Watermarked - needs a clean photo' ) : $chip( 'on', (int) $px[0] . ' × ' . (int) $px[1] . ' px' ); ?>
                    <span class="lm-sub"><?php echo esc_html( $file_name . ' · ' . ( $px[1] > $px[0] ? 'portrait' : 'landscape' ) . ' · ' . ( $own_file ? 'this artwork’s own print file' : 'the featured image’s original upload' ) . ( $wm_state === 'watermarked' ? ' · watermarked, a clean copy is used' : '' ) ); ?></span></summary>
                <div class="lm-body">
                    <?php if ( $wm_state === 'blocked' ) : ?>
                        <p style="color:#b32d2e;font-weight:600">The print file is watermarked and there is no clean copy on the server. Use “Replace print file” with an unwatermarked photo - until then no prints can be made or changed.</p>
                    <?php elseif ( $wm_state === 'watermarked' ) : ?>
                        <p style="color:#9a6700">The image is watermarked - the prints use a clean copy of it (made automatically).</p>
                    <?php endif; ?>
                    <?php if ( ! $running && $has_prints ) : ?>
                    <div class="ms-prints-tools">
                        <p><button type="button" class="button ms-prints-replace">Replace print file…</button>
                            <span class="description">e.g. a higher-resolution scan - every variant in Printful gets it, and the mockups follow.</span></p>
                        <div class="ms-prints-preview" style="display:none"></div>
                    </div>
                    <?php else : ?>
                    <p class="description">Prints are made from this file. Once they exist, “Replace print file” changes it for all of them.</p>
                    <?php endif; ?>
                </div>
            </details>

            <?php if ( ! $running ) : $todo = 0; ?>
                <?php foreach ( $templates as $type => $tpl ) :
                    $linked  = (int) get_post_meta( $post->ID, $tpl['meta_key'], true );
                    $product = $linked ? wc_get_product( $linked ) : null;
                    $hidden  = $product && function_exists( 'monospace_prints_is_hidden' ) && monospace_prints_is_hidden( $post->ID, $type );
                    if ( $product ) { $state = $hidden ? 'off' : 'on'; $text = 'Linked · ' . count( $product->get_children() ) . ( count( $product->get_children() ) === 1 ? ' size' : ' sizes' ) . ( $hidden ? ' · hidden' : '' ); }
                    elseif ( ! empty( $unlinked[ $type ] ) ) { $todo++; $state = 'empty'; $text = 'Ready to link'; }
                    else { $todo++; $state = 'empty'; $text = 'None yet'; } ?>
                    <details class="lm-panel" data-panel="<?php echo esc_attr( $type ); ?>"<?php echo $product ? '' : ' open'; ?>>
                        <summary><span class="lm-t"><?php echo esc_html( $tpl['label'] ); ?></span> <?php echo $chip( $state, $text ); ?>
                            <?php if ( $product ) : ?><span class="lm-sub">#<?php echo (int) $linked; ?> · <?php echo esc_html( $product->get_sku() ); ?></span><?php endif; ?></summary>
                        <div class="lm-body">
                            <div class="ms-prints-type" data-type="<?php echo esc_attr( $type ); ?>">
                                <?php if ( $product ) : ?>
                                    <p><a href="<?php echo esc_url( get_edit_post_link( $linked ) ); ?>">#<?php echo (int) $linked; ?> <?php echo esc_html( $product->get_name() ); ?></a>,
                                        <?php echo count( $product->get_children() ); ?> variants, <?php echo esc_html( $product->get_sku() ); ?>.</p>
                                <?php elseif ( ! empty( $unlinked[ $type ] ) ) : $pid = $unlinked[ $type ][0]; $p = wc_get_product( $pid ); ?>
                                    <label><input type="checkbox" class="ms-prints-existing" value="<?php echo (int) $pid; ?>" checked>
                                        Use the existing, not yet linked <a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>">#<?php echo (int) $pid; ?></a> (<?php echo count( $p->get_children() ); ?> variants)</label>
                                <?php else : ?>
                                    <?php echo monospace_prints_options_table( $type, $px ); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </details>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ( ! $running && $todo ) : ?>
            <p class="ms-prints-go-row"><button type="button" class="button button-primary" id="ms-prints-go">Create prints</button>
                <span class="description">Runs in the background: you can close this page or go on to the next painting.</span></p>
        <?php endif; ?>
    </div>
    <style>
        #ms-prints.lm-areas { max-width: none; }
        #ms-prints .lm-panels { max-width: 760px; }
        #ms-prints .ms-prints-table { max-width: 640px; margin-bottom: 6px; }
        #ms-prints .ms-prints-table td { vertical-align: middle; }
        #ms-prints .ms-prints-logbox > summary { cursor: pointer; color: #646970; margin-bottom: 6px; }
        #ms-prints .ms-prints-log { background: #f6f7f7; padding: 8px 8px 8px 28px; max-width: 640px; max-height: 220px; overflow: auto; }
        #ms-prints .ms-prints-status[data-error] strong { color: #b32d2e; }
        #ms-prints .ms-prints-go-row { margin-top: 12px; }
    </style>
    <?php
    if ( function_exists( 'monospace_lm_area_assets' ) ) monospace_lm_area_assets(); // the admin UI kit (Core)
}

/** What the prints UI sends for one artwork: read from a container with .ms-prints-type blocks */
function monospace_prints_request_script() {
    return <<<'JS'
window.msPrintsRequest = function ($scope) {
    var $ = jQuery; // WordPress loads jQuery without the $ shortcut
    var request = {};
    $scope.find('.ms-prints-type').each(function () {
        var type = $(this).data('type'), $ex = $(this).find('.ms-prints-existing');
        if ($ex.length) { if ($ex.is(':checked')) request[type] = { existing: $ex.val() }; return; }
        var variants = {};
        $(this).find('tbody tr').each(function () {
            var $on = $(this).find('.ms-prints-on');
            if ($on.is(':checked')) variants[$on.data('key')] = $(this).find('.ms-prints-price').val();
        });
        if (Object.keys(variants).length) request[type] = { variants: variants };
    });
    return request;
};
JS;
}

/** "Replace print file": pick an image, show each size's quality with it, confirm - shared by the box and All prints */
function monospace_prints_file_script() {
    return <<<'JS'
window.msPrintsReplaceFile = function (post, nonce, $preview, done) {
    var $ = jQuery;
    var frame = wp.media({ title: 'New print file', library: { type: 'image' }, multiple: false, button: { text: 'Check this file' } });
    frame.on('select', function () {
        var file = frame.state().get('selection').first().get('id');
        var call = function (op, data) { return $.post(window.ajaxurl || '/wp-admin/admin-ajax.php', $.extend({ action: 'monospace_prints', op: op, post_id: post, _ajax_nonce: nonce }, data)); };
        call('preview_file', { file: file }).done(function (r) {
            if (!r.success) { alert(r.data || 'Error'); return; }
            var d = r.data, rows = d.rows.map(function (x) {
                var color = x.dpi >= 100 ? '#1a7f37' : (x.dpi >= 75 ? '#9a6700' : '#b32d2e');
                return '<tr><td>' + x.size + '″</td><td style="color:' + color + '">' + x.dpi + ' dpi - ' + x.rating + '</td><td>' + (x.trim >= 2 ? x.trim + '% trimmed off the edges' : '') + '</td></tr>';
            }).join('');
            $preview.show().html('<p><strong>' + $('<i>').text(d.name).html() + '</strong>, ' + d.px[0] + ' × ' + d.px[1] + ' px:</p>' +
                '<table class="widefat striped" style="max-width:520px"><tbody>' + rows + '</tbody></table>' +
                '<p><button type="button" class="button button-primary ms-pf-use">Use this file</button> <button type="button" class="button-link ms-pf-cancel">Cancel</button></p>');
            $preview.find('.ms-pf-cancel').on('click', function () { $preview.hide().empty(); });
            $preview.find('.ms-pf-use').on('click', function () {
                $(this).prop('disabled', true);
                call('refresh', { file: file }).done(function (r) {
                    if (!r.success) { alert(r.data || 'Error'); return; }
                    $preview.hide().empty(); done(r.data);
                });
            });
        });
    });
    frame.open();
};
JS;
}

// The box's script, enqueued after jQuery (the block editor loads jQuery late - a <script> in the box ran too early)
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || get_current_screen()->post_type !== 'post' ) return;
    wp_enqueue_script( 'jquery' );
    wp_enqueue_media();
    wp_add_inline_script( 'jquery', monospace_prints_request_script() . monospace_prints_file_script() . monospace_prints_box_script() );
} );

function monospace_prints_box_script() {
    return <<<'JS'
jQuery(function ($) {
    if (!$('#ms-prints').length) return;
    var $box = $('#ms-prints'), post = $box.data('post'), nonce = $box.data('nonce'), timer = null;

    // Starts from Quick Product (sidebar): a link under its print ID fields that opens this box
    var $qpField = $('[name=qp_framed_print_product_id]').closest('.qp-field');
    if ($qpField.length) {
        $('<p style="margin:6px 0 0"><a href="#monospace-prints">Create / link prints ↓</a></p>').insertAfter($qpField)
            .find('a').on('click', function (e) {
                e.preventDefault();
                // The block editor keeps meta boxes in a collapsible "Meta Boxes" panel, closed by default
                var toggle = document.querySelector('.edit-post-meta-boxes-main button[aria-expanded="false"]');
                if (toggle) toggle.click();
                setTimeout(function () { var b = document.getElementById('monospace-prints'); if (b) b.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, toggle ? 350 : 0);
            });
    }

    function call(op, data) {
        return $.post(window.ajaxurl || '/wp-admin/admin-ajax.php', $.extend({ action: 'monospace_prints', op: op, post_id: post, _ajax_nonce: nonce }, data || {}));
    }
    function show(st) {
        var $st = $box.find('.ms-prints-status').show();
        $st.find('.ms-prints-status-text').text(st.text || '');
        if (st.state === 'error') $st.attr('data-error', 1); else $st.removeAttr('data-error');
        $st.find('.ms-prints-retry').toggle(st.state === 'error');
        $st.find('.ms-prints-cancel').toggle(['waiting', 'linking', 'mockups', 'error'].indexOf(st.state) > -1);
        var $log = $st.find('.ms-prints-log').empty();
        (st.log || []).forEach(function (l) { $log.append($('<li>').text(new Date(l[0] * 1000).toTimeString().slice(0, 5) + ' - ' + l[1])); });
        clearTimeout(timer);
        if (['waiting', 'linking', 'mockups'].indexOf(st.state) > -1) timer = setTimeout(poll, 15000);
        // Keep Quick Product's fields in step with the links the job made (saving the post re-saves them)
        if (st.ids) {
            if (st.ids.unframed && !$('[name=qp_print_product_id]').val()) $('[name=qp_print_product_id]').val(st.ids.unframed);
            if (st.ids.framed && !$('[name=qp_framed_print_product_id]').val()) $('[name=qp_framed_print_product_id]').val(st.ids.framed);
        }
    }
    function poll() { call('status').done(function (r) { if (r.success && r.data[post]) show(r.data[post]); }); }

    if (['waiting', 'linking', 'mockups'].indexOf($box.data('state')) > -1) timer = setTimeout(poll, 5000);

    $(document).on('click', '#ms-prints-go', function () {
        var request = window.msPrintsRequest($box);
        if (!Object.keys(request).length) { alert('Choose at least one size or frame.'); return; }
        if (!confirm('Create these prints? They go on sale as soon as Printful has linked them.')) return;
        var $btn = $(this).prop('disabled', true).text('Creating…');
        call('queue', { request: JSON.stringify(request) }).done(function (r) {
            if (!r.success) { alert(r.data || 'Error'); $btn.prop('disabled', false).text('Create prints'); return; }
            $box.find('.ms-prints-type').closest('.lm-panel').remove(); $box.find('.ms-prints-go-row').remove();
            show(r.data);
        }).fail(function (x) { alert('Request failed (' + x.status + ')'); $btn.prop('disabled', false).text('Create prints'); });
    });
    $(document).on('click', '#ms-prints .ms-prints-replace', function () {
        window.msPrintsReplaceFile(post, nonce, $box.find('.ms-prints-preview'), function (st) { $box.find('.ms-prints-tools').remove(); show(st); });
    });
    $(document).on('click', '#ms-prints .ms-prints-retry', function () { call('retry').done(function (r) { if (r.success) show(r.data); }); });
    $(document).on('click', '#ms-prints .ms-prints-cancel', function () {
        if (confirm('Cancel this print job? Products already created stay in WooCommerce; links already made stay.')) call('cancel').done(function () { location.reload(); });
    });
});
JS;
}

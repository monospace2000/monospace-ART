<?php
/**
 * Artwork toolbar (2026-10-19, owner's wish) - quick edits in the black WordPress toolbar on an artwork's page.
 *
 * Logged in (edit_products) and looking at an artwork post on the site (a post with a linked product), the
 * toolbar shows:  #426  $ [350]  [Available ▾]   Unpublish
 * in the toolbar's own look (2026-10-19, owner's wish: native, no glass button): dark fields, and Publish /
 * Unpublish as a plain toolbar item (a link, like "Edit Post") - the site's button and input styles are kept out
 * (`all: revert` on the fields; no <button>). Availability in short words: Available | Reserved | Sold | Private |
 * Gallery | Gallery QR.
 *   - the SKU's number (how the owner identifies a piece; hover = the full SKU)
 *   - Price: saved on Enter or when the field is left
 *   - Availability: saved when picked (colored per status, as in the editors)
 *   - Publish / Unpublish (owner's words): Unpublish = the post becomes a Draft, off the site for visitors (asks
 *     first; the bar turns brown and says "Unpublished"), Publish = back on the site
 * Each change saves at once through Core's own saving (monospace_artwork_editor_save_row() - the same path as
 * the Quick Artwork Editor and the Artwork Editor), then clears that page from the cache; a ✓ / the error shows
 * beside it, and "reload" refreshes the page (the price card shows the new values after a reload).
 *
 * Needs Core (monospace_is_artwork, monospace_get_post_product_id, monospace_artwork_editor_save_row, QuickProduct\MetaBox). AJAX
 * monospace_artwork_toolbar ( post_id, field = price | availability | published, value ), nonce
 * monospace_artwork_toolbar.
 *
 * @package monospace-admin-tools
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The artwork on screen (front end, single post with a linked product) that this user may edit, or 0 */
function monospace_artwork_toolbar_post() {
    if ( is_admin() || ! is_singular( 'post' ) || ! current_user_can( 'edit_products' ) ) return 0;
    if ( ! function_exists( 'monospace_is_artwork' ) || ! function_exists( 'monospace_artwork_editor_save_row' ) ) return 0;
    $post_id = get_queried_object_id();
    return $post_id && current_user_can( 'edit_post', $post_id ) && monospace_is_artwork( $post_id ) ? $post_id : 0;
}

add_action( 'admin_bar_menu', function ( $bar ) {
    $post_id = monospace_artwork_toolbar_post();
    if ( ! $post_id ) return;
    $pid   = monospace_get_post_product_id( $post_id );
    $sku   = (string) get_post_meta( $pid, '_sku', true );
    $num   = monospace_sku_number( $sku );
    $price = (string) get_post_meta( $pid, '_regular_price', true );
    $stock = get_post_meta( $pid, '_stock', true );
    // As the Quick Artwork Editor reads it: no stock = sold, else the saved status
    $status = ( $stock === '' || (int) $stock <= 0 ) ? 'sold' : ( get_post_meta( $pid, 'painting_availability_status', true ) ?: 'available' );
    $published = get_post_status( $post_id ) === 'publish';
    // Short words for the toolbar (owner's choice) - the editors keep the full names
    $options = [ 'available' => 'Available', 'reserved' => 'Reserved', 'sold' => 'Sold', 'private' => 'Private', 'gallery' => 'Gallery', 'gallery-reserved' => 'Gallery QR' ];

    $sel = '';
    foreach ( $options as $value => $label ) $sel .= '<option value="' . esc_attr( $value ) . '"' . selected( $status, $value, false ) . '>' . esc_html( $label ) . '</option>';

    $html = '<span class="ms-atb" data-post="' . (int) $post_id . '">'
          . ( $num !== '' ? '<span class="ms-atb-num" title="' . esc_attr( $sku ) . '">#' . esc_html( $num ) . '</span>' : '' )
          . '<label class="ms-atb-price" title="Price - saved on Enter or when you leave the field">$<input type="text" inputmode="decimal" data-field="price" value="' . esc_attr( $price ) . '" data-was="' . esc_attr( $price ) . '" aria-label="Price" placeholder="' . ( function_exists( 'monospace_price_required' ) && ! monospace_price_required( $status ) ? '--' : '' ) . '"'
          . ' data-optional-when="' . esc_attr( function_exists( 'monospace_price_optional_statuses' ) ? implode( ' ', monospace_price_optional_statuses() ) : '' ) . '"></label>'
          . '<select data-field="availability" data-status="' . esc_attr( $status ) . '" data-was="' . esc_attr( $status ) . '" aria-label="Availability" title="Availability">' . $sel . '</select>'
          . '<span class="ms-atb-state">' . ( $published ? '' : 'Unpublished' ) . '</span>'
          . '<span class="ms-atb-msg" aria-live="polite"></span>'
          . '</span>';

    $bar->add_node( [ 'id' => 'ms-artwork-toolbar', 'title' => $html, 'meta' => [ 'class' => 'ms-atb-node' . ( $published ? '' : ' is-unpublished' ) ] ] );
    // Publish / Unpublish: a toolbar item of its own, like "Edit Post"
    $bar->add_node( [ 'id' => 'ms-artwork-publish', 'title' => $published ? 'Unpublish' : 'Publish', 'href' => '#',
                      'meta' => [ 'class' => 'ms-atb-pub-node' . ( $published ? '' : ' is-unpublished' ), 'title' => $published ? 'Unpublish: the post becomes a Draft - off the site for visitors' : 'Publish: back on the site' ] ] );
}, 85 );

add_action( 'wp_footer', function () {
    if ( ! monospace_artwork_toolbar_post() || ! is_admin_bar_showing() ) return; ?>
    <style>
        #wpadminbar .ms-atb-node > .ab-item { display: flex !important; align-items: center; height: 32px; padding: 0 8px !important; }
        #wpadminbar .ms-atb { display: inline-flex; align-items: center; gap: 8px; }
        #wpadminbar .ms-atb-num { font-weight: 600; color: #fff; font-variant-numeric: tabular-nums; }
        #wpadminbar .ms-atb-price { display: inline-flex; align-items: center; gap: 4px; color: #c3c4c7; cursor: text; }
        /* The toolbar's own field look; the site's input / select styles are reset first */
        #wpadminbar .ms-atb input, #wpadminbar .ms-atb select { all: revert; box-sizing: border-box; height: 24px; margin: 0; padding: 0 6px;
            font: 400 13px/22px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #f0f0f1; background: #2c3338;
            border: 1px solid #50575e; border-radius: 2px; box-shadow: none; outline: none; vertical-align: middle; }
        #wpadminbar .ms-atb input:hover, #wpadminbar .ms-atb select:hover { border-color: #8c8f94; }
        #wpadminbar .ms-atb input:focus, #wpadminbar .ms-atb select:focus { border-color: #72aee6; box-shadow: 0 0 0 1px #72aee6; }
        #wpadminbar .ms-atb input { width: 64px; }
        #wpadminbar .ms-atb select { padding-right: 4px; cursor: pointer; border-left: 3px solid var(--st, #50575e); }
        #wpadminbar .ms-atb select option { color: #1d2327; background: #fff; }
        #wpadminbar .ms-atb select[data-status="available"] { --st: #00a32a; }
        #wpadminbar .ms-atb select[data-status="reserved"] { --st: #dba617; }
        #wpadminbar .ms-atb select[data-status="sold"] { --st: #2271b1; }
        #wpadminbar .ms-atb select[data-status="private"] { --st: #d63638; }
        #wpadminbar .ms-atb select[data-status="gallery"] { --st: #8a4fd1; }
        #wpadminbar .ms-atb select[data-status="gallery-reserved"] { --st: #e26f12; }
        #wpadminbar .ms-atb-state { color: #fff; font-weight: 600; }
        #wpadminbar .ms-atb-state:empty { display: none; }
        #wpadminbar .ms-atb-msg { color: #9ec2e6; }
        #wpadminbar .ms-atb-msg:empty { display: none; }
        #wpadminbar .ms-atb-msg.is-error { color: #ffabaf; }
        #wpadminbar .ms-atb-msg a { display: inline; height: auto; padding: 0; color: #72aee6; }
        /* An unpublished artwork (a Draft): both items say so */
        #wpadminbar li.is-unpublished, #wpadminbar li.is-unpublished > .ab-item { background: #8a5a00 !important; color: #fff !important; }
        @media (max-width: 782px) { #wpadminbar li#wp-admin-bar-ms-artwork-toolbar, #wpadminbar li#wp-admin-bar-ms-artwork-publish { display: block; } }
    </style>
    <script>
    // Runs once the page is parsed: this theme has no wp_body_open, so WordPress prints the admin bar at the very
    // end of the page (wp_footer, priority 1000) - after this script
    (function (run) { document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', run) : run(); })(function () {
        var box = document.querySelector('#wpadminbar .ms-atb'); if (!box) return;
        var node = box.closest('.ms-atb-node'), msg = box.querySelector('.ms-atb-msg');
        var ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, nonce = <?php echo wp_json_encode( wp_create_nonce( 'monospace_artwork_toolbar' ) ); ?>;
        var pubNode = document.getElementById('wp-admin-bar-ms-artwork-publish'), pub = pubNode && pubNode.querySelector('.ab-item'), state = box.querySelector('.ms-atb-state');
        var published = !node.classList.contains('is-unpublished');
        function pubLook(on) {
            published = on;
            node.classList.toggle('is-unpublished', !on); pubNode.classList.toggle('is-unpublished', !on);
            pub.textContent = on ? 'Unpublish' : 'Publish';
            pub.title = on ? 'Unpublish: the post becomes a Draft - off the site for visitors' : 'Publish: back on the site';
            state.textContent = on ? '' : 'Unpublished';
        }
        function save(field, value, el, undo) {
            msg.className = 'ms-atb-msg'; msg.textContent = '…';
            var body = new FormData();
            body.append('action', 'monospace_artwork_toolbar'); body.append('_ajax_nonce', nonce);
            body.append('post_id', box.dataset.post); body.append('field', field); body.append('value', value);
            fetch(ajax, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) {
                if (!r.success) throw new Error(r.data || 'Not saved');
                if (el.dataset) el.dataset.was = value;
                msg.innerHTML = '✓ ' + r.data + ' · <a href="#" class="ms-atb-reload">reload</a>';
            }).catch(function (e) {
                undo();
                msg.className = 'ms-atb-msg is-error'; msg.textContent = e.message;
            });
        }
        // Clicks inside the bar's fields mustn't open / close the toolbar's menus
        box.addEventListener('click', function (e) {
            if (e.target.classList.contains('ms-atb-reload')) { e.preventDefault(); location.reload(); }
            e.stopPropagation();
        });
        var price = box.querySelector('[data-field=price]');
        function savePrice() {
            var v = price.value.replace(/[^\d.]/g, '');
            if (v === price.dataset.was) return;
            // Empty is fine for sold / private / gallery pieces ("--"); the others need a price above 0
            var free = String(price.dataset.optionalWhen || '').split(' ').indexOf(av.value) > -1;
            if (v === '' ? !free : !(parseFloat(v) > 0)) { msg.className = 'ms-atb-msg is-error'; msg.textContent = v === '' ? 'This availability needs a price' : 'A price above 0'; price.value = price.dataset.was; return; }
            price.value = v;
            save('price', v, price, function () { price.value = price.dataset.was; });
        }
        price.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); price.blur(); } if (e.key === 'Escape') { price.value = price.dataset.was; price.blur(); } });
        price.addEventListener('blur', savePrice);
        var av = box.querySelector('[data-field=availability]');
        av.addEventListener('change', function () {
            var was = av.dataset.was; av.dataset.status = av.value;
            price.placeholder = String(price.dataset.optionalWhen || '').split(' ').indexOf(av.value) > -1 ? '--' : '';
            save('availability', av.value, av, function () { av.value = was; av.dataset.status = was; });
        });
        pub.addEventListener('click', function (e) {
            e.preventDefault();
            var was = published, to = !was;
            if (!confirm(to ? 'Publish this artwork? It goes back on the site.' : 'Unpublish this artwork? The post becomes a Draft - visitors can no longer see this page.')) return;
            pubLook(to);
            save('published', to ? '1' : '', {}, function () { pubLook(was); });
        });
    });
    </script>
    <?php
}, 99 );

add_action( 'wp_ajax_monospace_artwork_toolbar', function () {
    check_ajax_referer( 'monospace_artwork_toolbar' );
    $post_id = absint( $_POST['post_id'] ?? 0 );
    $field   = sanitize_key( $_POST['field'] ?? '' );
    $value   = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );
    if ( ! $post_id || get_post_type( $post_id ) !== 'post' || ! current_user_can( 'edit_products' ) || ! current_user_can( 'edit_post', $post_id ) ) wp_send_json_error( 'Not allowed.' );
    if ( ! function_exists( 'monospace_artwork_editor_save_row' ) || ! function_exists( 'monospace_is_artwork' ) ) wp_send_json_error( 'monospace ART Core is missing.' );
    if ( ! monospace_is_artwork( $post_id ) ) wp_send_json_error( 'Not an artwork.' );

    // The page's address before anything changes: a Draft's own link turns into ?p=…, but the cached page is at this one
    $url = get_permalink( $post_id );
    if ( get_post_status( $post_id ) !== 'publish' ) { $p = get_post( $post_id ); $p->post_status = 'publish'; $url = get_permalink( $p ); }

    if ( $field === 'published' ) {
        $status = $value ? 'publish' : 'draft';
        if ( $status === 'publish' && ! current_user_can( 'publish_post', $post_id ) ) wp_send_json_error( 'Not allowed to publish.' );
        $r = wp_update_post( [ 'ID' => $post_id, 'post_status' => $status ], true );
        if ( is_wp_error( $r ) ) wp_send_json_error( $r->get_error_message() );
        $done = $value ? 'Published' : 'Unpublished (Draft)';
    } elseif ( $field === 'price' || $field === 'availability' ) {
        $r = monospace_artwork_editor_save_row( $post_id, [ $field => $value ] );
        if ( $r !== true ) wp_send_json_error( $r );
        $done = $field === 'price' ? 'Price $' . $value : 'Availability saved';
    } else {
        wp_send_json_error( 'Unknown field.' );
    }
    if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache( $url );
    wp_send_json_success( $done );
} );

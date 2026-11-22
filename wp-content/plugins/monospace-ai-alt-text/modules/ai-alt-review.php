<?php
/**
 * Posts > Alt text review (2026-11-17): the pictures in blog posts that have no alt text, with a suggestion for each that you read, edit and
 * keep or drop. Reached from the Posts list (tick posts > Bulk actions > Review missing alt text) or from the menu (all blog posts).
 *
 * Nothing is written until "Save the checked ones": then the Media Library's alt text (when it has none) and the image's alt in the post's text.
 * A plain list, not a panel per picture (Admin UI style); the suggestions are asked for one at a time with the button, about a cent each.
 *
 * @package monospace-ai-alt-text
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
    add_submenu_page( 'edit.php', 'Alt text review', 'Alt text review', 'edit_posts', 'ms-ai-alt-review', 'ms_ai_alt_review_page' );
} );

add_filter( 'bulk_actions-edit-post', function ( $actions ) {
    $actions['ms_ai_alt_review'] = 'Review missing alt text';
    return $actions;
} );

add_filter( 'handle_bulk_actions-edit-post', function ( $redirect, $action, $ids ) {
    if ( $action !== 'ms_ai_alt_review' ) return $redirect;
    return add_query_arg( [ 'page' => 'ms-ai-alt-review', 'ids' => implode( ',', array_map( 'absint', $ids ) ) ], admin_url( 'edit.php' ) );
}, 10, 3 );

/** The pictures without alt text in these posts: [ post_id, attachment_id, post title ] */
function ms_ai_alt_missing( array $post_ids ) {
    $out = [];
    foreach ( $post_ids as $pid ) {
        $post = get_post( $pid );
        if ( ! $post || $post->post_type !== 'post' || get_post_meta( $pid, '_qp_product_id', true ) || ! current_user_can( 'edit_post', $pid ) ) continue;
        if ( ! preg_match_all( '/<img\b[^>]*>/i', $post->post_content, $m ) ) continue;
        $seen = [];
        foreach ( $m[0] as $tag ) {
            if ( ! preg_match( '/wp-image-(\d+)/', $tag, $id ) ) continue;
            $att = (int) $id[1];
            if ( isset( $seen[ $att ] ) || trim( (string) ( preg_match( '/\balt="([^"]*)"/i', $tag, $a ) ? $a[1] : '' ) ) !== '' ) continue;
            if ( ! wp_attachment_is_image( $att ) ) continue;
            $seen[ $att ] = true;
            $out[] = [ $pid, $att, get_the_title( $post ) ];
        }
    }
    return $out;
}

function ms_ai_alt_review_page() {
    if ( ! current_user_can( 'edit_posts' ) ) return;
    $ids = isset( $_GET['ids'] ) ? array_filter( array_map( 'absint', explode( ',', (string) wp_unslash( $_GET['ids'] ) ) ) ) : [];
    $all = ! $ids;
    if ( $all ) {
        $ids = get_posts( [ 'post_type' => 'post', 'post_status' => [ 'publish', 'draft', 'private' ], 'numberposts' => 300, 'fields' => 'ids',
            'meta_query' => [ [ 'key' => '_qp_product_id', 'compare' => 'NOT EXISTS' ] ] ] );
    }
    $rows = ms_ai_alt_missing( $ids );
    $nonce = wp_create_nonce( 'ms_ai' );
    ?>
    <div class="wrap">
        <h1>Alt text review</h1>
        <p><?php echo $all ? 'Every blog post (artworks have their own tools).' : count( $ids ) . ' post' . ( count( $ids ) === 1 ? '' : 's' ) . ' ticked.'; ?>
            <?php if ( ! $all ) : ?><a href="<?php echo esc_url( admin_url( 'edit.php?page=ms-ai-alt-review' ) ); ?>">Look at all blog posts instead</a><?php endif; ?></p>
        <?php if ( ! $rows ) : ?>
            <div class="notice notice-success inline"><p>No picture without alt text. Nothing to do.</p></div>
        <?php elseif ( ms_ai_key() === '' ) : ?>
            <div class="notice notice-warning inline"><p>No API key yet, so no suggestions can be written: <?php echo count( $rows ); ?> picture<?php echo count( $rows ) === 1 ? '' : 's'; ?> without alt text.</p></div>
        <?php else : ?>
            <p><strong><?php echo count( $rows ); ?> picture<?php echo count( $rows ) === 1 ? '' : 's'; ?> without alt text.</strong>
                Writing the suggestions costs about <?php echo count( $rows ); ?> cent<?php echo count( $rows ) === 1 ? '' : 's'; ?> and nothing is saved until you press Save.</p>
            <p>
                <button type="button" class="button button-primary" id="ms-alt-go">Write suggestions</button>
                <button type="button" class="button" id="ms-alt-save" disabled>Save the checked ones</button>
                <span id="ms-alt-status" style="margin-left:8px;color:#646970"></span>
            </p>
            <table class="widefat striped" id="ms-alt-list" style="max-width:980px">
                <thead><tr><th style="width:30px"></th><th style="width:72px">Picture</th><th style="width:22%">Post</th><th>Alt text</th></tr></thead>
                <tbody>
                <?php foreach ( $rows as $i => $r ) : ?>
                    <tr data-post="<?php echo (int) $r[0]; ?>" data-att="<?php echo (int) $r[1]; ?>">
                        <td><input type="checkbox" class="ms-alt-keep" disabled checked></td>
                        <td><?php echo wp_get_attachment_image( $r[1], [ 64, 64 ], false, [ 'style' => 'width:64px;height:64px;object-fit:cover;border-radius:3px' ] ); ?></td>
                        <td><a href="<?php echo esc_url( get_edit_post_link( $r[0] ) ); ?>"><?php echo esc_html( $r[2] ?: '(no title)' ); ?></a></td>
                        <td><textarea rows="3" class="large-text ms-alt-text" disabled placeholder="Not written yet"></textarea></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <script>
            (function () {
                var nonce = <?php echo wp_json_encode( $nonce ); ?>, ajax = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
                var rows = [].slice.call(document.querySelectorAll('#ms-alt-list tbody tr')), status = document.getElementById('ms-alt-status');
                var go = document.getElementById('ms-alt-go'), save = document.getElementById('ms-alt-save');
                function post(data) {
                    var fd = new FormData(); fd.append('_ajax_nonce', nonce);
                    Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
                    return fetch(ajax, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
                }
                go.addEventListener('click', function () {
                    go.disabled = true; var todo = rows.slice(), n = 0, total = rows.length;
                    (function next() {
                        if (!todo.length) { status.textContent = 'Written. Read them, change what you like, untick what you do not want, then Save.'; save.disabled = false; return; }
                        var tr = todo.shift(); status.textContent = 'Writing ' + (++n) + ' of ' + total + '...';
                        post({ action: 'ms_ai_post_alt', post_id: tr.dataset.post, image_id: tr.dataset.att }).then(function (j) {
                            var box = tr.querySelector('.ms-alt-text'), keep = tr.querySelector('.ms-alt-keep');
                            if (j.success) { box.value = j.data.alt; box.disabled = false; keep.disabled = false; }
                            else { box.placeholder = j.data || 'Failed'; keep.checked = false; }
                            next();
                        }, function () { status.textContent = 'The connection failed. Reload and try again.'; });
                    })();
                });
                save.addEventListener('click', function () {
                    var items = rows.filter(function (tr) { return tr.querySelector('.ms-alt-keep').checked && tr.querySelector('.ms-alt-text').value.trim(); })
                        .map(function (tr) { return { post: tr.dataset.post, att: tr.dataset.att, alt: tr.querySelector('.ms-alt-text').value.trim() }; });
                    if (!items.length) { status.textContent = 'Nothing is checked.'; return; }
                    save.disabled = true; status.textContent = 'Saving...';
                    post({ action: 'ms_ai_alt_review_save', items: JSON.stringify(items) }).then(function (j) {
                        status.textContent = j.success ? j.data : (j.data || 'Could not save.');
                        if (j.success) rows.forEach(function (tr) { if (tr.querySelector('.ms-alt-keep').checked && tr.querySelector('.ms-alt-text').value.trim()) tr.style.opacity = '.45'; });
                        save.disabled = false;
                    });
                });
            })();
            </script>
        <?php endif; ?>
    </div>
    <?php
}

/** Save what was kept: the image's alt in the post's text, and the Media Library's alt when it has none */
add_action( 'wp_ajax_ms_ai_alt_review_save', function () {
    check_ajax_referer( 'ms_ai' );
    $items = json_decode( (string) wp_unslash( $_POST['items'] ?? '[]' ), true );
    if ( ! is_array( $items ) ) wp_send_json_error( 'Nothing to save.' );
    $saved = 0;
    $by_post = [];
    foreach ( $items as $it ) {
        $pid = absint( $it['post'] ?? 0 ); $att = absint( $it['att'] ?? 0 ); $alt = trim( sanitize_text_field( (string) ( $it['alt'] ?? '' ) ) );
        if ( ! $pid || ! $att || $alt === '' || ! current_user_can( 'edit_post', $pid ) || get_post_meta( $pid, '_qp_product_id', true ) || ! wp_attachment_is_image( $att ) ) continue;
        $by_post[ $pid ][ $att ] = $alt;
    }
    foreach ( $by_post as $pid => $alts ) {
        $post = get_post( $pid );
        if ( ! $post ) continue;
        $content = $post->post_content;
        foreach ( $alts as $att => $alt ) {
            $content = preg_replace_callback( '/<img\b[^>]*wp-image-' . (int) $att . '\b[^>]*>/i', function ( $m ) use ( $alt ) {
                $tag = $m[0];
                if ( preg_match( '/\balt="([^"]*)"/i', $tag, $a ) ) return trim( $a[1] ) === '' ? preg_replace( '/\balt="[^"]*"/i', 'alt="' . esc_attr( $alt ) . '"', $tag, 1 ) : $tag;
                return preg_replace( '/<img\b/i', '<img alt="' . esc_attr( $alt ) . '"', $tag, 1 );
            }, $content );
            if ( trim( (string) get_post_meta( $att, '_wp_attachment_image_alt', true ) ) === '' ) update_post_meta( $att, '_wp_attachment_image_alt', $alt );
            $saved++;
        }
        if ( $content !== $post->post_content ) wp_update_post( wp_slash( [ 'ID' => $pid, 'post_content' => $content ] ) );
    }
    wp_send_json_success( 'Saved ' . $saved . ' alt text' . ( $saved === 1 ? '' : 's' ) . '.' );
} );

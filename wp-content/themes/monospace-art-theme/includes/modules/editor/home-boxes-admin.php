<?php
/**
 * Layout Manager > Home Page - drag-and-drop editor for the home page boxes
 * (frontend/home/home-boxes.php has the data, defaults and rendering).
 *
 * Rows: drag to reorder, optional label, add / remove. Boxes: drag within or between rows, add,
 * remove, edit title / badge / description / link / width / background (media library image,
 * notes for the editor only - 'notes', never printed - 2026-10-16,
 * "automatic" = newest artwork behind the link, or the original theme image). Saved as JSON in a
 * hidden field to admin-post.php (action monospace_home_layout_save), which sanitizes, stores option
 * monospace_home_layout and purges the page cache. "Reset to default" deletes the option.
 * Social media preview (2026-10-16): the home page's og:image / twitter:image, option
 * monospace_home_share_image (attachment ID, saved with the same form; empty = theme's site-preview.png).
 * Welcome and News stay fixed above the rows (their text comes from the Welcome / News pages).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
    add_submenu_page( 'monospace-layout-manager', 'Home Page Boxes', 'Home Page', 'manage_options', 'monospace-home-boxes', 'monospace_home_boxes_page' );
}, 20 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( strpos( $hook, 'monospace-home-boxes' ) === false ) return;
    wp_enqueue_media();
    wp_enqueue_editor(); // Visual / Text editor for a text box's own text
    wp_enqueue_script( 'jquery-ui-sortable' );
} );

// A page's text, to start a text box's own text from (block markers stripped)
add_action( 'wp_ajax_monospace_home_page_text', function () {
    check_ajax_referer( 'monospace_home_preview' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
    $page = get_post( absint( $_POST['page'] ?? 0 ) );
    if ( ! $page || $page->post_type !== 'page' ) wp_send_json_error();
    wp_send_json_success( [ 'html' => trim( preg_replace( '#<!-- /?wp:.*?-->\s*#s', '', $page->post_content ) ) ] );
} );

// Preview of an "automatic" background while editing (the link may have changed)
add_action( 'wp_ajax_monospace_home_preview', function () {
    check_ajax_referer( 'monospace_home_preview' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error();
    $link = esc_url_raw( wp_unslash( $_POST['link'] ?? '' ) );
    wp_send_json_success( [ 'url' => monospace_home_auto_image( $link, 'medium_large' ) ] );
} );

add_action( 'admin_post_monospace_home_layout_save', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed' );
    check_admin_referer( 'monospace_home_layout_save' );

    // Social media preview (head-tags.php reads it for the home page); empty = the site's default image
    if ( isset( $_POST['share_image'] ) ) {
        $share = absint( $_POST['share_image'] );
        $share && wp_attachment_is_image( $share ) ? update_option( 'monospace_home_share_image', $share, false ) : delete_option( 'monospace_home_share_image' );
    }
    // ...and its title / description (empty = the defaults, monospace_home_share_defaults())
    foreach ( [ 'share_title' => 'monospace_home_share_title', 'share_description' => 'monospace_home_share_description' ] as $field => $option ) {
        if ( ! isset( $_POST[ $field ] ) ) continue;
        $value = $field === 'share_title' ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : sanitize_textarea_field( wp_unslash( $_POST[ $field ] ) );
        $value !== '' ? update_option( $option, $value, false ) : delete_option( $option );
    }

    if ( ! empty( $_POST['reset'] ) ) {
        delete_option( MONOSPACE_HOME_LAYOUT );
        $msg = 'reset';
    } else {
        $rows = json_decode( wp_unslash( $_POST['layout_json'] ?? '' ), true );
        if ( ! is_array( $rows ) ) wp_die( 'The layout could not be read - nothing was saved.' );
        update_option( MONOSPACE_HOME_LAYOUT, monospace_home_layout_sanitize( $rows ), false );
        $msg = 'saved';
    }
    if ( function_exists( 'sg_cachepress_purge_everything' ) ) sg_cachepress_purge_everything();
    wp_safe_redirect( admin_url( 'admin.php?page=monospace-home-boxes&msg=' . $msg ) );
    exit;
} );

/** Link suggestions: pages, categories and the local landing pages (tags with a header title) */
function monospace_home_link_choices() {
    $rel = function ( $url ) { return wp_make_link_relative( $url ); };
    $groups = [ 'Pages' => [], 'Categories' => [], 'Places' => [] ];
    foreach ( get_pages( [ 'sort_column' => 'menu_order,post_title' ] ) as $p ) {
        $groups['Pages'][] = [ get_the_title( $p ), $rel( get_permalink( $p ) ) ];
    }
    foreach ( get_terms( [ 'taxonomy' => 'category', 'hide_empty' => false ] ) as $t ) {
        $title = get_term_meta( $t->term_id, '_monospace_header_title', true ) ?: $t->name;
        $groups['Categories'][] = [ html_entity_decode( $title ) . ' (' . $t->slug . ')', $rel( get_term_link( $t ) ) ];
    }
    foreach ( get_terms( [ 'taxonomy' => 'post_tag', 'hide_empty' => false, 'meta_key' => '_monospace_header_title' ] ) as $t ) {
        $groups['Places'][] = [ html_entity_decode( get_term_meta( $t->term_id, '_monospace_header_title', true ) ), $rel( get_term_link( $t ) ) ];
    }
    return $groups;
}

function monospace_home_boxes_page() {
    $rows = monospace_home_layout();
    foreach ( $rows as &$row ) {
        foreach ( $row['boxes'] as &$box ) $box['preview'] = monospace_home_box_image( $box, 'medium_large' );
    }
    unset( $row, $box );
    $msg = sanitize_key( $_GET['msg'] ?? '' );
    ?>
    <div class="wrap" id="mhb-wrap">
        <?php if ( function_exists( 'monospace_layout_manager_nav' ) ) monospace_layout_manager_nav( 'home' ); else echo '<h1>Home Page Boxes</h1>'; ?>
        <?php if ( $msg === 'saved' ) : ?><div class="notice notice-success is-dismissible"><p>Saved - the home page is updated.</p></div><?php endif; ?>
        <?php if ( $msg === 'reset' ) : ?><div class="notice notice-success is-dismissible"><p>Reset to the default layout.</p></div><?php endif; ?>
        <p class="description" style="max-width:760px">The boxes of the <a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank">home page</a>, row by row.
            Drag rows by <span class="dashicons dashicons-menu"></span> and boxes anywhere, also from one row to another. Click a box to edit it.
            <strong>Text boxes</strong> (like Welcome and News) show text written in the box itself, or a page's text (edited on that page, “Edit text”).
            <strong>Automatic</strong> backgrounds show the newest artwork behind the box's link. Changes go live when you press Save.</p>

        <div class="mhb-tabs"><button type="button" class="mhb-tab is-on" data-view="desktop"><span class="dashicons dashicons-desktop"></span> Desktop</button><button type="button" class="mhb-tab" data-view="phone"><span class="dashicons dashicons-smartphone"></span> Phone</button></div>
        <div id="mhb-rows"></div>
        <p id="mhb-add-row-wrap"><button type="button" class="button" id="mhb-add-row">+ Add row</button></p>
        <div id="mhb-phone" style="display:none"></div>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="mhb-form">
            <?php wp_nonce_field( 'monospace_home_layout_save' ); ?>
            <input type="hidden" name="action" value="monospace_home_layout_save">
            <input type="hidden" name="layout_json" id="mhb-json" value="">

            <?php $share = (int) get_option( 'monospace_home_share_image', 0 );
            $share_src = $share ? wp_get_attachment_image_url( $share, 'medium_large' ) : '';
            $default_src = get_stylesheet_directory_uri() . '/assets/images/site-preview.png'; ?>
            <h2 style="margin-top:28px">Social media preview</h2>
            <div class="mhb-share" data-default="<?php echo esc_url( $default_src ); ?>">
                <div class="mhb-share-card"><img id="mhb-share-img" src="<?php echo esc_url( $share_src ?: $default_src ); ?>" alt="">
                    <span class="mhb-share-tag" id="mhb-share-tag"<?php echo $share_src ? ' hidden' : ''; ?>>default</span></div>
                <div>
                    <input type="hidden" name="share_image" id="mhb-share-id" value="<?php echo $share_src ? (int) $share : ''; ?>">
                    <button type="button" class="button" id="mhb-share-choose">Choose image…</button>
                    <button type="button" class="button-link" id="mhb-share-reset"<?php echo $share_src ? '' : ' hidden'; ?>>Use the default</button>
                    <?php $share_defaults = function_exists( 'monospace_home_share_defaults' ) ? monospace_home_share_defaults() : [ 'title' => '', 'description' => '' ]; ?>
                    <p style="margin-top:12px"><label for="mhb-share-title"><strong>Title</strong></label><br>
                        <input type="text" name="share_title" id="mhb-share-title" class="large-text" maxlength="120"
                               value="<?php echo esc_attr( get_option( 'monospace_home_share_title', '' ) ); ?>" placeholder="<?php echo esc_attr( $share_defaults['title'] ); ?>"></p>
                    <p><label for="mhb-share-description"><strong>Description</strong></label><br>
                        <textarea name="share_description" id="mhb-share-description" class="large-text" rows="3" maxlength="300"
                                  placeholder="<?php echo esc_attr( $share_defaults['description'] ); ?>"><?php echo esc_textarea( get_option( 'monospace_home_share_description', '' ) ); ?></textarea></p>
                    <p class="description">Leave a field empty to use the default shown in grey. Only the share card changes - the browser tab title and the description search engines show stay as they are.
                        Around 60 characters for the title and 155 for the description show in full in most apps.</p>
                    <p class="description">The picture shown when the home page is shared - Facebook, Instagram, Bluesky, iMessage and others.
                        Best 1200 × 630 px (landscape); most apps crop to about that shape, so keep the important part in the middle.
                        Other pages share their own artwork. Saved with Save home page.</p>
                    <p class="description">Apps keep the old picture for a while after a change - Facebook's <a href="https://developers.facebook.com/tools/debug/" target="_blank" rel="noopener">Sharing Debugger</a> refreshes it.</p>
                </div>
            </div>
            <p class="submit">
                <button type="submit" class="button button-primary button-large">Save home page</button>
                <button type="submit" name="reset" value="1" class="button button-link-delete" id="mhb-reset" style="margin-left:16px">Reset to default layout</button>
            </p>
        </form>
    </div>

    <style>
        .mhb-share { display: flex; gap: 18px; align-items: flex-start; max-width: 1100px; }
        .mhb-share-card { position: relative; flex: 0 0 300px; aspect-ratio: 1200 / 630; border: 1px solid #ccd0d4; border-radius: 6px; overflow: hidden; background: #f0f0f1; }
        .mhb-share-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .mhb-share-tag { position: absolute; top: 6px; left: 6px; background: rgba(0,0,0,.6); color: #fff; border-radius: 3px; padding: 1px 6px; font-size: 11px; }
        .mhb-share-tag[hidden] { display: none; }
        #mhb-rows { max-width: 1100px; }
        .mhb-row { background: #fff; border: 1px solid #ccd0d4; border-radius: 6px; margin: 0 0 14px; padding: 10px 12px 12px; }
        .mhb-row-head { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
        .mhb-row-head .dashicons-menu { cursor: move; color: #888; }
        .mhb-row-label { width: 260px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
        .mhb-row-head .mhb-row-del { margin-left: auto; }
        .mhb-row-eye { display: inline-flex; align-items: center; gap: 3px; border: 1px solid #c3c4c7; border-radius: 4px; background: #f6f7f7; color: #50575e; padding: 3px 8px; cursor: pointer; font-size: 12px; }
        .mhb-row-eye:hover { color: #2271b1; border-color: #2271b1; }
        .mhb-row-eye .dashicons { font-size: 16px; width: 16px; height: 16px; }
        .mhb-row-hidden { display: none; background: #b32d2e; color: #fff; border-radius: 3px; padding: 1px 6px; font-size: 11px; }
        .mhb-row.is-row-hidden { border-style: dashed; background: #f6f7f7; }
        .mhb-row.is-row-hidden .mhb-row-hidden { display: inline; }
        .mhb-row.is-row-hidden .mhb-boxes, .mhb-row.is-row-hidden .mhb-row-label { opacity: .45; filter: grayscale(1); }
        .mhb-row-anchor-wrap { color: #646970; }
        .mhb-row-anchor { width: 150px; }
        .mhb-anchor-url { font-size: 11px; }
        .mhb-boxes { display: flex; flex-wrap: wrap; gap: 10px; min-height: 120px; margin: 0; padding: 6px; background: #f6f7f7; border-radius: 4px; }
        .mhb-box { position: relative; width: 180px; height: 120px; margin: 0; border-radius: 8px; overflow: hidden; cursor: grab;
                   background: #8c8f94 center / cover no-repeat; color: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.3); display: flex; flex-direction: column; justify-content: space-between; }
        .mhb-box.w1 { width: 140px; } .mhb-box.w3 { width: 240px; }
        .mhb-box-title { background: rgba(0,0,0,.72); padding: 5px 8px; font-weight: 700; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .mhb-box-badge { color: #fc0; margin-right: 3px; }
        .mhb-box-foot { display: flex; justify-content: space-between; align-items: flex-end; padding: 5px 8px; font-size: 11px;
                        background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,.75) 70%); text-shadow: 0 1px 2px #000; }
        .mhb-box-auto { font-style: italic; opacity: .9; }
        .mhb-box.is-editing { outline: 3px solid #2271b1; outline-offset: 2px; }
        .mhb-eye { position: absolute; top: 4px; right: 4px; z-index: 2; width: 26px; height: 26px; padding: 0; border: 0; border-radius: 50%;
                   background: rgba(255,255,255,.9); color: #1d2327; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,.3); }
        .mhb-eye:hover { background: #fff; color: #2271b1; }
        .mhb-eye .dashicons { font-size: 17px; width: 17px; height: 17px; }
        .mhb-box.is-hidden { opacity: .45; filter: grayscale(1); outline: 2px dashed #8c8f94; outline-offset: -2px; }
        .mhb-box.is-hidden .mhb-eye { opacity: 1; }
        .mhb-box-hidden { margin-left: 4px; background: #b32d2e; color: #fff; border-radius: 3px; padding: 0 5px; font-style: normal; text-shadow: none; }
        .mhb-box.is-text { background-color: #a7aaad; }
        .mhb-box.is-plain { background-color: #f0ece4; color: #1d2327; }
        .mhb-box.is-plain .mhb-box-title { background: none; color: #1d2327; }
        .mhb-box.is-plain .mhb-box-foot { background: none; text-shadow: none; }
        .mhb-add-box-li { display: flex; flex-direction: column; gap: 6px; }
        .mhb-add-box-li .mhb-add-box { height: 57px; }
        .mhb-add-box { width: 120px; height: 120px; border: 2px dashed #b4b9be; border-radius: 8px; background: none; color: #50575e; cursor: pointer; font-size: 13px; }
        .mhb-add-box:hover { border-color: #2271b1; color: #2271b1; }
        .mhb-placeholder { width: 180px; height: 120px; border: 2px dashed #2271b1; border-radius: 8px; background: #e9f2fc; visibility: visible !important; }
        .mhb-editor { margin-top: 12px; padding: 12px 14px; background: #f9f9f9; border: 1px solid #dcdcde; border-radius: 4px; display: grid; grid-template-columns: 1fr 280px; gap: 6px 24px; }
        .mhb-editor .f { display: flex; gap: 10px; margin: 0 0 8px; align-items: flex-start; }
        .mhb-editor .f > label { flex: 0 0 95px; font-weight: 600; padding-top: 5px; }
        .mhb-editor .f > div { flex: 1; }
        .mhb-src-own { margin: 0 0 10px; }
        .mhb-tabs { display: flex; gap: 0; margin: 14px 0 12px; }
        .mhb-tab { display: inline-flex; align-items: center; gap: 6px; padding: 6px 16px; border: 1px solid #c3c4c7; background: #f6f7f7; color: #50575e; cursor: pointer; font-size: 13px; }
        .mhb-tab:first-child { border-radius: 4px 0 0 4px; } .mhb-tab:last-child { border-radius: 0 4px 4px 0; border-left: 0; }
        .mhb-tab.is-on { background: #2271b1; border-color: #2271b1; color: #fff; }
        .mhb-phone-wrap { display: flex; gap: 32px; align-items: flex-start; flex-wrap: wrap; }
        .mhb-phone-frame { width: 340px; padding: 18px 12px 24px; background: #1d2327; border-radius: 34px; box-shadow: 0 4px 16px rgba(0,0,0,.25); }
        .mhb-phone-list { margin: 0; padding: 10px 8px; background: #f0ece4; border-radius: 20px; min-height: 200px; }
        .mhb-ph-item { display: flex; align-items: center; gap: 8px; margin: 0 0 6px; padding: 9px 8px; background: #fff; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,.2); cursor: grab; font-size: 13px; }
        .mhb-ph-item .dashicons-menu { color: #a7aaad; }
        .mhb-ph-title { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
        .mhb-ph-title em { font-weight: 400; color: #8c8f94; }
        .mhb-ph-label { background: none; box-shadow: none; padding: 10px 8px 2px; text-transform: uppercase; letter-spacing: .06em; font-size: 11px; color: #50575e; }
        .mhb-ph-item.is-phone-hidden { opacity: .4; text-decoration: line-through; }
        .mhb-ph-eye { border: 0; background: none; padding: 0; cursor: pointer; color: #50575e; }
        .mhb-ph-eye:hover { color: #2271b1; }
        .mhb-phone-help { max-width: 420px; }
        .mhb-box-note { position: absolute; top: 6px; right: 34px; z-index: 2; color: #fff; background: rgba(0,0,0,.55); border-radius: 50%; width: 22px; height: 22px; font-size: 15px; line-height: 22px; text-align: center; cursor: help; }
        .mhb-box-phone { margin-left: 4px; background: #646970; color: #fff; border-radius: 3px; padding: 0 5px; font-style: normal; text-shadow: none; }
        .mhb-src-own .wp-editor-container { background: #fff; }
        .mhb-editor input[type=text], .mhb-editor textarea, .mhb-editor select { width: 100%; }
        .mhb-editor .mhb-img-preview { width: 100%; aspect-ratio: 3 / 2; border-radius: 8px; background: #8c8f94 center / cover no-repeat; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; color: #fff; font-style: italic; }
        .mhb-editor .mhb-actions { grid-column: 1 / -1; display: flex; gap: 10px; align-items: center; }
        .mhb-editor .mhb-actions .button-link-delete { margin-left: auto; }
        .mhb-empty { color: #888; font-style: italic; padding: 40px 10px; }
    </style>

    <script>
    jQuery(function ($) {
        var rows    = <?php echo wp_json_encode( $rows ); ?>;
        var CHOICES = <?php echo wp_json_encode( monospace_home_link_choices() ); ?>;
        var THEME   = <?php echo wp_json_encode( trailingslashit( get_stylesheet_directory_uri() ) ); ?>;
        var AJAX    = { url: ajaxurl, nonce: <?php echo wp_json_encode( wp_create_nonce( 'monospace_home_preview' ) ); ?> };
        var WIDTHS  = { 1: 'Narrow', 2: 'Normal', 3: 'Wide' };
        var PAGES   = <?php echo wp_json_encode( array_map( function ( $p ) { return [ $p->ID, html_entity_decode( get_the_title( $p ) ), get_edit_post_link( $p->ID, 'raw' ), $p->post_name ]; }, get_pages( [ 'sort_column' => 'post_title' ] ) ) ); ?>;
        var HOME    = <?php echo wp_json_encode( home_url( '/' ) ); ?>;
        // Anchors: like WordPress's sanitize_title - lower case, letters / digits / dashes
        function slug(t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/&/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); }
        function anchorField(value, auto) {
            return '<div class="f"><label>Anchor</label><div><input type="text" class="mhb-f-anchor" placeholder="' + esc(auto) + '" style="max-width:260px"> ' +
                '<code class="mhb-anchor-url"></code> <button type="button" class="button-link mhb-copy">Copy link</button>' +
                '<p class="description">Link straight to this box. Leave empty to use the automatic one (from the title).</p></div></div>';
        }
        // Notes: for the editor only - saved with the layout, never printed on the site
        function notesField() {
            return '<div class="f"><label>Notes</label><div><textarea class="mhb-f-notes" rows="3" style="width:100%;max-width:520px"></textarea>' +
                '<p class="description">Only for you - never shown on the site.</p></div></div>';
        }
        function wireAnchor($ed, autoOf) {
            function upd() {
                var a = slug($ed.find('.mhb-f-anchor').val()) || autoOf();
                $ed.find('.mhb-f-anchor').attr('placeholder', autoOf());
                $ed.find('.mhb-anchor-url').text(HOME + '#' + a);
            }
            $ed.on('input change', '.mhb-f-anchor, .mhb-f-title, .mhb-f-page', upd);
            $ed.on('click', '.mhb-copy', function () {
                var text = $ed.find('.mhb-anchor-url').text(), $b = $(this);
                (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () { $b.text('Copied'); setTimeout(function () { $b.text('Copy link'); }, 1500); }, function () { window.prompt('Copy this link:', text); });
            });
            upd();
        }
        function pageOf(id) { for (var i = 0; i < PAGES.length; i++) if (+PAGES[i][0] === +id) return PAGES[i]; return null; }
        var dirty = false, $editing = null;

        function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }
        function bg(url) { return url ? "url('" + String(url).replace(/'/g, "%27") + "')" : ''; }
        function previewOf(box) { return box.image.mode === 'file' ? THEME + box.image.file : (box.image.mode === 'none' ? '' : (box.preview || '')); }

        function boxCard(box) {
            if (box.type === 'content') {
                var page = pageOf(box.page);
                var $t = $('<li class="mhb-box is-text w' + box.width + (box.look === 'plain' ? ' is-plain' : '') + '">' +
                    '<div class="mhb-box-title">' + esc(box.title || '(no title)') + '</div>' +
                    '<div class="mhb-box-foot"><span>Text: ' + (box.source === 'own' ? 'written here' : esc(page ? page[1] : 'missing page')) + '</span><span class="mhb-box-auto">' + (box.look === 'plain' ? 'plain' : 'box') + '</span></div></li>');
                $t.css('background-image', bg(previewOf(box))).data('box', box);
                return eyed($t, box);
            }
            var $li = $('<li class="mhb-box w' + box.width + '">' +
                '<div class="mhb-box-title">' + (box.badge ? '<span class="mhb-box-badge">' + esc(box.badge) + '</span>' : '') + esc(box.title || '(no title)') + '</div>' +
                '<div class="mhb-box-foot"><span>' + esc(box.link || 'no link') + '</span>' +
                (box.image.mode === 'auto' ? '<span class="mhb-box-auto">automatic</span>' : '') + '</div></li>');
            $li.css('background-image', bg(previewOf(box))).data('box', box);
            return eyed($li, box);
        }

        // Hide / show a whole row on computers (d_hide; phones: the Phone view). Its boxes keep their own switches.
        function rowHidden($row, hidden) {
            $row.data('hidden', hidden).toggleClass('is-row-hidden', hidden);
            $row.find('> .mhb-row-head .mhb-row-eye')
                .attr('title', hidden ? 'Row hidden on computers - click to show it' : 'Click to hide this whole row on computers (phones: Phone view)')
                .html('<span class="dashicons dashicons-' + (hidden ? 'hidden' : 'visibility') + '"></span> ' + (hidden ? 'Show row' : 'Hide row'));
        }

        // Desktop view's eye: shown / hidden on computers (d_hide); a hidden box stays here, faded.
        // Phones have their own eye (Phone view, m_hide) - the tag says when it's off there.
        function eyed($li, box) {
            $li.toggleClass('is-hidden', !!box.d_hide)
               .append('<button type="button" class="mhb-eye" title="' + (box.d_hide ? 'Hidden on computers - click to show' : 'Shown on computers - click to hide') + '">' +
                       '<span class="dashicons dashicons-' + (box.d_hide ? 'hidden' : 'visibility') + '"></span></button>');
            if (box.m_hide) $li.find('.mhb-box-foot').append('<span class="mhb-box-phone" title="Hidden on phones (Phone view)">hidden on phones</span>');
            if (box.notes) $li.append($('<span class="mhb-box-note dashicons dashicons-format-aside"></span>').attr('title', box.notes));
            return $li;
        }

        function render() {
            var $wrap = $('#mhb-rows').empty();
            rows.forEach(function (row) {
                var $row = $('<div class="mhb-row">' +
                    '<div class="mhb-row-head"><span class="dashicons dashicons-menu" title="Drag to move the row"></span>' +
                    '<input type="text" class="mhb-row-label" placeholder="Row label (optional)"> ' +
                    '<span class="mhb-row-anchor-wrap">#<input type="text" class="mhb-row-anchor" placeholder="anchor"></span> ' +
                    '<button type="button" class="button-link mhb-row-copy">Copy link</button> ' +
                    '<button type="button" class="mhb-row-eye"></button><span class="mhb-row-hidden">hidden on computers</span>' +
                    '<button type="button" class="button-link button-link-delete mhb-row-del">Remove row</button></div>' +
                    '<ul class="mhb-boxes"></ul></div>');
                $row.find('.mhb-row-label').val(row.label);
                $row.data('m', { m_order: +row.m_order || 0, m_hide: !!row.m_hide });
                rowHidden($row, !!row.d_hide);
                $row.find('.mhb-row-anchor').val(row.anchor || '').attr('placeholder', slug(row.label) || 'anchor');
                var $list = $row.find('.mhb-boxes');
                row.boxes.forEach(function (box) { $list.append(boxCard(box)); });
                $list.append('<li class="mhb-add-box-li" style="list-style:none"><button type="button" class="mhb-add-box">+ Add box</button><button type="button" class="mhb-add-box mhb-add-text">+ Add text box</button></li>');
                $wrap.append($row);
            });
            if (!rows.length) $wrap.append('<p class="mhb-empty">No rows - add one below.</p>');

            $wrap.sortable({ handle: '.mhb-row-head .dashicons-menu', items: '> .mhb-row', update: changed });
            $('.mhb-boxes').sortable({
                items: '> .mhb-box', connectWith: '.mhb-boxes', placeholder: 'mhb-placeholder', tolerance: 'pointer',
                cancel: '.mhb-add-box-li', update: function () { changed(); },
                stop: function () { $('.mhb-boxes').each(function () { $(this).append($(this).children('.mhb-add-box-li')); }); }
            });
        }

        // State from the screen (order of rows and boxes as dragged, labels as typed)
        function sync() {
            rows = $('#mhb-rows > .mhb-row').map(function () {
                var m = $(this).data('m') || {};
                return { label: $(this).find('.mhb-row-label').val(), anchor: slug($(this).find('.mhb-row-anchor').val()),
                         m_order: +m.m_order || 0, m_hide: !!m.m_hide, d_hide: !!$(this).data('hidden'),
                         boxes: $(this).find('.mhb-box').map(function () { return $(this).data('box'); }).get() };
            }).get();
            $('#mhb-json').val(JSON.stringify(rows));
        }
        function changed() { dirty = true; sync(); }

        function linkSelect(current) {
            var h = '<select class="mhb-f-pick"><option value="">Pick a page, category or place…</option>';
            Object.keys(CHOICES).forEach(function (g) {
                h += '<optgroup label="' + esc(g) + '">';
                CHOICES[g].forEach(function (c) { h += '<option value="' + esc(c[1]) + '"' + (c[1] === current ? ' selected' : '') + '>' + esc(c[0]) + '</option>'; });
                h += '</optgroup>';
            });
            return h + '</select>';
        }

        function closeEditor() { if (window.wp && wp.editor && window.tinymce && tinymce.get('mhb-html')) wp.editor.remove('mhb-html'); $('.mhb-editor').remove(); $('.mhb-box').removeClass('is-editing'); $editing = null; }

        // Text box: title, the page whose text it shows (edited there), look, width, background
        function openTextEditor($li) {
            closeEditor();
            $editing = $li.addClass('is-editing');
            var box = $.extend(true, {}, $li.data('box'));
            var options = PAGES.map(function (p) { return '<option value="' + p[0] + '"' + (+p[0] === +box.page ? ' selected' : '') + '>' + esc(p[1]) + '</option>'; }).join('');
            var $ed = $('<div class="mhb-editor">' +
                '<div>' +
                  '<div class="f"><label>Title</label><div><input type="text" class="mhb-f-title"></div></div>' +
                  '<div class="f"><label>Text from</label><div>' +
                    '<label style="margin-right:14px"><input type="radio" name="mhb-src" value="own"> Written here</label>' +
                    '<label><input type="radio" name="mhb-src" value="page"> A page</label></div></div>' +
                  '<div class="f mhb-src-page"><label>Page</label><div><select class="mhb-f-page"><option value="">Choose a page…</option>' + options + '</select> ' +
                    '<a class="button mhb-f-edit" target="_blank">Edit text ↗</a><p class="description">The box shows this page’s text - edit it in the page (opens in a new tab), then reload the home page.</p></div></div>' +
                  '<div class="mhb-src-own"><textarea id="mhb-html" rows="10"></textarea>' +
                    '<p class="description">Headings, bold, links, lists, images… Saved with the layout (press Save below).</p></div>' +
                  '<div class="f"><label>Look</label><div><label style="margin-right:14px"><input type="radio" name="mhb-look" value="plain"> Plain text (like Welcome)</label>' +
                    '<label><input type="radio" name="mhb-look" value="box"> Box with title bar and background (like News; long text scrolls)</label></div></div>' +
                  '<div class="f"><label>Width</label><div>' + Object.keys(WIDTHS).map(function (w) {
                        return '<label style="margin-right:14px"><input type="radio" name="mhb-w" value="' + w + '"' + (+w === +box.width ? ' checked' : '') + '> ' + WIDTHS[w] + '</label>'; }).join('') + '</div></div>' +
                  anchorField() +
                  notesField() +
                '</div>' +
                '<div class="mhb-text-bg">' +
                  '<div class="mhb-img-preview"></div>' +
                  '<label style="display:block;margin-bottom:4px"><input type="radio" name="mhb-img" value="none"> No background</label>' +
                  '<label style="display:block;margin-bottom:4px"><input type="radio" name="mhb-img" value="media"> Image from the media library</label>' +
                  (box.image.mode === 'file' ? '<label style="display:block;margin-bottom:4px"><input type="radio" name="mhb-img" value="file"> Original theme image</label>' : '') +
                  '<button type="button" class="button mhb-f-choose" style="margin-top:6px">Choose image…</button>' +
                '</div>' +
                '<div class="mhb-actions"><button type="button" class="button button-primary mhb-done">Done</button>' +
                  '<button type="button" class="button mhb-cancel">Cancel</button>' +
                  '<button type="button" class="button-link button-link-delete mhb-del">Remove box</button></div>' +
                '</div>');
            $ed.find('.mhb-f-title').val(box.title);
            $ed.find('[name=mhb-look][value=' + (box.look === 'plain' ? 'plain' : 'box') + ']').prop('checked', true);
            $ed.find('.mhb-f-anchor').val(box.anchor || '');
            $ed.find('.mhb-f-notes').val(box.notes || '');
            $ed.find('[name=mhb-src][value=' + (box.source === 'own' ? 'own' : 'page') + ']').prop('checked', true);
            $ed.find('#mhb-html').val(box.html || '');
            function own() { return $ed.find('[name=mhb-src]:checked').val() === 'own'; }
            function ownHtml() { return window.tinymce && tinymce.get('mhb-html') ? wp.editor.getContent('mhb-html') : $ed.find('#mhb-html').val(); }
            wireAnchor($ed, function () { var p = pageOf($ed.find('.mhb-f-page').val()); return !own() && p ? p[3] : slug($ed.find('.mhb-f-title').val()); });
            $ed.on('change', '[name=mhb-src]', function () {
                $ed.find('.mhb-f-anchor').trigger('change');
                // switching to "Written here" with nothing written yet: start from the page's text
                var p = pageOf($ed.find('.mhb-f-page').val());
                if (own() && p && !$.trim(ownHtml())) {
                    $.post(AJAX.url, { action: 'monospace_home_page_text', _ajax_nonce: AJAX.nonce, page: p[0] }, function (r) {
                        if (!r || !r.success) return;
                        if (window.tinymce && tinymce.get('mhb-html')) tinymce.get('mhb-html').setContent(wp.editor.autop ? wp.editor.autop(r.data.html) : r.data.html);
                        $ed.find('#mhb-html').val(r.data.html);
                    });
                }
            });
            $ed.find('[name=mhb-img][value=' + box.image.mode + ']').prop('checked', true);
            if (box.image.mode === 'media') box.mediaPreview = box.preview;

            function refreshPanel() {
                var page = pageOf($ed.find('.mhb-f-page').val());
                $ed.find('.mhb-f-edit').attr('href', page ? page[2] : '#').toggle(!!page);
                $ed.find('.mhb-src-page').toggle(!own()); $ed.find('.mhb-src-own').toggle(own());
                var plain = $ed.find('[name=mhb-look]:checked').val() === 'plain';
                $ed.find('.mhb-text-bg').css('opacity', plain ? .4 : 1);
                var mode = $ed.find('[name=mhb-img]:checked').val();
                var url = mode === 'file' ? THEME + box.image.file : (mode === 'media' ? box.mediaPreview : '');
                $ed.find('.mhb-img-preview').css('background-image', bg(url)).text(url ? '' : (mode === 'media' ? 'Choose an image' : 'No background'));
            }
            refreshPanel();
            $ed.on('change', 'select, input', refreshPanel);
            $ed.on('click', '.mhb-f-choose', function () {
                var frame = wp.media({ title: 'Box background', library: { type: 'image' }, multiple: false, button: { text: 'Use this image' } });
                frame.on('select', function () {
                    var a = frame.state().get('selection').first().toJSON();
                    box.image = { mode: 'media', id: a.id };
                    box.mediaPreview = (a.sizes && (a.sizes.medium_large || a.sizes.large || a.sizes.medium) || a).url;
                    $ed.find('[name=mhb-img][value=media]').prop('checked', true);
                    refreshPanel();
                });
                frame.open();
            });
            $ed.on('click', '.mhb-cancel', closeEditor);
            $ed.on('click', '.mhb-del', function () {
                if (!confirm('Remove this box from the home page? (The page and its text stay.)')) return;
                $li.remove(); closeEditor(); changed();
            });
            $ed.on('click', '.mhb-done', function () {
                var page = +$ed.find('.mhb-f-page').val(), mode = $ed.find('[name=mhb-img]:checked').val() || 'none';
                if (!own() && !page) { alert('Choose the page whose text the box shows.'); return; }
                if (mode === 'media' && !(box.image.mode === 'media' && box.image.id)) { alert('Choose an image from the media library first, or pick No background.'); return; }
                var updated = { type: 'content', title: $.trim($ed.find('.mhb-f-title').val()), page: own() ? 0 : page,
                    source: own() ? 'own' : 'page', html: own() ? ownHtml() : '', anchor: slug($ed.find('.mhb-f-anchor').val()),
                    look: $ed.find('[name=mhb-look]:checked').val() === 'plain' ? 'plain' : 'box',
                    width: +$ed.find('[name=mhb-w]:checked').val() || 1,
                    image: mode === 'media' ? { mode: 'media', id: box.image.id } : (mode === 'file' ? { mode: 'file', file: $li.data('box').image.file } : { mode: 'none' }) };
                updated.preview = mode === 'media' ? box.mediaPreview : '';
                updated.m_order = $li.data('box').m_order || 0;
                updated.m_hide = !!$li.data('box').m_hide; updated.d_hide = !!$li.data('box').d_hide; // the eyes set these
                updated.notes = $.trim($ed.find('.mhb-f-notes').val());
                $li.replaceWith(boxCard(updated)); closeEditor(); changed();
            });
            $li.closest('.mhb-row').append($ed);
            if (window.wp && wp.editor && wp.editor.initialize) wp.editor.initialize('mhb-html', {
                tinymce: { wpautop: true, height: 240, toolbar1: 'formatselect,bold,italic,underline,link,bullist,numlist,blockquote,alignleft,aligncenter,alignright,hr,removeformat,undo,redo' },
                quicktags: true, mediaButtons: true });
            refreshPanel();
            $ed.find('.mhb-f-title').focus();
        }

        function openEditor($li) {
            if ($li.data('box').type === 'content') return openTextEditor($li);
            closeEditor();
            $editing = $li.addClass('is-editing');
            var box = $.extend(true, {}, $li.data('box'));
            var $ed = $('<div class="mhb-editor">' +
                '<div>' +
                  '<div class="f"><label>Title</label><div><input type="text" class="mhb-f-title"></div></div>' +
                  '<div class="f"><label>Badge</label><div><input type="text" class="mhb-f-badge" placeholder="e.g. NEW!"><p class="description">Small highlighted text before the title. Leave empty for none.</p></div></div>' +
                  '<div class="f"><label>Description</label><div><textarea class="mhb-f-text" rows="3"></textarea></div></div>' +
                  '<div class="f"><label>Link</label><div><input type="text" class="mhb-f-link" placeholder="/category/print/ or https://…" style="margin-bottom:6px">' + linkSelect(box.link) + '</div></div>' +
                  '<div class="f"><label>Width</label><div>' + Object.keys(WIDTHS).map(function (w) {
                        return '<label style="margin-right:14px"><input type="radio" name="mhb-w" value="' + w + '"' + (+w === +box.width ? ' checked' : '') + '> ' + WIDTHS[w] + '</label>'; }).join('') +
                        '<p class="description">Share of the row - a wide box next to two normal ones takes about 40%.</p></div></div>' +
                  anchorField() +
                  notesField() +
                '</div>' +
                '<div>' +
                  '<div class="mhb-img-preview"></div>' +
                  '<label style="display:block;margin-bottom:4px"><input type="radio" name="mhb-img" value="auto"> Automatic (newest artwork)</label>' +
                  '<label style="display:block;margin-bottom:4px"><input type="radio" name="mhb-img" value="media"> Image from the media library</label>' +
                  (box.image.mode === 'file' ? '<label style="display:block;margin-bottom:4px"><input type="radio" name="mhb-img" value="file"> Original theme image</label>' : '') +
                  '<button type="button" class="button mhb-f-choose" style="margin-top:6px">Choose image…</button>' +
                '</div>' +
                '<div class="mhb-actions"><button type="button" class="button button-primary mhb-done">Done</button>' +
                  '<button type="button" class="button mhb-cancel">Cancel</button>' +
                  '<button type="button" class="button-link button-link-delete mhb-del">Remove box</button></div>' +
                '</div>');
            $ed.find('.mhb-f-title').val(box.title);
            $ed.find('.mhb-f-badge').val(box.badge);
            $ed.find('.mhb-f-text').val(box.text);
            $ed.find('.mhb-f-link').val(box.link);
            $ed.find('[name=mhb-img][value=' + box.image.mode + ']').prop('checked', true);
            $ed.find('.mhb-f-anchor').val(box.anchor || '');
            $ed.find('.mhb-f-notes').val(box.notes || '');
            wireAnchor($ed, function () { return slug($ed.find('.mhb-f-title').val()) || 'box'; });

            function showPreview() {
                var mode = $ed.find('[name=mhb-img]:checked').val();
                var url = mode === 'file' ? THEME + box.image.file : (mode === 'media' ? box.mediaPreview || box.preview : box.autoPreview);
                $ed.find('.mhb-img-preview').css('background-image', bg(url)).text(url ? '' : (mode === 'media' ? 'Choose an image' : 'No artwork found for this link'));
            }
            function refreshAuto() {
                var link = $.trim($ed.find('.mhb-f-link').val());
                $.post(AJAX.url, { action: 'monospace_home_preview', _ajax_nonce: AJAX.nonce, link: link }, function (r) {
                    box.autoPreview = r && r.success ? r.data.url : ''; showPreview();
                });
            }
            if (box.image.mode === 'media') box.mediaPreview = box.preview;
            box.autoPreview = box.image.mode === 'auto' ? box.preview : '';
            if (box.image.mode !== 'auto') refreshAuto();
            showPreview();

            $ed.on('change', '[name=mhb-img]', showPreview);
            $ed.on('change', '.mhb-f-pick', function () { if (this.value) { $ed.find('.mhb-f-link').val(this.value); refreshAuto(); } });
            $ed.on('change', '.mhb-f-link', refreshAuto);
            $ed.on('click', '.mhb-f-choose', function () {
                var frame = wp.media({ title: 'Box background', library: { type: 'image' }, multiple: false, button: { text: 'Use this image' } });
                frame.on('select', function () {
                    var a = frame.state().get('selection').first().toJSON();
                    box.image = { mode: 'media', id: a.id };
                    box.mediaPreview = (a.sizes && (a.sizes.medium_large || a.sizes.large || a.sizes.medium) || a).url;
                    $ed.find('[name=mhb-img][value=media]').prop('checked', true);
                    showPreview();
                });
                frame.open();
            });
            $ed.on('click', '.mhb-cancel', closeEditor);
            $ed.on('click', '.mhb-del', function () {
                if (!confirm('Remove this box?')) return;
                $li.remove(); closeEditor(); changed();
            });
            $ed.on('click', '.mhb-done', function () {
                var mode = $ed.find('[name=mhb-img]:checked').val() || 'auto';
                if (mode === 'media' && !(box.image.mode === 'media' && box.image.id)) { alert('Choose an image from the media library first, or pick Automatic.'); return; }
                var updated = {
                    title: $.trim($ed.find('.mhb-f-title').val()), badge: $.trim($ed.find('.mhb-f-badge').val()),
                    text: $.trim($ed.find('.mhb-f-text').val()), link: $.trim($ed.find('.mhb-f-link').val()),
                    width: +$ed.find('[name=mhb-w]:checked').val() || 2,
                    image: mode === 'media' ? { mode: 'media', id: box.image.id } : (mode === 'file' ? { mode: 'file', file: $li.data('box').image.file } : { mode: 'auto' }),
                    anchor: slug($ed.find('.mhb-f-anchor').val())
                };
                updated.preview = mode === 'media' ? box.mediaPreview : (mode === 'auto' ? box.autoPreview : '');
                if (!updated.title && !updated.link) { alert('A box needs at least a title or a link.'); return; }
                updated.m_order = $li.data('box').m_order || 0;
                updated.m_hide = !!$li.data('box').m_hide; updated.d_hide = !!$li.data('box').d_hide; // the eyes set these
                updated.notes = $.trim($ed.find('.mhb-f-notes').val());
                var $new = boxCard(updated);
                $li.replaceWith($new); closeEditor(); changed();
            });
            $li.closest('.mhb-row').append($ed);
            $ed.find('.mhb-f-title').focus();
        }

        $('#mhb-rows')
            .on('click', '.mhb-eye', function (e) {
                e.stopPropagation();
                var $li = $(this).closest('.mhb-box'), box = $.extend({}, $li.data('box'), { d_hide: !$li.data('box').d_hide });
                if ($li.hasClass('is-editing')) closeEditor();
                $li.replaceWith(boxCard(box)); changed();
            })
            .on('click', '.mhb-box', function () { if (!$(this).hasClass('is-editing')) openEditor($(this)); })
            .on('click', '.mhb-add-box', function () {
                var $card = $(this).hasClass('mhb-add-text')
                    ? boxCard({ type: 'content', title: 'New text box', source: 'own', html: '', page: 0, look: 'box', width: 1, image: { mode: 'none' }, preview: '' })
                    : boxCard({ title: 'New box', badge: '', text: '', link: '', width: 2, image: { mode: 'auto' }, preview: '' });
                $(this).closest('.mhb-add-box-li').before($card); changed(); openEditor($card);
            })
            .on('click', '.mhb-row-del', function () {
                var $row = $(this).closest('.mhb-row');
                if ($row.find('.mhb-box').length && !confirm('Remove this row and its boxes?')) return;
                $row.remove(); closeEditor(); changed();
            })
            .on('input', '.mhb-row-label', function () {
                $(this).closest('.mhb-row-head').find('.mhb-row-anchor').attr('placeholder', slug(this.value) || 'anchor');
                changed();
            })
            .on('input', '.mhb-row-anchor', changed)
            .on('click', '.mhb-row-copy', function () {
                var $h = $(this).closest('.mhb-row-head'), $b = $(this);
                var a = slug($h.find('.mhb-row-anchor').val()) || slug($h.find('.mhb-row-label').val());
                if (!a) { alert('This row has no label or anchor yet - type one first.'); return; }
                var text = HOME + '#' + a;
                (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () { $b.text('Copied'); setTimeout(function () { $b.text('Copy link'); }, 1500); }, function () { window.prompt('Copy this link:', text); });
            })
            .on('mouseenter', '.mhb-row-copy', function () {
                var $h = $(this).closest('.mhb-row-head'), a = slug($h.find('.mhb-row-anchor').val()) || slug($h.find('.mhb-row-label').val());
                this.title = a ? HOME + '#' + a : 'Type a label or anchor first';
            })
            .on('click', '.mhb-row-eye', function () { var $row = $(this).closest('.mhb-row'); rowHidden($row, !$row.data('hidden')); changed(); });
        // Phone view: every row label and box, in one column as phones show it. Drag = phone-only
        // order (m_order 1, 2, ... on every item); the eye = shown / hidden on phones (m_hide). Desktop untouched.
        function phoneItems() {
            var items = [], n = 0;
            $('#mhb-rows > .mhb-row').each(function () {
                var $row = $(this), $boxes = $row.find('.mhb-box');
                if (!$boxes.length) return;
                var label = $.trim($row.find('.mhb-row-label').val());
                if (!$row.data('m')) $row.data('m', {});
                if (label) items.push({ kind: 'label', m: $row.data('m'), title: label, nat: n++ });
                $boxes.each(function () { var b = $(this).data('box'); items.push({ kind: 'box', m: b, title: b.title || '(no title)', text: b.type === 'content', nat: n++ }); });
            });
            var custom = items.some(function (it) { return +it.m.m_order > 0; });
            if (custom) items.sort(function (a, b) { return ((+a.m.m_order) || 10000 + a.nat) - ((+b.m.m_order) || 10000 + b.nat); });
            return { items: items, custom: custom };
        }
        function renderPhone() {
            var p = phoneItems(), $ul = $('<ul class="mhb-phone-list"></ul>');
            p.items.forEach(function (it) {
                var hide = !!it.m.m_hide;
                var $li = $('<li class="mhb-ph-item mhb-ph-' + it.kind + (hide ? ' is-phone-hidden' : '') + '"><span class="dashicons dashicons-menu"></span>' +
                    '<span class="mhb-ph-title">' + esc(it.title) + (it.text ? ' <em>text</em>' : '') + '</span>' +
                    '<button type="button" class="mhb-ph-eye" title="' + (hide ? 'Hidden on phones - click to show' : 'Click to hide on phones only') + '"><span class="dashicons dashicons-' + (hide ? 'hidden' : 'visibility') + '"></span></button></li>');
                $ul.append($li.data('it', it));
            });
            var $help = $('<div class="mhb-phone-help">' +
                '<p><strong>' + (p.custom ? 'Phones use their own order.' : 'Phones show the desktop order, row by row.') + '</strong></p>' +
                '<p class="description">On phones (screens narrower than 640px) the boxes stack in one column. Drag to give phones their own order; ' +
                'the eye shows or hides a box or row label on phones only - computers and tablets have their own eye in the Desktop view. ' +
                'Boxes added later go at the end of the phone order.</p>' +
                (p.custom ? '<p><button type="button" class="button mhb-ph-reset">Use the desktop order again</button></p>' : '') +
                '<p class="description">Changes go live when you press Save.</p></div>');
            $('#mhb-phone').empty().append($('<div class="mhb-phone-wrap"></div>').append($('<div class="mhb-phone-frame"></div>').append($ul), $help));
            $ul.sortable({ placeholder: 'mhb-ph-item', tolerance: 'pointer', update: function () {
                $ul.children('.mhb-ph-item').each(function (i) { $(this).data('it').m.m_order = i + 1; });
                changed(); renderPhone();
            } });
        }
        $('#mhb-phone')
            .on('click', '.mhb-ph-eye', function () { var it = $(this).closest('.mhb-ph-item').data('it'); it.m.m_hide = !it.m.m_hide; changed(); renderPhone(); })
            .on('click', '.mhb-ph-reset', function () {
                $('#mhb-rows > .mhb-row').each(function () {
                    var m = $(this).data('m'); if (m) m.m_order = 0;
                    $(this).find('.mhb-box').each(function () { $(this).data('box').m_order = 0; });
                });
                changed(); renderPhone();
            });
        $('.mhb-tabs').on('click', '.mhb-tab', function () {
            var phone = $(this).data('view') === 'phone';
            if (phone && $('.mhb-editor').length) { alert('Press Done (or Cancel) on the box you are editing first.'); return; }
            $('.mhb-tab').removeClass('is-on'); $(this).addClass('is-on');
            $('#mhb-rows, #mhb-add-row-wrap').toggle(!phone); $('#mhb-phone').toggle(phone);
            if (phone) renderPhone();
            else $('#mhb-rows .mhb-box').each(function () { $(this).replaceWith(boxCard($(this).data('box'))); }); // "not on phones" tags
        });
        $('#mhb-add-row').on('click', function () { sync(); rows.push({ label: '', boxes: [] }); render(); changed(); });
        $('#mhb-form').on('submit', function (e) {
            var reset = e.originalEvent && e.originalEvent.submitter && e.originalEvent.submitter.name === 'reset';
            if (reset && !confirm('Replace the whole layout with the default one?')) { e.preventDefault(); return; }
            if ($('.mhb-editor').length && !reset) { e.preventDefault(); alert('Press Done (or Cancel) on the box you are editing first.'); return; }
            sync(); dirty = false;
        });
        $(window).on('beforeunload', function () { if (dirty) return 'You have unsaved changes.'; });

        // Social media preview: media library picker (saved with the form)
        var shareFrame = null;
        $('#mhb-share-choose').on('click', function () {
            if (!shareFrame) {
                shareFrame = wp.media({ title: 'Social media preview', library: { type: 'image' }, button: { text: 'Use this image' }, multiple: false });
                shareFrame.on('select', function () {
                    var a = shareFrame.state().get('selection').first().toJSON(), s = a.sizes || {};
                    $('#mhb-share-id').val(a.id);
                    $('#mhb-share-img').attr('src', (s.medium_large || s.large || s.full || a).url);
                    $('#mhb-share-tag, #mhb-share-reset').prop('hidden', function () { return this.id === 'mhb-share-tag'; });
                    dirty = true;
                });
            }
            shareFrame.open();
        });
        $('#mhb-share-reset').on('click', function () {
            $('#mhb-share-id').val('');
            $('#mhb-share-img').attr('src', $('.mhb-share').data('default'));
            $('#mhb-share-tag').prop('hidden', false); $(this).prop('hidden', true);
            dirty = true;
        });

        $('#mhb-share-title, #mhb-share-description').on('input', function () { dirty = true; });

        render(); sync();
    });
    </script>
    <?php
}

<?php
/**
 * The same two buttons on an artwork's edit screen, in the Quick Artwork Editor box (owner, 2026-11-15): a folding panel
 * "Alt text & tags". Alt text: the box starts from the featured image's alt text; Generate alt text / Regenerate alt text fill it
 * (Regenerate alt text asks first - extra API credit); any save (Save alt text, the editor's Save, Cmd-S - Core's
 * artwork-save.php) puts it into the post's image block and the Media Library. Tags: Suggest tags adds the suggestions that already exist as tags to the post
 * (the block editor's Tags panel shows them; save the post to keep them); new ones are only listed, to add by hand.
 * Core's box offers the hook monospace_quick_editor_sections.
 *
 * @package monospace-ai-alt-text
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'monospace_quick_editor_sections', function ( $post ) {
    if ( ms_ai_key() === '' ) return;
    $thumb = (int) get_post_thumbnail_id( $post->ID );
    $alt   = $thumb ? trim( (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) ) : '';
    $state = ! $thumb ? [ 'empty', 'No image' ] : ( $alt === '' ? [ 'warn', 'No alt text' ] : [ 'on', 'Has alt text' ] );
    ?>
    <details class="qp-sec qp-sec--ai" id="ms-ai-q" data-post="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'ms_ai' ) ); ?>">
        <summary class="qp-sec-head"><span class="qp-sec-title">Alt text &amp; tags</span><span class="qp-chip is-<?php echo esc_attr( $state[0] ); ?>" id="ms-ai-q-chip"><?php echo esc_html( $state[1] ); ?></span><span class="qp-sec-sub">Claude can write them for the featured image</span></summary>
        <div class="qp-sec-body">
            <p><label for="ms-ai-q-alt"><strong>Alt text</strong> (featured image)</label></p>
            <textarea id="ms-ai-q-alt" rows="4" maxlength="800" style="width:100%" placeholder="What is in the picture - for screen readers and search"><?php echo esc_textarea( $alt ); ?></textarea>
            <p>
                <button type="button" class="button" id="ms-ai-q-alt-btn">Generate alt text</button>
                <button type="button" class="button button-primary" id="ms-ai-q-save-btn">Save alt text</button>
                <span id="ms-ai-q-alt-msg" aria-live="polite" style="font-size:12px;color:#646970"></span>
            </p>
            <p>
                <button type="button" class="button" id="ms-ai-q-tags-btn">Suggest tags</button>
                <span id="ms-ai-q-tags-msg" aria-live="polite" style="font-size:12px;color:#646970"></span>
            </p>
            <p class="description">Each request costs about a cent. Regenerate alt text and Suggest tags again ask first.</p>
        </div>
    </details>
    <?php
} );

add_action( 'admin_footer', function () {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || $screen->base !== 'post' || ms_ai_key() === '' ) return;
    ?>
    <script>
    jQuery(function ($) {
        var $box = $('#ms-ai-q');
        if (!$box.length || !window.wp || !wp.data) return;
        var post = $box.data('post'), nonce = $box.data('nonce');
        var ed = function () { return wp.data.select('core/editor'); };
        function featured() { return ed() ? ed().getEditedPostAttribute('featured_media') : 0; }
        function say($el, t, kind) { $el.text(t).css('color', kind === 'error' ? '#b32d2e' : (kind === 'warn' ? '#8a5a00' : '#646970')); }
        function run($btn, $msg, action, busy, done) {
            var img = featured();
            if (!img) { say($msg, 'Set the featured image first.', 'warn'); return; }
            $btn.prop('disabled', true); say($msg, busy);
            $.post(ajaxurl, { action: action, _ajax_nonce: nonce, post_id: post, image_id: img, title: ed().getEditedPostAttribute('title') || '' })
                .done(function (r) { if (r && r.success) done(r.data); else say($msg, (r && r.data) || 'Something went wrong.', 'error'); })
                .fail(function (x) { say($msg, 'Request failed (' + x.status + ').', 'error'); })
                .always(function () { $btn.prop('disabled', false); });
        }
        function chip(kind, text) { $('#ms-ai-q-chip').attr('class', 'qp-chip is-' + kind).text(text); }

        // Alt text
        var $alt = $('#ms-ai-q-alt'), $aBtn = $('#ms-ai-q-alt-btn'), $aMsg = $('#ms-ai-q-alt-msg'), wrote = false;
        $aBtn.on('click', function () {
            if (wrote && !window.confirm('Regenerate alt text asks Claude again and uses extra API credit (about a cent each time). The text in the box will be replaced. Continue?')) return;
            if (!wrote && $.trim($alt.val()) && !window.confirm('Replace the alt text in the box with a new one?')) return;
            run($aBtn, $aMsg, 'ms_ai_alt', 'Writing…', function (d) {
                wrote = true; $alt.val(d.alt).trigger('change'); $aBtn.text('Regenerate alt text');
                say($aMsg, (d.uncertain ? 'Written - Claude was unsure about something, please read it.' : 'Written - edit it if you like; any Save keeps it.') + ' Regenerate alt text uses extra credit.', d.uncertain ? 'warn' : '');
            });
        });
        // Its part of the one save (Core, artwork-save.php): the image block gets the words before the post is saved, the
        // Media Library after
        var savedAlt = $.trim($alt.val());
        function altChanged() { return !!featured() && $.trim($alt.val()) !== savedAlt; }
        function intoBlocks() {
            if (!altChanged()) return null;
            var img = featured(), text = $.trim($alt.val()), wrote = null;
            (function walk(blocks) {
                blocks.forEach(function (b) {
                    if (b.name === 'core/image' && b.attributes && b.attributes.id === img && b.attributes.alt !== text) {
                        wp.data.dispatch('core/block-editor').updateBlockAttributes(b.clientId, { alt: text });
                        wrote = true;
                    }
                    if (b.innerBlocks && b.innerBlocks.length) walk(b.innerBlocks);
                });
            })(wp.data.select('core/block-editor').getBlocks());
            return wrote;
        }
        $alt.on('change', intoBlocks);
        (window.msSaveParts = window.msSaveParts || []).push({
            flush: intoBlocks,
            dirty: altChanged,
            save: function () {
                if (!altChanged()) return null;
                var text = $.trim($alt.val());
                say($aMsg, 'Saving…');
                return wp.apiFetch({ path: '/wp/v2/media/' + featured(), method: 'POST', data: { alt_text: text } }).then(function () {
                    savedAlt = text;
                    chip(text ? 'on' : 'warn', text ? 'Has alt text' : 'No alt text');
                    say($aMsg, text ? 'Saved.' : 'Alt text cleared.');
                }, function (e) { say($aMsg, 'Could not save the alt text.', 'error'); throw e; });
            }
        });
        $('#ms-ai-q-save-btn').on('click', function () {
            if (!featured()) { say($aMsg, 'Set the featured image first.', 'warn'); return; }
            if (window.msSave) window.msSave.save();
        });

        // Tags: the ones that exist are added to the post; new ones are only listed
        var $tBtn = $('#ms-ai-q-tags-btn'), $tMsg = $('#ms-ai-q-tags-msg'), asked = false;
        $tBtn.on('click', function () {
            if (asked && !window.confirm('Asking for tags again uses extra API credit (about a cent). Continue?')) return;
            asked = true;
            run($tBtn, $tMsg, 'ms_ai_tags', 'Looking at the picture…', function (d) {
                var have = ed().getEditedPostAttribute('tags') || [], add = (d.ids || []).filter(function (i) { return have.indexOf(i) < 0; });
                if (add.length) wp.data.dispatch('core/editor').editPost({ tags: have.concat(add) });
                var newer = (d['new'] || []);
                say($tMsg, (add.length ? 'Added to the post: ' + (d.tags || []).join(', ') + ' - save the post to keep them. ' : 'No new tags to add. ') + (newer.length ? 'New tag ideas (add by hand if you want them): ' + newer.join(', ') + '. ' : '') + 'Asking again uses extra credit.', newer.length ? 'warn' : '');
            });
        });
    });
    </script>
    <?php
} );

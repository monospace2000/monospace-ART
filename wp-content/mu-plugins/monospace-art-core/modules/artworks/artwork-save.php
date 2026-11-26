<?php
/**
 * One save on an artwork's edit screen (2026-11-24, owner: "no more hunting for the right button"). The editor's blue
 * Save / Publish, Cmd-S, Update product and Save story all save everything that changed - and only that: a product is not
 * saved when only the post changed, nor the post when only the product did.
 *
 * Each part of the screen adds itself to window.msSaveParts:
 *   flush()   optional: put what is in its fields into the editor (so the post is saved with it).
 *             true = it changed the post, false = the owner said no (nothing is saved)
 *   dirty()   it has something to save that the post's own save does not cover
 *   save(ctx) save that; returns a promise and shows its own messages. ctx.create: Create product was clicked
 * The parts: the product (quick-artwork-editor.php), the story (artwork-story.php), alt text (the AI plugin).
 *
 * The editor runs "editor.savePost" after every save of the post (after the meta boxes), so the parts are saved there;
 * with nothing to save in the post, they are saved on their own.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_footer', function () {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || $screen->base !== 'post' || $screen->post_type !== 'post' ) return;
    ?>
    <style>
        /* The editor greys its button when the post is unchanged; the product or story may still need saving */
        body.ms-unsaved .editor-post-publish-button__button[aria-disabled="true"] { opacity: 1; cursor: pointer; background: var(--wp-admin-theme-color, #2271b1); color: #fff; }
        body.ms-unsaved .editor-post-saved-state { opacity: 1; cursor: pointer; color: var(--wp-admin-theme-color, #2271b1); }
    </style>
    <script>
    (function start() {
        if (!window.wp || !wp.data || !wp.hooks || !wp.data.select('core/editor')) { setTimeout(start, 200); return; }
        var ctx = {}, running = null, saving = false;
        function parts() { return window.msSaveParts || []; }
        function editor() { return wp.data.select('core/editor'); }
        function dirty() { return parts().some(function (p) { return p.dirty(); }); }
        // true: something was written into the post; false: the owner said no
        function flush() {
            var wrote = null, list = parts();
            for (var i = 0; i < list.length; i++) {
                var r = list[i].flush ? list[i].flush() : null;
                if (r === false) return false;
                if (r) wrote = true;
            }
            return wrote;
        }
        function notice(text) { wp.data.dispatch('core/notices').createInfoNotice(text, { type: 'snackbar', id: 'ms-save' }); }

        // Everything that is not in the post, one after the other; one part failing does not stop the next
        function saveParts() {
            if (running) return running;
            var now = ctx;
            ctx = {};
            running = parts().reduce(function (chain, p) {
                return chain.then(function () { return p.dirty() || now.create ? p.save(now) : null; }).catch(function () {});
            }, Promise.resolve()).then(function () { running = null; });
            return running;
        }
        wp.hooks.addAction('editor.savePost', 'monospace/one-save', function (post, options) {
            if (!options || !options.isAutosave) return saveParts();
        }, 20);

        function save(wanted) {
            if (saving || editor().isSavingPost() || editor().isPostSavingLocked()) return Promise.resolve();
            if (flush() === false) return Promise.resolve();
            saving = true;
            ctx = wanted || {};
            return new Promise(function (r) { setTimeout(r, 0); }).then(function () {
                if (editor().isEditedPostDirty()) return wp.data.dispatch('core/editor').savePost();
                if (dirty() || ctx.create) return saveParts();
                notice('Nothing has changed - nothing to save.');
            }).catch(function () {}).then(function () { saving = false; ctx = {}; });
        }
        window.msSave = { save: save };

        // Cmd-S / Ctrl-S. With nothing of ours to save, the editor's own shortcut is left to do its work
        function key(e) {
            if (!(e.metaKey || e.ctrlKey) || e.altKey || e.shiftKey || String(e.key).toLowerCase() !== 's') return;
            var wrote = flush();
            if (wrote === null && !dirty()) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            if (wrote !== false) save();
        }
        window.addEventListener('keydown', key, true);
        // The blue button while it is greyed out
        document.addEventListener('click', function (e) {
            var b = e.target.closest && e.target.closest('.editor-post-publish-button__button[aria-disabled="true"], .editor-post-saved-state');
            if (!b || !dirty()) return;
            e.preventDefault();
            e.stopImmediatePropagation();
            save();
        }, true);
        window.addEventListener('beforeunload', function (e) { if (dirty()) { e.preventDefault(); e.returnValue = ''; } });
        setInterval(function () {
            document.body.classList.toggle('ms-unsaved', dirty());
            // The writing area may be a frame of its own, with its own key presses
            var frame = document.querySelector('iframe[name="editor-canvas"]');
            if (frame && frame.contentWindow && !frame.contentWindow.msSaveKeys) {
                frame.contentWindow.msSaveKeys = true;
                frame.contentWindow.addEventListener('keydown', key, true);
            }
        }, 600);
    })();
    </script>
    <?php
}, 5 );

<?php
/**
 * The artwork's background story (2026-11-24, owner): the paragraphs between the More break and the price card, typed in
 * the block editor or typed / dictated in the story box. The post is the one place it is stored, so what the box saves is
 * what the block editor shows (owner, 2026-11-24: "so it's clear that it is being saved").
 *
 *   _ms_story_hidden  '1' = the story is not shown on the site; no meta = shown (the default)
 *   _ms_story         a story saved by the first version (its own field). Still shown while it is there; the story box
 *                     picks it up and Save story moves it into the post.
 *
 * The story's place: after the More block (a post without one: after its first picture) up to the price card (or the
 * "meta" synced block #428). Only paragraphs are the story; a quote or a picture there stays as it is. Blocks marked
 * "Hide this block" are left alone. The panel's script finds the same place in the editor - keep the two in step.
 *
 * monospace_story_fields() prints the box with Dictate (the browser's own speech recognition; the words go where the
 * cursor is) and the "Show the story on the site" switch - used by Artworks > New artwork and the Quick Artwork Editor.
 * A feature can add buttons beside Dictate with the action monospace_story_tools (the AI plugin: Clean up). The Artwork
 * Editor (bulk) has no story on purpose.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The first version's field */
function monospace_story_get( $post_id ) {
    return (string) get_post_meta( (int) $post_id, '_ms_story', true );
}

function monospace_story_is_hidden( $post_id ) {
    return get_post_meta( (int) $post_id, '_ms_story_hidden', true ) === '1';
}

function monospace_story_set_show( $post_id, $show ) {
    if ( $show ) delete_post_meta( $post_id, '_ms_story_hidden' );
    else update_post_meta( $post_id, '_ms_story_hidden', '1' );
}

function monospace_story_is_end_block( array $block ) {
    return strpos( (string) $block['innerHTML'], '[price_card' ) !== false
        || ( $block['blockName'] === 'core/block' && (int) ( $block['attrs']['ref'] ?? 0 ) === 428 );
}

/** Which of parse_blocks()' blocks are the story's paragraphs: their keys. Empty when the content has no story place. */
function monospace_story_block_keys( array $blocks ) {
    $more = $picture = $end = null;
    foreach ( $blocks as $i => $b ) {
        if ( $more === null && $b['blockName'] === 'core/more' ) $more = $i;
        if ( $picture === null && in_array( $b['blockName'], [ 'core/image', 'core/gallery' ], true ) ) $picture = $i;
    }
    $from = $more ?? $picture ?? -1;
    foreach ( $blocks as $i => $b ) {
        if ( $i > $from && monospace_story_is_end_block( $b ) ) { $end = $i; break; }
    }
    if ( $more === null && $end === null ) return []; // a feed teaser, or not an artwork's shape
    $keys = [];
    foreach ( $blocks as $i => $b ) {
        if ( $i > $from && ( $end === null || $i < $end ) && $b['blockName'] === 'core/paragraph' && empty( $b['attrs']['msHidden'] ) ) $keys[] = $i;
    }
    return $keys;
}

/** The content with the story's paragraphs left out */
function monospace_story_without( $content ) {
    $blocks = parse_blocks( $content );
    $keys   = monospace_story_block_keys( $blocks );
    if ( ! $keys ) return $content;
    foreach ( $keys as $i ) unset( $blocks[ $i ] );
    return serialize_blocks( array_values( $blocks ) );
}

/** The post's content as the site shows it - for descriptions, excerpts and search */
function monospace_story_public_content( $post ) {
    $post = get_post( $post );
    if ( ! $post ) return '';
    if ( monospace_story_is_hidden( $post->ID ) ) return monospace_story_without( $post->post_content );
    $old = trim( monospace_story_get( $post->ID ) );
    return $old === '' ? $post->post_content : $post->post_content . ' ' . esc_html( $old );
}

/** The first version's field as paragraph blocks */
function monospace_story_blocks( $text ) {
    $out = '';
    foreach ( preg_split( '/\R\s*\R/', trim( $text ) ) as $paragraph ) {
        $paragraph = trim( $paragraph );
        if ( $paragraph === '' ) continue;
        // [ is written as an entity so words in brackets never run as a shortcode
        $out .= "<!-- wp:paragraph -->\n<p>" . str_replace( '[', '&#91;', nl2br( esc_html( $paragraph ), false ) ) . "</p>\n<!-- /wp:paragraph -->\n\n";
    }
    return $out;
}

// Before do_blocks (9), while the content still has its block comments and the [price_card] shortcode as written
add_filter( 'the_content', function ( $content ) {
    $post = get_post();
    if ( ! $post || $post->post_type !== 'post' || post_password_required( $post ) ) return $content;
    // Switched off: the paragraphs stay in the post and are left out of the page (the editor is not touched)
    if ( monospace_story_is_hidden( $post->ID ) ) return monospace_story_without( $content );

    $old = trim( monospace_story_get( $post->ID ) );
    if ( $old === '' ) return $content;
    // Before the price card; else before the "meta" synced block. A feed teaser (cut at More) has neither.
    foreach ( [ '/(?:<!--\s*wp:shortcode\s*-->\s*)?\[price_card\b/', '/<!--\s*wp:block\s*\{"ref":428\}\s*\/-->/' ] as $pattern ) {
        if ( preg_match( $pattern, $content, $m, PREG_OFFSET_CAPTURE ) ) {
            return substr( $content, 0, $m[0][1] ) . monospace_story_blocks( $old ) . substr( $content, $m[0][1] );
        }
    }
    if ( is_singular() && in_the_loop() && is_main_query() && get_queried_object_id() === $post->ID ) {
        return $content . "\n\n" . monospace_story_blocks( $old );
    }
    return $content;
}, 8 );

// Site search: a switched-off story is not found
add_filter( 'relevanssi_post_content', function ( $content, $post ) {
    return $post && $post->post_type === 'post' && monospace_story_is_hidden( $post->ID ) ? monospace_story_without( $content ) : $content;
}, 10, 2 );
add_filter( 'relevanssi_content_to_index', function ( $content, $post ) {
    return $post && $post->post_type === 'post' && ! monospace_story_is_hidden( $post->ID ) ? $content . ' ' . monospace_story_get( $post->ID ) : $content;
}, 10, 2 );

/** [ state, chip text ] for a title line */
function monospace_story_state( $post_id ) {
    $blocks = parse_blocks( (string) get_post_field( 'post_content', $post_id ) );
    $has    = trim( monospace_story_get( $post_id ) ) !== '';
    foreach ( monospace_story_block_keys( $blocks ) as $i ) if ( trim( wp_strip_all_tags( $blocks[ $i ]['innerHTML'] ) ) !== '' ) $has = true;
    if ( ! $has ) return [ 'empty', 'Empty' ];
    return monospace_story_is_hidden( $post_id ) ? [ 'off', 'Not shown' ] : [ 'on', 'Shown' ];
}

/**
 * The story field. $args: id (the textarea's id), text, show (bool), named (true = send as `story` and `story_show`
 * with the surrounding form).
 */
function monospace_story_fields( array $args = [] ) {
    $a = $args + [ 'id' => 'ms-story-text', 'text' => '', 'show' => true, 'named' => false ];
    ?>
    <div class="ms-story-box">
        <textarea id="<?php echo esc_attr( $a['id'] ); ?>" class="ms-story-text" <?php if ( $a['named'] ) echo 'name="story" '; ?>rows="9" aria-label="Story" placeholder="Where it was made, what you remember… A blank line starts a new paragraph."><?php echo esc_textarea( $a['text'] ); ?></textarea>
        <div class="ms-story-tools">
            <button type="button" class="button ms-story-dictate" aria-pressed="false" hidden><span class="dashicons dashicons-microphone" aria-hidden="true"></span><span class="ms-story-dictate-label">Dictate</span></button>
            <?php do_action( 'monospace_story_tools' ); // a feature can add its buttons here (the AI plugin: Clean up) ?>
        </div>
        <p class="ms-story-nodictate" hidden>Dictation isn't available in this browser. Tap in the box and use the microphone key on your keyboard (or type).</p>
        <p class="ms-story-msg" aria-live="polite"></p>
        <label class="ms-story-show">
            <?php if ( $a['named'] ) : ?><input type="hidden" name="story_show" value="0"><?php endif; ?>
            <input type="checkbox" class="ms-story-show-box" <?php if ( $a['named'] ) echo 'name="story_show" value="1" '; ?><?php checked( $a['show'] ); ?>>
            Show the story on the site
        </label>
    </div>
    <?php
    monospace_story_assets();
}

/** Styles and the dictation script, once per page */
function monospace_story_assets() {
    static $done = false;
    if ( $done ) return;
    $done = true;
    ?>
    <style>
        .ms-story-box textarea.ms-story-text { width: 100%; max-width: 100%; box-sizing: border-box; font-size: 14px; line-height: 1.5; }
        .ms-story-box textarea.ms-story-text[readonly] { background: #f6f7f7; }
        .ms-story-tools { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 8px 0 0; }
        .ms-story-box .ms-story-tools .button { min-height: 36px; display: inline-flex; align-items: center; gap: 5px; margin: 0; }
        .ms-story-box .ms-story-tools .button[hidden] { display: none; }
        .ms-story-box .ms-story-tools .button .dashicons { width: 18px; height: 18px; font-size: 18px; line-height: 1; margin: 0; color: inherit; vertical-align: middle; }
        .ms-story-box .ms-story-dictate.is-listening { background: #b32d2e; border-color: #b32d2e; color: #fff; }
        .ms-story-msg, .ms-story-nodictate { margin: 6px 0 0; font-size: 12px; color: #646970; line-height: 1.4; }
        .ms-story-msg:empty { display: none; }
        .ms-story-msg.is-error { color: #b32d2e; }
        .ms-story-msg.is-warn { color: #8a5a00; }
        .ms-story-box .ms-story-show { display: flex; align-items: center; gap: 6px; margin: 10px 0; }
        @media (max-width: 782px) {
            .ms-story-box textarea.ms-story-text { font-size: 16px; } /* smaller makes iOS zoom the page */
            .ms-story-box .ms-story-tools .button, .ms-story-box .ms-story-show { min-height: 44px; }
        }
    </style>
    <script>
    (function () {
        if (window.msStory) return;
        var Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        var live = null; // the box being dictated into: { box, rec, wanted, heard }

        function say(box, text, kind) {
            var el = box.querySelector('.ms-story-msg');
            el.textContent = text;
            el.className = 'ms-story-msg' + (kind ? ' is-' + kind : '');
        }
        function changed(ta) { ta.dispatchEvent(new Event('input', { bubbles: true })); }
        // While listening or cleaning up, nothing else may write into the box
        function busy(box, on) {
            box.classList.toggle('is-busy', on);
            box.querySelector('.ms-story-text').readOnly = on;
            box.querySelectorAll('.ms-story-tools button:not(.ms-story-dictate)').forEach(function (b) { b.disabled = on; });
        }
        function button(box, on) {
            var b = box.querySelector('.ms-story-dictate');
            b.classList.toggle('is-listening', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
            b.querySelector('.ms-story-dictate-label').textContent = on ? 'Stop' : 'Dictate';
        }
        var errors = {
            'not-allowed': 'The microphone is not allowed for this site. Allow it in the browser\'s site settings, then tap Dictate again.',
            'service-not-allowed': 'Dictation is switched off or not offered here (iPhone and iPad: Settings > General > Keyboard > Enable Dictation). You can use the microphone key on the keyboard instead.',
            'audio-capture': 'No microphone was found.',
            'network': 'Dictation needs a connection and could not reach the speech service.',
            'no-speech': 'Nothing heard. Tap Dictate to try again.'
        };

        function finish(box, text, kind) {
            if (!live || live.box !== box) return;
            var stopped = !live.wanted; // Stop was tapped (not a pause or an error)
            live = null;
            busy(box, false);
            button(box, false);
            if (text !== undefined) say(box, text, kind);
            box.dispatchEvent(new CustomEvent('ms-story-done', { bubbles: true, detail: { stopped: stopped } }));
        }

        function trace(box, what) { box.dispatchEvent(new CustomEvent('ms-story-trace', { bubbles: true, detail: what })); }

        // One recognition session. Browsers end a session after a pause, so a new one follows until Stop is tapped.
        function listen(state) {
            var box = state.box, ta = box.querySelector('.ms-story-text'), rec = new Recognition(), failed = '';
            // The words go where the cursor is (a selection is replaced); never placed = the end
            var head = ta.value.slice(0, ta.selectionStart), tail = ta.value.slice(ta.selectionEnd);
            state.rec = rec;
            state.heard = false;
            rec.lang = 'en-US';
            rec.continuous = true;
            rec.interimResults = true;
            // The whole session's words are rebuilt each time, so nothing is added twice
            rec.onresult = function (e) {
                var parts = [];
                for (var i = 0; i < e.results.length; i++) {
                    var t = e.results[i][0].transcript.trim();
                    if (!t) continue;
                    // iOS Safari can hand the same sentence over again, or again but longer: keep one (three words or more)
                    var last = parts.length ? parts[parts.length - 1].toLowerCase() : '';
                    if (last.split(' ').length > 2 && (t.toLowerCase() + ' ').indexOf(last + ' ') === 0) parts[parts.length - 1] = t;
                    else parts.push(t);
                }
                trace(box, 'result ' + e.results.length);
                if (!parts.length) return;
                state.heard = true;
                var said = (head && !/\s$/.test(head) ? ' ' : '') + parts.join(' '), gap = tail && !/^[\s.,;:!?)]/.test(tail) ? ' ' : '';
                ta.value = head + said + gap + tail;
                ta.setSelectionRange((head + said).length, (head + said).length);
                if (!tail) ta.scrollTop = ta.scrollHeight;
                changed(ta);
            };
            // What the browser's recognizer does, for a screen that wants to show it (the terminal in practice mode)
            ['start', 'audiostart', 'speechstart', 'speechend', 'nomatch'].forEach(function (n) { rec['on' + n] = function () { trace(box, n); }; });
            rec.onerror = function (e) { trace(box, 'error ' + e.error); if (e.error !== 'aborted') failed = e.error; };
            rec.onend = function () {
                trace(box, 'end');
                if (!live || live !== state || state.rec !== rec) return;
                if (state.wanted && !failed && state.heard) {
                    try { listen(state); return; } catch (err) {}
                }
                if (failed) finish(box, errors[failed] || ('Dictation stopped (' + failed + ').'), failed === 'no-speech' ? 'warn' : 'error');
                else finish(box, state.wanted ? 'Dictation paused. Tap Dictate to go on.' : 'Done. Check the words, then edit or clean up.');
            };
            rec.start();
        }

        function stop() {
            if (!live) return;
            var state = live;
            state.wanted = false;
            try { state.rec.stop(); } catch (e) {}
            // Some browsers never send "end" after stop
            setTimeout(function () {
                if (live !== state) return;
                trace(state.box, 'no end after stop, aborting');
                finish(state.box, 'Done. Check the words, then edit or clean up.');
            }, 1500);
        }

        function start(box) {
            if (live) { stop(); return; }
            live = { box: box, wanted: true };
            busy(box, true);
            button(box, true);
            say(box, 'Listening… tap Stop when you are done.');
            try { listen(live); } catch (e) { finish(box, 'Dictation could not start. Use the microphone key on the keyboard instead.', 'error'); }
        }

        window.msStory = {
            say: say,
            busy: busy,
            changed: changed,
            // Show the Dictate button, or the note for a browser without speech recognition
            init: function () {
                document.querySelectorAll('.ms-story-box:not([data-ready])').forEach(function (box) {
                    box.setAttribute('data-ready', '1');
                    box.querySelector('.ms-story-dictate').hidden = !Recognition;
                    box.querySelector('.ms-story-nodictate').hidden = !!Recognition;
                });
            }
        };
        document.addEventListener('click', function (e) {
            var b = e.target.closest && e.target.closest('.ms-story-dictate');
            if (b) { e.preventDefault(); start(b.closest('.ms-story-box')); }
        });
        // Sending the form (Create draft) ends dictation
        document.addEventListener('submit', stop, true);
        window.addEventListener('pagehide', stop);
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', window.msStory.init);
        setTimeout(window.msStory.init, 0);
    })();
    </script>
    <?php
}

/* ============================================================
 * The Quick Artwork Editor's panel: the box is filled from the post in the editor and writes back into it; saving is the
 * screen's one save (artwork-save.php)
 * ============================================================ */

add_action( 'monospace_quick_editor_sections', function ( $post ) {
    // Artworks (and posts being made into one): the box needs the story's place, which a blog post does not have
    if ( ! get_post_meta( $post->ID, '_qp_product_id', true ) && strpos( $post->post_content, '[price_card' ) === false ) return;
    [ $state, $chip ] = monospace_story_state( $post->ID );
    ?>
    <details class="qp-sec qp-sec--story" id="ms-story-q" data-post="<?php echo (int) $post->ID; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'monospace_story_' . $post->ID ) ); ?>" data-old="<?php echo esc_attr( trim( monospace_story_get( $post->ID ) ) ); ?>">
        <summary class="qp-sec-head"><span class="qp-sec-title">Story</span><span class="qp-chip is-<?php echo esc_attr( $state ); ?>" id="ms-story-q-chip"><?php echo esc_html( $chip ); ?></span><span class="qp-sec-sub">The text between the More break and the price card</span></summary>
        <div class="qp-sec-body">
            <p class="description">Click where you want to add words, then Dictate or type. The words go into the post above; any Save button (or Cmd-S) saves everything.</p>
            <?php monospace_story_fields( [ 'id' => 'ms-story-q-text', 'show' => ! monospace_story_is_hidden( $post->ID ) ] ); ?>
            <p><button type="button" class="button button-primary" id="ms-story-q-save">Save story</button> <span id="ms-story-q-saved" aria-live="polite" style="font-size:12px;color:#646970"></span></p>
        </div>
    </details>
    <script>
    (function () {
        var panel = document.getElementById('ms-story-q');
        if (!panel) return;
        var box = panel.querySelector('.ms-story-box'), ta = box.querySelector('.ms-story-text'), show = box.querySelector('.ms-story-show-box'),
            btn = document.getElementById('ms-story-q-save'), note = document.getElementById('ms-story-q-saved'), chip = document.getElementById('ms-story-q-chip');
        var old = panel.dataset.old || '';     // a story saved by the first version, to be moved into the post
        var base = '', loaded = false, writing = false, shown = show.checked;

        function say(text, color) { note.style.color = color || '#646970'; note.textContent = text; }
        function paragraphs(text) { return text.split(/\n\s*\n/).map(function (p) { return p.trim(); }).filter(Boolean); }
        function plain(html) {
            var source = String(html || '').replace(/\r?\n[ \t]*/g, ' ').replace(/<br[^>]*>/gi, '\n');
            return new DOMParser().parseFromString(source, 'text/html').body.textContent.replace(/[ \t]*\n[ \t]*/g, '\n').trim();
        }
        function isEnd(b) {
            if (b.name === 'core/block') return +b.attributes.ref === 428;
            var text = b.name === 'core/shortcode' ? b.attributes.text : (b.name === 'core/paragraph' ? b.attributes.content : '');
            return String(text || '').indexOf('[price_card') > -1;
        }
        // The story's place in the editor - the same rule as monospace_story_block_keys()
        function region() {
            var blocks = wp.data.select('core/block-editor').getBlocks();
            function first(names) { for (var i = 0; i < blocks.length; i++) if (names.indexOf(blocks[i].name) > -1) return i; return -1; }
            var more = first(['core/more']), from = more > -1 ? more : first(['core/image', 'core/gallery']), end = blocks.length;
            for (var i = from + 1; i < blocks.length; i++) if (isEnd(blocks[i])) { end = i; break; }
            var list = blocks.slice(from + 1, end).filter(function (b) { return b.name === 'core/paragraph' && !b.attributes.msHidden && plain(b.attributes.content) !== ''; });
            return { list: list, end: end, text: list.map(function (b) { return plain(b.attributes.content); }).join('\n\n') };
        }
        function ready() {
            var ed = window.wp && wp.data && wp.data.select('core/editor');
            var post = ed && ed.getCurrentPost && ed.getCurrentPost();
            return !!(post && +post.id === +panel.dataset.post && wp.data.select('core/block-editor').getBlocks().length);
        }
        // The story's part of the one save (artwork-save.php): the box writes into the editor, the post carries it
        var typing = null;
        function boxText() { return paragraphs(ta.value).join('\n\n'); }
        // The box follows the post until the owner changes something in the box
        function sync() {
            if (writing || box.classList.contains('is-busy') || !ready()) return;
            var now = region().text;
            if (!loaded) {
                loaded = true;
                base = now;
                ta.value = old && now.indexOf(old) < 0 ? (now ? now + '\n\n' : '') + old : now;
                mark();
            } else if (now !== base && document.activeElement !== ta && boxText() === base) {
                base = now;
                ta.value = now;
                chipNow();
            }
        }
        function unwritten() { return loaded && boxText() !== base; }
        function chipNow() {
            var has = !!base || !!old, state = !has ? 'empty' : (show.checked ? 'on' : 'off');
            chip.className = 'qp-chip is-' + state;
            chip.textContent = !has ? 'Empty' : (show.checked ? 'Shown' : 'Not shown');
        }
        function mark() {
            if (!loaded) return;
            if (unwritten() || show.checked !== shown) say('Not saved yet', '#8a5a00');
            else if (old) say('Includes the story saved earlier - saving puts it into the post.', '#8a5a00');
            else if (wp.data.select('core/editor').isEditedPostDirty()) say('In the post above - not saved yet', '#8a5a00');
            else say('');
        }

        function newBlock(text) { return wp.blocks.createBlock('core/paragraph', { content: html(text) }); }
        function html(text) {
            var el = document.createElement('div');
            el.textContent = text;
            return el.innerHTML.replace(/\n/g, '<br>');
        }
        // Writes the box into the editor. Only the paragraphs that differ are touched, so the others keep their links and
        // styling, and anything between them (a quote, a picture) stays where it is. quiet: never ask - leave it for later.
        // Returns true (the post changed), null (nothing to write) or false (not written)
        function write(quiet) {
            if (!loaded || !ready() || box.classList.contains('is-busy') || !unwritten()) return null;
            var now = region(), parts = paragraphs(ta.value);
            if (parts.join('\n\n') === now.text) { base = now.text; return null; }
            var was = now.list.map(function (b) { return plain(b.attributes.content); });
            var head = 0, tail = 0;
            while (head < was.length && head < parts.length && was[head] === parts[head]) head++;
            while (tail < was.length - head && tail < parts.length - head && was[was.length - 1 - tail] === parts[parts.length - 1 - tail]) tail++;
            var oldMid = now.list.slice(head, was.length - tail), newMid = parts.slice(head, parts.length - tail);
            var lost = oldMid.some(function (b) { return /<(a|strong|em|b|i|s|u|span|sup|sub|mark|code|img)\b/i.test(String(b.attributes.content)); });
            if (quiet && (lost || now.text !== base || (!parts.length && was.length))) return false;
            if (now.text !== base && !window.confirm('The text in the post changed after this box was filled. Replace it with what is in the box?')) return false;
            if (!parts.length && was.length && !window.confirm('The box is empty. Remove the story from the post?')) return false;
            if (parts.length && lost && !window.confirm('A paragraph you changed or removed has a link or styling. Saving from this box removes it (to keep it, edit that paragraph in the post itself). Continue?')) return false;

            writing = true;
            var store = wp.data.dispatch('core/block-editor'), editor = wp.data.select('core/block-editor'), i;
            var pairs = Math.min(oldMid.length, newMid.length);
            for (i = 0; i < pairs; i++) store.updateBlockAttributes(oldMid[i].clientId, { content: html(newMid[i]) });
            if (oldMid.length > pairs) store.removeBlocks(oldMid.slice(pairs).map(function (b) { return b.clientId; }), false);
            if (newMid.length > pairs) {
                var at = now.end;
                if (pairs) at = editor.getBlockIndex(oldMid[pairs - 1].clientId) + 1;
                else if (head) at = editor.getBlockIndex(now.list[head - 1].clientId) + 1;
                else if (tail) at = editor.getBlockIndex(now.list[was.length - tail].clientId);
                store.insertBlocks(newMid.slice(pairs).map(newBlock), at, undefined, false);
            }
            base = region().text;
            writing = false;
            chipNow();
            mark();
            return true;
        }
        // A pause in typing (or the end of dictating or cleaning up) puts the words into the post
        function later() {
            clearTimeout(typing);
            typing = setTimeout(function () { if (box.classList.contains('is-busy')) later(); else write(true); }, 900);
        }
        ta.addEventListener('input', function () { mark(); later(); });
        ta.addEventListener('blur', function () { if (!box.classList.contains('is-busy')) write(false); });
        show.addEventListener('change', function () { chipNow(); mark(); });
        btn.addEventListener('click', function () { if (window.msSave) window.msSave.save(); });

        (window.msSaveParts = window.msSaveParts || []).push({
            flush: function () { clearTimeout(typing); return write(false); },
            dirty: function () { return loaded && (unwritten() || show.checked !== shown || !!old); },
            // The post is saved by now; only the switch (and the first version's copy) are the story's own to save
            save: function () {
                if (show.checked === shown && !old) { mark(); return null; }
                if (unwritten()) return null;
                var showNow = show.checked, data = new FormData();
                data.append('action', 'monospace_story_save');
                data.append('_ajax_nonce', panel.dataset.nonce);
                data.append('post_id', panel.dataset.post);
                data.append('show', showNow ? '1' : '0');
                return fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: data })
                    .then(function (r) { return r.json(); })
                    .then(function (r) {
                        if (!r || !r.success) throw new Error((r && r.data) || 'Could not save.');
                        old = '';
                        shown = showNow;
                        chip.className = 'qp-chip is-' + r.data.state;
                        chip.textContent = r.data.chip;
                        say(showNow ? 'Saved.' : 'Saved. The story is not shown on the site.');
                    })
                    .catch(function (e) { say((e && e.message) || 'Could not save.', '#b32d2e'); throw e; });
            }
        });

        (function start() {
            if (!window.wp || !wp.data) { setTimeout(start, 200); return; }
            var wasDirty = false;
            wp.data.subscribe(function () {
                sync();
                var ed = wp.data.select('core/editor'), isDirty = !!(ed && ed.isEditedPostDirty && ed.isEditedPostDirty());
                if (wasDirty && !isDirty && note.textContent.indexOf('In the post above') === 0) say('Saved.');
                wasDirty = isDirty;
            });
            sync();
        })();
    })();
    </script>
    <?php
}, 5 );

add_action( 'wp_ajax_monospace_story_save', function () {
    $post_id = absint( $_POST['post_id'] ?? 0 );
    check_ajax_referer( 'monospace_story_' . $post_id );
    if ( ! $post_id || get_post_type( $post_id ) !== 'post' || ! current_user_can( 'edit_post', $post_id ) ) wp_send_json_error( 'Not allowed.' );
    // The text itself was saved with the post, by the editor
    monospace_story_set_show( $post_id, ( $_POST['show'] ?? '1' ) !== '0' );
    delete_post_meta( $post_id, '_ms_story' );

    // The switch alone is not a post save, so the page cache and the search index don't update by themselves
    if ( get_post_status( $post_id ) === 'publish' ) {
        if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache( get_permalink( $post_id ) );
        if ( function_exists( 'relevanssi_index_doc' ) && function_exists( 'relevanssi_get_custom_fields' ) ) relevanssi_index_doc( $post_id, true, relevanssi_get_custom_fields(), true );
    }
    [ $state, $chip ] = monospace_story_state( $post_id );
    wp_send_json_success( [ 'state' => $state, 'chip' => $chip ] );
} );

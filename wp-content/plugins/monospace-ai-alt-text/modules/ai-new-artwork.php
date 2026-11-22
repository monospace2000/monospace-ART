<?php
/**
 * The buttons on Artworks > New artwork (owner, 2026-11-15): "Generate alt text" fills the Alt text box under the image (the
 * box stays editable; "Regenerate alt text" asks for another version) and "Suggest tags" adds tags from the site's own list as
 * chips (each can be removed with its x). Nothing is saved until Create draft. Core's screen offers two hooks for this
 * (monospace_new_artwork_alt_tools, monospace_new_artwork_tag_tools); the screen and its Alt text box work without this plugin.
 *
 * @package monospace-ai-alt-text
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function ms_ai_on_new_artwork_screen() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    return $screen && $screen->id === 'toplevel_page_monospace-artworks';
}

add_action( 'monospace_new_artwork_alt_tools', function () {
    if ( ms_ai_key() === '' ) {
        echo '<p class="ms-na-help">Claude can write this for you once an API key is set up (Artworks &rarr; AI writing).</p>';
        return;
    }
    echo '<p class="ms-ai-tools"><button type="button" class="button" id="ms-ai-alt-btn">Generate alt text</button> <span class="ms-ai-msg" id="ms-ai-alt-msg" aria-live="polite"></span></p>';
} );

add_action( 'monospace_new_artwork_tag_tools', function () {
    if ( ms_ai_key() === '' ) return;
    echo '<p class="ms-ai-tools"><button type="button" class="button" id="ms-ai-tags-btn">Suggest tags</button> <span class="ms-ai-msg" id="ms-ai-tags-msg" aria-live="polite"></span></p>';
} );

add_action( 'admin_footer', function () {
    if ( ! ms_ai_on_new_artwork_screen() || ms_ai_key() === '' ) return;
    ?>
    <style>
        .ms-na .ms-ai-tools { margin: 6px 0 0; display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .ms-na .ms-ai-msg { font-size: 12px; color: #646970; line-height: 1.4; }
        .ms-na .ms-ai-msg.is-error { color: #b32d2e; }
        .ms-na .ms-ai-msg.is-warn { color: #8a5a00; }
    </style>
    <script>
    jQuery(function ($) {
        var nonce = <?php echo wp_json_encode( wp_create_nonce( 'ms_ai' ) ); ?>;

        function facts(action) {
            var cats = [], tags = [], medium = $('[data-taxonomy="pa_medium"]').val() ? $.trim($('[data-taxonomy="pa_medium"] option:selected').text()) : '',
                format = $('[data-taxonomy="pa_format"]').val() ? $.trim($('[data-taxonomy="pa_format"] option:selected').text()) : '';
            $('input[name="look[]"]:checked').each(function () { cats.push($.trim($(this).parent().text())); });
            $('#ms-na-auto-cats strong').each(function () { cats.push($.trim($(this).text())); });
            $('#ms-na-tags input[name="tags[]"]').each(function () { tags.push(this.value); });
            return { action: action, _ajax_nonce: nonce, image_id: $('#ms-na-image-id').val(), title: $.trim($('#ms-na-title').val()), medium: medium, format: format, categories: cats, tags: tags };
        }
        function say($el, text, kind) { $el.text(text).removeClass('is-error is-warn').addClass(kind || ''); }
        function run($btn, $msg, action, busy, done) {
            if (!$('#ms-na-image-id').val()) { say($msg, 'Choose the image first.', 'is-warn'); return; }
            $btn.prop('disabled', true); say($msg, busy);
            $.post(ajaxurl, facts(action))
                .done(function (r) { if (r && r.success) done(r.data); else say($msg, (r && r.data) || 'Something went wrong.', 'is-error'); })
                .fail(function (x) { say($msg, 'Request failed (' + x.status + ').', 'is-error'); })
                .always(function () { $btn.prop('disabled', false); });
        }

        // Alt text: fills the box, which stays editable; the button then says Regenerate alt text
        var $alt = $('#ms-na-alt'), $altBtn = $('#ms-ai-alt-btn'), $altMsg = $('#ms-ai-alt-msg');
        $alt.on('input', function () { $alt.data('ai', false); });
        $('#ms-na-image-id').on('change', function () { $altBtn.text('Generate alt text'); say($altMsg, ''); });
        // Every request is paid API credit (about a cent): trying again asks first (owner, 2026-11-15)
        var AGAIN = 'Regenerate alt text asks Claude again and uses extra API credit (about a cent each time). Continue?';
        $altBtn.on('click', function () {
            var again = $altBtn.text() === 'Regenerate alt text';
            if (again && !window.confirm(AGAIN + ($.trim($alt.val()) ? '\n\nThe text in the box will be replaced.' : ''))) return;
            if (!again && $.trim($alt.val()) && !$alt.data('ai') && !window.confirm('Replace the alt text in the box with a new one?')) return;
            run($altBtn, $altMsg, 'ms_ai_alt', 'Writing…', function (d) {
                $alt.val(d.alt).data('ai', true).trigger('change');
                $altBtn.text('Regenerate alt text').attr('title', 'Uses extra API credit (about a cent)');
                say($altMsg, (d.uncertain ? 'Written - Claude was unsure about something, please read it.' : 'Written - edit it if you like.') + ' Regenerate alt text uses extra credit.', d.uncertain ? 'is-warn' : '');
            });
        });

        // Tags: added as chips through the screen's own tag box (blur adds what is typed)
        var $tagsBtn = $('#ms-ai-tags-btn'), $tagsMsg = $('#ms-ai-tags-msg');
        var tagsAsked = false;
        $tagsBtn.on('click', function () {
            if (tagsAsked && !window.confirm('Asking for tags again uses extra API credit (about a cent). Continue?')) return;
            tagsAsked = true;
            run($tagsBtn, $tagsMsg, 'ms_ai_tags', 'Looking at the picture…', function (d) {
                var all = (d.tags || []).concat(d['new'] || []);
                all.forEach(function (t) { $('#ms-na-tag-input').val(t).trigger('blur'); });
                if (!all.length) { say($tagsMsg, 'No new tags to suggest.'); return; }
                say($tagsMsg, 'Added: ' + (d.tags || []).join(', ') + ((d['new'] || []).length ? (d.tags.length ? '. ' : '') + 'New tags (check them): ' + d['new'].join(', ') : '') + '. Remove any with its x. Asking again uses extra credit.', (d['new'] || []).length ? 'is-warn' : '');
            });
        });
    });
    </script>
    <?php
} );

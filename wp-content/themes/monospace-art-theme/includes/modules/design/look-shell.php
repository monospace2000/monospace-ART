<?php
/**
 * Look screen: Light and Dark side by side, and saving both (2026-10-22, owner: "switching between Light and Dark should be
 * instantaneous, preview and all" ... "the Save is still super ambiguous ... add a button to save both modes at once, and a
 * smaller button to save Light and Dark separately").
 *
 * Appearance > Look is now a shell around two copies of the screen (look.php, unchanged): the Light one and the Dark one
 * (?ms_embed=1, with and without &mode=dark), both loaded when the page opens, each with its own preview frame, form and
 * scripts. The Light / Dark tabs only show one and hide the other - nothing reloads, so the switch is instant, preview and
 * all. Their own headings, notices and Apply bar are hidden (embed styles below); the shell has the tabs, the Dark mode for
 * visitors choice and the save buttons:
 *   Save Light & Dark   saves both looks (the one you are looking at last, so it wins where a linked panel differs)
 *   Save Light only / Save Dark only   one look - its 🔗 linked panels (look-links.php) still go to the other look
 * Each tab shows a dot while it has changes that aren't saved; the buttons say what they will do.
 *
 * Linked panels (🔗): the shell keeps them equal on both screens while you edit - a linked panel changed on one screen is copied
 * to the other (twice a second, and when you switch tab or save), so what you see on either tab is what Save will write.
 * (The server copies a saved screen's linked panels to the other look as before - look-links.php.)
 * Dark mode for visitors (Off / Switch only / Automatic...) is set in the shell and goes along with whichever save you press.
 *
 * ?classic=1 opens the old single screen (Light, or Dark with &mode=dark).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** This request is one of the shell's two embedded screens */
function monospace_look_embed() {
    return is_admin() && ( ( $_GET['page'] ?? '' ) === 'monospace-look' && ! empty( $_GET['ms_embed'] ) || ! empty( $_POST['ms_embed'] ) );
}

/** The shell instead of the single screen? */
function monospace_look_use_shell() {
    return function_exists( 'monospace_dark_settings' ) && empty( $_GET['ms_embed'] ) && empty( $_GET['classic'] );
}

// An embedded screen: no admin menu, bar or headings - the shell has them
add_action( 'admin_head', function () {
    if ( ! monospace_look_embed() ) return; ?>
    <style>
        html.wp-toolbar { padding-top: 0 !important; }
        #wpadminbar, #adminmenumain, #adminmenuback, #adminmenuwrap, #wpfooter, .update-nag, #screen-meta-links,
        .ms-look > h1, .ms-look > .lm-where, .ms-look > .notice, .wrap > .notice, .ms-dark-settings, .ms-dark-tabs, .ms-look-save { display: none !important; }
        #wpcontent { margin-left: 0 !important; padding-left: 0 !important; }
        #wpbody-content { padding-bottom: 0 !important; }
        .ms-look.wrap { margin: 0 10px; }
        .ms-spot-preview { top: 8px !important; }
        body { background: #f0f0f1; }
    </style>
    <?php
} );
add_filter( 'admin_body_class', fn( $c ) => monospace_look_embed() ? $c . ' ms-look-embed' : $c );

// The embedded screen's form says so, so its save lands back in the shell's frame
add_action( 'monospace_look_save_buttons', function () {
    if ( monospace_look_embed() ) echo '<input type="hidden" name="ms_embed" value="1">';
}, 4 );
add_action( 'admin_post_monospace_look', function () {
    if ( empty( $_POST['ms_embed'] ) ) return;
    add_filter( 'wp_redirect', fn( $to ) => strpos( $to, 'monospace-look' ) !== false ? add_query_arg( 'ms_embed', 1, $to ) : $to, 5 );
}, 0 );

function monospace_look_shell() {
    $base    = admin_url( 'themes.php?page=monospace-look&ms_embed=1' );
    $dark    = ( $_GET['mode'] ?? '' ) === 'dark';
    $s       = monospace_dark_settings();
    $now     = monospace_dark_choice( $s );
    $choices = monospace_dark_choices(); ?>
    <div class="wrap ms-look-shell">
        <h1>Look</h1>
        <p class="lm-where" style="max-width:900px">The look of the whole site in one place, with the site itself as a live preview. Light and Dark are both open - switch between them
            as often as you like. A 🔗 panel is the same in both looks: change it on one tab and it changes on the other. Nothing goes live until you save.
            Where the logo and the shop button lead, and the viewing toolbar: <a href="<?php echo esc_url( admin_url( 'admin.php?page=monospace-layout-manager&tab=links' ) ); ?>">Layout Manager &gt; Links &amp; toolbar</a>.</p>

        <div class="ms-shell-head">
            <div class="ms-shell-tabs" role="tablist">
                <button type="button" class="button<?php echo $dark ? '' : ' button-primary'; ?>" role="tab" data-mode="light">☀ Light look <i class="ms-shell-dot" hidden title="Unsaved changes"></i></button><button type="button" class="button<?php echo $dark ? ' button-primary' : ''; ?>" role="tab" data-mode="dark">☾ Dark look <i class="ms-shell-dot" hidden title="Unsaved changes"></i></button>
            </div>
            <div class="ms-shell-visitors">
                <strong>Dark mode for visitors:</strong>
                <?php foreach ( $choices as $key => [ $label, , $tip ] ) : ?>
                    <label title="<?php echo esc_attr( $tip ); ?>"><input type="radio" name="ms_shell_dark_choice" value="<?php echo esc_attr( $key ); ?>" <?php checked( $now, $key ); ?>> <?php echo esc_html( $label ); ?></label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="ms-shell-stage" id="ms-shell-stage">
            <iframe id="ms-shell-light" title="Light look" data-mode="light" src="<?php echo esc_url( $base ); ?>"<?php echo $dark ? '' : ' class="is-on"'; ?>></iframe>
            <iframe id="ms-shell-dark" title="Dark look" data-mode="dark" src="<?php echo esc_url( $base . '&mode=dark' ); ?>"<?php echo $dark ? ' class="is-on"' : ''; ?>></iframe>
        </div>

        <div class="ms-shell-save">
            <button type="button" class="button button-primary" id="ms-shell-both">Save Light &amp; Dark</button>
            <span class="ms-shell-small">or only
                <button type="button" class="button button-small" id="ms-shell-only-light">Light</button>
                <button type="button" class="button button-small" id="ms-shell-only-dark">Dark</button></span>
            <span class="ms-shell-status" id="ms-shell-status" aria-live="polite"></span>
            <span class="description ms-shell-note">Saving goes live straight away. 🔗 linked panels are the same in both looks whichever button you press; the rest of a look stays as it is when you save only the other one.
                <a href="<?php echo esc_url( admin_url( 'themes.php?page=monospace-look&classic=1' . ( $dark ? '&mode=dark' : '' ) ) ); ?>">Classic single screen</a></span>
        </div>
    </div>
    <style>
        .ms-look-shell { max-width: 1500px; }
        .ms-shell-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 24px; margin: 0 0 10px; }
        .ms-shell-tabs .button:first-child { border-radius: 3px 0 0 3px; } .ms-shell-tabs .button:nth-child(2) { border-radius: 0 3px 3px 0; margin-left: -1px; }
        .ms-shell-visitors { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 14px; }
        .ms-shell-visitors label { display: inline-flex; align-items: center; gap: 5px; }
        .ms-shell-dot { display: inline-block; width: 8px; height: 8px; margin-left: 4px; border-radius: 50%; background: #d63638; vertical-align: middle; }
        .ms-shell-dot[hidden] { display: none; }
        .ms-shell-stage { position: relative; height: calc(100vh - 250px); min-height: 520px; background: #f0f0f1; border: 1px solid #c3c4c7; border-radius: 6px; overflow: hidden; }
        .ms-shell-stage iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; visibility: hidden; pointer-events: none; }
        .ms-shell-stage iframe.is-on { visibility: visible; pointer-events: auto; }
        .ms-shell-save { position: sticky; bottom: 0; z-index: 5; display: flex; flex-wrap: wrap; align-items: center; gap: 6px 12px; margin-top: 10px; padding: 10px 0; background: #f0f0f1; }
        .ms-shell-small { color: #50575e; font-size: 12px; }
        .ms-shell-small .button { margin-left: 2px; }
        .ms-shell-status { font-weight: 600; min-width: 12em; }
        .ms-shell-status.is-bad { color: #d63638; }
        .ms-shell-note { flex: 1 1 100%; margin: 0; }
    </style>
    <script>
    (function () {
        var frames = { light: document.getElementById('ms-shell-light'), dark: document.getElementById('ms-shell-dark') },
            tabs = document.querySelectorAll('.ms-shell-tabs [data-mode]'),
            status = document.getElementById('ms-shell-status'),
            active = <?php echo $dark ? "'dark'" : "'light'"; ?>,
            base = { light: null, dark: null },      // each screen's values when it loaded or was saved
            last = { light: {}, dark: {} },          // each panel's values at the last look (to see what changed)
            linked = { light: {}, dark: {} },        // which panels were linked, last time we looked
            ready = { light: false, dark: false },
            busy = false, choice0 = <?php echo wp_json_encode( $now ); ?>;

        function other(m) { return m === 'light' ? 'dark' : 'light'; }
        function doc(m) { try { return frames[m].contentDocument; } catch (e) { return null; } }
        function form(m) { var d = doc(m); return d && d.getElementById('ms-look-form'); }
        function say(text, bad) { status.textContent = text; status.classList.toggle('is-bad', !!bad); }

        // The values of a set of fields: name (and value, for ticks and radios) => string
        function snap(root) {
            var out = {};
            root.querySelectorAll('[name]').forEach(function (el) {
                if (el.disabled || el.type === 'submit' || el.type === 'button' || el.type === 'file') return;
                var key = el.type === 'checkbox' || el.type === 'radio' ? el.name + '=' + el.value : el.name;
                out[key] = el.type === 'checkbox' || el.type === 'radio' ? (el.checked ? '1' : '0') : el.value;
            });
            return out;
        }
        function field(root, key) {
            var i = key.indexOf('='), n = i < 0 ? key : key.slice(0, i), v = i < 0 ? null : key.slice(i + 1);
            return root.querySelector('[name="' + n.replace(/"/g, '\\"') + '"]' + (v === null ? '' : '[value="' + v.replace(/"/g, '\\"') + '"]'));
        }
        function same(a, b) { var k; for (k in a) if (a[k] !== b[k]) return false; for (k in b) if (!(k in a)) return false; return true; }

        // Unsaved changes: this screen now against how it was when it loaded or was saved
        function dirty(m) { var f = form(m); return !!(f && ready[m] && base[m] && !same(snap(f), base[m])); }
        function marks() {
            tabs.forEach(function (t) { t.querySelector('.ms-shell-dot').hidden = !dirty(t.dataset.mode); });
        }

        // Linked panels: copy what changed on screen $from to the other screen
        function linkedPanels(d) {
            var out = {};
            d.querySelectorAll('details.lm-panel[data-panel]').forEach(function (p) {
                var box = p.querySelector('input[name="look_link[' + p.dataset.panel + ']"]');
                out[p.dataset.panel] = box ? box.checked : !!p.querySelector('.ms-link-row.is-fixed');
            });
            return out;
        }
        function syncLinked(from) {
            var to = other(from), a = doc(from), b = doc(to);
            if (!a || !b || !ready[from] || !ready[to]) return;
            var now = linkedPanels(a), moved = false;
            a.querySelectorAll('details.lm-panel[data-panel]').forEach(function (p) {
                var id = p.dataset.panel, q = b.querySelector('details.lm-panel[data-panel="' + id + '"]');
                if (!q || !now[id]) return;
                var mine = snap(p), was = last[from][id] || {}, justLinked = !linked[from][id];
                Object.keys(mine).forEach(function (key) {
                    if (!justLinked && mine[key] === was[key]) return; // unchanged since the last look
                    var el = field(q, key);
                    if (!el) return;
                    var v = mine[key];
                    if (el.type === 'checkbox' || el.type === 'radio') { if (el.checked !== (v === '1')) { el.checked = v === '1'; moved = true; } }
                    else if (el.value !== v) { el.value = v; moved = true; }
                });
            });
            // The link toggles themselves follow (they are in the panels above); remember how things stand
            linked[from] = now;
            remember(from);
            if (moved) {
                var f = form(to);
                f.dispatchEvent(new Event('input', { bubbles: true })); f.dispatchEvent(new Event('change', { bubbles: true })); // the screen's own live preview
                remember(to);
            }
        }
        // What each linked panel held at the last look (per screen)
        function remember(m) {
            var d = doc(m); if (!d) return;
            var panels = {};
            d.querySelectorAll('details.lm-panel[data-panel]').forEach(function (p) { panels[p.dataset.panel] = snap(p); });
            last[m] = panels;
        }

        function attach(m) {
            var d = doc(m), f = form(m);
            if (!f) { ready[m] = false; return; }
            // Taken a moment after load, once the screen's own scripts have set their fields
            setTimeout(function () {
                var s = snap(f);
                base[m] = s; ready[m] = true; linked[m] = linkedPanels(d); remember(m);
                marks();
            }, 700);
        }
        Object.keys(frames).forEach(function (m) { frames[m].addEventListener('load', function () { ready[m] = false; attach(m); }); });

        // Twice a second: the screen you're on, its linked panels copied across, the dots
        setInterval(function () { if (busy) return; syncLinked(active); marks(); }, 500);

        function show(m) {
            if (m === active) return;
            syncLinked(active); // what you changed in a linked panel is there when you arrive
            active = m;
            Object.keys(frames).forEach(function (k) { frames[k].classList.toggle('is-on', k === m); });
            tabs.forEach(function (t) { t.classList.toggle('button-primary', t.dataset.mode === m); });
            try { history.replaceState(null, '', location.href.replace(/&mode=dark/, '') + (m === 'dark' ? '&mode=dark' : '')); } catch (e) {}
        }
        tabs.forEach(function (t) { t.addEventListener('click', function () { show(t.dataset.mode); }); });

        // Saving: the screen's own form, posted in its frame; the frame comes back to the screen when it is done
        function choiceValue() { var r = document.querySelector('input[name="ms_shell_dark_choice"]:checked'); return r ? r.value : choice0; }
        function submit(m) {
            return new Promise(function (resolve) {
                var f = form(m), d = doc(m);
                if (!f) { resolve(false); return; }
                var r = d.querySelector('input[name="dark_choice"][value="' + choiceValue() + '"]');
                if (r) r.checked = true;
                ready[m] = false;
                var timer = setTimeout(function () { frames[m].removeEventListener('load', h); resolve(false); }, 45000);
                function h() {
                    frames[m].removeEventListener('load', h);
                    clearTimeout(timer);
                    resolve(!!form(m)); // back on the screen = saved; anything else (an error page) = not
                }
                frames[m].addEventListener('load', h);
                // The screen's Apply button is named "submit", which hides the form's own submit() - call the real one
                HTMLFormElement.prototype.submit.call(f);
            });
        }
        var buttons = ['ms-shell-both', 'ms-shell-only-light', 'ms-shell-only-dark'].map(function (id) { return document.getElementById(id); }),
            labels = buttons.map(function (b) { return b.textContent; });
        function lock(on) { buttons.forEach(function (b, i) { b.disabled = on; b.textContent = on ? 'Saving…' : labels[i]; }); }
        async function save(modes) {
            if (busy) return;
            busy = true;
            lock(true);
            say('Saving…');
            var done = [];
            try {
                syncLinked(active);
                // the screen you're on last: where a linked panel differs, it wins
                modes = modes.slice().sort(function (a) { return a === active ? 1 : -1; });
                for (var i = 0; i < modes.length; i++) {
                    var ok = await submit(modes[i]);
                    if (!ok) { say('Could not save the ' + modes[i] + ' look' + (done.length ? ' (the ' + done.join(' & ').toLowerCase() + ' look was saved)' : ' - nothing was saved') + '. Reload the page and try again.', true); return; }
                    done.push(modes[i] === 'light' ? 'Light' : 'Dark');
                }
                // The look you didn't save may have got linked panels from this one: show it as the server has it - unless it has changes of its own
                modes.length === 1 && !dirty(other(modes[0])) && frames[other(modes[0])].contentWindow.location.reload();
                choice0 = choiceValue();
                say('✓ Saved ' + done.join(' & ') + ' at ' + new Date().toLocaleTimeString() + ' - the site shows it now.');
            } catch (e) {
                say('Could not save: ' + (e && e.message ? e.message : e) + (done.length ? ' (the ' + done.join(' & ').toLowerCase() + ' look was saved)' : ''), true);
            } finally {
                busy = false;
                lock(false);
            }
        }
        document.getElementById('ms-shell-both').addEventListener('click', function () { save(['light', 'dark']); });
        document.getElementById('ms-shell-only-light').addEventListener('click', function () { save(['light']); });
        document.getElementById('ms-shell-only-dark').addEventListener('click', function () { save(['dark']); });

        window.addEventListener('beforeunload', function (e) {
            if (!busy && (dirty('light') || dirty('dark') || choiceValue() !== choice0)) { e.preventDefault(); e.returnValue = ''; }
        });
    })();
    </script>
    <?php
}

<?php
/**
 * Design modules - the registry
 *
 * The theme's configurable design parts, each a self-contained file in this folder that owns its
 * options, its settings form, saving, and its front-end output:
 *
 *   appearance.php      Appearance - page background, button style and colour (presets or custom)
 *   scroll-effects.php  Scroll effect - Off / Slide-in / Elastic scroll
 *   hover-effects.php   Rollover effect - Off / Lift (grow + shadow), strength, where
 *   logo-link.php       Logo link - where clicking the big logo goes, and its hover text
 *   shop-link.php       Shop link - the empty cart's "Return to shop" button
 *   archive-toolbar.php Viewing toolbar - the bar above category pages and the Art Feed
 *   content-jump.php    Open at the content - posts / pages open scrolled past the logo and search
 *   print-preview.php   Print preview - the "See a preview" lightbox's background (blur / image / pattern / colour) and look
 *
 * A module registers itself on the 'monospace_design_modules' filter:
 *
 *   add_filter( 'monospace_design_modules', function ( $modules ) {
 *       $modules['my-part'] = [
 *           'title'       => 'My part',
 *           'description' => 'One line under the title.',      // optional
 *           'group'       => 'look',                            // look | motion | links (monospace_design_groups())
 *           'order'       => 30,                                // position in its group, low first
 *           'summary'     => fn() => 'On',                      // optional: the current setting, in a few words
 *           'render'      => 'my_render_function',              // prints the settings (form fields)
 *           'save'        => 'my_save_function',                // reads $_POST, updates options
 *       ];
 *       return $modules;
 *   } );
 *
 * Layout Manager > Appearance & Effects (layout-manager.php, tab slug 'effects') shows the modules by
 * group (2026-10-19 clean-up: one group at a time, chosen at the top and remembered; each module a card
 * with its current setting on its title line; a Save bar fixed to the bottom of the screen that says when
 * something has changed). All groups stay in the one form, so Save saves everything, then each module's
 * save runs and the page cache is cleared. Adding a part = adding a file here; nothing else changes.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The groups on the tab, in order: label, one line about it */
function monospace_design_groups() {
    return [
        'look'   => [ 'Look',             'Background, buttons, and how a print preview is shown.' ],
        'motion' => [ 'Motion',           'What moves while scrolling and under the mouse.' ],
        'links'  => [ 'Links & toolbar',  'Where the logo and the empty cart lead, and the bar above category pages.' ],
    ];
}

/** The registered design modules, by group, then order */
function monospace_design_modules() {
    $modules = apply_filters( 'monospace_design_modules', [] );
    $groups  = array_keys( monospace_design_groups() );
    $rank    = fn( $m ) => [ array_search( $m['group'] ?? 'look', $groups, true ), $m['order'] ?? 50 ];
    uasort( $modules, fn( $a, $b ) => $rank( $a ) <=> $rank( $b ) );
    return $modules;
}

/** The Appearance & Effects tab: the groups (one at a time), each module a card */
function monospace_render_design_modules() {
    $modules = monospace_design_modules();
    $groups  = monospace_design_groups();
    ?>
    <style>
        .ms-design-nav { display: flex; gap: 6px; flex-wrap: wrap; margin: 18px 0 6px; }
        .ms-design-nav button { font-size: 14px; padding: 7px 16px; border: 1px solid #c3c4c7; border-radius: 999px; background: #fff; cursor: pointer; }
        .ms-design-nav button[aria-selected="true"] { background: #2271b1; border-color: #2271b1; color: #fff; }
        .ms-design-intro { margin: 0 0 14px; color: #50575e; }
        .ms-design-group[hidden] { display: none; }
        .ms-design-module { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; margin: 0 0 16px; }
        .ms-design-module > summary { list-style: none; cursor: pointer; padding: 14px 18px; display: flex; align-items: baseline; gap: 12px; flex-wrap: wrap; }
        .ms-design-module > summary::-webkit-details-marker { display: none; }
        .ms-design-module > summary::before { content: "▸"; color: #8c8f94; transition: transform .15s; }
        .ms-design-module[open] > summary::before { transform: rotate(90deg); }
        .ms-design-module > summary h2 { margin: 0; font-size: 16px; }
        .ms-design-module > summary .ms-now { margin-left: auto; padding: 2px 10px; border-radius: 999px; background: #f0f0f1; color: #3c434a; font-size: 12px; }
        .ms-design-module > summary .description { flex-basis: 100%; margin: 2px 0 0 22px; }
        .ms-design-body { padding: 0 18px 8px; border-top: 1px solid #f0f0f1; }
        .ms-design-body .form-table { margin-top: 4px; }
        /* The Save button: fixed to the bottom of the screen on this tab */
        .ms-design ~ p.submit { position: sticky; bottom: 0; z-index: 20; margin: 0; padding: 12px 0; background: #f0f0f1; border-top: 1px solid #dcdcde; display: flex; align-items: center; gap: 14px; }
        .ms-design-dirty { color: #b26200; font-weight: 600; }
    </style>
    <div class="ms-design">
        <div class="ms-design-nav" role="tablist">
            <?php foreach ( $groups as $g => [ $label ] ) : ?>
                <button type="button" role="tab" data-group="<?php echo esc_attr( $g ); ?>" aria-selected="false"><?php echo esc_html( $label ); ?></button>
            <?php endforeach; ?>
        </div>
        <?php foreach ( $groups as $g => [ $label, $intro ] ) : ?>
            <div class="ms-design-group" data-group="<?php echo esc_attr( $g ); ?>" role="tabpanel" hidden>
                <p class="ms-design-intro"><?php echo esc_html( $intro ); ?> Click a section to open it.</p>
                <?php foreach ( $modules as $id => $module ) {
                    if ( ( $module['group'] ?? 'look' ) !== $g || empty( $module['render'] ) || ! is_callable( $module['render'] ) ) continue;
                    $now = ! empty( $module['summary'] ) && is_callable( $module['summary'] ) ? (string) call_user_func( $module['summary'] ) : '';
                    echo '<details class="ms-design-module" id="ms-design-' . esc_attr( $id ) . '"><summary>';
                    echo '<h2>' . esc_html( $module['title'] ?? $id ) . '</h2>';
                    if ( $now !== '' ) echo '<span class="ms-now" title="Saved setting">' . esc_html( $now ) . '</span>';
                    if ( ! empty( $module['description'] ) ) echo '<p class="description">' . esc_html( $module['description'] ) . '</p>';
                    echo '</summary><div class="ms-design-body">';
                    call_user_func( $module['render'] );
                    echo '</div></details>';
                } ?>
            </div>
        <?php endforeach; ?>
    </div>
    <script>
    (function () {
        var wrap = document.querySelector('.ms-design'), form = wrap.closest('form'), KEY = 'msDesignTab';
        var store = { get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } },
                      set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} } };

        // One group at a time; the last one (and its open sections) remembered across saves
        function show(g) {
            if (!wrap.querySelector('.ms-design-group[data-group="' + g + '"]')) g = 'look';
            wrap.querySelectorAll('.ms-design-nav button').forEach(function (b) { b.setAttribute('aria-selected', b.dataset.group === g); });
            wrap.querySelectorAll('.ms-design-group').forEach(function (p) { p.hidden = p.dataset.group !== g; });
            store.set(KEY, g);
        }
        wrap.querySelectorAll('.ms-design-nav button').forEach(function (b) { b.addEventListener('click', function () { show(b.dataset.group); }); });
        var hash = location.hash.replace('#', ''), target = hash && document.getElementById('ms-design-' + hash);
        show(target ? target.closest('.ms-design-group').dataset.group : store.get(KEY) || 'look');

        var open = (store.get(KEY + 'Open') || '').split(',');
        wrap.querySelectorAll('.ms-design-module').forEach(function (d) {
            if (open.indexOf(d.id) > -1 || d === target) d.open = true;
            d.addEventListener('toggle', function () {
                store.set(KEY + 'Open', Array.prototype.map.call(wrap.querySelectorAll('.ms-design-module[open]'), function (x) { return x.id; }).join(','));
            });
        });

        // Unsaved changes: say so next to Save, and ask before leaving
        // (the Save button comes after this script - Layout Manager prints it below the tab)
        var dirty = false, note = document.createElement('span');
        note.className = 'ms-design-dirty'; note.hidden = true; note.textContent = 'Unsaved changes';
        document.addEventListener('DOMContentLoaded', function () {
            var submit = form.querySelector('p.submit');
            if (submit) submit.appendChild(note);
        });
        function mark() { dirty = true; note.hidden = false; }
        form.addEventListener('input', mark);
        form.addEventListener('change', mark);
        form.addEventListener('submit', function () { dirty = false; });
        window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
    })();
    </script>
    <?php
}

/** Save: every module saves its own options, then the page cache is cleared */
function monospace_save_design_modules() {
    foreach ( monospace_design_modules() as $module ) {
        if ( ! empty( $module['save'] ) && is_callable( $module['save'] ) ) {
            call_user_func( $module['save'] );
        }
    }
    // The page cache holds the old settings - clear it so the change shows right away
    if ( function_exists( 'sg_cachepress_purge_everything' ) ) {
        sg_cachepress_purge_everything();
    }
}

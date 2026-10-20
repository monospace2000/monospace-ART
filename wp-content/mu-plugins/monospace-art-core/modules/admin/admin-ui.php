<?php
/**
 * The admin UI kit (2026-10-20, owner's rule: one admin UI style - CLAUDE.md "Admin UI style", ARCHITECTURE.md 6b).
 *
 * monospace_lm_area_assets() prints, once per page, the styles and script every monospace ART admin screen shares:
 *   .lm-panel   folding panel (<details>): summary = .lm-t title + .lm-chip state + .lm-sub one-line summary;
 *               .lm-body holds the fields
 *   .lm-chip    is-on (in use, blue) · is-empty (outlined grey) · is-off (pale) · is-terms (set elsewhere, green)
 *               · is-warn (red)
 *   .lm-map     the page map beside the panels (.lm-area is-on / is-empty / is-off), linked to them by data-panel
 *   .lm-mirror + .lm-mobile   "Same on phones & tablets" folds the phone field away
 *   .lm-show-sidebar + .lm-sidebar-fields   a checkbox that folds its fields away
 * Wrap a screen's panels in .lm-areas (and .lm-panels); the script sets up every .lm-areas on the page.
 * Used by: Layout Manager > Headers & Sidebars and the category / tag screens (theme), the "This page's layout"
 * box (theme), the Prints box and pages (Prints plugin). Moved here from the theme so plugins can use it.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The areas screens' look and behaviour (the map and the panels), printed once per page after the first one -
 * Layout Manager > Headers & Sidebars and the category / tag edit screens (term-areas.php) share it.
 */
function monospace_lm_area_assets() {
    static $done = false;
    if ( $done ) return;
    $done = true; ?>
    <style>
        .lm-areas { max-width: 1180px; }
        .lm-where { margin: 4px 0 14px; max-width: 900px; }
        .lm-grid { display: grid; grid-template-columns: 230px minmax(0, 1fr); gap: 24px; align-items: start; }
        @media (max-width: 900px) { .lm-grid { grid-template-columns: 1fr; } .lm-map { position: static !important; max-width: 320px; } }

        /* The map */
        .lm-map { position: sticky; top: 46px; margin: 0; padding: 10px; background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; display: grid; gap: 6px; font-size: 12px; }
        .lm-r { display: block; width: 100%; box-sizing: border-box; margin: 0; padding: 7px 8px; border-radius: 4px; text-align: center; font: inherit; line-height: 1.3; }
        .lm-fixed { background: #f0f0f1; color: #787c82; }
        .lm-logo { padding: 12px 8px; }
        .lm-content { padding: 34px 8px; }
        .lm-cols { display: grid; grid-template-columns: 1fr 64px; gap: 6px; }
        .lm-main { display: grid; gap: 6px; align-content: start; }
        .lm-side { display: flex; flex-direction: column; gap: 6px; align-items: stretch; justify-content: flex-start; min-height: 100%; }
        .lm-area { cursor: pointer; border: 2px solid transparent; }
        .lm-area.is-on, .lm-in-side.is-on, .lm-key.is-on { background: #e7f0fa; border-color: #2271b1; color: #135e96; font-weight: 600; }
        .lm-area.is-terms, .lm-in-side.is-terms { background: #f0f6ee; border-color: #68a35f; color: #2e6b26; font-weight: 600; }
        .lm-area.is-empty, .lm-in-side.is-empty, .lm-key.is-empty { background: #fff; border: 2px dashed #a7aaad; color: #646970; }
        .lm-area.is-off, .lm-in-side.is-off, .lm-key.is-off { background: repeating-linear-gradient(135deg, #f6f7f7 0 5px, #e8e9ea 5px 10px); color: #8c8f94; text-decoration: line-through; }
        .lm-in-side { display: block; padding: 5px 2px; border-radius: 3px; border: 2px solid transparent; }
        .lm-area:hover, .lm-area.is-hl, .lm-in-side.is-hl { outline: 3px solid #f0b849; outline-offset: 1px; }
        .lm-map figcaption { margin-top: 4px; color: #646970; line-height: 1.6; }
        .lm-key { display: inline-block; width: 12px; height: 10px; margin: 0 2px 0 6px; vertical-align: -1px; border-radius: 2px; box-sizing: border-box; }
        .lm-key:first-of-type { margin-left: 0; }

        /* The panels */
        .lm-panels { display: grid; grid-template-columns: minmax(0, 1fr); gap: 10px; } /* never wider than the page */
        .lm-panel { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; }
        .lm-panel.is-hl { border-color: #f0b849; box-shadow: 0 0 0 2px #f0b849; }
        .lm-panel > summary { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 10px; padding: 12px 14px; cursor: pointer; list-style: none; }
        .lm-panel > summary::-webkit-details-marker { display: none; }
        .lm-panel > summary::before { content: "▸"; color: #8c8f94; width: 10px; }
        .lm-panel[open] > summary::before { content: "▾"; }
        .lm-panel[open] > summary { border-bottom: 1px solid #f0f0f1; }
        .lm-t { font-size: 14px; font-weight: 600; }
        .lm-sub { flex-basis: 100%; padding-left: 20px; color: #646970; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .lm-chip { font-size: 11px; padding: 1px 8px; border-radius: 10px; background: #f0f0f1; color: #50575e; }
        .lm-chip.is-on { background: #e7f0fa; color: #135e96; }
        .lm-chip.is-terms { background: #f0f6ee; color: #2e6b26; }
        .lm-chip.is-off { background: #f6f7f7; color: #8c8f94; }
        .lm-chip.is-warn { background: #fcf0f1; color: #8a2424; }
        .lm-body { padding: 12px 14px 14px; }
        .lm-body > p:first-child { margin-top: 0; }
        .lm-label { display: inline-block; min-width: 70px; font-weight: 600; margin: 10px 0 6px; }
        .lm-mirror { display: inline-block; margin: 10px 0 0; }
        .lm-mobile { margin-top: 6px; padding: 8px 12px 10px; background: #f6f7f7; border-radius: 4px; }
        .lm-mobile[hidden], .lm-sidebar-fields[hidden] { display: none; }
    </style>
    <script>
    document.querySelectorAll('.lm-areas:not([data-lm-ready])').forEach(function (root) {
        root.dataset.lmReady = '1';
        function panel(name) { return root.querySelector('.lm-panel[data-panel="' + name + '"]'); }
        function areas(name) { return root.querySelectorAll('.lm-map [data-panel="' + name + '"]'); }
        // The map and the panels light each other up
        root.querySelectorAll('.lm-panel').forEach(function (p) {
            ['mouseenter', 'focusin'].forEach(function (ev) { p.addEventListener(ev, function () { areas(p.dataset.panel).forEach(function (a) { a.classList.add('is-hl'); }); }); });
            ['mouseleave', 'focusout'].forEach(function (ev) { p.addEventListener(ev, function () { areas(p.dataset.panel).forEach(function (a) { a.classList.remove('is-hl'); }); }); });
            // An editor opened from a folded panel: let TinyMCE size itself
            p.addEventListener('toggle', function () { if (p.open) refresh(p); });
        });
        root.querySelectorAll('.lm-map [data-panel]').forEach(function (a) {
            a.addEventListener('mouseenter', function () { var p = panel(a.dataset.panel); if (p) p.classList.add('is-hl'); });
            a.addEventListener('mouseleave', function () { var p = panel(a.dataset.panel); if (p) p.classList.remove('is-hl'); });
            a.addEventListener('click', function (e) {
                e.preventDefault(); e.stopPropagation();
                var p = panel(a.dataset.panel); if (!p) return;
                p.open = true; p.scrollIntoView({ behavior: 'smooth', block: 'start' });
                var f = p.querySelector('summary'); if (f) f.focus({ preventScroll: true });
            });
        });
        // "Same on phones & tablets" folds the phone editor away; "Show the sidebar" off folds the sidebar editors
        root.querySelectorAll('.lm-mirror input').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var m = cb.closest('.lm-mirror').nextElementSibling; m.hidden = cb.checked; if (!cb.checked) refresh(m);
            });
        });
        var show = root.querySelector('.lm-show-sidebar'), fields = root.querySelector('.lm-sidebar-fields');
        if (show && fields) show.addEventListener('change', function () { fields.hidden = !show.checked; if (show.checked) refresh(fields); });
        function refresh(el) {
            setTimeout(function () {
                if (!window.tinymce) return;
                el.querySelectorAll('textarea.wp-editor-area').forEach(function (t) {
                    var ed = tinymce.get(t.id);
                    if (ed && !ed.isHidden()) { ed.execCommand('mceRepaint'); ed.fire('ResizeEditor'); }
                });
                window.dispatchEvent(new Event('resize'));
            }, 0);
        }
    });
    </script>
    <?php
}

/**
 * Elastic scroll ("rubber" scroll)
 *
 * The method of Codrops' Elastic Grid Scroll (tympanus.net/Tutorials/ElasticGridScroll, GSAP
 * ScrollSmoother): the page content sits in a fixed wrapper (#smooth-wrapper > #smooth-content,
 * header.php / footer.php) and is moved by GSAP, which follows the real scroll position with a delay
 * ("smooth"). Every post row / home box also gets its own extra delay ("lag"), so while scrolling
 * the rows drift apart and bunch up, and settle when the scroll stops - the page feels rubbery.
 *
 * Because one script draws the whole page in one go, nothing jitters against the browser's own
 * scrolling (the problem of moving single items while the browser scrolls the page). normalizeScroll
 * makes touch scrolling go through GSAP as well, so phones behave the same as desktops.
 *
 * Lag per item: rows rise and fall along a gentle wave down the page (Codrops demo 5) and, in a grid,
 * the middle column leads and the outer ones trail (Codrops demo 1). Neighboring rows differ only a
 * little, so they stretch apart rather than run into each other.
 *
 * Loaded only while Layout Manager > Effects > Scroll effect is "Elastic scroll", and not on the
 * cart / checkout / account pages (layout-manager.php), which prints window.monospaceRubber
 * { smooth, touch, stagger }. GSAP is self-hosted in assets/vendor/gsap.
 */
(function () {
    'use strict';

    if (!window.gsap || !window.ScrollTrigger || !window.ScrollSmoother) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var wrapper = document.getElementById('smooth-wrapper');
    var content = document.getElementById('smooth-content');
    if (!wrapper || !content) return;

    var SETTINGS = window.monospaceRubber || {};
    var SMOOTH   = SETTINGS.smooth != null ? SETTINGS.smooth : 1;      // s the page takes to catch up
    var TOUCH    = SETTINGS.touch != null ? SETTINGS.touch : 0.6;      // same, on touch screens
    var STAGGER  = SETTINGS.stagger != null ? SETTINGS.stagger : 0.25; // s of extra lag between rows / columns
    var BASE_LAG = 0.05;
    var SELECTOR = '#main-content .archive-item, '
                 + 'body.page-template-start_template .container > .box, '
                 + 'body.page-template-start_template .container > .home-row-label, ' // row labels move with their rows
                 + '.scroll-fade';

    gsap.registerPlugin(ScrollTrigger, ScrollSmoother);

    // The wrapper is fixed, so it ignores the body's margin: carry it over to the content as padding,
    // then correct the top and bottom against the page as it was (the body's margin merged with the
    // first and last blocks' own margins; padding doesn't), so nothing shifts
    var first = content.firstElementChild;
    var before = {
        top: first ? first.getBoundingClientRect().top + window.scrollY : 0,
        height: document.documentElement.scrollHeight
    };
    var bodyStyle = getComputedStyle(document.body);
    ['Top', 'Right', 'Bottom', 'Left'].forEach(function (side) {
        content.style['padding' + side] = bodyStyle['margin' + side];
    });
    document.body.style.margin = '0';
    content.style.display = 'flow-root';   // contain the first / last block's margins: measurable
    if (first) {
        var shift = first.getBoundingClientRect().top + window.scrollY - before.top;
        content.style.paddingTop = Math.max(0, parseFloat(content.style.paddingTop) - shift) + 'px';
    }

    var smoother = ScrollSmoother.create({
        wrapper: wrapper,
        content: content,
        smooth: SMOOTH,
        smoothTouch: TOUCH,
        effects: false,          // lags are applied per item below
        // touch scrolling through GSAP too: no address-bar jumps, same feel everywhere. allowNestedScroll:
        // text boxes with their own scrollbar (home page) still scroll by touch; the rest is ScrollSmoother's default.
        normalizeScroll: { debounce: true, allowNestedScroll: true, content: ScrollTrigger.isTouch === 1 ? false : content }
    });
    var missing = before.height - document.documentElement.scrollHeight;
    if (missing) {
        content.style.paddingBottom = Math.max(0, parseFloat(content.style.paddingBottom) + missing) + 'px';
        ScrollTrigger.refresh();
    }
    document.documentElement.classList.add('has-rubber-scroll');

    // ---- Per-item lag --------------------------------------------------------------------------
    var done = new WeakSet();
    var rowCount = 0, lastRowTop = null;

    function applyLags() {
        var items = Array.prototype.slice.call(document.querySelectorAll(SELECTOR)).filter(function (el) {
            return !done.has(el) && el.offsetParent !== null;
        });
        if (!items.length) return;

        // Rows: items whose tops line up (grid view, home page boxes); columns: left to right
        var base = content.getBoundingClientRect().top;
        var rows = [];
        items.map(function (el) {
            var r = el.getBoundingClientRect();
            return { el: el, top: Math.round(r.top - base), left: r.left };
        }).sort(function (a, b) { return a.top - b.top || a.left - b.left; }).forEach(function (it) {
            var row = rows[rows.length - 1];
            if (!row || Math.abs(it.top - row.top) > 4) rows.push(row = { top: it.top, items: [] });
            row.items.push(it);
        });

        rows.forEach(function (row) {
            // New items may continue the last row (infinite scroll in grid view)
            if (lastRowTop === null || Math.abs(row.top - lastRowTop) > 4) { rowCount++; lastRowTop = row.top; }
            var wave = 0.5 + 0.5 * Math.sin(rowCount * 0.9);          // 0..1 down the page
            var n = row.items.length, mid = (n - 1) / 2;
            row.items.forEach(function (it, c) {
                var side = mid ? Math.abs(c - mid) / mid : 0;          // 0 in the middle, 1 at the edges
                var lag = BASE_LAG + STAGGER * wave + STAGGER * 0.6 * side;
                smoother.effects(it.el, { speed: 1, lag: lag, effectsPadding: 600 });
                done.add(it.el);
            });
        });
    }
    applyLags();

    // ---- Content changes: infinite scroll, images, resizes -------------------------------------
    var refreshTimer = 0;
    function refreshSoon() {
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(function () {
            applyLags();
            ScrollTrigger.refresh();
            if (pinned) scrollToAnchor(pinned, false); // the layout moved under the anchor the page opened on
        }, 150);
    }
    var main = document.getElementById('main-content');
    if (main) new MutationObserver(refreshSoon).observe(main, { childList: true, subtree: true });
    if (window.ResizeObserver) new ResizeObserver(refreshSoon).observe(content);
    window.addEventListener('load', refreshSoon);

    // Anchors (#prints, home page boxes): the browser's own jump misses with the page drawn by
    // ScrollSmoother - scroll there through it, landing just below the fixed menu bar
    // A home page box's anchor lands on its row on computers (home-anchors.js: window.monospaceAnchorTarget)
    function anchorTarget(hash) {
        if (!hash || hash.length < 2) return null;
        try {
            var el = document.getElementById(decodeURIComponent(hash.slice(1)));
            return el && window.monospaceAnchorTarget ? window.monospaceAnchorTarget(el) : el;
        } catch (e) { return null; }
    }
    function scrollToAnchor(el, smooth) {
        var bar = document.querySelector('.primary-menu-bar');
        var offset = (bar ? bar.getBoundingClientRect().bottom : 0) + 16;
        smoother.scrollTo(el, smooth, 'top ' + Math.round(offset) + 'px');
    }
    // Opened on an anchor: jump there at once - not after every image has loaded - and hold it there
    // while images / fonts still move the layout (refreshSoon re-aims), until 'load' or until the
    // visitor scrolls, taps or types themselves
    var pinned = anchorTarget(location.hash);
    if (pinned) {
        var USER = ['wheel', 'touchstart', 'keydown', 'pointerdown'];
        var release = function () {
            pinned = null;
            USER.forEach(function (t) { window.removeEventListener(t, release, true); });
        };
        USER.forEach(function (t) { window.addEventListener(t, release, { capture: true, passive: true }); });
        scrollToAnchor(pinned, false);
        window.addEventListener('load', function () {
            setTimeout(function () { if (pinned) scrollToAnchor(pinned, false); release(); }, 200);
        });
    }
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[href*="#"]');
        if (!a || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank') return;
        var url = new URL(a.href, location.href);
        if (url.origin !== location.origin || url.pathname !== location.pathname) return;
        var el = anchorTarget(url.hash);
        if (!el) return;
        e.preventDefault();
        history.pushState(null, '', url.hash);
        scrollToAnchor(el, true);
    });
    window.addEventListener('popstate', function () { var el = anchorTarget(location.hash); if (el) scrollToAnchor(el, true); });

    window.monospaceSmoother = smoother;   // for debugging in the console
})();

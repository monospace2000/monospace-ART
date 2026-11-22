// Home page: a # link to a box (#prints, #miniatures...) lands on the box's ROW on computers and tablets, so the row lines up
// below the menu bar (its label, or its top edge when it has none) - on phones (up to 640px) it lands on the box itself, as before.
// A row is not an element: its label (.home-row-label) or marker (.flex-break) comes first and the boxes follow, so the row's
// anchor is the nearest one before the box. window.monospaceAnchorTarget(el) gives it; rubber-scroll.js (Elastic scroll) scrolls
// through it, and without that effect this script does the scrolling itself.
(function () {
    var wide = window.matchMedia('(min-width: 641px)');

    function rowOf(el) {
        if (!wide.matches || !el || !el.matches || !el.matches('.container > .box')) return el;
        for (var p = el.previousElementSibling; p; p = p.previousElementSibling) {
            if (p.matches('.home-row-label, .flex-break')) return p;
        }
        return el;
    }
    window.monospaceAnchorTarget = rowOf;

    function target(hash) {
        if (!hash || hash.length < 2) return null;
        try { return document.getElementById(decodeURIComponent(hash.slice(1))); } catch (e) { return null; }
    }
    // Just below the fixed menu bar (which already sits under the admin bar), 16px of room
    function go(el, smooth) {
        var bar = document.querySelector('.primary-menu-bar');
        var top = el.getBoundingClientRect().top + window.pageYOffset - ((bar ? bar.getBoundingClientRect().bottom : 0) + 16);
        window.scrollTo({ top: Math.max(0, top), behavior: smooth ? 'smooth' : 'auto' });
    }
    function rubber() { return !!window.monospaceSmoother || !!window.monospaceRubber; }

    // Opened on an anchor: the browser jumped to the box - move to its row (until the visitor scrolls)
    function opened() {
        if (rubber()) return;
        var box = target(location.hash), row = rowOf(box);
        if (box && row !== box) go(row, false);
    }
    var scrolled = false;
    ['wheel', 'touchstart', 'keydown', 'pointerdown'].forEach(function (t) {
        window.addEventListener(t, function () { scrolled = true; }, { capture: true, passive: true });
    });
    document.addEventListener('DOMContentLoaded', opened);
    window.addEventListener('load', function () { setTimeout(function () { if (!scrolled) opened(); }, 200); }); // images moved the layout

    // A click on a link to a box on this page, and Back / Forward
    document.addEventListener('click', function (e) {
        if (rubber()) return;
        var a = e.target.closest && e.target.closest('a[href*="#"]');
        if (!a || e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank') return;
        var url = new URL(a.href, location.href);
        if (url.origin !== location.origin || url.pathname !== location.pathname) return;
        var box = target(url.hash), row = rowOf(box);
        if (!box || row === box) return;
        e.preventDefault();
        history.pushState(null, '', url.hash);
        go(row, true);
    });
    window.addEventListener('popstate', function () {
        if (rubber()) return;
        var box = target(location.hash), row = rowOf(box);
        if (box && row !== box) go(row, true);
    });
})();

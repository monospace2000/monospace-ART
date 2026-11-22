/**
 * Scroll slide-in
 *
 * Post previews (feed, category and search pages) and the home page boxes slide into place as
 * they come into view: from below when scrolling down, from above when scrolling up. No slide out.
 *
 * The slide follows the scrolling: an arriving item's offset is worked out from how far it has
 * moved into the screen, so it glides as slowly or quickly as you scroll, stops when you stop and
 * backs off if you reverse. Once it has traveled the "settle distance" it's in place and stays
 * there - it doesn't slide out as it leaves. When it's fully off screen again it waits, shifted,
 * on the side it left by (.slide-pending-below / -above), so it first appears in its starting
 * position rather than in place.
 *
 * Where the browser supports scroll-driven animations (CSS animation-timeline), the waiting classes
 * carry the slide themselves (_archives.scss): the browser moves the item in step with the scroll on
 * every frame, so it can't lag behind a fast phone swipe. This script then only picks the side and
 * clears the class once the item is in place. Elsewhere it sets the offset itself, as below.
 *
 * Items already on screen when the page opens have no scrolling to follow: they play a timed
 * entrance instead (.slide-from-below, CSS) - on every page alike. They start shifted from the
 * first paint (CSS under html.slide-ready), so they never show in place before it.
 *
 * Loaded only while Layout Manager > Effects > Scroll slide-in is on (layout-manager.php), which
 * also prints the settings as window.monospaceSlide { distance, settle, overshoot, phone: {...} } -
 * the phone set applies to screens up to 640px wide.
 */
(function () {
    'use strict';

    if (!('IntersectionObserver' in window)) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    var ALL = window.monospaceSlide || {};
    var PHONE = window.matchMedia ? window.matchMedia('(max-width: 640px)') : { matches: false };
    var DISTANCE, SETTLE, OVERSHOOT;

    // Desktop or phone settings, for the current screen width
    function applySettings() {
        var s = PHONE.matches && ALL.phone ? ALL.phone : ALL;
        DISTANCE  = s.distance != null ? s.distance : 120;    // px
        SETTLE    = (s.settle != null ? s.settle : 40) / 100; // share of screen height
        OVERSHOOT = s.overshoot != null ? s.overshoot : 0.6;  // 0 = none
    }
    applySettings();

    // Screen height, held steady: phone browsers resize the window while scrolling (the address
    // bar hides/shows), which would recalculate every slide mid-scroll and make it jump. Only a
    // change of width (e.g. rotating the phone) updates it.
    var viewW = window.innerWidth, viewH = window.innerHeight;
    window.addEventListener('resize', function () {
        if (window.innerWidth === viewW) return;
        viewW = window.innerWidth;
        viewH = window.innerHeight;
        applySettings();
        requestUpdate();
    });

    // The browser runs the slide (CSS) - see the top
    var CSS_SLIDE = !!(window.CSS && CSS.supports && CSS.supports('animation-timeline: view()')
                    && CSS.supports('animation-range: cover 10px cover 20px'));

    // The CSS measures in vh / lvh: on phones the screen height with the address bar hidden - which
    // doesn't change as the bar expands or collapses. Measured once, for the "in place" check
    var cssVh = 0;
    function largeVh() {
        if (!cssVh) {
            var probe = document.createElement('div');
            probe.style.cssText = 'position:absolute;top:0;height:100vh;width:0;visibility:hidden';
            document.body.appendChild(probe);
            cssVh = probe.offsetHeight || window.innerHeight;
            probe.remove();
        }
        return cssVh;
    }

    // The item's current shift, whoever applies it (inline or CSS animation)
    function currentShift(el) {
        var t = getComputedStyle(el).transform;
        if (!t || t === 'none') return 0;
        var m = t.match(/^matrix(3d)?\((.+)\)$/);
        if (!m) return 0;
        var v = m[2].split(',');
        return parseFloat(v[m[1] ? 13 : 5]) || 0;
    }

    var SELECTOR = 'body:is(.blog, .archive, .search) #main-content .archive-item, '
                 + 'body.page-template-start_template .container > .box, '
                 + 'body.page-template-start_template .container > .home-row-label, ' // row labels move with their rows
                 + '.scroll-fade';
    var CLASSES = ['slide-pending-below', 'slide-pending-above', 'slide-from-below', 'slide-from-above'];

    // Ease-out, with an optional overshoot ("back" curve)
    function ease(p) {
        var q = p - 1;
        return OVERSHOOT ? 1 + (OVERSHOOT + 1) * q * q * q + OVERSHOOT * q * q : 1 + q * q * q;
    }

    function setClass(el, cls) {
        el.classList.remove.apply(el.classList, CLASSES);
        if (cls) el.classList.add(cls);
    }

    function clearOffset(el) {
        el.style.transform = '';
        el.dataset.slideShift = '';
    }

    // Items currently sliding in: element -> 'below' | 'above'
    var arriving = new Map();

    function update() {
        if (CSS_SLIDE) return updateCss();
        var vh = viewH, range = Math.max(1, vh * SETTLE);
        arriving.forEach(function (side, el) {
            var shift = parseFloat(el.dataset.slideShift || '0');
            var r = el.getBoundingClientRect();
            var top = r.top - shift, bottom = r.bottom - shift;   // where it sits without the slide
            // Progress from the moment it actually appears (it waits DISTANCE off its spot), so the
            // whole glide is visible
            var p = side === 'below' ? (vh - top - DISTANCE) / range : (bottom - DISTANCE) / range;
            if (p >= 1) {
                // In place: done, and it stays put from here on
                arriving.delete(el);
                clearOffset(el);
                return;
            }
            var offset = (side === 'below' ? 1 : -1) * DISTANCE * (1 - ease(Math.max(0, p)));
            el.style.transform = 'translateY(' + offset.toFixed(1) + 'px)';
            el.dataset.slideShift = offset;
        });
    }

    // CSS slide: clear the class once the item has gone all the way and sits in place (so it then
    // stays put rather than sliding back out)
    function updateCss() {
        var vh = largeVh(), range = SETTLE * vh;   // the same fixed height the CSS uses
        arriving.forEach(function (side, el) {
            var shift = currentShift(el);
            if (Math.abs(shift) > 0.5) return;
            var r = el.getBoundingClientRect();
            var done = side === 'below' ? vh - r.top >= DISTANCE + range - 1 : r.bottom >= DISTANCE + range - 1;
            if (!done) return;
            arriving.delete(el);
            setClass(el, null);
        });
    }

    var ticking = false;
    function requestUpdate() {
        if (ticking || !arriving.size) return;
        ticking = true;
        requestAnimationFrame(function () { ticking = false; update(); });
    }
    window.addEventListener('scroll', requestUpdate, { passive: true });

    // A timed entrance has finished: back to normal
    document.addEventListener('animationend', function (e) {
        if (/^slide-from-/.test(e.animationName) && e.target.classList.contains(e.animationName)) {
            setClass(e.target, null);
        }
    });

    var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            var el = entry.target;

            if (!entry.isIntersecting) {
                // Fully off screen: wait, shifted, on the side it left by
                delete el.dataset.slideOnLoad;
                arriving.delete(el);
                clearOffset(el);
                setClass(el, entry.boundingClientRect.top > 0 ? 'slide-pending-below' : 'slide-pending-above');
                return;
            }

            if (el.dataset.slideOnLoad) {
                // Home page box on screen at load: timed entrance
                delete el.dataset.slideOnLoad;
                setClass(el, 'slide-from-below');
                return;
            }

            var side = el.classList.contains('slide-pending-below') ? 'below'
                     : el.classList.contains('slide-pending-above') ? 'above' : null;
            if (!side) return;   // on screen when first seen: leave it be

            if (CSS_SLIDE) {
                // The class's scroll-driven animation does the slide; watch for it to finish
                arriving.set(el, side);
                requestUpdate();
                return;
            }

            // Hand over from the class to the scroll-driven offset (same value - no jump)
            var start = (side === 'below' ? 1 : -1) * DISTANCE;
            el.style.transform = 'translateY(' + start + 'px)';
            el.dataset.slideShift = start;
            setClass(el, null);
            arriving.set(el, side);
            requestUpdate();
        });
    });

    var started = false;

    function watch(root) {
        (root.matches && root.matches(SELECTOR) ? [root] : []).concat(
            Array.prototype.slice.call(root.querySelectorAll ? root.querySelectorAll(SELECTOR) : [])
        ).forEach(function (el) {
            if (el.dataset.slideWatched) return;
            if (!started) {
                // Keep it shifted (CSS did that from the first paint) until the observer decides
                setClass(el, 'slide-pending-below');
                el.dataset.slideOnLoad = '1';
            }
            el.dataset.slideWatched = '1';
            observer.observe(el);
        });
    }

    function init() {
        watch(document);
        started = true;

        // Items added by infinite scroll (feeds only)
        var main = document.getElementById('main-content');
        if (main) {
            new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    m.addedNodes.forEach(function (n) { if (n.nodeType === 1) watch(n); });
                });
            }).observe(main, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

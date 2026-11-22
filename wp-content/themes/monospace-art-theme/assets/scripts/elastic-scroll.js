/**
 * Elastic Scroll — monospace ART
 *
 * Virtual scroll with a spring-gap effect. While scrolling, the space between
 * elements grows proportionally to scroll speed, then springs back to normal —
 * as if every element is connected by an invisible elastic string.
 *
 * Two levels of spreading:
 *   L1 — direct children of the elastic layer (major structural blocks)
 *   L2 — their direct children (content items: posts, paragraphs, boxes…)
 *
 * Toggle: small 〰 button fixed to bottom-right. State persists via localStorage.
 */
(function () {
    'use strict';

    var STORAGE_KEY  = 'ms-elastic-scroll';
    var SCROLL_LERP  = 0.09;   // how quickly scroll catches up (lower = more lag)
    var SPREAD_LERP  = 0.08;   // how quickly spread decays back to zero
    var SPREAD_SCALE = 2.0;    // velocity (px/frame) → spread (px per L1 child gap)
    var L2_FACTOR    = 0.25;   // L2 children spread at 25 % of the L1 spread value

    var layer      = null;
    var proxy      = null;
    var ro         = null;
    var raf        = null;
    var toggleBtn  = null;

    var current    = 0;
    var prevScroll = 0;
    var spread     = 0;
    var enabled    = false;

    // Precomputed target lists (built once on enable)
    var targets = [];  // { el, origTransform, l1i, l2i }
    //   l1i  = L1 index  (direct child of layer)
    //   l2i  = L2 index within its L1 parent, or -1 if it IS the L1 element

    // ------------------------------------------------------------------
    function lerp(a, b, t) { return a + (b - a) * t; }

    // ------------------------------------------------------------------
    // Build target list (called once on enable, not every frame)
    // ------------------------------------------------------------------
    function buildTargets() {
        targets = [];
        var l1children = layer.children;

        for (var i = 0; i < l1children.length; i++) {
            var l1 = l1children[i];

            targets.push({
                el:            l1,
                origTransform: l1.style.transform || '',
                l1i:           i,
                l2i:           -1
            });

            var l2children = l1.children;
            for (var j = 0; j < l2children.length; j++) {
                var l2 = l2children[j];
                targets.push({
                    el:            l2,
                    origTransform: l2.style.transform || '',
                    l1i:           i,
                    l2i:           j
                });
            }
        }
    }

    // ------------------------------------------------------------------
    // Clear all transforms we applied (called on disable)
    // ------------------------------------------------------------------
    function clearTargetTransforms() {
        for (var k = 0; k < targets.length; k++) {
            targets[k].el.style.transform = targets[k].origTransform;
        }
        targets = [];
    }

    // ------------------------------------------------------------------
    // Animation loop
    // ------------------------------------------------------------------
    function tick() {
        var scrollY  = window.scrollY;
        var velocity = scrollY - prevScroll;
        prevScroll   = scrollY;

        current = lerp(current, scrollY, SCROLL_LERP);
        spread  = lerp(spread, velocity * SPREAD_SCALE, SPREAD_LERP);

        if (layer) {
            layer.style.transform = 'translateY(' + (-current) + 'px)';

            for (var k = 0; k < targets.length; k++) {
                var t = targets[k];

                if (t.l2i === -1) {
                    // L1: each consecutive structural block shifts by one unit of spread
                    t.el.style.transform = 'translateY(' + (spread * t.l1i) + 'px)';
                } else {
                    // L2: children within each L1 block spread at a reduced rate.
                    // The parent's L1 transform is inherited visually; this adds
                    // an extra per-item offset within that block.
                    t.el.style.transform = 'translateY(' + (spread * t.l2i * L2_FACTOR) + 'px)';
                }
            }
        }

        raf = requestAnimationFrame(tick);
    }

    // ------------------------------------------------------------------
    // Enable
    // ------------------------------------------------------------------
    function enable() {
        if (enabled) return;
        enabled = true;
        localStorage.setItem(STORAGE_KEY, '1');

        proxy = document.createElement('div');
        proxy.id = 'ms-elastic-proxy';
        proxy.setAttribute('aria-hidden', 'true');
        proxy.style.cssText =
            'position:absolute;top:0;left:0;width:1px;' +
            'pointer-events:none;visibility:hidden;';

        layer = document.createElement('div');
        layer.id = 'ms-elastic-layer';
        layer.style.cssText =
            'position:fixed;top:0;left:0;width:100%;will-change:transform;';

        var leaveInBody = [
            document.querySelector('.primary-menu-bar'),
            toggleBtn
        ];

        var bodyKids = Array.prototype.slice.call(document.body.children);
        bodyKids.forEach(function (child) {
            if (leaveInBody.indexOf(child) === -1 && child.tagName !== 'SCRIPT') {
                layer.appendChild(child);
            }
        });

        document.body.style.position  = 'relative';
        document.body.style.overflowX = 'hidden';
        document.body.appendChild(proxy);
        document.body.appendChild(layer);

        function syncHeight() {
            if (proxy && layer) proxy.style.height = layer.scrollHeight + 'px';
        }
        syncHeight();

        if (window.ResizeObserver) {
            ro = new ResizeObserver(syncHeight);
            ro.observe(layer);
        }

        current    = window.scrollY;
        prevScroll = window.scrollY;
        spread     = 0;

        buildTargets();
        tick();

        toggleBtn.classList.add('ms-elastic-on');
        toggleBtn.title = 'Elastic scroll: ON — click to disable';
    }

    // ------------------------------------------------------------------
    // Disable
    // ------------------------------------------------------------------
    function disable() {
        if (!enabled) return;
        enabled = false;
        localStorage.setItem(STORAGE_KEY, '0');

        cancelAnimationFrame(raf);
        raf = null;
        if (ro) { ro.disconnect(); ro = null; }

        if (layer) {
            clearTargetTransforms();
            layer.style.transform = '';

            var anchor = proxy || layer;
            var kids = Array.prototype.slice.call(layer.children);
            kids.forEach(function (child) {
                document.body.insertBefore(child, anchor);
            });

            layer.remove();
            layer = null;
        }

        if (proxy) { proxy.remove(); proxy = null; }

        document.body.style.position  = '';
        document.body.style.overflowX = '';
        spread     = 0;
        prevScroll = window.scrollY;

        toggleBtn.classList.remove('ms-elastic-on');
        toggleBtn.title = 'Elastic scroll: OFF — click to enable';
    }

    // ------------------------------------------------------------------
    // Toggle button
    // ------------------------------------------------------------------
    function createToggle() {
        var style = document.createElement('style');
        style.textContent =
            '#ms-elastic-toggle{' +
                'position:fixed;bottom:18px;right:18px;z-index:2147483647;' +
                'width:28px;height:28px;border-radius:50%;border:none;' +
                'background:rgba(0,0,0,0.13);color:#555;font-size:15px;' +
                'cursor:pointer;display:flex;align-items:center;' +
                'justify-content:center;padding:0;' +
                'backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);' +
                'transition:background 0.2s,color 0.2s,transform 0.15s;' +
                'user-select:none;' +
            '}' +
            '#ms-elastic-toggle:hover{background:rgba(0,0,0,0.25);transform:scale(1.1);}' +
            '#ms-elastic-toggle.ms-elastic-on{background:rgba(4,107,210,0.55);color:#fff;}' +
            '#ms-elastic-toggle.ms-elastic-on:hover{background:rgba(4,107,210,0.75);}';
        document.head.appendChild(style);

        toggleBtn           = document.createElement('button');
        toggleBtn.id        = 'ms-elastic-toggle';
        toggleBtn.title     = 'Elastic scroll: OFF — click to enable';
        toggleBtn.innerHTML = '〰';

        toggleBtn.addEventListener('click', function () {
            if (enabled) disable();
            else enable();
        });

        document.body.appendChild(toggleBtn);
    }

    // ------------------------------------------------------------------
    // Boot
    // ------------------------------------------------------------------
    document.addEventListener('DOMContentLoaded', function () {
        createToggle();
        if (localStorage.getItem(STORAGE_KEY) === '1') enable();
    });

})();

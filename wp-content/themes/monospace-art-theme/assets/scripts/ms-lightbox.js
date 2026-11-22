/**
 * The site's lightbox (2026-10-19, replaced GLightbox): one image at a time, no buttons.
 * A tap / click closes it, a sideways swipe / drag or the arrow keys change the image, a swipe up or
 * down or Escape closes it. Each change replaces the image, so nothing is left behind (GLightbox left
 * faded "ghost" images on phones).
 *
 * Pinch to zoom (2026-10-19): two fingers zoom around the point between them (up to 5x); zoomed, one
 * finger pans, a tap zooms back out (instead of closing) and swiping to the next image waits; after a zoom
 * the full-size file replaces a srcset copy. Option zoomBackground: the background layer zooms along (the
 * print preview on an image / pattern / color background); otherwise only the image does.
 *
 * Used by:
 * - post images: links a[data-gallery] (set up by includes/modules/frontend/content/lightbox.php),
 *   every link with the same data-gallery on the page is one set; the image's own srcset goes along,
 *   so a phone loads a phone-sized copy, not the multi-MB original.
 * - "See a preview" on the price card (commerce/print-order-shortcode.php), through
 *   msLightbox.open(slides, start, { skin: 'ms-pp', open: 'fade', caption: true }).
 *
 * Options also: zoomBackground (see above).
 * Slides: { src, srcset?, label?, alt?, scale? } - alt (the image's full alt text, 2026-11-12) is printed at the bottom of the screen, outside the zoomed stage; scale (0-1) goes to the skin as --lb-scale (the print
 * preview shows prints at relative sizes with it). Options: skin (extra class on the lightbox, for its look),
 * open ('none' | 'fade' | 'zoom'), caption (show the label under the image).
 * Base CSS is added on first use; a skin's CSS comes from whoever uses it.
 */
window.msLightbox = (function () {
    var CSS = ''
        + 'html.ms-lb-open{overflow:hidden;scrollbar-gutter:stable}'
        + '.ms-lb{position:fixed;inset:0;z-index:999999;touch-action:none;cursor:zoom-out;outline:none;-webkit-user-select:none;user-select:none;color:#fff}'
        + '.ms-lb-layer,.ms-lb-stage{position:absolute;inset:0;transform-origin:0 0}'
        + '.ms-lb.is-zoom-anim .ms-lb-layer,.ms-lb.is-zoom-anim .ms-lb-stage{transition:transform .2s ease}'
        + '.ms-lb.is-zoomed{cursor:grab}'
        + '.ms-lb-layer{background:#000}'
        + '.ms-lb-stage{display:flex;align-items:center;justify-content:center}'
        + '.ms-lb-slide{margin:0;display:flex;flex-direction:column;align-items:center}'
        + '.ms-lb-slide img{display:block;width:auto;height:auto;max-width:100vw;max-height:100vh;max-height:100dvh;pointer-events:none;-webkit-user-drag:none;transition:opacity .2s ease}'
        // Post images: an explicit box (fitted inside it), not the file's own size. iPhone Safari sized a srcset copy
        // to a thumbnail (about 90px) when left to "auto" (2026-11-10); the print preview skin sets its own width.
        + '.ms-lb-post .ms-lb-slide img{width:100vw;height:100vh;height:100dvh;max-width:none;max-height:none;object-fit:contain}'
        + '.ms-lb-slide figcaption{margin-top:10px;font-size:15px;text-align:center}'
        + '.ms-lb-alt{position:absolute;left:0;right:0;bottom:0;margin:0;padding:48px max(16px,env(safe-area-inset-right)) max(14px,env(safe-area-inset-bottom)) max(16px,env(safe-area-inset-left));max-height:45%;overflow:hidden;box-sizing:border-box;background:linear-gradient(to bottom,rgba(0,0,0,0),rgba(0,0,0,.78) 42%);color:#fff;font-family:var(--ms-font-ui,\'Open Sans\',sans-serif);font-size:15px;line-height:1.45;text-align:center;text-shadow:0 1px 2px rgba(0,0,0,.6);pointer-events:none}'
        + '.ms-lb-alt[hidden]{display:none}'
        + '.ms-lb.is-zoomed .ms-lb-alt{opacity:0}'
        + '.ms-lb-slide.is-loading img{opacity:0;position:absolute}'
        + '.ms-lb-slide.is-loading::after{content:"";width:34px;height:34px;border-radius:50%;border:3px solid rgba(128,128,128,.3);border-top-color:rgba(128,128,128,.9);animation:ms-lb-spin .8s linear infinite}'
        + '@keyframes ms-lb-spin{to{transform:rotate(360deg)}}'
        + '.ms-lb.fx-fade .ms-lb-layer,.ms-lb.fx-fade .ms-lb-stage,.ms-lb.fx-zoom .ms-lb-layer,.ms-lb.fx-zoom .ms-lb-stage{transition:opacity .3s ease,transform .3s ease}'
        + '.ms-lb.is-hidden .ms-lb-layer,.ms-lb.is-hidden .ms-lb-stage{opacity:0}'
        + '.ms-lb.fx-zoom.is-hidden .ms-lb-stage{transform:scale(.85)}'
        + '@media (prefers-reduced-motion:reduce){.ms-lb .ms-lb-layer,.ms-lb .ms-lb-stage{transition:none!important}}';

    var box = null, stage = null, layer = null, altBox = null, slides = [], index = 0, opts = {}, lastFocus = null, drag = null, moved = false, styled = false;
    var MAX_ZOOM = 5, zoom = { s: 1, x: 0, y: 0 }, pointers = {}, pinch = null, pan = null;

    function el(tag, cls) { var e = document.createElement(tag); if (cls) e.className = cls; return e; }

    function image(s) {
        var img = new Image();
        if (s.srcset) { img.sizes = '100vw'; img.srcset = s.srcset; }
        img.src = s.src;
        return img;
    }

    function render() {
        var s = slides[index], fig = el('figure', 'ms-lb-slide is-loading'), img = image(s);
        if (s.scale) fig.style.setProperty('--lb-scale', s.scale);
        img.alt = s.label || '';
        img.decoding = 'async';
        if (img.complete && img.naturalWidth) fig.classList.remove('is-loading');
        img.onload = img.onerror = function () { fig.classList.remove('is-loading'); };
        fig.appendChild(img);
        if (opts.caption && s.label) { var c = el('figcaption'); c.textContent = s.label; fig.appendChild(c); }
        stage.replaceChildren(fig);
        altBox.textContent = s.alt || '';
        altBox.hidden = !s.alt;
        setZoom(1, 0, 0);
        box.setAttribute('aria-label', s.label || 'Image');
        // The next and previous image, ready for a swipe
        if (slides.length > 1) [1, -1].forEach(function (d) { image(slides[(index + d + slides.length) % slides.length]); });
    }

    function go(step) {
        if (slides.length < 2) return;
        index = (index + step + slides.length) % slides.length;
        render();
    }

    function open(list, start, options) {
        if (box || !list.length) return;
        if (!styled) { var st = el('style'); st.textContent = CSS; document.head.appendChild(st); styled = true; }
        slides = list; index = Math.max(0, Math.min(start || 0, list.length - 1)); opts = options || {};
        var fx = opts.open === 'fade' || opts.open === 'zoom' ? opts.open : '';
        lastFocus = document.activeElement;
        box = el('div', 'ms-lb' + (opts.skin ? ' ' + opts.skin : '') + (fx ? ' fx-' + fx + ' is-hidden' : ''));
        box.setAttribute('role', 'dialog');
        box.setAttribute('aria-modal', 'true');
        box.tabIndex = -1;
        stage = el('div', 'ms-lb-stage');
        layer = el('div', 'ms-lb-layer');
        altBox = el('p', 'ms-lb-alt');
        altBox.hidden = true;
        box.append(layer, stage, altBox);
        render();
        document.documentElement.classList.add('ms-lb-open');
        document.body.appendChild(box);
        box.focus({ preventScroll: true });
        if (fx) { box.offsetWidth; box.classList.remove('is-hidden'); }
        document.addEventListener('keydown', onKey);
    }

    function close() {
        if (!box) return;
        var gone = box, fx = /\bfx-/.test(gone.className);
        box = null; drag = null; pinch = null; pan = null; pointers = {};
        document.removeEventListener('keydown', onKey);
        document.documentElement.classList.remove('ms-lb-open');
        if (lastFocus && lastFocus.focus) lastFocus.focus({ preventScroll: true });
        if (!fx) return gone.remove();
        gone.classList.add('is-hidden');
        setTimeout(function () { gone.remove(); }, 300);
    }

    function onKey(e) {
        if (e.key === 'Escape') zoom.s > 1 ? resetZoom() : close();
        else if (e.key === 'ArrowRight') go(1);
        else if (e.key === 'ArrowLeft') go(-1);
        else if (e.key === 'Tab') e.preventDefault(); // nothing to tab to - focus stays here
    }

    // ---- Zoom: one transform on the stage (and the background layer, with zoomBackground) ----
    function setZoom(sc, x, y, animate) {
        if (!box) return;
        var w = box.clientWidth, h = box.clientHeight;
        sc = Math.max(1, Math.min(MAX_ZOOM, sc));
        // Keep the zoomed stage covering the screen - no panning off into empty space
        x = Math.min(0, Math.max(w - sc * w, x));
        y = Math.min(0, Math.max(h - sc * h, y));
        zoom = { s: sc, x: x, y: y };
        var t = sc === 1 ? '' : 'translate(' + x + 'px,' + y + 'px) scale(' + sc + ')';
        box.classList.toggle('is-zoom-anim', !!animate);
        stage.style.transform = t;
        layer.style.transform = opts.zoomBackground ? t : '';
        box.classList.toggle('is-zoomed', sc > 1);
    }
    function resetZoom() { setZoom(1, 0, 0, true); }

    // Zoomed in: swap a srcset copy for the full-size file once it has loaded
    function sharpen() {
        var s = slides[index], img = stage.querySelector('img');
        if (!img || !s.srcset || img.dataset.full) return;
        img.dataset.full = '1';
        var full = new Image();
        full.onload = function () { if (img.isConnected) { img.removeAttribute('srcset'); img.removeAttribute('sizes'); img.src = s.src; } };
        full.src = s.src;
    }

    function points() { return Object.keys(pointers).map(function (k) { return pointers[k]; }); }
    function startPinch() {
        var p = points(), a = p[0], b = p[1];
        var mx = (a.x + b.x) / 2, my = (a.y + b.y) / 2;
        // The point under the fingers, in the stage's own (unzoomed) coordinates
        pinch = { d: Math.hypot(a.x - b.x, a.y - b.y) || 1, s: zoom.s, lx: (mx - zoom.x) / zoom.s, ly: (my - zoom.y) / zoom.s };
        pan = null;
        if (drag && drag.slide) drag.slide.style.transform = '';
        drag = null;
        moved = true; // a pinch never ends as a tap
    }
    function startPan(p) { pan = { x: p.x, y: p.y, zx: zoom.x, zy: zoom.y }; }

    // Fingers / mouse: two = pinch; one while zoomed = pan; one otherwise = swipe (sideways changes the
    // image, up or down closes; the image follows the finger). A press that barely moves = a tap, handled
    // on its click (so it can't fall through to the page): zoomed - zoom back out, else close.
    document.addEventListener('pointerdown', function (e) {
        if (!box || !box.contains(e.target)) return;
        pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
        var n = Object.keys(pointers).length;
        if (n === 2) return startPinch();
        if (n > 2) return;
        moved = false;
        if (zoom.s > 1) return startPan(pointers[e.pointerId]);
        drag = { x: e.clientX, y: e.clientY, dx: 0, dy: 0, slide: stage.firstChild };
    });
    document.addEventListener('pointermove', function (e) {
        if (!box || !pointers[e.pointerId]) return;
        pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
        if (pinch && Object.keys(pointers).length >= 2) {
            var p = points(), a = p[0], b = p[1];
            var sc = Math.max(0.6, Math.min(MAX_ZOOM, pinch.s * Math.hypot(a.x - b.x, a.y - b.y) / pinch.d));
            var mx = (a.x + b.x) / 2, my = (a.y + b.y) / 2;
            // Below 1 it follows the fingers a little (rubber band), then snaps back on release
            var x = mx - sc * pinch.lx, y = my - sc * pinch.ly;
            if (sc < 1) { stage.style.transform = 'translate(' + x + 'px,' + y + 'px) scale(' + sc + ')'; if (opts.zoomBackground) layer.style.transform = stage.style.transform; zoom.s = sc; return; }
            return setZoom(sc, x, y);
        }
        if (pan) {
            if (Math.abs(e.clientX - pan.x) > 10 || Math.abs(e.clientY - pan.y) > 10) moved = true;
            return setZoom(zoom.s, pan.zx + e.clientX - pan.x, pan.zy + e.clientY - pan.y);
        }
        if (!drag) return;
        drag.dx = e.clientX - drag.x;
        drag.dy = e.clientY - drag.y;
        if (!drag.slide) return;
        var sideways = Math.abs(drag.dx) > Math.abs(drag.dy);
        drag.slide.style.transform = sideways
            ? (slides.length > 1 ? 'translateX(' + drag.dx + 'px)' : '')
            : 'translateY(' + drag.dy + 'px)';
    });
    function lift(e) {
        if (!pointers[e.pointerId]) return;
        delete pointers[e.pointerId];
        if (!box) return;
        var left = points();
        if (pinch) {
            if (left.length >= 2) return startPinch();
            pinch = null;
            if (zoom.s <= 1.02) resetZoom(); else { setZoom(zoom.s, zoom.x, zoom.y); sharpen(); }
            if (left.length === 1 && zoom.s > 1) startPan(left[0]);
            return;
        }
        if (pan) { pan = null; return; }
        if (!drag || e.type === 'pointercancel') { if (drag && drag.slide) drag.slide.style.transform = ''; drag = null; return; }
        var d = drag; drag = null;
        moved = Math.abs(d.dx) > 10 || Math.abs(d.dy) > 10;
        if (!moved) return;
        var sideways = Math.abs(d.dx) > Math.abs(d.dy);
        if (sideways && slides.length > 1 && Math.abs(d.dx) > 50) return go(d.dx < 0 ? 1 : -1);
        if (!sideways && Math.abs(d.dy) > 90) return close();
        if (d.slide) { d.slide.style.transition = 'transform .2s ease'; d.slide.style.transform = ''; }
    }
    document.addEventListener('pointerup', lift);
    document.addEventListener('pointercancel', lift);

    document.addEventListener('click', function (e) {
        if (box && box.contains(e.target)) { e.preventDefault(); if (!moved) { zoom.s > 1 ? resetZoom() : close(); } return; }

        // Post images: a[data-gallery] - the set is every link with the same data-gallery
        var link = e.target.closest('a[data-gallery]');
        if (!link || e.ctrlKey || e.metaKey || e.shiftKey || e.button > 0) return;
        e.preventDefault();
        var links = Array.prototype.slice.call(document.querySelectorAll('a[data-gallery="' + link.dataset.gallery + '"]'));
        open(links.map(function (a) {
            var img = a.querySelector('img');
            return { src: a.href, srcset: img && img.getAttribute('srcset') || '', label: a.dataset.title || (img && img.alt) || '', alt: a.dataset.alt || '' };
        }), links.indexOf(link), { skin: 'ms-lb-post' });
    });

    return { open: open, close: close };
})();

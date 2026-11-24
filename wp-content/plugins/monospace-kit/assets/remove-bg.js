/* Remove background (modules/admin/remove-background.php): swipe over a color to make it transparent, like Keynote's Instant Alpha. */
(function () {
    'use strict';
    if (!window.msRemoveBg) return;
    var cfg = window.msRemoveBg, MAX = 3000, UNDO_MAX = 8;

    /* ---- the button in Edit Image ---- */
    function addButtons() {
        var menus = document.querySelectorAll('.imgedit-menu');
        for (var i = 0; i < menus.length; i++) {
            var menu = menus[i];
            if (menu.querySelector('.ms-rmbg-btn')) continue;
            var panel = menu.closest('[id^="imgedit-panel-"]');
            if (!panel) continue;
            var id = parseInt(panel.id.replace('imgedit-panel-', ''), 10);
            if (!id) continue;
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'button ms-rmbg-btn';
            b.textContent = 'Remove background';
            b.addEventListener('click', (function (n) { return function (e) { e.preventDefault(); open(n); }; })(id));
            menu.appendChild(b);
        }
    }
    var timer = 0;
    new MutationObserver(function () {
        if (timer) return;
        timer = setTimeout(function () { timer = 0; addButtons(); }, 100);
    }).observe(document.body, { childList: true, subtree: true });
    addButtons();

    /* ---- the editor ---- */
    function el(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html) e.innerHTML = html; return e; }

    function open(id) {
        var root = el('div', 'ms-rmbg');
        root.setAttribute('role', 'dialog');
        root.setAttribute('aria-label', 'Remove background');
        root.innerHTML =
            '<div class="ms-rmbg-bar">' +
            '<h2>Remove background</h2>' +
            '<label>Similar colors <input type="range" class="ms-tol" min="1" max="60" value="12"> <span class="ms-tolv">12</span></label>' +
            '<label><input type="checkbox" class="ms-touch" checked> Only touching areas</label>' +
            '<button type="button" class="button ms-undo" disabled>Undo</button>' +
            '<button type="button" class="button ms-reset" disabled>Reset</button>' +
            '<span class="ms-rmbg-sp"></span>' +
            '<button type="button" class="button ms-cancel">Cancel</button>' +
            '<button type="button" class="button button-primary ms-save" disabled>Save as new image</button>' +
            '<button type="button" class="ms-rmbg-x" aria-label="Close" title="Close (Esc)"></button>' +
            '</div>' +
            '<div class="ms-rmbg-hint">Swipe over a color to remove it and the colors like it. Raise “Similar colors” to take more at once; untick “Only touching areas” to remove that color everywhere. The original is never changed: the result is saved as a new PNG.</div>' +
            '<div class="ms-rmbg-stage"><div class="ms-rmbg-msg">Loading…</div></div>';
        document.body.appendChild(root);

        var stage = root.querySelector('.ms-rmbg-stage'), msg = root.querySelector('.ms-rmbg-msg');
        var tol = root.querySelector('.ms-tol'), tolv = root.querySelector('.ms-tolv'), touch = root.querySelector('.ms-touch');
        var bUndo = root.querySelector('.ms-undo'), bReset = root.querySelector('.ms-reset'), bSave = root.querySelector('.ms-save');
        var canvas, ctx, img, d, w, h, original, undo = [], title = '', saving = false;

        function close() {
            document.removeEventListener('keydown', onKey, true);
            root.remove();
        }
        function note(text, cls) {
            var old = root.querySelector('.ms-rmbg-done, .ms-rmbg-err');
            if (old) old.remove();
            if (!text) return;
            var n = el('div', cls); n.innerHTML = text;
            root.insertBefore(n, stage);
        }
        function buttons() {
            bUndo.disabled = !undo.length;
            bReset.disabled = !undo.length;
            bSave.disabled = !undo.length || saving;
        }
        function onKey(e) {
            if (e.key === 'Escape') { e.stopPropagation(); close(); }
            else if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'z') { e.preventDefault(); e.stopPropagation(); doUndo(); }
        }
        document.addEventListener('keydown', onKey, true);
        root.querySelector('.ms-cancel').addEventListener('click', close);
        root.querySelector('.ms-rmbg-x').addEventListener('click', close);
        tol.addEventListener('input', function () { tolv.textContent = tol.value; });

        function doUndo() {
            if (!undo.length) return;
            d.set(undo.pop());
            ctx.putImageData(img, 0, 0);
            buttons();
        }
        bUndo.addEventListener('click', doUndo);
        bReset.addEventListener('click', function () { d.set(original); undo = []; ctx.putImageData(img, 0, 0); buttons(); });

        /* the picture */
        var form = new FormData();
        form.append('action', 'monospace_rmbg_info'); form.append('nonce', cfg.nonce); form.append('id', id);
        fetch(cfg.ajax, { method: 'POST', body: form, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j.success) throw new Error(j.data || 'Could not load the picture.');
                title = j.data.title;
                return new Promise(function (ok, no) {
                    var im = new Image();
                    im.onload = function () { ok(im); };
                    im.onerror = function () { no(new Error('The picture could not be loaded.')); };
                    im.src = j.data.url;
                });
            })
            .then(function (im) {
                var s = Math.min(1, MAX / Math.max(im.naturalWidth, im.naturalHeight));
                w = Math.round(im.naturalWidth * s); h = Math.round(im.naturalHeight * s);
                canvas = el('canvas'); canvas.width = w; canvas.height = h;
                ctx = canvas.getContext('2d', { willReadFrequently: true });
                ctx.drawImage(im, 0, 0, w, h);
                img = ctx.getImageData(0, 0, w, h); // throws if the browser treats the picture as foreign
                d = img.data;
                original = d.slice();
                msg.remove();
                stage.appendChild(canvas);
                bind();
            })
            .catch(function (e) { msg.textContent = e.message || 'Could not load the picture.'; });

        /* painting */
        var last = null, changed = false, dirty = null, stroke = false;

        function at(e) {
            var r = canvas.getBoundingClientRect();
            return { x: Math.floor((e.clientX - r.left) * w / r.width), y: Math.floor((e.clientY - r.top) * h / r.height) };
        }
        function grow(x, y) {
            if (!dirty) dirty = [x, y, x, y];
            else { if (x < dirty[0]) dirty[0] = x; if (y < dirty[1]) dirty[1] = y; if (x > dirty[2]) dirty[2] = x; if (y > dirty[3]) dirty[3] = y; }
        }
        function paint(px, py) {
            if (px < 0 || py < 0 || px >= w || py >= h) return;
            if (d[(py * w + px) * 4 + 3] === 0) return;
            // the color under the pointer: the average of the 3x3 around it (one speck doesn't decide)
            var r = 0, g = 0, b = 0, n = 0;
            for (var yy = Math.max(0, py - 1); yy <= Math.min(h - 1, py + 1); yy++)
                for (var xx = Math.max(0, px - 1); xx <= Math.min(w - 1, px + 1); xx++) {
                    var q = (yy * w + xx) * 4;
                    if (d[q + 3] === 0) continue;
                    r += d[q]; g += d[q + 1]; b += d[q + 2]; n++;
                }
            if (!n) return;
            r /= n; g /= n; b /= n;
            var lim = Math.pow(tol.value / 100 * 441.7, 2);
            function like(p) {
                if (d[p + 3] === 0) return false;
                var dr = d[p] - r, dg = d[p + 1] - g, db = d[p + 2] - b;
                return dr * dr + dg * dg + db * db <= lim;
            }
            if (!touch.checked) {
                for (var i = 0, len = w * h; i < len; i++) {
                    var p = i * 4;
                    if (like(p)) { d[p + 3] = 0; grow(i % w, (i / w) | 0); changed = true; }
                }
                return;
            }
            var stack = [py * w + px];
            while (stack.length) {
                var i2 = stack.pop(), p2 = i2 * 4;
                if (!like(p2)) continue;
                d[p2 + 3] = 0; changed = true;
                var x = i2 % w, y = (i2 / w) | 0;
                grow(x, y);
                if (x > 0) stack.push(i2 - 1);
                if (x < w - 1) stack.push(i2 + 1);
                if (y > 0) stack.push(i2 - w);
                if (y < h - 1) stack.push(i2 + w);
            }
        }
        function flush() {
            if (!dirty) return;
            var x = dirty[0], y = dirty[1];
            ctx.putImageData(img, 0, 0, x, y, dirty[2] - x + 1, dirty[3] - y + 1);
            dirty = null;
        }
        function bind() {
            canvas.addEventListener('pointerdown', function (e) {
                if (e.button > 0) return;
                e.preventDefault();
                canvas.setPointerCapture(e.pointerId);
                stroke = true; changed = false;
                undo.push(d.slice()); if (undo.length > UNDO_MAX + 1) undo.shift();
                note('');
                last = at(e);
                paint(last.x, last.y); flush();
            });
            canvas.addEventListener('pointermove', function (e) {
                if (!stroke) return;
                var p = at(e), dx = p.x - last.x, dy = p.y - last.y, steps = Math.max(1, Math.ceil(Math.max(Math.abs(dx), Math.abs(dy)) / 3));
                for (var s = 1; s <= steps; s++) paint(Math.round(last.x + dx * s / steps), Math.round(last.y + dy * s / steps));
                last = p;
                flush();
            });
            function end() {
                if (!stroke) return;
                stroke = false;
                if (!changed) undo.pop();
                buttons();
            }
            canvas.addEventListener('pointerup', end);
            canvas.addEventListener('pointercancel', end);
        }

        /* save as a new image */
        bSave.addEventListener('click', function () {
            if (saving || !undo.length) return;
            saving = true; buttons(); bSave.textContent = 'Saving…'; note('');
            canvas.toBlob(function (blob) {
                var f = new FormData();
                f.append('action', 'monospace_rmbg_save'); f.append('nonce', cfg.nonce); f.append('id', id);
                f.append('image', blob, 'cutout.png');
                fetch(cfg.ajax, { method: 'POST', body: f, credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (!j.success) throw new Error(j.data || 'Saving failed.');
                        saving = false; bSave.textContent = 'Save as new image'; buttons();
                        note('Saved to the Media Library as “' + j.data.title.replace(/</g, '&lt;') + '”. <a href="' + j.data.edit + '" target="_blank" rel="noopener">Open it</a>', 'ms-rmbg-done');
                    })
                    .catch(function (e) {
                        saving = false; bSave.textContent = 'Save as new image'; buttons();
                        note(e.message || 'Saving failed.', 'ms-rmbg-err');
                    });
            }, 'image/png');
        });
    }
})();

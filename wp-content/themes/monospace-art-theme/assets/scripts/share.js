/**
 * Share button (design/share-button.php): the device's share sheet where there is one (navigator.share), else the
 * small menu - Copy link, Pinterest, Facebook, Bluesky, Email. The sheet gets only the link (2026-10-28). The menu closes on a click elsewhere or Escape.
 * Computers also have Facebook / Instagram / Bluesky buttons: Instagram copies the link (it can't take one from a website)
 * and opens instagram.com in a new tab, with a note to paste it there.
 */
(function () {
    function close(except) {
        document.querySelectorAll('.ms-share-menu:not([hidden])').forEach(function (m) {
            if (m === except) return;
            m.hidden = true;
            m.previousElementSibling.setAttribute('aria-expanded', 'false');
        });
    }

    function note(box, text) {
        var n = box.querySelector('.ms-share-note');
        if (!n) { n = document.createElement('span'); n.className = 'ms-share-note'; n.setAttribute('role', 'status'); box.appendChild(n); }
        n.textContent = text;
        n.hidden = false;
        clearTimeout(n.t);
        n.t = setTimeout(function () { n.hidden = true; }, 4000);
    }

    document.addEventListener('click', function (e) {
        var ig = e.target.closest('[data-share-instagram]');
        if (ig) { // the link opens instagram.com (new tab) as usual - the page's address goes to the clipboard first
            var box0 = ig.closest('.ms-share');
            if (navigator.clipboard) navigator.clipboard.writeText(box0.dataset.url).then(function () { note(box0, 'Link copied - paste it in Instagram'); }, function () {});
            return;
        }
        var btn = e.target.closest('.ms-share-btn');
        if (btn) {
            e.preventDefault();
            var box = btn.closest('.ms-share'), menu = box.querySelector('.ms-share-menu');
            if (navigator.share) {
                // The link alone, as Safari's own Share does: with a title / text beside it, iOS apps such as Bluesky take the text and drop the link
                navigator.share({ url: box.dataset.url }).catch(function () {});
                return;
            }
            close(menu);
            menu.hidden = !menu.hidden;
            btn.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true');
            return;
        }
        var copy = e.target.closest('[data-share-copy]');
        if (copy) {
            e.preventDefault();
            var url = copy.closest('.ms-share').dataset.url, label = copy.textContent;
            var done = function () { copy.textContent = 'Link copied ✓'; setTimeout(function () { copy.textContent = label; close(); }, 1200); };
            if (navigator.clipboard) navigator.clipboard.writeText(url).then(done, function () { window.prompt('Copy this link:', url); });
            else window.prompt('Copy this link:', url);
            return;
        }
        if (!e.target.closest('.ms-share-menu')) close();
    });

    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();

/**
 * Copy link ([copy_text target=".ms-bio-text" label="Copy bio"], includes/modules/frontend/content/copy-text.php): copies the text of
 * the element the selector names - its paragraphs, headings and list items, one blank line between them, the link itself left out -
 * and says "Copied" for a moment. Without the clipboard API (an older browser, an insecure page) it selects a hidden field and
 * copies that; failing that it shows the text to copy by hand.
 */
(function () {
    function textOf(el) {
        var parts = [];
        el.querySelectorAll('p, h1, h2, h3, h4, h5, h6, li').forEach(function (n) {
            if (n.querySelector('.ms-copy') || n.closest('.ms-copy')) return; // the line the link sits in
            var t = n.textContent.replace(/\s+/g, ' ').trim();
            if (t) parts.push(t);
        });
        return parts.length ? parts.join('\n\n') : el.textContent.replace(/\s+/g, ' ').trim();
    }

    function copy(text) {
        if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
        return new Promise(function (resolve, reject) {
            var field = document.createElement('textarea'), ok = false;
            field.value = text;
            field.setAttribute('readonly', '');
            field.style.cssText = 'position:fixed;top:-1000px;opacity:0';
            document.body.appendChild(field);
            field.select();
            try { ok = document.execCommand('copy'); } catch (e) {}
            document.body.removeChild(field);
            ok ? resolve() : reject();
        });
    }

    document.addEventListener('click', function (e) {
        var link = e.target.closest('.ms-copy');
        if (!link) return;
        e.preventDefault();
        var el = null;
        try { el = document.querySelector(link.dataset.copyTarget); } catch (err) {}
        if (!el) return;
        var text = textOf(el), label = link.querySelector('.ms-copy-label'), was = link.dataset.label || label.textContent;
        link.dataset.label = was;
        copy(text).then(function () {
            label.textContent = 'Copied ✓';
            link.classList.add('is-copied');
            clearTimeout(link.t);
            link.t = setTimeout(function () { label.textContent = was; link.classList.remove('is-copied'); }, 1600);
        }, function () { window.prompt('Copy this text:', text); });
    });
})();

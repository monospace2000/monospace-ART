/**
 * Ko-fi buttons (includes/modules/frontend/content/kofi.php): a click opens Ko-fi's donation page in a small panel on the
 * page - its embed, loaded on the first click only. A modifier-key click (new tab / window) is left to the browser.
 * Esc, the × or a click outside closes it; focus goes back to the button.
 */
(function () {
    'use strict';
    var panel = null, frame = null, opener = null, code = '';

    function build() {
        panel = document.createElement('div');
        panel.className = 'ms-kofi-panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', 'Support on Ko-fi');
        panel.hidden = true;
        panel.innerHTML = '<button type="button" class="ms-kofi-close" aria-label="Close">×</button>';
        frame = document.createElement('iframe');
        frame.title = 'Ko-fi';
        frame.setAttribute('allow', 'payment');
        panel.appendChild(frame);
        document.body.appendChild(panel);
        panel.querySelector('.ms-kofi-close').addEventListener('click', close);
    }

    function open(link) {
        if (!panel) build();
        var want = link.getAttribute('data-ms-kofi');
        if (want !== code) { code = want; frame.src = 'https://ko-fi.com/' + encodeURIComponent(code) + '/?hidefeed=true&widget=true&embed=true'; }
        opener = link;
        // Next to the floating button (bottom left); in the middle for a button in the page
        panel.classList.toggle('is-docked', link.classList.contains('ms-kofi-float'));
        panel.hidden = false;
        link.setAttribute('aria-expanded', 'true');
        panel.querySelector('.ms-kofi-close').focus();
    }

    function close() {
        if (!panel || panel.hidden) return;
        panel.hidden = true;
        if (opener) { opener.setAttribute('aria-expanded', 'false'); opener.focus(); }
    }

    document.addEventListener('click', function (e) {
        var link = e.target.closest && e.target.closest('a[data-ms-kofi]');
        if (link) {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button) return;
            e.preventDefault();
            panel && !panel.hidden && opener === link ? close() : open(link);
            return;
        }
        if (panel && !panel.hidden && !panel.contains(e.target)) close();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();

/**
 * Collapsible category header (feed-headers.php): the title is the toggle, the description folds away, the title and the share / social
 * icons beside it stay. The choice carries over to every category page, like the View settings: localStorage monospace_header_folded
 * ("1" = folded), also a class on <html> that a small script in <head> sets before the page paints (the CSS folds the header from it,
 * so nothing flashes). Without this script nothing folds.
 */
(function () {
    var KEY = 'monospace_header_folded', root = document.documentElement;

    function remember(collapsed) {
        root.classList.toggle('ms-header-folded', collapsed);
        try { localStorage.setItem(KEY, collapsed ? '1' : '0'); } catch (e) {}
    }

    function set(header, collapsed) {
        var toggle = header.querySelector('.feed-toggle'), body = header.querySelector('.feed-description-wrap');
        if (!toggle || !body) return;
        header.classList.toggle('is-collapsed', collapsed);
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        toggle.title = collapsed ? 'Show the description' : 'Hide the description';
        if (collapsed) body.setAttribute('inert', ''); else body.removeAttribute('inert'); // folded text can't be tabbed to
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('.feed-toggle');
        if (!toggle) return;
        e.preventDefault();
        var header = toggle.closest('.feed-header'), collapsed = !header.classList.contains('is-collapsed');
        set(header, collapsed);
        remember(collapsed);
    });

    // the remembered choice (the CSS already shows it from the class on <html>; this brings the header's own state in step)
    if (root.classList.contains('ms-header-folded')) {
        document.querySelectorAll('.feed-header.is-collapsible').forEach(function (h) { set(h, true); });
    }

    // a link opens with Enter; a button also with Space
    document.addEventListener('keydown', function (e) {
        var toggle = e.key === ' ' && e.target.closest && e.target.closest('.feed-toggle');
        if (toggle) { e.preventDefault(); toggle.click(); }
    });
})();

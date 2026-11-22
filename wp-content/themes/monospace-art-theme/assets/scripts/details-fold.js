/**
 * WP Details block (details-fold.php): opens and closes with the same fold as the price card badge (pc-badge-card.js, 2026-11-16).
 * A closed <details> doesn't render its content, so the click is taken over here: .is-open drives the CSS (grid-template-rows
 * 0fr <-> 1fr, _components.scss), and the real `open` attribute follows - set at once when opening, removed when the fold has
 * finished. Without this script the block just opens.
 */
(function () {
    var blocks = document.querySelectorAll('details.wp-block-details');
    if (!blocks.length) return;
    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

    blocks.forEach(function (d) {
        var body = d.querySelector(':scope > .ms-fold-body'), sum = d.querySelector(':scope > summary');
        if (!body || !sum) return;
        d.classList.toggle('is-open', d.open);
        void d.offsetHeight; // settle the starting state before transitions switch on
        d.classList.add('is-anim');

        sum.addEventListener('click', function (e) {
            e.preventDefault();
            if (d.classList.contains('is-open')) {
                d.classList.remove('is-open');
                body.setAttribute('inert', '');
                if (reduce) { d.open = false; return; }
                var done = function (ev) {
                    if (ev && (ev.target !== body || ev.propertyName !== 'grid-template-rows')) return;
                    body.removeEventListener('transitionend', done);
                    if (!d.classList.contains('is-open')) d.open = false;
                };
                body.addEventListener('transitionend', done);
                setTimeout(done, 400); // in case no transition fires
            } else {
                d.open = true;
                body.removeAttribute('inert');
                void d.offsetHeight; // render at 0fr first, then fold out
                d.classList.add('is-open');
            }
        });
    });
})();

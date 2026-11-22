document.addEventListener('DOMContentLoaded', function () {
    const DESKTOP_BREAKPOINT = 1024;
    const toggle = document.querySelector('.mobile-menu-toggle');
    const primaryMenu = document.querySelector('.primary-menu');

    if (!toggle || !primaryMenu) return;

    // Prevent clicks on parent menu items with submenus (desktop only)
    document.querySelectorAll('.primary-menu > li.menu-item-has-children > a').forEach(link => {
        link.addEventListener('click', e => {
            if (window.innerWidth > DESKTOP_BREAKPOINT) {
                e.preventDefault();
                return false;
            }
        });
    });

    // Create overlay container once
    const overlay = document.createElement('div');
    overlay.className = 'mobile-menu-overlay';
    document.body.appendChild(overlay);

    // A floating window, not a page: a fixed header with the close button, the items scroll under it
    const head = document.createElement('div');
    head.className = 'mobile-menu-head';
    head.innerHTML = '<span>Menu</span><button type="button" class="mobile-menu-close" aria-label="Close menu">\u2715</button>';
    overlay.appendChild(head);
    const scroll = document.createElement('div');
    scroll.className = 'mobile-menu-scroll';
    overlay.appendChild(scroll);

    // Behind the window: an invisible, undimmed layer under the menu bar. It catches taps outside the window (they close
    // it instead of reaching the page) and keeps the page from scrolling; the bar itself stays usable
    const backdrop = document.createElement('div');
    backdrop.className = 'mobile-menu-backdrop';
    document.body.appendChild(backdrop);
    const close = function () {
        overlay.classList.remove('active');
        document.body.classList.remove('mobile-menu-open');
    };
    backdrop.addEventListener('click', function (e) { e.preventDefault(); close(); });

    // Clone the menu for mobile (keeps original in place for desktop)
    const mobileMenuClone = primaryMenu.cloneNode(true);
    scroll.appendChild(mobileMenuClone);

    // Open / close - non-modal: the page behind stays usable, the window stays until it is closed or a link is followed
    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        overlay.classList.toggle('active');
        document.body.classList.toggle('mobile-menu-open');
        if (overlay.classList.contains('active')) backdrop.style.top = Math.max(0, overlay.getBoundingClientRect().top - 8) + 'px';
    });
    head.querySelector('.mobile-menu-close').addEventListener('click', function () {
        overlay.classList.remove('active');
        document.body.classList.remove('mobile-menu-open');
        toggle.focus();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && overlay.classList.contains('active')) {
            overlay.classList.remove('active');
            document.body.classList.remove('mobile-menu-open');
        }
    });

    // Hierarchical submenu toggles for mobile menu (a parent is a link, or a header button -
    // menu-items.php)
    const mobileItems = mobileMenuClone.querySelectorAll('li.menu-item-has-children');
    mobileItems.forEach(item => {
        const link = item.querySelector(':scope > a, :scope > .menu-header');
        if (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                item.classList.toggle('open');
                if (link.matches('.menu-header')) link.setAttribute('aria-expanded', item.classList.contains('open'));
            });
        }
    });

    // Close overlay when following a link (not on dividers or labels)
    const leafItems = mobileMenuClone.querySelectorAll('li:not(.menu-item-has-children)');
    leafItems.forEach(item => {
        item.addEventListener('click', function (e) {
            if (!e.target.closest('a')) return;
            overlay.classList.remove('active');
            document.body.classList.remove('mobile-menu-open');
        });
    });

    // Desktop header buttons: aria-expanded follows the dropdown (opened by hover / focus in CSS)
    primaryMenu.querySelectorAll(':scope > li.menu-item-has-children').forEach(item => {
        const header = item.querySelector(':scope > .menu-header');
        if (!header) return;
        const set = open => header.setAttribute('aria-expanded', open);
        item.addEventListener('mouseenter', () => set(true));
        item.addEventListener('mouseleave', () => set(item.contains(document.activeElement)));
        item.addEventListener('focusin', () => set(true));
        item.addEventListener('focusout', e => { if (!item.contains(e.relatedTarget)) set(false); });
    });

    // Close overlay on window resize to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > DESKTOP_BREAKPOINT) {
            overlay.classList.remove('active');
            document.body.classList.remove('mobile-menu-open');
        }
    });
});
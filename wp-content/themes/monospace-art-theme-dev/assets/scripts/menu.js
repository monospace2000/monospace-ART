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

    // Clone the menu for mobile (keeps original in place for desktop)
    const mobileMenuClone = primaryMenu.cloneNode(true);
    overlay.appendChild(mobileMenuClone);

    // Toggle overlay
    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        overlay.classList.toggle('active');
        document.body.classList.toggle('mobile-menu-open');

    });

    // Close when clicking outside
    document.addEventListener('click', function (e) {
        if (!overlay.contains(e.target) && !toggle.contains(e.target)) {
            overlay.classList.remove('active');
            document.body.classList.remove('mobile-menu-open');
        }
    });

    // Hierarchical submenu toggles for mobile menu
    const mobileItems = mobileMenuClone.querySelectorAll('li.menu-item-has-children');
    mobileItems.forEach(item => {
        const link = item.querySelector('a');
        if (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                item.classList.toggle('open');
            });
        }
    });

    // Close overlay when clicking leaf items (no children)
    const leafItems = mobileMenuClone.querySelectorAll('li:not(.menu-item-has-children)');
    leafItems.forEach(item => {
        item.addEventListener('click', function () {
            overlay.classList.remove('active');
        });
    });

    // Close overlay on window resize to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > DESKTOP_BREAKPOINT) {
            overlay.classList.remove('active');
        }
    });
});
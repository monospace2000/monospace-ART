// The menu bar's search icon (design/menu-bar-items.php): opens the search field inside the bar. The form is the site's
// ordinary search (GET /?s=) - this only shows and hides the field.
(function () {
    var bar = document.querySelector('.primary-menu-bar'), box = document.querySelector('.menu-bar-search');
    if (!bar || !box) return;
    var form = box.querySelector('form'), field = box.querySelector('.ms-bar-search-field'),
        open = box.querySelector('.ms-bar-search-open'), close = box.querySelector('.ms-bar-search-close');

    function isOpen() { return box.classList.contains('is-open'); }

    function set(on) {
        box.classList.toggle('is-open', on);
        bar.classList.toggle('is-searching', on); // phones and tablets: the field takes the whole bar (_menu.scss)
        open.setAttribute('aria-expanded', on);
        field.tabIndex = close.tabIndex = on ? 0 : -1;
        if (on) {
            var menu = document.querySelector('.mobile-menu-overlay'); // the phone menu (menu.js) makes room
            if (menu) menu.classList.remove('active');
            document.body.classList.remove('mobile-menu-open');
            field.focus();
        }
    }

    // The icon: open (with the field cleared - a results page pre-fills the last search, and the browser can restore typed
    // text); open with something typed - search; open and empty - close
    open.addEventListener('click', function () {
        if (!isOpen()) { field.value = ''; set(true); }
        else if (field.value.trim() !== '') form.submit();
        else set(false);
    });
    close.addEventListener('click', function () { set(false); open.focus(); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen()) { set(false); open.focus(); }
    });
    // A click elsewhere closes an empty field
    document.addEventListener('click', function (e) {
        if (isOpen() && !box.contains(e.target) && field.value.trim() === '') set(false);
    });
})();

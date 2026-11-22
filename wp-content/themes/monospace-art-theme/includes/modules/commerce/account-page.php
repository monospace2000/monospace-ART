<?php
/**
 * My Account page - appearance only (2026-11-16): the order status chips and the script that switches tabs in place
 * (no page reload, so no flash) and scrolls the current tab into view on phones. The look is in assets/scss/_woocommerce.scss (MY ACCOUNT PAGE);
 * the dashboard tiles are woocommerce/myaccount/dashboard.php.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// The order status as a chip (WooCommerce prints the plain name unless something hooks this column)
add_action( 'woocommerce_my_account_my_orders_column_order-status', function ( $order ) {
    $status = $order->get_status();
    printf(
        '<span class="ms-acct-status is-%s">%s</span>',
        esc_attr( sanitize_html_class( $status ) ),
        esc_html( wc_get_order_status_name( $status ) )
    );
} );

// The empty-state messages on Orders and Downloads are text only (owner, 2026-11-18): WooCommerce's "Browse products" button is taken out
add_filter( 'woocommerce_add_notice', function ( $message ) {
    if ( ! function_exists( 'is_wc_endpoint_url' ) || ! ( is_wc_endpoint_url( 'orders' ) || is_wc_endpoint_url( 'downloads' ) ) ) return $message;
    return trim( preg_replace( '#\s*<a\b[^>]*\bbutton\b[^>]*>.*?</a>#s', '', $message ) );
} );

// The tabs switch in place (2026-11-16; message boxes are text only - their button is lifted out below them): a click on a tab fetches that page, swaps the content and the current pill and
// updates the address - the page itself never reloads, so the background (canvas texture, spotlight layers) is not
// repainted and nothing flashes. Anything unexpected (a failed fetch, content that carries a script, a Log out link, a
// modified click) falls back to the normal page load. Phones: the tabs swipe sideways - the current one is brought
// into view (the bar scrolls, never the page).
add_action( 'wp_footer', function () {
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) return;
    ?>
    <script>
    (function () {
        var nav     = document.querySelector('.woocommerce-MyAccount-navigation');
        var content = document.querySelector('.woocommerce-MyAccount-content');
        if (!nav || !content) return;
        var bar = nav.querySelector('ul');

        // A message box holds text only: its button (Browse products, Browse custom orders ...) goes below it, as on Payment methods
        function lift() {
            var boxes = content.querySelectorAll('.woocommerce-info, .woocommerce-message, .woocommerce-error');
            for (var i = 0; i < boxes.length; i++) {
                var b = boxes[i].querySelector(':scope > a.button, :scope > .button');
                if (b) boxes[i].parentNode.insertBefore(b, boxes[i].nextSibling);
            }
        }
        lift();

        function center() {
            var on = bar && bar.querySelector('.is-active a');
            if (on) bar.scrollLeft = on.offsetLeft - (bar.clientWidth - on.offsetWidth) / 2;
        }
        document.addEventListener('DOMContentLoaded', center);
        if (!window.fetch || !window.DOMParser || !history.pushState) return;

        var shown = location.href, busy = 0;

        function go(url, push) {
            var mine = ++busy;
            content.classList.add('is-loading');
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { if (!r.ok) throw 0; return r.text(); })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    var c = doc.querySelector('.woocommerce-MyAccount-content');
                    var n = doc.querySelector('.woocommerce-MyAccount-navigation');
                    if (!c || !n || c.querySelector('script')) throw 0;
                    if (mine !== busy) return;
                    var from = bar.children, to = n.querySelectorAll('ul > li');
                    if (from.length !== to.length) throw 0;
                    for (var i = 0; i < from.length; i++) from[i].classList.toggle('is-active', to[i].classList.contains('is-active'));
                    var badge = bar.querySelector('.is-active .ms-badge');
                    if (badge) badge.remove();
                    content.innerHTML = c.innerHTML;
                    lift();
                    document.title = doc.title;
                    if (push) history.pushState({}, '', url);
                    shown = url;
                    content.classList.remove('is-loading');
                    center();
                })
                .catch(function () { location.href = url; });
        }

        nav.addEventListener('click', function (e) {
            var a = e.target.closest('a');
            if (!a || e.button || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
            if (a.parentElement.className.indexOf('--customer-logout') > -1) return;
            e.preventDefault();
            if (a.href !== shown) go(a.href, true);
        });
        window.addEventListener('popstate', function () { if (location.href !== shown) go(location.href, false); });
    })();
    </script>
    <?php
} );

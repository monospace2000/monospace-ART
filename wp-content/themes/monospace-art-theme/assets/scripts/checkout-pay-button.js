/* Checkout: the glass Place order button follows the chosen payment method; a light / dark switch reloads the page. With PayPal selected it takes PayPal's
   colors (body.ms-pay-paypal, see _woocommerce.scss) and reads "Pay with PayPal"; any other method gets the
   button's normal look and label back. WooCommerce re-draws the button on every checkout update, so it's re-applied then. */
(function ($) {
    var PAYPAL = 'ppcp-gateway';
    var LABEL = 'Pay with PayPal';
    var original = null;

    function apply() {
        var $btn = $('#place_order');
        if (!$btn.length) return;
        var method = $('form.checkout input[name="payment_method"]:checked').val();
        var isPaypal = method === PAYPAL;
        if (original === null && !isPaypal) original = $btn.text();
        $('body').toggleClass('ms-pay-paypal', isPaypal);
        if (isPaypal) {
            $btn.text(LABEL).attr('data-value', LABEL);
        } else if (original !== null) {
            $btn.text(original).attr('data-value', original);
        }
    }

    $(document.body).on('change', 'input[name="payment_method"]', apply)
                    .on('updated_checkout payment_method_selected', apply);
    $(apply);

    /* The card fields are WooCommerce Payments' iframes: it copies the page's field and label colors once, when it
       mounts them, so a light / dark switch on this page leaves them in the old colors. Reload to draw them again
       (WooCommerce keeps the typed checkout details in the session). */
    var root = document.documentElement;
    var wasDark = root.classList.contains('ms-dark-ui');
    new MutationObserver(function () {
        var dark = root.classList.contains('ms-dark-ui');
        if (dark !== wasDark) { wasDark = dark; window.location.reload(); }
    }).observe(root, { attributes: true, attributeFilter: ['class'] });
})(jQuery);

<?php

// -----------------------------------------------------------
// Add inline critical CSS to prevent FOUC (runs first)
// -----------------------------------------------------------
//add_action('wp_head', 'monospace_block_spacing_critical_css', 1);

function monospace_block_spacing_critical_css() {
    ?>
    <style id="block-spacing-override">
/*
        .wp-block-group,
        .wp-block-columns,
        .wp-block-column {
            margin-block-start: 0 !important;
            margin-block-end: 0 !important;
            padding-block-start: 0 !important;
            padding-block-end: 0 !important;
        }

        .entry-content .wp-block-group {
            margin:  0 !important;
            padding: calc(var(--global-padding)/2) var(--global-padding) !important;
        }
 */
    </style>
    <?php
}

// -----------------------------------------------------------
// Add preconnect for Google Fonts in head (performance optimization)
// -----------------------------------------------------------
function monospace_art_resource_hints($hints, $relation_type) {
    if ('preconnect' === $relation_type) {
        $hints[] = [
            'href' => 'https://fonts.googleapis.com',
            'crossorigin' => 'anonymous',
        ];
        $hints[] = [
            'href' => 'https://fonts.gstatic.com',
            'crossorigin' => 'anonymous',
        ];
    }
    return $hints;
}
add_filter('wp_resource_hints', 'monospace_art_resource_hints', 10, 2);

// -----------------------------------------------------------
// Fewer render-blocking files (2026-10-16) - the page can't be drawn (or jump to a #anchor) until
// every stylesheet / head script is in
// -----------------------------------------------------------

// MailPoet's form font picker loaded ~75 Google Fonts families on every page (3 stylesheets from
// another server). Off - the one font the forms use, Montserrat (form "Clouds"), is in the theme's
// own font request below.
add_filter('mailpoet_display_custom_fonts', '__return_false');

// The home page has no shop: no WooCommerce styles or scripts there (the menu's cart count is
// printed server-side, cart-icon-shortcode.php)
add_filter('woocommerce_enqueue_styles', function ($styles) {
    return is_front_page() ? [] : $styles;
});
add_action('wp_enqueue_scripts', function () {
    if (!is_front_page()) return;
    foreach (['wc-add-to-cart', 'woocommerce', 'wc-jquery-blockui', 'jquery-blockui', 'wc-js-cookie', 'js-cookie'] as $handle) {
        wp_dequeue_script($handle);
    }
}, 99);

// -----------------------------------------------------------
// Enqueue Styles & Scripts (early priority to load before block styles)
// -----------------------------------------------------------
add_action('wp_enqueue_scripts', 'monospace_art_enqueue_assets', 1);

function monospace_art_enqueue_assets() {
    $theme_version = wp_get_theme()->get('Version');

    // -------------------------------------------------------
    // Stylesheets
    // -------------------------------------------------------

    // Google Fonts - using display=optional to prevent layout shift
    wp_enqueue_style(
        'monospace-art-google-fonts',
        'https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400;1,700&family=Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Montserrat:ital,wght@0,400;0,700;1,400;1,700&family=Oswald:wght@200..700&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap',
        array(),
        null
    );

        // Theme styles - compiled from SASS
    wp_enqueue_style(
        'monospace-theme',
        get_template_directory_uri() . '/assets/css/style.css',
        array('monospace-art-google-fonts'),
        filemtime(get_template_directory() . '/assets/css/style.css')
    );

    // -------------------------------------------------------
    // Scripts
    // -------------------------------------------------------
    wp_enqueue_script(
        'monospace-art-menu',
        get_template_directory_uri() . '/assets/scripts/menu.js',
        array('jquery'),
        (string) filemtime( get_template_directory() . '/assets/scripts/menu.js' ),
        true
    );
    // Home page: a # link to a box lands on its row (computers and tablets)
    if ( is_front_page() ) {
        $anchors_js = '/assets/scripts/home-anchors.js';
        wp_enqueue_script( 'monospace-home-anchors', get_template_directory_uri() . $anchors_js, array(), filemtime( get_template_directory() . $anchors_js ), true );
    }
    // Checkout: the Place order button follows the chosen payment method (PayPal colors and label)
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        $pay_js = '/assets/scripts/checkout-pay-button.js';
        wp_enqueue_script( 'monospace-checkout-pay-button', get_template_directory_uri() . $pay_js, array( 'jquery' ), (string) filemtime( get_template_directory() . $pay_js ), true );
    }
    // Fit-text script (container-relative)
    wp_enqueue_script(
        'monospace-fit-text',
        get_template_directory_uri() . '/assets/scripts/fit-text.js',
        array(),
        $theme_version,
        true
    );


}

// Checkout: WooCommerce Payments keeps the card fields' computed colors in localStorage (wcpay_appearance_*, versioned
// only by theme / style changes, not by light / dark) - the dark colors stayed on a light page and the other way round.
// Right after the theme's mode script (wp_head 1), and before the plugin mounts the fields, forget them when the mode
// changed since they were saved.
add_action( 'wp_head', function () {
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return; ?>
<script data-no-optimize="1" data-no-defer="1">
(function () {
    try {
        var m = document.documentElement.classList.contains('ms-dark-ui') ? 'dark' : 'light', k = 'ms-wcpay-mode';
        if (localStorage.getItem(k) !== m) {
            Object.keys(localStorage).forEach(function (x) { if (x.indexOf('wcpay_appearance_') === 0) localStorage.removeItem(x); });
            localStorage.setItem(k, m);
        }
    } catch (e) {}
})();
</script>
<?php
}, 2 );

// -----------------------------------------------------------
// Enqueue Infinite Scroll on all pages (for scroll-to-top button)
// -----------------------------------------------------------
add_action('wp_enqueue_scripts', 'monospace_art_enqueue_infinite_scroll');

function monospace_art_enqueue_infinite_scroll() {
    $theme_version = wp_get_theme()->get('Version');

    wp_enqueue_script(
        'monospace-infinite-scroll',
        get_template_directory_uri() . '/assets/scripts/infinite-scroll.js',
        array(),
        $theme_version, // Change back to $theme_version when done developing
        true
    );
}
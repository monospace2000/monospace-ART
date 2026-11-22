<?php
/**
 * View As - the core (2026-11-17, owner: "I should never be able to execute user actions, like send confirmations, change passwords
 * or messages, or complete checkouts. I should be able to add stuff to cart but all that should automatically clear when I switch
 * back. This is just to be able to see what a specific user would see").
 *
 * How it works: you stay logged in as yourself (your own cookie, nothing is swapped - which is what makes every action real in
 * User Switching). A signed cookie says "this admin is looking as that customer"; while it is valid and your own login matches it,
 * WordPress's current user is the customer (determine_current_user), so every page is drawn for them. Then the writes are shut:
 *
 *  1. The gate (init, first thing): a request that would DO something for the customer is refused - every POST except the
 *     cart's own forms, every admin-ajax / wc-ajax action except a short list of reads and cart actions, REST writes, the
 *     admin screens, logout, password links, order cancel links. The list is ms_view_as_denied(), a pure function.
 *  2. Safety nets behind the gate: no email at all (pre_wp_mail), no user-meta writes (a customer's saved cart, last-active time,
 *     addresses and every other setting live there), and the checkout throws before an order exists.
 *  3. The cart is a throw-away: WooCommerce's session is swapped for one stored under its own key (viewas_<you>_<customer>), so the
 *     customer's real cart / session is never touched, and it is deleted when you switch back (or start again).
 *
 * The customer's own saved cart still shows (read from their user meta) - that is what they would see; what you add or remove is
 * kept only in the throw-away session.
 *
 * @package monospace-view-as
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MS_VIEW_AS_COOKIE = 'ms_view_as';

/**
 * ===========================
 * THE SIGNED COOKIE AND THE CURRENT USER
 * ===========================
 */

/** @return array|null admin, target, exp, return - from a valid, unexpired cookie */
function ms_view_as_cookie_data() {
    $raw = $_COOKIE[ MS_VIEW_AS_COOKIE ] ?? '';
    if ( ! is_string( $raw ) || strpos( $raw, '.' ) === false ) return null;
    list( $b64, $sig ) = explode( '.', $raw, 2 );
    if ( ! hash_equals( wp_hash( $b64, 'auth' ), $sig ) ) return null;
    $d = json_decode( (string) base64_decode( $b64 ), true );
    if ( ! is_array( $d ) || empty( $d['a'] ) || empty( $d['t'] ) || empty( $d['e'] ) || (int) $d['e'] < time() ) return null;
    return [ 'admin' => (int) $d['a'], 'target' => (int) $d['t'], 'exp' => (int) $d['e'], 'return' => (string) ( $d['r'] ?? '' ) ];
}

function ms_view_as_cookie_value( $admin, $target, $return ) {
    $b64 = base64_encode( wp_json_encode( [ 'a' => (int) $admin, 't' => (int) $target, 'e' => time() + ms_view_as_hours() * HOUR_IN_SECONDS, 'r' => (string) $return ] ) );
    return $b64 . '.' . wp_hash( $b64, 'auth' );
}

function ms_view_as_set_cookie( $value, $expires ) {
    setcookie( MS_VIEW_AS_COOKIE, $value, [ 'expires' => $expires, 'path' => COOKIEPATH, 'domain' => COOKIE_DOMAIN, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ] );
}

/**
 * The page is drawn for the customer - but only when the request's own login is exactly the admin the cookie names, and that
 * user is still an administrator, and the customer still exists and is not one.
 */
add_filter( 'determine_current_user', function ( $user_id ) {
    $d = ms_view_as_cookie_data();
    if ( ! $d || ! $user_id || (int) $user_id !== $d['admin'] ) return $user_id;
    if ( ! user_can( $d['admin'], ms_view_as_cap() ) ) return $user_id;
    $target = get_userdata( $d['target'] );
    if ( ! ms_view_as_can_view( $target ) ) return $user_id;
    $GLOBALS['ms_view_as_state'] = $d;
    return $d['target'];
}, 99 );

/** The state while viewing (admin, target, return), else null */
function ms_view_as_active() {
    $s = $GLOBALS['ms_view_as_state'] ?? null;
    return $s && get_current_user_id() === $s['target'] ? $s : null;
}

/**
 * ===========================
 * START AND STOP
 * ===========================
 */
function ms_view_as_start_url( $user_id ) {
    $back = set_url_scheme( 'http://' . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '' ) );
    return add_query_arg( [
        'ms_view_as' => (int) $user_id,
        '_wpnonce'   => wp_create_nonce( 'ms_view_as_' . (int) $user_id ),
        'back'       => rawurlencode( $back ),
    ], home_url( '/' ) );
}

/** Switch back: to the page being looked at when it is clicked (owner, 2026-11-17: stay on the page, also after moving around) */
function ms_view_as_stop_url() {
    $here = set_url_scheme( 'http://' . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '' ) );
    return add_query_arg( [ 'ms_view_as' => 'stop', '_wpnonce' => wp_create_nonce( 'ms_view_as_stop' ), 'back' => rawurlencode( $here ) ], home_url( '/' ) );
}

/**
 * The page to stay on when switching (owner, 2026-11-17): the page you were on, when that is a page of the site. A page of wp-admin
 * (the Users screen, an order) is not one the customer can see, so it falls back to $fallback (the front page unless given).
 */
function ms_view_as_return_url( $back, $fallback = '' ) {
    $fallback = $fallback !== '' ? $fallback : home_url( '/' );
    $back = wp_validate_redirect( (string) $back, '' );
    if ( $back === '' || strpos( $back, admin_url() ) === 0 || strpos( $back, '/wp-admin/' ) !== false || strpos( $back, 'wp-login.php' ) !== false || strpos( $back, 'ms_view_as=' ) !== false ) return $fallback;
    return $back;
}

function ms_view_as_session_key( $admin, $target ) {
    return 'viewas_' . (int) $admin . '_' . (int) $target;
}

/** Delete the throw-away cart (session row + cache) */
function ms_view_as_forget_cart( $admin, $target ) {
    if ( ! function_exists( 'WC' ) || ! class_exists( 'WC_Session_Handler' ) ) return;
    $session = WC()->session instanceof WC_Session_Handler ? WC()->session : new WC_Session_Handler();
    $session->delete_session( ms_view_as_session_key( $admin, $target ) );
}

add_action( 'init', function () {
    if ( ! isset( $_GET['ms_view_as'] ) ) return;
    $arg = sanitize_text_field( wp_unslash( $_GET['ms_view_as'] ) );
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

    if ( $arg === 'stop' ) {
        $data = ms_view_as_cookie_data();
        if ( ms_view_as_active() && ! wp_verify_nonce( $nonce, 'ms_view_as_stop' ) ) wp_die( 'That link has expired. Reload the page and use Switch back again.', 'View As', [ 'response' => 403 ] );
        if ( $data ) ms_view_as_forget_cart( $data['admin'], $data['target'] );
        ms_view_as_set_cookie( '', time() - YEAR_IN_SECONDS );
        unset( $_COOKIE[ MS_VIEW_AS_COOKIE ] );
        // The cart's cookies were set for the customer's throw-away cart
        if ( function_exists( 'wc_setcookie' ) ) {
            wc_setcookie( 'woocommerce_items_in_cart', 0, time() - HOUR_IN_SECONDS );
            wc_setcookie( 'woocommerce_cart_hash', '', time() - HOUR_IN_SECONDS );
        }
        $page = isset( $_GET['back'] ) ? esc_url_raw( rawurldecode( wp_unslash( $_GET['back'] ) ) ) : '';   // the page being looked at
        $to = ms_view_as_return_url( $page, $data ? ms_view_as_return_url( $data['return'] ) : '' );
        wp_safe_redirect( $to );
        exit;
    }

    $target = (int) $arg;
    $admin  = get_current_user_id();
    if ( ms_view_as_active() ) wp_die( 'Switch back first, then choose another customer.', 'View As', [ 'response' => 403 ] );
    if ( ! $admin || ! current_user_can( ms_view_as_cap() ) || ! wp_verify_nonce( $nonce, 'ms_view_as_' . $target ) ) wp_die( 'That link has expired or is not for you.', 'View As', [ 'response' => 403 ] );
    $user = get_userdata( $target );
    if ( ! $user || $target === $admin || ! ms_view_as_can_view( $user ) ) wp_die( 'That user can not be viewed.', 'View As', [ 'response' => 400 ] );

    ms_view_as_forget_cart( $admin, $target );   // a fresh, empty throw-away cart each time
    $back = isset( $_GET['back'] ) ? esc_url_raw( rawurldecode( wp_unslash( $_GET['back'] ) ) ) : '';
    ms_view_as_set_cookie( ms_view_as_cookie_value( $admin, $target, ms_view_as_return_url( $back ) ), time() + ms_view_as_hours() * HOUR_IN_SECONDS );

    // Remember it for the toolbar's list (the admin's own setting - written now, before anything is shut)
    $recent = array_values( array_unique( array_merge( [ $target ], array_map( 'intval', (array) get_user_meta( $admin, 'ms_view_as_recent', true ) ) ) ) );
    update_user_meta( $admin, 'ms_view_as_recent', array_slice( $recent, 0, 10 ) );

    // Stay on the page you were on; from a wp-admin screen (nothing there for a customer) the customer's account page
    wp_safe_redirect( ms_view_as_return_url( $back, ms_view_as_landing() ) );
    exit;
}, 0 );

/**
 * ===========================
 * THE GATE
 * ===========================
 */

/** wc-ajax actions that only read, or only change the visitor's own cart */
function ms_view_as_allowed_wc_ajax() {
    return apply_filters( 'ms_view_as_allowed_wc_ajax', [
        'get_refreshed_fragments', 'add_to_cart', 'remove_from_cart', 'apply_coupon', 'remove_coupon', 'update_shipping_method',
        'update_order_review', 'get_cart_totals', 'get_variation', 'get_customer_location', 'ms_cart_contents',
    ] );
}

/**
 * Would this request do something for the customer? Pure: the request's parts in, a reason out ('' = allowed).
 */
function ms_view_as_denied( $method, array $get, array $post, $uri, $is_admin, $is_ajax ) {
    $no   = ms_view_as_message();
    $method = strtoupper( (string) $method );
    $path = (string) wp_parse_url( (string) $uri, PHP_URL_PATH );
    $read = in_array( $method, [ 'GET', 'HEAD' ], true );

    $wc = (string) ( $get['wc-ajax'] ?? $post['wc-ajax'] ?? '' );
    if ( $wc !== '' ) return in_array( $wc, ms_view_as_allowed_wc_ajax(), true ) ? '' : $no;

    if ( $is_ajax ) {
        $action = (string) ( $post['action'] ?? $get['action'] ?? '' );
        return in_array( $action, ms_view_as_allowed_ajax(), true ) ? '' : $no;
    }

    if ( strpos( $path, '/wp-json/' ) === 0 || isset( $get['rest_route'] ) ) return $read ? '' : $no;
    if ( $is_admin ) return 'The admin is not available while viewing as a customer. Use Switch back in the toolbar.';
    if ( preg_match( '#/(wp-login|xmlrpc|wp-comments-post)\.php#', $path ) || strpos( $path, 'customer-logout' ) !== false ) return $no;

    if ( ! $read ) {
        if ( isset( $post['woocommerce_checkout_place_order'] ) || isset( $post['woocommerce-process-checkout-nonce'] ) || isset( $post['woocommerce_pay'] ) ) return $no;
        $cart = isset( $post['update_cart'] ) || isset( $post['apply_coupon'] ) || isset( $post['calc_shipping'] ) || isset( $post['add-to-cart'] ) || isset( $get['add-to-cart'] );
        return $cart ? '' : $no;
    }

    // Links that act: password email, cancel an order, logout, a lost-password / reset action; any nonce'd link that is not a cart one
    if ( isset( $get['wc-resend-set-password'] ) || isset( $get['cancel_order'] ) ) return $no;
    if ( isset( $get['action'] ) && in_array( (string) $get['action'], [ 'logout', 'lostpassword', 'rp', 'resetpass', 'register' ], true ) ) return $no;
    if ( isset( $get['_wpnonce'] ) && ! array_intersect( [ 'remove_item', 'undo_item', 'add-to-cart', 'order_again' ], array_keys( $get ) ) ) return $no;
    return '';
}

/**
 * What a refused page request answers: a bare page whose script raises the alert and takes the visitor back where they were.
 * Never history.back(): when the page before was a wp-admin one too, every step back was refused again - an endless alert loop
 * (2026-11-18). It goes to the referring site page instead (ms_view_as_return_url turns admin pages away), else the landing page.
 */
function ms_view_as_blocked_page( $message ) {
    $home = ms_view_as_return_url( (string) wp_get_raw_referer(), ms_view_as_landing() );
    return '<!doctype html><html><head><meta charset="utf-8"><title>View As</title><meta name="robots" content="noindex"></head><body>'
        . '<script>alert(' . wp_json_encode( $message ) . ');location.replace(' . wp_json_encode( $home ) . ');</script>'
        . '<noscript><p>' . esc_html( $message ) . ' <a href="' . esc_url( $home ) . '">Back</a></p></noscript></body></html>';
}

/** Refuse, in the shape the caller expects. A script's request gets a 403 marked X-MS-View-As (the page's own script below raises the alert); a page request gets the alert page */
function ms_view_as_deny( $message ) {
    header( 'X-MS-View-As: blocked' );
    nocache_headers();
    if ( isset( $_REQUEST['wc-ajax'] ) ) {
        status_header( 403 );
        wp_send_json( [ 'result' => 'failure', 'messages' => '', 'error' => $message ] );
    }
    if ( wp_doing_ajax() ) wp_send_json_error( $message, 403 );
    if ( strpos( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), '/wp-json/' ) === 0 || isset( $_GET['rest_route'] ) ) {
        status_header( 403 );
        wp_send_json( [ 'code' => 'ms_view_as', 'message' => $message, 'data' => [ 'status' => 403 ] ], 403 );
    }
    status_header( 403 );
    header( 'Content-Type: text/html; charset=utf-8' );
    echo ms_view_as_blocked_page( $message ); // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped above
    exit;
}

/** While viewing, a refused script request (cart, comment, checkout ...) raises the same alert: fetch and XMLHttpRequest (jQuery) both */
add_action( 'wp_head', function () {
    if ( ! ms_view_as_active() ) return; ?>
    <script id="ms-view-as-alerts">
    (function () {
        var msg = <?php echo wp_json_encode( ms_view_as_message() ); ?>, shown = 0;
        function say() { var n = Date.now(); if (n - shown < 800) return; shown = n; alert(msg); }
        var f = window.fetch;
        if (f) window.fetch = function () {
            return f.apply(this, arguments).then(function (r) { if (r && r.headers && r.headers.get('X-MS-View-As') === 'blocked') say(); return r; });
        };
        var send = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.send = function () {
            this.addEventListener('load', function () { if (this.getResponseHeader('X-MS-View-As') === 'blocked') say(); });
            return send.apply(this, arguments);
        };
    })();
    </script>
    <?php
}, 1 );

/** The safety nets, behind the gate: they hold even if a request gets past it */
function ms_view_as_arm() {
    add_filter( 'pre_wp_mail', '__return_false', 1 );                       // not one email, from anything
    foreach ( [ 'add', 'update', 'delete' ] as $op ) add_filter( $op . '_user_metadata', '__return_false', 1 );   // saved cart, last active, addresses, settings
    add_action( 'woocommerce_before_checkout_process', function () {
        throw new Exception( 'View As is read-only: the checkout is switched off.' );
    }, 0 );
}

add_action( 'init', function () {
    if ( ! ms_view_as_active() ) return;
    ms_view_as_arm();
    $reason = ms_view_as_denied( $_SERVER['REQUEST_METHOD'] ?? 'GET', $_GET, $_POST, $_SERVER['REQUEST_URI'] ?? '', is_admin(), wp_doing_ajax() );
    if ( $reason !== '' ) ms_view_as_deny( $reason );
}, 1 );

/**
 * ===========================
 * THE THROW-AWAY CART
 * ===========================
 */
add_filter( 'woocommerce_session_handler', function ( $class ) {
    if ( ! ms_view_as_active() ) return $class;
    if ( ! class_exists( 'MS_View_As_Session' ) ) require_once __DIR__ . '/class-view-as-session.php';
    return 'MS_View_As_Session';
} );

/** What a customer's own page does as a side effect of being looked at (e.g. "new preview" is marked seen) must not happen */
function ms_view_as_reading_only() {
    return (bool) ms_view_as_active();
}

/** The toolbar shows for the customer's page too (a customer has none), with its own content (modules/admin/view-as-toolbar.php) */
add_filter( 'show_admin_bar', function ( $show ) {
    return ms_view_as_active() ? true : $show;
}, 999 );

add_filter( 'body_class', function ( $classes ) {
    if ( ms_view_as_active() ) $classes[] = 'ms-view-as';
    return $classes;
} );

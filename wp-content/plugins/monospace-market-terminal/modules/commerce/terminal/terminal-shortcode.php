<?php
/**
 * Market terminal: the sale builder, [market_terminal] on art.monospace.com/terminal/ (2026-11-14, owner).
 *
 * The hub for in-person sales, built beside the Market Inventory Manager ([market_inventory_terminal] on /inventory/),
 * which is left exactly as it is. Plan: KB sites/art-monospace/notes/market-payments-checkout-plan.md.
 *
 * Step 1 (this file): build the sale - look a piece up by its number, add it to the sale, see the automatic discounts
 * (the Sales Dashboard's volume rules, the very code the cart uses, through the monospace_volume_prices filter), take
 * a discount off on the spot ($, % or a final total) and read the total. Nothing is written to WooCommerce here: the
 * sale is a short list kept per user (user meta) until the payment screens of step 2 turn it into an order.
 *
 * Prices are the ones the site shows (monospace_shop_price(): a running sale included). Originals only (never prints),
 * only pieces that are Available. A set (diptych, triptych) is one piece with its own price.
 *
 * Logged-in users with manage_woocommerce only (a login form for visitors); the page is noindex and never cached.
 * Forms post to the page itself and answer with a redirect (a reload never repeats an action).
 *
 * @package monospace-market-terminal
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'market_terminal', 'ms_term_render' );

const MS_TERM_MAX_PIECES = 30;

/** Is the current page the one with the terminal? */
function ms_term_is_page() {
    return is_singular( 'page' ) && has_shortcode( (string) get_post_field( 'post_content', get_queried_object_id() ), 'market_terminal' );
}

// The page is for the owner, not for search engines
add_filter( 'wp_robots', function ( $robots ) {
    return ms_term_is_page() ? wp_robots_no_robots( $robots ) : $robots;
} );

/* ------------------------------
   The pieces
-------------------------------*/

/** An original (never a print or a custom order) */
function ms_term_is_original( $product_id ) {
    return $product_id && has_term( 'original', 'product_cat', $product_id );
}

/** A piece the owner can sell now */
function ms_term_is_available( $product_id ) {
    return ms_term_is_original( $product_id ) && get_post_status( $product_id ) === 'publish'
        && get_post_meta( $product_id, 'painting_availability_status', true ) === 'available';
}

/** The original whose SKU ends in this number (426 and 0426 find MNSPC-2026-M-0426); 0 = none */
function ms_term_find( $number ) {
    global $wpdb;
    $digits = ltrim( preg_replace( '/\D/', '', (string) $number ), '0' );
    if ( $digits === '' || strlen( $digits ) > 4 ) return 0;
    $digits = str_pad( $digits, 4, '0', STR_PAD_LEFT );
    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_sku'
         WHERE p.post_type = 'product' AND p.post_status = 'publish' AND pm.meta_value LIKE %s ORDER BY p.ID DESC LIMIT 10",
        '%-' . $digits
    ) );
    foreach ( $ids as $id ) {
        if ( ms_term_is_original( (int) $id ) ) return (int) $id;
    }
    return 0;
}

/** The label of an availability status, as the owner's tools call it */
function ms_term_status_label( $product_id ) {
    $status = (string) get_post_meta( $product_id, 'painting_availability_status', true );
    $labels = [ 'available' => 'Available', 'reserved' => 'Reserved', 'sold' => 'Sold', 'private' => 'Private', 'gallery' => 'At gallery', 'gallery-reserved' => 'At gallery (QR purchase only)' ];
    return $labels[ $status ] ?? 'Unknown';
}

/* ------------------------------
   The sale (kept per user until step 2 turns it into an order)
-------------------------------*/

function ms_term_get_sale() {
    $sale = get_user_meta( get_current_user_id(), 'ms_terminal_sale', true );
    $sale = is_array( $sale ) ? $sale : [];
    $disc = is_array( $sale['disc'] ?? null ) ? $sale['disc'] : [];
    $kind = in_array( $disc['kind'] ?? '', [ 'amount', 'percent', 'total' ], true ) ? $disc['kind'] : '';
    return [
        'ids'  => array_values( array_unique( array_filter( array_map( 'absint', (array) ( $sale['ids'] ?? [] ) ) ) ) ),
        'disc' => $kind ? [ 'kind' => $kind, 'value' => max( 0, (float) ( $disc['value'] ?? 0 ) ) ] : [],
        'email' => is_email( $sale['email'] ?? '' ) ? (string) $sale['email'] : '',
        'note'  => mb_substr( sanitize_textarea_field( (string) ( $sale['note'] ?? '' ) ), 0, 500 ),
    ];
}

function ms_term_save_sale( $sale ) {
    update_user_meta( get_current_user_id(), 'ms_terminal_sale', $sale );
}

/** A message for the next page load (an answer after a redirect) */
function ms_term_flash( $text, $type = 'ok' ) {
    set_transient( 'ms_term_msg_' . get_current_user_id(), [ $type, $text ], 120 );
}

/**
 * The sale's numbers: a line per piece (listed price, the price after the automatic discount and its rule),
 * the subtotal, the discount given on the spot and the total.
 */
function ms_term_calc( $sale ) {
    $ids      = $sale['ids'];
    $decimals = wc_get_price_decimals();
    $auto     = $ids ? (array) apply_filters( 'monospace_volume_prices', [], $ids ) : []; // [] without the Sales Dashboard
    $lines    = [];
    $subtotal = 0.0;
    foreach ( $ids as $id ) {
        $product = wc_get_product( $id );
        if ( ! $product ) continue;
        $listed = round( (float) monospace_shop_price( $product ), $decimals );
        $price  = isset( $auto[ $id ] ) ? round( (float) $auto[ $id ]['price'], $decimals ) : $listed;
        $lines[] = [ 'id' => $id, 'product' => $product, 'listed' => $listed, 'price' => $price, 'label' => $price < $listed ? (string) ( $auto[ $id ]['label'] ?? '' ) : '' ];
        $subtotal += $price;
    }
    $subtotal = round( $subtotal, $decimals );
    $disc     = $sale['disc'];
    $given    = 0.0;
    if ( $disc ) {
        if ( $disc['kind'] === 'amount' )  $given = min( $subtotal, $disc['value'] );
        if ( $disc['kind'] === 'percent' ) $given = round( $subtotal * min( 100, $disc['value'] ) / 100, $decimals );
        if ( $disc['kind'] === 'total' )   $given = max( 0, $subtotal - $disc['value'] );
    }
    $given = round( min( $subtotal, $given ), $decimals );
    return [ 'lines' => $lines, 'subtotal' => $subtotal, 'discount' => $given, 'total' => round( $subtotal - $given, $decimals ) ];
}

/** What the discount on the spot says in words */
/** A piece's picture as a link to its artwork post (owner, 2026-11-15: tap the image to open the post); the plain picture when it has no published post */
function ms_term_image_link( $product, $size ) {
    $img  = wp_kses_post( $product->get_image( $size ) );
    $post = (int) get_post_meta( $product->get_id(), '_related_post_id', true );
    $url  = $post && get_post_status( $post ) === 'publish' ? get_permalink( $post ) : '';
    return $url ? '<a class="ms-term-img" href="' . esc_url( $url ) . '" aria-label="Open the artwork post: ' . esc_attr( $product->get_name() ) . '">' . $img . '</a>' : $img;
}

/** The round white chip with a way to pay's logo (2026-11-15, owner's icons in assets/pay/; '' = none for that name) */
function ms_term_method_icon( $method ) {
    $file = 'pay-' . sanitize_key( $method ) . '.webp';
    if ( ! file_exists( MONOSPACE_TERMINAL_DIR . '/assets/pay/' . $file ) ) return '';
    return '<img class="ms-term-payicon" src="' . esc_url( MONOSPACE_TERMINAL_URL . 'assets/pay/' . $file ) . '" alt="" width="32" height="32">';
}

function ms_term_discount_text( $disc ) {
    if ( ! $disc ) return '';
    $v = rtrim( rtrim( number_format( $disc['value'], 2, '.', '' ), '0' ), '.' );
    if ( $disc['kind'] === 'amount' )  return '$' . $v . ' off';
    if ( $disc['kind'] === 'percent' ) return $v . '% off';
    return 'final total $' . $v;
}

/* ------------------------------
   The forms (before anything is printed, then a redirect)
-------------------------------*/

add_action( 'template_redirect', function () {
    if ( ! ms_term_is_page() ) return;
    nocache_headers();

    // Square switches back here with its answer in the address (?data={...}); handled, then on to the plain page
    if ( isset( $_GET['data'] ) && ! isset( $_GET['practice'] ) ) {
        if ( is_user_logged_in() && current_user_can( 'manage_woocommerce' ) ) {
            $answer = json_decode( (string) wp_unslash( $_GET['data'] ), true );
            $result = is_array( $answer ) ? ms_term_handle_square_return( $answer ) : [ 'error', 'Square answered in a way the terminal could not read. Nothing was changed.' ];
            ms_term_flash( $result[1], $result[0] );
            wp_safe_redirect( remove_query_arg( [ 'data', 'piece' ], get_permalink() ) );
            exit;
        }
        // not signed in (the session ran out): the login form brings the answer back here
    }

    // Practice mode is switched by the address: /terminal/?practice turns it on, ?practice=0 turns it off (then back to the plain page)
    if ( isset( $_GET['practice'] ) ) {
        if ( is_user_logged_in() && current_user_can( 'manage_woocommerce' ) ) {
            $off = in_array( strtolower( (string) wp_unslash( $_GET['practice'] ) ), [ '0', 'off', 'no', 'false' ], true );
            if ( ms_term_pending_order() ) {
                ms_term_flash( 'Finish the unfinished sale first (Paid or Back), then switch practice mode.', 'warn' );
            } else {
                update_user_meta( get_current_user_id(), 'ms_terminal_practice', $off ? '0' : '1' );
                delete_user_meta( get_current_user_id(), 'ms_terminal_practice_pending' );
                ms_term_flash( $off ? 'Practice mode is off. Sales are real again.' : 'Practice mode is on. Nothing is recorded.', $off ? 'ok' : 'warn' );
            }
        }
        wp_safe_redirect( remove_query_arg( [ 'practice', 'piece' ], get_permalink() ) );
        exit;
    }
    if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || empty( $_POST['ms_term_action'] ) ) return;
    if ( ! is_user_logged_in() || ! current_user_can( 'manage_woocommerce' ) ) return;

    $back = remove_query_arg( [ 'piece' ], get_permalink() );
    if ( ! isset( $_POST['ms_term_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['ms_term_nonce'] ), 'ms_term' ) ) {
        ms_term_flash( 'That page was out of date. Try again.', 'error' );
        wp_safe_redirect( $back ); exit;
    }

    $action = sanitize_key( wp_unslash( $_POST['ms_term_action'] ) );
    $sale   = ms_term_get_sale();
    $id     = absint( $_POST['product_id'] ?? 0 );

    switch ( $action ) {
        case 'search':
            $found = ms_term_find( wp_unslash( $_POST['number'] ?? '' ) );
            if ( $found ) { wp_safe_redirect( add_query_arg( 'piece', $found, $back ) ); exit; }
            ms_term_flash( 'No artwork found for that number.', 'error' );
            break;

        case 'add':
            if ( ! ms_term_is_available( $id ) ) {
                ms_term_flash( 'That artwork is not available for sale.', 'error' );
            } elseif ( in_array( $id, $sale['ids'], true ) ) {
                ms_term_flash( 'That artwork is already in this sale.', 'warn' );
            } elseif ( count( $sale['ids'] ) >= MS_TERM_MAX_PIECES ) {
                ms_term_flash( 'A sale holds up to ' . MS_TERM_MAX_PIECES . ' pieces.', 'error' );
            } else {
                $sale['ids'][] = $id;
                ms_term_save_sale( $sale );
                ms_term_flash( 'Added to the sale.' );
            }
            break;

        case 'remove':
            $sale['ids'] = array_values( array_diff( $sale['ids'], [ $id ] ) );
            if ( ! $sale['ids'] ) $sale['disc'] = [];
            ms_term_save_sale( $sale );
            break;

        case 'discount':
            $kind  = sanitize_key( wp_unslash( $_POST['kind'] ?? '' ) );
            $value = (float) str_replace( ',', '.', preg_replace( '/[^0-9.,]/', '', wp_unslash( $_POST['value'] ?? '' ) ) );
            if ( isset( $_POST['remove_discount'] ) || ! in_array( $kind, [ 'amount', 'percent', 'total' ], true ) || $value <= 0 ) {
                $sale['disc'] = [];
            } elseif ( $kind === 'percent' && $value > 100 ) {
                ms_term_flash( 'A percentage goes up to 100.', 'error' );
                break;
            } else {
                $sale['disc'] = [ 'kind' => $kind, 'value' => $value ];
            }
            ms_term_save_sale( $sale );
            break;

        case 'pay':
            $email = trim( sanitize_text_field( wp_unslash( $_POST['email'] ?? '' ) ) );
            if ( $email !== '' && ! is_email( $email ) ) {
                $sale['email'] = '';
                $sale['note']  = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ), 0, 500 ); // the note typed is kept
                ms_term_save_sale( $sale );
                ms_term_flash( 'That email address does not look right.', 'error' );
                break;
            }
            $sale['email'] = $email;
            $sale['note']  = mb_substr( sanitize_textarea_field( wp_unslash( $_POST['note'] ?? '' ) ), 0, 500 );
            ms_term_save_sale( $sale );
            if ( ms_term_practice() ) { // nothing is created: the payment screen is shown from a note
                $method = sanitize_text_field( wp_unslash( $_POST['method'] ?? '' ) );
                $calc   = ms_term_calc( $sale );
                if ( ! in_array( $method, ms_term_settings()['methods'], true ) || ! $calc['lines'] ) { ms_term_flash( 'Add a piece to the sale first.', 'error' ); break; }
                $paid = ms_term_paid_prices( $calc );
                $rows = [];
                foreach ( $calc['lines'] as $i => $line ) $rows[] = [ $line['id'], $paid[ $i ] ];
                update_user_meta( get_current_user_id(), 'ms_terminal_practice_pending', [ 'method' => $method, 'total' => $calc['total'], 'lines' => $rows, 'note' => $sale['note'] ] );
                break;
            }
            $result = ms_term_start_order( sanitize_text_field( wp_unslash( $_POST['method'] ?? '' ) ), $sale, $email );
            if ( is_wp_error( $result ) ) ms_term_flash( $result->get_error_message(), 'error' );
            break;

        case 'paid':
        case 'notpaid':
            if ( ms_term_practice() && ms_term_practice_pending() && ! ms_term_pending_order() ) {
                delete_user_meta( get_current_user_id(), 'ms_terminal_practice_pending' );
                if ( $action === 'paid' ) {
                    ms_term_save_sale( [ 'ids' => [], 'disc' => [], 'email' => '', 'note' => '' ] );
                    ms_term_flash( 'Practice sale complete. Nothing was recorded; the pieces were not touched.', 'warn' );
                } else {
                    ms_term_flash( 'Back to the sale (practice).', 'ok' );
                }
                break;
            }
            $order = ms_term_pending_order();
            if ( ! $order || $order->get_id() !== absint( $_POST['order_id'] ?? 0 ) ) {
                ms_term_flash( 'That sale is already finished.', 'warn' );
            } elseif ( $action === 'paid' ) {
                $message = ms_term_paid_message( $order );
                ms_term_finish_order( $order );
                ms_term_flash( $message );
            } else {
                ms_term_drop_order( $order ); // puts the list, the discount, the email and the note back
                ms_term_flash( 'Back to the sale, nothing was charged here. Choose a way to pay.', 'ok' );
            }
            break;

        case 'undo':
            $order = wc_get_order( absint( $_POST['order_id'] ?? 0 ) );
            $today = $order ? array_map( function ( $o ) { return $o->get_id(); }, ms_term_today_orders() ) : [];
            if ( ! $order || ! in_array( $order->get_id(), $today, true ) ) {
                ms_term_flash( 'That sale is not in today\'s list (or is already undone).', 'warn' );
            } else {
                ms_term_undo_order( $order );
                ms_term_flash( sprintf( 'Undone: %s. The pieces are available again. Refund the money in the %s app.', ms_term_order_numbers( $order ), $order->get_payment_method() ?: 'payment' ), 'warn' );
            }
            break;

        case 'clear_sale':
            ms_term_save_sale( [ 'ids' => [], 'disc' => [], 'email' => '', 'note' => '' ] );
            ms_term_flash( 'Sale cleared.' );
            break;
    }
    wp_safe_redirect( $back );
    exit;
} );

/* ------------------------------
   The page
-------------------------------*/

function ms_term_render() {

    // Logged-out visitors get a login prompt that returns them to this page.
    if ( ! is_user_logged_in() ) {
        ob_start();
        ?>
        <div class="ms-term ms-term-login">
            <p>Please log in to use the market terminal.</p>
            <?php wp_login_form( [ 'echo' => true, 'redirect' => home_url( add_query_arg( [] ) ), 'remember' => true ] ); // add_query_arg( [] ) = this very address, so Square's answer survives a login ?>
        </div>
        <?php
        return ob_get_clean();
    }
    if ( ! current_user_can( 'manage_woocommerce' ) ) return '<p>Access denied.</p>';

    $msg = get_transient( 'ms_term_msg_' . get_current_user_id() );
    delete_transient( 'ms_term_msg_' . get_current_user_id() );

    // An unfinished sale (waiting for payment) is the page, however often it is reloaded or left
    $pending = ms_term_pending_order();
    if ( $pending ) return ms_term_render_paying( $pending, $msg );
    $practice = ms_term_practice();
    if ( $practice && ( $pp = ms_term_practice_pending() ) ) return ms_term_render_paying( ms_term_practice_order( $pp ), $msg, true );

    // Pieces that were sold or hidden since they were added (online, in the other terminal) leave the sale
    $sale = ms_term_get_sale();
    $gone = array_values( array_filter( $sale['ids'], function ( $id ) { return ! ms_term_is_available( $id ); } ) );
    if ( $gone ) {
        $names = array_map( function ( $id ) { $p = wc_get_product( $id ); return $p ? $p->get_name() : "#$id"; }, $gone );
        $sale['ids'] = array_values( array_diff( $sale['ids'], $gone ) );
        if ( ! $sale['ids'] ) $sale['disc'] = [];
        ms_term_save_sale( $sale );
        $msg = [ 'warn', 'No longer available, taken out of the sale: ' . implode( ', ', $names ) . '.' ];
    }

    $calc  = ms_term_calc( $sale );
    $piece = isset( $_GET['piece'] ) ? wc_get_product( absint( $_GET['piece'] ) ) : null;
    if ( $piece && ! ms_term_is_original( $piece->get_id() ) ) $piece = null;

    $nonce = '<input type="hidden" name="ms_term_nonce" value="' . esc_attr( wp_create_nonce( 'ms_term' ) ) . '">';
    $url   = esc_url( get_permalink() );

    ob_start();
    ?>
<?php echo ms_term_css(); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed CSS below ?>

<div class="ms-term">

    <?php if ( $practice ) : ?>
        <div class="ms-term-practice">
            <strong>Practice mode.</strong> Nothing is recorded: no order, the pieces stay as they are, no email. The Venmo and PayPal codes ask $0.01.
            <a href="<?php echo esc_url( add_query_arg( 'practice', '0', get_permalink() ) ); ?>">Turn it off</a>
        </div>
    <?php endif; ?>

    <?php if ( $msg ) : ?><p class="ms-term-msg is-<?php echo esc_attr( $msg[0] ); ?>"><?php echo esc_html( $msg[1] ); ?></p><?php endif; ?>

    <form method="post" action="<?php echo $url; ?>" class="ms-term-search">
        <?php echo $nonce; ?>
        <input type="hidden" name="ms_term_action" value="search">
        <input name="number" inputmode="numeric" pattern="[0-9]*" placeholder="Artwork number" autocomplete="off" required aria-label="Artwork number">
        <button class="ms-term-btn is-primary">Search</button>
    </form>
    <script>
    // Nothing takes the cursor (and the keyboard) on load: the field is read-only until it is tapped
    (function () {
        var f = document.querySelector('.ms-term-search input[name=number]');
        if (!f) return;
        f.readOnly = true;
        if (document.activeElement === f) f.blur();
        function arm() { f.readOnly = false; }
        ['pointerdown', 'touchstart', 'mousedown'].forEach(function (e) { f.addEventListener(e, arm, { passive: true }); });
        f.addEventListener('click', function () { arm(); f.focus(); });
    })();
    </script>

    <?php if ( $piece ) :
        $pid       = $piece->get_id();
        $available = ms_term_is_available( $pid );
        $in_sale   = in_array( $pid, $sale['ids'], true );
        ?>
        <div class="ms-term-card">
            <a href="<?php echo esc_url( remove_query_arg( 'piece', get_permalink() ) ); ?>" class="ms-term-close" role="button" aria-label="Close" title="Close">&times;</a>
            <strong><?php echo esc_html( $piece->get_name() ); ?></strong><br>
            <span class="ms-term-sku"><?php echo esc_html( $piece->get_sku() ); ?></span><br>
            Status: <strong class="<?php echo $available ? 'ms-term-ok' : 'ms-term-no'; ?>"><?php echo esc_html( ms_term_status_label( $pid ) ); ?></strong>
            <?php if ( $available ) : ?> &middot; <strong><?php echo wp_kses_post( wc_price( monospace_shop_price( $piece ) ) ); ?></strong><?php endif; ?>
            <?php echo ms_term_image_link( $piece, 'medium_large' ); // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped in the helper ?>
            <?php if ( $available && ! $in_sale ) : ?>
                <form method="post" action="<?php echo $url; ?>">
                    <?php echo $nonce; ?>
                    <input type="hidden" name="ms_term_action" value="add">
                    <input type="hidden" name="product_id" value="<?php echo (int) $pid; ?>">
                    <button class="ms-term-btn is-primary is-wide">Add to sale</button>
                </form>
            <?php elseif ( $in_sale ) : ?>
                <em>Already in this sale.</em>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h3>This sale</h3>
    <?php if ( ! $calc['lines'] ) : ?>
        <p class="ms-term-empty">No pieces yet. Search a number and add it.</p>
    <?php else : ?>
        <ul class="ms-term-list<?php echo $calc['discount'] > 0 ? '' : ' is-last-open'; ?>">
        <?php foreach ( $calc['lines'] as $line ) : $p = $line['product']; ?>
            <li class="ms-term-line">
                <?php echo ms_term_image_link( $p, 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped in the helper ?>
                <div>
                    <div class="ms-term-title"><?php echo esc_html( $p->get_name() ); ?></div>
                    <div class="ms-term-sku"><?php echo esc_html( $p->get_sku() ); ?></div>
                    <?php if ( $line['label'] ) : ?><div class="ms-term-rule"><?php echo esc_html( $line['label'] ); ?></div><?php endif; ?>
                </div>
                <div class="ms-term-price">
                    <?php if ( $line['price'] < $line['listed'] ) : ?><del><?php echo wp_kses_post( wc_price( $line['listed'] ) ); ?></del><?php endif; ?>
                    <?php echo wp_kses_post( wc_price( $line['price'] ) ); ?>
                </div>
                <form method="post" action="<?php echo $url; ?>">
                    <?php echo $nonce; ?>
                    <input type="hidden" name="ms_term_action" value="remove">
                    <input type="hidden" name="product_id" value="<?php echo (int) $line['id']; ?>">
                    <a href="#" class="ms-term-x" role="button" aria-label="Remove <?php echo esc_attr( $p->get_name() ); ?>" title="Remove" onclick="this.closest('form').submit();return false;">&times;</a>
                </form>
            </li>
        <?php endforeach; ?>
        </ul>

        <div class="ms-term-sums">
            <?php if ( $calc['discount'] > 0 ) : ?>
                <div><span>Subtotal</span><span><?php echo wp_kses_post( wc_price( $calc['subtotal'] ) ); ?></span></div>
                <div><span>Discount (<?php echo esc_html( ms_term_discount_text( $sale['disc'] ) ); ?>)</span><span>&minus;<?php echo wp_kses_post( wc_price( $calc['discount'] ) ); ?></span></div>
            <?php endif; ?>
            <div class="ms-term-total"><span>Total</span><span><?php echo wp_kses_post( wc_price( $calc['total'] ) ); ?></span></div>
        </div>

        <h3>Discount on the spot</h3>
        <form method="post" action="<?php echo $url; ?>" class="ms-term-disc">
            <?php echo $nonce; ?>
            <input type="hidden" name="ms_term_action" value="discount">
            <select name="kind" aria-label="Kind of discount">
                <?php foreach ( [ 'amount' => '$ off', 'percent' => '% off', 'total' => 'Final total $' ] as $k => $label ) : ?>
                    <option value="<?php echo esc_attr( $k ); ?>"<?php selected( $sale['disc']['kind'] ?? 'amount', $k ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
            <input name="value" inputmode="decimal" placeholder="0" autocomplete="off" aria-label="Discount"
                   value="<?php echo $sale['disc'] ? esc_attr( rtrim( rtrim( number_format( $sale['disc']['value'], 2, '.', '' ), '0' ), '.' ) ) : ''; ?>">
            <button class="ms-term-btn is-primary">Apply</button>
            <?php if ( $sale['disc'] ) : ?><button class="ms-term-btn" name="remove_discount" value="1">Remove discount</button><?php endif; ?>
        </form>

        <h3>Take payment</h3>
        <form method="post" action="<?php echo $url; ?>">
            <?php echo $nonce; ?>
            <input type="hidden" name="ms_term_action" value="pay">
            <label for="ms-term-email">Email for a receipt (optional)</label>
            <input id="ms-term-email" class="ms-term-email" type="email" name="email" inputmode="email" autocomplete="off" placeholder="name@example.com" value="<?php echo esc_attr( $sale['email'] ); ?>">
            <label for="ms-term-note">Note for the order (optional)</label>
            <textarea id="ms-term-note" class="ms-term-email" name="note" rows="2" maxlength="500" placeholder="Shows on the order in WooCommerce"><?php echo esc_textarea( $sale['note'] ); ?></textarea>
            <div class="ms-term-methods">
                <?php foreach ( ms_term_settings()['methods'] as $method ) : ?>
                    <button class="ms-term-btn is-primary" name="method" value="<?php echo esc_attr( $method ); ?>"><span class="ms-term-inner"><?php echo ms_term_method_icon( $method ); ?><span><?php echo esc_html( $method ); ?></span></span></button>
                <?php endforeach; ?>
            </div>
        </form>

        <form method="post" action="<?php echo $url; ?>" class="ms-term-clear">
            <?php echo $nonce; ?>
            <input type="hidden" name="ms_term_action" value="clear_sale">
            <?php // a link, not a <button>: the theme's glass style claims every button, with !important, and would make this blue ?>
            <a href="#" class="ms-term-btn is-glass is-red-glass" role="button" onclick="if(confirm('Clear this sale?')){this.closest('form').submit();}return false;">Clear sale</a>
        </form>
    <?php endif; ?>

    <?php if ( ! $practice ) echo ms_term_render_today( $nonce, $url ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside ?>

</div>
<?php
    return ob_get_clean();
}

/** Today's sales: totals per way of paying and one line per sale with Undo (closed until opened) */
function ms_term_render_today( $nonce, $url ) {
    $orders = ms_term_today_orders();
    $by     = [];
    $sum    = 0.0;
    foreach ( $orders as $o ) {
        $m = $o->get_payment_method() ?: 'Other';
        $by[ $m ] = [ ( $by[ $m ][0] ?? 0 ) + (float) $o->get_total(), ( $by[ $m ][1] ?? 0 ) + 1 ];
        $sum += (float) $o->get_total();
    }
    ob_start();
    ?>
    <details class="ms-term-today">
        <summary><strong>Today's sales</strong> &middot; <?php echo count( $orders ); ?> &middot; <?php echo wp_kses_post( wc_price( $sum ) ); ?></summary>
        <?php if ( ! $orders ) : ?>
            <p class="ms-term-empty">Nothing sold yet today.</p>
        <?php else : ?>
            <div class="ms-term-sums">
                <?php foreach ( $by as $method => $x ) : ?>
                    <div><span><?php echo esc_html( $method ); ?> (<?php echo (int) $x[1]; ?>)</span><span><?php echo wp_kses_post( wc_price( $x[0] ) ); ?></span></div>
                <?php endforeach; ?>
                <div class="ms-term-total"><span>All</span><span><?php echo wp_kses_post( wc_price( $sum ) ); ?></span></div>
            </div>
            <ul class="ms-term-list">
                <?php foreach ( array_reverse( $orders ) as $o ) : $when = $o->get_date_completed() ?: $o->get_date_created(); ?>
                    <li class="ms-term-sale">
                        <div>
                            <div class="ms-term-title"><?php echo esc_html( ms_term_order_numbers( $o ) ?: 'Order #' . $o->get_id() ); ?></div>
                            <div class="ms-term-sku"><?php echo esc_html( $when ? wp_date( 'g:i a', $when->getTimestamp() ) : '' ); ?> &middot; <?php echo esc_html( $o->get_payment_method() ?: 'Other' ); ?> &middot; order #<?php echo (int) $o->get_id(); ?></div>
                        </div>
                        <div class="ms-term-price"><?php echo wp_kses_post( wc_price( $o->get_total() ) ); ?></div>
                        <form method="post" action="<?php echo $url; ?>" onsubmit="return confirm('Undo this sale? The pieces become available again. The money is not returned by this.');">
                            <?php echo $nonce; ?>
                            <input type="hidden" name="ms_term_action" value="undo">
                            <input type="hidden" name="order_id" value="<?php echo (int) $o->get_id(); ?>">
                            <button class="ms-term-btn is-small">Undo</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </details>
    <?php
    return ob_get_clean();
}

/**
 * The payment screen of an unfinished sale: the total, how to pay (a QR code for Venmo and PayPal), then Paid / Not paid.
 * Square is taken in the Square app for now (Tap to Pay), the total typed there; the round trip comes in a later step.
 */
function ms_term_render_paying( $order, $msg, $practice = false ) {
    $nonce  = '<input type="hidden" name="ms_term_nonce" value="' . esc_attr( wp_create_nonce( 'ms_term' ) ) . '">';
    $url    = esc_url( get_permalink() );
    $method = (string) $order->get_payment_method();
    $total  = (float) $order->get_total();
    $link   = $practice ? ms_term_pay_url( $method, 0.01, 'PRACTICE - please do not pay' ) : ms_term_pay_url( $method, $total, ms_term_pay_note( $order ) );
    $qr     = $link && function_exists( 'monospace_qr_svg' ) ? monospace_qr_svg( $link, [ 'ecc' => 'M', 'bg' => '#ffffff', 'label' => $method . ' payment code for ' . wp_strip_all_tags( wc_price( $total ) ) ] ) : '';
    $notes  = [ 'Square' => 'Charge it in the Square app (Tap to Pay), then press Paid.', 'Cash' => 'Take the cash, then press Paid.', 'Venmo' => 'The customer scans the code, then press Paid when the payment shows in Venmo.', 'PayPal' => 'The customer scans the code (or pays in the PayPal app), then press Paid when the payment shows.' ];
    ob_start();
    echo ms_term_css(); // phpcs:ignore WordPress.Security.EscapeOutput -- fixed CSS
    ?>
<div class="ms-term">
    <?php if ( $practice ) : ?><div class="ms-term-practice"><strong>Practice mode.</strong> Nothing is recorded. The code below asks $0.01 &ndash; do not pay it.</div><?php endif; ?>
    <?php if ( $msg ) : ?><p class="ms-term-msg is-<?php echo esc_attr( $msg[0] ); ?>"><?php echo esc_html( $msg[1] ); ?></p><?php endif; ?>
    <form method="post" action="<?php echo $url; ?>" style="margin:0 0 12px;">
        <?php echo $nonce; ?>
        <input type="hidden" name="ms_term_action" value="notpaid">
        <input type="hidden" name="order_id" value="<?php echo (int) $order->get_id(); ?>">
        <button class="ms-term-btn">&larr; Change payment method</button>
    </form>
    <div class="ms-term-card ms-term-pay">
        <h3 class="ms-term-waiting"><?php echo ms_term_method_icon( $method ); ?>Waiting for payment &middot; <?php echo esc_html( $method ); ?></h3>
        <div class="ms-term-bigtotal"><?php echo wp_kses_post( wc_price( $total ) ); ?></div>
        <?php if ( $order->get_meta( '_ms_term_note' ) ) : ?><p><em>Note: <?php echo esc_html( $order->get_meta( '_ms_term_note' ) ); ?></em></p><?php endif; ?>
        <ul class="ms-term-pieces">
            <?php foreach ( $order->get_items() as $item ) : $p = $item->get_product(); ?>
                <li>
                    <div class="ms-term-title"><?php echo esc_html( $item->get_name() ); ?></div>
                    <?php if ( $p ) : ?><div class="ms-term-sku"><?php echo esc_html( $p->get_sku() ); ?></div><?php endif; ?>
                    <div class="ms-term-linetotal"><?php echo wp_kses_post( wc_price( $item->get_total() ) ); ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ( $qr ) : ?>
            <div class="ms-term-qr">
                <?php echo $qr; // phpcs:ignore WordPress.Security.EscapeOutput -- built by monospace_qr_svg() ?>
            </div>
        <?php endif; ?>
        <?php if ( $method === 'Square' && $practice ) : ?>
            <p><em>Practice: Square is not opened, it would charge a card. Press Paid to finish the practice sale.</em></p>
        <?php elseif ( $method === 'Square' && ( $square = ms_term_square_url( $order ) ) ) : ?>
            <p><a href="<?php echo esc_url( $square, [ 'square-commerce-v1' ] ); ?>" class="ms-term-btn is-glass is-big" role="button">Open Square &ndash; charge <?php echo wp_kses_post( wc_price( $total ) ); ?></a></p>
            <p>Square comes back here by itself after the payment. If it does not, check the payment in Square and press Paid.</p>
        <?php else : ?>
            <p><?php echo esc_html( $notes[ $method ] ?? 'Take the payment, then press Paid.' ); ?></p>
        <?php endif; ?>
        <div class="ms-term-pay-actions">
            <form method="post" action="<?php echo $url; ?>" onsubmit="return confirm('Payment received? This sells the pieces.');">
                <?php echo $nonce; ?>
                <input type="hidden" name="ms_term_action" value="paid">
                <input type="hidden" name="order_id" value="<?php echo (int) $order->get_id(); ?>">
                <button class="ms-term-btn is-primary is-wide">Paid</button>
            </form>
        </div>
    </div>
</div>
<?php
    return ob_get_clean();
}

/** The page's styles (one block, shared by the sale builder and the payment screen) */
function ms_term_css() {
    return <<<'CSS'
<style>
.ms-term { max-width: 850px; margin: 0; padding: 0; font-family: sans-serif; }
.ms-term input, .ms-term select { font-size: 16px; }
.ms-term form { margin: 0; }
.ms-term .ms-term-msg { margin: 0 0 16px; font-weight: bold; }
.ms-term .is-ok { color: green; } .ms-term .is-error { color: red; } .ms-term .is-warn { color: #b26a00; }
.ms-term .ms-term-search { display: flex; gap: 8px; margin: 0 0 16px; }
.ms-term .ms-term-search input { flex: 1; min-width: 0; padding: 12px; border: 1px solid #333; background: #fff; }
.ms-term .ms-term-btn { padding: 12px 16px; font-size: 16px; line-height: 1.2; border: 1px solid #1d1d1d; border-radius: 6px; background: transparent; color: inherit; cursor: pointer; }
.ms-term .ms-term-btn.is-primary { background: #1d1d1d; color: #fff; }
.ms-term .ms-term-btn.is-wide { width: 100%; }
.ms-term .ms-term-btn.is-small { padding: 8px 12px; font-size: 14px; }
/* the site's Card style (Appearance > Look: fill, outline, thickness, rounding), as the theme's card-style mixin */
.ms-term .ms-term-card { border: var(--ms-card-border-width, 1px) solid var(--ms-card-border, #ccc); border-radius: var(--ms-card-radius, var(--ms-radius, 8px)); padding: 15px; margin: 0 0 20px; background: var(--ms-card-bg, #fff); }
.ms-term .ms-term-card { position: relative; }
.ms-term a.ms-term-close { position: absolute; top: 6px; right: 6px; width: 34px; height: 34px; line-height: 32px; text-align: center; font-size: 28px; font-weight: 400; text-decoration: none; color: inherit; border-radius: 100%; opacity: .7; }
.ms-term a.ms-term-close:hover { opacity: 1; background: rgba(128,128,128,.25); }
.ms-term a.ms-term-img { display: block; line-height: 0; text-decoration: none; border: 0; }
.ms-term .ms-term-card img { max-width: 100%; height: auto; display: block; margin: 12px 0; }
.ms-term .ms-term-card .ms-term-btn { margin-top: 4px; }
.ms-term .ms-term-ok { color: green; font-weight: bold; } .ms-term .ms-term-no { color: red; font-weight: bold; }
.ms-term h3 { margin: 24px 0 8px; font-size: 1.1rem; }
.ms-term .ms-term-list { list-style: none; margin: 0; padding: 0; border-top: 1px solid rgba(128,128,128,.45); }
.ms-term .ms-term-line { display: grid; grid-template-columns: 56px 1fr auto auto; gap: 10px; align-items: center; padding: 10px 0; border-bottom: 1px solid rgba(128,128,128,.45); }
.ms-term .ms-term-line img { width: 56px; height: 56px; object-fit: cover; display: block; border-radius: 4px; }
.ms-term .ms-term-title { font-weight: bold; } .ms-term .ms-term-sku { font-size: .85em; opacity: .7; }
.ms-term .ms-term-rule { font-size: .85em; color: green; }
.ms-term .ms-term-price { text-align: right; white-space: nowrap; } .ms-term .ms-term-price del { opacity: .6; font-size: .85em; display: block; }
.ms-term .ms-term-sums { margin: 12px 0 0; }
.ms-term .ms-term-sums div { display: flex; justify-content: space-between; padding: 3px 0; }
/* no double rule: without discount rows the Total's own line closes the list */
.ms-term .ms-term-list.is-last-open .ms-term-line:last-child { border-bottom: 0; }
.ms-term .ms-term-total { font-size: 2rem; font-weight: bold; padding-top: 8px; border-top: 2px solid currentColor; margin-top: 6px; }
.ms-term .ms-term-disc { display: flex; flex-wrap: wrap; gap: 8px; margin: 16px 0 0; }
.ms-term .ms-term-disc select, .ms-term .ms-term-disc input { padding: 10px; border: 1px solid #333; background: #fff; }
.ms-term .ms-term-disc input { width: 7em; }
.ms-term .ms-term-empty { opacity: .7; }
/* The cart's remove cross (WooCommerce's a.remove: a red circle with a x, filled on hover) */
/* (links, not <button>s: the theme's glass style claims every button with !important) */
.ms-term a.ms-term-x { display: block; font-size: 1.5em; height: 1em; width: 1em; padding: 0; text-align: center; line-height: 1; border-radius: 100%; color: var(--wc-red, #a00); background: transparent; border: 0; font-weight: 700; cursor: pointer; text-decoration: none; }
.ms-term a.ms-term-x:hover { color: #fff; background: var(--wc-red, #a00); }
/* Glass link: the theme's glass button (_button-styles.scss button-glass-var + the gloss strip), in the look's button color (red with .is-red-glass) - same size, sheen, shadow, gloss and hover */
.ms-term a.ms-term-btn.is-red-glass { --btn-color: rgba(200, 30, 30, 0.9); }
.ms-term a.ms-term-btn.is-big { display: block; padding: 16px; font-size: 1.15rem; }
.ms-term a.ms-term-btn.is-glass { position: relative; display: inline-block; padding: 7px 16px; font-family: 'Open Sans', sans-serif; font-weight: 600; font-size: calc(1.1rem * .85); line-height: 1.4; text-align: center; text-decoration: none; cursor: pointer; border: 0; border-radius: var(--ms-btn-radius, 9999px); color: #fff; background-color: var(--btn-color, rgba(4, 107, 210, 0.9));
    background-image: linear-gradient(to top, rgba(255,255,255,0.35) 0%, rgba(255,255,255,0.15) 45%, rgba(0,0,0,0.05) 55%, rgba(0,0,0,0.18) 100%);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.55), inset 0 -1px 0 rgba(255,255,255,0.3), 0 2px 3px rgba(0,0,0,0.25);
    transition: background-image 0.15s ease, box-shadow 0.15s ease, transform 0.05s ease; }
.ms-term a.ms-term-btn.is-glass::before { content: ""; position: absolute; top: 3px; left: 10px; right: 10px; height: 45%; border-radius: inherit; background: linear-gradient(to bottom, rgba(255,255,255,0.7), rgba(255,255,255,0)); pointer-events: none; }
.ms-term a.ms-term-btn.is-glass:hover { background-color: color-mix(in srgb, var(--btn-color, rgba(4, 107, 210, 0.9)), white 12%); transform: scale(1.02);
    background-image: linear-gradient(to top, rgba(255,255,255,0.55) 0%, rgba(255,255,255,0.30) 45%, rgba(255,255,255,0.05) 60%, rgba(0,0,0,0.12) 100%);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.45), inset 0 -1px 0 rgba(0,0,0,0.25), 0 4px 6px rgba(0,0,0,0.2); }
.ms-term a.ms-term-btn.is-glass:active { background-color: color-mix(in srgb, var(--btn-color, rgba(4, 107, 210, 0.9)), black 10%); transform: translateY(1px) scale(1); box-shadow: inset 0 2px 3px rgba(255,255,255,0.35), inset 0 -1px 2px rgba(0,0,0,0.25); }
.ms-term a.ms-term-btn.is-glass:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
html.ms-dark-ui .ms-term a.ms-term-btn.is-glass { border: 0; color: #fff; }
.ms-term .ms-term-practice { background: #fff4d6; color: #5c4300; border: 1px solid #f0b849; border-radius: 6px; padding: 10px 12px; margin: 0 0 16px; }
.ms-term .ms-term-practice a { margin-left: 8px; font-weight: bold; }
html.ms-dark-ui .ms-term .ms-term-practice { background: #3b3118; color: #f5dca0; border-color: #8a6a1f; }
.ms-term .ms-term-today { margin: 28px 0 0; border-top: 1px solid rgba(128,128,128,.45); padding-top: 12px; }
.ms-term .ms-term-today > summary { cursor: pointer; padding: 6px 0; }
.ms-term .ms-term-sale { display: grid; grid-template-columns: 1fr auto auto; gap: 10px; align-items: center; padding: 10px 0; border-bottom: 1px solid rgba(128,128,128,.45); }
.ms-term .ms-term-clear { margin: 24px 0 0; }
html.ms-dark-ui .ms-term { color: var(--ms-text, #e4dfd6); }
html.ms-dark-ui .ms-term .ms-term-search input, html.ms-dark-ui .ms-term .ms-term-disc select, html.ms-dark-ui .ms-term .ms-term-disc input { color: var(--ms-text, #e4dfd6); background: #262422; border-color: rgba(255,255,255,.35); }
html.ms-dark-ui .ms-term .ms-term-card { color: var(--ms-text, #e4dfd6); background: var(--ms-card-bg, #2b2926); border-color: var(--ms-card-border, #48443f); }
html.ms-dark-ui .ms-term .ms-term-btn { border-color: rgba(255,255,255,.55); }
html.ms-dark-ui .ms-term .ms-term-btn.is-primary { background: #e4dfd6; color: #1d1d1d; }
html.ms-dark-ui .ms-term .is-ok, html.ms-dark-ui .ms-term .ms-term-ok, html.ms-dark-ui .ms-term .ms-term-rule { color: #6fd36f; }
html.ms-dark-ui .ms-term .is-error, html.ms-dark-ui .ms-term .ms-term-no { color: #ff7a7a; }
html.ms-dark-ui .ms-term .is-warn { color: #f5b04f; }

.ms-term .ms-term-methods { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin: 12px 0 0; }
.ms-term .ms-term-methods .ms-term-btn { padding: 18px 12px; font-size: 18px; }
/* the theme's glass rule forces its own display on every button: icon and name sit in an inner span (a button is not flexed here) */
.ms-term .ms-term-inner { display: flex; align-items: center; justify-content: center; gap: 10px; }
.ms-term .ms-term-inner > span { font-size: 1.3rem; font-weight: 800; line-height: 1.1; }
.ms-term .ms-term-payicon { flex: none; width: 32px; height: 32px; padding: 5px; box-sizing: border-box; border-radius: 10px; background: #fff; object-fit: contain; vertical-align: middle; }
.ms-term .ms-term-waiting { display: flex; align-items: center; gap: 14px; }
.ms-term .ms-term-waiting .ms-term-payicon { width: 64px; height: 64px; padding: 9px; border-radius: 16px; border: 1px solid rgba(0,0,0,.12); }
.ms-term .ms-term-email { display: block; width: 100%; box-sizing: border-box; padding: 10px; margin: 6px 0 0; border: 1px solid #333; background: #fff; }
.ms-term .ms-term-pay h3 { margin-top: 0; }
.ms-term .ms-term-bigtotal { font-size: 3rem; font-weight: bold; line-height: 1.1; margin: 4px 0 12px; }
.ms-term .ms-term-qr { display: flex; flex-direction: column; align-items: center; gap: 8px; margin: 12px 0; }
.ms-term .ms-term-qr svg { width: min(78vw, 320px); height: auto; background: #fff; padding: 8px; border-radius: 6px; }
.ms-term .ms-term-pay-actions { display: grid; gap: 10px; margin: 16px 0 0; }
.ms-term .ms-term-pieces { margin: 0 0 8px; padding: 0; list-style: none; opacity: .85; }
.ms-term .ms-term-pieces li { margin: 0 0 10px; }
html.ms-dark-ui .ms-term .ms-term-email { color: var(--ms-text, #e4dfd6); background: #262422; border-color: rgba(255,255,255,.35); }
</style>
CSS;
}

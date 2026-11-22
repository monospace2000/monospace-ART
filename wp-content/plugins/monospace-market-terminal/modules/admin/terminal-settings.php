<?php
/**
 * WooCommerce > Market terminal (2026-11-14): the settings of the sale terminal on /terminal/ - which ways of paying show,
 * the Venmo and PayPal.Me names behind the QR codes, the Square application ID (used when Square opens by itself, a later
 * step). One option, ms_terminal_settings (read by ms_term_settings() in terminal-orders.php).
 * Admin UI style (CLAUDE.md): WP-native parts, folding panels that start closed, a state chip each.
 *
 * @package monospace-market-terminal
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
    add_submenu_page( 'woocommerce', 'Market terminal', 'Market terminal', 'manage_woocommerce', 'ms-market-terminal', 'ms_term_settings_page' );
} );

add_action( 'admin_post_ms_term_settings', function () {
    if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Not allowed.' );
    check_admin_referer( 'ms_term_settings' );
    $methods = array_values( array_intersect( [ 'Square', 'Cash', 'Venmo', 'PayPal' ], array_map( 'sanitize_text_field', (array) ( $_POST['methods'] ?? [] ) ) ) );
    $name    = function ( $key ) { return preg_replace( '/[^A-Za-z0-9._-]/', '', ltrim( trim( (string) wp_unslash( $_POST[ $key ] ?? '' ) ), '@' ) ); };
    update_option( 'ms_terminal_settings', [
        'methods'       => $methods,
        'venmo'         => $name( 'venmo' ),
        'paypal'        => $name( 'paypal' ),
        'paypal_email'  => is_email( trim( (string) wp_unslash( $_POST['paypal_email'] ?? '' ) ) ) ? trim( (string) wp_unslash( $_POST['paypal_email'] ) ) : '',
        'square_app_id' => preg_replace( '/[^A-Za-z0-9_-]/', '', trim( (string) wp_unslash( $_POST['square_app_id'] ?? '' ) ) ),
    ], false );
    wp_safe_redirect( add_query_arg( [ 'page' => 'ms-market-terminal', 'saved' => 1 ], admin_url( 'admin.php' ) ) );
    exit;
} );

function ms_term_settings_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) return;
    if ( function_exists( 'monospace_lm_area_assets' ) ) monospace_lm_area_assets();
    $s    = ms_term_settings();
    $page = ms_term_page_url();
    ?>
    <div class="wrap">
        <h1>Market terminal</h1>
        <?php if ( ! empty( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Saved.</p></div><?php endif; ?>
        <p>The sale screen for markets<?php echo $page ? ': <a href="' . esc_url( $page ) . '"><code>' . esc_html( wp_parse_url( $page, PHP_URL_PATH ) ) . '</code></a>' : ''; ?>. The older Market Inventory Manager is separate and unchanged.</p>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="ms_term_settings">
            <?php wp_nonce_field( 'ms_term_settings' ); ?>
            <div class="lm-areas"><div class="lm-panels">

                <details class="lm-panel" data-panel="methods">
                    <summary>
                        <span class="lm-t">Ways to pay</span>
                        <span class="lm-chip <?php echo $s['methods'] ? 'is-on' : 'is-empty'; ?>"><?php echo $s['methods'] ? 'In use' : 'None'; ?></span>
                        <span class="lm-sub"><?php echo $s['methods'] ? esc_html( implode( ', ', $s['methods'] ) ) : 'No buttons show on the sale screen'; ?></span>
                    </summary>
                    <div class="lm-body">
                        <p class="description">The buttons under "Take payment", in this order.</p>
                        <?php foreach ( [ 'Square', 'Cash', 'Venmo', 'PayPal' ] as $m ) : ?>
                            <label style="display:block;margin:4px 0;"><input type="checkbox" name="methods[]" value="<?php echo esc_attr( $m ); ?>"<?php checked( in_array( $m, $s['methods'], true ) ); ?>> <?php echo esc_html( $m ); ?></label>
                        <?php endforeach; ?>
                    </div>
                </details>

                <details class="lm-panel" data-panel="venmo">
                    <summary>
                        <span class="lm-t">Venmo</span>
                        <span class="lm-chip <?php echo $s['venmo'] !== '' ? 'is-on' : 'is-empty'; ?>"><?php echo $s['venmo'] !== '' ? 'In use' : 'Empty'; ?></span>
                        <span class="lm-sub"><?php echo $s['venmo'] !== '' ? '<code>@' . esc_html( $s['venmo'] ) . '</code>' : 'No code is shown without a name'; ?></span>
                    </summary>
                    <div class="lm-body">
                        <p><label>Venmo username <input type="text" name="venmo" value="<?php echo esc_attr( $s['venmo'] ); ?>" class="regular-text code" placeholder="Hens-Breet"></label></p>
                        <p class="description">The QR code opens a Venmo payment to this name with the total and the piece numbers filled in.</p>
                    </div>
                </details>

                <details class="lm-panel" data-panel="paypal">
                    <summary>
                        <span class="lm-t">PayPal</span>
                        <span class="lm-chip <?php echo ( $s['paypal_email'] !== '' || $s['paypal'] !== '' ) ? 'is-on' : 'is-empty'; ?>"><?php echo ( $s['paypal_email'] !== '' || $s['paypal'] !== '' ) ? 'In use' : 'Empty'; ?></span>
                        <span class="lm-sub"><?php echo $s['paypal_email'] !== '' ? esc_html( $s['paypal_email'] ) : ( $s['paypal'] !== '' ? '<code>paypal.me/' . esc_html( $s['paypal'] ) . '</code>' : 'No code is shown without an email or a PayPal.Me name' ); ?></span>
                    </summary>
                    <div class="lm-body">
                        <p><label>PayPal account email <input type="email" name="paypal_email" value="<?php echo esc_attr( $s['paypal_email'] ); ?>" class="regular-text code" placeholder="you@example.com"></label></p>
                        <p class="description">The QR code opens PayPal's checkout page for this account with the total filled in (it works from any phone, with or without the PayPal app).</p>
                        <p><label>PayPal.Me name <input type="text" name="paypal" value="<?php echo esc_attr( $s['paypal'] ); ?>" class="regular-text code" placeholder="monospace"></label></p>
                        <p class="description">Used instead when no email is set: <code>paypal.me/name/total</code>. The PayPal phone app drops the total from these links.</p>
                    </div>
                </details>

                <details class="lm-panel" data-panel="square">
                    <summary>
                        <span class="lm-t">Square</span>
                        <span class="lm-chip <?php echo $s['square_app_id'] !== '' ? 'is-on' : 'is-empty'; ?>"><?php echo $s['square_app_id'] !== '' ? 'In use' : 'Empty'; ?></span>
                        <span class="lm-sub"><?php echo $s['square_app_id'] !== '' ? 'The Square button opens the Square app with the total' : 'Empty: the total is typed into the Square app by hand'; ?></span>
                    </summary>
                    <div class="lm-body">
                        <p><label>Square application ID <input type="text" name="square_app_id" value="<?php echo esc_attr( $s['square_app_id'] ); ?>" class="regular-text code"></label></p>
                        <p class="description">From the application in your Square developer account (not a secret). With it, the Square payment screen has a button that opens the Square app (Tap to Pay or a reader) with the total filled in, and Square switches back here by itself. In Square's developer console: Point of Sale API &rarr; Web &rarr; Web Callback URL must be exactly <code><?php echo esc_html( $page ?: home_url( '/terminal/' ) ); ?></code>. Practice mode never opens Square.</p>
                    </div>
                </details>

            </div></div>
            <?php submit_button( 'Save' ); ?>
        </form>
    </div>
    <?php
}

/** The address of the page with the sale screen - the page with the role terminal, wherever it is ('' = none) */
function ms_term_page_url() {
    $page = function_exists( 'monospace_role_page' ) ? monospace_role_page( 'terminal' ) : null;
    return ( $page && $page->post_status === 'publish' && has_shortcode( $page->post_content, 'market_terminal' ) ) ? (string) get_permalink( $page ) : '';
}

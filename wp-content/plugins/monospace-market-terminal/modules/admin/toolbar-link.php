<?php
/**
 * Toolbar link to the Market Inventory Manager (2026-10-21, owner) - the page that shows [market_inventory_terminal]
 * (art.monospace.com/inventory/). For those who can use the terminal (manage_woocommerce), on the front end and in
 * wp-admin. Part of this plugin, so it goes when the plugin is switched off.
 *
 * The item's id starts with ms-tb-, so the Admin tools plugin's toolbar layout (Settings > Admin toolbar) treats it
 * like its own shortcuts: icon-only on phones, placed and hidden like any other item there. Its icon: the store
 * dashicon, below.
 *
 * On an artwork (its post or product on the site, or its edit screen in wp-admin) the link opens that artwork in the
 * terminal (?product_id=, 2026-10-21, owner) - when it's an original, the terminal's only stock.
 *
 * @package monospace-market-terminal
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The page with the Inventory Manager (the page with the role inventory), else the first published page with the shortcode ('' = none) */
function monospace_terminal_page_url() {
    static $url = null;
    if ( $url !== null ) return $url;
    $page = function_exists( 'monospace_role_page' ) ? monospace_role_page( 'inventory' ) : null;
    if ( ! $page || $page->post_status !== 'publish' || ! has_shortcode( $page->post_content, 'market_inventory_terminal' ) ) {
        $id = get_transient( 'monospace_terminal_page' );
        if ( $id === false ) {
            global $wpdb;
            $id = (int) $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE '%[market\\_inventory\\_terminal%' ORDER BY ID LIMIT 1" );
            set_transient( 'monospace_terminal_page', $id, DAY_IN_SECONDS );
        }
        $page = $id ? get_post( $id ) : null;
    }
    return $url = $page ? (string) get_permalink( $page ) : '';
}

/** The original on the current page - an artwork post's product, a product, or one being edited (0 = none) */
function monospace_terminal_current_product() {
    $id = 0;
    if ( is_admin() ) {
        global $pagenow;
        if ( $pagenow === 'post.php' && ! empty( $_GET['post'] ) ) $id = absint( $_GET['post'] );
    } elseif ( is_singular( [ 'post', 'product' ] ) ) {
        $id = (int) get_queried_object_id();
    }
    if ( ! $id ) return 0;
    $product_id = get_post_type( $id ) === 'product' ? $id : ( function_exists( 'monospace_get_post_product_id' ) ? monospace_get_post_product_id( $id ) : (int) get_post_meta( $id, '_qp_product_id', true ) );
    return $product_id && function_exists( 'mit_is_original' ) && mit_is_original( $product_id ) ? $product_id : 0;
}

add_action( 'admin_bar_menu', function ( $bar ) {
    if ( ! current_user_can( 'manage_woocommerce' ) ) return;
    // 2026-11-15 (owner): the toolbar icon (phones, by default) opens the sale screen, /terminal/; the Inventory Manager page stays, reached by its address. Without the sale screen page, the icon falls back to the Inventory Manager
    if ( function_exists( 'ms_term_page_url' ) && ( $url = ms_term_page_url() ) ) {
        $bar->add_node( [
            'id'    => 'ms-tb-market',
            'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ms-tb-label">Market Terminal</span>',
            'href'  => $url,
            'meta'  => [ 'title' => 'Market Terminal' ],
        ] );
        return;
    }
    if ( ! ( $url = monospace_terminal_page_url() ) ) return;
    if ( $product_id = monospace_terminal_current_product() ) $url = add_query_arg( 'product_id', $product_id, $url );
    $bar->add_node( [
        'id'    => 'ms-tb-market',
        'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ms-tb-label">Market Inventory</span>',
        'href'  => $url,
        'meta'  => [ 'title' => $product_id ? 'Open this artwork in the Market Inventory Manager' : 'Market Inventory Manager' ],
    ] );
}, 96 );

// The icon (the store dashicon), front end and wp-admin
$monospace_terminal_toolbar_css = function () {
    if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_woocommerce' ) ) return;
    echo '<style id="ms-tb-market-css">#wpadminbar li#wp-admin-bar-ms-tb-market .ab-icon:before{content:"\f513";top:2px}#wpadminbar li#wp-admin-bar-ms-tb-market .ms-tb-label{margin-left:4px}</style>' . "\n";
};
add_action( 'wp_head', $monospace_terminal_toolbar_css );
add_action( 'admin_head', $monospace_terminal_toolbar_css );

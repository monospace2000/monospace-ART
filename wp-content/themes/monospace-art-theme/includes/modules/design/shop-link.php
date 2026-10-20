<?php
/**
 * Design module: Shop link (2026-10-16)
 *
 * The empty cart's "Return to shop" button (WooCommerce's cart-empty template): where it goes and
 * its text. Default: the Art Feed (/art-feed/ - what _misc-woocommerce-tweaks.php hard-coded before)
 * and WooCommerce's own wording. A site path ("/category/available/") or a full URL.
 * Shown in Layout Manager > Appearance & Effects through the design-module registry.
 *
 * Options: monospace_shop_link, monospace_shop_link_text ('' = WooCommerce's "Return to shop").
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['shop-link'] = [
        'title'       => 'Shop link',
        'description' => 'The "Return to shop" button on the empty cart.',
        'group'       => 'links',
        'order'       => 20,
        'summary'     => function () { return (string) get_option( 'monospace_shop_link', '/art-feed/' ); },
        'render'      => 'monospace_render_shop_link_settings',
        'save'        => 'monospace_save_shop_link_settings',
    ];
    return $modules;
} );

/** The button's link (absolute URL) */
function monospace_shop_link_url() {
    $link = (string) get_option( 'monospace_shop_link', '/art-feed/' );
    if ( $link === '' ) $link = '/';
    return $link[0] === '/' ? home_url( $link ) : $link;
}

add_filter( 'woocommerce_return_to_shop_redirect', 'monospace_shop_link_url' );

add_filter( 'woocommerce_return_to_shop_text', function ( $text ) {
    $own = (string) get_option( 'monospace_shop_link_text', '' );
    return $own !== '' ? $own : $text;
} );

function monospace_render_shop_link_settings() {
    $link = (string) get_option( 'monospace_shop_link', '/art-feed/' );
    $text = (string) get_option( 'monospace_shop_link_text', '' );
    $choices = function_exists( 'monospace_home_link_choices' ) ? monospace_home_link_choices() : []; ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="ms-shop-link">Link</label></th>
            <td>
                <input type="text" name="shop_link" id="ms-shop-link" class="regular-text" value="<?php echo esc_attr( $link ); ?>" placeholder="/category/available/ or https://…">
                <?php if ( $choices ) : ?>
                    <select id="ms-shop-link-pick" style="max-width:320px">
                        <option value="">Pick a page, category or place…</option>
                        <option value="/">Home page</option>
                        <?php foreach ( $choices as $group => $items ) : ?>
                            <optgroup label="<?php echo esc_attr( $group ); ?>">
                                <?php foreach ( $items as [ $label, $url ] ) echo '<option value="' . esc_attr( $url ) . '"' . selected( $url, $link, false ) . '>' . esc_html( $label ) . '</option>'; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <script>
                        document.getElementById('ms-shop-link-pick').addEventListener('change', function () {
                            if (this.value) document.getElementById('ms-shop-link').value = this.value;
                        });
                    </script>
                <?php endif; ?>
                <p class="description">A path on this site (starting with /) or a full address. Now: <a href="<?php echo esc_url( monospace_shop_link_url() ); ?>" target="_blank"><?php echo esc_html( monospace_shop_link_url() ); ?></a></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="ms-shop-link-text">Button text</label></th>
            <td><input type="text" name="shop_link_text" id="ms-shop-link-text" class="regular-text" value="<?php echo esc_attr( $text ); ?>" placeholder="Return to shop">
                <p class="description">Leave empty for WooCommerce's “Return to shop”.</p></td>
        </tr>
    </table>
    <?php
}

function monospace_save_shop_link_settings() {
    if ( ! isset( $_POST['shop_link'] ) ) return;
    $link = trim( wp_unslash( $_POST['shop_link'] ) );
    $link = $link === '' ? '/' : esc_url_raw( $link ); // a site path stays relative
    if ( $link !== '' ) update_option( 'monospace_shop_link', $link );
    update_option( 'monospace_shop_link_text', sanitize_text_field( wp_unslash( $_POST['shop_link_text'] ?? '' ) ) );
}

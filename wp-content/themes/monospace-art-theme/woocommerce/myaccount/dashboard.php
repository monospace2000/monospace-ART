<?php
/**
 * My Account dashboard - theme override of WooCommerce's myaccount/dashboard.php (2026-11-16, appearance only).
 * The greeting is in the page header (includes/modules/commerce/account-page.php); here every tab (except Log out)
 * is a tile with a line about it. Styles: _woocommerce.scss (MY ACCOUNT PAGE).
 *
 * @version 4.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$blurbs = array(
	'orders'          => 'Track and review your orders.',
	'my-commissions'  => 'Previews and progress on your commissions.',
	'downloads'       => 'Files you can download.',
	'edit-address'    => 'Your billing and shipping addresses.',
	'payment-methods' => 'Your saved payment methods.',
	'edit-account'    => 'Your name, email and password.',
);

$tiles = wc_get_account_menu_items();
unset( $tiles['dashboard'], $tiles['customer-logout'] );
?>

<?php
	// A short welcome with the customer's name (2026-11-17, owner)
	$user = wp_get_current_user();
	$name = $user->first_name !== '' ? $user->first_name : $user->display_name;
?>
<p class="ms-acct-welcome">Welcome, <?php echo esc_html( $name ); ?>. Your orders and commissions are all here.</p>

<div class="ms-acct-tiles">
	<?php foreach ( $tiles as $endpoint => $label ) : ?>
		<a class="ms-acct-tile ms-acct-tile--<?php echo esc_attr( $endpoint ); ?>" href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>">
			<span class="ms-acct-tile__title"><?php echo esc_html( $label ); ?></span>
			<?php if ( isset( $blurbs[ $endpoint ] ) ) : ?>
				<span class="ms-acct-tile__text"><?php echo esc_html( $blurbs[ $endpoint ] ); ?></span>
			<?php endif; ?>
		</a>
	<?php endforeach; ?>
</div>

<?php
	/** This action is documented in WooCommerce's myaccount/dashboard.php. */
	do_action( 'woocommerce_account_dashboard' );

	/** @deprecated 2.6.0 */
	do_action( 'woocommerce_before_my_account' );

	/** @deprecated 2.6.0 */
	do_action( 'woocommerce_after_my_account' );

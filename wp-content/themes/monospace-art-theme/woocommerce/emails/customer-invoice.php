<?php
/**
 * Customer invoice email
 *
 * (template header omitted for brevity)
 */

use Automattic\WooCommerce\Enums\OrderStatus;
use Automattic\WooCommerce\Utilities\FeaturesUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$email_improvements_enabled = FeaturesUtil::feature_is_enabled( 'email_improvements' );

/**
 * Executes the e-mail header.
 */
do_action( 'woocommerce_email_header', $email_heading, $email );

// -------------------------

echo $email_improvements_enabled ? '<div class="email-introduction">' : '';
?>
<p>
<?php
$is_final_payment = false;
$original_order_id = null;

foreach ( $order->get_items() as $item ) {
    if ( $item->get_meta('_is_final_payment', true) ) {
        $is_final_payment = true;
        $original_order_id = $item->get_meta('_original_order_id', true);
        break;
    }
}

if ( $is_final_payment && $original_order_id ) {

    // Open wrapper if improvements enabled
    if ( $email_improvements_enabled ) echo '<div class="email-introduction">';

    echo '<p><strong>Note:</strong> This is the <em>final payment</em> for your custom order ';
    echo '<a href="' . esc_url( get_permalink( wc_get_order($original_order_id) ) ) . '">Order #' . esc_html( $original_order_id ) . '</a>. ';
    echo 'The item will be shipped as soon as payment is received.</p>';

    if ( $email_improvements_enabled ) echo '</div>';
}
?>

<?php
if ( ! empty( $order->get_billing_first_name() ) ) {
	/* translators: %s: Customer first name */
	printf( esc_html__( 'Hi %s,', 'woocommerce' ), esc_html( $order->get_billing_first_name() ) );
} else {
	printf( esc_html__( 'Hi,', 'woocommerce' ) );
}
?>
</p>
<?php if ( $order->needs_payment() ) { ?>
	<p>
	<?php
	if ( $order->has_status( OrderStatus::FAILED ) ) {
		printf(
			wp_kses(
			/* translators: %1$s Site title, %2$s Order pay link */
				__( 'Sorry, your order on %1$s was unsuccessful. Your order details are below, with a link to try your payment again: %2$s', 'woocommerce' ),
				array(
					'a' => array(
						'href' => array(),
					),
				)
			),
			esc_html( get_bloginfo( 'name', 'display' ) ),
			'<a href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . esc_html__( 'Pay for this order', 'woocommerce' ) . '</a>'
		);
	} else {
		printf(
			wp_kses(
			/* translators: %1$s Site title, %2$s Order pay link */
				__( 'An order has been created for you on %1$s. Your order details are below, with a link to make payment when you’re ready: %2$s', 'woocommerce' ),
				array(
					'a' => array(
						'href' => array(),
					),
				)
			),
			esc_html( get_bloginfo( 'name', 'display' ) ),
			'<a href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . esc_html__( 'Pay for this order', 'woocommerce' ) . '</a>'
		);
	}
	?>
	</p>

<?php } else { ?>
	<p>
	<?php
	/* translators: %s Order date */
	printf( esc_html__( 'Here are the details of your order placed on %s:', 'woocommerce' ), esc_html( wc_format_datetime( $order->get_date_created() ) ) );
	?>
	</p>
<?php
}
?>
<?php echo $email_improvements_enabled ? '</div>' : ''; ?>

<?php
// ... remainder of original template unchanged
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
	echo $email_improvements_enabled ? '<table border="0" cellpadding="0" cellspacing="0" width="100%" role="presentation"><tr><td class="email-additional-content">' : '';
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
	echo $email_improvements_enabled ? '</td></tr></table>' : '';
}

do_action( 'woocommerce_email_footer', $email );

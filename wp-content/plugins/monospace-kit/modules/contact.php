<?php
/**
 * Contact form (replaces Contact Form 7 + Akismet + WP Mail SMTP, owner 2026-11-23).
 *
 * Shortcode [monospace_contact]. While Contact Form 7 is not active, its shortcode [contact-form-7 ...] shows this form too, so
 * the existing Contact pages keep working unchanged. Fields: name, email, subject, message (what all three CF7 forms had).
 * Markup keeps CF7's class names, so each theme's existing form styling still applies.
 *
 * Sent with wp_mail (the server's mail) to 'to' from the site's config, from 'from', Reply-To the visitor.
 * Spam: a hidden field people never fill in, and a signed time stamp (a form sent within 3 seconds of loading, or a day
 * later, is dropped). Posts to admin-post.php, then back to the page with ?contact=sent|error#contact (page cache safe).
 *
 * Config: 'contact' => [ 'to' => address, 'from' => address, 'privacy' => HTML note under the form, 'subject_prefix' => text ].
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_kit_contact_form() {
	$c      = monospace_kit_settings()['contact'];
	$state  = isset( $_GET['contact'] ) ? sanitize_key( $_GET['contact'] ) : '';
	$stamp  = time();
	$sign   = wp_hash( 'monospace-contact|' . $stamp );
	$action = esc_url( admin_url( 'admin-post.php' ) );
	$back   = esc_url( remove_query_arg( 'contact', get_permalink() ?: home_url( add_query_arg( [] ) ) ) );

	if ( $state === 'sent' ) {
		return '<div class="wpcf7 ms-contact" id="contact"><p class="wpcf7-response-output ms-contact-sent" role="status">Thank you for your message. It has been sent.</p></div>';
	}
	$out  = '<div class="wpcf7 ms-contact" id="contact">';
	if ( $state === 'error' ) {
		$out .= '<p class="wpcf7-response-output ms-contact-error" role="alert">Sorry, the message could not be sent. Please check the fields and try again.</p>';
	}
	$out .= '<form class="wpcf7-form" method="post" action="' . $action . '">';
	$fields = '<input type="hidden" name="action" value="monospace_contact">'
		. '<input type="hidden" name="ms_back" value="' . $back . '">'
		. '<input type="hidden" name="ms_t" value="' . $stamp . '"><input type="hidden" name="ms_s" value="' . esc_attr( $sign ) . '">'
		. '<p class="ms-contact-hp" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">'
		. '<label>Leave this empty <input type="text" name="ms_website" tabindex="-1" autocomplete="off"></label></p>'
		. '<p><label> Your name<br><input class="wpcf7-form-control wpcf7-text" type="text" name="ms_name" required maxlength="200" autocomplete="name"></label></p>'
		. '<p><label> Your email<br><input class="wpcf7-form-control wpcf7-email" type="email" name="ms_email" required maxlength="200" autocomplete="email"></label></p>'
		. '<p><label> Subject<br><input class="wpcf7-form-control wpcf7-text" type="text" name="ms_subject" required maxlength="200"></label></p>'
		. '<p><label> Your message<br><textarea class="wpcf7-form-control wpcf7-textarea" name="ms_message" required rows="10" maxlength="10000"></textarea></label></p>';
	if ( ! empty( $c['privacy'] ) ) $fields .= '<p class="form-privacy" style="font-size:0.7rem">' . wp_kses_post( $c['privacy'] ) . '</p>';
	$fields .= '<p><input class="wpcf7-form-control wpcf7-submit" type="submit" value="Submit"></p>';
	// Same filter as Contact Form 7, so a theme that reworks CF7's fields (the art theme's glass submit button) does the same here
	$out .= apply_filters( 'wpcf7_form_elements', $fields ) . '</form></div>';
	return $out;
}

add_shortcode( 'monospace_contact', 'monospace_kit_contact_form' );
add_action( 'init', function () {
	if ( ! shortcode_exists( 'contact-form-7' ) ) add_shortcode( 'contact-form-7', 'monospace_kit_contact_form' );
}, 20 );

function monospace_kit_contact_handle() {
	$back = esc_url_raw( wp_unslash( $_POST['ms_back'] ?? '' ) );
	if ( ! $back || wp_parse_url( $back, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) $back = home_url( '/' );
	$go = function ( $state ) use ( $back ) { wp_safe_redirect( add_query_arg( 'contact', $state, $back ) . '#contact', 303 ); exit; };

	$stamp = (int) ( $_POST['ms_t'] ?? 0 );
	$age   = time() - $stamp;
	$spam  = ! empty( $_POST['ms_website'] ) || ! hash_equals( wp_hash( 'monospace-contact|' . $stamp ), (string) ( $_POST['ms_s'] ?? '' ) ) || $age < 3 || $age > DAY_IN_SECONDS;
	if ( $spam ) $go( 'sent' ); // say nothing useful to a bot

	$name    = sanitize_text_field( wp_unslash( $_POST['ms_name'] ?? '' ) );
	$email   = sanitize_email( wp_unslash( $_POST['ms_email'] ?? '' ) );
	$subject = sanitize_text_field( wp_unslash( $_POST['ms_subject'] ?? '' ) );
	$message = sanitize_textarea_field( wp_unslash( $_POST['ms_message'] ?? '' ) );
	if ( ! $name || ! is_email( $email ) || ! $subject || ! $message ) $go( 'error' );

	$c    = monospace_kit_settings()['contact'];
	$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$to   = $c['to'] ?? get_option( 'admin_email' );
	$from = $c['from'] ?? get_option( 'admin_email' );
	$headers = [ 'From: ' . $site . ' <' . $from . '>', 'Reply-To: ' . $name . ' <' . $email . '>' ];
	$body = "From: $name <$email>\nSubject: $subject\n\nMessage Body:\n$message\n\n-- \nSent with the contact form on " . wp_parse_url( home_url(), PHP_URL_HOST ) . ' (' . $back . ')';
	$ok   = wp_mail( $to, ( $c['subject_prefix'] ?? '[' . $site . '] ' ) . $subject, $body, $headers );
	$go( $ok ? 'sent' : 'error' );
}
add_action( 'admin_post_nopriv_monospace_contact', 'monospace_kit_contact_handle' );
add_action( 'admin_post_monospace_contact', 'monospace_kit_contact_handle' );

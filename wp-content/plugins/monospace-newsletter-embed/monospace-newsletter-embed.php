<?php
/**
 * Plugin Name: monospace ART - Newsletter Embed
 * Update URI: false
 * Description: [newsletter] shows a sent MailPoet newsletter inside a post or page, in the page's own layout instead of MailPoet's standalone view. [newsletter id="14"] picks one; without id it shows the latest sent.
 * Version: 1.0.0
 * Author: Hens Breet
 * Requires at least: 6.0
 *
 * A standalone, generic plugin - needs only MailPoet. Folder monospace-newsletter-embed (a plain name could be
 * matched to another developer's plugin on WordPress.org). It builds the same "view in browser" address MailPoet
 * puts in its emails, so there is no token to make by hand: the id is the number in the newsletter's edit address.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The "view in browser" address of a sent newsletter ( 0 = the latest sent ), or '' when there is none. */
function monospace_newsletter_embed_url( $newsletter_id = 0 ) {
	global $wpdb;
	$p = $wpdb->prefix . 'mailpoet_';
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $p . 'newsletters' ) ) !== $p . 'newsletters' ) return '';

	$newsletter_id = (int) $newsletter_id;
	$row = $wpdb->get_row( $newsletter_id
		? $wpdb->prepare( "SELECT n.id, n.hash, MAX(q.id) AS queue_id FROM {$p}newsletters n JOIN {$p}sending_queues q ON q.newsletter_id = n.id WHERE n.id = %d AND n.status = 'sent' GROUP BY n.id, n.hash", $newsletter_id )
		: "SELECT n.id, n.hash, MAX(q.id) AS queue_id FROM {$p}newsletters n JOIN {$p}sending_queues q ON q.newsletter_id = n.id WHERE n.type = 'standard' AND n.status = 'sent' GROUP BY n.id, n.hash ORDER BY n.id DESC LIMIT 1" );
	if ( ! $row ) return '';

	// MailPoet shows the page as one subscriber: the site owner's, else the first subscribed.
	$sub = $wpdb->get_row( $wpdb->prepare( "SELECT id, link_token FROM {$p}subscribers WHERE email = %s", get_option( 'admin_email' ) ) );
	if ( ! $sub ) $sub = $wpdb->get_row( "SELECT id, link_token FROM {$p}subscribers WHERE status = 'subscribed' ORDER BY id LIMIT 1" );
	if ( ! $sub ) return '';

	$data = array( (int) $row->id, $row->hash, (int) $sub->id, substr( $sub->link_token, 0, 6 ), (int) $row->queue_id, 1 );
	$token = rtrim( base64_encode( wp_json_encode( $data ) ), '=' );
	return home_url( '/?mailpoet_router&endpoint=view_in_browser&action=view&data=' . $token );
}

add_shortcode( 'newsletter', function ( $atts ) {
	static $n = 0;
	$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'newsletter' );
	$url  = monospace_newsletter_embed_url( $atts['id'] );
	if ( ! $url ) return current_user_can( 'edit_posts' ) ? '<p><em>[newsletter] - no sent newsletter found' . ( $atts['id'] ? ' with id ' . (int) $atts['id'] : '' ) . '.</em></p>' : '';

	$dom = 'ms-newsletter-' . ++$n;
	return '<iframe id="' . esc_attr( $dom ) . '" src="' . esc_url( $url ) . '" width="100%" frameborder="0" scrolling="no" style="border-radius: 8px; border: solid 1px #CCC !important;"></iframe>'
		. '<script>(function(){var f=document.getElementById("' . esc_js( $dom ) . '");'
		. 'var fit=function(){try{f.style.height=f.contentDocument.body.scrollHeight+"px";}catch(e){}};'
		. 'f.addEventListener("load",function(){fit();if(window.ResizeObserver)new ResizeObserver(fit).observe(f.contentDocument.body);});'
		. 'window.addEventListener("resize",fit);})();</script>';
} );

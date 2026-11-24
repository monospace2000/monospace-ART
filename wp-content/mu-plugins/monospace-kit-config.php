<?php
/**
 * Plugin Name: monospace Kit settings (this site)
 * Description: Which parts of the shared monospace Kit plugin are on here, and their settings. Master of the Kit: knowledge base tools/monospace-kit/.
 * Update URI: false
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// The art site keeps its own lightbox and "Hide this block" (theme / monospace-admin-tools); GA4 is not sent here (Site Kit had
// "place the tag" off), only Google consent mode for the cookie banner and the Google for WooCommerce tag.
function monospace_kit_config() {
	return [
		'modules'     => [ 'analytics', 'contact' ],
		// done by the art site's own code (theme, Core, monospace-admin-tools, monospace-ai-alt-text, monospace-privacy): shown on the screen, never loaded
		'own'         => [ 'lightbox', 'hide-blocks', 'remove-background', 'media-usage', 'ai-alt-text', 'alt-sync', 'heic', 'video-poster', 'youtube-poster', 'save-shortcut', 'post-navigation', 'cookie-banner', 'spotlight', 'related-posts' ],
		'ga4'         => '',
		'ga4_consent' => true,
		'contact'     => [
			'to'             => 'info@monospace.com',
			'from'           => 'info@monospace.com',
			'subject_prefix' => '[monospace ART] Form Submit: ',
			'privacy'        => 'Your details are only used to reply to your message. See our <a href="https://art.monospace.com/legal/privacy-policy/">Privacy Policy</a>.',
		],
	];
}

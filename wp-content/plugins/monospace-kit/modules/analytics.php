<?php
/**
 * Analytics: Google consent mode and the Google Analytics 4 tag (replace Site Kit, owner 2026-11-23). Reports: in Google
 * Analytics itself. Logged-in editors are not counted (Site Kit did the same).
 *
 * Config:
 *   'ga4'         => 'G-XXXXXXX'  the GA4 measurement ID; empty = no Analytics tag.
 *   'ga4_consent' => true         (also automatic while the Kit's cookie-banner part is on) sites with a cookie banner (the art site's monospace-privacy): Google consent mode, everything
 *                                 denied until the visitor accepts. Printed first in <head>, before any Google tag (also the
 *                                 Google Ads tag of Google for WooCommerce, which needs it). The choice comes from the WP Consent
 *                                 API as Site Kit read it: on page load (wp_has_consent) and on change (the event
 *                                 wp_listen_for_consent_change); statistics -> analytics_storage, marketing -> the ad signals.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_kit_analytics_skip() {
	return ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) || is_preview();
}

// Consent mode: before every Google tag
add_action( 'wp_head', function () {
	if ( empty( monospace_kit_settings()['ga4_consent'] ) && ! monospace_kit_on( 'cookie-banner' ) ) return;
	echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}"
		. "gtag('consent','default',{analytics_storage:'denied',ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',wait_for_update:500});"
		. "(function(){function map(c){var u={};if(c.statistics)u.analytics_storage=c.statistics==='allow'?'granted':'denied';"
		. "if(c.marketing){var m=c.marketing==='allow'?'granted':'denied';u.ad_storage=m;u.ad_user_data=m;u.ad_personalization=m;}return u;}"
		. "function now(){if(typeof wp_has_consent!=='function')return;gtag('consent','update',map({statistics:wp_has_consent('statistics')?'allow':'deny',marketing:wp_has_consent('marketing')?'allow':'deny'}));}"
		. "document.addEventListener('wp_listen_for_consent_change',function(e){var u=map(e.detail||{});if(Object.keys(u).length)gtag('consent','update',u);});"
		. "if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',now);else now();})();</script>\n";
}, 0 );

// The Analytics tag
add_action( 'wp_head', function () {
	$id = trim( (string) monospace_kit_settings()['ga4'] );
	if ( ! preg_match( '/^G-[A-Z0-9]+$/', $id ) || monospace_kit_analytics_skip() ) return;
	echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $id ) . "\"></script>\n"
		. "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . esc_js( $id ) . "');</script>\n";
}, 5 );

<?php
/**
 * Plugin Name: Block AI Crawlers
 * Description: Appends AI-training crawler rules to the WordPress-generated robots.txt.
 *
 * Shared across the monospace WordPress subdomains (art, music, sightings).
 * Meta-WebIndexer (Meta AI search citations) is deliberately left allowed.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'robots_txt', function ( $output ) {
	return rtrim( $output ) . "\n\n"
		. "# Meta AI model training\n"
		. "User-agent: Meta-ExternalAgent\n"
		. "Disallow: /\n";
}, 99 );

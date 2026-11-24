<?php
/**
 * Lightbox (replaces Easy FancyBox / Featured Image Zoom, owner 2026-11-23): the art site's own lightbox, assets/ms-lightbox.js
 * (copied unchanged from art.monospace.com's theme; edit it there first). Tap closes, swipe changes the picture, pinch zooms.
 *
 * Posts: every picture opens in it, except pictures inside a link to somewhere else and pictures with the class nolightbox.
 * A picture already linked to its own file opens in the lightbox too. Pages: only pictures with the class lightbox
 * (Image block > Advanced > Additional CSS class). The script loads only where it is used.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_kit_lightbox_mode() {
	if ( is_singular( 'post' ) ) return 'all';
	if ( is_singular() ) {
		$post = get_post();
		if ( $post && preg_match( '/class="[^"]*\blightbox\b/', $post->post_content ) ) return 'opt-in';
	}
	return '';
}

add_action( 'wp_enqueue_scripts', function () {
	wp_register_script( 'ms-lightbox', MONOSPACE_KIT_URL . 'assets/ms-lightbox.js', [], filemtime( MONOSPACE_KIT_DIR . '/assets/ms-lightbox.js' ), true );
	if ( monospace_kit_lightbox_mode() ) wp_enqueue_script( 'ms-lightbox' );
} );

/** Is this address the picture's own file (or a size of it)? */
function monospace_kit_lightbox_is_file( $href ) {
	return (bool) preg_match( '/\.(jpe?g|png|gif|webp|avif)(\?.*)?$/i', (string) $href );
}

add_filter( 'the_content', function ( $content ) {
	$mode = monospace_kit_lightbox_mode();
	if ( ! $mode || ! in_the_loop() || ! is_main_query() || stripos( $content, '<img' ) === false ) return $content;

	libxml_use_internal_errors( true );
	$dom = new DOMDocument();
	$dom->loadHTML( '<?xml encoding="UTF-8"><div id="ms-kit-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();

	$images = [];
	foreach ( $dom->getElementsByTagName( 'img' ) as $img ) $images[] = $img;
	$changed = false;

	foreach ( $images as $img ) {
		// Opted in: the picture or its block's <figure> carries the class lightbox
		$opted = false;
		for ( $n = $img, $d = 0; $n && $n->nodeType === XML_ELEMENT_NODE && $d < 4; $n = $n->parentNode, $d++ ) {
			if ( preg_match( '/(^|\s)lightbox(\s|$)/', (string) $n->getAttribute( 'class' ) ) ) { $opted = true; break; }
			if ( $n->nodeName === 'figure' ) break;
		}
		if ( $mode === 'opt-in' && ! $opted ) continue;
		if ( preg_match( '/(^|\s)nolightbox(\s|$)/', (string) $img->getAttribute( 'class' ) ) ) continue;

		// Inside a link: only a link to the picture's own file (or an opted-in picture's direct link) becomes the lightbox's
		$link = null;
		for ( $n = $img->parentNode; $n && $n->nodeType === XML_ELEMENT_NODE; $n = $n->parentNode ) {
			if ( $n->nodeName === 'a' ) { $link = $n; break; }
		}
		if ( $link && ! ( monospace_kit_lightbox_is_file( $link->getAttribute( 'href' ) ) || ( $opted && $img->parentNode === $link ) ) ) continue;

		$full = '';
		if ( preg_match( '/wp-image-(\d+)/', (string) $img->getAttribute( 'class' ), $m ) ) {
			$src  = wp_get_attachment_image_src( (int) $m[1], 'full' );
			$full = $src ? $src[0] : '';
		}
		if ( ! $full ) $full = $link && monospace_kit_lightbox_is_file( $link->getAttribute( 'href' ) ) ? $link->getAttribute( 'href' ) : $img->getAttribute( 'src' );

		if ( ! $link ) {
			$link = $dom->createElement( 'a' );
			$img->parentNode->replaceChild( $link, $img );
			$link->appendChild( $img );
		}
		$link->setAttribute( 'href', $full );
		$link->setAttribute( 'data-gallery', 'post-gallery' );
		$link->setAttribute( 'class', trim( $link->getAttribute( 'class' ) . ' ms-lightbox-link' ) );
		$link->removeAttribute( 'target' );
		$alt = trim( (string) $img->getAttribute( 'alt' ) );
		if ( $alt !== '' ) $link->setAttribute( 'data-alt', $alt );
		$changed = true;
	}
	if ( ! $changed ) return $content;

	$root = $dom->getElementById( 'ms-kit-root' );
	$html = '';
	foreach ( $root->childNodes as $child ) $html .= $dom->saveHTML( $child );
	return $html;
}, 20 );

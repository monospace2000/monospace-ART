<?php
/**
 * Auto Featured Image
 *
 * Automatically sets the post's featured image from the first image in the post content
 * when a post is published or updated. Saves time by eliminating manual featured image selection.
 *
 * FUNCTIONALITY:
 * - Triggers when a post is saved or published
 * - Finds the first <img> tag in post content
 * - Extracts the image ID from WordPress classes (wp-image-{id})
 * - Sets that image as the post's featured image
 * - Only runs if no featured image is already set
 *
 * BEHAVIOR:
 * - Skips autosaves and revisions
 * - Only processes regular posts (not pages or products)
 * - Does nothing if a featured image is already manually set
 * - Requires images to be uploaded through WordPress media library
 *
 * USE CASE:
 * When creating artwork posts, just add an image in the content. WordPress will
 * automatically use it as the featured image for archive pages and thumbnails.
 *
 * TECHNICAL:
 * - Hooks into 'save_post_post' action (priority 20)
 * - Parses HTML to find image with regex
 * - Uses set_post_thumbnail() to assign featured image
 */
add_action( 'save_post_post', 'auto_set_featured_image_from_content', 20, 3 );
function auto_set_featured_image_from_content( $post_id, $post, $update ) {
	// Bail if this is an autosave
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Bail if this is a revision
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	monospace_ensure_featured_image( $post_id );
}

// The block editor saves in two steps: the post (save_post, above), then its featured image - and with none chosen it sends
// 0, which removes the image just set (2026-10-28: artwork 722 had neither a post nor a product image). This runs after
// that step (REST) and keeps the artwork's product in step with the post.
add_action( 'rest_after_insert_post', function ( $post ) {
	if ( $post->post_type === 'post' && ! wp_is_post_revision( $post->ID ) ) monospace_ensure_featured_image( $post->ID );
}, 20 );

/**
 * The post gets its first content image as featured image when it has none; its product (Quick Artwork Editor's link,
 * _qp_product_id) then gets the post's image. Nothing changes when both already have one.
 */
function monospace_ensure_featured_image( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || $post->post_type !== 'post' || in_array( $post->post_status, [ 'auto-draft', 'trash' ], true ) ) {
		return;
	}

	if ( ! has_post_thumbnail( $post_id ) && $post->post_content !== '' ) {
		$image_id = extract_first_image_id_from_content( $post->post_content );
		if ( $image_id ) {
			set_post_thumbnail( $post_id, $image_id );
		}
	}

	$thumb_id   = (int) get_post_thumbnail_id( $post_id );
	$product_id = (int) get_post_meta( $post_id, '_qp_product_id', true );
	if ( $thumb_id && $product_id && function_exists( 'wc_get_product' ) ) {
		$product = wc_get_product( $product_id );
		if ( $product && ! $product->get_image_id() ) {
			$product->set_image_id( $thumb_id );
			$product->save();
		}
	}
}

/**
 * Extract the first image ID from post content
 * Tries multiple methods to find images
 */
function extract_first_image_id_from_content( $content ) {
	$image_id = 0;
	
	// Method 1: Look for wp-image-XXX class in img tags (most reliable)
	if ( preg_match( '/wp-image-(\d+)/', $content, $matches ) ) {
		$image_id = intval( $matches[1] );
	}
	
	// Method 2: Look for attachment IDs in Gutenberg image blocks
	if ( ! $image_id && preg_match( '/<!-- wp:image {"id":(\d+)/', $content, $matches ) ) {
		$image_id = intval( $matches[1] );
	}
	
	// Method 3: Extract image URL and find attachment by URL
	if ( ! $image_id && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $content, $matches ) ) {
		$image_url = $matches[1];
		$image_id = attachment_url_to_postid( $image_url );
	}
	
	// Verify it's actually an image attachment
	if ( $image_id && ! wp_attachment_is_image( $image_id ) ) {
		return 0;
	}
	
	return $image_id;
}

/**
 * Remove featured image from post content to prevent duplicates
 * This filters the content output on single post pages
 */
//add_filter( 'the_content', 'remove_featured_image_from_content' );
function remove_featured_image_from_content( $content ) {
	// Only run on single post pages
	if ( ! is_single() || get_post_type() !== 'post' ) {
		return $content;
	}
	
	// Get the featured image ID
	$thumbnail_id = get_post_thumbnail_id();
	
	if ( ! $thumbnail_id ) {
		return $content;
	}
	
	// Method 1: Remove Gutenberg image blocks with this specific image ID
	$content = preg_replace(
		'/<!-- wp:image {"id":' . $thumbnail_id . '[^}]*}[^>]*-->.*?<!-- \/wp:image -->/s',
		'',
		$content
	);
	
	// Method 2: Remove img tags with wp-image-XXX class
	$content = preg_replace(
		'/<figure[^>]*class="[^"]*wp-image-' . $thumbnail_id . '[^"]*"[^>]*>.*?<\/figure>/s',
		'',
		$content
	);
	
	// Method 3: Remove standalone img tags with the featured image URL
	$thumbnail_url = wp_get_attachment_url( $thumbnail_id );
	if ( $thumbnail_url ) {
		$content = preg_replace(
			'/<img[^>]*src=["\']' . preg_quote( $thumbnail_url, '/' ) . '["\'][^>]*>/i',
			'',
			$content
		);
	}
	
	// Clean up any empty paragraphs or whitespace left behind
	$content = preg_replace( '/<p>(\s|&nbsp;)*<\/p>/', '', $content );
	$content = preg_replace( '/^\s+$/m', '', $content );
	
	return $content;
}

/**
 * Hide featured image on archive pages (category, tag, blog index, etc.)
 * Uncomment this function if you want to hide featured images on archive pages
 */
// add_filter( 'post_thumbnail_html', 'hide_featured_image_on_archives', 10, 5 );
function hide_featured_image_on_archives( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
	// Hide on all archive pages (category, tag, date, author, search, blog index)
	if ( is_archive() || is_home() || is_search() ) {
		return '';
	}
	
	return $html;
}

/**
 * Alternative: Only show featured image excerpt on archives, not full image
 * Uncomment this if you prefer a smaller version on archives instead of hiding completely
 */
// add_filter( 'post_thumbnail_size', 'use_thumbnail_size_on_archives', 10, 2 );
function use_thumbnail_size_on_archives( $size, $post_id ) {
	if ( is_archive() || is_home() || is_search() ) {
		return 'thumbnail'; // or 'medium', 'small', etc.
	}
	
	return $size;
}
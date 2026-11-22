<?php
/**
 * Alt text in site search
 *
 * Adds the alt text of an artwork's images to what Relevanssi indexes for that post (and its product), so a search
 * for "barn" or "silhouette" also finds artworks whose pictures are described that way.
 *
 * WHAT IS INDEXED: the featured image, a product's gallery images, and any image placed in the post content
 * (the "wp-image-<ID>" class WordPress puts on them). Capped at 20 images per post.
 *
 * Relevanssi only reads this when a post is indexed (on save, or a re-index), so after bulk alt-text changes
 * the posts need re-indexing.
 *
 * REQUIREMENTS: Relevanssi search plugin active (the filter is simply never called without it).
 */

add_filter( 'relevanssi_content_to_index', function ( $content, $post ) {
	if ( ! $post ) {
		return $content;
	}

	$ids = array();
	$thumb = get_post_thumbnail_id( $post->ID );
	if ( $thumb ) {
		$ids[] = (int) $thumb;
	}
	if ( 'product' === $post->post_type ) {
		$gallery = get_post_meta( $post->ID, '_product_image_gallery', true );
		if ( $gallery ) {
			$ids = array_merge( $ids, array_map( 'intval', explode( ',', $gallery ) ) );
		}
	}
	if ( preg_match_all( '/wp-image-(\d+)/', (string) $post->post_content, $m ) ) {
		$ids = array_merge( $ids, array_map( 'intval', $m[1] ) );
	}

	$alts = array();
	foreach ( array_slice( array_unique( array_filter( $ids ) ), 0, 20 ) as $id ) {
		$alt = trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) );
		if ( '' !== $alt ) {
			$alts[] = $alt;
		}
	}

	return $alts ? trim( $content . ' ' . implode( ' ', $alts ) ) : $content;
}, 10, 2 );

<?php
/**
 * Related posts (Kit part since 2026-11-23; from the art site's theme, includes/modules/frontend/content/related-posts-shortcode.php -
 * the art site keeps its own (artwork items, Look editor): it lists this part as "own". Change art first, then copy what applies).
 *
 * Under every post: a few other posts that share its tags, newest-shared first, as pictures with their titles (grid, or a stack with
 * the picture on the left). Automatic after the post's content; or put [related_posts] where you want it (then it is not added
 * twice). While this part is on, Jetpack's own Related Posts stay out of the way (no setting of Jetpack is changed).
 *
 * Settings (this site's settings file, mu-plugins/monospace-kit-config.php, key 'related'; the shortcode takes the same as attributes):
 *   'related' => [ 'heading' => 'Related', 'limit' => 4, 'min_tags' => 1, 'fill' => 'no', 'layout' => 'grid', 'columns' => 4,
 *                  'title' => 'yes', 'square' => 'yes', 'auto' => 'yes', 'exclude_categories' => [ 'blog' ] ]
 *   fill = pad with random posts when too few share enough tags; auto = add it after a post's content by itself.
 * Colors, rounding and shadow follow the monospace theme's custom properties (--ms-*) when present, else the page's own.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_kit_related_defaults() {
	return [ 'heading' => 'Related', 'limit' => 4, 'min_tags' => 1, 'fill' => 'no', 'layout' => 'grid', 'columns' => 4,
		'title' => 'yes', 'square' => 'yes', 'auto' => 'yes', 'exclude_categories' => [] ];
}

/** Defaults, then the site's settings, then the shortcode's attributes - cleaned */
function monospace_kit_related_settings( $atts = [] ) {
	$cfg = (array) ( monospace_kit_settings()['related'] ?? [] );
	$out = array_merge( monospace_kit_related_defaults(), array_intersect_key( $cfg, monospace_kit_related_defaults() ) );
	foreach ( (array) $atts as $k => $v ) if ( array_key_exists( $k, $out ) ) $out[ $k ] = $v;
	$out['heading'] = mb_substr( sanitize_text_field( (string) $out['heading'] ), 0, 120 );
	$out['limit']    = max( 1, min( 24, (int) $out['limit'] ) );
	$out['min_tags'] = max( 0, min( 20, (int) $out['min_tags'] ) );
	$out['columns']  = max( 1, min( 6, (int) $out['columns'] ) );
	foreach ( [ 'fill', 'title', 'square', 'auto' ] as $k ) $out[ $k ] = strtolower( (string) $out[ $k ] ) === 'yes' ? 'yes' : 'no';
	$out['layout']   = $out['layout'] === 'stack' ? 'stack' : 'grid';
	$out['exclude_categories'] = array_filter( array_map( 'sanitize_title', is_array( $out['exclude_categories'] ) ? $out['exclude_categories'] : explode( ',', (string) $out['exclude_categories'] ) ) );
	return $out;
}

/** IDs of the related posts: most shared tags first (ties random), then random ones when "fill" is on */
function monospace_kit_related_ids( $post_id, $s ) {
	global $wpdb;
	$exclude = [];
	foreach ( $s['exclude_categories'] as $slug ) { $c = get_category_by_slug( $slug ); if ( $c ) $exclude[] = (int) $c->term_id; }
	$tags = array_map( 'absint', wp_get_post_terms( $post_id, 'post_tag', [ 'fields' => 'ids' ] ) );
	$ids  = [];
	if ( $tags ) {
		$sql = "SELECT p.ID FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE p.post_type = 'post' AND p.post_status = 'publish' AND p.ID != %d AND tt.taxonomy = 'post_tag' AND tt.term_id IN (" . implode( ',', $tags ) . ')';
		if ( $exclude ) $sql .= " AND p.ID NOT IN ( SELECT tr2.object_id FROM {$wpdb->term_relationships} tr2 INNER JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id WHERE tt2.taxonomy = 'category' AND tt2.term_id IN (" . implode( ',', $exclude ) . ') )';
		$sql .= ' GROUP BY p.ID HAVING COUNT(tr.term_taxonomy_id) >= %d ORDER BY COUNT(tr.term_taxonomy_id) DESC, RAND() LIMIT %d';
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( $sql, $post_id, max( 1, $s['min_tags'] ), $s['limit'] ) ) );
	}
	$missing = $s['limit'] - count( $ids );
	if ( $missing > 0 && $s['fill'] === 'yes' ) {
		$q = new WP_Query( [ 'post_type' => 'post', 'posts_per_page' => $missing, 'post__not_in' => array_merge( [ $post_id ], $ids ), 'orderby' => 'rand', 'fields' => 'ids',
			'no_found_rows' => true, 'category__not_in' => $exclude ] );
		$ids = array_merge( $ids, array_map( 'intval', $q->posts ) );
	}
	return array_slice( $ids, 0, $s['limit'] );
}

/** The post's picture: its featured image, else the first picture in its content, else none */
function monospace_kit_related_thumb( $id, $title ) {
	if ( has_post_thumbnail( $id ) ) return get_the_post_thumbnail( $id, 'medium', [ 'class' => 'monospace-related-img nolightbox', 'loading' => 'lazy' ] );
	if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', (string) get_post_field( 'post_content', $id ), $m ) ) {
		if ( wp_parse_url( $m[1], PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ) $m[1] = set_url_scheme( $m[1] ); // this site's own picture: the page's scheme (no mixed content)
		return '<img src="' . esc_url( $m[1] ) . '" alt="' . esc_attr( $title ) . '" class="monospace-related-img nolightbox" loading="lazy">';
	}
	return '';
}

function monospace_kit_related_html( $post_id, $s ) {
	$ids = monospace_kit_related_ids( $post_id, $s );
	if ( ! $ids ) return '';
	static $styled = false;
	$cols = max( 1, min( $s['columns'], count( $ids ) ) );
	$out  = '';
	if ( ! $styled ) {
		$styled = true;
		$out .= '<style>.monospace-related-posts{margin:3em 0 1em;clear:both}.monospace-related-heading{margin:0 0 1em;font-size:1em;font-weight:600;color:var(--ms-heading,inherit)}'
			. '.monospace-related-grid{display:grid;grid-template-columns:repeat(var(--rel-cols,4),minmax(0,1fr));gap:20px}@media(max-width:920px){.monospace-related-grid{grid-template-columns:repeat(min(var(--rel-cols,4),3),minmax(0,1fr))}}@media(max-width:544px){.monospace-related-grid{grid-template-columns:repeat(min(var(--rel-cols,4),2),minmax(0,1fr))}}'
			. '.monospace-related-link{display:block;color:inherit;text-decoration:none}.monospace-related-thumb{display:block;position:relative;overflow:hidden;border-radius:var(--ms-img-radius,5px);box-shadow:0 0 8px rgba(0,0,0,.3);background:rgba(128,128,128,.2)}'
			. '.monospace-related-posts:not(.is-natural) .monospace-related-thumb{padding-bottom:100%}.monospace-related-posts:not(.is-natural) .monospace-related-img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.monospace-related-img{display:block;width:100%;height:auto}'
			. '.monospace-related-title{display:block;margin-top:.75em;font-size:.85em;font-weight:500;line-height:1.4}'
			. '.monospace-related-posts.is-stack .monospace-related-link{display:grid;grid-template-columns:minmax(0,42%) 1fr;column-gap:12px;align-items:start}.monospace-related-posts.is-stack .monospace-related-title{margin-top:0}</style>';
	}
	$out .= '<div class="monospace-related-posts' . ( $s['layout'] === 'stack' ? ' is-stack' : '' ) . ( $s['square'] === 'no' ? ' is-natural' : '' ) . '">';
	if ( $s['heading'] !== '' ) $out .= '<h3 class="monospace-related-heading">' . esc_html( $s['heading'] ) . '</h3>';
	$out .= '<div class="monospace-related-grid" style="--rel-cols:' . $cols . '">';
	foreach ( $ids as $id ) {
		$title = get_the_title( $id );
		$thumb = monospace_kit_related_thumb( $id, $title );
		$out  .= '<div class="monospace-related-item"><a class="monospace-related-link" href="' . esc_url( get_permalink( $id ) ) . '">';
		if ( $thumb ) $out .= '<span class="monospace-related-thumb">' . $thumb . '</span>';
		if ( $s['title'] === 'yes' || ! $thumb ) $out .= '<span class="monospace-related-title">' . esc_html( $title ) . '</span>';
		$out  .= '</a></div>';
	}
	return $out . '</div></div>';
}

add_shortcode( 'related_posts', function ( $atts ) {
	$post = get_post();
	if ( ! $post || ! is_singular( 'post' ) ) return '';
	return monospace_kit_related_html( $post->ID, monospace_kit_related_settings( $atts ) );
} );

// Automatic: after the content of a post (not when the post has the shortcode itself)
add_filter( 'the_content', function ( $content ) {
	if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() || has_shortcode( $content, 'related_posts' ) ) return $content;
	$s = monospace_kit_related_settings();
	return $s['auto'] === 'yes' ? $content . monospace_kit_related_html( get_the_ID(), $s ) : $content;
}, 30 );

// Jetpack's own related posts step aside while this part is on
add_filter( 'jetpack_relatedposts_filter_enabled_for_request', '__return_false' );

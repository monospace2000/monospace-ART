<?php
/**
 * Used in (Kit part since 2026-11-23): where each media file is used, and how often. WordPress's "Uploaded to" only says where a
 * file was first uploaded from. The general version of the art site's media-usage.php (monospace-admin-tools), which also knows
 * the art site's print files, home page boxes and its "unused" smart folder; the art site keeps its own.
 *
 * Shown as the Media Library list view's **Used in** column (sortable by number of places, filter Used / Not used), in the grid
 * view's filter bar (same filter) and in the attachment details (pop-up and edit screen). "Rebuild usage" next to the filters.
 *
 * A use is found in: the featured image (any post type), WooCommerce product galleries, the text (image / cover / media-text /
 * gallery / video / audio / file blocks, wp-image-N classes, [gallery ids], any /wp-content/uploads/ address; resized and
 * -scaled copies count for their original), patterns, templates and menus, the site icon, the logo, widgets, and the theme's
 * own code (an uploads address in its PHP / CSS / JS; found by the rebuild only). Real Media Library shortcuts are left out.
 *
 * A site adds its own places with filters (in its settings file or its own code):
 *   monospace_kit_usage_types   [ post type, ... ]                     more post types whose pictures count (e.g. color's 'bafg')
 *   monospace_kit_usage_meta    [ post meta key => how ]               an ID, a list of IDs, or any stored value naming pictures
 *   monospace_kit_usage_options [ option name => label ]               options holding a picture (ID or HTML / address)
 *   monospace_kit_usage_dirs    [ folder, ... ]                        more code folders to search (default: the theme)
 *
 * Stored, so the screens only read: attachment meta _ms_used_in = [ source => [ how, ... ] ] (source "p:<post ID>", "o:<option>",
 * "t:theme") and _ms_used_count; each post keeps _ms_media_uses. Kept current on save / trash / delete and on option changes;
 * a full rebuild runs nightly (WP-Cron) and from "Rebuild usage". The same meta names as the art site's, on purpose.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_KIT_USAGE_HOOK = 'monospace_kit_usage_rebuild';

function monospace_kit_usage_types() {
	return array_values( array_unique( array_merge( [ 'post', 'page', 'product', 'product_variation', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' ], (array) apply_filters( 'monospace_kit_usage_types', [] ) ) ) );
}

function monospace_kit_usage_meta_keys() {
	return array_merge( [ '_thumbnail_id' => 'featured image', '_product_image_gallery' => 'product gallery' ], (array) apply_filters( 'monospace_kit_usage_meta', [] ) );
}

/* ---------- finding uses ---------- */

function monospace_kit_usage_maps( $reset = false ) {
	static $maps = null;
	if ( $maps && ! $reset ) return $maps;
	global $wpdb;
	$ids = []; $paths = [];
	foreach ( $wpdb->get_results( "SELECT p.ID, m.meta_value FROM $wpdb->posts p LEFT JOIN $wpdb->postmeta m ON m.post_id = p.ID AND m.meta_key = '_wp_attached_file' WHERE p.post_type = 'attachment' AND p.guid NOT LIKE '%?sc=%'" ) as $r ) {
		$ids[ (int) $r->ID ] = true;
		if ( $r->meta_value ) $paths[ monospace_kit_usage_norm( $r->meta_value ) ] = (int) $r->ID;
	}
	return $maps = [ 'ids' => $ids, 'paths' => $paths ];
}

function monospace_kit_usage_norm( $path ) {
	return preg_replace( '/(-\d+x\d+|-scaled|-rotated)+(\.[a-z0-9]+)$/', '$2', strtolower( rawurldecode( $path ) ) );
}

function monospace_kit_usage_in_html( $html ) {
	$found = [];
	if ( ! is_string( $html ) || $html === '' ) return $found;
	if ( strpos( $html, '<!-- wp:' ) !== false ) {
		$walk = function ( $blocks ) use ( &$walk, &$found ) {
			foreach ( $blocks as $b ) {
				$a = $b['attrs'] ?? [];
				switch ( $b['blockName'] ) {
					case 'core/image': case 'core/cover': case 'core/video': case 'core/audio': case 'core/file':
						if ( ! empty( $a['id'] ) ) $found[] = (int) $a['id'];
						break;
					case 'core/media-text':
						if ( ! empty( $a['mediaId'] ) ) $found[] = (int) $a['mediaId'];
						break;
					case 'core/gallery':
						foreach ( (array) ( $a['ids'] ?? [] ) as $id ) $found[] = (int) $id;
						break;
				}
				if ( ! empty( $b['innerBlocks'] ) ) $walk( $b['innerBlocks'] );
			}
		};
		$walk( parse_blocks( $html ) );
	}
	if ( preg_match_all( '/wp-image-(\d+)/', $html, $m ) ) foreach ( $m[1] as $id ) $found[] = (int) $id;
	if ( preg_match_all( '/\[gallery[^\]]*\bids="([\d,\s]+)"/', $html, $m ) ) foreach ( $m[1] as $list ) foreach ( explode( ',', $list ) as $id ) $found[] = (int) $id;
	if ( preg_match_all( '#/wp-content/uploads/([^"\'\s\)\?\#<>]+\.[a-z0-9]{2,5})#i', $html, $m ) ) {
		$paths = monospace_kit_usage_maps()['paths'];
		foreach ( $m[1] as $p ) { $p = monospace_kit_usage_norm( str_replace( '\\/', '/', $p ) ); if ( isset( $paths[ $p ] ) ) $found[] = $paths[ $p ]; }
	}
	return array_values( array_unique( array_filter( $found ) ) );
}

function monospace_kit_usage_only_files( $uses ) {
	$ids = monospace_kit_usage_maps()['ids'];
	$out = [];
	foreach ( $uses as $id => $how ) if ( isset( $ids[ $id ] ) ) $out[ $id ] = array_values( array_unique( $how ) );
	return $out;
}

function monospace_kit_usage_scan_post( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || ! in_array( $post->post_type, monospace_kit_usage_types(), true ) || in_array( $post->post_status, [ 'trash', 'auto-draft', 'inherit' ], true ) ) return [];
	$uses = [];
	foreach ( monospace_kit_usage_meta_keys() as $key => $how ) {
		if ( $key === '_thumbnail_id' && $post->post_type === 'product_variation' ) $how = 'variation image';
		$value = get_post_meta( $post->ID, $key, true );
		if ( is_scalar( $value ) && preg_match( '/^[\d,\s]*$/', (string) $value ) ) {
			// an ID or a list of IDs
			foreach ( array_filter( explode( ',', (string) $value ) ) as $id ) if ( (int) $id ) $uses[ (int) $id ][] = $how;
		} elseif ( $value ) {
			// anything else (a stored array, HTML, an address): the pictures it names
			foreach ( monospace_kit_usage_in_html( is_string( $value ) ? $value : (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) as $id ) $uses[ $id ][] = $how;
		}
	}
	foreach ( monospace_kit_usage_in_html( $post->post_content . "\n" . $post->post_excerpt ) as $id ) $uses[ $id ][] = 'in the text';
	return monospace_kit_usage_only_files( $uses );
}

/** Options that can hold a picture: [ name => label ] */
function monospace_kit_usage_options() {
	global $wpdb;
	$names = [ 'site_icon' => 'Site icon', 'theme_mods_' . get_stylesheet() => 'Logo / theme settings' ];
	foreach ( $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'widget\_%' AND ( option_value LIKE '%wp-content/uploads%' OR option_value LIKE '%wp-image-%' )" ) as $n ) $names[ $n ] = 'Widgets';
	return array_merge( $names, (array) apply_filters( 'monospace_kit_usage_options', [] ) );
}

function monospace_kit_usage_scan_option( $name ) {
	$value = get_option( $name );
	$uses  = [];
	if ( $name === 'site_icon' ) {
		if ( (int) $value ) $uses[ (int) $value ][] = 'site icon';
	} elseif ( $name === 'theme_mods_' . get_stylesheet() ) {
		if ( ! empty( $value['custom_logo'] ) ) $uses[ (int) $value['custom_logo'] ][] = 'logo';
		if ( ! empty( $value['header_image_data']->attachment_id ) ) $uses[ (int) $value['header_image_data']->attachment_id ][] = 'header picture';
		foreach ( monospace_kit_usage_in_html( (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) as $id ) $uses[ $id ][] = 'theme setting';
	} elseif ( is_numeric( $value ) ) {
		$uses[ (int) $value ][] = 'setting';
	} else {
		foreach ( monospace_kit_usage_in_html( is_string( $value ) ? $value : (string) wp_json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) as $id ) $uses[ $id ][] = strpos( $name, 'widget_' ) === 0 ? 'widget' : 'setting';
	}
	return monospace_kit_usage_only_files( $uses );
}

/* ---------- storing ---------- */

function monospace_kit_usage_store( $source, $uses, $old ) {
	foreach ( array_unique( array_merge( array_map( 'intval', (array) $old ), array_keys( $uses ) ) ) as $att ) {
		if ( get_post_type( $att ) !== 'attachment' ) continue;
		$map = get_post_meta( $att, '_ms_used_in', true );
		$map = is_array( $map ) ? $map : [];
		if ( isset( $uses[ $att ] ) ) $map[ $source ] = $uses[ $att ]; else unset( $map[ $source ] );
		update_post_meta( $att, '_ms_used_in', $map );
		update_post_meta( $att, '_ms_used_count', count( $map ) );
	}
}

function monospace_kit_usage_update_post( $post_id ) {
	$old  = (array) get_post_meta( $post_id, '_ms_media_uses', true );
	$uses = monospace_kit_usage_scan_post( $post_id );
	monospace_kit_usage_store( 'p:' . $post_id, $uses, $old );
	if ( get_post( $post_id ) ) update_post_meta( $post_id, '_ms_media_uses', array_keys( $uses ) );
}

function monospace_kit_usage_update_option( $name ) {
	$all  = (array) get_option( 'monospace_kit_usage_options', [] );
	$uses = get_option( $name, null ) === null ? [] : monospace_kit_usage_scan_option( $name );
	monospace_kit_usage_store( 'o:' . $name, $uses, $all[ $name ] ?? [] );
	if ( $uses ) $all[ $name ] = array_keys( $uses ); else unset( $all[ $name ] );
	update_option( 'monospace_kit_usage_options', $all, false );
}

/** Everything from scratch. Returns [ files, used, not used ]. */
function monospace_kit_usage_rebuild() {
	global $wpdb;
	@set_time_limit( 300 );
	monospace_kit_usage_maps( true );
	$map = []; $per_post = [];
	$types = "'" . implode( "','", array_map( 'esc_sql', monospace_kit_usage_types() ) ) . "'";
	foreach ( $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type IN ($types) AND post_status NOT IN ('trash','auto-draft','inherit')" ) as $pid ) {
		$uses = monospace_kit_usage_scan_post( (int) $pid );
		$per_post[ (int) $pid ] = array_keys( $uses );
		foreach ( $uses as $att => $how ) $map[ $att ][ 'p:' . $pid ] = $how;
	}
	$per_option = [];
	foreach ( array_keys( monospace_kit_usage_options() ) as $name ) {
		$uses = monospace_kit_usage_scan_option( $name );
		if ( $uses ) $per_option[ $name ] = array_keys( $uses );
		foreach ( $uses as $att => $how ) $map[ $att ][ 'o:' . $name ] = $how;
	}
	// The theme's own code (and any folders a site adds)
	$code = '';
	foreach ( array_unique( array_merge( [ get_stylesheet_directory(), get_template_directory() ], (array) apply_filters( 'monospace_kit_usage_dirs', [] ) ) ) as $dir ) {
		if ( ! is_dir( $dir ) ) continue;
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
			if ( preg_match( '/\.(php|s?css|js)$/', $f->getFilename() ) && ! preg_match( '#/(node_modules|vendor)/#', $f->getPathname() ) ) $code .= file_get_contents( $f->getPathname() ) . "\n";
		}
	}
	if ( preg_match_all( '#/wp-content/uploads/([^"\'\s\)\?\#<>]+\.[a-z0-9]{2,5})#i', $code, $m ) ) {
		$paths = monospace_kit_usage_maps()['paths'];
		foreach ( $m[1] as $p ) { $p = monospace_kit_usage_norm( $p ); if ( isset( $paths[ $p ] ) ) $map[ $paths[ $p ] ]['t:theme'] = [ 'site design' ]; }
	}

	$wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key IN ('_ms_used_in','_ms_used_count','_ms_media_uses')" );
	foreach ( array_keys( monospace_kit_usage_maps()['ids'] ) as $att ) {
		add_post_meta( $att, '_ms_used_in', $map[ $att ] ?? [], true );
		add_post_meta( $att, '_ms_used_count', count( $map[ $att ] ?? [] ), true );
	}
	foreach ( $per_post as $pid => $ids ) if ( $ids ) add_post_meta( $pid, '_ms_media_uses', $ids, true );
	update_option( 'monospace_kit_usage_options', $per_option, false );
	update_option( 'monospace_kit_usage_built', time(), false );
	wp_cache_flush();
	$total = count( monospace_kit_usage_maps()['ids'] );
	return [ $total, count( $map ), $total - count( $map ) ];
}

/* ---------- keeping it current (queued, done once at the end of the request) ---------- */

function monospace_kit_usage_queue( $kind, $key ) {
	static $hooked = false;
	$GLOBALS['monospace_kit_usage_queue'][ $kind ][ $key ] = true;
	if ( $hooked ) return;
	$hooked = true;
	add_action( 'shutdown', function () {
		$q = $GLOBALS['monospace_kit_usage_queue'] ?? [];
		$GLOBALS['monospace_kit_usage_queue'] = [];
		foreach ( array_keys( $q['post'] ?? [] ) as $id ) monospace_kit_usage_update_post( $id );
		foreach ( array_keys( $q['option'] ?? [] ) as $name ) monospace_kit_usage_update_option( $name );
	}, 5 );
}

add_action( 'save_post', function ( $post_id, $post ) {
	if ( in_array( $post->post_type, monospace_kit_usage_types(), true ) ) monospace_kit_usage_queue( 'post', (int) $post_id );
}, 99, 2 );
foreach ( [ 'trashed_post', 'untrashed_post' ] as $hook ) {
	add_action( $hook, function ( $post_id ) { monospace_kit_usage_queue( 'post', (int) $post_id ); } );
}
add_action( 'before_delete_post', function ( $post_id ) {
	if ( get_post_type( $post_id ) === 'attachment' ) return;
	$old = (array) get_post_meta( $post_id, '_ms_media_uses', true );
	if ( $old ) monospace_kit_usage_store( 'p:' . $post_id, [], $old );
} );
foreach ( [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ] as $hook ) {
	add_action( $hook, function ( $meta_id, $object_id, $key ) {
		if ( isset( monospace_kit_usage_meta_keys()[ $key ] ) ) monospace_kit_usage_queue( 'post', (int) $object_id );
	}, 10, 3 );
}
foreach ( [ 'added_option', 'updated_option', 'deleted_option' ] as $hook ) {
	add_action( $hook, function ( $name ) {
		if ( strpos( $name, '_transient' ) !== false || strpos( $name, 'monospace_kit_usage' ) === 0 ) return;
		if ( in_array( $name, [ 'site_icon', 'theme_mods_' . get_stylesheet() ], true ) || strpos( $name, 'widget_' ) === 0 || isset( ( (array) apply_filters( 'monospace_kit_usage_options', [] ) )[ $name ] ) ) monospace_kit_usage_queue( 'option', $name );
	} );
}
add_action( 'add_attachment', function ( $id ) {
	if ( function_exists( 'wp_attachment_is_shortcut' ) && wp_attachment_is_shortcut( $id ) ) return;
	add_post_meta( $id, '_ms_used_in', [], true );
	add_post_meta( $id, '_ms_used_count', 0, true );
} );

// Nightly rebuild (WP-Cron: every site has it; Action Scheduler only comes with WooCommerce)
add_action( MONOSPACE_KIT_USAGE_HOOK, 'monospace_kit_usage_rebuild' );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( MONOSPACE_KIT_USAGE_HOOK ) ) wp_schedule_event( strtotime( 'tomorrow 04:30' ), 'daily', MONOSPACE_KIT_USAGE_HOOK );
} );
add_action( 'admin_post_monospace_kit_usage_rebuild', function () {
	if ( ! current_user_can( 'upload_files' ) ) wp_die( 'Not allowed' );
	check_admin_referer( 'monospace_kit_usage_rebuild' );
	[ $total, $used, $unused ] = monospace_kit_usage_rebuild();
	wp_safe_redirect( add_query_arg( [ 'mode' => 'list', 'ms_usage_rebuilt' => "$used-$unused" ], admin_url( 'upload.php' ) ) );
	exit;
} );

/* ---------- showing it ---------- */

function monospace_kit_usage_lines( $att ) {
	$map     = get_post_meta( $att, '_ms_used_in', true );
	$options = null;
	$lines   = [];
	foreach ( is_array( $map ) ? $map : [] as $source => $how ) {
		[ $kind, $key ] = explode( ':', $source, 2 ) + [ '', '' ];
		if ( $kind === 'p' ) {
			$post = get_post( (int) $key );
			if ( ! $post ) continue;
			$target = $post->post_type === 'product_variation' ? get_post( $post->post_parent ) : $post;
			$type   = [ 'post' => 'post', 'page' => 'page', 'product' => 'product', 'product_variation' => 'product variation', 'wp_block' => 'pattern', 'wp_template' => 'template', 'wp_template_part' => 'template part', 'wp_navigation' => 'menu' ][ $post->post_type ] ?? strtolower( get_post_type_object( $post->post_type )->labels->singular_name ?? $post->post_type );
			$title  = $target ? ( get_the_title( $target ) ?: '(no title)' ) : '(no title)';
			$link   = $target ? get_edit_post_link( $target->ID ) : '';
			$status = $post->post_status !== 'publish' ? ', ' . $post->post_status : '';
			$label  = ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . ' <span class="ms-usage-type">(' . esc_html( $type . $status ) . ')</span>';
		} elseif ( $kind === 't' ) {
			$label = 'The theme';
		} else {
			if ( $options === null ) $options = monospace_kit_usage_options();
			$label = esc_html( $options[ $key ] ?? ( strpos( $key, 'widget_' ) === 0 ? 'Widgets' : 'Setting ' . $key ) );
		}
		$lines[] = $label . ' - ' . esc_html( implode( ', ', (array) $how ) );
	}
	return $lines;
}

function monospace_kit_usage_html( $att, $limit = 0 ) {
	if ( function_exists( 'wp_attachment_is_shortcut' ) && ( $source = wp_attachment_is_shortcut( $att, true ) ) ) return '<span class="ms-usage-type">Shortcut - the file:</span> ' . monospace_kit_usage_html( $source, $limit );
	if ( get_post_meta( $att, '_ms_used_count', true ) === '' ) return '<span class="ms-usage-none">not checked yet</span>';
	$lines = monospace_kit_usage_lines( $att );
	$n     = count( $lines );
	if ( ! $n ) return '<span class="ms-usage-none">not used</span>';
	$out = '<strong>' . $n . ' ' . ( $n === 1 ? 'place' : 'places' ) . '</strong><ul class="ms-usage-list"><li>' . implode( '</li><li>', $limit ? array_slice( $lines, 0, $limit ) : $lines ) . '</li></ul>';
	if ( $limit && $n > $limit ) $out .= '<span class="ms-usage-more">+ ' . ( $n - $limit ) . ' more (open the file)</span>';
	return $out;
}

add_filter( 'manage_media_columns', function ( $cols ) {
	$out = [];
	foreach ( $cols as $k => $v ) { $out[ $k ] = $v; if ( $k === 'parent' ) $out['ms_used_in'] = 'Used in'; }
	if ( ! isset( $out['ms_used_in'] ) ) $out['ms_used_in'] = 'Used in';
	return $out;
} );
add_action( 'manage_media_custom_column', function ( $col, $id ) {
	if ( $col === 'ms_used_in' ) echo monospace_kit_usage_html( $id, 4 );
}, 10, 2 );
add_filter( 'manage_upload_sortable_columns', function ( $cols ) { $cols['ms_used_in'] = 'ms_used'; return $cols; } );

function monospace_kit_usage_clause( $used ) {
	return [ 'key' => '_ms_used_count', 'value' => 0, 'compare' => $used === 'unused' ? '=' : '>', 'type' => 'NUMERIC' ];
}
add_action( 'restrict_manage_posts', function ( $type ) {
	if ( $type !== 'attachment' ) return;
	$cur = sanitize_key( $_GET['ms_used'] ?? '' );
	echo '<select name="ms_used"><option value="">Used and not used</option><option value="used"' . selected( $cur, 'used', false ) . '>Used</option><option value="unused"' . selected( $cur, 'unused', false ) . '>Not used</option></select>';
} );
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || ( $GLOBALS['pagenow'] ?? '' ) !== 'upload.php' ) return;
	$used = sanitize_key( $_GET['ms_used'] ?? '' );
	if ( in_array( $used, [ 'used', 'unused' ], true ) ) $q->set( 'meta_query', array_merge( (array) $q->get( 'meta_query' ), [ monospace_kit_usage_clause( $used ) ] ) );
	if ( $q->get( 'orderby' ) === 'ms_used' ) { $q->set( 'meta_key', '_ms_used_count' ); $q->set( 'orderby', 'meta_value_num' ); }
} );
add_filter( 'ajax_query_attachments_args', function ( $args ) {
	$used = sanitize_key( $_REQUEST['query']['ms_used'] ?? '' );
	if ( in_array( $used, [ 'used', 'unused' ], true ) ) $args['meta_query'] = array_merge( (array) ( $args['meta_query'] ?? [] ), [ monospace_kit_usage_clause( $used ) ] );
	return $args;
} );
add_filter( 'attachment_fields_to_edit', function ( $fields, $post ) {
	$fields['ms_used_in'] = [ 'label' => 'Used in', 'input' => 'html', 'html' => '<div class="ms-usage-field">' . monospace_kit_usage_html( $post->ID ) . '</div>' ];
	return $fields;
}, 10, 2 );

add_action( 'admin_footer-upload.php', function () {
	$built   = (int) get_option( 'monospace_kit_usage_built', 0 );
	$rebuild = wp_nonce_url( admin_url( 'admin-post.php?action=monospace_kit_usage_rebuild' ), 'monospace_kit_usage_rebuild' );
	?>
	<style>
		.column-ms_used_in { width: 22%; }
		.ms-usage-list { margin: 2px 0 0; } .ms-usage-list li { margin: 0 0 2px; }
		.ms-usage-type, .ms-usage-more { color: #646970; } .ms-usage-none { color: #8c8f94; }
		.ms-usage-field { padding-top: 6px; } .ms-usage-field ul { margin-top: 4px; }
		.ms-usage-note { display: inline-block; margin: 0 0 0 8px; line-height: 30px; color: #646970; }
	</style>
	<script>
	(function () {
		var bar = document.querySelector('.tablenav.top .actions');
		if (!bar) return;
		var note = document.createElement('span');
		note.className = 'ms-usage-note';
		note.innerHTML = 'Usage checked <?php echo esc_js( $built ? human_time_diff( $built ) . ' ago' : 'never' ); ?> · <a href="<?php echo esc_url( $rebuild ); ?>">Rebuild usage</a>';
		bar.appendChild(note);
	})();
	</script>
	<?php
} );
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( $hook !== 'upload.php' ) return;
	wp_enqueue_media();
	wp_add_inline_script( 'media-views', <<<'JS'
(function () {
	if (!window.wp || !wp.media || !wp.media.view || !wp.media.view.AttachmentFilters) return;
	var Filter = wp.media.view.AttachmentFilters.extend({
		id: 'ms-used-filter',
		createFilters: function () {
			this.filters = {
				all:    { text: 'Used and not used', props: { ms_used: '' },       priority: 10 },
				used:   { text: 'Used',              props: { ms_used: 'used' },   priority: 20 },
				unused: { text: 'Not used',          props: { ms_used: 'unused' }, priority: 30 }
			};
		}
	});
	var Browser = wp.media.view.AttachmentsBrowser;
	wp.media.view.AttachmentsBrowser = Browser.extend({
		createToolbar: function () {
			Browser.prototype.createToolbar.apply(this, arguments);
			this.toolbar.set('msUsedFilter', new Filter({ controller: this.controller, model: this.collection.props, priority: -75 }).render());
		}
	});
})();
JS
	);
} );
add_action( 'admin_notices', function () {
	if ( ( $GLOBALS['pagenow'] ?? '' ) !== 'upload.php' || empty( $_GET['ms_usage_rebuilt'] ) ) return;
	[ $used, $unused ] = array_map( 'intval', explode( '-', sanitize_text_field( wp_unslash( $_GET['ms_usage_rebuilt'] ) ) ) + [ 0, 0 ] );
	echo '<div class="notice notice-success is-dismissible"><p>Usage rebuilt: ' . $used . ' files used, ' . $unused . ' not used.</p></div>';
} );

<?php
/**
 * Media usage (2026-10-19, owner's wish: "where a media item is being used, how often" - WordPress's
 * "Uploaded to" only says where a file was first uploaded from).
 *
 * Every media item gets a "Used in" list: the posts, pages, products, patterns and site settings that
 * show it, and how (featured image, product gallery, in the text, print file, home page box, share
 * image, site icon, logo, widget). Shown as the Media Library list view's **Used in** column
 * (sortable by number of places, filter Used / Not used), in the grid view's filter bar (same filter)
 * and in the attachment details (pop-up and edit screen).
 *
 * A use is found in: _thumbnail_id (posts, pages, products, variations), _product_image_gallery,
 * _ms_print_file (the prints tool), the text (blocks' image / cover / media-text / gallery / video /
 * audio / file ids, the Miniature Set block's images, wp-image-N classes, [gallery ids], and any
 * /wp-content/uploads/ URL - resized and -scaled copies count for their original), the home page
 * layout (box backgrounds, own-text boxes), the share image, site icon, logo, any monospace_* /
 * widget_* option holding an image, and the theme's own code (an uploads URL in its SCSS / CSS / PHP /
 * JS - found by the rebuild only, since theme files only change on a deploy).
 *
 * Stored, so the screens only read: attachment meta _ms_used_in = [ source => [ how, … ] ] (source
 * "p:<post ID>", "o:<option name>" or "t:theme") and _ms_used_count (number of places; 0 = not used); each
 * source post keeps _ms_media_uses (its attachment IDs), options in option monospace_media_usage_options.
 * Kept current on save / trash / delete of a post and on option changes (queued, done at shutdown);
 * a full rebuild runs nightly (Action Scheduler) and from "Rebuild usage" in the list view.
 *
 * Smart folder (Real Media Library, 2026-10-19, owner's wish): the folder "unused" (its ID is remembered -
 * renaming it is fine) holds a **shortcut** to every file used nowhere, so the file itself can live in any
 * folder (an unused portrait in "portraits") and still be listed there. Each rebuild (nightly) adds and
 * removes shortcuts; dragging a shortcut out of "unused" into a folder files the file itself there and the
 * shortcut stays. Uploads younger than 14 days aren't listed yet. RML shortcuts are separate attachment
 * posts (guid "…?sc=<file ID>") - they're left out of the usage index everywhere.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_MEDIA_USAGE_TYPES = [ 'post', 'page', 'product', 'product_variation', 'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation' ];
const MONOSPACE_MEDIA_USAGE_HOOK  = 'monospace_media_usage_rebuild';

/* ============================================================
 * FINDING USES
 * ============================================================ */

/** All attachment IDs (as keys), and a map of normalized upload paths to attachment IDs */
function monospace_media_usage_maps( $reset = false ) {
    static $maps = null;
    if ( $maps && ! $reset ) return $maps;
    global $wpdb;
    $ids = []; $paths = [];
    foreach ( $wpdb->get_results( "SELECT p.ID, m.meta_value FROM $wpdb->posts p LEFT JOIN $wpdb->postmeta m ON m.post_id = p.ID AND m.meta_key = '_wp_attached_file' WHERE p.post_type = 'attachment' AND p.guid NOT LIKE '%?sc=%'" ) as $r ) { // RML shortcuts aren't files of their own
        $ids[ (int) $r->ID ] = true;
        if ( $r->meta_value ) $paths[ monospace_media_usage_norm( $r->meta_value ) ] = (int) $r->ID;
    }
    return $maps = [ 'ids' => $ids, 'paths' => $paths ];
}

/** An upload path without WordPress's size / -scaled / -rotated suffixes, lower case */
function monospace_media_usage_norm( $path ) {
    $path = strtolower( rawurldecode( $path ) );
    return preg_replace( '/(-\d+x\d+|-scaled|-rotated)+(\.[a-z0-9]+)$/', '$2', $path );
}

/** Attachment IDs shown by a piece of HTML / block markup */
function monospace_media_usage_in_html( $html ) {
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
                    case 'monospace/miniature-set':
                        foreach ( (array) ( $a['images'] ?? [] ) as $img ) if ( ! empty( $img['id'] ) ) $found[] = (int) $img['id'];
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
        $paths = monospace_media_usage_maps()['paths'];
        foreach ( $m[1] as $p ) { $p = monospace_media_usage_norm( str_replace( '\\/', '/', $p ) ); if ( isset( $paths[ $p ] ) ) $found[] = $paths[ $p ]; }
    }
    return array_values( array_unique( array_filter( $found ) ) );
}

/** What one post / page / product / pattern uses: [ attachment ID => [ how, … ] ] */
function monospace_media_usage_scan_post( $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || ! in_array( $post->post_type, MONOSPACE_MEDIA_USAGE_TYPES, true ) || in_array( $post->post_status, [ 'trash', 'auto-draft', 'inherit' ], true ) ) return [];
    $uses = [];
    $add = function ( $id, $how ) use ( &$uses ) { $id = (int) $id; if ( $id ) $uses[ $id ][] = $how; };
    $add( get_post_meta( $post->ID, '_thumbnail_id', true ), $post->post_type === 'product_variation' ? 'variation image' : 'featured image' );
    foreach ( array_filter( explode( ',', (string) get_post_meta( $post->ID, '_product_image_gallery', true ) ) ) as $id ) $add( $id, 'product gallery' );
    $add( get_post_meta( $post->ID, '_ms_print_file', true ), 'print file' );
    foreach ( monospace_media_usage_in_html( $post->post_content . "\n" . $post->post_excerpt ) as $id ) $add( $id, 'in the text' );
    $ids = monospace_media_usage_maps()['ids'];
    $out = [];
    foreach ( $uses as $id => $how ) if ( isset( $ids[ $id ] ) ) $out[ $id ] = array_values( array_unique( $how ) );
    return $out;
}

/** The options that can hold an image */
function monospace_media_usage_option_names() {
    global $wpdb;
    $names = [ 'site_icon', 'theme_mods_' . get_stylesheet(), 'monospace_home_layout', 'monospace_home_share_image' ];
    foreach ( $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE ( option_name LIKE 'monospace\_%' OR option_name LIKE 'widget\_%' ) AND option_name NOT LIKE 'monospace\_media\_usage%' AND ( option_value LIKE '%wp-content/uploads%' OR option_value LIKE '%wp-image-%' )" ) as $n ) $names[] = $n;
    return array_values( array_unique( $names ) );
}

/** Whether an option is one that can hold an image (for the change hooks) */
function monospace_media_usage_option_matters( $name ) {
    if ( strpos( $name, 'monospace_media_' ) === 0 || strpos( $name, '_transient' ) !== false ) return false;
    return in_array( $name, [ 'site_icon', 'theme_mods_' . get_stylesheet() ], true ) || strpos( $name, 'monospace_' ) === 0 || strpos( $name, 'widget_' ) === 0;
}

/** What one option uses: [ attachment ID => [ how, … ] ] */
function monospace_media_usage_scan_option( $name ) {
    $value = get_option( $name );
    $uses = [];
    $add = function ( $id, $how ) use ( &$uses ) { $id = (int) $id; if ( $id ) $uses[ $id ][] = $how; };
    if ( $name === 'site_icon' ) $add( $value, 'site icon' );
    elseif ( $name === 'theme_mods_' . get_stylesheet() ) $add( $value['custom_logo'] ?? 0, 'logo' );
    elseif ( $name === 'monospace_home_share_image' ) $add( $value, 'share image' );
    elseif ( $name === 'monospace_placeholder_image' ) $add( $value, 'placeholder picture' );
    elseif ( $name === 'monospace_home_layout' ) {
        foreach ( (array) $value as $row ) foreach ( (array) ( $row['boxes'] ?? [] ) as $box ) {
            if ( ( $box['image']['mode'] ?? '' ) === 'media' ) $add( $box['image']['id'] ?? 0, 'box background' );
            if ( ! empty( $box['html'] ) ) foreach ( monospace_media_usage_in_html( $box['html'] ) as $id ) $add( $id, 'box text' );
        }
    } else {
        $text = is_string( $value ) ? $value : wp_json_encode( $value );
        foreach ( monospace_media_usage_in_html( (string) $text ) as $id ) $add( $id, strpos( $name, 'widget_' ) === 0 ? 'widget' : 'setting' );
    }
    $ids = monospace_media_usage_maps()['ids'];
    $out = [];
    foreach ( $uses as $id => $how ) if ( isset( $ids[ $id ] ) ) $out[ $id ] = array_values( array_unique( $how ) );
    return $out;
}

/* ============================================================
 * STORING
 * ============================================================ */

/** Write one source's uses onto the attachments; $old = the attachment IDs it used before */
function monospace_media_usage_store( $source, $uses, $old ) {
    foreach ( array_unique( array_merge( array_map( 'intval', (array) $old ), array_keys( $uses ) ) ) as $att ) {
        if ( get_post_type( $att ) !== 'attachment' ) continue;
        $map = get_post_meta( $att, '_ms_used_in', true );
        $map = is_array( $map ) ? $map : [];
        if ( isset( $uses[ $att ] ) ) $map[ $source ] = $uses[ $att ]; else unset( $map[ $source ] );
        update_post_meta( $att, '_ms_used_in', $map );
        update_post_meta( $att, '_ms_used_count', count( $map ) );
    }
}

function monospace_media_usage_update_post( $post_id ) {
    $old  = (array) get_post_meta( $post_id, '_ms_media_uses', true );
    $uses = monospace_media_usage_scan_post( $post_id );
    monospace_media_usage_store( 'p:' . $post_id, $uses, $old );
    if ( get_post( $post_id ) ) update_post_meta( $post_id, '_ms_media_uses', array_keys( $uses ) );
}

function monospace_media_usage_update_option( $name ) {
    $all  = (array) get_option( 'monospace_media_usage_options', [] );
    $uses = get_option( $name, null ) === null ? [] : monospace_media_usage_scan_option( $name );
    monospace_media_usage_store( 'o:' . $name, $uses, $all[ $name ] ?? [] );
    if ( $uses ) $all[ $name ] = array_keys( $uses ); else unset( $all[ $name ] );
    update_option( 'monospace_media_usage_options', $all, false );
}

/** Everything from scratch. Returns [ attachments, used, unused ]. */
function monospace_media_usage_rebuild() {
    global $wpdb;
    @set_time_limit( 300 );
    monospace_media_usage_maps( true );
    $map = []; $per_post = [];
    $types = "'" . implode( "','", MONOSPACE_MEDIA_USAGE_TYPES ) . "'";
    foreach ( $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_type IN ($types) AND post_status NOT IN ('trash','auto-draft','inherit')" ) as $pid ) {
        $uses = monospace_media_usage_scan_post( (int) $pid );
        $per_post[ (int) $pid ] = array_keys( $uses );
        foreach ( $uses as $att => $how ) $map[ $att ][ 'p:' . $pid ] = $how;
    }
    $per_option = [];
    foreach ( monospace_media_usage_option_names() as $name ) {
        $uses = monospace_media_usage_scan_option( $name );
        if ( $uses ) $per_option[ $name ] = array_keys( $uses );
        foreach ( $uses as $att => $how ) $map[ $att ][ 'o:' . $name ] = $how;
    }
    // The placeholder picture (the one chosen, or the default file art_placeholder.png)
    if ( function_exists( 'monospace_placeholder_image_id' ) && ( $ph = monospace_placeholder_image_id() ) && isset( monospace_media_usage_maps()['ids'][ $ph ] ) ) $map[ $ph ]['o:monospace_placeholder_image'] = [ 'placeholder picture' ];
    // A category's / tag's share image (term meta _monospace_share_image: its edit screen, Layout Manager > Categories)
    foreach ( $wpdb->get_results( "SELECT term_id, meta_value FROM $wpdb->termmeta WHERE meta_key = '_monospace_share_image' AND meta_value <> ''" ) as $r ) {
        if ( isset( monospace_media_usage_maps()['ids'][ (int) $r->meta_value ] ) ) $map[ (int) $r->meta_value ][ 'c:' . (int) $r->term_id ] = [ 'share image' ];
    }
    // The site's own code (SCSS / CSS / PHP / JS) - the theme, Core and the monospace feature plugins (2026-10-19
    // restructure: code moved out of the theme): e.g. the Watercolor background, the footer's social icons
    $code = '';
    $dirs = array_merge( [ get_template_directory(), WPMU_PLUGIN_DIR . '/monospace-art-core' ], glob( WP_PLUGIN_DIR . '/monospace-*', GLOB_ONLYDIR ) ?: [] );
    foreach ( $dirs as $dir ) {
        if ( ! is_dir( $dir ) ) continue;
        foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) ) as $f ) {
            if ( preg_match( '/\.(php|s?css|js)$/', $f->getFilename() ) && ! preg_match( '#/(node_modules|vendor)/#', $f->getPathname() ) ) $code .= file_get_contents( $f->getPathname() ) . "\n";
        }
    }
    if ( preg_match_all( '#/wp-content/uploads/([^"\'\s\)\?\#<>]+\.[a-z0-9]{2,5})#i', $code, $m ) ) {
        $paths = monospace_media_usage_maps()['paths'];
        foreach ( $m[1] as $p ) { $p = monospace_media_usage_norm( $p ); if ( isset( $paths[ $p ] ) ) $map[ $paths[ $p ] ]['t:theme'] = [ 'site design' ]; }
    }

    $wpdb->query( "DELETE FROM $wpdb->postmeta WHERE meta_key IN ('_ms_used_in','_ms_used_count','_ms_media_uses')" );
    foreach ( array_keys( monospace_media_usage_maps()['ids'] ) as $att ) {
        add_post_meta( $att, '_ms_used_in', $map[ $att ] ?? [], true );
        add_post_meta( $att, '_ms_used_count', count( $map[ $att ] ?? [] ), true );
    }
    foreach ( $per_post as $pid => $ids ) if ( $ids ) add_post_meta( $pid, '_ms_media_uses', $ids, true );
    update_option( 'monospace_media_usage_options', $per_option, false );
    update_option( 'monospace_media_usage_built', time(), false );
    wp_cache_flush();
    monospace_media_usage_sync_folder();
    $total = count( monospace_media_usage_maps()['ids'] );
    return [ $total, count( $map ), $total - count( $map ) ];
}

/* ============================================================
 * SMART FOLDER "unused" (Real Media Library) - kept in step with the usage by every rebuild (nightly)
 * ============================================================ */

const MONOSPACE_UNUSED_GRACE_DAYS = 14; // a new upload isn't "unused" yet - it may be for a post still being written

/** The "unused" folder's ID (remembered, so a rename keeps it; found by name, or made) - or 0 */
function monospace_media_unused_folder() {
    global $wpdb;
    if ( ! function_exists( 'wp_rml_move' ) ) return 0;
    $table = $wpdb->prefix . 'realmedialibrary';
    $id = (int) get_option( 'monospace_media_unused_folder', 0 );
    if ( $id && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE id = %d", $id ) ) ) return $id;
    $id = (int) $wpdb->get_var( "SELECT id FROM $table WHERE LOWER(name) IN ('unused','to review') ORDER BY parent = -1 DESC, id LIMIT 1" );
    if ( ! $id ) {
        $id = wp_rml_create_or_return_existing_id( 'unused', -1, RML_TYPE_FOLDER, [], true );
        $id = is_numeric( $id ) ? (int) $id : 0;
    }
    if ( $id ) update_option( 'monospace_media_unused_folder', $id, false );
    return $id;
}

/** Whether an attachment is a Real Media Library shortcut (with $source: the original's ID, or 0) */
function monospace_media_is_shortcut( $att, $source = false ) {
    if ( function_exists( 'wp_attachment_is_shortcut' ) ) return wp_attachment_is_shortcut( $att, $source );
    return $source ? 0 : false;
}

/**
 * Where a file that was physically in "unused" goes (before 2026-10-19 the folder held the files
 * themselves): the folder it came from (_ms_unused_from), else by its use - an artwork's featured image
 * -> the artwork's category folder, a print file -> prints, the home page / theme / settings -> site
 * assets, anything else -> post assets.
 */
function monospace_media_usage_home_folder( $att, $folders ) {
    $from = (int) get_post_meta( $att, '_ms_unused_from', true );
    if ( $from && in_array( $from, $folders, true ) ) return $from;
    $map = (array) get_post_meta( $att, '_ms_used_in', true );
    foreach ( $map as $source => $how ) {
        if ( strpos( $source, 'p:' ) !== 0 ) continue;
        $post = get_post( (int) substr( $source, 2 ) );
        $prints = $folders['print files'] ?? $folders['prints'] ?? 0;
        if ( in_array( 'print file', (array) $how, true ) && $prints ) return $prints;
        if ( $post && $post->post_type === 'post' && in_array( 'featured image', (array) $how, true ) ) {
            $cats = wp_get_post_categories( $post->ID, [ 'fields' => 'slugs' ] );
            foreach ( [ 'hoboken-historical-museum' => 'hoboken-historical', 'miniature' => 'miniatures', 'portraits' => 'portraits', 'drawing' => 'drawings', 'painting' => 'paintings' ] as $cat => $folder ) {
                if ( in_array( $cat, $cats, true ) && isset( $folders[ $folder ] ) ) return $folders[ $folder ];
            }
        }
    }
    $only_site = $map && ! array_filter( array_keys( $map ), function ( $s ) { return strpos( $s, 'p:' ) === 0; } );
    if ( $only_site && isset( $folders['site assets'] ) ) return $folders['site assets'];
    return $folders['post assets'] ?? -1;
}

/**
 * Keep "unused" in step: it holds a **shortcut** to every file used nowhere (the file itself stays in
 * its own folder - owner's wish: an unused portrait can live in "portraits" and still be listed here).
 * Files physically in "unused" go back to a real folder; shortcuts of files used again (or gone) go.
 * Returns [ shortcuts added, shortcuts removed ].
 */
function monospace_media_usage_sync_folder() {
    global $wpdb;
    $unused = monospace_media_unused_folder();
    if ( ! $unused || ! function_exists( 'wp_rml_create_shortcuts' ) ) return [ 0, 0 ];
    $folders = [];
    foreach ( $wpdb->get_results( "SELECT id, name FROM {$wpdb->prefix}realmedialibrary" ) as $f ) if ( (int) $f->id !== $unused ) $folders[ strtolower( $f->name ) ] = (int) $f->id;
    $cutoff = gmdate( 'Y-m-d H:i:s', time() - MONOSPACE_UNUSED_GRACE_DAYS * DAY_IN_SECONDS );
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT p.ID, p.post_date_gmt, m.meta_value used, r.fid, r.isShortcut source FROM $wpdb->posts p
         LEFT JOIN $wpdb->postmeta m ON m.post_id = p.ID AND m.meta_key = '_ms_used_count'
         LEFT JOIN {$wpdb->prefix}realmedialibrary_posts r ON r.attachment = p.ID
         WHERE p.post_type = 'attachment' AND ( p.guid NOT LIKE %s OR r.fid = %d )", '%?sc=%', $unused ) );

    $files = []; $shortcut_of = []; $move_back = [];
    foreach ( $rows as $r ) {
        $id = (int) $r->ID;
        if ( (int) $r->source > 0 ) { if ( (int) $r->fid === $unused ) $shortcut_of[ $id ] = (int) $r->source; continue; }
        $files[ $id ] = $r;
        if ( (int) $r->fid === $unused ) $move_back[ monospace_media_usage_home_folder( $id, $folders ) ][] = $id;
    }
    // Files that are in "unused" themselves (the old way) -> a real folder
    foreach ( $move_back as $to => $ids ) {
        foreach ( array_chunk( $ids, 100 ) as $chunk ) wp_rml_move( $to, $chunk, true );
        foreach ( $ids as $id ) delete_post_meta( $id, '_ms_unused_from' );
    }
    // Shortcuts of files used again, gone or listed twice -> removed (RML keeps the file itself)
    $removed = 0; $have = [];
    foreach ( $shortcut_of as $sc => $source ) {
        $keep = isset( $files[ $source ] ) && (int) $files[ $source ]->used === 0 && ! isset( $have[ $source ] );
        if ( $keep ) { $have[ $source ] = $sc; continue; }
        if ( wp_delete_attachment( $sc, true ) ) $removed++;
    }
    // Files used nowhere (older than the grace period) without a shortcut yet -> one in "unused"
    $add = [];
    foreach ( $files as $id => $r ) if ( $r->used !== null && (int) $r->used === 0 && $r->post_date_gmt < $cutoff && ! isset( $have[ $id ] ) ) $add[] = $id;
    $added = 0;
    foreach ( array_chunk( $add, 100 ) as $chunk ) if ( wp_rml_create_shortcuts( $unused, $chunk, true ) === true ) $added += count( $chunk );
    wp_rml_update_count();
    update_option( 'monospace_media_unused_sync', [ 'at' => time(), 'in' => $added, 'out' => $removed ], false );
    return [ $added, $removed ];
}

/**
 * Dragging a shortcut out of "unused" into a folder files the file itself there; the shortcut goes back
 * to "unused" (it's still unused). Done at once, not at night.
 */
add_action( 'RML/Item/Move', function ( $fid, $ids, $folder, $is_shortcut ) {
    if ( $is_shortcut ) return;
    foreach ( (array) $ids as $id ) $GLOBALS['monospace_media_moving'][ (int) $id ] = (int) wp_attachment_folder( $id );
}, 10, 4 );
add_action( 'RML/Item/MoveFinished', function ( $fid, $ids, $folder, $is_shortcut ) {
    static $busy = false;
    if ( $is_shortcut || $busy ) return;
    $unused = (int) get_option( 'monospace_media_unused_folder', 0 );
    if ( ! $unused || (int) $fid === $unused ) return;
    $files = []; $back = [];
    foreach ( (array) $ids as $id ) {
        $from = $GLOBALS['monospace_media_moving'][ (int) $id ] ?? 0;
        unset( $GLOBALS['monospace_media_moving'][ (int) $id ] );
        $source = monospace_media_is_shortcut( $id, true );
        if ( $from !== $unused || ! $source ) continue;
        $files[] = $source; $back[] = (int) $id;
    }
    if ( ! $files ) return;
    $busy = true;
    wp_rml_move( (int) $fid, $files, true );
    wp_rml_move( $unused, $back, true );
    $busy = false;
}, 10, 4 );

/* ============================================================
 * KEEPING IT CURRENT - changes are queued and handled once, at the end of the request
 * ============================================================ */

function monospace_media_usage_queue( $kind, $key ) {
    static $hooked = false;
    $GLOBALS['monospace_media_usage_queue'][ $kind ][ $key ] = true;
    if ( ! $hooked ) {
        $hooked = true;
        add_action( 'shutdown', function () {
            $q = $GLOBALS['monospace_media_usage_queue'] ?? [];
            $GLOBALS['monospace_media_usage_queue'] = [];
            foreach ( array_keys( $q['post'] ?? [] ) as $id ) monospace_media_usage_update_post( $id );
            foreach ( array_keys( $q['option'] ?? [] ) as $name ) monospace_media_usage_update_option( $name );
        }, 5 );
    }
}

add_action( 'save_post', function ( $post_id, $post ) {
    if ( in_array( $post->post_type, MONOSPACE_MEDIA_USAGE_TYPES, true ) ) monospace_media_usage_queue( 'post', (int) $post_id );
}, 99, 2 );
foreach ( [ 'trashed_post', 'untrashed_post' ] as $hook ) {
    add_action( $hook, function ( $post_id ) { monospace_media_usage_queue( 'post', (int) $post_id ); } );
}
// Deleted: its uses go now (its meta is gone by the time "deleted_post" runs)
add_action( 'before_delete_post', function ( $post_id ) {
    if ( get_post_type( $post_id ) === 'attachment' ) return;
    $old = (array) get_post_meta( $post_id, '_ms_media_uses', true );
    if ( $old ) monospace_media_usage_store( 'p:' . $post_id, [], $old );
} );
// Featured image / gallery / print file set outside a post save (Quick Product, the prints tool, set_post_thumbnail)
foreach ( [ 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ] as $hook ) {
    add_action( $hook, function ( $meta_id, $object_id, $key ) {
        if ( in_array( $key, [ '_thumbnail_id', '_product_image_gallery', '_ms_print_file' ], true ) ) monospace_media_usage_queue( 'post', (int) $object_id );
    }, 10, 3 );
}
foreach ( [ 'added_option' => 1, 'updated_option' => 1, 'deleted_option' => 1 ] as $hook => $_ ) {
    add_action( $hook, function ( $name ) { if ( monospace_media_usage_option_matters( $name ) ) monospace_media_usage_queue( 'option', $name ); } );
}
// New uploads start as "not used" (so sorting and the filter include them)
add_action( 'add_attachment', function ( $id ) {
    if ( monospace_media_is_shortcut( $id ) ) return;
    add_post_meta( $id, '_ms_used_in', [], true );
    add_post_meta( $id, '_ms_used_count', 0, true );
} );

// Nightly rebuild
add_action( MONOSPACE_MEDIA_USAGE_HOOK, 'monospace_media_usage_rebuild' );
add_action( 'admin_init', function () {
    if ( function_exists( 'as_has_scheduled_action' ) && ! as_has_scheduled_action( MONOSPACE_MEDIA_USAGE_HOOK ) ) {
        as_schedule_recurring_action( strtotime( 'tomorrow 04:00' ), DAY_IN_SECONDS, MONOSPACE_MEDIA_USAGE_HOOK, [], 'monospace' );
    }
} );
add_action( 'admin_post_monospace_media_usage_rebuild', function () {
    if ( ! current_user_can( 'upload_files' ) ) wp_die( 'Not allowed' );
    check_admin_referer( 'monospace_media_usage_rebuild' );
    [ $total, $used, $unused ] = monospace_media_usage_rebuild();
    wp_safe_redirect( add_query_arg( [ 'mode' => 'list', 'ms_usage_rebuilt' => "$used-$unused" ], admin_url( 'upload.php' ) ) );
    exit;
} );

/* ============================================================
 * SHOWING IT
 * ============================================================ */

/** "Blue Heron, DeKorte Park (post) - featured image" lines for an attachment: [ [ html, … ], total places ] */
function monospace_media_usage_lines( $att ) {
    $map = get_post_meta( $att, '_ms_used_in', true );
    $lines = [];
    foreach ( is_array( $map ) ? $map : [] as $source => $how ) {
        [ $kind, $key ] = explode( ':', $source, 2 );
        if ( $kind === 'p' ) {
            $post = get_post( (int) $key );
            if ( ! $post ) continue;
            $target = $post->post_type === 'product_variation' ? get_post( $post->post_parent ) : $post;
            $type = [ 'post' => 'post', 'page' => 'page', 'product' => 'product', 'product_variation' => 'product variation', 'wp_block' => 'pattern', 'wp_template' => 'template', 'wp_template_part' => 'template part', 'wp_navigation' => 'menu' ][ $post->post_type ] ?? $post->post_type;
            $title = $target ? ( get_the_title( $target ) ?: '(no title)' ) : '(no title)';
            $link = $target ? get_edit_post_link( $target->ID ) : '';
            $status = $post->post_status !== 'publish' ? ', ' . $post->post_status : '';
            $label = ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title ) ) . ' <span class="ms-usage-type">(' . esc_html( $type . $status ) . ')</span>';
        } elseif ( $kind === 'c' ) {
            $term = get_term( (int) $key );
            if ( ! $term || is_wp_error( $term ) ) continue;
            $link  = get_edit_term_link( $term );
            $label = ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a>' : esc_html( $term->name ) ) . ' <span class="ms-usage-type">(' . ( $term->taxonomy === 'post_tag' ? 'tag' : 'category' ) . ')</span>';
        } else {
            $names = [ 'site_icon' => 'Site icon', 'theme_mods_' . get_stylesheet() => 'Logo', 'monospace_home_layout' => 'Home page', 'monospace_home_share_image' => 'Home page', 'monospace_placeholder_image' => 'Placeholder picture' ];
            $label = esc_html( $kind === 't' ? 'The theme' : ( $names[ $key ] ?? ( strpos( $key, 'widget_' ) === 0 ? 'Widgets' : 'Setting ' . $key ) ) );
            if ( $key === 'monospace_home_layout' || $key === 'monospace_home_share_image' ) $label = '<a href="' . esc_url( admin_url( 'admin.php?page=monospace-home-boxes' ) ) . '">' . $label . '</a>';
            if ( $key === 'monospace_placeholder_image' ) $label = '<a href="' . esc_url( admin_url( 'themes.php?page=monospace-look' ) ) . '">' . $label . '</a>';
        }
        $lines[] = $label . ' - ' . esc_html( implode( ', ', (array) $how ) );
    }
    return [ $lines, count( $lines ) ];
}

function monospace_media_usage_html( $att, $limit = 0 ) {
    if ( $source = monospace_media_is_shortcut( $att, true ) ) return '<span class="ms-usage-type">Shortcut - the file:</span> ' . monospace_media_usage_html( $source, $limit );
    if ( get_post_meta( $att, '_ms_used_count', true ) === '' ) return '<span class="ms-usage-none">not checked yet</span>';
    [ $lines, $n ] = monospace_media_usage_lines( $att );
    if ( ! $n ) return '<span class="ms-usage-none">not used</span>';
    $shown = $limit ? array_slice( $lines, 0, $limit ) : $lines;
    $out = '<strong>' . $n . ' ' . ( $n === 1 ? 'place' : 'places' ) . '</strong><ul class="ms-usage-list"><li>' . implode( '</li><li>', $shown ) . '</li></ul>';
    if ( $limit && $n > $limit ) $out .= '<span class="ms-usage-more">+ ' . ( $n - $limit ) . ' more (open the file)</span>';
    return $out;
}

// List view: "Used in" column, sortable
add_filter( 'manage_media_columns', function ( $cols ) {
    $out = [];
    foreach ( $cols as $k => $v ) { $out[ $k ] = $v; if ( $k === 'parent' ) $out['ms_used_in'] = 'Used in'; }
    if ( ! isset( $out['ms_used_in'] ) ) $out['ms_used_in'] = 'Used in';
    return $out;
} );
add_action( 'manage_media_custom_column', function ( $col, $id ) {
    if ( $col === 'ms_used_in' ) echo monospace_media_usage_html( $id, 4 );
}, 10, 2 );
add_filter( 'manage_upload_sortable_columns', function ( $cols ) { $cols['ms_used_in'] = 'ms_used'; return $cols; } );

// Filter Used / Not used + Rebuild, in the list view's toolbar
add_action( 'restrict_manage_posts', function ( $type ) {
    if ( $type !== 'attachment' ) return;
    $cur = sanitize_key( $_GET['ms_used'] ?? '' );
    echo '<select name="ms_used"><option value="">Used and not used</option><option value="used"' . selected( $cur, 'used', false ) . '>Used</option><option value="unused"' . selected( $cur, 'unused', false ) . '>Not used</option></select>';
} );
add_action( 'pre_get_posts', function ( $q ) {
    if ( ! is_admin() || ! $q->is_main_query() || ( $q->get( 'post_type' ) !== 'attachment' && ( $GLOBALS['pagenow'] ?? '' ) !== 'upload.php' ) ) return;
    monospace_media_usage_apply_query( $q, sanitize_key( $_GET['ms_used'] ?? '' ), $q->get( 'orderby' ) === 'ms_used' );
} );
// Grid view (Ajax): the filter's value arrives in query[ms_used] (WordPress drops unknown keys, so read it here)
add_filter( 'ajax_query_attachments_args', function ( $args ) {
    $used = sanitize_key( $_REQUEST['query']['ms_used'] ?? '' );
    if ( $used ) {
        $args['meta_query'] = array_merge( (array) ( $args['meta_query'] ?? [] ), [ monospace_media_usage_meta_clause( $used ) ] );
    }
    return $args;
} );
function monospace_media_usage_meta_clause( $used ) {
    return $used === 'unused'
        ? [ 'key' => '_ms_used_count', 'value' => 0, 'compare' => '=', 'type' => 'NUMERIC' ] // shortcuts have no count - left out
        : [ 'key' => '_ms_used_count', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ];
}
function monospace_media_usage_apply_query( $q, $used, $sort ) {
    if ( in_array( $used, [ 'used', 'unused' ], true ) ) $q->set( 'meta_query', array_merge( (array) $q->get( 'meta_query' ), [ monospace_media_usage_meta_clause( $used ) ] ) );
    if ( $sort ) { $q->set( 'meta_key', '_ms_used_count' ); $q->set( 'orderby', 'meta_value_num' ); }
}

// Attachment details (grid pop-up and edit screen)
add_filter( 'attachment_fields_to_edit', function ( $fields, $post ) {
    $fields['ms_used_in'] = [ 'label' => 'Used in', 'input' => 'html', 'html' => '<div class="ms-usage-field">' . monospace_media_usage_html( $post->ID ) . '</div>' ];
    return $fields;
}, 10, 2 );

// Media Library: styles; list view: when usage was last checked + "Rebuild usage", next to the filters
add_action( 'admin_footer-upload.php', function () {
    $built = (int) get_option( 'monospace_media_usage_built', 0 );
    $sync  = get_option( 'monospace_media_unused_sync' );
    $rebuild = wp_nonce_url( admin_url( 'admin-post.php?action=monospace_media_usage_rebuild' ), 'monospace_media_usage_rebuild' );
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
        if (bar) {
            var note = document.createElement('span');
            note.className = 'ms-usage-note';
            note.innerHTML = 'Usage checked <?php echo esc_js( ( $built ? human_time_diff( $built ) . ' ago' : 'never' ) . ( $sync ? ' (folder “unused”: ' . (int) $sync['in'] . ' in, ' . (int) $sync['out'] . ' out)' : '' ) ); ?> · <a href="<?php echo esc_url( $rebuild ); ?>">Rebuild usage</a>';
            bar.appendChild(note);
        }
    })();
    </script>
    <?php
} );
// Grid view: a Used / Not used filter in the toolbar - added right after media-views loads, before the
// library view is built (a footer script would run too late)
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

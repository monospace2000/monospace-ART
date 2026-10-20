<?php
/**
 * Prints > Watermarked images (2026-10-17) - a review list of every image the Image Watermark plugin
 * stamped (meta iw-is-watermarked) that has NO clean backup in uploads/iw-backup/, i.e. no clean copy can be
 * made for printing (create-prints.php: monospace_prints_usable_file()). Read-only. Three groups:
 *   1. current print files (those artworks' prints are paused until a clean photo is set)
 *   2. featured images of artworks without prints yet - they can't get prints until a clean photo is set
 *   3. everything else (images inside posts etc.)
 * Miniatures (post category "miniature") are left out: they never get prints (owner's decision).
 * Fix: the artwork's Prints box > "Replace print file" with an unwatermarked photo (then Resume prints).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
    add_submenu_page( 'monospace-prints', 'Watermarked images', 'Watermarked images', 'edit_products', 'monospace-prints-watermarks', 'monospace_prints_watermarks_page' );
}, 30 );

/** [ 'print' => [...], 'artwork' => [...], 'other' => [...] ], each row [ image ID, post ID or 0 ] */
function monospace_prints_watermark_review() {
    global $wpdb;
    $marked = array_map( 'intval', $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'iw-is-watermarked' AND meta_value = '1'" ) );
    // image => artwork whose featured image it is (published posts)
    $featured = [];
    foreach ( $wpdb->get_results( "SELECT pm.meta_value img, pm.post_id post FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                                   AND p.post_type = 'post' AND p.post_status = 'publish' WHERE pm.meta_key = '_thumbnail_id'" ) as $r ) {
        $featured[ (int) $r->img ] = (int) $r->post;
    }
    $groups = [ 'print' => [], 'artwork' => [], 'other' => [] ];
    foreach ( $marked as $id ) {
        if ( get_post_meta( $id, '_ms_print_file_clean', true ) || monospace_prints_watermark_backup( $id ) || monospace_prints_clean_copy_of( $id ) ) continue;
        $post = $featured[ $id ] ?? 0;
        // Miniatures never get prints (owner's decision, 2026-10-17) - not listed
        $owner = $post ?: (int) wp_get_post_parent_id( $id );
        if ( $owner && has_category( 'miniature', $owner ) ) continue;
        if ( $post && ( get_post_meta( $post, '_print_product_id', true ) || get_post_meta( $post, '_framed_print_product_id', true ) ) && (int) monospace_prints_file_for( $post ) === $id ) {
            $groups['print'][] = [ $id, $post ];
        } elseif ( $post ) {
            $groups['artwork'][] = [ $id, $post ];
        } else {
            $groups['other'][] = [ $id, (int) wp_get_post_parent_id( $id ) ];
        }
    }
    foreach ( $groups as &$rows ) usort( $rows, function ( $a, $b ) { return $b[0] <=> $a[0]; } ); // newest first
    return $groups;
}

function monospace_prints_watermarks_page() {
    $groups = monospace_prints_watermark_review();
    $titles = [
        'print'   => [ 'Paused prints - need a clean photo', 'These artworks’ prints are off sale. Set a clean photo with “Replace print file” in the artwork’s Prints box, then Resume prints.' ],
        'artwork' => [ 'Artworks without prints yet', 'No prints can be made of these until a clean photo is set (Prints box > Replace print file).' ],
        'other'   => [ 'Other images', 'Not an artwork’s main image (photos inside posts and the like) - they don’t affect prints.' ],
    ];
    $total = array_sum( array_map( 'count', $groups ) ); ?>
    <div class="wrap" id="ms-wm">
        <h1>Watermarked images</h1>
        <p class="description" style="max-width:820px">Images the Image Watermark plugin stamped that have <strong>no clean original</strong> on the server
            (<?php echo (int) $total; ?> in all). The marker only says the plugin processed an image - open the full size to see the watermark (a faint “monospace ART” across the middle).
            Images with a clean original aren’t listed: the prints use a clean copy of those automatically.</p>
        <p><?php foreach ( $groups as $key => $rows ) echo '<a href="#ms-wm-' . esc_attr( $key ) . '">' . esc_html( $titles[ $key ][0] ) . '</a> (' . count( $rows ) . ') &nbsp; '; ?></p>

        <div class="lm-areas" style="max-width:none"><div class="lm-panels">
        <?php foreach ( $groups as $key => $rows ) :
            // Admin UI style (2026-10-20): a panel per group - the ones to act on open and red, "Other images" folded
            $act   = $key !== 'other';
            $state = ! $rows ? 'on' : ( $act ? 'warn' : 'empty' ); ?>
            <details class="lm-panel" id="ms-wm-<?php echo esc_attr( $key ); ?>"<?php echo $act && $rows ? ' open' : ''; ?>>
            <summary><span class="lm-t"><?php echo esc_html( $titles[ $key ][0] ); ?></span>
                <span class="lm-chip is-<?php echo esc_attr( $state ); ?>"><?php echo $rows ? count( $rows ) : 'None'; ?></span>
                <span class="lm-sub"><?php echo esc_html( $titles[ $key ][1] ); ?></span></summary>
            <div class="lm-body">
            <?php if ( ! $rows ) : ?><p>None.</p></div></details><?php continue; endif; ?>
            <div class="ms-wm-grid">
                <?php foreach ( $rows as [ $img, $post ] ) :
                    $full = wp_get_original_image_url( $img ) ?: wp_get_attachment_url( $img );
                    $file = basename( (string) get_post_meta( $img, '_wp_attached_file', true ) ); ?>
                    <div class="ms-wm-card">
                        <a href="<?php echo esc_url( $full ); ?>" target="_blank" title="Full size">
                            <?php echo wp_get_attachment_image( $img, 'medium', false, [ 'loading' => 'lazy', 'alt' => '' ] ); ?></a>
                        <div class="ms-wm-info">
                            <strong><?php echo esc_html( $post ? html_entity_decode( get_the_title( $post ), ENT_QUOTES ) : get_the_title( $img ) ); ?></strong>
                            <span class="description"><?php echo esc_html( $file ); ?></span>
                            <span>
                                <a href="<?php echo esc_url( $full ); ?>" target="_blank">Full size</a>
                                <?php if ( $post ) : ?> · <a href="<?php echo esc_url( get_edit_post_link( $post ) ); ?>" target="_blank">Edit artwork</a><?php endif; ?>
                                · <a href="<?php echo esc_url( get_edit_post_link( $img ) ); ?>" target="_blank">Media item</a>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            </div></details>
        <?php endforeach; ?>
        </div></div>
    </div>
    <?php if ( function_exists( 'monospace_lm_area_assets' ) ) monospace_lm_area_assets(); // the admin UI kit (Core) ?>
    <style>
        #ms-wm .ms-wm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; max-width: 1400px; margin-bottom: 8px; }
        #ms-wm .ms-wm-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; overflow: hidden; }
        #ms-wm .lm-sub { white-space: normal; }
        #ms-wm .ms-wm-card img { display: block; width: 100%; height: 170px; object-fit: cover; background: #f0f0f1; }
        #ms-wm .ms-wm-info { display: flex; flex-direction: column; gap: 3px; padding: 8px 10px 10px; font-size: 13px; }
        #ms-wm .ms-wm-info .description { font-size: 12px; word-break: break-all; }
        #ms-wm h2 .count { color: #646970; font-weight: 400; }
    </style>
    <?php
}

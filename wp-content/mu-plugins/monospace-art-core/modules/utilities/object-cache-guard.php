<?php
/**
 * Object cache guard (2026-11-22).
 *
 * SiteGround's Memcached drop-in (wp-content/object-cache.php, SG Optimizer) appends every cache call to
 * $wp_object_cache->group_ops ("get 123", ...) and never empties it. It is only read by the drop-in's own debug view,
 * but in a long job (an overnight batch, a WP-CLI script over hundreds of products) it grows until PHP runs out of
 * memory: two "Allowed memory size of 805306368 bytes exhausted ... object-cache.php on line 263" fatals on 2026-11-22.
 * The drop-in is SiteGround's (an update would overwrite an edit), so this empties the list instead, at moments that
 * come round often in any long job. Caching itself is untouched.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function monospace_object_cache_trim() {
    global $wp_object_cache;
    if ( isset( $wp_object_cache->group_ops ) && is_array( $wp_object_cache->group_ops ) && $wp_object_cache->group_ops ) {
        $wp_object_cache->group_ops = array();
    }
}

foreach ( array( 'clean_post_cache', 'the_post', 'added_post_meta', 'updated_post_meta', 'woocommerce_after_product_object_save',
    'action_scheduler_after_execute' ) as $monospace_hook ) {
    add_action( $monospace_hook, 'monospace_object_cache_trim', 999 );
}
unset( $monospace_hook );

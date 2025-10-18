<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */


function custom_media_library_items_per_page( $query ) {
    if ( isset( $query['post_type'] ) && $query['post_type'] === 'attachment' ) {
        $query['posts_per_page'] = 200; // change to your preferred number
    }
    return $query;
}
add_filter( 'ajax_query_attachments_args', 'custom_media_library_items_per_page' );




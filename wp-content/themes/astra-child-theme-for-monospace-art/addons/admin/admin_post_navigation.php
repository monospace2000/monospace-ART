<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */



/**
 * Add next/previous edit links to the WordPress admin bar
 * 
 * Features:
 * - Shows on single post/product edit screens
 * - Adds "Previous" and "Next" edit links aligned to the right
 * - Supports both posts and WooCommerce products
 */
add_action( 'admin_bar_menu', function( $admin_bar ) {
    global $post, $wpdb, $pagenow;

    // Only run on post edit screen
    if ( $pagenow !== 'post.php' || empty( $post ) ) return;

    $post_types = [ 'post', 'product' ];
    if ( ! in_array( $post->post_type, $post_types ) ) return;

    $current_id = $post->ID;

    // Get previous post/product ID (by ID)
    $prev_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type = %s
           AND post_status IN ('publish','private')
           AND ID < %d
         ORDER BY ID DESC
         LIMIT 1",
        $post->post_type,
        $current_id
    ) );

    // Get next post/product ID (by ID)
    $next_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type = %s
           AND post_status IN ('publish','private')
           AND ID > %d
         ORDER BY ID ASC
         LIMIT 1",
        $post->post_type,
        $current_id
    ) );

 
    // Add "Next" link (points to newer ID)
    if ( $next_id ) {
        $admin_bar->add_node([
            'id'     => 'prev_post', // visually newer
            'title'  => '← Previous',
            'href'   => get_edit_post_link( $next_id ),
            'parent' => 'top-secondary', // align right
            'meta'   => [ 'class' => 'prev-post-link' ]
        ]);
    }
   // Add "Previous" link (points to older ID)
    if ( $prev_id ) {
        $admin_bar->add_node([
            'id'     => 'next_post', // visually older
            'title'  => 'Next →',
            'href'   => get_edit_post_link( $prev_id ),
            'parent' => 'top-secondary', // align right
            'meta'   => [ 'class' => 'next-post-link' ]
        ]);
    }

}, 200 ); // Run late to avoid conflicts with other admin bar items


<?php
/**
 * Admin Post Navigation
 *
 * Adds previous/next navigation links to the WordPress admin bar when editing posts or products.
 * This makes it easy to quickly navigate between content items without returning to the list view.
 *
 * FUNCTIONALITY:
 * - Shows "Previous" and "Next" links in the admin bar on edit screens
 * - Works with both regular posts and WooCommerce products
 * - Links appear on the right side of the admin bar
 * - Only appears on single post/product edit screens (post.php, post-new.php)
 *
 * USAGE:
 * Navigate to any post or product edit screen. The admin bar will show prev/next links
 * allowing you to cycle through content items in chronological order.
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
            'title'  => '← Newer',
            'href'   => get_edit_post_link( $next_id ),
            'parent' => 'top-secondary', // align right
            'meta'   => [ 'class' => 'prev-post-link' ]
        ]);
    }
   // Add "Previous" link (points to older ID)
    if ( $prev_id ) {
        $admin_bar->add_node([
            'id'     => 'next_post', // visually older
            'title'  => 'Older →',
            'href'   => get_edit_post_link( $prev_id ),
            'parent' => 'top-secondary', // align right
            'meta'   => [ 'class' => 'next-post-link' ]
        ]);
    }

}, 200 ); // Run late to avoid conflicts with other admin bar items


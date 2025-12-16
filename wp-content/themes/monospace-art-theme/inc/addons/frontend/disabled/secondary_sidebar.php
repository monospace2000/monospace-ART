<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */



<?php
// Register a second sidebar for Astra child theme
function monospace_register_second_sidebar() {
    register_sidebar( array(
        'name'          => __( 'Secondary Sidebar', 'astra-child-theme-for-monospace-art' ),
        'id'            => 'secondary-sidebar',
        'description'   => __( 'An additional sidebar area for custom layouts.', 'astra-child-theme-for-monospace-art' ),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
}
add_action( 'widgets_init', 'monospace_register_second_sidebar' );


// Display the secondary sidebar only on selected pages
function monospace_display_second_sidebar() {

    // Change these slugs to match your pages
    $target_pages = array( 'custom-work', 'shop' );

    if ( is_page( $target_pages ) && is_active_sidebar( 'secondary-sidebar' ) ) {
        echo '<aside id="secondary-sidebar" class="widget-area secondary-sidebar">';
        dynamic_sidebar( 'secondary-sidebar' );
        echo '</aside>';
    }
}
add_action( 'astra_primary_content_bottom', 'monospace_display_second_sidebar' );

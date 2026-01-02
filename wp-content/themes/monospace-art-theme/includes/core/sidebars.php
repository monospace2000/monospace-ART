<?php

function monospace_art_register_sidebars() {

    /*
    |--------------------------------------------------------------------------
    | Default Sidebar (Posts, Archives, Pages)
    |--------------------------------------------------------------------------
    */
    register_sidebar([
        'name'          => __('Sidebar — Default', 'monospace-art-theme'),
        'id'            => 'sidebar-default',
        'description'   => __('Default sidebar for blog posts, archives, and pages.', 'monospace-art-theme'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Post Override Sidebar
    |--------------------------------------------------------------------------
    */
    register_sidebar([
        'name'          => __('Sidebar — Post Override', 'monospace-art-theme'),
        'id'            => 'sidebar-post-override',
        'description'   => __('Optional sidebar used when enabled on individual posts.', 'monospace-art-theme'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ]);

    /*
    |--------------------------------------------------------------------------
    | Page Override Sidebar
    |--------------------------------------------------------------------------
    */
    register_sidebar([
        'name'          => __('Sidebar — Page Override', 'monospace-art-theme'),
        'id'            => 'sidebar-page-override',
        'description'   => __('Optional sidebar used when enabled on individual pages.', 'monospace-art-theme'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ]);

    /*
    |--------------------------------------------------------------------------
    | WooCommerce Override Sidebar
    |--------------------------------------------------------------------------
    */
    register_sidebar([
        'name'          => __('Sidebar — WooCommerce Override', 'monospace-art-theme'),
        'id'            => 'sidebar-woocommerce-override',
        'description'   => __('Optional sidebar used on WooCommerce pages when enabled.', 'monospace-art-theme'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ]);


    /*
    |--------------------------------------------------------------------------
    | Footer Widgets (unchanged)
    |--------------------------------------------------------------------------
    */
    register_sidebar([
        'name'          => __('Footer 1', 'monospace-art-theme'),
        'id'            => 'footer-1',
        'description'   => __('Widgets displayed in the footer.', 'monospace-art-theme'),
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h4 class="widget-title">',
        'after_title'   => '</h4>',
    ]);
}

add_action('widgets_init', 'monospace_art_register_sidebars');

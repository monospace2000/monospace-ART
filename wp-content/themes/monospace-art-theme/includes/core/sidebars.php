<?php

function monospace_art_register_sidebars() {

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

//add_action('widgets_init', 'monospace_art_register_sidebars');

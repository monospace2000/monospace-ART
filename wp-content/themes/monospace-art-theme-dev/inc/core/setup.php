<?php

// -----------------------------------------------------------
// Theme Setup
// -----------------------------------------------------------
function monospace_art_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo');
    add_theme_support('html5', array('search-form', 'comment-form', 'gallery', 'caption'));

    // Primary Menu
    register_nav_menus(array(
        'primary' => __('Primary Menu', 'monospace-art'),
    ));
}
add_action('after_setup_theme', 'monospace_art_setup');

// -----------------------------------------------------------
// Enable Shortcodes in Menu Item Titles
// -----------------------------------------------------------
add_filter('nav_menu_item_title', 'do_shortcode');

// -----------------------------------------------------------
// Add 'menu-item-has-children' class to menu items with submenus
// -----------------------------------------------------------
add_filter('wp_nav_menu_objects', function($items) {
    foreach ($items as $item) {
        if (in_array('menu-item-has-children', $item->classes)) {
            // Class already exists, skip
            continue;
        }

        // Check if this item is a parent
        foreach ($items as $potential_child) {
            if ($potential_child->menu_item_parent == $item->ID) {
                $item->classes[] = 'menu-item-has-children';
                break;
            }
        }
    }
    return $items;
});
<?php
$post_id = get_queried_object_id();
$use_override = false;
$custom_sidebar = '';

// Per-post override
if ($post_id) {
    $meta_value = get_post_meta($post_id, '_monospace_use_sidebar_override', true);
    $use_override = ($meta_value === '1');

    $custom_sidebar = get_post_meta($post_id, '_monospace_custom_sidebar', true);
}

// ----------------------------
// 1. WooCommerce single product override
// ----------------------------
if (function_exists('is_product') && is_product() && $use_override) {

    // First: render widget area if active
    if (is_active_sidebar('sidebar-woocommerce-override')) {
        dynamic_sidebar('sidebar-woocommerce-override');
    }

    // Then: append per-post custom content
    if (!empty($custom_sidebar)) {
        echo '<div class="custom-sidebar-content">';
        echo do_shortcode($custom_sidebar);
        echo '</div>';
    }

// ----------------------------
// 2. WooCommerce global pages (shop, cart, checkout)
// ----------------------------
} elseif (function_exists('is_woocommerce') && is_woocommerce()) {

    if (is_active_sidebar('sidebar-woocommerce-override')) {
        dynamic_sidebar('sidebar-woocommerce-override');
    }

    if (!empty($custom_sidebar)) {
        echo '<div class="custom-sidebar-content">';
        echo do_shortcode($custom_sidebar);
        echo '</div>';
    }

// ----------------------------
// 3. Page override
// ----------------------------
} elseif (is_page() && $use_override) {

    if (is_active_sidebar('sidebar-page-override')) {
        dynamic_sidebar('sidebar-page-override');
    }

    if (!empty($custom_sidebar)) {
        echo '<div class="custom-sidebar-content">';
        echo do_shortcode($custom_sidebar);
        echo '</div>';
    }

// ----------------------------
// 4. Post override
// ----------------------------
} elseif (is_singular('post') && $use_override) {

    if (is_active_sidebar('sidebar-post-override')) {
        dynamic_sidebar('sidebar-post-override');
    }

    if (!empty($custom_sidebar)) {
        echo '<div class="custom-sidebar-content">';
        echo do_shortcode($custom_sidebar);
        echo '</div>';
    }

// ----------------------------
// 5. Default sidebar
// ----------------------------
} elseif (is_active_sidebar('sidebar-default')) {
    dynamic_sidebar('sidebar-default');

// ----------------------------
// 6. Fallback
// ----------------------------
} else {
    echo '<!-- No sidebar active -->';
}

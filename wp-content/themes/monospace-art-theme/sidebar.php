<?php
/**
 * Sidebar Template
 *
 * Displays sidebar content from Layout Manager defaults or per-post overrides.
 * Always calls monospace_get_sidebar_content() which handles all logic internally.
 *
 * The function automatically:
 * - Checks for per-post/page overrides
 * - Falls back to Layout Manager defaults
 * - Handles responsive content wrapping with .content-desktop/.content-mobile
 * - Processes all shortcodes
 *
 * Related Files:
 * - sidebar-override.php - Contains monospace_get_sidebar_content() function
 * - layout-manager.php - Admin interface for configuring defaults
 * - feed-headers.php - Handles feed/category-specific sidebars
 * - _layout.scss - Responsive CSS that shows/hides content based on viewport
 */





// Get sidebar content based on context
$sidebar_content = '';

// Check if this is a singular post/page/product
if (is_singular(['post', 'page', 'product'])) {
    $post_id = get_the_ID();
    // Always call the function - it handles checking for overrides internally
    $sidebar_content = monospace_get_sidebar_content($post_id);
} else {
    // Feed/archive/search content
    $sidebar_content = monospace_get_sidebar_content(null);
}

// Output sidebar content
if (!empty($sidebar_content)) {
    echo '<div class="sidebar-content">';
    echo $sidebar_content;
    echo '</div>';
} else {
    echo '<!-- No sidebar content configured -->';
}
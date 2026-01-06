<?php
/**
 * Sidebar Override System
 *
 * Provides per-post/page/product sidebar customization with meta boxes.
 * Also contains the main helper function that retrieves sidebar content for all contexts.
 *
 * Key Function:
 * - monospace_get_sidebar_content($post_id) - Central function that returns sidebar content
 *   - For feeds/archives: Returns configured feed sidebar
 *   - For posts/pages/products: Returns default or overridden sidebar
 *   - Handles mirror checkbox logic and responsive content wrapping
 *
 * Override Modes:
 * - Replace: Use only custom content
 * - Append: Show default content, then custom content
 * - Prepend: Show custom content, then default content
 *
 * Related Files:
 * - layout-manager.php - Admin interface for site-wide defaults
 * - feed-headers.php - Handles feed/category headers and sidebars
 * - sidebar.php - Theme template that calls monospace_get_sidebar_content()
 * - _layout.scss - Responsive CSS for .content-desktop/.content-mobile visibility
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * META BOX REGISTRATION
 * ============================================================ */

add_action('add_meta_boxes', function() {
    add_meta_box(
        'monospace_sidebar_override',
        __('Sidebar Override', 'monospace-art-theme'),
        'monospace_sidebar_override_metabox_callback',
        ['post', 'page', 'product'],
        'side',
        'default'
    );
});

/* ============================================================
 * META BOX CONTENT
 * ============================================================ */

function monospace_sidebar_override_metabox_callback($post) {
    $use_override   = get_post_meta($post->ID, '_monospace_use_sidebar_override', true);
    $override_mode  = get_post_meta($post->ID, '_monospace_override_mode', true) ?: 'replace';
    $custom_content = get_post_meta($post->ID, '_monospace_custom_sidebar', true);
    $mobile_content = get_post_meta($post->ID, '_monospace_mobile_sidebar', true);
    $hide_sidebar   = get_post_meta($post->ID, '_monospace_hide_sidebar', true);
    $hide_header    = get_post_meta($post->ID, '_monospace_hide_header', true);

    // Mirror checkbox - default to checked (1) if not set
    $mirror_sidebar = get_post_meta($post->ID, '_monospace_mirror_sidebar', true);
    $mirror_sidebar = ($mirror_sidebar === '') ? '1' : $mirror_sidebar;

    wp_nonce_field('monospace_sidebar_override_nonce', 'monospace_sidebar_override_nonce_field');
    ?>

    <h4 style="margin: 0 0 10px 0;"><?php esc_html_e('Layout Options', 'monospace-art-theme'); ?></h4>

    <p>
        <label>
            <input type="checkbox" name="monospace_hide_header" value="1" <?php checked($hide_header, '1'); ?>>
            <?php esc_html_e('Hide site header (logo & tagline)', 'monospace-art-theme'); ?>
        </label>
    </p>

    <p>
        <label>
            <input type="checkbox" name="monospace_hide_sidebar" value="1" <?php checked($hide_sidebar, '1'); ?>>
            <?php esc_html_e('Hide sidebar entirely', 'monospace-art-theme'); ?>
        </label>
    </p>

    <hr style="margin: 15px 0;">

    <h4 style="margin: 0 0 10px 0;"><?php esc_html_e('Custom Sidebar', 'monospace-art-theme'); ?></h4>

    <p>
        <label>
            <input type="checkbox" name="monospace_sidebar_override" value="1" <?php checked($use_override, '1'); ?>>
            <?php esc_html_e('Use custom sidebar for this content', 'monospace-art-theme'); ?>
        </label>
    </p>

    <p>
        <label for="monospace_override_mode"><?php esc_html_e('Override Mode', 'monospace-art-theme'); ?></label>
        <select name="monospace_override_mode" id="monospace_override_mode" style="width: 100%;">
            <option value="replace" <?php selected($override_mode, 'replace'); ?>>Replace default sidebar</option>
            <option value="append" <?php selected($override_mode, 'append'); ?>>Append to default sidebar</option>
            <option value="prepend" <?php selected($override_mode, 'prepend'); ?>>Prepend to default sidebar</option>
        </select>
    </p>

    <p style="font-size:0.85em; color:#666; margin-top: 5px;">
        <strong>Replace:</strong> Use only this custom content<br>
        <strong>Append:</strong> Show default content, then this custom content<br>
        <strong>Prepend:</strong> Show this custom content, then default content
    </p>

    <p>
        <label for="monospace_custom_sidebar">
            <?php esc_html_e('Desktop Sidebar', 'monospace-art-theme'); ?>
        </label>
        <?php
        wp_editor($custom_content, 'monospace_custom_sidebar', [
            'textarea_name' => 'monospace_custom_sidebar',
            'media_buttons' => true,
            'textarea_rows' => 10,
            'teeny'         => false,
            'quicktags'     => true,
        ]);
        ?>
    </p>

    <p>
        <label for="monospace_mobile_sidebar">
            <?php esc_html_e('Mobile & Tablet Sidebar', 'monospace-art-theme'); ?>
        </label>
        <?php
        wp_editor($mobile_content, 'monospace_mobile_sidebar', [
            'textarea_name' => 'monospace_mobile_sidebar',
            'media_buttons' => true,
            'textarea_rows' => 10,
            'teeny'         => false,
            'quicktags'     => true,
        ]);
        ?>
    </p>

    <p>
        <label>
            <input type="checkbox" name="monospace_mirror_sidebar" value="1" <?php checked($mirror_sidebar, '1'); ?>>
            <?php esc_html_e('Mirror desktop content to mobile (ignore mobile field above)', 'monospace-art-theme'); ?>
        </label>
    </p>

    <p style="font-size:0.85em; color:#666;">
        <?php esc_html_e('If mobile/tablet is empty, desktop content will be used. HTML and shortcodes allowed. See Layout Manager → Shortcodes for options.', 'monospace-art-theme'); ?>
    </p>
    <?php
}

/* ============================================================
 * SAVE META BOX
 * ============================================================ */

add_action('save_post', function($post_id) {
    if (
        ! isset($_POST['monospace_sidebar_override_nonce_field']) ||
        ! wp_verify_nonce($_POST['monospace_sidebar_override_nonce_field'], 'monospace_sidebar_override_nonce')
    ) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Save checkboxes
    if (isset($_POST['monospace_hide_header'])) {
        update_post_meta($post_id, '_monospace_hide_header', '1');
    } else {
        delete_post_meta($post_id, '_monospace_hide_header');
    }

    if (isset($_POST['monospace_hide_sidebar'])) {
        update_post_meta($post_id, '_monospace_hide_sidebar', '1');
    } else {
        delete_post_meta($post_id, '_monospace_hide_sidebar');
    }

    if (isset($_POST['monospace_sidebar_override'])) {
        update_post_meta($post_id, '_monospace_use_sidebar_override', '1');
    } else {
        delete_post_meta($post_id, '_monospace_use_sidebar_override');
    }

    // Save override mode
    if (isset($_POST['monospace_override_mode'])) {
        update_post_meta($post_id, '_monospace_override_mode', sanitize_text_field($_POST['monospace_override_mode']));
    }

    // Save mirror checkbox
    if (isset($_POST['monospace_mirror_sidebar'])) {
        update_post_meta($post_id, '_monospace_mirror_sidebar', '1');
    } else {
        update_post_meta($post_id, '_monospace_mirror_sidebar', '0');
    }

    // Save content
    if (isset($_POST['monospace_custom_sidebar'])) {
        update_post_meta($post_id, '_monospace_custom_sidebar', wp_kses_post($_POST['monospace_custom_sidebar']));
    } else {
        delete_post_meta($post_id, '_monospace_custom_sidebar');
    }

    if (isset($_POST['monospace_mobile_sidebar'])) {
        update_post_meta($post_id, '_monospace_mobile_sidebar', wp_kses_post($_POST['monospace_mobile_sidebar']));
    } else {
        delete_post_meta($post_id, '_monospace_mobile_sidebar');
    }
});

/* ============================================================
 * HELPER FUNCTION - GET SIDEBAR CONTENT
 * ============================================================ */
function monospace_get_sidebar_content($post_id = null) {
    // Handle feeds and archives
    if (!$post_id) {
        // Category/taxonomy archives
        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();
            if ($term) {
                return monospace_get_category_sidebar_content($term->term_id);
            }
        }

        // Blog feed
        if (is_home()) {
            $prefix = 'monospace_blog_feed_';
            $desktop = get_option($prefix . 'sidebar_desktop', '');
            $mobile = get_option($prefix . 'sidebar_mobile', '');
            $mirror = get_option($prefix . 'mirror_sidebar', '');
            if ($mirror === '') $mirror = '1';

            if ($mirror === '1') {
                return do_shortcode($desktop);
            }

            // Output both with CSS classes
            $output = '';
            if ($desktop) {
                $output .= '<div class="content-desktop">' . do_shortcode($desktop) . '</div>';
            }
            if ($mobile) {
                $output .= '<div class="content-mobile">' . do_shortcode($mobile) . '</div>';
            }
            return $output;
        }

        // Search results
        if (is_search()) {
            $prefix = 'monospace_search_';
            $desktop = get_option($prefix . 'sidebar_desktop', '');
            $mobile = get_option($prefix . 'sidebar_mobile', '');
            $mirror = get_option($prefix . 'mirror_sidebar', '');
            if ($mirror === '') $mirror = '1';

            if ($mirror === '1') {
                return do_shortcode($desktop);
            }

            // Output both with CSS classes
            $output = '';
            if ($desktop) {
                $output .= '<div class="content-desktop">' . do_shortcode($desktop) . '</div>';
            }
            if ($mobile) {
                $output .= '<div class="content-mobile">' . do_shortcode($mobile) . '</div>';
            }
            return $output;
        }

        return '';
    }

    $post_type = get_post_type($post_id);
    $use_override = get_post_meta($post_id, '_monospace_use_sidebar_override', true);
    $override_mode = get_post_meta($post_id, '_monospace_override_mode', true) ?: 'replace';

    // Get default content for this post type
    $prefix = 'monospace_' . $post_type . 's_';
    $default_desktop = get_option($prefix . 'sidebar_desktop', '');
    $default_mobile = get_option($prefix . 'sidebar_mobile', '');
    $default_mirror = get_option($prefix . 'mirror_sidebar', '');
    if ($default_mirror === '') $default_mirror = '1';

    // Determine default content
    if ($default_mirror === '1') {
        $default_content = $default_desktop;
    } else {
        // Output both with CSS classes
        $default_content = '';
        if ($default_desktop) {
            $default_content .= '<div class="content-desktop">' . do_shortcode($default_desktop) . '</div>';
        }
        if ($default_mobile) {
            $default_content .= '<div class="content-mobile">' . do_shortcode($default_mobile) . '</div>';
        }
    }

    // If no override enabled, return default
    if (!$use_override) {
        return do_shortcode($default_content);
    }

    // Get post-specific content
    $custom_desktop = get_post_meta($post_id, '_monospace_custom_sidebar', true);
    $custom_mobile = get_post_meta($post_id, '_monospace_mobile_sidebar', true);
    $custom_mirror = get_post_meta($post_id, '_monospace_mirror_sidebar', true);
    if ($custom_mirror === '') $custom_mirror = '1';

    // Determine custom content
    if ($custom_mirror === '1') {
        $custom_content = $custom_desktop;
    } else {
        // Output both with CSS classes
        $custom_content = '';
        if ($custom_desktop) {
            $custom_content .= '<div class="content-desktop">' . do_shortcode($custom_desktop) . '</div>';
        }
        if ($custom_mobile) {
            $custom_content .= '<div class="content-mobile">' . do_shortcode($custom_mobile) . '</div>';
        }
    }

    // Apply override mode
    switch ($override_mode) {
        case 'append':
            $final_content = $default_content . "\n\n" . $custom_content;
            break;
        case 'prepend':
            $final_content = $custom_content . "\n\n" . $default_content;
            break;
        case 'replace':
        default:
            $final_content = $custom_content ?: $default_content;
            break;
    }

    return do_shortcode($final_content);
}
/* ============================================================
 * BODY CLASSES
 * ============================================================ */

add_filter('body_class', function($classes) {
    if (is_singular(['post', 'page', 'product'])) {
        $hide_header = get_post_meta(get_the_ID(), '_monospace_hide_header', true);
        if ($hide_header) {
            $classes[] = 'hide-header';
        }

        $hide_sidebar = get_post_meta(get_the_ID(), '_monospace_hide_sidebar', true);
        if ($hide_sidebar) {
            $classes[] = 'hide-sidebar';
        }
    }

    if (function_exists('is_woocommerce') && is_woocommerce()) {
        $classes[] = 'hide-sidebar';
        $classes[] = 'wc-fullwidth';
    }

    return $classes;
});
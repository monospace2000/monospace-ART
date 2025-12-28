<?php

// ---------------------------------------------------------------------------
// Register the Sidebar Override Meta Box
// ---------------------------------------------------------------------------
function monospace_art_add_sidebar_override_metabox() {

    add_meta_box(
        'monospace_sidebar_override',
        __('Sidebar Override', 'monospace-art-theme'),
        'monospace_art_sidebar_override_metabox_callback',
        ['post', 'page', 'product'], // supports posts, pages, products
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'monospace_art_add_sidebar_override_metabox');


// ---------------------------------------------------------------------------
// Render the Meta Box (Checkbox + Custom Content)
// ---------------------------------------------------------------------------
function monospace_art_sidebar_override_metabox_callback($post) {

    // Retrieve existing values
    $use_override = get_post_meta($post->ID, '_monospace_use_sidebar_override', true);
    $custom_content = get_post_meta($post->ID, '_monospace_custom_sidebar', true);
    $hide_sidebar = get_post_meta($post->ID, '_monospace_hide_sidebar', true);

    // Security nonce
    wp_nonce_field(
        'monospace_sidebar_override_nonce',
        'monospace_sidebar_override_nonce_field'
    );

    ?>
    <p>
        <label>
            <input type="checkbox"
                   name="monospace_sidebar_override"
                   value="1"
                   <?php checked($use_override, '1'); ?>>
            <?php esc_html_e('Use custom sidebar for this content', 'monospace-art-theme'); ?>
        </label>
    </p>

    <p>
        <label for="monospace_custom_sidebar">
            <?php esc_html_e('Custom Sidebar Content', 'monospace-art-theme'); ?>
        </label>
        <textarea id="monospace_custom_sidebar"
                  name="monospace_custom_sidebar"
                  rows="6"
                  style="width:100%;"><?php echo esc_textarea($custom_content); ?></textarea>
    </p>

    <p>
        <label>
            <input type="checkbox"
                   name="monospace_hide_sidebar"
                   value="1"
                   <?php checked($hide_sidebar, '1'); ?>>
            <?php esc_html_e('Hide sidebar entirely', 'monospace-art-theme'); ?>
        </label>
    </p>

    <p style="font-size:0.85em; color:#666;">
        <?php esc_html_e('If left empty, the override sidebar widgets will display. Otherwise, this content will display.', 'monospace-art-theme'); ?>
    </p>
    <?php
}


// ---------------------------------------------------------------------------
// Save Meta Box Values
// ---------------------------------------------------------------------------
function monospace_art_save_sidebar_override($post_id) {

    // Verify nonce
    if (
        ! isset($_POST['monospace_sidebar_override_nonce_field']) ||
        ! wp_verify_nonce($_POST['monospace_sidebar_override_nonce_field'], 'monospace_sidebar_override_nonce')
    ) {
        return;
    }

    // Avoid autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    // Permission check
    if (!current_user_can('edit_post', $post_id)) return;

    // Save checkbox: custom sidebar
    if (isset($_POST['monospace_sidebar_override'])) {
        update_post_meta($post_id, '_monospace_use_sidebar_override', '1');
    } else {
        delete_post_meta($post_id, '_monospace_use_sidebar_override');
    }

    // Save custom sidebar content
    if (isset($_POST['monospace_custom_sidebar'])) {
        $custom_content = wp_kses_post($_POST['monospace_custom_sidebar']);
        update_post_meta($post_id, '_monospace_custom_sidebar', $custom_content);
    } else {
        delete_post_meta($post_id, '_monospace_custom_sidebar');
    }

    // Save checkbox: hide sidebar
    if (isset($_POST['monospace_hide_sidebar'])) {
        update_post_meta($post_id, '_monospace_hide_sidebar', '1');
    } else {
        delete_post_meta($post_id, '_monospace_hide_sidebar');
    }
}
add_action('save_post', 'monospace_art_save_sidebar_override');


// ---------------------------------------------------------------------------
// Add body class if sidebar hidden
// ---------------------------------------------------------------------------
function monospace_art_add_body_class_hide_sidebar($classes) {
    if (is_singular(['post','page','product'])) {
        $hide_sidebar = get_post_meta(get_the_ID(), '_monospace_hide_sidebar', true);
        if ($hide_sidebar) {
            $classes[] = 'hide-sidebar';
        }
    }
    return $classes;
}
add_filter('body_class', 'monospace_art_add_body_class_hide_sidebar');

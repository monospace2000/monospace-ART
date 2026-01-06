<?php
// ---------------------------------------------------------------------------
// Register the Layout Override Meta Box
// ---------------------------------------------------------------------------
function monospace_art_add_sidebar_override_metabox() {

    add_meta_box(
        'monospace_sidebar_override',
        __('Layout Override', 'monospace-art-theme'),
        'monospace_art_sidebar_override_metabox_callback',
        ['post', 'page', 'product'], // supports posts, pages, products
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'monospace_art_add_sidebar_override_metabox');


// ---------------------------------------------------------------------------
// Render the Meta Box (Gutenberg-ready editor)
// ---------------------------------------------------------------------------
function monospace_art_sidebar_override_metabox_callback($post) {

    // Retrieve existing values
    $use_override   = get_post_meta($post->ID, '_monospace_use_sidebar_override', true);
    $custom_content = get_post_meta($post->ID, '_monospace_custom_sidebar', true);
    $mobile_content = get_post_meta($post->ID, '_monospace_mobile_sidebar', true);
    $hide_sidebar   = get_post_meta($post->ID, '_monospace_hide_sidebar', true);
    $hide_header    = get_post_meta($post->ID, '_monospace_hide_header', true);

    // Security nonce
    wp_nonce_field(
        'monospace_sidebar_override_nonce',
        'monospace_sidebar_override_nonce_field'
    );

    ?>
    <h4 style="margin: 0 0 10px 0;"><?php esc_html_e('Page Layout', 'monospace-art-theme'); ?></h4>

    <p>
        <label>
            <input type="checkbox"
                   name="monospace_hide_header"
                   value="1"
                   <?php checked($hide_header, '1'); ?>>
            <?php esc_html_e('Hide site header (logo & tagline)', 'monospace-art-theme'); ?>
        </label>
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

    <hr style="margin: 15px 0;">

    <h4 style="margin: 0 0 10px 0;"><?php esc_html_e('Custom Sidebar', 'monospace-art-theme'); ?></h4>

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
            <?php esc_html_e('Desktop Sidebar Content', 'monospace-art-theme'); ?>
        </label>
        <?php
        wp_editor(
            $custom_content,
            'monospace_custom_sidebar',
            [
                'textarea_name' => 'monospace_custom_sidebar',
                'media_buttons' => true,
                'textarea_rows' => 10,
                'teeny'         => false,
                'quicktags'     => true,
            ]
        );
        ?>
    </p>

    <p>
        <label for="monospace_mobile_sidebar">
            <?php esc_html_e('Mobile & Tablet Sidebar Content', 'monospace-art-theme'); ?>
        </label>
        <?php
        wp_editor(
            $mobile_content,
            'monospace_mobile_sidebar',
            [
                'textarea_name' => 'monospace_mobile_sidebar',
                'media_buttons' => true,
                'textarea_rows' => 10,
                'teeny'         => false,
                'quicktags'     => true,
            ]
        );
        ?>
    </p>

    <p style="font-size:0.85em; color:#666;">
        <?php esc_html_e('If mobile/tablet content is left empty, desktop content will be used on all devices. If both are empty, the override sidebar widgets will display.', 'monospace-art-theme'); ?>
    </p>

    <p style="font-size:0.85em; color:#c09853; background:#fcf8e3; padding:8px; border-left:3px solid #c09853; margin-top:10px;">
        <strong><?php esc_html_e('Note:', 'monospace-art-theme'); ?></strong> <?php esc_html_e('Adding content here will replace all widgets in the override sidebar. To combine widgets with custom text, leave these fields empty and add a Text widget to the override sidebar instead.', 'monospace-art-theme'); ?>
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

    // Save checkbox: hide header
    if (isset($_POST['monospace_hide_header'])) {
        update_post_meta($post_id, '_monospace_hide_header', '1');
    } else {
        delete_post_meta($post_id, '_monospace_hide_header');
    }

    // Save checkbox: hide sidebar
    if (isset($_POST['monospace_hide_sidebar'])) {
        update_post_meta($post_id, '_monospace_hide_sidebar', '1');
    } else {
        delete_post_meta($post_id, '_monospace_hide_sidebar');
    }

    // Save checkbox: custom sidebar
    if (isset($_POST['monospace_sidebar_override'])) {
        update_post_meta($post_id, '_monospace_use_sidebar_override', '1');
    } else {
        delete_post_meta($post_id, '_monospace_use_sidebar_override');
    }

    // Save desktop custom sidebar content
    if (isset($_POST['monospace_custom_sidebar'])) {
        $custom_content = wp_kses_post($_POST['monospace_custom_sidebar']);
        update_post_meta($post_id, '_monospace_custom_sidebar', $custom_content);
    } else {
        delete_post_meta($post_id, '_monospace_custom_sidebar');
    }

    // Save mobile/tablet custom sidebar content
    if (isset($_POST['monospace_mobile_sidebar'])) {
        $mobile_content = wp_kses_post($_POST['monospace_mobile_sidebar']);
        update_post_meta($post_id, '_monospace_mobile_sidebar', $mobile_content);
    } else {
        delete_post_meta($post_id, '_monospace_mobile_sidebar');
    }
}
add_action('save_post', 'monospace_art_save_sidebar_override');


// ---------------------------------------------------------------------------
// Global Post Defaults Settings Page
// ---------------------------------------------------------------------------
add_action( 'admin_menu', function () {
    add_options_page(
        'Post Sidebar Defaults',
        'Post Sidebar Defaults',
        'manage_options',
        'monospace-post-sidebar-defaults',
        'monospace_post_sidebar_defaults_page'
    );
} );

function monospace_post_sidebar_defaults_page() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['monospace_post_defaults_submit'] ) ) {
        check_admin_referer( 'monospace_post_defaults_settings' );

        update_option( 'monospace_post_default_desktop_sidebar', wp_kses_post( $_POST['post_default_desktop_sidebar'] ?? '' ) );
        update_option( 'monospace_post_default_mobile_sidebar', wp_kses_post( $_POST['post_default_mobile_sidebar'] ?? '' ) );

        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    $desktop = get_option( 'monospace_post_default_desktop_sidebar', '' );
    $mobile  = get_option( 'monospace_post_default_mobile_sidebar', '' );
    ?>

    <div class="wrap">
        <h1>Post Sidebar Defaults</h1>
        <p>Set default sidebar content for all single posts. Individual posts can override this in their Layout Override meta box.</p>

        <form method="post" action="">
            <?php wp_nonce_field( 'monospace_post_defaults_settings' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="post-default-desktop-sidebar">Desktop Sidebar Content</label>
                    </th>
                    <td>
                        <textarea
                            name="post_default_desktop_sidebar"
                            id="post-default-desktop-sidebar"
                            rows="10"
                            class="large-text"
                        ><?php echo esc_textarea( $desktop ); ?></textarea>
                        <p class="description">Default sidebar content for all posts on desktop. HTML allowed.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="post-default-mobile-sidebar">Mobile & Tablet Sidebar Content</label>
                    </th>
                    <td>
                        <textarea
                            name="post_default_mobile_sidebar"
                            id="post-default-mobile-sidebar"
                            rows="10"
                            class="large-text"
                        ><?php echo esc_textarea( $mobile ); ?></textarea>
                        <p class="description">Default sidebar content for all posts on mobile/tablet. If empty, desktop content will be used. HTML allowed.</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input
                    type="submit"
                    name="monospace_post_defaults_submit"
                    class="button button-primary"
                    value="Save Settings"
                />
            </p>
        </form>

        <hr>

        <h3>How It Works</h3>
        <ul>
            <li><strong>If a post has custom sidebar content set</strong> → Uses that post's content</li>
            <li><strong>If a post has override enabled but no content</strong> → Uses Post Override sidebar widgets</li>
            <li><strong>If a post has no override enabled</strong> → Uses these defaults (if set), otherwise Default sidebar widgets</li>
        </ul>
    </div>

    <?php
}


// ---------------------------------------------------------------------------
// Output the appropriate sidebar content based on device
// ---------------------------------------------------------------------------
function monospace_art_get_custom_sidebar_content($post_id) {
    // First check for post-specific content
    $desktop_content = get_post_meta($post_id, '_monospace_custom_sidebar', true);
    $mobile_content = get_post_meta($post_id, '_monospace_mobile_sidebar', true);

    // If no post-specific content and this is a post, check for global defaults
    if (!$desktop_content && !$mobile_content && get_post_type($post_id) === 'post') {
        $desktop_content = get_option('monospace_post_default_desktop_sidebar', '');
        $mobile_content = get_option('monospace_post_default_mobile_sidebar', '');
    }

    // If mobile content exists and we're on mobile/tablet, use it
    if ($mobile_content && wp_is_mobile()) {
        return $mobile_content;
    }

    // Otherwise use desktop content
    return $desktop_content;
}


// ---------------------------------------------------------------------------
// Add body classes for layout behavior
// ---------------------------------------------------------------------------
function monospace_art_add_body_class_hide_sidebar($classes) {

    /*
     * 1. Per-post / page / product override - hide header
     */
    if (is_singular(['post', 'page', 'product'])) {
        $hide_header = get_post_meta(get_the_ID(), '_monospace_hide_header', true);
        if ($hide_header) {
            $classes[] = 'hide-header';
        }
    }

    /*
     * 2. Per-post / page / product override - hide sidebar
     */
    if (is_singular(['post', 'page', 'product'])) {
        $hide_sidebar = get_post_meta(get_the_ID(), '_monospace_hide_sidebar', true);
        if ($hide_sidebar) {
            $classes[] = 'hide-sidebar';
        }
    }

    /*
     * 3. Force full-width layout on ALL WooCommerce pages
     */
    if (function_exists('is_woocommerce') && is_woocommerce()) {
        $classes[] = 'hide-sidebar';
        $classes[] = 'wc-fullwidth';
    }

    return $classes;
}
add_filter('body_class', 'monospace_art_add_body_class_hide_sidebar');
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
 * The box (2026-10-20) is in the admin UI style (CLAUDE.md "Admin UI style"): a small map of this page and
 * panels - Header & title, Top content, Sidebar - each open when this page overrides the default. Same fields
 * and names as before, saved below.
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
        __('This page’s layout', 'monospace-art-theme'), // was "Sidebar Override" (2026-10-20: it also sets logo, title, top content)
        'monospace_sidebar_override_metabox_callback',
        ['post', 'page'], // not products: they never show a sidebar
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
    $sidebar_choice = monospace_sidebar_override_choice($post->ID);
    $top_mode       = get_post_meta($post->ID, '_monospace_top_mode', true);
    $top_content    = get_post_meta($post->ID, '_monospace_top_content', true);
    $top_style      = (array) get_post_meta($post->ID, '_monospace_top_style', true); // its own banner style, dates, close
    $hide_header    = get_post_meta($post->ID, '_monospace_hide_header', true);
    $hide_title     = get_post_meta($post->ID, '_monospace_hide_title', true);

    // Mirror checkbox - default to checked (1) if not set
    $mirror_sidebar = get_post_meta($post->ID, '_monospace_mirror_sidebar', true);
    $mirror_sidebar = ($mirror_sidebar === '') ? '1' : $mirror_sidebar;

    // The page type's defaults (Layout Manager > Headers & Sidebars)
    $type       = $post->post_type === 'post' ? 'posts' : ($post->post_type === 'product' ? 'products' : 'pages');
    $show_def   = monospace_sidebar_default_visible($type);
    $top_def    = trim((string) monospace_layout_option($type, 'top', 'top_content', '')) !== '';
    $lm         = admin_url('admin.php?page=monospace-layout-manager&tab=' . $type);
    $preview    = function_exists('monospace_lm_preview') ? 'monospace_lm_preview' : fn($t) => wp_strip_all_tags($t);

    // Each area's state on this page: what the map and the title lines say
    $h_state  = ($hide_header || $hide_title) ? 'on' : 'empty';
    $h_chip   = $hide_header && $hide_title ? 'Logo and title hidden' : ($hide_header ? 'Logo hidden' : ($hide_title ? 'Title hidden' : 'As usual'));
    $t_state  = $top_mode === 'custom' ? 'on' : ($top_mode === 'none' ? 'off' : 'empty');
    $t_chip   = $top_mode === 'custom' ? 'Its own' : ($top_mode === 'none' ? 'None' : 'Default' . ($top_def ? '' : ' (empty)'));
    $s_shown  = $sidebar_choice ? $sidebar_choice === 'show' : $show_def;
    $s_state  = ! $s_shown ? 'off' : ($use_override ? 'on' : 'empty');
    $s_chip   = (! $s_shown ? 'Hidden' : ($use_override ? 'Own content' : 'Default content')) . ($sidebar_choice ? '' : ' (default)');
    $modes    = ['replace' => 'instead of the default', 'append' => 'after the default', 'prepend' => 'before the default'];

    $chip = fn($state, $text) => '<span class="lm-chip is-' . esc_attr($state) . '">' . esc_html($text) . '</span>';
    $open = fn($state) => $state === 'on' ? ' open' : '';

    wp_nonce_field('monospace_sidebar_override_nonce', 'monospace_sidebar_override_nonce_field');
    ?>
    <div class="lm-areas lm-compact">
        <!-- This page, as a map: what it overrides -->
        <figure class="lm-map" aria-label="This page's layout">
            <div class="lm-r lm-fixed">Menu bar</div>
            <button type="button" class="lm-r lm-area lm-logo is-<?php echo $hide_header ? 'off' : 'empty'; ?>" data-panel="header">Logo &amp; tagline</button>
            <button type="button" class="lm-r lm-area is-<?php echo esc_attr($t_state); ?>" data-panel="top">Top content</button>
            <div class="lm-cols">
                <div class="lm-main">
                    <button type="button" class="lm-r lm-area is-<?php echo $hide_title ? 'off' : 'empty'; ?>" data-panel="header">Title</button>
                    <div class="lm-r lm-fixed lm-content">Content</div>
                </div>
                <button type="button" class="lm-r lm-area lm-side is-<?php echo esc_attr($s_state); ?>" data-panel="sidebar">Sidebar</button>
            </div>
            <figcaption><span class="lm-key is-on"></span> this page's own <span class="lm-key is-empty"></span> default <span class="lm-key is-off"></span> hidden</figcaption>
        </figure>

        <div class="lm-panels">
            <!-- HEADER & TITLE -->
            <details class="lm-panel" data-panel="header"<?php echo $open($h_state); ?>>
                <summary><span class="lm-t">Header &amp; title</span> <?php echo $chip($h_state, $h_chip); ?></summary>
                <div class="lm-body">
                    <p><label><input type="checkbox" name="monospace_hide_header" value="1" <?php checked($hide_header, '1'); ?>> Hide the logo &amp; tagline</label></p>
                    <p><label><input type="checkbox" name="monospace_hide_title" value="1" <?php checked($hide_title, '1'); ?>> Hide the page / post title</label></p>
                </div>
            </details>

            <!-- TOP CONTENT -->
            <details class="lm-panel" data-panel="top"<?php echo $top_mode ? ' open' : ''; ?>>
                <summary><span class="lm-t">Top content</span> <?php echo $chip($t_state, $t_chip); ?>
                    <span class="lm-sub">Full width, under the logo<?php echo $top_mode === 'custom' && trim((string) $top_content) !== '' ? ' · ' . esc_html($preview($top_content)) : ''; ?></span></summary>
                <div class="lm-body">
                    <select name="monospace_top_mode" id="monospace_top_mode" style="width: 100%;"
                            onchange="document.getElementById('monospace_top_own').style.display = this.value === 'custom' ? '' : 'none';">
                        <option value="" <?php selected($top_mode, ''); ?>>Default<?php echo $top_def ? '' : ' (empty)'; ?> - Layout Manager</option>
                        <option value="custom" <?php selected($top_mode, 'custom'); ?>>Its own</option>
                        <option value="none" <?php selected($top_mode, 'none'); ?>>None</option>
                    </select>
                    <div id="monospace_top_own"<?php echo $top_mode === 'custom' ? '' : ' style="display: none;"'; ?>>
                        <textarea name="monospace_top_content" id="monospace_top_custom" rows="5" style="width: 100%; margin-top: 6px;" placeholder="Markdown (**bold**, *italic*, [link](url)), HTML and shortcodes allowed"><?php echo esc_textarea($top_content); ?></textarea>
                        <?php // How this banner looks: as in Look > Banners, or its own style, color, alignment, size - and dates, close button (design/banner-style.php)
                        if (function_exists('monospace_banner_fields')) monospace_banner_fields('monospace_top_style', monospace_banner_area($top_style), true, 'monospace_top_custom'); ?>
                    </div>
                    <p class="description"><a href="<?php echo esc_url($lm); ?>">The default for every <?php echo esc_html(rtrim($type, 's')); ?></a></p>
                </div>
            </details>

            <!-- SIDEBAR -->
            <details class="lm-panel" data-panel="sidebar"<?php echo ($sidebar_choice || $use_override) ? ' open' : ''; ?>>
                <summary><span class="lm-t">Sidebar</span> <?php echo $chip($s_state, $s_chip); ?>
                    <span class="lm-sub"><?php echo $use_override && $s_shown ? esc_html($modes[$override_mode] ?? '') . ($custom_content ? ' · ' . esc_html($preview($custom_content)) : '') : 'Right of the content (phones: above it)'; ?></span></summary>
                <div class="lm-body">
                    <p><label class="lm-label" for="monospace_sidebar_visibility">Show</label>
                        <select name="monospace_sidebar_visibility" id="monospace_sidebar_visibility">
                            <option value="" <?php selected($sidebar_choice, ''); ?>>Default (<?php echo $show_def ? 'shown' : 'hidden'; ?>)</option>
                            <option value="show" <?php selected($sidebar_choice, 'show'); ?>>Always show</option>
                            <option value="hide" <?php selected($sidebar_choice, 'hide'); ?>>Always hide</option>
                        </select></p>
                    <p><label><input type="checkbox" name="monospace_sidebar_override" value="1" class="lm-show-sidebar" <?php checked($use_override, '1'); ?>> Use a custom sidebar here</label></p>
                    <div class="lm-sidebar-fields"<?php echo $use_override ? '' : ' hidden'; ?>>
                        <p><label class="lm-label" for="monospace_override_mode">Where</label>
                            <select name="monospace_override_mode" id="monospace_override_mode">
                                <option value="replace" <?php selected($override_mode, 'replace'); ?>>Instead of the default sidebar</option>
                                <option value="append" <?php selected($override_mode, 'append'); ?>>After the default sidebar</option>
                                <option value="prepend" <?php selected($override_mode, 'prepend'); ?>>Before the default sidebar</option>
                            </select></p>
                        <?php
                        wp_editor($custom_content, 'monospace_custom_sidebar', [
                            'textarea_name' => 'monospace_custom_sidebar',
                            'media_buttons' => true,
                            'textarea_rows' => 8,
                            'teeny'         => false,
                            'quicktags'     => true,
                        ]);
                        ?>
                        <label class="lm-mirror"><input type="checkbox" name="monospace_mirror_sidebar" value="1" <?php checked($mirror_sidebar, '1'); ?>> Same on phones &amp; tablets</label>
                        <div class="lm-mobile"<?php echo $mirror_sidebar === '1' ? ' hidden' : ''; ?>>
                            <p class="lm-label">Sidebar on phones &amp; tablets <span class="description">(empty = the one above)</span></p>
                            <?php
                            wp_editor($mobile_content, 'monospace_mobile_sidebar', [
                                'textarea_name' => 'monospace_mobile_sidebar',
                                'media_buttons' => true,
                                'textarea_rows' => 8,
                                'teeny'         => false,
                                'quicktags'     => true,
                            ]);
                            ?>
                        </div>
                        <p class="description">HTML and shortcodes allowed - see Layout Manager → Shortcodes.</p>
                    </div>
                </div>
            </details>
        </div>
    </div>
    <style>
        /* The admin UI style in the editor's side column: map on top, panels below, one column */
        .lm-compact { max-width: none; }
        .lm-compact .lm-map { position: static; margin-bottom: 10px; }
        .lm-compact .lm-panel > summary { padding: 9px 10px; }
        .lm-compact .lm-body { padding: 10px; }
        .lm-compact .lm-label { min-width: 0; margin-right: 6px; }
        .lm-compact .lm-cols { grid-template-columns: 1fr 52px; }
        .lm-compact .lm-content { padding: 22px 8px; }
        .lm-compact select { max-width: 100%; }
        .lm-compact .lm-panels, .lm-compact .lm-panel, .lm-compact .lm-body { min-width: 0; max-width: 100%; box-sizing: border-box; }
        .lm-compact .lm-panel { overflow: hidden; }
        .lm-compact .lm-sub { white-space: normal; }
        .lm-compact .lm-body select, .lm-compact .lm-body textarea { width: 100%; }
    </style>
    <?php
    if (function_exists('monospace_lm_area_assets')) monospace_lm_area_assets();
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

    // Sidebar: '' = Layout Manager default, 'show' / 'hide' = override
    $visibility = in_array($_POST['monospace_sidebar_visibility'] ?? '', ['show', 'hide'], true) ? $_POST['monospace_sidebar_visibility'] : '';
    if ($visibility) {
        update_post_meta($post_id, '_monospace_sidebar_visibility', $visibility);
    } else {
        delete_post_meta($post_id, '_monospace_sidebar_visibility');
    }
    delete_post_meta($post_id, '_monospace_hide_sidebar'); // replaced by _monospace_sidebar_visibility

    // Top content: '' = Layout Manager default, 'custom' = own content, 'none' = no top content
    $top_mode = in_array($_POST['monospace_top_mode'] ?? '', ['custom', 'none'], true) ? $_POST['monospace_top_mode'] : '';
    if ($top_mode) {
        update_post_meta($post_id, '_monospace_top_mode', $top_mode);
    } else {
        delete_post_meta($post_id, '_monospace_top_mode');
    }
    if ($top_mode === 'custom') {
        update_post_meta($post_id, '_monospace_top_content', wp_kses_post(wp_unslash($_POST['monospace_top_content'] ?? '')));
        // ...and how it looks: its own style, color, alignment, size, dates, close (empty = as in Look) - as a category's
        if (isset($_POST['monospace_top_style']) && is_array($_POST['monospace_top_style']) && function_exists('monospace_banner_clean')) {
            $style = monospace_banner_clean(wp_unslash($_POST['monospace_top_style']), ['style' => '', 'color' => '', 'align' => '', 'size' => '', 'from' => '', 'until' => '', 'close' => '']);
            array_filter($style) ? update_post_meta($post_id, '_monospace_top_style', $style) : delete_post_meta($post_id, '_monospace_top_style');
        }
    } else {
        delete_post_meta($post_id, '_monospace_top_content');
        delete_post_meta($post_id, '_monospace_top_style');
    }

    if (isset($_POST['monospace_hide_title'])) {
        update_post_meta($post_id, '_monospace_hide_title', '1');
    } else {
        delete_post_meta($post_id, '_monospace_hide_title');
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

        // Art Feed, search results, 404: the type's own sidebar where it has one, else the Site default (layout-inherit.php)
        if (is_home())   return monospace_sidebar_from_options(monospace_layout_prefix('blog-feed', 'sidebar'));
        if (is_search()) return monospace_sidebar_from_options(monospace_layout_prefix('search', 'sidebar'));
        if (is_404())    return monospace_sidebar_from_options(monospace_layout_prefix('404', 'sidebar'));

        return '';
    }

    $post_type = get_post_type($post_id);
    if ($post_type === 'product') return ''; // product pages never show a sidebar
    $use_override = get_post_meta($post_id, '_monospace_use_sidebar_override', true);
    $override_mode = get_post_meta($post_id, '_monospace_override_mode', true) ?: 'replace';

    // Get default content for this post type
    $prefix = monospace_layout_prefix($post_type === 'post' ? 'posts' : 'pages', 'sidebar'); // Posts / Pages override, else the Site default
    $default_desktop = get_option($prefix . 'sidebar_desktop', '');
    $default_mobile = get_option($prefix . 'sidebar_mobile', '');
    $default_desktop = function_exists('monospace_line_breaks') ? monospace_line_breaks($default_desktop) : $default_desktop;
    $default_mobile  = function_exists('monospace_line_breaks') ? monospace_line_breaks($default_mobile) : $default_mobile;
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
    $custom_desktop = function_exists('monospace_line_breaks') ? monospace_line_breaks($custom_desktop) : $custom_desktop;
    $custom_mobile  = function_exists('monospace_line_breaks') ? monospace_line_breaks($custom_mobile) : $custom_mobile;
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
 * HELPER FUNCTION - CHECK IF TITLE SHOULD BE HIDDEN
 * ============================================================ */
function monospace_should_hide_title($post_id = null) {
    if (!$post_id) {
        $post_id = get_the_ID();
    }
    return get_post_meta($post_id, '_monospace_hide_title', true) === '1';
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

        $hide_title = get_post_meta(get_the_ID(), '_monospace_hide_title', true);
        if ($hide_title) {
            $classes[] = 'hide-title';
        }
    }

    if (function_exists('is_woocommerce') && is_woocommerce()) {
        $classes[] = 'wc-fullwidth';
    }

    if (! monospace_sidebar_visible()) {
        $classes[] = 'hide-sidebar';
    }

    return $classes;
});

/* ============================================================
 * SIDEBAR VISIBILITY
 * Layout Manager sets a default per page type ("Show the sidebar"); a post, page or
 * product can override it here ("Always show" / "Always hide").
 * ============================================================ */

/** The Layout Manager page type for the current request, or null */
function monospace_layout_context() {
    if (is_404()) return '404';
    if (is_search()) return 'search';
    if ((function_exists('is_woocommerce') && is_woocommerce()) || is_singular('product')) return 'products';
    if (is_singular('post')) return 'posts';
    if (is_singular('page')) return 'pages';
    if (is_home()) return 'blog-feed';
    if (is_archive()) return 'categories';
    return null;
}

/** Layout Manager default for a page type (products: hidden, like before) */
function monospace_sidebar_default_visible($context) {
    if ($context === 'products') return false; // product pages never show a sidebar
    $prefix = $context === 'site' ? 'monospace_site_' : monospace_layout_prefix($context, 'sidebar'); // the type's own, else the Site default
    return get_option($prefix . 'show_sidebar', '1') === '1';
}

/** A post's own choice: 'show', 'hide' or '' (use default). Reads the old "Hide sidebar" flag too. */
function monospace_sidebar_override_choice($post_id) {
    $choice = get_post_meta($post_id, '_monospace_sidebar_visibility', true);
    if ($choice === 'show' || $choice === 'hide') return $choice;
    return get_post_meta($post_id, '_monospace_hide_sidebar', true) ? 'hide' : '';
}

/**
 * The full-width top content area (index.php / 404.php, above .two-column-layout; the home page: start_template.php):
 * the post's own choice (Custom / None) first, else the Layout Manager default for the page type - the home page its
 * own (Layout Manager > Home Page). Drawn as a banner (design/banner-style.php: the page type's style, dates, close).
 */
function monospace_render_top_content() {
    $context = is_front_page() ? 'home' : monospace_layout_context();
    if (! $context || $context === 'products') return;

    $content = null;
    $style   = null;
    // A category / tag / place page: its own top content, or none (its edit screen - term-areas.php), else the shared one
    if (is_category() || is_tag() || is_tax()) {
        $term = get_queried_object();
        $mode = $term && ! empty($term->term_id) ? get_term_meta($term->term_id, '_monospace_top_mode', true) : '';
        if ($mode === 'none') return;
        if ($mode === 'custom') {
            $content = (string) get_term_meta($term->term_id, '_monospace_top_content', true);
            $style   = (array) get_term_meta($term->term_id, '_monospace_top_style', true);
        }
    }
    if ($context !== 'home' && is_singular(['post', 'page', 'product'])) {
        $mode = get_post_meta(get_the_ID(), '_monospace_top_mode', true);
        if ($mode === 'none') return;
        if ($mode === 'custom') {
            $content = get_post_meta(get_the_ID(), '_monospace_top_content', true);
            $style   = (array) get_post_meta(get_the_ID(), '_monospace_top_style', true); // its own style, dates, close (2026-10-22)
        }
    }
    if ($content === null) {
        $content = monospace_layout_option($context, 'top', 'top_content', ''); // the type's own, else the Site default
    }
    if (trim((string) $content) === '') return;

    $html = do_shortcode(wpautop(function_exists('monospace_markdown_lite') ? monospace_markdown_lite($content) : $content));
    $key  = monospace_layout_prefix($context, 'top') . 'top_style';
    echo function_exists('monospace_banner_wrap') ? monospace_banner_wrap($html, monospace_banner_area($style !== null ? $style : $key), $context) : '<div class="top-content-area">' . $html . '</div>';
}

function monospace_sidebar_visible() {
    $context = monospace_layout_context();
    if (! $context) return true;

    if (is_singular(['post', 'page', 'product'])) {
        $choice = monospace_sidebar_override_choice(get_the_ID());
        if ($choice) return $choice === 'show';
    }
    return monospace_sidebar_default_visible($context);
}
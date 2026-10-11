<?php
/**
 * Feed Headers System
 *
 * Manages headers and sidebars for blog feeds, categories, and search results.
 * Works with Layout Manager for site-wide defaults and per-category overrides.
 *
 * Key Functions:
 * - monospace_get_feed_header_description() - Retrieves header content with responsive support
 * - monospace_get_category_sidebar_content() - Retrieves sidebar content for archives/categories
 *
 * Related Files:
 * - layout-manager.php - Admin interface for configuring defaults
 * - sidebar-override.php - Per-post/page sidebar customization
 * - sidebar.php - Theme template that displays sidebar content
 * - _layout.scss - Responsive CSS for .content-desktop/.content-mobile visibility
 */



if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * CONFIGURATION
 * ============================================================ */

define( 'MONOSPACE_ARCHIVE_TAXONOMIES', [
    'category',
    'post_tag',
    'product_cat',
    'product_tag',
] );

/* ============================================================
 * TERM META FIELDS (CATEGORY EDIT SCREENS)
 * ============================================================ */

add_action( 'admin_init', function () {
    foreach ( MONOSPACE_ARCHIVE_TAXONOMIES as $taxonomy ) {
        add_action( "{$taxonomy}_edit_form_fields", 'monospace_render_term_fields', 10, 1 );
        add_action( "edited_{$taxonomy}", 'monospace_save_term_fields' );
    }
});

function monospace_render_term_fields( $term ) {
    $title = get_term_meta( $term->term_id, '_monospace_header_title', true );
    $desc_desktop = get_term_meta( $term->term_id, '_monospace_header_desc_desktop', true );
    $desc_mobile = get_term_meta( $term->term_id, '_monospace_header_desc_mobile', true );
    $location = get_term_meta( $term->term_id, '_monospace_header_location', true ) ?: 'top';
    $sidebar_desktop = get_term_meta( $term->term_id, '_monospace_sidebar_desktop', true );
    $sidebar_mobile = get_term_meta( $term->term_id, '_monospace_sidebar_mobile', true );

    // Mirror checkboxes - default to checked (1) if not set
    $mirror_desc = get_term_meta( $term->term_id, '_monospace_mirror_desc', true );
    $mirror_desc = ( $mirror_desc === '' ) ? '1' : $mirror_desc;
    $mirror_sidebar = get_term_meta( $term->term_id, '_monospace_mirror_sidebar', true );
    $mirror_sidebar = ( $mirror_sidebar === '' ) ? '1' : $mirror_sidebar;
    ?>

    <tr class="form-field">
        <th colspan="2"><h3 style="margin: 1.5em 0 0.5em;">Archive Header</h3></th>
    </tr>

    <tr class="form-field">
        <th scope="row"><label for="monospace-header-title">Header Title</label></th>
        <td>
            <input type="text" name="monospace_header_title" id="monospace-header-title"
                   value="<?php echo esc_attr( $title ); ?>" class="regular-text" />
            <p class="description">Custom title for this archive page.</p>
        </td>
    </tr>

    <tr class="form-field">
        <th scope="row"><label for="monospace-header-desc-desktop">Header Description (Desktop)</label></th>
        <td>
            <textarea name="monospace_header_desc_desktop" id="monospace-header-desc-desktop"
                      rows="6" class="large-text"><?php echo esc_textarea( $desc_desktop ); ?></textarea>
            <p class="description">HTML and shortcodes allowed.</p>
        </td>
    </tr>

    <tr class="form-field">
        <th scope="row"><label for="monospace-header-desc-mobile">Header Description (Mobile/Tablet)</label></th>
        <td>
            <textarea name="monospace_header_desc_mobile" id="monospace-header-desc-mobile"
                      rows="6" class="large-text"><?php echo esc_textarea( $desc_mobile ); ?></textarea>
            <p class="description">If empty, desktop version will be used. HTML and shortcodes allowed.</p>
        </td>
    </tr>

    <tr class="form-field">
        <th scope="row"></th>
        <td>
            <label>
                <input type="checkbox" name="monospace_mirror_desc" value="1" <?php checked( $mirror_desc, '1' ); ?> />
                Mirror desktop content to mobile (ignore mobile field above)
            </label>
        </td>
    </tr>

    <tr class="form-field">
        <th scope="row"><label for="monospace-header-location">Header Location</label></th>
        <td>
            <select name="monospace_header_location" id="monospace-header-location">
                <option value="top" <?php selected( $location, 'top' ); ?>>Above content</option>
                <option value="sidebar" <?php selected( $location, 'sidebar' ); ?>>In sidebar (use [archive_header])</option>
                <option value="none" <?php selected( $location, 'none' ); ?>>Don't display</option>
            </select>
        </td>
    </tr>

    <tr class="form-field">
        <th colspan="2"><h3 style="margin: 1.5em 0 0.5em;">Archive Sidebar</h3></th>
    </tr>

    <tr class="form-field">
        <th scope="row"><label for="monospace-sidebar-desktop">Sidebar Content (Desktop)</label></th>
        <td>
            <textarea name="monospace_sidebar_desktop" id="monospace-sidebar-desktop"
                      rows="10" class="large-text"><?php echo esc_textarea( $sidebar_desktop ); ?></textarea>
            <p class="description">Custom sidebar for this category. If empty, uses Blog Feed sidebar. HTML and shortcodes allowed.</p>
        </td>
    </tr>

    <tr class="form-field">
        <th scope="row"><label for="monospace-sidebar-mobile">Sidebar Content (Mobile/Tablet)</label></th>
        <td>
            <textarea name="monospace_sidebar_mobile" id="monospace-sidebar-mobile"
                      rows="10" class="large-text"><?php echo esc_textarea( $sidebar_mobile ); ?></textarea>
            <p class="description">If empty, desktop version will be used. HTML and shortcodes allowed.</p>
        </td>
    </tr>

    <tr class="form-field">
        <th scope="row"></th>
        <td>
            <label>
                <input type="checkbox" name="monospace_mirror_sidebar" value="1" <?php checked( $mirror_sidebar, '1' ); ?> />
                Mirror desktop content to mobile (ignore mobile field above)
            </label>
        </td>
    </tr>
    <?php
}

function monospace_save_term_fields( $term_id ) {
    // Save header fields
    if ( isset( $_POST['monospace_header_title'] ) ) {
        update_term_meta( $term_id, '_monospace_header_title', sanitize_text_field( $_POST['monospace_header_title'] ) );
    }
    if ( isset( $_POST['monospace_header_desc_desktop'] ) ) {
        update_term_meta( $term_id, '_monospace_header_desc_desktop', stripslashes( wp_kses_post( $_POST['monospace_header_desc_desktop'] ) ) );
    }
    if ( isset( $_POST['monospace_header_desc_mobile'] ) ) {
        update_term_meta( $term_id, '_monospace_header_desc_mobile', stripslashes( wp_kses_post( $_POST['monospace_header_desc_mobile'] ) ) );
    }
    if ( isset( $_POST['monospace_header_location'] ) ) {
        update_term_meta( $term_id, '_monospace_header_location', sanitize_text_field( $_POST['monospace_header_location'] ) );
    }

    // Save mirror checkboxes
    update_term_meta( $term_id, '_monospace_mirror_desc', isset( $_POST['monospace_mirror_desc'] ) ? '1' : '0' );
    update_term_meta( $term_id, '_monospace_mirror_sidebar', isset( $_POST['monospace_mirror_sidebar'] ) ? '1' : '0' );

    // Save sidebar fields
    if ( isset( $_POST['monospace_sidebar_desktop'] ) ) {
        update_term_meta( $term_id, '_monospace_sidebar_desktop', stripslashes( wp_kses_post( $_POST['monospace_sidebar_desktop'] ) ) );
    }
    if ( isset( $_POST['monospace_sidebar_mobile'] ) ) {
        update_term_meta( $term_id, '_monospace_sidebar_mobile', stripslashes( wp_kses_post( $_POST['monospace_sidebar_mobile'] ) ) );
    }
}

/* ============================================================
 * HELPER FUNCTIONS
 * ============================================================ */

function monospace_get_feed_header_description( $context = 'blog-feed', $term_id = null ) {
    if ( $term_id ) {
        // Category-specific settings
        $desktop = get_term_meta( $term_id, '_monospace_header_desc_desktop', true );
        $mobile = get_term_meta( $term_id, '_monospace_header_desc_mobile', true );
        $mirror = get_term_meta( $term_id, '_monospace_mirror_desc', true );
        if ( $mirror === '' ) $mirror = '1'; // Default to mirroring
    } else {
        // Layout Manager settings
        $prefix = 'monospace_' . str_replace( '-', '_', $context ) . '_';
        $desktop = get_option( $prefix . 'header_desc_desktop', '' );
        $mobile = get_option( $prefix . 'header_desc_mobile', '' );
        $mirror = get_option( $prefix . 'mirror_header', '' );
        if ( $mirror === '' ) $mirror = '1'; // Default to mirroring
    }

    // If mirror is ON: only output desktop content
    if ( $mirror === '1' ) {
        return $desktop;
    }

    // If mirror is OFF: output both with CSS classes for responsive display
    $output = '';
    if ( $desktop ) {
        $output .= '<div class="content-desktop">' . $desktop . '</div>';
    }
    if ( $mobile ) {
        $output .= '<div class="content-mobile">' . $mobile . '</div>';
    }

    return $output;
}

function monospace_get_category_sidebar_content( $term_id = null ) {
    if ( $term_id ) {
        // Check for category-specific sidebar
        $desktop = get_term_meta( $term_id, '_monospace_sidebar_desktop', true );
        $mobile = get_term_meta( $term_id, '_monospace_sidebar_mobile', true );
        $mirror = get_term_meta( $term_id, '_monospace_mirror_sidebar', true );
        if ( $mirror === '' ) $mirror = '1'; // Default to mirroring

        // If category has custom sidebar, use it
        if ( $desktop || $mobile ) {
            if ( $mirror === '1' ) {
                return do_shortcode( $desktop );
            }

            // Output both with CSS classes
            $output = '';
            if ( $desktop ) {
                $output .= '<div class="content-desktop">' . do_shortcode( $desktop ) . '</div>';
            }
            if ( $mobile ) {
                $output .= '<div class="content-mobile">' . do_shortcode( $mobile ) . '</div>';
            }
            return $output;
        }
    }

    // Fall back to blog feed sidebar
    $prefix = 'monospace_blog_feed_';
    $desktop = get_option( $prefix . 'sidebar_desktop', '' );
    $mobile = get_option( $prefix . 'sidebar_mobile', '' );
    $mirror = get_option( $prefix . 'mirror_sidebar', '' );
    if ( $mirror === '' ) $mirror = '1'; // Default to mirroring

    if ( $mirror === '1' ) {
        return do_shortcode( $desktop );
    }

    // Output both with CSS classes
    $output = '';
    if ( $desktop ) {
        $output .= '<div class="content-desktop">' . do_shortcode( $desktop ) . '</div>';
    }
    if ( $mobile ) {
        $output .= '<div class="content-mobile">' . do_shortcode( $mobile ) . '</div>';
    }
    return $output;
}

/* ============================================================
 * FRONTEND OUTPUT
 * ============================================================ */

add_action( 'loop_start', function ( $query ) {
    if ( ! $query->is_main_query() ) return;

    static $rendered = false;
    if ( $rendered ) return;
    $rendered = true;

    // Category/taxonomy archives
    if ( is_tax() || is_category() || is_tag() ) {
        $term = get_queried_object();
        if ( ! $term || ! in_array( $term->taxonomy, MONOSPACE_ARCHIVE_TAXONOMIES, true ) ) return;

        $location = get_term_meta( $term->term_id, '_monospace_header_location', true ) ?: 'top';
        if ( $location !== 'top' ) return;

        $title = get_term_meta( $term->term_id, '_monospace_header_title', true );
        // Default titles based on taxonomy type
        if ( ! $title ) {
            if ( is_tag() || $term->taxonomy === 'post_tag' ) {
                $title = 'Items tagged with: ' . $term->name;
            } else {
                $title = 'Items filed under: ' . $term->name;
            }
        }
        $desc = monospace_get_feed_header_description( 'categories', $term->term_id );

        if ( ! $title && ! $desc ) return;

        echo '<header class="feed-header">';
        if ( $title ) echo '<h2 class="feed-title">' . esc_html( $title ) . '</h2>';
        if ( $desc ) echo '<div class="feed-description">' . wp_kses_post( $desc ) . '</div>';
        echo '</header>';
        return;
    }

    // Blog feed
    if ( is_home() ) {
        $location = get_option( 'monospace_blog_feed_header_location', 'top' );
        if ( $location !== 'top' ) return;

        $title = get_option( 'monospace_blog_feed_header_title', '' );
        $desc = monospace_get_feed_header_description( 'blog-feed' );

        if ( ! $title && ! $desc ) return;

        echo '<header class="feed-header blog-feed-header">';
        if ( $title ) echo '<h2 class="feed-title">' . esc_html( $title ) . '</h2>';
        if ( $desc ) echo '<div class="feed-description">' . wp_kses_post( $desc ) . '</div>';
        echo '</header>';
    }

    // Search results
    if ( is_search() ) {
        $title = get_option( 'monospace_search_header_title', '' );
        $desc = monospace_get_feed_header_description( 'search' );

        // Replace {search_term} placeholder if present
        if ( $title ) {
            $title = str_replace( '{search_term}', get_search_query(), $title );
        } else {
            // Default to "Search results for: [query]" if no custom title set
            $title = 'Search results for: ' . get_search_query();
        }

        if ( ! $title && ! $desc ) return;

        echo '<header class="feed-header search-header">';
        if ( $title ) echo '<h2 class="feed-title">' . esc_html( $title ) . '</h2>';
        if ( $desc ) echo '<div class="feed-description">' . wp_kses_post( $desc ) . '</div>';
        echo '</header>';
    }
}, 5 );

/* ============================================================
 * SHORTCODE [archive_header]
 * ============================================================ */

add_shortcode( 'archive_header', function() {
    if ( is_admin() ) return '';

    // Category archives
    if ( is_tax() || is_category() || is_tag() ) {
        $term = get_queried_object();
        if ( ! $term || ! in_array( $term->taxonomy, MONOSPACE_ARCHIVE_TAXONOMIES, true ) ) return '';

        $location = get_term_meta( $term->term_id, '_monospace_header_location', true ) ?: 'top';
        if ( $location !== 'sidebar' ) return '';

        $title = get_term_meta( $term->term_id, '_monospace_header_title', true );
        // Default titles based on taxonomy type
        if ( ! $title ) {
            if ( is_tag() || $term->taxonomy === 'post_tag' ) {
                $title = 'Items tagged with: ' . $term->name;
            } else {
                $title = 'Items filed under: ' . $term->name;
            }
        }
        $desc = monospace_get_feed_header_description( 'categories', $term->term_id );

        if ( ! $title && ! $desc ) return '';

        $out = '<div class="feed-header">';
        if ( $title ) $out .= '<h3 class="feed-title">' . esc_html( $title ) . '</h3>';
        if ( $desc ) $out .= '<div class="feed-description">' . wp_kses_post( $desc ) . '</div>';
        $out .= '</div>';
        return $out;
    }

    // Blog feed
    if ( is_home() ) {
        $location = get_option( 'monospace_blog_feed_header_location', 'top' );
        if ( $location !== 'sidebar' ) return '';

        $title = get_option( 'monospace_blog_feed_header_title', '' );
        $desc = monospace_get_feed_header_description( 'blog-feed' );

        if ( ! $title && ! $desc ) return '';

        $out = '<div class="feed-header blog-feed-header">';
        if ( $title ) $out .= '<h3 class="feed-title">' . esc_html( $title ) . '</h3>';
        if ( $desc ) $out .= '<div class="feed-description">' . wp_kses_post( $desc ) . '</div>';
        $out .= '</div>';
        return $out;
    }

    return '';
});
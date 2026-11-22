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
        // The fields are drawn by editor/term-areas.php (2026-10-20, Layout Manager style); saved here
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
        update_term_meta( $term_id, '_monospace_header_title', wp_slash( monospace_title_clean( $_POST['monospace_header_title'] ) ) );
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

    // Save mirror checkboxes - only from the edit screen (term-areas.php sends monospace_term_fields): Quick Edit
    // in the list doesn't send them, and an unticked box isn't sent either, so it would switch both off
    if ( isset( $_POST['monospace_term_fields'] ) ) {
        update_term_meta( $term_id, '_monospace_mirror_desc', isset( $_POST['monospace_mirror_desc'] ) ? '1' : '0' );
        update_term_meta( $term_id, '_monospace_mirror_sidebar', isset( $_POST['monospace_mirror_sidebar'] ) ? '1' : '0' );
    }

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
        // Nothing of its own: the Site default's (layout-inherit.php)
        if ( ! $desktop && ! $mobile ) return monospace_get_feed_header_description( 'site' );
    } else {
        // Layout Manager settings: the page type's own, or the Site default's
        $prefix = monospace_layout_prefix( $context, 'header' );
        $desktop = get_option( $prefix . 'header_desc_desktop', '' );
        $mobile = get_option( $prefix . 'header_desc_mobile', '' );
        $mirror = get_option( $prefix . 'mirror_header', '' );
        if ( $mirror === '' ) $mirror = '1'; // Default to mirroring
    }

    $desktop = monospace_line_breaks( $desktop );
    $mobile  = monospace_line_breaks( $mobile );

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
        $desktop = monospace_line_breaks( $desktop );
        $mobile  = monospace_line_breaks( $mobile );
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

    // No sidebar of its own: the Site default (categories have no page type of their own - layout-inherit.php)
    return monospace_sidebar_from_options( 'monospace_site_' );
}

/* ============================================================
 * FRONTEND OUTPUT
 * ============================================================ */

// The title is the page's <h1> (styled as before in _layout.scss)
add_action( 'loop_start', function ( $query ) {
    if ( ! $query->is_main_query() ) return;

    static $rendered = false;
    if ( $rendered ) return;
    $rendered = true;

    // Category/taxonomy archives
    if ( is_tax() || is_category() || is_tag() ) {
        $term = get_queried_object();
        if ( ! $term || ! in_array( $term->taxonomy, MONOSPACE_ARCHIVE_TAXONOMIES, true ) ) return;

        $location = get_term_meta( $term->term_id, '_monospace_header_location', true ) ?: monospace_layout_option( 'categories', 'header', 'header_location', 'top' );
        if ( $location !== 'top' ) return;

        $title = get_term_meta( $term->term_id, '_monospace_header_title', true );
        // The Site default's title ({name} = this category), else a title made from the kind of page
        if ( ! $title ) {
            $site_title = (string) monospace_layout_option( 'categories', 'header', 'header_title', '' );
            if ( $site_title !== '' ) $title = str_replace( '{name}', $term->name, $site_title );
        }
        if ( ! $title ) {
            if ( is_tag() || $term->taxonomy === 'post_tag' ) {
                $title = 'Items tagged with: ' . $term->name;
            } else {
                $title = 'Items filed under: ' . $term->name;
            }
        }
        $desc = monospace_get_feed_header_description( 'categories', $term->term_id );

        if ( ! $title && ! $desc ) return;

        monospace_feed_header_print( '', $title, $desc );
        return;
    }

    // Blog feed
    if ( is_home() ) {
        $location = monospace_layout_option( 'blog-feed', 'header', 'header_location', 'top' );
        if ( $location !== 'top' ) return;

        $title = monospace_layout_option( 'blog-feed', 'header', 'header_title', '' );
        $desc = monospace_get_feed_header_description( 'blog-feed' );

        if ( ! $title && ! $desc ) return;

        monospace_feed_header_print( 'blog-feed-header', $title, $desc );
    }

    // Search results
    if ( is_search() ) {
        $title = monospace_layout_option( 'search', 'header', 'header_title', '' );
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
        if ( $title ) { echo '<h1 class="feed-title">' . monospace_title_html( $title ) . '</h1>'; $GLOBALS['monospace_feed_h1'] = true; do_action( 'monospace_feed_title_after', wp_strip_all_tags( str_replace( '\n', ' ', $title ) ) ); } // e.g. the share button (design/share-button.php)
        if ( $desc ) echo '<div class="feed-description">' . wp_kses_post( $desc ) . '</div>';
        echo '</header>';
    }
}, 5 );

// A visitor's folded / open choice carries over to every category page and the Art Feed, like the View settings (the same kind of browser memory):
// a class on <html> set before the page paints, so a folded header never flashes open (assets/scripts/header-collapse.js keeps it)
add_action( 'wp_head', function () {
    if ( ! ( is_category() || is_tag() || is_tax() || is_home() ) ) return;
    echo '<script>try{if(localStorage.getItem("monospace_header_folded")==="1")document.documentElement.classList.add("ms-header-folded")}catch(e){}</script>' . "\n";
}, 1 );

/**
 * A page's header card: title (with the share button beside it), description. A header with a title and a description folds - the
 * category / tag / place pages' and the Art Feed's (2026-10-23, owner): the title is the toggle (a chevron says so), the
 * description folds away, the share / social icons beside the title stay, and the visitor's choice carries over (header-collapse.js).
 * Without a title or without a description it stays as it is.
 */
function monospace_feed_header_print( $extra_class, $title, $desc ) {
    $collapsible = $title && $desc;
    if ( $collapsible ) wp_enqueue_script( 'ms-header-collapse', get_template_directory_uri() . '/assets/scripts/header-collapse.js', [], (string) filemtime( get_template_directory() . '/assets/scripts/header-collapse.js' ), true );

    echo '<header class="' . esc_attr( trim( 'feed-header ' . $extra_class . ( $collapsible ? ' is-collapsible' : '' ) ) ) . '">';
    if ( $title ) {
        echo '<h1 class="feed-title">' . ( $collapsible ? monospace_feed_toggle_html( $title ) : monospace_title_html( $title ) ) . '</h1>';
        $GLOBALS['monospace_feed_h1'] = true;
        do_action( 'monospace_feed_title_after', wp_strip_all_tags( str_replace( '\n', ' ', $title ) ) ); // e.g. the share button (design/share-button.php)
    }
    if ( $desc ) {
        $html = '<div class="feed-description">' . wp_kses_post( $desc ) . '</div>';
        echo $collapsible ? '<div class="feed-description-wrap" id="feed-header-body">' . $html . '</div>' : $html;
    }
    echo '</header>';
}

/** A collapsible header's title: a link with role="button" (like the share icon - the site's button style leaves it alone) and a chevron */
function monospace_feed_toggle_html( $title ) {
    $chevron = '<svg class="feed-toggle-icon" viewBox="0 0 12 12" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.5 4.25 6 7.75l3.5-3.5"/></svg>';
    return '<a href="#" class="feed-toggle" role="button" aria-expanded="true" aria-controls="feed-header-body" title="Hide the description"><span class="feed-toggle-text">' . monospace_title_html( $title ) . '</span>' . $chevron . '</a>';
}

/* ============================================================
 * SHORTCODE [archive_header]
 * ============================================================ */

add_shortcode( 'archive_header', function() {
    if ( is_admin() ) return '';

    // Category archives
    if ( is_tax() || is_category() || is_tag() ) {
        $term = get_queried_object();
        if ( ! $term || ! in_array( $term->taxonomy, MONOSPACE_ARCHIVE_TAXONOMIES, true ) ) return '';

        $location = get_term_meta( $term->term_id, '_monospace_header_location', true ) ?: monospace_layout_option( 'categories', 'header', 'header_location', 'top' );
        if ( $location !== 'sidebar' ) return '';

        $title = get_term_meta( $term->term_id, '_monospace_header_title', true );
        // The Site default's title ({name} = this category), else a title made from the kind of page
        if ( ! $title ) {
            $site_title = (string) monospace_layout_option( 'categories', 'header', 'header_title', '' );
            if ( $site_title !== '' ) $title = str_replace( '{name}', $term->name, $site_title );
        }
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
        if ( $title ) $out .= '<h3 class="feed-title">' . monospace_title_html( $title ) . '</h3>';
        if ( $desc ) $out .= '<div class="feed-description">' . wp_kses_post( $desc ) . '</div>';
        $out .= '</div>';
        return $out;
    }

    // Blog feed
    if ( is_home() ) {
        $location = monospace_layout_option( 'blog-feed', 'header', 'header_location', 'top' );
        if ( $location !== 'sidebar' ) return '';

        $title = monospace_layout_option( 'blog-feed', 'header', 'header_title', '' );
        $desc = monospace_get_feed_header_description( 'blog-feed' );

        if ( ! $title && ! $desc ) return '';

        $out = '<div class="feed-header blog-feed-header">';
        if ( $title ) $out .= '<h3 class="feed-title">' . monospace_title_html( $title ) . '</h3>';
        if ( $desc ) $out .= '<div class="feed-description">' . wp_kses_post( $desc ) . '</div>';
        $out .= '</div>';
        return $out;
    }

    return '';
});
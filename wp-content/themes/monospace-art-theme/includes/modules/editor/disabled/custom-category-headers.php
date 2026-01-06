<?php
/**
 * Monospace – Custom Archive Headers & Sidebars
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * CONFIGURATION
 * ============================================================ */

define( 'MONOSPACE_ARCHIVE_TAXONOMIES', [
    'category',
    'product_cat',
    'product_tag',
] );

define( 'MONOSPACE_TERM_HEADER_TITLE', '_monospace_header_title' );
define( 'MONOSPACE_TERM_HEADER_DESC',  '_monospace_header_description' );
define( 'MONOSPACE_TERM_HEADER_DESC_MOBILE',  '_monospace_header_description_mobile' );
define( 'MONOSPACE_TERM_HEADER_LOCATION', '_monospace_header_location' );

define( 'MONOSPACE_BLOG_HEADER_TITLE', 'monospace_blog_header_title' );
define( 'MONOSPACE_BLOG_HEADER_DESC',  'monospace_blog_header_description' );
define( 'MONOSPACE_BLOG_HEADER_DESC_MOBILE',  'monospace_blog_header_description_mobile' );
define( 'MONOSPACE_BLOG_HEADER_LOCATION', 'monospace_blog_header_location' );

/* ============================================================
 * ADMIN UI — TERM EDIT SCREENS
 * ============================================================ */

add_action( 'admin_init', function () {

    foreach ( MONOSPACE_ARCHIVE_TAXONOMIES as $taxonomy ) {

        add_action( "{$taxonomy}_edit_form_fields", function ( $term ) {

            $title = get_term_meta( $term->term_id, MONOSPACE_TERM_HEADER_TITLE, true );
            $desc  = get_term_meta( $term->term_id, MONOSPACE_TERM_HEADER_DESC, true );
            $desc_mobile  = get_term_meta( $term->term_id, MONOSPACE_TERM_HEADER_DESC_MOBILE, true );
            $location = get_term_meta( $term->term_id, MONOSPACE_TERM_HEADER_LOCATION, true ) ?: 'top';
            ?>

            <tr class="form-field">
                <th scope="row">
                    <label for="monospace-header-title">Category Header Title</label>
                </th>
                <td>
                    <input
                        type="text"
                        name="monospace_header_title"
                        id="monospace-header-title"
                        value="<?php echo esc_attr( $title ); ?>"
                        class="regular-text"
                    />
                    <p class="description">Overrides the category title for this term.</p>
                </td>
            </tr>

            <tr class="form-field">
                <th scope="row">
                    <label for="monospace-header-description">Desktop Header Description</label>
                </th>
                <td>
                    <textarea
                        name="monospace_header_description"
                        id="monospace-header-description"
                        rows="10"
                        class="large-text"
                    ><?php echo esc_textarea( $desc ); ?></textarea>
                    <p class="description">Appears with the archive title on desktop. HTML allowed.</p>
                </td>
            </tr>

            <tr class="form-field">
                <th scope="row">
                    <label for="monospace-header-description-mobile">Mobile & Tablet Header Description</label>
                </th>
                <td>
                    <textarea
                        name="monospace_header_description_mobile"
                        id="monospace-header-description-mobile"
                        rows="10"
                        class="large-text"
                    ><?php echo esc_textarea( $desc_mobile ); ?></textarea>
                    <p class="description">Appears on mobile/tablet devices. If empty, desktop description will be used. HTML allowed.</p>
                </td>
            </tr>

            <tr class="form-field">
                <th scope="row">
                    <label for="monospace-header-location">Header Display Location</label>
                </th>
                <td>
                    <select name="monospace_header_location" id="monospace-header-location">
                        <option value="top" <?php selected( $location, 'top' ); ?>>Above content</option>
                        <option value="block" <?php selected( $location, 'block' ); ?>>As block (use shortcode)</option>
                        <option value="none" <?php selected( $location, 'none' ); ?>>Don't display</option>
                    </select>
                    <p class="description">
                        Choose "Above content" for automatic display, or "As block" to use the shortcode
                        <code>[archive_header]</code> anywhere in your content or sidebar.
                    </p>
                </td>
            </tr>

            <?php
        }, 10, 1 );

        add_action( "edited_{$taxonomy}", function ( $term_id ) {

            if ( isset( $_POST['monospace_header_title'] ) ) {
                update_term_meta( $term_id, MONOSPACE_TERM_HEADER_TITLE, sanitize_text_field( $_POST['monospace_header_title'] ) );
            }

            if ( isset( $_POST['monospace_header_description'] ) ) {
                update_term_meta( $term_id, MONOSPACE_TERM_HEADER_DESC, wp_kses_post( $_POST['monospace_header_description'] ) );
            }

            if ( isset( $_POST['monospace_header_description_mobile'] ) ) {
                update_term_meta( $term_id, MONOSPACE_TERM_HEADER_DESC_MOBILE, wp_kses_post( $_POST['monospace_header_description_mobile'] ) );
            }

            if ( isset( $_POST['monospace_header_location'] ) ) {
                update_term_meta( $term_id, MONOSPACE_TERM_HEADER_LOCATION, sanitize_text_field( $_POST['monospace_header_location'] ) );
            }

        } );
    }
});

/* ============================================================
 * ADMIN UI — BLOG FEED SETTINGS
 * ============================================================ */

add_action( 'admin_menu', function () {
    add_options_page(
        'Blog Feed Header',
        'Blog Feed Header',
        'manage_options',
        'monospace-blog-header',
        'monospace_blog_header_settings_page'
    );
} );

function monospace_blog_header_settings_page() {

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['monospace_blog_header_submit'] ) ) {
        check_admin_referer( 'monospace_blog_header_settings' );

        update_option( MONOSPACE_BLOG_HEADER_TITLE, sanitize_text_field( $_POST['blog_header_title'] ?? '' ) );
        update_option( MONOSPACE_BLOG_HEADER_DESC, wp_kses_post( $_POST['blog_header_description'] ?? '' ) );
        update_option( MONOSPACE_BLOG_HEADER_DESC_MOBILE, wp_kses_post( $_POST['blog_header_description_mobile'] ?? '' ) );
        update_option( MONOSPACE_BLOG_HEADER_LOCATION, sanitize_text_field( $_POST['blog_header_location'] ?? 'top' ) );

        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    $title = get_option( MONOSPACE_BLOG_HEADER_TITLE, '' );
    $desc  = get_option( MONOSPACE_BLOG_HEADER_DESC, '' );
    $desc_mobile  = get_option( MONOSPACE_BLOG_HEADER_DESC_MOBILE, '' );
    $location = get_option( MONOSPACE_BLOG_HEADER_LOCATION, 'top' );
    ?>

    <div class="wrap">
        <h1>Blog Feed Header</h1>
        <p>Customize the header for your main blog feed (home page).</p>

        <form method="post" action="">
            <?php wp_nonce_field( 'monospace_blog_header_settings' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="blog-header-title">Header Title</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            name="blog_header_title"
                            id="blog-header-title"
                            value="<?php echo esc_attr( $title ); ?>"
                            class="regular-text"
                        />
                        <p class="description">Leave empty to hide the header title.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="blog-header-description">Desktop Header Description</label>
                    </th>
                    <td>
                        <textarea
                            name="blog_header_description"
                            id="blog-header-description"
                            rows="10"
                            class="large-text"
                        ><?php echo esc_textarea( $desc ); ?></textarea>
                        <p class="description">Appears with the title on desktop. HTML allowed.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="blog-header-description-mobile">Mobile & Tablet Header Description</label>
                    </th>
                    <td>
                        <textarea
                            name="blog_header_description_mobile"
                            id="blog-header-description-mobile"
                            rows="10"
                            class="large-text"
                        ><?php echo esc_textarea( $desc_mobile ); ?></textarea>
                        <p class="description">Appears on mobile/tablet devices. If empty, desktop description will be used. HTML allowed.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="blog-header-location">Header Display Location</label>
                    </th>
                    <td>
                        <select name="blog_header_location" id="blog-header-location">
                            <option value="top" <?php selected( $location, 'top' ); ?>>Above content</option>
                            <option value="block" <?php selected( $location, 'block' ); ?>>As block (use shortcode)</option>
                            <option value="none" <?php selected( $location, 'none' ); ?>>Don't display</option>
                        </select>
                        <p class="description">
                            Choose "Above content" for automatic display, or "As block" to use the shortcode
                            <code>[archive_header]</code> anywhere in your content or sidebar.
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input
                    type="submit"
                    name="monospace_blog_header_submit"
                    class="button button-primary"
                    value="Save Settings"
                />
            </p>
        </form>
    </div>

    <?php
}

/* ============================================================
 * HELPER FUNCTION — GET APPROPRIATE DESCRIPTION
 * ============================================================ */
function monospace_get_archive_header_description( $term_id = null ) {
    if ( $term_id ) {
        $desktop = get_term_meta( $term_id, MONOSPACE_TERM_HEADER_DESC, true );
        $mobile = get_term_meta( $term_id, MONOSPACE_TERM_HEADER_DESC_MOBILE, true );
    } else {
        $desktop = get_option( MONOSPACE_BLOG_HEADER_DESC, '' );
        $mobile = get_option( MONOSPACE_BLOG_HEADER_DESC_MOBILE, '' );
    }

    // If mobile version exists, output both wrapped in classes
    if ( $mobile ) {
        return '<div class="desktop-content">' . $desktop . '</div><div class="mobile-content">' . $mobile . '</div>';
    }

    return $desktop;
}
/* ============================================================
 * FRONTEND OUTPUT — ABOVE CONTENT
 * ============================================================ */

add_action( 'loop_start', function ( $query ) {

    if ( ! $query->is_main_query() ) {
        return;
    }

    static $rendered = false;
    if ( $rendered ) {
        return;
    }
    $rendered = true;

    // Taxonomy archives
    if ( is_tax() || is_category() || is_tag() ) {

        $term = get_queried_object();

        if (
            ! $term ||
            empty( $term->taxonomy ) ||
            ! in_array( $term->taxonomy, MONOSPACE_ARCHIVE_TAXONOMIES, true )
        ) {
            return;
        }

        $location = get_term_meta( $term->term_id, MONOSPACE_TERM_HEADER_LOCATION, true ) ?: 'top';

        if ( $location !== 'top' ) {
            return;
        }

        $title = get_term_meta( $term->term_id, MONOSPACE_TERM_HEADER_TITLE, true );
        $desc  = monospace_get_archive_header_description( $term->term_id );

        if ( ! $title && ! $desc ) {
            return;
        }

        echo '<header class="archive-custom-header">';
        if ( $title ) {
            echo '<h2 class="archive-title">' . esc_html( $title ) . '</h2>';
        }
        if ( $desc ) {
            echo '<div class="archive-description">' . wp_kses_post( $desc ) . '</div>';
        }
        echo '</header>';

        return;
    }

    // Blog feed
    if ( is_home() ) {

        $location = get_option( MONOSPACE_BLOG_HEADER_LOCATION, 'top' );

        if ( $location !== 'top' ) {
            return;
        }

        $title = get_option( MONOSPACE_BLOG_HEADER_TITLE, '' );
        $desc  = monospace_get_archive_header_description();

        if ( ! $title && ! $desc ) {
            return;
        }

        echo '<header class="archive-custom-header blog-feed-header">';
        if ( $title ) {
            echo '<h2 class="archive-title">' . esc_html( $title ) . '</h2>';
        }
        if ( $desc ) {
            echo '<div class="archive-description">' . wp_kses_post( $desc ) . '</div>';
        }
        echo '</header>';
    }

}, 5 );

/* ============================================================
 * SHORTCODE — [archive_header]
 * ============================================================ */

add_shortcode( 'archive_header', function() {

    if ( is_admin() ) {
        return '';
    }

    // Taxonomy archives
    if ( is_tax() || is_category() || is_tag() ) {

        $term = get_queried_object();

        if (
            ! $term ||
            empty( $term->taxonomy ) ||
            ! in_array( $term->taxonomy, MONOSPACE_ARCHIVE_TAXONOMIES, true )
        ) {
            return '';
        }

        $location = get_term_meta( $term->term_id, MONOSPACE_TERM_HEADER_LOCATION, true ) ?: 'top';
        if ( $location !== 'block' ) {
            return '';
        }

        $title = get_term_meta( $term->term_id, MONOSPACE_TERM_HEADER_TITLE, true );
        $desc  = monospace_get_archive_header_description( $term->term_id );

        if ( ! $title && ! $desc ) {
            return '';
        }

        $out  = '<div class="archive-custom-header">';
        if ( $title ) {
            $out .= '<h3 class="archive-title">' . esc_html( $title ) . '</h3>';
        }
        if ( $desc ) {
            $out .= '<div class="archive-description">' . wp_kses_post( $desc ) . '</div>';
        }
        $out .= '</div>';

        return $out;
    }

    // Blog feed
    if ( is_home() ) {

        $location = get_option( MONOSPACE_BLOG_HEADER_LOCATION, 'top' );
        if ( $location !== 'block' ) {
            return '';
        }

        $title = get_option( MONOSPACE_BLOG_HEADER_TITLE, '' );
        $desc  = monospace_get_archive_header_description();

        if ( ! $title && ! $desc ) {
            return '';
        }

        $out  = '<div class="archive-custom-header blog-feed-header">';
        if ( $title ) {
            $out .= '<h3 class="archive-title">' . esc_html( $title ) . '</h3>';
        }
        if ( $desc ) {
            $out .= '<div class="archive-description">' . wp_kses_post( $desc ) . '</div>';
        }
        $out .= '</div>';

        return $out;
    }

    return '';
});
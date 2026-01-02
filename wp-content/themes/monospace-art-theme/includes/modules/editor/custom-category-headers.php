<?php
/**
 * Monospace – Custom Archive Headers (Taxonomy-Agnostic + Blog Feed)
 *
 * Drop-in module with visual editors
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ============================================================
 * CONFIGURATION
 * ============================================================ */

/**
 * Taxonomies that support custom archive headers
 */
define( 'MONOSPACE_ARCHIVE_TAXONOMIES', [
    'category',
    'product_cat',
    'product_tag',
] );

/**
 * Term meta keys
 */
define( 'MONOSPACE_TERM_HEADER_TITLE', '_monospace_header_title' );
define( 'MONOSPACE_TERM_HEADER_DESC',  '_monospace_header_description' );

/**
 * Blog feed option keys
 */
define( 'MONOSPACE_BLOG_HEADER_TITLE', 'monospace_blog_header_title' );
define( 'MONOSPACE_BLOG_HEADER_DESC',  'monospace_blog_header_description' );

/* ============================================================
 * ADMIN UI — TERM EDIT SCREENS
 * ============================================================ */

add_action( 'admin_init', function () {

    foreach ( MONOSPACE_ARCHIVE_TAXONOMIES as $taxonomy ) {

        /**
         * Edit form fields
         */
        add_action( "{$taxonomy}_edit_form_fields", function ( $term ) {

            $title = get_term_meta( $term->term_id, MONOSPACE_TERM_HEADER_TITLE, true );
            $desc  = get_term_meta( $term->term_id, MONOSPACE_TERM_HEADER_DESC, true );
            ?>

            <tr class="form-field term-header-title-wrap">
                <th scope="row">
                    <label for="monospace-header-title">Archive Header Title</label>
                </th>
                <td>
                    <input
                        type="text"
                        name="monospace_header_title"
                        id="monospace-header-title"
                        value="<?php echo esc_attr( $title ); ?>"
                        class="regular-text"
                    />
                    <p class="description">
                        Overrides the archive title for this term.
                    </p>
                </td>
            </tr>

            <tr class="form-field term-header-description-wrap">
                <th scope="row">
                    <label for="monospace-header-description">Archive Header Description</label>
                </th>
                <td>
                    <?php
                    wp_editor( $desc, 'monospace_header_description', [
                        'textarea_name' => 'monospace_header_description',
                        'textarea_rows' => 10,
                        'media_buttons' => true,
                        'teeny'         => false,
                        'tinymce'       => true,
                        'quicktags'     => true,
                    ] );
                    ?>
                    <p class="description">
                        Appears below the archive title.
                    </p>
                </td>
            </tr>

            <?php
        }, 10, 1 );

        /**
         * Save term meta
         */
        add_action( "edited_{$taxonomy}", function ( $term_id ) {

            if ( isset( $_POST['monospace_header_title'] ) ) {
                update_term_meta(
                    $term_id,
                    MONOSPACE_TERM_HEADER_TITLE,
                    sanitize_text_field( $_POST['monospace_header_title'] )
                );
            }

            if ( isset( $_POST['monospace_header_description'] ) ) {
                update_term_meta(
                    $term_id,
                    MONOSPACE_TERM_HEADER_DESC,
                    wp_kses_post( $_POST['monospace_header_description'] )
                );
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

    // Save settings
    if ( isset( $_POST['monospace_blog_header_submit'] ) ) {
        check_admin_referer( 'monospace_blog_header_settings' );

        update_option( MONOSPACE_BLOG_HEADER_TITLE, sanitize_text_field( $_POST['blog_header_title'] ?? '' ) );
        update_option( MONOSPACE_BLOG_HEADER_DESC, wp_kses_post( $_POST['blog_header_description'] ?? '' ) );

        echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
    }

    $title = get_option( MONOSPACE_BLOG_HEADER_TITLE, '' );
    $desc  = get_option( MONOSPACE_BLOG_HEADER_DESC, '' );
    ?>

    <div class="wrap">
        <h1>Blog Feed Header</h1>
        <p>Customize the header that appears at the top of your main blog feed (home page).</p>

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
                        <p class="description">
                            Leave empty to hide the header title on the blog feed.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="blog-header-description">Header Description</label>
                    </th>
                    <td>
                        <?php
                        wp_editor( $desc, 'blog_header_description', [
                            'textarea_name' => 'blog_header_description',
                            'textarea_rows' => 10,
                            'media_buttons' => true,
                            'teeny'         => false,
                            'tinymce'       => true,
                            'quicktags'     => true,
                        ] );
                        ?>
                        <p class="description">
                            Appears below the title.
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
 * FRONTEND OUTPUT — BEFORE LOOP (THEME-AGNOSTIC)
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

    // Handle taxonomy archives
    if ( is_tax() || is_category() || is_tag() ) {

        $term = get_queried_object();

        if (
            ! $term ||
            empty( $term->taxonomy ) ||
            ! in_array( $term->taxonomy, MONOSPACE_ARCHIVE_TAXONOMIES, true )
        ) {
            return;
        }

        $title = get_term_meta( $term->term_id, MONOSPACE_TERM_HEADER_TITLE, true );
        $desc  = get_term_meta( $term->term_id, MONOSPACE_TERM_HEADER_DESC, true );

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

    // Handle main blog feed (home page)
    if ( is_home() ) {

        $title = get_option( MONOSPACE_BLOG_HEADER_TITLE, '' );
        $desc  = get_option( MONOSPACE_BLOG_HEADER_DESC, '' );

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
<?php
/**
 * Monospace – Custom Archive Headers (Taxonomy-Agnostic)
 *
 * Drop-in module
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

            <tr class="form-field">
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

            <tr class="form-field">
                <th scope="row">
                    <label for="monospace-header-description">Archive Header Description</label>
                </th>
                <td>
                    <textarea
                        name="monospace_header_description"
                        id="monospace-header-description"
                        rows="5"
                        class="large-text"
                    ><?php echo esc_textarea( $desc ); ?></textarea>
                    <p class="description">
                        HTML allowed. Appears below the archive title.
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
 * FRONTEND OUTPUT — BEFORE LOOP (THEME-AGNOSTIC)
 * ============================================================ */

add_action( 'loop_start', function ( $query ) {

    if ( ! $query->is_main_query() ) {
        return;
    }

    if ( ! is_tax() && ! is_category() && ! is_tag() ) {
        return;
    }

    static $rendered = false;
    if ( $rendered ) {
        return;
    }
    $rendered = true;

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
        echo '<div class="archive-description">' . $desc . '</div>';
    }

    echo '</header>';

}, 5 );

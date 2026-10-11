<?php
/**
 * Password Protection Customization
 *
 * Customizes WordPress's default password protection form to provide a cleaner,
 * simpler user experience for password-protected posts and pages.
 *
 * FUNCTIONALITY:
 * - Removes post title from password form (for privacy)
 * - Simplifies the form text ("This content is password protected")
 * - Maintains WordPress's secure password cookie system
 * - Works with all post types (posts, pages, products)
 *
 * USE CASE:
 * When you set a post/page to "Password Protected" in WordPress, this module
 * customizes the form visitors see to make it cleaner and more professional.
 */

// Hide the title from the password form
add_filter( 'the_password_form', function() {

    global $post;

    $label = 'pwbox-' . ( empty( $post->ID ) ? wp_rand() : $post->ID );

    return sprintf(
        '
        <form class="post-password-form" action="%s" method="post">

            <style>
                .post-password-form .password-row {
                    display: flex;
                    gap: 0.75rem;
                    align-items: center;
                }
                h1.entry-title {
                    display: none;
                }
            </style>

            <p>%s</p>

            <div class="password-row">
                <label for="%s" class="screen-reader-text">%s</label>

                <input
                    name="post_password"
                    id="%s"
                    type="password"
                    spellcheck="false"
                    required
                >

                <button type="submit" class="pw-submit"><span class="pw-submit-label">%s</span></button>
            </div>

        </form>
        ',
        esc_url( site_url( 'wp-login.php?action=postpass', 'login_post' ) ),
        esc_html__( 'This content is password-protected. To view it, please enter the password below.' ),
        esc_attr( $label ),
        esc_html__( 'Password' ),
        esc_attr( $label ),
        esc_html__( 'Enter' )
    );
});

/* hide "Protected: " and "Private: " from titles */
add_filter( 'protected_title_format', fn() => '%s' );
add_filter( 'private_title_format',   fn() => '%s' );

/* exclude from search */
add_filter('relevanssi_do_not_index', function($block, $post_id) {
    $post = get_post($post_id);
    if (!empty($post->post_password)) {
        return true;
    }
    return $block;
}, 10, 2);
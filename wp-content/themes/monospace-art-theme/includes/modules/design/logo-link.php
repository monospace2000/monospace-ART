<?php
/**
 * Design module: Logo link (2026-10-16)
 *
 * Where the big logo above the content (header.php) takes a visitor, and its hover text. Default:
 * Available Originals, "View available originals" (the owner's choice of 2026-10-15; before that it
 * went to the Art Feed). A site path ("/category/available/") or a full URL.
 * Shown in Layout Manager > Appearance & Effects through the design-module registry.
 *
 * Options: monospace_logo_link, monospace_logo_title.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['logo-link'] = [
        'title'       => 'Logo link',
        'description' => 'Where clicking the big logo above the content goes.',
        'group'       => 'links',
        'order'       => 10,
        'summary'     => function () { return (string) get_option( 'monospace_logo_link', '/category/available/' ); },
        'render'      => 'monospace_render_logo_link_settings',
        'save'        => 'monospace_save_logo_link_settings',
    ];
    return $modules;
} );

/** The logo's link (absolute URL) and hover text */
function monospace_logo_link() {
    $link = (string) get_option( 'monospace_logo_link', '/category/available/' );
    if ( $link === '' ) $link = '/';
    return [
        'url'   => $link[0] === '/' ? home_url( $link ) : $link,
        'title' => (string) get_option( 'monospace_logo_title', 'View available originals' ),
    ];
}

function monospace_render_logo_link_settings() {
    $link  = (string) get_option( 'monospace_logo_link', '/category/available/' );
    $title = (string) get_option( 'monospace_logo_title', 'View available originals' );
    $choices = function_exists( 'monospace_home_link_choices' ) ? monospace_home_link_choices() : []; ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="ms-logo-link">Link</label></th>
            <td>
                <input type="text" name="logo_link" id="ms-logo-link" class="regular-text" value="<?php echo esc_attr( $link ); ?>" placeholder="/category/available/ or https://…">
                <?php if ( $choices ) : ?>
                    <select id="ms-logo-link-pick" style="max-width:320px">
                        <option value="">Pick a page, category or place…</option>
                        <option value="/">Home page</option>
                        <?php foreach ( $choices as $group => $items ) : ?>
                            <optgroup label="<?php echo esc_attr( $group ); ?>">
                                <?php foreach ( $items as [ $label, $url ] ) echo '<option value="' . esc_attr( $url ) . '"' . selected( $url, $link, false ) . '>' . esc_html( $label ) . '</option>'; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <script>
                        document.getElementById('ms-logo-link-pick').addEventListener('change', function () {
                            if (this.value) document.getElementById('ms-logo-link').value = this.value;
                        });
                    </script>
                <?php endif; ?>
                <p class="description">A path on this site (starting with /) or a full address. Now: <a href="<?php echo esc_url( monospace_logo_link()['url'] ); ?>" target="_blank"><?php echo esc_html( monospace_logo_link()['url'] ); ?></a></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="ms-logo-title">Hover text</label></th>
            <td><input type="text" name="logo_title" id="ms-logo-title" class="regular-text" value="<?php echo esc_attr( $title ); ?>">
                <p class="description">Shown when the mouse rests on the logo.</p></td>
        </tr>
    </table>
    <?php
}

function monospace_save_logo_link_settings() {
    if ( ! isset( $_POST['logo_link'] ) ) return;
    $link = trim( wp_unslash( $_POST['logo_link'] ) );
    $link = $link === '' ? '/' : esc_url_raw( $link ); // a site path stays relative
    if ( $link !== '' ) update_option( 'monospace_logo_link', $link );
    update_option( 'monospace_logo_title', sanitize_text_field( wp_unslash( $_POST['logo_title'] ?? '' ) ) );
}

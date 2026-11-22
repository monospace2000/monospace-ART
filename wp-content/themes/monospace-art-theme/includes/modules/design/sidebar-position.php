<?php
/**
 * Design module: Sidebar position - where the sidebar goes, site-wide (2026-10-22, owner: "global setting for sidebar: on
 * wider screen left or right (default is right), and above or below content on mobile (default above)").
 *
 *   Computers         right (default) | left
 *   Phones & tablets  above the content (default) | below it - pages that load more as you scroll (the Art Feed,
 *                     categories, tags, places) then show no sidebar: it would sit after an endless list (owner: "hide on
 *                     infinite scroll")
 * Option monospace_sidebar_position [ desktop, phone ] (not set = right / above). Body classes sidebar-left and
 * sidebar-phone-below, read by _layout.scss. The light look only (not in monospace_dark_modules()): the layout is the
 * same in dark mode.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_sidebar_position() {
    $s = get_option( 'monospace_sidebar_position', [] );
    $s = is_array( $s ) ? $s : [];
    return [
        'desktop' => ( $s['desktop'] ?? '' ) === 'left' ? 'left' : 'right',
        'phone'   => ( $s['phone'] ?? '' ) === 'below' ? 'below' : 'above',
    ];
}

add_filter( 'body_class', function ( $classes ) {
    $s = monospace_sidebar_position();
    if ( $s['desktop'] === 'left' ) $classes[] = 'sidebar-left';
    if ( $s['phone'] === 'below' ) $classes[] = 'sidebar-phone-below';
    return $classes;
} );

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['sidebar-position'] = [
        'title'       => 'Sidebar',
        'description' => 'Where the sidebar goes on every page: left or right of the content on computers, above or below it on phones and tablets.',
        'group'       => 'look',
        'order'       => 46,
        'summary'     => function () {
            $s = monospace_sidebar_position();
            return ucfirst( $s['desktop'] ) . ' · phones: ' . $s['phone'];
        },
        'render'      => 'monospace_render_sidebar_position',
        'save'        => 'monospace_save_sidebar_position',
    ];
    return $modules;
} );

function monospace_render_sidebar_position() {
    $s = monospace_sidebar_position(); ?>
    <table class="form-table" role="presentation" id="ms-sbpos">
        <tr><th scope="row">Computers</th><td>
            <input type="hidden" name="ms_sbpos[seen]" value="1">
            <?php foreach ( [ 'right' => 'Right of the content', 'left' => 'Left of the content' ] as $k => $label ) : ?>
                <label style="display:block;margin-bottom:4px"><input type="radio" name="ms_sbpos[desktop]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['desktop'], $k ); ?>> <?php echo esc_html( $label ); ?></label>
            <?php endforeach; ?>
        </td></tr>
        <tr><th scope="row">Phones &amp; tablets</th><td>
            <?php foreach ( [ 'above' => 'Above the content', 'below' => 'Below the content' ] as $k => $label ) : ?>
                <label style="display:block;margin-bottom:4px"><input type="radio" name="ms_sbpos[phone]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['phone'], $k ); ?>> <?php echo esc_html( $label ); ?></label>
            <?php endforeach; ?>
            <p class="description">Below: the Art Feed, categories, tags and places - pages that load more as you scroll - show no sidebar on phones, as it would come after an endless list.</p>
        </td></tr>
    </table>
    <script>
    (function () {
        // Look's preview frame follows the fields (the body classes the page gets)
        var t = document.getElementById('ms-sbpos'), iframe = document.getElementById('ms-spot-iframe');
        if (!t) return;
        function apply() {
            var doc; try { doc = iframe && iframe.contentDocument; } catch (e) {}
            if (!doc || !doc.body) return;
            doc.body.classList.toggle('sidebar-left', (t.querySelector('[name="ms_sbpos[desktop]"]:checked') || {}).value === 'left');
            doc.body.classList.toggle('sidebar-phone-below', (t.querySelector('[name="ms_sbpos[phone]"]:checked') || {}).value === 'below');
        }
        t.addEventListener('change', apply);
        if (iframe) iframe.addEventListener('load', apply);
        apply();
    })();
    </script>
    <?php
}

function monospace_save_sidebar_position() {
    if ( empty( $_POST['ms_sbpos']['seen'] ) ) return;
    $in = wp_unslash( (array) $_POST['ms_sbpos'] );
    $s  = [ 'desktop' => ( $in['desktop'] ?? '' ) === 'left' ? 'left' : 'right', 'phone' => ( $in['phone'] ?? '' ) === 'below' ? 'below' : 'above' ];
    $s === [ 'desktop' => 'right', 'phone' => 'above' ] ? delete_option( 'monospace_sidebar_position' ) : update_option( 'monospace_sidebar_position', $s );
}

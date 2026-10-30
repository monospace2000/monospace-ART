<?php
/**
 * Design module: Placeholder picture (2026-10-23, owner: "a place where I can select a placeholder image for the whole site. Use
 * it whenever an image is missing (products, artworks, etc)").
 *
 * The picture shown wherever a post, an artwork or a product has no picture of its own: archive grids and detail rows, "See also"
 * thumbnails, the Art Filter, the price card, and WooCommerce's own placeholder (shop lists, the cart, emails). Chosen here - the
 * media library's picker - or, until one is chosen, the media library file art_placeholder.png. The lookup and the WooCommerce hook
 * are Core's (monospace_placeholder_image_id(), frontend/archive/artwork-row.php).
 *
 * Option: monospace_placeholder_image (attachment ID; empty = the default file). In Appearance > Look (group 'look'); the Look screen
 * already loads the media window.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['placeholder-image'] = [
        'title'       => 'Placeholder picture',
        'description' => 'The picture shown wherever a post, artwork or product has no picture of its own.',
        'group'       => 'look',
        'order'       => 70,
        'summary'     => function () {
            $own = (int) get_option( 'monospace_placeholder_image', 0 );
            return $own && wp_attachment_is_image( $own ) ? 'Your own' : ( function_exists( 'monospace_placeholder_image_id' ) && monospace_placeholder_image_id() ? 'The default' : 'None uploaded' );
        },
        'render'      => 'monospace_render_placeholder_image_settings',
        'save'        => 'monospace_save_placeholder_image_settings',
    ];
    return $modules;
} );

function monospace_render_placeholder_image_settings() {
    $own     = (int) get_option( 'monospace_placeholder_image', 0 );
    $own     = $own && wp_attachment_is_image( $own ) ? $own : 0;
    $shown   = $own ?: ( function_exists( 'monospace_placeholder_image_id' ) ? monospace_placeholder_image_id() : 0 );
    $src     = $shown ? (string) wp_get_attachment_image_url( $shown, 'medium_large' ) : '';
    ?>
    <input type="hidden" name="ms_placeholder_seen" value="1">
    <input type="hidden" name="ms_placeholder_image" id="ms-ph-id" value="<?php echo $own ?: ''; ?>">
    <div class="ms-ph">
        <div class="ms-ph-card"><img id="ms-ph-img" src="<?php echo esc_url( $src ); ?>" alt=""<?php echo $src ? '' : ' hidden'; ?>><span id="ms-ph-none"<?php echo $src ? ' hidden' : ''; ?>>nothing uploaded yet</span>
            <span class="ms-ph-tag" id="ms-ph-tag"<?php echo $own ? ' hidden' : ''; ?>>default</span></div>
        <div>
            <button type="button" class="button" id="ms-ph-choose">Choose image…</button>
            <button type="button" class="button-link" id="ms-ph-reset"<?php echo $own ? '' : ' hidden'; ?>>Use the default (art_placeholder.png)</button>
            <p class="description" style="max-width:640px">Shown wherever a picture is missing: grid and detail views of posts without a picture, “See also” thumbnails, the Art Filter, price cards, and
                everywhere WooCommerce shows a product with no photo (shop lists, the cart, emails). A square picture works best - grid tiles are square. It is
                shown at its own colors, so a picture without transparency looks the same on every background. Replacing the file in the media library works too:
                its address changes with it, so nobody keeps seeing the old one.</p>
        </div>
    </div>
    <style>
        .ms-ph { display: flex; gap: 18px; align-items: flex-start; padding: 6px 0 12px; }
        .ms-ph-card { position: relative; flex: 0 0 160px; aspect-ratio: 1; border: 1px solid #c3c4c7; border-radius: 6px; overflow: hidden; background: #f0f0f1; display: flex; align-items: center; justify-content: center; color: #646970; font-size: 12px; }
        .ms-ph-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .ms-ph-card img[hidden], .ms-ph-card span[hidden] { display: none; }
        .ms-ph-tag { position: absolute; top: 6px; left: 6px; background: rgba(0,0,0,.6); color: #fff; border-radius: 3px; padding: 1px 6px; font-size: 11px; }
    </style>
    <script>
    jQuery(function ($) {
        var frame, defaultSrc = <?php echo wp_json_encode( $own ? '' : $src ); ?>;
        $('#ms-ph-choose').on('click', function (e) {
            e.preventDefault();
            if (!window.wp || !wp.media) return;
            if (!frame) {
                frame = wp.media({ title: 'Placeholder picture', library: { type: 'image' }, multiple: false, button: { text: 'Use this image' } });
                frame.on('select', function () {
                    var a = frame.state().get('selection').first().toJSON(), s = a.sizes || {};
                    $('#ms-ph-id').val(a.id).trigger('change');
                    $('#ms-ph-img').attr('src', (s.medium_large || s.large || a).url).prop('hidden', false);
                    $('#ms-ph-none').prop('hidden', true);
                    $('#ms-ph-tag').prop('hidden', true);
                    $('#ms-ph-reset').prop('hidden', false);
                });
            }
            frame.open();
        });
        $('#ms-ph-reset').on('click', function () {
            $('#ms-ph-id').val('').trigger('change');
            if (defaultSrc) { $('#ms-ph-img').attr('src', defaultSrc).prop('hidden', false); $('#ms-ph-none').prop('hidden', true); $('#ms-ph-tag').prop('hidden', false); }
            else { $('#ms-ph-img').prop('hidden', true); $('#ms-ph-none').prop('hidden', false); }
            $(this).prop('hidden', true);
        });
    });
    </script>
    <?php
}

function monospace_save_placeholder_image_settings() {
    if ( ! isset( $_POST['ms_placeholder_seen'] ) ) return;
    $id = absint( $_POST['ms_placeholder_image'] ?? 0 );
    $id && wp_attachment_is_image( $id ) ? update_option( 'monospace_placeholder_image', $id, false ) : delete_option( 'monospace_placeholder_image' );
}

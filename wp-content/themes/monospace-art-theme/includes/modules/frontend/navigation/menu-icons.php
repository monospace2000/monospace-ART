<?php
/**
 * Menu icons (2026-10-21, owner: "allow me to change the icons and make it look good in Dark Mode").
 *
 * Every menu item (Appearance > Menus) has an Icon field: none, one of the theme's set (assets/images/icons/ - the
 * footer's rounded squares: Instagram, Bluesky, Facebook, YouTube, Website, Email) or your own image from the Media
 * Library. The icon is drawn in the link's text color (the image is a mask), so it follows the look and dark mode;
 * your own image can keep its own colors instead ("Its own colors").
 *
 * Post meta on the menu item: _ms_menu_icon (key / 'own' / 'none'), _ms_menu_icon_img (attachment ID),
 * _ms_menu_icon_colour ('own' = its own colors). Items with no setting fall back to the old CSS classes
 * menu-icon-instagram / -bluesky / -facebook. Styles: _global.scss (.ms-menu-icon).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The theme's icons: key => label */
function monospace_menu_icons() {
    return [ 'instagram' => 'Instagram', 'bluesky' => 'Bluesky', 'facebook' => 'Facebook', 'youtube' => 'YouTube', 'website' => 'Website', 'email' => 'Email' ];
}

function monospace_menu_icon_file_url( $key ) {
    return get_template_directory_uri() . '/assets/images/icons/' . $key . '.png';
}

/** This item's icon setting: a key, 'own', 'none' - or, not set here, the old CSS class's key ('' = none) */
function monospace_menu_item_icon_key( $item ) {
    $key = (string) get_post_meta( $item->ID, '_ms_menu_icon', true );
    if ( $key !== '' ) return $key;
    foreach ( (array) $item->classes as $class ) {
        if ( strpos( (string) $class, 'menu-icon-' ) === 0 && isset( monospace_menu_icons()[ substr( $class, 10 ) ] ) ) return substr( $class, 10 );
    }
    return '';
}

/** This item's icon: [ url, own colors? ] or null */
function monospace_menu_item_icon( $item ) {
    $key = monospace_menu_item_icon_key( $item );
    if ( $key === 'own' ) {
        $url = wp_get_attachment_image_url( (int) get_post_meta( $item->ID, '_ms_menu_icon_img', true ), 'thumbnail' );
        return $url ? [ $url, get_post_meta( $item->ID, '_ms_menu_icon_colour', true ) === 'own' ] : null;
    }
    return isset( monospace_menu_icons()[ $key ] ) ? [ monospace_menu_icon_file_url( $key ), false ] : null;
}

// On the site: the link carries the icon (--ms-icon) and a class; the old menu-icon-* class rules are gone
add_filter( 'nav_menu_link_attributes', function ( $atts, $item ) {
    $icon = monospace_menu_item_icon( $item );
    if ( ! $icon ) return $atts;
    $atts['class'] = trim( ( $atts['class'] ?? '' ) . ' ms-menu-icon' . ( $icon[1] ? ' ms-menu-icon-own' : '' ) );
    $atts['style'] = trim( ( $atts['style'] ?? '' ) . " --ms-icon: url('" . esc_url( $icon[0] ) . "');" );
    return $atts;
}, 10, 2 );

// Appearance > Menus: the Icon field in each item
add_action( 'wp_nav_menu_item_custom_fields', function ( $id, $item ) {
    $key   = monospace_menu_item_icon_key( $item );
    $img   = (int) get_post_meta( $id, '_ms_menu_icon_img', true );
    $own_c = get_post_meta( $id, '_ms_menu_icon_colour', true ) === 'own';
    $thumb = $img ? wp_get_attachment_image_url( $img, 'thumbnail' ) : '';
    ?>
    <div class="field-ms-icon description description-wide" data-ms-icon-base="<?php echo esc_url( monospace_menu_icon_file_url( '' ) ); ?>">
        <label for="edit-menu-item-ms-icon-<?php echo (int) $id; ?>">Icon</label><br>
        <select id="edit-menu-item-ms-icon-<?php echo (int) $id; ?>" name="menu-item-ms-icon[<?php echo (int) $id; ?>]" class="ms-icon-key">
            <option value="none">No icon</option>
            <?php foreach ( monospace_menu_icons() as $k => $label ) : ?>
                <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $key, $k ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
            <option value="own" <?php selected( $key, 'own' ); ?>>Your own image…</option>
        </select>
        <span class="ms-icon-preview"<?php echo $key === '' || $key === 'none' ? ' hidden' : ''; ?>><img src="<?php echo esc_url( $key === 'own' ? $thumb : ( isset( monospace_menu_icons()[ $key ] ) ? monospace_menu_icon_file_url( $key ) : '' ) ); ?>" alt=""></span>
        <span class="ms-icon-own"<?php echo $key === 'own' ? '' : ' hidden'; ?>>
            <input type="hidden" name="menu-item-ms-icon-img[<?php echo (int) $id; ?>]" value="<?php echo $img ?: ''; ?>" class="ms-icon-img">
            <button type="button" class="button ms-icon-pick">Choose image</button>
            <label><input type="checkbox" name="menu-item-ms-icon-colour[<?php echo (int) $id; ?>]" value="own" <?php checked( $own_c ); ?>> Its own colors</label>
        </span>
        <span class="description" style="display:block">Drawn in the text color, so it follows the look and dark mode - your own image too, unless it keeps its own colors.</span>
    </div>
    <?php
}, 10, 2 );

add_action( 'wp_update_nav_menu_item', function ( $menu_id, $id ) {
    if ( ! isset( $_POST['menu-item-ms-icon'][ $id ] ) || ! current_user_can( 'edit_theme_options' ) ) return; // saved from elsewhere: leave it
    $key = sanitize_key( wp_unslash( $_POST['menu-item-ms-icon'][ $id ] ) );
    if ( $key !== 'own' && $key !== 'none' && ! isset( monospace_menu_icons()[ $key ] ) ) $key = 'none';
    update_post_meta( $id, '_ms_menu_icon', $key );
    if ( $key === 'own' ) {
        update_post_meta( $id, '_ms_menu_icon_img', absint( $_POST['menu-item-ms-icon-img'][ $id ] ?? 0 ) );
        update_post_meta( $id, '_ms_menu_icon_colour', ! empty( $_POST['menu-item-ms-icon-colour'][ $id ] ) ? 'own' : '' );
    } else {
        delete_post_meta( $id, '_ms_menu_icon_img' );
        delete_post_meta( $id, '_ms_menu_icon_colour' );
    }
}, 10, 2 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook !== 'nav-menus.php' ) return;
    wp_enqueue_media();
    add_action( 'admin_footer', function () { ?>
    <style>
        .field-ms-icon { margin: 6px 0 10px; }
        .field-ms-icon select { vertical-align: middle; }
        .field-ms-icon .ms-icon-preview img { width: 24px; height: 24px; object-fit: contain; vertical-align: middle; margin: 0 6px; }
        .field-ms-icon [hidden] { display: none !important; }
        .field-ms-icon .ms-icon-own label { margin-left: 6px; }
    </style>
    <script>
    jQuery(function ($) {
        // One handler for every item, also items added later
        $(document).on('change', '.field-ms-icon .ms-icon-key', function () {
            var box = $(this).closest('.field-ms-icon'), key = this.value, img = box.find('.ms-icon-preview img');
            box.find('.ms-icon-own').prop('hidden', key !== 'own');
            if (key === 'none') { box.find('.ms-icon-preview').prop('hidden', true); return; }
            if (key !== 'own') img.attr('src', box.data('msIconBase').replace(/\.png$/, '') + key + '.png');
            box.find('.ms-icon-preview').prop('hidden', key === 'own' && !box.find('.ms-icon-img').val());
            if (key === 'own' && !box.find('.ms-icon-img').val()) box.find('.ms-icon-pick').trigger('click');
        });
        $(document).on('click', '.field-ms-icon .ms-icon-pick', function () {
            var box = $(this).closest('.field-ms-icon');
            var frame = wp.media({ title: 'Choose an icon', library: { type: 'image' }, multiple: false, button: { text: 'Use as icon' } });
            frame.on('select', function () {
                var a = frame.state().get('selection').first().toJSON();
                box.find('.ms-icon-img').val(a.id);
                box.find('.ms-icon-preview img').attr('src', (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url));
                box.find('.ms-icon-preview').prop('hidden', false);
            });
            frame.open();
        });
    });
    </script>
    <?php } );
} );

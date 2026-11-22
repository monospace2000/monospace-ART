<?php
/**
 * Design module: Rounding - how round the corners are, site-wide and per part (2026-10-21, owner: "add border radius
 * somewhere. For boxes, cards and also images. Sitewide too").
 *
 *   Site-wide   one rounding the parts below follow unless they have their own; "the theme's own" = each part keeps
 *               its own (home page boxes 10px, cards 8px, images 5px)
 *   Images      artwork images in feeds and on posts, sidebar images, the shop's product images, the grid view's
 *               squares, miniature covers - site-wide, or their own
 *   Cards       their own rounding stays in Look > Cards (monospace_look card_radius, appearance.php); left at the
 *               theme's 8px they follow site-wide
 *   Home page boxes  their own in Look > Home page boxes, or one box's in Layout Manager > Home Page (home-box-style.php
 *               'radius'); unset they follow site-wide
 *   Buttons     all buttons - the main ones, the secondary ones and the price card's tabs (2026-10-22, owner: "also add
 *               roundness for buttons", "add those [the tab pills] to the button rounding control"):
 *               a pill (the theme's own), like site-wide, or 0-30px - --ms-btn-radius, read by button-base (_mixins.scss)
 * Option monospace_rounding [ 'site' => '' | 0-30, 'images' => '' | 0-30, 'buttons' => '' (pill) | 'site' | 0-30 ]. Output: --ms-radius and --ms-img-radius on
 * :root (<style id="ms-rounding">), only when set; the CSS falls back var(--part's own, var(--ms-radius, theme's own))
 * (_mixins.scss card-style, _start_template.scss boxes, _global.scss / _archives.scss / _main-feed-images.scss images).
 * Live preview: in Appearance > Look the panel sets the same variables on the preview frame as the fields change.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The roundings to choose from, px */
function monospace_rounding_steps() {
    return [ 0, 2, 4, 5, 6, 8, 10, 12, 14, 16, 20, 24, 30 ];
}

/** '' (not set) or a rounding 0-30 */
function monospace_rounding_value( $v ) {
    return $v === '' || $v === null || ! is_numeric( $v ) ? '' : max( 0, min( 30, (int) $v ) );
}

function monospace_rounding() {
    $s = (array) get_option( 'monospace_rounding', [] );
    return [ 'site' => monospace_rounding_value( $s['site'] ?? '' ), 'images' => monospace_rounding_value( $s['images'] ?? '' ),
             'buttons' => ( $s['buttons'] ?? '' ) === 'site' ? 'site' : monospace_rounding_value( $s['buttons'] ?? '' ) ];
}

/** The variables: --ms-radius (site-wide), --ms-img-radius (images' own) */
function monospace_rounding_vars() {
    $s = monospace_rounding(); $vars = [];
    if ( $s['site'] !== '' ) $vars['--ms-radius'] = $s['site'] . 'px';
    if ( $s['images'] !== '' ) $vars['--ms-img-radius'] = $s['images'] . 'px';
    if ( $s['buttons'] === 'site' ) $vars['--ms-btn-radius'] = 'var(--ms-radius, 8px)';
    elseif ( $s['buttons'] !== '' ) $vars['--ms-btn-radius'] = $s['buttons'] . 'px';
    return $vars;
}

add_action( 'wp_head', function () {
    $vars = monospace_rounding_vars();
    if ( ! $vars ) return;
    $css = '';
    foreach ( $vars as $k => $v ) $css .= $k . ':' . $v . ';';
    if ( function_exists( 'monospace_look_style' ) ) monospace_look_style( 'ms-rounding', ':root{' . $css . '}' ); // scoped when dark mode is on
    else echo '<style id="ms-rounding">:root{' . esc_html( $css ) . "}</style>\n";
}, 30 );

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['rounding'] = [
        'title'       => 'Rounding',
        'description' => 'How round the corners are - site-wide, and images on their own (cards and home page boxes in their panels).',
        'group'       => 'look',
        'order'       => 45,
        'summary'     => function () {
            $s = monospace_rounding();
            return ( $s['site'] === '' ? 'The theme’s own' : 'Site-wide ' . $s['site'] . 'px' ) . ( $s['images'] !== '' ? ', images ' . $s['images'] . 'px' : '' )
                 . ( $s['buttons'] === '' ? '' : ', buttons ' . ( $s['buttons'] === 'site' ? 'site-wide' : $s['buttons'] . 'px' ) );
        },
        'render'      => 'monospace_render_rounding',
        'save'        => 'monospace_save_rounding',
    ];
    return $modules;
} );

/** A rounding select: $first = the label of '' (not set) */
function monospace_rounding_select( $name, $id, $value, $first ) {
    echo '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '"><option value="">' . esc_html( $first ) . '</option>';
    foreach ( monospace_rounding_steps() as $px ) echo '<option value="' . $px . '"' . selected( $value, $px, false ) . '>' . ( $px ? $px . 'px' : 'Square (0)' ) . '</option>';
    echo '</select>';
}

function monospace_render_rounding() {
    $s = monospace_rounding(); ?>
    <table class="form-table" role="presentation" id="ms-rounding">
        <tr>
            <th scope="row"><label for="ms-round-site">Site-wide</label></th>
            <td><?php monospace_rounding_select( 'rounding[site]', 'ms-round-site', $s['site'], 'The theme’s own (boxes 10px, cards 8px, images 5px)' ); ?>
                <p class="description">Cards, home page boxes and images follow this unless they have their own.</p></td>
        </tr>
        <tr>
            <th scope="row"><label for="ms-round-images">Images</label></th>
            <td><?php monospace_rounding_select( 'rounding[images]', 'ms-round-images', $s['images'], 'Site-wide' ); ?>
                <p class="description">Artwork images in the feeds and on posts, the grid view's squares, miniature covers, sidebar and shop images.</p></td>
        </tr>
        <tr>
            <th scope="row"><label for="ms-round-buttons">Buttons</label></th>
            <td><select name="rounding[buttons]" id="ms-round-buttons">
                    <option value="">Pill - round ends (the theme’s own)</option>
                    <option value="site" <?php selected( $s['buttons'], 'site' ); ?>>Site-wide</option>
                    <?php foreach ( monospace_rounding_steps() as $px ) echo '<option value="' . $px . '"' . selected( $s['buttons'], $px, false ) . '>' . ( $px ? $px . 'px' : 'Square (0)' ) . '</option>'; ?>
                </select>
                <p class="description">Every button - Add to cart, checkout, forms -, the secondary ones (Older / Newer, the sidebar menus) and the price card’s tabs (Original / Framed / Unframed).</p></td>
        </tr>
        <tr>
            <th scope="row">Cards</th>
            <td><p class="description" style="margin-top:0">Their own in the <strong>Cards</strong> panel (Corner rounding) - left at the theme's 8px they follow site-wide.</p></td>
        </tr>
        <tr>
            <th scope="row">Home page boxes</th>
            <td><p class="description" style="margin-top:0">Their own in the <strong>Home page boxes</strong> panel, or one box's in Layout Manager → Home Page.</p></td>
        </tr>
    </table>
    <script>
    (function () {
        // Live preview: the Look screen's frame (none on the Layout Manager's tab)
        var iframe = document.getElementById('ms-spot-iframe'), table = document.getElementById('ms-rounding');
        if (!iframe || !table) return;
        function preview() {
            var root; try { root = iframe.contentDocument.documentElement.style; } catch (e) { return; }
            var old = iframe.contentDocument.getElementById('ms-rounding'); if (old) old.remove();
            [['ms-round-site', '--ms-radius'], ['ms-round-images', '--ms-img-radius'], ['ms-round-buttons', '--ms-btn-radius']].forEach(function (p) {
                var v = document.getElementById(p[0]).value;
                if (v === '') root.removeProperty(p[1]); else root.setProperty(p[1], v === 'site' ? 'var(--ms-radius, 8px)' : v + 'px');
            });
            // The Buttons panel's samples too
            var b = document.getElementById('ms-round-buttons').value;
            document.querySelectorAll('.ms-btn-sample, .ms-nb-btn').forEach(function (x) { if (b === '') x.style.removeProperty('--ms-btn-radius'); else x.style.setProperty('--ms-btn-radius', b === 'site' ? 'var(--ms-radius, 8px)' : b + 'px'); });
        }
        table.addEventListener('change', preview);
        iframe.addEventListener('load', preview);
        preview();
    })();
    </script>
    <?php
}

function monospace_save_rounding() {
    if ( ! isset( $_POST['rounding'] ) ) return;
    $in = wp_unslash( (array) $_POST['rounding'] );
    $s  = [ 'site' => monospace_rounding_value( $in['site'] ?? '' ), 'images' => monospace_rounding_value( $in['images'] ?? '' ),
            'buttons' => ( $in['buttons'] ?? '' ) === 'site' ? 'site' : monospace_rounding_value( $in['buttons'] ?? '' ) ];
    $s === [ 'site' => '', 'images' => '', 'buttons' => '' ] ? delete_option( 'monospace_rounding' ) : update_option( 'monospace_rounding', $s );
}

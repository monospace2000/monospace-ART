<?php
/**
 * Footer - the Layout Manager tab (2026-11-16, owner: "add a module to the Layout manager to edit the Footer").
 *
 * What footer.php prints, editable in one place:
 *   - the menu part (the Footer menu's columns): shown or not. The links themselves stay in Appearance > Menus - this tab
 *     lists them and links there, it never keeps a second copy;
 *   - the copyright line: shown or not, and its text ({year} = the current year; a link, bold, italic and a line break are allowed).
 * A utility page can still leave the whole footer out (Layout Manager > Utility pages).
 *
 * Option monospace_footer = [ 'menu' => 1|0, 'copyright_on' => 1|0, 'copyright' => text ] - defaults are what the footer showed before.
 * Content, not look: not part of the look snapshots.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The text the footer showed before this tab existed */
function monospace_footer_default_copyright() {
    return 'Copyright &copy;{year} Hens Breet | monospace ART LLC. All rights reserved.';
}

/** The saved footer settings with the defaults filled in */
function monospace_footer_settings() {
    $saved = get_option( 'monospace_footer', [] );
    if ( ! is_array( $saved ) ) $saved = [];
    $text = isset( $saved['copyright'] ) ? (string) $saved['copyright'] : '';
    return [
        'menu'         => (int) ( $saved['menu'] ?? 1 ),
        'copyright_on' => (int) ( $saved['copyright_on'] ?? 1 ),
        'copyright'    => trim( $text ) === '' ? monospace_footer_default_copyright() : $text,
    ];
}

/** The tags the copyright line may carry */
function monospace_footer_allowed_html() {
    return [ 'a' => [ 'href' => true, 'title' => true, 'target' => true, 'rel' => true ], 'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'br' => [] ];
}

/** The copyright line as printed: {year} replaced, only the allowed tags */
function monospace_footer_copyright_html() {
    $text = str_replace( '{year}', wp_date( 'Y' ), monospace_footer_settings()['copyright'] );
    return wp_kses( $text, monospace_footer_allowed_html() );
}

/** The Footer menu's top-level items (the column headings), each with its links: [ [ heading, [ link, ... ] ], ... ] */
function monospace_footer_menu_columns() {
    $loc  = get_nav_menu_locations();
    $menu = ! empty( $loc['footer'] ) ? wp_get_nav_menu_object( $loc['footer'] ) : null;
    if ( ! $menu ) return [ null, [] ];
    $cols  = [];
    $items = wp_get_nav_menu_items( $menu->term_id ) ?: [];
    foreach ( $items as $item ) {
        if ( (int) $item->menu_item_parent === 0 ) $cols[ $item->ID ] = [ $item->title, [] ];
    }
    foreach ( $items as $item ) {
        if ( isset( $cols[ (int) $item->menu_item_parent ] ) ) $cols[ (int) $item->menu_item_parent ][1][] = $item->title;
    }
    return [ $menu, array_values( $cols ) ];
}

/** The tab's content (inside Layout Manager's form, which prints the Save button) */
function monospace_render_footer_settings() {
    if ( function_exists( 'monospace_lm_area_assets' ) ) monospace_lm_area_assets();
    $s = monospace_footer_settings();
    [ $menu, $cols ] = monospace_footer_menu_columns();
    $menu_edit = $menu ? admin_url( 'nav-menus.php?action=edit&menu=' . (int) $menu->term_id ) : admin_url( 'nav-menus.php' );
    $chip = fn( $on ) => '<span class="lm-chip ' . ( $on ? 'is-on' : 'is-off' ) . '">' . ( $on ? 'In use' : 'Not shown' ) . '</span>';
    $links_total = array_sum( array_map( fn( $c ) => count( $c[1] ), $cols ) );
    ?>
    <p class="description" style="max-width:760px">The footer at the bottom of every page: the Footer menu’s columns and the copyright line. A utility page can leave the whole footer out (Utility pages).</p>
    <div class="lm-panels" style="max-width:780px">
        <details class="lm-panel" data-panel="footer-menu">
            <summary><span class="lm-t">Footer menu</span> <?php echo $chip( $s['menu'] && $menu ); ?>
                <span class="lm-sub"><?php
                    if ( ! $menu ) echo 'No menu is set to the Footer location yet';
                    else echo esc_html( $menu->name . ' · ' . count( $cols ) . ' columns, ' . $links_total . ' links' );
                ?></span></summary>
            <div class="lm-body">
                <label style="display:block;margin:8px 0"><input type="checkbox" name="footer_menu" value="1" <?php checked( $s['menu'] ); ?>> Show the Footer menu’s columns</label>
                <?php if ( $cols ) : ?>
                    <ul style="margin:6px 0 10px">
                        <?php foreach ( $cols as [ $heading, $links ] ) : ?>
                            <li><strong><?php echo esc_html( $heading ); ?></strong><?php echo $links ? ' - ' . esc_html( implode( ', ', $links ) ) : ''; ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <p class="description" style="margin-bottom:8px">The links and columns are edited in the menu itself: <a href="<?php echo esc_url( $menu_edit ); ?>">Appearance &rsaquo; Menus &rsaquo; <?php echo esc_html( $menu ? $menu->name : 'Footer' ); ?></a>.</p>
            </div>
        </details>

        <details class="lm-panel" data-panel="footer-copyright">
            <summary><span class="lm-t">Copyright line</span> <?php echo $chip( $s['copyright_on'] ); ?>
                <span class="lm-sub"><?php echo esc_html( wp_strip_all_tags( html_entity_decode( str_replace( '{year}', wp_date( 'Y' ), $s['copyright'] ), ENT_QUOTES, 'UTF-8' ) ) ); ?></span></summary>
            <div class="lm-body">
                <label style="display:block;margin:8px 0"><input type="checkbox" name="footer_copyright_on" value="1" <?php checked( $s['copyright_on'] ); ?>> Show the copyright line</label>
                <p style="margin:8px 0 4px"><label for="ms-footer-copyright"><strong>Text</strong></label></p>
                <textarea name="footer_copyright" id="ms-footer-copyright" rows="3" class="large-text"><?php echo esc_textarea( $s['copyright'] ); ?></textarea>
                <p class="description"><code>{year}</code> becomes the current year, so it never needs changing. A link, bold, italic and a line break (<code>&lt;br&gt;</code>) are allowed. Leave it empty to go back to the standard line.</p>
            </div>
        </details>
    </div>
    <?php
}

/** Save the tab */
function monospace_save_footer_settings() {
    if ( ! isset( $_POST['monospace_layout_manager_submit'] ) ) return;
    $text = trim( wp_kses( wp_unslash( $_POST['footer_copyright'] ?? '' ), monospace_footer_allowed_html() ) );
    update_option( 'monospace_footer', [
        'menu'         => empty( $_POST['footer_menu'] ) ? 0 : 1,
        'copyright_on' => empty( $_POST['footer_copyright_on'] ) ? 0 : 1,
        'copyright'    => $text,
    ] );
    // The page cache holds the old footer
    if ( function_exists( 'sg_cachepress_purge_everything' ) ) sg_cachepress_purge_everything();
}

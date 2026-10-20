<?php
/**
 * Plugin Name: monospace ART - Dashboard Shortcuts
 * Update URI: false
 * Description: A "Shortcuts" box on the WordPress Dashboard - buttons to the admin screens, pages and posts you use most. Per user; pick, rename and drag to reorder under the box's Configure link.
 * Version: 1.0.0
 * Author: Hens Breet
 * Requires at least: 6.0
 *
 * Dashboard Shortcuts
 *
 * A "Shortcuts" box on the WordPress Dashboard: buttons linking to pages, posts and admin
 * screens. Its "Configure" link (top right of the box) opens the editor: pick from every admin
 * menu screen, pages, recent posts, anything other plugins add, or a custom link; rename, drag to
 * reorder, remove.
 *
 * Shortcuts are per user (user meta ms_dashboard_shortcuts: [ { label, url } ] - the name it had as a module of
 * monospace ART's Admin tools, kept so nobody's shortcuts move).
 *
 * A standalone, generic plugin (2026-10-19, owner's wish) - no monospace ART dependencies. Folder
 * monospace-dashboard-shortcuts: the plain "dashboard-shortcuts" is another developer's plugin on WordPress.org,
 * and WordPress took ours for theirs (details, updates). Other plugins add
 * choices to the picker with the filter dashboard_shortcuts_choices ( [ group label => [ [ 'label', 'url' ] ] ] );
 * the Sales Dashboard adds its sections that way.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const DASHBOARD_SHORTCUTS_META = 'ms_dashboard_shortcuts';

add_action( 'wp_dashboard_setup', function () {
    if ( ! current_user_can( 'edit_posts' ) ) return;
    wp_add_dashboard_widget( 'ms_shortcuts', __( 'Shortcuts', 'dashboard-shortcuts' ), 'dashboard_shortcuts_widget', 'dashboard_shortcuts_control' );
} );

function dashboard_shortcuts_get() {
    $saved = get_user_meta( get_current_user_id(), DASHBOARD_SHORTCUTS_META, true );
    return is_array( $saved ) ? $saved : [];
}

/** The box: one button per shortcut */
function dashboard_shortcuts_widget() {
    $shortcuts = dashboard_shortcuts_get();
    if ( ! $shortcuts ) {
        $edit = esc_url( add_query_arg( 'edit', 'ms_shortcuts', admin_url() ) . '#ms_shortcuts' );
        echo '<p>' . sprintf( __( 'No shortcuts yet. <a href="%s">Add some</a>.', 'dashboard-shortcuts' ), $edit ) . '</p>';
        return;
    }
    echo '<div class="ms-shortcuts">';
    foreach ( $shortcuts as $s ) {
        $external = strpos( $s['url'], admin_url() ) !== 0 && strpos( $s['url'], home_url() ) !== 0;
        printf( '<a class="button" href="%s"%s>%s</a>', esc_url( $s['url'] ), $external ? ' target="_blank" rel="noopener"' : '', esc_html( $s['label'] ) );
    }
    echo '</div><style>.ms-shortcuts{display:flex;flex-wrap:wrap;gap:6px}.ms-shortcuts .button{margin:0}</style>';
}

/** Everything the "Add a shortcut" dropdown offers, grouped */
function dashboard_shortcuts_choices() {
    global $menu, $submenu;
    $clean = function ( $title ) {
        return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( preg_replace( '/<span.*<\/span>/Us', '', (string) $title ) ) ) );
    };
    $url_for = function ( $slug, $parent = '' ) {
        if ( strpos( $slug, '.php' ) !== false || strpos( $slug, 'http' ) === 0 ) return admin_url( $slug );
        if ( $parent && strpos( $parent, '.php' ) !== false && $parent !== 'admin.php' ) return admin_url( add_query_arg( 'page', $slug, $parent ) );
        return admin_url( 'admin.php?page=' . $slug );
    };

    $admin = [];
    foreach ( (array) $menu as $item ) {
        if ( empty( $item[0] ) || empty( $item[2] ) || ! current_user_can( $item[1] ) ) continue;
        $top = $clean( $item[0] );
        if ( $top === '' ) continue;
        $admin[] = [ 'label' => $top, 'url' => $url_for( $item[2] ) ];
        foreach ( (array) ( $submenu[ $item[2] ] ?? [] ) as $sub ) {
            if ( empty( $sub[0] ) || ! current_user_can( $sub[1] ) ) continue;
            $label = $clean( $sub[0] );
            if ( $label === '' || $label === $top ) continue;
            $admin[] = [ 'label' => $top . ' › ' . $label, 'url' => $url_for( $sub[2], $item[2] ) ];
        }
    }

    $pages = array_map( function ( $p ) {
        return [ 'label' => $p->post_title ?: '(no title)', 'url' => get_edit_post_link( $p->ID, 'raw' ) ];
    }, get_pages( [ 'sort_column' => 'post_title', 'post_status' => 'publish,draft,private' ] ) );

    $posts = array_map( function ( $p ) {
        return [ 'label' => $p->post_title ?: '(no title)', 'url' => get_edit_post_link( $p->ID, 'raw' ) ];
    }, get_posts( [ 'post_type' => 'post', 'post_status' => 'any', 'posts_per_page' => 50 ] ) );

    // Other plugins add their own groups (e.g. the Sales Dashboard's sections)
    return array_filter( (array) apply_filters( 'dashboard_shortcuts_choices', [
        'Admin screens'   => $admin,
        'Pages (edit)'    => $pages,
        'Recent posts (edit)' => $posts,
    ] ) );
}

/** Configure: the editor. WordPress checks the nonce before calling this on save. */
function dashboard_shortcuts_control() {
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['ms_shortcuts_json'] ) ) {
        $clean = [];
        foreach ( (array) json_decode( wp_unslash( $_POST['ms_shortcuts_json'] ), true ) as $s ) {
            $label = sanitize_text_field( $s['label'] ?? '' );
            $url   = esc_url_raw( $s['url'] ?? '' );
            if ( $label !== '' && $url !== '' ) $clean[] = [ 'label' => $label, 'url' => $url ];
        }
        update_user_meta( get_current_user_id(), DASHBOARD_SHORTCUTS_META, $clean );
        return;
    }

    wp_enqueue_script( 'jquery-ui-sortable' );
    ?>
    <input type="hidden" name="ms_shortcuts_json" id="ms-sc-json" value="<?php echo esc_attr( wp_json_encode( dashboard_shortcuts_get() ) ); ?>">
    <ul id="ms-sc-list"></ul>
    <p>
        <select id="ms-sc-add" style="max-width:100%">
            <option value=""><?php esc_html_e( '+ Add a shortcut…', 'dashboard-shortcuts' ); ?></option>
            <?php foreach ( dashboard_shortcuts_choices() as $group => $items ) : ?>
                <optgroup label="<?php echo esc_attr( $group ); ?>">
                    <?php foreach ( $items as $c ) : ?>
                        <option value="<?php echo esc_attr( $c['url'] ); ?>" data-label="<?php echo esc_attr( preg_replace( '/^.* › /', '', $c['label'] ) ); ?>"><?php echo esc_html( $c['label'] ); ?></option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
            <optgroup label="Other"><option value="custom"><?php esc_html_e( 'Custom link…', 'dashboard-shortcuts' ); ?></option></optgroup>
        </select>
    </p>
    <p class="description"><?php esc_html_e( 'Drag to reorder. Click a name or link to edit it. Then Submit.', 'dashboard-shortcuts' ); ?></p>
    <style>
        #ms-sc-list { margin: 0; }
        #ms-sc-list li { display: flex; gap: 6px; align-items: center; background: #fff; border: 1px solid #ccd0d4; padding: 4px 6px; margin: 0 0 4px; }
        #ms-sc-list .h { cursor: move; color: #999; }
        #ms-sc-list input { flex: 1; min-width: 0; }
        #ms-sc-list input.l { flex: 0 0 35%; font-weight: 600; }
        #ms-sc-list .x { color: #b32d2e; cursor: pointer; border: 0; background: none; font-size: 16px; }
    </style>
    <script>
    jQuery(function ($) {
        var $json = $('#ms-sc-json'), $list = $('#ms-sc-list');
        function row(s) {
            return $('<li><span class="h dashicons dashicons-menu" title="Drag"></span>' +
                '<input type="text" class="l" placeholder="Name"><input type="text" class="u" placeholder="https://…">' +
                '<button type="button" class="x" title="Remove">✕</button></li>')
                .find('.l').val(s.label).end().find('.u').val(s.url).end();
        }
        function sync() {
            $json.val(JSON.stringify($list.children().map(function () {
                return { label: $.trim($(this).find('.l').val()), url: $.trim($(this).find('.u').val()) };
            }).get()));
        }
        JSON.parse($json.val() || '[]').forEach(function (s) { $list.append(row(s)); });
        $list.sortable({ handle: '.h', update: sync })
             .on('input', 'input', sync)
             .on('click', '.x', function () { $(this).closest('li').remove(); sync(); });
        $('#ms-sc-add').on('change', function () {
            var $o = $(this).find(':selected'), v = this.value;
            if (!v) return;
            var $r = row(v === 'custom' ? { label: '', url: '' } : { label: $o.data('label'), url: v });
            $list.append($r); sync(); this.value = '';
            $r.find(v === 'custom' ? '.l' : '.l').focus();
        });
        $json.closest('form').on('submit', sync);
    });
    </script>
    <?php
}

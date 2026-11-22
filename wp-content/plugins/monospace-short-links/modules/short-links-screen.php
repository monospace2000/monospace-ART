<?php
/**
 * Pages → Short links: the owner's list of short addresses (see short-links-redirect.php for how they work).
 * Admin UI style (CLAUDE.md): WP-native parts and state chips. The links are a plain list (one line each: address,
 * where it goes, state; Edit opens the fields in place) - a folding panel per link was clunky for a list of records.
 * Only the "Ideas" group is a closed panel (Core's kit when it exists, the same few rules printed here otherwise).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
    add_submenu_page( 'edit.php?post_type=page', __( 'Short links', 'monospace-short-links' ), __( 'Short links', 'monospace-short-links' ), 'manage_options', 'monospace-short-links', 'monospace_short_links_screen' );
} );

/**
 * The pages as the tree they are in (2026-11-16, owner: the editor did not show the page hierarchy): [ [ id, depth, title, trail, uri, selectable ], ... ]
 * in tree order (menu order, then title). get_pages() leaves out every page whose parent is not published - with the page folders (Private
 * parents) that was almost all of them - so the whole list is read flat and the tree is built here: a published page, and the unpublished
 * folders above it (shown, but not selectable).
 */
function monospace_short_links_pages_tree() {
    static $rows = null;
    if ( $rows !== null ) return $rows;
    $pages = get_pages( [ 'post_status' => [ 'publish', 'private', 'draft' ], 'hierarchical' => 0, 'number' => 0, 'sort_column' => 'menu_order,post_title' ] );
    $by_id = [];
    $by_parent = [];
    foreach ( $pages as $p ) { $by_id[ $p->ID ] = $p; $by_parent[ (int) $p->post_parent ][] = $p; }
    $keep = [];
    foreach ( $pages as $p ) {
        if ( $p->post_status !== 'publish' ) continue;
        for ( $id = $p->ID; $id && isset( $by_id[ $id ] ) && empty( $keep[ $id ] ); $id = (int) $by_id[ $id ]->post_parent ) $keep[ $id ] = true;
    }
    $title = function ( $p ) { $t = trim( wp_strip_all_tags( (string) $p->post_title ) ); return $t !== '' ? $t : __( '(no title)', 'monospace-short-links' ); };
    $rows = [];
    $walk = function ( $parent, $depth, $trail ) use ( &$walk, &$rows, $by_parent, $keep, $title ) {
        foreach ( $by_parent[ $parent ] ?? [] as $p ) {
            if ( empty( $keep[ $p->ID ] ) ) continue;
            $t = $trail;
            $t[] = $title( $p );
            $rows[] = [ 'id' => $p->ID, 'depth' => $depth, 'title' => $title( $p ), 'trail' => implode( ' › ', $t ), 'uri' => get_page_uri( $p ), 'selectable' => $p->post_status === 'publish' ];
            $walk( $p->ID, $depth + 1, $t );
        }
    };
    $walk( 0, 0, [] );
    return $rows;
}

/** [ group label => [ target => label ] ] for the "Goes to" dropdown (a page's label is its trail: Services › Commissions › Policies) */
function monospace_short_links_choices() {
    $groups = [];
    foreach ( monospace_short_links_pages_tree() as $row ) {
        if ( $row['selectable'] ) $groups[ __( 'Pages', 'monospace-short-links' ) ][ 'post:' . $row['id'] ] = $row['trail'];
    }
    // This screen lives under edit.php, where other plugins hide categories from the post editor's checklists
    // (get_terms_args on $pagenow) - a short link may still lead to those, so look the terms up as on any other screen
    global $pagenow;
    $real_pagenow = $pagenow;
    $pagenow = 'admin.php';
    foreach ( [ 'category' => __( 'Categories', 'monospace-short-links' ), 'product_cat' => __( 'Product categories', 'monospace-short-links' ) ] as $tax => $label ) {
        $terms = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false ] );
        if ( is_wp_error( $terms ) ) continue;
        $out = [];
        foreach ( $terms as $t ) {
            $trail = array_reverse( array_map( function ( $id ) use ( $tax ) { $x = get_term( $id, $tax ); return $x ? $x->name : ''; }, get_ancestors( $t->term_id, $tax, 'taxonomy' ) ) );
            $trail[] = $t->name;
            $out[ "term:$tax:" . $t->term_id ] = implode( ' › ', $trail );
        }
        asort( $out );
        if ( $out ) $groups[ $label ] = $out;
    }
    $pagenow = $real_pagenow;
    return $groups;
}

/** The address each target leads to, looked up once per page (shown under the dropdown) */
function monospace_short_links_dest( $target ) {
    static $cache = [];
    if ( ! isset( $cache[ $target ] ) ) $cache[ $target ] = monospace_short_links_url( $target );
    return $cache[ $target ];
}

/**
 * <option>s for a dropdown. $full = every choice, no selection (printed once, the shared list); otherwise only
 * "Another address…" and the current choice - the script fills in the rest when the dropdown is used, so a page
 * with many links stays small.
 */
function monospace_short_links_options( $choices, $current, $full = false ) {
    $is_url = strpos( $current, 'url:' ) === 0 || $current === 'url';
    $html   = '<option value="url"' . ( $full ? '' : selected( $is_url, true, false ) ) . '>' . esc_html__( 'Another address…', 'monospace-short-links' ) . '</option>';
    $found  = $is_url;
    foreach ( $choices as $group => $items ) {
        $out = '';
        if ( $group === __( 'Pages', 'monospace-short-links' ) ) {
            // The page tree: indented in tree order, each with its address; a private folder is a heading that can't be picked
            foreach ( monospace_short_links_pages_tree() as $row ) {
                $indent = str_repeat( '— ', (int) $row['depth'] );
                $path   = '/' . $row['uri'] . '/';
                if ( ! $row['selectable'] ) {
                    if ( $full ) $out .= '<option disabled>' . esc_html( $indent . $row['title'] . '   ' . $path . ' · ' . __( 'folder', 'monospace-short-links' ) ) . '</option>';
                    continue;
                }
                $value = 'post:' . $row['id'];
                $sel   = ! $full && $value === $current;
                if ( ! $full && ! $sel ) continue;
                $found = $found || $sel;
                $out  .= '<option value="' . esc_attr( $value ) . '" data-url="' . esc_attr( monospace_short_links_dest( $value ) ) . '"' . selected( $sel, true, false ) . '>' . esc_html( $indent . $row['title'] . '   ' . $path ) . '</option>';
            }
            if ( $out ) $html .= '<optgroup label="' . esc_attr( $group ) . '">' . $out . '</optgroup>';
            continue;
        }
        foreach ( $items as $value => $label ) {
            $sel = ! $full && $value === $current;
            if ( ! $full && ! $sel ) continue;
            $found = $found || $sel;
            $out .= '<option value="' . esc_attr( $value ) . '" data-url="' . esc_attr( monospace_short_links_dest( $value ) ) . '"' . selected( $sel, true, false ) . '>' . esc_html( $label ) . '</option>';
        }
        if ( $out ) $html .= '<optgroup label="' . esc_attr( $group ) . '">' . $out . '</optgroup>';
    }
    if ( ! $full && ! $found && $current !== '' ) $html = '<option value="' . esc_attr( $current ) . '" selected>' . esc_html__( '(missing - pick another)', 'monospace-short-links' ) . '</option>' . $html;
    return $html;
}

/** Turns the posted dropdown + address field into a stored target ('' = nothing usable) */
function monospace_short_links_target_from_post( $choice, $address ) {
    $choice = sanitize_text_field( wp_unslash( $choice ) );
    if ( $choice === 'url' ) {
        $address = trim( sanitize_text_field( wp_unslash( $address ) ) );
        $address = preg_replace( '#^https?://' . preg_quote( (string) wp_parse_url( home_url(), PHP_URL_HOST ), '#' ) . '#i', '', $address );
        if ( $address === '' ) return '';
        if ( $address[0] !== '/' && ! preg_match( '#^https?://#i', $address ) ) $address = '/' . $address;
        return 'url:' . $address;
    }
    return preg_match( '/^(post:\d+|term:[a-z_]+:\d+)$/', $choice ) ? $choice : '';
}

add_action( 'admin_post_monospace_short_links_save', function () {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'Not allowed.', 'monospace-short-links' ) );
    check_admin_referer( 'monospace_short_links_save' );

    $old = [];
    foreach ( monospace_short_links_all() as $l ) $old[ $l['slug'] ] = true;

    $list = [];
    $seen = [];
    $problems = [];
    $rows = [];
    foreach ( (array) ( $_POST['rows'] ?? [] ) as $r )  $rows[] = [ 'row' => (array) $r, 'new' => false ];
    foreach ( (array) ( $_POST['ideas'] ?? [] ) as $r ) { if ( ! empty( $r['on'] ) ) $rows[] = [ 'row' => (array) $r, 'new' => true ]; }
    $new_row = (array) ( $_POST['new'] ?? [] );
    if ( trim( (string) ( $new_row['slug'] ?? '' ) ) !== '' ) $rows[] = [ 'row' => $new_row, 'new' => true ];

    foreach ( $rows as $entry ) {
        $r = $entry['row'];
        if ( ! empty( $r['remove'] ) ) continue;
        $slug = monospace_short_links_clean_slug( wp_unslash( $r['slug'] ?? '' ) );
        if ( $slug === '' ) continue;
        $target = monospace_short_links_target_from_post( $r['choice'] ?? '', $r['address'] ?? '' );
        $code   = (int) ( $r['code'] ?? 302 ) === 301 ? 301 : 302;
        if ( isset( $seen[ $slug ] ) ) { $problems[] = sprintf( __( '/%s is in the list twice - kept the first.', 'monospace-short-links' ), $slug ); continue; }
        // An existing link may keep its slug even if a page has since taken it (the screen flags it); a new one may not
        $problem = ( $entry['new'] || ! isset( $old[ $slug ] ) ) ? monospace_short_links_slug_problem( $slug ) : '';
        if ( $problem ) { $problems[] = sprintf( '/%s: %s', $slug, $problem ); continue; }
        if ( $target === '' ) { $problems[] = sprintf( __( '/%s has nowhere to go - not saved.', 'monospace-short-links' ), $slug ); continue; }
        $seen[ $slug ] = true;
        $note   = mb_substr( sanitize_text_field( wp_unslash( $r['note'] ?? '' ) ), 0, 300 );
        $list[] = [ 'slug' => $slug, 'target' => $target, 'code' => $code ] + ( $note !== '' ? [ 'note' => $note ] : [] ) + ( ! empty( $r['below'] ) ? [ 'below' => 1 ] : [] );
    }

    update_option( MONOSPACE_SHORT_LINKS_OPTION, $list, false );
    if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache();
    set_transient( 'monospace_short_links_notice_' . get_current_user_id(), $problems, 60 );
    wp_safe_redirect( admin_url( 'edit.php?post_type=page&page=monospace-short-links&saved=1' ) );
    exit;
} );

/** Ideas: the menus' own items, as short links not made yet */
function monospace_short_links_ideas( $taken ) {
    $ideas = [];
    $seen_target = [];
    foreach ( wp_get_nav_menus() as $menu ) {
        foreach ( (array) wp_get_nav_menu_items( $menu ) as $item ) {
            $target = '';
            if ( $item->type === 'post_type' && in_array( $item->object, [ 'page', 'post' ], true ) ) $target = 'post:' . (int) $item->object_id;
            elseif ( $item->type === 'taxonomy' ) $target = 'term:' . $item->object . ':' . (int) $item->object_id;
            elseif ( $item->type === 'custom' && $item->url !== '' && $item->url[0] !== '#' ) {
                $path = monospace_short_links_site_path( $item->url );
                if ( $path === '' ) continue;
                $target = 'url:' . $path . ( wp_parse_url( $item->url, PHP_URL_QUERY ) ? '?' . wp_parse_url( $item->url, PHP_URL_QUERY ) : '' );
            }
            if ( $target === '' || isset( $seen_target[ $target ] ) ) continue;
            $slug = monospace_short_links_clean_slug( sanitize_title( $item->title ) );
            if ( $slug === '' || isset( $taken[ $slug ] ) || monospace_short_links_slug_problem( $slug ) !== '' ) continue;
            $seen_target[ $target ] = true;
            $ideas[] = [ 'slug' => $slug, 'target' => $target, 'title' => $item->title ];
        }
    }
    // already linked targets aren't ideas
    foreach ( monospace_short_links_all() as $l ) {
        $ideas = array_values( array_filter( $ideas, function ( $i ) use ( $l ) { return $i['target'] !== $l['target']; } ) );
    }
    return $ideas;
}

/** The same few panel rules as Core's admin UI kit, for a site that has no kit (only the Ideas panel uses them) */
function monospace_short_links_fallback_css() {
    ?>
    <style>
        .lm-panel { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; }
        .lm-panel > summary { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 10px; padding: 12px 14px; cursor: pointer; list-style: none; }
        .lm-panel > summary::-webkit-details-marker { display: none; }
        .lm-panel > summary::before { content: "▸"; color: #8c8f94; width: 10px; }
        .lm-panel[open] > summary::before { content: "▾"; }
        .lm-panel[open] > summary { border-bottom: 1px solid #f0f0f1; }
        .lm-t { font-size: 14px; font-weight: 600; }
        .lm-sub { flex-basis: 100%; padding-left: 20px; color: #646970; }
        .lm-chip { font-size: 11px; padding: 1px 8px; border-radius: 10px; background: #f0f0f1; color: #50575e; }
        .lm-chip.is-on { background: #e7f0fa; color: #135e96; }
        .lm-chip.is-warn { background: #fcf0f1; color: #8a2424; }
        .lm-body { padding: 12px 14px 14px; }
    </style>
    <?php
}

/** The inputs of one link (or of the add bar): $name is the form key prefix */
function monospace_short_links_inputs( $name, $link, $choices ) {
    $target = $link['target'] ?? '';
    $is_url = strpos( $target, 'url:' ) === 0;
    $code   = (int) ( $link['code'] ?? 302 );
    $host   = preg_replace( '#^https?://#', '', home_url( '/' ) );
    ?>
    <div class="ms-sl-grid">
        <label class="ms-sl-f ms-sl-f-slug"><span><?php esc_html_e( 'Short address', 'monospace-short-links' ); ?></span>
            <span class="ms-sl-slug"><span class="ms-sl-host"><?php echo esc_html( $host ); ?></span><input type="text" name="<?php echo esc_attr( $name ); ?>[slug]" value="<?php echo esc_attr( $link['slug'] ?? '' ); ?>" placeholder="paintings" autocomplete="off" spellcheck="false"></span></label>
        <label class="ms-sl-f ms-sl-f-to"><span><?php esc_html_e( 'Goes to', 'monospace-short-links' ); ?></span>
            <select name="<?php echo esc_attr( $name ); ?>[choice]" class="ms-sl-choice"><?php echo monospace_short_links_options( $choices, $is_url ? 'url' : $target ); // phpcs:ignore WordPress.Security.EscapeOutput ?></select>
            <input type="text" name="<?php echo esc_attr( $name ); ?>[address]" value="<?php echo esc_attr( $is_url ? substr( $target, 4 ) : '' ); ?>" placeholder="/art-filter/?sort=price" class="ms-sl-address" <?php echo $is_url ? '' : 'hidden'; ?>>
            <span class="ms-sl-dest description"><?php echo esc_html( preg_replace( '#^https?://#', '', (string) monospace_short_links_dest( $target ) ) ); ?></span></label>
        <label class="ms-sl-f"><span><?php esc_html_e( 'Redirect', 'monospace-short-links' ); ?></span>
            <select name="<?php echo esc_attr( $name ); ?>[code]">
                <option value="302"<?php selected( $code !== 301 ); ?>><?php esc_html_e( 'Temporary (302)', 'monospace-short-links' ); ?></option>
                <option value="301"<?php selected( $code === 301 ); ?>><?php esc_html_e( 'Permanent (301)', 'monospace-short-links' ); ?></option>
            </select></label>
        <label class="ms-sl-f ms-sl-f-below"><span class="ms-sl-tickrow"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[below]" value="1" <?php checked( ! empty( $link['below'] ) ); ?>> <?php esc_html_e( 'Also forward everything below this address (/address/anything leads to the target’s /anything)', 'monospace-short-links' ); ?></span></label>
        <label class="ms-sl-f ms-sl-f-note"><span><?php esc_html_e( 'Admin note', 'monospace-short-links' ); ?> <span class="description"><?php esc_html_e( '(only you see this)', 'monospace-short-links' ); ?></span></span>
            <input type="text" name="<?php echo esc_attr( $name ); ?>[note]" value="<?php echo esc_attr( $link['note'] ?? '' ); ?>" maxlength="300" placeholder="<?php esc_attr_e( 'e.g. what it is for, or when it can be removed', 'monospace-short-links' ); ?>"></label>
    </div>
    <?php
}

function monospace_short_links_screen() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( function_exists( 'monospace_lm_area_assets' ) ) monospace_lm_area_assets();
    else monospace_short_links_fallback_css();
    $choices = monospace_short_links_choices();
    $names   = [];
    foreach ( $choices as $items ) foreach ( $items as $value => $label ) $names[ $value ] = $label;
    $links   = monospace_short_links_all();
    $taken   = [];
    foreach ( $links as $l ) $taken[ $l['slug'] ] = true;
    $ideas   = monospace_short_links_ideas( $taken );
    $problems = get_transient( 'monospace_short_links_notice_' . get_current_user_id() );
    delete_transient( 'monospace_short_links_notice_' . get_current_user_id() );
    $host   = preg_replace( '#^https?://#', '', home_url( '/' ) );
    $has_qr = function_exists( 'monospace_qr_svg' ); // Core's QR helper - without it the QR button is left out
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Short links', 'monospace-short-links' ); ?></h1>
        <p class="ms-sl-intro"><?php esc_html_e( 'Short, shareable addresses that lead to a page, a category or any address on the site. A short link only answers where the site has no page of its own.', 'monospace-short-links' ); ?></p>
        <?php if ( ! empty( $_GET['saved'] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'monospace-short-links' ); ?></p></div>
        <?php endif; ?>
        <?php if ( $problems ) : ?>
            <div class="notice notice-warning"><p><strong><?php esc_html_e( 'Not saved:', 'monospace-short-links' ); ?></strong></p><ul style="list-style:disc;margin-left:20px"><?php foreach ( $problems as $p ) echo '<li>' . esc_html( $p ) . '</li>'; ?></ul></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="ms-sl">
            <input type="hidden" name="action" value="monospace_short_links_save">
            <?php wp_nonce_field( 'monospace_short_links_save' ); ?>

            <div class="ms-sl-card ms-sl-add">
                <h2><?php esc_html_e( 'Add a short link', 'monospace-short-links' ); ?></h2>
                <?php monospace_short_links_inputs( 'new', [], $choices ); ?>
                <button type="submit" class="button button-primary"><?php esc_html_e( 'Add link', 'monospace-short-links' ); ?></button>
            </div>

            <div class="ms-sl-card">
                <div class="ms-sl-head">
                    <h2><?php echo esc_html( sprintf( _n( '%d short link', '%d short links', count( $links ), 'monospace-short-links' ), count( $links ) ) ); ?></h2>
                    <?php if ( count( $links ) > 6 ) : ?><input type="search" class="ms-sl-filter" placeholder="<?php esc_attr_e( 'Filter…', 'monospace-short-links' ); ?>" aria-label="<?php esc_attr_e( 'Filter short links', 'monospace-short-links' ); ?>"><?php endif; ?>
                </div>
                <?php if ( ! $links ) : ?><p class="ms-sl-empty"><?php esc_html_e( 'No short links yet - add the first one above.', 'monospace-short-links' ); ?></p><?php endif; ?>
                <ul class="ms-sl-list">
                <?php foreach ( $links as $i => $l ) :
                    $target = $l['target'] ?? '';
                    $url    = monospace_short_links_url( $target );
                    $clash  = (bool) get_page_by_path( $l['slug'], OBJECT, [ 'page', 'post', 'product' ] );
                    $perm   = (int) ( $l['code'] ?? 302 ) === 301;
                    $name   = $names[ $target ] ?? ( strpos( $target, 'url:' ) === 0 ? __( 'Custom address', 'monospace-short-links' ) : '' );
                    ?>
                    <li class="ms-sl-item" data-q="<?php echo esc_attr( strtolower( $l['slug'] . ' ' . $name . ' ' . $url . ' ' . ( $l['note'] ?? '' ) ) ); ?>">
                        <div class="ms-sl-view">
                            <div class="ms-sl-main" role="button" tabindex="0" aria-expanded="false" title="<?php esc_attr_e( 'Click to edit', 'monospace-short-links' ); ?>">
                                <span class="ms-sl-addr"><span class="ms-sl-h"><?php echo esc_html( $host ); ?></span><strong><?php echo esc_html( $l['slug'] ); ?></strong><?php echo ! empty( $l['below'] ) ? '<span class="ms-sl-h">/…</span>' : ''; ?></span>
                                <div class="ms-sl-to">
                                    <span aria-hidden="true">→</span>
                                    <?php if ( $name !== '' ) : ?><span class="ms-sl-name"><?php echo esc_html( $name ); ?></span><?php endif; ?>
                                    <span class="ms-sl-url"><?php echo esc_html( $url !== '' ? preg_replace( '#^https?://#', '', $url ) : '' ); ?></span>
                                </div>
                                <?php if ( ! empty( $l['note'] ) ) : ?><div class="ms-sl-note"><span><?php esc_html_e( 'Note:', 'monospace-short-links' ); ?></span> <?php echo esc_html( $l['note'] ); ?></div><?php endif; ?>
                            </div>
                            <div class="ms-sl-side">
                                <?php if ( $clash ) : ?><span class="lm-chip is-warn"><?php esc_html_e( 'A page uses this address', 'monospace-short-links' ); ?></span>
                                <?php elseif ( $url === '' ) : ?><span class="lm-chip is-warn"><?php esc_html_e( 'Target missing', 'monospace-short-links' ); ?></span>
                                <?php else : ?><span class="lm-chip is-on"><?php echo $perm ? esc_html__( 'Permanent', 'monospace-short-links' ) : esc_html__( 'Works', 'monospace-short-links' ); ?></span><?php endif; ?>
                                <a class="button button-small ms-sl-open" href="<?php echo esc_url( monospace_short_links_public_url( $l['slug'] ) ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Open the short address in a new tab', 'monospace-short-links' ); ?>"><span class="dashicons dashicons-external"></span><span class="screen-reader-text"><?php esc_html_e( 'Open in a new tab', 'monospace-short-links' ); ?></span></a>
                                <button type="button" class="button button-small ms-sl-copy" data-url="<?php echo esc_attr( monospace_short_links_public_url( $l['slug'] ) ); ?>" title="<?php esc_attr_e( 'Copy the short address', 'monospace-short-links' ); ?>"><span class="dashicons dashicons-admin-page"></span><span class="screen-reader-text"><?php esc_html_e( 'Copy', 'monospace-short-links' ); ?></span></button>
                                <?php if ( $has_qr ) : ?><button type="button" class="button button-small ms-sl-qr-btn" aria-expanded="false" title="<?php esc_attr_e( 'QR code for this short address', 'monospace-short-links' ); ?>">QR</button><?php endif; ?>
                            </div>
                        </div>
                        <?php if ( $has_qr ) :
                            $public = monospace_short_links_public_url( $l['slug'] );
                            $png    = function_exists( 'imagecreate' ) ? monospace_qr_png( $public, [ 'size' => 14 ] ) : '';
                            ?>
                        <div class="ms-sl-qr" hidden>
                            <?php echo monospace_qr_svg( $public, [ 'size' => 150, 'bg' => '#ffffff', 'label' => sprintf( /* translators: %s: a short address */ __( 'QR code for %s', 'monospace-short-links' ), preg_replace( '#^https?://#', '', $public ) ) ] ); // phpcs:ignore WordPress.Security.EscapeOutput -- built by the QR helper ?>
                            <div>
                                <code class="ms-sl-qr-url"><?php echo esc_html( preg_replace( '#^https?://#', '', $public ) ); ?></code>
                                <p class="ms-sl-qr-actions">
                                    <?php if ( $png !== '' ) : ?>
                                    <button type="button" class="button ms-sl-qr-copy"><?php esc_html_e( 'Copy QR image', 'monospace-short-links' ); ?></button>
                                    <a class="button ms-sl-qr-png" download="<?php echo esc_attr( 'qr-' . sanitize_title( $l['slug'] ) . '.png' ); ?>" href="data:image/png;base64,<?php echo esc_attr( base64_encode( $png ) ); ?>"><?php esc_html_e( 'Download PNG', 'monospace-short-links' ); ?></a>
                                    <?php endif; ?>
                                    <button type="button" class="button ms-sl-copy" data-url="<?php echo esc_attr( $public ); ?>"><?php esc_html_e( 'Copy address', 'monospace-short-links' ); ?></button>
                                </p>
                                <p class="description"><?php esc_html_e( 'Points at the short address, so the printed code keeps working when you change where the link goes.', 'monospace-short-links' ); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="ms-sl-edit" hidden>
                            <?php monospace_short_links_inputs( "rows[$i]", $l, $choices ); ?>
                            <label class="ms-sl-remove"><input type="checkbox" name="rows[<?php echo (int) $i; ?>][remove]" value="1"> <?php esc_html_e( 'Remove this link when saving', 'monospace-short-links' ); ?></label>
                        </div>
                    </li>
                <?php endforeach; ?>
                </ul>
            </div>

            <?php if ( $ideas ) : ?>
            <div class="lm-areas"><div class="lm-panels">
            <details class="lm-panel" data-panel="ideas">
                <summary><span class="lm-t"><?php esc_html_e( 'Ideas from your menus', 'monospace-short-links' ); ?></span> <span class="lm-chip"><?php echo esc_html( sprintf( _n( '%d idea', '%d ideas', count( $ideas ), 'monospace-short-links' ), count( $ideas ) ) ); ?></span><span class="lm-sub"><?php esc_html_e( 'Menu items with no short link yet - tick the ones to add, change the address if you like.', 'monospace-short-links' ); ?></span></summary>
                <div class="lm-body">
                    <?php // One card per idea, the same fields as the edit box (a table made the short address a sliver and cut off the menu name) ?>
                    <ul class="ms-sl-ideas">
                    <?php foreach ( $ideas as $i => $idea ) :
                        $is_url = strpos( $idea['target'], 'url:' ) === 0; ?>
                        <li class="ms-sl-idea">
                            <input type="checkbox" class="ms-sl-idea-tick" name="ideas[<?php echo (int) $i; ?>][on]" value="1" aria-label="<?php echo esc_attr( sprintf( __( 'Add a short link for the menu item %s', 'monospace-short-links' ), $idea['title'] ) ); ?>">
                            <div class="ms-sl-idea-body">
                                <div class="ms-sl-idea-from"><?php echo esc_html( sprintf( __( 'From the menu: %s', 'monospace-short-links' ), $idea['title'] ) ); ?></div>
                                <label class="ms-sl-f"><span><?php esc_html_e( 'Short address', 'monospace-short-links' ); ?></span>
                                    <span class="ms-sl-slug"><span class="ms-sl-host"><?php echo esc_html( $host ); ?></span><input type="text" name="ideas[<?php echo (int) $i; ?>][slug]" value="<?php echo esc_attr( $idea['slug'] ); ?>" autocomplete="off" spellcheck="false"></span></label>
                                <label class="ms-sl-f ms-sl-f-to"><span><?php esc_html_e( 'Goes to', 'monospace-short-links' ); ?></span>
                                    <select name="ideas[<?php echo (int) $i; ?>][choice]" class="ms-sl-choice"><?php echo monospace_short_links_options( $choices, $is_url ? 'url' : $idea['target'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?></select>
                                    <input type="text" name="ideas[<?php echo (int) $i; ?>][address]" value="<?php echo esc_attr( $is_url ? substr( $idea['target'], 4 ) : '' ); ?>" class="ms-sl-address" <?php echo $is_url ? '' : 'hidden'; ?>>
                                    <input type="hidden" name="ideas[<?php echo (int) $i; ?>][code]" value="302">
                                    <span class="ms-sl-dest description"><?php echo esc_html( preg_replace( '#^https?://#', '', (string) monospace_short_links_dest( $idea['target'] ) ) ); ?></span></label>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                </div>
            </details>
            </div></div>
            <?php endif; ?>

            <?php submit_button( __( 'Save changes', 'monospace-short-links' ) ); ?>
        </form>
    </div>
    <select id="ms-sl-master" hidden aria-hidden="true" tabindex="-1"><?php echo monospace_short_links_options( $choices, '', true ); // phpcs:ignore WordPress.Security.EscapeOutput ?></select>
    <style>
        .ms-sl { max-width: 1100px; }
        .ms-sl-intro { max-width: 760px; color: #50575e; }
        .ms-sl-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; padding: 14px 16px; margin: 0 0 16px; }
        .ms-sl-card h2 { margin: 0 0 10px; font-size: 14px; }
        .ms-sl-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 4px; }
        .ms-sl-head h2 { margin: 0; }
        .ms-sl-filter { width: 200px; max-width: 50%; }
        .ms-sl-empty { color: #646970; margin: 8px 0 0; }
        /* The short address has a row of its own (owner, 2026-11-16: "so I can read the whole thing"); Goes to and Redirect share the next one */
        .ms-sl-grid { display: grid; grid-template-columns: minmax(260px, 1fr) 190px; gap: 12px 16px; align-items: start; margin-bottom: 12px; }
        .ms-sl-f-slug { grid-column: 1 / -1; }
        .ms-sl-f { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
        .ms-sl-f > span:first-child { font-size: 12px; font-weight: 600; color: #50575e; }
        .ms-sl-f select { max-width: 100%; width: 100%; }
        .ms-sl-f-below { grid-column: 1 / -1; }
        .ms-sl-f-below .ms-sl-tickrow { font-weight: 400; color: #1d2327; }
        .ms-sl-f-note { grid-column: 1 / -1; }
        .ms-sl-f-note input { width: 100%; }
        .ms-sl-f-note .description { font-weight: 400; }
        .ms-sl-note { margin-top: 3px; color: #8a5a00; font-size: 12px; }
        .ms-sl-note span { font-weight: 600; }
        .ms-sl-slug { display: flex; align-items: center; border: 1px solid #8c8f94; border-radius: 4px; background: #fff; overflow: hidden; }
        .ms-sl-slug:focus-within { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
        .ms-sl-host { padding: 0 6px 0 8px; color: #646970; white-space: nowrap; background: #f6f7f7; align-self: stretch; display: flex; align-items: center; border-right: 1px solid #dcdcde; margin-right: 6px; font-weight: 400 !important; font-size: 13px !important; }
        .ms-sl-slug input { border: 0 !important; box-shadow: none !important; min-width: 0; width: 100%; padding-left: 0; }
        /* Addresses are set in monospace everywhere (owner, 2026-10-30) */
        .ms-sl-addr, .ms-sl-url, .ms-sl-dest, .ms-sl-host, .ms-sl-slug input, .ms-sl-address { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        .ms-sl-addr { font-size: 14px; } .ms-sl-url, .ms-sl-dest, .ms-sl-host, .ms-sl-slug input, .ms-sl-address { font-size: 12.5px; }
        .ms-sl-address { margin-top: 4px; width: 100%; }
        .ms-sl-address[hidden] { display: none; }
        .ms-sl-dest { word-break: break-all; font-weight: 400 !important; }
        .ms-sl-dest:empty { display: none; }
        .ms-sl-list { list-style: none; margin: 0; padding: 0; }
        .ms-sl-item { border-top: 1px solid #f0f0f1; padding: 12px 0; }
        .ms-sl-item:first-child { border-top: 0; }
        .ms-sl-item[hidden] { display: none; }
        /* A row: the shortcut, then the destination's name, then its address, then the note - one per line, wrapping, never cut off.
           The status chip and buttons sit to the right, or drop below when there is no room (they used to overlap the address). */
        .ms-sl-view { display: flex; flex-wrap: wrap; align-items: flex-start; gap: 8px 16px; }
        .ms-sl-main { flex: 1 1 320px; min-width: 0; cursor: pointer; }
        .ms-sl-addr { display: block; font-size: 15px; text-decoration: none; color: #1d2327; overflow-wrap: anywhere; }
        .ms-sl-addr .ms-sl-h { color: #8c8f94; }
        .ms-sl-to { position: relative; margin-top: 4px; padding-left: 20px; color: #646970; }
        .ms-sl-to > span[aria-hidden] { position: absolute; left: 2px; top: 0; }
        .ms-sl-name { display: block; color: #1d2327; font-weight: 600; overflow-wrap: anywhere; }
        .ms-sl-url { display: block; margin-top: 1px; overflow-wrap: anywhere; }
        .ms-sl-side { display: flex; align-items: center; gap: 6px; flex: 0 0 auto; margin-left: auto; }
        .ms-sl-copy .dashicons, .ms-sl-open .dashicons { font-size: 16px; width: 16px; height: 16px; margin-top: 3px; }
        .ms-sl-edit { margin-top: 12px; padding: 14px 16px 6px; background: #dcdfe3; border: 1px solid #b4b8bd; border-radius: 6px; } /* clearly darker than the card while editing */
        .ms-sl-edit[hidden] { display: none; }
        .ms-sl-qr { margin-top: 12px; padding: 14px 16px; background: #dcdfe3; border: 1px solid #b4b8bd; border-radius: 6px; display: flex; gap: 18px; align-items: center; }
        .ms-sl-qr[hidden] { display: none; }
        .ms-sl-qr svg { flex: none; border-radius: 4px; }
        .ms-sl-qr-url { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 13px; background: none; padding: 0; }
        .ms-sl-qr-actions { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 6px; }
        .ms-sl-remove { display: inline-block; margin: 0 0 10px; color: #b32d2e; }
        .ms-sl-add .button-primary { margin-top: 0; }
        .ms-sl-ideas { list-style: none; margin: 0; padding: 0; }
        .ms-sl-idea { display: flex; align-items: flex-start; gap: 14px; padding: 14px 0; border-top: 1px solid #f0f0f1; }
        .ms-sl-idea:first-child { border-top: 0; padding-top: 4px; }
        .ms-sl-idea-tick { flex: 0 0 auto; margin-top: 2px !important; }
        .ms-sl-idea-body { flex: 1; min-width: 0; display: grid; gap: 10px; max-width: 760px; }
        .ms-sl-idea-from { color: #646970; }
        .ms-sl .lm-panels { margin-bottom: 8px; }
        /* A narrow screen: the site's name goes on a line above the short address, so a long one is readable whole */
        @media (max-width: 560px) {
            .ms-sl-slug { flex-wrap: wrap; }
            .ms-sl-host { flex: 1 0 100%; margin-right: 0; padding: 3px 8px; border-right: 0; border-bottom: 1px solid #dcdcde; }
            .ms-sl-slug input { padding-left: 8px; }
        }
        @media (max-width: 782px) {
            .ms-sl-grid { grid-template-columns: 1fr; }
            .ms-sl-side { margin-left: 0; }
        }
    </style>
    <script>
    (function () {
        var home = <?php echo wp_json_encode( untrailingslashit( home_url() ) ); ?>;
        function show(cell) {
            var sel = cell.querySelector('.ms-sl-choice'), box = cell.querySelector('.ms-sl-address'), out = cell.querySelector('.ms-sl-dest');
            if (!sel || !out) return;
            var url = '';
            if (sel.value === 'url') {
                var a = box.value.trim();
                if (a) url = /^https?:\/\//i.test(a) ? a : home + (a.charAt(0) === '/' ? '' : '/') + a;
            } else if (sel.selectedOptions[0]) url = sel.selectedOptions[0].dataset.url || '';
            out.textContent = url.replace(/^https?:\/\//, '');
        }
        // A dropdown starts with just its current choice; the first time it is used it gets the whole list
        var master = document.getElementById('ms-sl-master');
        function fill(e) {
            var sel = e.target.closest ? e.target.closest('.ms-sl-choice') : null;
            if (!sel || sel.dataset.full) return;
            var val = sel.value;
            sel.innerHTML = master.innerHTML;
            sel.value = val;
            if (sel.value !== val) { var o = document.createElement('option'); o.value = val; o.textContent = <?php echo wp_json_encode( __( '(missing - pick another)', 'monospace-short-links' ) ); ?>; sel.insertBefore(o, sel.firstChild); sel.value = val; }
            sel.dataset.full = '1';
        }
        ['mouseover', 'focusin', 'touchstart'].forEach(function (ev) { document.addEventListener(ev, fill, { passive: true }); });
        document.addEventListener('change', function (e) {
            if (!e.target.classList.contains('ms-sl-choice')) return;
            var cell = e.target.parentNode, box = cell.querySelector('.ms-sl-address');
            if (box) box.hidden = e.target.value !== 'url';
            show(cell);
        });
        document.addEventListener('input', function (e) {
            if (e.target.classList.contains('ms-sl-address')) show(e.target.parentNode);
            if (e.target.classList.contains('ms-sl-filter')) {
                var q = e.target.value.trim().toLowerCase();
                document.querySelectorAll('.ms-sl-item').forEach(function (li) { li.hidden = q !== '' && li.dataset.q.indexOf(q) === -1; });
            }
        });
        document.addEventListener('click', function (e) {
            var c = e.target.closest('.ms-sl-copy');
            if (c) {
                var done = function () { var t = c.innerHTML; c.textContent = <?php echo wp_json_encode( __( 'Copied', 'monospace-short-links' ) ); ?>; setTimeout(function () { c.innerHTML = t; }, 1200); };
                if (navigator.clipboard) navigator.clipboard.writeText(c.dataset.url).then(done);
                return;
            }
            var qb = e.target.closest('.ms-sl-qr-btn');
            if (qb) {
                var qbox = qb.closest('.ms-sl-item').querySelector('.ms-sl-qr'), qopen = qbox.hidden;
                qbox.hidden = !qopen;
                qb.setAttribute('aria-expanded', qopen ? 'true' : 'false');
                return;
            }
            var qc = e.target.closest('.ms-sl-qr-copy');
            if (qc) {
                var png = qc.parentNode.querySelector('.ms-sl-qr-png'), label = qc.textContent;
                var say = function (t) { qc.textContent = t; setTimeout(function () { qc.textContent = label; }, 1600); };
                if (!png || !navigator.clipboard || !window.ClipboardItem) { say(<?php echo wp_json_encode( __( 'Not supported here - use Download', 'monospace-short-links' ) ); ?>); return; }
                fetch(png.href).then(function (r) { return r.blob(); })
                    .then(function (blob) { return navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]); })
                    .then(function () { say(<?php echo wp_json_encode( __( 'Copied', 'monospace-short-links' ) ); ?>); })
                    .catch(function () { say(<?php echo wp_json_encode( __( 'Could not copy - use Download', 'monospace-short-links' ) ); ?>); });
                return;
            }
            var m = e.target.closest('.ms-sl-main');
            if (m && !(window.getSelection && String(window.getSelection()))) {
                var panel = m.closest('.ms-sl-item').querySelector('.ms-sl-edit');
                panel.hidden = !panel.hidden;
                m.setAttribute('aria-expanded', panel.hidden ? 'false' : 'true');
            }
        });
        document.addEventListener('keydown', function (e) {
            if ((e.key === 'Enter' || e.key === ' ') && e.target.classList && e.target.classList.contains('ms-sl-main')) { e.preventDefault(); e.target.click(); }
        });
    })();
    </script>
    <?php
}

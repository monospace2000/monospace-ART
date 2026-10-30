<?php
/**
 * Design module: Sharing icons (2026-10-21 as "Share button"; reworked 2026-10-24 - owner: "Sharing icons should be a new panel in
 * Look. Global toggle, services config, show / hide on tags / categories (default show on all, including Art Feed), show / hide for
 * Pages, posts, etc, with individual overrides. Keep current locations. On Pages, icons are right aligned on the same line as the
 * page title").
 *
 *   Posts       right-aligned on the caption's row under the post's first picture (the artwork's title pill); a post
 *               without a caption has none
 *   Pages       right-aligned on the page title's row (index.php, .entry-title-row) - not when the page's title is hidden
 *   Archives    right-aligned on the title's row of a category / tag / place header and of the Art Feed's header (feed-headers.php,
 *               hook monospace_feed_title_after) - not when the header is in the sidebar, has no title or is switched off
 * Look > Sharing icons: a master switch, which services (the share menu; the own buttons on computers), and the kinds of page
 * (Art Feed, categories, tags, posts, pages). One post, page, category or tag can differ: its edit screen has Use the Look setting /
 * Show / Hide (post meta and term meta `_monospace_share`). The master switch beats every override.
 * On computers (a mouse: hover + fine pointer) Facebook, Instagram and Bluesky have their own buttons left of it, with the
 * footer's icons (menu-icons.php) - owner, 2026-10-21. Instagram can't take a link from a website: its button copies
 * the link and opens instagram.com to paste it. Phones and tablets: the share sheet covers them.
 * Tapping it: the device's share sheet (navigator.share - phones, iPads, Safari, Edge…); without one, a small menu: Copy
 * link, Pinterest (with the page's share picture - the SEO plugin's monospace_seo_image() when it's on, else the post's
 * featured image), Facebook, Bluesky, Email. Plain links - no third-party scripts, nothing tracks, no consent needed.
 * Option monospace_share [ on, kinds, menu, buttons ] - only what differs from the default is stored (the 2026-10-21 form,
 * [ posts, archives ] yes / no, is still read). The look: _share.scss; the script: assets/scripts/share.js (only on pages that
 * show the icons).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The kinds of page that show the icons: key => [ label, where ] */
function monospace_share_kinds() {
    return [
        'feed'     => [ 'Art Feed',   'on the title’s row of its header (when the header has a title)' ],
        'category' => [ 'Categories', 'on the title’s row of a category page’s header' ],
        'tag'      => [ 'Tags',       'on the title’s row of a tag or place page’s header' ],
        'post'     => [ 'Posts',      'on the caption’s row under the artwork' ],
        'page'     => [ 'Pages',      'on the page title’s row' ],
    ];
}

/** The services: key => [ label, can be in the share menu, can be an own button on computers ] */
function monospace_share_services() {
    return [
        'copy'      => [ 'Copy link', true,  false ],
        'pinterest' => [ 'Pinterest', true,  false ],
        'facebook'  => [ 'Facebook',  true,  true ],
        'instagram' => [ 'Instagram', false, true ],
        'bluesky'   => [ 'Bluesky',   true,  true ],
        'email'     => [ 'Email',     true,  false ],
    ];
}

function monospace_share_defaults() {
    return [
        'on'      => true,
        'kinds'   => array_fill_keys( array_keys( monospace_share_kinds() ), true ),
        'menu'    => [ 'copy', 'pinterest', 'facebook', 'bluesky', 'email' ],
        'buttons' => [ 'facebook', 'instagram', 'bluesky' ],
    ];
}

function monospace_share_settings() {
    $d = monospace_share_defaults();
    $s = get_option( 'monospace_share', [] );
    $s = is_array( $s ) ? $s : [];
    $out = $d;
    $out['on'] = ( $s['on'] ?? 'yes' ) !== 'no';
    foreach ( array_keys( $d['kinds'] ) as $k ) $out['kinds'][ $k ] = ( $s['kinds'][ $k ] ?? 'yes' ) !== 'no';
    // The 2026-10-21 form: posts / archives
    if ( ( $s['posts'] ?? '' ) === 'no' ) $out['kinds']['post'] = false;
    if ( ( $s['archives'] ?? '' ) === 'no' ) $out['kinds']['category'] = $out['kinds']['tag'] = false;
    foreach ( [ 'menu', 'buttons' ] as $list ) if ( isset( $s[ $list ] ) && is_array( $s[ $list ] ) ) $out[ $list ] = array_values( array_intersect( array_keys( monospace_share_services() ), $s[ $list ] ) );
    return $out;
}

/** Does this kind of page show the icons? $override: the item's own '' / 'show' / 'hide' (the master switch beats it) */
function monospace_share_visible( $kind, $override = '' ) {
    $s = monospace_share_settings();
    if ( ! $s['on'] ) return false;
    if ( $override === 'show' ) return true;
    if ( $override === 'hide' ) return false;
    return ! empty( $s['kinds'][ $kind ] );
}

/** A term's kind: tags and places are 'tag', every other taxonomy (categories, product categories) 'category' */
function monospace_share_term_kind( $term ) {
    return in_array( $term->taxonomy, [ 'post_tag', 'product_tag' ], true ) || ! is_taxonomy_hierarchical( $term->taxonomy ) ? 'tag' : 'category';
}

function monospace_share_override( $value ) {
    return in_array( $value, [ 'show', 'hide' ], true ) ? $value : '';
}

/** The button and its menu for a page: its address, title, a line of text, a picture */
function monospace_share_html( $url, $title, $text, $image = '' ) {
    wp_enqueue_script( 'ms-share', get_template_directory_uri() . '/assets/scripts/share.js', [], (string) filemtime( get_template_directory() . '/assets/scripts/share.js' ), true );
    $set  = monospace_share_settings();
    $u = rawurlencode( $url );
    $links = [
        'pinterest' => 'https://www.pinterest.com/pin/create/button/?url=' . $u . ( $image ? '&media=' . rawurlencode( $image ) : '' ) . '&description=' . rawurlencode( $text ),
        'facebook'  => 'https://www.facebook.com/sharer/sharer.php?u=' . $u,
        'bluesky'   => 'https://bsky.app/intent/compose?text=' . rawurlencode( $text . ' ' . $url ),
        'email'     => 'mailto:?subject=' . rawurlencode( $title ) . '&body=' . rawurlencode( $text . "\n\n" . $url ),
    ];
    $labels = array_map( fn( $v ) => $v[0], monospace_share_services() );
    $icon = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"/><path d="M7.5 7.5 12 3l4.5 4.5"/><path d="M5 11v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8"/></svg>';
    // Computers: the chosen sites as buttons (the icon drawn in the text color - a mask, as in the footer)
    $icon_url  = fn( $key ) => function_exists( 'monospace_menu_icon_file_url' ) ? monospace_menu_icon_file_url( $key ) : '';
    $site_btns = '';
    foreach ( array_intersect( [ 'facebook', 'instagram', 'bluesky' ], $set['buttons'] ) as $key ) {
        $href = $key === 'instagram' ? 'https://www.instagram.com/' : $links[ $key ];
        $site_btns .= '<a href="' . esc_url( $href ) . '" class="ms-share-site" target="_blank" rel="noopener"' . ( $key === 'instagram' ? ' data-share-instagram' : '' )
                    . ' title="' . esc_attr( $key === 'instagram' ? 'Copy the link for Instagram' : 'Share on ' . $labels[ $key ] ) . '" aria-label="' . esc_attr( 'Share on ' . $labels[ $key ] ) . '"'
                    . ' style="--ms-icon:url(\'' . esc_url( $icon_url( $key ) ) . '\')"></a>';
    }
    $menu = $set['menu'] ?: [ 'copy' ]; // the menu is the way without a share sheet - never empty
    $out  = '<div class="ms-share" data-url="' . esc_url( $url ) . '" data-title="' . esc_attr( $title ) . '" data-text="' . esc_attr( $text ) . '">'
          . ( $site_btns ? '<span class="ms-share-sites">' . $site_btns . '</span>' : '' )
          . '<a href="' . esc_url( $url ) . '" class="ms-share-btn" role="button" aria-haspopup="menu" aria-expanded="false" aria-label="Share" title="Share">' . $icon . '</a>'
          . '<div class="ms-share-menu" role="menu" hidden>';
    foreach ( [ 'copy', 'pinterest', 'facebook', 'bluesky', 'email' ] as $key ) {
        if ( ! in_array( $key, $menu, true ) ) continue;
        if ( $key === 'copy' ) { $out .= '<a href="' . esc_url( $url ) . '" role="menuitem" data-share-copy>Copy link</a>'; continue; }
        $out .= '<a href="' . esc_url( $links[ $key ], [ 'https', 'mailto' ] ) . '" role="menuitem"' . ( $key === 'email' ? '' : ' target="_blank" rel="noopener"' ) . '>' . esc_html( $labels[ $key ] ) . '</a>';
    }
    return $out . '</div></div>';
}

/** The page's share picture (the SEO plugin's, as in the link preview), else the post's featured image */
function monospace_share_image( $post_id = 0 ) {
    if ( function_exists( 'monospace_seo_image' ) ) { $i = monospace_seo_image(); if ( ! empty( $i['url'] ) ) return $i['url']; }
    return $post_id ? (string) get_the_post_thumbnail_url( $post_id, 'large' ) : '';
}

/** A page's icons for its title's row (index.php) - '' when the page shows none */
function monospace_share_page_html() {
    $id = get_queried_object_id();
    if ( ! $id || ! is_page( $id ) || ! monospace_share_visible( 'page', monospace_share_override( get_post_meta( $id, '_monospace_share', true ) ) ) ) return '';
    $title = wp_strip_all_tags( get_the_title( $id ) );
    return monospace_share_html( get_permalink( $id ), $title, $title . ' - ' . get_bloginfo( 'name' ), monospace_share_image( $id ) );
}

// Posts: after the first caption, in its figure (the CSS puts it on the caption's row, on the right) - after the SEO
// plugin has made that caption the <h1> (priority 30)
add_filter( 'the_content', function ( $content ) {
    if ( ! is_singular( 'post' ) || ! in_the_loop() || ! is_main_query() || get_the_ID() !== get_queried_object_id() ) return $content;
    if ( ! monospace_share_visible( 'post', monospace_share_override( get_post_meta( get_the_ID(), '_monospace_share', true ) ) ) ) return $content;
    $end = stripos( $content, '</figcaption>' );
    if ( $end === false ) return $content;
    $id    = get_the_ID();
    $title = wp_strip_all_tags( get_the_title( $id ) );
    $html  = monospace_share_html( get_permalink( $id ), $title, $title . ' - ' . get_bloginfo( 'name' ), monospace_share_image( $id ) );
    $at    = $end + strlen( '</figcaption>' );
    return substr( $content, 0, $at ) . $html . substr( $content, $at );
}, 35 );

// Category / tag / place pages and the Art Feed: on the header's title row
add_action( 'monospace_feed_title_after', function ( $heading = '' ) {
    if ( is_category() || is_tag() || is_tax() ) {
        $term = get_queried_object();
        if ( ! $term || empty( $term->term_id ) || ! monospace_share_visible( monospace_share_term_kind( $term ), monospace_share_override( get_term_meta( $term->term_id, '_monospace_share', true ) ) ) ) return;
        $url   = get_term_link( $term );
        if ( is_wp_error( $url ) ) return;
        $title = wp_strip_all_tags( $heading ?: single_term_title( '', false ) ); // the header's title ("Paintings"), not the term's name
    } elseif ( is_home() ) {
        if ( ! monospace_share_visible( 'feed' ) ) return;
        $posts_page = (int) get_option( 'page_for_posts' );
        $url   = $posts_page ? get_permalink( $posts_page ) : home_url( '/' );
        $title = wp_strip_all_tags( $heading ?: get_bloginfo( 'name' ) );
    } else {
        return;
    }
    echo monospace_share_html( $url, $title . ' - ' . get_bloginfo( 'name' ), $title . ' - ' . get_bloginfo( 'name' ), monospace_share_image() );
}, 10, 1 );

/* ============================================================
 * ONE POST / PAGE / TERM: Use the Look setting, Show or Hide
 * ============================================================ */

/** The select for an item's own choice; $now = what the Look setting gives it */
function monospace_share_override_field( $value, $now ) {
    $value = monospace_share_override( $value );
    $opts  = [ '' => 'Use the Look setting (' . ( $now ? 'shown' : 'hidden' ) . ')', 'show' => 'Show', 'hide' => 'Hide' ];
    $out   = '<input type="hidden" name="ms_share_override_seen" value="1"><select name="ms_share_override">';
    foreach ( $opts as $k => $label ) $out .= '<option value="' . esc_attr( $k ) . '"' . selected( $value, $k, false ) . '>' . esc_html( $label ) . '</option>';
    return $out . '</select>';
}

add_action( 'add_meta_boxes', function () {
    add_meta_box( 'monospace_share_override', 'Sharing icons', function ( $post ) {
        wp_nonce_field( 'monospace_share_override', 'monospace_share_override_nonce' );
        $kind = $post->post_type === 'page' ? 'page' : 'post';
        echo '<p>' . monospace_share_override_field( get_post_meta( $post->ID, '_monospace_share', true ), monospace_share_visible( $kind ) ) . '</p>';
        echo '<p class="description">The share icon' . ( $kind === 'page' ? ' beside the page’s title' : ' under the artwork’s image' ) . '. Appearance → Look → Sharing icons sets it for all ' . ( $kind === 'page' ? 'pages' : 'posts' ) . '; switched off there, it is off everywhere.</p>';
    }, [ 'post', 'page' ], 'side', 'default' );
} );

add_action( 'save_post', function ( $post_id ) {
    if ( empty( $_POST['monospace_share_override_nonce'] ) || ! wp_verify_nonce( $_POST['monospace_share_override_nonce'], 'monospace_share_override' ) ) return;
    if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) return;
    $v = monospace_share_override( wp_unslash( $_POST['ms_share_override'] ?? '' ) );
    $v === '' ? delete_post_meta( $post_id, '_monospace_share' ) : update_post_meta( $post_id, '_monospace_share', $v );
} );

/** Save a term's choice (called from its edit screen's Share image panel - term-areas.php) */
function monospace_save_term_share_override( $term_id ) {
    if ( empty( $_POST['ms_share_override_seen'] ) || ! current_user_can( 'manage_categories' ) ) return;
    $v = monospace_share_override( wp_unslash( $_POST['ms_share_override'] ?? '' ) );
    $v === '' ? delete_term_meta( $term_id, '_monospace_share' ) : update_term_meta( $term_id, '_monospace_share', $v );
}

/* ============================================================
 * THE PANEL (Look)
 * ============================================================ */

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['share'] = [
        'title'       => 'Sharing icons',
        'description' => 'The share icon on artwork captions, page titles, and category, tag and Art Feed headers - and which services it offers.',
        'group'       => 'look',
        'order'       => 46,
        'summary'     => 'monospace_share_summary',
        'render'      => 'monospace_render_share_settings',
        'save'        => 'monospace_save_share_settings',
    ];
    return $modules;
} );

/** The setting in a few words: [ text, chip class ] */
function monospace_share_summary() {
    $s = monospace_share_settings();
    if ( ! $s['on'] ) return 'Off';
    $n = count( array_filter( $s['kinds'] ) );
    return $n === count( $s['kinds'] ) ? 'Everywhere' : ( $n ? $n . ' of ' . count( $s['kinds'] ) . ' kinds of page' : 'Off' );
}

function monospace_render_share_settings() {
    $s = monospace_share_settings(); ?>
    <input type="hidden" name="ms_share[seen]" value="1">
    <table class="form-table" role="presentation">
        <tr><th scope="row">Sharing icons</th><td>
            <label><input type="checkbox" name="ms_share[on]" value="yes" <?php checked( $s['on'] ); ?>> Show the share icon on the site</label>
            <p class="description">The master switch: off, no page shows it, whatever a page’s own setting says.</p>
        </td></tr>
        <tr><th scope="row">Show it on</th><td>
            <?php foreach ( monospace_share_kinds() as $key => [ $label, $where ] ) : ?>
                <label style="display:block;margin-bottom:6px"><input type="checkbox" name="ms_share[kinds][<?php echo esc_attr( $key ); ?>]" value="yes" <?php checked( $s['kinds'][ $key ] ); ?>>
                    <?php echo esc_html( $label ); ?> - <?php echo esc_html( $where ); ?></label>
            <?php endforeach; ?>
            <p class="description">One post, page, category or tag can differ: its own edit screen has <i>Use the Look setting / Show / Hide</i> (posts and pages: the Sharing icons box in the sidebar; categories and tags: the Share image panel).</p>
        </td></tr>
        <tr><th scope="row">Services</th><td>
            <table class="widefat striped" style="max-width:460px"><thead><tr><th>Service</th><th>In the share menu</th><th>Own button on computers</th></tr></thead><tbody>
            <?php foreach ( monospace_share_services() as $key => [ $label, $in_menu, $as_button ] ) : ?>
                <tr><td><?php echo esc_html( $label ); ?></td>
                    <td><?php if ( $in_menu ) : ?><input type="checkbox" name="ms_share[menu][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $s['menu'], true ) ); ?> aria-label="<?php echo esc_attr( $label . ' in the share menu' ); ?>"><?php else : ?>-<?php endif; ?></td>
                    <td><?php if ( $as_button ) : ?><input type="checkbox" name="ms_share[buttons][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $s['buttons'], true ) ); ?> aria-label="<?php echo esc_attr( $label . ' as an own button' ); ?>"><?php else : ?>-<?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody></table>
            <p class="description">Phones, iPads and Safari open the device’s own share sheet (it offers whatever apps the visitor has); elsewhere the small menu. Instagram can’t take a link from a website: its button copies the link and opens instagram.com to paste it. No share scripts from other sites. The menu always keeps at least Copy link.</p>
        </td></tr>
    </table>
    <?php
}

function monospace_save_share_settings() {
    if ( empty( $_POST['ms_share']['seen'] ) ) return;
    $in  = (array) $_POST['ms_share'];
    $d   = monospace_share_defaults();
    $s   = [];
    if ( empty( $in['on'] ) ) $s['on'] = 'no';
    foreach ( array_keys( $d['kinds'] ) as $k ) if ( empty( $in['kinds'][ $k ] ) ) $s['kinds'][ $k ] = 'no';
    $services = array_keys( monospace_share_services() );
    foreach ( [ 'menu', 'buttons' ] as $list ) {
        $chosen = array_values( array_intersect( $services, array_map( 'sanitize_key', (array) ( $in[ $list ] ?? [] ) ) ) );
        if ( $chosen !== $d[ $list ] ) $s[ $list ] = $chosen;
    }
    $s ? update_option( 'monospace_share', $s ) : delete_option( 'monospace_share' );
}

<?php
/**
 * Admin toolbar layout (2026-10-20, owner's wish: a customized WordPress toolbar on phones, and a tool to set it).
 *
 * On phones (up to 782px) the theme used to hide the toolbar; WordPress itself shows only a fixed handful of icons
 * there. With the phone toolbar on, the site shows the items chosen in Settings > Admin toolbar, in that order, in a
 * bar fixed at the top, ONE row that swipes sideways when the items don't fit (2026-11-16, owner: to save screen space; it
 * used to wrap onto a second row) - soft fades at an edge say there is more that way; the theme puts its menu bar under it
 * (body class ms-phone-toolbar, CSS variable --ms-ab-h = the toolbar's measured height - _menu.scss).
 * Inside wp-admin, WordPress's own phone layout stays (wp-admin's pages are laid out for it).
 *
 * On computers any item can be hidden (CSS only - the item stays available on phones), and the items can be put in any
 * order, on the left or the right of the bar (2026-10-20: drag and drop on the settings screen, phones and computers).
 * The computer order is applied as the toolbar is built (wp_before_admin_bar_render, after every plugin has added its
 * items): the top-level items are taken out and put back in the chosen order and side. The phone order is CSS
 * (`order`), so the two never interfere.
 *
 * Tablets and computers (2026-10-21 grew to two rows; 2026-11-16, owner: "make it work at all widths") - the same single
 * row that swipes (scrolls) sideways when the items don't fit, 32px high, the right-hand items kept at the right end;
 * --ms-ab-h still carries the height, so the theme's menu bar and the page start below it. WordPress's search
 * is styled wherever it's placed (its own rules expect it at the right end).
 *
 * Shortcuts this adds (shown on phones by default, hidden on computers by default): New artwork, Artwork Editor,
 * Dashboard. The settings screen lists every top-level item seen in the toolbar (option ms_toolbar_seen, updated
 * as the toolbar is drawn - plugins' items appear once they've been seen), with a preview of the phone row.
 *
 * Option ms_admin_toolbar: [ 'phone_on' => bool, 'phone' => [ node ids, in order ], 'desk_hidden' => [ node ids ],
 * 'desk' => [ 'left' => [ ids ], 'right' => [ ids, as seen on screen, left to right ] ] (empty = WordPress's order) ].
 * Option ms_toolbar_places: WordPress's own order and side of every item seen ([ 'left' => [...], 'right' => [...] ]),
 * so items not placed yet (new plugins) keep their usual place.
 *
 * @package monospace-admin-tools
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The shortcuts this module adds: id => [ label, admin URL, dashicon code, capability ] */
function monospace_toolbar_shortcuts() {
    return [
        'ms-tb-new-artwork' => [ 'New artwork', 'admin.php?page=monospace-artworks', '\f132', 'edit_products' ],
        'ms-tb-editor'      => [ 'Artwork Editor', 'admin.php?page=monospace-artwork-editor', '\f163', 'edit_products' ],
        'ms-tb-dashboard'   => [ 'Dashboard', 'index.php', '\f226', 'edit_posts' ],
    ];
}

/** The saved layout, with its defaults */
function monospace_toolbar_settings() {
    $s = get_option( 'ms_admin_toolbar', null );
    $defaults = [
        'phone_on'    => true,
        'phone'       => [ 'ms-artwork-toolbar', 'ms-artwork-publish', 'edit', 'ms-tb-new-artwork', 'ms-tb-editor', 'ms-tb-dashboard', 'my-account' ],
        'desk_hidden' => array_keys( monospace_toolbar_shortcuts() ),
        'desk'        => [ 'left' => [], 'right' => [] ],
    ];
    if ( ! is_array( $s ) ) return apply_filters( 'monospace_toolbar_settings', $defaults );
    $ids = fn( $list ) => array_values( array_filter( (array) $list, 'is_string' ) );
    // The filter lets a plugin keep its own item visible whatever is saved here (View As: always shown, at every width)
    return apply_filters( 'monospace_toolbar_settings', [
        'phone_on'    => ! empty( $s['phone_on'] ),
        'phone'       => $ids( $s['phone'] ?? [] ),
        'desk_hidden' => $ids( $s['desk_hidden'] ?? [] ),
        'desk'        => [ 'left' => $ids( $s['desk']['left'] ?? [] ), 'right' => $ids( $s['desk']['right'] ?? [] ) ],
    ] );
}

/** A toolbar item id from a form: ids keep their case (Purge SG Cache is SG_CachePress_Supercacher_Purge) */
function monospace_toolbar_id( $id ) {
    return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $id );
}

/** The toolbar's top-level items now: [ 'left' => [ id => node ], 'right' => [ id => node, left to right on screen ] ] */
function monospace_toolbar_top_nodes( $bar ) {
    $out = [ 'left' => [], 'right' => [] ];
    foreach ( (array) $bar->get_nodes() as $id => $node ) {
        if ( in_array( $id, [ 'top-secondary', 'root-default', 'root' ], true ) || $node->group ) continue;
        if ( ! $node->parent ) $out['left'][ $id ] = $node;
        elseif ( $node->parent === 'top-secondary' ) $out['right'][ $id ] = $node;
    }
    $out['right'] = array_reverse( $out['right'], true ); // the right side floats right: its first item is rightmost
    return $out;
}

/** Merge what's on the bar now into a remembered order: new ids go after the item they follow now */
function monospace_toolbar_merge_order( array $known, array $now ) {
    foreach ( $now as $i => $id ) {
        if ( in_array( $id, $known, true ) ) continue;
        $after = null;
        for ( $j = $i - 1; $j >= 0; $j-- ) if ( in_array( $now[ $j ], $known, true ) ) { $after = $now[ $j ]; break; }
        $pos = $after === null ? 0 : array_search( $after, $known, true ) + 1;
        array_splice( $known, $pos, 0, [ $id ] );
    }
    return $known;
}

/** Friendly names for WordPress's own items (their titles are often just an icon or a count) */
function monospace_toolbar_known_names() {
    return [
        'wp-logo' => 'WordPress menu', 'site-name' => 'Site name (Dashboard / Visit site)', 'updates' => 'Updates',
        'comments' => 'Comments', 'new-content' => '+ New', 'edit' => 'Edit this page', 'my-account' => 'Your account',
        'search' => 'Search', 'customize' => 'Customize', 'ms-artwork-toolbar' => 'Artwork: number, price, availability',
        'ms-artwork-publish' => 'Artwork: Publish / Unpublish', 'menu-toggle' => 'Menu (wp-admin)',
    ];
}

// The shortcuts
add_action( 'admin_bar_menu', function ( $bar ) {
    foreach ( monospace_toolbar_shortcuts() as $id => [ $label, $url, , $cap ] ) {
        if ( ! current_user_can( $cap ) ) continue;
        $bar->add_node( [ 'id' => $id, 'title' => '<span class="ab-icon" aria-hidden="true"></span><span class="ms-tb-label">' . esc_html( $label ) . '</span>', 'href' => admin_url( $url ), 'meta' => [ 'title' => $label ] ] );
    }
}, 95 );

// Your account item: just the name (owner, 2026-11-17), without "Howdy," (changed once the toolbar is built, whoever added the item)
add_action( 'wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    $node = $wp_admin_bar ? $wp_admin_bar->get_node( 'my-account' ) : null;
    if ( ! $node || ! is_string( $node->title ) ) return;
    $title = preg_replace( '/^.*?(<span class="display-name">)/s', '$1', $node->title );
    if ( $title !== $node->title ) $wp_admin_bar->add_node( [ 'id' => 'my-account', 'title' => $title ] );
}, 5 );

// Remember the top-level items seen, for the settings screen (written only when the list changes)
add_action( 'wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    if ( ! $wp_admin_bar || ! current_user_can( 'manage_options' ) ) return;
    $seen = (array) get_option( 'ms_toolbar_seen', [] );
    $names = monospace_toolbar_known_names();
    $new = $seen;
    foreach ( (array) $wp_admin_bar->get_nodes() as $node ) {
        if ( $node->parent && $node->parent !== 'top-secondary' ) continue;
        if ( in_array( $node->id, [ 'top-secondary', 'root-default' ], true ) ) continue;
        $label = $names[ $node->id ] ?? trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $node->title ) ) );
        $label = preg_replace( '/^\d+\s*/', '', $label ) ?: $node->id; // counts ("2 Updates")
        $new[ $node->id ] = mb_substr( $label, 0, 60 );
    }
    if ( $new !== $seen ) update_option( 'ms_toolbar_seen', $new, false );

    // WordPress's own order and side, before this module rearranges it (below, priority 20)
    $places = (array) get_option( 'ms_toolbar_places', [] );
    $top    = monospace_toolbar_top_nodes( $wp_admin_bar );
    $merged = [];
    foreach ( [ 'left', 'right' ] as $side ) {
        $other = $side === 'left' ? 'right' : 'left';
        $known = array_values( array_diff( (array) ( $places[ $side ] ?? [] ), array_keys( $top[ $other ] ) ) );
        $merged[ $side ] = monospace_toolbar_merge_order( $known, array_keys( $top[ $side ] ) );
    }
    $merged['left']  = array_values( array_diff( $merged['left'], $merged['right'] ) );
    if ( $merged !== $places ) update_option( 'ms_toolbar_places', $merged, false );
} );

// Computers: the chosen order and side. Items not placed on the settings screen keep their usual side, after the
// placed ones there.
add_action( 'wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    if ( ! $wp_admin_bar ) return;
    $desk = monospace_toolbar_settings()['desk'];
    if ( ! $desk['left'] && ! $desk['right'] ) return;
    $top  = monospace_toolbar_top_nodes( $wp_admin_bar );
    $all  = $top['left'] + $top['right'];
    // wp-admin's Menu button stays first (it only shows on phones, in WordPress's own phone layout)
    $want = [ 'left' => isset( $all['menu-toggle'] ) ? [ 'menu-toggle' => $all['menu-toggle'] ] : [], 'right' => [] ];
    foreach ( [ 'left', 'right' ] as $side ) {
        foreach ( $desk[ $side ] as $id ) if ( isset( $all[ $id ] ) && $id !== 'menu-toggle' ) $want[ $side ][ $id ] = $all[ $id ];
    }
    $placed = array_merge( array_keys( $want['left'] ), array_keys( $want['right'] ) ); // incl. menu-toggle
    foreach ( [ 'left', 'right' ] as $side ) {
        foreach ( $top[ $side ] as $id => $node ) if ( ! in_array( $id, $placed, true ) ) $want[ $side ][ $id ] = $node;
    }
    foreach ( $all as $id => $node ) $wp_admin_bar->remove_node( $id );
    foreach ( $want['left'] as $node ) {
        $args = get_object_vars( $node ); $args['parent'] = false;
        $wp_admin_bar->add_node( $args );
    }
    foreach ( array_reverse( $want['right'], true ) as $node ) {
        $args = get_object_vars( $node ); $args['parent'] = 'top-secondary';
        $wp_admin_bar->add_node( $args );
    }
}, 20 );

// Phones: the body class the theme reads
add_filter( 'body_class', function ( $classes ) {
    if ( is_admin_bar_showing() && monospace_toolbar_settings()['phone_on'] ) $classes[] = 'ms-phone-toolbar';
    return $classes;
} );

/** The layout's CSS: phone row (front end only) and items hidden on computers (everywhere) */
function monospace_toolbar_css( $front ) {
    $s   = monospace_toolbar_settings();
    $sel = fn( $id ) => '#wpadminbar li#wp-admin-bar-' . sanitize_html_class( $id );
    $css = '';
    foreach ( monospace_toolbar_shortcuts() as $id => [ , , $icon ] ) {
        $css .= $sel( $id ) . ' .ab-icon:before{content:"' . $icon . '";top:2px}';
    }
    $css .= '#wpadminbar .ms-tb-label{margin-left:4px}';
    // WordPress's search, wherever it's placed (its own rules only reach it at the right end): an icon that opens a
    // field, with room of its own - it used to sit on the next item
    $css .= '#wpadminbar #wp-admin-bar-search .ab-item{padding:0;background:none}'
          . '#wpadminbar #wp-admin-bar-search #adminbarsearch{position:relative;height:32px;padding:0 4px}'
          . '#wpadminbar #wp-admin-bar-search #adminbarsearch input.adminbar-input{display:inline-block;position:relative;z-index:30;height:24px;width:24px;margin:4px 0 0;padding:0 3px 0 24px;'
          . 'font:13px/1.85 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;color:#c3c4c7;background:transparent;border:0;outline:0;box-shadow:none;box-sizing:border-box;cursor:pointer;'
          . 'transition:width .4s ease,background .4s ease}'
          . '#wpadminbar #wp-admin-bar-search #adminbarsearch input.adminbar-input:focus{width:200px;color:#000;background:rgba(255,255,255,.9);cursor:text}';
    if ( $s['desk_hidden'] ) {
        $css .= '@media screen and (min-width:783px){' . implode( ',', array_map( $sel, $s['desk_hidden'] ) ) . '{display:none!important}}';
    }
    // The edge fades, at every width: the script below turns one on when items are out of sight on that side
    if ( $front ) {
        $css .= '#wpadminbar::before,#wpadminbar::after{content:"";position:absolute;top:0;bottom:0;width:28px;z-index:2;pointer-events:none;opacity:0;transition:opacity .15s}'
              . '#wpadminbar::before{left:0;background:linear-gradient(to right,#1d2327,rgba(29,35,39,0))}'
              . '#wpadminbar::after{right:0;background:linear-gradient(to left,#1d2327,rgba(29,35,39,0))}'
              . '#wpadminbar.ms-tb-more-l::before,#wpadminbar.ms-tb-more-r::after{opacity:1}';
        // Tablets and computers: one row of 32px (a second row once ran under the site's menu bar on an iPad); what doesn't
        // fit swipes / scrolls. The left list and the right list (WordPress floats them) become the two ends of a flex row;
        // the right list keeps WordPress's right-to-left order. Dropdowns are pinned under the bar so the scrolling row
        // doesn't clip them (left stays where the item puts it).
        $css .= '@media screen and (min-width:783px){'
              . '#wpadminbar{height:32px!important;min-height:32px}'
              . '#wpadminbar #wp-toolbar{display:flex;flex-wrap:nowrap;align-items:stretch;height:32px;overflow-x:auto;overflow-y:hidden;overscroll-behavior-x:contain;scrollbar-width:none}'
              . '#wpadminbar #wp-toolbar::-webkit-scrollbar{display:none}'
              . '#wpadminbar #wp-toolbar>ul{float:none!important;display:flex;flex:0 0 auto}'
              . '#wpadminbar #wp-toolbar>ul>li{float:none!important;flex:0 0 auto}'
              . '#wpadminbar #wp-toolbar>ul#wp-admin-bar-top-secondary{order:2;margin-left:auto;flex-direction:row-reverse}'
              // (WordPress gives a pulldown min-width:100% = as wide as its button; pinned with position:fixed that became the whole window, 2026-11-17)
              . '#wpadminbar .ab-sub-wrapper{position:fixed!important;top:32px;min-width:180px!important}'
              . '}';
    }
    // The pulldown panel (see the script below): the same on phones and on touch screens wider than a phone
    $panel_css = '#ms-tb-panel[hidden]{display:none}'
              . '#ms-tb-panel{position:fixed;left:0;right:0;top:var(--ms-ab-h,46px);z-index:99999;max-height:calc(100vh - var(--ms-ab-h,46px) - 24px);overflow-y:auto;-webkit-overflow-scrolling:touch;background:#2c3338;box-shadow:0 4px 8px rgba(0,0,0,.4)}'
              . '#ms-tb-panel ul{list-style:none;margin:0;padding:0}'
              . '#ms-tb-panel a,#ms-tb-panel .ab-item{display:block;padding:12px 16px;color:#f0f0f1;font:16px/1.35 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.08)}'
              . '#ms-tb-panel img,#ms-tb-panel .avatar{display:none}'
              . '#ms-tb-panel .ab-item span{display:block}'
              . '#ms-tb-panel .ab-item .username{font-size:13px;opacity:.65}';
    if ( $front ) {
        $css .= '@media screen and (min-width:783px) and (pointer:coarse){#wpadminbar .ab-sub-wrapper{display:none!important}' . $panel_css
              // wider than a phone: a narrower panel under the button that was tapped (the script sets its left edge)
              . '#ms-tb-panel{right:auto;left:8px;width:360px;max-width:calc(100vw - 16px);border-radius:0 0 6px 6px}}';
    }
    if ( $front && $s['phone_on'] ) {
        $css .= '@media screen and (max-width:782px){'
              . 'html{--ms-ab-h:46px}'
              . '#wpadminbar{position:fixed!important;top:0;height:46px!important;min-height:46px}'
              . '#wpadminbar #wp-toolbar{display:flex;flex-wrap:nowrap;align-items:stretch;height:46px;overflow-x:auto;overflow-y:hidden;overscroll-behavior-x:contain;-webkit-overflow-scrolling:touch;scrollbar-width:none}'
              . '#wpadminbar #wp-toolbar::-webkit-scrollbar{display:none}'
              . '#wpadminbar #wp-toolbar>ul{display:contents}'
              . '#wpadminbar #wp-toolbar>ul>li{display:none!important;float:none;flex:0 0 auto}'
              // A dropdown would be clipped by the scrolling row: it is pinned under the bar instead
              . '#wpadminbar .ab-sub-wrapper{display:none!important}'   // (the pulldown is a panel of its own on phones: see the script below)
              . $panel_css
              // The shortcuts: icon-only, like WordPress's own phone items (the name stays for screen readers / tooltip)
              . '#wpadminbar .ms-tb-label{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}'
              . '#wpadminbar li[id^="wp-admin-bar-ms-tb-"]>.ab-item{width:48px;padding:0!important;text-align:center}'
              . '#wpadminbar li[id^="wp-admin-bar-ms-tb-"] .ab-icon{width:48px!important;height:46px;margin:0!important;font:28px/46px dashicons!important}'
              . '#wpadminbar li[id^="wp-admin-bar-ms-tb-"] .ab-icon:before{top:0;font:28px/46px dashicons}';
        // As specific as the "hide every item" rule above, plus the item's id - so it wins
        foreach ( array_values( $s['phone'] ) as $i => $id ) {
            $css .= '#wpadminbar #wp-toolbar>ul>li#wp-admin-bar-' . sanitize_html_class( $id ) . '{display:block!important;order:' . (int) $i . '}';
        }
        // The artwork's own controls (number, price, availability) and Publish / Unpublish come first in the row, whatever
        // order is saved (2026-11-02, owner); the rest follow, and swipe. (Until 2026-11-16 a full-width break after them
        // made the rest wrap onto a second row.)
        $css .= '#wpadminbar #wp-toolbar>ul>li#wp-admin-bar-my-account{display:block!important;order:1000;flex:0 0 auto}'
              . '#wpadminbar #wp-toolbar>ul>li#wp-admin-bar-ms-artwork-toolbar{order:-2;flex:0 0 auto;box-sizing:border-box}'
              . '#wpadminbar #wp-toolbar>ul>li#wp-admin-bar-ms-artwork-publish{order:-1}';
        $css .= '}';
    }
    return $css;
}

add_action( 'wp_head', function () {
    if ( ! is_admin_bar_showing() ) return;
    echo '<style id="ms-toolbar-layout">' . monospace_toolbar_css( true ) . "</style>\n";
}, 99 );
add_action( 'admin_head', function () { echo '<style id="ms-toolbar-layout">' . monospace_toolbar_css( false ) . "</style>\n"; }, 99 );

// The toolbar's real height for the theme's menu bar - and again on resize. Phones: when the phone toolbar is on (else the
// theme hides the toolbar there); tablets and computers: always
add_action( 'wp_footer', function () {
    if ( ! is_admin_bar_showing() ) return; ?>
    <script id="ms-toolbar-height">
    (function () {
        var phoneOn = <?php echo monospace_toolbar_settings()['phone_on'] ? 'true' : 'false'; ?>;
        // Touch screens at any width - phones, and tablets where the row also swipes (owner, 2026-11-17: "start from scratch"; then "on screens where the
        // admin bar is swipable"): a pulldown is a panel of its own, outside the swipe row. WordPress's pulldown is a
        // fixed menu inside a scrolling box: iOS did not show it reliably (the + New and account menus did not answer a tap on an iPhone front
        // end), and the row's scroll handler below closes a menu on any scroll. A tap on a top-level item with a menu opens a copy of that menu
        // under the toolbar; a second tap or a tap anywhere else closes it. Links in it are the real links. Computers and wp-admin keep WordPress's own.
        (function () {
            var bar = document.getElementById('wpadminbar'), mq = window.matchMedia && window.matchMedia('(max-width: 782px), (pointer: coarse)'), open = null;
            if (!bar || !mq) return;
            var panel = document.createElement('div');
            panel.id = 'ms-tb-panel'; panel.hidden = true;
            document.body.appendChild(panel);
            function close() { panel.hidden = true; panel.innerHTML = ''; open = null; }
            bar.addEventListener('click', function (e) {
                if (!mq.matches) return;
                var item = e.target.closest ? e.target.closest('li.menupop > .ab-item') : null;
                if (!item) return;
                var li = item.parentNode, sub = li.querySelector(':scope > .ab-sub-wrapper');
                if (!sub || (li.parentNode.id !== 'wp-admin-bar-root-default' && li.parentNode.id !== 'wp-admin-bar-top-secondary')) return;
                e.preventDefault(); e.stopPropagation();
                if (open === li) { close(); return; }
                var copy = sub.cloneNode(true);
                [].forEach.call(copy.querySelectorAll('[id]'), function (n) { n.removeAttribute('id'); });
                panel.innerHTML = ''; panel.appendChild(copy); panel.hidden = false; open = li;
                if (window.innerWidth > 782) {   // a tablet: 360px wide under the button, kept inside the screen
                    var w = Math.min(360, window.innerWidth - 16), r = li.getBoundingClientRect();
                    panel.style.width = w + 'px'; panel.style.right = 'auto';
                    panel.style.left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8)) + 'px';
                } else { panel.style.width = panel.style.left = panel.style.right = ''; }
            }, true);
            function outside(e) { var t = e.target; if (!panel.hidden && !panel.contains(t) && !(t.closest && t.closest('#wpadminbar li.menupop'))) close(); }
            document.addEventListener('touchstart', outside, { passive: true });
            document.addEventListener('click', outside);
            if (mq.addEventListener) mq.addEventListener('change', close); else if (mq.addListener) mq.addListener(close);
        })();
        function set() {
            var bar = document.getElementById('wpadminbar'), phone = window.matchMedia('(max-width: 782px)').matches;
            if (!bar) return;
            if (!phone || phoneOn) document.documentElement.style.setProperty('--ms-ab-h', bar.offsetHeight + 'px');
            else document.documentElement.style.removeProperty('--ms-ab-h');
        }
        // The row swipes at every width: fade the edge that has more items behind it
        var tb = document.getElementById('wp-toolbar'), ab = document.getElementById('wpadminbar');
        function edges() {
            if (!tb || !ab) return;
            var max = tb.scrollWidth - tb.clientWidth;
            ab.classList.toggle('ms-tb-more-l', tb.scrollLeft > 4);
            ab.classList.toggle('ms-tb-more-r', tb.scrollLeft < max - 4);
        }
        // An open dropdown is pinned, so scrolling the row closes it
        if (tb) { tb.addEventListener('scroll', function () { edges(); ab.querySelectorAll('li.hover').forEach(function (l) { l.classList.remove('hover'); }); }, { passive: true }); window.addEventListener('load', edges); }
        set(); edges();
        window.addEventListener('resize', function () { set(); edges(); });
        if (window.ResizeObserver) { var b = document.getElementById('wpadminbar'); if (b) new ResizeObserver(function () { set(); edges(); }).observe(b); }
    })();
    </script>
    <?php
}, 1001 ); // after the admin bar itself (wp_footer 1000 in this theme)

/* ============================================================
 * SETTINGS > ADMIN TOOLBAR
 * ============================================================ */

add_action( 'admin_menu', function () {
    add_options_page( 'Admin toolbar', 'Admin toolbar', 'manage_options', 'ms-admin-toolbar', 'monospace_toolbar_settings_page' );
} );

function monospace_toolbar_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $seen = (array) get_option( 'ms_toolbar_seen', [] );
    foreach ( monospace_toolbar_shortcuts() as $id => [ $label ] ) $seen[ $id ] = $label;
    // The usual items are listed even before they've been seen (the artwork ones only show on an artwork's page)
    $names = monospace_toolbar_known_names();
    foreach ( [ 'ms-artwork-toolbar', 'ms-artwork-publish', 'edit', 'new-content', 'site-name', 'my-account', 'wp-logo', 'updates', 'comments' ] as $id ) {
        if ( ! isset( $seen[ $id ] ) ) $seen[ $id ] = $names[ $id ];
    }
    foreach ( $names as $id => $label ) if ( isset( $seen[ $id ] ) ) $seen[ $id ] = $label;

    if ( isset( $_POST['ms_toolbar_save'] ) && check_admin_referer( 'ms_admin_toolbar' ) ) {
        $list = function ( $field ) use ( $seen ) {
            $ids = array_map( 'monospace_toolbar_id', explode( ',', (string) wp_unslash( $_POST[ $field ] ?? '' ) ) );
            return array_values( array_unique( array_filter( $ids, fn( $id ) => $id !== '' && isset( $seen[ $id ] ) ) ) );
        };
        $reset = ! empty( $_POST['ms_desk_reset'] );
        update_option( 'ms_admin_toolbar', [
            'phone_on'    => ! empty( $_POST['ms_phone_on'] ),
            'phone'       => $list( 'ms_phone_order' ),
            'desk_hidden' => $list( 'ms_desk_hidden' ),
            'desk'        => $reset ? [ 'left' => [], 'right' => [] ] : [ 'left' => $list( 'ms_desk_left' ), 'right' => $list( 'ms_desk_right' ) ],
        ] );
        echo '<div class="notice notice-success is-dismissible"><p>' . ( $reset ? 'Saved - the computer toolbar is back in WordPress’s order.' : 'Saved. Reload any page to see the toolbar; on a phone (or a narrow window) for the phone layout.' ) . '</p></div>';
    }
    $s = monospace_toolbar_settings();

    // Phones: the chosen ones in their order; the tray: the rest
    $phone      = array_values( array_intersect( $s['phone'], array_keys( $seen ) ) );
    $phone_tray = array_values( array_diff( array_keys( $seen ), $phone ) );
    // Computers: the saved order and sides, else WordPress's (remembered as the toolbar is drawn); hidden ones in the tray
    $places = (array) get_option( 'ms_toolbar_places', [] );
    $left   = $s['desk']['left'];
    $right  = $s['desk']['right'];
    foreach ( [ 'left', 'right' ] as $side ) {
        foreach ( (array) ( $places[ $side ] ?? [] ) as $id ) {
            if ( ! in_array( $id, $left, true ) && ! in_array( $id, $right, true ) ) ${$side}[] = $id;
        }
    }
    foreach ( array_keys( $seen ) as $id ) if ( ! in_array( $id, $left, true ) && ! in_array( $id, $right, true ) ) $left[] = $id;
    $hidden = array_values( array_diff( array_intersect( array_keys( $seen ), $s['desk_hidden'] ), [ 'menu-toggle' ] ) );
    // (wp-admin's Menu button only shows on phones - not on the computer bar)
    $known  = fn( $ids ) => array_values( array_filter( $ids, fn( $id ) => isset( $seen[ $id ] ) && $id !== 'menu-toggle' && ! in_array( $id, $hidden, true ) ) );
    $left   = $known( $left );
    $right  = $known( $right );

    $chip = fn( $state, $text ) => '<span class="lm-chip is-' . esc_attr( $state ) . '">' . esc_html( $text ) . '</span>';
    $item = function ( $id ) use ( $seen ) {
        $short = [ 'ms-artwork-toolbar' => 'Artwork: no. · price · status', 'ms-artwork-publish' => 'Artwork: Publish', 'site-name' => 'Site name', 'menu-toggle' => 'Menu (wp-admin)' ];
        $label = $short[ $id ] ?? $seen[ $id ];
        return '<button type="button" class="ms-tb-item" data-id="' . esc_attr( $id ) . '" title="' . esc_attr( $seen[ $id ] . ' (' . $id . ')' ) . '">' . esc_html( $label ) . '</button>';
    };
    $zone = fn( $name, $ids, $class ) => '<div class="ms-tb-zone ' . $class . '" data-zone="' . $name . '">' . implode( '', array_map( $item, $ids ) ) . '</div>';
    ?>
    <div class="wrap" id="ms-tb">
        <h1>Admin toolbar</h1>
        <p class="description" style="max-width:820px">The black WordPress toolbar at the top of the site while you're logged in. <strong>Drag the items</strong> into
            the order you want - on phones and on computers separately; drag an item into the tray below a bar to leave it out there.
            Keyboard: ← → move the focused item, ↓ puts it in the tray, ↑ brings it back. The list grows as the toolbar is seen -
            other plugins' items appear after they've been on a page you opened.</p>
        <form method="post" id="ms-tb-form">
            <?php wp_nonce_field( 'ms_admin_toolbar' ); ?>
            <input type="hidden" name="ms_phone_order" value="">
            <input type="hidden" name="ms_desk_left" value="">
            <input type="hidden" name="ms_desk_right" value="">
            <input type="hidden" name="ms_desk_hidden" value="">
            <div class="lm-areas" style="max-width:1100px"><div class="lm-panels">

                <!-- PHONES -->
                <details class="lm-panel" open>
                    <summary><span class="lm-t">On phones</span> <span id="ms-tb-chip-phone"><?php echo $s['phone_on'] ? $chip( 'on', count( $phone ) . ' items' ) : $chip( 'off', 'Off - the toolbar is hidden on phones' ); ?></span>
                        <span class="lm-sub">Screens up to 782px wide, on the site (wp-admin keeps WordPress's own phone toolbar)</span></summary>
                    <div class="lm-body">
                        <p><label><input type="checkbox" name="ms_phone_on" value="1" class="lm-show-sidebar" <?php checked( $s['phone_on'] ); ?>> Show the toolbar on phones</label></p>
                        <div class="lm-sidebar-fields"<?php echo $s['phone_on'] ? '' : ' hidden'; ?>>
                            <div class="ms-tb-group" data-group="phone">
                                <p class="lm-label">The phone toolbar <span class="description">- as wide as a phone; more than fits swipes sideways, in one row (a soft fade shows which side has more)</span></p>
                                <?php echo $zone( 'phone', $phone, 'ms-tb-bar ms-tb-phone' ); ?>
                                <p class="lm-label">Not on phones</p>
                                <?php echo $zone( 'phone-tray', $phone_tray, 'ms-tb-tray' ); ?>
                            </div>
                        </div>
                    </div>
                </details>

                <!-- COMPUTERS -->
                <details class="lm-panel" open>
                    <summary><span class="lm-t">On computers</span> <span id="ms-tb-chip-desk"></span>
                        <span class="lm-sub">Wider screens, on the site and in wp-admin - the left and the right end of the bar</span></summary>
                    <div class="lm-body">
                        <div class="ms-tb-group" data-group="desk">
                            <p class="lm-label">The toolbar</p>
                            <div class="ms-tb-bar ms-tb-desk">
                                <?php echo $zone( 'desk-left', $left, 'ms-tb-side' ); ?>
                                <span class="ms-tb-gap" aria-hidden="true"></span>
                                <?php echo $zone( 'desk-right', $right, 'ms-tb-side ms-tb-right' ); ?>
                            </div>
                            <p class="lm-label">Hidden on computers</p>
                            <?php echo $zone( 'desk-tray', $hidden, 'ms-tb-tray' ); ?>
                        </div>
                        <p><label><input type="checkbox" name="ms_desk_reset" value="1"> Back to WordPress's order (keeps what's hidden)</label></p>
                    </div>
                </details>
            </div></div>
            <p class="submit"><button type="submit" name="ms_toolbar_save" value="1" class="button button-primary">Save</button></p>
        </form>
    </div>
    <style>
        #ms-tb .ms-tb-zone { display: flex; flex-wrap: wrap; align-content: flex-start; gap: 0; min-height: 40px; box-sizing: border-box; }
        #ms-tb .ms-tb-bar { background: #1d2327; border-radius: 6px; padding: 0 4px; }
        #ms-tb .ms-tb-phone { width: 390px; max-width: 100%; min-height: 46px; }
        #ms-tb .ms-tb-desk { display: flex; align-items: stretch; min-height: 40px; }
        #ms-tb .ms-tb-side { flex: 1 1 0; min-width: 60px; }               /* the left side wraps when it's long */
        #ms-tb .ms-tb-gap { flex: 0 0 18px; border-left: 1px dashed #50575e; margin: 6px 0; }
        #ms-tb .ms-tb-right { flex: 0 0 auto; flex-wrap: nowrap; justify-content: flex-end; } /* the right end stays one line */
        #ms-tb .ms-tb-tray { background: #f6f7f7; border: 2px dashed #c3c4c7; border-radius: 6px; padding: 4px; gap: 4px; min-height: 44px; }
        #ms-tb .ms-tb-tray:empty::before { content: "Drag items here to leave them out"; color: #8c8f94; font-size: 12px; line-height: 32px; padding: 0 8px; }
        #ms-tb .ms-tb-item { all: unset; box-sizing: border-box; cursor: grab; touch-action: none; user-select: none; -webkit-user-select: none;
            color: #f0f0f1; font: 13px/40px -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 0 10px; white-space: nowrap; border-right: 1px solid #3c434a; }
        #ms-tb .ms-tb-phone .ms-tb-item { line-height: 46px; }
        #ms-tb .ms-tb-item:hover { background: #2c3338; color: #72aee6; }
        #ms-tb .ms-tb-item:focus-visible { outline: 2px solid #72aee6; outline-offset: -2px; }
        #ms-tb .ms-tb-tray .ms-tb-item { color: #50575e; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; line-height: 30px; }
        #ms-tb .ms-tb-tray .ms-tb-item:hover { color: #2271b1; border-color: #2271b1; }
        #ms-tb .ms-tb-item.is-placeholder { opacity: .35; outline: 2px dashed #72aee6; outline-offset: -3px; }
        #ms-tb .ms-tb-ghost { position: fixed; z-index: 100000; pointer-events: none; background: #2271b1 !important; color: #fff !important; border-radius: 4px; box-shadow: 0 6px 18px rgba(0,0,0,.35); opacity: .95; cursor: grabbing; }
        #ms-tb .ms-tb-zone.is-over { box-shadow: 0 0 0 2px #72aee6 inset; }
        #ms-tb.is-dragging, #ms-tb.is-dragging * { cursor: grabbing !important; }
    </style>
    <script>
    (function () {
        var root = document.getElementById('ms-tb'), form = document.getElementById('ms-tb-form');
        if (!root) return;
        function zone(name) { return root.querySelector('[data-zone="' + name + '"]'); }
        function ids(name) { return Array.prototype.map.call(zone(name).querySelectorAll('.ms-tb-item:not(.is-placeholder)'), function (b) { return b.dataset.id; }); }
        function chip(id, state, text) { document.getElementById(id).innerHTML = '<span class="lm-chip is-' + state + '">' + text + '</span>'; }
        function sync() {
            form.ms_phone_order.value = ids('phone').join(',');
            form.ms_desk_left.value   = ids('desk-left').join(',');
            form.ms_desk_right.value  = ids('desk-right').join(',');
            form.ms_desk_hidden.value = ids('desk-tray').join(',');
            var on = form.ms_phone_on.checked, n = ids('phone').length, h = ids('desk-tray').length;
            chip('ms-tb-chip-phone', on ? 'on' : 'off', on ? n + ' items' : 'Off - the toolbar is hidden on phones');
            chip('ms-tb-chip-desk', h ? 'on' : 'empty', h ? h + ' hidden' : 'All shown');
        }
        form.addEventListener('change', sync);

        // Dragging - pointer events, so a mouse and a finger both work. An item can move within its group's zones
        // (phone bar / phone tray; computer left / right / tray), never from one group to the other.
        var drag = null;
        function zoneAt(x, y, group) {
            var el = document.elementFromPoint(x, y), z = el && el.closest('.ms-tb-zone');
            if (z && z.closest('.ms-tb-group') === group) return z;
            // Over the gap in the middle of the computer bar: the nearer side
            var bar = el && el.closest('.ms-tb-desk');
            if (bar && bar.closest('.ms-tb-group') === group) {
                var r = bar.getBoundingClientRect(); return zone(x < r.left + r.width / 2 ? 'desk-left' : 'desk-right');
            }
            return null;
        }
        function place(z, x, y) {
            // Before the first item whose middle is past the pointer, row by row
            var items = Array.prototype.filter.call(z.querySelectorAll('.ms-tb-item'), function (b) { return b !== drag.ph; }), before = null;
            for (var i = 0; i < items.length; i++) {
                var r = items[i].getBoundingClientRect();
                if (y < r.top) { before = items[i]; break; }
                if (y <= r.bottom && x < r.left + r.width / 2) { before = items[i]; break; }
            }
            if (before) z.insertBefore(drag.ph, before); else z.appendChild(drag.ph);
        }
        root.addEventListener('pointerdown', function (e) {
            var b = e.target.closest('.ms-tb-item'); if (!b || e.button > 0) return;
            drag = { item: b, x: e.clientX, y: e.clientY, id: e.pointerId, moving: false, group: b.closest('.ms-tb-group') };
        });
        document.addEventListener('pointermove', function (e) {
            if (!drag || e.pointerId !== drag.id) return;
            if (!drag.moving) {
                if (Math.abs(e.clientX - drag.x) + Math.abs(e.clientY - drag.y) < 6) return;
                drag.moving = true;
                var r = drag.item.getBoundingClientRect();
                drag.dx = drag.x - r.left; drag.dy = drag.y - r.top;
                drag.ghost = drag.item.cloneNode(true); drag.ghost.classList.add('ms-tb-ghost');
                drag.ghost.style.width = r.width + 'px'; root.appendChild(drag.ghost);
                drag.ph = drag.item; drag.ph.classList.add('is-placeholder');
                root.classList.add('is-dragging');
            }
            e.preventDefault();
            drag.ghost.style.left = (e.clientX - drag.dx) + 'px'; drag.ghost.style.top = (e.clientY - drag.dy) + 'px';
            var z = zoneAt(e.clientX, e.clientY, drag.group);
            root.querySelectorAll('.ms-tb-zone.is-over').forEach(function (o) { if (o !== z) o.classList.remove('is-over'); });
            if (z) { z.classList.add('is-over'); place(z, e.clientX, e.clientY); }
        }, { passive: false });
        function end(e) {
            if (!drag || e.pointerId !== drag.id) return;
            if (drag.moving) {
                drag.ph.classList.remove('is-placeholder'); drag.ghost.remove();
                root.classList.remove('is-dragging');
                root.querySelectorAll('.ms-tb-zone.is-over').forEach(function (o) { o.classList.remove('is-over'); });
                drag.item.focus({ preventScroll: true }); sync();
            }
            drag = null;
        }
        document.addEventListener('pointerup', end);
        document.addEventListener('pointercancel', end);
        // A drag is not a click (the items are buttons)
        root.addEventListener('click', function (e) { if (e.target.closest('.ms-tb-item')) e.preventDefault(); });

        // Keyboard: ← → within a zone (and across the computer bar's two sides), ↓ to the tray, ↑ back out of it
        var trayOf = { 'phone': 'phone-tray', 'desk-left': 'desk-tray', 'desk-right': 'desk-tray' };
        root.addEventListener('keydown', function (e) {
            var b = e.target.closest('.ms-tb-item'); if (!b) return;
            var z = b.parentNode, name = z.dataset.zone, done = true;
            if (e.key === 'ArrowLeft') {
                if (b.previousElementSibling) z.insertBefore(b, b.previousElementSibling);
                else if (name === 'desk-right') zone('desk-left').appendChild(b);
                else done = false;
            } else if (e.key === 'ArrowRight') {
                if (b.nextElementSibling) z.insertBefore(b.nextElementSibling, b);
                else if (name === 'desk-left') zone('desk-right').insertBefore(b, zone('desk-right').firstChild);
                else done = false;
            } else if (e.key === 'ArrowDown' && trayOf[name]) zone(trayOf[name]).appendChild(b);
            else if (e.key === 'ArrowUp' && /-tray$/.test(name)) (name === 'phone-tray' ? zone('phone') : zone('desk-left')).appendChild(b);
            else done = false;
            if (done) { e.preventDefault(); b.focus(); sync(); }
        });
        sync();
    })();
    </script>
    <?php
    if ( function_exists( 'monospace_lm_area_assets' ) ) monospace_lm_area_assets(); // the admin UI kit (Core)
}

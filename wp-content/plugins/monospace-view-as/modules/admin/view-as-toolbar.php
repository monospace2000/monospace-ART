<?php
/**
 * View As in the admin toolbar.
 *
 * Idle (you, in admin mode): a "View As" button in the right-hand group (immediately left of the item named in Settings > View As,
 * if any), at every width. It opens a pulldown: the people you looked at last (topped up with the newest users) and "Other..." - the
 * Users screen, which has a View As button on every row (view-as-users.php).
 *
 * While viewing: the toolbar is red and holds nothing else - no other admin tools: "Viewing as <name> - read-only" and the Switch back
 * button.
 *
 * Phones: the same button is a second node in the left-hand group (icon only, at the end of the row): WordPress styles and handles a
 * phone menu from that group, not from the right-hand one. In wp-admin both have WordPress's own pulldown; on the front end of a phone
 * the pulldown is a panel of its own outside the toolbar row. CSS shows one of the two copies.
 *
 * If a plugin keeps a layout of the toolbar (monospace Admin tools does), its filter monospace_toolbar_settings keeps this item
 * out of the "hidden on computers" list whatever is saved there.
 *
 * @package monospace-view-as
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The items this module adds */
function ms_view_as_toolbar_ids() {
    return [ 'ms-view-as', 'ms-view-as-m', 'ms-view-as-info', 'ms-view-as-stop' ];
}

add_filter( 'monospace_toolbar_settings', function ( $s ) {
    $s['desk_hidden'] = array_values( array_diff( (array) $s['desk_hidden'], ms_view_as_toolbar_ids() ) );
    return $s;
} );

/** The people in the pulldown: the ones looked at last, then the newest (the number is a setting) */
function ms_view_as_toolbar_users() {
    $me    = get_current_user_id();
    $max   = max( 1, min( 10, (int) ms_view_as_setting( 'recent' ) ) );
    $users = [];
    foreach ( array_map( 'intval', (array) get_user_meta( $me, 'ms_view_as_recent', true ) ) as $id ) {
        $u = $id ? get_userdata( $id ) : false;
        if ( $u && $id !== $me && ms_view_as_can_view( $u ) ) $users[ $id ] = $u;
        if ( count( $users ) >= $max ) return $users;
    }
    $more = get_users( [ 'orderby' => 'registered', 'order' => 'DESC', 'number' => $max * 4, 'exclude' => array_merge( [ $me ], array_keys( $users ) ) ] );
    foreach ( $more as $u ) {
        if ( ! ms_view_as_can_view( $u ) ) continue;
        $users[ $u->ID ] = $u;
        if ( count( $users ) >= $max ) break;
    }
    return $users;
}

// Idle: the button and its pulldown. Two copies: on computers it sits in the toolbar's right-hand group (left of Purge); on phones
// (owner: the whole toolbar swipes there, there is no need for a right-hand group) the same button is an ordinary item of the left-hand
// group at the end of the row. In wp-admin both have WordPress's own pulldown (it works there). On the front end of a phone the
// pulldown is a panel of its own outside the swipe row (below), and the phone copy is a plain link to the Users screen - which is what
// a tap does if the script is not there.
add_action( 'admin_bar_menu', function ( $bar ) {
    if ( ms_view_as_active() || ! current_user_can( ms_view_as_cap() ) ) return;

    $users = ms_view_as_toolbar_users();
    foreach ( [ 'ms-view-as' => 'top-secondary', 'ms-view-as-m' => false ] as $id => $parent ) {
        $own_menu = $id === 'ms-view-as' || is_admin();
        $bar->add_node( [
            'id'     => $id,
            'parent' => $parent,
            'title'  => '<span class="ab-icon" aria-hidden="true"></span><span class="ms-tb-label">View As</span>',
            'href'   => $own_menu ? '#' : admin_url( 'users.php' ),
            'meta'   => $own_menu ? [ 'onclick' => 'return false;' ] : [],
        ] );
        if ( ! $own_menu ) continue;
        foreach ( $users as $u ) {
            $bar->add_node( [
                'id'     => $id . '-u' . $u->ID,
                'parent' => $id,
                'title'  => esc_html( $u->display_name ) . ' <span class="ms-view-as-sub">' . esc_html( $u->user_email ) . '</span>',
                'href'   => ms_view_as_start_url( $u->ID ),
                'meta'   => [ 'class' => 'ms-view-as-person' ],
            ] );
        }
        $bar->add_node( [ 'id' => $id . '-other', 'parent' => $id, 'title' => 'Other&hellip;', 'href' => admin_url( 'users.php' ) ] );
    }
}, 100 );

// Idle: View As sits immediately left of the toolbar item named in the settings (the right-hand items run right to left in the list)
add_action( 'wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    if ( ! $wp_admin_bar || ms_view_as_active() ) return;
    $purge = (string) ms_view_as_setting( 'place_left_of' );
    if ( $purge === '' ) return;
    $right = [];
    foreach ( (array) $wp_admin_bar->get_nodes() as $id => $node ) {
        if ( ! $node->group && $node->parent === 'top-secondary' ) $right[ $id ] = $node;
    }
    if ( ! isset( $right[ $purge ], $right['ms-view-as'] ) ) return;
    $ids = array_values( array_diff( array_keys( $right ), [ 'ms-view-as' ] ) );
    array_splice( $ids, array_search( $purge, $ids, true ) + 1, 0, [ 'ms-view-as' ] );
    foreach ( $right as $id => $node ) $wp_admin_bar->remove_node( $id );
    foreach ( $ids as $id ) {
        $args = get_object_vars( $right[ $id ] );
        $args['parent'] = 'top-secondary';
        $wp_admin_bar->add_node( $args );
    }
}, 30 );

// Viewing: nothing but the red bar's own two items
add_action( 'wp_before_admin_bar_render', function () {
    global $wp_admin_bar;
    $state = ms_view_as_active();
    if ( ! $wp_admin_bar || ! $state ) return;
    foreach ( (array) $wp_admin_bar->get_nodes() as $id => $node ) {
        if ( ! $node->group ) $wp_admin_bar->remove_node( $id );
    }
    $target = get_userdata( $state['target'] );
    $admin  = get_userdata( $state['admin'] );
    $wp_admin_bar->add_node( [
        'id'    => 'ms-view-as-info',
        'title' => 'Viewing as <strong>' . esc_html( $target ? $target->display_name : '' ) . '</strong> &middot; read-only',
        'meta'  => [ 'title' => 'Nothing done here is sent or saved for them. Items in the cart are cleared when you switch back.' ],
    ] );
    $wp_admin_bar->add_node( [
        'id'     => 'ms-view-as-stop',
        'parent' => 'top-secondary',
        'title'  => '<span class="ab-icon" aria-hidden="true"></span><span class="ms-tb-label">Switch back to ' . esc_html( $admin ? $admin->display_name : '' ) . '</span>',
        'href'   => ms_view_as_stop_url(),
    ] );
}, 99 );

function ms_view_as_toolbar_css() {
    $btn = '#wpadminbar li#wp-admin-bar-ms-view-as>.ab-item,#wpadminbar li#wp-admin-bar-ms-view-as-m>.ab-item';
    $css = $btn . '{font-weight:600;cursor:pointer}'
         . '#wpadminbar li#wp-admin-bar-ms-view-as .ab-icon:before,#wpadminbar li#wp-admin-bar-ms-view-as-m .ab-icon:before,#wpadminbar li#wp-admin-bar-ms-view-as-stop .ab-icon:before{top:2px}'
         . '#wpadminbar li#wp-admin-bar-ms-view-as-stop .ab-icon:before{color:#fff!important}'
         . '#wpadminbar li#wp-admin-bar-ms-view-as .ab-icon:before,#wpadminbar li#wp-admin-bar-ms-view-as-m .ab-icon:before{content:"\f177"}'
         . '#wpadminbar li#wp-admin-bar-ms-view-as-stop .ab-icon:before{content:"\f171"}'
         . '#wpadminbar li#wp-admin-bar-ms-view-as .ab-sub-wrapper{min-width:240px}'
         // The customers: name over email, two lines each (the toolbar's own menu items are one fixed line high)
         . '#wpadminbar li.ms-view-as-person>.ab-item{height:auto!important;line-height:1.35!important;padding:6px 12px!important}'
         . '#wpadminbar .ms-view-as-sub{display:block;font-size:11px;line-height:1.3;opacity:.7}'
         // Computers: the right-hand copy. Phones: the left-hand copy, icon only (the phone toolbar hides the label), at the end of the row
         // that swipes (the phone row hides every item that is not in its list; this one is always shown)
         . '#wpadminbar li#wp-admin-bar-ms-view-as-m{display:none}'
         // (the "html" in front wins over a toolbar layout that lists either copy for the phone row)
         . '@media screen and (max-width:782px){html #wpadminbar #wp-toolbar>ul>li#wp-admin-bar-ms-view-as{display:none!important}'
         . 'html #wpadminbar #wp-toolbar>ul>li#wp-admin-bar-ms-view-as-m{display:block!important;order:900;margin-left:auto}'
         // Icon only, on the front end and in wp-admin alike (WordPress's own phone layout there showed the label)
         . '#wpadminbar li#wp-admin-bar-ms-view-as-m>.ab-item{width:48px;height:46px;line-height:46px;padding:0!important;text-align:center}'
         . '#wpadminbar li#wp-admin-bar-ms-view-as-m .ms-tb-label{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}'
         . '#wpadminbar li#wp-admin-bar-ms-view-as-m .ab-icon{width:48px!important;height:46px;margin:0!important;font:28px/46px dashicons!important}'
         . '#wpadminbar li#wp-admin-bar-ms-view-as-m .ab-icon:before{top:0;font:28px/46px dashicons}'
         // The front end's phone pulldown: a panel hung under the toolbar
         . '#ms-view-as-panel[hidden]{display:none}'
         . '#ms-view-as-panel{position:fixed;left:0;right:0;top:var(--ms-ab-h,46px);z-index:99999;max-height:calc(100vh - var(--ms-ab-h,46px) - 24px);overflow-y:auto;background:#2c3338;box-shadow:0 4px 8px rgba(0,0,0,.4)}'
         . '#ms-view-as-panel a{display:block;padding:12px 16px;color:#f0f0f1;font:16px/1.35 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.08)}'
         . '#ms-view-as-panel a:last-child{border-bottom:0}'
         . '#ms-view-as-panel a span{display:block;font-size:12px;opacity:.65}}';
    if ( ms_view_as_active() ) {
        $red = '#b32d2e';
        $css .= 'body.ms-view-as #wpadminbar{background:' . $red . '!important}'
              . 'body.ms-view-as #wpadminbar::before{background:linear-gradient(to right,' . $red . ',rgba(179,45,46,0))!important}'
              . 'body.ms-view-as #wpadminbar::after{background:linear-gradient(to left,' . $red . ',rgba(179,45,46,0))!important}'
              . 'body.ms-view-as #wpadminbar .ab-item,body.ms-view-as #wpadminbar .ab-empty-item{color:#fff!important}'
              . 'body.ms-view-as #wpadminbar li#wp-admin-bar-ms-view-as-stop>.ab-item{background:#7a1415!important;font-weight:600}'
              . 'body.ms-view-as #wpadminbar li#wp-admin-bar-ms-view-as-stop:hover>.ab-item{background:#5c0f10!important}'
              . '@media screen and (max-width:782px){#wpadminbar #wp-toolbar>ul>li#wp-admin-bar-ms-view-as-info{display:block!important;order:0}'
              . '#wpadminbar #wp-toolbar>ul>li#wp-admin-bar-ms-view-as-stop{display:block!important;order:1;margin-left:auto}'
              . '#wpadminbar li#wp-admin-bar-ms-view-as-stop>.ab-item,#wpadminbar li#wp-admin-bar-ms-view-as-info>.ab-empty-item{height:46px;line-height:46px}'
              . '#wpadminbar li#wp-admin-bar-ms-view-as-info>.ab-empty-item{padding:0 0 0 14px!important}'
              . '#wpadminbar li#wp-admin-bar-ms-view-as-stop .ms-tb-label{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%)}}';
    }
    return $css;
}

add_action( 'wp_head', function () {
    if ( ! is_admin_bar_showing() ) return;
    echo '<style id="ms-view-as-toolbar">' . ms_view_as_toolbar_css() . "</style>\n";
}, 100 );
add_action( 'admin_head', function () {
    echo '<style id="ms-view-as-toolbar">' . ms_view_as_toolbar_css() . "</style>\n";
}, 100 );

// The front end's phone pulldown: the same five customers and Other..., in a panel outside the swipe row. A tap on the button opens it,
// a tap anywhere else closes it. (WordPress's own pulldown inside the row did not open on an iPhone; this does not depend on it.)
add_action( 'wp_footer', function () {
    if ( is_admin() || ! is_admin_bar_showing() || ms_view_as_active() || ! current_user_can( ms_view_as_cap() ) ) return; ?>
    <div id="ms-view-as-panel" hidden>
        <?php foreach ( ms_view_as_toolbar_users() as $u ) : ?>
            <a href="<?php echo esc_url( ms_view_as_start_url( $u->ID ) ); ?>"><?php echo esc_html( $u->display_name ); ?><span><?php echo esc_html( $u->user_email ); ?></span></a>
        <?php endforeach; ?>
        <a href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>">Other&hellip;</a>
    </div>
    <script id="ms-view-as-panel-script">
    (function () {
        var panel = document.getElementById('ms-view-as-panel');
        if (!panel) return;
        document.addEventListener('click', function (e) {
            var t = e.target, btn = t.closest ? t.closest('#wp-admin-bar-ms-view-as-m') : null;
            if (btn) { e.preventDefault(); panel.hidden = !panel.hidden; return; }
            if (!panel.hidden && !(t.closest && t.closest('#ms-view-as-panel'))) panel.hidden = true;
        }, true);
        // a touch anywhere else closes it at once (some browsers send no click for a tap on empty page)
        document.addEventListener('touchstart', function (e) {
            var t = e.target;
            if (!panel.hidden && t.closest && !t.closest('#ms-view-as-panel') && !t.closest('#wp-admin-bar-ms-view-as-m')) panel.hidden = true;
        }, { passive: true });
    })();
    </script>
    <?php
}, 1002 );

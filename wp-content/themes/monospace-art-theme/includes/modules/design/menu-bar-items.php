<?php
/**
 * Design module: the menu bar's items (2026-10-22, owner: "Add a Search icon in the Menu bar. Allow me to configure it in
 * Look > Menu Bar, along with the other icons: Home, <WP Main Menu>, Search, Dark Mode, Cart").
 *
 * The bar holds five items - Home, Menu (the WordPress Main Menu; "Menu ☰" on phones and tablets), Search, Dark mode,
 * Cart - printed by header.php in the order set in Appearance > Look > Menu bar, each one shown or hidden (the Menu
 * and the Dark mode switch aside: the Menu always shows, the switch's own setting is "Dark mode for visitors" at the
 * top of the Look screen). Whatever comes after the Menu sits at the right end of the bar, what comes before it at the left.
 *
 * Search is an icon that opens the search field inside the bar (menu-bar-search.js) - on computers beside the other
 * items, on phones and tablets over the whole bar. It is the site's ordinary search (GET /?s=), nothing changed there.
 *
 * Option monospace_menu_bar [ order => [ keys ], off => [ keys ] ] - not part of the saved looks (the bar's items are the
 * same in the light and the dark look). Styles: _menu.scss.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The items: key => label (in the default order) */
function monospace_menu_bar_item_labels() {
    return [ 'home' => 'Home', 'menu' => 'Menu', 'search' => 'Search', 'dark' => 'Dark mode', 'account' => 'Account', 'cart' => 'Cart' ];
}

/** The saved setting, cleaned: every item once, in the saved order (new ones at their default place), the hidden ones listed */
function monospace_menu_bar_settings() {
    $s     = (array) get_option( 'monospace_menu_bar', [] );
    $known = array_keys( monospace_menu_bar_item_labels() );
    $order = array_values( array_unique( array_intersect( (array) ( $s['order'] ?? [] ), $known ) ) );
    foreach ( $known as $i => $key ) {
        if ( in_array( $key, $order, true ) ) continue;
        // an item the saved order doesn't know yet: right after the one before it in the default order
        $after = $i ? array_search( $known[ $i - 1 ], $order, true ) : false;
        array_splice( $order, $after === false ? ( $i ? count( $order ) : 0 ) : $after + 1, 0, [ $key ] );
    }
    $off = array_values( array_diff( array_intersect( (array) ( $s['off'] ?? [] ), $known ), [ 'menu', 'dark' ] ) );
    return [ 'order' => $order, 'off' => $off ];
}

/** The keys to print, in order */
function monospace_menu_bar_items() {
    $s = monospace_menu_bar_settings();
    return array_values( array_diff( $s['order'], $s['off'] ) );
}

/** One item of the bar */
function monospace_menu_bar_item( $key ) {
    switch ( $key ) {
        case 'home':
            echo '<div class="menu-bar-home">';
            echo do_shortcode( '[monospace_home]' );
            echo '</div>';
            break;

        case 'menu':
            echo '<div class="mobile-menu-toggle" aria-label="Open Menu">Menu ☰</div>';
            wp_nav_menu( [ 'theme_location' => 'primary', 'container' => false, 'menu_class' => 'primary-menu', 'fallback_cb' => false ] );
            break;

        case 'search': ?>
            <div class="menu-bar-search">
                <form role="search" method="get" class="ms-bar-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
                    <button type="button" class="ms-bar-search-btn ms-bar-search-open" aria-label="Search" title="Search" aria-expanded="false" aria-controls="ms-bar-search-field">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    </button>
                    <input type="search" id="ms-bar-search-field" class="ms-bar-search-field" name="s" placeholder="Search…" aria-label="Search" autocomplete="off" tabindex="-1" value="<?php echo esc_attr( get_search_query( false ) ); ?>">
                    <button type="button" class="ms-bar-search-btn ms-bar-search-close" aria-label="Close search" title="Close search" tabindex="-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>
                    </button>
                </form>
            </div>
            <?php
            break;

        case 'dark':
            do_action( 'monospace_menu_bar_end' ); // the switch, when dark mode is on (design/dark-mode.php)
            break;

        case 'account':
            $url = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
            echo '<div class="menu-bar-account">';
            echo '<a href="' . esc_url( $url ) . '" class="monospace-account-link" aria-label="Account settings" title="Account settings">';
            echo '<span class="monospace-account-icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>';
            echo '</a></div>';
            break;

        case 'cart':
            echo '<div class="menu-bar-cart">';
            echo do_shortcode( '[monospace_cart]' );
            echo '</div>';
            break;
    }
}

/** The whole bar's content, in the configured order; a spacer after the Menu pushes the rest to the right end */
function monospace_menu_bar_render() {
    foreach ( monospace_menu_bar_items() as $key ) {
        monospace_menu_bar_item( $key );
        if ( $key === 'menu' ) echo '<span class="ms-bar-spacer" aria-hidden="true"></span>';
    }
}

// The script that opens the search field - only when the item is shown
add_action( 'wp_footer', function () {
    if ( ! in_array( 'search', monospace_menu_bar_items(), true ) ) return;
    $js = get_template_directory() . '/assets/scripts/menu-search.js';
    if ( ! file_exists( $js ) ) return;
    echo '<script id="ms-menu-search">' . file_get_contents( $js ) . "</script>\n"; // a few lines: printed, not one more request
}, 20 );

/* ---------- Appearance > Look > Menu bar (the panel look.php draws) ---------- */

function monospace_render_menu_bar_items_panel() {
    $s      = monospace_menu_bar_settings();
    $labels = monospace_menu_bar_item_labels();
    $dark   = function_exists( 'monospace_dark_settings' ) ? monospace_dark_settings() : [ 'on' => false, 'switch' => false ];
    $notes  = [
        'menu' => 'Always shown. Phones and tablets: “Menu ☰”.',
        'dark' => $dark['on'] && $dark['switch'] ? 'Shown while Dark mode is on - turn it off under “Dark mode for visitors” above.' : 'Not shown now - set under “Dark mode for visitors” above.',
    ]; ?>
    <div class="ms-look-sub ms-bar-items">
        <p class="lm-label">Icons &amp; order</p>
        <ul class="ms-bar-items-list" id="ms-bar-items">
            <?php foreach ( $s['order'] as $key ) : $fixed = in_array( $key, [ 'menu', 'dark' ], true ); ?>
                <li data-key="<?php echo esc_attr( $key ); ?>">
                    <input type="hidden" name="menu_bar[order][]" value="<?php echo esc_attr( $key ); ?>">
                    <label>
                        <?php if ( $fixed ) : ?>
                            <input type="checkbox" checked disabled>
                        <?php else : ?>
                            <input type="hidden" name="menu_bar[off][<?php echo esc_attr( $key ); ?>]" value="1">
                            <input type="checkbox" name="menu_bar[off][<?php echo esc_attr( $key ); ?>]" value="0" <?php checked( ! in_array( $key, $s['off'], true ) ); ?>>
                        <?php endif; ?>
                        <strong><?php echo esc_html( $labels[ $key ] ); ?></strong>
                    </label>
                    <?php if ( isset( $notes[ $key ] ) ) : ?><span class="description"><?php echo esc_html( $notes[ $key ] ); ?></span><?php endif; ?>
                    <span class="ms-bar-items-move">
                        <button type="button" class="button-link" data-move="-1" aria-label="Move <?php echo esc_attr( $labels[ $key ] ); ?> left">↑ Earlier</button>
                        <button type="button" class="button-link" data-move="1" aria-label="Move <?php echo esc_attr( $labels[ $key ] ); ?> later">↓ Later</button>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="description">Left to right along the bar. What comes after the Menu sits at the right end, what comes before it at the left.
            Search opens its field inside the bar - beside the other items on a computer, over the whole bar on a phone or tablet.
            The preview follows as you change it; the site changes when you save.</p>
    </div>
    <style>
        .ms-bar-items-list { margin: 0 0 8px; padding: 0; list-style: none; max-width: 560px; }
        .ms-bar-items-list li { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 12px; padding: 6px 10px; margin: 0 0 -1px; background: #fff; border: 1px solid #c3c4c7; }
        .ms-bar-items-list li > label { flex: 0 0 130px; }
        .ms-bar-items-list .description { flex: 1 1 200px; margin: 0; }
        .ms-bar-items-move { margin-left: auto; white-space: nowrap; }
        .ms-bar-items-move .button-link { margin-left: 10px; }
    </style>
    <script>
    (function () {
        var list = document.getElementById('ms-bar-items');
        if (!list) return;
        var iframe = document.getElementById('ms-spot-iframe');

        // The preview frame follows the list: the same items, in the same order, shown or hidden (the saved page is only
        // reloaded by Save). The Menu's three parts and the dark switch are found by their classes; one that isn't on the
        // page (the switch while dark mode is off) is skipped.
        var parts = {
            home:   ['.menu-bar-home'],
            menu:   ['.mobile-menu-toggle', '.primary-menu', '.ms-bar-spacer'],
            search: ['.menu-bar-search'],
            dark:   ['.ms-theme-toggle'],
            account: ['.menu-bar-account'],
            cart:   ['.menu-bar-cart']
        };
        function preview() {
            var doc; try { doc = iframe && iframe.contentDocument; } catch (e) {}
            var bar = doc && doc.querySelector('.primary-menu-container');
            if (!bar) return;
            list.querySelectorAll('li').forEach(function (li) {
                var key = li.dataset.key, box = li.querySelector('input[type=checkbox]');
                var shown = !box || box.disabled || box.checked;
                (parts[key] || []).forEach(function (sel) {
                    var el = bar.querySelector(':scope > ' + sel);
                    if (!el) return;
                    bar.appendChild(el); // in list order
                    el.style.display = shown ? '' : 'none';
                });
            });
        }

        list.addEventListener('click', function (e) {
            var b = e.target.closest('[data-move]'), li = b && b.closest('li');
            if (!li) return;
            var to = +b.dataset.move < 0 ? li.previousElementSibling : li.nextElementSibling;
            if (!to) return;
            +b.dataset.move < 0 ? list.insertBefore(li, to) : list.insertBefore(to, li); // the saved order is the order in the list
            b.focus();
            preview();
        });
        list.addEventListener('change', preview);
        if (iframe) iframe.addEventListener('load', preview);
        preview();
    })();
    </script>
    <?php
}

function monospace_save_menu_bar_items() {
    if ( ! isset( $_POST['menu_bar']['order'] ) ) return;
    $in    = wp_unslash( (array) $_POST['menu_bar'] );
    $known = array_keys( monospace_menu_bar_item_labels() );
    $order = array_values( array_unique( array_intersect( array_map( 'sanitize_key', (array) $in['order'] ), $known ) ) );
    // A checkbox sends 0 (shown) after its hidden 1 (hidden); the last value wins
    $off   = array_keys( array_filter( (array) ( $in['off'] ?? [] ), fn( $v ) => (string) $v === '1' ) );
    $s     = [ 'order' => $order, 'off' => array_values( array_intersect( $off, $known ) ) ];
    $clean = monospace_menu_bar_settings_from( $s );
    $default = array_keys( monospace_menu_bar_item_labels() );
    $clean['order'] === $default && ! $clean['off'] ? delete_option( 'monospace_menu_bar' ) : update_option( 'monospace_menu_bar', $clean );
}

/** A setting array run through the same cleaning as a saved one */
function monospace_menu_bar_settings_from( array $s ) {
    $known = array_keys( monospace_menu_bar_item_labels() );
    $order = array_values( array_unique( array_intersect( $s['order'] ?? [], $known ) ) );
    $order = array_merge( $order, array_diff( $known, $order ) );
    return [ 'order' => $order, 'off' => array_values( array_diff( array_intersect( $s['off'] ?? [], $known ), [ 'menu', 'dark' ] ) ) ];
}

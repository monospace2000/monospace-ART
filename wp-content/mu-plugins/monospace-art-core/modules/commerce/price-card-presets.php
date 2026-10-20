<?php
/**
 * Price card presets (2026-10-19, owner's wish: a compact price card - every kind, as presets).
 *
 * The card's content is built once (monospace_price_card_build(), buy-button-shortcode.php) and laid out as:
 *   full      the card as it always was: tabs, details list, options, SKU, button, offer
 *   compact   the same card, tighter: details on one line, smaller tabs and options (CSS: .pc-compact)
 *   line      one row - a dropdown for Original / Framed / Unframed, the price, the button; the rest (details,
 *             print sizes and frames, SKU, offer) behind "Details"
 *   badge     a small pill - price · availability · "Prints from $…"; it links to the artwork, and on the
 *             artwork's own page it opens the full card
 *
 * Where (Layout Manager > Appearance & Effects > Price card - registered here, through the theme's design-module
 * filter; options monospace_price_card_page / _phone / _feed):
 *   artwork pages   the [price_card] in every artwork post (page = desktop; phone = up to 640px wide, "same"
 *                   by default). When they differ both are printed and CSS shows one (.pc-desk / .pc-phone).
 *   archive & feed  a card under each artwork on category pages, the Art Feed and search (off by default;
 *                   hidden in grid view). Not on /category/print/ while Prints is on - it shows its own card.
 *   per category    the category (tag, place) edit screen's "Price cards": Site setting / Hide / a preset
 *                   (term meta _ms_price_cards, 2026-10-20), and "Opens on" (_ms_price_card_tab).
 *
 * Opens on (2026-10-20): the tab a card opens on - Original, Prints (the first print it has), Framed print,
 * Unframed print. Site-wide (option monospace_price_card_tab), per category, or a shortcode's tab="". Default:
 * Original - or the first print when the original has sold. A tab the artwork doesn't have falls back to that.
 * (The Prints plugin's separate prints-only card was retired the same day: on the Print category, pick a
 * preset and "Opens on: Prints".)
 *   shortcode       [price_card style="badge" sku="444"] anywhere (sku = the SKU's number, or post="123").
 *   per artwork     its own preset (_ms_price_card_style, set in the Quick Artwork Editor and the Artwork Editor)
 *                   wins over the artwork-page settings, on every screen; a shortcode's style="" wins over it.
 *
 * Features add tabs through monospace_price_card_panes: [ key => [ 'label', 'html', 'parts', 'from', 'from_price' ] ]
 * - 'parts' ( info, variants, buy, offer, sku, price ) lets the line preset lay a tab out; 'from' (the lowest
 * price, HTML; 'from_price' the number) is the badge's "Prints from" - the lowest tab's. Without 'parts' a tab
 * shows its html behind "Details".
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The presets, in menu order */
function monospace_price_card_presets() {
    return [ 'full' => 'Full', 'compact' => 'Compact', 'line' => 'One line', 'badge' => 'Badge' ];
}

/** A saved setting: 'page' (a preset), 'phone' (a preset or 'same'), 'feed' (a preset or 'off') */
function monospace_price_card_setting( $where ) {
    $default = [ 'page' => 'full', 'phone' => 'same', 'feed' => 'off' ][ $where ] ?? 'full';
    $value   = (string) get_option( 'monospace_price_card_' . $where, $default );
    $allowed = array_keys( monospace_price_card_presets() );
    if ( $where === 'phone' ) $allowed[] = 'same';
    if ( $where === 'feed' ) $allowed[] = 'off';
    return in_array( $value, $allowed, true ) ? $value : $default;
}

/**
 * An artwork's own preset (_ms_price_card_style - Quick Artwork Editor, Artwork Editor), '' = the site's setting.
 * It's for the artwork's card wherever [price_card] shows it, on every screen; the archive & feed keep theirs.
 */
function monospace_price_card_post_style( $post_id ) {
    $style = $post_id ? (string) get_post_meta( $post_id, '_ms_price_card_style', true ) : '';
    return isset( monospace_price_card_presets()[ $style ] ) ? $style : '';
}

/** Set an artwork's own preset ('' or anything not a preset = back to the site's setting) */
function monospace_price_card_set_post_style( $post_id, $style ) {
    isset( monospace_price_card_presets()[ $style ] )
        ? update_post_meta( $post_id, '_ms_price_card_style', $style )
        : delete_post_meta( $post_id, '_ms_price_card_style' );
}

/**
 * [ 'desk' => preset, 'phone' => preset ]: the style asked for (a shortcode's style=""), else the artwork's own
 * preset, else the artwork-page settings
 */
function monospace_price_card_styles( $style = '', $post_id = 0 ) {
    if ( ! isset( monospace_price_card_presets()[ $style ] ) ) $style = monospace_price_card_post_style( $post_id );
    if ( isset( monospace_price_card_presets()[ $style ] ) ) return [ 'desk' => $style, 'phone' => $style ];
    $desk  = monospace_price_card_setting( 'page' );
    $phone = monospace_price_card_setting( 'phone' );
    return [ 'desk' => $desk, 'phone' => $phone === 'same' ? $desk : $phone ];
}

/** The tabs a card can open on: '' = the default (Original, or the first print when sold) */
function monospace_price_card_tabs() {
    return [ '' => 'Original (sold: first print)', 'original' => 'Original, always', 'prints' => 'Prints (the first one)', 'framed' => 'Framed print', 'unframed' => 'Unframed print' ];
}

/** The tab asked for: $asked (a shortcode's tab="") if it's one, else the site setting */
function monospace_price_card_tab_setting( $asked = '' ) {
    $tabs = monospace_price_card_tabs();
    if ( $asked !== '' && isset( $tabs[ $asked ] ) ) return $asked;
    $site = (string) get_option( 'monospace_price_card_tab', '' );
    return isset( $tabs[ $site ] ) ? $site : '';
}

/** Which of a card's tabs ( 'original', 'framed', 'unframed' …) it opens on, for the tab asked for */
function monospace_price_card_active_tab( array $keys, $sold, $want = '' ) {
    $prints = array_values( array_diff( $keys, [ 'original' ] ) );
    if ( $want === 'original' ) return 'original';
    if ( $want === 'prints' && $prints ) return $prints[0];
    if ( $want !== '' && in_array( $want, $keys, true ) ) return $want;
    return $sold && $prints ? $prints[0] : 'original';
}

/** A number for each card rendered on the page - keeps tab names and form IDs unique */
function monospace_price_card_instance() {
    static $n = 0;
    return ++$n;
}

/**
 * The artwork post a [price_card] is for: post="123", sku="444" (the SKU's number - MNSPC-2026-P-0444 - or the
 * whole SKU), else the current post. false: nothing found.
 */
function monospace_price_card_target( $sku = '', $post = '' ) {
    if ( $post !== '' ) return get_post_type( (int) $post ) === 'post' ? (int) $post : false;
    $sku = trim( $sku );
    if ( $sku === '' ) return (int) get_the_ID();

    global $wpdb;
    $match = ctype_digit( $sku )
        ? $wpdb->prepare( 's.meta_value REGEXP %s', '[^0-9]0*' . ( ltrim( $sku, '0' ) ?: '0' ) . '$' )
        : $wpdb->prepare( 's.meta_value = %s', $sku );
    $id = $wpdb->get_var( "SELECT q.post_id FROM {$wpdb->postmeta} q
        JOIN {$wpdb->postmeta} s ON s.post_id = q.meta_value AND s.meta_key = '_sku'
        JOIN {$wpdb->posts} p ON p.ID = q.post_id AND p.post_type = 'post'
        WHERE q.meta_key = '_qp_product_id' AND $match
        ORDER BY p.post_status = 'publish' DESC, p.ID DESC LIMIT 1" );
    return $id ? (int) $id : false;
}

/** Run $render with $post_id as the current post (the card reads get_the_ID()), then put the old one back */
function monospace_price_card_with_post( $post_id, callable $render ) {
    if ( ! $post_id || (int) get_the_ID() === (int) $post_id ) return $render();
    global $post;
    $previous = $post;
    $post     = get_post( $post_id );
    setup_postdata( $post );
    try {
        return $render();
    } finally {
        $post = $previous;
        if ( $previous ) setup_postdata( $previous );
    }
}

/** The tabs features add for the current post (Prints: Framed / Unframed print) */
function monospace_price_card_extra_panes() {
    $post_id = get_the_ID();
    return $post_id ? (array) apply_filters( 'monospace_price_card_panes', [], $post_id ) : [];
}

/** The card for the desktop and phone presets: one card, or both with CSS showing the right one */
function monospace_render_price_card_styles( $card, $styles ) {
    if ( $styles['desk'] === $styles['phone'] ) return monospace_render_price_card( $card, $styles['desk'] );
    return '<div class="pc-desk">' . monospace_render_price_card( $card, $styles['desk'] ) . '</div>'
         . '<div class="pc-phone">' . monospace_render_price_card( $card, $styles['phone'] ) . '</div>';
}

/** One card in one preset */
function monospace_render_price_card( $card, $style ) {
    if ( $style === 'line' )  return monospace_render_price_card_line( $card );
    if ( $style === 'badge' ) return monospace_render_price_card_badge( $card );
    return monospace_render_painting_card( monospace_render_card_slots( ...$card['parts'] ),
        $card['status_class'] . ( $style === 'compact' ? ' pc-compact' : '' ), $card['status_slug'], $card['tab'] ?? '' );
}

/** Is the original sold (or in a private collection)? Its tab is struck through and the card opens on a print */
function monospace_price_card_sold( $card ) {
    return in_array( $card['status_slug'], [ 'sold', 'private-collection' ], true );
}

/** "line": [ Original ▾ ] price [ Add to cart ] - Details ▸ */
function monospace_render_price_card_line( $card ) {
    [ $info, , $buy, $offer, $sku ] = $card['parts'];
    $sold   = monospace_price_card_sold( $card );
    $prints = monospace_price_card_extra_panes();
    $panes  = [ 'original' => [ 'label' => 'Original', 'struck' => $sold,
                                'parts' => [ 'info' => $info, 'variants' => '', 'buy' => $buy, 'offer' => $offer, 'sku' => $sku, 'price' => $card['price'] ] ] ] + $prints;
    $active = monospace_price_card_active_tab( array_keys( $panes ), $sold, $card['tab'] ?? '' );

    // The tab dropdown sits in every tab's row, set to that tab - the cards' script (Prints) shows the tab chosen
    // ('change' in .print-type-switch), so the rest of the row and "Details" always have the card's full width
    $switch = function ( $current ) use ( $panes ) {
        if ( count( $panes ) < 2 ) return '';
        $out = '<label class="print-type-switch pc-switch"><span class="screen-reader-text">Purchase option</span>'
             . '<select name="artwork-card-' . (int) get_the_ID() . '-' . monospace_price_card_instance() . '">';
        foreach ( $panes as $key => $pane ) {
            $out .= '<option value="' . esc_attr( $key ) . '"' . selected( $key, $current, false ) . '>'
                  // Short words, so the row fits a phone: Original / Framed / Unframed
                  . esc_html( preg_replace( '/ print$/i', '', $pane['label'] ) . ( empty( $pane['struck'] ) ? '' : ' (sold)' ) ) . '</option>';
        }
        return $out . '</select></label>';
    };
    if ( count( $panes ) > 1 ) $GLOBALS['monospace_print_card_rendered'] = true;

    $out = '<div class="painting-buy-row print-order-row pc-line' . esc_attr( $card['status_class'] ) . '" data-status="' . esc_attr( $card['status_slug'] ) . '">';
    foreach ( $panes as $key => $pane ) {
        $p    = $pane['parts'] ?? null;
        $out .= '<div class="print-order-pane pc-line-pane" data-pane="' . esc_attr( $key ) . '"' . ( $key === $active ? '' : ' hidden' ) . '>'
              . '<div class="pc-line-main">' . $switch( $key );
        if ( $p ) {
            $out .= ( $p['price'] !== '' ? '<span class="pc-line-price">' . $p['price'] . '</span>' : '' )
                  . '<span class="card-buy">' . $p['buy'] . '</span>';
            $more = monospace_render_card_slots( $p['info'], $p['variants'], '', $p['offer'], $p['sku'] );
        } else {
            $more = $pane['html'];
        }
        $out .= '</div><details class="pc-details"><summary>' . ( $key === 'original' ? 'Details' : 'Sizes & details' ) . '</summary>'
              . '<div class="card-panel">' . $more . '</div></details></div>';
    }
    return $out . '</div>';
}

/** "badge": $450 · Available · Prints from $20 - a link to the artwork; on its own page it opens the full card */
function monospace_render_price_card_badge( $card ) {
    $post_id = (int) get_the_ID();
    $sold    = monospace_price_card_sold( $card );
    $bits    = [];

    if ( $card['price'] !== '' && ! $sold ) $bits[] = '<span class="pc-badge-price">' . $card['price'] . '</span>';
    if ( $card['label'] !== '' ) {
        $text   = trim( str_replace( '↗', '', wp_strip_all_tags( $card['label'] ) ) );
        $bits[] = '<span class="status-label status-' . esc_attr( $card['status_slug'] ) . '">' . esc_html( $text ) . '</span>';
    } elseif ( $card['cart'] !== '' ) {
        $bits[] = '<span class="status-label status-available">Available</span>';
    }
    // The lowest of the tabs' lowest prices ('from_price'; a tab without one counts only when no other has one)
    $from = ''; $low = null;
    foreach ( monospace_price_card_extra_panes() as $pane ) {
        if ( empty( $pane['from'] ) ) continue;
        $price = isset( $pane['from_price'] ) ? (float) $pane['from_price'] : null;
        if ( $from === '' || ( $price !== null && ( $low === null || $price < $low ) ) ) { $from = $pane['from']; $low = $price; }
    }
    if ( $from !== '' ) $bits[] = '<span class="pc-badge-prints">Prints from ' . $from . '</span>';
    if ( ! $bits ) return '';

    $pill = '<span class="pc-badge' . esc_attr( $card['status_class'] ) . '">' . implode( '<span class="pc-badge-sep" aria-hidden="true">·</span>', $bits ) . '</span>';
    if ( is_singular() && (int) get_queried_object_id() === $post_id ) {
        return '<details class="pc-badge-card"><summary>' . $pill . '</summary>' . monospace_render_price_card( $card, 'full' ) . '</details>';
    }
    return '<a class="pc-badge-link" href="' . esc_url( get_permalink( $post_id ) ) . '">' . $pill . '</a>';
}

/** A category's (tag's, place's) own "Price cards" choice: '' = the site setting, 'hide', or a preset */
function monospace_price_card_term_style( $term_id ) {
    $v = $term_id ? (string) get_term_meta( $term_id, '_ms_price_cards', true ) : '';
    return $v === 'hide' || isset( monospace_price_card_presets()[ $v ] ) ? $v : '';
}

/** The archive being viewed: its term's choice ('' outside a category / tag / place page) */
function monospace_price_card_archive_term_style() {
    return ( is_category() || is_tag() || is_tax() ) ? monospace_price_card_term_style( (int) get_queried_object_id() ) : '';
}

// Archive & feed: a card under each artwork (Layout Manager > Price card > Archive & feed; a category's own choice first)
add_filter( 'the_content', function ( $content ) {
    if ( is_admin() || is_feed() || ! ( is_home() || is_archive() || is_search() ) || ! in_the_loop() || ! is_main_query() ) return $content;
    $own   = monospace_price_card_archive_term_style();
    if ( $own === 'hide' ) return $content;
    $style = $own ?: monospace_price_card_setting( 'feed' );
    if ( $style === 'off' ) return $content;
    $post_id = (int) get_the_ID();
    if ( ! function_exists( 'monospace_is_artwork' ) || ! monospace_is_artwork( $post_id ) ) return $content;
    $card = monospace_price_card_build( monospace_get_post_product_id( $post_id ) );
    if ( $card ) {
        $term_tab    = ( is_category() || is_tag() || is_tax() ) ? (string) get_term_meta( (int) get_queried_object_id(), '_ms_price_card_tab', true ) : '';
        $card['tab'] = monospace_price_card_tab_setting( $term_tab );
    }
    return $card ? $content . '<div class="pc-feed">' . monospace_render_price_card( $card, $style ) . '</div>' : $content;
}, 20 );

// Layout Manager > Appearance & Effects > Price card (the theme's design-module registry; no theme, no settings
// screen - the saved presets still apply)
add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['price-card'] = [
        'title'       => 'Price card',
        'description' => 'How the price card looks on artwork pages, on phones, and under each artwork in the archive and feed.',
        'group'       => 'look',
        'order'       => 40,
        'summary'     => function () {
            $p     = monospace_price_card_presets();
            $phone = monospace_price_card_setting( 'phone' );
            $feed  = monospace_price_card_setting( 'feed' );
            return 'Pages ' . $p[ monospace_price_card_setting( 'page' ) ]
                 . ( $phone === 'same' ? '' : ' · Phones ' . $p[ $phone ] )
                 . ' · Feed ' . ( $feed === 'off' ? 'off' : $p[ $feed ] );
        },
        'render'      => 'monospace_render_price_card_settings',
        'save'        => 'monospace_save_price_card_settings',
    ];
    return $modules;
} );

function monospace_render_price_card_settings() {
    $presets = monospace_price_card_presets();
    $select  = function ( $name, $value, $extra = [] ) use ( $presets ) {
        $out = '<select name="price_card_' . esc_attr( $name ) . '">';
        foreach ( $extra + $presets as $k => $label ) $out .= '<option value="' . esc_attr( $k ) . '"' . selected( $value, $k, false ) . '>' . esc_html( $label ) . '</option>';
        return $out . '</select>';
    }; ?>
    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">Artwork pages</th>
            <td>
                <?php echo $select( 'page', monospace_price_card_setting( 'page' ) ); ?>
                <p class="description"><b>Full</b>: tabs, details, options, SKU, button. <b>Compact</b>: the same, tighter - details on one line.
                    <b>One line</b>: Original / Framed / Unframed as a dropdown, price and button; the rest behind “Details”.
                    <b>Badge</b>: price · availability · prints from - opens the full card.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">On phones</th>
            <td>
                <?php echo $select( 'phone', monospace_price_card_setting( 'phone' ), [ 'same' => 'Same as artwork pages' ] ); ?>
                <p class="description">Screens up to 640px wide.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Opens on</th>
            <td>
                <select name="price_card_tab">
                    <?php foreach ( monospace_price_card_tabs() as $k => $label ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>" <?php selected( monospace_price_card_tab_setting(), $k ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">The tab every card starts on - a category can choose its own (edit the category), a shortcode too: <code>tab="prints"</code>.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Archive &amp; feed</th>
            <td>
                <?php echo $select( 'feed', monospace_price_card_setting( 'feed' ), [ 'off' => 'Off' ] ); ?>
                <p class="description">A card under each artwork on category pages, the Art Feed and search results (list view only).
                    The Prints category keeps its own print card.</p>
            </td>
        </tr>
        <tr>
            <th scope="row">Anywhere</th>
            <td>
                <p class="description" style="margin-top:0">In a page, post or home box: <code>[price_card sku="444"]</code> - the artwork by its SKU number,
                    with <code>style="full"</code>, <code>"compact"</code>, <code>"line"</code> or <code>"badge"</code> (else as artwork pages).</p>
            </td>
        </tr>
    </table>
    <?php
}

function monospace_save_price_card_settings() {
    if ( ! isset( $_POST['price_card_page'] ) ) return;
    foreach ( [ 'page', 'phone', 'feed' ] as $where ) {
        $value = sanitize_key( wp_unslash( $_POST[ 'price_card_' . $where ] ?? '' ) );
        update_option( 'monospace_price_card_' . $where, $value );
        // Anything not on the list goes back to the default
        if ( monospace_price_card_setting( $where ) !== $value ) delete_option( 'monospace_price_card_' . $where );
    }
    $tab = sanitize_key( wp_unslash( $_POST['price_card_tab'] ?? '' ) );
    ( $tab !== '' && isset( monospace_price_card_tabs()[ $tab ] ) ) ? update_option( 'monospace_price_card_tab', $tab ) : delete_option( 'monospace_price_card_tab' );
}

// The category / tag / place edit screen: "Price cards" (the taxonomies feed-headers.php gives their header fields)
add_action( 'admin_init', function () {
    $taxonomies = defined( 'MONOSPACE_ARCHIVE_TAXONOMIES' ) ? MONOSPACE_ARCHIVE_TAXONOMIES : [ 'category', 'post_tag' ];
    foreach ( $taxonomies as $taxonomy ) {
        add_action( "{$taxonomy}_edit_form_fields", 'monospace_price_card_term_field', 20 );
        add_action( "edited_{$taxonomy}", 'monospace_price_card_term_save' );
    }
} );

function monospace_price_card_term_field( $term ) {
    $value = monospace_price_card_term_style( $term->term_id );
    $feed  = monospace_price_card_setting( 'feed' );
    $site  = $feed === 'off' ? 'none' : monospace_price_card_presets()[ $feed ];
    $tab   = (string) get_term_meta( $term->term_id, '_ms_price_card_tab', true );
    $tabs  = monospace_price_card_tabs();
    $stab  = $tabs[ monospace_price_card_tab_setting() ];
    ?>
    <tr class="form-field">
        <th scope="row"><label for="ms-price-cards">Price cards</label></th>
        <td>
            <select name="ms_price_cards" id="ms-price-cards">
                <option value="" <?php selected( $value, '' ); ?>>Site setting (<?php echo esc_html( $site ); ?>)</option>
                <option value="hide" <?php selected( $value, 'hide' ); ?>>Hide</option>
                <?php foreach ( monospace_price_card_presets() as $k => $label ) : ?>
                    <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $value, $k ); ?>>Show: <?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description">A price card under each artwork on this page (list view). Site setting: Layout Manager → Appearance &amp; Effects → Price card → Archive &amp; feed.</p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="ms-price-card-tab">Price cards open on</label></th>
        <td>
            <select name="ms_price_card_tab" id="ms-price-card-tab">
                <option value="" <?php selected( isset( $tabs[ $tab ] ) && $tab !== '' ? $tab : '', '' ); ?>>Site setting (<?php echo esc_html( $stab ); ?>)</option>
                <?php foreach ( $tabs as $k => $label ) : if ( $k === '' ) continue; ?>
                    <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $tab, $k ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
            <p class="description">The tab each card starts on. An artwork without that tab opens on Original (sold: its first print).</p>
        </td>
    </tr>
    <?php
}

function monospace_price_card_term_save( $term_id ) {
    if ( ! isset( $_POST['ms_price_cards'] ) || ! current_user_can( 'edit_term', $term_id ) ) return;
    $v = sanitize_key( wp_unslash( $_POST['ms_price_cards'] ) );
    ( $v === 'hide' || isset( monospace_price_card_presets()[ $v ] ) )
        ? update_term_meta( $term_id, '_ms_price_cards', $v )
        : delete_term_meta( $term_id, '_ms_price_cards' );
    $t = sanitize_key( wp_unslash( $_POST['ms_price_card_tab'] ?? '' ) );
    ( $t !== '' && isset( monospace_price_card_tabs()[ $t ] ) )
        ? update_term_meta( $term_id, '_ms_price_card_tab', $t )
        : delete_term_meta( $term_id, '_ms_price_card_tab' );
}

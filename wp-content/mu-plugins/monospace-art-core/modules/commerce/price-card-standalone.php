<?php
/**
 * Standalone price card (2026-10-21, owner: "a standalone version of the Price Card. It keeps all the existing elements
 * and functionality but adds an image preview (print preview as well) in a new column to the left (new row at top on
 * mobile or stacked mode); option to link back to original post. All configurable with shortcode too.")
 *
 *   [price_card_standalone sku="444"]   the artwork's price card - exactly the [price_card] (tabs, details, options,
 *                                       SKU, button, offer; buy-button-shortcode.php) - with its picture beside it
 *
 * The picture: the artwork's image; on a print tab, the mockup of the selected size and frame (the Prints plugin keeps
 * each print tab's "See a preview" link on the selected variation's mockup - the script below follows that link, so
 * the picture always matches what would be ordered). Clicking it (or the title) opens the artwork's page - no separate
 * text link (removed 2026-10-21, owner: "remove product link, make clicking the preview go to the post").
 * Relative sizes (2026-10-21): a print's mockup is shown at its size next to the largest print this card offers (framed
 * and unframed - the Prints plugin's per-variation 'scale', its size against the largest print sold), so a 5x7 looks
 * smaller than a framed 11x14 (data-pc-sa-relative). Its own setting, "Print sizes" (sizes="relative | same"), shown on the
 * Price card screen and pointed to from Look > Print preview (owner: "show this in the Look / Appearance config screens").
 * Layout: side (picture on the left; on phones a row on top) or stacked (always on top).
 *
 * Defaults: Appearance > Look > Standalone price card (option monospace_price_card_standalone); any of them per card:
 *   layout="side | stacked"  title="yes | no" (the artwork's title on top)  image="yes | no"  preview="yes | no" (print mockups)
 *   link="yes | no" (the picture and title open the artwork's page)  sizes="relative | same" (print mockups)
 *   style="full | compact" (the card)  sku="" / post=""  tab="" (as [price_card])
 * The CSS: the theme's _components.scss (.pc-standalone). Core: works without the Prints plugin (no print tabs, then).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_pc_standalone_defaults() {
    return [ 'layout' => 'side', 'title' => 'yes', 'image' => 'yes', 'preview' => 'yes', 'link' => 'yes', 'sizes' => 'relative', 'style' => 'full' ];
}

/** The defaults as set in Look, cleaned */
function monospace_pc_standalone_settings() {
    return monospace_pc_standalone_clean( (array) get_option( 'monospace_price_card_standalone', [] ), monospace_pc_standalone_defaults() );
}

/** Clean settings or shortcode attributes; anything missing or not allowed falls back to $base */
function monospace_pc_standalone_clean( array $in, array $base ) {
    $yes = fn( $k ) => isset( $in[ $k ] ) && in_array( strtolower( (string) $in[ $k ] ), [ 'yes', 'no' ], true ) ? strtolower( $in[ $k ] ) : $base[ $k ];
    return [
        'layout'    => in_array( $in['layout'] ?? '', [ 'side', 'stacked' ], true ) ? $in['layout'] : $base['layout'],
        'title'     => $yes( 'title' ),
        'image'     => $yes( 'image' ),
        'preview'   => $yes( 'preview' ),
        'link'      => $yes( 'link' ),
        'sizes'     => in_array( $in['sizes'] ?? '', [ 'relative', 'same' ], true ) ? $in['sizes'] : ( $base['sizes'] ?? 'relative' ),
        'style'     => in_array( $in['style'] ?? '', [ 'full', 'compact' ], true ) ? $in['style'] : $base['style'],
    ];
}

/**
 * The artwork's picture - one: its featured image, else the first picture in it (monospace_row_image_id(), artwork-row.php, which
 * also finds the first of a Miniature Set block). A diptych or triptych shows its first picture only, as in the detail list and grid.
 */
function monospace_pc_standalone_image( $post_id ) {
    $id = function_exists( 'monospace_row_image_id' ) ? monospace_row_image_id( $post_id ) : (int) get_post_thumbnail_id( $post_id );
    if ( ! $id ) return null;
    $src = wp_get_attachment_image_src( $id, 'large' );
    return $src ? [ 'src' => $src[0], 'w' => (int) $src[1], 'h' => (int) $src[2],
                    'srcset' => (string) wp_get_attachment_image_srcset( $id, 'large' ), 'alt' => get_the_title( $post_id ) ] : null;
}

add_shortcode( 'price_card_standalone', function ( $atts ) {
    $atts = (array) $atts;
    $set  = monospace_pc_standalone_clean( $atts, monospace_pc_standalone_settings() );

    $post_id = monospace_price_card_target( (string) ( $atts['sku'] ?? '' ), (string) ( $atts['post'] ?? '' ) );
    if ( ! $post_id ) return monospace_is_editor_context() ? '<div class="painting-buy-placeholder" style="padding:6px;border:1px dashed #c00;background:#f9f9f9;">Standalone price card: no artwork with SKU / post "' . esc_html( ( $atts['sku'] ?? '' ) . ( $atts['post'] ?? '' ) ) . '"</div>' : '';
    if ( monospace_is_editor_context() ) return '<div class="painting-buy-placeholder" style="padding:6px;border:1px dashed #aaa;background:#f9f9f9;">Standalone price card: ' . esc_html( get_the_title( $post_id ) ) . ' (' . esc_html( $set['layout'] ) . ')</div>';

    // The card itself: [price_card] for this artwork, in the chosen preset
    $card = monospace_custom_add_to_cart_shortcode( [ 'post' => (string) $post_id, 'style' => $set['style'], 'tab' => (string) ( $atts['tab'] ?? '' ) ] );
    if ( $card === '' ) return '';

    $url   = get_post_status( $post_id ) === 'publish' ? get_permalink( $post_id ) : '';
    $link  = $set['link'] === 'yes' && $url;
    $image = $set['image'] === 'yes' ? monospace_pc_standalone_image( $post_id ) : null;

    $media = '';
    if ( $image ) {
        $img = sprintf( '<img class="pc-sa-img" src="%s" srcset="%s" sizes="(max-width: 640px) 100vw, 420px" width="%d" height="%d" alt="%s" loading="lazy" decoding="async" data-original="%s">',
            esc_url( $image['src'] ), esc_attr( $image['srcset'] ), $image['w'], $image['h'], esc_attr( $image['alt'] ), esc_url( $image['src'] ) );
        // The box keeps the artwork's shape (no taller than 70% of the screen), so a mockup fits inside it and the card
        // doesn't move when the picture changes
        $ratio = $image['h'] ? $image['w'] / $image['h'] : 1;
        $box   = sprintf( 'aspect-ratio:%d / %d;max-width:min(100%%, calc(70vh * %s))', $image['w'], $image['h'], round( $ratio, 4 ) );
        $media = '<div class="pc-sa-media" style="' . esc_attr( $box ) . '">' . ( $link ? '<a href="' . esc_url( $url ) . '" class="pc-sa-media-link">' . $img . '</a>' : $img ) . '</div>';
    }
    // The artwork's title on top (a list of cards needs it - Cards view, 2026-10-21)
    $title = '';
    if ( $set['title'] === 'yes' ) {
        $name  = esc_html( get_the_title( $post_id ) );
        $title = '<h3 class="pc-sa-title">' . ( $link ? '<a href="' . esc_url( $url ) . '">' . $name . '</a>' : $name ) . '</h3>';
    }

    monospace_pc_standalone_script();
    // Prints at their relative sizes (Print sizes: relative, the default) or all the same size
    $relative = $set['sizes'] === 'relative';
    return sprintf( '<div class="pc-standalone pc-sa-%s%s"%s%s>%s%s<div class="pc-sa-body">%s%s</div></div>',
        esc_attr( $set['layout'] ), $media ? '' : ' pc-sa-no-image', $set['preview'] === 'yes' && $media ? ' data-pc-sa-preview' : '',
        $relative ? ' data-pc-sa-relative' : '', $title, $media, $card, '' );
} );

/**
 * [artwork sku="234"] - the standalone card without the card (2026-10-22, owner: "a shortcode that displays an Artwork
 * wherever it's used ... maybe it should just be a variation of the Price Card?"). Just the picture: its longest edge
 * fills the container (a portrait is as tall as the container is wide), the site's picture look (outline, shadow,
 * rounding, rollover - it is an .ms-archive-image-link, so Look > Shadows & outlines and Rollover effect > feed apply),
 * and a click opens the artwork's post. Adding to it:
 *   title="yes"  the artwork's title under it
 *   info="yes"  "medium on surface, dimensions" (+ the Miniature tag)    availability="yes"  the availability, the #number and
 *   the price while it is for sale    prints="yes"  "Prints available" - the same lines, in the same UI type, as the list
 *   and detail views (monospace_artwork_info_html(), .ms-info); several together make one block
 *   square="yes"  a square thumbnail (cropped to the middle) at the box's
 *                 full width, as in the detail list - without it the longest edge fills the box
 *   badges="yes"  the grid view's overlays on the picture: the green dot (Available / Gallery) bottom left, "M" (a miniature) bottom right
 *   link="no"    not clickable     align="left | center | right"  the picture (a portrait is narrower than its box)
 *                and the text under it
 *   width="50%"  the box the picture fills (%, px, rem, em or vw; a bare number is px), never wider than the column;
 *                align places the box. Without it the box is the whole column
 *   sku="" / post=""  as [price_card]
 */
add_shortcode( 'artwork', function ( $atts ) {
    $atts = (array) $atts;
    $yn   = fn( $k, $d ) => isset( $atts[ $k ] ) && in_array( strtolower( (string) $atts[ $k ] ), [ 'yes', 'no' ], true ) ? strtolower( $atts[ $k ] ) === 'yes' : $d;

    $post_id = monospace_price_card_target( (string) ( $atts['sku'] ?? '' ), (string) ( $atts['post'] ?? '' ) );
    if ( ! $post_id ) return monospace_is_editor_context() ? '<div class="painting-buy-placeholder" style="padding:6px;border:1px dashed #c00;background:#f9f9f9;">Artwork: no artwork with SKU / post "' . esc_html( ( $atts['sku'] ?? '' ) . ( $atts['post'] ?? '' ) ) . '"</div>' : '';
    if ( monospace_is_editor_context() ) return '<div class="painting-buy-placeholder" style="padding:6px;border:1px dashed #aaa;background:#f9f9f9;">Artwork: ' . esc_html( get_the_title( $post_id ) ) . '</div>';

    $square = $yn( 'square', false );
    $image  = monospace_pc_standalone_image( $post_id );
    if ( ! $image ) return '';
    $url  = get_post_status( $post_id ) === 'publish' ? get_permalink( $post_id ) : '';
    $link = $yn( 'link', true ) && $url;
    $name = esc_html( get_the_title( $post_id ) );

    // Longest edge = the container: a landscape fills the width, a portrait is as wide as its ratio of the width; a square fills it
    $pct   = $square ? 100 : ( $image['h'] ? min( 1, $image['w'] / $image['h'] ) * 100 : 100 );
    $align = in_array( $atts['align'] ?? '', [ 'left', 'right' ], true ) ? $atts['align'] : 'center';
    $style = sprintf( 'width:%s%%;margin:0 %s 0 %s', round( $pct, 3 ), $align === 'left' ? 'auto' : ( $align === 'right' ? '0' : 'auto' ), $align === 'right' ? 'auto' : ( $align === 'left' ? '0' : 'auto' ) );
    $img   = sprintf( '<img src="%s" srcset="%s" sizes="(max-width: 640px) 100vw, 600px" width="%d" height="%d" alt="%s" loading="lazy" decoding="async">',
        esc_url( $image['src'] ), esc_attr( $image['srcset'] ), $image['w'], $image['h'], esc_attr( $image['alt'] ) );
    $pic   = $link
        ? '<a class="ms-archive-image-link ms-artwork-link" href="' . esc_url( $url ) . '" style="' . esc_attr( $style ) . '">' . $img . '</a>'
        : '<span class="ms-artwork-link" style="display:block;' . esc_attr( $style ) . '">' . $img . '</span>';

    // Badge overlays (the grid view's): the green dot and the "M", drawn by the theme (monospace_build_labels())
    $badges = '';
    if ( ( $yn( 'badges', false ) || ( function_exists( 'monospace_dots_all' ) && monospace_dots_all() ) ) && function_exists( 'monospace_get_product_info' ) && function_exists( 'monospace_build_labels' ) ) {
        $badges = monospace_build_labels( monospace_get_product_info( $post_id ) );
    }
    if ( $badges !== '' ) {
        $pic = $link
            ? '<a class="ms-archive-image-link ms-artwork-link ms-artwork-badged" href="' . esc_url( $url ) . '" style="' . esc_attr( $style ) . '">' . $img . $badges . '</a>'
            : '<span class="ms-artwork-link ms-artwork-badged" style="display:block;' . esc_attr( $style ) . '">' . $img . $badges . '</span>';
    }

    $title = $yn( 'title', false ) ? '<h3 class="ms-artwork-title">' . ( $link ? '<a href="' . esc_url( $url ) . '">' . $name . '</a>' : $name ) . '</h3>' : '';
    // The info lines - the list and detail views' (monospace_artwork_info_html()): what was asked for, in one block
    $info  = '';
    $want  = array_keys( array_filter( [ 'made' => $yn( 'info', false ), 'status' => $yn( 'availability', false ), 'prints' => $yn( 'prints', false ) ] ) );
    if ( $want && function_exists( 'monospace_artwork_info_html' ) && function_exists( 'wc_get_product' )
        && ( $pid = monospace_get_post_product_id( $post_id ) ) && ( $product = wc_get_product( $pid ) ) ) {
        $info = monospace_artwork_info_html( $post_id, $product, $want );
    }
    // width="50%" / "300px" / "20rem": the box the picture fills (never wider than the column), placed by align
    $box = '';
    if ( isset( $atts['width'] ) && preg_match( '/^\s*(\d+(?:\.\d+)?)\s*(%|px|rem|em|vw)?\s*$/', (string) $atts['width'], $m ) && (float) $m[1] > 0 ) {
        $box = sprintf( ';width:%s%s;max-width:100%%;margin-left:%s;margin-right:%s', $m[1], $m[2] ?? 'px',
            $align === 'left' ? '0' : 'auto', $align === 'right' ? '0' : 'auto' );
    }
    return '<figure class="ms-artwork' . ( $square ? ' ms-artwork-square' : '' ) . '" style="text-align:' . $align . $box . '">' . $pic . $title . $info . '</figure>';
} );

/** The picture follows the card: a print tab shows the mockup its "See a preview" link points to (printed once) */
function monospace_pc_standalone_script() {
    static $done = false;
    if ( $done ) return;
    $done = true;
    add_action( 'wp_footer', function () { ?>
    <script id="ms-pc-standalone">
    (function () {
        // A print's size next to the largest print this card offers (the variations' 'scale': size against the largest
        // print sold) - 1 for the largest, smaller for the rest
        function relativeSize(card, pane, src) {
            if (!card.hasAttribute('data-pc-sa-relative')) return 1;
            var max = 0, mine = 0;
            card.querySelectorAll('.print-order-form[data-variations]').forEach(function (form) {
                var list; try { list = JSON.parse(form.dataset.variations); } catch (e) { return; }
                list.forEach(function (v) {
                    var sc = +v.scale || 1; if (sc > max) max = sc;
                    if (form.closest('.print-order-pane') === pane && v.preview && src.indexOf(v.preview) > -1) mine = sc;
                });
            });
            return max && mine ? Math.min(1, mine / max) : 1;
        }
        function update(card) {
            var img = card.querySelector('.pc-sa-img'); if (!img) return;
            if (img.dataset.srcset === undefined) img.dataset.srcset = img.getAttribute('srcset') || '';
            var pane = card.querySelector('.print-order-pane:not([hidden])'), src = img.dataset.original, mockup = false;
            if (pane && pane.dataset.pane !== 'original') {
                var link = pane.querySelector('.print-preview-link');
                if (link && !link.hidden && link.getAttribute('href') && link.getAttribute('href') !== '#') { src = link.href; mockup = true; }
            }
            // Mockup files are square with a clear margin (the print covers ~73% of the width): shown 60% larger (2026-10-23, owner: "bigger still" - was 25%), the largest
            // print nearly fills the picture (2026-10-21, owner: "a tad larger"); the rest at their relative sizes
            var size = mockup ? relativeSize(card, pane, src) * 1.6 : 1;
            img.style.transform = mockup ? 'scale(' + size.toFixed(3) + ')' : '';
            if (img.getAttribute('src') === src) return;
            img.removeAttribute('srcset'); // the artwork's sizes don't apply to a mockup; put back below
            if (!mockup && img.dataset.srcset) img.setAttribute('srcset', img.dataset.srcset);
            img.src = src;
            card.classList.toggle('is-mockup', mockup);
        }
        // On the page, so cards added later (infinite scroll in the price card view) follow too. The card's own scripts switch
        // the tab / update the preview link on the same events - look after them.
        ['change', 'click'].forEach(function (ev) {
            document.addEventListener(ev, function (e) {
                var card = e.target.closest && e.target.closest('.pc-standalone[data-pc-sa-preview]');
                if (card) setTimeout(function () { update(card); }, 0);
            });
        });
        // The button stays put between tabs: the panes share the tallest's height (CSS), and the offer line under the button
        // is as tall on every tab - a two-line offer (a print's, wrapped on a phone) would push the button up otherwise
        function evenOffers(card) {
            var offers = card.querySelectorAll('.print-order-pane > .card-offer'), max = 0;
            if (offers.length < 2) return;
            offers.forEach(function (o) { o.style.minHeight = ''; });
            offers.forEach(function (o) { max = Math.max(max, o.offsetHeight); });
            offers.forEach(function (o) { o.style.minHeight = max + 'px'; });
        }
        function fresh() {
            document.querySelectorAll('.pc-standalone:not([data-pc-sa-ready])').forEach(function (card) {
                card.setAttribute('data-pc-sa-ready', '');
                if (card.hasAttribute('data-pc-sa-preview')) update(card);
                evenOffers(card);
            });
        }
        var resized = 0;
        window.addEventListener('resize', function () {
            clearTimeout(resized);
            resized = setTimeout(function () { document.querySelectorAll('.pc-standalone').forEach(evenOffers); }, 150);
        });
        fresh();
        // Cards added by infinite scroll: their starting picture (a card opening on a print tab shows the mockup). Only
        // added elements are watched - never the picture changes made here.
        if (window.MutationObserver) new MutationObserver(function (list) {
            if (list.some(function (m) { return m.addedNodes.length; })) fresh();
        }).observe(document.body, { childList: true, subtree: true });
    })();
    </script>
    <?php }, 30 );
}

/* ---------- Appearance > Look ---------- */

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['price-card-standalone'] = [
        'title'       => 'Standalone price card',
        'description' => 'The price card with the artwork’s picture beside it - [price_card_standalone sku="444"] in any page, post or box.',
        'group'       => 'look',
        'order'       => 41,
        'summary'     => function () {
            $s = monospace_pc_standalone_settings();
            return ( $s['layout'] === 'side' ? 'Picture beside' : 'Picture on top' ) . ( $s['preview'] === 'yes' ? ', print previews' . ( $s['sizes'] === 'relative' ? ' at relative sizes' : '' ) : '' ) . ( $s['link'] === 'yes' ? ', link' : '' );
        },
        'render'      => 'monospace_render_pc_standalone_settings',
        'save'        => 'monospace_save_pc_standalone_settings',
    ];
    return $modules;
} );

function monospace_render_pc_standalone_settings() {
    $s  = monospace_pc_standalone_settings();
    $rb = function ( $name, $value, $choices ) use ( $s ) {
        foreach ( $choices as $k => $label ) echo '<label style="margin-right:16px"><input type="radio" name="pcsa[' . esc_attr( $name ) . ']" value="' . esc_attr( $k ) . '" ' . checked( $s[ $name ], $k, false ) . '> ' . esc_html( $label ) . '</label>';
    }; ?>
    <table class="form-table" role="presentation">
        <tr><th scope="row">Title</th>
            <td><?php $rb( 'title', $s['title'], [ 'yes' => 'The artwork’s title on top', 'no' => 'No title' ] ); ?></td></tr>
        <tr><th scope="row">Picture</th>
            <td><?php $rb( 'image', $s['image'], [ 'yes' => 'Show the artwork', 'no' => 'No picture' ] ); ?></td></tr>
        <tr><th scope="row">Layout</th>
            <td><?php $rb( 'layout', $s['layout'], [ 'side' => 'Picture on the left (on top on phones)', 'stacked' => 'Picture on top, always' ] ); ?></td></tr>
        <tr><th scope="row">Print preview</th>
            <td><?php $rb( 'preview', $s['preview'], [ 'yes' => 'On a print tab, show the print as it would hang', 'no' => 'Always the artwork' ] ); ?>
                <p class="description">The mockup of the size and frame chosen in the card.</p></td></tr>
        <tr><th scope="row">Print sizes</th>
            <td><?php $rb( 'sizes', $s['sizes'], [ 'relative' => 'Relative - a 5×7 smaller than a framed 11×14', 'same' => 'All the same size' ] ); ?>
                <p class="description">Relative: the largest print the artwork offers fills the picture, smaller ones shrink to match (frames included).</p></td></tr>
        <tr><th scope="row">Link to the artwork</th>
            <td><?php $rb( 'link', $s['link'], [ 'yes' => 'Clicking the picture or title opens the artwork’s page', 'no' => 'No' ] ); ?></td></tr>
        <tr><th scope="row">Card</th>
            <td><?php $rb( 'style', $s['style'], [ 'full' => 'Full', 'compact' => 'Compact' ] ); ?></td></tr>
        <tr><th scope="row">Shortcode</th>
            <td><p class="description" style="margin-top:0"><code>[price_card_standalone sku="444"]</code> - and any of these to differ from the settings above:
                <code>layout="side"</code> / <code>"stacked"</code>, <code>title="no"</code>, <code>image="no"</code>, <code>preview="no"</code>, <code>link="no"</code>,
                <code>sizes="same"</code>, <code>style="compact"</code>, <code>tab="prints"</code>; <code>post="123"</code> instead of <code>sku</code>.</p></td></tr>
    </table>
    <?php
}

function monospace_save_pc_standalone_settings() {
    if ( ! isset( $_POST['pcsa'] ) ) return;
    $s = monospace_pc_standalone_clean( wp_unslash( (array) $_POST['pcsa'] ), monospace_pc_standalone_defaults() );
    $s === monospace_pc_standalone_defaults() ? delete_option( 'monospace_price_card_standalone' ) : update_option( 'monospace_price_card_standalone', $s );
}

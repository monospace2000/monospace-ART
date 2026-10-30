<?php
/**
 * Home page boxes - the rows of link boxes below Welcome / News on the home page
 * (start_template.php), edited with drag and drop in Layout Manager > Home Page
 * (editor/home-boxes-admin.php).
 *
 * Stored in option monospace_home_layout:
 *   [ [ 'label' => 'Shop', 'boxes' => [ box, ... ] ], ... ]   (rows in order; label optional)
 *   box = [ 'title', 'badge' (small text before the title, e.g. "NEW!"), 'text', 'link',
 *           'width' (1 narrow / 2 normal / 3 wide - flex share within the row),
 *           'image' => [ 'mode' => 'auto' | 'media' | 'file', 'id' (attachment), 'file' (theme path) ] ]
 * Image modes: media = a media library image; file = a theme image (assets/images/backgrounds/...,
 * the original box backgrounds); auto = the newest artwork behind the link (monospace_home_auto_image);
 * random (2026-10-23, owner: "allow for random images in the Home Page Boxes") = a different picture each time the home page
 * is opened: 'pool' => 'link' (artworks behind the box's link) or 'media' (+ 'ids': pictures the owner chose, 2 to 12; text boxes
 * only have this one). The server prints one of them (shuffled, so the cached page changes too) and the whole pool as
 * data-bg-pool; a small script after the boxes picks one in the visitor's browser (monospace_home_render_boxes()).
 * No option saved = monospace_home_layout_default() (the menu's sections, 2026-10-15).
 *
 * Text boxes (box 'type' => 'content'): show a page's text instead of linking - Welcome and News
 * (the top row, editable since 2026-10-15). [ 'type' => 'content', 'page' => page ID, 'title',
 * 'width', 'look' => 'plain' (text on the page, like Welcome) | 'box' (title bar + background, like
 * News; the text scrolls), 'image' ]. The box's id is the page's slug (#welcome / #news keep their
 * CSS). A saved layout without text boxes gets the default top row put back in front.
 *
 * Anchors (2026-10-16): every box and every labeled row has an id to link to - art.monospace.com/#prints.
 * 'anchor' on a box or row = a custom one; empty = made from the title / label (a text box: its
 * page's slug, so #welcome / #news). Duplicates get -2, -3. Landing below the fixed menu bar:
 * scroll-margin-top (_start_template.scss); with Elastic scroll, rubber-scroll.js scrolls there itself.
 *
 * Own text (2026-10-16): a text box with 'source' => 'own' shows its own formatted text ('html',
 * written in the editor's Visual / Text editor, kept through wp_kses_post) instead of a page's; its
 * automatic anchor is then made from the title. 'source' => 'page' (or missing) = the page's text.
 *
 * Phones (2026-10-16): below 640px the boxes stack in one column. 'm_order' (1, 2, ...) on boxes and
 * rows gives phones their own order (CSS `order` through --m-order, _start_template.scss; nothing
 * changes on bigger screens); 0 everywhere = the desktop order. With a phone order set, items without
 * one (added later) go at the end.
 *
 * Hide / show (2026-10-16): two independent switches, one eye per editor view. 'd_hide' hides a box on
 * computers and tablets (.d-hide, 641px and up - Desktop view's eye), 'm_hide' on phones (.m-hide -
 * Phone view's eye); both = the box isn't printed at all. A row's 'd_hide' (Desktop view, "Hide row")
 * hides the whole row on computers; its 'm_hide' hides just its label on phones. A row label also
 * hides on a screen where none of its boxes shows. The old 'hidden' (= everywhere) is read as both
 * switches on. Anchors are worked out over all boxes, so hiding one never renames another.
 *
 * Style (2026-10-21): 'style' on a box = its own title bar / color / shadow / font / size / text color, only the
 * parts set (design/home-box-style.php has the style of all boxes, set in Appearance > Look, and the output).
 *
 * Background blur (2026-10-24, owner: "add it to all boxes that have image backgrounds. Separate settings for light/dark"): 'blur' =
 * 0-40 px (0 = sharp), 'dark_blur' = the dark look's own (null = as light), on link and text boxes. Printed as --bg-blur / --bg-scale
 * (and -dark) on the box; the background layer (.box::before, _start_template.scss) is blurred and enlarged a little so the blur
 * doesn't fade at the edges.
 *
 * Dark mode background (2026-10-21): 'dark_image' on a box = [ 'mode' => 'same' (as light) | 'media' (+ 'id') | 'none' ].
 * Printed as --bg-dark beside --bg; html.ms-dark uses it (_dark-mode.scss); in the Look screen's dark preview it is --bg.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_HOME_LAYOUT = 'monospace_home_layout';
const MONOSPACE_HOME_BLUR_MAX = 40; // px, a text box's background blur
const MONOSPACE_HOME_RANDOM_MAX = 12; // pictures in a random box's pool

function monospace_home_layout_default() {
    $box = function ( $title, $link, $text, $image = 'auto', $width = 2, $badge = '' ) {
        return [
            'title' => $title, 'badge' => $badge, 'text' => $text, 'link' => $link, 'width' => $width,
            'image' => $image === 'auto' ? [ 'mode' => 'auto' ] : [ 'mode' => 'file', 'file' => 'assets/images/backgrounds/web/' . $image ],
        ];
    };
    return array_merge( monospace_home_top_row_default(), [
        [ 'label' => 'Shop', 'boxes' => [
            $box( 'Available Originals', '/category/available/', 'Original paintings and drawings you can buy right now, newest first.', 'auto', 3 ),
            $box( 'Prints', '/category/print/', 'Browse a curated selection of works available as print on demand.', 'ducks.webp', 2 ),
            $box( 'Miniatures', '/category/format/miniature/', 'View a collection of small-scale landscape paintings created on 4×4 inch panels.', 'miniature.webp', 2 ),
        ] ],
        [ 'label' => 'Work', 'boxes' => [
            $box( 'Favorites', '/category/favorite/', 'A selection of my favorite pieces.' ),
            $box( 'Paintings', '/category/format/painting/', 'Discover recent paintings in gouache, casein, and acrylic, presented in chronological order.', 'sunset.webp' ),
            $box( 'Drawings', '/category/format/drawing/', 'Explore recent sketches and drawings, documented over time.', 'steps.webp' ),
            $box( 'The Art Feed', '/art-feed/', 'A live chronological feed of new artworks, studio updates, and blog posts.' ),
        ] ],
        [ 'label' => 'Commissions', 'boxes' => [
            $box( 'Places', '/services/custom-work/', 'Commission a painting or drawing of a place that matters to you.', 'custom_paint.webp' ),
            $box( 'Portraits', '/portraits/', 'Commission a painted portrait of someone who matters to you.' ),
            $box( 'Illustration', '/services/illustration/', 'Selected illustration projects spanning games, publications, and interactive media.', 'illustration.webp' ),
        ] ],
        [ 'label' => 'Events', 'boxes' => [
            $box( 'Upcoming Markets', '/markets-and-events/schedule/', 'Find upcoming markets where you can see and buy my work in person.', 'jcfridays.webp' ),
            $box( 'Exhibitions', '/markets-and-events/exhibitions/', 'Current and past exhibitions of my work, from local galleries to a solo museum show.' ),
        ] ],
        [ 'label' => 'About', 'boxes' => [
            $box( 'About Me', '/about/about-me/', 'Learn more about my background, practice, and artistic approach.', 'genexpanded.webp' ),
            $box( 'Art Classes', '/services/classes/', 'Personalized art instruction focused on technique, confidence, and creative development.', 'fritz.webp' ),
            $box( 'Contact', '/connect/contact/', 'Contact me for inquiries, collaborations, or commissions.', 'pens.webp' ),
        ] ],
    ] );
}

/** The top row as it was before it became editable: Welcome (plain text) and News (box, clouds) */
function monospace_home_top_row_default() {
    $page = function ( $slug ) { $p = get_page_by_path( $slug ); return $p ? (int) $p->ID : 0; };
    return [ [ 'label' => '', 'boxes' => [
        [ 'type' => 'content', 'title' => 'Welcome!', 'page' => $page( 'welcome' ), 'width' => 1, 'look' => 'plain', 'image' => [ 'mode' => 'none' ] ],
        [ 'type' => 'content', 'title' => 'News', 'page' => $page( 'news' ), 'width' => 1, 'look' => 'box', 'image' => [ 'mode' => 'file', 'file' => 'assets/images/backgrounds/clouds2.jpg' ] ],
    ] ] ];
}

/** Clean up a submitted / stored layout */
/** A random image setting, cleaned: [ 'mode' => 'random', 'pool' => 'link' ] or with 'media' + 'ids' (2-12 pictures); null = not usable */
function monospace_home_random_sanitize( $img, $link_ok ) {
    if ( $link_ok && ( $img['pool'] ?? '' ) === 'link' ) return [ 'mode' => 'random', 'pool' => 'link' ];
    $ids = [];
    foreach ( (array) ( $img['ids'] ?? [] ) as $id ) {
        $id = absint( $id );
        if ( $id && wp_attachment_is_image( $id ) ) $ids[ $id ] = $id;
    }
    $ids = array_slice( array_values( $ids ), 0, MONOSPACE_HOME_RANDOM_MAX );
    return count( $ids ) >= 2 ? [ 'mode' => 'random', 'pool' => 'media', 'ids' => $ids ] : null;
}

function monospace_home_layout_sanitize( $rows ) {
    $clean = [];
    foreach ( (array) $rows as $row ) {
        if ( ! is_array( $row ) ) continue;
        $boxes = [];
        foreach ( (array) ( $row['boxes'] ?? [] ) as $b ) {
            if ( ! is_array( $b ) ) continue;
            if ( ( $b['type'] ?? '' ) === 'content' ) {
                $own  = ( $b['source'] ?? '' ) === 'own';
                $page = absint( $b['page'] ?? 0 );
                if ( ! $own && ( ! $page || get_post_type( $page ) !== 'page' ) ) continue;
                $img = (array) ( $b['image'] ?? [] );
                $mode = in_array( $img['mode'] ?? '', [ 'none', 'media', 'file', 'random' ], true ) ? $img['mode'] : 'none';
                $image = [ 'mode' => $mode ];
                if ( $mode === 'random' ) $image = monospace_home_random_sanitize( $img, false ) ?: [ 'mode' => 'none' ];
                if ( $mode === 'media' ) { $image['id'] = absint( $img['id'] ?? 0 ); if ( ! $image['id'] ) $image = [ 'mode' => 'none' ]; }
                if ( $mode === 'file' ) {
                    $file = (string) ( $img['file'] ?? '' );
                    $image = preg_match( '#^assets/images/backgrounds/[\w/.-]+\.(webp|jpe?g|png)$#', $file ) && strpos( $file, '..' ) === false ? [ 'mode' => 'file', 'file' => $file ] : [ 'mode' => 'none' ];
                }
                $boxes[] = [ 'type' => 'content', 'title' => sanitize_text_field( $b['title'] ?? '' ), 'page' => $own ? 0 : $page,
                             'source' => $own ? 'own' : 'page', 'html' => $own ? wp_kses_post( (string) ( $b['html'] ?? '' ) ) : '', 'anchor' => sanitize_title( $b['anchor'] ?? '' ),
                             'm_order' => absint( $b['m_order'] ?? 0 ), 'm_hide' => ! empty( $b['m_hide'] ) || ! empty( $b['hidden'] ),
                             'd_hide' => ! empty( $b['d_hide'] ) || ! empty( $b['hidden'] ),
                             'notes' => mb_substr( sanitize_textarea_field( $b['notes'] ?? '' ), 0, 2000 ),
                             'link' => ( $b['look'] ?? '' ) === 'plain' ? '' : esc_url_raw( trim( (string) ( $b['link'] ?? '' ) ) ), // optional, windowed boxes only: the title bar and the box link there
                             'width' => min( 3, max( 1, (int) ( $b['width'] ?? 1 ) ) ), 'look' => ( $b['look'] ?? '' ) === 'plain' ? 'plain' : 'box', 'image' => $image,
                             'blur' => monospace_home_blur( $b['blur'] ?? 0 ), 'dark_blur' => monospace_home_blur( $b['dark_blur'] ?? null, true ),
                             'dark_image' => monospace_home_dark_image_sanitize( $b['dark_image'] ?? [] ),
                             'style' => function_exists( 'monospace_home_box_style_sanitize' ) ? monospace_home_box_style_sanitize( $b['style'] ?? [], true ) : [],
                             'dark_style' => function_exists( 'monospace_home_box_style_sanitize' ) ? monospace_home_box_style_sanitize( $b['dark_style'] ?? [], true ) : [] ];
                continue;
            }
            $title = sanitize_text_field( $b['title'] ?? '' );
            $link  = trim( (string) ( $b['link'] ?? '' ) );
            if ( $title === '' && $link === '' ) continue;

            $img  = (array) ( $b['image'] ?? [] );
            $mode = in_array( $img['mode'] ?? '', [ 'auto', 'media', 'file', 'random' ], true ) ? $img['mode'] : 'auto';
            $image = [ 'mode' => $mode ];
            if ( $mode === 'random' ) {
                $image = monospace_home_random_sanitize( $img, true ) ?: [ 'mode' => 'auto' ];
            } elseif ( $mode === 'media' ) {
                $image['id'] = absint( $img['id'] ?? 0 );
                if ( ! $image['id'] ) $image = [ 'mode' => 'auto' ];
            } elseif ( $mode === 'file' ) {
                $file = (string) ( $img['file'] ?? '' );
                $image = preg_match( '#^assets/images/backgrounds/[\w/.-]+\.(webp|jpe?g|png)$#', $file ) && strpos( $file, '..' ) === false
                    ? [ 'mode' => 'file', 'file' => $file ] : [ 'mode' => 'auto' ];
            }

            $boxes[] = [
                'title' => $title,
                'badge' => sanitize_text_field( $b['badge'] ?? '' ),
                'text'  => sanitize_textarea_field( $b['text'] ?? '' ),
                'link'  => esc_url_raw( $link ), // a site path ("/category/print/") stays relative
                'width' => min( 3, max( 1, (int) ( $b['width'] ?? 2 ) ) ),
                'image' => $image,
                'blur' => monospace_home_blur( $b['blur'] ?? 0 ), 'dark_blur' => monospace_home_blur( $b['dark_blur'] ?? null, true ),
                'dark_image' => monospace_home_dark_image_sanitize( $b['dark_image'] ?? [] ),
                'anchor' => sanitize_title( $b['anchor'] ?? '' ),
                'm_order' => absint( $b['m_order'] ?? 0 ),
                'm_hide' => ! empty( $b['m_hide'] ) || ! empty( $b['hidden'] ), // old 'hidden' = everywhere
                'd_hide' => ! empty( $b['d_hide'] ) || ! empty( $b['hidden'] ),
                'notes' => mb_substr( sanitize_textarea_field( $b['notes'] ?? '' ), 0, 2000 ), // editor only, never printed
                'style' => function_exists( 'monospace_home_box_style_sanitize' ) ? monospace_home_box_style_sanitize( $b['style'] ?? [], true ) : [],
                'dark_style' => function_exists( 'monospace_home_box_style_sanitize' ) ? monospace_home_box_style_sanitize( $b['dark_style'] ?? [], true ) : [], // the dark look's parts over 'style'
            ];
        }
        $label = sanitize_text_field( $row['label'] ?? '' );
        if ( ! $boxes && $label === '' ) continue;
        $clean[] = [ 'label' => mb_substr( $label, 0, 60 ), 'anchor' => sanitize_title( $row['anchor'] ?? '' ),
                    'm_order' => absint( $row['m_order'] ?? 0 ), 'm_hide' => ! empty( $row['m_hide'] ),
                    'd_hide' => ! empty( $row['d_hide'] ) || ! empty( $row['hidden'] ), 'boxes' => $boxes ];
    }
    return $clean;
}

function monospace_home_layout() {
    $saved = get_option( MONOSPACE_HOME_LAYOUT, null );
    if ( ! is_array( $saved ) ) return monospace_home_layout_default();
    $rows = monospace_home_layout_sanitize( $saved );
    foreach ( $rows as $row ) foreach ( $row['boxes'] as $b ) if ( ( $b['type'] ?? '' ) === 'content' ) return $rows;
    return array_merge( monospace_home_top_row_default(), $rows ); // saved before Welcome / News were editable
}

/** The anchor a box or row gets on the page (before de-duplication): custom, else from its title / label */
function monospace_home_anchor_base( $item, $is_row = false ) {
    if ( ! empty( $item['anchor'] ) ) return $item['anchor'];
    if ( $is_row ) return sanitize_title( $item['label'] ?? '' );
    if ( ( $item['type'] ?? '' ) === 'content' && ( $item['source'] ?? '' ) !== 'own' ) {
        $slug = get_post_field( 'post_name', (int) ( $item['page'] ?? 0 ) );
        if ( $slug ) return $slug;
    }
    return sanitize_title( $item['title'] ?? '' ) ?: 'box';
}

/** Every anchor on the page, unique: [ 'row-<i>' => id, 'box-<i>-<j>' => id ] */
function monospace_home_anchors( $rows ) {
    $used = []; $out = [];
    $take = function ( $base ) use ( &$used ) {
        if ( $base === '' ) return '';
        $id = $base; $n = 2;
        while ( isset( $used[ $id ] ) ) $id = $base . '-' . $n++;
        $used[ $id ] = true;
        return $id;
    };
    foreach ( $rows as $i => $row ) {
        $out[ "row-$i" ] = $take( monospace_home_anchor_base( $row, true ) );
        foreach ( $row['boxes'] as $j => $box ) $out[ "box-$i-$j" ] = $take( monospace_home_anchor_base( $box ) );
    }
    return $out;
}

/** A box's link as an absolute URL (relative links are site paths) */
function monospace_home_box_url( $link ) {
    if ( $link === '' ) return '';
    return $link[0] === '/' ? home_url( $link ) : $link;
}

/** The image of a post: featured image, else the first media library image in its content */
function monospace_home_post_image( $post_id, $size = 'large' ) {
    if ( has_post_thumbnail( $post_id ) ) {
        $url = get_the_post_thumbnail_url( $post_id, $size );
        if ( $url ) return $url;
    }
    $content = get_post_field( 'post_content', $post_id );
    if ( preg_match( '/wp-image-(\d+)/', $content, $m ) ) {
        $url = wp_get_attachment_image_url( (int) $m[1], $size );
        if ( $url ) return $url;
    }
    return '';
}

/** The image of the newest of these posts that has one */
function monospace_home_newest_image( $query_args, $size = 'large' ) {
    $ids = get_posts( $query_args + [ 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 8, 'fields' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ] );
    foreach ( $ids as $id ) {
        $url = monospace_home_post_image( $id, $size );
        if ( $url ) return $url;
    }
    return '';
}

/**
 * The posts behind a box's link: [ 'post' => id ] (any other page or post: its own image), [ 'args' => get_posts() arguments ] (Available
 * Originals, a category, a tag, the Art Feed), or [] (nothing). The automatic background takes the newest of them, a random box several.
 */
function monospace_home_link_source( $link ) {
    $path = trim( (string) wp_parse_url( monospace_home_box_url( $link ), PHP_URL_PATH ), '/' );

    if ( $path === 'category/available' && function_exists( 'monospace_available_post_ids' ) ) {
        $ids = monospace_available_post_ids();
        return $ids ? [ 'args' => [ 'post__in' => $ids ] ] : [];
    }
    if ( preg_match( '#^(category|tag)/(?:.*/)?([^/]+)$#', $path, $m ) ) {
        $term = get_term_by( 'slug', $m[2], $m[1] === 'tag' ? 'post_tag' : 'category' );
        return $term ? [ 'args' => [ 'tax_query' => [ [ 'taxonomy' => $term->taxonomy, 'terms' => $term->term_id ] ] ] ] : [];
    }
    if ( get_option( 'page_for_posts' ) && $path === trim( (string) wp_parse_url( get_permalink( get_option( 'page_for_posts' ) ), PHP_URL_PATH ), '/' ) ) {
        return [ 'args' => [ 'tax_query' => [ [ 'taxonomy' => 'category', 'field' => 'slug', 'terms' => [ 'uncategorized', 'blog', 'miniature' ], 'operator' => 'NOT IN' ] ] ] ];
    }
    if ( $path !== '' ) {
        $id = url_to_postid( home_url( '/' . $path . '/' ) );
        if ( $id ) return [ 'post' => $id ];
    }
    return [];
}

/**
 * Automatic background: the newest artwork behind the box's link - Available Originals: the newest
 * piece for sale; a category or tag: its newest post; the Art Feed: the newest artwork (no blog posts
 * or miniatures, as before); any other page or post: its own image.
 */
function monospace_home_auto_image( $link, $size = 'large' ) {
    static $cache = [];
    if ( isset( $cache[ $link . $size ] ) ) return $cache[ $link . $size ];

    $src = monospace_home_link_source( $link );
    $url = '';
    if ( ! empty( $src['post'] ) ) $url = monospace_home_post_image( $src['post'], $size );
    elseif ( ! empty( $src['args'] ) ) $url = monospace_home_newest_image( $src['args'], $size );
    return $cache[ $link . $size ] = $url;
}

/** Up to $limit pictures of random artworks behind a link (shuffled; fewer when there are fewer) */
function monospace_home_link_images( $link, $size = 'large', $limit = MONOSPACE_HOME_RANDOM_MAX ) {
    $src = monospace_home_link_source( $link );
    if ( ! empty( $src['post'] ) ) return array_filter( [ monospace_home_post_image( $src['post'], $size ) ] );
    if ( empty( $src['args'] ) ) return [];
    $ids  = get_posts( $src['args'] + [ 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => $limit * 4, 'fields' => 'ids', 'orderby' => 'rand', 'no_found_rows' => true ] );
    $urls = [];
    foreach ( $ids as $id ) {
        $url = monospace_home_post_image( $id, $size );
        if ( $url && ! in_array( $url, $urls, true ) ) $urls[] = $url;
        if ( count( $urls ) >= $limit ) break;
    }
    return $urls;
}

/**
 * A random box's pictures (URLs, shuffled): the ones the owner chose, or random artworks behind its link. Worked out once a request,
 * so the --bg printed and the pool in data-bg-pool agree. [] = none.
 */
function monospace_home_random_pool( $box, $size = 'large' ) {
    static $cache = [];
    $key = md5( wp_json_encode( [ $box['image'] ?? [], $box['link'] ?? '', $size ] ) );
    if ( isset( $cache[ $key ] ) ) return $cache[ $key ];
    $img  = $box['image'] ?? [];
    $urls = [];
    if ( ( $img['pool'] ?? 'media' ) === 'link' ) {
        $urls = monospace_home_link_images( $box['link'] ?? '', $size );
    } else {
        foreach ( (array) ( $img['ids'] ?? [] ) as $id ) {
            $url = wp_get_attachment_image_url( (int) $id, $size );
            if ( $url ) $urls[] = $url;
        }
        shuffle( $urls );
    }
    return $cache[ $key ] = array_values( array_slice( $urls, 0, MONOSPACE_HOME_RANDOM_MAX ) );
}

/** A random box's whole pool as an attribute, for the script that picks one in the browser ('' for any other box, or a single picture) */
function monospace_home_pool_attr( $box ) {
    if ( ( $box['image']['mode'] ?? '' ) !== 'random' ) return '';
    if ( function_exists( 'monospace_dark_preview' ) && monospace_dark_preview() ) return ''; // the Look screen's dark preview shows --bg as the dark picture
    $pool = monospace_home_random_pool( $box );
    return count( $pool ) > 1 ? ' data-bg-pool="' . esc_attr( wp_json_encode( $pool ) ) . '"' : '';
}

/** A box's background image URL ('' = none) */
function monospace_home_box_image( $box, $size = 'large' ) {
    $img = $box['image'] ?? [ 'mode' => 'auto' ];
    switch ( $img['mode'] ?? 'auto' ) {
        case 'none':
            return '';
        case 'media':
            return (string) wp_get_attachment_image_url( (int) ( $img['id'] ?? 0 ), $size );
        case 'file':
            return get_stylesheet_directory_uri() . '/' . $img['file'];
        case 'random':
            return monospace_home_random_pool( $box, $size )[0] ?? '';
        default:
            return monospace_home_auto_image( $box['link'] ?? '', $size );
    }
}

/** A box's dark mode background: [ 'mode' => same | media (+ id) | none ] */
function monospace_home_dark_image_sanitize( $img ) {
    $img  = (array) $img;
    $mode = in_array( $img['mode'] ?? '', [ 'media', 'none' ], true ) ? $img['mode'] : 'same';
    if ( $mode === 'media' ) {
        $id = absint( $img['id'] ?? 0 );
        return $id ? [ 'mode' => 'media', 'id' => $id ] : [ 'mode' => 'same' ];
    }
    return [ 'mode' => $mode ];
}

/** The dark mode background's URL: null = as light, '' = none */
function monospace_home_box_dark_image( $box, $size = 'large' ) {
    $img = $box['dark_image'] ?? [ 'mode' => 'same' ];
    if ( ( $img['mode'] ?? 'same' ) === 'none' ) return '';
    if ( ( $img['mode'] ?? '' ) === 'media' ) return (string) wp_get_attachment_image_url( (int) $img['id'], $size ) ?: null;
    return null;
}

/** A box's background variables: --bg, and --bg-dark when dark mode differs (the Look screen's dark preview: the dark one as --bg) */
function monospace_home_box_bg_style( $box ) {
    $blur  = monospace_home_blur( $box['blur'] ?? 0 );
    $dblur = monospace_home_blur( $box['dark_blur'] ?? null, true ); // null = as light
    $light = monospace_home_box_image( $box );
    $dark  = function_exists( 'monospace_dark_settings' ) ? monospace_home_box_dark_image( $box ) : null;
    $url   = fn( $u ) => "url('" . esc_url( $u ) . "')";
    if ( function_exists( 'monospace_dark_preview' ) && monospace_dark_preview() && ( $dark !== null || $dblur !== null ) ) {
        $img = $dark ?? $light; // the Look screen's dark preview: the dark look as --bg
        return ( $img !== '' ? '--bg:' . $url( $img ) . ';' : '' ) . monospace_home_blur_vars( $dblur ?? $blur );
    }
    $css = monospace_home_blur_vars( $blur ) . ( $light ? '--bg:' . $url( $light ) . ';' : '' );
    if ( function_exists( 'monospace_dark_split' ) && monospace_dark_split() ) {
        if ( $dark !== null ) $css .= '--bg-dark:' . ( $dark !== '' ? $url( $dark ) : 'none' ) . ';';
        if ( $dblur !== null && $dblur !== $blur ) $css .= monospace_home_blur_vars( $dblur, '-dark' );
    }
    return $css;
}

/** A background blur in px: 0 - MONOSPACE_HOME_BLUR_MAX; $nullable: unset / '' = null (dark mode: as light) */
function monospace_home_blur( $v, $nullable = false ) {
    if ( $nullable && ( $v === null || $v === '' ) ) return null;
    return min( MONOSPACE_HOME_BLUR_MAX, absint( $v ) );
}

/** The CSS variables of a blur ($suffix '-dark' for the dark look; its 0 is printed too, it overrides the light blur) */
function monospace_home_blur_vars( $px, $suffix = '' ) {
    if ( ! $px && $suffix === '' ) return '';
    return '--bg-blur' . $suffix . ':' . $px . 'px;--bg-scale' . $suffix . ':' . round( 1 + $px * 0.008, 3 ) . ';';
}

/** A box's style classes and CSS variables (design/home-box-style.php) - '' for the theme's own look */
function monospace_home_box_look( $box ) {
    return function_exists( 'monospace_home_box_style_attrs' ) ? monospace_home_box_style_attrs( $box ) : [ 'class' => '', 'style' => '', 'data' => '' ];
}

/** The rows of boxes, as the home page prints them inside .container */
function monospace_home_render_boxes() {
    $out = '';
    $rows = monospace_home_layout();
    $anchors = monospace_home_anchors( $rows );
    // Phones: own order when any item has one (unordered items after them), hidden-on-phone class
    $custom = false;
    foreach ( $rows as $row ) {
        if ( ! empty( $row['m_order'] ) ) $custom = true;
        foreach ( $row['boxes'] as $b ) if ( ! empty( $b['m_order'] ) ) $custom = true;
    }
    $seq = 0;
    $phone = function ( $item ) use ( $custom, &$seq ) {
        $seq++;
        return [
            'style' => $custom ? '--m-order:' . ( ! empty( $item['m_order'] ) ? (int) $item['m_order'] : 10000 + $seq ) . ';' : '',
            'class' => ( ! empty( $item['m_hide'] ) ? ' m-hide' : '' ) . ( ! empty( $item['d_hide'] ) ? ' d-hide' : '' ),
        ];
    };
    foreach ( $rows as $i => $row ) {
        // Each box's hide switches, with the row's desktop switch applied; hidden on both = not printed
        foreach ( $row['boxes'] as $j => $b ) {
            $row['boxes'][ $j ]['d_hide'] = ! empty( $b['d_hide'] ) || ! empty( $row['d_hide'] );
            $row['boxes'][ $j ]['m_hide'] = ! empty( $b['m_hide'] );
        }
        $shown = array_filter( $row['boxes'], function ( $b ) { return ! ( $b['d_hide'] && $b['m_hide'] ); } );
        if ( ! $shown ) continue; // on no screen at all
        $row_id = $anchors[ "row-$i" ] !== '' ? ' id="' . esc_attr( $anchors[ "row-$i" ] ) . '"' : '';
        // The label: hidden on a screen where it's switched off or none of its boxes shows
        $m = $row['label'] !== '' ? $phone( [
            'm_order' => $row['m_order'],
            'm_hide'  => ! empty( $row['m_hide'] ) || ! array_filter( $shown, function ( $b ) { return ! $b['m_hide']; } ),
            'd_hide'  => ! empty( $row['d_hide'] ) || ! array_filter( $shown, function ( $b ) { return ! $b['d_hide']; } ),
        ] ) : [ 'style' => '', 'class' => '' ];
        $out .= $row['label'] !== ''
            ? "\n" . '<h2 class="home-row-label' . $m['class'] . '"' . $row_id . ( $m['style'] ? ' style="' . esc_attr( $m['style'] ) . '"' : '' ) . '>' . esc_html( $row['label'] ) . '</h2>' . "\n"
            : "\n" . '<div class="flex-break"' . $row_id . '></div>' . "\n";

        foreach ( $row['boxes'] as $j => $box ) {
            if ( $box['d_hide'] && $box['m_hide'] ) continue;
            $box['_id'] = $anchors[ "box-$i-$j" ];
            $box['_m'] = $phone( $box );
            if ( ( $box['type'] ?? '' ) === 'content' ) {
                $out .= monospace_home_render_text_box( $box );
                continue;
            }
            $look  = monospace_home_box_look( $box );
            $style = 'flex:' . (int) $box['width'] . ';' . monospace_home_box_bg_style( $box ) . $look['style'] . $box['_m']['style'];
            $title = ( $box['badge'] !== '' ? '<span class="box-badge">' . esc_html( $box['badge'] ) . '</span> ' : '' ) . esc_html( $box['title'] );
            $out .= sprintf(
                '<a class="box link%s" id="%s" href="%s" style="%s"%s><div class="box-title">%s</div><div class="box-content bottom-align">%s</div></a>' . "\n",
                $look['class'] . $box['_m']['class'], esc_attr( $box['_id'] ), esc_url( monospace_home_box_url( $box['link'] ) ), esc_attr( $style ), ( $look['data'] ?? '' ) . monospace_home_pool_attr( $box ), $title, nl2br( esc_html( $box['text'] ) )
            );
        }
    }
    // Random pictures: each box with a pool takes one of them in the visitor's browser (right after the boxes, so before they paint);
    // the server's own pick stays for browsers without scripts
    if ( strpos( $out, 'data-bg-pool' ) !== false ) {
        $out .= "<script data-no-optimize=\"1\">document.querySelectorAll('[data-bg-pool]').forEach(function(e){try{var p=JSON.parse(e.getAttribute('data-bg-pool'));if(p.length>1)e.style.setProperty('--bg','url(\\''+p[Math.floor(Math.random()*p.length)].replace(/'/g,'%27')+'\\')')}catch(x){}})</script>\n";
    }
    return $out;
}

/** A text box: the title and the page's text (Welcome, News) */
function monospace_home_render_text_box( $box ) {
    if ( ( $box['source'] ?? '' ) === 'own' ) {
        if ( trim( wp_strip_all_tags( $box['html'] ) ) === '' && strpos( $box['html'], '<img' ) === false ) return '';
        $slug = sanitize_title( $box['title'] ) ?: 'box';
        $html = $box['html'];
    } else {
        $page = get_post( $box['page'] );
        if ( ! $page || $page->post_status !== 'publish' ) return '';
        $slug = $page->post_name;
        $html = $page->post_content;
    }
    $look  = monospace_home_box_look( $box );
    $style = 'flex:' . (int) $box['width'] . ';' . monospace_home_box_bg_style( $box ) . $look['style'] . ( $box['_m']['style'] ?? '' );
    // Optional link (2026-10-26; windowed boxes only, not plain text): only the title bar is the link (.box-linked, _start_template.scss); the rest of the box scrolls
    $url   = $box['look'] === 'plain' ? '' : monospace_home_box_url( (string) ( $box['link'] ?? '' ) );
    $title = $url !== '' ? '<a class="box-cover" href="' . esc_url( $url ) . '">' . esc_html( $box['title'] ) . '</a>' : esc_html( $box['title'] );
    if ( $url !== '' ) $look['class'] .= ' box-linked';
    return sprintf(
        "\n" . '<div class="box box-text box-%s%s" id="%s" style="%s"%s><div class="box-title">%s</div><div class="box-content">%s</div></div>' . "\n",
        $box['look'] === 'plain' ? 'plain' : 'boxed', $look['class'] . ( $box['_m']['class'] ?? '' ), esc_attr( $box['_id'] ?? $slug ), esc_attr( $style ), ( $look['data'] ?? '' ) . monospace_home_pool_attr( $box ), $title,
        apply_filters( 'the_content', $html )
    );
}

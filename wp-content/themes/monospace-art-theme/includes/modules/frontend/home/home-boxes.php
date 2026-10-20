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
 * the original box backgrounds); auto = the newest artwork behind the link (monospace_home_auto_image).
 * No option saved = monospace_home_layout_default() (the menu's sections, 2026-10-15).
 *
 * Text boxes (box 'type' => 'content'): show a page's text instead of linking - Welcome and News
 * (the top row, editable since 2026-10-15). [ 'type' => 'content', 'page' => page ID, 'title',
 * 'width', 'look' => 'plain' (text on the page, like Welcome) | 'box' (title bar + background, like
 * News; the text scrolls), 'image' ]. The box's id is the page's slug (#welcome / #news keep their
 * CSS). A saved layout without text boxes gets the default top row put back in front.
 *
 * Anchors (2026-10-16): every box and every labelled row has an id to link to - art.monospace.com/#prints.
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
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_HOME_LAYOUT = 'monospace_home_layout';

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
                $mode = in_array( $img['mode'] ?? '', [ 'none', 'media', 'file' ], true ) ? $img['mode'] : 'none';
                $image = [ 'mode' => $mode ];
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
                             'width' => min( 3, max( 1, (int) ( $b['width'] ?? 1 ) ) ), 'look' => ( $b['look'] ?? '' ) === 'plain' ? 'plain' : 'box', 'image' => $image ];
                continue;
            }
            $title = sanitize_text_field( $b['title'] ?? '' );
            $link  = trim( (string) ( $b['link'] ?? '' ) );
            if ( $title === '' && $link === '' ) continue;

            $img  = (array) ( $b['image'] ?? [] );
            $mode = in_array( $img['mode'] ?? '', [ 'auto', 'media', 'file' ], true ) ? $img['mode'] : 'auto';
            $image = [ 'mode' => $mode ];
            if ( $mode === 'media' ) {
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
                'anchor' => sanitize_title( $b['anchor'] ?? '' ),
                'm_order' => absint( $b['m_order'] ?? 0 ),
                'm_hide' => ! empty( $b['m_hide'] ) || ! empty( $b['hidden'] ), // old 'hidden' = everywhere
                'd_hide' => ! empty( $b['d_hide'] ) || ! empty( $b['hidden'] ),
                'notes' => mb_substr( sanitize_textarea_field( $b['notes'] ?? '' ), 0, 2000 ), // editor only, never printed
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
 * Automatic background: the newest artwork behind the box's link - Available Originals: the newest
 * piece for sale; a category or tag: its newest post; the Art Feed: the newest artwork (no blog posts
 * or miniatures, as before); any other page or post: its own image.
 */
function monospace_home_auto_image( $link, $size = 'large' ) {
    static $cache = [];
    if ( isset( $cache[ $link . $size ] ) ) return $cache[ $link . $size ];

    $path = trim( (string) wp_parse_url( monospace_home_box_url( $link ), PHP_URL_PATH ), '/' );
    $url  = '';

    if ( $path === 'category/available' && function_exists( 'monospace_available_post_ids' ) ) {
        $ids = monospace_available_post_ids();
        $url = $ids ? monospace_home_newest_image( [ 'post__in' => $ids ], $size ) : '';
    } elseif ( preg_match( '#^(category|tag)/(?:.*/)?([^/]+)$#', $path, $m ) ) {
        $term = get_term_by( 'slug', $m[2], $m[1] === 'tag' ? 'post_tag' : 'category' );
        if ( $term ) $url = monospace_home_newest_image( [ 'tax_query' => [ [ 'taxonomy' => $term->taxonomy, 'terms' => $term->term_id ] ] ], $size );
    } elseif ( get_option( 'page_for_posts' ) && $path === trim( (string) wp_parse_url( get_permalink( get_option( 'page_for_posts' ) ), PHP_URL_PATH ), '/' ) ) {
        $url = monospace_home_newest_image( [ 'tax_query' => [ [ 'taxonomy' => 'category', 'field' => 'slug', 'terms' => [ 'uncategorized', 'blog', 'miniature' ], 'operator' => 'NOT IN' ] ] ], $size );
    } elseif ( $path !== '' ) {
        $id = url_to_postid( home_url( '/' . $path . '/' ) );
        if ( $id ) $url = monospace_home_post_image( $id, $size );
    }
    return $cache[ $link . $size ] = $url;
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
        default:
            return monospace_home_auto_image( $box['link'] ?? '', $size );
    }
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
            $image = monospace_home_box_image( $box );
            $style = 'flex:' . (int) $box['width'] . ';' . ( $image ? "--bg:url('" . esc_url( $image ) . "');" : '' ) . $box['_m']['style'];
            $title = ( $box['badge'] !== '' ? '<span class="box-badge">' . esc_html( $box['badge'] ) . '</span> ' : '' ) . esc_html( $box['title'] );
            $out .= sprintf(
                '<a class="box link%s" id="%s" href="%s" style="%s"><div class="box-title">%s</div><div class="box-content bottom-align">%s</div></a>' . "\n",
                $box['_m']['class'], esc_attr( $box['_id'] ), esc_url( monospace_home_box_url( $box['link'] ) ), esc_attr( $style ), $title, nl2br( esc_html( $box['text'] ) )
            );
        }
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
    $image = monospace_home_box_image( $box );
    $style = 'flex:' . (int) $box['width'] . ';' . ( $image ? "--bg:url('" . esc_url( $image ) . "');" : '' ) . ( $box['_m']['style'] ?? '' );
    return sprintf(
        "\n" . '<div class="box box-text box-%s%s" id="%s" style="%s"><div class="box-title">%s</div><div class="box-content">%s</div></div>' . "\n",
        $box['look'] === 'plain' ? 'plain' : 'boxed', $box['_m']['class'] ?? '', esc_attr( $box['_id'] ?? $slug ), esc_attr( $style ), esc_html( $box['title'] ),
        apply_filters( 'the_content', $html )
    );
}

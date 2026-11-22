<?php
/**
 * [next_market] (2026-11-17, owner): the name, the date and time and the location of the next scheduled market, read from the Upcoming Markets
 * page - the card for the market is a group with a heading (the name) and a paragraph that starts with the date and time in bold, then the
 * location on the lines after it. Put it at the top of the News page: the News box on the home page shows that page's text.
 *
 *   [next_market]                              <h2>Next Market: Owl Holiday Market</h2><p><strong>Saturday November 28, 4pm — 8pm</strong><br>Market Pavilion, 212 Raritan ...</p>
 *   [next_market label="" tag="h3" link="yes"] without the label, as an h3, the name linking to the Upcoming Markets page
 *   [next_market empty="No market scheduled right now."]  what to print when no market is ahead (default: nothing)
 *   [next_market count="3"]                    the next three: the headline "Upcoming Markets" (label="" for none), then each market's name in bold, its
 *                                              date and time and its location (default count 1; at most 12; fewer if the page lists fewer)
 *   [next_market color="#c0392b"]              the headline's color (the date and location keep the text color): a hex (#c00, #cc0000, #cc0000cc), rgb() / rgba() /
 *                                              hsl() / hsla(), or an HTML color name (tomato, rebeccapurple ...); anything else is ignored
 *
 * The page is found by its role "markets" (page meta _monospace_role, Core's page-roles.php) - never by its address, which the owner moves. A
 * card is skipped if its date can not be read (a month, a day, and a year if there is one - without one it is the coming one). The next market is
 * the earliest whose last day is today or later. Nothing is stored: the cards are read from the page (cached while the page is unchanged).
 *
 * @package monospace-art-core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** A color for the headline, or '' when it is not one: hex (with or without #), rgb() / rgba() / hsl() / hsla(), or an HTML color name. Nothing else gets through. */
function monospace_market_color( $value ) {
    $v = strtolower( trim( (string) $value ) );
    if ( $v === '' ) return '';
    if ( preg_match( '/^#?([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $v, $m ) ) return '#' . $m[1];
    $n = '\s*(\d{1,3}(?:\.\d+)?%?)\s*';
    $a = '(?:\s*[,\/]\s*(\d(?:\.\d+)?|\.\d+|\d{1,3}%))?';
    if ( preg_match( '/^(rgba?|hsla?)\(' . $n . '[,\s]' . $n . '[,\s]' . $n . $a . '\s*\)$/', $v ) ) return $v;
    $names = 'aliceblue antiquewhite aqua aquamarine azure beige bisque black blanchedalmond blue blueviolet brown burlywood cadetblue chartreuse chocolate coral cornflowerblue cornsilk crimson cyan darkblue darkcyan darkgoldenrod darkgray darkgreen darkgrey darkkhaki darkmagenta darkolivegreen darkorange darkorchid darkred darksalmon darkseagreen darkslateblue darkslategray darkslategrey darkturquoise darkviolet deeppink deepskyblue dimgray dimgrey dodgerblue firebrick floralwhite forestgreen fuchsia gainsboro ghostwhite gold goldenrod gray green greenyellow grey honeydew hotpink indianred indigo ivory khaki lavender lavenderblush lawngreen lemonchiffon lightblue lightcoral lightcyan lightgoldenrodyellow lightgray lightgreen lightgrey lightpink lightsalmon lightseagreen lightskyblue lightslategray lightslategrey lightsteelblue lightyellow lime limegreen linen magenta maroon mediumaquamarine mediumblue mediumorchid mediumpurple mediumseagreen mediumslateblue mediumspringgreen mediumturquoise mediumvioletred midnightblue mintcream mistyrose moccasin navajowhite navy oldlace olive olivedrab orange orangered orchid palegoldenrod palegreen paleturquoise palevioletred papayawhip peachpuff peru pink plum powderblue purple rebeccapurple red rosybrown royalblue saddlebrown salmon sandybrown seagreen seashell sienna silver skyblue slateblue slategray slategrey snow springgreen steelblue tan teal thistle tomato turquoise violet wheat white whitesmoke yellow yellowgreen currentcolor';
    return in_array( $v, explode( ' ', $names ), true ) ? $v : '';
}

/** The first and last day in a card's date text, as Y-m-d, or null: "Saturday, December 5, 2026, 10am-4pm", "Saturday November 28, 4pm", "November 7-8, noon-6pm, 2026" */
function monospace_market_dates( $text, $today = null ) {
    $today  = $today ?: current_time( 'Y-m-d' );
    $months = [ 'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4, 'may' => 5, 'jun' => 6, 'jul' => 7, 'aug' => 8, 'sep' => 9, 'oct' => 10, 'nov' => 11, 'dec' => 12 ];
    if ( ! preg_match( '/\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?\s+(\d{1,2})(?:\s*[-\x{2013}\x{2014}]\s*(\d{1,2})(?!\s*(?:am|pm|:)))?/iu', $text, $m ) ) return null;
    $mon   = $months[ strtolower( $m[1] ) ];
    $first = (int) $m[2];
    $last  = ! empty( $m[3] ) ? (int) $m[3] : $first;
    if ( $first < 1 || $first > 31 || $last < $first || $last > 31 ) return null;
    if ( preg_match( '/\b(20\d{2})\b/', $text, $y ) ) {
        $year = (int) $y[1];
    } else {
        $year = (int) substr( $today, 0, 4 );
        // No year written: the coming one (a date more than four months behind us is next year's)
        if ( strtotime( sprintf( '%04d-%02d-%02d', $year, $mon, $last ) ) < strtotime( $today ) - 120 * DAY_IN_SECONDS ) $year++;
    }
    $a = sprintf( '%04d-%02d-%02d', $year, $mon, $first );
    $b = sprintf( '%04d-%02d-%02d', $year, $mon, $last );
    return checkdate( $mon, $first, $year ) && checkdate( $mon, $last, $year ) ? [ $a, $b ] : null;
}

/** One block's plain text */
function monospace_market_text( $html ) {
    return trim( preg_replace( '/\s+/', ' ', html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) ) );
}

/** The cards on a list of blocks (any depth): [ 'name', 'when', 'where' ] in page order */
function monospace_market_cards_in( array $blocks ) {
    $out = [];
    foreach ( $blocks as $b ) {
        $name = $when = $where = '';
        foreach ( (array) ( $b['innerBlocks'] ?? [] ) as $in ) {
            if ( $in['blockName'] === 'core/heading' && $name === '' ) $name = monospace_market_text( $in['innerHTML'] );
            if ( $in['blockName'] === 'core/paragraph' && $when === '' && preg_match( '#<strong>(.*?)</strong>(.*)#s', (string) $in['innerHTML'], $m ) ) {
                $when  = monospace_market_text( $m[1] );
                $lines = array_filter( array_map( 'monospace_market_text', preg_split( '#<br\s*/?>#i', (string) $m[2] ) ), 'strlen' );
                $where = implode( ', ', $lines );
            }
        }
        if ( $b['blockName'] === 'core/group' && $name !== '' && $when !== '' ) {
            $out[] = [ 'name' => $name, 'when' => $when, 'where' => $where ];
        } elseif ( ! empty( $b['innerBlocks'] ) ) {
            $out = array_merge( $out, monospace_market_cards_in( $b['innerBlocks'] ) );
        }
    }
    return $out;
}

/** The cards on the Upcoming Markets page with their dates; cached while the page is unchanged */
function monospace_market_cards() {
    $page = function_exists( 'monospace_role_page' ) ? monospace_role_page( 'markets' ) : null;
    if ( ! $page || $page->post_status === 'trash' ) return [];
    $key = 'market_cards_' . $page->ID . '_' . strtotime( $page->post_modified_gmt );
    $hit = wp_cache_get( $key, 'monospace' );
    if ( is_array( $hit ) ) return $hit;
    $cards = [];
    foreach ( monospace_market_cards_in( parse_blocks( $page->post_content ) ) as $c ) {
        $d = monospace_market_dates( $c['when'] );
        if ( $d ) $cards[] = $c + [ 'start' => $d[0], 'end' => $d[1] ];
    }
    wp_cache_set( $key, $cards, 'monospace', DAY_IN_SECONDS );
    return $cards;
}

/** The next $n markets (those whose last day is today or later), the earliest first; a tie keeps the page's order */
function monospace_upcoming_markets( $n = 1, $today = null ) {
    $today = $today ?: current_time( 'Y-m-d' );
    $ahead = [];
    foreach ( monospace_market_cards() as $i => $c ) {
        if ( $c['end'] >= $today ) $ahead[] = $c + [ 'order' => $i ];
    }
    usort( $ahead, function ( $a, $b ) { return [ $a['start'], $a['order'] ] <=> [ $b['start'], $b['order'] ]; } );
    return array_slice( $ahead, 0, max( 1, (int) $n ) );
}

/** The next market (the earliest whose last day is today or later), or null */
function monospace_next_market( $today = null ) {
    $m = monospace_upcoming_markets( 1, $today );
    return $m ? $m[0] : null;
}

add_shortcode( 'next_market', function ( $atts ) {
    $a = shortcode_atts( [ 'label' => 'Next Market', 'tag' => 'h2', 'link' => 'no', 'empty' => '', 'color' => '', 'count' => 1 ], $atts, 'next_market' );
    $n = max( 1, min( 12, (int) $a['count'] ) );
    $markets = monospace_upcoming_markets( $n );
    if ( ! $markets ) return $a['empty'] !== '' ? '<p class="ms-next-market">' . esc_html( $a['empty'] ) . '</p>' : '';

    $tag   = in_array( $a['tag'], [ 'h2', 'h3', 'h4', 'p' ], true ) ? $a['tag'] : 'h2';
    $color = monospace_market_color( $a['color'] );
    $style = $color !== '' ? ' style="color:' . esc_attr( $color ) . '"' : '';
    $url   = strtolower( $a['link'] ) === 'yes' && function_exists( 'monospace_role_url' ) ? monospace_role_url( 'markets' ) : '';
    $named = function ( $name, $inherit ) use ( $url ) {   // the name, linked to the Upcoming Markets page when asked (in the headline the link takes the headline's color)
        $name = esc_html( $name );
        return $url ? '<a href="' . esc_url( $url ) . '"' . ( $inherit ? ' style="color:inherit"' : '' ) . '>' . $name . '</a>' : $name;
    };
    $head = function ( $text ) use ( $tag, $style ) {
        return '<' . $tag . ( $tag === 'p' ? $style . '><strong>' . $text . '</strong></p>' : ' class="wp-block-heading"' . $style . '>' . $text . '</' . $tag . '>' );
    };

    // One market: "Next Market: Name", then its date and time and location
    if ( $n === 1 ) {
        $m = $markets[0];
        return '<div class="ms-next-market">'
            . $head( ( $a['label'] !== '' ? esc_html( $a['label'] ) . ': ' : '' ) . $named( $m['name'], $color !== '' ) )
            . '<p><strong>' . esc_html( $m['when'] ) . '</strong>' . ( $m['where'] !== '' ? '<br>' . esc_html( $m['where'] ) : '' ) . '</p>'
            . '</div>';
    }

    // Several: one headline (the label is "Upcoming Markets" unless given), then each market's name in bold, when, where
    $label = isset( $atts['label'] ) ? (string) $a['label'] : 'Upcoming Markets';
    $out   = '<div class="ms-next-market">' . ( $label !== '' ? $head( esc_html( $label ) ) : '' );
    foreach ( $markets as $m ) {
        $out .= '<p><strong>' . $named( $m['name'], false ) . '</strong><br>' . esc_html( $m['when'] ) . ( $m['where'] !== '' ? '<br>' . esc_html( $m['where'] ) : '' ) . '</p>';
    }
    return $out . '</div>';
} );

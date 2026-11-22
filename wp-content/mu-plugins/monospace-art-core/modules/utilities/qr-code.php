<?php
/**
 * QR code helper (2026-10-30, owner's request) - lean custom code, no library (owner's rule).
 *
 *   monospace_qr_svg( $text, $args )       an inline <svg> string - crisp at any size, prints well
 *   monospace_qr_data_uri( $text, $args )  'data:image/svg+xml;base64,...' for an <img src> or CSS background
 *   monospace_qr_png( $text, $args )       PNG bytes (GD) for a download or an email - '' without GD
 *   monospace_qr_matrix( $text, $ecc )     [ 'version', 'size', 'modules' => [ row => [ 0|1 ] ] ] for anything else
 *
 * $args (all optional):
 *   ecc     'L' 7% · 'M' 15% (default) · 'Q' 25% · 'H' 30% damage the code survives
 *   margin  quiet zone in modules (default 4 - the standard; scanners need it)
 *   fg, bg  '#rrggbb' / '#rgb'; bg '' or 'transparent' = none (svg only; PNG draws white)
 *   size    svg: width / height in px (omitted = fills its container) · png: pixels per module (default 8)
 *   label   svg: accessible name (<title> + aria-label); without one the svg is decorative (aria-hidden)
 *   class   svg: class attribute
 *
 * Text goes in as UTF-8 bytes (byte mode). Versions 1-20, up to 858 bytes at ecc L / 666 at M / 482 at Q / 382 at H -
 * plenty for a URL. Too long → '' (the matrix function returns null). A URL is the usual payload:
 *   echo monospace_qr_svg( get_permalink( $id ), [ 'label' => 'Link to this artwork', 'size' => 160 ] );
 * Paint it dark on light with the quiet zone intact: scanners read dark-on-light, and a logo over it eats the ecc budget.
 * Pure functions, no output, no side effects.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** [ ec codewords per block, count1, data per block1, (count2, data per block2) ] for versions 1-20 and ecc L, M, Q, H */
function monospace_qr_block_table() {
    return [
        1 => [ [7,1,19], [10,1,16], [13,1,13], [17,1,9] ],
        2 => [ [10,1,34], [16,1,28], [22,1,22], [28,1,16] ],
        3 => [ [15,1,55], [26,1,44], [18,2,17], [22,2,13] ],
        4 => [ [20,1,80], [18,2,32], [26,2,24], [16,4,9] ],
        5 => [ [26,1,108], [24,2,43], [18,2,15,2,16], [22,2,11,2,12] ],
        6 => [ [18,2,68], [16,4,27], [24,4,19], [28,4,15] ],
        7 => [ [20,2,78], [18,4,31], [18,2,14,4,15], [26,4,13,1,14] ],
        8 => [ [24,2,97], [22,2,38,2,39], [22,4,18,2,19], [26,4,14,2,15] ],
        9 => [ [30,2,116], [22,3,36,2,37], [20,4,16,4,17], [24,4,12,4,13] ],
        10 => [ [18,2,68,2,69], [26,4,43,1,44], [24,6,19,2,20], [28,6,15,2,16] ],
        11 => [ [20,4,81], [30,1,50,4,51], [28,4,22,4,23], [24,3,12,8,13] ],
        12 => [ [24,2,92,2,93], [22,6,36,2,37], [26,4,20,6,21], [28,7,14,4,15] ],
        13 => [ [26,4,107], [22,8,37,1,38], [24,8,20,4,21], [22,12,11,4,12] ],
        14 => [ [30,3,115,1,116], [24,4,40,5,41], [20,11,16,5,17], [24,11,12,5,13] ],
        15 => [ [22,5,87,1,88], [24,5,41,5,42], [30,5,24,7,25], [24,11,12,7,13] ],
        16 => [ [24,5,98,1,99], [28,7,45,3,46], [24,15,19,2,20], [30,3,15,13,16] ],
        17 => [ [28,1,107,5,108], [28,10,46,1,47], [28,1,22,15,23], [28,2,14,17,15] ],
        18 => [ [30,5,120,1,121], [26,9,43,4,44], [28,17,22,1,23], [28,2,14,19,15] ],
        19 => [ [28,3,113,4,114], [26,3,44,11,45], [26,17,21,4,22], [26,9,13,16,14] ],
        20 => [ [28,3,107,5,108], [26,3,41,13,42], [30,15,24,5,25], [28,15,15,10,16] ],
    ];
}

/** Centres of the alignment patterns per version */
function monospace_qr_alignment( $version ) {
    $t = [ 1 => [], 2 => [6,18], 3 => [6,22], 4 => [6,26], 5 => [6,30], 6 => [6,34], 7 => [6,22,38], 8 => [6,24,42], 9 => [6,26,46], 10 => [6,28,50],
        11 => [6,30,54], 12 => [6,32,58], 13 => [6,34,62], 14 => [6,26,46,66], 15 => [6,26,48,70], 16 => [6,26,50,74], 17 => [6,30,54,78],
        18 => [6,30,56,82], 19 => [6,30,58,86], 20 => [6,34,62,90] ];
    return $t[ $version ];
}

/** Reed-Solomon check bytes (GF(256), polynomial 0x11D) */
function monospace_qr_rs( array $data, $ec ) {
    static $exp = null, $log = null;
    if ( $exp === null ) {
        $exp = []; $log = [];
        $x = 1;
        for ( $i = 0; $i < 255; $i++ ) { $exp[ $i ] = $x; $log[ $x ] = $i; $x <<= 1; if ( $x & 0x100 ) $x ^= 0x11D; }
        for ( $i = 255; $i < 512; $i++ ) $exp[ $i ] = $exp[ $i - 255 ];
    }
    $gen = [ 1 ];
    for ( $i = 0; $i < $ec; $i++ ) {
        $next = array_fill( 0, count( $gen ) + 1, 0 );
        foreach ( $gen as $j => $coef ) {
            $next[ $j ] ^= $coef;
            if ( $coef ) $next[ $j + 1 ] ^= $exp[ $log[ $coef ] + $i ];
        }
        $gen = $next;
    }
    $rem = array_fill( 0, $ec, 0 );
    foreach ( $data as $byte ) {
        $factor = $byte ^ array_shift( $rem );
        $rem[] = 0;
        if ( $factor ) {
            for ( $j = 0; $j < $ec; $j++ ) {
                if ( $gen[ $j + 1 ] ) $rem[ $j ] ^= $exp[ $log[ $gen[ $j + 1 ] ] + $log[ $factor ] ];
            }
        }
    }
    return $rem;
}

function monospace_qr_bch( $value, $generator, $shift ) {
    $digits = function ( $n ) { $d = 0; while ( $n ) { $d++; $n >>= 1; } return $d; };
    $d = $value << $shift;
    while ( $digits( $d ) - $digits( $generator ) >= 0 ) $d ^= $generator << ( $digits( $d ) - $digits( $generator ) );
    return ( $value << $shift ) | $d;
}

/** Is this module flipped by the mask? */
function monospace_qr_mask( $mask, $i, $j ) {
    switch ( $mask ) {
        case 0: return ( $i + $j ) % 2 === 0;
        case 1: return $i % 2 === 0;
        case 2: return $j % 3 === 0;
        case 3: return ( $i + $j ) % 3 === 0;
        case 4: return ( intdiv( $i, 2 ) + intdiv( $j, 3 ) ) % 2 === 0;
        case 5: return ( $i * $j ) % 2 + ( $i * $j ) % 3 === 0;
        case 6: return ( ( $i * $j ) % 2 + ( $i * $j ) % 3 ) % 2 === 0;
        default: return ( ( $i * $j ) % 3 + ( $i + $j ) % 2 ) % 2 === 0;
    }
}

/** The mask choice: lower is better (runs, 2x2 blocks, finder look-alikes, dark balance) */
function monospace_qr_penalty( array $m, $n ) {
    $score = 0;
    foreach ( [ false, true ] as $columns ) {
        for ( $a = 0; $a < $n; $a++ ) {
            $line = [];
            for ( $b = 0; $b < $n; $b++ ) $line[] = $columns ? (int) $m[ $b ][ $a ] : (int) $m[ $a ][ $b ];
            $run = 1;
            for ( $b = 1; $b < $n; $b++ ) {
                if ( $line[ $b ] === $line[ $b - 1 ] ) { $run++; }
                else { if ( $run >= 5 ) $score += 3 + $run - 5; $run = 1; }
            }
            if ( $run >= 5 ) $score += 3 + $run - 5;
            $s = implode( '', $line );
            $score += 40 * ( substr_count( $s, '10111010000' ) + substr_count( $s, '00001011101' ) );
        }
    }
    $dark = 0;
    for ( $r = 0; $r < $n; $r++ ) {
        for ( $c = 0; $c < $n; $c++ ) {
            $dark += (int) $m[ $r ][ $c ];
            if ( $r < $n - 1 && $c < $n - 1 ) {
                $v = (int) $m[ $r ][ $c ];
                if ( $v === (int) $m[ $r ][ $c + 1 ] && $v === (int) $m[ $r + 1 ][ $c ] && $v === (int) $m[ $r + 1 ][ $c + 1 ] ) $score += 3;
            }
        }
    }
    return $score + 10 * intdiv( (int) abs( $dark * 100 / ( $n * $n ) - 50 ), 5 );
}

/**
 * The QR matrix for $text, or null when it doesn't fit in version 20.
 * @return array|null [ 'version' => int, 'size' => int, 'modules' => [ [ 0|1 ] ] ]
 */
function monospace_qr_matrix( $text, $ecc = 'M' ) {
    $ecc = strtoupper( (string) $ecc );
    $ecc_index = [ 'L' => 0, 'M' => 1, 'Q' => 2, 'H' => 3 ][ $ecc ] ?? 1;
    $ecc_bits  = [ 1, 0, 3, 2 ][ $ecc_index ];
    $bytes = array_values( unpack( 'C*', (string) $text ) ?: [] );
    $table = monospace_qr_block_table();

    // the smallest version that holds it
    $version = 0;
    foreach ( $table as $v => $levels ) {
        $row = $levels[ $ecc_index ];
        $data_cw = $row[1] * $row[2] + ( isset( $row[3] ) ? $row[3] * $row[4] : 0 );
        $bits = 4 + ( $v < 10 ? 8 : 16 ) + 8 * count( $bytes );
        if ( (int) ceil( $bits / 8 ) <= $data_cw ) { $version = $v; break; }
    }
    if ( ! $version || ! $bytes ) return null;
    $row = $table[ $version ][ $ecc_index ];
    $ec = $row[0];
    $groups = [ [ $row[1], $row[2] ] ];
    if ( isset( $row[3] ) ) $groups[] = [ $row[3], $row[4] ];
    $data_cw = 0;
    foreach ( $groups as $g ) $data_cw += $g[0] * $g[1];

    // data bits: mode 0100 (bytes), count, the bytes, terminator, padding
    $bits = '0100' . str_pad( decbin( count( $bytes ) ), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT );
    foreach ( $bytes as $b ) $bits .= str_pad( decbin( $b ), 8, '0', STR_PAD_LEFT );
    $bits .= str_repeat( '0', min( 4, $data_cw * 8 - strlen( $bits ) ) );
    $bits .= str_repeat( '0', ( 8 - strlen( $bits ) % 8 ) % 8 );
    $words = array_map( 'bindec', str_split( $bits, 8 ) );
    for ( $pad = 0; count( $words ) < $data_cw; $pad++ ) $words[] = $pad % 2 ? 0x11 : 0xEC;

    // blocks, check bytes, interleaved
    $blocks = []; $ecs = []; $at = 0;
    foreach ( $groups as $g ) {
        for ( $i = 0; $i < $g[0]; $i++ ) {
            $block = array_slice( $words, $at, $g[1] );
            $at += $g[1];
            $blocks[] = $block;
            $ecs[] = monospace_qr_rs( $block, $ec );
        }
    }
    $stream = [];
    $longest = max( array_map( 'count', $blocks ) );
    for ( $i = 0; $i < $longest; $i++ ) foreach ( $blocks as $block ) { if ( isset( $block[ $i ] ) ) $stream[] = $block[ $i ]; }
    for ( $i = 0; $i < $ec; $i++ ) foreach ( $ecs as $e ) $stream[] = $e[ $i ];

    $n = 17 + 4 * $version;
    $base = array_fill( 0, $n, array_fill( 0, $n, null ) );

    // finder patterns with their separators
    foreach ( [ [ 0, 0 ], [ $n - 7, 0 ], [ 0, $n - 7 ] ] as $pos ) {
        for ( $r = -1; $r <= 7; $r++ ) {
            for ( $c = -1; $c <= 7; $c++ ) {
                $y = $pos[0] + $r; $x = $pos[1] + $c;
                if ( $y < 0 || $y >= $n || $x < 0 || $x >= $n ) continue;
                $base[ $y ][ $x ] = ( $r >= 0 && $r <= 6 && ( $c === 0 || $c === 6 ) ) || ( $c >= 0 && $c <= 6 && ( $r === 0 || $r === 6 ) ) || ( $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4 );
            }
        }
    }
    // alignment patterns (not where a finder already is)
    $centres = monospace_qr_alignment( $version );
    foreach ( $centres as $cy ) foreach ( $centres as $cx ) {
        if ( $base[ $cy ][ $cx ] !== null ) continue;
        for ( $r = -2; $r <= 2; $r++ ) for ( $c = -2; $c <= 2; $c++ ) {
            $base[ $cy + $r ][ $cx + $c ] = $r === -2 || $r === 2 || $c === -2 || $c === 2 || ( $r === 0 && $c === 0 );
        }
    }
    // timing patterns
    for ( $i = 8; $i < $n - 8; $i++ ) {
        if ( $base[ $i ][ 6 ] === null ) $base[ $i ][ 6 ] = $i % 2 === 0;
        if ( $base[ 6 ][ $i ] === null ) $base[ 6 ][ $i ] = $i % 2 === 0;
    }
    // format and version information
    $put_format = function ( array &$m, $mask, $real ) use ( $n, $ecc_bits ) {
        $bits = monospace_qr_bch( ( $ecc_bits << 3 ) | $mask, 0x537, 10 ) ^ 0x5412;
        for ( $i = 0; $i < 15; $i++ ) {
            $dark = $real && ( ( $bits >> $i ) & 1 ) === 1;
            if ( $i < 6 ) $m[ $i ][ 8 ] = $dark; elseif ( $i < 8 ) $m[ $i + 1 ][ 8 ] = $dark; else $m[ $n - 15 + $i ][ 8 ] = $dark;
            if ( $i < 8 ) $m[ 8 ][ $n - $i - 1 ] = $dark; elseif ( $i < 9 ) $m[ 8 ][ 15 - $i ] = $dark; else $m[ 8 ][ 14 - $i ] = $dark;
        }
        $m[ $n - 8 ][ 8 ] = $real;
    };
    $put_format( $base, 0, false );
    if ( $version >= 7 ) {
        $bits = monospace_qr_bch( $version, 0x1F25, 12 );
        for ( $i = 0; $i < 18; $i++ ) {
            $dark = ( ( $bits >> $i ) & 1 ) === 1;
            $base[ intdiv( $i, 3 ) ][ $i % 3 + $n - 11 ] = $dark;
            $base[ $i % 3 + $n - 11 ][ intdiv( $i, 3 ) ] = $dark;
        }
    }

    // data, zig-zag from the bottom right, for each of the 8 masks - keep the best
    $best = null; $best_score = PHP_INT_MAX;
    for ( $mask = 0; $mask < 8; $mask++ ) {
        $m = $base;
        $inc = -1; $row = $n - 1; $bit = 7; $byte = 0; $count = count( $stream );
        for ( $pair = $n - 1; $pair > 0; $pair -= 2 ) {
            $col = $pair <= 6 ? $pair - 1 : $pair; // column pairs step over the vertical timing pattern (column 6)
            while ( true ) {
                for ( $c = 0; $c < 2; $c++ ) {
                    if ( $m[ $row ][ $col - $c ] !== null ) continue;
                    $dark = $byte < $count && ( ( $stream[ $byte ] >> $bit ) & 1 ) === 1;
                    if ( monospace_qr_mask( $mask, $row, $col - $c ) ) $dark = ! $dark;
                    $m[ $row ][ $col - $c ] = $dark;
                    if ( --$bit === -1 ) { $byte++; $bit = 7; }
                }
                $row += $inc;
                if ( $row < 0 || $row >= $n ) { $row -= $inc; $inc = -$inc; break; }
            }
        }
        $put_format( $m, $mask, true );
        $score = monospace_qr_penalty( $m, $n );
        if ( $score < $best_score ) { $best_score = $score; $best = $m; }
    }

    $modules = [];
    foreach ( $best as $r => $cells ) $modules[ $r ] = array_map( function ( $v ) { return $v ? 1 : 0; }, $cells );
    return [ 'version' => $version, 'size' => $n, 'modules' => $modules ];
}

/** A '#rgb' / '#rrggbb' colour, or $default */
function monospace_qr_colour( $value, $default ) {
    $value = trim( (string) $value );
    return preg_match( '/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value ) ? strtolower( $value ) : $default;
}

/** The code as an inline <svg> ('' when the text is empty or too long) */
function monospace_qr_svg( $text, $args = [] ) {
    $args   = (array) $args;
    $matrix = monospace_qr_matrix( $text, $args['ecc'] ?? 'M' );
    if ( ! $matrix ) return '';
    $margin = isset( $args['margin'] ) ? max( 0, (int) $args['margin'] ) : 4;
    $fg     = monospace_qr_colour( $args['fg'] ?? '', '#000000' );
    $bg     = ( ! isset( $args['bg'] ) || $args['bg'] === '' || $args['bg'] === 'transparent' ) ? '' : monospace_qr_colour( $args['bg'], '' );
    $total  = $matrix['size'] + 2 * $margin;
    $path   = '';
    foreach ( $matrix['modules'] as $y => $cells ) {
        $x = 0;
        while ( $x < $matrix['size'] ) {
            if ( ! $cells[ $x ] ) { $x++; continue; }
            $start = $x;
            while ( $x < $matrix['size'] && $cells[ $x ] ) $x++;
            $path .= 'M' . ( $start + $margin ) . ' ' . ( $y + $margin ) . 'h' . ( $x - $start ) . 'v1h-' . ( $x - $start ) . 'z';
        }
    }
    $label = isset( $args['label'] ) ? trim( (string) $args['label'] ) : '';
    $size  = isset( $args['size'] ) ? (int) $args['size'] : 0;
    $attrs = 'xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges"'
        . ( $size > 0 ? ' width="' . $size . '" height="' . $size . '"' : '' )
        . ( ! empty( $args['class'] ) ? ' class="' . esc_attr( $args['class'] ) . '"' : '' )
        . ( $label !== '' ? ' role="img" aria-label="' . esc_attr( $label ) . '"' : ' aria-hidden="true"' );
    return '<svg ' . $attrs . '>' . ( $label !== '' ? '<title>' . esc_html( $label ) . '</title>' : '' )
        . ( $bg !== '' ? '<rect width="' . $total . '" height="' . $total . '" fill="' . $bg . '"/>' : '' )
        . '<path fill="' . $fg . '" d="' . $path . '"/></svg>';
}

/** The code as a data: URI for <img src> or a CSS background ('' when it can't be made) */
function monospace_qr_data_uri( $text, $args = [] ) {
    $svg = monospace_qr_svg( $text, $args );
    return $svg === '' ? '' : 'data:image/svg+xml;base64,' . base64_encode( $svg );
}

/** The code as PNG bytes - $args['size'] is pixels per module (default 8); '' without GD or when it can't be made */
function monospace_qr_png( $text, $args = [] ) {
    $args   = (array) $args;
    $matrix = function_exists( 'imagecreate' ) ? monospace_qr_matrix( $text, $args['ecc'] ?? 'M' ) : null;
    if ( ! $matrix ) return '';
    $margin = isset( $args['margin'] ) ? max( 0, (int) $args['margin'] ) : 4;
    $scale  = isset( $args['size'] ) ? max( 1, min( 64, (int) $args['size'] ) ) : 8;
    $px     = ( $matrix['size'] + 2 * $margin ) * $scale;
    $rgb    = function ( $hex ) {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        return [ hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) ];
    };
    $image = imagecreate( $px, $px );
    $bg = $rgb( monospace_qr_colour( $args['bg'] ?? '', '#ffffff' ) );
    $fg = $rgb( monospace_qr_colour( $args['fg'] ?? '', '#000000' ) );
    $light = imagecolorallocate( $image, $bg[0], $bg[1], $bg[2] );
    $dark  = imagecolorallocate( $image, $fg[0], $fg[1], $fg[2] );
    imagefill( $image, 0, 0, $light );
    foreach ( $matrix['modules'] as $y => $cells ) foreach ( $cells as $x => $on ) {
        if ( $on ) imagefilledrectangle( $image, ( $x + $margin ) * $scale, ( $y + $margin ) * $scale, ( $x + $margin + 1 ) * $scale - 1, ( $y + $margin + 1 ) * $scale - 1, $dark );
    }
    ob_start();
    imagepng( $image );
    $png = (string) ob_get_clean();
    return $png;
}

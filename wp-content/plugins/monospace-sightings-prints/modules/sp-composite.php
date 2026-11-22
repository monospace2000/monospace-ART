<?php
/**
 * Sightings prints: the print file. The drawing and its caption on one canvas, in one of three layouts the buyer picks
 * (owner, 2026-11-19): 'dark' = the drawing on a dark band with the caption below (like the website), 'overlay' = the drawing
 * fills the canvas and the caption sits on a soft dark gradient, 'light' = a cream margin with the caption below.
 * Canvases: '4x5' (8x10 and 11x14 prints, 2000x2500) and '2x3' (the 4x6 postcard, 1200x1800). Drawn with GD + FreeType
 * (Lora, the site's own text face).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** key => [ label shown to the buyer, letter used in file names ] */
function monospace_sp_layouts() {
    return [
        'dark'    => [ 'label' => 'dark band',    'letter' => 'a' ],
        'overlay' => [ 'label' => 'overlay',      'letter' => 'b' ],
        'light'   => [ 'label' => 'light margin', 'letter' => 'c' ],
    ];
}

/**
 * Geometry per canvas: size in px; for the two band layouts the drawing's width and top margin and the largest caption size (px);
 * for the overlay the caption box (fractions of the canvas).
 */
function monospace_sp_canvases() {
    return [
        '4x5' => [ 'w' => 2000, 'h' => 2500,
                   'dark'  => [ 'pw' => 1250, 'top' => 162, 'size' => 90 ],
                   'light' => [ 'pw' => 1325, 'top' => 162, 'size' => 85 ],
                   'overlay' => [ 'size' => 70, 'x' => 0.09, 'y' => 0.775, 'h' => 0.18 ] ],
        // The 4x6 postcard held upright: 4.13 x 6.14 in at 300 dpi = the card plus Printful's bleed (its print file is 1842x1240 landscape;
        // monospace_sp_turn_for_card() turns this picture sideways to fit it)
        '2x3' => [ 'w' => 1240, 'h' => 1842,
                   'dark'  => [ 'pw' => 950, 'top' => 114, 'size' => 62 ],
                   'light' => [ 'pw' => 971, 'top' => 114, 'size' => 54 ],
                   'overlay' => [ 'size' => 43, 'x' => 0.09, 'y' => 0.775, 'h' => 0.18 ] ],
    ];
}

/**
 * The postcard's print file: the upright picture turned 90 degrees counterclockwise into Printful's landscape card (1842x1240), so
 * the buyer's card reads upright when it is turned clockwise. $upright and $dest are JPEG paths. Returns $dest.
 */
function monospace_sp_turn_for_card( $upright, $dest ) {
    monospace_prints_turn_file( $upright, $dest ); // the shared turn (prints plugin: also used for the artworks' postcards)
    $size = @getimagesize( $dest );
    if ( ! $size || $size[0] !== 1842 || $size[1] !== 1240 ) throw new Exception( 'The postcard file is not 1842x1240 px.' );
    return $dest;
}

function monospace_sp_font() {
    return MONOSPACE_SP_DIR . '/assets/fonts/Lora.ttf';
}

/** GD image from a PNG / JPEG / WebP file */
function monospace_sp_load( $path ) {
    $info = @getimagesize( $path );
    if ( ! $info ) throw new Exception( 'Not an image: ' . basename( $path ) );
    $fn = [ IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_WEBP => 'imagecreatefromwebp' ][ $info[2] ] ?? null;
    if ( ! $fn ) throw new Exception( 'Unsupported image type: ' . basename( $path ) );
    $im = $fn( $path );
    if ( ! $im ) throw new Exception( 'Could not read ' . basename( $path ) );
    return $im;
}

function monospace_sp_text_width( $text, $px ) {
    $b = imagettfbbox( $px * 0.75, 0, monospace_sp_font(), $text ); // GD sizes are points: 0.75 x the pixel size
    return $b ? abs( $b[2] - $b[0] ) : 0;
}

/**
 * The words of a caption as break units: [ text, glued ] - glued = no space before it (what follows an em dash, so a line may
 * break after a dash but never in front of it).
 */
function monospace_sp_units( $text ) {
    $units = [];
    foreach ( preg_split( '/\s+/u', trim( $text ) ) as $word ) {
        $parts = preg_split( '/(?<=—)/u', $word, -1, PREG_SPLIT_NO_EMPTY );
        foreach ( $parts as $i => $part ) $units[] = [ $part, $i > 0 ];
    }
    return $units;
}

/** Words that read badly at the end of a line (the next line should start with them) */
function monospace_sp_weak_end( $word ) {
    return in_array( strtolower( trim( $word, '“”"‘’\'.,;:!?—-' ) ), [ 'a', 'an', 'the', 'of', 'to', 'in', 'on', 'at', 'by', 'for', 'and', 'or', 'but', 'as', 'if', 'no', 'with', 'from', 'into' ], true );
}

/** Text of units $i..$j-1 as one line */
function monospace_sp_line_text( $units, $i, $j ) {
    $t = '';
    for ( $k = $i; $k < $j; $k++ ) $t .= ( $k > $i && ! $units[ $k ][1] ? ' ' : '' ) . $units[ $k ][0];
    return $t;
}

/**
 * Wrap $text to $maxw px at $px with even lines (minimum raggedness over the whole caption, not line by line): no last line of a
 * single word or a stub (under 40% of the longest line), no line ending on "a" / "the" / "of" / "No"..., and as few lines as the width allows.
 * Returns [ lines, ok ] - ok = none of those faults is left.
 */
function monospace_sp_wrap( $text, $px, $maxw ) {
    $u = monospace_sp_units( $text );
    $n = count( $u );
    if ( ! $n ) return [ [], true ];
    $w = []; // width of each candidate line, computed once
    $width = function ( $i, $j ) use ( &$w, $u, $px ) {
        return $w[ $i ][ $j ] ?? ( $w[ $i ][ $j ] = monospace_sp_text_width( monospace_sp_line_text( $u, $i, $j ), $px ) );
    };
    $INF = 1e18; $best = array_fill( 0, $n + 1, $INF ); $from = array_fill( 0, $n + 1, 0 ); $best[0] = 0;
    for ( $j = 1; $j <= $n; $j++ ) {
        for ( $i = $j - 1; $i >= 0; $i-- ) {
            if ( $u[ $i ][1] ) continue;               // a line never starts with what follows a dash
            $lw = $width( $i, $j );
            if ( $lw > $maxw && $j - $i > 1 ) break;   // too wide (a lone over-long word is let through)
            if ( $best[ $i ] >= $INF ) continue;
            $slack = ( $maxw - $lw ) / $maxw;
            $cost  = $j === $n ? 0 : $slack * $slack * 100;
            if ( $j === $n ) {
                if ( $j - $i < 2 && $n > 1 ) $cost += 400;                       // a one-word last line
                elseif ( $lw < 0.4 * $maxw ) $cost += 120 * ( 0.4 - $lw / $maxw ); // a stub
                $cost += 4 * ( 1 - $lw / $maxw );                                // a fuller last line is nicer
            } elseif ( monospace_sp_weak_end( $u[ $j - 1 ][0] ) ) $cost += 14;
            $cost += 12;                                                          // every line costs: fewer lines
            if ( $best[ $i ] + $cost < $best[ $j ] ) { $best[ $j ] = $best[ $i ] + $cost; $from[ $j ] = $i; }
        }
    }
    $lines = []; $j = $n; $guard = 0;
    while ( $j > 0 && $guard++ < 500 ) { $i = $from[ $j ]; array_unshift( $lines, monospace_sp_line_text( $u, $i, $j ) ); $j = $i; }
    // Quality of what the cost picked
    $ok = true; $longest = 0;
    foreach ( $lines as $l ) $longest = max( $longest, monospace_sp_text_width( $l, $px ) );
    $last = end( $lines );
    if ( count( $lines ) > 1 ) {
        if ( count( preg_split( '/\s+/u', trim( $last ) ) ) < 2 ) $ok = false;
        if ( monospace_sp_text_width( $last, $px ) < 0.4 * $longest ) $ok = false;
        for ( $k = 0; $k < count( $lines ) - 1; $k++ ) {
            if ( monospace_sp_text_width( $lines[ $k ], $px ) < 0.62 * $longest ) $ok = false; // a ragged middle
            $words = preg_split( '/\s+/u', trim( $lines[ $k ] ) );
            if ( monospace_sp_weak_end( end( $words ) ) ) $ok = false;                          // "... of" / "the"
        }
    }
    return [ $lines, $ok ];
}

/**
 * The caption inside a box: the largest size (down from $start px) at which it fits the height AND wraps cleanly. Before giving up a
 * size it tries a slightly narrower column (down to 78%), which usually evens a stubborn last line out. $center = centered lines.
 */
function monospace_sp_caption( $im, $text, $x, $y, $w, $h, $rgb, $start, $center = false ) {
    $chosen = null; $fallback = null;
    for ( $px = $start; $px >= 22; $px -= 2 ) {
        $lh = (int) round( $px * 1.38 );
        foreach ( [ 1, 0.95, 0.9, 0.85, 0.78 ] as $f ) {
            list( $lines, $ok ) = monospace_sp_wrap( $text, $px, (int) round( $w * $f ) );
            if ( count( $lines ) * $lh > $h ) continue;
            if ( $fallback === null ) $fallback = [ $px, $lines, $lh ]; // the biggest size that fits at all
            if ( $ok ) { $chosen = [ $px, $lines, $lh ]; break 2; }
        }
    }
    if ( $chosen === null ) $chosen = $fallback;
    if ( $chosen === null ) { // nothing fits even at the smallest size: draw it anyway
        $lh = (int) round( 22 * 1.38 ); list( $lines ) = monospace_sp_wrap( $text, 22, $w ); $chosen = [ 22, $lines, $lh ];
    }
    list( $px, $lines, $lh ) = $chosen;
    $color = imagecolorallocate( $im, $rgb[0], $rgb[1], $rgb[2] );
    $top = $y + (int) floor( ( $h - count( $lines ) * $lh ) / 2 );
    // Left-aligned text keeps the picture's left edge; a narrower column still starts there
    foreach ( $lines as $i => $line ) {
        $lx = $center ? $x + (int) floor( ( $w - monospace_sp_text_width( $line, $px ) ) / 2 ) : $x;
        // imagettftext's y is the baseline: about 1.05 x the size below the top of a line box of this height
        imagettftext( $im, $px * 0.75, 0, $lx, $top + $i * $lh + (int) round( $px * 1.05 ), $color, monospace_sp_font(), $line );
    }
}

/** Resize $src into a new true-color image of $w x $h */
function monospace_sp_scaled( $src, $w, $h ) {
    $dst = imagecreatetruecolor( $w, $h );
    imagecopyresampled( $dst, $src, 0, 0, 0, 0, $w, $h, imagesx( $src ), imagesy( $src ) );
    return $dst;
}

/** A mark (PNG with transparency) resized to $w x $h as a GD image: white at 92% opacity, or a black silhouette at $factor of that (for the soft shadow) */
function monospace_sp_mark_img( $file, $w, $h, $black = false, $factor = 1.0 ) {
    $src = imagecreatefrompng( $file );
    $dst = imagecreatetruecolor( $w, $h );
    imagealphablending( $dst, false ); imagesavealpha( $dst, true );
    imagefill( $dst, 0, 0, imagecolorallocatealpha( $dst, 0, 0, 0, 127 ) );
    imagecopyresampled( $dst, $src, 0, 0, 0, 0, $w, $h, imagesx( $src ), imagesy( $src ) );
    imagedestroy( $src );
    $rgb = $black ? 0 : 0xFFFFFF;
    for ( $y = 0; $y < $h; $y++ ) for ( $x = 0; $x < $w; $x++ ) {
        $a = ( imagecolorat( $dst, $x, $y ) >> 24 ) & 127;
        if ( $a >= 127 ) continue;
        imagesetpixel( $dst, $x, $y, ( ( 127 - (int) round( ( 127 - $a ) * 0.92 * $factor ) ) << 24 ) | $rgb );
    }
    return $dst;
}

/**
 * The two marks on top of the picture (owner 2026-11-20): the Sightings logo at the left ($x0), "a [monospace ART logo] project" at the
 * right (ending at $x1), $y = top of the row; $k = canvas width / 2000. White, with a faint soft shadow so they stay readable.
 */
function monospace_sp_marks( $im, $x0, $x1, $y, $k ) {
    $dir = MONOSPACE_SP_DIR . '/assets/img/';
    $s = getimagesize( $dir . 'mark-sightings.png' ); $a = getimagesize( $dir . 'mark-art.png' );
    $sw = (int) round( 240 * $k ); $sh = (int) round( $s[1] * $sw / $s[0] );
    $ah = (int) round( 34 * $k );  $aw = (int) round( $a[0] * $ah / $a[1] );
    $px = 34 * $k; $pt = $px * 0.75; $font = monospace_sp_font(); $gap = (int) round( $px * 0.26 );
    $wa = monospace_sp_text_width( 'a', $px );
    $tx = (int) round( $x1 - ( $wa + $gap + $aw + $gap + monospace_sp_text_width( 'project', $px ) ) );
    $bl = $y + intdiv( $sh, 2 ) + (int) round( 10 * $k );            // the text baseline
    $ay = (int) round( $bl - 0.797 * $ah );                           // the logo's own baseline sits 79.7% down
    $ax = $tx + (int) round( $wa ) + $gap;
    $px2 = $ax + $aw + $gap;
    imagealphablending( $im, true );
    $draw = function ( $dx, $dy, $black, $factor ) use ( $im, $dir, $sw, $sh, $aw, $ah, $x0, $y, $tx, $bl, $ay, $ax, $px2, $pt, $font ) {
        $l1 = monospace_sp_mark_img( $dir . 'mark-sightings.png', $sw, $sh, $black, $factor );
        $l2 = monospace_sp_mark_img( $dir . 'mark-art.png', $aw, $ah, $black, $factor );
        imagecopy( $im, $l1, $x0 + $dx, $y + $dy, 0, 0, $sw, $sh );
        imagecopy( $im, $l2, $ax + $dx, $ay + $dy, 0, 0, $aw, $ah );
        $c = $black ? imagecolorallocatealpha( $im, 0, 0, 0, 127 - (int) round( 127 * 0.92 * $factor ) ) : imagecolorallocatealpha( $im, 255, 255, 255, 10 );
        imagettftext( $im, $pt, 0, $tx + $dx, $bl + $dy, $c, $font, 'a' );
        imagettftext( $im, $pt, 0, $px2 + $dx, $bl + $dy, $c, $font, 'project' );
        imagedestroy( $l1 ); imagedestroy( $l2 );
    };
    $r = max( 2, (int) round( 3 * $k ) );
    foreach ( [ [ $r, 0 ], [ -$r, 0 ], [ 0, $r ], [ 0, -$r ], [ $r, $r ], [ -$r, -$r ], [ $r, -$r ], [ -$r, $r ] ] as $o ) $draw( $o[0], $o[1], true, 0.06 );
    $draw( 0, 0, false, 1.0 );
}

/**
 * Draw the print file for a drawing. $source = path of the clean original, $text = the caption, $layout / $canvas as above,
 * $dest = JPEG path to write. Returns $dest.
 */
function monospace_sp_render( $source, $text, $layout, $canvas, $dest ) {
    $cv = monospace_sp_canvases()[ $canvas ] ?? null;
    if ( ! $cv || ! isset( monospace_sp_layouts()[ $layout ] ) ) throw new Exception( 'Unknown layout or canvas.' );
    if ( ! function_exists( 'imagettftext' ) ) throw new Exception( 'This server cannot draw text (GD FreeType is missing).' );
    $W = $cv['w']; $H = $cv['h'];
    $art = monospace_sp_load( $source );
    $aw = imagesx( $art ); $ah = imagesy( $art );

    if ( $layout === 'overlay' ) {
        // The drawing covers the canvas (trimmed to fit), a dark gradient rises from the lower 40%
        $s = max( $W / $aw, $H / $ah );
        $sw = (int) ceil( $aw * $s ); $sh = (int) ceil( $ah * $s );
        $big = monospace_sp_scaled( $art, $sw, $sh );
        $im = imagecreatetruecolor( $W, $H );
        imagecopy( $im, $big, 0, 0, (int) floor( ( $sw - $W ) / 2 ), (int) floor( ( $sh - $H ) / 2 ), $W, $H );
        imagedestroy( $big );
        imagealphablending( $im, true );
        $top = (int) floor( $H * 0.60 );
        for ( $y = $top; $y < $H; $y++ ) {
            $opacity = 0.92 * pow( ( $y - $top ) / ( $H - $top ), 0.9 );
            imageline( $im, 0, $y, $W - 1, $y, imagecolorallocatealpha( $im, 10, 10, 10, (int) round( 127 * ( 1 - $opacity ) ) ) );
        }
        $o = $cv['overlay'];
        $m = (int) round( $W * $o['x'] );
        monospace_sp_caption( $im, $text, $m, (int) round( $H * $o['y'] ), $W - 2 * $m, (int) round( $H * $o['h'] ), [ 240, 240, 240 ], $o['size'] );
        $edge = (int) round( 70 * $W / 2000 );
        monospace_sp_marks( $im, $edge, $W - $edge, $edge, $W / 2000 );
    } else {
        $g = $cv[ $layout ];
        $im = imagecreatetruecolor( $W, $H );
        $bg = $layout === 'dark' ? [ 17, 17, 17 ] : [ 244, 239, 230 ];
        imagefill( $im, 0, 0, imagecolorallocate( $im, $bg[0], $bg[1], $bg[2] ) );
        $ph = (int) round( $g['pw'] * $ah / $aw );
        $small = monospace_sp_scaled( $art, $g['pw'], $ph );
        $x0 = (int) floor( ( $W - $g['pw'] ) / 2 );
        imagecopy( $im, $small, $x0, $g['top'], 0, 0, $g['pw'], $ph );
        imagedestroy( $small );
        $ty = $g['top'] + $ph + (int) round( $W * 0.02 );
        monospace_sp_caption( $im, $text, $x0, $ty, $g['pw'], $H - $ty - (int) round( $H * ( $layout === 'dark' ? 0.045 : 0.04 ) ),
            $layout === 'dark' ? [ 206, 206, 206 ] : [ 34, 34, 34 ], $g['size'], $layout === 'light' );
        $inset = (int) round( 45 * $W / 2000 );
        monospace_sp_marks( $im, $x0 + $inset, $x0 + $g['pw'] - $inset, $g['top'] + $inset, $W / 2000 );
    }
    imagedestroy( $art );
    if ( ! imagejpeg( $im, $dest, 95 ) ) throw new Exception( 'Could not save the print file.' );
    imagedestroy( $im );
    return $dest;
}

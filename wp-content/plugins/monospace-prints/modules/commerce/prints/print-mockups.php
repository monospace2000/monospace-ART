<?php
/**
 * Print mockups, made on the site (2026-10-18; they used to come from Printful's mockup generator).
 *
 * monospace_print_mockup_render() draws one print the way it hangs: the artwork centre-cropped to the
 * print's shape (as Printful prints it - "fill"), unframed (matte paper, soft shadow) or in a frame
 * (Black / White: flat color; Red Oak: the owner's wood sample, assets/images/red-oak-texture.jpg,
 * a copy of red-oak-sample.jpg at a working size), mitred corners, light from above. Front-on, square,
 * **transparent background** (owner's wish: the mockup can be put on the site's background or any
 * other later) - WebP, which keeps the transparency at a tenth of a PNG's size. GD only (the host
 * has no Imagick); drawn at 2× and scaled down for smooth edges, about a second per image.
 *
 * They are not in the media library: each print variation's mockup is a file in
 * uploads/print-mockups/<post>/<type>-<variant>-<hash>.webp (+ .jpg on white for Meta's catalog),
 * drawn the first time it's asked for (monospace_print_mockup_url(), the parse_request handler) and
 * ahead of time by the print job (monospace_print_mockups_warm()). Used by the price card's "See a
 * preview", the cart thumbnail and Facebook for WooCommerce (monospace_print_mockups_to_meta()).
 * Changing the drawing: bump MONOSPACE_MOCKUP_VERSION - every mockup gets a new URL and is redrawn
 * (run scripts/print-mockups-2026-10-18.php to redraw ahead and update Meta).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_MOCKUP_PX = 1200;  // final size, square
const MONOSPACE_MOCKUP_SS = 2;     // drawn at 2×, scaled down = antialiasing
const MONOSPACE_FRAME_IN  = 0.78;  // frame face width in inches (measured off Printful's own mockups)
const MONOSPACE_MOCKUP_FIT = 0.72; // the print (with frame) takes this much of the square
const MONOSPACE_MOCKUP_VERSION = 1; // bump after changing the drawing: every mockup gets a new URL
const MONOSPACE_MOCKUP_DIR = 'print-mockups'; // in uploads

/* ============================================================
 * DRAWING
 * ============================================================ */

/** A transparent truecolor canvas */
function monospace_mockup_canvas( $w, $h ) {
    $c = imagecreatetruecolor( $w, $h );
    imagealphablending( $c, false );
    imagefill( $c, 0, 0, imagecolorallocatealpha( $c, 0, 0, 0, 127 ) );
    imagealphablending( $c, true );
    imagesavealpha( $c, true );
    return $c;
}

/** The artwork centre-cropped to w × h (Printful "fill") */
function monospace_mockup_fill( $src, $w, $h ) {
    $sw = imagesx( $src ); $sh = imagesy( $src );
    $r  = max( $w / $sw, $h / $sh );
    $cw = $w / $r; $ch = $h / $r;
    $dst = imagecreatetruecolor( $w, $h );
    imagecopyresampled( $dst, $src, 0, 0, (int) ( ( $sw - $cw ) / 2 ), (int) ( ( $sh - $ch ) / 2 ), $w, $h, (int) round( $cw ), (int) round( $ch ) );
    return $dst;
}

/**
 * A soft drop shadow as black with alpha: drawn small, blurred (GD's blur is 3×3, so it's blurred
 * at 1/8 size), turned into alpha and scaled up onto the canvas.
 */
function monospace_mockup_shadow( $canvas, $x, $y, $w, $h, $blur, $offset, $opacity ) {
    $k  = 8;
    $cw = imagesx( $canvas ); $ch = imagesy( $canvas );
    $sw = (int) ceil( $cw / $k ); $sh = (int) ceil( $ch / $k );
    $s = imagecreatetruecolor( $sw, $sh );
    imagefill( $s, 0, 0, imagecolorallocate( $s, 255, 255, 255 ) );
    imagefilledrectangle( $s, (int) ( $x / $k ), (int) ( ( $y + $offset ) / $k ), (int) ( ( $x + $w ) / $k ), (int) ( ( $y + $h + $offset ) / $k ), imagecolorallocate( $s, 0, 0, 0 ) );
    for ( $i = 0; $i < $blur; $i++ ) imagefilter( $s, IMG_FILTER_GAUSSIAN_BLUR );
    $a = monospace_mockup_canvas( $sw, $sh );
    imagealphablending( $a, false );
    for ( $py = 0; $py < $sh; $py++ ) {
        for ( $px = 0; $px < $sw; $px++ ) {
            $dark = 255 - ( imagecolorat( $s, $px, $py ) & 0xFF );
            imagesetpixel( $a, $px, $py, 127 - (int) round( 127 * $opacity * $dark / 255 ) << 24 );
        }
    }
    imagecopyresampled( $canvas, $a, 0, 0, 0, 0, $sw * $k, $sh * $k, $sw, $sh );
}

/** The Red Oak texture (grain running up and down), loaded once */
function monospace_mockup_oak() {
    static $oak = null;
    if ( $oak === null ) {
        $file = MONOSPACE_PRINTS_DIR . '/assets/red-oak-texture.jpg'; // the owner's wood sample (was in the theme's images until 2026-10-19)
        $oak = file_exists( $file ) ? imagecreatefromjpeg( $file ) : false;
        if ( ! $oak ) throw new Exception( 'The Red Oak texture is missing (assets/images/red-oak-texture.jpg).' );
    }
    return $oak;
}

/**
 * One side of the frame's face: $len along the molding, $fw across it, grain along its length.
 * $scale = texture px per canvas px (the same for all four sides, so the grain matches); $seed picks
 * a different piece of the wood per side. Returns $fw × $len (vertical side) or $len × $fw.
 */
function monospace_mockup_face( $frame, $len, $fw, $vertical, $scale, $seed ) {
    if ( $frame === 'red-oak' ) {
        $oak = monospace_mockup_oak();
        $tw = imagesx( $oak ); $th = imagesy( $oak );
        $sw = min( $tw, max( 1, (int) round( $fw * $scale ) ) );
        $sh = min( $th, max( 1, (int) round( $len * $scale ) ) );
        $sx = (int) ( ( $tw - $sw ) * [ 0.12, 0.78, 0.40, 0.60 ][ $seed % 4 ] );
        $sy = (int) ( ( $th - $sh ) * [ 0.30, 0.65, 0.10, 0.85 ][ $seed % 4 ] );
        $side = imagecreatetruecolor( $fw, $len );
        imagecopyresampled( $side, $oak, 0, 0, $sx, $sy, $fw, $len, $sw, $sh );
        return $vertical ? $side : imagerotate( $side, 90, 0 );
    }
    [ $w, $h ] = $vertical ? [ $fw, $len ] : [ $len, $fw ];
    $side = imagecreatetruecolor( $w, $h );
    imagefill( $side, 0, 0, $frame === 'white' ? imagecolorallocate( $side, 244, 243, 240 ) : imagecolorallocate( $side, 30, 30, 30 ) );
    return $side;
}

/**
 * One mockup. $art = GD image of the artwork (its shape gives the orientation), $inches = [ short,
 * long ] side of the print, $frame = '' (unframed) | black | white | red-oak. Returns a GD image,
 * MONOSPACE_MOCKUP_PX square, transparent around the print.
 */
function monospace_print_mockup_render( $art, $inches, $frame = '' ) {
    $W   = MONOSPACE_MOCKUP_PX * MONOSPACE_MOCKUP_SS;
    $ss  = MONOSPACE_MOCKUP_SS;
    $portrait = imagesy( $art ) > imagesx( $art );
    [ $pw, $ph ] = $portrait ? [ $inches[0], $inches[1] ] : [ $inches[1], $inches[0] ];
    $fin = $frame ? MONOSPACE_FRAME_IN : 0;
    $ppi = MONOSPACE_MOCKUP_FIT * $W / max( $pw + 2 * $fin, $ph + 2 * $fin );
    $fw  = (int) round( $fin * $ppi );
    $IW  = (int) round( $pw * $ppi ); $IH = (int) round( $ph * $ppi );
    $OW  = $IW + 2 * $fw; $OH = $IH + 2 * $fw;
    $x0  = (int) ( ( $W - $OW ) / 2 ); $y0 = (int) ( ( $W - $OH ) / 2 ) - (int) ( 0.02 * $W );
    $x1  = $x0 + $OW; $y1 = $y0 + $OH;

    $c = monospace_mockup_canvas( $W, $W );
    monospace_mockup_shadow( $c, $x0, $y0, $OW, $OH, $frame ? 6 : 4, ( $frame ? 0.012 : 0.008 ) * $W, $frame ? 0.30 : 0.22 );
    imagecopy( $c, monospace_mockup_fill( $art, $IW, $IH ), $x0 + $fw, $y0 + $fw, 0, 0, $IW, $IH );

    if ( ! $frame ) { // matte paper: a faint sheen from the top, a hairline edge
        for ( $i = 0; $i < $IH; $i++ ) {
            imageline( $c, $x0, $y0 + $i, $x1 - 1, $y0 + $i, imagecolorallocatealpha( $c, 255, 255, 255, 127 - (int) ( 10 * ( 1 - $i / $IH ) ) ) );
        }
        imagerectangle( $c, $x0, $y0, $x1 - 1, $y1 - 1, imagecolorallocatealpha( $c, 0, 0, 0, 115 ) );
        return monospace_mockup_finish( $c );
    }

    // The frame: top + bottom across the full width, then left / right row by row inside the mitres
    $scale = $frame === 'red-oak' ? imagesy( monospace_mockup_oak() ) / max( $OW, $OH ) : 1;
    imagecopy( $c, monospace_mockup_face( $frame, $OW, $fw, false, $scale, 1 ), $x0, $y0, 0, 0, $OW, $fw );
    imagecopy( $c, monospace_mockup_face( $frame, $OW, $fw, false, $scale, 2 ), $x0, $y1 - $fw, 0, 0, $OW, $fw );
    $l = monospace_mockup_face( $frame, $OH, $fw, true, $scale, 3 );
    $r = monospace_mockup_face( $frame, $OH, $fw, true, $scale, 0 );
    for ( $y = 0; $y < $OH; $y++ ) {
        $span = min( $fw, $y, $OH - 1 - $y );
        if ( $span <= 0 ) continue;
        imagecopy( $c, $l, $x0, $y0 + $y, 0, $y, $span, 1 );
        imagecopy( $c, $r, $x1 - $span, $y0 + $y, $fw - $span, $y, $span, 1 );
    }

    // Light from above: top face lighter, bottom darker, right side a little darker
    $tint = function ( $pts, $rgb, $alpha ) use ( $c ) { imagefilledpolygon( $c, $pts, imagecolorallocatealpha( $c, $rgb, $rgb, $rgb, $alpha ) ); };
    $tint( [ $x0, $y0, $x1, $y0, $x1 - $fw, $y0 + $fw, $x0 + $fw, $y0 + $fw ], 255, $frame === 'black' ? 112 : 120 );
    $tint( [ $x0, $y1, $x1, $y1, $x1 - $fw, $y1 - $fw, $x0 + $fw, $y1 - $fw ], 0, $frame === 'white' ? 116 : 108 );
    $tint( [ $x1, $y0, $x1, $y1, $x1 - $fw, $y1 - $fw, $x1 - $fw, $y0 + $fw ], 0, $frame === 'white' ? 122 : 118 );
    // Mitre seams, outer edge, inner edge
    $seam = imagecolorallocatealpha( $c, 0, 0, 0, $frame === 'black' ? 90 : 105 );
    imagesetthickness( $c, $ss );
    imageline( $c, $x0, $y0, $x0 + $fw, $y0 + $fw, $seam ); imageline( $c, $x1, $y0, $x1 - $fw, $y0 + $fw, $seam );
    imageline( $c, $x0, $y1, $x0 + $fw, $y1 - $fw, $seam ); imageline( $c, $x1, $y1, $x1 - $fw, $y1 - $fw, $seam );
    imagesetthickness( $c, 1 );
    imagerectangle( $c, $x0, $y0, $x1 - 1, $y1 - 1, imagecolorallocatealpha( $c, 0, 0, 0, $frame === 'white' ? 100 : 110 ) );
    imagerectangle( $c, $x0 + $fw, $y0 + $fw, $x1 - $fw - 1, $y1 - $fw - 1, imagecolorallocatealpha( $c, 0, 0, 0, 100 ) );
    // The frame's lip throws a shadow onto the print (top and left)
    $sh = (int) ( $fw * 0.35 );
    for ( $i = 0; $i < $sh; $i++ ) {
        $col = imagecolorallocatealpha( $c, 0, 0, 0, 127 - (int) ( 60 * ( 1 - $i / $sh ) ** 2 ) );
        imageline( $c, $x0 + $fw, $y0 + $fw + $i, $x1 - $fw - 1, $y0 + $fw + $i, $col );
        imageline( $c, $x0 + $fw + $i, $y0 + $fw + $i, $x0 + $fw + $i, $y1 - $fw - 1, $col );
    }
    return monospace_mockup_finish( $c );
}

/** Scale the 2× drawing down to the final size, keeping the alpha */
function monospace_mockup_finish( $c ) {
    $o = monospace_mockup_canvas( MONOSPACE_MOCKUP_PX, MONOSPACE_MOCKUP_PX );
    imagealphablending( $o, false );
    imagecopyresampled( $o, $c, 0, 0, 0, 0, MONOSPACE_MOCKUP_PX, MONOSPACE_MOCKUP_PX, imagesx( $c ), imagesy( $c ) );
    return $o;
}

/* ============================================================
 * ON DEMAND - files in uploads/print-mockups/<post>/, drawn the first time they're asked for
 * ============================================================ */

/** The artwork as a GD image, from the smallest size that's still big enough (not the multi-MB original) */
function monospace_mockup_source( $image_id ) {
    $need = MONOSPACE_MOCKUP_FIT * MONOSPACE_MOCKUP_PX * MONOSPACE_MOCKUP_SS;
    $file = get_attached_file( $image_id );
    $meta = wp_get_attachment_metadata( $image_id );
    $best = null;
    foreach ( $meta['sizes'] ?? [] as $s ) {
        $long = max( $s['width'], $s['height'] );
        if ( $long >= $need && ( ! $best || $long < max( $best['width'], $best['height'] ) ) ) $best = $s;
    }
    if ( $best && file_exists( dirname( $file ) . '/' . $best['file'] ) ) $file = dirname( $file ) . '/' . $best['file'];
    if ( ! $file || ! file_exists( $file ) ) throw new Exception( 'Mockups: the image file is missing.' );
    $im = @imagecreatefromstring( file_get_contents( $file ) );
    if ( ! $im ) throw new Exception( 'Mockups: GD can’t read ' . basename( $file ) . '.' );
    return $im;
}

/**
 * What a print variation's mockup shows, or null (not a print variation of a linked artwork):
 * [ post, name (e.g. framed-red-oak-8x10), hash, frame, inches, image ]. The hash changes with the
 * print file (attachment and file time) and MONOSPACE_MOCKUP_VERSION, so a new file or a new look
 * gives new URLs - nothing is ever served stale.
 */
function monospace_print_mockup_spec( $variation_id ) {
    static $cache = [];
    if ( array_key_exists( $variation_id, $cache ) ) return $cache[ $variation_id ];
    $cache[ $variation_id ] = null;
    $variation = wc_get_product( $variation_id );
    if ( ! $variation || ! $variation->is_type( 'variation' ) || ! has_term( 'print', 'product_cat', $variation->get_parent_id() ) ) return null;
    $parent  = wc_get_product( $variation->get_parent_id() );
    $post_id = $parent ? (int) $parent->get_meta( '_related_post_id' ) : 0;
    $type    = $parent ? monospace_print_type_of( $parent ) : '';
    $v       = $post_id ? monospace_print_variant_for( $type, $variation ) : null;
    $image   = $v ? (int) monospace_prints_file_for( $post_id ) : 0;
    if ( ! $image ) return null;
    $file = get_attached_file( $image );
    $name = $type . '-' . $v['key'];
    $hash = substr( md5( implode( '|', [ MONOSPACE_MOCKUP_VERSION, $name, $image, $file && file_exists( $file ) ? filemtime( $file ) : 0 ] ) ), 0, 10 );
    return $cache[ $variation_id ] = [ 'post' => $post_id, 'name' => $name, 'hash' => $hash,
        'frame' => $variation->get_attributes()['pa_color'] ?? '', 'inches' => $v['inches'], 'image' => $image ];
}

/**
 * How big a print is next to the largest one sold, by outer size (frame included): 0-1. "See a preview"
 * shows the prints at these relative sizes (2026-10-19) - every mockup file is drawn to the same share of
 * its square, so without it a 5x7 looked as big as a framed 11x14.
 */
function monospace_print_mockup_scale( $variation_id ) {
    static $ref = null;
    $long = fn( $inches, $framed ) => max( $inches ) + ( $framed ? 2 * MONOSPACE_FRAME_IN : 0 );
    if ( $ref === null ) {
        $ref = 0;
        foreach ( monospace_print_templates() as $t ) {
            foreach ( $t['variants'] as $v ) $ref = max( $ref, $long( $v['inches'], isset( $v['attributes']['pa_color'] ) ) );
        }
    }
    $spec = monospace_print_mockup_spec( (int) $variation_id );
    return $spec && $ref ? round( min( 1, $long( $spec['inches'], $spec['frame'] !== '' ) / $ref ), 3 ) : 1;
}

/** Folder (path or URL) of an artwork's mockups */
function monospace_print_mockup_dir( $post_id, $url = false ) {
    $up = wp_upload_dir( null, false );
    return ( $url ? $up['baseurl'] : $up['basedir'] ) . '/' . MONOSPACE_MOCKUP_DIR . '/' . (int) $post_id;
}

/** A print variation's mockup URL - webp (transparent, the site) or jpg (on white: Meta's catalog) - or '' */
function monospace_print_mockup_url( $variation_id, $ext = 'webp' ) {
    $spec = monospace_print_mockup_spec( (int) $variation_id );
    return $spec ? monospace_print_mockup_dir( $spec['post'], true ) . "/{$spec['name']}-{$spec['hash']}.$ext" : '';
}

/**
 * Draw a mockup and save both files (webp + jpg on white); older files of the same print go.
 * Returns [ ext => path ]. $art: the artwork as GD image, when drawing several from one image.
 */
function monospace_print_mockup_make( $spec, $art = null ) {
    wp_raise_memory_limit( 'image' );
    $dir = monospace_print_mockup_dir( $spec['post'] );
    if ( ! wp_mkdir_p( $dir ) ) throw new Exception( 'Mockups: can’t create ' . $dir );
    $im   = monospace_print_mockup_render( $art ?: monospace_mockup_source( $spec['image'] ), $spec['inches'], $spec['frame'] );
    $flat = imagecreatetruecolor( imagesx( $im ), imagesy( $im ) );
    imagefill( $flat, 0, 0, imagecolorallocate( $flat, 255, 255, 255 ) );
    imagecopy( $flat, $im, 0, 0, 0, 0, imagesx( $im ), imagesy( $im ) );

    $base = "$dir/{$spec['name']}-{$spec['hash']}";
    $tmp  = $base . '.' . getmypid() . '.tmp';
    imagewebp( $im, $tmp, 86 );   rename( $tmp, "$base.webp" );
    imagejpeg( $flat, $tmp, 88 ); rename( $tmp, "$base.jpg" );
    foreach ( glob( "$dir/{$spec['name']}-*" ) ?: [] as $f ) {
        if ( strpos( $f, $base . '.' ) !== 0 ) @unlink( $f );
    }
    return [ 'webp' => "$base.webp", 'jpg' => "$base.jpg" ];
}

/** Draw every missing mockup of an artwork's prints now (the print job does this ahead of visitors). Returns how many. */
function monospace_print_mockups_warm( $post_id ) {
    $art = null; $made = 0;
    foreach ( monospace_print_templates() as $tpl ) {
        $product = wc_get_product( (int) get_post_meta( $post_id, $tpl['meta_key'], true ) );
        if ( ! $product ) continue;
        foreach ( $product->get_children() as $vid ) {
            $spec = monospace_print_mockup_spec( $vid );
            if ( ! $spec || file_exists( monospace_print_mockup_dir( $spec['post'] ) . "/{$spec['name']}-{$spec['hash']}.jpg" ) ) continue;
            $art = $art ?: monospace_mockup_source( $spec['image'] );
            monospace_print_mockup_make( $spec, $art );
            $made++;
        }
    }
    return $made;
}

/**
 * A request for a mockup file that isn't there yet reaches WordPress (the .htaccess rewrite): draw it,
 * save it, send it. Only names that match a real print variation's current hash are drawn.
 */
add_action( 'parse_request', function () {
    $path = (string) parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
    $base = (string) parse_url( wp_upload_dir( null, false )['baseurl'], PHP_URL_PATH ) . '/' . MONOSPACE_MOCKUP_DIR . '/';
    if ( strpos( $path, $base ) !== 0 || ! preg_match( '#^(\d+)/([a-z0-9-]+)-([0-9a-f]{10})\.(webp|jpg)$#', substr( $path, strlen( $base ) ), $m ) ) return;
    [ , $post_id, $name, $hash, $ext ] = $m;

    $spec = null;
    foreach ( monospace_print_templates() as $tpl ) {
        $product = wc_get_product( (int) get_post_meta( (int) $post_id, $tpl['meta_key'], true ) );
        foreach ( $product ? $product->get_children() : [] as $vid ) {
            $s = monospace_print_mockup_spec( $vid );
            if ( $s && $s['name'] === $name && $s['hash'] === $hash && $s['post'] === (int) $post_id ) { $spec = $s; break 2; }
        }
    }
    if ( ! $spec ) return; // WordPress's 404
    try {
        @set_time_limit( 60 );
        $file = monospace_print_mockup_make( $spec )[ $ext ];
    } catch ( Exception $e ) {
        return;
    }
    status_header( 200 );
    header( 'Content-Type: ' . ( $ext === 'jpg' ? 'image/jpeg' : 'image/webp' ) );
    header( 'Content-Length: ' . filesize( $file ) );
    header( 'Cache-Control: public, max-age=31536000, immutable' );
    readfile( $file );
    exit;
}, 0 );

/** Cart / mini cart: a print shows its mockup, not the painting */
add_filter( 'woocommerce_cart_item_thumbnail', function ( $html, $item ) {
    $url = ! empty( $item['variation_id'] ) ? monospace_print_mockup_url( $item['variation_id'] ) : '';
    if ( ! $url ) return $html;
    $alt = isset( $item['data'] ) ? $item['data']->get_name() : '';
    return '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" width="300" height="300" class="attachment-woocommerce_thumbnail size-woocommerce_thumbnail" loading="lazy" decoding="async">';
}, 10, 2 );

/**
 * Meta's catalog (Facebook for WooCommerce): each print variation's image is its mockup on white, set
 * as the plugin's own "custom image" (meta fb_product_image + image source custom). A changed URL
 * (new print file / new look) is saved, which makes the plugin sync the variation again.
 * Returns how many variations changed.
 */
function monospace_print_mockups_to_meta( $post_id ) {
    $changed = 0;
    foreach ( monospace_print_templates() as $tpl ) {
        $product = wc_get_product( (int) get_post_meta( $post_id, $tpl['meta_key'], true ) );
        foreach ( $product ? $product->get_children() : [] as $vid ) {
            $url = monospace_print_mockup_url( $vid, 'jpg' );
            $variation = wc_get_product( $vid );
            if ( ! $url || ! $variation || ( $variation->get_meta( 'fb_product_image' ) === $url && $variation->get_meta( '_wc_facebook_product_image_source' ) === 'custom' ) ) continue;
            $variation->update_meta_data( 'fb_product_image', $url );
            $variation->update_meta_data( '_wc_facebook_product_image_source', 'custom' );
            $variation->save();
            $changed++;
        }
    }
    return $changed;
}

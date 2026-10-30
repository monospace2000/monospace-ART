<?php
/**
 * Design module: Print preview (2026-10-19)
 *
 * How the price card's "See a preview" lightbox looks (commerce/print-order-shortcode.php opens it):
 * what's behind the mockup - Blur (the page itself, blurred), Image (a picture from the media
 * library, filling the screen or tiled), Pattern (the site's own background or one of the paper
 * textures) or Solid color - plus a tint over it, the spotlight, the mockup's size and shadow, a
 * caption (frame and size) and how it opens. A live preview in the settings shows the choice.
 * Shown in Appearance > Look through the design-module registry.
 *
 * Front end: the site's own lightbox (assets/scripts/ms-lightbox.js, skin ms-pp; opened by
 * print-order-shortcode.php's footer script; GLightbox until 2026-10-19). monospace_print_preview_css() = its CSS:
 * variables from the settings + the rules of the chosen background (monospace_print_preview_mode_css(),
 * the same rules the settings preview uses for every background). Opening / caption for the script:
 * monospace_print_preview_js_options().
 *
 * Option: monospace_print_preview (array, see monospace_print_preview_defaults()).
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'monospace_design_modules', function ( $modules ) {
    if ( function_exists( 'monospace_feature' ) && ! monospace_feature( 'prints' ) ) return $modules; // the Prints plugin is off
    $modules['print-preview'] = [
        'title'       => 'Print preview',
        'description' => 'The “See a preview” lightbox on the price card’s print tabs: what’s behind the print, and how it shows.',
        'group'       => 'look',
        'order'       => 20,
        'summary'     => function () {
            $s = monospace_print_preview_settings();
            return [ 'blur' => 'Blurred page, ' . $s['blur'] . 'px', 'image' => 'Image', 'pattern' => 'Pattern: ' . monospace_print_preview_patterns()[ $s['pattern'] ][0], 'solid' => 'Solid color' ][ $s['mode'] ];
        },
        'render'      => 'monospace_render_print_preview_settings',
        'save'        => 'monospace_save_print_preview_settings',
    ];
    return $modules;
} );

/** Defaults = the look of 2026-10-19 (blurred page, spotlight on top) */
function monospace_print_preview_defaults() {
    return [
        'mode'          => 'blur',     // blur | image | pattern | solid
        'blur'          => 18,         // px
        'image'         => 0,          // attachment ID
        'image_fit'     => 'cover',    // cover | tile
        'pattern'       => 'site',     // site | canvas | paper | watercolor
        'color'         => '#f1ede7',  // solid color
        'color_opacity' => 100,        // %
        'tint'          => '#000000',
        'tint_strength' => 0,          // %
        'spotlight'     => 1,
        'size'          => 92,         // % of the screen height
        'shadow'        => 'none',     // none | soft | strong
        'caption'       => 0,
        'open'          => 'none',     // none | fade | zoom
        'relative'      => 1,          // prints at relative sizes (a 5x7 smaller than a framed 11x14)
    ];
}

/** The patterns: label, image URL ('' = the site's own background, from the Appearance) */
function monospace_print_preview_patterns() {
    $img = get_template_directory_uri() . '/assets/images/';
    return [
        'site'       => [ 'Same as the site (Appearance)', '' ],
        'canvas'     => [ 'Canvas',                        $img . 'canvas.jpeg' ],
        'paper'      => [ 'Paper',                         $img . 'paper.jpeg' ],
        'watercolor' => [ 'Watercolor paper',             content_url( 'uploads/paper2.jpg' ) ],
    ];
}

/** Current settings, cleaned */
function monospace_print_preview_settings() {
    $d = monospace_print_preview_defaults();
    $s = get_option( 'monospace_print_preview', [] );
    $s = is_array( $s ) ? array_merge( $d, array_intersect_key( $s, $d ) ) : $d;
    $pick  = fn( $v, $allowed, $def ) => in_array( $v, $allowed, true ) ? $v : $def;
    $range = fn( $v, $min, $max ) => max( $min, min( $max, (int) $v ) );
    $color = fn( $v, $def ) => preg_match( '/^#[0-9a-f]{6}$/i', (string) $v ) ? strtolower( $v ) : $def;
    return [
        'mode'          => $pick( $s['mode'], [ 'blur', 'image', 'pattern', 'solid' ], $d['mode'] ),
        'blur'          => $range( $s['blur'], 0, 40 ),
        'image'         => (int) $s['image'],
        'image_fit'     => $pick( $s['image_fit'], [ 'cover', 'tile' ], $d['image_fit'] ),
        'pattern'       => $pick( $s['pattern'], array_keys( monospace_print_preview_patterns() ), $d['pattern'] ),
        'color'         => $color( $s['color'], $d['color'] ),
        'color_opacity' => $range( $s['color_opacity'], 0, 100 ),
        'tint'          => $color( $s['tint'], $d['tint'] ),
        'tint_strength' => $range( $s['tint_strength'], 0, 90 ),
        'spotlight'     => $s['spotlight'] ? 1 : 0,
        'size'          => $range( $s['size'], 50, 98 ),
        'shadow'        => $pick( $s['shadow'], [ 'none', 'soft', 'strong' ], $d['shadow'] ),
        'caption'       => $s['caption'] ? 1 : 0,
        'open'          => $pick( $s['open'], [ 'none', 'fade', 'zoom' ], $d['open'] ),
        'relative'      => $s['relative'] ? 1 : 0,
    ];
}

/** '#rrggbb' + % -> rgba() */
function monospace_print_preview_rgba( $hex, $percent ) {
    [ $r, $g, $b ] = sscanf( $hex, '#%02x%02x%02x' );
    return sprintf( 'rgba(%d, %d, %d, %s)', $r, $g, $b, round( $percent / 100, 2 ) );
}

/** The shadow under the mockup (drop-shadow follows the frame's shape - the file is transparent) */
function monospace_print_preview_shadows() {
    return [
        'none'   => [ 'None',   'none' ],
        'soft'   => [ 'Soft',   'drop-shadow(0 6px 14px rgb(var(--ms-shadow-rgb, 0 0 0) / calc(0.25 * var(--ms-shadow-k, 1))))' ], // Look > Shadows' color
        'strong' => [ 'Strong', 'drop-shadow(0 14px 30px rgb(var(--ms-shadow-rgb, 0 0 0) / calc(0.45 * var(--ms-shadow-k, 1))))' ],
    ];
}

/** CSS variables for the settings (front end and the settings preview) */
function monospace_print_preview_vars( $s ) {
    $image = $s['image'] ? wp_get_attachment_image_url( $s['image'], 'full' ) : '';
    $pats  = monospace_print_preview_patterns();
    $pat   = $pats[ $s['pattern'] ][1];
    if ( $s['pattern'] === 'site' ) { // the Appearance's texture (none for the plain ones: their --page-color shows)
        $bg  = function_exists( 'monospace_appearance' ) ? monospace_appearance()[1] : 'canvas';
        $pat = $pats[ $bg ][1] ?? '';
    }
    return [
        '--pp-blur'     => $s['blur'] . 'px',
        '--pp-tint'     => monospace_print_preview_rgba( $s['tint'], $s['tint_strength'] ),
        '--pp-color'    => monospace_print_preview_rgba( $s['color'], $s['color_opacity'] ),
        '--pp-image'    => $image ? 'url("' . esc_url_raw( $image ) . '")' : 'none',
        '--pp-img-size' => $s['image_fit'] === 'tile' ? 'auto' : 'cover',
        '--pp-img-rep'  => $s['image_fit'] === 'tile' ? 'repeat' : 'no-repeat',
        '--pp-pattern'  => $pat ? 'url("' . esc_url_raw( $pat ) . '")' : 'none',
        '--pp-shadow'   => monospace_print_preview_shadows()[ $s['shadow'] ][1],
        '--pp-size'     => $s['size'] . 'vh',
    ];
}

/**
 * The rules of one background. $box = the lightbox (or the settings preview), $layer = the layer
 * over the page in it. --pp-spot is the spotlight (or a see-through gradient when it's off).
 * $blur: a number, not a variable, on the site - Safari ignores var() inside -webkit-backdrop-filter
 * (the blur showed as none on iPhones).
 */
function monospace_print_preview_mode_css( $mode, $pattern, $box, $layer, $blur = 'var(--pp-blur)' ) {
    $tint = 'linear-gradient(var(--pp-tint), var(--pp-tint))';
    switch ( $mode ) {
        case 'image':
            return "$layer { background: var(--pp-spot), $tint, var(--pp-image) center / var(--pp-img-size) var(--pp-img-rep), #000; }";
        case 'pattern':
            return "$layer { background: var(--pp-spot), $tint, var(--pp-pattern) repeat, var(--page-color, #ddd); }";
        case 'solid':
            return "$layer { background: var(--pp-spot), linear-gradient(var(--pp-color), var(--pp-color)); }";
        default: // blur
            return "$layer { background: var(--pp-spot), $tint; -webkit-backdrop-filter: blur($blur) saturate(1.1); backdrop-filter: blur($blur) saturate(1.1); }";
    }
}

/** Front end: the lightbox's CSS (print-order-shortcode.php prints it in the footer, with its script) */
function monospace_print_preview_css() {
    $s    = monospace_print_preview_settings();
    $vars = '';
    foreach ( monospace_print_preview_vars( $s ) as $k => $v ) $vars .= "$k: $v; ";
    $spot = $s['spotlight']
        ? 'radial-gradient(circle at 50vw 45vh, var(--gradient-foreground) var(--gradient-start), var(--gradient-background) var(--gradient-radius))'
        : 'linear-gradient(transparent, transparent)';
    // The lightbox's base CSS is in ms-lightbox.js; this is the print preview's skin (.ms-lb.ms-pp)
    return ".ms-lb.ms-pp { $vars--pp-spot: $spot; color: inherit; }\n"
         . monospace_print_preview_mode_css( $s['mode'], $s['pattern'], '.ms-lb.ms-pp', '.ms-lb.ms-pp .ms-lb-layer', $s['blur'] . 'px' ) . "\n"
         . ".ms-lb.ms-pp .ms-lb-slide img { width: min(1200px, calc(min(var(--pp-size), 94vw) * var(--lb-scale, 1))); max-width: none; max-height: none; filter: var(--pp-shadow); }\n"
         // The site's menu bar stays on top, sharp and usable, whatever the background (owner, 2026-10-21): above the
         // preview, so its blur / image / color is only behind it (the lightbox is z-index 999999)
         . "html.ms-lb-open:has(.ms-lb.ms-pp) :is(.primary-menu-bar, .mobile-menu-overlay) { z-index: 1000000; }\n"; // + the phone menu (menu.js)
}

/** Front end: the lightbox script's settings */
function monospace_print_preview_js_options() {
    $s = monospace_print_preview_settings();
    // Pinch-zoom: on the blurred page only the print zooms; an image / pattern / color enlarges with it
    return [ 'open' => $s['open'], 'caption' => (bool) $s['caption'], 'relative' => (bool) $s['relative'], 'zoomBackground' => $s['mode'] !== 'blur' ];
}

/** A print to show in the settings preview: the newest artwork with a framed (else unframed) print */
function monospace_print_preview_sample() {
    foreach ( [ '_framed_print_product_id', '_print_product_id' ] as $key ) {
        $posts = get_posts( [ 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 5, 'meta_key' => $key, 'meta_compare' => '!=', 'meta_value' => '' ] );
        foreach ( $posts as $post ) {
            $product = function_exists( 'wc_get_product' ) ? wc_get_product( (int) get_post_meta( $post->ID, $key, true ) ) : null;
            if ( ! $product ) continue;
            foreach ( $product->get_children() as $vid ) {
                $url = function_exists( 'monospace_print_mockup_url' ) ? monospace_print_mockup_url( $vid ) : '';
                if ( $url ) return [ 'mockup' => $url, 'art' => get_the_post_thumbnail_url( $post, 'medium_large' ), 'link' => get_permalink( $post ), 'title' => get_the_title( $post ),
                    'scale' => function_exists( 'monospace_print_mockup_scale' ) ? monospace_print_mockup_scale( $vid ) : 1 ];
            }
        }
    }
    return null;
}

// The media picker (Image background) - Layout Manager > Appearance & Effects until 2026-10-22, now Appearance > Look (look.php loads the media library)
add_action( 'admin_enqueue_scripts', function () {
    if ( ( $_GET['page'] ?? '' ) === 'monospace-layout-manager' && ( $_GET['tab'] ?? '' ) === 'effects' ) wp_enqueue_media();
} );

function monospace_render_print_preview_settings() {
    $s        = monospace_print_preview_settings();
    $sample   = monospace_print_preview_sample();
    $patterns = monospace_print_preview_patterns();
    $img_url  = $s['image'] ? wp_get_attachment_image_url( $s['image'], 'medium' ) : '';
    $site_bg  = function_exists( 'monospace_appearance' ) ? monospace_appearance()[1] : 'canvas';
    $site_pat = $patterns[ $site_bg ][1] ?? '';
    $show     = fn( $modes ) => ' data-pp-show="' . esc_attr( $modes ) . '"';

    // The preview: a stand-in page (site background, the artwork, lines of text) with the layer over it
    $demo_rules = '';
    foreach ( [ 'blur', 'image', 'solid' ] as $m ) $demo_rules .= monospace_print_preview_mode_css( $m, '', ".pp-demo[data-mode=\"$m\"]", ".pp-demo[data-mode=\"$m\"] .pp-layer" ) . "\n";
    $demo_rules .= monospace_print_preview_mode_css( 'pattern', 'x', '.pp-demo[data-mode="pattern"]', '.pp-demo[data-mode="pattern"] .pp-layer' ) . "\n";
    ?>
    <style>
        .pp-wrap { display: flex; gap: 24px; flex-wrap: wrap; align-items: flex-start; }
        .pp-wrap .form-table { flex: 1 1 520px; margin-top: 0; }
        .pp-side { flex: 0 1 380px; position: sticky; top: 46px; }
        .pp-demo { position: relative; aspect-ratio: 4 / 3; border: 1px solid #ccc; border-radius: 4px; overflow: hidden; container-type: size;
            --gradient-foreground: rgba(255, 255, 255, 0.8); --gradient-background: rgba(83, 37, 0, 0.2);
            --pp-spot: radial-gradient(circle at 50% 45%, var(--gradient-foreground) 20%, var(--gradient-background) 75%); }
        .pp-demo[data-spot="0"] { --pp-spot: linear-gradient(transparent, transparent); }
        .pp-page { position: absolute; inset: 0; padding: 7% 12%; background: <?php echo $site_pat ? 'url(' . esc_url( $site_pat ) . ') repeat' : '#e8e2d8'; ?>; }
        .pp-page img { display: block; width: 62%; margin: 0 auto 6%; box-shadow: 0 2px 6px rgba(0,0,0,.3); }
        .pp-page i { display: block; height: 7px; margin: 0 0 7px; background: rgba(0,0,0,.35); border-radius: 3px; }
        .pp-page i:nth-child(odd) { width: 80%; }
        .pp-layer { position: absolute; inset: 0; }
        .pp-print { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .pp-print img { width: calc(min(var(--pp-size-n) * 1cqh, 92cqw) * var(--pp-rel, 1)); filter: var(--pp-shadow); }
        .pp-print span { font-size: 11px; margin-top: 4px; }
        .pp-row-hidden { display: none; }
        .pp-image-pick img { max-width: 160px; max-height: 90px; display: block; margin-bottom: 6px; border: 1px solid #ccc; }
        <?php echo $demo_rules; ?>
    </style>
    <div class="pp-wrap" id="pp-settings">
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">Background</th>
                <td>
                    <?php foreach ( [ 'blur' => 'Blur - the page itself, blurred', 'image' => 'Image - a picture from the media library', 'pattern' => 'Pattern - a paper or canvas texture', 'solid' => 'Solid color' ] as $k => $label ) : ?>
                        <label style="display:block;margin-bottom:4px"><input type="radio" name="pp[mode]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['mode'], $k ); ?>> <?php echo esc_html( $label ); ?></label>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr<?php echo $show( 'blur' ); ?>>
                <th scope="row"><label for="pp-blur">Blur amount</label></th>
                <td><input type="range" id="pp-blur" name="pp[blur]" min="0" max="40" value="<?php echo esc_attr( $s['blur'] ); ?>"> <output><?php echo esc_html( $s['blur'] ); ?></output> px
                    <p class="description">Browsers that can’t blur (very old ones) show the page with just the tint.</p></td>
            </tr>
            <tr<?php echo $show( 'image' ); ?>>
                <th scope="row">Image</th>
                <td class="pp-image-pick">
                    <input type="hidden" name="pp[image]" id="pp-image" value="<?php echo esc_attr( $s['image'] ?: '' ); ?>" data-full="<?php echo esc_attr( $s['image'] ? wp_get_attachment_image_url( $s['image'], 'full' ) : '' ); ?>">
                    <img id="pp-image-thumb" src="<?php echo esc_url( $img_url ); ?>" alt=""<?php echo $img_url ? '' : ' hidden'; ?>>
                    <button type="button" class="button" id="pp-image-choose">Choose image…</button>
                    <button type="button" class="button-link" id="pp-image-clear"<?php echo $img_url ? '' : ' hidden'; ?>>Remove</button>
                    <p style="margin-top:8px">
                        <label style="margin-right:18px"><input type="radio" name="pp[image_fit]" value="cover" <?php checked( $s['image_fit'], 'cover' ); ?>> Fill the screen</label>
                        <label><input type="radio" name="pp[image_fit]" value="tile" <?php checked( $s['image_fit'], 'tile' ); ?>> Tile (repeat at its own size)</label>
                    </p>
                </td>
            </tr>
            <tr<?php echo $show( 'pattern' ); ?>>
                <th scope="row"><label for="pp-pattern">Pattern</label></th>
                <td><select name="pp[pattern]" id="pp-pattern">
                    <?php foreach ( $patterns as $k => [ $label, $url ] ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>" data-url="<?php echo esc_attr( $url ?: $site_pat ); ?>" <?php selected( $s['pattern'], $k ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select></td>
            </tr>
            <tr<?php echo $show( 'solid' ); ?>>
                <th scope="row"><label for="pp-color">Color</label></th>
                <td><input type="color" id="pp-color" name="pp[color]" value="<?php echo esc_attr( $s['color'] ); ?>">
                    <label style="margin-left:14px">Opacity <input type="range" name="pp[color_opacity]" min="0" max="100" value="<?php echo esc_attr( $s['color_opacity'] ); ?>"> <output><?php echo esc_html( $s['color_opacity'] ); ?></output>%</label>
                    <p class="description">Below 100% the page shows through.</p></td>
            </tr>
            <tr<?php echo $show( 'blur image pattern' ); ?>>
                <th scope="row"><label for="pp-tint">Tint</label></th>
                <td><input type="color" id="pp-tint" name="pp[tint]" value="<?php echo esc_attr( $s['tint'] ); ?>">
                    <label style="margin-left:14px">Strength <input type="range" name="pp[tint_strength]" min="0" max="90" value="<?php echo esc_attr( $s['tint_strength'] ); ?>"> <output><?php echo esc_html( $s['tint_strength'] ); ?></output>%</label>
                    <p class="description">A color wash over the background - darker (black) or lighter (white) makes the print stand out.</p></td>
            </tr>
            <tr>
                <th scope="row">Spotlight</th>
                <td><label><input type="hidden" name="pp[spotlight]" value="0"><input type="checkbox" name="pp[spotlight]" value="1" <?php checked( $s['spotlight'] ); ?>> The site’s spotlight on top (light in the middle, darker edges - its colors follow the Appearance)</label></td>
            </tr>
            <tr>
                <th scope="row"><label for="pp-size">Print size</label></th>
                <td><input type="range" id="pp-size" name="pp[size]" min="50" max="98" value="<?php echo esc_attr( $s['size'] ); ?>"> <output><?php echo esc_html( $s['size'] ); ?></output>% of the screen height
                    <p style="margin-top:8px"><label><input type="hidden" name="pp[relative]" value="0"><input type="checkbox" name="pp[relative]" value="1" <?php checked( $s['relative'] ); ?>> Relative sizes - the largest print (a framed 11" × 14") gets this size, smaller ones in proportion, as they'd look side by side on a wall</label></p>
                    <?php if ( function_exists( 'monospace_pc_standalone_settings' ) ) : // the standalone price card's picture: its own choice (Core) ?>
                    <p class="description">The standalone price card’s picture shows print previews too - relative sizes or all the same:
                        <a href="<?php echo esc_url( admin_url( 'themes.php?page=monospace-price-card' ) ); ?>">Appearance &gt; Price card</a> &gt; Standalone card &gt; Print sizes
                        (now: <?php echo monospace_pc_standalone_settings()['sizes'] === 'relative' ? 'relative' : 'all the same'; ?>).</p>
                    <?php endif; ?>
                    <p class="description">Off: every print fills this much of the screen.</p></td>
            </tr>
            <tr>
                <th scope="row">Shadow</th>
                <td><?php foreach ( monospace_print_preview_shadows() as $k => [ $label ] ) : ?>
                    <label style="margin-right:18px"><input type="radio" name="pp[shadow]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['shadow'], $k ); ?>> <?php echo esc_html( $label ); ?></label>
                <?php endforeach; ?>
                    <p class="description">Under the frame or paper, following its shape.</p></td>
            </tr>
            <tr>
                <th scope="row">Caption</th>
                <td><label><input type="hidden" name="pp[caption]" value="0"><input type="checkbox" name="pp[caption]" value="1" <?php checked( $s['caption'] ); ?>> Show the frame and size under the print (e.g. “Red Oak frame, 8" × 10"”)</label></td>
            </tr>
            <tr>
                <th scope="row">Opening</th>
                <td><?php foreach ( [ 'none' => 'Instant', 'fade' => 'Fade in', 'zoom' => 'Zoom in' ] as $k => $label ) : ?>
                    <label style="margin-right:18px"><input type="radio" name="pp[open]" value="<?php echo esc_attr( $k ); ?>" <?php checked( $s['open'], $k ); ?>> <?php echo esc_html( $label ); ?></label>
                <?php endforeach; ?></td>
            </tr>
        </table>
        <div class="pp-side">
            <div class="pp-demo" id="pp-demo">
                <div class="pp-page">
                    <?php if ( $sample && $sample['art'] ) : ?><img src="<?php echo esc_url( $sample['art'] ); ?>" alt=""><?php endif; ?>
                    <i></i><i></i><i></i><i></i>
                </div>
                <div class="pp-layer"></div>
                <div class="pp-print">
                    <?php if ( $sample ) : ?>
                        <img src="<?php echo esc_url( $sample['mockup'] ); ?>" alt="">
                    <?php else : ?>
                        <img alt="" src="data:image/svg+xml,<?php echo rawurlencode( '<svg xmlns="http://www.w3.org/2000/svg" width="80" height="100"><rect width="80" height="100" fill="#222"/><rect x="8" y="8" width="64" height="84" fill="#9ab"/></svg>' ); ?>">
                    <?php endif; ?>
                    <span>Black frame, 8" × 10"</span>
                </div>
            </div>
            <p class="description">Preview, roughly. <?php if ( $sample ) : ?>See the real thing (after saving) on <a href="<?php echo esc_url( $sample['link'] ); ?>" target="_blank"><?php echo esc_html( $sample['title'] ); ?></a> → a print tab → See a preview.<?php endif; ?></p>
        </div>
    </div>
    <script>
    (function () {
        var root = document.getElementById('pp-settings'), demo = document.getElementById('pp-demo');
        var val = function (name) {
            var el = root.querySelector('[name="pp[' + name + ']"]:checked') || root.querySelector('[name="pp[' + name + ']"]:not([type=hidden]):not([type=radio]):not([type=checkbox])');
            if (!el && root.querySelector('[name="pp[' + name + ']"][type=checkbox]')) return 0;
            return el ? el.value : '';
        };
        var rgba = function (hex, pct) {
            var n = parseInt(hex.slice(1), 16);
            return 'rgba(' + (n >> 16 & 255) + ',' + (n >> 8 & 255) + ',' + (n & 255) + ',' + (pct / 100) + ')';
        };
        var shadows = <?php echo wp_json_encode( array_map( fn( $x ) => $x[1], monospace_print_preview_shadows() ) ); ?>;
        function update() {
            var mode = val('mode'), img = document.getElementById('pp-image').dataset.full, pat = document.getElementById('pp-pattern');
            demo.dataset.mode = mode;
            demo.dataset.spot = val('spotlight') ? '1' : '0';
            // Safari ignores var() inside -webkit-backdrop-filter: the blur goes on the layer as a number
            // (the preview is about a third of a screen, so a third of the blur)
            var layer = demo.querySelector('.pp-layer'), bf = mode === 'blur' ? 'blur(' + Math.round(val('blur') / 3) + 'px) saturate(1.1)' : '';
            layer.style.webkitBackdropFilter = bf; layer.style.backdropFilter = bf;
            demo.style.setProperty('--pp-tint', rgba(val('tint'), val('tint_strength')));
            demo.style.setProperty('--pp-color', rgba(val('color'), val('color_opacity')));
            demo.style.setProperty('--pp-image', img ? 'url("' + img + '")' : 'none');
            demo.style.setProperty('--pp-img-size', val('image_fit') === 'tile' ? '33%' : 'cover');
            demo.style.setProperty('--pp-img-rep', val('image_fit') === 'tile' ? 'repeat' : 'no-repeat');
            var p = pat.options[pat.selectedIndex].dataset.url;
            demo.style.setProperty('--pp-pattern', p ? 'url("' + p + '")' : 'none');
            demo.style.setProperty('--pp-shadow', shadows[val('shadow')] || 'none');
            demo.style.setProperty('--pp-size-n', val('size'));
            demo.style.setProperty('--pp-rel', val('relative') ? <?php echo wp_json_encode( $sample['scale'] ?? 1 ); ?> : 1);
            demo.querySelector('.pp-print span').hidden = !val('caption');
            root.querySelectorAll('[data-pp-show]').forEach(function (tr) {
                tr.classList.toggle('pp-row-hidden', tr.dataset.ppShow.split(' ').indexOf(mode) < 0);
            });
            root.querySelectorAll('input[type=range]').forEach(function (r) { var o = r.parentNode.querySelector('output'); if (o) o.textContent = r.value; });
        }
        root.addEventListener('input', update);
        root.addEventListener('change', update);
        update();

        // Image: the media library
        var frame;
        document.getElementById('pp-image-choose').addEventListener('click', function () {
            if (!window.wp || !wp.media) return;
            frame = frame || wp.media({ title: 'Background for the print preview', library: { type: 'image' }, multiple: false, button: { text: 'Use this image' } });
            frame.off('select').on('select', function () {
                var a = frame.state().get('selection').first().toJSON(), input = document.getElementById('pp-image'), thumb = document.getElementById('pp-image-thumb');
                input.value = a.id; input.dataset.full = a.url;
                thumb.src = (a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url); thumb.hidden = false;
                document.getElementById('pp-image-clear').hidden = false;
                update();
            });
            frame.open();
        });
        document.getElementById('pp-image-clear').addEventListener('click', function () {
            var input = document.getElementById('pp-image');
            input.value = ''; input.dataset.full = '';
            document.getElementById('pp-image-thumb').hidden = true; this.hidden = true;
            update();
        });
    })();
    </script>
    <?php
}

function monospace_save_print_preview_settings() {
    if ( ! isset( $_POST['pp'] ) || ! is_array( $_POST['pp'] ) ) return;
    $in = wp_unslash( $_POST['pp'] );
    update_option( 'monospace_print_preview', array_intersect_key( $in, monospace_print_preview_defaults() ) );
    // Cleaned on read (monospace_print_preview_settings()); store the cleaned values too
    update_option( 'monospace_print_preview', monospace_print_preview_settings() );
}

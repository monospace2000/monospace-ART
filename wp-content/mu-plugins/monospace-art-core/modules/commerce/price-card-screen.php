<?php
/**
 * Appearance > Price card - the price card's own screen (2026-10-21, owner: "Price Card should have its own Look screen
 * with all the options including shortcode reference").
 *
 * Left: a live preview - a chosen artwork's card, as [price_card] or [price_card_standalone], computer or phone width -
 * redrawn from the form as it changes (nothing saved until Save). Right: panels (the admin UI style, admin-ui.php):
 *   Price card            artwork pages / phones / Opens on / archive & feed (price-card-presets.php's fields and save)
 *   Standalone card       its defaults (price-card-standalone.php's fields and save)
 *   Elsewhere             what's set in other places: per artwork, per category, Cards view on category pages
 *   Shortcode reference   both shortcodes, every attribute, examples to copy
 * Under the preview (2026-10-21, owner: "copy the shortcode with all relevant settings for the current selection"): the
 * shortcode for what's previewed - the artwork by its SKU number, every setting from the form spelled out (unsaved changes
 * too, so the copied card keeps this look whatever the defaults become) - and a Copy button.
 * The preview: the site's own page head and foot around one card (?ms_pc_preview=1, for those who can edit the look),
 * the settings it shows passed in the address. The Look screen and the Layout Manager keep a short panel that links here.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', function () {
    add_submenu_page( 'themes.php', 'Price card', 'Price card', 'edit_theme_options', 'monospace-price-card', 'monospace_price_card_screen' );
} );

// Saving: the two settings groups' own save functions
add_action( 'admin_post_monospace_price_card_screen', function () {
    if ( ! current_user_can( 'edit_theme_options' ) ) wp_die( 'Not allowed.' );
    check_admin_referer( 'monospace_price_card_screen' );
    monospace_save_price_card_settings();
    monospace_save_pc_standalone_settings();
    if ( function_exists( 'sg_cachepress_purge_cache' ) ) sg_cachepress_purge_cache();
    wp_safe_redirect( admin_url( 'themes.php?page=monospace-price-card&updated=1' ) );
    exit;
} );

/* ---------- The preview page ---------- */

add_action( 'template_redirect', function () {
    if ( empty( $_GET['ms_pc_preview'] ) || ! current_user_can( 'edit_theme_options' ) ) return;
    $kind = ( $_GET['kind'] ?? '' ) === 'standalone' ? 'price_card_standalone' : 'price_card';
    $allowed = $kind === 'price_card' ? [ 'style', 'tab' ] : [ 'layout', 'title', 'image', 'preview', 'link', 'sizes', 'style', 'tab' ];
    $atts = 'post="' . absint( $_GET['post'] ?? 0 ) . '"';
    foreach ( $allowed as $k ) {
        $v = sanitize_text_field( wp_unslash( $_GET[ $k ] ?? '' ) );
        if ( $v !== '' ) $atts .= ' ' . $k . '="' . esc_attr( str_replace( '"', '', $v ) ) . '"';
    }
    add_filter( 'show_admin_bar', '__return_false' );
    nocache_headers(); ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head><meta charset="<?php bloginfo( 'charset' ); ?>"><meta name="viewport" content="width=device-width, initial-scale=1"><?php wp_head(); ?>
<style>body{margin:0;padding:24px 16px}.ms-pc-preview{max-width:1000px;margin:0 auto}</style></head>
<body <?php body_class( 'ms-pc-preview-page' ); ?>>
<main class="ms-pc-preview"><?php echo do_shortcode( '[' . $kind . ' ' . $atts . ']' ) ?: '<p>No card for this artwork.</p>'; ?></main>
<?php wp_footer(); ?>
</body>
</html>
    <?php
    exit;
} );

/* ---------- The screen ---------- */

/** Artworks to preview: the newest with a product, prints first (they show the most) */
function monospace_price_card_screen_artworks() {
    $ids  = get_posts( [ 'numberposts' => 40, 'post_status' => 'publish', 'meta_key' => '_qp_product_id', 'fields' => 'ids' ] );
    $list = [];
    foreach ( $ids as $id ) {
        $prints = get_post_meta( $id, '_framed_print_product_id', true ) || get_post_meta( $id, '_print_product_id', true );
        $sku    = (string) get_post_meta( (int) get_post_meta( $id, '_qp_product_id', true ), '_sku', true );
        $num    = $sku !== '' ? preg_replace( '/^.*?0*(\d+)$/', '$1', $sku ) : '';
        $list[] = [ $id, get_the_title( $id ) . ( $num !== '' ? ' · ' . $num : '' ) . ( $prints ? ' · prints' : '' ), $prints, $num ];
    }
    usort( $list, fn( $a, $b ) => (int) $b[2] <=> (int) $a[2] );
    return $list;
}

function monospace_price_card_screen() {
    if ( ! current_user_can( 'edit_theme_options' ) ) return;
    $artworks = monospace_price_card_screen_artworks();
    if ( function_exists( 'monospace_lm_area_assets' ) ) monospace_lm_area_assets();
    $panel = function ( $id, $title, $sub, $chip, $open = true ) {
        echo '<details class="lm-panel" data-panel="' . esc_attr( $id ) . '"' . ( $open ? ' open' : '' ) . '><summary><span class="lm-t">' . esc_html( $title ) . '</span>'
           . ( $chip !== '' ? ' <span class="lm-chip is-on">' . esc_html( $chip ) . '</span>' : '' ) . '<span class="lm-sub">' . esc_html( $sub ) . '</span></summary><div class="lm-body">';
    };
    $end = function () { echo '</div></details>'; };
    $pc  = function_exists( 'monospace_design_modules' ) ? monospace_design_modules() : [];
    $sum = fn( $k ) => isset( $pc[ $k ]['summary'] ) && is_callable( $pc[ $k ]['summary'] ) ? (string) call_user_func( $pc[ $k ]['summary'] ) : '';
    ?>
    <div class="wrap ms-pcs">
        <h1>Price card</h1>
        <p class="lm-where">The card that sells an artwork - on its page, under it in the archive and the feed, and anywhere a shortcode puts it.
            The preview changes as you go; nothing changes on the site until you save.</p>
        <?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Saved - the site shows it now.</p></div><?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ms-pcs-form" class="lm-areas">
            <input type="hidden" name="action" value="monospace_price_card_screen">
            <?php wp_nonce_field( 'monospace_price_card_screen' ); ?>
            <div class="ms-pcs-grid">
                <div class="ms-pcs-preview">
                    <div class="ms-pcs-bar">
                        <select id="ms-pcs-art" aria-label="Artwork to preview">
                            <?php foreach ( $artworks as [ $id, $label, , $num ] ) : ?><option value="<?php echo (int) $id; ?>" data-sku="<?php echo esc_attr( $num ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
                        </select>
                        <span class="button-group">
                            <button type="button" class="button button-primary" data-kind="card">Price card</button><button type="button" class="button" data-kind="standalone">Standalone</button>
                        </span>
                        <span class="button-group">
                            <button type="button" class="button button-primary" data-w="1040">Computer</button><button type="button" class="button" data-w="390">Phone</button>
                        </span>
                    </div>
                    <div class="ms-pcs-stage"><div class="ms-pcs-frame"><iframe id="ms-pcs-iframe" title="Preview"></iframe></div></div>
                    <p class="ms-pcs-code"><span class="ms-pcs-code-label">Shortcode for this:</span> <code id="ms-pcs-shortcode"></code>
                        <button type="button" class="button button-small" id="ms-pcs-copy-code">Copy</button></p>
                    <p class="description">The card as it shows on the site - try its tabs and options. “Price card” previews the artwork-page preset (or the phone one at phone width).
                        The shortcode spells out every setting shown, so a card you paste keeps this look even when the defaults change.</p>
                </div>

                <div class="lm-panels">
                    <?php $panel( 'card', 'Price card', 'Artwork pages, phones, the tab it opens on, the archive and feed', $sum( 'price-card' ) ); ?>
                        <?php monospace_render_price_card_settings(); ?>
                    <?php $end(); ?>

                    <?php $panel( 'standalone', 'Standalone card', 'The card with the artwork’s picture in it - [price_card_standalone], and the price card view on category pages', $sum( 'price-card-standalone' ) ); ?>
                        <?php monospace_render_pc_standalone_settings(); ?>
                    <?php $end(); ?>

                    <?php $panel( 'elsewhere', 'Set elsewhere', 'Per artwork, per category, and the price card view', '', false ); ?>
                        <ul class="ms-pcs-list">
                            <li><strong>One artwork</strong>: its own preset - the Quick Artwork Editor or the Artwork Editor (“Price card”). It wins over “Artwork pages” here.</li>
                            <li><strong>One category</strong> (tag, place): its own card under each artwork (Hide / a preset) and the tab it opens on - <a href="<?php echo esc_url( admin_url( 'edit-tags.php?taxonomy=category' ) ); ?>">edit the category</a>.</li>
                            <li><strong>Price card view</strong> on category pages and the Art Feed (the “Price card” button beside list and grid): each artwork as a standalone card - <a href="<?php echo esc_url( admin_url( 'themes.php?page=monospace-look' ) ); ?>">Look</a> &gt; Viewing toolbar &gt; Price card view; a category can start in it (edit the category: “On, start in price card view”).</li>
                            <li><strong>The card’s colors and rounding</strong>: <a href="<?php echo esc_url( admin_url( 'themes.php?page=monospace-look' ) ); ?>">Look</a> &gt; Cards; dark mode: Look &gt; Dark look.</li>
                            <li><strong>Messages and fine print</strong> on the card (offers, shipping): Sales Dashboard &gt; Messages.</li>
                        </ul>
                    <?php $end(); ?>

                    <?php $panel( 'shortcodes', 'Shortcode reference', 'In any page, post or home page text box', '', false ); ?>
                        <h3 class="ms-pcs-h">[price_card]</h3>
                        <p class="description">The price card for an artwork - its tabs (Original, Framed print, Unframed print), details, options, SKU, button and offer.</p>
                        <table class="widefat striped ms-pcs-ref"><tbody>
                            <tr><td><code>sku="444"</code></td><td>The artwork, by the number at the end of its SKU (MNSPC-2026-P-0444) - or the whole SKU. Without sku or post: the artwork the shortcode is in.</td></tr>
                            <tr><td><code>post="123"</code></td><td>The artwork, by its post ID.</td></tr>
                            <tr><td><code>style="full"</code></td><td><code>full</code> · <code>compact</code> · <code>line</code> · <code>badge</code> - else the artwork’s own preset, else “Artwork pages”.</td></tr>
                            <tr><td><code>tab="prints"</code></td><td>The tab it opens on: <code>original</code> · <code>prints</code> (the first print it has) · <code>framed</code> · <code>unframed</code>.</td></tr>
                        </tbody></table>
                        <h3 class="ms-pcs-h">[price_card_standalone]</h3>
                        <p class="description">The same card with the artwork’s title and picture in it - the print’s mockup on a print tab; clicking the picture or title opens the artwork. Anything left out follows the Standalone card settings above.</p>
                        <table class="widefat striped ms-pcs-ref"><tbody>
                            <tr><td><code>sku="444"</code> · <code>post="123"</code></td><td>The artwork, as above.</td></tr>
                            <tr><td><code>layout="side"</code></td><td><code>side</code> (picture on the left; on top on phones) · <code>stacked</code> (picture on top).</td></tr>
                            <tr><td><code>title="no"</code></td><td>Leave out the artwork’s title.</td></tr>
                            <tr><td><code>image="no"</code></td><td>Leave out the picture.</td></tr>
                            <tr><td><code>preview="no"</code></td><td>Always the artwork’s picture, also on a print tab.</td></tr>
                            <tr><td><code>link="no"</code></td><td>The picture and title don’t open the artwork’s page.</td></tr>
                            <tr><td><code>sizes="same"</code></td><td>Print mockups all the same size - else at their relative sizes (a 5×7 smaller than a framed 11×14).</td></tr>
                            <tr><td><code>style="compact"</code></td><td>The card inside: <code>full</code> · <code>compact</code>.</td></tr>
                            <tr><td><code>tab="framed"</code></td><td>The tab it opens on, as above.</td></tr>
                        </tbody></table>
                        <h3 class="ms-pcs-h">Examples <span class="description">(click to copy)</span></h3>
                        <p class="ms-pcs-examples">
                            <?php foreach ( [ '[price_card sku="444"]', '[price_card sku="444" style="badge"]', '[price_card sku="444" tab="prints"]',
                                              '[price_card_standalone sku="444"]', '[price_card_standalone sku="444" layout="stacked" tab="framed"]',
                                              '[price_card_standalone sku="444" style="compact" title="no"]' ] as $ex ) : ?>
                                <button type="button" class="button-link ms-pcs-copy"><code><?php echo esc_html( $ex ); ?></code></button>
                            <?php endforeach; ?>
                        </p>
                    <?php $end(); ?>

                    <p class="submit"><?php submit_button( 'Save the price card', 'primary', 'submit', false ); ?></p>
                </div>
            </div>
        </form>
    </div>
    <style>
        .ms-pcs-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(360px, 480px); gap: 24px; align-items: start; max-width: 1500px; }
        @media (max-width: 1100px) { .ms-pcs-grid { grid-template-columns: minmax(0, 1fr); } }
        .ms-pcs-preview { position: sticky; top: 46px; }
        @media (max-width: 1100px) { .ms-pcs-preview { position: static; } }
        .ms-pcs-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 8px; }
        .ms-pcs-bar select { max-width: 260px; }
        .ms-pcs-stage { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; padding: 10px; overflow: hidden; }
        .ms-pcs-frame { position: relative; margin: 0 auto; overflow: hidden; border-radius: 3px; box-shadow: 0 0 0 1px #dcdcde; }
        .ms-pcs-frame iframe { position: absolute; top: 0; left: 0; border: 0; transform-origin: 0 0; background: #fff; }
        .ms-pcs-list { margin: 0; padding-left: 18px; list-style: disc; } .ms-pcs-list li { margin-bottom: 6px; }
        .ms-pcs-h { margin: 14px 0 4px; font-size: 14px; }
        .ms-pcs-ref td:first-child { width: 42%; white-space: nowrap; }
        .ms-pcs-ref code { font-size: 12px; }
        .ms-pcs-examples { display: grid; gap: 4px; }
        .ms-pcs-code { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px; margin: 10px 0 4px; }
        .ms-pcs-code code { flex: 1 1 260px; padding: 5px 8px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; font-size: 12px; word-break: break-word; }
        .ms-pcs-code-label { font-weight: 600; }
        .ms-pcs-copy { text-align: left; } .ms-pcs-copy.is-copied::after { content: " copied"; color: #00a32a; font-size: 12px; }
        .ms-pcs .lm-body .form-table th { width: 130px; padding-right: 10px; }
    </style>
    <script>
    (function () {
        var form = document.getElementById('ms-pcs-form'), iframe = document.getElementById('ms-pcs-iframe'), frame = iframe.parentNode, stage = frame.parentNode,
            art = document.getElementById('ms-pcs-art'), kind = 'card', width = 1040, home = <?php echo wp_json_encode( home_url( '/' ) ); ?>, timer = 0;
        function val(name) { var el = form.querySelector('[name="' + name + '"]:checked') || form.querySelector('select[name="' + name + '"], input[type=text][name="' + name + '"]'); return el ? el.value : ''; }
        function url() {
            var q = { ms_pc_preview: 1, kind: kind, post: art.value };
            if (kind === 'card') {
                var page = val('price_card_page'), phone = val('price_card_phone');
                q.style = width < 641 && phone && phone !== 'same' ? phone : page;
                q.tab = val('price_card_tab');
            } else {
                ['layout', 'title', 'image', 'preview', 'link', 'sizes', 'style'].forEach(function (k) { q[k] = val('pcsa[' + k + ']'); });
                q.tab = val('price_card_tab');
            }
            return home + '?' + Object.keys(q).map(function (k) { return k + '=' + encodeURIComponent(q[k]); }).join('&');
        }
        // The shortcode for what's previewed: the artwork by its SKU number (else its post ID) and every setting spelled out
        function shortcode() {
            var opt = art.options[art.selectedIndex], who = opt && opt.dataset.sku ? 'sku="' + opt.dataset.sku + '"' : 'post="' + art.value + '"', parts = [who];
            if (kind === 'card') {
                var page = val('price_card_page'), phone = val('price_card_phone');
                parts.push('style="' + (width < 641 && phone && phone !== 'same' ? phone : page) + '"');
            } else {
                ['layout', 'title', 'image', 'preview', 'link', 'sizes', 'style'].forEach(function (k) { var v = val('pcsa[' + k + ']'); if (v) parts.push(k + '="' + v + '"'); });
            }
            var tab = val('price_card_tab'); if (tab) parts.push('tab="' + tab + '"');
            return '[' + (kind === 'card' ? 'price_card' : 'price_card_standalone') + ' ' + parts.join(' ') + ']';
        }
        function showCode() { document.getElementById('ms-pcs-shortcode').textContent = shortcode(); }
        document.getElementById('ms-pcs-copy-code').addEventListener('click', function () {
            var b = this, t = shortcode();
            (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () {
                b.textContent = 'Copied'; setTimeout(function () { b.textContent = 'Copy'; }, 1500); }, function () { window.prompt('Copy:', t); });
        });
        function size() {
            var h = Math.max(520, window.innerHeight - 220), k = Math.min(1, (stage.clientWidth - 20) / width);
            iframe.style.width = width + 'px'; iframe.style.height = Math.round(h / k) + 'px'; iframe.style.transform = 'scale(' + k + ')';
            frame.style.width = Math.round(width * k) + 'px'; frame.style.height = h + 'px';
        }
        function load() { showCode(); clearTimeout(timer); timer = setTimeout(function () { iframe.src = url(); }, 250); }
        document.querySelectorAll('.ms-pcs-bar [data-kind]').forEach(function (b) { b.addEventListener('click', function () {
            kind = b.dataset.kind; document.querySelectorAll('.ms-pcs-bar [data-kind]').forEach(function (x) { x.classList.toggle('button-primary', x === b); }); load(); }); });
        document.querySelectorAll('.ms-pcs-bar [data-w]').forEach(function (b) { b.addEventListener('click', function () {
            width = +b.dataset.w; document.querySelectorAll('.ms-pcs-bar [data-w]').forEach(function (x) { x.classList.toggle('button-primary', x === b); }); size(); load(); }); });
        art.addEventListener('change', load);
        form.addEventListener('change', function (e) {
            if (e.target.closest('[data-panel="standalone"]') && kind !== 'standalone') document.querySelector('.ms-pcs-bar [data-kind="standalone"]').click();
            else load();
        });
        form.addEventListener('input', function (e) { if (e.target.type === 'text') load(); });
        document.querySelectorAll('.ms-pcs-copy').forEach(function (b) { b.addEventListener('click', function () {
            var t = b.textContent.trim(); (navigator.clipboard ? navigator.clipboard.writeText(t) : Promise.reject()).then(function () {
                b.classList.add('is-copied'); setTimeout(function () { b.classList.remove('is-copied'); }, 1500); }, function () { window.prompt('Copy:', t); }); }); });
        window.addEventListener('resize', size);
        size(); showCode(); iframe.src = url();
    })();
    </script>
    <?php
}

// The Look screen and the Layout Manager: one short panel for both groups, linking here (the settings live on this screen)
add_filter( 'monospace_design_modules', function ( $modules ) {
    if ( ! isset( $modules['price-card'] ) ) return $modules;
    $summary = $modules['price-card']['summary'];
    unset( $modules['price-card-standalone'] );
    $modules['price-card'] = array_merge( $modules['price-card'], [
        'description' => 'Its own screen: Appearance > Price card - the card, the standalone card, the shortcodes.',
        'summary'     => $summary,
        'render'      => function () {
            echo '<p style="margin:12px 0"><a class="button" href="' . esc_url( admin_url( 'themes.php?page=monospace-price-card' ) ) . '">Open Appearance &gt; Price card</a></p>'
               . '<p class="description">Everything about the price card is on its own screen now, with a preview and the shortcode reference.</p>';
        },
        'save'        => '__return_null',
    ] );
    return $modules;
}, 50 );

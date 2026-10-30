<?php
/**
 * Artworks > New artwork (2026-10-19, owner's wish: entering a new piece was a hassle).
 *
 * One page, image first: title, caption, price, status, the product details, categories, tags, an
 * optional story and (off by default) prints. "Create draft" then does the setup by hand in one go:
 * - the post, in the owner's fixed shape: image (full size) + caption, More (blank text), story
 *   paragraphs, [price_card], the "meta" synced block (#428 - MONOSPACE_ARTWORK_META_BLOCK)
 * - categories: format + medium from the details (painting / drawing, acrylic / ink …), plus the look
 *   ticked here (color, black-and-white, sepia, miniature, favorite - line & wash was merged into the
 *   Ink & Wash medium category 2026-10-19); tags; featured image
 * - the product, through Quick Product's own ProductSyncer (same SKU rule, Original category,
 *   shipping class by size), so it's identical to one made from the Quick Product box
 * - prints, when ticked: every size and frame at the default prices, sizes under 100 dpi left out
 *   (monospace_prints_queue(), as Prints > New prints does)
 * and opens the draft in the block editor - the story and the rest are edited there as usual.
 *
 * The Artworks menu is meant to hold the all-artworks list later.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_ARTWORK_META_BLOCK = 428;

/** Look categories ticked by hand on the form (slug => label) */
function monospace_artwork_look_categories() {
    return [
        'color'           => 'Color',
        'black-and-white' => 'Black and white',
        'sepia'           => 'Sepia',
        'miniature'       => 'Miniature',
        'favorite'        => 'Favorite',
    ];
}

/** Post category for a medium (pa_medium term name), or '' */
function monospace_artwork_medium_category( $medium ) {
    $map = [
        'acrylic' => 'acrylic', 'casein' => 'casein', 'gouache' => 'gouache', 'ink & wash' => 'ink',
        'ink & markers' => 'ink', 'watercolor' => 'watercolor', 'graphite pencil' => 'pencil',
        'color pencil' => 'pencil', 'oil' => 'oil', 'tempera' => 'tempera', 'sanguine' => 'sanguine',
    ];
    $key = strtolower( trim( html_entity_decode( (string) $medium, ENT_QUOTES, 'UTF-8' ) ) );
    $slug = $map[ $key ] ?? '';
    return $slug && get_category_by_slug( $slug ) ? $slug : '';
}

/** Post category for a format (pa_format term slug), or '' */
function monospace_artwork_format_category( $format ) {
    return $format && get_category_by_slug( $format ) ? $format : '';
}

/* ============================================================
 * MENU
 * ============================================================ */

add_action( 'admin_menu', function () {
    if ( ! class_exists( '\QuickProduct\ProductSyncer' ) ) return; // needs the Quick Product plugin
    $hook = add_menu_page( 'New artwork', 'Artworks', 'edit_products', 'monospace-artworks', 'monospace_new_artwork_page', 'dashicons-art', '5.5' );
    add_submenu_page( 'monospace-artworks', 'New artwork', 'New artwork', 'edit_products', 'monospace-artworks', 'monospace_new_artwork_page' );
    add_action( 'admin_enqueue_scripts', function ( $current ) use ( $hook ) {
        if ( $current === $hook ) wp_enqueue_media();
    } );
} );

// "Artwork" at the top of the admin bar's "+ New" menu (owner, 2026-10-19). Added before WordPress builds that
// menu (priority 70) - the bar lists a menu's items in the order they were added
add_action( 'admin_bar_menu', function ( $bar ) {
    if ( ! class_exists( '\QuickProduct\ProductSyncer' ) || ! current_user_can( 'edit_products' ) || ! current_user_can( 'edit_posts' ) ) return;
    $bar->add_node( [ 'parent' => 'new-content', 'id' => 'new-artwork', 'title' => 'Artwork', 'href' => admin_url( 'admin.php?page=monospace-artworks' ) ] );
}, 69 );

/* ============================================================
 * FORM DATA
 * ============================================================ */

/** The attributes on the form: taxonomy => [ label, required, options ] (options: [ group, slug, name ]) */
function monospace_new_artwork_attributes() {
    $out = [];
    $required = [ 'pa_format', 'pa_medium', 'pa_year-created' ];
    foreach ( wc_get_attribute_taxonomies() as $tax ) {
        $taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
        if ( $taxonomy === 'pa_color' ) continue; // print products only
        $terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
        if ( is_wp_error( $terms ) || ! $terms ) continue;

        $options = [];
        if ( $taxonomy === 'pa_size' ) {
            // Originals' sizes only - Printful's print sizes (bare "8×10", "5x7-2") live in the same taxonomy
            // Grouped Landscape / Portrait / Square / Other, large to small (MetaBox::size_group(), 2026-10-20)
            // Portrait mirrors Landscape (MetaBox::dropdown_size_terms())
            foreach ( \QuickProduct\MetaBox::sort_size_terms( \QuickProduct\MetaBox::dropdown_size_terms( $terms ) ) as $t ) {
                $options[] = [ \QuickProduct\MetaBox::size_group( $t->name ), $t->slug, $t->name ];
            }
        } else {
            // Years newest first, "Other" last
            if ( $taxonomy === 'pa_year-created' ) usort( $terms, function ( $a, $b ) { return (int) is_numeric( $b->name ) <=> (int) is_numeric( $a->name ) ?: strcmp( $b->name, $a->name ); } );
            foreach ( $terms as $t ) $options[] = [ '', $t->slug, html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ) ];
        }
        $out[ $taxonomy ] = [ $tax->attribute_label, in_array( $taxonomy, $required, true ), $options ];
    }
    return $out;
}

/** Starting values: medium, surface and format from the last piece entered, year = this year */
function monospace_new_artwork_defaults() {
    $defaults = [ 'pa_year-created' => '' ];
    $year = get_term_by( 'name', date( 'Y' ), 'pa_year-created' );
    if ( $year ) $defaults['pa_year-created'] = $year->slug;

    $last = get_posts( [ 'post_type' => 'post', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
                         'meta_key' => '_qp_product_id', 'orderby' => 'date', 'order' => 'DESC' ] );
    $pid = $last ? (int) get_post_meta( $last[0], '_qp_product_id', true ) : 0;
    if ( $pid ) {
        foreach ( [ 'pa_format', 'pa_medium', 'pa_surface' ] as $taxonomy ) {
            $terms = wp_get_post_terms( $pid, $taxonomy, [ 'fields' => 'slugs' ] );
            if ( ! is_wp_error( $terms ) && $terms ) $defaults[ $taxonomy ] = $terms[0];
        }
    }
    return $defaults;
}

/* ============================================================
 * PAGE
 * ============================================================ */

function monospace_new_artwork_page() {
    $attributes = monospace_new_artwork_attributes();
    $defaults   = monospace_new_artwork_defaults();
    // A feature can open this page pre-filled (?origin=...): filter `monospace_new_artwork_prefill` returns
    // [ image_id, status, price, notice ] for that origin (a plain value); the origin goes back with the form
    $origin     = isset( $_GET['origin'] ) ? sanitize_text_field( wp_unslash( $_GET['origin'] ) ) : '';
    $prefill    = $origin !== '' ? (array) apply_filters( 'monospace_new_artwork_prefill', [], $origin ) : [];
    if ( ! empty( $prefill['image_id'] ) ) {
        $prefill['image_url']  = (string) wp_get_attachment_image_url( (int) $prefill['image_id'], 'large' );
        $prefill['image_info'] = basename( (string) get_attached_file( (int) $prefill['image_id'] ) ) . ' - click the image to choose another.';
    }
    $statuses   = \QuickProduct\MetaBox::availability_options();
    $tags       = get_terms( [ 'taxonomy' => 'post_tag', 'hide_empty' => false, 'orderby' => 'count', 'order' => 'DESC', 'fields' => 'names' ] );

    // For the "from details" categories line: medium name per slug, format category per slug
    $medium_cats = [];
    foreach ( $attributes['pa_medium'][2] ?? [] as $o ) $medium_cats[ $o[1] ] = monospace_artwork_medium_category( $o[2] );
    $format_cats = [];
    foreach ( $attributes['pa_format'][2] ?? [] as $o ) $format_cats[ $o[1] ] = monospace_artwork_format_category( $o[1] );
    ?>
    <div class="wrap ms-na">
        <h1>New artwork</h1>
        <p class="ms-na-intro">Image first, then the facts. <strong>Create draft</strong> makes the post (image + caption, More, story, price card), sets its categories, tags and featured image, creates the product with its SKU, and opens the draft in the editor.</p>

        <?php if ( ! empty( $prefill['notice'] ) ) : ?><div class="notice notice-info inline"><p><?php echo esc_html( $prefill['notice'] ); ?></p></div><?php endif; ?>

        <form id="ms-na-form" novalidate>
            <?php wp_nonce_field( 'monospace_new_artwork', '_ajax_nonce' ); ?>
            <?php if ( $origin !== '' ) : ?><input type="hidden" name="origin" value="<?php echo esc_attr( $origin ); ?>"><?php endif; ?>
            <div class="ms-na-grid">

                <section class="ms-na-card ms-na-image">
                    <h2>Image <span class="ms-na-req">*</span> <span class="ms-na-state" data-state="image"></span></h2>
                    <button type="button" class="ms-na-drop" id="ms-na-pick">
                        <span class="ms-na-drop-empty"><span class="dashicons dashicons-format-image" aria-hidden="true"></span>Choose or upload the image</span>
                        <img alt="" hidden>
                    </button>
                    <input type="hidden" name="image_id" id="ms-na-image-id">
                    <p class="ms-na-help" id="ms-na-image-info">The post's image and featured image. Drag a file onto the media window to upload.</p>
                </section>

                <div class="ms-na-main">
                    <section class="ms-na-card">
                        <h2>Artwork <span class="ms-na-state" data-state="artwork"></span></h2>
                        <div class="ms-na-field">
                            <label for="ms-na-title">Title <span class="ms-na-req">*</span></label>
                            <input type="text" id="ms-na-title" name="title" class="ms-na-big" data-required="Title" autocomplete="off">
                        </div>
                        <div class="ms-na-field">
                            <label for="ms-na-caption">Caption</label>
                            <input type="text" id="ms-na-caption" name="caption" autocomplete="off">
                            <p class="ms-na-help">Under the image (and the page's heading). Starts as the title - make it longer if you like, e.g. with the place.</p>
                        </div>
                        <div class="ms-na-row">
                            <div class="ms-na-field">
                                <label for="ms-na-price">Price <span class="ms-na-req" id="ms-na-price-req">*</span></label>
                                <div class="ms-na-money"><input type="text" id="ms-na-price" name="price" class="ms-na-big" inputmode="decimal" placeholder="0.00"
                                    data-optional-when="<?php echo esc_attr( implode( ' ', monospace_price_optional_statuses() ) ); ?>"></div>
                            </div>
                            <div class="ms-na-field">
                                <label for="ms-na-status">Status</label>
                                <select id="ms-na-status" name="status" class="ms-na-big" data-status="available">
                                    <?php foreach ( $statuses as $value => $label ) printf( '<option value="%s">%s</option>', esc_attr( $value ), esc_html( $label ) ); ?>
                                </select>
                            </div>
                        </div>
                        <div class="ms-na-row" id="ms-na-gallery" hidden>
                            <div class="ms-na-field"><label for="ms-na-gallery-name">Gallery name</label><input type="text" id="ms-na-gallery-name" name="gallery_name" placeholder="e.g. Fountain Art Fair"></div>
                            <div class="ms-na-field"><label for="ms-na-gallery-url">Gallery URL</label><input type="url" id="ms-na-gallery-url" name="gallery_url" placeholder="https://"></div>
                        </div>
                    </section>

                    <section class="ms-na-card">
                        <h2>Details <span class="ms-na-state" data-state="details"></span></h2>
                        <div class="ms-na-attrs">
                            <?php foreach ( $attributes as $taxonomy => [ $label, $required, $options ] ) : $id = 'ms-na-' . sanitize_html_class( $taxonomy ); ?>
                                <div class="ms-na-field">
                                    <label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?><?php if ( $required ) echo ' <span class="ms-na-req">*</span>'; ?></label>
                                    <select id="<?php echo esc_attr( $id ); ?>" name="attributes[<?php echo esc_attr( $taxonomy ); ?>]" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>"<?php
                                        // Size: a "Recent & frequent" group on top, as in the other editors (script below)
                                        if ( $taxonomy === 'pa_size' ) echo ' data-suggest="' . esc_attr( implode( ',', \QuickProduct\MetaBox::detail_suggestions( 'pa_size' ) ) ) . '"'; ?><?php if ( $required ) echo ' data-required="' . esc_attr( $label ) . '"'; ?>>
                                        <option value="">— Select —</option>
                                        <?php
                                        $open = null;
                                        foreach ( $options as [ $group, $slug, $name ] ) {
                                            if ( $group !== '' && $group !== $open ) {
                                                if ( $open !== null ) echo '</optgroup>';
                                                echo '<optgroup label="' . esc_attr( $group ) . '">';
                                                $open = $group;
                                            }
                                            printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $defaults[ $taxonomy ] ?? '', $slug, false ), esc_html( $name ) );
                                        }
                                        if ( $open !== null ) echo '</optgroup>';
                                        ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="ms-na-help">Format, medium and surface start from the last piece you entered.</p>
                    </section>

                    <section class="ms-na-card">
                        <h2>Categories &amp; tags <span class="ms-na-state" data-state="terms"></span></h2>
                        <p class="ms-na-auto">From the details: <span id="ms-na-auto-cats">-</span></p>
                        <div class="ms-na-checks">
                            <?php foreach ( monospace_artwork_look_categories() as $slug => $label ) :
                                if ( ! get_category_by_slug( $slug ) ) continue; ?>
                                <label class="ms-na-chip-check"><input type="checkbox" name="look[]" value="<?php echo esc_attr( $slug ); ?>"> <?php echo esc_html( $label ); ?></label>
                            <?php endforeach; ?>
                        </div>

                        <div class="ms-na-field ms-na-tags-field">
                            <label for="ms-na-tag-input">Tags</label>
                            <div class="ms-na-tags" id="ms-na-tags">
                                <input type="text" id="ms-na-tag-input" list="ms-na-tag-list" placeholder="Type a tag, Enter or comma to add" autocomplete="off">
                            </div>
                            <datalist id="ms-na-tag-list">
                                <?php foreach ( is_array( $tags ) ? $tags : [] as $name ) echo '<option value="' . esc_attr( html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ) ) . '">'; ?>
                            </datalist>
                            <p class="ms-na-help">Places and subjects. Existing tags are suggested as you type; new ones are created.</p>
                        </div>
                    </section>

                    <details class="ms-na-card ms-na-fold">
                        <summary><h2>Story <span class="ms-na-state" data-state="story"></span></h2><span class="ms-na-sub">After the More break, before the price card</span></summary>
                        <textarea id="ms-na-story" name="story" rows="5" placeholder="A blank line starts a new paragraph. You can also write it in the editor."></textarea>
                    </details>

                    <section class="ms-na-card"<?php echo monospace_feature( 'prints' ) ? '' : ' hidden'; // the Prints plugin is off ?>>
                        <label class="ms-na-switch"><input type="checkbox" name="prints" value="1" id="ms-na-prints"> Make prints</label>
                        <p class="ms-na-help">Every size and frame at the default prices (Prints &gt; New prints); sizes the image is too small for are left out. Runs in the background - the prints go on sale once Printful has linked them. You can also make them later from the post's Prints box.</p>
                    </section>

                    <div class="ms-na-actions">
                        <div id="ms-na-result" class="ms-na-result" hidden></div>
                        <button type="submit" class="button button-primary button-hero" id="ms-na-submit">Create draft</button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <style>
        .ms-na { max-width: 1180px; }
        .ms-na *, .ms-na *::before, .ms-na *::after { box-sizing: border-box; }
        .ms-na-intro { max-width: 760px; color: #50575e; margin: 4px 0 18px; }
        .ms-na-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; align-items: start; }
        @media (min-width: 1100px) { .ms-na-grid { grid-template-columns: 380px minmax(0, 1fr); } .ms-na-image { position: sticky; top: 46px; } }
        .ms-na-main { display: grid; gap: 16px; min-width: 0; }
        .ms-na-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; padding: 16px 18px 6px; min-width: 0; }
        .ms-na-card h2 { display: flex; align-items: center; gap: 6px; margin: 0 0 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #757575; }
        /* State chips on the title lines (admin UI style, 2026-10-20) - kept up to date as the form is filled in */
        .ms-na-state { font-size: 11px; font-weight: 400; letter-spacing: 0; text-transform: none; line-height: 1.6; padding: 0 7px; border-radius: 9px; background: #f0f0f1; color: #50575e; }
        .ms-na-state:empty { display: none; }
        .ms-na-state.is-on { background: #e7f0fa; color: #135e96; }
        .ms-na-state.is-warn { background: #fcf0f1; color: #8a2424; }
        /* The optional Story folds */
        .ms-na-fold > summary { list-style: none; cursor: pointer; display: flex; flex-wrap: wrap; align-items: center; gap: 0 10px; margin-bottom: 10px; }
        .ms-na-fold > summary::-webkit-details-marker { display: none; }
        .ms-na-fold > summary h2 { margin: 0; }
        .ms-na-fold > summary h2::before { content: "▸"; margin-right: 2px; color: #8c8f94; }
        .ms-na-fold[open] > summary h2::before { content: "▾"; }
        .ms-na-sub { color: #646970; font-size: 12px; }
        .ms-na-opt { text-transform: none; letter-spacing: 0; font-weight: 400; color: #8c8f94; margin-left: 4px; }
        .ms-na-req { color: #d63638; font-weight: 400; }
        .ms-na-field { margin: 0 0 12px; min-width: 0; }
        .ms-na-field > label { display: block; margin: 0 0 4px; font-size: 12px; font-weight: 600; }
        .ms-na input[type="text"], .ms-na input[type="url"], .ms-na select, .ms-na textarea { width: 100%; max-width: none; margin: 0; min-height: 34px; }
        .ms-na input.ms-na-big, .ms-na select.ms-na-big { min-height: 42px; font-size: 16px; font-weight: 600; }
        .ms-na-help { margin: 4px 0 10px; font-size: 12px; color: #757575; line-height: 1.45; }
        .ms-na-field .ms-na-help { margin-bottom: 0; }
        .ms-na-row, .ms-na-attrs { display: grid; grid-template-columns: minmax(0, 1fr); column-gap: 12px; }
        @media (min-width: 700px) { .ms-na-row { grid-template-columns: repeat(2, minmax(0, 1fr)); } .ms-na-attrs { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        .ms-na-row[hidden] { display: none; }
        .ms-na-field.is-invalid input, .ms-na-field.is-invalid select { border-color: #d63638; box-shadow: 0 0 0 1px #d63638; }
        .ms-na-image.is-invalid .ms-na-drop { border-color: #d63638; }

        .ms-na-money { position: relative; }
        .ms-na-money::before { content: "$"; position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #757575; font-size: 16px; font-weight: 600; pointer-events: none; }
        .ms-na .ms-na-money input { padding-left: 22px; }

        /* Status colors, as in the Quick Product box */
        #ms-na-status { border-left-width: 4px; }
        #ms-na-status[data-status="available"] { background-color: #edfaef; border-left-color: #00a32a; color: #00631f; }
        #ms-na-status[data-status="reserved"] { background-color: #fcf9e8; border-left-color: #dba617; color: #6e4e00; }
        #ms-na-status[data-status="sold"] { background-color: #f0f6fc; border-left-color: #2271b1; color: #135e96; }
        #ms-na-status[data-status="private"] { background-color: #fcf0f1; border-left-color: #d63638; color: #8a2424; }
        #ms-na-status[data-status="gallery"] { background-color: #f5effc; border-left-color: #8a4fd1; color: #5b2a99; }
        #ms-na-status[data-status="gallery-reserved"] { background-color: #fdf1e7; border-left-color: #e26f12; color: #8a3f00; }

        .ms-na-drop { display: flex; align-items: center; justify-content: center; width: 100%; min-height: 260px; padding: 0; border: 2px dashed #c3c4c7; border-radius: 6px; background: #f6f7f7; cursor: pointer; overflow: hidden; }
        .ms-na-drop:hover, .ms-na-drop:focus-visible { border-color: #2271b1; background: #f0f6fc; outline: none; }
        .ms-na-drop.has-image { border-style: solid; border-color: #dcdcde; background: #fff; min-height: 0; }
        .ms-na-drop img { display: block; width: 100%; height: auto; }
        .ms-na-drop img[hidden], .ms-na-drop.has-image .ms-na-drop-empty { display: none; }
        .ms-na-drop-empty { display: flex; flex-direction: column; align-items: center; gap: 8px; color: #50575e; font-size: 14px; }
        .ms-na-drop-empty .dashicons { width: 40px; height: 40px; font-size: 40px; color: #8c8f94; }

        .ms-na-auto { margin: 0 0 10px; font-size: 13px; color: #50575e; }
        .ms-na-auto strong { color: #1e1e1e; }
        .ms-na-checks { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 14px; }
        .ms-na .ms-na-chip-check { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px 5px 9px; border: 1px solid #c3c4c7; border-radius: 999px; background: #fff; font-size: 13px; cursor: pointer; user-select: none; }
        .ms-na .ms-na-chip-check input { margin: 0; }
        .ms-na .ms-na-chip-check:has(input:checked) { border-color: #2271b1; background: #f0f6fc; color: #135e96; }

        .ms-na-tags { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; padding: 5px; min-height: 40px; border: 1px solid #8c8f94; border-radius: 4px; background: #fff; cursor: text; }
        .ms-na-tags:focus-within { border-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }
        .ms-na .ms-na-tags input[type="text"] { flex: 1; min-width: 200px; width: auto; min-height: 28px; border: 0; box-shadow: none; padding: 0 4px; }
        .ms-na-tag { display: inline-flex; align-items: center; gap: 2px; padding: 2px 4px 2px 9px; border-radius: 999px; background: #f0f0f1; font-size: 13px; line-height: 20px; }
        .ms-na-tag.is-new { background: #fcf9e8; }
        .ms-na-tag button { display: inline-flex; width: 18px; height: 18px; padding: 0; border: 0; border-radius: 50%; background: none; color: #757575; cursor: pointer; align-items: center; justify-content: center; font-size: 15px; line-height: 1; }
        .ms-na-tag button:hover { background: #dcdcde; color: #1e1e1e; }

        .ms-na textarea { min-height: 110px; line-height: 1.5; }

        .ms-na .ms-na-switch { display: flex; align-items: center; gap: 10px; margin: 0 0 6px; font-size: 14px; font-weight: 600; cursor: pointer; }
        .ms-na .ms-na-switch input[type="checkbox"] { -webkit-appearance: none; appearance: none; position: relative; flex: none; width: 34px; min-width: 34px; height: 20px; margin: 0; border: 0; border-radius: 10px; background: #c3c4c7; cursor: pointer; transition: background .15s; }
        .ms-na .ms-na-switch input[type="checkbox"]::before { content: ""; position: absolute; top: 2px; left: 2px; float: none; width: 16px; height: 16px; margin: 0; border-radius: 50%; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.2); transition: transform .15s; }
        .ms-na .ms-na-switch input[type="checkbox"]:checked { background: #00a32a; }
        .ms-na .ms-na-switch input[type="checkbox"]:checked::before { transform: translateX(14px); }

        .ms-na-actions { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: 12px; padding-bottom: 24px; }
        .ms-na-result { flex: 1 1 300px; padding: 10px 12px; border-radius: 4px; font-size: 13px; line-height: 1.5; }
        .ms-na-result.is-error { background: #fcf0f1; border-left: 3px solid #d63638; }
        .ms-na-result.is-ok { background: #edfaef; border-left: 3px solid #00a32a; }
        .ms-na-result[hidden] { display: none; }
    </style>

    <script>
    jQuery(function ($) {
        var $form = $('#ms-na-form'), $result = $('#ms-na-result'), $submit = $('#ms-na-submit');
        var mediumCats = <?php echo wp_json_encode( $medium_cats ); ?>;
        var formatCats = <?php echo wp_json_encode( $format_cats ); ?>;
        var miniSize = <?php echo wp_json_encode( ( $t = get_term_by( 'name', '4" × 4"', 'pa_size' ) ) ? $t->slug : '' ); ?>;

        function showResult(type, html) { $result.removeClass('is-error is-ok').addClass(type).html(html).prop('hidden', false); }

        // ---- Image: the media window (pick or upload)
        var frame;
        $('#ms-na-pick').on('click', function () {
            if (!frame) {
                frame = wp.media({ title: 'Artwork image', button: { text: 'Use this image' }, library: { type: 'image' }, multiple: false });
                frame.on('select', function () {
                    var a = frame.state().get('selection').first().toJSON();
                    var preview = (a.sizes && (a.sizes.large || a.sizes.medium_large || a.sizes.full)) || a;
                    $('#ms-na-image-id').val(a.id).trigger('change');
                    $('#ms-na-pick').addClass('has-image').find('img').attr('src', preview.url).prop('hidden', false);
                    $('#ms-na-image-info').text(a.filename + ' · ' + a.width + ' × ' + a.height + ' px - click the image to choose another.');
                    $('.ms-na-image').removeClass('is-invalid');
                    // An empty title starts from the image's title (often the file name - edit it)
                    if (!$.trim($('#ms-na-title').val()) && a.title && !/^(img|dsc|image|photo)[\s_-]?\d/i.test(a.title)) $('#ms-na-title').val(a.title).trigger('input');
                });
            }
            frame.open();
        });

        // ---- Caption follows the title until it's edited
        var captionEdited = false;
        $('#ms-na-caption').on('input', function () { captionEdited = $.trim(this.value) !== ''; });
        $('#ms-na-title').on('input', function () { if (!captionEdited) $('#ms-na-caption').val(this.value); });

        // ---- State chips on the title lines (admin UI style): what's done, what's missing
        function chip(name, state, text) {
            var $c = $('.ms-na-state[data-state="' + name + '"]'), cls = 'ms-na-state is-' + state;
            if ($c.attr('class') !== cls) $c.attr('class', cls);
            if ($c.text() !== text) $c.text(text); // only real changes - nothing for the watcher below to react to
        }
        function missingIn($scope) {
            var out = [];
            $scope.find('[data-required]').each(function () { if (!$.trim($(this).val() || '')) out.push(String($(this).data('required')).toLowerCase()); });
            return out;
        }
        function states() {
            chip('image', $('#ms-na-image-id').val() ? 'on' : 'warn', $('#ms-na-image-id').val() ? 'Chosen' : 'Needed');
            var art = missingIn($('[data-state="artwork"]').closest('.ms-na-card'));
            if (!$.trim($('#ms-na-price').val()) && !(typeof priceOptional === 'function' && priceOptional())) art.push('price');
            chip('artwork', art.length ? 'warn' : 'on', art.length ? 'Missing: ' + art.join(', ') : 'Complete');
            var det = missingIn($('[data-state="details"]').closest('.ms-na-card'));
            chip('details', det.length ? 'warn' : 'on', det.length ? 'Missing: ' + det.join(', ') : 'Complete');
            var auto = $.trim($('#ms-na-auto-cats').text()), cats = (auto && auto !== '-' ? auto.split(',').length : 0) + $('input[name="look[]"]:checked').length,
                tags = $('#ms-na-tags input[name="tags[]"]').length;
            chip('terms', cats + tags ? 'on' : 'empty', cats + ' ' + (cats === 1 ? 'category' : 'categories') + ' · ' + tags + ' ' + (tags === 1 ? 'tag' : 'tags'));
            var story = $.trim($('#ms-na-story').val());
            chip('story', story ? 'on' : 'empty', story ? 'Written' : 'Optional');
            if (story) $('#ms-na-story').closest('details').prop('open', true);
        }
        $form.on('input change', states);
        // Tags and the categories from the details change without input events: watch just those two (never the
        // chips themselves - that would loop)
        ['ms-na-tags', 'ms-na-auto-cats'].forEach(function (id) { var el = document.getElementById(id); if (el) new MutationObserver(states).observe(el, { childList: true, subtree: true, characterData: true }); });
        $('#ms-na-image-id').on('change', states);
        setTimeout(states, 0);

        // ---- Sizes: "Recent & frequent" on top - the picks made last here or in the other editors (localStorage
        // msAeRecent, shared with them), then the newest and most used sizes; up to 6
        $('select[data-suggest]').each(function () {
            var sel = this, tax = sel.dataset.taxonomy, known = {}, list = [], picks = {};
            try { picks = JSON.parse(localStorage.getItem('msAeRecent') || '{}'); } catch (e) {}
            Array.prototype.forEach.call(sel.options, function (o) { if (o.value && !(o.value in known)) known[o.value] = o.textContent.trim(); });
            ((picks[tax] || []).concat(sel.dataset.suggest ? sel.dataset.suggest.split(',') : [])).forEach(function (v) {
                if (v && v in known && list.indexOf(v) < 0 && list.length < 6) list.push(v);
            });
            if (list.length) {
                var group = document.createElement('optgroup'), keep = sel.value;
                group.label = 'Recent & frequent';
                list.forEach(function (v) { var o = document.createElement('option'); o.value = v; o.textContent = known[v]; group.appendChild(o); });
                sel.insertBefore(group, sel.children[1] || null);
                sel.value = keep;
            }
            $(sel).on('change', function () {
                if (!sel.value) return;
                try { var all = JSON.parse(localStorage.getItem('msAeRecent') || '{}'); all[tax] = [sel.value].concat((all[tax] || []).filter(function (x) { return x !== sel.value; })).slice(0, 3); localStorage.setItem('msAeRecent', JSON.stringify(all)); } catch (e) {}
            });
        });

        // ---- Status color + gallery fields
        $('#ms-na-status').on('change', function () {
            $(this).attr('data-status', this.value);
            $('#ms-na-gallery').prop('hidden', this.value !== 'gallery' && this.value !== 'gallery-reserved');
            priceRule();
        });
        // ---- The price is needed unless the status lets it be empty (sold, private, gallery): the * shows only
        // when it's needed, an empty price reads "--"
        function priceOptional() {
            return String($('#ms-na-price').data('optional-when') || '').split(' ').indexOf($('#ms-na-status').val()) > -1;
        }
        function priceRule() {
            var free = priceOptional();
            $('#ms-na-price-req').toggle(!free);
            $('#ms-na-price').attr('placeholder', free ? '--' : '0.00');
            if (free) $('#ms-na-price').closest('.ms-na-field').removeClass('is-invalid');
        }
        priceRule();

        // ---- Categories from the details (+ miniature ticked for a 4" × 4")
        function autoCats() {
            var cats = [], f = formatCats[$('[data-taxonomy="pa_format"]').val()], m = mediumCats[$('[data-taxonomy="pa_medium"]').val()];
            if (f) cats.push(f);
            if (m && cats.indexOf(m) < 0) cats.push(m);
            $('#ms-na-auto-cats').html(cats.length ? cats.map(function (c) { return '<strong>' + c + '</strong>'; }).join(', ') : '-');
        }
        $('[data-taxonomy="pa_format"], [data-taxonomy="pa_medium"]').on('change', autoCats);
        $('[data-taxonomy="pa_size"]').on('change', function () {
            if (miniSize && this.value === miniSize) $('input[name="look[]"][value="miniature"]').prop('checked', true);
        });
        autoCats();

        // ---- Tags: chips, existing names suggested (and their spelling reused)
        var known = {};
        $('#ms-na-tag-list option').each(function () { known[this.value.toLowerCase()] = this.value; });
        function addTag(raw) {
            var name = $.trim(raw).replace(/\s+/g, ' ');
            if (!name) return;
            name = known[name.toLowerCase()] || name;
            var exists = false;
            $('#ms-na-tags input[name="tags[]"]').each(function () { if (this.value.toLowerCase() === name.toLowerCase()) exists = true; });
            if (exists) return;
            var $chip = $('<span class="ms-na-tag">').toggleClass('is-new', !known[name.toLowerCase()])
                .append($('<span>').text(name))
                .append($('<button type="button" aria-label="Remove">').text('×'))
                .append($('<input type="hidden" name="tags[]">').val(name));
            $chip.insertBefore('#ms-na-tag-input');
        }
        $('#ms-na-tag-input').on('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); addTag(this.value); this.value = ''; }
            else if (e.key === 'Backspace' && !this.value) $(this).prev('.ms-na-tag').remove();
        }).on('input', function (e) {
            // Picking a suggestion from the list adds it at once (typing doesn't - "park" on the way to "parking")
            var how = e.originalEvent && e.originalEvent.inputType;
            if (known[this.value.toLowerCase()] && (how === undefined || how === 'insertReplacementText')) { addTag(this.value); this.value = ''; }
        }).on('blur', function () { if ($.trim(this.value)) { addTag(this.value); this.value = ''; } });
        $('#ms-na-tags').on('click', 'button', function () { $(this).closest('.ms-na-tag').remove(); })
            .on('click', function (e) { if (e.target === this) $('#ms-na-tag-input').trigger('focus'); });

        // ---- Pre-filled by the feature that opened the page (see `monospace_new_artwork_prefill`)
        var pre = <?php echo wp_json_encode( array_intersect_key( $prefill, array_flip( [ 'image_id', 'image_url', 'image_info', 'status', 'price' ] ) ) ); ?>;
        if (pre.image_id) {
            $('#ms-na-image-id').val(pre.image_id).trigger('change');
            $('#ms-na-pick').addClass('has-image').find('img').attr('src', pre.image_url || '').prop('hidden', !pre.image_url);
            $('#ms-na-image-info').text(pre.image_info || '');
        }
        if (pre.price) $('#ms-na-price').val(pre.price);
        if (pre.status) $('#ms-na-status').val(pre.status).trigger('change');

        // ---- Create draft
        $form.on('input change', ':input', function () { $(this).closest('.ms-na-field').removeClass('is-invalid'); });
        $form.on('submit', function (e) {
            e.preventDefault();
            var missing = [];
            $form.find('.is-invalid').removeClass('is-invalid');
            if (!$('#ms-na-image-id').val()) { missing.push('Image'); $('.ms-na-image').addClass('is-invalid'); }
            $form.find('[data-required]').each(function () {
                if (!$.trim($(this).val() || '')) { missing.push($(this).data('required')); $(this).closest('.ms-na-field').addClass('is-invalid'); }
            });
            // The price: needed unless the status lets it be empty (sold, private, gallery - monospace_price_required())
            var price = $.trim($('#ms-na-price').val()).replace(/[^\d.]/g, ''), priceFree = priceOptional();
            if (!$.trim($('#ms-na-price').val())) { if (!priceFree) { missing.push('Price'); $('#ms-na-price').closest('.ms-na-field').addClass('is-invalid'); } }
            else if (!(parseFloat(price) > 0)) { missing.push('Price (a number)'); $('#ms-na-price').closest('.ms-na-field').addClass('is-invalid'); }
            if (missing.length) { showResult('is-error', '<strong>Missing:</strong> ' + $('<div>').text(missing.join(', ')).html()); return; }

            var pending = $.trim($('#ms-na-tag-input').val());
            if (pending) { addTag(pending); $('#ms-na-tag-input').val(''); }

            $submit.prop('disabled', true).text('Creating…');
            $result.prop('hidden', true);
            $.post(ajaxurl, $form.serialize() + '&action=monospace_new_artwork')
                .done(function (r) {
                    if (!r || !r.success) {
                        showResult('is-error', '<strong>Not created:</strong> ' + $('<div>').text((r && r.data) || 'Unknown error').html());
                        $submit.prop('disabled', false).text('Create draft');
                        return;
                    }
                    var notes = (r.data.notes || []).map(function (n) { return '<br>' + $('<div>').text(n).html(); }).join('');
                    showResult('is-ok', 'Draft created - SKU ' + $('<div>').text(r.data.sku).html() + '. Opening the editor…' + notes);
                    setTimeout(function () { location.href = r.data.edit_url; }, notes ? 2500 : 600);
                })
                .fail(function (x) {
                    showResult('is-error', 'Request failed (' + x.status + '). Nothing may have been created - check Posts > Drafts before trying again.');
                    $submit.prop('disabled', false).text('Create draft');
                });
        });
    });
    </script>
    <?php
}

/* ============================================================
 * CREATE
 * ============================================================ */

/** The post body in the owner's shape: image + caption, More, story, [price_card], meta block */
function monospace_new_artwork_content( $image_id, $caption, $story ) {
    $url = wp_get_attachment_url( $image_id );
    $out  = '<!-- wp:image {"id":' . (int) $image_id . ',"sizeSlug":"full","linkDestination":"none"} -->' . "\n";
    $out .= '<figure class="wp-block-image size-full"><img src="' . esc_url( $url ) . '" alt="" class="wp-image-' . (int) $image_id . '"/>';
    if ( $caption !== '' ) $out .= '<figcaption class="wp-element-caption">' . esc_html( $caption ) . '</figcaption>';
    $out .= "</figure>\n<!-- /wp:image -->\n\n";
    $out .= "<!-- wp:more {\"customText\":\" \"} -->\n<!--more  -->\n<!-- /wp:more -->\n\n";
    foreach ( preg_split( '/\R\s*\R/', trim( $story ) ) as $paragraph ) {
        $paragraph = trim( $paragraph );
        if ( $paragraph === '' ) continue;
        $out .= "<!-- wp:paragraph -->\n<p>" . nl2br( esc_html( $paragraph ), false ) . "</p>\n<!-- /wp:paragraph -->\n\n";
    }
    $out .= "<!-- wp:shortcode -->\n[price_card]\n<!-- /wp:shortcode -->\n\n";
    $out .= '<!-- wp:block {"ref":' . MONOSPACE_ARTWORK_META_BLOCK . '} /-->';
    return $out;
}

add_action( 'wp_ajax_monospace_new_artwork', function () {
    check_ajax_referer( 'monospace_new_artwork' );
    if ( ! current_user_can( 'edit_products' ) || ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Not allowed.' );
    if ( ! class_exists( '\QuickProduct\ProductSyncer' ) ) wp_send_json_error( 'The Quick Artwork Editor plugin is not active.' );

    $in       = wp_unslash( $_POST );
    $image_id = absint( $in['image_id'] ?? 0 );
    $title    = sanitize_text_field( $in['title'] ?? '' );
    $caption  = sanitize_text_field( $in['caption'] ?? '' ) ?: $title;
    $price    = preg_replace( '/[^\d.]/', '', (string) ( $in['price'] ?? '' ) );
    $status   = sanitize_key( $in['status'] ?? 'available' );
    $story    = sanitize_textarea_field( $in['story'] ?? '' );

    // Attributes: only real terms of the form's taxonomies
    $attributes = [];
    foreach ( (array) ( $in['attributes'] ?? [] ) as $taxonomy => $slug ) {
        $taxonomy = sanitize_key( $taxonomy );
        $slug     = sanitize_title( $slug );
        if ( $slug !== '' && taxonomy_exists( $taxonomy ) && term_exists( $slug, $taxonomy ) ) $attributes[ $taxonomy ] = $slug;
    }

    $missing = [];
    if ( ! $image_id || ! wp_attachment_is_image( $image_id ) ) $missing[] = 'Image';
    if ( $title === '' ) $missing[] = 'Title';
    // The price: needed unless the status lets it be empty (sold, private, gallery); a number when given
    if ( $price === '' ? monospace_price_required( $status ) : ( ! is_numeric( $price ) || (float) $price <= 0 ) ) $missing[] = 'Price';
    foreach ( [ 'pa_format' => 'Format', 'pa_medium' => 'Medium', 'pa_year-created' => 'Year' ] as $taxonomy => $label ) {
        if ( empty( $attributes[ $taxonomy ] ) ) $missing[] = $label;
    }
    if ( ! array_key_exists( $status, \QuickProduct\MetaBox::availability_options() ) ) $missing[] = 'Status';
    if ( $missing ) wp_send_json_error( 'Missing: ' . implode( ', ', $missing ) );

    // Post categories: format + medium from the details, plus the look ticked on the form
    $slugs = [];
    $slugs[] = monospace_artwork_format_category( $attributes['pa_format'] );
    $medium = get_term_by( 'slug', $attributes['pa_medium'], 'pa_medium' );
    $slugs[] = $medium ? monospace_artwork_medium_category( $medium->name ) : '';
    foreach ( (array) ( $in['look'] ?? [] ) as $slug ) {
        if ( isset( monospace_artwork_look_categories()[ $slug ] ) ) $slugs[] = $slug;
    }
    // A feature may add post categories (e.g. the commissions plugin: custom-artwork)
    $slugs = (array) apply_filters( 'monospace_new_artwork_categories', $slugs, $in );
    $categories = [];
    foreach ( array_unique( array_filter( $slugs ) ) as $slug ) {
        $cat = get_category_by_slug( $slug );
        if ( $cat ) $categories[] = (int) $cat->term_id;
    }

    $tags = array_values( array_unique( array_filter( array_map( 'sanitize_text_field', (array) ( $in['tags'] ?? [] ) ) ) ) );

    // 1. The post (categories first: the SKU's type letter reads them, e.g. miniature = M)
    $post_id = wp_insert_post( wp_slash( [
        'post_type'     => 'post',
        'post_status'   => 'draft',
        'post_title'    => $title,
        'post_content'  => monospace_new_artwork_content( $image_id, $caption, $story ),
        'post_category' => $categories ?: [ (int) get_option( 'default_category' ) ],
        'tags_input'    => $tags,
    ] ), true );
    if ( is_wp_error( $post_id ) ) wp_send_json_error( $post_id->get_error_message() );

    set_post_thumbnail( $post_id, $image_id );
    if ( ! wp_get_post_parent_id( $image_id ) ) wp_update_post( [ 'ID' => $image_id, 'post_parent' => $post_id ] );

    // 2. The product - Quick Product's own sync, as its Create product button does
    $original = get_term_by( 'name', 'Original', 'product_cat' );
    $syncer = new \QuickProduct\ProductSyncer( $post_id, [
        'product_id'              => 0,
        'product_name'            => $title,
        'product_price'           => $price,
        'sku'                     => '',
        'attributes'              => $attributes,
        'categories'              => $original ? [ (int) $original->term_id ] : [],
        'availability'            => $status,
        'gallery_name'            => sanitize_text_field( $in['gallery_name'] ?? '' ),
        'gallery_url'             => esc_url_raw( $in['gallery_url'] ?? '' ),
        'print_product_id'        => 0,
        'framed_print_product_id' => 0,
    ] );
    $result = $syncer->sync();
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( 'The draft was made (post #' . $post_id . ') but the product was not: ' . $result->get_error_message() . ' - finish it in the Quick Artwork Editor.' );
    }
    $product = wc_get_product( $result['product_id'] );
    $sku = $product ? $product->get_sku() : '';

    // 3. Prints, when asked: every size and frame at the default prices, too-small sizes skipped
    $notes = [];
    if ( ! empty( $in['prints'] ) && function_exists( 'monospace_prints_queue' ) ) {
        $request = [ 'min_dpi' => 100 ];
        foreach ( monospace_print_templates() as $type => $tpl ) {
            $request[ $type ] = [ 'variants' => array_map( function ( $v ) { return $v['price']; }, $tpl['variants'] ) ];
        }
        try {
            monospace_prints_queue( $post_id, $request );
            $notes[] = 'Prints: started in the background - see the Prints box on the post.';
        } catch ( Exception $e ) {
            $notes[] = 'Prints not started: ' . $e->getMessage();
        }
    }

    do_action( 'monospace_new_artwork_created', $post_id, $product, $in );

    wp_send_json_success( [
        'post_id'  => $post_id,
        'sku'      => $sku,
        'edit_url' => get_edit_post_link( $post_id, 'raw' ),
        'notes'    => $notes,
    ] );
} );

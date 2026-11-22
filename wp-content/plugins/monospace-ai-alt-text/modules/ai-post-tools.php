<?php
/**
 * Writing help for ordinary blog posts (2026-11-17, owner: "it would be nice to have auto-tag and auto-alt-text available here too, and any
 * other tools that pertain to blog posts" - do all of it). The block editor gets a "Writing help" panel and a "Before you publish" list
 * (assets/post-tools.js); this file is the PHP side: the script, and the calls behind its buttons.
 *
 * Same rules as the artwork tools: nothing is written by itself. Every call only sends an answer back to the editor, where it lands in a
 * field you can edit; the post is saved when you save it. (The one exception is the tag chip's "Add": a tag that does not exist yet is
 * created then, as in the editor's own tag box.)
 *
 * Calls (nonce 'ms_ai', capability edit_posts / edit_post):
 *   ms_ai_post_alt       alt text for one picture            ms_ai_post_tags      tags from the site's list (max 2 new)
 *   ms_ai_post_category  a category from the Site posts tree  ms_ai_post_excerpt   a short summary for the feed card and search results
 *   ms_ai_post_share     a caption to share the post          ms_post_find_artwork find an artwork to put a card in the post (no AI)
 *
 * @package monospace-ai-alt-text
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * ===========================
 * THE EDITOR SCRIPT
 * ===========================
 */
add_action( 'enqueue_block_editor_assets', function () {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || $screen->post_type !== 'post' ) return;
    $post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
    if ( $post_id && get_post_meta( $post_id, '_qp_product_id', true ) ) return;   // an artwork: its own panel (Quick Artwork Editor)

    $file = MONOSPACE_AI_DIR . '/assets/post-tools.js';
    wp_enqueue_script( 'ms-post-tools', plugins_url( 'assets/post-tools.js', MONOSPACE_AI_DIR . '/monospace-ai-alt-text.php' ),
        [ 'wp-plugins', 'wp-element', 'wp-components', 'wp-data', 'wp-api-fetch', 'wp-blocks', 'wp-block-editor', 'wp-editor', 'wp-edit-post' ], (string) filemtime( $file ), true );
    wp_localize_script( 'ms-post-tools', 'msPostTools', [
        'ajax'  => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'ms_ai' ),
        'ai'    => ms_ai_key() !== '',
    ] );
} );

/**
 * ===========================
 * WHAT THE EDITOR TELLS US
 * ===========================
 */
function ms_ai_post_guard() {
    check_ajax_referer( 'ms_ai' );
    $post_id = absint( $_POST['post_id'] ?? 0 );
    if ( ! current_user_can( 'edit_posts' ) || ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) ) wp_send_json_error( 'Not allowed.' );
}

/** The post as the editor has it now: title, the text (plain, shortened), the tags and categories it has */
function ms_ai_post_context() {
    $in   = wp_unslash( $_POST );
    $text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( (string) ( $in['content'] ?? '' ) ) ) ) );
    $names = function ( $taxonomy, $key ) use ( $in ) {
        $out = [];
        foreach ( array_filter( array_map( 'absint', (array) ( $in[ $key ] ?? [] ) ) ) as $id ) {
            $t = get_term( $id, $taxonomy );
            if ( $t && ! is_wp_error( $t ) ) $out[] = html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' );
        }
        return $out;
    };
    $c = [
        'post_id'    => absint( $in['post_id'] ?? 0 ),
        'image_id'   => absint( $in['image_id'] ?? 0 ),
        'title'      => sanitize_text_field( $in['title'] ?? '' ),
        'text'       => mb_substr( $text, 0, 3500 ),
        'tags'       => $names( 'post_tag', 'tag_ids' ),
        'categories' => $names( 'category', 'cat_ids' ),
    ];
    // Called without the editor's state (the Alt text review page): the saved post is the context
    if ( $c['post_id'] && $c['text'] === '' && $c['title'] === '' ) {
        $post = get_post( $c['post_id'] );
        if ( $post ) {
            $c['title'] = $post->post_title;
            $c['text']  = mb_substr( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) ) ), 0, 3500 );
            $c['tags']  = array_map( function ( $n ) { return html_entity_decode( $n, ENT_QUOTES, 'UTF-8' ); }, wp_list_pluck( wp_get_post_tags( $c['post_id'] ), 'name' ) );
            $c['categories'] = array_map( function ( $n ) { return html_entity_decode( $n, ENT_QUOTES, 'UTF-8' ); }, wp_list_pluck( get_the_category( $c['post_id'] ), 'name' ) );
        }
    }
    return $c;
}

function ms_ai_post_context_lines( array $c ) {
    $lines = [];
    if ( $c['title'] !== '' )  $lines[] = 'Post title: ' . $c['title'];
    if ( $c['categories'] )    $lines[] = 'Categories: ' . implode( ', ', $c['categories'] );
    if ( $c['tags'] )          $lines[] = 'Tags it already has: ' . implode( ', ', $c['tags'] );
    if ( $c['text'] !== '' )   $lines[] = "The post's text (written by the artist):\n" . $c['text'];
    return $lines;
}

/** One request that must answer in JSON; returns the decoded array or sends the error */
function ms_ai_post_ask( $system, array $blocks, $max_tokens ) {
    $text = ms_ai_request( $system, $blocks, $max_tokens );
    if ( is_wp_error( $text ) ) wp_send_json_error( $text->get_error_message() );
    $data = ms_ai_json( $text );
    if ( ! is_array( $data ) ) wp_send_json_error( 'Claude answered, but not in a form I could use. Try again.' );
    return $data;
}

/**
 * ===========================
 * WHAT CLAUDE IS TOLD (blog posts)
 * ===========================
 */
function ms_ai_alt_system_post() {
    return <<<'TXT'
You write alt text for pictures in blog posts on art.monospace.com, the site of the artist Hens Breet (landscapes and city scenes, mostly in acrylic, plus drawings; the blog also has photos, market and exhibition news).

Rules:
- Describe only what is visibly in the picture. Do not interpret, guess at mood or meaning, or invent places, people or stories. The post's title and text are only context to help you tell what the picture shows; the picture decides.
- Two sentences, about 200 to 300 characters in all. The first names the subject and its setting; the second adds the main colors, light, sky, foreground or other notable details.
- A painting or drawing: begin with its medium only when you can tell it ("Acrylic painting of ...", "Pen and ink drawing of ..."). A photograph: begin "Photo of ...". A flyer, sign or graphic: say what it is, then transcribe the important text exactly (dates, times, names).
- Plain, concrete, present tense. US English. Do not start with "Image of" or "Picture of". Do not mention the artist's signature. No hashtags, emoji or promotional words. Never guess the age or gender of a person.
- If you cannot tell something important, write what you can see and set "uncertain" to true.

Answer with JSON only, no other text: {"alt": "...", "uncertain": true or false}
TXT;
}

function ms_ai_tags_system_post() {
    return <<<'TXT'
You suggest tags for a blog post on art.monospace.com, the site of the artist Hens Breet. The tags drive the site's tag pages and search, so they must stay tidy.

Rules:
- Reuse tags from the list you are given, spelled exactly as in the list. This is the main job.
- Suggest at most 6 tags in all, from what the post is clearly about: subjects, places, events, the season or time of day.
- Place and event names: use one only if it is in the list or in the post's title or text. Never guess a place from a picture.
- You may suggest at most 2 new tags that are not in the list, only when something central has no tag; put them in "new". Prefer the singular, lowercase style of the list.
- Do not suggest a tag that only repeats a category you are given, and do not repeat tags the post already has.

Answer with JSON only, no other text: {"tags": ["..."], "new": ["..."]}
TXT;
}

function ms_ai_category_system() {
    return <<<'TXT'
You choose the category for a blog post on art.monospace.com. You get a numbered list of categories ("id: name"). Pick the one that fits best, or two when the post clearly belongs to both. Only ids from the list; if nothing fits, answer an empty list.

Answer with JSON only, no other text: {"ids": [123]}
TXT;
}

function ms_ai_excerpt_system() {
    return <<<'TXT'
You write the excerpt of a blog post on art.monospace.com, the site of the artist Hens Breet. The excerpt appears under the post's picture in the feed and as the description in search results.

Rules:
- One or two plain sentences, at most 160 characters in all, saying what the post is about. Use only facts that are in the post; invent nothing.
- Third person is fine; first person only if the post is plainly written that way. US English. No emoji, hashtags or promotional words. Do not repeat the title word for word.

Answer with JSON only, no other text: {"excerpt": "..."}
TXT;
}

function ms_ai_share_system() {
    return <<<'TXT'
You draft the caption the artist Hens Breet will paste when sharing a blog post from art.monospace.com on Bluesky or Facebook.

Rules:
- At most 260 characters including the link. First person, plain and warm, saying what the post shows or announces. Use only facts from the post; invent nothing.
- End with the exact text {link} where the post's address goes.
- No emoji. At most two lowercase hashtags, only if natural. US English.

Answer with JSON only, no other text: {"caption": "..."}
TXT;
}

/**
 * ===========================
 * THE CALLS
 * ===========================
 */
add_action( 'wp_ajax_ms_ai_post_alt', function () {
    ms_ai_post_guard();
    $c   = ms_ai_post_context();
    $img = ms_ai_image_block( $c['image_id'] );
    if ( is_wp_error( $img ) ) wp_send_json_error( $img->get_error_message() );
    $lines   = ms_ai_post_context_lines( $c );
    $lines[] = 'Write the alt text for this picture.';
    $data = ms_ai_post_ask( ms_ai_alt_system_post(), [ $img, [ 'type' => 'text', 'text' => implode( "\n", $lines ) ] ], 400 );
    $alt  = trim( (string) ( $data['alt'] ?? '' ) );
    if ( $alt === '' ) wp_send_json_error( 'Claude answered, but not in a form I could use. Try again.' );
    wp_send_json_success( [ 'alt' => $alt, 'uncertain' => ! empty( $data['uncertain'] ) ] );
} );

add_action( 'wp_ajax_ms_ai_post_tags', function () {
    ms_ai_post_guard();
    $c = ms_ai_post_context();
    $vocab = get_terms( [ 'taxonomy' => 'post_tag', 'orderby' => 'count', 'order' => 'DESC', 'number' => 0, 'hide_empty' => true, 'fields' => 'names' ] );
    $vocab = array_values( array_map( function ( $n ) { return html_entity_decode( $n, ENT_QUOTES, 'UTF-8' ); }, is_array( $vocab ) ? $vocab : [] ) );
    $known = [];
    foreach ( $vocab as $v ) $known[ strtolower( $v ) ] = $v;

    $blocks = [];
    if ( $c['image_id'] ) { $img = ms_ai_image_block( $c['image_id'] ); if ( ! is_wp_error( $img ) ) $blocks[] = $img; }
    $lines   = ms_ai_post_context_lines( $c );
    $lines[] = "The site's tag list: " . implode( ', ', $vocab );
    $lines[] = 'Suggest tags for this post.';
    $blocks[] = [ 'type' => 'text', 'text' => implode( "\n", $lines ) ];
    $data = ms_ai_post_ask( ms_ai_tags_system_post(), $blocks, 300 );

    $skip = array_map( 'strtolower', array_merge( $c['tags'], $c['categories'] ) );
    $tags = $new = [];
    foreach ( array_merge( (array) ( $data['tags'] ?? [] ), (array) ( $data['new'] ?? [] ) ) as $t ) {
        $t = trim( sanitize_text_field( (string) $t ) );
        $k = strtolower( $t );
        if ( $t === '' || in_array( $k, $skip, true ) ) continue;
        if ( isset( $known[ $k ] ) ) $tags[ $k ] = $known[ $k ]; else $new[ $k ] = $t;
    }
    $tags = array_slice( array_values( $tags ), 0, 6 );
    $new  = array_slice( array_values( $new ), 0, 2 );
    $out  = [];
    foreach ( $tags as $t ) { $term = get_term_by( 'name', $t, 'post_tag' ); if ( $term ) $out[] = [ 'id' => (int) $term->term_id, 'name' => $t ]; }
    wp_send_json_success( [ 'tags' => $out, 'new' => $new ] );
} );

/** The categories a blog post may use: the Site posts tree if the site has one, else every category except the artworks' tree */
function ms_ai_post_categories() {
    $all   = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => false ] );
    $all   = is_array( $all ) ? $all : [];
    $root  = get_term_by( 'slug', 'site-posts', 'category' );
    $skip  = get_term_by( 'slug', 'artworks', 'category' );
    $out   = [];
    foreach ( $all as $t ) {
        $inside = $root ? ( $t->term_id === $root->term_id || term_is_ancestor_of( $root, $t, 'category' ) ) : true;
        if ( ! $root && $skip && ( $t->term_id === $skip->term_id || term_is_ancestor_of( $skip, $t, 'category' ) ) ) $inside = false;
        if ( ! $inside || ( $root && $t->term_id === $root->term_id ) ) continue;
        $path = [ html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' ) ];
        foreach ( get_ancestors( $t->term_id, 'category' ) as $a ) {
            $p = get_term( $a, 'category' );
            if ( $p && ! is_wp_error( $p ) && ( ! $root || $p->term_id !== $root->term_id ) ) array_unshift( $path, html_entity_decode( $p->name, ENT_QUOTES, 'UTF-8' ) );
        }
        $out[ (int) $t->term_id ] = implode( ' > ', $path );
    }
    return $out;
}

add_action( 'wp_ajax_ms_ai_post_category', function () {
    ms_ai_post_guard();
    $c    = ms_ai_post_context();
    $cats = ms_ai_post_categories();
    if ( ! $cats ) wp_send_json_error( 'The site has no categories to choose from.' );
    $blocks = [];
    if ( $c['image_id'] ) { $img = ms_ai_image_block( $c['image_id'] ); if ( ! is_wp_error( $img ) ) $blocks[] = $img; }
    $list = [];
    foreach ( $cats as $id => $label ) $list[] = $id . ': ' . $label;
    $lines   = ms_ai_post_context_lines( $c );
    $lines[] = "The categories:\n" . implode( "\n", $list );
    $lines[] = 'Choose the category for this post.';
    $blocks[] = [ 'type' => 'text', 'text' => implode( "\n", $lines ) ];
    $data = ms_ai_post_ask( ms_ai_category_system(), $blocks, 150 );
    $out  = [];
    foreach ( array_slice( array_map( 'absint', (array) ( $data['ids'] ?? [] ) ), 0, 2 ) as $id ) if ( isset( $cats[ $id ] ) ) $out[] = [ 'id' => $id, 'name' => $cats[ $id ] ];
    wp_send_json_success( [ 'categories' => $out ] );
} );

add_action( 'wp_ajax_ms_ai_post_excerpt', function () {
    ms_ai_post_guard();
    $c = ms_ai_post_context();
    if ( $c['text'] === '' && $c['title'] === '' ) wp_send_json_error( 'Write something in the post first.' );
    $lines   = ms_ai_post_context_lines( $c );
    $lines[] = 'Write the excerpt for this post.';
    $data = ms_ai_post_ask( ms_ai_excerpt_system(), [ [ 'type' => 'text', 'text' => implode( "\n", $lines ) ] ], 250 );
    $ex = trim( (string) ( $data['excerpt'] ?? '' ) );
    if ( $ex === '' ) wp_send_json_error( 'Claude answered, but not in a form I could use. Try again.' );
    wp_send_json_success( [ 'excerpt' => mb_substr( $ex, 0, 220 ) ] );
} );

add_action( 'wp_ajax_ms_ai_post_share', function () {
    ms_ai_post_guard();
    $c = ms_ai_post_context();
    if ( $c['text'] === '' && $c['title'] === '' ) wp_send_json_error( 'Write something in the post first.' );
    $blocks = [];
    if ( $c['image_id'] ) { $img = ms_ai_image_block( $c['image_id'] ); if ( ! is_wp_error( $img ) ) $blocks[] = $img; }
    $lines   = ms_ai_post_context_lines( $c );
    $lines[] = 'Draft the caption.';
    $blocks[] = [ 'type' => 'text', 'text' => implode( "\n", $lines ) ];
    $data = ms_ai_post_ask( ms_ai_share_system(), $blocks, 300 );
    $cap  = trim( (string) ( $data['caption'] ?? '' ) );
    if ( $cap === '' ) wp_send_json_error( 'Claude answered, but not in a form I could use. Try again.' );
    $link = $c['post_id'] ? get_permalink( $c['post_id'] ) : '';
    wp_send_json_success( [ 'caption' => $link ? str_replace( '{link}', $link, $cap ) : $cap, 'has_link' => (bool) $link ] );
} );

/** Find an artwork to put a card of in the post: by title words or by its number (the SKU's last digits). No AI. */
add_action( 'wp_ajax_ms_post_find_artwork', function () {
    ms_ai_post_guard();
    $q = trim( sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) ) );
    if ( $q === '' ) wp_send_json_success( [ 'items' => [] ] );
    $args = [ 'post_type' => 'post', 'post_status' => [ 'publish', 'draft', 'private' ], 'posts_per_page' => 8, 'meta_query' => [ [ 'key' => '_qp_product_id', 'compare' => 'EXISTS' ] ] ];
    if ( ctype_digit( $q ) ) {
        global $wpdb;
        $like = '%-' . str_pad( $q, 4, '0', STR_PAD_LEFT );
        $pids = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s LIMIT 8", $like ) );
        $args['meta_query'] = [ [ 'key' => '_qp_product_id', 'value' => $pids ?: [ 0 ], 'compare' => 'IN' ] ];
    } else {
        $args['s'] = $q;
    }
    $items = [];
    foreach ( get_posts( $args ) as $p ) {
        $product = function_exists( 'wc_get_product' ) ? wc_get_product( (int) get_post_meta( $p->ID, '_qp_product_id', true ) ) : null;
        $sku = $product ? (string) $product->get_sku() : '';
        if ( ! preg_match( '/(\d+)$/', $sku, $m ) ) continue;
        $items[] = [ 'number' => (int) $m[1], 'title' => html_entity_decode( get_the_title( $p ), ENT_QUOTES, 'UTF-8' ), 'thumb' => (string) get_the_post_thumbnail_url( $p, 'thumbnail' ) ];
    }
    wp_send_json_success( [ 'items' => $items ] );
} );

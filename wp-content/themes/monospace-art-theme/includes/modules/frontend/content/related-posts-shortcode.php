<?php
/**
 * Related Posts Shortcode
 *
 * Displays a grid of related posts based on shared tags with the current post.
 * Shows thumbnails and titles in a responsive 4-column grid.
 *
 * SHORTCODE USAGE:
 * [related_posts]
 *
 * FUNCTIONALITY:
 * - Finds posts that share tags with the current post
 * - Displays up to 8 related posts
 * - Shows featured image thumbnail and post title for each
 * - Responsive grid: 4 columns on desktop, 2 on tablet/mobile
 * - Only appears on single post pages
 *
 * DISPLAY:
 * - Section heading: "Related Posts"
 * - Grid of clickable post thumbnails
 * - Thumbnails are square (1:1 aspect ratio)
 * - Styled with .monospace-related-posts CSS class
 *
 * ALGORITHM:
 * - Queries posts with matching tags
 * - Excludes current post
 * - Orders by publish date (newest first)
 * - No caching - always shows current data
 *
 * EDITOR (2026-10-26, owner: "I want an editor for the Related Posts functionality"): Appearance > Look > Related posts
 * (module below, option monospace_related) sets the heading, how many are shown, how many tags a post must share, grid or
 * stack (columns), and what each item shows - each item is the [artwork] shortcode (price-card-standalone.php), so it has its
 * display options: title, info, availability, prints, square, align. The pictures follow the global Rollover effect (the Art
 * Feed place: they are .ms-archive-image-link). The shortcode takes the same settings as attributes, e.g.
 * [related_posts limit="6" min_tags="2" layout="stack" title="More like this" info="yes"].
 *
 * @package astra-child-theme-for-monospace-art
 */

/** The defaults: as the block was before it had an editor (4 square pictures with a title, 5 shared tags) */
function monospace_related_defaults() {
    return [
        'heading' => 'See also:', 'limit' => 4, 'min_tags' => 5, 'fill' => 'yes',
        'layout' => 'grid', 'columns' => 4,
        'title' => 'yes', 'info' => 'no', 'availability' => 'no', 'prints' => 'no', 'badges' => 'no', 'square' => 'yes', 'align' => 'left',
    ];
}

/** Clean up settings (the saved option, or a shortcode's attributes over it) */
function monospace_related_clean( $in, $base = null ) {
    $d   = $base ?: monospace_related_defaults();
    $in  = (array) $in;
    $out = $d;
    if ( isset( $in['heading'] ) ) $out['heading'] = mb_substr( sanitize_text_field( $in['heading'] ), 0, 120 );
    foreach ( [ 'limit' => [ 1, 24 ], 'min_tags' => [ 0, 20 ], 'columns' => [ 1, 6 ] ] as $k => [ $lo, $hi ] ) {
        if ( isset( $in[ $k ] ) && is_numeric( $in[ $k ] ) ) $out[ $k ] = max( $lo, min( $hi, (int) $in[ $k ] ) );
    }
    foreach ( [ 'fill', 'title', 'info', 'availability', 'prints', 'badges', 'square' ] as $k ) {
        if ( isset( $in[ $k ] ) && in_array( strtolower( (string) $in[ $k ] ), [ 'yes', 'no' ], true ) ) $out[ $k ] = strtolower( $in[ $k ] );
    }
    if ( isset( $in['layout'] ) && in_array( $in['layout'], [ 'grid', 'stack' ], true ) ) $out['layout'] = $in['layout'];
    if ( isset( $in['align'] ) && in_array( $in['align'], [ 'left', 'center', 'right' ], true ) ) $out['align'] = $in['align'];
    return $out;
}

function monospace_related_settings() {
    $s = get_option( 'monospace_related', [] );
    return monospace_related_clean( is_array( $s ) ? $s : [] );
}

/**
 * Display related posts for the current post.
 *
 * @since 1.0.0
 * @return string HTML markup of related posts or empty string.
 */
function monospace_related_posts_display( $atts = [] ) {
    global $post;

    if ( ! is_singular( 'post' ) || ! $post ) {
        return '';
    }

    $post_id = $post->ID;

    // Get settings: the editor's, with the shortcode's attributes over them
    $settings = apply_filters( 'monospace_related_posts_settings', array_merge( monospace_related_clean( (array) $atts, monospace_related_settings() ), array(
        'fallback_image_url' => get_stylesheet_directory_uri() . '/images/fallback-thumb.jpg',
        'exclude_categories' => array( 'blog' ),
    ) ), $post_id );
    $settings['columns_set'] = isset( $atts['columns'] ); // an explicit columns="" also counts in the narrow sidebar
    if ( isset( $atts['title'] ) && ! in_array( strtolower( (string) $atts['title'] ), [ 'yes', 'no' ], true ) ) {
        $settings['heading'] = sanitize_text_field( $atts['title'] ); // title="More like this" = the heading; title="yes" / "no" = the items' titles
    }

    // Get related posts
    $related_ids = monospace_related_get_post_ids( $post_id, $settings );

    if ( empty( $related_ids ) ) {
        return '';
    }

    // Build HTML output
    return monospace_related_build_html( $related_ids, $settings );
}

/**
 * Get IDs of related posts.
 */
function monospace_related_get_post_ids( $post_id, $settings ) {
    global $wpdb;

    $limit    = absint( $settings['limit'] );
    $min_tags = absint( $settings['min_tags'] );

    $tags = wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'ids' ) );

    $fill = ( $settings['fill'] ?? 'yes' ) !== 'no'; // pad with random posts when too few share enough tags

    if ( empty( $tags ) ) {
        return $fill ? monospace_related_get_fallback_posts( $post_id, $settings, $limit ) : array();
    }

    $exclude_cat_ids = monospace_related_get_excluded_category_ids( $settings['exclude_categories'] );

    $tag_ids_str = implode( ',', array_map( 'absint', $tags ) );
    $exclude_cats_str = ! empty( $exclude_cat_ids )
        ? implode( ',', array_map( 'absint', $exclude_cat_ids ) )
        : '0';

    $sql = "
        SELECT p.ID, COUNT(tr.term_taxonomy_id) as shared_tags
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
        WHERE p.post_type = 'post'
            AND p.post_status = 'publish'
            AND p.ID != %d
            AND tt.taxonomy = 'post_tag'
            AND tt.term_id IN ($tag_ids_str)
    ";

    if ( ! empty( $exclude_cat_ids ) ) {
        $sql .= "
            AND p.ID NOT IN (
                SELECT object_id FROM {$wpdb->term_relationships} tr2
                INNER JOIN {$wpdb->term_taxonomy} tt2 ON tr2.term_taxonomy_id = tt2.term_taxonomy_id
                WHERE tt2.taxonomy = 'category' AND tt2.term_id IN ($exclude_cats_str)
            )
        ";
    }

    $sql .= "
        GROUP BY p.ID
        HAVING shared_tags >= %d
        ORDER BY shared_tags DESC, RAND()
        LIMIT %d
    ";

    $results = $wpdb->get_results(
        $wpdb->prepare( $sql, $post_id, $min_tags, $limit ),
        ARRAY_A
    );

    $related_ids = $results ? wp_list_pluck( $results, 'ID' ) : array();

    $remaining = $limit - count( $related_ids );
    if ( $remaining > 0 && $fill ) {
        $fallback_ids = monospace_related_get_fallback_posts( $post_id, $settings, $remaining, $related_ids );
        $related_ids = array_merge( $related_ids, $fallback_ids );
    }

    return array_slice( $related_ids, 0, $limit );
}

/**
 * Get random fallback posts.
 */
function monospace_related_get_fallback_posts( $post_id, $settings, $limit, $exclude = array() ) {
    $exclude_cat_ids = monospace_related_get_excluded_category_ids( $settings['exclude_categories'] );
    $exclude = array_merge( array( $post_id ), $exclude );

    $args = array(
        'post_type'      => 'post',
        'posts_per_page' => $limit,
        'post__not_in'   => $exclude,
        'orderby'        => 'rand',
        'fields'         => 'ids',
    );

    if ( ! empty( $exclude_cat_ids ) ) {
        $args['category__not_in'] = $exclude_cat_ids;
    }

    $query = new WP_Query( $args );
    return $query->posts;
}

/**
 * Convert category slugs to term IDs.
 */
function monospace_related_get_excluded_category_ids( $category_slugs ) {
    if ( empty( $category_slugs ) ) {
        return array();
    }

    $cat_ids = array();
    foreach ( (array) $category_slugs as $slug ) {
        $cat = get_category_by_slug( $slug );
        if ( $cat ) {
            $cat_ids[] = $cat->term_id;
        }
    }

    return $cat_ids;
}

/**
 * Build HTML for related posts: each item is the [artwork] shortcode with the editor's display options (the old
 * thumbnail + title when an item has no artwork picture or the shortcode isn't there).
 */
function monospace_related_build_html( $related_ids, $settings ) {
    $stack = ( $settings['layout'] ?? 'grid' ) === 'stack'; // each item: the picture on the left, its text on the right; the columns apply to both
    $cols  = max( 1, min( 6, (int) ( $settings['columns'] ?? 4 ), count( $related_ids ) ) ); // never more columns than pictures: no empty space on the right
    $output = '<div class="monospace-related-posts' . ( $stack ? ' is-stack' : '' ) . '">';
    if ( ( $settings['heading'] ?? 'See also:' ) !== '' ) $output .= '<h3 class="monospace-related-heading">' . esc_html( $settings['heading'] ) . '</h3>';
    $output .= '<div class="monospace-related-grid" style="--rel-cols:' . $cols . ';--rel-cols-t:' . min( $cols, 3 ) . ( ! empty( $settings['columns_set'] ) ? ';--rel-cols-s:' . $cols : '' ) . '">';

    foreach ( $related_ids as $related_id ) {
        $item = shortcode_exists( 'artwork' ) ? do_shortcode( sprintf( '[artwork post="%d" title="%s" info="%s" availability="%s" prints="%s" badges="%s" square="%s" align="%s"]',
            $related_id, $settings['title'], $settings['info'], $settings['availability'], $settings['prints'], $settings['badges'], $settings['square'], $settings['align'] ) ) : '';

        $output .= '<div class="monospace-related-item">';
        if ( $item !== '' ) {
            $output .= $item;
        } else {
            $link  = get_permalink( $related_id );
            $title = get_the_title( $related_id );
            $thumb = monospace_related_get_thumbnail( $related_id, $title, $settings['fallback_image_url'] );
            $output .= sprintf( '<a href="%s" class="monospace-related-link">', esc_url( $link ) );
            $output .= '<div class="monospace-related-thumb">' . $thumb . '</div>';
            if ( $settings['title'] !== 'no' ) $output .= sprintf( '<span class="monospace-related-title">%s</span>', esc_html( $title ) );
            $output .= '</a>';
        }
        $output .= '</div>';
    }

    $output .= '</div></div>';

    return apply_filters( 'monospace_related_posts_html', $output, $related_ids );
}

/**
 * Get post thumbnail or fallback.
 */
function monospace_related_get_thumbnail( $post_id, $title, $fallback_url ) {
    if ( has_post_thumbnail( $post_id ) ) {
        return get_the_post_thumbnail( $post_id, 'medium', array( 'class' => 'monospace-related-img nolightbox', 'data-nolightbox' => '1' ) );
    }

    $content_image = monospace_related_get_first_content_image( $post_id );
    if ( $content_image ) {
        return sprintf(
            '<img src="%s" alt="%s" class="monospace-related-img nolightbox" data-nolightbox="1" loading="lazy" />',
            esc_url( $content_image ),
            esc_attr( $title )
        );
    }

    // No picture at all: the art placeholder (2026-10-23), else the configured fallback picture
    if ( function_exists( 'monospace_placeholder_image_id' ) && ( $placeholder = monospace_placeholder_image_id() ) ) {
        return wp_get_attachment_image( $placeholder, 'medium', false, array( 'class' => 'monospace-related-img nolightbox', 'data-nolightbox' => '1', 'alt' => $title, 'loading' => 'lazy' ) );
    }
    return sprintf(
        '<img src="%s" alt="No image" class="monospace-related-img nolightbox" data-nolightbox="1" loading="lazy" />',
        esc_url( $fallback_url )
    );
}

/**
 * Get first image from post content.
 */
function monospace_related_get_first_content_image( $post_id ) {
    $content = get_post_field( 'post_content', $post_id );
    if ( empty( $content ) ) {
        return false;
    }

    if ( class_exists( 'DOMDocument' ) ) {
        $dom = new DOMDocument();
        libxml_use_internal_errors( true );
        $dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) );
        libxml_clear_errors();

        $images = $dom->getElementsByTagName( 'img' );
        if ( $images->length > 0 ) {
            $src = $images->item(0)->getAttribute( 'src' );
            if ( $src ) {
                return $src;
            }
        }
    } else {
        preg_match( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );
        if ( ! empty( $matches[1] ) ) {
            return $matches[1];
        }
    }

    return false;
}



// Shortcode
add_shortcode( 'related_posts', 'monospace_related_posts_display' );


/* ---------- Appearance > Look > Related posts ---------- */

add_filter( 'monospace_design_modules', function ( $modules ) {
    $modules['related-posts'] = [
        'title'       => 'Related posts',
        'description' => 'The “See also” pictures under an artwork: the heading, how many, how many tags they must share, grid or stack, and what each shows.',
        'group'       => 'look',
        'order'       => 43,
        'summary'     => function () {
            $s = monospace_related_settings();
            return $s['limit'] . ' · ' . ( $s['layout'] === 'stack' ? 'stack' : $s['columns'] . ' columns' ) . ' · ' . $s['min_tags'] . '+ shared tags';
        },
        'render'      => 'monospace_render_related_settings',
        'save'        => 'monospace_save_related_settings',
    ];
    return $modules;
} );

function monospace_render_related_settings() {
    $s  = monospace_related_settings();
    $rb = function ( $name, $choices ) use ( $s ) {
        foreach ( $choices as $k => $label ) echo '<label style="margin-right:16px"><input type="radio" name="related[' . esc_attr( $name ) . ']" value="' . esc_attr( $k ) . '" ' . checked( $s[ $name ], $k, false ) . '> ' . esc_html( $label ) . '</label>';
    };
    $cb = function ( $name, $label ) use ( $s ) {
        echo '<label style="margin-right:16px"><input type="hidden" name="related[' . esc_attr( $name ) . ']" value="no"><input type="checkbox" name="related[' . esc_attr( $name ) . ']" value="yes" ' . checked( $s[ $name ], 'yes', false ) . '> ' . esc_html( $label ) . '</label>';
    }; ?>
    <table class="form-table" role="presentation">
        <tr><th scope="row"><label for="ms-rel-heading">Heading</label></th><td>
            <input type="hidden" name="related[seen]" value="1">
            <input type="text" id="ms-rel-heading" name="related[heading]" value="<?php echo esc_attr( $s['heading'] ); ?>" class="regular-text">
            <p class="description">Above the pictures. Empty = no heading.</p></td></tr>
        <tr><th scope="row"><label for="ms-rel-limit">How many</label></th><td>
            <input type="number" id="ms-rel-limit" name="related[limit]" value="<?php echo (int) $s['limit']; ?>" min="1" max="24" class="small-text"> posts shown</td></tr>
        <tr><th scope="row"><label for="ms-rel-tags">Matching tags</label></th><td>
            A post must share at least <input type="number" id="ms-rel-tags" name="related[min_tags]" value="<?php echo (int) $s['min_tags']; ?>" min="0" max="20" class="small-text"> tags with this one
            <p class="description">The posts with the most shared tags come first.</p>
            <p><?php $cb( 'fill', 'When too few match, fill up with random posts' ); ?></p></td></tr>
        <tr><th scope="row">Layout</th><td>
            <?php $rb( 'layout', [ 'grid' => 'Grid', 'stack' => 'Stack (picture on the left, its text on the right)' ] ); ?>
            <p>Columns (grid): <select name="related[columns]"><?php for ( $n = 1; $n <= 6; $n++ ) echo '<option value="' . $n . '" ' . selected( $s['columns'], $n, false ) . '>' . $n . '</option>'; ?></select>
            <span class="description">Both layouts; tablets and phones show at most 3.</span></p></td></tr>
        <tr><th scope="row">Each one shows</th><td>
            <?php $cb( 'title', 'Title' ); $cb( 'info', 'Medium and size' ); $cb( 'availability', 'Availability and price' ); $cb( 'prints', 'Prints available' ); ?>
            <p><?php $cb( 'badges', 'Badges on the picture (green dot = available, “M” = miniature)' ); ?></p>
            <p><?php $cb( 'square', 'A square thumbnail (otherwise the picture as it is)' ); ?></p>
            <p>Text under the picture: <?php $rb( 'align', [ 'left' => 'Left', 'center' => 'Center', 'right' => 'Right' ] ); ?></p>
            <p class="description">The same display options as the <code>[artwork]</code> shortcode, and the same rollover as the other artwork pictures (Rollover effect &gt; Art Feed). Also as shortcode options: <code>[related_posts limit="6" layout="stack" title="More like this"]</code>.</p></td></tr>
    </table>
    <?php
}

function monospace_save_related_settings() {
    if ( empty( $_POST['related']['seen'] ) ) return;
    $in = wp_unslash( (array) $_POST['related'] );
    $s  = monospace_related_clean( $in );
    $s == monospace_related_defaults() ? delete_option( 'monospace_related' ) : update_option( 'monospace_related', $s );
}

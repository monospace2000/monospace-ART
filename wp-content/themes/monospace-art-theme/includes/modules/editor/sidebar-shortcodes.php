<?php
/**
 * Monospace Shortcodes Library
 * Common sidebar elements as shortcodes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** A yes / no option: "true", "yes", "1", "on" = yes - "false" / "no" / "0" = no (2026-10-21: any text counted as yes, so
 *  show_date="false" showed the dates and hide_empty="false" didn't show empty categories). Every option: Layout Manager >
 *  Shortcodes (layout-manager.php). */
function monospace_shortcode_bool( $value ) {
    if ( is_bool( $value ) ) return $value;
    return in_array( strtolower( trim( (string) $value ) ), [ 'true', 'yes', '1', 'on' ], true );
}

/* ============================================================
 * SEARCH FORM
 * Usage: [search_form]
 * ============================================================ */

add_shortcode( 'search_form', function( $atts ) {
    $atts = shortcode_atts( [
        'placeholder' => 'Search...',
        'button_text' => 'Search',
    ], $atts );

    ob_start();
    ?>
    <form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
        <label>
            <span class="screen-reader-text">Search for:</span>
            <input type="search" class="search-field" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" 
                   value="<?php echo get_search_query(); ?>" name="s" />
        </label>
        <button type="submit" class="search-submit"><?php echo esc_html( $atts['button_text'] ); ?></button>
    </form>
    <?php
    return ob_get_clean();
});

/* ============================================================
 * ARTWORK TAGS (2026-11-11, owner: "I don't really want blog posts to show up in the tag clouds")
 * Usage: [artwork_tags] or [artwork_tags taxonomy="post_tag" number="45" orderby="count" order="DESC"]
 * The site's own tag cloud (WordPress's wp_tag_cloud counts every post). It counts and sizes by artwork posts only - posts linked to
 * a product - so a blog post or newsletter can carry tags without adding to the cloud, and a tag used only on blog posts is left
 * out. who="all" counts every published post instead. Works for any taxonomy of posts (post_tag, category, place...).
 * [tag_cloud] is the old name and still works (it is the same shortcode). Every option: Layout Manager > Shortcodes.
 * ============================================================ */

/** How many artwork posts (who = artwork) or all published posts each term of a taxonomy has: [ term_taxonomy_id => count ] */
function monospace_artwork_term_counts( $taxonomy, $who = 'artwork' ) {
    global $wpdb;
    $key    = 'counts:' . $taxonomy . ':' . $who . ':' . wp_cache_get_last_changed( 'posts' );
    $cached = wp_cache_get( $key, 'monospace_artwork_tags' );
    if ( is_array( $cached ) ) return $cached;

    $join = $who === 'all' ? '' : "JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_qp_product_id' AND m.meta_value + 0 > 0";
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT tt.term_id, COUNT(DISTINCT p.ID) AS n
         FROM {$wpdb->term_taxonomy} tt
         JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
         JOIN {$wpdb->posts} p ON p.ID = tr.object_id AND p.post_type = 'post' AND p.post_status = 'publish'
         $join
         WHERE tt.taxonomy = %s
         GROUP BY tt.term_id",
        $taxonomy
    ) );
    $counts = [];
    foreach ( (array) $rows as $row ) $counts[ (int) $row->term_id ] = (int) $row->n;

    wp_cache_set( $key, $counts, 'monospace_artwork_tags', HOUR_IN_SECONDS );
    return $counts;
}

function monospace_artwork_tags_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'taxonomy' => 'post_tag',
        'number'   => 45,
        'smallest' => 8,
        'largest'  => 22,
        'unit'     => 'pt',
        'orderby'  => 'count',
        'order'    => 'DESC',
        'who'      => 'artwork',
    ], $atts );

    $taxonomy = sanitize_key( $atts['taxonomy'] );
    if ( ! taxonomy_exists( $taxonomy ) ) return '';
    $who      = strtolower( trim( $atts['who'] ) ) === 'all' ? 'all' : 'artwork';
    $number   = max( 0, (int) $atts['number'] );
    $unit     = in_array( $atts['unit'], [ 'pt', 'px', 'em', '%' ], true ) ? $atts['unit'] : 'pt';
    $smallest = (float) $atts['smallest'];
    $largest  = (float) $atts['largest'];
    $order    = strtoupper( trim( $atts['order'] ) );

    $counts = monospace_artwork_term_counts( $taxonomy, $who );
    if ( ! $counts ) return '';

    // The most used first, cut to the number asked; only then sorted as asked
    arsort( $counts );
    if ( $number ) $counts = array_slice( $counts, 0, $number, true );
    $terms = get_terms( [ 'taxonomy' => $taxonomy, 'include' => array_keys( $counts ), 'hide_empty' => false ] );
    if ( is_wp_error( $terms ) || ! $terms ) return '';

    if ( $atts['orderby'] === 'name' ) {
        usort( $terms, fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );
        if ( $order === 'DESC' ) $terms = array_reverse( $terms );
    } else {
        usort( $terms, fn( $a, $b ) => $counts[ $b->term_id ] <=> $counts[ $a->term_id ] ?: strcasecmp( $a->name, $b->name ) );
        if ( $order === 'ASC' ) $terms = array_reverse( $terms );
    }
    if ( $order === 'RAND' ) shuffle( $terms );

    // Size by the number of artworks, on a log scale (a few very big tags don't flatten the rest)
    $scale = fn( $n ) => log10( $n + 1 ) * 100;
    $min   = $scale( min( $counts ) );
    $max   = $scale( max( $counts ) );
    $step  = $max > $min ? ( $largest - $smallest ) / ( $max - $min ) : 0;

    $out = [];
    foreach ( $terms as $i => $term ) {
        $link = get_term_link( $term );
        if ( is_wp_error( $link ) ) continue;
        $n    = $counts[ $term->term_id ];
        $size = $smallest + ( $scale( $n ) - $min ) * $step;
        $out[] = sprintf(
            '<a href="%s" class="tag-cloud-link tag-link-%d tag-link-position-%d" style="font-size: %s%s;" aria-label="%s" title="%s">%s</a>',
            esc_url( $link ), $term->term_id, $i + 1, esc_attr( round( $size, 2 ) ), esc_attr( $unit ),
            esc_attr( sprintf( _n( '%1$s (%2$d item)', '%1$s (%2$d items)', $n ), $term->name, $n ) ),
            // The hover hint (2026-11-12, owner's wording)
            esc_attr( sprintf( $taxonomy === 'post_tag' ? 'See all items tagged with %s' : 'See all items in %s', $term->name ) ),
            esc_html( $term->name )
        );
    }
    return implode( "\n", $out );
}
add_shortcode( 'artwork_tags', 'monospace_artwork_tags_shortcode' );
add_shortcode( 'tag_cloud', 'monospace_artwork_tags_shortcode' ); // the old name

/* ============================================================
 * RECENT POSTS
 * Usage: [recent_posts] or [recent_posts number="5" show_date="true"]
 * ============================================================ */

add_shortcode( 'recent_posts', function( $atts ) {
    $atts = shortcode_atts( [
        'number'    => 5,
        'show_date' => false,
        'show_excerpt' => false,
        'excerpt_length' => 20,
    ], $atts );

    $recent_posts = wp_get_recent_posts( [
        'numberposts' => $atts['number'],
        'post_status' => 'publish',
    ] );

    if ( empty( $recent_posts ) ) {
        return '<p>No recent posts found.</p>';
    }

    ob_start();
    echo '<ul class="recent-posts-list">';
    foreach ( $recent_posts as $post ) {
        echo '<li>';
        echo '<a href="' . get_permalink( $post['ID'] ) . '">' . esc_html( $post['post_title'] ) . '</a>';
        
        if ( monospace_shortcode_bool( $atts['show_date'] ) ) {
            echo '<span class="post-date"> - ' . get_the_date( '', $post['ID'] ) . '</span>';
        }
        
        if ( monospace_shortcode_bool( $atts['show_excerpt'] ) ) {
            $excerpt = wp_trim_words( $post['post_content'], $atts['excerpt_length'] );
            echo '<p class="post-excerpt">' . esc_html( $excerpt ) . '</p>';
        }
        
        echo '</li>';
    }
    echo '</ul>';
    
    return ob_get_clean();
});

/* ============================================================
 * NAVIGATION MENU
 * Usage: [nav_menu menu="primary"] or [nav_menu menu="footer-menu"]
 * ============================================================ */

add_shortcode( 'nav_menu', function( $atts ) {
    $atts = shortcode_atts( [
        'menu'       => '',
        'container'  => 'nav',
        'menu_class' => 'shortcode-menu',
    ], $atts );

    if ( empty( $atts['menu'] ) ) {
        return '<p>Please specify a menu name.</p>';
    }

    ob_start();
    wp_nav_menu( [
        'menu'       => $atts['menu'],
        'container'  => $atts['container'],
        'menu_class' => $atts['menu_class'],
        'fallback_cb' => false,
    ] );
    return ob_get_clean();
});

/* ============================================================
 * CATEGORY LIST
 * Usage: [category_list] or [category_list show_count="true"]
 * ============================================================ */

add_shortcode( 'category_list', function( $atts ) {
    $atts = shortcode_atts( [
        'show_count' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
        'hide_empty' => true,
    ], $atts );

    ob_start();
    echo '<ul class="category-list">';
    wp_list_categories( [
        'show_count' => monospace_shortcode_bool( $atts['show_count'] ),
        'orderby'    => $atts['orderby'],
        'order'      => $atts['order'],
        'hide_empty' => monospace_shortcode_bool( $atts['hide_empty'] ),
        'title_li'   => '',
    ] );
    echo '</ul>';
    return ob_get_clean();
});

/* ============================================================
 * ARCHIVES LIST
 * Usage: [archives_list] or [archives_list type="monthly" limit="12"]
 * ============================================================ */

add_shortcode( 'archives_list', function( $atts ) {
    $atts = shortcode_atts( [
        'type'       => 'monthly',
        'limit'      => '',
        'show_count' => false,
    ], $atts );

    ob_start();
    echo '<ul class="archives-list">';
    wp_get_archives( [
        'type'       => $atts['type'],
        'limit'      => $atts['limit'],
        'show_post_count' => monospace_shortcode_bool( $atts['show_count'] ),
    ] );
    echo '</ul>';
    return ob_get_clean();
});
<?php
/**
 * Monospace Shortcodes Library
 * Common sidebar elements as shortcodes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
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
 * TAG CLOUD
 * Usage: [tag_cloud] or [tag_cloud taxonomy="post_tag" number="45"]
 * ============================================================ */

add_shortcode( 'tag_cloud', function( $atts ) {
    $atts = shortcode_atts( [
        'taxonomy' => 'post_tag',
        'number'   => 45,
        'smallest' => 8,
        'largest'  => 22,
        'unit'     => 'pt',
        'orderby'  => 'count',
        'order'    => 'DESC',
    ], $atts );

    ob_start();
    wp_tag_cloud( [
        'taxonomy' => $atts['taxonomy'],
        'number'   => $atts['number'],
        'smallest' => $atts['smallest'],
        'largest'  => $atts['largest'],
        'unit'     => $atts['unit'],
        'orderby'  => $atts['orderby'],
        'order'    => $atts['order'],
    ] );
    return ob_get_clean();
});

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
        
        if ( $atts['show_date'] ) {
            echo '<span class="post-date"> - ' . get_the_date( '', $post['ID'] ) . '</span>';
        }
        
        if ( $atts['show_excerpt'] ) {
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
        'show_count' => $atts['show_count'],
        'orderby'    => $atts['orderby'],
        'order'      => $atts['order'],
        'hide_empty' => $atts['hide_empty'],
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
        'show_post_count' => $atts['show_count'],
    ] );
    echo '</ul>';
    return ob_get_clean();
});
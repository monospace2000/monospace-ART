<?php

/**
 * Shortcode: [post_nav_buttons]
 * Renders newer (left) and older (right) post navigation buttons
 * ONLY on single blog posts
 */
function monospace_post_nav_shortcode() {

    // Hard stop: only single posts, never pages or CPTs
    if ( ! is_singular('post') ) {
       // return '';
    }

    $newer = get_next_post_link(
        '%link',
        '◂ NEWER&nbsp;'
    );

    $older = get_previous_post_link(
        '%link',
        '&nbsp;OLDER ▸'
    );

    // Hover hint (2026-11-12): the title of the newer / older post
    $hint = function ( $link, $previous ) {
        if ( ! $link || ! class_exists( 'WP_HTML_Tag_Processor' ) ) return $link;
        $adjacent = get_adjacent_post( false, '', $previous );
        if ( ! $adjacent ) return $link;
        $html = new WP_HTML_Tag_Processor( $link );
        if ( $html->next_tag( 'a' ) ) $html->set_attribute( 'title', html_entity_decode( wp_strip_all_tags( get_the_title( $adjacent ) ), ENT_QUOTES, 'UTF-8' ) );
        return $html->get_updated_html();
    };
    $newer = $hint( $newer, false );
    $older = $hint( $older, true );

    // If no navigation exists, output nothing
    if ( ! $newer && ! $older ) {
        return '';
    }

    ob_start();
    ?>
    <nav class="post-nav-buttons" aria-label="Post navigation">
        <div class="post-nav-left">
            <?php echo $newer ?: ''; ?>
        </div>
        <div class="post-nav-right">
            <?php echo $older ?: ''; ?>
        </div>
    </nav>
    <?php

    return ob_get_clean();
}
add_shortcode('post_nav_buttons', 'monospace_post_nav_shortcode');

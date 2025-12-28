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
        '◂ NEWER'
    );

    $older = get_previous_post_link(
        '%link',
        'OLDER ▸'
    );

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

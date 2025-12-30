<?php get_header(); ?>

<div class="two-column-layout">

    <!-- Main Content -->
    <main id="main-content"
          <?php if (is_home() || is_front_page() || is_archive()) : ?>
          data-infinite-scroll
          <?php if (get_next_posts_link()) : ?>data-next-page="<?php echo esc_url(get_next_posts_page_link()); ?>"<?php endif; ?>
          <?php endif; ?>>

        <?php
        // Render title ONLY on pages
        if (is_page() || is_product()) :
        ?>
            <h1 class="entry-title"><?php the_title(); ?></h1>
        <?php endif; ?>

        <?php if (have_posts()) : ?>
            <?php while (have_posts()) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('archive-item'); ?>>

                    <?php
                    if (!is_single()) {
                        // Get the post content
                        $content = get_the_content();

/*                         // Wrap all <img> tags in links to the post without breaking block structure
                        $content = preg_replace_callback(
                            '/<img([^>]+)>/i',
                            function ($matches) {
                                $img_tag = $matches[0];
                                // Don't double-wrap if already inside a link
                                if (preg_match('/<a[^>]*>.*<\/a>/i', $img_tag)) {
                                    return $img_tag;
                                }
                                return '<a href="' . get_permalink() . '" class="archive-item-link">' . $img_tag . '</a>';
                            },
                            $content
                        );
 */
                        echo apply_filters('the_content', $content);
                    } else {
                        // Single post: normal content
                        the_content();
                    }
                    ?>

                </article>

            <?php endwhile; ?>
        <?php else : ?>
            <p>No posts found.</p>
        <?php endif; ?>

    </main>

    <!-- Right Sidebar -->
    <aside id="right-sidebar" class="sidebar">
        <?php get_sidebar(); ?>
    </aside>

</div>

<!-- Pagination - Hidden on infinite scroll pages, shown on single posts/pages -->
<?php if (!is_home() && !is_front_page() && !is_archive()) : ?>
<div class="pagination">
    <?php
    the_posts_pagination(array(
        'mid_size'  => 2,
        'prev_text' => __('« Previous', 'monospace-art-theme'),
        'next_text' => __('Next »', 'monospace-art-theme'),
    ));
    ?>
</div>
<?php endif; ?>

<?php get_footer(); ?>
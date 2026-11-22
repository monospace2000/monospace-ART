<?php get_header(); ?>

<?php if (function_exists('monospace_render_top_content')) monospace_render_top_content(); ?>

<div class="two-column-layout">

    <!-- Main Content -->
    <main id="main-content"
          <?php if (is_home() || is_front_page() || is_archive()) : ?>
          data-infinite-scroll
          <?php if (get_next_posts_link()) : ?>data-next-page="<?php echo esc_url(get_next_posts_page_link()); ?>"<?php endif; ?>
          <?php endif; ?>>

        <?php
        // Render title ONLY on pages (unless hidden via meta)
        if (is_page() || is_product()) :
            if (!monospace_should_hide_title()) :
                // A page's sharing icons sit on the title's row, on the right (design/share-button.php, Look > Sharing icons)
                $share_icons = is_page() && function_exists('monospace_share_page_html') ? monospace_share_page_html() : '';
        ?>
            <?php if ($share_icons) : ?><div class="entry-title-row"><?php endif; ?>
            <h1 class="entry-title"><?php the_title(); ?></h1>
            <?php if ($share_icons) : echo $share_icons; ?></div><?php endif; ?>
        <?php
            // Title hidden: the page's own heading block is its <h1> - if it has none, a hidden one
            elseif (stripos(get_post_field('post_content', get_queried_object_id()), '<h1') === false) :
        ?>
            <h1 class="screen-reader-text"><?php the_title(); ?></h1>
        <?php
            endif;
        endif;
        ?>

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
        <?php elseif (!function_exists('monospace_archive_tools_empty') || !monospace_archive_tools_empty()) : ?>
            <h3 style="margin-left:0.5rem;">No posts found.</h3>
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
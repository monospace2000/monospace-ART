<article id="post-<?php the_ID(); ?>" <?php post_class('archive-item'); ?>>

    <?php if (has_post_thumbnail()) : ?>
        <a href="<?php the_permalink(); ?>">
            <?php the_post_thumbnail('medium', array('class' => 'archive-thumb')); ?>
        </a>
    <?php endif; ?>

    <h2 class="archive-item-title">
        <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
    </h2>

    <div class="archive-item-excerpt">
        <?php the_excerpt(); ?>
    </div>

</article>

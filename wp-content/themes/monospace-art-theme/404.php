<?php get_header(); ?>

<div class="two-column-layout">

    <!-- Main Content -->
    <main id="main-content">

        <h1 class="entry-title">404 - Page Not Found</h1>

        <article class="error-404">
            <div class="entry-content">
                <p>Sorry, the page you're looking for doesn't exist or has been moved.</p>

                <p><a href="<?php echo esc_url(home_url('/')); ?>">Return to homepage</a></p>
            </div>
        </article>

    </main>

    <!-- Right Sidebar -->
    <aside id="right-sidebar" class="sidebar">
        <?php get_sidebar(); ?>
    </aside>

</div>

<?php get_footer(); ?>

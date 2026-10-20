<?php
/**
 * Template Name: Start Page
 * Description: Static intro / splash page for the front of the site.
 */
?>

<?php get_header() ?>


<?php // Welcome, News and the link boxes: all from Layout Manager > Home Page ?>


<div class="container">

<div class="search-bar" style="width: 100%; display: flex; justify-content: flex-end; padding: 0px 0; margin-bottom: 0px;">
    <?php echo do_shortcode('[search_form placeholder="" button_text="Search"]'); ?>
</div>

            <?php // All rows - Welcome / News (text boxes) and the link boxes - edited in Layout Manager > Home Page
            echo function_exists( 'monospace_home_render_boxes' ) ? monospace_home_render_boxes() : ''; ?>

    </div>



<?php get_footer() // closes </body></html> itself ?>






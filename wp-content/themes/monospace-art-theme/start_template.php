<?php
/**
 * Template Name: Start Page
 * Description: Static intro / splash page for the front of the site.
 */
?>

<?php get_header() ?>


<?php // Welcome, News and the link boxes: all from Layout Manager > Home Page ?>

<?php // Top content - a banner under the logo, as on the other pages (Layout Manager > Home Page > Top content)
if ( function_exists( 'monospace_render_top_content' ) ) monospace_render_top_content(); ?>


<div class="container">

<?php // The search box: on unless switched off in Layout Manager > Home Page
if ( ! function_exists( 'monospace_home_search_on' ) || monospace_home_search_on() ) : ?>
<div class="search-bar" style="width: 100%; display: flex; justify-content: flex-end; padding: 0px 0; margin-bottom: 0px;">
    <?php echo do_shortcode('[search_form placeholder="" button_text="Search"]'); ?>
</div>
<?php endif; ?>

            <?php // All rows - Welcome / News (text boxes) and the link boxes - edited in Layout Manager > Home Page
            echo function_exists( 'monospace_home_render_boxes' ) ? monospace_home_render_boxes() : ''; ?>

    </div>



<?php get_footer() // closes </body></html> itself ?>






<?php
/**
 * Template Name: Start Page
 * Description: Static intro / splash page for the front of the site.
 */
?>

<?php
/**
 * Get background image for Art Feed box:
 * latest post NOT in blog or miniature
 */
function ms_get_latest_artwork_image_url() {
    $query = new WP_Query([
        'posts_per_page' => 1,
        'post_status'    => 'publish',

        'tax_query' => [
            'relation' => 'AND',

            // Must have a category
            [
                'taxonomy' => 'category',
                'operator' => 'EXISTS',
            ],

            // Must not be ONLY excluded categories
            [
                'taxonomy' => 'category',
                'field'    => 'slug',
                'operator' => 'NOT IN',
                'terms'    => ['uncategorized', 'blog', 'miniature'],
            ],
        ],
    ]);

    if (!$query->have_posts()) {
        return '';
    }

    $query->the_post();
    $post_id = get_the_ID();
    $stamp   = get_post_modified_time('U', false, $post_id);

    // Featured image first
    if (has_post_thumbnail($post_id)) {
        $url = get_the_post_thumbnail_url($post_id, 'full');
        wp_reset_postdata();
        return $url . '?v=' . $stamp;
    }

    // First inline image
    $content = get_the_content();
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $content, $matches)) {
        wp_reset_postdata();
        return esc_url_raw($matches[1]) . '?v=' . $stamp;
    }

    wp_reset_postdata();
    return '';
}

$feed_bg_image = ms_get_latest_artwork_image_url();
?>


<?php get_header() ?>


<style>
/* -----------------------------------------------------------
   BOX BACKGROUND IMAGES
----------------------------------------------------------- */

#welcome.box{
    flex: 1;
}
#news.box {
    --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/clouds2.jpg');
    flex: 1;
}

#paintings.box {
    --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/sunset.jpg');
    flex: 1;
}
#miniatures.box {
    --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/miniature.jpg');
    flex: 1;
}
#drawings.box {
    --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/steps.jpg');
    flex: 1;
}

#commissions.box {
    --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/custom_paint.jpg');
    flex: 2;
}
#feed.box {
    --bg: url('<?php
        echo esc_url(
            $feed_bg_image
            ?: get_stylesheet_directory_uri() . '/assets/images/backgrounds/bigbrook1.jpg'
        );
    ?>');
    flex: 3;
}



#markets.box {
    --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/jcfridays.png');
    flex: 2;
}
#classes.box {
    --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/fritz.jpg');
    flex: 2;
}
#illustration.box {
    --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/illustration.png');
    flex: 2;
}


#about.box {
    --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/genexpanded.jpg');
    flex: 2;
}

#connect.box {
    --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/pens.png');
    flex: 3;
}
</style>

<div class="container">


            <div class="box" id="welcome">
                <div class="box-title">Welcome!</div>
                <div class="box-content" style="padding-top: 0.5em">
                    <?php
                    $page = get_page_by_path( 'welcome' );
                    if ( $page ) {
                        echo apply_filters( 'the_content', $page->post_content );
                    }
                    ?>
                </div>
            </div>

            <div class="box" id="news">
                <div class="box-title">News</div>
                <div class="box-content">
                    <?php
                    $page = get_page_by_path( 'news' );
                    if ( $page ) {
                        echo apply_filters( 'the_content', $page->post_content );
                    }
                    ?>
                </div>
            </div>






            <div class="flex-break"></div>

            <div class="box link" id="paintings" onclick="document.location.href='category/painting'">
                <div class="box-title">Paintings</div>
                <div class="box-content bottom-align">Discover recent paintings in gouache, casein, and acrylic, presented in chronological order.</div>
            </div>
            <div class="box link" id="miniatures" onclick="document.location.href='category/miniature'">
                <div class="box-title">Miniatures</div>
                <div class="box-content bottom-align">View a collection of small-scale landscape paintings created on 4×4 inch panels.</div>
            </div>
            <div class="box link" id="drawings" onclick="document.location.href='category/drawing'">
                <div class="box-title">Drawings</div>
                <div class="box-content bottom-align">Explore recent sketches and drawings, documented over time.</div>
            </div>




            <div class="flex-break"></div>

            <div class="box link" id="commissions" onclick="document.location.href='custom-work'">
                <div class="box-title">Custom Orders</div>
                <div class="box-content bottom-align">Commission a custom artwork designed specifically for your space and ideas.</div>
            </div>
            <div class="box link" id="feed" onclick="document.location.href='art-feed'">
                <div class="box-title">The Art Feed</div>
                <div class="box-content bottom-align">A live chronological feed of new artworks, studio updates, and blog posts.</div>
            </div>






            <div class="flex-break"></div>

            <div class="box link" id="markets" onclick="document.location.href='market-and-events/schedule'">
                <div class="box-title">Markets & Events</div>
                <div class="box-content bottom-align">Find upcoming markets, exhibitions, and in-person events where my work will be available.</div>
            </div>

            <div class="box link" id="classes" onclick="document.location.href='classes'">
                <div class="box-title">Art Classes</div>
                <div class="box-content bottom-align">Personalized art instruction focused on technique, confidence, and creative development.</div>
            </div>

            <div class="box link" id="illustration" onclick="document.location.href='illustration'">
                <div class="box-title">Illustrations</div>
                <div class="box-content bottom-align">
                    Selected illustration projects spanning games, publications, and interactive media.
                </div>
            </div>



            <div class="flex-break"></div>

            <div class="box link" id="about" onclick="document.location.href='about/about-me'">
                <div class="box-title">About</div>
                <div class="box-content bottom-align">Learn more about my background, practice, and artistic approach.</div>
            </div>
            <div class="box link" id="connect" onclick="document.location.href='connect/contact'">
                <div class="box-title">Connect</div>
                <div class="box-content bottom-align">Contact me for inquiries, collaborations, or commissions.</div>
            </div>


    </div>



<?php get_footer() ?>



</body>
</html>






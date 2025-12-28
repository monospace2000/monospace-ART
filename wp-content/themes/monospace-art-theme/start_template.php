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
//echo $feed_bg_image; // for testing
?>


<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>monospace ART | Landscape Paintings & Urban Sketches by Hens Breet</title>
<meta name="description" content="monospace ART by Hens Breet – landscape paintings and urban sketches capturing the natural and urban world.">

<link rel="icon" href="/favicon.ico" sizes="16x16">
<link rel="canonical" href="<?php echo esc_url(home_url('/')); ?>">

<meta property="og:title" content="monospace ART | Landscape Paintings & Urban Sketches by Hens Breet">
<meta property="og:description" content="Explore landscape paintings and urban sketches by Hens Breet.">
<meta property="og:site_name" content="art.monospace.com">
<meta property="og:locale" content="en_US">
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo esc_url(home_url('/')); ?>">
<meta property="og:image" content="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/site-preview.png">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="monospace ART | Landscape Paintings & Urban Sketches by Hens Breet">
<meta name="twitter:description" content="Explore artwork by Hens Breet.">
<meta name="twitter:image" content="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/site-preview.png">

<meta name="theme-color" content="#c8c8c8">

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Person",
  "name": "Hens Breet",
  "url": "https://art.monospace.com",
  "sameAs": ["https://www.instagram.com/hens.breet"],
  "jobTitle": "Artist"
}
</script>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400;1,700&family=Merriweather:ital,opsz,wght@0,18..144,300..900;1,18..144,300..900&family=Open+Sans:ital,wght@0,300..800;1,300..800&family=Oswald:wght@200..700&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">





<style>

/* -----------------------------------------------------------
   BASE TYPOGRAPHY
----------------------------------------------------------- */
body {
    font-family: "Merriweather", serif !important;
    font-size: 1.2em;
    font-weight: 400 !important;
}
body {
  position: relative; /* establishes containing block for ::after */
}
a, a:hover{
    text-decoration: underline !important;
}
/* -----------------------------------------------------------
   HEADER
----------------------------------------------------------- */
.header {
    position: relative;
    overflow: hidden;
    aspect-ratio: 1437/276;
    max-width: calc(100% - 48px); /* same as box margins */
    max-width:1200px;
    margin: 0px auto;
}

.header::before {
    content: "";
    position: absolute;
    top: 0;
    left: 24px;            /* aligns with header left edge */
    right: 0;
    bottom: 0;
    background-image: url('https://art.monospace.com/wp-content/uploads/logo_mobile.png');
    background-size: contain;   /* keeps aspect ratio, no cropping */
    background-position: left top;
    background-repeat: no-repeat;
}

/* -----------------------------------------------------------
   GRID LAYOUT
----------------------------------------------------------- */
.container {
    display: flex;
    flex-wrap: wrap;
    margin: 0 auto;
    align-items:stretch;
    max-width:1200px;
}

.flex-break {
    flex-basis: 100%;
    height: 0;
}


/* Mobile adjustments */
@media (max-width: 768px) {
    .container { flex-direction: column; }
    .box { margin: 0; }
    .flex-break { display: none; }
    .header{ margin-right:20px; margin-top:20px;}
    body { font-size: 1.1em;
        margin: 0 !important;
    }
    #news .box-content{
        min-height: 220px !important;
    }
}

/* -----------------------------------------------------------
   BOXES (PSEUDO-BACKGROUND SYSTEM)
----------------------------------------------------------- */
.box {
    position: relative;
    overflow: hidden;
    flex: 1;
    border-radius: 10px;
    margin: 24px;
    box-shadow: 0 0 10px rgba(0,0,0,0.5);

    display: flex;
    flex-direction: column;  /* so inner content can shrink or scroll */
    min-height: 0;           /* IMPORTANT */
}

/* The magic: stretch background to match content height */
.box::before {
    content: "";
    position: absolute;
    inset: 0;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    z-index: 0;
    background-image: var(--bg); /* each box sets its own */
}

.box > * {
    position: relative;
    z-index: 1;
}

/* -----------------------------------------------------------
   BOX CONTENT
----------------------------------------------------------- */

/* Auto-scaling single-line titles */
.box-title {
    display: block;
    position: relative;
    width: 100%;
    overflow: hidden;
    border-radius: 10px 10px 0 0;
    background: rgba(0,0,0,0.75);

    text-overflow: ellipsis;  /* keeps it pretty if it REALLY can’t fit */
    white-space: nowrap;      /* force one line */
    font-family: "Oswald", sans-serif;
    font-size: clamp(1rem, 1.4vw, 1.6rem);
    /* ↓ smaller screens reduce font size to stay on one line */
    line-height: 1.2;
    color: white;
    font-weight: 700;
    padding: 3px 20px 9px 20px;
 }

.box-title:not(#welcome .box-title)::before {
    content: "";
    position: absolute;
    top: 2px;       /* slightly inset from top */
    left: 2px;      /* slightly inset from left */
    width: 240px;   /* longer horizontally */
    height: 40px;   /* much shorter vertically */
    border-top-left-radius: 8px; /* match box radius */

    /* angled gradient to simulate the corner highlight */
    background: linear-gradient(
        160deg,
        rgba(255,255,255,0.8) 0%,
        rgba(255,255,255,0) 25%
    );

    pointer-events: none;
}



.box-content {
    padding: 10px 20px;
    background: rgba(255,255,255,0.5);
    border-radius: 0 0 10px 10px;
    min-height: clamp(220px, 20vw, 400px);
    font-size: clamp(0.8rem, 1.0vw, 1.3rem);
    /* ↓ smaller screens reduce font size to stay on one line */
    line-height: 1.2;
}


.bottom-align {
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    color: white;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    background: linear-gradient(180deg, rgba(0,0,0,0) 50%, rgba(0,0,0,0.75) 80%);
    filter: url('#noise');

}
/* .bottom-align::after {
    content: "";
    position: absolute;
    inset: 0;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' /%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='0.05'/%3E%3C/svg%3E");
    pointer-events: none;
    mix-blend-mode: overlay;
}
 */


/* -----------------------------------------------------------
   SPECIAL CASES
----------------------------------------------------------- */

#news .box-content {
    font-size: clamp(0.8rem, 1vw, 1.4rem);
    line-height: 1.2;
}




    #news .box-content {
    flex: 1 1 0;
    min-height: 0;      /* critical: allows flex child to respect overflow */
    overflow-y: auto;   /* scroll internally if content exceeds height */


    }



#news h4{
    margin-top:20px;
    margin-bottom: 2px;
}

#welcome.box {
    box-shadow: none;
}

#welcome .box-title,
#welcome .box-content {
    background: none;
    color: black;
    padding: 0;
}

#welcome .box-title{
    font-size: clamp(1rem, 1.4vw, 1.6rem);
    line-height: 1.4;
}
#welcome .box-content{
    font-size: clamp(0.8rem, 1.1vw, 1.4rem);
    line-height: 1.4;

}

/* -----------------------------------------------------------
   FOOTER
----------------------------------------------------------- */
.footer {
    margin: 50px;
    text-align: center;
    font-size: 0.8em;
}


/* -----------------------------------------------------------
   INTERACTION: make all boxes unselectable except News + Welcome
----------------------------------------------------------- */

/* All boxes default: unselectable + pointer + hover zoom */
.box {
    user-select: none;
    cursor: pointer;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.box:hover {
    transform: scale(1.03);
    box-shadow: 0 0 18px rgba(0,0,0,0.55);
}

/* EXCEPTIONS — Welcome and News stay normal */
#welcome,
#news {
    user-select: text;      /* selectable again */
    cursor: default;        /* no finger cursor */
    transform: none !important;
}

#welcome:hover{
    transform: none !important;
}

#news:hover {
    transform: none !important;
    box-shadow: 0 0 10px rgba(0,0,0,0.5);
}




/* -----------------------------------------------------------
   BOX BACKGROUND IMAGES — FINAL, VERIFIED PATHS
   (All images pulled from /assets/images/backgrounds/)
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
/* #feed.box {
    --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/bigbrook1.jpg');
    flex: 3
}
 */
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
    /* --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/self_portrait_clean.png'); */
    --bg: url('https://art.monospace.com/wp-content/uploads/img_2325.jpg');
    flex: 2;
}

#connect.box {
    --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/pens.png');
    flex: 3;
}


</style>

<?php wp_head(); ?>
</head>

<body class="start-page">

<svg style="position: absolute; width: 0; height: 0;">
  <filter id="noise">
    <feTurbulence baseFrequency="0.8" numOctaves="8" result="noise" />
    <feColorMatrix in="noise" type="saturate" values="0" result="desaturated" />
    <feComponentTransfer in="desaturated" result="contrast">
      <feFuncA type="linear" slope="0.1" intercept="0" />
    </feComponentTransfer>
    <feBlend in="SourceGraphic" in2="contrast" mode="multiply" />
  </filter>
</svg>

<div class="header"></div>

<div class="container" style="margin-top:2em">



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

            <!--
            <div class="flex-break"></div>

             <div class="box link" id="shop" onclick="document.location.href='https://monospace.etsy.com'">
                <div class="box-title">Etsy Store</div>
                <div class="box-content bottom-align">Browse a curated selection of original works, prints, and commissions available on Etsy.</div>
            </div>
            -->
            <div class="flex-break"></div>

            <div class="box link" id="paintings" onclick="document.location.href='category/painting'">
                <div class="box-title">Paintings</div>
                <div class="box-content bottom-align">Explore recent paintings in gouache, casein, and acrylic, organized chronologically.</div>
            </div>
            <div class="box link" id="miniatures" onclick="document.location.href='category/miniature'">
                <div class="box-title">Miniatures</div>
                <div class="box-content bottom-align">Browse all miniature landscapes, created on 4x4 canvas panels.</div>
            </div>
            <div class="box link" id="drawings" onclick="document.location.href='category/drawing'">
                <div class="box-title">Drawings</div>
                <div class="box-content bottom-align">A collection of recent sketches and drawings arranged in chronological order.</div>
            </div>

            <div class="flex-break"></div>

            <div class="box link" id="commissions" onclick="document.location.href='custom-work'">
                <div class="box-title">Custom Orders</div>
                <div class="box-content bottom-align">Request a personalized piece of art tailored to your vision.</div>
            </div>
            <div class="box link" id="feed" onclick="document.location.href='art-feed'">
                <div class="box-title">The Art Feed</div>
                <div class="box-content bottom-align">A raw, unfiltered, chronological listing of all recently added artworks and blog posts.</div>
            </div>




            <div class="flex-break"></div>


            <div class="box link" id="markets" onclick="document.location.href='market-dates'">
                <div class="box-title">Markets & Events</div>
                <div class="box-content bottom-align">Upcoming markets, fairs, exhibits, and other events where my work will be available in person.</div>
            </div>

            <div class="box link" id="classes" onclick="document.location.href='classes'">
                <div class="box-title">Art Classes</div>
                <div class="box-content bottom-align">Individualized art classes designed to support your creative growth.</div>
            </div>

            <div class="box link" id="illustration" onclick="document.location.href='illustration'">
                <div class="box-title">Illustrations</div>
                <div class="box-content bottom-align">
                    Portfolio highlights of illustration projects, including games and interactive media.
                </div>
            </div>

            <div class="flex-break"></div>


        <div class="box link" id="about" onclick="document.location.href='about/about-me'">
                <div class="box-title">About</div>
                <div class="box-content bottom-align">Get to know the artist behind the work.</div>
            </div>
            <div class="box link" id="connect" onclick="document.location.href='connect/contact'">
                <div class="box-title">Connect</div>
                <div class="box-content bottom-align">Reach out with questions, collaboration ideas, or commission requests.</div>
            </div>


    </div>


<!-- <div class="footer">Copyright &copy;<?php echo date('Y');?> Hens Breet | monospace ART LLC. All rights reserved.</div> -->

<?php get_footer() ?>

</body>
</html>






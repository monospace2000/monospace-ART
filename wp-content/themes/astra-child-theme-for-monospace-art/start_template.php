<?php
/**
 * Template Name: Start Page
 * Description: Static intro / splash page for the front of the site.
 */
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
<meta property="og:image" content="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/site.preview.jpg">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="monospace ART | Landscape Paintings & Urban Sketches by Hens Breet">
<meta name="twitter:description" content="Explore artwork by Hens Breet.">
<meta name="twitter:image" content="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/site.preview.jpg">

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
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<style>

/* -----------------------------------------------------------
   BASE TYPOGRAPHY
----------------------------------------------------------- */
body {
    font-family: "Open Sans", sans-serif;
    font-size: 1.2em;
    font-weight: 500 !important;
    margin: 0 24px !important;
}
@media (min-width: 960px) {
   body{ 
    margin: 0 15vw !important;
   }
}
/* -----------------------------------------------------------
   HEADER
----------------------------------------------------------- */
.header {
    position: relative;
    overflow: hidden;
    aspect-ratio: 1437/276;
    max-width: calc(100% - 48px); /* same as box margins */
    margin-left: 24px;
    margin-right: 24px;
    margin-top: 24px;
}

.header::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;            /* aligns with header left edge */
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
}

.flex-break {
    flex-basis: 100%;
    height: 0;
}


/* Mobile adjustments */
@media (max-width: 550px) {
    .container { flex-direction: column; }
    .box { margin: 8px; }
    .flex-break { display: none; }
    body { font-size: 1.1em;
        margin: 0 8px;
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
.box-title {
    color: white;
    font-weight: 700;
    font-size: 120%;
    padding: 5px 20px;
    border-radius: 10px 10px 0 0;
    background: rgba(0,0,0,0.75);
}

.box-content {
    padding: 10px 20px;
    background: rgba(255,255,255,0.5);
    border-radius: 0 0 10px 10px;
    min-height: 240px;
}

.bottom-align {
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    color: white;
    text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
    background: linear-gradient(180deg, rgba(0,0,0,0) 50%, rgba(0,0,0,0.75) 80%);
}

h5{
    color: #333 !important;
    margin-bottom: 0px !important;
}
p{
    margin-top: 0px !important;
}
/* -----------------------------------------------------------
   BOX BACKGROUND IMAGES — FINAL, VERIFIED PATHS
   (All images pulled from /assets/images/backgrounds/)
----------------------------------------------------------- */

#welcom.box{
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
    --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/bigbrook1.jpg');
    flex: 3
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
    --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/self_portrait_clean.png');
    flex: 2;
}

#connect.box {
    --bg: url('<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/pens.png');
    flex: 3;
}


/* -----------------------------------------------------------
   SPECIAL CASES
----------------------------------------------------------- */
#welcome.box {
    box-shadow: none;
}

#welcome .box-title,
#welcome .box-content {
    background: none;
    color: black;
    padding: 0;
}

/* -----------------------------------------------------------
   FOOTER
----------------------------------------------------------- */
.footer {
    margin: 50px;
    text-align: center;
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


</style>

<?php wp_head(); ?>
</head>

<body class="start-page">

<div class="header"></div>

<div class="container">



            <div class="box" id="welcome">
                <div class="box-title">Welcome</div>
                <div class="box-content">I’m Hens Breet, a painter and illustrator based in Hudson County, New Jersey. Much of my work is inspired by my local surroundings — from city streets and river views to nearby parks and wooded trails. I work in gouache, casein, acrylic, and pen and ink to capture landscapes, cityscapes, and the quiet details of the natural world. Alongside painting and sketching, I create illustrations and interactive projects that bring stories and ideas to life. Here, you can explore my latest works, purchase originals directly, learn about upcoming exhibitions, events, and markets, and find information on commissions and art classes.

                </div>
            </div>
        
            <div class="box" id="news">
                <div class="box-title">News</div>
                <div class="box-content"><!--Stay up to date with upcoming gallery exhibitions, events, and announcements.-->
                    <h5>Holdiday Special</h5><p><span style="font-weight:bold;color: #d00">Buy 3 <a style="color:#d00" href="category/miniature">miniatures</a> for only $99 </span>(plus s/h), from now until January 1, 2026. Includes free mini display easels. Discount applied at checkout.</p>
                  <h5>Holdiday Markets</h5><p>I am selling my original art at select local Holiday Markets throughout the North New Jersey area. Check the <u><a href='market-dates'>Markets & Events Page</a></u> for dates and locations. Fun fact: buying items from me in person is up to 25% cheaper than buying them onine!</p>
<!--                     <p><strong>Online store now open</strong><br>You can now purchase available artworks directly from this site, with secure payments and fast shipping. I’m gradually adding older pieces to the inventory, so check back often for more.
 -->                   <h5>Upcoming Solo Exhibition</h5><p>My work will be featured in a solo exhibition at the Hoboken Historical Museum’s Upper Gallery in February 2026. More details to follow.
                  <!--       <p><strong>Hoboken Art Month</strong><br>This October, two of my favorite pieces are on display at <b>Louise And Jerry's Tavern</b>, a beloved Hoboken institution, as part of <em><a href="https://mainstreetpops.com/?event=hoboken-art-month-storefront-art-walk">Hoboken Art Month Storefront Walk</a></em>, a citywide celebration of creativity and community.  -->
                    <!-- <p><strong>Hoboken Artists’ Studio Tour</strong><br>On November 2, some of my work will be on view at the Hoboken Historic Museum as part of the <a href="https://www.visithudson.org/things-to-do/artsandculture/hoboken-artists-studio-tours/">Hoboken Artists’ Studio Tour</a>. -->
                   <h5>The Collective Hoboken</h5><p>Select pieces are on display and available for purchase at <em><a href="https://thecollectivehoboken.com">The Collective Hoboken</a></em>, a new gallery on Washington Street highlighting local artists.</p>
                    <!--<p><strong>One Mile Gallery</strong><br>My work was included in <em>Phantasma</em>, a group exhibition at <em><a href="https://onemilegallery.com/">One Mile Gallery</a></em> in Kingston, NY, that ran from mid-August through September.-->
                   
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
<!--             <div class="box link" id="uploads" onclick="document.location.href='blog/'" style="background-image: url('/art/blog/wp-content/uploads/IMG_7647.heic');"> -->
                <div class="box-title">Art Feed</div>
                <div class="box-content bottom-align">A chronological feed of all recently added artwork and blog posts.</div>
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


        <div class="box link" id="about" onclick="document.location.href='about'">
                <div class="box-title">About</div>
                <div class="box-content bottom-align">Get to know the artist behind the work.</div>
            </div>
            <div class="box link" id="connect" onclick="document.location.href='contact'">
                <div class="box-title">Connect</div>
                <div class="box-content bottom-align">Reach out with questions, collaboration ideas, or commission requests.</div>
            </div>
  

    </div>
        
    
<div class="footer">Copyright &copy;2025 Hens Breet | monospace ART | <strong>I ♥️ NJ</strong></div>

<?php //get_footer(); ?>

</body>
</html>




 

<?php
/**
 * Template Name: Start Page
 * Description: Static intro or splash page for the front page of the site.
 *
 * To use:
 * 1. Upload this file to your Astra child theme folder.
 * 2. In WP admin, create a page called "Start" (or whatever you want).
 * 3. Assign the "Start Page" template in Page Attributes → Template.
 * 4. Set that page as the homepage under Settings → Reading → Homepage.
 */

//get_header();
?>









<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>monospace ART | Landscape Paintings & Urban Sketches by Hens Breet</title>
<meta name="description" content="monospace ART by Hens Breet – landscape paintings and urban sketches, capturing urban and natural environments with expressive color and detail.">

<!-- Favicon -->
<link rel="icon" href="/favicon.ico" sizes="16x16" type="image/x-icon">

<!-- Canonical URL -->
<link rel="canonical" href="<?php echo esc_url(home_url('/')); ?>">

<!-- Open Graph -->
<meta property="og:title" content="monospace ART | Landscape Paintings & Urban Sketches by Hens Breet">
<meta property="og:description" content="Explore landscape paintings and urban sketches by Hens Breet at monospace ART.">
<meta property="og:site_name" content="art.monospace.com">
<meta property="og:locale" content="en_US">
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo esc_url(home_url('/')); ?>">
<meta property="og:image" content="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/site.preview.jpg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="1200">

<!-- Twitter (no account needed) -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="monospace ART | Landscape Paintings & Urban Sketches by Hens Breet">
<meta name="twitter:description" content="Explore landscape paintings and urban sketches by Hens Breet at monospace ART.">
<meta name="twitter:image" content="<?php echo get_stylesheet_directory_uri(); ?>/assets/images/site.preview.jpg">
<meta name="twitter:image:alt" content="Artwork by Hens Breet">

<!-- Theme color -->
<meta name="theme-color" content="#c8c8c8">

<!-- JSON-LD Structured Data -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Person",
  "name": "Hens Breet",
  "url": "https://art.monospace.com",
  "sameAs": [
    "https://www.instagram.com/hens.breet"
  ],
  "jobTitle": "Artist"
}
</script>





<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Open+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    
    <style>






  body {
          font-family: "Open Sans", sans-serif;
      font-optical-sizing: auto;

    font-size: 1.2em !important;
    font-weight: 500 !important;
}





/* Header container */
.header {
    position: relative; 
    overflow: hidden;   
    aspect-ratio: 1437/276;
    margin: 0 auto;
}

/* Header pseudo-element image */
.header::before {
    content: "";
    position: absolute;
    top: 0;
    left: 50px;
    width: 100%;
    height: 100%;
    background-image: url(https://art.monospace.com/wp-content/uploads/logo_mobile.png);
    background-size: cover;
    background-position: left top;
    transform: scale(0.93);
    transform-origin: top left;
    background-repeat: no-repeat;
    z-index: -1; /* behind header content but in front of body bg */
}




        @media screen and (min-width: 1300px) {

            .header, .container{
                width: 1300px;
            }
        }

        @media screen and (orientation: portrait) {
            body {
                _font-size: 1.1em;
            }
        }
        
        a{ color: black;}
        .container{
            display: flex;
            flex-wrap: wrap;
            margin: 0 auto;
        }
        .flex-break {
            flex-basis: 100%; /* Or width: 100%; */
            height: 0;
            _display: none;
        }


        

 @media (max-width: 750px) {
  .container {
    flex-direction: column;
    flex-wrap: nowrap;
  }

  .box {
    flex: none;
    margin: 8px;
  }

  .flex-break {
    display: none;
  }

  .header {
    margin-top: 40px;
  }

  .header::before {
    transform: scale(0.85);
    left: 25px
  }

  body {
        font-size: 1.1em !important;
  }
}


        
        .box{
            flex: 1;
            border-radius: 10px;
            margin: 32px;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.5);

            background-size: cover;
            background-position: center;
        }
        .box-title{
            color: white;
            font-weight: 700;
            font-size: 120%;            
            padding: 5px 20px;
            border-radius: 10px 10px 0 0;
            background-color: rgba(0, 0, 0, 0.75); 
        }
        .box-content{
            padding: 10px 20px;
            background-color: rgba(255, 255, 255, 0.5);
            border-radius: 0 0 10px 10px;
            min-height: 240px;
 
        }
        .link{
            cursor: pointer;
        }
        .link:hover{
            transform: scale(1.01);
            box-shadow: 0px 4px 16px rgba(0, 0, 0, 0.75);
              transition: transform 0.5s;

        }
        .bottom-align{
            display: flex;
              flex-direction: column; 
            justify-content: flex-end; 

            color: white;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);

            _background: linear-gradient(180deg,rgba(255, 255, 255, 0.10) 0%, rgba(0, 0, 0, 0.75) 98%);
            background: linear-gradient(180deg,rgba(0, 0, 0, 0) 50%, rgba(0, 0, 0, 0.75) 80%);
        }
        .footer{
            margin: 50px;
            text-align: center;
        }



        #welcome.box{
            box-shadow: none;
            flex: 1;
        }
        #welcome .box-title, #welcome .box-content{
            background: none;
        }
        #welcome .box-title{
            color: black;
        }

        #news.box{
            background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/clouds2.jpg);
            flex: 2;
        }
        #news .box-content{
                        background-color: rgba(255, 255, 255, 0.5);
        }
        
        
        #uploads.box{
           background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/bigbrook1.jpg);
             
            _background-position: 50% 70%;
            flex: 3;
        }
        #commissions.box{
            background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/custom_paint.jpg);
            flex: 2;
        }
        @media (min-width: 750px) {
            #uploads .box-content, #commissions .box-content{
                min-height: 400px;
            }
        }


        #paintings.box{
            background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/sunset.jpg);
            flex: 1;
        }

        #penandink.box{
            background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/steps.jpg);
            flex: 1;
        }

        #illustration.box{
            background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/illustration.png);
            flex: 1;
        }



        #classes.box{
            background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/fritz.jpg);
            flex: 3;
        }


        #markets.box{
            background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/jcfridays.png);
            flex: 4;
            background-position: 50% 40%;
        }

        #about.box{
            background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/assets/images/self_portrait_clean.png);
      
            flex: 1;
        
        }
        #about .box-content{
            _min-height: 80px;
        }

        #connect.box{
            background-image: url(<?php echo get_stylesheet_directory_uri(); ?>/assets/images/backgrounds/pens.png);
            flex: 3;

        }
        #connect .box-content{
            _min-height: 80px;
        }



        
        </style>
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
                    <p><strong>New online store now open!</strong><br>You can now purchase available artworks directly from this site, with secure payments and fast shipping. I’m gradually adding older pieces to the inventory, so check back often for more.
                        <p><strong>Hoboken Art Month</strong><br>This October, two of my favorite pieces are on display at <b>Louise And Jerry's Tavern</b>, a beloved Hoboken institution, as part of <em><a href="https://mainstreetpops.com/?event=hoboken-art-month-storefront-art-walk">Hoboken Art Month Storefront Walk</a></em>, a citywide celebration of creativity and community. 
                    <p><strong>Hoboken Artists’ Studio Tour</strong><br>On November 2, some of my work will be on view at the Hoboken Historic Museum as part of the <a href="https://www.visithudson.org/things-to-do/artsandculture/hoboken-artists-studio-tours/">Hoboken Artists’ Studio Tour</a>.
                    <p><strong>The Collective Hoboken</strong><br>Select pieces are currently on display and available for purchase at <em><a href="https://thecollectivehoboken.com">The Collective Hoboken</a></em>, a new gallery on Washington Street highlighting local artists.
                    <!--<p><strong>One Mile Gallery</strong><br>My work was included in <em>Phantasma</em>, a group exhibition at <em><a href="https://onemilegallery.com/">One Mile Gallery</a></em> in Kingston, NY, that ran from mid-August through September.-->
                    <p><strong>Upcoming Solo Exhibition</strong><br>Looking ahead, I’m planning a solo show in Hoboken in early 2026. More details to come!
                   
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

            <div class="box link" id="uploads" onclick="document.location.href='recent-work'">
<!--             <div class="box link" id="uploads" onclick="document.location.href='blog/'" style="background-image: url('/art/blog/wp-content/uploads/IMG_7647.heic');"> -->
                <div class="box-title">Recent Works</div>
                <div class="box-content bottom-align">See the most recently added works across all categories in chronological order. Purchase directly online.</div>
            </div>
            <div class="box link" id="commissions" onclick="document.location.href='custom-work'">
                <div class="box-title">Custom Orders</div>
                <div class="box-content bottom-align">Learn how to request a personalized piece of art tailored to your vision.</div>
            </div>

            <div class="flex-break"></div>

            <div class="box link" id="paintings" onclick="document.location.href='category/style/color'">
                <div class="box-title">Paintings</div>
                <div class="box-content bottom-align">Explore recent paintings in gouache, casein, and acrylic, organized chronologically.</div>
            </div>
            <div class="box link" id="penandink" onclick="document.location.href='category/style/black-and-white/'">
                <div class="box-title">Pen And Ink</div>
                <div class="box-content bottom-align">A collection of recent pen and ink sketches, arranged in chronological order.</div>
            </div>

            <div class="box link" id="illustration" onclick="document.location.href='illustration'">
                <div class="box-title">Illustrations</div>
                <div class="box-content bottom-align">
                    Portfolio highlights of illustration projects, including games and interactive media.
                </div>
            </div>
   

            <div class="flex-break"></div>

        
            <div class="box link" id="markets" onclick="document.location.href='market-dates'">
                <div class="box-title">Markets & Events</div>
                <div class="box-content bottom-align">View upcoming markets, fairs, exhibits, and other events where my work will be available in person.</div>
            </div>
  
            <div class="box link" id="classes" onclick="document.location.href='classes'">
                <div class="box-title">Art Classes</div>
                <div class="box-content bottom-align">Find out more about individualized art classes designed to support your creative growth.</div>
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




 

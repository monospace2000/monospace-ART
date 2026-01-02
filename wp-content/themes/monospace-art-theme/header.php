<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>


<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">

<?php
// Dynamic title & description
if (is_singular()) {
    $page_title = get_the_title() . ' | monospace ART by Hens Breet';
    $page_description = get_post_meta(get_the_ID(), '_yoast_wpseo_metadesc', true) ?: 'monospace ART by Hens Breet – landscape paintings and urban sketches.';
} else {
    $page_title = 'monospace ART | Landscape Paintings & Urban Sketches by Hens Breet';
    $page_description = 'monospace ART by Hens Breet – landscape paintings and urban sketches.';
}
?>

<title><?php echo esc_html($page_title); ?></title>
<meta name="description" content="<?php echo esc_attr($page_description); ?>">

<!-- Favicon -->
<link rel="icon" href="/favicon.ico" sizes="16x16" type="image/x-icon">
<link rel="canonical" href="<?php echo esc_url(get_permalink()); ?>">

<!-- Open Graph -->
<meta property="og:title" content="<?php echo esc_attr($page_title); ?>">
<meta property="og:description" content="<?php echo esc_attr($page_description); ?>">
<meta property="og:site_name" content="art.monospace.com">
<meta property="og:locale" content="en_US">
<meta property="og:type" content="website">
<meta property="og:url" content="<?php echo esc_url(get_permalink()); ?>">
<?php
$og_image = has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(),'full') : get_stylesheet_directory_uri().'/assets/images/site-preview.png';
?>
<meta property="og:image" content="<?php echo esc_url($og_image); ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="1200">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo esc_attr($page_title); ?>">
<meta name="twitter:description" content="<?php echo esc_attr($page_description); ?>">
<meta name="twitter:image" content="<?php echo esc_url($og_image); ?>">
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
  "sameAs": ["https://www.instagram.com/hens.breet"],
  "jobTitle": "Artist"
  <?php if (is_singular() && has_post_thumbnail()) : ?>,
  "worksFeatured": [
    {
      "@type": "VisualArtwork",
      "name": "<?php echo esc_js(get_the_title()); ?>",
      "url": "<?php echo esc_url(get_permalink()); ?>",
      "image": "<?php echo esc_url($og_image); ?>",
      "creator": {"@type":"Person","name":"Hens Breet"}
    }
  ]
  <?php endif; ?>
}
</script>

<?php wp_head(); ?>




</head>

<body <?php body_class(); ?>>


<!-- noise link -->
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

<!-- Primary Menu Bar -->
<nav class="primary-menu-bar">

    <div class="primary-menu-container">





        <div class="menu-bar-home">
            <?php echo do_shortcode('[monospace_home]'); ?>
        </div>


        <div class="mobile-menu-toggle" aria-label="Open Menu">Menu ☰</div>

        <?php
        wp_nav_menu(array(
            'theme_location' => 'primary',
            'container' => false,
            'menu_class' => 'primary-menu',
            'fallback_cb' => false,
        ));
        ?>


        <div class="menu-bar-cart">
            <?php echo do_shortcode('[monospace_cart]'); ?>
        </div>


    </div>

</nav>



<div class="site-logo-wrapper">
    <div class="site-logo">
        <?php
        $logo_link = esc_url(home_url('/art-feed/'));
        if (has_custom_logo()) {
            $logo_id  = get_theme_mod('custom_logo');
            $logo_img = wp_get_attachment_image($logo_id, 'full', false, array('class' => 'custom-logo'));
            echo '<a href="' . $logo_link . '">' . $logo_img . '</a>';
        } else { ?>
            <a href="<?php echo $logo_link; ?>" class="logo-text"><?php bloginfo('name'); ?></a>
        <?php } ?>
    </div>

    <?php if ( get_bloginfo('description') ) : ?>
      <div
            class="site-tagline"
            data-fit-text
            data-fit-width="100"
            data-fit-font="Roboto"
            data-fit-weight="300"
            data-fit-padding="0"
            data-fit-max-width="1000"
            >
            <?php  echo esc_html( get_bloginfo('description') );  ?>


        </div>
    <?php endif; ?>
</div>


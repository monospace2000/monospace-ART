<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?><!DOCTYPE html>
<?php astra_html_before(); ?>
<html <?php language_attributes(); ?>>
<head>
<?php astra_head_top(); ?>

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
$og_image = has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(),'full') : get_stylesheet_directory_uri().'/assets/images/site.preview.jpg';
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


<?php
if ( apply_filters( 'astra_header_profile_gmpg_link', true ) ) {
	?><link rel="profile" href="https://gmpg.org/xfn/11"><?php
}
?>

<?php wp_head(); ?>
<?php astra_head_bottom(); ?>
</head>

<body <?php astra_schema_body(); ?> <?php body_class(); ?>>
<?php astra_body_top(); ?>
<?php wp_body_open(); ?>




<a class="skip-link screen-reader-text" href="#content" title="<?php echo esc_attr( astra_default_strings( 'string-header-skip-link', false ) ); ?>">
    <?php echo esc_html( astra_default_strings( 'string-header-skip-link', false ) ); ?>
</a>

<div <?php echo wp_kses_post( astra_attr( 'site', array( 'id' => 'page', 'class' => 'hfeed site' ) ) ); ?>>
    <?php
    astra_header_before();
    ?>
    <!-- <div id="spacer" style="height: 100px;"></div> -->
    <?php
    astra_header();
    astra_header_after();
    astra_content_before();
    ?>
    <div id="content" class="site-content">
        <div class="ast-container">
        <?php astra_content_top(); ?>

<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>


<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Title, description, canonical, Open Graph, JSON-LD: wp_head() (includes/modules/frontend/seo/) -->

<!-- Favicon -->
<link rel="icon" href="/favicon.ico" sizes="16x16" type="image/x-icon">

<!-- Theme color -->
<meta name="theme-color" content="#c8c8c8">

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

<?php // Elastic scroll (rubber-scroll.js): GSAP moves this wrapper's content - closed in footer.php
if ( function_exists( 'monospace_rubber_scroll_active' ) && monospace_rubber_scroll_active() ) echo '<div id="smooth-wrapper"><div id="smooth-content">'; ?>



<?php // The home page's <h1>: the logo + tagline (reset to look the same in _header.scss)
$logo_tag = is_front_page() ? 'h1' : 'div'; ?>
<<?php echo $logo_tag; ?> class="site-logo-wrapper">
    <div class="site-logo">
        <?php
        // Link and hover text: Layout Manager > Appearance & Effects > Logo link (design/logo-link.php)
        $logo      = function_exists('monospace_logo_link') ? monospace_logo_link() : ['url' => home_url('/category/available/'), 'title' => 'View available originals'];
        $logo_link = esc_url($logo['url']);
        $logo_title = $logo['title'] !== '' ? ' title="' . esc_attr($logo['title']) . '"' : '';
        // The logo: assets/images/logo.svg (viewBox trimmed to the letters - 1437.76 x 202.31), sized by
        // _header.scss to the column width, so it scales with the page
        $logo_svg = '/assets/images/logo.svg';
        if (file_exists(get_template_directory() . $logo_svg)) {
            echo '<a' . $logo_title . ' href="' . $logo_link . '"><img class="custom-logo" src="' . esc_url(get_template_directory_uri() . $logo_svg . '?v=' . filemtime(get_template_directory() . $logo_svg)) . '" width="1437.76" height="202.31" alt="monospace ART"></a>';
        } elseif (has_custom_logo()) {
            $logo_id  = get_theme_mod('custom_logo');
            $logo_img = wp_get_attachment_image($logo_id, 'full', false, array('class' => 'custom-logo'));
            echo '<a' . $logo_title . ' href="' . $logo_link . '">' . $logo_img . '</a>';
        } else { ?>
            <a href="<?php echo $logo_link; ?>" class="logo-text"><?php bloginfo('name'); ?></a>
        <?php } ?>
    </div>

    <?php if ( get_bloginfo('description') ) :
        // The tagline as SVG text, stretched to the logo's width: the viewBox uses the logo's own units
        // (logo.svg, trimmed to its letters: 0 to 1437.75) and the SVG takes the logo's box
        // (_header.scss), so the text starts and ends where the logo's letters do at any screen size.
        // textLength locks the width. Font size from the text length (Roboto 200 averages 0.455em per
        // character); the first / last letter's side bearings are allowed for.
        $tagline = get_bloginfo('description');
        $ink_l   = 0;
        $ink_w   = 1437.75;
        $font    = $ink_w / ( max( 1, mb_strlen( $tagline ) ) * 0.455 );
        $pad     = 0.07 * $font;
        $height  = ( 0.75 + 0.213 ) * $font + 2 * $pad;
        $fmt     = function ( $n ) { return rtrim( rtrim( number_format( $n, 1, '.', '' ), '0' ), '.' ); };
        ?>
        <svg class="site-tagline" viewBox="0 0 1437.75 <?php echo $fmt( $height ); ?>" role="img" aria-label="<?php echo esc_attr( $tagline ); ?>">
            <text x="<?php echo $fmt( $ink_l - 0.095 * $font ); ?>" y="<?php echo $fmt( $pad + 0.75 * $font ); ?>" font-size="<?php echo $fmt( $font ); ?>"
                  textLength="<?php echo $fmt( $ink_w + 0.132 * $font ); ?>" lengthAdjust="spacingAndGlyphs"><?php echo esc_html( $tagline ); ?></text>
        </svg>
    <?php endif; ?>
</<?php echo $logo_tag; ?>>


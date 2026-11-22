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
<?php do_action( 'monospace_body_open' ); // dark mode: the look's classes for the mode (design/dark-mode.php) ?>


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





        <?php // Home, Menu, Search, Dark mode, Cart - in the order set in Appearance > Look > Menu bar (design/menu-bar-items.php)
        monospace_menu_bar_render(); ?>

    </div>

</nav>

<?php // Elastic scroll (rubber-scroll.js): GSAP moves this wrapper's content - closed in footer.php
if ( function_exists( 'monospace_rubber_scroll_active' ) && monospace_rubber_scroll_active() ) echo '<div id="smooth-wrapper"><div id="smooth-content">'; ?>



<?php // The home page's <h1>: the logo + tagline (reset to look the same in _header.scss)
$logo_tag = is_front_page() ? 'h1' : 'div'; ?>
<<?php echo $logo_tag; ?> class="site-logo-wrapper">
    <div class="site-logo">
        <?php
        // Link and hover text: Layout Manager > Links & toolbar > Logo link (design/logo-link.php)
        $logo      = function_exists('monospace_logo_link') ? monospace_logo_link() : ['url' => home_url('/category/artworks/available/'), 'title' => 'View available originals'];
        $logo_link = esc_url($logo['url']);
        $logo_title = $logo['title'] !== '' ? ' title="' . esc_attr($logo['title']) . '"' : '';
        // The logo in two parts (2026-10-21): assets/images/logo/monospace and art - an .svg if there is one, else the
        // .webp (made from the owner's .png: ART at WebP quality 80 / alpha 60 - 63 KB instead of 264; monospace lossless),
        // else the .png. Placed by _header.scss where the two halves of logo.svg were (side by side; stacked on phones). Takes
        // priority over logo.svg and over the logo chosen in Customize > Site Identity.
        $logo_part = function ( $name ) {
            foreach ( [ 'svg', 'webp', 'png' ] as $ext ) {
                $file = '/assets/images/logo/' . $name . '.' . $ext;
                if ( file_exists( get_template_directory() . $file ) ) return get_template_directory_uri() . $file . '?v=' . filemtime( get_template_directory() . $file );
            }
            return '';
        };
        $logo_word = $logo_part( 'monospace' );
        $logo_art  = $logo_part( 'art' );
        // The earlier logo: assets/images/logo.svg (viewBox trimmed to the letters - 1437.76 x 202.31), sized by
        // _header.scss to the column width, so it scales with the page
        $logo_svg = '/assets/images/logo.svg';
        if ( $logo_word && $logo_art ) {
            // One line or stacked, apart for computers & tablets and phones; stacked, ART's size and place (Appearance >
            // Look > Logo - design/logo-layout.php)
            $logo_class = 'ms-logo';
            $logo_style = '';
            if ( function_exists( 'monospace_logo_settings' ) ) {
                if ( monospace_logo_settings( 'wide' )['stack'] ) $logo_class .= ' is-stacked-wide';
                if ( monospace_logo_settings( 'phone' )['stack'] ) $logo_class .= ' is-stacked';
                $logo_style = monospace_logo_art_style();
            }
            echo '<a' . $logo_title . ' href="' . $logo_link . '"><span class="' . $logo_class . '"' . ( $logo_style ? ' style="' . esc_attr( $logo_style ) . '"' : '' ) . ' role="img" aria-label="monospace ART">'
               . '<img class="custom-logo ms-logo-word" src="' . esc_url( $logo_word ) . '" alt="" width="1366" height="179" fetchpriority="high">'
               . '<img class="custom-logo ms-logo-art" src="' . esc_url( $logo_art ) . '" alt="" width="948" height="540" fetchpriority="high">'
               . '</span></a>';
        } elseif (file_exists(get_template_directory() . $logo_svg)) {
            echo '<a' . $logo_title . ' href="' . $logo_link . '"><img class="custom-logo" src="' . esc_url(get_template_directory_uri() . $logo_svg . '?v=' . filemtime(get_template_directory() . $logo_svg)) . '" width="1437.76" height="202.31" alt="monospace ART"></a>';
        } elseif (has_custom_logo()) {
            $logo_id  = get_theme_mod('custom_logo');
            $logo_img = wp_get_attachment_image($logo_id, 'full', false, array('class' => 'custom-logo'));
            echo '<a' . $logo_title . ' href="' . $logo_link . '">' . $logo_img . '</a>';
        } else { ?>
            <a href="<?php echo $logo_link; ?>" class="logo-text"><?php bloginfo('name'); ?></a>
        <?php } ?>
    </div>

    <?php
    // The tagline - typed \n starts a new line; one for each screen size when they differ (design/logo-layout.php)
    if ( function_exists( 'monospace_tagline_html' ) ) echo monospace_tagline_html();
    elseif ( get_bloginfo( 'description' ) ) echo '<p class="site-description">' . esc_html( get_bloginfo( 'description' ) ) . '</p>';
    ?>
</<?php echo $logo_tag; ?>>


<?php
/**
 * monospace ART
 * --------------------------------------
 * Theme setup and addon loader
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ------------------------------
 * Theme Constants (define first)
 * ------------------------------
 */
define( 'MONOSPACE_ART_THEME_VERSION', '1.0.0' );
define( 'MONOSPACE_ART_THEME_DIR', get_stylesheet_directory() );
define( 'MONOSPACE_ART_THEME_URI', get_stylesheet_directory_uri() );

/**
 * ------------------------------
 * Global Debug Settings
 * ------------------------------
 */
define( 'MONOSPACE_ADDON_DEBUG', false ); // Set to false to disable loader logging

function monospace_log( $message ) {
	if ( defined( 'MONOSPACE_ADDON_DEBUG' ) && MONOSPACE_ADDON_DEBUG ) {
		error_log( '[monospace-addon] ' . $message ); // goes to SiteGround php_errorlog
	}
}
$priority_addon_files = [
    'inc/addons/astra_site_identity.php',
    'inc/addons/enqueue_styles.php',
];

function monospace_art_theme_load_priority_addons( $files ) {
    foreach ( $files as $relative_path ) {
        $file = get_stylesheet_directory() . '/' . $relative_path;
        if ( file_exists( $file ) ) {
            require_once $file;
            monospace_log( "Loaded priority file: $file" );
        } else {
            monospace_log( "Priority file missing: $file" );
        }
    }
}

function monospace_art_theme_load_remaining_addons( $base_dir, $priority_files, $exclude_dirs = ['disabled'] ) {
    if ( ! is_dir( $base_dir ) ) return;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $base_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $files = [];
    foreach ( $iterator as $file ) {
        if ( $file->isFile() && 'php' === $file->getExtension() ) {
            $files[] = $file->getPathname();
        }
    }

    // Sort alphabetically for predictable load order
    sort($files);

    foreach ( $files as $file_path ) {

        // Skip disabled dirs
        foreach ( $exclude_dirs as $dir ) {
            if ( strpos( $file_path, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR ) !== false ) {
                monospace_log( "Skipped (disabled dir): $file_path" );
                continue 2;
            }
        }

        // Skip priority files
        foreach ( $priority_files as $priority ) {
            if ( realpath($file_path) === realpath( get_stylesheet_directory() . '/' . $priority ) ) {
                monospace_log( "Skipped (priority file already loaded): $file_path" );
                continue 2;
            }
        }

        // Load the file
        require_once $file_path;
        monospace_log( "Loaded: $file_path" );
    }
}

// Hook on init (slightly later)
add_action( 'init', function() use ( $priority_addon_files ) {
    monospace_art_theme_load_priority_addons( $priority_addon_files );
    monospace_art_theme_load_remaining_addons(
        get_stylesheet_directory() . '/inc/addons',
        $priority_addon_files
    );
}, 5 );


// Include About page only in admin
if ( is_admin() ) {
    require_once get_stylesheet_directory() . '/inc/admin/about.php';
}

add_action( 'admin_menu', function() {
    add_theme_page(
        'About monospace ART',  // Page title
        'About monospace ART',  // Menu title
        'edit_theme_options',   // Capability
        'monospace-art-about',  // Menu slug
        'monospace_art_child_about_page' // Callback
    );
});
add_action( 'after_switch_theme', function() {
    if ( current_user_can( 'edit_theme_options' ) ) {
        add_option( 'monospace_art_about_redirect', true );
    }
});

add_action( 'admin_init', function() {
    if ( get_option( 'monospace_art_about_redirect', false ) ) {
        delete_option( 'monospace_art_about_redirect' );
        wp_redirect( admin_url( 'themes.php?page=monospace-art-about' ) );
        exit;
    }
});

// Remove related products from single product pages
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );



// Usage: [product_price id="123"]
function ms_product_price_shortcode( $atts ) {
    $atts = shortcode_atts([ 'id' => null ], $atts);
    if ( ! $atts['id'] ) return '';

    $product = wc_get_product( intval($atts['id']) );
    if ( ! $product ) return '';

    $price_html = $product->get_price_html();
    if ( ! $price_html ) return '';

    // return inline element (no CSS) so it won't cause block margin collapse
    return '<span class="ms-product-price">' . wp_kses_post( $price_html ) . '</span>';
}
add_shortcode( 'product_price', 'ms_product_price_shortcode' );


/**
 * Show publication date for posts in the "blog" category:
 * - single blog posts
 * - blog category archive
 * - main posts feed (home/blog index)
 */
add_filter( 'the_content', 'ms_show_date_on_blog_posts_everywhere' );
function ms_show_date_on_blog_posts_everywhere( $content ) {

    // We only add dates to normal posts
    if ( get_post_type() !== 'post' ) {
        return $content;
    }

    $is_blog_post = has_category( 'blog' );

    // --- SINGLE POST ---
    if ( is_singular( 'post' ) && $is_blog_post ) {
        $date_html = '<p class="post-date">' . get_the_date() . '</p>';
        return $date_html . $content;
    }

    // --- BLOG CATEGORY ARCHIVE ---
    if ( is_category( 'blog' ) && is_main_query() ) {
        $date_html = '<p class="post-date">' . get_the_date() . '</p>';
        return $date_html . $content;
    }

    // --- MAIN FEED (home / posts page) ---
    if ( ( is_home() || is_archive() ) && is_main_query() && $is_blog_post ) {
        $date_html = '<p class="post-date">' . get_the_date() . '</p>';
        return $date_html . $content;
    }

    return $content;
}




<?php

/*
Theme Name: monospace ART
Theme URI: https://art.monospace.com
Author: Hens Breet
Description: Custom top-level theme for monospace ART.
Version: 1.0.0
Text Domain: monospace-art-theme
*/

define( 'MONOSPACE_ART_THEME_VERSION', '1.0.0' );
define( 'MONOSPACE_ART_THEME_DIR', get_template_directory() );
define( 'MONOSPACE_ART_THEME_URI', get_template_directory_uri() );

require_once MONOSPACE_ART_THEME_DIR . '/inc/core/enqueue.php';
require_once MONOSPACE_ART_THEME_DIR . '/inc/core/setup.php';
require_once MONOSPACE_ART_THEME_DIR . '/inc/core/sidebars.php';
require_once MONOSPACE_ART_THEME_DIR . '/inc/core/editor.php';


/**
 * ------------------------------
 * Global Debug Settings
 * ------------------------------
 */
define( 'MONOSPACE_MODULE_DEBUG', true ); // Set to false to disable loader logging

function monospace_log( $message ) {
    if ( defined( 'MONOSPACE_MODULE_DEBUG' ) && MONOSPACE_MODULE_DEBUG ) {
        error_log( '[monospace-module] ' . $message ); // goes to SiteGround php_errorlog
    }
}

/**
 * ------------------------------
 * Module Loaders
 * ------------------------------
 */
function monospace_art_load_modules() {
    $base_dir = MONOSPACE_ART_THEME_DIR . '/inc/modules';
    if ( ! is_dir( $base_dir ) ) return;

    $exclude_dirs = ['disabled'];

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

        // Load the file
        require_once $file_path;
        monospace_log( "Loaded: $file_path" );
    }
}
// module bootstrap
add_action( 'init', 'monospace_art_load_modules');















// Include About page only in admin
if ( is_admin() ) {
   // require_once get_stylesheet_directory() . '/inc/admin/about.php';
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











// Index only the last 4 digits of the SKU for search, allowing partial matches
add_filter('relevanssi_content_to_index', function ($content, $post) {
    $sku = get_post_meta($post->ID, '_sku', true);
    if ($sku) {
        // Extract last 4 digits
        if (preg_match('/(\d{4})$/', $sku, $matches)) {
            $last4 = $matches[1];
            // Add full last 4 digits and each partial substring
            for ($i = 1; $i <= 4; $i++) {
                $content .= ' ' . substr($last4, -$i);
            }
        }
    }
    return $content;
}, 10, 2);

// Boost relevance for exact or partial last-4 matches
add_filter('relevanssi_hits_filter', function ($hits, $query) {
    $q = $query->query_vars['s'];
    foreach ($hits as $id => $hit) {
        $sku = get_post_meta($hit->ID, '_sku', true);
        if ($sku && preg_match('/(\d{4})$/', $sku, $matches)) {
            $last4 = $matches[1];
            if (strpos($last4, $q) !== false) {
                // Boost relevance
                $hits[$id]->relevance += 10;
            }
        }
    }
    return $hits;
}, 10, 2);













// custom category headers

/**
 * Add custom headers to specific category archives
 */
add_action( 'astra_primary_content_top', 'monospace_category_custom_header' );

function monospace_category_custom_header() {

	if ( is_category() ) {

		$category = get_queried_object();

		if ( ! $category || empty( $category->slug ) ) {
			return;
		}

		switch ( $category->slug ) {

			case 'drawing':
				$title = 'Drawings';
				$description = 'Works in pen & ink, with added watercolor or markers';
				$description .= '<small><br><br>&bullet; Click any image for details</small>';
				$description .= '<small><br>&bullet; Order a custom drawing <a href="/services/custom-work">here</a></small>';
				break;

			case 'painting':
				$title = 'Paintings';
				$description = 'Paintings made with casein, gouache, or acrylics';
				$description .= '<small><br><br>&bullet; Click any image for details</small>';
				$description .= '<small><br>&bullet; Order a custom painting <a href="/services/custom-work">here</a></small>';
				break;

			case 'miniature':
				$title = 'Miniatures';
				$description = 'Miniature acrylic paintings on 4" &times; 4" canvas panels';
				$description .= '<small><br><br>&bullet; Click any image for details</small>';
				$description .= '<small><br>&bullet; Order a custom miniature painting <a href="/services/custom-work">here</a></small>';
				break;



			default:
				return; // Do nothing for other categories
		}

		echo '<header class="archive-custom-header">';
		echo '<h2 class="archive-title">' . esc_html( $title ) . '</h2>';

		if ( ! empty( $description ) ) {
			echo '<p class="archive-description">' . $description . '</p>';
		}

		echo '</header>';
	}
}




// Usage: [external_html url="https://example.com/content.html" class="my-custom-class"]
add_shortcode('external_html', function($atts) {
    $url   = $atts['url'] ?? '';
    $class = $atts['class'] ?? 'external-html-container'; // default class

    if (empty($url)) {
        return 'No URL provided';
    }

    $response = wp_remote_get($url);

    if (is_wp_error($response)) {
        return 'Error loading content';
    }

    $html = wp_remote_retrieve_body($response);

    // Optionally sanitize/filter the HTML
   // $html = wp_kses_post($html);

    // Wrap in a div with a class for styling
    return sprintf('<div class="%s">%s</div>', esc_attr($class), $html);
});

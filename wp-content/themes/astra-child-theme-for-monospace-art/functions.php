<?php
/**
 * Astra Child Theme for monospace | art
 * --------------------------------------
 * Theme setup and addon loader
 *
 * @package astra-child-theme-for-monospace-art
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ------------------------------
 * Theme Constants (define first)
 * ------------------------------
 */
define( 'CHILD_THEME_ASTRA_CHILD_THEME_FOR_MONOSPACE_ART_VERSION', '1.0.0' );
define( 'CHILD_THEME_MONOSPACE_DIR', get_stylesheet_directory() );
define( 'CHILD_THEME_MONOSPACE_URI', get_stylesheet_directory_uri() );

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

/**
 * ------------------------------
 * Define priority files (once)
 * Paths are relative to theme root
 * ------------------------------
 */
$priority_addon_files = [
    'addons/astra_site_identity.php', // custom logo link
	'addons/enqueue_styles.php',         // needs constants defined first
	//'addons/frontend/frontend-init.php',
	//'addons/admin/admin-init.php',
];

/**
 * ------------------------------
 * Load priority files
 * ------------------------------
 */
function astra_child_monospace_load_priority_addons( $files ) {
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

/**
 * ------------------------------
 * Recursively load remaining addons
 * ------------------------------
 */
function astra_child_monospace_load_remaining_addons( $base_dir, $priority_files, $exclude_dirs = [ 'disabled' ] ) {
	if ( ! is_dir( $base_dir ) ) {
		monospace_log( "Base directory does not exist: $base_dir" );
		return;
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $base_dir, RecursiveDirectoryIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( $file->isFile() && 'php' === $file->getExtension() ) {
			$file_path = $file->getPathname();

			// Skip disabled directories
			$skip = false;
			foreach ( $exclude_dirs as $dir ) {
				if ( strpos( $file_path, DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR ) !== false ) {
					$skip = true;
					monospace_log( "Skipped (disabled dir): $file_path" );
					break;
				}
			}
			if ( $skip ) continue;

			// Skip priority files
			foreach ( $priority_files as $priority ) {
				if ( str_ends_with( $file_path, basename( $priority ) ) ) {
					$skip = true;
					monospace_log( "Skipped (priority file already loaded): $file_path" );
					break;
				}
			}
			if ( $skip ) continue;

			// Load the file
			require_once $file_path;
			monospace_log( "Loaded: $file_path" );
		}
	}
}

/**
 * ------------------------------
 * Hook everything up
 * ------------------------------
 */
add_action( 'after_setup_theme', function() use ( $priority_addon_files ) {
	// 1. Load priority files (constants guaranteed to exist first)
	astra_child_monospace_load_priority_addons( $priority_addon_files );

	// 2. Load all remaining files recursively
	astra_child_monospace_load_remaining_addons(
		get_stylesheet_directory() . '/addons',
		$priority_addon_files
	);
});










add_filter('the_content', 'monospace_miniature_cover_fullwidth');
add_filter('the_excerpt', 'monospace_miniature_cover_fullwidth');

function monospace_miniature_cover_fullwidth($content) {

    global $post;
    if (!$post) return $content;

    // Only apply to posts in category "miniature"
    if (!has_category('miniature', $post)) return $content;

    // Find the first image tag
    if (!preg_match('/<img[^>]+>/i', $content, $match)) return $content;

    $img_tag = $match[0];

    // Extract src
    if (!preg_match('/src=["\']([^"\']+)["\']/i', $img_tag, $src_match)) return $content;

    $src = $src_match[1];

    // =====================
    // CONFIGURABLE OPTIONS
    // =====================
    $overlay_opacity = 0.5;   // 0 = transparent, 1 = black
    $mini_scale      = 0.95;  // 1 = full size, 0.5 = 50%
    $cover_height    = '60vh'; 

    // Wrapper mimicking WP Cover
    $replacement = '
    <div style="
        position:relative;
        width:100%;
        height:' . esc_attr($cover_height) . ';
        background-image:url(' . esc_url($src) . ');
        background-size:cover;
        background-position:center;
        overflow:hidden;
        margin:1em 0;
    ">
        <!-- Dark overlay -->
        <div style="
            position:absolute;
            inset:0;
            background:rgba(0,0,0,' . esc_attr($overlay_opacity) . ');
        "></div>

        <!-- Centered mini image -->
        <div style="
            position:absolute;
            top:50%;
            left:50%;
            transform:translate(-50%, -50%) scale(' . esc_attr($mini_scale) . ');
            transform-origin:center;
        ">
            ' . $img_tag . '
        </div>

        <!-- Bottom-right label -->
        <div style="
            position:absolute;
            bottom:10px;
            right:10px;
            padding:4px 8px;
            font-size:12px;
            letter-spacing:0.5px;
            background:rgba(0,0,0,0.6);
            color:white;
            border-radius:4px;
            font-family:inherit;
        ">
            4×4 miniature acrylic painting
        </div>
    </div>
    ';

    return preg_replace('/<img[^>]+>/i', $replacement, $content, 1);
}




add_filter('the_content', 'monospace_fullsize_painting_label');
add_filter('the_excerpt', 'monospace_fullsize_painting_label');

function monospace_fullsize_painting_label($content) {
    global $post;
    if (!$post) return $content;

    // Only apply on home or archive pages
    if (!is_home() && !is_archive()) return $content;

    // Skip if post is in miniature category
    if (has_category('miniature', $post)) return $content;

    // Find the first image tag
    if (!preg_match('/<img[^>]+>/i', $content, $match)) return $content;

    $img_tag = $match[0];

    // Default label based on category
    $label_text = 'full size painting';
    if (has_category('drawing', $post)) {
        $label_text = 'full size drawing';
    }

    // Try to find painting_buy_button shortcode
    $post_content = get_post_field('post_content', $post->ID);
    if (preg_match('/\[painting_buy_button\s+id=["\']?(\d+)["\']?\]/i', $post_content, $shortcode_match)) {
        $product_id = intval($shortcode_match[1]);
        
        // Check if WooCommerce is active and product exists
        if (function_exists('wc_get_product')) {
            $product = wc_get_product($product_id);
            
            if ($product) {
                $size_slug = '';
                $medium_slug = '';
                
                // Get size taxonomy term
                $size_terms = get_the_terms($product_id, 'pa_size');
                if ($size_terms && !is_wp_error($size_terms)) {
                    $size_slug = $size_terms[0]->slug;
                }
                
                // Get medium taxonomy term
                $medium_terms = get_the_terms($product_id, 'pa_medium');
                if ($medium_terms && !is_wp_error($medium_terms)) {
                    $medium_slug = $medium_terms[0]->slug;
                }
                
                // Build label if we have data
                if ($size_slug || $medium_slug) {
                    $label_parts = array_filter([$size_slug, $medium_slug]);
                    
                    // Determine type based on category
                    $type = has_category('drawing', $post) ? 'drawing' : 'painting';
                    $label_parts[] = $type;
                    
                    $label_text = implode(' ', $label_parts);
                }
            }
        }
    }

    // Wrap image with label
    $replacement = '
    <div style="position:relative; display:inline-block; width:100%;">
        ' . $img_tag . '
        <div style="
            position:absolute;
            bottom:10px;
            right:10px;
            padding:4px 8px;
            font-size:12px;
            letter-spacing:0.5px;
            background:rgba(0,0,0,0.6);
            color:white;
            border-radius:4px;
            font-family:inherit;
        ">
            ' . esc_html($label_text) . '
        </div>
    </div>
    ';

    return preg_replace('/<img[^>]+>/i', $replacement, $content, 1);
}


// exclude miniatures from paintings page
function monospace_exclude_miniatures_from_paintings($query) {
    if (!is_admin() && $query->is_main_query() && is_category('painting')) {
        $query->set('category__not_in', [ get_cat_ID('miniature') ]);
    }
}
add_action('pre_get_posts', 'monospace_exclude_miniatures_from_paintings');

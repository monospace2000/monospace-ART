<?php
/**
 * Disable Plugin Rating Notices
 *
 * Removes annoying plugin rating reminders from the WordPress Dashboard.
 * Hides notices that ask to rate/review plugins on WordPress.org.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Remove rating notices with CSS (fastest method)
 */
add_action('admin_head', function() {
    ?>
    <style>
        /* Hide rating notices - target common patterns */
        .notice[class*="rate"],
        .notice[class*="review"],
        .notice[class*="rating"],
        div[class*="rate-plugin"],
        div[class*="review-plugin"],
        .notice:has(a[href*="wordpress.org/support/plugin"]):has(a[href*="reviews"]),
        .notice:has(a[href*="rate"]),
        .updated.notice:has(a[href*="wordpress.org"]):has(a[href*="review"]) {
            display: none !important;
        }
    </style>
    <?php
});

/**
 * Filter out rating notices from common plugins
 * This removes them before they're rendered
 */
add_action('admin_init', function() {
    // Remove WooCommerce rating notice
    remove_action('admin_notices', 'woothemes_queue_update');

    // Remove common rating notice actions
    global $wp_filter;

    if (isset($wp_filter['admin_notices'])) {
        foreach ($wp_filter['admin_notices']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $key => $callback) {
                // Check if callback contains rating-related keywords
                if (is_string($key) && (
                    stripos($key, 'rate') !== false ||
                    stripos($key, 'review') !== false ||
                    stripos($key, 'rating') !== false
                )) {
                    unset($wp_filter['admin_notices']->callbacks[$priority][$key]);
                }
            }
        }
    }
}, 999);

/**
 * Disable specific plugin rating notices by plugin slug
 */
add_action('admin_init', function() {
    // Add plugin slugs here to disable their notices
    $disabled_notices = [
        'woocommerce',
        'advanced-custom-fields',
        'akismet',
        'jetpack',
        'yoast',
        'wordfence',
    ];

    foreach ($disabled_notices as $slug) {
        // Remove various rating notice formats
        remove_action('admin_notices', $slug . '_admin_notice');
        remove_action('admin_notices', $slug . '_review_notice');
        remove_action('admin_notices', $slug . '_rating_notice');
        remove_action('admin_notices', $slug . '_rate_notice');

        // Update user meta to mark notices as dismissed
        $user_id = get_current_user_id();
        update_user_meta($user_id, $slug . '_rating_dismissed', true);
        update_user_meta($user_id, $slug . '_review_dismissed', true);
        update_user_meta($user_id, 'dismissed_wp_pointers', true);
    }
}, 999);

/**
 * Disable WordPress.org plugin API calls for ratings
 * This prevents plugins from fetching rating data
 */
add_filter('pre_http_request', function($preempt, $args, $url) {
    // Block requests to WordPress.org API for plugin info/ratings
    if (strpos($url, 'api.wordpress.org') !== false &&
        (strpos($url, 'plugin_information') !== false ||
         strpos($url, 'reviews') !== false)) {
        return new WP_Error('rating_check_disabled', 'Rating checks disabled');
    }
    return $preempt;
}, 10, 3);

<?php
/**
 * External HTML Shortcode
 *
 * Allows embedding external HTML content into posts and pages via shortcode.
 * Useful for including content hosted elsewhere without iframe limitations.
 *
 * SHORTCODE USAGE:
 * [external_html url="https://example.com/content.html" class="my-custom-class"]
 *
 * PARAMETERS:
 * - url (required): Full URL to the external HTML file
 * - class (optional): CSS class for the wrapper div (default: "external-html-container")
 *
 * FUNCTIONALITY:
 * - Fetches HTML content from external URL using wp_remote_get()
 * - Wraps content in a div with custom or default class
 * - Handles errors gracefully with user-friendly messages
 * - Respects WordPress security and timeout settings
 *
 * USE CASES:
 * - Embedding content from external documentation
 * - Including HTML from other services
 * - Displaying remote HTML snippets
 *
 * SECURITY NOTE:
 * Only fetch content from trusted sources. The HTML is output directly.
 */

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


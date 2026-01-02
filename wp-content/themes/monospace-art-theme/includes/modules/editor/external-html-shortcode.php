<?php

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


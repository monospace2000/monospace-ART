<?php

/**
 * Inject custom headers for search and archive pages with inline CSS,
 * with option to exclude certain categories or tags by slug.
 */
add_filter('the_content', 'monospace_custom_archive_headers', 5); // run early

function monospace_custom_archive_headers($content) {
    if (!is_home() && !is_archive() && !is_search()) {
        return $content;
    }

    global $post;
    $header = '';

    $inline_style = 'font-size:1.5em; font-weight:bold; margin:1em 0;';

    // Configure slugs to exclude
    $excluded_category_slugs = ['painting', 'drawing', 'miniature']; // slugs to exclude
    $excluded_tag_slugs = ['private', 'archive-only'];               // slugs to exclude

    if (is_search()) {
        $search_query = get_search_query();
        $header = '<h4 style="' . esc_attr($inline_style) . '">🔎 Results for "' . esc_html($search_query) . '"</h4>';
    } elseif (is_category()) {
        $cat = get_queried_object();
        if (!in_array($cat->slug, $excluded_category_slugs)) {
            $header = '<h4 style="' . esc_attr($inline_style) . '">📁 Category: ' . esc_html($cat->name) . '</h4>';
        }
    } elseif (is_tag()) {
        $tag = get_queried_object();
        if (!in_array($tag->slug, $excluded_tag_slugs)) {
            $header = '<h4 style="' . esc_attr($inline_style) . '">🏷️ Tag: ' . esc_html($tag->name) . '</h4>';
        }
    }

    // Prepend header to the content
    return $header . $content;
}

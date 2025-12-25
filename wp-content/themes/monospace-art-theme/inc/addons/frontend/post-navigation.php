<?php


// Next post link
add_filter('next_post_link', function($output) {
    if (preg_match('/href=[\'"](.*?)[\'"].*?>(.*?)<\/a>/', $output, $matches)) {
        $url = $matches[1];
        //$output = '<a class="nav-left" style="font-family: Open Sans, sans-serif" href="' . esc_url($url) . '" rel="next"></span>◂&nbsp;NEWER</a>';
        $output = '<a class="button monospace-nav-left" href="' . esc_url($url) . '" rel="next">◂ NEWER</a>';
    }
    return $output;
});

// Previous post link
add_filter('previous_post_link', function($output) {
    // Grab the post URL and wrap it with custom label
    if (preg_match('/href=[\'"](.*?)[\'"].*?>(.*?)<\/a>/', $output, $matches)) {
        $url = $matches[1];
        //$output = '<a class="nav-right" style="font-family: Open Sans, sans-serif" href="' . esc_url($url) . '" rel="prev">OLDER&nbsp;▸</a>';
        $output = '<a class="button monospace-nav-right" href="' . esc_url($url) . '" rel="prev">OLDER ▸</a>';
    }
    return $output;
});



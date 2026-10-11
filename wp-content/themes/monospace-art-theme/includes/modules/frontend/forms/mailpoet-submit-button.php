<?php

// re-wrap the MailPoet submit button to allow for styling
add_filter('the_content', function($content) {
    if (strpos($content, 'mailpoet_submit') !== false) {
        $content = preg_replace(
            '/<input\s+([^>]*?)class="([^"]*mailpoet_submit[^"]*)"([^>]*?)value="([^"]*)"([^>]*)>/i',
            '<button type="submit" $1class="$2"$3$5>$4</button>',
            $content
        );
        // Remove inline styles from the button
        $content = preg_replace(
            '/(<button[^>]*class="[^"]*mailpoet_submit[^"]*"[^>]*)\s+style="[^"]*"([^>]*>)/i',
            '$1$2',
            $content
        );
    }
    return $content;
}, 20);

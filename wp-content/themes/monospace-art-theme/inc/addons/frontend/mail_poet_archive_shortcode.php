<?php

// Shortcode: [mailpoet_archive]
add_shortcode('mailpoet_archive', function($atts) {
    // Pull all sent newsletters (MailPoet 3)
    $newsletters = get_posts([
        'post_type'      => 'mailpoet_newsletter',
        'post_status'    => 'sent',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    if (!$newsletters) {
        return '<p>No newsletters found.</p>';
    }

    // Start output
    $out = '<ul class="mailpoet-archive">';

    foreach ($newsletters as $nl) {
        // Get public browser view URL via MailPoet API
        try {
            $mp_newsletter = \MailPoet\Models\Newsletter::findOne($nl->ID);
            $view_url = $mp_newsletter ? $mp_newsletter->getBrowserViewUrl() : '';
        } catch (Exception $e) {
            continue;
        }

        if (!$view_url) continue;

        $title = esc_html($nl->post_title);

        $out .= '<li><a href="' . esc_url($view_url) . '">' . $title . '</a></li>';
    }

    $out .= '</ul>';

    return $out;
});

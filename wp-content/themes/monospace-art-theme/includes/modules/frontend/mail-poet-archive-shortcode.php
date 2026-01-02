<?php
add_shortcode('mailpoet_archive', function () {
    if (!class_exists('\MailPoet\API\API')) {
        return '<p>MailPoet is not available.</p>';
    }

    // Get sent newsletters with their queue IDs
    global $wpdb;
    $newsletters_table = $wpdb->prefix . 'mailpoet_newsletters';
    $queues_table = $wpdb->prefix . 'mailpoet_sending_queues';

    $newsletters = $wpdb->get_results(
        "SELECT n.id, n.subject, n.hash, n.sent_at, q.id as queue_id
         FROM {$newsletters_table} n
         LEFT JOIN {$queues_table} q ON n.id = q.newsletter_id
         WHERE n.status = 'sent'
         AND n.type = 'standard'
         AND n.hash IS NOT NULL
         AND n.hash != ''
         ORDER BY n.sent_at DESC
         LIMIT 1000",
        ARRAY_A
    );

    if (empty($newsletters)) {
        return '<p>No newsletters found.</p>';
    }

    $out = '<ul class="mailpoet-archive">';
    foreach ($newsletters as $nl) {
        $subject = $nl['subject'];
        $newsletter_id = (int)$nl['id'];
        $hash = $nl['hash'];
        $queue_id = (int)($nl['queue_id'] ?? 0);

        if (empty($subject) || empty($newsletter_id) || empty($hash)) {
            continue;
        }

        // Create the data array with the correct queue_id
        $data = [$newsletter_id, $hash, 0, 0, $queue_id, 1];
        $encoded_data = rtrim(base64_encode(json_encode($data)), '=');

        $url = home_url('/?mailpoet_router&endpoint=view_in_browser&action=view&data=' . $encoded_data);

        $out .= sprintf(
            '<li><a href="%s" target="_blank">%s</a></li>',
            esc_url($url),
            esc_html($subject)
        );
    }
    $out .= '</ul>';

    return $out;
});
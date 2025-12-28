<?php
add_shortcode('mailpoet_archive', function () {
    if (!class_exists('\MailPoet\API\API')) {
        return '<p>MailPoet is not available.</p>';
    }

    try {
        $api = \MailPoet\API\API::MP('v1');
    } catch (\Throwable $e) {
        return '<p>MailPoet API init failed: ' . esc_html($e->getMessage()) . '</p>';
    }

    try {
        // Use getListing instead
        $newsletters = $api->getListing([
            'filter' => ['status' => 'sent'],
            'limit' => 1000,
            'sort_by' => 'sent_at',
            'sort_order' => 'desc'
        ]);

        // getListing returns items in a 'items' key
        if (isset($newsletters['items'])) {
            $newsletters = $newsletters['items'];
        }
    } catch (\Throwable $e) {
        // Try direct database query as fallback
        global $wpdb;
        $table = $wpdb->prefix . 'mailpoet_newsletters';

        $newsletters = $wpdb->get_results(
            "SELECT * FROM {$table}
             WHERE status = 'sent'
             AND type = 'standard'
             ORDER BY sent_at DESC
             LIMIT 1000",
            ARRAY_A
        );

        if ($wpdb->last_error) {
            return '<p>Unable to load newsletters: ' . esc_html($e->getMessage()) . '</p>';
        }
    }

    if (empty($newsletters)) {
        return '<p>No newsletters found.</p>';
    }

    $out = '<ul class="mailpoet-archive">';
    foreach ($newsletters as $nl) {
        $subject = $nl['subject'] ?? '';
        $hash = $nl['hash'] ?? '';

        if (empty($subject) || empty($hash)) {
            continue;
        }

        // Construct preview URL manually
        $url = home_url('?mailpoet_router&endpoint=view_in_browser&action=view&data=') . $hash;

        $out .= sprintf(
            '<li><a href="%s" target="_blank">%s</a></li>',
            esc_url($url),
            esc_html($subject)
        );
    }
    $out .= '</ul>';

    return $out;
});
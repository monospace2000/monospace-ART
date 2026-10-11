<?php

function monospace_mailpoet_form_shortcode($atts) {
    // Get list ID from shortcode attribute, default to 3
    $atts = shortcode_atts(['list_id' => 3], $atts);

    ob_start();
    ?>
    <style>
        .monospace-mailpoet-form input[type="text"], .monospace-mailpoet-form input[type="email"]{
            width: 100%;
            margin-bottom: 10px;
        }
        button.submit-button{
            float: right;
        }
        .form-message {
            clear: both;
            padding: 10px;
            margin-top: 10px;
            border-radius: 4px;
        }
        .form-message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .form-message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
    <form class="monospace-mailpoet-form" method="post" action="">
        <?php wp_nonce_field('monospace_mailpoet_subscribe', 'monospace_mailpoet_nonce'); ?>

        <div class="form-field">
            <input type="text" id="mp-first-name" name="first_name" required placeholder="First Name *">
        </div>

        <div class="form-field">
            <input type="text" id="mp-last-name" name="last_name" placeholder="Last Name">
        </div>

        <div class="form-field">
            <input type="email" id="mp-email" name="email" required placeholder="Email *">
        </div>

        <input type="hidden" name="list_id" value="<?php echo esc_attr($atts['list_id']); ?>">
        <input type="hidden" name="monospace_mailpoet_submit" value="1">

        <button type="submit" class="submit-button">Subscribe</button>

        <div class="form-message">
            <?php
            if (isset($_GET['mp_success'])) {
                echo '<p class="success">Thank you for subscribing! Please check your email to confirm your subscription.</p>';
            }
            if (isset($_GET['mp_error'])) {
                $error = sanitize_text_field($_GET['mp_error']);
                if ($error === 'invalid') {
                    echo '<p class="error">Security check failed. Please try again.</p>';
                } else {
                    echo '<p class="error">Something went wrong. Please try again later.</p>';
                }
            }
            ?>
        </div>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('monospace_mailpoet_form', 'monospace_mailpoet_form_shortcode');

// Handle form submission
function monospace_mailpoet_handle_submission() {
    if (!isset($_POST['monospace_mailpoet_submit'])) {
        return;
    }

    if (!wp_verify_nonce($_POST['monospace_mailpoet_nonce'], 'monospace_mailpoet_subscribe')) {
        wp_redirect(add_query_arg('mp_error', 'invalid', wp_get_referer()));
        exit;
    }

    $subscriber_data = [
        'email' => sanitize_email($_POST['email']),
        'first_name' => sanitize_text_field($_POST['first_name']),
    ];

    if (!empty($_POST['last_name'])) {
        $subscriber_data['last_name'] = sanitize_text_field($_POST['last_name']);
    }

    $list_id = intval($_POST['list_id']);

    try {
        \MailPoet\API\API::MP('v1')->addSubscriber($subscriber_data, [$list_id]);
        wp_redirect(add_query_arg('mp_success', '1', wp_get_referer()));
        exit;
    } catch (\Exception $e) {
        wp_redirect(add_query_arg('mp_error', 'failed', wp_get_referer()));
        exit;
    }
}
add_action('template_redirect', 'monospace_mailpoet_handle_submission');
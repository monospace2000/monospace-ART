<?php
/**
 * The commission email classes. Loaded from the plugin's main file on woocommerce_email_classes: WooCommerce builds
 * its mailer before the modules/ load, so these can't live in a module. They use functions of the modules
 * (ms_commission_*) only when an email is built, long after init.
 */

if (!defined('ABSPATH')) exit;
if (!class_exists('WC_Email') || class_exists('MS_Commission_Email')) return;


/**
 * Shared: render a body between the WooCommerce email header and footer, html or plain
 * (through the header/footer actions, as WooCommerce's own emails do, so MailPoet's template and the footer text apply)
 */
abstract class MS_Commission_Email extends WC_Email {
    public $item_name = '';
    public $body_html = '';
    public $body_text = '';

    protected function wrap_html($body) {
        ob_start();
        do_action('woocommerce_email_header', $this->get_heading(), $this);
        echo $body;
        $extra = $this->get_additional_content();
        if ($extra) echo '<p>' . wp_kses_post(wptexturize($extra)) . '</p>';
        do_action('woocommerce_email_footer', $this);
        return ob_get_clean();
    }

    public function get_content_html() { return $this->wrap_html($this->body_html); }

    public function get_content_plain() {
        $extra = $this->get_additional_content();
        return wp_strip_all_tags($this->body_text . ($extra ? "\n\n" . $extra : '')) . "\n\n" . wp_strip_all_tags(apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text')));
    }

    public function get_default_additional_content() { return ''; }

    /**
     * The approval request, in the same words in every email that carries it (the finished-artwork update and a message sent while the
     * finished artwork waits for approval; owner, 2026-11-23). The button lands on the order's Approve final artwork button
     * (#approve-<id>, commissions-workflow.php). @return array [html, text]
     */
    protected function approve_block($order) {
        $approve = wc_get_account_endpoint_url('my-commissions') . '#approve-' . $order->get_id();
        $html = '<div style="border:3px solid #0c0519;border-radius:8px;padding:20px 22px;margin:24px 0;background:#f4f1ea">'
            . '<p style="margin:0 0 10px;font-size:1.25em;font-weight:bold;color:#0c0519">One step left: please approve your artwork</p>'
            . '<p style="margin:0 0 12px">Before I can prepare your artwork for shipping, I need your approval <strong>on the site</strong>. Please open your commission page and press the <strong>Approve final artwork</strong> button.</p>'
            . '<p style="margin:0 0 18px"><strong>A reply to this email, or a comment such as "Looks great", does not count as approval.</strong> Only the button does.</p>'
            . '<p style="margin:0 0 18px;text-align:center"><a href="' . esc_url($approve) . '" style="background:#0c0519;color:#ffffff;text-decoration:none;padding:16px 32px;border-radius:6px;display:inline-block;font-weight:bold;font-size:1.15em">Approve final artwork</a></p>'
            . '<p style="margin:0;font-size:.95em">What happens next: you approve, I send your final payment invoice, and once it is paid your artwork is prepared and shipped.</p>'
            . '</div>'
            . '<p>Would you like something changed instead? Reply to this email or add a comment on your commission page, and we will talk it through before you approve.</p>';
        $text = "\n==============================\nONE STEP LEFT: PLEASE APPROVE YOUR ARTWORK\n==============================\n"
            . "Before I can prepare your artwork for shipping, I need your approval ON THE SITE. Please open your commission page and press the Approve final artwork button.\n\n"
            . "A reply to this email, or a comment such as \"Looks great\", does not count as approval. Only the button does.\n\n"
            . "Approve final artwork: " . $approve . "\n\n"
            . "What happens next: you approve, I send your final payment invoice, and once it is paid your artwork is prepared and shipped.\n\n"
            . "Would you like something changed instead? Reply to this email or add a comment on your commission page, and we will talk it through before you approve.\n";
        return [$html, $text];
    }

    /** No second greeting when the owner's message already opens with one ("Hi Frances," + "Hi,", 2026-11-22) */
    protected function greeting($order, $message) {
        if (preg_match('/^\s*(hi|hello|hey|dear|good (morning|afternoon|evening))\b/i', $message)) return '';
        return sprintf('Hi %s,', $order->get_billing_first_name() ?: 'there');
    }

    public function get_headers() {
        $headers = parent::get_headers();
        if ($this->customer_email) $headers .= 'Bcc: ' . ms_commission_admin_email() . "\r\n";
        return $headers;
    }
}

/**
 * To the customer, once, when the deposit order is placed/paid: what happens next
 */
class MS_Email_Commission_Received extends MS_Commission_Email {
    public function __construct() {
        $this->id = 'ms_commission_received';
        $this->customer_email = true;
        $this->title = 'Commission received (customer)';
        $this->description = 'Sent once to the customer when their commission deposit order comes in: what you received, what happens next and the terms.';
        $this->template_html = '';
        $this->template_plain = '';
        $this->placeholders = ['{order_number}' => '', '{item_name}' => ''];
        parent::__construct();
    }
    public function get_default_subject() { return 'We received your commission order (#{order_number})'; }
    public function get_default_heading() { return 'Thank you - your commission is in'; }

    public function trigger($order_id, $order = false) {
        $this->setup_locale();
        if ($order_id && !is_a($order, 'WC_Order')) $order = wc_get_order($order_id);
        if (!$order) { $this->restore_locale(); return; }

        $this->object = $order;
        $this->recipient = $order->get_billing_email();
        $items = ms_commission_order_items($order);
        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{item_name}'] = $items ? ms_commission_friendly_name(reset($items)->get_name()) : '';

        $first = $order->get_billing_first_name() ?: 'there';
        $html = '<p>' . sprintf('Hi %s,', esc_html($first)) . '</p>';
        $text = sprintf('Hi %s,', $first) . "\n\n";
        $html .= '<p>Thank you for your commission. Here is what I received and what happens next.</p>';
        $text .= "Thank you for your commission. Here is what I received and what happens next.\n\n";

        // A guest's account was made at checkout: point down to its set-password link (further below the fold)
        $user = $order->get_customer_id() ? get_userdata($order->get_customer_id()) : false;
        $new_account = $user && get_user_meta($user->ID, '_ms_account_via_commission', true);
        if ($new_account) {
            $html .= '<p><strong>Please read to the end</strong> - the last part of this email has your account and the link to choose your password. You will need it to follow your commission.</p>';
            $text .= "Please read to the end - the last part of this email has your account and the link to choose your password. You will need it to follow your commission.\n\n";
        }

        foreach ($items as $item) {
            $full = (float) ms_get_commission_meta($item, 'full_price');
            $deposit = (float) ms_get_commission_meta($item, 'deposit_paid');
            $refs = ms_normalize_image_array(ms_get_commission_meta($item, 'reference_images'));
            $note = ms_get_commission_meta($item, 'special_request');
            $line = sprintf('%s - total %s, deposit %s, balance after approval %s; %d reference photo(s)',
                ucfirst(ms_commission_friendly_name($item->get_name())), wc_price($full), wc_price($deposit), wc_price(max(0, $full - $deposit)), count($refs));
            $html .= '<p><strong>' . wp_kses_post($line) . '</strong>' . ($note ? '<br>Your notes: ' . esc_html($note) : '') . '</p>';
            $text .= wp_strip_all_tags(html_entity_decode($line)) . ($note ? "\nYour notes: " . $note : '') . "\n\n";
        }

        $html .= '<p><strong>What happens next</strong></p><ol>';
        $text .= "What happens next\n";
        foreach (ms_commission_steps() as $i => $step) {
            if ($i === 0) continue; // step 1 (deposit + photos) is what just happened
            $html .= '<li>' . esc_html($step) . '</li>';
            $text .= ($i + 1) . '. ' . $step . "\n";
        }
        $html .= '</ol>' . ms_commission_policies_html();
        $text .= "\nGood to know\n" . ms_commission_policies_text() . "\n";

        $url = wc_get_account_endpoint_url('my-commissions');
        $html .= '<p>You can follow your commission any time at <a href="' . esc_url($url) . '">My Account → My Commissions</a>. Just reply to this email if you have a question.</p>';
        $text .= "\nFollow your commission any time: " . $url . "\nJust reply to this email if you have a question.\n";

        // A guest's account was made at checkout: the set-password link lives here (the separate account email is off)
        if ($new_account) {
            $key = get_password_reset_key($user);
            if (!is_wp_error($key)) {
                $set = add_query_arg(['action' => 'newaccount', 'key' => $key, 'login' => rawurlencode($user->user_login)], wc_get_endpoint_url('lost-password', '', wc_get_page_permalink('myaccount')));
                $html .= '<p><strong>Your account</strong><br>I set up an account for you so you can follow your commission. Your username is <strong>' . esc_html($user->user_login) . '</strong>. '
                    . '<a href="' . esc_url($set) . '">Choose your password</a> to sign in and see My Commissions.</p>';
                $text .= "\nYour account\nI set up an account for you so you can follow your commission. Your username is " . $user->user_login . ".\nChoose your password: " . $set . "\n";
                delete_user_meta($user->ID, '_ms_account_via_commission');
            }
        }

        $this->body_html = $html;
        $this->body_text = $text;

        if ($this->is_enabled() && $this->get_recipient()) {
            $sent = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
            $order->add_order_note($sent ? 'Commission confirmation email sent to the customer.' : 'Commission confirmation email FAILED to send.');
        }
        $this->restore_locale();
    }
}

/**
 * To the owner when a commission comes in
 */
class MS_Email_New_Commission extends MS_Commission_Email {
    public function __construct() {
        $this->id = 'ms_new_commission';
        $this->title = 'New commission (to you)';
        $this->description = 'Sent to you when a commission deposit order comes in: who, what, notes and a link to the order.';
        $this->placeholders = ['{order_number}' => '', '{item_name}' => ''];
        parent::__construct();
        $this->recipient = $this->get_option('recipient', ms_commission_admin_email());
    }
    public function get_default_subject() { return 'New commission: {item_name} (#{order_number})'; }
    public function get_default_heading() { return 'New commission'; }

    public function init_form_fields() {
        parent::init_form_fields();
        $this->form_fields = array_merge([
            'recipient' => [
                'title' => 'Recipient(s)',
                'type' => 'text',
                'description' => 'Comma-separated. Also the address that gets a copy of the customer emails of a commission.',
                'default' => ms_commission_admin_email(),
                'desc_tip' => true,
            ],
        ], $this->form_fields);
    }

    public function trigger($order_id, $order = false) {
        $this->setup_locale();
        if ($order_id && !is_a($order, 'WC_Order')) $order = wc_get_order($order_id);
        if (!$order) { $this->restore_locale(); return; }

        $this->object = $order;
        $items = ms_commission_order_items($order);
        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{item_name}'] = $items ? reset($items)->get_name() : '';

        $edit = $order->get_edit_order_url();
        $who = trim($order->get_formatted_billing_full_name()) . ' <' . $order->get_billing_email() . '>';
        $html = '<p>' . esc_html($who) . ' ordered:</p>';
        $text = $who . " ordered:\n\n";

        foreach ($items as $item) {
            $refs = ms_normalize_image_array(ms_get_commission_meta($item, 'reference_images'));
            $note = ms_get_commission_meta($item, 'special_request');
            $line = sprintf('%s - %s (%s, %s, %s) - deposit %s of %s',
                $item->get_name(), '', ms_get_commission_meta($item, 'medium'), ms_get_commission_meta($item, 'size'), ms_get_commission_meta($item, 'surface'),
                wc_price((float) ms_get_commission_meta($item, 'deposit_paid')), wc_price((float) ms_get_commission_meta($item, 'full_price')));
            $line = str_replace(' -  (', ' (', $line);
            $html .= '<p><strong>' . wp_kses_post($line) . '</strong><br>' . count($refs) . ' reference photo(s) (open them in the order)'
                . '<br>Notes: ' . ($note ? esc_html($note) : '-') . '<br>Terms accepted: ' . esc_html(ms_get_commission_meta($item, 'terms_accepted') ?: '-') . '</p>';
            $text .= wp_strip_all_tags(html_entity_decode($line)) . "\n" . count($refs) . " reference photo(s) (open them in the order)\nNotes: " . ($note ?: '-') . "\n\n";
        }

        $html .= '<p><a href="' . esc_url($edit) . '">Open order #' . esc_html($order->get_order_number()) . '</a></p>';
        $text .= 'Open the order: ' . $edit . "\n";

        $this->body_html = $html;
        $this->body_text = $text;

        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
        $this->restore_locale();
    }
}

/**
 * To the customer: a new preview is ready (uses light copies; the log goes on the order)
 */
class MS_Email_Commission_Preview extends MS_Commission_Email {
    public function __construct() {
        $this->id = 'ms_commission_preview';
        $this->customer_email = true;
        $this->title = 'Commission update (customer, with images)';
        $this->description = 'Sent to the customer when you share work in progress (images) on the order screen.';
        $this->placeholders = ['{order_number}' => '', '{item_name}' => ''];
        parent::__construct();
    }
    public function get_default_subject() { return 'An update on your commission'; }
    public function get_default_heading() { return 'An update on your {item_name}'; }

    /** The finished artwork has its own subject and heading, so the approval request is the first thing read (owner, 2026-11-23) */
    public $is_final = false;
    public function get_subject() {
        return $this->is_final ? $this->format_string('Action needed: please approve your finished {item_name}') : parent::get_subject();
    }
    public function get_heading() {
        return $this->is_final ? $this->format_string('Your artwork is finished: please approve it') : parent::get_heading();
    }

    public function trigger($order, $item, $preview_urls, $custom_message = '', $is_final = false) {
        $this->setup_locale();
        $this->object = $order;
        $this->is_final = (bool) $is_final;
        $this->recipient = $order->get_billing_email();
        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{item_name}'] = ms_commission_friendly_name($item->get_name());

        $intro = $custom_message !== '' ? $custom_message : ($this->is_final ? 'Your artwork is finished. Here it is.' : 'Here is the current state of your commission. This may include sketches, composition studies, or value and color tests.');
        $hello = $this->greeting($order, $intro);
        $html = ($hello ? '<p>' . esc_html($hello) . '</p>' : '') . '<p>' . nl2br(esc_html($intro)) . '</p><div style="margin:20px 0">';
        $text = ($hello ? $hello . "\n\n" : '') . $intro . "\n\n";

        foreach ($preview_urls as $url) {
            $html .= '<img src="' . esc_url(ms_commission_email_image_url($url)) . '" style="max-width:100%;height:auto;border:1px solid #ddd;padding:2px;margin-bottom:10px" alt="' . ($this->is_final ? 'Your finished artwork' : 'Commission work in progress') . '">';
            $text .= 'Image: ' . $url . "\n";
        }

        $account = wc_get_account_endpoint_url('my-commissions');
        $button = 'background:#0c0519;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:4px;display:inline-block;font-weight:bold';
        $html .= '</div>';
        if ($this->is_final) {
            list($bh, $bt) = $this->approve_block($order);
            $html .= $bh;
            $text .= $bt;
        } else {
            $html .= '<p>You are welcome to respond by replying to this email or through your account.</p>'
                . '<p><a href="' . esc_url($account) . '" style="' . $button . '">View all commissions</a></p>';
            $text .= "\nYou are welcome to respond by replying to this email or through your account.\nYour commissions: " . $account . "\n";
        }
        $html .= '<p style="color:#666;font-size:.9em">Order #' . esc_html($order->get_order_number()) . '</p>';

        $this->body_html = $html;
        $this->body_text = $text;

        $sent = false;
        if ($this->is_enabled() && $this->get_recipient()) {
            $sent = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
        $order->add_order_note($sent ? 'Update email sent to ' . $order->get_billing_email() . '.' : 'Update email FAILED to send to ' . $order->get_billing_email() . ' - tell the customer another way.');
        $this->restore_locale();
        return $sent;
    }
}

/**
 * To the customer: a message from you, written as WooCommerce's own "Note to customer" in the Order notes box (owner, 2026-11-23: one way to
 * message a commission customer, no second message box). On a commission order it replaces WooCommerce's "A note has been added to your
 * order" email (commissions-message.php): the message, a link to the commission page, and - while the finished artwork waits for approval -
 * the same Approve block as the finished-artwork email. The stage does not change. The note shows in the customer's Conversation.
 */
class MS_Email_Commission_Message extends MS_Commission_Email {
    public function __construct() {
        $this->id = 'ms_commission_message';
        $this->customer_email = true;
        $this->title = 'Commission message (customer)';
        $this->description = 'Sent to the customer when you add a "Note to customer" to a commission order (Order notes box). Replaces WooCommerce\'s Customer note email on commission orders.';
        $this->placeholders = ['{order_number}' => '', '{item_name}' => ''];
        parent::__construct();
    }
    public function get_default_subject() { return 'A message about your {item_name}'; }
    public function get_default_heading() { return 'A message about your {item_name}'; }

    public function trigger($order, $message) {
        $items = ms_commission_order_items($order);
        if (!$items) return false;
        $this->setup_locale();
        $this->object = $order;
        $this->recipient = $order->get_billing_email();
        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{item_name}'] = ms_commission_friendly_name(reset($items)->get_name());

        $hello = $this->greeting($order, $message);
        $html = ($hello ? '<p>' . esc_html($hello) . '</p>' : '') . '<p>' . nl2br(esc_html($message)) . '</p>';
        $text = ($hello ? $hello . "\n\n" : '') . $message . "\n\n";

        $awaiting = ms_commission_final_shared($order) > 0 && in_array(ms_commission_stage($order), ['preview_sent', 'changes_requested'], true);
        if ($awaiting) {
            list($bh, $bt) = $this->approve_block($order);
            $html .= $bh;
            $text .= $bt;
        } else {
            $page = wc_get_account_endpoint_url('my-commissions') . '#commission-' . $order->get_id();
            $html .= '<p>You are welcome to respond by replying to this email or on your commission page.</p>'
                . '<p><a href="' . esc_url($page) . '" style="background:#0c0519;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:4px;display:inline-block;font-weight:bold">View your commission</a></p>';
            $text .= "You are welcome to respond by replying to this email or on your commission page.\nYour commission: " . $page . "\n";
        }
        $html .= '<p style="color:#666;font-size:.9em">Order #' . esc_html($order->get_order_number()) . '</p>';
        $this->body_html = $html;
        $this->body_text = $text;

        $sent = false;
        if ($this->is_enabled() && $this->get_recipient()) {
            $sent = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
        $order->add_order_note($sent ? 'Message emailed to ' . $order->get_billing_email() . ($awaiting ? ' (with the Approve final artwork button).' : '.') : 'Message email FAILED to send to ' . $order->get_billing_email() . ' - tell the customer another way.');
        $this->restore_locale();
        return $sent;
    }
}

/**
 * To the owner: the customer approved the artwork, said it looks good so far, or left a comment
 */
class MS_Email_Commission_Response extends MS_Commission_Email {
    public function __construct() {
        $this->id = 'ms_commission_response';
        $this->title = 'Commission response (to you)';
        $this->description = 'Sent to you when a customer approves the final artwork, replies "Approved so far", or leaves a comment.';
        $this->placeholders = ['{order_number}' => '', '{item_name}' => '', '{response}' => ''];
        parent::__construct();
        $this->recipient = $this->get_option('recipient', ms_commission_admin_email());
    }
    public function get_default_subject() { return '{response}: commission #{order_number}'; }
    public function get_default_heading() { return '{response}'; }

    public function init_form_fields() {
        parent::init_form_fields();
        $this->form_fields = array_merge([
            'recipient' => ['title' => 'Recipient(s)', 'type' => 'text', 'description' => 'Comma-separated.', 'default' => ms_commission_admin_email(), 'desc_tip' => true],
        ], $this->form_fields);
    }

    public function trigger($order, $type, $message = '', $revision = 0) {
        $this->setup_locale();
        $this->object = $order;
        $items = ms_commission_order_items($order);
        $name = $items ? reset($items)->get_name() : 'Commission';
        $approved = $type === 'approve';
        $ack = $type === 'ack';
        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{item_name}'] = $name;
        $this->placeholders['{response}'] = $approved ? 'Final artwork approved' : ($ack ? 'Approved so far' : 'New comment');

        $who = trim($order->get_formatted_billing_full_name()) . ' <' . $order->get_billing_email() . '>';
        $edit = $order->get_edit_order_url();
        $verb = $approved ? ' approved the final artwork of ' : ($ack ? ' is happy so far with ' : ' left a comment on ');
        $html = '<p>' . esc_html($who) . $verb . '<strong>' . esc_html($name) . '</strong>.</p>';
        $text = $who . $verb . $name . ".\n\n";

        if ($approved) {
            $html .= '<p>Next: create the final payment (open the order, "Review final payment").</p>';
            $text .= "Next: create the final payment (open the order, Review final payment).\n";
        } elseif (!$ack) {
            $html .= '<blockquote style="margin:1em 0;padding:.5em 1em;border-left:3px solid #ccc">' . nl2br(esc_html($message)) . '</blockquote>';
            $text .= $message . "\n";
        }
        $html .= '<p><a href="' . esc_url($edit) . '">Open order #' . esc_html($order->get_order_number()) . '</a></p>';
        $text .= "\nOpen the order: " . $edit . "\n";

        $this->body_html = $html;
        $this->body_text = $text;
        if ($this->is_enabled() && $this->get_recipient()) {
            $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
        $this->restore_locale();
    }
}

/**
 * To the customer: the final payment is still open (3 and 7 days after the invoice went out)
 */
class MS_Email_Commission_Reminder extends MS_Commission_Email {
    public function __construct() {
        $this->id = 'ms_commission_reminder';
        $this->customer_email = true;
        $this->title = 'Final payment reminder (customer)';
        $this->description = 'Sent to the customer 3 and 7 days after the final payment invoice, while it is still unpaid.';
        $this->placeholders = ['{order_number}' => '', '{item_name}' => ''];
        parent::__construct();
    }
    public function get_default_subject() { return 'Reminder: final payment for your {item_name}'; }
    public function get_default_heading() { return 'Your final payment is waiting'; }

    public function trigger($final, $item_name) {
        $this->setup_locale();
        $this->object = $final;
        $this->recipient = $final->get_billing_email();
        $item_name = ms_commission_friendly_name($item_name);
        $this->placeholders['{order_number}'] = $final->get_order_number();
        $this->placeholders['{item_name}'] = $item_name;

        $first = $final->get_billing_first_name() ?: 'there';
        $pay = $final->get_checkout_payment_url();
        $total = $final->get_formatted_order_total();
        $html = '<p>' . sprintf('Hi %s,', esc_html($first)) . '</p>'
            . '<p>A friendly reminder: the final payment for your <strong>' . esc_html($item_name) . '</strong> is still open (' . wp_kses_post($total) . ', shipping included). Once it is paid, your artwork is shipped.</p>'
            . '<p><a href="' . esc_url($pay) . '" style="background:#0c0519;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:4px;display:inline-block;font-weight:bold">Pay now</a></p>'
            . '<p>Any question? Just reply to this email.</p>';
        $text = sprintf('Hi %s,', $first) . "\n\nA friendly reminder: the final payment for your " . $item_name . ' is still open (' . wp_strip_all_tags(html_entity_decode($total)) . ", shipping included). Once it is paid, your artwork is shipped.\n\nPay now: " . $pay . "\n\nAny question? Just reply to this email.\n";

        $this->body_html = $html;
        $this->body_text = $text;
        $sent = false;
        if ($this->is_enabled() && $this->get_recipient()) {
            $sent = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
        $final->add_order_note($sent ? 'Payment reminder sent to the customer.' : 'Payment reminder FAILED to send.');
        $this->restore_locale();
        return $sent;
    }
}

/**
 * To the owner: the daily list of commissions that need you
 */
class MS_Email_Commission_Digest extends MS_Commission_Email {
    public function __construct() {
        $this->id = 'ms_commission_digest';
        $this->title = 'Commissions needing you (to you)';
        $this->description = 'Sent to you when a commission is past its due date or an approved one is still waiting for its balance invoice (once per commission).';
        $this->placeholders = ['{count}' => ''];
        parent::__construct();
        $this->recipient = $this->get_option('recipient', ms_commission_admin_email());
    }
    public function get_default_subject() { return '{count} commission(s) need you'; }
    public function get_default_heading() { return 'Commissions needing you'; }

    public function init_form_fields() {
        parent::init_form_fields();
        $this->form_fields = array_merge([
            'recipient' => ['title' => 'Recipient(s)', 'type' => 'text', 'description' => 'Comma-separated.', 'default' => ms_commission_admin_email(), 'desc_tip' => true],
        ], $this->form_fields);
    }

    /** $lines: [ ['text' => '...', 'url' => '...'], ... ] */
    public function trigger($lines) {
        $this->setup_locale();
        $this->placeholders['{count}'] = count($lines);
        $html = '<ul>';
        $text = '';
        foreach ($lines as $l) {
            $html .= '<li><a href="' . esc_url($l['url']) . '">' . esc_html($l['text']) . '</a></li>';
            $text .= '- ' . $l['text'] . ' - ' . $l['url'] . "\n";
        }
        $html .= '</ul>';
        $this->body_html = $html;
        $this->body_text = $text;
        $sent = false;
        if ($this->is_enabled() && $this->get_recipient()) {
            $sent = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
        $this->restore_locale();
        return $sent;
    }
}

/**
 * To the customer when the balance order is completed: it is on its way (tracking, the finished piece, care note)
 */
class MS_Email_Commission_Shipped extends MS_Commission_Email {
    public function __construct() {
        $this->id = 'ms_commission_shipped';
        $this->customer_email = true;
        $this->title = 'Commission shipped (customer)';
        $this->description = 'Sent once to the customer when the balance order is completed: the tracking number, a photo of the finished piece, a care note. Replaces WooCommerce\'s completed-order email for a balance order.';
        $this->placeholders = ['{order_number}' => '', '{item_name}' => ''];
        parent::__construct();
    }
    public function get_default_subject() { return 'Your {item_name} is on its way'; }
    public function get_default_heading() { return 'Your artwork is on its way'; }
    public function get_default_additional_content() {
        return "A care note: please keep your artwork out of direct sunlight and away from damp.\n\nI would love to see it on your wall. If you enjoy it, a short review means a lot - and may I show a photo of your commission on my website? Just reply to this email to let me know.";
    }

    public function trigger($final) {
        $this->setup_locale();
        $this->object = $final;
        $this->recipient = $final->get_billing_email();
        $name = '';
        foreach (ms_commission_final_items($final) as $item) {
            $name = $item->get_name();
            break;
        }
        // line & wash pieces ship on 9x12 paper with a faint pencil outline at 8x10 (owner, 2026-11-23)
        $line_wash = (bool) preg_match('/\b(line|ink)\s*(&|&amp;|and)\s*wash\b/i', html_entity_decode($name, ENT_QUOTES, 'UTF-8'));
        $name = ms_commission_friendly_name($name);
        $this->placeholders['{order_number}'] = $final->get_order_number();
        $this->placeholders['{item_name}'] = $name;

        $first = $final->get_billing_first_name() ?: 'there';
        $t = ms_commission_tracking($final);
        $photo = (string) $final->get_meta('_ms_commission_finished_photo');

        $html = '<p>' . sprintf('Hi %s,', esc_html($first)) . '</p><p>Your final payment is in - thank you! Your <strong>' . esc_html($name) . '</strong> is finished and on its way to you.</p>';
        $text = sprintf('Hi %s,', $first) . "\n\nYour final payment is in - thank you! Your " . $name . " is finished and on its way to you.\n\n";

        if ($photo) {
            $html .= '<p><img src="' . esc_url(ms_commission_email_image_url($photo)) . '" style="max-width:100%;height:auto;border:1px solid #ddd;padding:2px" alt="Your finished artwork"></p>';
        }
        if ($t) {
            $line = trim($t['carrier'] . ' ' . $t['number']);
            $html .= '<p><strong>Tracking:</strong> ' . esc_html($line) . ($t['url'] ? ' - <a href="' . esc_url($t['url']) . '">Track your package</a>' : '') . '</p>';
            $text .= 'Tracking: ' . $line . ($t['url'] ? ' - ' . $t['url'] : '') . "\n\n";
        }
        if ($line_wash) {
            $frame = 'For framing: your artwork is on 9×12 paper, and a faint pencil line marks the 8×10 area. Use it as your guide for an 8×10 mat or frame opening.';
            $html .= '<p>' . esc_html($frame) . '</p>';
            $text .= $frame . "\n\n";
        }

        $this->body_html = $html;
        $this->body_text = $text;
        $sent = false;
        if ($this->is_enabled() && $this->get_recipient()) {
            $sent = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
        $final->add_order_note($sent ? 'Commission completed email sent to the customer.' : 'Commission completed email FAILED to send.');
        $this->restore_locale();
        return $sent;
    }
}

/**
 * To the customer, only when ticked on the order screen while saving the stage: you started on the piece / are working on the changes
 */
class MS_Email_Commission_Update extends MS_Commission_Email {
    public function __construct() {
        $this->id = 'ms_commission_update';
        $this->customer_email = true;
        $this->title = 'Commission update (customer)';
        $this->description = 'Sent to the customer only when you tick "Email the customer" while saving the stage on the order screen (In progress, Customer replied). Never sent by itself.';
        $this->placeholders = ['{order_number}' => '', '{item_name}' => ''];
        parent::__construct();
    }
    public function get_default_subject() { return 'An update on your {item_name}'; }
    public function get_default_heading() { return 'An update on your commission'; }

    public function trigger($order, $stage) {
        $this->setup_locale();
        $this->object = $order;
        $this->recipient = $order->get_billing_email();
        $items = ms_commission_order_items($order);
        $name = ms_commission_friendly_name($items ? reset($items)->get_name() : '');
        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{item_name}'] = $name;

        $first = $order->get_billing_first_name() ?: 'there';
        $line = $stage === 'changes_requested'
            ? 'Thank you for your response. I am continuing to work on your <strong>' . esc_html($name) . '</strong> and will share more as it develops.'
            : 'Work on your <strong>' . esc_html($name) . '</strong> has begun. I will share updates as the work develops.';
        $account = wc_get_account_endpoint_url('my-commissions');
        $html = '<p>' . sprintf('Hi %s,', esc_html($first)) . '</p><p>' . $line . '</p>'
            . '<p>You can follow your commission any time at <a href="' . esc_url($account) . '">My Account → My Commissions</a>. Just reply to this email if you have a question.</p>'
            . '<p style="color:#666;font-size:.9em">Order #' . esc_html($order->get_order_number()) . '</p>';
        $text = sprintf('Hi %s,', $first) . "\n\n" . wp_strip_all_tags($line) . "\n\nFollow your commission any time: " . $account . "\nJust reply to this email if you have a question.\n";

        $this->body_html = $html;
        $this->body_text = $text;
        $sent = false;
        if ($this->is_enabled() && $this->get_recipient()) {
            $sent = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
        $order->add_order_note($sent ? 'Commission update email sent to the customer.' : 'Commission update email FAILED to send.');
        $this->restore_locale();
        return $sent;
    }
}

class MS_Email_Commission_DueDate extends MS_Commission_Email {
    public function __construct() {
        $this->id = 'ms_commission_due_date';
        $this->customer_email = true;
        $this->title = 'Commission due date changed (customer)';
        $this->description = 'Sent to the customer only when you tick "Email the customer the new date" while saving a changed due date on the order screen. Never sent by itself.';
        $this->placeholders = ['{order_number}' => '', '{item_name}' => ''];
        parent::__construct();
    }
    public function get_default_subject() { return 'The expected date for your {item_name}'; }
    public function get_default_heading() { return 'The expected date has changed'; }

    public function trigger($order, $new_due) {
        $this->setup_locale();
        $this->object = $order;
        $this->recipient = $order->get_billing_email();
        $items = ms_commission_order_items($order);
        $name = ms_commission_friendly_name($items ? reset($items)->get_name() : '');
        $this->placeholders['{order_number}'] = $order->get_order_number();
        $this->placeholders['{item_name}'] = $name;

        $first = $order->get_billing_first_name() ?: 'there';
        $date = wp_date(get_option('date_format'), strtotime($new_due . ' 12:00:00'));
        $line = 'I now expect to have your <strong>' . esc_html($name) . '</strong> finished by <strong>' . esc_html($date) . '</strong>. Thank you for your patience.';
        $account = wc_get_account_endpoint_url('my-commissions');
        $html = '<p>' . sprintf('Hi %s,', esc_html($first)) . '</p><p>' . $line . '</p>'
            . '<p>You can follow your commission any time at <a href="' . esc_url($account) . '">My Account → My Commissions</a>. Just reply to this email if you have a question.</p>'
            . '<p style="color:#666;font-size:.9em">Order #' . esc_html($order->get_order_number()) . '</p>';
        $text = sprintf('Hi %s,', $first) . "\n\n" . wp_strip_all_tags($line) . "\n\nFollow your commission any time: " . $account . "\nJust reply to this email if you have a question.\n";

        $this->body_html = $html;
        $this->body_text = $text;
        $sent = false;
        if ($this->is_enabled() && $this->get_recipient()) {
            $sent = $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }
        $order->add_order_note($sent ? 'Due date email sent to the customer (new date ' . $date . ').' : 'Due date email FAILED to send.');
        $this->restore_locale();
        return $sent;
    }
}

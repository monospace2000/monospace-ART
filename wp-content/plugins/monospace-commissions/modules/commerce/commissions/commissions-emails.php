<?php
/**
 * Commissions: what the customer is told (policies, product-page guidance, terms checkbox) and the emails
 * (commission received, new commission for the owner, work-in-progress update) as WooCommerce email classes - editable under
 * WooCommerce → Emails, with a plain-text option.
 *
 * @package monospace-commissions
 */

if (!defined('ABSPATH')) exit;

/**
 * ===========================
 * POLICIES (one source: the Commission policies page; product page, emails and [commission_policies] read it)
 * ===========================
 * Owner's decisions 2026-10-27: two revisions, no refund once work has started, no holiday cutoff, two-week
 * turnaround as a reminder, photos stay the customer's and are deleted 6 months after completion.
 */
function ms_commission_policies() {
    return apply_filters('ms_commission_policies', [
        'Turnaround' => 'Most commissions are completed within approximately two weeks of the deposit. If a commission will take longer, I will let you know.',
        'Communication' => 'The commission proceeds as a conversation. You may respond to any update at any stage, and there is no set number of revisions. Significant changes in direction may affect the timeline. The balance is due only after you approve the finished artwork.',
        'Deposit' => 'The deposit is refundable until work on your artwork has begun. Once work has begun, it is non-refundable.',
        'Reference photos' => 'Your reference photos remain your property. They are used only to create your artwork and are deleted automatically six months after the commission is completed. Please send only photos you have the right to use.',
        'Shipping' => 'Shipping is added to the final payment, after you approve the finished artwork.',
    ]);
}

/**
 * What a customer reads instead of the stored order line name ("Deposit (50%) \u2014 Custom Order: Line & Wash" ->
 * "Line & Wash commission"). The order line itself is not renamed: receipts and the books keep it. Reads as "your <name>".
 */
function ms_commission_friendly_name($name) {
    $name = trim(html_entity_decode(wp_strip_all_tags((string) $name), ENT_QUOTES, 'UTF-8'));
    $name = preg_replace('/^(Deposit\s*\(\d+%\)|Final Payment)\s*[-\x{2013}\x{2014}]\s*/u', '', $name);
    $name = trim(preg_replace('/^Custom Order:\s*/i', '', $name));
    if ($name === '' || strtolower($name) === 'your commission' || strtolower($name) === 'commission') return 'commission';
    return preg_match('/commission$/i', $name) ? $name : $name . ' commission';
}

/**
 * The page the owner edits (Pages > Commission policies, the page with the role commission-policies - Core's page-roles.php, so it
 * can be moved into any folder; owner, 2026-11-04). Its text is what
 * the product page, the emails and [commission_policies] show; ms_commission_policies() above is the fallback while the
 * page is missing, unpublished or empty. Blocks only - the page's shortcodes are not run (it must not show itself).
 */
function ms_commission_policies_page_html() {
    $page = function_exists('monospace_role_page') ? monospace_role_page('commission-policies') : null;
    if (!$page || $page->post_status !== 'publish' || trim(wp_strip_all_tags($page->post_content)) === '') return '';
    return wp_kses_post(wpautop(do_blocks($page->post_content)));
}

function ms_commission_policies_html($with_heading = true) {
    $html = '<div class="ms-commission-policies" style="margin:0 0 1em">';
    if ($with_heading) $html .= '<p style="margin:0 0 .4em"><strong>Good to know</strong></p>';
    $page = ms_commission_policies_page_html();
    if ($page !== '') return $html . $page . '</div>';
    $html .= '<ul style="margin:0 0 0 1.2em;padding:0">';
    foreach (ms_commission_policies() as $label => $text) {
        $html .= '<li style="margin:0 0 .3em"><strong>' . esc_html($label) . ':</strong> ' . esc_html($text) . '</li>';
    }
    return $html . '</ul></div>';
}

function ms_commission_policies_text() {
    $page = ms_commission_policies_page_html();
    if ($page !== '') {
        $page = preg_replace('~<li[^>]*>~i', '- ', str_replace(["\r", "\n"], ' ', $page));
        $page = preg_replace('~</(li|p)>|<br\s*/?>~i', "\n", $page);
        $lines = array_filter(array_map('trim', explode("\n", html_entity_decode(wp_strip_all_tags($page), ENT_QUOTES, 'UTF-8'))), 'strlen');
        return implode("\n", $lines);
    }
    $lines = [];
    foreach (ms_commission_policies() as $label => $text) $lines[] = '- ' . $label . ': ' . $text;
    return implode("\n", $lines);
}

add_shortcode('commission_policies', function() {
    return ms_commission_policies_html(false);
});

/**
 * The page the owner edits for "How it works" (Pages > How a commission works, the page with the role commission-how-it-works; owner,
 * 2026-11-15) - an ordered list, like the Commission policies page. Blocks only; '' while the page is missing,
 * unpublished or empty (ms_commission_steps() below is then the fallback).
 */
function ms_commission_steps_page_html() {
    $page = function_exists('monospace_role_page') ? monospace_role_page('commission-how-it-works') : null;
    if (!$page || $page->post_status !== 'publish' || trim(wp_strip_all_tags($page->post_content)) === '') return '';
    return wp_kses_post(wpautop(do_blocks($page->post_content)));
}

/**
 * The steps as plain lines, for the confirmation email and the other emails (the product page shows the page itself, with
 * its formatting): the list items of the page above, else the built-in five. The first step is what has just happened
 * when the email goes out, so keep the order.
 */
function ms_commission_steps() {
    $html = ms_commission_steps_page_html();
    if ($html !== '' && preg_match_all('~<li[^>]*>(.*?)</li>~is', $html, $m)) {
        $steps = array_values(array_filter(array_map(function($x) { return trim(preg_replace('~\s+~', ' ', html_entity_decode(wp_strip_all_tags($x), ENT_QUOTES, 'UTF-8'))); }, $m[1]), 'strlen'));
        if ($steps) return $steps;
    }
    return [
        'Deposit and references. You pay the deposit and send your reference photos.',
        'Work begins. I share updates as the work develops. These may be sketches, composition studies, or value and color tests. The number of updates varies by commission. Most commissions are completed within approximately two weeks of the deposit.',
        'Ongoing conversation. You may respond to any update, in your account (My Account → My Commissions) or by replying to the email. A brief confirmation that you are happy so far is sufficient. Comments and questions are welcome at any point. Significant changes in direction may affect the timeline.',
        'Final approval. When the work meets your expectations, you approve the finished artwork in your account. I then send the final payment invoice: the balance plus shipping.',
        'Shipping. Your artwork ships once the final payment is received.',
    ];
}

/**
 * [commission_steps] - the same steps on any page (the Custom Artwork page), read from the How a commission works page, so the
 * page, the product page and the emails never drift apart (like [commission_policies] above).
 */
add_shortcode('commission_steps', function() {
    $page = ms_commission_steps_page_html();
    if ($page !== '') return '<div class="ms-commission-steps">' . $page . '</div>';
    $html = '<ol class="ms-commission-steps">';
    foreach (ms_commission_steps() as $step) $html .= '<li>' . esc_html($step) . '</li>';
    return $html . '</ol>';
});

/**
 * ===========================
 * PRODUCT PAGE: HOW IT WORKS, PHOTO TIPS, TERMS
 * ===========================
 */
/**
 * Full width under the picture and the order form (not squeezed into the form column): steps | good to know on
 * desktop, the photo tips across the bottom; one column on phones.
 */
add_action('woocommerce_after_single_product_summary', function() {
    global $product;
    if (!$product || !ms_is_commission_product($product->get_id())) return;

    echo '<style>.ms-commission-info{display:grid;gap:1em 3em;clear:both;grid-column:1/-1;flex:0 0 100%;width:100%;box-sizing:border-box;margin:1.5em 0;padding:1.2em 1.5em;border:1px solid rgba(128,128,128,.35);border-radius:6px;font-size:.95em}'
        . '@media (min-width:782px){.ms-commission-info{grid-template-columns:1fr 1fr}.ms-commission-info .ms-ci-wide{grid-column:1/-1}}</style>';
    echo '<div class="ms-commission-info">';
    echo '<div><p style="margin:0 0 .4em"><strong>How it works</strong></p>';
    if (($steps_page = ms_commission_steps_page_html()) !== '') {
        echo '<div class="ms-commission-steps">' . $steps_page . '</div>';
    } else {
        echo '<ol style="margin:0 0 0 1.2em;padding:0">';
        foreach (ms_commission_steps() as $step) echo '<li style="margin:0 0 .25em">' . esc_html($step) . '</li>';
        echo '</ol>';
    }
    echo '</div>';
    echo '<div>' . ms_commission_policies_html() . '</div>';
    echo '<p class="ms-ci-wide" style="margin:0"><strong>Choosing photos:</strong> sharp, well lit and not a screenshot; the bigger the original, the better. Send the scene itself plus any details you care about.</p>';
    echo '</div>';
}, 5);

// in the form, next to the button: the checkbox
add_action('woocommerce_before_add_to_cart_button', function() {
    global $product;
    if (!$product || !ms_is_commission_product($product->get_id())) return;

    echo '<p style="margin:0 0 1em"><label><input type="checkbox" name="commission_terms" value="yes" required /> I have read "How it works" and "Good to know" below and agree to them.</label></p>';
}, 20);

add_filter('woocommerce_add_to_cart_validation', function($passed, $product_id) {
    if (!$passed || !ms_is_commission_product($product_id)) return $passed;
    if (empty($_POST['commission_terms']) || $_POST['commission_terms'] !== 'yes') {
        wc_add_notice('Please tick the box to confirm you have read how a commission works.', 'error');
        return false;
    }
    return $passed;
}, 5, 2);

/**
 * ===========================
 * RESIZED PREVIEW COPIES (email and My Commissions tab; the original stays downloadable)
 * ===========================
 */
function ms_commission_make_web_copy($path) {
    $info = pathinfo($path);
    $target = $info['dirname'] . '/' . $info['filename'] . '-web.jpg';
    if (is_file($target)) return $target;

    $editor = wp_get_image_editor($path);
    if (is_wp_error($editor)) return false;
    $editor->maybe_exif_rotate();
    $editor->resize(1600, 1600, false);
    $editor->set_quality(82);
    $saved = $editor->save($target, 'image/jpeg');
    return is_wp_error($saved) ? false : $target;
}

/**
 * URL to show for a preview: the light copy when there is one, else the original
 */
function ms_commission_web_url($url) {
    $paths = ms_commission_paths();
    if (strpos($url, $paths['url']) !== 0) return $url;

    $relative = substr($url, strlen($paths['url']));
    $info = pathinfo($relative);
    $web = ($info['dirname'] !== '.' ? $info['dirname'] . '/' : '') . $info['filename'] . '-web.jpg';

    return is_file($paths['dir'] . $web) ? $paths['url'] . $web : $url;
}

/**
 * An image for an EMAIL: the light copy, through Jetpack's image CDN (i0.wp.com). iOS Mail does not load images straight from this
 * host (Gmail does, through Google's proxy; 2026-11-07), while the CDN works everywhere. The CDN keeps a cached public copy of the
 * file (owner's choice, option A). The site's own pages keep using ms_commission_web_url() directly.
 */
function ms_commission_email_image_url($url) {
    $url = ms_commission_web_url($url);
    $parts = wp_parse_url($url);
    $site = wp_parse_url(home_url());
    if (empty($parts['host']) || empty($site['host']) || $parts['host'] !== $site['host'] || strpos($parts['path'] ?? '', '/wp-content/uploads/') !== 0) return $url;
    return 'https://i0.wp.com/' . $parts['host'] . $parts['path'] . '?w=900&quality=85';
}

/**
 * The order-received page: what happens next (the same steps as the product page and the confirmation email)
 */
add_action('woocommerce_thankyou', function($order_id) {
    $order = wc_get_order($order_id);
    if (!$order || !ms_commission_order_items($order)) return;

    echo '<div class="ms-commission-next" style="margin:0 0 2em;padding:1em 1.4em;border:1px solid rgba(128,128,128,.35);border-radius:6px">';
    echo '<p style="margin:0 0 .4em"><strong>What happens next</strong></p><ol style="margin:0 0 .8em 1.2em;padding:0">';
    foreach (ms_commission_steps() as $i => $step) {
        if ($i === 0) continue; // step 1 (deposit + photos) is what just happened
        echo '<li style="margin:0 0 .25em">' . esc_html($step) . '</li>';
    }
    echo '</ol><p style="margin:0">We emailed you a confirmation. Follow your commission any time at <a href="' . esc_url(wc_get_account_endpoint_url('my-commissions')) . '">My Account &rarr; My Commissions</a>.</p>';
    echo '</div>';
}, 5);

/**
 * WooCommerce's invoice email, sent for a balance order: a subject and heading that say what it is
 */
add_filter('woocommerce_email_subject_customer_invoice', function($subject, $order) {
    if (!($order instanceof WC_Order) || !ms_commission_original_order($order)) return $subject;

    $items = ms_commission_final_items($order);
    $name = ms_commission_friendly_name($items ? reset($items)->get_name() : '');
    return sprintf('Final payment for your %s', $name);
}, 10, 2);

add_filter('woocommerce_email_heading_customer_invoice', function($heading, $order) {
    return ($order instanceof WC_Order && ms_commission_original_order($order)) ? 'Your final payment is ready' : $heading;
}, 10, 2);

/**
 * The body of the invoice email for a final-payment (balance) order, in place of WooCommerce's generic "an order has been
 * created for you" text and its empty product table (theme: woocommerce/emails/customer-invoice.php calls this).
 * What it is, what was already paid, what is due, a Pay button, what happens next. Empty string = not a balance order.
 */
function ms_commission_final_invoice_body($order) {
    $finals = ms_commission_final_items($order);
    if (!$finals || !$order->needs_payment()) return '';
    $final = reset($finals);

    $name = ms_commission_friendly_name($final->get_name());
    $orig = wc_get_order(ms_get_commission_meta($final, 'original_order_id'));
    $full = $deposit = 0.0;
    if ($orig) {
        $orig_item = $orig->get_item((int) ms_get_commission_meta($final, 'original_item_id'));
        if ($orig_item) {
            $full = (float) ms_get_commission_meta($orig_item, 'full_price');
            $deposit = (float) ms_get_commission_meta($orig_item, 'deposit_paid');
        }
    }
    $first = $order->get_billing_first_name() ?: 'there';
    $td = 'style="padding:6px 0;border-bottom:1px solid #e5e5e5;"';
    $tr = 'style="padding:6px 0;border-bottom:1px solid #e5e5e5;text-align:right;white-space:nowrap;"';

    $html = '<p>' . sprintf('Hi %s,', esc_html($first)) . '</p>';
    $html .= '<p>Your <strong>' . esc_html($name) . '</strong> is approved - thank you! This is the final payment. '
        . 'Once it is paid I ship your artwork and send you the tracking number.</p>';

    $html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:16px 0;">';
    if ($full > 0) {
        $html .= '<tr><td ' . $td . '>Price of your artwork</td><td ' . $tr . '>' . wc_price($full) . '</td></tr>';
        $html .= '<tr><td ' . $td . '>Deposit already paid</td><td ' . $tr . '>- ' . wc_price($deposit) . '</td></tr>';
    }
    foreach ($order->get_fees() as $fee) {
        $html .= '<tr><td ' . $td . '>Balance</td><td ' . $tr . '>' . wc_price($fee->get_total()) . '</td></tr>';
    }
    foreach ($order->get_items() as $line) {
        $html .= '<tr><td ' . $td . '>Balance</td><td ' . $tr . '>' . wc_price($line->get_total()) . '</td></tr>';
    }
    foreach ($order->get_items('shipping') as $ship) {
        $html .= '<tr><td ' . $td . '>Shipping (' . esc_html($ship->get_method_title()) . ')</td><td ' . $tr . '>' . wc_price($ship->get_total()) . '</td></tr>';
    }
    $html .= '<tr><td style="padding:8px 0;"><strong>Total due now</strong></td><td style="padding:8px 0;text-align:right;"><strong>' . wc_price($order->get_total()) . '</strong></td></tr>';
    $html .= '</table>';

    $html .= '<p style="margin:20px 0;"><a href="' . esc_url($order->get_checkout_payment_url()) . '" style="background:#0c0519;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:4px;display:inline-block;font-weight:bold;">Pay ' . wc_price($order->get_total()) . ' now</a></p>';

    $addr = $order->get_formatted_shipping_address();
    if ($addr) $html .= '<p><strong>Shipping to</strong><br>' . wp_kses_post($addr) . '</p>';
    $html .= '<p>Just reply to this email if anything looks wrong.</p>';
    return $html;
}

/**
 * The commission lines of a deposit order (none for a balance order)
 */
function ms_commission_order_items($order) {
    $items = [];
    foreach ($order->get_items() as $item) {
        if (ms_get_commission_meta($item, 'key') && !ms_get_commission_meta($item, 'is_final_payment')) $items[] = $item;
    }
    return $items;
}

/**
 * Once per order: when the deposit order comes in (paid -> processing, or on hold), send the two emails
 */
function ms_commission_order_in($order_id, $order = false) {
    $order = $order ?: wc_get_order($order_id);
    if (!$order || $order->get_meta('_ms_commission_emails_sent')) return;
    if (!ms_commission_order_items($order)) return;

    $order->update_meta_data('_ms_commission_emails_sent', wp_date('Y-m-d H:i'));
    $order->save();

    $emails = WC()->mailer()->get_emails();
    if (isset($emails['MS_Email_Commission_Received'])) $emails['MS_Email_Commission_Received']->trigger($order->get_id(), $order);
    if (isset($emails['MS_Email_New_Commission'])) $emails['MS_Email_New_Commission']->trigger($order->get_id(), $order);
}
add_action('woocommerce_order_status_processing', 'ms_commission_order_in', 20, 2);
// the commission confirmation replaces WooCommerce's generic "order received" email for a commission deposit order
add_filter('woocommerce_email_enabled_customer_processing_order', function($enabled, $order) {
    return ($order instanceof WC_Order && ms_commission_order_items($order)) ? false : $enabled;
}, 10, 2);
add_filter('woocommerce_email_enabled_customer_on_hold_order', function($enabled, $order) {
    return ($order instanceof WC_Order && ms_commission_order_items($order)) ? false : $enabled;
}, 10, 2);

add_action('woocommerce_order_status_on-hold', 'ms_commission_order_in', 20, 2);

/**
 * One email, not two: a guest's account is created at a commission checkout (commissions-core.php). WooCommerce's own
 * "Your account has been created" email stays off for it - the commission confirmation carries the set-password link
 * instead (MS_Email_Commission_Received). The flag on the new user says the confirmation still owes them that link.
 */
add_action('woocommerce_created_customer', function($customer_id) {
    if (function_exists('ms_cart_has_commission') && ms_cart_has_commission()) update_user_meta($customer_id, '_ms_account_via_commission', 1);
}, 5);
add_filter('woocommerce_email_enabled_customer_new_account', function($enabled) {
    return (function_exists('ms_cart_has_commission') && ms_cart_has_commission()) ? false : $enabled;
});

/**
 * The preview email (kept name: the upload handler in commissions-preview.php calls it)
 */
function ms_send_commission_preview_email($order, $item, $preview_urls, $custom_message = '') {
    if (!$order || !$item || empty($preview_urls)) return;

    // light copies for the email and the tab
    $paths = ms_commission_paths();
    foreach ($preview_urls as $url) {
        if (strpos($url, $paths['url']) === 0) ms_commission_make_web_copy($paths['dir'] . substr($url, strlen($paths['url'])));
    }

    $emails = WC()->mailer()->get_emails();
    if (isset($emails['MS_Email_Commission_Preview'])) {
        $emails['MS_Email_Commission_Preview']->trigger($order, $item, $preview_urls, $custom_message);
    } else {
        $order->add_order_note('Update email could not be sent (email class missing).');
    }
    do_action('ms_commission_preview_sent', $order);
}

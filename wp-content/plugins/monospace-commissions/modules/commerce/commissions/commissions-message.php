<?php
/**
 * Messages to a commission customer (owner, 2026-11-23: "a general send message to customer" - without duplicating WooCommerce's own).
 *
 * The tool is WooCommerce's Order notes box: write the message, choose "Note to customer", Add note. On a commission (deposit) order:
 *   - WooCommerce's generic "A note has been added to your order" email (with the order table) is not sent;
 *   - the commission message email goes instead (MS_Email_Commission_Message): from the commissions address, a Bcc to you, the message,
 *     a link to the commission page, and - while the finished artwork waits for approval - the Approve final artwork block;
 *   - the note shows in the customer's Conversation on the commission page (ms_commission_thread());
 *   - the stage does not change. A customer's answer comes back as a comment on the site or an email reply (read from Gmail).
 * A private note stays private, as always. Other orders keep WooCommerce's own email.
 */

if (!defined('ABSPATH')) exit;

/** Is this a commission deposit order (the order the conversation lives on)? */
function ms_commission_message_order($order) {
    return $order instanceof WC_Order && function_exists('ms_commission_order_items') && ms_commission_order_items($order);
}

// WooCommerce's own Customer note email: not on a commission order (the commission message goes instead)
add_filter('woocommerce_email_enabled_customer_note', function($enabled, $order = null) {
    return ms_commission_message_order($order) ? false : $enabled;
}, 20, 2);

add_action('woocommerce_new_customer_note', function($args) {
    $order = wc_get_order(absint($args['order_id'] ?? 0));
    $message = trim(wp_strip_all_tags((string) ($args['customer_note'] ?? '')));
    if (!ms_commission_message_order($order) || $message === '') return;
    $emails = WC()->mailer()->get_emails();
    if (isset($emails['MS_Email_Commission_Message'])) $emails['MS_Email_Commission_Message']->trigger($order, $message);
}, 20);

// On the order screen, under the Order notes box: what "Note to customer" does on this order
add_action('admin_footer', function() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || !in_array($screen->id, ['shop_order', 'woocommerce_page_wc-orders'], true)) return;
    $order = wc_get_order(absint($_GET['id'] ?? ($_GET['post'] ?? 0)));
    if (!ms_commission_message_order($order)) return;
    $awaiting = ms_commission_final_shared($order) > 0 && in_array(ms_commission_stage($order), ['preview_sent', 'changes_requested'], true);
    $hint = '<strong>Message the customer:</strong> write it here, choose <em>Note to customer</em>, then Add note. It is emailed from the commissions address (you get a copy) and shows in the Conversation on the commission page. The stage does not change.'
        . ($awaiting ? ' The finished artwork is waiting for approval, so the email also carries the Approve final artwork button.' : '');
    ?>
    <script>
    (function(){
        var add = document.querySelector('#woocommerce-order-notes .add_note');
        if (!add) return;
        var p = document.createElement('p');
        p.className = 'ms-note-hint';
        p.style.cssText = 'margin:8px 0 0;color:#646970;font-size:12px;line-height:1.5';
        p.innerHTML = <?php echo wp_json_encode($hint); ?>;
        add.appendChild(p);
    })();
    </script>
    <?php
});

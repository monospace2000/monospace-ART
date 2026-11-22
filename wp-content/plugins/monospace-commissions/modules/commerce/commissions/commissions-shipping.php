<?php
/**
 * Commissions: shipping and the completed email. On the balance order screen you save the tracking number and a
 * photo of the finished piece, then "Mark shipped" completes the order - which sends the customer one email
 * (tracking, the photo, a care note and a thank-you; replaces WooCommerce's generic "completed" email for a
 * balance order). Completing the order any other way sends the same email, with whatever is saved.
 *
 * @package monospace-commissions
 */

if (!defined('ABSPATH')) exit;

function ms_commission_carriers() {
    return [
        'USPS' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=%s',
        'UPS' => 'https://www.ups.com/track?tracknum=%s',
        'FedEx' => 'https://www.fedex.com/fedextrack/?trknbr=%s',
        'DHL' => 'https://www.dhl.com/global-en/home/tracking.html?tracking-id=%s',
        'Canada Post' => 'https://www.canadapost-postescanada.ca/track-reperage/en#/search?searchFor=%s',
        'Other' => '',
    ];
}

function ms_commission_tracking($final) {
    $carrier = (string) $final->get_meta('_ms_commission_carrier');
    $number = (string) $final->get_meta('_ms_commission_tracking');
    if ($number === '') return null;

    $templates = ms_commission_carriers();
    $url = !empty($templates[$carrier]) ? sprintf($templates[$carrier], rawurlencode($number)) : '';
    return ['carrier' => $carrier, 'number' => $number, 'url' => $url];
}

/**
 * ===========================
 * ADMIN: PANEL ON THE BALANCE ORDER
 * ===========================
 */
add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    if (!ms_commission_original_order($order)) return;

    $carrier = (string) $order->get_meta('_ms_commission_carrier');
    $number = (string) $order->get_meta('_ms_commission_tracking');
    $photo = (string) $order->get_meta('_ms_commission_finished_photo');
    $sent = $order->get_meta('_ms_commission_shipped_sent');

    echo '<div id="ms-ship-box" class="ms-order-panel ms-zone-main">';
    echo '<h3 style="margin:0 0 10px;font-size:14px">Shipping the commission</h3>';
    if ($sent) echo '<p style="margin:0 0 10px;color:#646970">Completed email sent ' . esc_html($sent) . '.</p>';

    echo '<p style="margin:0 0 10px"><label>Carrier <select id="ms-ship-carrier">';
    foreach (array_keys(ms_commission_carriers()) as $c) echo '<option' . selected($carrier, $c, false) . '>' . esc_html($c) . '</option>';
    echo '</select></label> &nbsp; <label>Tracking number <input type="text" id="ms-ship-number" value="' . esc_attr($number) . '" size="26"></label></p>';

    echo '<p style="margin:0 0 10px"><label>Photo of the finished piece (JPEG, PNG or WebP) <input type="file" id="ms-ship-photo" accept="image/jpeg,image/png,image/webp"></label>';
    if ($photo) echo '<br><img src="' . esc_url(ms_commission_web_url($photo)) . '" alt="" style="max-width:160px;height:auto;margin-top:6px;border:1px solid #c3c4c7">';
    echo '</p>';

    echo '<p style="margin:0"><button type="button" class="button" id="ms-ship-save" data-order="' . esc_attr($order->get_id()) . '">Save tracking &amp; photo</button> ';
    if ($order->has_status('completed')) {
        echo '<span style="color:#646970">This order is completed.</span>';
    } else {
        echo '<button type="button" class="button button-primary" id="ms-ship-done" data-order="' . esc_attr($order->get_id()) . '">Mark shipped &amp; email the customer</button>';
    }
    echo ' <span id="ms-ship-status" style="margin-left:8px"></span></p>';
    echo '<p style="margin:10px 0 0;color:#646970">"Mark shipped" completes this order, which sends the customer the completed email.</p>';
    echo '</div>';
    ?>
    <script>
    jQuery(function($){
        var nonce = <?php echo wp_json_encode(wp_create_nonce('ms_commission_ship')); ?>;
        function go(complete, $b) {
            var $s = $('#ms-ship-status');
            if (complete && !window.confirm('Complete this order and email the customer now?')) return;
            var fd = new FormData();
            fd.append('action', 'ms_commission_ship'); fd.append('nonce', nonce); fd.append('order_id', $b.data('order'));
            fd.append('carrier', $('#ms-ship-carrier').val()); fd.append('number', $('#ms-ship-number').val());
            fd.append('complete', complete ? 'yes' : 'no');
            var f = $('#ms-ship-photo')[0].files[0]; if (f) fd.append('photo', f);
            $('#ms-ship-box button').prop('disabled', true); $s.text('Saving...');
            $.ajax({url: ajaxurl, type: 'POST', data: fd, processData: false, contentType: false}).done(function(r){
                if (r && r.success) { $s.text(complete ? 'Done - reloading...' : 'Saved.'); if (complete || f) setTimeout(function(){ location.reload(); }, 800); else $('#ms-ship-box button').prop('disabled', false); }
                else { $s.text((r && r.data) || 'Could not save.'); $('#ms-ship-box button').prop('disabled', false); }
            }).fail(function(){ $s.text('Could not save.'); $('#ms-ship-box button').prop('disabled', false); });
        }
        $('#ms-ship-save').on('click', function(){ go(false, $(this)); });
        $('#ms-ship-done').on('click', function(){ go(true, $(this)); });
    });
    </script>
    <?php
}, 17);

add_action('wp_ajax_ms_commission_ship', function() {
    check_ajax_referer('ms_commission_ship', 'nonce');
    if (!current_user_can('edit_shop_orders')) wp_send_json_error('Insufficient permissions.');

    $order = wc_get_order(absint($_POST['order_id'] ?? 0));
    $original = $order ? ms_commission_original_order($order) : null;
    if (!$order || !$original) wp_send_json_error('This is not a commission balance order.');

    $carrier = sanitize_text_field(wp_unslash($_POST['carrier'] ?? ''));
    if (!isset(ms_commission_carriers()[$carrier])) $carrier = 'Other';
    $number = preg_replace('/[^A-Za-z0-9 \-]/', '', sanitize_text_field(wp_unslash($_POST['number'] ?? '')));

    // the finished piece's photo (the owner's own work: public, next to the previews, with a light copy)
    if (!empty($_FILES['photo']['name'])) {
        $file = $_FILES['photo'];
        if ($file['error'] !== UPLOAD_ERR_OK) wp_send_json_error('The photo could not be uploaded.');
        $check = ms_validate_upload_file($file);
        if (is_wp_error($check)) wp_send_json_error($check->get_error_message());

        $paths = ms_commission_paths();
        $dir = $paths['dir'] . 'order-' . $original->get_id() . '/finished/';
        wp_mkdir_p($dir);
        $name = wp_unique_filename($dir, current_time('Y-m-d_H-i-s') . '_' . sanitize_file_name($file['name']));
        if (!move_uploaded_file($file['tmp_name'], $dir . $name)) wp_send_json_error('The photo could not be saved.');
        ms_commission_make_web_copy($dir . $name);
        $order->update_meta_data('_ms_commission_finished_photo', $paths['url'] . 'order-' . $original->get_id() . '/finished/' . $name);
    }

    $order->update_meta_data('_ms_commission_carrier', $carrier);
    $order->update_meta_data('_ms_commission_tracking', $number);
    $order->save();
    $order->add_order_note('Commission shipping saved: ' . ($number !== '' ? $carrier . ' ' . $number : 'no tracking number') . (!empty($_FILES['photo']['name']) ? ', finished-piece photo added' : '') . '.');

    if (($_POST['complete'] ?? '') === 'yes' && !$order->has_status('completed')) {
        $order->update_status('completed', 'Marked shipped.');
    }

    wp_send_json_success();
});

/**
 * ===========================
 * COMPLETED EMAIL
 * ===========================
 */
add_action('woocommerce_order_status_completed', function($order_id, $order = false) {
    $order = $order ?: wc_get_order($order_id);
    if (!$order || !ms_commission_original_order($order) || $order->get_meta('_ms_commission_shipped_sent')) return;

    $order->update_meta_data('_ms_commission_shipped_sent', wp_date('Y-m-d H:i'));
    $order->save();

    $emails = WC()->mailer()->get_emails();
    if (isset($emails['MS_Email_Commission_Shipped'])) $emails['MS_Email_Commission_Shipped']->trigger($order);
}, 25, 2);

// the commission completed email replaces WooCommerce's generic one for a balance order
add_filter('woocommerce_email_enabled_customer_completed_order', function($enabled, $order) {
    return ($order instanceof WC_Order && ms_commission_original_order($order)) ? false : $enabled;
}, 10, 2);

/**
 * My Commissions: the tracking line for a shipped commission (called from the tab)
 */
function ms_commission_render_tracking($order) {
    foreach (ms_commission_order_items($order) as $item) {
        $fid = ms_get_commission_meta($item, 'final_payment_order_id');
        $final = $fid ? wc_get_order($fid) : null;
        $t = $final ? ms_commission_tracking($final) : null;
        if (!$t) continue;

        echo '<p class="commission-tracking" style="margin:0 0 1em"><strong>Shipping:</strong> ' . esc_html(trim($t['carrier'] . ' ' . $t['number']));
        if ($t['url']) echo ' &middot; <a href="' . esc_url($t['url']) . '" target="_blank" rel="noopener">Track your package</a>';
        echo '</p>';
        return;
    }
}

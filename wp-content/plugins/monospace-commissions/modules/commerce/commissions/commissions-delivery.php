<?php
/**
 * Delivery watch (owner, 2026-11-26): you buy the USPS label, set up USPS email updates for the number on usps.com, then press
 * "Start delivery watch" in the balance order's shipping panel. Every hour the site reads the Gmail account the commission emails
 * already use (read-only) for USPS's "delivered" email about that number. When it arrives, commissions@ is told once and the watch
 * stops. The site never asks USPS itself (its tracking access is closed to these labels) and nothing is sent to the customer.
 *
 * Counts as delivered: sender *.usps.com (not the Informed Delivery digest, which lists several packages), the tracking number in
 * the subject, and the subject says delivered - not expected, out for delivery, attempted or undeliverable.
 *
 * Order meta: _ms_delivery_watching ('yes' while watching), _ms_delivery_watch (number, since, checked, latest, error, fail_since,
 * fail_told), _ms_delivery_delivered (timestamp).
 *
 * @package monospace-commissions
 */

if (!defined('ABSPATH')) exit;

const MS_DELIVERY_GIVE_UP_DAYS = 30;

function ms_delivery_number($number) {
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $number));
}

/** What a message says about this number: 'delivered', 'other' (a USPS update that is not a delivery) or '' (not about it) */
function ms_delivery_read($from, $subject, $number) {
    $from = strtolower((string) $from);
    if (!preg_match('/@([a-z0-9\-]+\.)*usps\.(com|gov)$/', $from) || strpos($from, 'informeddelivery') !== false) return '';
    $number = ms_delivery_number($number);
    if ($number === '' || strpos(ms_delivery_number($subject), $number) === false) return '';
    if (!preg_match('/\bdelivered\b/i', $subject)) return 'other';
    return preg_match('/expected|out for delivery|attempt|undeliver|not (be )?delivered|to be delivered|will be delivered/i', $subject) ? 'other' : 'delivered';
}

/**
 * Looks for the numbers in the Gmail account(s). Read-only: nothing is marked or moved.
 *
 * @param array $wanted order id => ['number' => ..., 'since' => timestamp]
 * @return array error ('' when every mailbox was read), found: order id => ['state', 'subject', 'time']
 */
function ms_delivery_search(array $wanted) {
    $out = ['error' => '', 'found' => []];
    $boxes = function_exists('ms_commission_mail_mailboxes') ? ms_commission_mail_mailboxes() : [];
    if (!$boxes) { $out['error'] = 'no Gmail login in wp-config.php'; return $out; }

    // from a month before the watch began: a package may be delivered before the button is pressed
    $since = min(array_column($wanted, 'since')) - 30 * DAY_IN_SECONDS;

    foreach ($boxes as $box) {
        $imap = new MS_Commission_Imap();
        if (!$imap->open($box['host'], $box['user'], $box['pass'])) { $out['error'] = $imap->error; continue; }
        $folder = '[Gmail]/All Mail';
        foreach ($imap->folders() as $f) if (stripos($f['attrs'], '\\All') !== false) { $folder = $f['name']; break; }
        if (!$imap->examine($folder)) { $out['error'] = 'no folder ' . $folder; $imap->close(); continue; }

        $uids = $imap->search('SINCE "' . gmdate('d-M-Y', $since) . '" FROM "usps"');
        rsort($uids);
        foreach (array_slice($uids, 0, 100) as $uid) {
            $raw = $imap->body($uid);
            if ($raw === '') continue;
            $m = ms_commission_mail_parse($raw);
            foreach ($wanted as $id => $w) {
                $state = ms_delivery_read($m['from'], $m['subject'], $w['number']);
                if ($state === '') continue;
                $have = $out['found'][$id] ?? null;
                // a delivery wins; otherwise the newest update
                if (!$have || ($state === 'delivered' && $have['state'] !== 'delivered') || ($state === $have['state'] && $m['date'] > $have['time'])) {
                    $out['found'][$id] = ['state' => $state, 'subject' => $m['subject'], 'time' => $m['date']];
                }
            }
        }
        $imap->close();
    }
    return $out;
}

function ms_delivery_watched_orders() {
    return wc_get_orders(['limit' => -1, 'meta_query' => [['key' => '_ms_delivery_watching', 'value' => 'yes']]]);
}

/** The commission's name for the alert: the deposit order's artwork line */
function ms_delivery_name($order) {
    $original = ms_commission_original_order($order) ?: $order;
    $items = ms_commission_order_items($original);
    $name = $items ? preg_replace('/^\s*Deposit \(\d+%\)\s*[—–-]\s*/u', '', html_entity_decode(reset($items)->get_name())) : 'Commission';
    $who = trim($original->get_formatted_billing_full_name());
    return $name . ($who !== '' ? ' for ' . $who : '') . ' (order #' . $original->get_id() . ')';
}

function ms_delivery_tell($order, $subject, array $lines) {
    $lines[] = 'Order: ' . $order->get_edit_order_url();
    return wp_mail(ms_commission_admin_email(), $subject, implode("\n\n", $lines));
}

function ms_delivery_stop($order) {
    $order->delete_meta_data('_ms_delivery_watching');
    $order->save();
}

/** The hourly check */
function ms_delivery_check() {
    $orders = ms_delivery_watched_orders();
    if (!$orders || get_transient('ms_delivery_lock')) return;
    set_transient('ms_delivery_lock', 1, 5 * MINUTE_IN_SECONDS);
    @set_time_limit(180);

    $wanted = [];
    foreach ($orders as $order) {
        $w = $order->get_meta('_ms_delivery_watch');
        if (is_array($w) && !empty($w['number'])) $wanted[$order->get_id()] = $w;
        else ms_delivery_stop($order);
    }
    $result = $wanted ? ms_delivery_search($wanted) : ['error' => '', 'found' => []];

    foreach ($orders as $order) {
        $id = $order->get_id();
        if (!isset($wanted[$id])) continue;
        $w = $wanted[$id];
        $hit = $result['found'][$id] ?? null;
        $w['checked'] = time();
        $w['error'] = $result['error'];
        if ($hit) $w['latest'] = $hit['subject'];

        if ($hit && $hit['state'] === 'delivered') {
            $when = wp_date('M j, Y, g:ia', $hit['time']);
            $sent = ms_delivery_tell($order, 'Delivered: ' . ms_delivery_name($order), [
                ms_delivery_name($order) . ' was delivered.',
                'USPS tracking: ' . $w['number'],
                'USPS email received: ' . $when . "\n" . $hit['subject'],
            ]);
            if (!$sent) { $w['error'] = 'the delivery alert could not be sent'; $order->update_meta_data('_ms_delivery_watch', $w); $order->save(); continue; }   // again next hour
            $order->update_meta_data('_ms_delivery_watch', $w);
            $order->update_meta_data('_ms_delivery_delivered', $hit['time']);
            $order->add_order_note('Delivery watch: delivered (USPS email ' . $when . '). Alert sent to ' . ms_commission_admin_email() . '.');
            ms_delivery_stop($order);
            continue;
        }

        if ($result['error'] !== '') {
            if (empty($w['fail_since'])) $w['fail_since'] = time();
            if (time() - $w['fail_since'] >= DAY_IN_SECONDS && empty($w['fail_told'])) {
                $w['fail_told'] = (int) ms_delivery_tell($order, 'Delivery watch is not working: ' . ms_delivery_name($order), ['The email check has failed for a day: ' . $result['error'], 'USPS tracking: ' . $w['number']]);
            }
        } else {
            unset($w['fail_since'], $w['fail_told']);
        }

        if (time() - $w['since'] >= MS_DELIVERY_GIVE_UP_DAYS * DAY_IN_SECONDS) {
            if (ms_delivery_tell($order, 'Delivery watch ended without a delivery: ' . ms_delivery_name($order), [
                'No USPS delivery email arrived in ' . MS_DELIVERY_GIVE_UP_DAYS . ' days, so the watch has stopped. Check the package on usps.com.',
                'USPS tracking: ' . $w['number'],
            ])) {
                $order->update_meta_data('_ms_delivery_watch', $w);
                $order->add_order_note('Delivery watch stopped: no delivery email in ' . MS_DELIVERY_GIVE_UP_DAYS . ' days.');
                ms_delivery_stop($order);
                continue;
            }
        }

        $order->update_meta_data('_ms_delivery_watch', $w);
        $order->save();
    }
    delete_transient('ms_delivery_lock');
}
add_action('ms_delivery_check', 'ms_delivery_check');

// scheduled only while something is being watched
add_action('shutdown', function() {
    if (!function_exists('wc_get_orders')) return;
    $next = wp_next_scheduled('ms_delivery_check');
    if (!is_admin() && !wp_doing_cron()) return;
    $watching = (bool) wc_get_orders(['limit' => 1, 'return' => 'ids', 'meta_query' => [['key' => '_ms_delivery_watching', 'value' => 'yes']]]);
    if ($watching && !$next) wp_schedule_event(time() + 60, 'hourly', 'ms_delivery_check');
    elseif (!$watching && $next) wp_clear_scheduled_hook('ms_delivery_check');
}, 21);

/**
 * ===========================
 * ADMIN: IN THE SHIPPING PANEL (balance order)
 * ===========================
 */
add_action('ms_commission_ship_panel_end', function($order) {
    $w = $order->get_meta('_ms_delivery_watch');
    $w = is_array($w) ? $w : [];
    $on = $order->get_meta('_ms_delivery_watching') === 'yes';
    $delivered = (int) $order->get_meta('_ms_delivery_delivered');
    $fmt = function($t) { return wp_date('M j, g:ia', $t); };

    echo '<div style="margin-top:14px;padding-top:12px;border-top:1px solid #c3c4c7">';
    echo '<h4 style="margin:0 0 8px">Delivery watch</h4>';
    if ($on) {
        echo '<p style="margin:0 0 8px"><strong>Watching</strong> <code>' . esc_html($w['number'] ?? '') . '</code> since ' . esc_html($fmt($w['since'] ?? time())) . '. ';
        echo !empty($w['checked']) ? 'Last checked ' . esc_html($fmt($w['checked'])) . '.' : 'First check within a few minutes.';
        echo '</p>';
        if (!empty($w['error'])) echo '<p style="margin:0 0 8px;color:#b32d2e">Last check failed: ' . esc_html($w['error']) . '</p>';
        if (!empty($w['latest'])) echo '<p style="margin:0 0 8px;color:#646970">Latest from USPS: ' . esc_html($w['latest']) . '</p>';
        echo '<p style="margin:0"><button type="button" class="button ms-delivery" data-do="check">Check now</button> <button type="button" class="button ms-delivery" data-do="stop">Stop watching</button> <span class="ms-delivery-status" style="margin-left:8px"></span></p>';
    } else {
        if ($delivered) echo '<p style="margin:0 0 8px"><strong>Delivered</strong> ' . esc_html($fmt($delivered)) . ' (<code>' . esc_html($w['number'] ?? '') . '</code>).</p>';
        echo '<p style="margin:0 0 8px;color:#646970">First set up email updates for the tracking number above on <a href="https://tools.usps.com/go/TrackConfirmAction_input" target="_blank" rel="noopener">usps.com</a> (Text &amp; Email Updates, include Delivered). Then start the watch: it reads your Gmail every hour and emails ' . esc_html(ms_commission_admin_email()) . ' when the package is delivered.</p>';
        echo '<p style="margin:0"><button type="button" class="button ms-delivery" data-do="start">Start delivery watch</button> <span class="ms-delivery-status" style="margin-left:8px"></span></p>';
    }
    echo '</div>';
    ?>
    <script>
    jQuery(function($){
        $('.ms-delivery').on('click', function(){
            var $b = $(this), what = $b.data('do'), $s = $b.parent().find('.ms-delivery-status');
            if (what === 'start' && !window.confirm('Have you set up USPS email updates for this number on usps.com?')) return;
            $b.prop('disabled', true); $s.text(what === 'check' ? 'Checking your email...' : 'Saving...');
            $.post(ajaxurl, {
                action: 'ms_commission_delivery', nonce: <?php echo wp_json_encode(wp_create_nonce('ms_commission_delivery')); ?>,
                order_id: <?php echo (int) $order->get_id(); ?>, what: what, carrier: $('#ms-ship-carrier').val(), number: $('#ms-ship-number').val()
            }, function(r){
                if (r && r.success) { location.reload(); return; }
                $b.prop('disabled', false); $s.text((r && r.data) || 'Could not do that.');
            }).fail(function(){ $b.prop('disabled', false); $s.text('Could not do that.'); });
        });
    });
    </script>
    <?php
});

add_action('wp_ajax_ms_commission_delivery', function() {
    check_ajax_referer('ms_commission_delivery', 'nonce');
    if (!current_user_can('edit_shop_orders')) wp_send_json_error('Insufficient permissions.');

    $order = wc_get_order(absint($_POST['order_id'] ?? 0));
    if (!$order || !ms_commission_original_order($order)) wp_send_json_error('This is not a commission balance order.');
    $what = sanitize_key($_POST['what'] ?? '');

    if ($what === 'start') {
        if (sanitize_text_field(wp_unslash($_POST['carrier'] ?? '')) !== 'USPS') wp_send_json_error('The watch only reads USPS emails.');
        $number = ms_delivery_number(sanitize_text_field(wp_unslash($_POST['number'] ?? '')));
        if (strlen($number) < 12) wp_send_json_error('Enter the full tracking number first.');
        $order->update_meta_data('_ms_commission_carrier', 'USPS');
        $order->update_meta_data('_ms_commission_tracking', $number);
        $order->update_meta_data('_ms_delivery_watch', ['number' => $number, 'since' => time()]);
        $order->update_meta_data('_ms_delivery_watching', 'yes');
        $order->delete_meta_data('_ms_delivery_delivered');
        $order->save();
        $order->add_order_note('Delivery watch started for USPS ' . $number . '.');
    } elseif ($what === 'stop') {
        ms_delivery_stop($order);
        $order->add_order_note('Delivery watch stopped by hand.');
    } elseif ($what === 'check') {
        delete_transient('ms_delivery_lock');
        ms_delivery_check();
    } else {
        wp_send_json_error('Unknown action.');
    }
    wp_send_json_success();
});

<?php
/**
 * Commissions: one folder of records per commission (2026-11-11, owner: "a central location for each commission's files and
 * correspondence, ideally off-line as well").
 *
 * The folder is the commission's private one, commission-files/order-{deposit order id}/ (outside the web root, next to the
 * reference photos):
 *   manifest.json        who, what, the orders, the stage, the folder name the offline copy uses ("7418 Jane Smith")
 *   order-notes.txt      the order notes of the deposit order and its balance order(s), rewritten whenever a note is added
 *   correspondence/      every WooCommerce email about the commission, one .html file each (From / To / Bcc / Subject / body)
 *   references/          the customer's photos (deleted 6 months after completion; the offline copy keeps them)
 * The previews and the finished-piece photo are the owner's own work and stay public (emails link to them), under
 * uploads/commissions/order-{id}/previews|finished/; the manifest says where. scripts/pull-commissions.sh copies both trees into
 * one offline folder per commission. Replies written in the mailbox itself are not seen by the site (see the pull script's notes).
 *
 * @package monospace-commissions
 */

if (!defined('ABSPATH')) exit;

/**
 * The deposit order a commission's files belong to (a balance order -> its deposit order)
 */
function ms_commission_root_order($order) {
    if (!$order instanceof WC_Order) return null;
    if (function_exists('ms_commission_final_items') && ms_commission_final_items($order)) {
        return function_exists('ms_commission_original_order') ? ms_commission_original_order($order) : null;
    }
    return ms_commission_order_items($order) ? $order : null;
}

function ms_commission_record_dir($root_id) {
    return ms_commission_private_dir() . 'order-' . (int) $root_id . '/';
}

/**
 * Folder name of the offline copy: "7418 Jane Smith"
 */
function ms_commission_folder_name($root) {
    $name = trim($root->get_formatted_billing_full_name());
    $name = trim(preg_replace('/[^\p{L}\p{N} .\'&-]+/u', '', $name));
    return $root->get_id() . ($name !== '' ? ' ' . $name : '');
}

/**
 * manifest.json: who, what and where. Written whenever something is recorded, so it follows the order.
 */
function ms_commission_write_manifest($root) {
    $dir = ms_commission_record_dir($root->get_id());
    if (!wp_mkdir_p($dir)) return false;

    $pieces = [];
    foreach (ms_commission_order_items($root) as $item) {
        $pieces[] = array_filter([
            'name' => $item->get_name(),
            'medium' => ms_get_commission_meta($item, 'medium'),
            'size' => ms_get_commission_meta($item, 'size'),
            'surface' => ms_get_commission_meta($item, 'surface'),
            'special_request' => ms_get_commission_meta($item, 'special_request'),
        ]);
    }

    $balance = [];
    foreach (ms_commission_order_items($root) as $item) {
        $id = ms_get_commission_meta($item, 'final_payment_order_id');
        if ($id) $balance[(int) $id] = (int) $id;
    }

    $manifest = [
        'order_id' => $root->get_id(),
        'balance_order_ids' => array_values($balance),
        'folder_name' => ms_commission_folder_name($root),
        'client' => trim($root->get_formatted_billing_full_name()),
        'email' => $root->get_billing_email(),
        'ordered' => $root->get_date_created() ? $root->get_date_created()->date('Y-m-d') : '',
        'stage' => function_exists('ms_commission_stage') ? ms_commission_stage($root) : '',
        'due' => function_exists('ms_commission_due') ? ms_commission_due($root) : '',
        'pieces' => $pieces,
        'public_folder' => 'wp-content/uploads/' . MS_COMMISSION_UPLOAD_DIR . '/order-' . $root->get_id() . '/',
        'written' => gmdate('c'),
    ];
    return file_put_contents($dir . 'manifest.json', wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false;
}

/**
 * order-notes.txt: the notes of the deposit order and its balance order(s), oldest first
 */
function ms_commission_write_notes($root) {
    $dir = ms_commission_record_dir($root->get_id());
    if (!wp_mkdir_p($dir)) return false;

    $ids = [$root->get_id()];
    foreach (ms_commission_order_items($root) as $item) {
        $id = ms_get_commission_meta($item, 'final_payment_order_id');
        if ($id) $ids[] = (int) $id;
    }

    $out = '';
    foreach (array_unique($ids) as $id) {
        $notes = wc_get_order_notes(['order_id' => $id, 'orderby' => 'date_created', 'order' => 'ASC']);
        $out .= 'ORDER #' . $id . "\n" . str_repeat('=', 40) . "\n";
        foreach ($notes as $note) {
            $kind = $note->customer_note ? ' [to customer]' : ($note->added_by === 'system' ? ' [system]' : '');
            $out .= $note->date_created->date('Y-m-d H:i') . $kind . "\n" . trim(wp_strip_all_tags($note->content)) . "\n\n";
        }
    }
    return file_put_contents($dir . 'order-notes.txt', $out) !== false;
}

/**
 * Remember the last mail WordPress is about to send; woocommerce_email_sent (right after) says which email it was
 */
add_filter('wp_mail', function($args) {
    $GLOBALS['ms_commission_last_mail'] = $args;
    return $args;
}, 99);

add_action('woocommerce_email_sent', function($sent, $email_id, $email) {
    $mail = $GLOBALS['ms_commission_last_mail'] ?? null;
    unset($GLOBALS['ms_commission_last_mail']);

    $order = is_object($email) ? ($email->object ?? null) : null;
    $root = ms_commission_root_order($order);
    if (!$root || !$mail) return;

    $dir = ms_commission_record_dir($root->get_id()) . 'correspondence/';
    if (!wp_mkdir_p($dir)) return;

    $headers = is_array($mail['headers'] ?? null) ? $mail['headers'] : preg_split('/\r?\n/', (string) ($mail['headers'] ?? ''));
    $head = [];
    foreach ($headers as $h) {
        if (preg_match('/^(From|Cc|Bcc|Reply-To|Content-Type):\s*(.+)$/i', trim((string) $h), $m)) $head[ucfirst(strtolower($m[1]))] = $m[2];
    }
    $is_html = stripos($head['Content-type'] ?? '', 'text/html') !== false;
    $to = is_array($mail['to']) ? implode(', ', $mail['to']) : (string) $mail['to'];
    $body = (string) ($mail['message'] ?? '');

    $meta = '<table style="font:14px/1.5 -apple-system,sans-serif;border-bottom:1px solid #ccc;margin-bottom:16px;padding-bottom:8px">'
        . '<tr><td><b>Date</b></td><td>' . esc_html(wp_date('Y-m-d H:i')) . '</td></tr>'
        . '<tr><td><b>Order</b></td><td>#' . (int) ($order instanceof WC_Order ? $order->get_id() : 0) . ' (commission #' . (int) $root->get_id() . ')</td></tr>'
        . '<tr><td><b>Email</b></td><td>' . esc_html((string) $email_id) . ($sent ? '' : ' - FAILED to send') . '</td></tr>'
        . '<tr><td><b>From</b></td><td>' . esc_html($head['From'] ?? '') . '</td></tr>'
        . '<tr><td><b>To</b></td><td>' . esc_html($to) . '</td></tr>'
        . (isset($head['Cc']) ? '<tr><td><b>Cc</b></td><td>' . esc_html($head['Cc']) . '</td></tr>' : '')
        . (isset($head['Bcc']) ? '<tr><td><b>Bcc</b></td><td>' . esc_html($head['Bcc']) . '</td></tr>' : '')
        . '<tr><td><b>Subject</b></td><td>' . esc_html((string) ($mail['subject'] ?? '')) . '</td></tr></table>';

    $html = '<!doctype html><meta charset="utf-8"><title>' . esc_html((string) ($mail['subject'] ?? '')) . '</title>' . $meta
        . ($is_html ? $body : '<pre style="white-space:pre-wrap;font:14px/1.5 -apple-system,sans-serif">' . esc_html($body) . '</pre>');

    $slug = sanitize_title((string) $email_id) ?: 'email';
    file_put_contents($dir . wp_date('Y-m-d_His') . '_' . $slug . '.html', $html);
    ms_commission_write_manifest($root);
}, 20, 3);

add_action('woocommerce_order_note_added', function($note_id, $order) {
    $root = ms_commission_root_order($order);
    if (!$root) return;
    ms_commission_write_notes($root);
    ms_commission_write_manifest($root);
}, 20, 2);

// a new commission gets its folder and manifest straight away (after the reference photos moved in, priority 10)
add_action('woocommerce_checkout_order_created', function($order) {
    $root = ms_commission_root_order($order);
    if ($root) ms_commission_write_manifest($root);
}, 20);

/**
 * Records for every commission that exists (the first run, and the button on WooCommerce -> Commission files). Earlier emails
 * were not kept by the site, so only the manifest and the order notes can be written for them.
 */
function ms_commission_archive_all() {
    $ids = wc_get_orders(['limit' => -1, 'return' => 'ids', 'type' => 'shop_order']);
    $n = 0;
    foreach ($ids as $id) {
        $root = ms_commission_root_order(wc_get_order($id));
        if (!$root || $root->get_id() !== (int) $id) continue;
        if (ms_commission_write_manifest($root) && ms_commission_write_notes($root)) $n++;
    }
    return $n;
}

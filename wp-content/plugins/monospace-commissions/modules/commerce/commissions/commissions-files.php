<?php
/**
 * Commissions: WooCommerce -> Commission files. What is stored (the customers' reference photos, private) and
 * when it goes, the unused temp folders, and the recent problems the commission code logged.
 *
 * @package monospace-commissions
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function() {
    add_submenu_page('woocommerce', 'Commission files', 'Commission files', 'manage_woocommerce', 'ms-commission-files', 'ms_commission_files_page');
});

/**
 * Size and file count of a folder
 */
function ms_commission_folder_stats($dir) {
    $bytes = 0;
    $files = 0;
    if (!is_dir($dir)) return [0, 0];

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getFilename() !== 'index.php') {
            $bytes += $f->getSize();
            $files++;
        }
    }
    return [$bytes, $files];
}

add_action('admin_post_ms_commission_files', function() {
    check_admin_referer('ms_commission_files');
    if (!current_user_can('manage_woocommerce')) wp_die('Insufficient permissions.');

    $what = sanitize_key($_POST['what'] ?? '');
    $private = ms_commission_private_dir();
    $msg = '';

    if ($what === 'delete_order') {
        $order = wc_get_order(absint($_POST['order_id'] ?? 0));
        if ($order && ms_commission_order_items($order) && is_dir($private . 'order-' . $order->get_id() . '/references')) {
            ms_commission_delete_references($order, 'by hand (Commission files screen)');
            $msg = 'Reference photos of order #' . $order->get_id() . ' deleted.';
        }
    } elseif ($what === 'cleanup_temp') {
        $n = 0;
        foreach ((array) glob($private . 'temp-*', GLOB_ONLYDIR) as $folder) {
            ms_commission_remove_dir($folder);
            $n++;
        }
        $msg = $n . ' unused temp folder(s) removed.';
    } elseif ($what === 'archive_all') {
        $msg = 'Records written for ' . ms_commission_archive_all() . ' commission(s).';
    } elseif ($what === 'clear_log') {
        delete_option('ms_commission_log');
        $msg = 'Problem list cleared.';
    }

    wp_safe_redirect(add_query_arg(['page' => 'ms-commission-files', 'done' => rawurlencode($msg)], admin_url('admin.php')));
    exit;
});

function ms_commission_files_page() {
    if (!current_user_can('manage_woocommerce')) return;

    $private = ms_commission_private_dir();
    $nonce = wp_nonce_field('ms_commission_files', '_wpnonce', true, false);
    $action = esc_url(admin_url('admin-post.php'));
    $date_format = get_option('date_format');

    // ---- the orders with stored photos
    $rows = [];
    $total = 0;
    foreach ((array) glob($private . 'order-*', GLOB_ONLYDIR) as $folder) {
        if (!preg_match('/order-(\d+)$/', $folder, $m) || !is_dir($folder . '/references')) continue;
        $order = wc_get_order((int) $m[1]);
        [$bytes, $count] = ms_commission_folder_stats($folder . '/references');
        $total += $bytes;
        $rows[] = ['order' => $order, 'id' => (int) $m[1], 'bytes' => $bytes, 'count' => $count, 'expires' => $order ? ms_commission_references_expire($order) : 0];
    }
    usort($rows, fn($a, $b) => $b['id'] <=> $a['id']);

    // ---- temp folders
    $temps = (array) glob($private . 'temp-*', GLOB_ONLYDIR);
    $temp_bytes = 0;
    foreach ($temps as $t) $temp_bytes += ms_commission_folder_stats($t)[0];

    $log = array_reverse((array) get_option('ms_commission_log', []));

    $panel = function($title, $chip, $chip_class, $line, $open, $body) {
        echo '<details class="ms-cf-panel"><summary><strong>' . esc_html($title) . '</strong> '
            . '<span class="ms-cf-chip ' . esc_attr($chip_class) . '">' . esc_html($chip) . '</span> <span class="ms-cf-line">' . esc_html($line) . '</span></summary><div class="ms-cf-body">';
        $body();
        echo '</div></details>';
    };

    echo '<div class="wrap"><h1>Commission files</h1>';
    echo '<style>.ms-cf-panel{background:#fff;border:1px solid #c3c4c7;border-radius:6px;margin:0 0 12px;max-width:980px}.ms-cf-panel summary{padding:12px 16px;cursor:pointer}'
        . '.ms-cf-body{padding:4px 16px 14px;border-top:1px solid #f0f0f1}.ms-cf-chip{display:inline-block;margin:0 8px;padding:1px 9px;border-radius:10px;font-size:12px;border:1px solid #c3c4c7;color:#646970}'
        . '.ms-cf-chip.is-on{background:#2271b1;border-color:#2271b1;color:#fff}.ms-cf-chip.is-warn{border-color:#b32d2e;color:#b32d2e}.ms-cf-line{color:#646970}</style>';

    if (!empty($_GET['done'])) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(rawurldecode(wp_unslash($_GET['done']))) . '</p></div>';
    echo '<p>The customers\' reference photos are stored privately (outside the website folder) and deleted automatically 6 months after the commission is completed. The finished-piece photos and work-in-progress images are your own work and stay public.</p>';

    $panel('Reference photos', $rows ? 'In use' : 'Empty', $rows ? 'is-on' : '', count($rows) . ' commission(s), ' . size_format($total), true, function() use ($rows, $date_format, $nonce, $action) {
        if (!$rows) { echo '<p>No reference photos are stored.</p>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>Order</th><th>Customer</th><th>Files</th><th>Size</th><th>Deleted on</th><th></th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $o = $r['order'];
            echo '<tr><td>' . ($o ? '<a href="' . esc_url($o->get_edit_order_url()) . '">#' . (int) $r['id'] . '</a>' : '#' . (int) $r['id'] . ' (order missing)') . '</td>';
            echo '<td>' . esc_html($o ? trim($o->get_formatted_billing_full_name()) : '-') . '</td><td>' . (int) $r['count'] . '</td><td>' . esc_html(size_format($r['bytes'])) . '</td>';
            echo '<td>' . ($r['expires'] ? esc_html(wp_date($date_format, $r['expires'])) : 'when the commission is completed + 6 months') . '</td>';
            echo '<td><form method="post" action="' . $action . '" onsubmit="return confirm(\'Delete these reference photos now? This cannot be undone.\')">' . $nonce
                . '<input type="hidden" name="action" value="ms_commission_files"><input type="hidden" name="what" value="delete_order"><input type="hidden" name="order_id" value="' . (int) $r['id'] . '">'
                . '<button class="button button-small">Delete now</button></form></td></tr>';
        }
        echo '</tbody></table>';
    });

    $panel('Commission records', 'Kept', 'is-on', 'manifest, order notes and emails of every commission, in its private folder', false, function() use ($nonce, $action) {
        echo '<p>Each commission has one private folder (<code>commission-files/order-{id}/</code>) with its manifest, the order notes and every email the site sent about it (<code>correspondence/</code>), next to the reference photos. These records are kept when the photos are deleted. <code>scripts/pull-commissions.sh</code> copies them, with the work-in-progress images and the finished-piece photo, into one folder per commission on your Mac. Emails sent before 2026-11-11 were not kept; this button writes the manifest and order notes for every commission.</p>';
        echo '<form method="post" action="' . $action . '">' . $nonce . '<input type="hidden" name="action" value="ms_commission_files"><input type="hidden" name="what" value="archive_all"><button class="button">Write records for all commissions</button></form>';
    });

    $panel('Unused temp folders', $temps ? 'In use' : 'Empty', $temps ? 'is-on' : '', count($temps) . ' folder(s), ' . size_format($temp_bytes), (bool) $temps, function() use ($temps, $nonce, $action) {
        echo '<p>Photos from a cart that never became an order. Folders older than 3 days are removed every day.</p>';
        if ($temps) {
            echo '<form method="post" action="' . $action . '">' . $nonce . '<input type="hidden" name="action" value="ms_commission_files"><input type="hidden" name="what" value="cleanup_temp"><button class="button">Remove them now</button></form>';
        }
    });

    $panel('Recent problems', $log ? count($log) . ' logged' : 'None', $log ? 'is-warn' : '', $log ? 'the commission code logged these' : 'nothing has gone wrong', (bool) $log, function() use ($log, $nonce, $action) {
        if (!$log) { echo '<p>Nothing logged.</p>'; return; }
        echo '<table class="widefat striped"><thead><tr><th>When</th><th>What</th><th>Details</th></tr></thead><tbody>';
        foreach (array_slice($log, 0, 30) as $l) {
            echo '<tr><td>' . esc_html(wp_date('M j, H:i', $l['t'])) . '</td><td>' . esc_html($l['m']) . '</td><td><code>' . esc_html(wp_json_encode($l['d'])) . '</code></td></tr>';
        }
        echo '</tbody></table><form method="post" action="' . $action . '" style="margin-top:10px">' . $nonce . '<input type="hidden" name="action" value="ms_commission_files"><input type="hidden" name="what" value="clear_log"><button class="button">Clear the list</button></form>';
    });

    echo '</div>';
}

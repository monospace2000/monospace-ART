<?php
/**
 * Commissions: stages, due date, the customer's Approve / Request changes, the order screen panel and the
 * orders-list column + filter. The stage and due date live on the deposit order (order meta
 * _ms_commission_stage / _ms_commission_due); every change leaves an order note.
 *
 * @package monospace-commissions
 */

if (!defined('ABSPATH')) exit;

define('MS_COMMISSION_TURNAROUND_DAYS', 14);   // "most commissions are finished within two weeks"

/**
 * ===========================
 * STAGES
 * ===========================
 */
function ms_commission_stages() {
    return [
        'received' => 'Received',
        'in_progress' => 'In progress',
        'preview_sent' => 'Update shared',
        'changes_requested' => 'Customer replied',
        'approved' => 'Approved',
        'balance_requested' => 'Balance requested',
        'paid' => 'Paid',
        'done' => 'Done',
    ];
}

/**
 * What the customer reads under the stage
 */
function ms_commission_stage_hint($stage) {
    $hints = [
        'received' => 'Your deposit has been received. Work on your artwork will begin soon.',
        'in_progress' => 'Your artwork is in progress. Updates will be shared as the work develops.',
        'preview_sent' => 'An update has been shared below. Your thoughts are welcome.',
        'changes_requested' => 'Thank you. Your response has been received and the work continues.',
        'approved' => 'Thank you for approving the artwork. Your final payment invoice is on its way.',
        'balance_requested' => 'Your final payment invoice has been sent. Once it is paid, your artwork is shipped.',
        'paid' => 'Paid - thank you! Your artwork is being packed and shipped.',
        'done' => 'Completed. I hope you love it!',
    ];
    return $hints[$stage] ?? '';
}

/**
 * What the customer sees as the status: the stages while the work is under way are one for them ("In progress"), however
 * many updates and replies there are (owner, 2026-11-16: a conversation, not rounds).
 */
function ms_commission_stage_customer_label($stage) {
    if (in_array($stage, ['received', 'in_progress', 'preview_sent', 'changes_requested'], true)) return 'In progress';
    return ms_commission_stages()[$stage] ?? '';
}

/**
 * The stage of a commission order. Orders from before stages existed (#7418) have no meta: it is worked out
 * from what is there (nothing is written).
 */
function ms_commission_stage($order) {
    $stages = ms_commission_stages();
    $stage = $order->get_meta('_ms_commission_stage');
    if ($stage && isset($stages[$stage])) return $stage;

    $finals = [];
    $previews = false;
    foreach (ms_commission_order_items($order) as $item) {
        $fid = ms_get_commission_meta($item, 'final_payment_order_id');
        $final = $fid ? wc_get_order($fid) : null;
        if ($final && !$final->has_status('cancelled')) $finals[] = $final;
        if (ms_get_commission_meta($item, 'preview_images')) $previews = true;
    }

    if ($finals) {
        $done = true;
        $paid = false;
        foreach ($finals as $f) {
            if (!$f->has_status('completed')) $done = false;
            if ($f->has_status(['processing', 'completed'])) $paid = true;
        }
        return $done ? 'done' : ($paid ? 'paid' : 'balance_requested');
    }

    return $previews ? 'preview_sent' : 'in_progress';
}

function ms_commission_set_stage($order, $stage, $detail = '') {
    $stages = ms_commission_stages();
    if (!isset($stages[$stage]) || $order->get_meta('_ms_commission_stage') === $stage) return;

    $order->update_meta_data('_ms_commission_stage', $stage);
    $order->update_meta_data('_ms_commission_stage_at', time());
    $order->save();
    $order->add_order_note('Commission stage: ' . $stages[$stage] . ($detail !== '' ? ' - ' . $detail : ''));
}

function ms_commission_due($order) {
    $due = $order->get_meta('_ms_commission_due');
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $due) ? $due : '';
}

function ms_commission_is_late($order) {
    $due = ms_commission_due($order);
    return $due && $due < wp_date('Y-m-d') && in_array(ms_commission_stage($order), ['received', 'in_progress', 'changes_requested'], true);
}

/**
 * The deposit order a balance order belongs to
 */
function ms_commission_original_order($final) {
    foreach (ms_commission_final_items($final) as $item) {
        $id = ms_get_commission_meta($item, 'original_order_id');
        return $id ? wc_get_order($id) : null;
    }
    return null;
}

/**
 * ===========================
 * AUTOMATIC TRANSITIONS
 * ===========================
 */

// deposit order comes in: Received + due date
function ms_commission_workflow_start($order_id, $order = false) {
    $order = $order ?: wc_get_order($order_id);
    if (!$order || $order->get_meta('_ms_commission_stage') || !ms_commission_order_items($order)) return;

    $order->update_meta_data('_ms_commission_due', wp_date('Y-m-d', time() + MS_COMMISSION_TURNAROUND_DAYS * DAY_IN_SECONDS));
    $order->save();
    ms_commission_set_stage($order, 'received');
}
add_action('woocommerce_order_status_processing', 'ms_commission_workflow_start', 10, 2);
add_action('woocommerce_order_status_on-hold', 'ms_commission_workflow_start', 10, 2);

// a preview was uploaded
add_action('ms_commission_preview_sent', function($order) {
    if (in_array(ms_commission_stage($order), ['received', 'in_progress', 'changes_requested', 'preview_sent'], true)) {
        ms_commission_set_stage($order, 'preview_sent');
    }
});

// the balance invoice went out (the button on the balance order, or WooCommerce's own "Email invoice" action)
function ms_commission_invoice_sent($final) {
    if (!($final instanceof WC_Order)) return;

    // when the invoice went out (a resend restarts the reminder clock)
    $original = ms_commission_original_order($final);
    if ($original) {
        $final->update_meta_data('_ms_commission_invoice_sent', time());
        $final->delete_meta_data('_ms_commission_reminders');
        $final->save();
    }
    if ($original) ms_commission_set_stage($original, 'balance_requested', 'invoice sent (order #' . $final->get_id() . ')');
}
add_action('ms_commission_invoice_sent', 'ms_commission_invoice_sent');
add_action('woocommerce_order_action_send_order_details', 'ms_commission_invoice_sent');

// the balance order is paid / completed
function ms_commission_balance_status($order_id, $order = false) {
    $order = $order ?: wc_get_order($order_id);
    $original = $order ? ms_commission_original_order($order) : null;
    if (!$original) return;

    ms_commission_set_stage($original, $order->has_status('completed') ? 'done' : 'paid', 'balance order #' . $order->get_id());
}
add_action('woocommerce_order_status_processing', 'ms_commission_balance_status', 10, 2);
add_action('woocommerce_order_status_completed', 'ms_commission_balance_status', 10, 2);

/**
 * ===========================
 * CUSTOMER: STATUS, APPROVE / REQUEST CHANGES (My Commissions tab)
 * ===========================
 */
function ms_commission_render_status($order) {
    $stage = ms_commission_stage($order);
    $label = ms_commission_stage_customer_label($stage);
    $due = ms_commission_due($order);

    // Its own sub-box (owner, 2026-11-17), tinted by where the commission stands: working = blue, waiting for the final payment = yellow, paid / done = green
    $tone = in_array($stage, ['approved', 'balance_requested'], true) ? 'is-waiting' : (in_array($stage, ['paid', 'done'], true) ? 'is-paid' : 'is-working');
    echo '<div class="commission-status-box ' . $tone . '">';
    echo '<p class="commission-status"><strong>Status:</strong> ' . esc_html($label);
    if ($due && in_array($stage, ['received', 'in_progress', 'preview_sent', 'changes_requested'], true)) {
        echo ' &middot; expected by ' . esc_html(wp_date(get_option('date_format'), strtotime($due . ' 12:00:00')));
    }
    echo '</p>';
    $hint = ms_commission_stage_hint($stage);
    if ($hint) echo '<p class="commission-status-hint">' . esc_html($hint) . '</p>';
    $dates = ms_commission_status_dates($order, $stage);
    if ($dates) echo '<p class="commission-status-dates">' . esc_html(implode(' · ', $dates)) . '</p>';
    echo '</div>';
}

/**
 * The dates under the status (owner, 2026-11-17): when the status last changed, when the last update was shared, when the customer last replied.
 * Without a year while it is this year.
 *
 * @return string[]
 */
function ms_commission_status_dates($order, $stage) {
    $fmt = function($t) { return wp_date(wp_date('Y', $t) === wp_date('Y') ? 'F j' : 'F j, Y', $t); };
    $labels = [
        'received' => 'Deposit received', 'in_progress' => 'Updated', 'preview_sent' => 'Update shared', 'changes_requested' => 'You replied',
        'approved' => 'Approved', 'balance_requested' => 'Final invoice sent', 'paid' => 'Paid', 'done' => 'Completed',
    ];
    $out = [];
    $at = (int) $order->get_meta('_ms_commission_stage_at');
    if ($at && isset($labels[$stage])) $out[] = $labels[$stage] . ' ' . $fmt($at);

    $shared = 0;
    $reply  = 0;
    foreach (ms_commission_thread($order) as $e) {
        if ($e['who'] === 'owner' && strpos($e['text'], 'Shared') === 0) $shared = max($shared, $e['time']);
        if ($e['who'] === 'customer') $reply = max($reply, $e['time']);
    }
    if ($shared && !($stage === 'preview_sent' && $at && $fmt($shared) === $fmt($at))) $out[] = 'Last update shared ' . $fmt($shared);
    if ($reply && $stage !== 'changes_requested') $out[] = 'Your last reply ' . $fmt($reply);
    return $out;
}

/**
 * The exchange on one commission, oldest first, for the customer's page (owner, 2026-11-17: her comments show there too, so the
 * whole conversation can be followed in one place). Built from the order notes the workflow already writes - no second copy to
 * keep: "Customer comment: ...", "Customer: approved so far.", "Commission stage: Approved - approved by the customer" and
 * "Shared N work-in-progress image(s) ...". The owner's message with an update sits in that note after "Message: " (since 2026-11-17).
 *
 * @return array[] each [ 'who' => 'customer'|'owner', 'time' => timestamp, 'text' => string ]
 */
/** When the finished artwork was shared (0 = not yet): the customer's Approve final artwork button is on only from then */
function ms_commission_final_shared($order) {
    return (int) $order->get_meta('_ms_commission_final_shared');
}

function ms_commission_thread($order) {
    $thread = [];
    foreach (wc_get_order_notes(['order_id' => $order->get_id(), 'order_by' => 'date_created', 'order' => 'ASC']) as $note) {
        $text = (string) $note->content;
        $time = $note->date_created ? $note->date_created->getTimestamp() : 0;
        if (strpos($text, 'Customer comment: ') === 0) {
            $thread[] = ['who' => 'customer', 'time' => $time, 'text' => substr($text, 18)];
        } elseif ($text === 'Customer: approved so far.') {
            $thread[] = ['who' => 'customer', 'time' => $time, 'text' => 'Approved so far.'];
        } elseif (strpos($text, 'Commission stage: Approved - approved by the customer') === 0) {
            $thread[] = ['who' => 'customer', 'time' => $time, 'text' => 'Approved the final artwork.'];
        } elseif (preg_match('/^Shared (\d+) (work-in-progress|finished-artwork) image\(s\)/', $text, $m)) {
            if ($m[2] === 'finished-artwork') $body = (int) $m[1] === 1 ? 'Shared the finished artwork (1 image).' : 'Shared the finished artwork (' . (int) $m[1] . ' images).';
            else $body = (int) $m[1] === 1 ? 'Shared a new image of the work in progress.' : 'Shared ' . (int) $m[1] . ' new images of the work in progress.';
            $pos = strpos($text, "\nMessage: ");
            if ($pos !== false) $body .= "\n" . trim(substr($text, $pos + 10));
            $thread[] = ['who' => 'owner', 'time' => $time, 'text' => $body];
        }
    }
    // Emails with the customer, read from the commissions@ mailbox (commissions-mail.php)
    $mail = $order->get_meta('_ms_commission_mail');
    foreach (is_array($mail) ? $mail : [] as $rec) {
        $urls = [];
        foreach ((array) ($rec['images'] ?? []) as $name) $urls[] = ms_commission_file_url('order-' . $order->get_id(), $name);
        $thread[] = ['who' => $rec['who'], 'time' => (int) $rec['time'], 'text' => (string) $rec['text'], 'mail' => true, 'images' => $urls];
    }
    usort($thread, function($a, $b) { return $a['time'] <=> $b['time']; });
    return $thread;
}

function ms_commission_render_conversation($order) {
    $thread = ms_commission_thread($order);
    if (!$thread) return;

    // The photos in the emails are reference photos: they are deleted with the others, 6 months after completion
    $photos_deleted = '';
    foreach (ms_commission_order_items($order) as $item) if ($d = ms_get_commission_meta($item, 'references_deleted')) $photos_deleted = $d;

    // A WP Details block's markup (closed; owner, 2026-11-17): the theme's details-fold.js folds it smoothly (details-fold.php, .ms-fold-body > .ms-fold-inner)
    echo '<details class="wp-block-details commission-conversation"><summary>Conversation</summary><div class="ms-fold-body"><div class="ms-fold-inner">';
    foreach (array_reverse($thread) as $entry) {   // newest on top (owner, 2026-11-17)
        $who = $entry['who'] === 'customer' ? 'You' : 'Hens';
        echo '<div class="commission-message commission-message-' . esc_attr($entry['who']) . '">';
        echo '<p class="commission-message-meta">' . esc_html($who) . ' &middot; ' . esc_html(wp_date(get_option('date_format') . ', g:ia', $entry['time'])) . (!empty($entry['mail']) ? ' &middot; by email' : '') . '</p>';
        echo '<p class="commission-message-text">' . nl2br(esc_html($entry['text'])) . '</p>';
        if (!empty($entry['images']) && $photos_deleted) {
            echo '<p class="commission-message-meta">Photos deleted on ' . esc_html($photos_deleted) . ', as the policies say.</p>';
        } elseif (!empty($entry['images'])) {
            echo '<div class="commission-preview-gallery commission-message-photos">';
            foreach ($entry['images'] as $url) {
                echo '<a href="' . esc_url($url) . '" target="_blank" class="commission-preview-link"><img src="' . esc_url(ms_commission_thumb_url($url)) . '" alt="Photo sent by email" class="commission-preview-img" loading="lazy"></a>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    echo '</div></div></details>';
}

/**
 * The customer's answer to an update, in two parts so the conversation can sit between them (owner, 2026-11-17):
 * 'reply' = Approved so far / Add a comment, above the conversation; 'approve' = Approve final artwork, right-aligned below it
 * (it also prints the script, once, after both).
 */
function ms_commission_render_respond($order, $part = 'reply') {
    if (!in_array(ms_commission_stage($order), ['preview_sent', 'changes_requested'], true)) return;

    static $script = false;
    $id = esc_attr($order->get_id());

    if ($part === 'reply') {
        echo '<div class="ms-commission-respond" data-order="' . $id . '" style="margin-top:1em;padding-top:1em;border-top:1px solid var(--ms-card-border, #ccc)">';
        echo '<p style="margin:0 0 .6em"><strong>Your thoughts on the work so far</strong></p>';
        echo '<p class="ms-respond-buttons" style="margin:0">';
        echo '<button type="button" class="button ms-respond-ack">Approved so far</button> ';
        echo '<button type="button" class="button ms-respond-comment">Add a comment</button>';
        echo '</p>';
        echo '<div class="ms-respond-form" style="display:none;margin-top:.8em">';
        echo '<label>Comments are welcome but not required.<br><textarea rows="4" style="width:100%" class="ms-respond-message"></textarea></label>';
        echo '<p style="margin:.6em 0 0"><button type="button" class="button ms-respond-send">Send</button></p>';
        echo '</div>';
        echo '<p class="ms-respond-result" role="status" style="margin:.8em 0 0"></p>';
        echo '</div>';
        return;
    }

    echo '<div class="ms-commission-respond ms-commission-approve" data-order="' . $id . '" style="margin-top:1.5em;text-align:right">';
    echo '<hr style="margin:0 0 1.2em;height:3px;background-color:currentColor;opacity:.6">';
    $ready = ms_commission_final_shared($order) > 0;
    echo '<button type="button" class="button ms-respond-approve"' . ($ready ? '' : ' disabled') . '>Approve final artwork</button>';
    if (!$ready) echo '<p class="ms-respond-approve-hint">Available once the finished artwork has been shared.</p>';
    echo '<p class="ms-respond-result" role="status" style="margin:.8em 0 0"></p>';
    echo '</div>';

    if ($script) return;
    $script = true;
    $nonce = wp_create_nonce('ms_commission_respond');
    ?>
    <script>
    (function(){
        var url = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>, nonce = <?php echo wp_json_encode($nonce); ?>, busy = false;
        function boxesOf(box) { return document.querySelectorAll('.ms-commission-respond[data-order="' + box.getAttribute('data-order') + '"]'); }
        function send(box, type) {
            if (busy) return;
            var order = box.getAttribute('data-order'), area = box.querySelector('.ms-respond-message'), msg = area ? area.value : '';
            var result = box.querySelector('.ms-respond-result');
            if (type === 'comment' && msg.trim().length < 3) { result.textContent = 'Please add a few words, or use "Approved so far".'; return; }
            if (type === 'ack' && !window.confirm('Send "Approved so far"? This lets me know you are happy with the work as it is. The work continues, and you can still add a comment.')) return;
            if (type === 'approve' && !window.confirm('Confirm that the artwork is finished? Your final payment invoice will follow.')) return;
            busy = true;
            var fd = new FormData();
            fd.append('action', 'ms_commission_respond'); fd.append('nonce', nonce);
            fd.append('order_id', order); fd.append('response', type); fd.append('message', msg);
            result.textContent = 'Sending...';
            fetch(url, {method: 'POST', body: fd, credentials: 'same-origin'}).then(function(r){ return r.json(); }).then(function(j){
                result.textContent = (j && j.data && (j.data.message || j.data)) || 'Something went wrong. Please reply to the email instead.';
                if (j && j.success) {
                    boxesOf(box).forEach(function(b){ b.querySelectorAll('button').forEach(function(x){ x.disabled = true; }); var f = b.querySelector('.ms-respond-form'); if (f) f.style.display = 'none'; });
                    setTimeout(function(){ location.reload(); }, 2500);
                } else { busy = false; }
            }).catch(function(){ busy = false; result.textContent = 'Could not send. Please reply to the email instead.'; });
        }
        document.addEventListener('click', function(e){
            var btn = e.target.closest ? e.target.closest('.ms-commission-respond button') : null;
            if (!btn) return;
            var box = btn.closest('.ms-commission-respond');
            if (btn.classList.contains('ms-respond-approve')) send(box, 'approve');
            else if (btn.classList.contains('ms-respond-ack')) send(box, 'ack');
            else if (btn.classList.contains('ms-respond-comment')) box.querySelector('.ms-respond-form').style.display = 'block';
            else if (btn.classList.contains('ms-respond-send')) send(box, 'comment');
        });
    })();
    </script>
    <?php
}

add_action('wp_ajax_ms_commission_respond', function() {
    check_ajax_referer('ms_commission_respond', 'nonce');

    $order = wc_get_order(absint($_POST['order_id'] ?? 0));
    if (!$order || !is_user_logged_in() || (int) $order->get_customer_id() !== get_current_user_id() || !ms_commission_order_items($order)) {
        wp_send_json_error('Commission not found.');
    }
    if (!in_array(ms_commission_stage($order), ['preview_sent', 'changes_requested'], true)) {
        wp_send_json_error('There is nothing to respond to right now.');
    }

    $type = sanitize_key($_POST['response'] ?? '');
    $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));
    $replies = (int) $order->get_meta('_ms_commission_revisions');   // meta name kept: the number of customer replies, no limit

    if ($type === 'approve') {
        if (!ms_commission_final_shared($order)) wp_send_json_error('The finished artwork has not been shared yet.');
        ms_commission_set_stage($order, 'approved', 'approved by the customer');
        $reply = 'Thank you. I will send your final payment invoice soon.';
    } elseif ($type === 'ack') {
        $order->add_order_note('Customer: approved so far.');
        $reply = 'Thank you. Your response has been received.';
    } elseif ($type === 'comment') {
        if (strlen(trim($message)) < 3) wp_send_json_error('Please add a few words, or use "Approved so far".');
        $replies++;
        $order->update_meta_data('_ms_commission_revisions', $replies);
        $order->save();
        ms_commission_set_stage($order, 'changes_requested', 'customer comment: ' . $message);
        if (ms_commission_stage($order) === 'changes_requested') $order->add_order_note('Customer comment: ' . $message);
        $reply = 'Thank you. Your response has been received.';
    } else {
        wp_send_json_error('Unknown request.');
    }

    $emails = WC()->mailer()->get_emails();
    if (isset($emails['MS_Email_Commission_Response'])) {
        $emails['MS_Email_Commission_Response']->trigger($order, $type, $message, $replies);
    }

    wp_send_json_success(['message' => $reply]);
});

/**
 * ===========================
 * ADMIN: ORDER SCREEN PANEL
 * ===========================
 */
add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    if (!ms_commission_order_items($order)) return;

    $stage = ms_commission_stage($order);
    $due = ms_commission_due($order);
    $used = (int) $order->get_meta('_ms_commission_revisions');
    $explicit = (bool) $order->get_meta('_ms_commission_stage');

    ms_commission_order_summary($order, $stage, $due);
    echo '<div id="ms-commission-stage-box" class="ms-order-panel ms-zone-main">';
    echo '<h3 style="margin:0 0 10px;font-size:14px">Commission stage</h3>';
    if (!$explicit) echo '<p style="margin:0 0 10px;color:#646970">No stage saved yet - shown as worked out from the order. Save to fix it.</p>';

    echo '<p class="ms-stage-row"><label>Stage <select id="ms-stage-select">';
    foreach (ms_commission_stages() as $key => $label) echo '<option value="' . esc_attr($key) . '"' . selected($stage, $key, false) . '>' . esc_html($label) . '</option>';
    echo '</select></label> <label>Due <input type="date" id="ms-stage-due" value="' . esc_attr($due) . '"></label></p>';
    echo '<p id="ms-due-note-row" style="margin:0 0 10px;display:none"><label>Note about the new date <span style="color:#646970">(only you see it)</span><br><input type="text" id="ms-due-note" class="large-text" maxlength="300" placeholder="Why the date moved"></label></p>';
    echo '<p id="ms-due-mail-row" style="margin:0 0 10px;display:none"><label><input type="checkbox" id="ms-due-mail"> Email the customer the new date (once, when saving)</label></p>';
    echo '<p id="ms-stage-mail-row" style="margin:0 0 10px;display:none"><label><input type="checkbox" id="ms-stage-mail"> Email the customer about this (once, when saving)</label></p>';
    echo '<p style="margin:0 0 10px;color:#646970">Customer comments so far: ' . (int) $used . '. There is no limit; the commission is a conversation.</p>';
    if (function_exists('ms_commission_mail_status_line')) echo '<p style="margin:0 0 10px;color:#646970">' . esc_html(ms_commission_mail_status_line()) . '</p>';
    echo '<p style="margin:0"><button type="button" class="button button-primary" id="ms-stage-save" data-order="' . esc_attr($order->get_id()) . '">Save stage</button> <span id="ms-stage-status" style="margin-left:8px"></span></p>';
    echo '</div>';
    ?>
    <script>
    jQuery(function($){
        function msStageMail(){ var v = $('#ms-stage-select').val(); $('#ms-stage-mail-row').toggle(v === 'in_progress' || v === 'changes_requested'); if (!$('#ms-stage-mail-row').is(':visible')) $('#ms-stage-mail').prop('checked', false); }
        $('#ms-stage-select').on('change', msStageMail); msStageMail();
        var msDueSaved = $('#ms-stage-due').val();
        function msDueRows(){ var ch = $('#ms-stage-due').val() !== msDueSaved; $('#ms-due-note-row').toggle(ch); $('#ms-due-mail-row').toggle(ch && $('#ms-stage-due').val() !== ''); if (!ch) { $('#ms-due-mail').prop('checked', false); } }
        $('#ms-stage-due').on('change input', msDueRows);
        $('#ms-stage-save').on('click', function(){
            var $b = $(this), $s = $('#ms-stage-status');
            $b.prop('disabled', true); $s.text('Saving...');
            $.post(ajaxurl, {
                action: 'ms_commission_admin_stage', nonce: <?php echo wp_json_encode(wp_create_nonce('ms_commission_admin_stage')); ?>,
                order_id: $b.data('order'), stage: $('#ms-stage-select').val(), due: $('#ms-stage-due').val(), due_note: $('#ms-due-note').val(), due_mail: $('#ms-due-mail').is(':checked') ? 'yes' : '', mail: $('#ms-stage-mail').is(':checked') ? 'yes' : ''
            }, function(r){
                $b.prop('disabled', false);
                if (r && r.success) { $('#ms-stage-mail').prop('checked', false); msDueSaved = $('#ms-stage-due').val(); $('#ms-due-note').val(''); msDueRows(); $('.ms-sum-chip').text($('#ms-stage-select option:selected').text()); }
                $s.text(r && r.success ? (r.data && (r.data.mailed || r.data.due_mailed) ? 'Saved. Email sent.' : 'Saved.') : ((r && r.data) || 'Could not save.'));
            }).fail(function(){ $b.prop('disabled', false); $s.text('Could not save.'); });
        });
    });
    </script>
    <?php
}, 5);

/**
 * The top of a commission order: what it is, who for, where it stands, what is owed (printed in the order box's
 * General column like the other panels; the layout script below moves everything to the top of the screen)
 */
function ms_commission_order_summary($order, $stage, $due) {
    $items = ms_commission_order_items($order);
    if (!$items) return;
    $item = reset($items);
    $stages = ms_commission_stages();
    $name = trim($order->get_formatted_billing_full_name()) ?: 'Customer';
    $email = $order->get_billing_email();
    $facts = [];
    foreach (['medium', 'size', 'surface'] as $k) {
        $v = ms_get_commission_meta($item, $k);
        if ($v) $facts[] = esc_html($v);
    }
    $deposit = ms_get_commission_meta($item, 'deposit_paid');
    $balance = ms_get_commission_meta($item, 'final_payment');
    $fid = (int) ms_get_commission_meta($item, 'final_payment_order_id');
    $fo = $fid ? wc_get_order($fid) : null;
    $money = [];
    if ($deposit) $money[] = ($order->is_paid() ? 'Deposit paid ' : 'Deposit ') . wp_strip_all_tags(wc_price($deposit));
    if ($fo && !$fo->has_status('cancelled')) {
        $money[] = 'Balance <a href="' . esc_url($fo->get_edit_order_url()) . '">order #' . (int) $fid . '</a> (' . esc_html(wc_get_order_status_name($fo->get_status())) . ')';
    } elseif ($balance) {
        $money[] = 'Balance ' . wp_strip_all_tags(wc_price($balance)) . ' not invoiced yet';
    }

    $late = ms_commission_is_late($order);
    echo '<div id="ms-order-summary" class="ms-order-panel">';
    echo '<div class="ms-sum-main"><strong class="ms-sum-title">' . esc_html($item->get_name()) . '</strong>';
    if ($facts) echo '<span class="ms-sum-facts">' . implode(' &middot; ', $facts) . '</span>';
    echo '</div>';
    echo '<div class="ms-sum-row">';
    echo '<span class="ms-sum-chip">' . esc_html($stages[$stage] ?? $stage) . '</span>';
    echo '<span class="ms-sum-due' . ($late ? ' is-late' : '') . '">' . ($due ? ($late ? 'Past due ' : 'Due ') . esc_html(wp_date('M j, Y', strtotime($due . ' 12:00:00'))) : 'No due date') . '</span>';
    echo '<span>For <strong>' . esc_html($name) . '</strong>' . ($email ? ' &middot; <a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>' : '') . '</span>';
    if ($money) echo '<span>' . implode(' &middot; ', $money) . '</span>';
    echo '<button type="button" class="button-link" id="ms-toggle-addresses" aria-expanded="false">Addresses &amp; contact</button>';
    echo '</div></div>';
}

/**
 * Commission order screens: everything the commission needs, in the order the work goes, at the top of the
 * screen (summary, then stage / next step on the left and the brief on the right); addresses fold away on a
 * deposit order. Each panel is printed by its own file with the classes ms-order-panel and ms-zone-main / -brief.
 */
add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    if (!$order->get_meta('_ms_commission_stage') && !ms_commission_order_items($order) && !ms_commission_original_order($order)) return;
    ?>
    <style>
        #ms-order-panels { display: grid; grid-template-columns: minmax(0, 3fr) minmax(0, 2fr); gap: 16px; align-items: start; margin: 0 0 16px; }
        #ms-order-panels > #ms-order-summary { grid-column: 1 / -1; }
        .ms-zone { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
        .ms-order-panel { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; padding: 14px 18px; box-sizing: border-box; margin: 0; }
        .ms-order-panel h3, .ms-order-panel h4 { margin: 0 0 10px; font-size: 14px; }
        #ms-order-summary { display: block; padding: 16px 18px; }
        .ms-sum-main { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 14px; margin-bottom: 8px; }
        .ms-sum-title { font-size: 16px; }
        .ms-sum-facts { color: #646970; }
        .ms-sum-row { display: flex; flex-wrap: wrap; align-items: center; gap: 6px 18px; color: #3c434a; }
        .ms-sum-chip { background: #2271b1; color: #fff; border-radius: 12px; padding: 1px 12px; font-weight: 600; }
        .ms-sum-due.is-late { color: #b32d2e; font-weight: 600; }
        #ms-commission-stage-box .ms-stage-row { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 16px; margin: 0 0 12px; }
        #ms-commission-stage-box .ms-stage-row label { display: inline-flex; align-items: center; gap: 8px; margin: 0; }
        #ms-commission-stage-box select, #ms-commission-stage-box input[type=date] { width: auto; min-width: 150px; margin: 0; }
        #ms-order-panels .commission-admin-preview-box { background: #fff; margin: 0; padding: 14px 18px; border: 1px solid #c3c4c7; border-radius: 6px; }
        .ms-commission-details table { width: 100%; }
        .ms-commission-details td { vertical-align: top; }
        @media (max-width: 1100px) { #ms-order-panels { grid-template-columns: minmax(0, 1fr); } }
        /* A deposit order: the order data is one compact row; addresses open with the toggle */
        body.ms-has-summary:not(.ms-show-addresses) .order_data_column_billing,
        body.ms-has-summary:not(.ms-show-addresses) .order_data_column_shipping { display: none; }
        body.ms-has-summary .order_data_column_general { float: none; width: 100% !important; padding: 0 !important; margin: 0 !important; display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 0 24px; align-items: start; }
        body.ms-has-summary .order_data_column_general > h3 { grid-column: 1 / -1; }
        body.ms-has-summary .order_data_column_billing, body.ms-has-summary .order_data_column_shipping { margin-top: 16px; }
    </style>
    <script>
    jQuery(function($){
        var $wrap = $('<div id="ms-order-panels"></div>');
        var $sum = $('#ms-order-summary');
        if ($sum.length) { $wrap.append($sum); $('body').addClass('ms-has-summary'); }
        // The work-in-progress upload is printed under the order's item; its handlers sit on document, so it can move
        var $main = $('<div class="ms-zone"></div>').append($('.ms-zone-main')), $wip = $('.commission-admin-preview-box').first();
        if ($wip.length) { $wip.addClass('ms-order-panel'); if ($main.find('#ms-commission-stage-box').length) $wip.insertAfter($main.find('#ms-commission-stage-box')); else $main.prepend($wip); }
        $wrap.append($main, $('<div class="ms-zone"></div>').append($('.ms-zone-brief')));
        $wrap.find('.ms-zone:empty').remove();
        if ($('#woocommerce-order-data').length) $wrap.insertBefore('#woocommerce-order-data'); else $wrap.insertBefore('.order_data_column_container');
        $('#ms-toggle-addresses').on('click', function(){
            var open = $('body').toggleClass('ms-show-addresses').hasClass('ms-show-addresses');
            $(this).attr('aria-expanded', open ? 'true' : 'false');
        });
    });
    </script>
    <?php
}, 99);

add_action('wp_ajax_ms_commission_admin_stage', function() {
    check_ajax_referer('ms_commission_admin_stage', 'nonce');
    if (!current_user_can('edit_shop_orders')) wp_send_json_error('Insufficient permissions.');

    $order = wc_get_order(absint($_POST['order_id'] ?? 0));
    if (!$order || !ms_commission_order_items($order)) wp_send_json_error('Commission order not found.');

    $stage = sanitize_key($_POST['stage'] ?? '');
    if (!isset(ms_commission_stages()[$stage])) wp_send_json_error('Unknown stage.');

    $due = sanitize_text_field(wp_unslash($_POST['due'] ?? ''));
    if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) wp_send_json_error('Invalid date.');

    $due_mailed = false;
    $old_due = ms_commission_due($order);
    if ($due !== $old_due) {
        $fmt = function($d) { return $d ? wp_date('M j, Y', strtotime($d . ' 12:00:00')) : 'none'; };
        $reason = sanitize_text_field(wp_unslash($_POST['due_note'] ?? ''));
        $order->update_meta_data('_ms_commission_due', $due);
        // A private note (the customer never sees order notes unless they are flagged for the customer)
        $order->add_order_note('Commission due date moved from ' . $fmt($old_due) . ' to ' . $fmt($due) . ($reason !== '' ? '. Reason (private): ' . $reason : '.'));
        $order->save();
        if (($_POST['due_mail'] ?? '') === 'yes' && $due !== '') {
            $emails = WC()->mailer()->get_emails();
            if (isset($emails['MS_Email_Commission_DueDate'])) $due_mailed = (bool) $emails['MS_Email_Commission_DueDate']->trigger($order, $due);
        }
    }
    $changed = ms_commission_stage($order) !== $stage || $order->get_meta('_ms_commission_stage') !== $stage;
    ms_commission_set_stage($order, $stage, 'set by hand');

    // Optional, unchecked by default: tell the customer (only for the stages where you are working on the piece)
    $mailed = false;
    if (($_POST['mail'] ?? '') === 'yes' && $changed && in_array($stage, ['in_progress', 'changes_requested'], true)) {
        $emails = WC()->mailer()->get_emails();
        if (isset($emails['MS_Email_Commission_Update'])) $mailed = (bool) $emails['MS_Email_Commission_Update']->trigger($order, $stage);
    }
    wp_send_json_success(['mailed' => $mailed, 'due_mailed' => $due_mailed]);
});

/**
 * ===========================
 * ADMIN: ORDERS LIST COLUMN + FILTER
 * ===========================
 */
add_filter('manage_woocommerce_page_wc-orders_columns', function($columns) {
    $out = [];
    foreach ($columns as $key => $label) {
        $out[$key] = $label;
        if ($key === 'order_status') $out['ms_commission'] = 'Commission';
    }
    if (!isset($out['ms_commission'])) $out['ms_commission'] = 'Commission';
    return $out;
});

add_action('manage_woocommerce_page_wc-orders_custom_column', function($column, $order) {
    if ($column !== 'ms_commission') return;
    if (!ms_commission_order_items($order)) { echo '<span aria-hidden="true">&ndash;</span>'; return; }

    $stage = ms_commission_stage($order);
    $late = ms_commission_is_late($order);
    echo '<span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;border:1px solid ' . ($late ? '#b32d2e' : '#c3c4c7') . ';color:' . ($late ? '#b32d2e' : '#1d2327') . '">' . esc_html(ms_commission_stages()[$stage]) . '</span>';
    $due = ms_commission_due($order);
    if ($due) echo '<br><small style="color:' . ($late ? '#b32d2e' : '#646970') . '">due ' . esc_html(wp_date('M j', strtotime($due . ' 12:00:00'))) . '</small>';
}, 10, 2);

add_action('woocommerce_order_list_table_restrict_manage_orders', function($order_type, $which) {
    if ($which !== 'top') return;
    $current = isset($_GET['ms_stage']) ? sanitize_key($_GET['ms_stage']) : '';

    echo '<select name="ms_stage"><option value="">All orders</option>';
    echo '<option value="open"' . selected($current, 'open', false) . '>Commissions - open</option>';
    echo '<option value="any"' . selected($current, 'any', false) . '>Commissions - all</option>';
    foreach (ms_commission_stages() as $key => $label) echo '<option value="' . esc_attr($key) . '"' . selected($current, $key, false) . '>' . esc_html('Commission: ' . $label) . '</option>';
    echo '</select>';
}, 10, 2);

add_filter('woocommerce_order_list_table_prepare_items_query_args', function($args) {
    $f = isset($_GET['ms_stage']) ? sanitize_key($_GET['ms_stage']) : '';
    if ($f === '') return $args;

    if ($f === 'any') {
        $args['meta_query'][] = ['key' => '_ms_commission_stage', 'compare' => 'EXISTS'];
    } elseif ($f === 'open') {
        $args['meta_query'][] = ['key' => '_ms_commission_stage', 'value' => 'done', 'compare' => '!='];
    } elseif (isset(ms_commission_stages()[$f])) {
        $args['meta_query'][] = ['key' => '_ms_commission_stage', 'value' => $f];
    }
    return $args;
});

/**
 * ===========================
 * INVOICE "SENT AT" (balance order screen)
 * ===========================
 */
add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    $sent = (int) $order->get_meta('_ms_commission_invoice_sent');
    if (!$sent || !ms_commission_original_order($order)) return;

    echo '<p class="ms-order-note ms-zone-main" style="margin:0;color:#646970">Invoice email sent ' . esc_html(wp_date('M j, Y H:i', $sent)) . '. Payment reminders go out 3 and 7 days later while it is unpaid.</p>';
}, 16);

/**
 * ===========================
 * REMINDERS (daily)
 * ===========================
 * You: a commission past its due date, or approved for 2+ days with no balance invoice - once each.
 * Customer: the unpaid balance order, 3 and 7 days after its invoice was sent.
 */
add_action('init', function() {
    if (!wp_next_scheduled('ms_commission_reminders')) {
        wp_schedule_event(time() + 2 * HOUR_IN_SECONDS, 'daily', 'ms_commission_reminders');
    }
}, 20);

/**
 * Works out (and with $send sends) the reminders. Returns what it found, for the tests.
 */
function ms_commission_reminders_run($send = true) {
    $found = ['owner' => [], 'customer' => []];
    $today = wp_date('Y-m-d');
    $stages = ms_commission_stages();

    // ---- owner
    $open = wc_get_orders([
        'limit' => -1,
        'meta_query' => [['key' => '_ms_commission_stage', 'value' => ['received', 'in_progress', 'changes_requested', 'approved'], 'compare' => 'IN']],
    ]);
    foreach ($open as $order) {
        $stage = $order->get_meta('_ms_commission_stage');
        $items = ms_commission_order_items($order);
        $name = $items ? reset($items)->get_name() : 'Commission';

        if ($stage === 'approved') {
            $at = (int) $order->get_meta('_ms_commission_stage_at');
            if ($at && time() - $at >= 2 * DAY_IN_SECONDS && !$order->get_meta('_ms_commission_approved_notified')) {
                $found['owner'][] = ['order' => $order, 'flag' => '_ms_commission_approved_notified', 'value' => '1',
                    'text' => sprintf('#%d %s - approved %d days ago, no balance invoice yet', $order->get_id(), $name, floor((time() - $at) / DAY_IN_SECONDS))];
            }
        } elseif (ms_commission_is_late($order)) {
            $due = ms_commission_due($order);
            if ($order->get_meta('_ms_commission_overdue_notified') !== $due) {
                $days = max(1, (int) floor((strtotime($today . ' 12:00:00') - strtotime($due . ' 12:00:00')) / DAY_IN_SECONDS));
                $found['owner'][] = ['order' => $order, 'flag' => '_ms_commission_overdue_notified', 'value' => $due,
                    'text' => sprintf('#%d %s - %s, %d day(s) past the due date', $order->get_id(), $name, $stages[$stage], $days)];
            }
        }
    }

    // ---- customer
    $unpaid = wc_get_orders([
        'limit' => -1,
        'status' => ['pending', 'failed'],
        'meta_query' => [['key' => '_ms_commission_invoice_sent', 'compare' => 'EXISTS']],
    ]);
    foreach ($unpaid as $final) {
        if (!ms_commission_original_order($final)) continue;

        $days = (int) floor((time() - (int) $final->get_meta('_ms_commission_invoice_sent')) / DAY_IN_SECONDS);
        $done = (array) $final->get_meta('_ms_commission_reminders');
        $due_now = array_values(array_filter([3, 7], fn($t) => $days >= $t && !in_array($t, $done, true)));
        if ($due_now) $found['customer'][] = ['order' => $final, 'mark' => array_merge($done, $due_now), 'day' => max($due_now)];
    }

    if (!$send) return $found;

    $emails = WC()->mailer()->get_emails();

    if ($found['owner'] && isset($emails['MS_Email_Commission_Digest'])) {
        $lines = [];
        foreach ($found['owner'] as $f) $lines[] = ['text' => $f['text'], 'url' => $f['order']->get_edit_order_url()];
        if ($emails['MS_Email_Commission_Digest']->trigger($lines)) {
            foreach ($found['owner'] as $f) {
                $f['order']->update_meta_data($f['flag'], $f['value']);
                $f['order']->save();
            }
        }
    }

    foreach ($found['customer'] as $f) {
        $final = $f['order'];
        $name = '';
        foreach (ms_commission_final_items($final) as $item) {
            $name = preg_replace('/^Final Payment\s*[-\x{2014}]\s*/u', '', $item->get_name());
            break;
        }
        $final->update_meta_data('_ms_commission_reminders', $f['mark']);
        $final->save();
        if (isset($emails['MS_Email_Commission_Reminder'])) $emails['MS_Email_Commission_Reminder']->trigger($final, $name ?: 'your commission');
    }

    return $found;
}
add_action('ms_commission_reminders', function() { ms_commission_reminders_run(true); });

/**
 * ===========================
 * DASHBOARD BOX: COMMISSIONS NEEDING ACTION
 * ===========================
 */
function ms_commission_attention($order) {
    $stage = ms_commission_stage($order);
    if ($stage === 'done') return null;

    if (ms_commission_is_late($order)) return ['you', 'Past its due date'];
    switch ($stage) {
        case 'received': return ['you', 'New - start on it'];
        case 'changes_requested': return ['you', 'Customer replied - have a look and continue'];
        case 'approved': return ['you', 'Approved - send the balance invoice'];
        case 'paid': return ['you', 'Paid - ship it, then complete the balance order'];
        case 'preview_sent': return ['customer', 'Update shared - waiting for the customer'];
        case 'balance_requested': return ['customer', 'Balance invoice sent - waiting for payment'];
    }
    return ['ok', 'In progress'];
}

add_action('wp_dashboard_setup', function() {
    if (!current_user_can('edit_shop_orders')) return;
    wp_add_dashboard_widget('ms_commission_needs_action', 'Commissions needing action', 'ms_commission_dashboard_widget');
});

function ms_commission_dashboard_widget() {
    $orders = wc_get_orders([
        'limit' => 100,
        'meta_query' => [['key' => '_ms_commission_stage', 'value' => 'done', 'compare' => '!=']],
        'orderby' => 'date',
        'order' => 'ASC',
    ]);

    $groups = ['you' => [], 'customer' => [], 'ok' => []];
    foreach ($orders as $order) {
        $a = ms_commission_attention($order);
        if ($a) $groups[$a[0]][] = [$order, $a[1]];
    }

    if (!$orders) {
        echo '<p>No open commissions.</p>';
    }

    $titles = ['you' => 'Needs you', 'customer' => 'Waiting for the customer', 'ok' => 'In progress'];
    foreach ($titles as $key => $title) {
        if (!$groups[$key]) continue;
        echo '<p style="margin:12px 0 4px"><strong>' . esc_html($title) . ' (' . count($groups[$key]) . ')</strong></p><ul style="margin:0">';
        foreach ($groups[$key] as [$order, $what]) {
            $items = ms_commission_order_items($order);
            $name = $items ? reset($items)->get_name() : 'Commission';
            $due = ms_commission_due($order);
            echo '<li><a href="' . esc_url($order->get_edit_order_url()) . '">#' . (int) $order->get_id() . ' ' . esc_html($name) . '</a> - ' . esc_html($what);
            if ($due) echo ' <span style="color:#646970">(due ' . esc_html(wp_date('M j', strtotime($due . ' 12:00:00'))) . ')</span>';
            echo '</li>';
        }
        echo '</ul>';
    }

    echo '<p style="margin:12px 0 0"><a href="' . esc_url(admin_url('admin.php?page=wc-orders&ms_stage=open')) . '">All open commissions</a></p>';
}

/**
 * ===========================
 * NEVER TO PRINTFUL
 * ===========================
 * A commission is never a print (owner's rule, 2026-11-07). Printful for WooCommerce hears about orders through WooCommerce
 * webhooks; the deposit order of the second test run reached it and Printful opened an empty draft order. Webhooks about a
 * commission's deposit or balance order are not delivered (nothing else about the webhooks changes).
 */
add_filter('woocommerce_webhook_should_deliver', function($should, $webhook, $arg) {
    // Printful's order webhook is the action topic "woocommerce_order_status_processing" (the argument is the order ID)
    if (!$should || !is_object($webhook) || !preg_match('/^(order\.|action\.woocommerce_order)/', (string) $webhook->get_topic())) return $should;
    $order = wc_get_order(is_numeric($arg) ? (int) $arg : 0);
    if ($order && (ms_commission_order_items($order) || ms_commission_final_items($order))) return false;
    return $should;
}, 10, 3);

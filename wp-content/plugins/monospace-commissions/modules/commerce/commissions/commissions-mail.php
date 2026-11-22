<?php
/**
 * Commission emails in the conversation (owner, 2026-11-17: "I won't remember to paste"): the commissions@ mailbox - which already gets a
 * Bcc of every commission email, and is where a customer's reply to those lands - is read every ten minutes. A message between you and a
 * commission customer is added to that order's conversation (order meta _ms_commission_mail), so ms_commission_thread() shows it on the
 * customer's page next to the site's own replies.
 *
 * Which mail: only a message that involves the address of a commission customer (an open commission's billing or account email) is
 * fetched and read - found by an IMAP search on that address, so the rest of a mailbox is never opened. A commission usually starts as a
 * website inquiry (the contact form mails info@ with the visitor as Reply-To): the first time a customer's address is seen the search
 * reaches back 180 days, so the inquiry and everything before the order comes with it; after that it looks at the last 3 days.
 *
 * Off until a mailbox login is in wp-config.php (never in the database or the docs). One entry per mailbox; an entry without a
 * password is skipped. SiteGround mailboxes read INBOX and their Sent folder; Gmail reads "All Mail" (its inbox and sent in one)
 * with an app password (Google account > Security > 2-Step Verification > App passwords):
 *   define( 'MS_COMMISSION_MAILBOXES', [
 *       [ 'user' => 'commissions@monospace.com', 'pass' => '...', 'host' => 'gvam1265.siteground.biz' ],
 *       [ 'user' => 'info@monospace.com',        'pass' => '...', 'host' => 'gvam1265.siteground.biz' ],
 *       [ 'user' => 'monospace@gmail.com',       'pass' => '...', 'host' => 'imap.gmail.com', 'folders' => [ '[Gmail]/All Mail' ] ],
 *   ] );
 * 'host' for a SiteGround mailbox is the server's own name (its certificate does not carry mail.monospace.com). Optional per entry: 'folders' => [...], 'validate_cert' => false (only if the host's certificate does not match).
 *
 * The mailboxes are read with this file's own small IMAP client (MS_Commission_Imap) instead of PHP's imap extension, which sends no server
 * name in the secure handshake and so cannot talk to Gmail.
 *
 * Read-only: mailboxes are opened read-only and nothing in them is marked, moved or deleted. Only the customer's own address counts
 * (the sender or Reply-To of their message, the recipient of yours); every email the site itself sent is ignored, except a contact
 * form message, which is the customer's inquiry. The same email in two mailboxes (a Bcc, a forward) is stored once (its Message-ID).
 */

if (!defined('ABSPATH')) exit;

/**
 * ===========================
 * SETTINGS AND SCHEDULE
 * ===========================
 */
function ms_commission_mail_mailboxes() {
    $list = defined('MS_COMMISSION_MAILBOXES') && is_array(MS_COMMISSION_MAILBOXES) ? MS_COMMISSION_MAILBOXES : [];
    return array_values(array_filter($list, function($m) { return !empty($m['user']) && !empty($m['pass']); }));
}

function ms_commission_mail_configured() {
    return (bool) ms_commission_mail_mailboxes();
}

add_filter('cron_schedules', function($schedules) {
    $schedules['ms_every_10_minutes'] = ['interval' => 600, 'display' => 'Every 10 minutes'];
    return $schedules;
});

add_action('init', function() {
    $next = wp_next_scheduled('ms_commission_mail_poll');
    if (ms_commission_mail_configured()) {
        if (!$next) wp_schedule_event(time() + 120, 'ms_every_10_minutes', 'ms_commission_mail_poll');
    } elseif ($next) {
        wp_unschedule_event($next, 'ms_commission_mail_poll');
    }
}, 20);

add_action('ms_commission_mail_poll', 'ms_commission_mail_poll');

/** The addresses that count as "you" */
function ms_commission_mail_owner_addresses() {
    $list = [strtolower(ms_commission_admin_email()), strtolower((string) get_option('admin_email')), 'hens@monospace.com'];
    return array_values(array_unique(array_filter(apply_filters('ms_commission_owner_addresses', $list))));
}

/**
 * ===========================
 * READING A MESSAGE (no mail extension needed: plain text in, array out)
 * ===========================
 */
function ms_commission_mime_headers($head) {
    $head = preg_replace("/\r?\n[ \t]+/", ' ', $head);
    $h = [];
    foreach (preg_split("/\r?\n/", $head) as $line) {
        if (strpos($line, ':') === false) continue;
        list($k, $v) = explode(':', $line, 2);
        $k = strtolower(trim($k));
        if (!isset($h[$k])) $h[$k] = trim($v);
    }
    return $h;
}

function ms_commission_mime_split($raw) {
    $p = preg_split("/\r?\n\r?\n/", $raw, 2);
    return [ms_commission_mime_headers($p[0]), $p[1] ?? ''];
}

function ms_commission_mime_param($value, $name) {
    if (!preg_match('/\b' . $name . '\s*=\s*(?:"([^"]*)"|([^;\s]+))/i', $value, $m)) return '';
    return $m[1] !== '' ? $m[1] : ($m[2] ?? '');
}

function ms_commission_mime_utf8($text, $charset) {
    $charset = strtolower(trim($charset ?: 'utf-8'));
    if ($charset === 'utf-8' || $charset === 'us-ascii') return $text;
    $out = @iconv($charset, 'UTF-8//IGNORE', $text);
    return $out === false ? $text : $out;
}

/** Walk the parts: plain and html texts (decoded to UTF-8) and the number of attachments */
function ms_commission_mime_collect($head, $body, &$acc, $depth = 0) {
    $ct = $head['content-type'] ?? 'text/plain';
    $type = strtolower(trim(strtok($ct, ';')));

    if (strpos($type, 'multipart/') === 0 && $depth < 6) {
        $boundary = ms_commission_mime_param($ct, 'boundary');
        if ($boundary === '') return;
        $chunks = preg_split('/\r?\n?--' . preg_quote($boundary, '/') . '(?:--)?[ \t]*\r?\n?/', $body);
        array_shift($chunks);   // the preamble
        foreach ($chunks as $chunk) {
            if (trim($chunk) === '') continue;
            list($h, $b) = ms_commission_mime_split($chunk);
            ms_commission_mime_collect($h, $b, $acc, $depth + 1);
        }
        return;
    }

    $disp = $head['content-disposition'] ?? '';
    $filename = ms_commission_mime_param($disp, 'filename') ?: ms_commission_mime_param($ct, 'name');

    // Photos the customer sent (a signature's small logo does not count): kept when asked for, scaled down when stored
    if (!empty($acc['keep']) && strpos($type, 'image/') === 0 && strtolower($head['content-transfer-encoding'] ?? '') === 'base64') {
        $data = (string) base64_decode($body);
        if (strlen($data) >= 30000) $acc['images'][] = ['name' => $filename ?: 'photo', 'type' => $type, 'data' => $data];
    }
    if (stripos($disp, 'attachment') === 0 || ($filename !== '' && empty($head['content-id']))) {
        $acc['att']++;
        return;
    }
    if ($type !== 'text/plain' && $type !== 'text/html') return;

    $enc = strtolower($head['content-transfer-encoding'] ?? '');
    if ($enc === 'base64') $body = (string) base64_decode($body);
    elseif ($enc === 'quoted-printable') $body = quoted_printable_decode($body);
    $acc[$type === 'text/plain' ? 'plain' : 'html'][] = ms_commission_mime_utf8($body, ms_commission_mime_param($ct, 'charset'));
}

function ms_commission_mail_html_to_text($html) {
    $html = preg_replace('/<div class="gmail_quote.*$/is', '', $html);
    $html = preg_replace('/<blockquote.*?<\/blockquote>/is', '', $html);
    $html = preg_replace('/<(style|script|head)\b.*?<\/\1>/is', '', $html);
    $html = preg_replace('/<br\s*\/?>|<\/(p|div|li|tr|h\d)>/i', "\n", $html);
    return html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Only the new words: the quoted older emails, the signature delimiter and "Sent from my ..." go */
function ms_commission_mail_clean_text($text) {
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
    $out = [];
    foreach ($lines as $i => $line) {
        $t = trim($line);
        $next = isset($lines[$i + 1]) ? trim($lines[$i + 1]) : '';
        if (preg_match('/^On\b/i', $t) && preg_match('/wrote:\s*$/i', $t . ' ' . $next)) break;
        if (preg_match('/^-{2,}\s*(Original Message|Forwarded message)/i', $t)) break;
        if (preg_match('/^From:\s.+/i', $t) && preg_match('/^(Sent|Date):/i', $next)) break;
        if ($t === '--' || preg_match('/^Sent from my\b/i', $t)) break;
        if (isset($t[0]) && $t[0] === '>') continue;
        $out[] = rtrim($line);
    }
    $text = trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $out)));
    return mb_substr($text, 0, 4000);
}

function ms_commission_mail_addresses($value) {
    preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', (string) $value, $m);
    return array_values(array_unique(array_map('strtolower', $m[0])));
}

/**
 * @return array from, reply_to, recipients, subject, message_id, date (timestamp), auto (an automatic reply or bulk mail), site (sent by this
 *         website or its mail service: its own emails, or a contact form message whose reply_to is the visitor), text, att
 */
function ms_commission_mail_parse($raw, $keep_images = false) {
    list($h, $body) = ms_commission_mime_split($raw);
    $subject = isset($h['subject']) ? trim((string) @iconv_mime_decode($h['subject'], 0, 'UTF-8')) : '';
    $from = ms_commission_mail_addresses($h['from'] ?? '');
    $id = trim($h['message-id'] ?? '');

    // Automatic replies and bulk mail are not a person's message; "site" = sent by this website (its copies in these mailboxes, or a contact form message)
    $auto = (isset($h['auto-submitted']) && strtolower($h['auto-submitted']) !== 'no')
        || isset($h['x-autoreply']) || isset($h['x-autorespond'])
        || (isset($h['precedence']) && preg_match('/bulk|junk|auto_reply/i', $h['precedence']))
        || (bool) preg_match('/^(automatic reply|auto:|out of office|undeliverable|delivery status)/i', $subject);
    $site = (bool) preg_match('/PHPMailer|MailPoet/i', $h['x-mailer'] ?? '') || (bool) preg_match('/@(art\.monospace\.com|sendingservice\.net)>?$/i', $id);
    $reply_to = ms_commission_mail_addresses($h['reply-to'] ?? '');

    $acc = ['plain' => [], 'html' => [], 'att' => 0, 'images' => [], 'keep' => $keep_images];
    ms_commission_mime_collect($h, $body, $acc);
    $text = $acc['plain'] ? implode("\n", $acc['plain']) : ms_commission_mail_html_to_text(implode("\n", $acc['html']));
    if ($site && preg_match('/^Message Body:[ \t]*\r?\n(.*)$/ms', $text, $mm)) $text = $mm[1];   // the contact form's own wrapper

    return [
        'from' => $from[0] ?? '',
        'reply_to' => $reply_to[0] ?? '',
        'recipients' => ms_commission_mail_addresses(($h['to'] ?? '') . ' ' . ($h['cc'] ?? '')),
        'subject' => $subject,
        'message_id' => $id,
        'date' => isset($h['date']) && strtotime($h['date']) ? strtotime($h['date']) : time(),
        'auto' => $auto,
        'site' => $site,
        'text' => ms_commission_mail_clean_text($text),
        'att' => max(0, $acc['att'] - count($acc['images'])),   // the photos are shown, not "see the email"
        'images' => $acc['images'],
    ];
}

/**
 * ===========================
 * WHICH COMMISSION, AND STORING IT
 * ===========================
 */

/** The customer's commission order for these addresses: the order number in the subject if there is one, else the newest not yet done */
function ms_commission_mail_find_order($emails, $subject) {
    $orders = [];
    foreach ($emails as $email) {
        $found = wc_get_orders(['billing_email' => $email, 'limit' => 20, 'orderby' => 'date', 'order' => 'DESC']);
        $user = get_user_by('email', $email);
        if ($user) $found = array_merge($found, wc_get_orders(['customer_id' => $user->ID, 'limit' => 20, 'orderby' => 'date', 'order' => 'DESC']));
        foreach ($found as $o) {
            if (ms_commission_order_items($o)) $orders[$o->get_id()] = $o;
        }
    }
    if (!$orders) return null;
    krsort($orders);

    if (preg_match_all('/#(\d{3,})/', $subject, $m)) {
        foreach ($m[1] as $n) if (isset($orders[(int) $n])) return $orders[(int) $n];
    }
    foreach ($orders as $o) if (ms_commission_stage($o) !== 'done') return $o;
    return reset($orders);
}

/**
 * Save a message's photos in the order's private references folder (served to the order's customer and to you only, deleted with the
 * other reference photos 6 months after completion), scaled down to 1200 px on the long edge, metadata dropped.
 *
 * @return string[] the file names saved (a photo that cannot be read is left out)
 */
function ms_commission_mail_store_images($order, $key, array $images) {
    $dir = ms_commission_private_dir() . 'order-' . $order->get_id() . '/references/';
    wp_mkdir_p($dir);
    $ext = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/heic' => 'heic', 'image/heif' => 'heic'];
    $names = [];
    foreach ($images as $i => $img) {
        $tmp = $dir . 'mail_' . $key . '_' . ($i + 1) . '.' . ($ext[strtolower($img['type'])] ?? 'jpg');
        $final = 'mail_' . $key . '_' . ($i + 1) . '.jpg';
        if (file_put_contents($tmp, $img['data']) === false) continue;
        $editor = wp_get_image_editor($tmp);
        if (is_wp_error($editor)) { @unlink($tmp); continue; }
        $editor->maybe_exif_rotate();
        $size = $editor->get_size();
        if (max($size['width'], $size['height']) > 1200) $editor->resize(1200, 1200, false);
        $editor->set_quality(82);
        $saved = $editor->save($dir . $final, 'image/jpeg');
        if ($tmp !== $dir . $final) @unlink($tmp);
        if (!is_wp_error($saved)) $names[] = $final;
    }
    return $names;
}

/** @return string what happened: added, would-add (dry run), duplicate or a reason it was left out */
function ms_commission_mail_handle($m, $dry = false) {
    if ($m['auto']) return 'ignored';
    $owners = ms_commission_mail_owner_addresses();

    // The site's own emails are ignored (order notices, its commission emails); a contact form message - subject: site title + "the visitor's subject" -
    // is the visitor's inquiry, and its Reply-To is the visitor
    $from = $m['from'];
    if ($m['site']) {
        if (strpos($m['subject'], get_bloginfo('name') . ' "') !== 0 || $m['reply_to'] === '' || in_array($m['reply_to'], $owners, true)) return 'ignored';
        $from = $m['reply_to'];
    }
    if ($from === '') return 'ignored';

    if (in_array($from, $owners, true)) {
        $who = 'owner';
        $candidates = array_values(array_diff($m['recipients'], $owners));
    } else {
        $who = 'customer';
        $candidates = [$from];
    }
    if (!$candidates) return 'no-customer';

    $order = ms_commission_mail_find_order($candidates, $m['subject']);
    if (!$order) return 'no-commission';
    if ($m['text'] === '' && !$m['att'] && empty($m['images'])) return 'empty';

    $key = substr(sha1($m['message_id'] !== '' ? $m['message_id'] : $from . $m['date'] . $m['subject']), 0, 16);
    $all = $order->get_meta('_ms_commission_mail');
    $all = is_array($all) ? $all : [];
    if (isset($all[$key])) return 'duplicate';
    $photos = empty($m['images']) ? 0 : count($m['images']);
    if ($dry) return 'would-add (order #' . $order->get_id() . ', ' . $who . ($photos ? ', ' . $photos . ' photo' . ($photos > 1 ? 's' : '') : '') . ')';

    $text = $m['text'] . ($m['att'] ? ($m['text'] !== '' ? "\n" : '') . '(+ ' . $m['att'] . ' attachment' . ($m['att'] > 1 ? 's' : '') . ', see the email)' : '');
    $all[$key] = ['who' => $who, 'time' => $m['date'], 'text' => $text];
    if ($photos) {
        $names = ms_commission_mail_store_images($order, $key, $m['images']);
        if ($names) $all[$key]['images'] = $names;
    }
    $order->update_meta_data('_ms_commission_mail', $all);
    $order->save();
    $first = strtok($text, "\n");
    $order->add_order_note(($who === 'customer' ? 'Email from the customer' : 'Email from you') . ' added to the conversation: ' . mb_substr((string) $first, 0, 120));

    // A reply to an update is "Customer replied", as when she answers on the site
    if ($who === 'customer' && ms_commission_stage($order) === 'preview_sent') ms_commission_set_stage($order, 'changes_requested', 'customer email');
    return 'added';
}

/**
 * A minimal read-only IMAP client over PHP streams (secure connection with the server name sent, LOGIN, EXAMINE, UID SEARCH, UID FETCH)
 */
class MS_Commission_Imap {
    private $fp = null;
    private $n = 0;
    public $error = '';

    private static function q($s) {
        return '"' . addcslashes((string) $s, "\"\\") . '"';
    }

    public function open($host, $user, $pass, $validate = true) {
        $ctx = stream_context_create(['ssl' => ['peer_name' => $host, 'SNI_enabled' => true, 'verify_peer' => $validate, 'verify_peer_name' => $validate]]);
        $this->fp = @stream_socket_client('ssl://' . $host . ':993', $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->fp) { $this->error = 'could not connect (' . ($errstr ?: 'no answer') . ')'; return false; }
        stream_set_timeout($this->fp, 40);
        if (!preg_match('/^\* (OK|PREAUTH)/', (string) fgets($this->fp))) { $this->error = 'unexpected greeting'; return false; }
        if (stripos($host, 'gmail') !== false || stripos($host, 'google') !== false) $pass = preg_replace('/\s+/', '', $pass);   // Google shows app passwords in groups
        $r = $this->cmd('LOGIN ' . self::q($user) . ' ' . self::q($pass));
        if (!$r['ok']) { $this->error = 'login refused (' . $r['status'] . ')'; return false; }
        return true;
    }

    /** @return array ok, status (the tagged line), lines (untagged: each ['text' => ..., 'lits' => [literals]]) */
    public function cmd($command) {
        $tag = 'A' . ++$this->n;
        fwrite($this->fp, $tag . ' ' . $command . "\r\n");
        $lines = [];
        while (($raw = fgets($this->fp)) !== false) {
            $text = rtrim($raw, "\r\n");
            if (strpos($text, $tag . ' ') === 0) {
                return ['ok' => strpos($text, $tag . ' OK') === 0, 'status' => substr($text, strlen($tag) + 1), 'lines' => $lines];
            }
            $lits = [];
            while (preg_match('/\{(\d+)\}$/', $text, $m)) {   // a literal follows: its bytes, then the rest of the line
                $buf = '';
                $len = (int) $m[1];
                while (strlen($buf) < $len) {
                    $chunk = fread($this->fp, $len - strlen($buf));
                    if ($chunk === false || $chunk === '') break 2;
                    $buf .= $chunk;
                }
                $lits[] = $buf;
                $rest = fgets($this->fp);
                if ($rest === false) break 2;
                $text .= rtrim($rest, "\r\n");
            }
            $lines[] = ['text' => $text, 'lits' => $lits];
        }
        return ['ok' => false, 'status' => 'connection lost', 'lines' => $lines];
    }

    public function folders() {
        $r = $this->cmd('LIST "" "*"');
        $out = [];
        foreach ($r['lines'] as $l) {
            if (preg_match('/^\* LIST \(([^)]*)\) (?:"[^"]*"|NIL) (?:"((?:[^"\\\\]|\\\\.)*)"|(\S+))/', $l['text'], $m)) {
                $out[] = ['attrs' => $m[1], 'name' => stripcslashes($m[2] !== '' ? $m[2] : ($m[3] ?? ''))];
            }
        }
        return $out;
    }

    public function examine($folder) {
        return $this->cmd('EXAMINE ' . self::q($folder))['ok'];
    }

    /** @return int[] UIDs; $criteria is a ready IMAP search string */
    public function search($criteria) {
        $r = $this->cmd('UID SEARCH ' . $criteria);
        foreach ($r['lines'] as $l) {
            if (preg_match('/^\* SEARCH ?(.*)$/', $l['text'], $m)) return array_map('intval', array_filter(explode(' ', trim($m[1])), 'strlen'));
        }
        return [];
    }

    public static function quote($s) {
        return self::q($s);
    }

    /** @return array [message-id header, size] without the body */
    public function head($uid) {
        $r = $this->cmd('UID FETCH ' . (int) $uid . ' (RFC822.SIZE BODY.PEEK[HEADER.FIELDS (MESSAGE-ID)])');
        $size = 0; $head = '';
        foreach ($r['lines'] as $l) {
            if (preg_match('/RFC822\.SIZE (\d+)/', $l['text'], $m)) $size = (int) $m[1];
            if ($l['lits']) $head = $l['lits'][0];
        }
        return [ms_commission_mime_headers($head)['message-id'] ?? '', $size];
    }

    public function body($uid) {
        $r = $this->cmd('UID FETCH ' . (int) $uid . ' BODY.PEEK[]');
        foreach ($r['lines'] as $l) if ($l['lits']) return $l['lits'][0];
        return '';
    }

    public function close() {
        if ($this->fp) { @fwrite($this->fp, 'A' . ++$this->n . " LOGOUT\r\n"); @fclose($this->fp); $this->fp = null; }
    }
}

/**
 * ===========================
 * THE MAILBOXES
 * ===========================
 */

/** The addresses of the customers with a commission under way: their billing and account email */
function ms_commission_mail_customer_emails() {
    $orders = wc_get_orders([
        'limit' => 60, 'orderby' => 'date', 'order' => 'DESC',
        'status' => array_diff(array_keys(wc_get_order_statuses()), ['wc-cancelled', 'wc-refunded', 'wc-failed']),
        'meta_query' => [['key' => '_ms_commission_stage', 'compare' => 'EXISTS'], ['key' => '_ms_commission_stage', 'value' => 'done', 'compare' => '!=']],
    ]);
    $owners = ms_commission_mail_owner_addresses();
    $emails = [];
    foreach ($orders as $o) {
        if (!ms_commission_order_items($o)) continue;
        $user = $o->get_customer_id() ? get_userdata($o->get_customer_id()) : null;
        foreach ([$o->get_billing_email(), $user ? $user->user_email : ''] as $e) {
            $e = strtolower(trim((string) $e));
            if ($e !== '' && preg_match('/^[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}$/', $e) && !in_array($e, $owners, true)) $emails[$e] = true;
        }
    }
    return array_keys($emails);
}

/** INBOX and the Sent folder (its name differs between mail systems; a \Sent flag or a name ending in "Sent") */
function ms_commission_mail_default_folders($imap) {
    $folders = ['INBOX'];
    foreach ($imap->folders() as $f) {
        if (stripos($f['attrs'], '\\Sent') !== false || preg_match('/(^|[.\/])sent( items| mail| messages)?$/i', $f['name'])) { $folders[] = $f['name']; break; }
    }
    return $folders;
}

/** Messages from, to or copied to this address, or with it as Reply-To (the contact form), since the date given */
function ms_commission_mail_search($email, $since) {
    $e = MS_Commission_Imap::quote($email);
    return 'SINCE "' . gmdate('d-M-Y', strtotime($since)) . '" OR OR OR FROM ' . $e . ' TO ' . $e . ' CC ' . $e . ' HEADER Reply-To ' . $e;
}

function ms_commission_mail_poll() {
    if (!ms_commission_mail_configured()) return;
    if (get_transient('ms_commission_mail_lock')) return;
    set_transient('ms_commission_mail_lock', 1, 8 * MINUTE_IN_SECONDS);
    @set_time_limit(180);

    $customers = ms_commission_mail_customer_emails();
    $seen = get_option('ms_commission_mail_seen');
    $seen = is_array($seen) ? $seen : [];
    $backfilled = get_option('ms_commission_mail_backfilled');
    $backfilled = is_array($backfilled) ? $backfilled : [];
    $fetches = 0;
    $capped = false;
    $boxes = [];

    foreach (ms_commission_mail_mailboxes() as $box) {
        $mbid = substr(sha1($box['user']), 0, 8);
        $res = ['user' => $box['user'], 'ok' => false, 'error' => '', 'added' => 0];
        $imap = new MS_Commission_Imap();
        if (!$imap->open($box['host'] ?? 'mail.monospace.com', $box['user'], $box['pass'], !isset($box['validate_cert']) || $box['validate_cert'])) {
            $res['error'] = $imap->error;
            $boxes[] = $res;
            continue;
        }

        $all_ok = true;
        foreach ($box['folders'] ?? ms_commission_mail_default_folders($imap) as $folder) {
            if (!$imap->examine($folder)) { $all_ok = false; $res['error'] = 'no folder ' . $folder; continue; }
            foreach ($customers as $email) {
                $uids = $imap->search(ms_commission_mail_search($email, in_array($mbid . ':' . $email, $backfilled, true) ? '-3 days' : '-180 days'));
                foreach ($uids as $uid) {
                    list($mid, $size) = $imap->head($uid);
                    $skey = trim($mid) !== '' ? substr(sha1(trim($mid)), 0, 16) : $mbid . $folder . $uid;
                    if (isset($seen[$skey])) continue;
                    if ($fetches >= 40) { $capped = true; break 3; }   // the rest next time
                    if ($size > 0 && $size < 40 * MB_IN_BYTES) {
                        $raw = $imap->body($uid);
                        $fetches++;
                        if ($raw !== '' && ms_commission_mail_handle(ms_commission_mail_parse($raw, true)) === 'added') $res['added']++;
                    }
                    $seen[$skey] = 1;
                }
            }
        }
        $imap->close();
        $res['ok'] = $all_ok;
        if ($all_ok && !$capped) foreach ($customers as $email) $backfilled[] = $mbid . ':' . $email;
        $boxes[] = $res;
    }

    update_option('ms_commission_mail_seen', array_slice($seen, -3000, null, true), false);
    update_option('ms_commission_mail_backfilled', array_values(array_unique($backfilled)), false);
    $old = get_option('ms_commission_mail_status');
    $total = (int) (is_array($old) ? ($old['total'] ?? 0) : 0);
    foreach ($boxes as $b) $total += $b['added'];
    update_option('ms_commission_mail_status', ['time' => time(), 'boxes' => $boxes, 'total' => $total, 'more' => $capped], false);
    delete_transient('ms_commission_mail_lock');
}

/**
 * One customer's mail, including a finished commission (the poll only watches open ones): every message with this address in the mailboxes
 * since $since, handled like a polled one. $dry = report what would be added and add nothing. Run by hand (wp eval).
 *
 * @return string[] one line per message found
 */
function ms_commission_mail_import($email, $since = '-365 days', $dry = true) {
    $report = [];
    foreach (ms_commission_mail_mailboxes() as $box) {
        $imap = new MS_Commission_Imap();
        if (!$imap->open($box['host'] ?? 'mail.monospace.com', $box['user'], $box['pass'], !isset($box['validate_cert']) || $box['validate_cert'])) {
            $report[] = '[' . $box['user'] . '] ' . $imap->error;
            continue;
        }
        foreach ($box['folders'] ?? ms_commission_mail_default_folders($imap) as $folder) {
            if (!$imap->examine($folder)) { $report[] = '[' . $box['user'] . '] no folder ' . $folder; continue; }
            foreach ($imap->search(ms_commission_mail_search($email, $since)) as $uid) {
                list($mid, $size) = $imap->head($uid);
                if ($size <= 0 || $size >= 40 * MB_IN_BYTES) { $report[] = '[' . $box['user'] . ' ' . $folder . '] skipped (size ' . $size . ')'; continue; }
                $m = ms_commission_mail_parse($imap->body($uid), true);
                $report[] = sprintf('[%s %s] %s | %s | %s | %s', $box['user'], $folder, wp_date('Y-m-d H:i', $m['date']), mb_substr($m['subject'], 0, 50), ms_commission_mail_handle($m, $dry), mb_substr(str_replace("\n", ' / ', $m['text']), 0, 70));
            }
        }
        $imap->close();
    }
    return $report;
}

/** One line for the commission panel on the order screen */
function ms_commission_mail_status_line() {
    if (!ms_commission_mail_configured()) return 'Emails in the conversation: not connected (no mailbox login in wp-config.php yet).';
    $s = get_option('ms_commission_mail_status');
    if (!is_array($s) || empty($s['time'])) return 'Emails in the conversation: connected, not checked yet (every 10 minutes).';
    $parts = [];
    foreach ((array) ($s['boxes'] ?? []) as $b) $parts[] = $b['user'] . ($b['ok'] ? ' ok' : ' FAILED - ' . $b['error']);
    return 'Emails in the conversation: last checked ' . wp_date(get_option('date_format') . ', g:ia', $s['time']) . '; ' . (int) ($s['total'] ?? 0) . ' added so far. ' . implode('; ', $parts) . (!empty($s['more']) ? ' (more to read on the next check)' : '') . '.';
}

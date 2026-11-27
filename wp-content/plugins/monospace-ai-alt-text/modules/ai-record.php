<?php
/**
 * Record and transcribe (2026-11-26, owner: one button). Safari's own speech recognition goes deaf after the first go on
 * iPhone (WebKit bug 321436), so the browser only records plain audio (MediaRecorder) and a speech-to-text service turns
 * it into words. Used by the Market Terminal and Listing by voice.
 *
 * The service is OpenAI's transcription API; its key is the constant MONOSPACE_OPENAI_KEY in wp-config.php - never in the
 * database, the repo or a log. The audio passes through to the service and is not kept on the site.
 *
 *   ms_ai_record_ready()    is there a key?
 *   ms_ai_record_script()   prints window.msRecorder once (call before wp_footer or in an admin page), and window.msWait:
 *                           something visible moves from the tap until the answer (owner, 2026-11-26)
 *
 * @package monospace-ai-alt-text
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MS_AI_TRANSCRIBE_MODEL = 'gpt-transcribe';
const MS_AI_RECORD_MAX_BYTES = 8000000;

function ms_ai_record_key() {
    return defined( 'MONOSPACE_OPENAI_KEY' ) ? trim( (string) MONOSPACE_OPENAI_KEY ) : '';
}

function ms_ai_record_ready() {
    return ms_ai_record_key() !== '';
}

/** The words in an audio file, or a WP_Error whose message is plain words for the owner (the key is never in it) */
function ms_ai_transcribe( $path, $filename, $type ) {
    $key = ms_ai_record_key();
    if ( $key === '' ) return new WP_Error( 'ms_rec_nokey', 'No speech service key yet (MONOSPACE_OPENAI_KEY in wp-config.php).' );
    $audio = (string) file_get_contents( $path );
    if ( $audio === '' ) return new WP_Error( 'ms_rec_empty', 'The recording was empty.' );

    $boundary = 'ms' . wp_generate_password( 24, false );
    $body     = '';
    // No example numbers here: a model can "hear" a hint in a quiet recording
    foreach ( [ 'model' => MS_AI_TRANSCRIBE_MODEL, 'language' => 'en' ] as $name => $value ) {
        $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"$name\"\r\n\r\n$value\r\n";
    }
    $body .= "--$boundary\r\nContent-Disposition: form-data; name=\"file\"; filename=\"$filename\"\r\nContent-Type: $type\r\n\r\n" . $audio . "\r\n--$boundary--\r\n";

    $response = wp_remote_post( 'https://api.openai.com/v1/audio/transcriptions', [
        'timeout' => 60,
        'headers' => [ 'Authorization' => 'Bearer ' . $key, 'Content-Type' => "multipart/form-data; boundary=$boundary" ],
        'body'    => $body,
    ] );
    if ( is_wp_error( $response ) ) return new WP_Error( 'ms_rec_net', 'Could not reach the speech service.' );
    $code = (int) wp_remote_retrieve_response_code( $response );
    $data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    if ( $code !== 200 ) {
        $why = (string) ( $data['error']['code'] ?? '' );
        if ( $why === 'credit_balance_exhausted' || $why === 'insufficient_quota' ) $msg = 'The speech service account is out of credit (platform.openai.com, billing).';
        elseif ( $code === 401 ) $msg = 'The speech service refused the key (wp-config.php).';
        elseif ( $code === 429 ) $msg = 'Too many requests right now. Try again in a minute.';
        else $msg = 'The speech service said no (' . $code . ').';
        return new WP_Error( 'ms_rec_http', $msg );
    }
    return trim( sanitize_textarea_field( (string) ( $data['text'] ?? '' ) ) );
}

add_action( 'wp_ajax_ms_ai_transcribe', function () {
    check_ajax_referer( 'ms_ai_record' );
    if ( ! current_user_can( 'edit_products' ) ) wp_send_json_error( 'Not allowed.' );
    $file = $_FILES['audio'] ?? null;
    if ( ! $file || (int) $file['error'] !== UPLOAD_ERR_OK || ! is_uploaded_file( $file['tmp_name'] ) ) wp_send_json_error( 'The recording did not arrive.' );
    if ( (int) $file['size'] > MS_AI_RECORD_MAX_BYTES ) wp_send_json_error( 'That recording is too long.' );
    // What the browser records: mp4 (Safari), webm or ogg (others)
    $type = strtolower( strtok( (string) $file['type'], ';' ) );
    $ext  = [ 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/aac' => 'm4a', 'video/mp4' => 'm4a', 'audio/webm' => 'webm', 'video/webm' => 'webm', 'audio/ogg' => 'ogg', 'audio/wav' => 'wav' ][ $type ] ?? '';
    if ( $ext === '' ) wp_send_json_error( 'This kind of recording cannot be used.' );

    $text = ms_ai_transcribe( $file['tmp_name'], "said.$ext", $type );
    if ( is_wp_error( $text ) ) wp_send_json_error( $text->get_error_message() );
    wp_send_json_success( [ 'text' => $text ] );
} );

/** window.msRecorder: start(), stop() => Promise of the words ('' = nothing heard), recording() */
function ms_ai_record_script() {
    static $done = false;
    if ( $done ) return;
    $done = true;
    ?>
    <style>
        .ms-spin { display: inline-block; flex: none; width: 1em; height: 1em; margin-right: .5em; vertical-align: -.15em; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: ms-spin .7s linear infinite; }
        @keyframes ms-spin { to { transform: rotate(360deg); } }
        .ms-dot { display: inline-block; flex: none; width: 12px; height: 12px; margin-right: 8px; border-radius: 50%; background: currentColor; animation: ms-blink 1s ease-in-out infinite; }
        @keyframes ms-blink { 50% { opacity: .2; } }
        .ms-live { animation: ms-ring 1.4s ease-out infinite; }
        @keyframes ms-ring { 0% { box-shadow: 0 0 0 0 rgba(255,59,48,.7); } 100% { box-shadow: 0 0 0 16px rgba(255,59,48,0); } }
        .ms-press:active, .ms-press-all button:active, .ms-press-all a[role="button"]:active { transform: scale(.96) !important; filter: brightness(.85); }
        .ms-waiting { pointer-events: none; }
        #ms-wait-bar { position: fixed; z-index: 2147483000; top: env(safe-area-inset-top, 0); left: 0; right: 0; height: 4px; overflow: hidden; background: rgba(0,122,255,.25); }
        #ms-wait-bar::after { content: ""; position: absolute; top: 0; bottom: 0; width: 40%; background: #0a84ff; animation: ms-slide 1.1s ease-in-out infinite; }
        @keyframes ms-slide { from { left: -40%; } to { left: 100%; } }
        @media (prefers-reduced-motion: reduce) { .ms-spin, .ms-dot, .ms-live, #ms-wait-bar::after { animation-duration: 2.5s; } }
    </style>
    <script>
    (function () {
        // iPhone only shows a pressed state (:active) when something listens for touches
        document.addEventListener('touchstart', function () {}, { passive: true });
        window.msWait = {
            // The bar across the top of the screen, while anything is on its way
            bar: function (on) {
                var bar = document.getElementById('ms-wait-bar');
                if (on && !bar) { bar = document.createElement('div'); bar.id = 'ms-wait-bar'; bar.setAttribute('aria-hidden', 'true'); document.body.appendChild(bar); }
                else if (!on && bar) bar.remove();
            },
            // A spinner (or the recording dot) in front of a button's words
            mark: function (el, kind) {
                var old = el.querySelector(':scope > .ms-spin, :scope > .ms-dot');
                if (old) old.remove();
                el.classList.toggle('ms-waiting', kind === 'ms-spin');
                if (!kind) return;
                var i = document.createElement('span');
                i.className = kind;
                i.setAttribute('aria-hidden', 'true');
                el.insertBefore(i, el.firstChild);
            },
            // 0:07, counting while recording
            clock: function (tick) {
                var start = Date.now();
                function show() { var n = Math.floor((Date.now() - start) / 1000); tick(Math.floor(n / 60) + ':' + ('0' + n % 60).slice(-2)); }
                show();
                var id = setInterval(show, 250);
                return function () { clearInterval(id); };
            }
        };
    })();
    </script>
    <script>
    (function () {
        var cfg = <?php echo wp_json_encode( [ 'url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'ms_ai_record' ) ] ); ?>;
        var recorder = null, stream = null, chunks = [], timer = null, starting = false;

        function release() {
            clearTimeout(timer);
            if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
        // A quiet recording is never sent: a speech model can make up words from silence
        function quiet(blob) {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return Promise.resolve(false);
            var ctx = new Ctx();
            return blob.arrayBuffer()
                .then(function (buf) { return new Promise(function (ok, no) { ctx.decodeAudioData(buf, ok, no); }); })
                .then(function (audio) {
                    var data = audio.getChannelData(0), peak = 0;
                    for (var i = 0; i < data.length; i += 10) peak = Math.max(peak, Math.abs(data[i]));
                    return audio.duration < 0.6 || peak < 0.02;
                })
                .catch(function () { return false; })
                .then(function (q) { ctx.close(); return q; });
        }
        function transcribe(blob) {
            var fd = new FormData();
            fd.append('action', 'ms_ai_transcribe');
            fd.append('_ajax_nonce', cfg.nonce);
            fd.append('audio', blob, 'said');
            return fetch(cfg.url, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(function (r) { if (!r.ok) throw new Error('Request failed (' + r.status + ').'); return r.json(); })
                .then(function (r) { if (!r || !r.success) throw new Error((r && r.data) || 'Something went wrong.'); return r.data.text; });
        }

        window.msRecorder = {
            supported: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.MediaRecorder),
            recording: function () { return !!recorder; },
            // tooLong is called if nobody taps Stop within two minutes
            start: function (tooLong) {
                if (recorder || starting) return Promise.reject(new Error('Already recording.'));
                starting = true;
                return navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
                    stream = s;
                    chunks = [];
                    recorder = new MediaRecorder(s);
                    recorder.ondataavailable = function (e) { if (e.data.size) chunks.push(e.data); };
                    recorder.start();
                    timer = setTimeout(tooLong, 120000);
                    starting = false;
                }).catch(function (e) {
                    starting = false;
                    recorder = null;
                    release();
                    throw new Error(e && e.name === 'NotAllowedError' ? 'The microphone is not allowed for this site (Safari’s website settings).' : 'The microphone could not start.');
                });
            },
            stop: function () {
                var r = recorder;
                if (!r) return Promise.resolve('');
                recorder = null;
                return new Promise(function (ok) {
                    r.onstop = function () { ok(new Blob(chunks, { type: r.mimeType || 'audio/mp4' })); };
                    r.stop();
                }).then(function (blob) {
                    release(); // let go of the microphone
                    return quiet(blob).then(function (q) { return q || !blob.size ? '' : transcribe(blob); });
                });
            }
        };
        window.addEventListener('pagehide', function () { if (recorder) { try { recorder.stop(); } catch (e) {} recorder = null; } release(); });
    })();
    </script>
    <?php
}

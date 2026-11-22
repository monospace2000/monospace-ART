<?php
/**
 * Slideshow (2026-10-28)
 *
 * A full-screen slideshow of the selected artworks: fade / push / wipe, Ken Burns, image and transition speed,
 * an optional title screen, landscape and portrait screens, no sound. "Play" renders it straight from the form;
 * "Get link" stores the selection + settings (like the public sheet links) and serves them at
 * /artwork-slideshows/{code}/. The viewer is one self-contained page (no theme, no libraries).
 *
 * @package monospace-art-theme
 */

class Monospace_Slideshow {
    private static $instance = null;
    private $option_prefix = 'msp_show_';
    private $expiration = 90 * DAY_IN_SECONDS; // like the sheet links

    public static function instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        $this->register_endpoint();
        add_action('parse_request', [$this, 'handle_public_request']);
        add_action('wp_ajax_generate_slideshow_link', [$this, 'ajax_generate_link']);
    }

    private function register_endpoint() {
        add_rewrite_rule(
            '^artwork-slideshows/([a-zA-Z0-9]+)/?$',
            'index.php?artwork_slideshow_code=$matches[1]',
            'top'
        );
        add_rewrite_tag('%artwork_slideshow_code%', '([a-zA-Z0-9]+)');

        // One-time flush after adding the endpoint
        if (!get_option('msp_slideshow_links_flushed_v1')) {
            flush_rewrite_rules();
            update_option('msp_slideshow_links_flushed_v1', true);
        }
    }

    /** The slideshow settings from a request (the form's show_* fields), cleaned up. */
    public static function options_from($src) {
        $src = wp_unslash($src);
        $num = function ($key, $default, $min, $max) use ($src) {
            $v = (isset($src[$key]) && is_numeric($src[$key])) ? (float) $src[$key] : $default;
            return max($min, min($max, $v));
        };
        $transition = isset($src['show_transition']) ? sanitize_key($src['show_transition']) : 'fade';

        return [
            'transition' => in_array($transition, ['fade', 'push', 'wipe'], true) ? $transition : 'fade',
            'seconds' => $num('show_seconds', 5, 1, 60),
            'trans_seconds' => $num('show_trans_seconds', 1, 0.2, 5),
            'kenburns' => !empty($src['show_kenburns']),
            'fit' => (isset($src['show_fit']) && $src['show_fit'] === 'cover') ? 'cover' : 'contain',
            'captions' => !empty($src['show_captions']),
            'loop' => !empty($src['show_loop']),
            'qr' => Monospace_Product_Data::qr_mode($src['show_qr'] ?? ''),
            'shuffle' => !empty($src['show_shuffle']),
            'title_screen' => !empty($src['show_title_screen']),
            'title' => isset($src['show_title']) ? mb_substr(sanitize_text_field($src['show_title']), 0, 120) : '',
            'subtitle' => isset($src['show_subtitle']) ? mb_substr(sanitize_text_field($src['show_subtitle']), 0, 160) : '',
            'title_seconds' => $num('show_title_seconds', 4, 1, 30),
        ];
    }

    /** "Play" in the form: rendered straight away, nothing stored. Called from admin_init. */
    public function handle_play() {
        $ids = isset($_POST['product_ids']) ? array_map('intval', (array) $_POST['product_ids']) : [];
        if (empty($ids)) {
            wp_die('No products selected');
        }
        $this->render($ids, self::options_from($_POST));
        exit;
    }

    public function ajax_generate_link() {
        check_ajax_referer('monospace_artwork_sheet', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Insufficient permissions');
        }

        $ids = isset($_POST['product_ids']) ? array_map('intval', (array) $_POST['product_ids']) : [];
        if (empty($ids)) {
            wp_send_json_error('No products selected');
        }

        $code = $this->generate_code();
        update_option($this->option_prefix . $code, [
            'product_ids' => $ids,
            'options' => self::options_from($_POST),
            'created' => time(),
            'expires' => time() + $this->expiration,
            'created_by' => get_current_user_id(),
        ], false); // false = don't autoload

        wp_send_json_success([
            'code' => $code,
            'url' => home_url('/artwork-slideshows/' . $code . '/'),
            'expires' => date_i18n('F j, Y', time() + $this->expiration),
        ]);
    }

    public function handle_public_request($wp) {
        if (empty($wp->query_vars['artwork_slideshow_code'])) {
            return;
        }

        $code = preg_replace('/[^a-zA-Z0-9]/', '', $wp->query_vars['artwork_slideshow_code']);
        $key = $this->option_prefix . $code;
        $data = get_option($key, false);

        if ($data && isset($data['expires']) && $data['expires'] < time()) {
            delete_option($key);
            $data = false;
        }
        if (!$data) {
            wp_die('This slideshow link has expired or is invalid.', 'Slideshow not found', ['response' => 404]);
        }

        $this->render($data['product_ids'], $data['options']);
        exit;
    }

    private function generate_code($length = 8) {
        $chars = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }

    /** The slides: an optional title screen, then one per artwork that has a picture. */
    private function build_slides($product_ids, $o) {
        $slides = [];
        if ($o['title_screen'] && ($o['title'] !== '' || $o['subtitle'] !== '')) {
            $slides[] = ['k' => 't', 'title' => $o['title'], 'sub' => $o['subtitle']];
        }
        foreach (array_slice($product_ids, 0, 500) as $id) {
            $product = wc_get_product($id);
            if (!$product || !$product->get_image_id()) {
                continue;
            }
            $img = wp_get_attachment_image_src($product->get_image_id(), 'full');
            if (!$img) {
                continue;
            }
            $slide = ['k' => 'i', 'src' => $img[0], 'w' => (int) $img[1], 'h' => (int) $img[2], 'title' => $product->get_name()];
            if (!empty($o['qr']) && function_exists('monospace_artwork_qr_svg')) {
                $svg = monospace_artwork_qr_svg($id, $o['qr']);
                if ($svg !== '') {
                    $slide['qr'] = $svg;
                    $slide['qrc'] = monospace_artwork_qr_caption($id, $o['qr']);
                }
            }
            $slides[] = $slide;
        }
        return $slides;
    }

    /**
     * The site's logo and tagline for the screen before the start (2026-10-28): the theme's own two logo parts, laid
     * out as Look > Logo says (one line or stacked, ART's size and place, apart for computers and phones), in white on
     * the black page, with the tagline under it. '' when the theme's logo code isn't there (then the name is shown).
     */
    private function brand_html() {
        if (!function_exists('monospace_logo_part_url') || !function_exists('monospace_logo_settings')) {
            return '';
        }
        $word = monospace_logo_part_url('monospace');
        $art = monospace_logo_part_url('art');
        if (!$word || !$art) {
            return '';
        }
        $class = 'ms-logo';
        if (monospace_logo_settings('wide')['stack']) $class .= ' is-stacked-wide';
        if (monospace_logo_settings('phone')['stack']) $class .= ' is-stacked';
        $style = function_exists('monospace_logo_art_style') ? monospace_logo_art_style() : '';

        $out = '<div class="brand"><span class="' . $class . '"' . ($style ? ' style="' . esc_attr($style) . '"' : '') . ' role="img" aria-label="monospace ART">'
            . '<img class="ms-logo-word" src="' . esc_url($word) . '" alt="" width="1366" height="179">'
            . '<img class="ms-logo-art" src="' . esc_url($art) . '" alt="" width="948" height="540">'
            . '</span>';

        if (function_exists('monospace_tagline_lines') && function_exists('monospace_tagline_svg')) {
            $wide = [monospace_tagline_lines('wide'), monospace_tagline_align('wide')];
            $phone = [monospace_tagline_lines('phone'), monospace_tagline_align('phone')];
            $out .= $wide === $phone
                ? monospace_tagline_svg($wide[0], $wide[1])
                : monospace_tagline_svg($wide[0], $wide[1], 'site-tagline--wide') . monospace_tagline_svg($phone[0], $phone[1], 'site-tagline--phone');
        }
        return $out . '</div>';
    }

    public function render($product_ids, $o) {
        $slides = $this->build_slides($product_ids, $o);
        $artworks = count(array_filter($slides, function ($s) { return $s['k'] === 'i'; }));
        $name = $o['title'] !== '' ? $o['title'] : get_option('monospace_sheet_artist_name', 'Hens Breet');
        $brand = $this->brand_html();
        $config = [
            'slides' => $slides,
            'transition' => $o['transition'],
            'hold' => $o['seconds'],
            'trans' => $o['trans_seconds'],
            'titleHold' => $o['title_seconds'],
            'kenburns' => $o['kenburns'],
            'fit' => $o['fit'],
            'captions' => $o['captions'],
            'loop' => $o['loop'],
            'shuffle' => !empty($o['shuffle']),
        ];

        nocache_headers();
        header('X-Robots-Tag: noindex, nofollow');
        header('Content-Type: text/html; charset=utf-8');
        ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#000000">
<title><?php echo esc_html($name); ?> – Slideshow</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Courier+Prime&amp;family=Oswald:wght@300;400&amp;family=Roboto:wght@200&amp;display=swap">
<style>
*{box-sizing:border-box}
html,body{margin:0;height:100%;background:#000;color:#fff;overflow:hidden;-webkit-tap-highlight-color:transparent}
body{font-family:"Courier Prime",ui-monospace,Menlo,monospace;height:100vh;height:100dvh}
#stage{position:fixed;inset:0;overflow:hidden;background:#000}
.slide{position:absolute;inset:0;overflow:hidden;background:#000;will-change:transform,opacity,clip-path}
.slide img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;transform-origin:center;user-select:none;-webkit-user-drag:none}
.fit-cover .slide img{object-fit:cover}
.slide.title{display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:8vmin}
.slide.title h1{margin:0;font:300 clamp(30px,7vmin,84px)/1.1 "Oswald","Helvetica Neue",Arial,sans-serif;letter-spacing:.06em;text-transform:uppercase}
.slide.title p{margin:3vmin 0 0;font-size:clamp(14px,2.6vmin,28px);opacity:.7;letter-spacing:.04em}
.qr{position:absolute;right:3vmin;bottom:3vmin;z-index:2;background:#fff;color:#000;border-radius:1.2vmin;padding:1.4vmin 1.4vmin .9vmin;text-align:center;font-size:clamp(10px,1.7vmin,17px);letter-spacing:.03em}
.qr svg{display:block;width:min(22vmin,190px);height:auto}
.cap{position:absolute;left:0;right:0;bottom:0;padding:5vmin 4vmin calc(3vmin + env(safe-area-inset-bottom));font-size:clamp(12px,2.2vmin,20px);letter-spacing:.03em;text-shadow:0 1px 6px #000,0 0 2px #000;background:linear-gradient(transparent,rgba(0,0,0,.55))}
#start{position:fixed;inset:0;z-index:5;display:flex;align-items:center;justify-content:center;text-align:center;background:#000;padding:6vmin}
#start[hidden],#bar[hidden]{display:none}
#start>div{width:100%;max-width:640px}
.brand{width:min(78vw,560px);margin:0 auto 5vmin}
.brand .ms-logo{position:relative;display:block;width:100%;aspect-ratio:1437.76/202.31;margin:0 0 1.8%}
.brand .ms-logo img{position:absolute;height:auto;margin:0;filter:invert(1);user-select:none}
.brand .ms-logo-word{left:0;bottom:0;width:74.63%}
.brand .ms-logo-art{left:77.69%;top:0;width:22.33%}
.brand .site-tagline{display:block;width:100%;height:auto;overflow:visible}
.brand .site-tagline text{font-family:Roboto,sans-serif;font-weight:200;fill:#fff}
.brand .site-tagline--phone{display:none}
@media (min-width:641px){.brand .ms-logo.is-stacked-wide{display:flex;flex-direction:column;align-items:center;gap:.6rem;aspect-ratio:auto}
.brand .ms-logo.is-stacked-wide img{position:static}.brand .ms-logo.is-stacked-wide .ms-logo-word{width:100%}
.brand .ms-logo.is-stacked-wide .ms-logo-art{width:var(--ms-art-w-wide,100%);align-self:var(--ms-art-align-wide,center)}}
@media (max-width:640px){.brand .site-tagline--wide{display:none}.brand .site-tagline--phone{display:block}
.brand .ms-logo.is-stacked{display:flex;flex-direction:column;align-items:center;gap:.6rem;aspect-ratio:auto}
.brand .ms-logo.is-stacked img{position:static}.brand .ms-logo.is-stacked .ms-logo-word{width:100%}
.brand .ms-logo.is-stacked .ms-logo-art{width:var(--ms-art-w-phone,100%);align-self:var(--ms-art-align-phone,center)}}
#start h1{margin:0 0 2vmin;font:300 clamp(26px,6vmin,64px)/1.1 "Oswald","Helvetica Neue",Arial,sans-serif;letter-spacing:.06em;text-transform:uppercase}
#start p{margin:0 0 5vmin;opacity:.6;font-size:clamp(13px,2.2vmin,18px)}
.btns{display:flex;flex-wrap:wrap;gap:12px;justify-content:center}
button{font:inherit;color:#fff;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.35);border-radius:999px;padding:12px 26px;cursor:pointer;letter-spacing:.04em}
button:hover,button:focus-visible{background:rgba(255,255,255,.24);outline:none}
button.main{background:#fff;color:#000;border-color:#fff}
button.main:hover,button.main:focus-visible{background:#e8e8e8}
#bar{position:fixed;left:50%;bottom:calc(14px + env(safe-area-inset-bottom));transform:translateX(-50%);z-index:4;display:flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:rgba(0,0,0,.6);-webkit-backdrop-filter:blur(8px);backdrop-filter:blur(8px);transition:opacity .35s}
#bar button{border:0;background:none;padding:8px 12px;font-size:18px;line-height:1;border-radius:999px}
#bar button:hover,#bar button:focus-visible{background:rgba(255,255,255,.18)}
#count{min-width:64px;text-align:center;font-size:13px;opacity:.75}
body.idle #bar{opacity:0;pointer-events:none}
body.idle.running{cursor:none}
</style>
</head>
<body class="fit-<?php echo esc_attr($o['fit']); ?>">
<div id="stage"></div>

<div id="start">
    <div>
        <?php echo $brand !== '' ? $brand : '<h1>' . esc_html($name) . '</h1>'; // WPCS: the theme's own markup ?>
        <p id="start-note"><?php echo (int) $artworks; ?> artwork<?php echo $artworks === 1 ? '' : 's'; ?></p>
        <div class="btns">
            <button type="button" class="main" id="go-fs">Play full screen</button>
            <button type="button" id="go">Play</button>
        </div>
    </div>
</div>

<div id="bar" hidden>
    <button type="button" id="prev" aria-label="Previous">&#8249;</button>
    <button type="button" id="pp" aria-label="Pause">&#10074;&#10074;</button>
    <button type="button" id="next" aria-label="Next">&#8250;</button>
    <span id="count"></span>
    <button type="button" id="fs" aria-label="Full screen">&#9974;</button>
</div>

<script>
(function () {
    var C = <?php echo wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var slides = C.slides, total = slides.length;
    var $ = function (id) { return document.getElementById(id); };
    var stage = $('stage'), startBox = $('start'), bar = $('bar'), pp = $('pp'), count = $('count');
    var reduced = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var kenburns = C.kenburns && !reduced;
    var T = C.trans * 1000;
    var doc = document.documentElement;
    var canFs = !!(doc.requestFullscreen || doc.webkitRequestFullscreen);

    var index = -1, cur = null, anims = [], playing = false, busy = false;
    var timer = null, due = 0, remaining = 0, zoomIn = true;

    if (!total) { $('start-note').textContent = 'There is nothing to show yet.'; $('go').hidden = $('go-fs').hidden = true; return; }
    if (!canFs) { $('go-fs').hidden = true; $('fs').hidden = true; $('go').className = 'main'; $('go').textContent = 'Play'; }

    // Shuffle: the artworks in random order (a title screen stays first); reshuffled each time round, never the same one twice running
    var firstArt = slides.length && slides[0].k === 't' ? 1 : 0;
    function shuffle(avoid) {
        var art = slides.slice(firstArt);
        for (var j = art.length - 1; j > 0; j--) { var r = Math.floor(Math.random() * (j + 1)), t = art[j]; art[j] = art[r]; art[r] = t; }
        if (avoid && art.length > 1 && art[0] === avoid) { var k = 1 + Math.floor(Math.random() * (art.length - 1)); art[0] = art[k]; art[k] = avoid; }
        slides = slides.slice(0, firstArt).concat(art);
    }
    if (C.shuffle) shuffle();

    function vertical() { return innerHeight > innerWidth; }
    function holdFor(s) { return (s.k === 't' ? C.titleHold : C.hold) * 1000; }

    function build(s) {
        var el = document.createElement('div');
        el.className = 'slide' + (s.k === 't' ? ' title' : '');
        if (s.k === 't') {
            if (s.title) { var h = document.createElement('h1'); h.textContent = s.title; el.appendChild(h); }
            if (s.sub) { var p = document.createElement('p'); p.textContent = s.sub; el.appendChild(p); }
        } else {
            var img = new Image();
            img.alt = s.title || '';
            img.draggable = false;
            img.src = s.src;
            el.appendChild(img);
            if (s.qr) { var q = document.createElement('div'); q.className = 'qr'; q.innerHTML = s.qr; var qc = document.createElement('span'); qc.textContent = s.qrc || ''; q.appendChild(qc); el.appendChild(q); }
            if (C.captions && s.title) { var c = document.createElement('div'); c.className = 'cap'; c.textContent = s.title; el.appendChild(c); }
        }
        return el;
    }

    function ready(el) {
        var img = el.querySelector('img');
        if (!img || img.complete) return Promise.resolve();
        return new Promise(function (ok) { img.onload = img.onerror = ok; setTimeout(ok, 8000); });
    }

    function preload(n) {
        var s = slides[(n + total) % total];
        if (s && s.k === 'i') new Image().src = s.src;
    }

    function track(a) { if (a) anims.push(a); return a; }

    function kb(el, s) {
        var img = el.querySelector('img');
        if (!img || !kenburns) return;
        var span = holdFor(s) + T * 2, z = 1.14;
        var dx = (Math.random() * 5 - 2.5).toFixed(2), dy = (Math.random() * 5 - 2.5).toFixed(2);
        var from = 'scale(1) translate(0,0)', to = 'scale(' + z + ') translate(' + dx + '%,' + dy + '%)';
        var a = img.animate(zoomIn ? [{ transform: from }, { transform: to }] : [{ transform: to }, { transform: from }],
            { duration: span, easing: 'linear', fill: 'both' });
        zoomIn = !zoomIn;
        if (!playing) a.pause();
        track(a);
    }

    // Moves from the slide on show to the next one; resolves when it's done
    function transition(prev, next, dir) {
        var v = vertical(), ease = 'cubic-bezier(.65,0,.35,1)', list = [];
        if (!prev || T < 1) return Promise.resolve([]);
        if (C.transition === 'push') {
            var a = v ? 'translateY' : 'translateX';
            list.push(next.animate([{ transform: a + '(' + 100 * dir + '%)' }, { transform: a + '(0)' }], { duration: T, easing: ease, fill: 'both' }));
            list.push(prev.animate([{ transform: a + '(0)' }, { transform: a + '(' + -100 * dir + '%)' }], { duration: T, easing: ease, fill: 'both' }));
        } else if (C.transition === 'wipe') {
            var hide = v ? (dir > 0 ? 'inset(0 0 100% 0)' : 'inset(100% 0 0 0)') : (dir > 0 ? 'inset(0 100% 0 0)' : 'inset(0 0 0 100%)');
            list.push(next.animate([{ clipPath: hide }, { clipPath: 'inset(0)' }], { duration: T, easing: ease, fill: 'both' }));
        } else {
            list.push(next.animate([{ opacity: 0 }, { opacity: 1 }], { duration: T, easing: 'ease-in-out', fill: 'both' }));
        }
        return Promise.all(list.map(function (x) { return x.finished.catch(function () {}); })).then(function () { list.forEach(function (x) { x.cancel(); }); });
    }

    function schedule(ms) {
        clearTimeout(timer);
        remaining = ms; due = performance.now() + ms;
        if (playing) timer = setTimeout(function () { show(index + 1, 1, true); }, ms);
    }

    function show(n, dir, auto) {
        if (busy) return;
        if (n >= total || n < 0) {
            if (!C.loop && auto) return finish();
            if (n >= total && C.shuffle) shuffle(slides[total - 1]);
            n = (n + total) % total;
        }
        busy = true; clearTimeout(timer);
        var s = slides[n], el = build(s), prev = cur;
        ready(el).then(function () {
            stage.appendChild(el);
            if (!prev && T) el.animate([{ opacity: 0 }, { opacity: 1 }], { duration: Math.min(T, 1200), fill: 'none' });
            var old = anims; anims = [];
            kb(el, s);
            return transition(prev, el, dir).then(function () {
                if (prev) prev.remove();
                old.forEach(function (a) { a.cancel(); });
                cur = el; index = n; busy = false;
                count.textContent = (n + 1) + ' / ' + total;
                preload(n + 1);
                schedule(holdFor(s));
            });
        });
    }

    function play() { if (playing) return; playing = true; document.body.classList.add('running'); pp.innerHTML = '&#10074;&#10074;'; pp.setAttribute('aria-label', 'Pause'); anims.forEach(function (a) { a.play(); }); if (!busy && cur) schedule(Math.max(remaining, 400)); }
    function pause() { if (!playing) return; playing = false; document.body.classList.remove('running'); pp.innerHTML = '&#9654;'; pp.setAttribute('aria-label', 'Play'); clearTimeout(timer); remaining = Math.max(due - performance.now(), 400); anims.forEach(function (a) { a.pause(); }); }
    function toggle() { playing ? pause() : play(); }

    function start(fs) {
        if (fs) fullscreen(true);
        startBox.hidden = true; bar.hidden = false;
        playing = true; document.body.classList.add('running');
        awake();
        show(0, 1, false);
    }

    function finish() {
        pause();
        $('start-note').textContent = 'The end';
        $('go').hidden = true;
        $('go-fs').textContent = 'Play again'; $('go-fs').hidden = false;
        $('go-fs').onclick = function () { startBox.hidden = true; bar.hidden = false; awake(); if (cur) { cur.remove(); cur = null; } show(0, 1, false); playing = true; document.body.classList.add('running'); };
        startBox.hidden = false; bar.hidden = true;
    }

    function fullscreen(on) {
        var isFs = document.fullscreenElement || document.webkitFullscreenElement;
        if (on === undefined) on = !isFs;
        try {
            if (on && !isFs) (doc.requestFullscreen || doc.webkitRequestFullscreen).call(doc);
            else if (!on && isFs) (document.exitFullscreen || document.webkitExitFullscreen).call(document);
        } catch (e) {}
    }

    // Controls fade away while it plays
    var idleTimer = null;
    function awake() {
        document.body.classList.remove('idle');
        clearTimeout(idleTimer);
        idleTimer = setTimeout(function () { document.body.classList.add('idle'); }, 2800);
    }
    ['mousemove', 'touchstart', 'keydown'].forEach(function (ev) { document.addEventListener(ev, awake, { passive: true }); });

    $('go-fs').onclick = function () { start(true); };
    $('go').onclick = function () { start(false); };
    $('prev').onclick = function () { show(index - 1, -1); };
    $('next').onclick = function () { show(index + 1, 1); };
    pp.onclick = toggle;
    $('fs').onclick = function () { fullscreen(); };
    stage.addEventListener('dblclick', function () { fullscreen(); });

    document.addEventListener('keydown', function (e) {
        if (startBox.hidden === false) return;
        if (e.key === ' ' || e.key === 'k') { e.preventDefault(); toggle(); }
        else if (e.key === 'ArrowRight') show(index + 1, 1);
        else if (e.key === 'ArrowLeft') show(index - 1, -1);
        else if (e.key === 'f' || e.key === 'F') fullscreen();
    });

    // Swipe on touch screens
    var tx = null;
    stage.addEventListener('touchstart', function (e) { tx = e.touches[0].clientX; }, { passive: true });
    stage.addEventListener('touchend', function (e) {
        if (tx === null) return;
        var d = e.changedTouches[0].clientX - tx; tx = null;
        if (Math.abs(d) > 50) show(index + (d < 0 ? 1 : -1), d < 0 ? 1 : -1);
    }, { passive: true });

    preload(0);
})();
</script>
</body>
</html>
        <?php
    }
}

// Initialize
Monospace_Slideshow::instance();

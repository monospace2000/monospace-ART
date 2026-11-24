<?php
/**
 * Cookie banner (Kit part since 2026-11-23; the art site's monospace-privacy banner made general). Opt-in: nothing from Google counts a
 * visitor until they press Accept. Goes with the analytics part: while this part is on, the analytics part prints Google consent
 * mode "denied" first in <head>, and this banner switches it to "granted" after Accept (also on later visits, from the stored choice).
 *
 * The choice is kept in the browser (localStorage "cookieConsent" = accepted | rejected, the same key as the art site's), so cached
 * pages work. Reject (or no answer) keeps everything denied. A link to the privacy policy shows when the site has one set
 * (Settings > Privacy). "Cookie settings" anywhere on the site: a link to #cookie-settings opens the banner again.
 * Look: a dark glass sheet at the bottom, finger-sized buttons, the iPhone's safe areas respected (design rules).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// The stored choice, applied before any Google tag (priority 1: after the consent default at 0)
add_action( 'wp_head', function () {
	echo "<script>(function(){var c=null;try{c=localStorage.getItem('cookieConsent')}catch(e){}if(c==='accepted'&&typeof gtag==='function')"
		. "gtag('consent','update',{analytics_storage:'granted',ad_storage:'granted',ad_user_data:'granted',ad_personalization:'granted'});})();</script>\n";
}, 1 );

add_action( 'wp_footer', function () {
	$policy = get_privacy_policy_url();
	?>
	<div id="ms-cookie" role="dialog" aria-live="polite" aria-label="Cookies" hidden>
		<p>This site counts visits with Google Analytics, using cookies. Is that all right?<?php if ( $policy ) : ?> <a href="<?php echo esc_url( $policy ); ?>">Privacy policy</a><?php endif; ?></p>
		<div class="ms-cookie-buttons">
			<a href="#" role="button" data-choice="rejected">No thanks</a>
			<a href="#" role="button" data-choice="accepted" class="is-yes">Accept</a>
		</div>
	</div>
	<style>
		#ms-cookie{position:fixed;z-index:99990;left:max(12px,env(safe-area-inset-left));right:max(12px,env(safe-area-inset-right));bottom:max(12px,env(safe-area-inset-bottom));
			max-width:560px;margin:0 auto;padding:16px 18px;border-radius:18px;color:#f2f2f2;
			background:rgba(22,22,24,.72);-webkit-backdrop-filter:blur(18px) saturate(1.4);backdrop-filter:blur(18px) saturate(1.4);
			border:1px solid rgba(255,255,255,.12);box-shadow:0 18px 50px rgba(0,0,0,.45);
			font:15px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",system-ui,sans-serif;
			transform:translateY(0);opacity:1;transition:transform .35s cubic-bezier(.2,.9,.3,1),opacity .35s}
		#ms-cookie[hidden]{display:block;transform:translateY(130%);opacity:0;pointer-events:none}
		#ms-cookie p{margin:0 0 12px}
		#ms-cookie p a{color:inherit;text-decoration:underline;text-underline-offset:2px}
		#ms-cookie .ms-cookie-buttons{display:flex;gap:10px;justify-content:flex-end}
		#ms-cookie .ms-cookie-buttons a{display:inline-flex;align-items:center;justify-content:center;min-height:44px;min-width:110px;padding:0 18px;border-radius:12px;
			text-decoration:none;font-weight:600;color:#f2f2f2;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.16)}
		#ms-cookie .ms-cookie-buttons a.is-yes{background:#f2f2f2;color:#111;border-color:#f2f2f2}
		#ms-cookie .ms-cookie-buttons a:focus-visible{outline:2px solid #fff;outline-offset:2px}
		@media (max-width:480px){#ms-cookie .ms-cookie-buttons a{flex:1}}
		@media (prefers-reduced-motion:reduce){#ms-cookie{transition:none}}
	</style>
	<script>
	(function () {
		var box = document.getElementById('ms-cookie'), KEY = 'cookieConsent';
		function stored() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
		function choose(c) {
			try { localStorage.setItem(KEY, c); } catch (e) {}
			box.hidden = true;
			if (typeof gtag === 'function') {
				var v = c === 'accepted' ? 'granted' : 'denied';
				gtag('consent', 'update', { analytics_storage: v, ad_storage: v, ad_user_data: v, ad_personalization: v });
			}
		}
		box.addEventListener('click', function (e) {
			var a = e.target.closest('[data-choice]');
			if (a) { e.preventDefault(); choose(a.dataset.choice); }
		});
		document.addEventListener('click', function (e) {
			var a = e.target.closest('a[href$="#cookie-settings"]');
			if (a) { e.preventDefault(); box.hidden = false; }
		});
		var c = stored();
		if (c !== 'accepted' && c !== 'rejected') setTimeout(function () { box.hidden = false; }, 600);
	})();
	</script>
	<?php
} );

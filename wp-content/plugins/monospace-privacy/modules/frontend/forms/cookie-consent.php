<?php
/**
 * Cookie consent - opt-in for every visitor, everywhere
 *
 * Nothing tracks until the visitor clicks Accept on the banner; Reject (or no answer) keeps it all
 * off. The banner doesn't load trackers itself - it passes the choice to the plugins that do:
 *
 *   - WP Consent API (plugin): consent type "optin", so every category is denied until set. The
 *     banner sets statistics / statistics-anonymous / marketing / preferences to allow or deny
 *     (cookies wp_consent_*, kept 365 days).
 *   - Google (Site Kit: Analytics, AdSense): Google consent mode, all denied by default - for every
 *     region (Site Kit's default only covers the EU/UK). Site Kit reads the WP Consent API on page
 *     load and on change, and switches analytics / ads storage on after Accept.
 *   - Facebook Pixel + Conversions API (Facebook for WooCommerce): signals "held" until Accept
 *     (cookie wc_facebook_signals_state; the plugin treats a missing cookie as active, so it's set
 *     to held - in PHP for the request (functions.php, before init), and in the head before the
 *     pixel for cached pages).
 *     Accept / Reject call the plugin's window.fbwcsignal.release() / hold().
 *
 * The choice itself is kept in localStorage "cookieConsent" (accepted | rejected); on each page
 * the script re-applies it if the plugins' cookies disagree (e.g. expired, or set before this
 * version).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Opt-in: no consent until given
add_filter( 'wp_get_consent_type', function () { return 'optin'; } );
add_filter( 'wp_cookie_expiration', function () { return 365; } );

// Google consent mode: denied by default everywhere, not just the EU/UK list
add_filter( 'googlesitekit_consent_defaults', function ( $defaults ) {
    unset( $defaults['region'] );
    return $defaults;
} );

// Facebook: a visitor without a state cookie is marked held in functions.php - it has to happen
// before init, when the plugin reads it (too late from here)

// Same for cached pages (no PHP runs): set the held cookie before the pixel initializes
add_action( 'wp_head', function () {
    echo "<script>(function(){var c=null;try{c=localStorage.getItem('cookieConsent')}catch(e){}"
       . "if(c!=='accepted'&&!/(?:^|;\\s*)wc_facebook_signals_state=/.test(document.cookie))"
       . "document.cookie='wc_facebook_signals_state=held;path=/;max-age=31536000;SameSite=Lax'})()</script>\n";
}, 1 );

add_action( 'wp_footer', function () {
    echo '
    <div id="cookie-consent-banner" style="
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: #222;
        color: #fff;
        padding: 15px;
        font-size: 14px;
        text-align: center;
        z-index: 9999;
        display: none;
    ">
        This website uses cookies to enhance your browsing experience.
        See our <a href="' . esc_url( get_privacy_policy_url() ?: home_url( '/privacy-policy-2/' ) ) . '" style="color:#4eaaff;text-decoration:underline;">Privacy Policy</a>.
        <div style="margin-top:10px;">
            <button id="accept-cookies" style="margin:0 5px; padding:6px 12px; background:#4caf50; color:#fff; border:none; cursor:pointer;">
                Accept
            </button>
            <button id="reject-cookies" style="margin:0 5px; padding:6px 12px; background:#f44336; color:#fff; border:none; cursor:pointer;">
                Reject
            </button>
        </div>
    </div>

    <script>
    (function(){
        var KEY = "cookieConsent";
        var CATEGORIES = ["statistics", "statistics-anonymous", "marketing", "preferences"];
        var banner = document.getElementById("cookie-consent-banner");

        function stored() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }

        function fbState() {
            var m = document.cookie.match(/(?:^|;\s*)wc_facebook_signals_state=([^;]*)/);
            return m ? decodeURIComponent(m[1]) : null;
        }

        // Pass the choice to the WP Consent API (Google via Site Kit) and Facebook. Only touches
        // what disagrees, so a page view with nothing to change costs nothing.
        function apply(choice) {
            var allow = choice === "accepted", value = allow ? "allow" : "deny";
            if (window.wp_set_consent && window.wp_has_consent) {
                CATEGORIES.forEach(function (c) {
                    if (wp_has_consent(c) !== allow) wp_set_consent(c, value);
                });
            }
            var fb = allow ? "active" : "held";
            if (fbState() !== fb) {
                if (window.fbwcsignal) {
                    (allow ? fbwcsignal.release() : fbwcsignal.hold()).catch(function () {});
                } else {
                    document.cookie = "wc_facebook_signals_state=" + fb + ";path=/;max-age=31536000;SameSite=Lax";
                }
            }
        }

        function choose(choice) {
            try { localStorage.setItem(KEY, choice); } catch (e) {}
            banner.style.display = "none";
            apply(choice);
        }

        document.getElementById("accept-cookies").addEventListener("click", function () { choose("accepted"); });
        document.getElementById("reject-cookies").addEventListener("click", function () { choose("rejected"); });

        // The consent scripts load after this one (footer): wait for them
        document.addEventListener("DOMContentLoaded", function () {
            var choice = stored();
            if (choice === "accepted" || choice === "rejected") apply(choice);
            else banner.style.display = "block";
        });
    })();
    </script>
    ';
});

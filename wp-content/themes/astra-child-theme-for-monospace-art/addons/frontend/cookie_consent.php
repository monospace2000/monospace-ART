<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */




/**
 * GDPR-compliant cookie consent banner
 *
 * Displays a banner at the bottom of the site asking users to accept or reject cookies.
 * If accepted, it loads Google Analytics (or any tracking script) dynamically.
 */
add_action('wp_footer', function() {
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
        See our <a href="/privacy-policy" style="color:#4eaaff;text-decoration:underline;">Privacy Policy</a>.
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
        // Show banner if consent not set
        if (!localStorage.getItem("cookieConsent")) {
            document.getElementById("cookie-consent-banner").style.display = "block";
        } else if (localStorage.getItem("cookieConsent") === "accepted") {
            loadAnalytics();
        }

        // Accept cookies
        document.getElementById("accept-cookies").addEventListener("click", function(){
            localStorage.setItem("cookieConsent", "accepted");
            document.getElementById("cookie-consent-banner").style.display = "none";
            loadAnalytics();
        });

        // Reject cookies
        document.getElementById("reject-cookies").addEventListener("click", function(){
            localStorage.setItem("cookieConsent", "rejected");
            document.getElementById("cookie-consent-banner").style.display = "none";
        });

        /**
         * Dynamically load Google Analytics script after consent
         */
        function loadAnalytics() {
            if (window.gaLoaded) return; // Avoid loading multiple times
            window.gaLoaded = true;

            var s = document.createElement("script");
            s.async = true;
            s.src = "https://www.googletagmanager.com/gtag/js?id=G-MSX5SP3W71"; // Replace with your GA4 ID
            document.head.appendChild(s);

            s.onload = function(){
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                gtag("js", new Date());
                gtag("config", "G-MSX5SP3W71"); // Replace with your GA4 ID
            };
        }
    })();
    </script>
    ';
});


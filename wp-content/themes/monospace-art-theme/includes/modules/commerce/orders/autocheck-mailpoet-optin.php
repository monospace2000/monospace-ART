<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */

// Enqueue custom checkout JS
add_action( 'wp_enqueue_scripts', 'custom_checkout_autocheck_mailpoet' );
function custom_checkout_autocheck_mailpoet() {
    if ( is_checkout() && ! is_wc_endpoint_url() ) {
        wp_add_inline_script( 'jquery-core', "
            jQuery(document).ready(function($) {
                function toggleMailPoet() {
                    var email = $('#billing_email').val();
                    if ( email.length > 0 ) {
                        $('#mailpoet_woocommerce_checkout_optin').prop('checked', true);
                    }
                }

                // Trigger on page load
                toggleMailPoet();

                // Trigger whenever email changes
                $('#billing_email').on('keyup change blur', function() {
                    toggleMailPoet();
                });
            });
        " );
    }
}

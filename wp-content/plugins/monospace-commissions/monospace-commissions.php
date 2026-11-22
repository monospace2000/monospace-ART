<?php
/**
 * Plugin Name: monospace ART - Commissions
 * Update URI: false
 * Description: Custom commission orders from request to shipping: ordering with photos, stages and due dates, previews with Approve / Request changes, the balance payment, reminders, the emails, and turning the finished piece into an artwork.
 * Version: 1.0.0
 * Author: Hens Breet
 * Requires Plugins: woocommerce
 *
 * A feature plugin (2026-10-19 restructure, ARCHITECTURE.md): it depends only on monospace ART - Core (the
 * mu-plugin) and can be switched off without breaking anything else. Its modules/ load on init, like the theme's
 * modules did (monospace_load_modules()). monospace_feature( 'commissions' ) is true while it's on.
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! function_exists( 'monospace_load_modules' ) ) return; // needs monospace ART - Core (wp-content/mu-plugins)

define( 'MONOSPACE_COMMISSIONS_DIR', __DIR__ );
define( 'MONOSPACE_COMMISSIONS_URL', plugin_dir_url( __FILE__ ) );

add_filter( 'monospace_feature_commissions', '__return_true' );
add_action( 'init', function () { monospace_load_modules( __DIR__ . '/modules' ); } );

add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

define( 'MS_COMMISSION_ADMIN_EMAIL', 'commissions@monospace.com' );

/** Address that gets a copy of the commission emails: the option ms_commission_admin_email, else the default above */
function ms_commission_admin_email() {
    return apply_filters( 'ms_commission_admin_email', get_option( 'ms_commission_admin_email' ) ?: MS_COMMISSION_ADMIN_EMAIL );
}

// WooCommerce builds its mailer before modules/ load, so the email classes are registered from here
add_filter( 'woocommerce_email_classes', function ( $emails ) {
    require_once __DIR__ . '/includes/class-commission-emails.php';
    if ( class_exists( 'MS_Commission_Email' ) ) {
        $emails['MS_Email_Commission_Received'] = new MS_Email_Commission_Received();
        $emails['MS_Email_New_Commission']      = new MS_Email_New_Commission();
        $emails['MS_Email_Commission_Preview']  = new MS_Email_Commission_Preview();
        $emails['MS_Email_Commission_Response'] = new MS_Email_Commission_Response();
        $emails['MS_Email_Commission_Reminder'] = new MS_Email_Commission_Reminder();
        $emails['MS_Email_Commission_Digest']   = new MS_Email_Commission_Digest();
        $emails['MS_Email_Commission_Shipped']  = new MS_Email_Commission_Shipped();
        $emails['MS_Email_Commission_Update']   = new MS_Email_Commission_Update();
        $emails['MS_Email_Commission_DueDate']  = new MS_Email_Commission_DueDate();
    }
    return $emails;
} );

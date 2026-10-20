<?php
/**
 * Legal Links (the fine print)
 *
 * The three legal pages, linked from the site footer on every page (footer.php).
 * Elsewhere, set outside this file: the checkout's Terms checkbox and privacy note (WooCommerce >
 * Settings > Advanced / Accounts & Privacy), the order email footer, the cookie banner
 * (cookie-consent.php), the MailPoet signup popup and the contact form.
 *
 * Pages: Privacy = Settings > Privacy (get_privacy_policy_url), Terms = WooCommerce's terms page,
 * GPSR = the page with slug MONOSPACE_GPSR_SLUG ("EU GPSR Compliance Notice"). A link whose page
 * isn't published is left out.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_GPSR_SLUG = 'eu-gpsr-compliance-notice';

/** [ key => [ label, url ] ] for the published legal pages */
function monospace_legal_links() {
    static $links = null;
    if ( $links !== null ) return $links;

    $page_url = function ( $id ) {
        return $id && get_post_status( $id ) === 'publish' ? get_permalink( $id ) : '';
    };
    $gpsr = get_page_by_path( MONOSPACE_GPSR_SLUG );

    $links = array_filter( [
        'privacy' => [ 'Privacy Policy', get_privacy_policy_url() ],
        'terms'   => [ 'Terms of Service', $page_url( function_exists( 'wc_terms_and_conditions_page_id' ) ? wc_terms_and_conditions_page_id() : 0 ) ],
        'gpsr'    => [ 'EU GPSR Compliance', $page_url( $gpsr ? $gpsr->ID : 0 ) ],
    ], function ( $link ) { return $link[1] !== ''; } );
    return $links;
}

/** Footer: all legal links on one line */
function monospace_render_legal_links() {
    $links = monospace_legal_links();
    if ( ! $links ) return '';
    $out = [];
    foreach ( $links as $link ) {
        $out[] = sprintf( '<a href="%s">%s</a>', esc_url( $link[1] ), esc_html( $link[0] ) );
    }
    return '<nav class="legal-links" aria-label="Legal">' . implode( '<span aria-hidden="true"> · </span>', $out ) . '</nav>';
}

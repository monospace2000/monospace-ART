<?php
/**
 * The Year (created) list gets the new year by itself (2026-11-15, owner: "making sure the Artwork Created date pulldown
 * automatically updates in the new year"). The Year dropdowns (New artwork, Quick Artwork Editor, Artwork Editor) list the
 * terms of the attribute pa_year-created, and the new-artwork default is the current year's term - so in January a year with
 * no term would be missing from the list and the default would be empty ("Missing: year"). The first time anyone who can edit
 * products opens wp-admin in a new year (site time zone), the term for that year is added. Nothing is ever removed.
 *
 * @package monospace-art-core
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Make sure the year's term exists; returns its term id, or 0 when it does not (yet) / cannot. $create = false only looks. */
function monospace_ensure_year_term( $year = null, $create = true ) {
    if ( ! taxonomy_exists( 'pa_year-created' ) ) return 0;
    $year = (string) ( $year ?: wp_date( 'Y' ) );
    $term = get_term_by( 'name', $year, 'pa_year-created' );
    if ( $term ) return (int) $term->term_id;
    if ( ! $create ) return 0;
    $made = wp_insert_term( $year, 'pa_year-created' );
    return is_wp_error( $made ) ? 0 : (int) $made['term_id'];
}

add_action( 'admin_init', function () {
    if ( wp_doing_ajax() || ! current_user_can( 'edit_products' ) ) return;
    $year = wp_date( 'Y' );
    if ( get_option( 'monospace_year_term_checked' ) === $year ) return;   // once a year
    if ( monospace_ensure_year_term( $year ) ) update_option( 'monospace_year_term_checked', $year, true );
}, 30 );

<?php
/**
 * The WP Details block folds like the price card badge (2026-11-16, owner: "the WP Details block should fold the same way Price
 * Card Badges do").
 *
 * The block prints <details><summary>..</summary>content</details>; here the content is wrapped in .ms-fold-body > .ms-fold-inner, the
 * markup the badge card uses (.pc-badge-body > .pc-badge-inner), so the CSS can fold it by its height (grid-template-rows 1fr <->
 * 0fr, _components.scss) and assets/scripts/details-fold.js can drive it. Without the script the block opens and closes as before.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'render_block_core/details', function ( $html ) {
    if ( ! is_string( $html ) || is_admin() || strpos( $html, 'ms-fold-body' ) !== false ) return $html;

    $start = stripos( $html, '</summary>' );
    $end   = strripos( $html, '</details>' );
    if ( $start === false || $end === false || $end < $start ) return $html;
    $start += strlen( '</summary>' );

    return substr( $html, 0, $start )
        . '<div class="ms-fold-body"><div class="ms-fold-inner">' . substr( $html, $start, $end - $start ) . '</div></div>'
        . substr( $html, $end );
} );

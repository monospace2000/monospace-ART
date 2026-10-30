<?php
/**
 * Form submit buttons as real <button>s, so they take the site's button style (Layout Manager > Appearance).
 *
 * MailPoet and Contact Form 7 print <input type="submit">. An input can't show the glass button's highlight
 * (it's a ::before layer, which inputs don't draw), and MailPoet writes its own look into a style="" attribute
 * (color, corner radius, padding, font), which beats the site's style. So their submit inputs become
 * <button type="submit"> with the same classes and attributes and the input's value as the label; MailPoet's
 * style="" is dropped.
 *
 * Where (2026-10-20 - before, only post content was converted, so the home page box's MailPoet form wasn't):
 *   mailpoet_form_widget_post_process   every MailPoet form (shortcode, widget, popup, home page box)
 *   wpcf7_form_elements                 every Contact Form 7 form
 *   the_content                         anything else that reaches post content
 *   wp_footer, priorities 9 - 11        MailPoet's popup where there's no post content to add it to (the home
 *                                       page, archives): MailPoet prints it straight into the footer at
 *                                       priority 10, with no filter - captured around it and converted
 * The forms' scripts find the button by its class (.mailpoet_submit / .wpcf7-submit), so they work the same.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** <input type="submit" class="… mailpoet_submit | wpcf7-submit …"> → <button type="submit" …>label</button> */
function monospace_form_submit_buttons( $html ) {
    if ( ! is_string( $html ) || stripos( $html, 'submit' ) === false ) return $html;
    return preg_replace_callback( '/<input\b([^>]*?)\s*\/?>/i', function ( $m ) {
        $attrs = $m[1];
        if ( ! preg_match( '/\btype\s*=\s*(["\'])submit\1/i', $attrs ) ) return $m[0];
        if ( ! preg_match( '/\bclass\s*=\s*(["\'])([^"\']*)\1/i', $attrs, $c ) ) return $m[0];
        $mailpoet = preg_match( '/(^|\s)mailpoet_submit(\s|$)/', $c[2] );
        if ( ! $mailpoet && ! preg_match( '/(^|\s)wpcf7-submit(\s|$)/', $c[2] ) ) return $m[0];

        $label = preg_match( '/\bvalue\s*=\s*(["\'])(.*?)\1/is', $attrs, $v ) ? $v[2] : 'Submit';
        $attrs = preg_replace( '/\s*\b(type|value)\s*=\s*(["\']).*?\2/is', '', $attrs );
        if ( $mailpoet ) $attrs = preg_replace( '/\s*\bstyle\s*=\s*(["\']).*?\1/is', '', $attrs );
        return '<button type="submit" ' . trim( $attrs ) . '>' . $label . '</button>';
    }, $html );
}

add_filter( 'mailpoet_form_widget_post_process', 'monospace_form_submit_buttons', 20 );
add_filter( 'wpcf7_form_elements', 'monospace_form_submit_buttons', 20 );
add_filter( 'the_content', function ( $content ) {
    return ( strpos( $content, 'mailpoet_submit' ) !== false || strpos( $content, 'wpcf7-submit' ) !== false )
        ? monospace_form_submit_buttons( $content ) : $content;
}, 20 );

// MailPoet's footer forms (popup on the home page / archives): no filter on that path - capture priority 10's output
add_action( 'wp_footer', function () { ob_start(); }, 9 );
add_action( 'wp_footer', function () { echo monospace_form_submit_buttons( (string) ob_get_clean() ); }, 11 );

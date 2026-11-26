<?php
/**
 * The connection to Claude (Anthropic Messages API). The key is the constant MONOSPACE_ANTHROPIC_KEY in wp-config.php -
 * never in the database, the repo or a log. Only a resized copy of an image already on the public site is sent.
 *
 * @package monospace-ai-alt-text
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MS_AI_DEFAULT_MODEL = 'claude-sonnet-5-5';

function ms_ai_key() {
    return defined( 'MONOSPACE_ANTHROPIC_KEY' ) ? trim( (string) MONOSPACE_ANTHROPIC_KEY ) : '';
}

/** Option monospace_ai_alt: [ 'model' => ... ] */
function ms_ai_settings() {
    $s = (array) get_option( 'monospace_ai_alt', [] );
    $model = isset( $s['model'] ) ? trim( (string) $s['model'] ) : '';
    return [ 'model' => preg_match( '/^[A-Za-z0-9._-]{3,80}$/', $model ) ? $model : MS_AI_DEFAULT_MODEL ];
}

/**
 * One request. $content = the user message's blocks (text and an image). Returns the model's text, or a WP_Error whose
 * message is plain words for the owner (the key is never in it).
 */
function ms_ai_request( $system, array $content, $max_tokens = 500 ) {
    $key = ms_ai_key();
    if ( $key === '' ) return new WP_Error( 'ms_ai_nokey', 'No API key yet. It goes into wp-config.php as MONOSPACE_ANTHROPIC_KEY.' );
    $response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
        'timeout' => 60,
        'headers' => [ 'x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json' ],
        'body'    => wp_json_encode( [
            'model'      => ms_ai_settings()['model'],
            // Short answers: no extended thinking (it used up the whole allowance and left no text, 2026-11-15) and room to spare
            'thinking'   => [ 'type' => 'between_tools' ], // this model's way of saying "do not think first" ("disabled" is refused)
            'max_tokens' => max( 1000, (int) $max_tokens ),
            'system'     => $system,
            'messages'   => [ [ 'role' => 'user', 'content' => $content ] ],
        ] ),
    ] );
    if ( is_wp_error( $response ) ) return new WP_Error( 'ms_ai_net', 'Could not reach Claude: ' . str_replace( $key, '[key]', $response->get_error_message() ) );
    $code = (int) wp_remote_retrieve_response_code( $response );
    $body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
    if ( $code !== 200 ) {
        $msg = is_array( $body ) ? (string) ( $body['error']['message'] ?? '' ) : '';
        $msg = str_replace( $key, '[key]', $msg );
        if ( $code === 401 ) $msg = 'The API key was refused (check the key in wp-config.php).';
        elseif ( $code === 429 ) $msg = 'Too many requests right now - try again in a minute.';
        elseif ( stripos( $msg, 'credit' ) !== false ) $msg = 'The API account is out of credit: ' . $msg;
        return new WP_Error( 'ms_ai_http', 'Claude said no (' . $code . ')' . ( $msg !== '' ? ': ' . $msg : '.' ) );
    }
    $text = '';
    foreach ( (array) ( $body['content'] ?? [] ) as $block ) if ( ( $block['type'] ?? '' ) === 'text' ) $text .= (string) $block['text'];
    if ( trim( $text ) !== '' ) return trim( $text );
    $why = is_array( $body ) ? (string) ( $body['stop_reason'] ?? '' ) : '';
    return new WP_Error( 'ms_ai_empty', $why === 'refusal' ? 'Claude declined this request.' : 'Claude sent back nothing' . ( $why !== '' ? ' (' . $why . ')' : '' ) . '. Try again.' );
}

/** The model's reply as an array (it is asked for JSON only; a code fence around it is tolerated) */
function ms_ai_json( $text ) {
    $text = trim( preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', trim( $text ) ) );
    $data = json_decode( $text, true );
    if ( ! is_array( $data ) && preg_match( '/\{.*\}/s', $text, $m ) ) $data = json_decode( $m[0], true );
    return is_array( $data ) ? $data : null;
}

/** The image as a request block: a copy of about 1,000 px on the long edge (JPEG), or a WP_Error */
function ms_ai_image_block( $attachment_id ) {
    if ( ! wp_attachment_is_image( $attachment_id ) ) return new WP_Error( 'ms_ai_img', 'That is not an image.' );
    if ( preg_match( '/hei[cf]/i', (string) get_post_mime_type( $attachment_id ) ) ) return new WP_Error( 'ms_ai_img', 'This is a HEIC photo; Claude needs a JPEG. Pick the image again and the screen makes a JPEG copy.' );
    $file = get_attached_file( $attachment_id );
    $use  = '';
    $info = image_get_intermediate_size( $attachment_id, 'large' );
    if ( $info && ! empty( $info['file'] ) && file_exists( trailingslashit( dirname( (string) $file ) ) . $info['file'] ) && max( (int) $info['width'], (int) $info['height'] ) >= 800 ) {
        $use = trailingslashit( dirname( (string) $file ) ) . $info['file'];
    }
    $tmp = '';
    if ( $use === '' ) {
        if ( ! $file || ! file_exists( $file ) ) return new WP_Error( 'ms_ai_img', 'The image file was not found.' );
        $editor = wp_get_image_editor( $file );
        if ( is_wp_error( $editor ) ) return new WP_Error( 'ms_ai_img', 'The image could not be opened.' );
        $editor->resize( 1000, 1000, false );
        $editor->set_quality( 82 );
        $tmp = wp_tempnam( 'ms-ai.jpg' );
        $saved = $editor->save( $tmp, 'image/jpeg' );
        if ( is_wp_error( $saved ) ) { @unlink( $tmp ); return new WP_Error( 'ms_ai_img', 'The image could not be resized.' ); }
        $use = $saved['path'];
    }
    $bytes = (string) file_get_contents( $use );
    $type  = wp_check_filetype( $use )['type'] ?: 'image/jpeg';
    if ( $tmp !== '' ) { @unlink( $tmp ); if ( isset( $saved['path'] ) ) @unlink( $saved['path'] ); }
    if ( $bytes === '' || strlen( $bytes ) > 4500000 ) return new WP_Error( 'ms_ai_img', 'The image is too large to send.' );
    return [ 'type' => 'image', 'source' => [ 'type' => 'base64', 'media_type' => $type, 'data' => base64_encode( $bytes ) ] ];
}

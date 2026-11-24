<?php
/**
 * AI image descriptions (alt text) (Kit part since 2026-11-23; built on Sightings' theme alt.php, the owner's rules in the knowledge base
 * workflows/alt-text.md). Owner: "automatic with option to change manually".
 *
 * - Automatic: a picture uploaded to the Media Library without a description gets one in the background about 20 seconds later
 *   (WP-Cron), written by Claude from the picture alone. A description that exists is never replaced.
 * - By hand: the Media Library's Alternative Text field as always, and a "Write a description" button in the picture's details
 *   (asks Claude again; about a cent; replaces the text after a confirm when there is one).
 * - Failures are kept on the picture (_ms_alt_error) and shown in its details.
 *
 * Key: MONOSPACE_ANTHROPIC_KEY in the site's wp-config.php (never print it). Without a key nothing is sent.
 * Site setting 'alt' => [ 'about' => 'one line on what this site's pictures are' ] (helps Claude name the medium), optional.
 * The art site and Sightings keep their own versions (artwork medium wording, "Digital drawing").
 */

if ( ! defined( 'ABSPATH' ) ) exit;

const MONOSPACE_KIT_ALT_MODEL = 'claude-sonnet-5-5';

function monospace_kit_alt_key() {
	return defined( 'MONOSPACE_ANTHROPIC_KEY' ) ? trim( (string) MONOSPACE_ANTHROPIC_KEY ) : '';
}

function monospace_kit_alt_system() {
	$about = trim( (string) ( monospace_kit_settings()['alt']['about'] ?? '' ) );
	$site  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	return "You write alt text for pictures on the website \"$site\"" . ( $about ? " ($about)" : '' ) . ".\n\n" . <<<'TXT'
Write one short paragraph (one to three sentences, under 300 characters if you can) and nothing else: no preamble, no quotes around it.

Rules:
- Lead with the most important element, then the setting or layout, then supporting details.
- Name the medium only when it is obvious (a photograph, a black-and-white photograph, a screenshot, a painting, a drawing). Never start with "Image of" or "Picture of".
- Describe only what is visibly there. No interpretation, mood, story or meaning. Present tense, plain and concrete, US English.
- Transcribe legible text in the picture exactly as shown.
- Use specific colors and counts where they help ("two ducks on a log").
- Do not assume materials or garment types: "a black collar", not "a black fur collar"; "a red top", not "a red sweater". Name a material or garment only when it is really obvious.
- No gender and no age unless the picture itself makes it explicit. Otherwise say "a person" or "a figure" and write around pronouns.
- A screenshot of a website or app: name the site or page and list the visible sections in reading order.
- Leave out signatures, watermarks and the photographer.
TXT;
}

/** The picture as an image block for the request (a copy around 1000 px when there is one) */
function monospace_kit_alt_image_block( $att ) {
	$file = (string) get_attached_file( $att );
	$use  = '';
	foreach ( (array) ( wp_get_attachment_metadata( $att )['sizes'] ?? [] ) as $s ) {
		if ( ! empty( $s['file'] ) && (int) $s['width'] >= 700 && (int) $s['width'] <= 1100 ) {
			$cand = trailingslashit( dirname( $file ) ) . $s['file'];
			if ( is_readable( $cand ) ) { $use = $cand; break; }
		}
	}
	if ( '' === $use ) $use = $file;
	if ( ! $use || ! is_readable( $use ) ) return new WP_Error( 'ms_alt_img', 'The picture file was not found.' );
	$type = wp_check_filetype( $use )['type'] ?: 'image/jpeg';
	if ( filesize( $use ) > 3500000 || ! in_array( $type, [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ], true ) ) {
		$ed = wp_get_image_editor( $use );
		if ( is_wp_error( $ed ) ) return new WP_Error( 'ms_alt_img', 'The picture could not be opened.' );
		$ed->resize( 1000, 1000, false );
		$tmp = wp_tempnam( 'ms-alt.jpg' );
		$ed->save( $tmp, 'image/jpeg' );
		$bytes = file_get_contents( $tmp );
		@unlink( $tmp );
		$type = 'image/jpeg';
	} else {
		$bytes = file_get_contents( $use );
	}
	return [ 'type' => 'image', 'source' => [ 'type' => 'base64', 'media_type' => $type, 'data' => base64_encode( $bytes ) ] ];
}

/** Ask Claude for a description; the text or a WP_Error in plain words */
function monospace_kit_alt_ask( $att ) {
	$key = monospace_kit_alt_key();
	if ( '' === $key ) return new WP_Error( 'ms_alt_nokey', 'No API key on this site (MONOSPACE_ANTHROPIC_KEY in wp-config.php).' );
	$img = monospace_kit_alt_image_block( $att );
	if ( is_wp_error( $img ) ) return $img;
	$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
		'timeout' => 60,
		'headers' => [ 'x-api-key' => $key, 'anthropic-version' => '2023-06-01', 'content-type' => 'application/json' ],
		'body'    => wp_json_encode( [
			'model'      => MONOSPACE_KIT_ALT_MODEL,
			'thinking'   => [ 'type' => 'between_tools' ], // this model's "do not think first" (see the art site)
			'max_tokens' => 1000,
			'system'     => monospace_kit_alt_system(),
			'messages'   => [ [ 'role' => 'user', 'content' => [ $img, [ 'type' => 'text', 'text' => 'Write the alt text for this picture.' ] ] ] ],
		] ),
	] );
	if ( is_wp_error( $response ) ) return new WP_Error( 'ms_alt_net', 'Could not reach Claude: ' . str_replace( $key, '[key]', $response->get_error_message() ) );
	$code = (int) wp_remote_retrieve_response_code( $response );
	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( 200 !== $code ) {
		$msg = is_array( $body ) ? str_replace( $key, '[key]', (string) ( $body['error']['message'] ?? '' ) ) : '';
		return new WP_Error( 'ms_alt_http', 'Claude said no (' . $code . ')' . ( '' !== $msg ? ': ' . $msg : '.' ) );
	}
	$text = '';
	foreach ( (array) ( $body['content'] ?? [] ) as $block ) if ( 'text' === ( $block['type'] ?? '' ) ) $text .= (string) $block['text'];
	$text = trim( preg_replace( '/\s+/u', ' ', trim( $text, " \t\n\r\"“”" ) ) );
	return '' === $text ? new WP_Error( 'ms_alt_empty', 'Claude sent back nothing. Try again.' ) : $text;
}

function monospace_kit_alt_is_picture( $att ) {
	return wp_attachment_is_image( $att ) && ! ( function_exists( 'wp_attachment_is_shortcut' ) && wp_attachment_is_shortcut( $att ) );
}

/* ---- Automatic: a new picture without a description gets one in the background ---- */

add_action( 'add_attachment', function ( $att ) {
	if ( ! monospace_kit_alt_key() ) return;
	if ( ! wp_next_scheduled( 'monospace_kit_alt_auto', [ (int) $att ] ) ) wp_schedule_single_event( time() + 20, 'monospace_kit_alt_auto', [ (int) $att ] );
} );

add_action( 'monospace_kit_alt_auto', function ( $att ) {
	if ( ! get_post( $att ) || ! monospace_kit_alt_is_picture( $att ) ) return;
	if ( '' !== trim( (string) get_post_meta( $att, '_wp_attachment_image_alt', true ) ) ) return; // written by hand meanwhile
	$text = monospace_kit_alt_ask( $att );
	if ( is_wp_error( $text ) ) { update_post_meta( $att, '_ms_alt_error', $text->get_error_message() ); return; }
	delete_post_meta( $att, '_ms_alt_error' );
	update_post_meta( $att, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( $text ) ) );
} );

/* ---- By hand: "Write a description" in the picture's details ---- */

add_filter( 'attachment_fields_to_edit', function ( $fields, $post ) {
	if ( ! monospace_kit_alt_is_picture( $post->ID ) || ! current_user_can( 'upload_files' ) ) return $fields;
	$err  = (string) get_post_meta( $post->ID, '_ms_alt_error', true );
	$html = '<button type="button" class="button ms-alt-write" data-id="' . (int) $post->ID . '" data-nonce="' . esc_attr( wp_create_nonce( 'monospace_kit_alt_' . $post->ID ) ) . '">Write a description</button>'
		. '<p class="description">Claude describes the picture (by your alt-text rules) and puts it in Alternative Text. About a cent.' . ( monospace_kit_alt_key() ? '' : ' <strong>No API key on this site yet.</strong>' ) . '</p>'
		. ( $err ? '<p class="description" style="color:#b32d2e">Last automatic try: ' . esc_html( $err ) . '</p>' : '' );
	$fields['ms_alt_write'] = [ 'label' => 'AI description', 'input' => 'html', 'html' => $html ];
	return $fields;
}, 20, 2 );

add_action( 'wp_ajax_monospace_kit_alt_write', function () {
	$id = (int) ( $_POST['id'] ?? 0 );
	check_ajax_referer( 'monospace_kit_alt_' . $id, 'nonce' );
	if ( ! $id || ! current_user_can( 'edit_post', $id ) || ! monospace_kit_alt_is_picture( $id ) ) wp_send_json_error( 'That picture can’t be described.' );
	$text = monospace_kit_alt_ask( $id );
	if ( is_wp_error( $text ) ) wp_send_json_error( $text->get_error_message() );
	delete_post_meta( $id, '_ms_alt_error' );
	update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( sanitize_text_field( $text ) ) );
	wp_send_json_success( $text );
} );

// The button: one delegated listener for the pop-up and the edit screen; fills the Alternative Text box it belongs to
$monospace_kit_alt_js = function () {
	if ( ! current_user_can( 'upload_files' ) ) return;
	?>
	<script>
	document.addEventListener('click', function (e) {
		var b = e.target.closest('.ms-alt-write');
		if (!b) return;
		e.preventDefault();
		var scope = b.closest('.attachment-details, .media-modal, form, #post-body') || document;
		var box = scope.querySelector('#attachment-details-two-column-alt-text, #attachment-details-alt-text, [data-setting="alt"] textarea, [data-setting="alt"] input, #attachment_alt');
		if (box && box.value.trim() && !confirm('Replace the description that is there now?')) return;
		var label = b.textContent; b.disabled = true; b.textContent = 'Writing…';
		var f = new FormData(); f.append('action', 'monospace_kit_alt_write'); f.append('id', b.dataset.id); f.append('nonce', b.dataset.nonce);
		fetch(ajaxurl, { method: 'POST', body: f, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (r) {
			if (!r.success) { alert(r.data || 'Something went wrong.'); return; }
			if (box) { box.value = r.data; box.dispatchEvent(new Event('change', { bubbles: true })); box.dispatchEvent(new Event('input', { bubbles: true })); }
		}).catch(function () { alert('Could not reach the site.'); }).finally(function () { b.disabled = false; b.textContent = label; });
	});
	</script>
	<?php
};
add_action( 'admin_footer-upload.php', $monospace_kit_alt_js );
add_action( 'admin_footer-post.php', $monospace_kit_alt_js );
add_action( 'admin_footer-post-new.php', $monospace_kit_alt_js );

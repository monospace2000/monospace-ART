<?php
/**
 * Plugin Name: monospace Kit
 * Description: The parts every monospace WordPress site shares. Switch each part on or off for this site under Settings > monospace Kit. SEO is its partner, the mu-plugin monospace-seo.
 * Version: 1.2.1
 * Author: monospace
 * Update URI: false
 *
 * ONE COPY FOR EVERY SITE: master in the knowledge base (monospace2000/Claude, tools/monospace-kit/). Edit it there, then copy the
 * whole folder unchanged to each site's wp-content/plugins/monospace-kit/. Owner, 2026-11-23: "make every website more or less
 * have the same functionality, the same set of plugins or custom code, so whenever I log into one, I'm familiar with what's there",
 * and "any of these features I can turn on or off per site".
 *
 * Which parts are on: Settings > monospace Kit (option monospace_kit_modules, once saved there); until then the site's settings file.
 * Settings file (per site, mu-plugins/monospace-kit-config.php):
 *   function monospace_kit_config() { return [
 *     'modules' => [ ... ],                     // the parts on at first (the screen takes over once saved)
 *     'own'     => [ ... ],                     // parts this site does with its own code: shown, never loaded (the art site)
 *     'ga4'     => 'G-XXXXXXX', 'ga4_consent' => true,
 *     'contact' => [ 'to' => '...', 'from' => '...', 'privacy' => '...', 'subject_prefix' => '' ],
 *     'alt'     => [ 'about' => 'what this site's pictures are' ],
 *   ]; }
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MONOSPACE_KIT_DIR', __DIR__ );
define( 'MONOSPACE_KIT_URL', plugin_dir_url( __FILE__ ) );

/** Every part: id => [ name, what it does, group ] - the order of the settings screen */
function monospace_kit_parts() {
	return [
		'analytics'         => [ 'Analytics', 'The Google Analytics tag (the ID is in this site’s settings file). Your own visits while logged in are not counted.', 'Visitors' ],
		'cookie-banner'     => [ 'Cookie banner', 'Asks visitors before Google counts them (opt-in). Needed with Analytics.', 'Visitors' ],
		'contact'           => [ 'Contact form', 'The form on the Contact page (shortcode [monospace_contact]); messages go to the address in this site’s settings file.', 'Visitors' ],
		'lightbox'          => [ 'Lightbox', 'Tap a picture in a post to see it full screen: swipe for the next, pinch to zoom, tap to close.', 'Visitors' ],
		'video-poster'      => [ 'Video stills', 'An uploaded video gets a still frame, so iPhones and iPads show a picture instead of a black box.', 'Visitors' ],
		'media-usage'       => [ 'Used in', 'Media Library column showing where each file is used, with a Used / Not used filter.', 'Media Library' ],
		'media-replace'     => [ 'Replace file', 'Upload a new version of a file under the same name and address (a file’s edit screen).', 'Media Library' ],
		'remove-background' => [ 'Remove background', 'Swipe over colors to make them transparent (Edit Image); saves a new PNG, the original stays.', 'Media Library' ],
		'ai-alt-text'       => [ 'AI descriptions', 'New pictures get a description (alt text) written by Claude, by your rules; a “Write a description” button too. Needs the API key in wp-config.php.', 'Media Library' ],
		'alt-sync'          => [ 'Descriptions in sync', 'A picture’s description stays the same in the Media Library and inside posts, whichever you edit.', 'Media Library' ],
		'heic'              => [ 'iPhone photos (HEIC)', 'HEIC photos become JPEGs as they upload.', 'Media Library' ],
		'youtube-poster'    => [ 'YouTube thumbnails', 'A post with only a YouTube video gets the video’s thumbnail as its featured image.', 'Editing' ],
		'hide-blocks'       => [ 'Hide a block', 'Block sidebar > Visibility > “Hide this block”: gone from the site, kept in the post.', 'Editing' ],
		'save-shortcut'     => [ '⌘/Ctrl+S saves', 'On the classic edit screens too (the block editor already does it).', 'Editing' ],
		'post-navigation'   => [ 'Newer / Older', 'Buttons in the toolbar on an edit screen: the next or previous post in your list, as you filtered and sorted it.', 'Editing' ],
		'thumbnail-column'  => [ 'Featured image column', 'A small picture beside each title in the Posts and Pages lists.', 'Editing' ],
	];
}

function monospace_kit_settings() {
	static $s = null;
	if ( $s === null ) {
		$s = array_merge( [ 'modules' => [], 'own' => [], 'ga4' => '', 'contact' => [], 'alt' => [] ], function_exists( 'monospace_kit_config' ) ? (array) monospace_kit_config() : [] );
		$saved = get_option( 'monospace_kit_modules', null );
		if ( is_array( $saved ) ) $s['modules'] = $saved;
		$s['modules'] = array_values( array_diff( array_intersect( $s['modules'], array_keys( monospace_kit_parts() ) ), $s['own'] ) );
	}
	return $s;
}

function monospace_kit_on( $module ) {
	return in_array( $module, monospace_kit_settings()['modules'], true );
}

foreach ( array_keys( monospace_kit_parts() ) as $module ) {
	if ( monospace_kit_on( $module ) && is_file( MONOSPACE_KIT_DIR . '/modules/' . $module . '.php' ) ) require MONOSPACE_KIT_DIR . '/modules/' . $module . '.php';
}

/* ============================================================
 * Settings > monospace Kit: a switch per part
 * ============================================================ */

add_action( 'admin_menu', function () {
	add_options_page( 'monospace Kit', 'monospace Kit', 'manage_options', 'monospace-kit', 'monospace_kit_screen' );
} );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=monospace-kit' ) ) . '">Parts</a>' );
	return $links;
} );

add_filter( 'plugin_row_meta', function ( $meta, $file ) {
	if ( $file === plugin_basename( __FILE__ ) ) {
		$n = count( monospace_kit_settings()['modules'] );
		$meta[] = $n . ' of ' . count( monospace_kit_parts() ) . ' parts on here';
	}
	return $meta;
}, 10, 2 );

add_action( 'admin_post_monospace_kit_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed' );
	check_admin_referer( 'monospace_kit_save' );
	$own = monospace_kit_settings()['own'];
	$on  = array_values( array_diff( array_intersect( array_map( 'sanitize_key', (array) ( $_POST['parts'] ?? [] ) ), array_keys( monospace_kit_parts() ) ), $own ) );
	update_option( 'monospace_kit_modules', $on, true );
	if ( function_exists( 'sg_cachepress_purge_everything' ) ) sg_cachepress_purge_everything();
	wp_safe_redirect( admin_url( 'options-general.php?page=monospace-kit&saved=1' ) );
	exit;
} );

/** The panel styles: the art site's Core ones when present (monospace_lm_area_assets), else the same rules, so every site looks alike */
function monospace_kit_panel_styles() {
	if ( function_exists( 'monospace_lm_area_assets' ) ) { monospace_lm_area_assets(); return; }
	echo '<style>
		.lm-panels{display:grid;grid-template-columns:minmax(0,1fr);gap:10px;max-width:860px}
		.lm-panel{background:#fff;border:1px solid #c3c4c7;border-radius:6px}
		.lm-panel>summary{display:flex;flex-wrap:wrap;align-items:baseline;gap:4px 10px;padding:12px 14px;cursor:pointer;list-style:none}
		.lm-panel>summary::-webkit-details-marker{display:none}
		.lm-panel>summary::before{content:"\25B8";color:#8c8f94;width:10px}
		.lm-panel[open]>summary::before{content:"\25BE"}
		.lm-panel[open]>summary{border-bottom:1px solid #f0f0f1}
		.lm-t{font-weight:600}
		.lm-sub{flex-basis:100%;padding-left:20px;color:#646970;white-space:normal}
		.lm-chip{font-size:11px;padding:1px 8px;border-radius:10px;background:#f0f0f1;color:#50575e}
		.lm-chip.is-on{background:#e7f0fa;color:#135e96}
		.lm-chip.is-empty{background:#fff;border:1px solid #c3c4c7;color:#646970}
		.lm-chip.is-off{background:#f6f7f7;color:#8c8f94}
	</style>';
}

function monospace_kit_screen() {
	$s     = monospace_kit_settings();
	$parts = monospace_kit_parts();
	$notes = [
		'analytics'   => $s['ga4'] ? 'ID ' . $s['ga4'] : ( ! empty( $s['ga4_consent'] ) ? 'consent only, no ID' : 'no ID set yet' ),
		'contact'     => ! empty( $s['contact']['to'] ) ? 'to ' . $s['contact']['to'] : 'to the site’s admin email',
		'ai-alt-text' => defined( 'MONOSPACE_ANTHROPIC_KEY' ) && MONOSPACE_ANTHROPIC_KEY ? 'key in place' : 'no key in wp-config.php',
	];
	$groups = [];
	foreach ( $parts as $id => [ $name, $what, $g ] ) $groups[ $g ][ $id ] = [ $name, $what ];
	monospace_kit_panel_styles();
	?>
	<div class="wrap">
		<h1>monospace Kit</h1>
		<p>The parts every monospace site shares. Switches here change <strong><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) . wp_parse_url( home_url(), PHP_URL_PATH ) ); ?></strong> only.</p>
		<?php if ( ! empty( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Saved. The site’s page cache was cleared.</p></div><?php endif; ?>
		<style>
			.ms-kit-row{display:flex;gap:10px;align-items:flex-start;padding:10px 16px 10px 34px;border-top:1px solid #f0f0f1;cursor:pointer}
			.ms-kit-row:first-of-type{border-top:0}
			.ms-kit-row input{margin-top:2px}
			.ms-kit-row .ms-kit-name{font-weight:600}
			.ms-kit-row .ms-kit-what{display:block;color:#646970}
			.ms-kit-row.is-own{cursor:default;color:#8c8f94}
			.ms-kit-row .lm-chip{margin-left:6px;vertical-align:1px}
		</style>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="monospace_kit_save">
			<?php wp_nonce_field( 'monospace_kit_save' ); ?>
			<div class="lm-panels">
			<?php foreach ( $groups as $g => $rows ) :
				$own_n = count( array_intersect( array_keys( $rows ), $s['own'] ) ); ?>
				<details class="lm-panel ms-kit-panel" data-panel="<?php echo esc_attr( sanitize_title( $g ) ); ?>" data-own="<?php echo (int) $own_n; ?>">
					<summary><span class="lm-t"><?php echo esc_html( $g ); ?></span><span class="lm-chip"></span><span class="lm-sub"></span></summary>
					<?php foreach ( $rows as $id => [ $name, $what ] ) :
						$own = in_array( $id, $s['own'], true );
						$on  = in_array( $id, $s['modules'], true ); ?>
						<label class="ms-kit-row<?php echo $own ? ' is-own' : ''; ?>">
							<input type="checkbox" name="parts[]" value="<?php echo esc_attr( $id ); ?>" data-name="<?php echo esc_attr( $name ); ?>"<?php checked( $on ); disabled( $own ); ?>>
							<span><span class="ms-kit-name"><?php echo esc_html( $name ); ?></span><?php
								if ( $own ) echo '<span class="lm-chip is-off">This site has its own</span>';
								elseif ( isset( $notes[ $id ] ) ) echo '<span class="lm-chip">' . esc_html( $notes[ $id ] ) . '</span>';
							?><span class="ms-kit-what"><?php echo esc_html( $what ); ?></span></span>
						</label>
					<?php endforeach; ?>
				</details>
			<?php endforeach; ?>
			</div>
			<p class="submit"><button class="button button-primary">Save</button></p>
		</form>
		<p class="description">Addresses, the Analytics ID and the contact recipients are in this site’s settings file (wp-content/mu-plugins/monospace-kit-config.php); ask Claude to change them. Master copy of the Kit: the knowledge base, tools/monospace-kit/.</p>
	</div>
	<script>
	// Each panel's chip ("2 of 5 on") and its one line ("On: ...") follow the switches. Listens to the switches only, never to the chips.
	(function () {
		function paint(panel) {
			var boxes = [].slice.call(panel.querySelectorAll('input[type=checkbox]:not(:disabled)'));
			var on = boxes.filter(function (b) { return b.checked; }).map(function (b) { return b.dataset.name; });
			var own = +panel.dataset.own, chip = panel.querySelector('summary .lm-chip'), sub = panel.querySelector('summary .lm-sub');
			if (!boxes.length) { chip.className = 'lm-chip is-off'; chip.textContent = 'This site has its own'; sub.textContent = 'Done here by the site’s own code.'; return; }
			chip.className = 'lm-chip ' + (on.length ? 'is-on' : 'is-empty');
			chip.textContent = on.length + ' of ' + boxes.length + ' on';
			sub.textContent = (on.length ? 'On: ' + on.join(', ') : 'Nothing on') + (own ? ' · ' + own + ' done by the site’s own code' : '');
		}
		document.querySelectorAll('.ms-kit-panel').forEach(function (p) {
			paint(p);
			p.addEventListener('change', function (e) { if (e.target.matches('input[type=checkbox]')) paint(p); });
		});
	})();
	</script>
	<?php
}

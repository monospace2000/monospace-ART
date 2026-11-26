<?php
/**
 * Spotlight (Kit part, 2026-11-23): the monospace signature light behind every page, with its own screen, Appearance > Spotlight.
 * Owner: "one of my trademark graphical elements on monospace as a property is the spotlight ... the one that lets me set angles
 * and colors". Ported from the art site (theme: includes/modules/design/spotlight.php + the Spotlight panels of Appearance > Look,
 * CSS in assets/scss/_global.scss). The art site keeps its own (Kit config 'own' => [ 'spotlight' ]); change art first, then this.
 *
 * Two layers on <body>, both fixed, behind the page's content (body becomes a stacking context: position relative, z-index 0):
 *   body::before  the pool of light: a radial gradient from the light color (bright core) to the shade color (the edges)
 *   body::after   the beam: the shade over the rest of the page with a soft cone cut out of it, coming in from --beam-angle
 *                 (clockwise from straight above: 300 = 10 o'clock); beam width 0 = no beam, only the pool
 * A theme that paints solid boxes over the whole page hides the light behind them; the screen's preview shows where it lands.
 *
 * Option monospace_kit_spotlight = [ on 0|1, pos => [ desktop|tablet|phone => [ x vw, y vh, core %, reach vw, angle deg ] ],
 *   colors => [ light #hex, light %, shade #hex, shade % ], beam => half the cone's width in degrees ]. Nothing saved = off.
 * Names differ from the art theme's (monospace_spotlight_*) so the two can never clash.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** key => [ label, media query ('' = all), preview width, preview height ] (as on the art site) */
function monospace_kit_spot_devices() {
	return [
		'desktop' => [ 'Computer', '',                   1280, 800 ],
		'tablet'  => [ 'Tablet',   '(max-width: 920px)', 820,  1080 ],
		'phone'   => [ 'Phone',    '(max-width: 640px)', 390,  780 ],
	];
}

/** The starting point: the art site's own (its canvas look) */
function monospace_kit_spot_defaults() {
	return [
		'on'     => 0,
		'pos'    => [ 'desktop' => [ 40, 40, 20, 50, 300 ], 'tablet' => [ 50, 40, 20, 80, 300 ], 'phone' => [ 50, 30, 20, 100, 300 ] ],
		'colors' => [ '#ffffff', 80, '#532500', 20 ],
		'beam'   => 13,
	];
}

function monospace_kit_spot_settings() {
	$d = monospace_kit_spot_defaults();
	$s = get_option( 'monospace_kit_spotlight', [] );
	if ( ! is_array( $s ) ) $s = [];
	$pos = $d['pos'];
	foreach ( array_keys( $pos ) as $dev ) if ( isset( $s['pos'][ $dev ] ) && count( (array) $s['pos'][ $dev ] ) === 5 ) $pos[ $dev ] = array_map( 'intval', array_values( $s['pos'][ $dev ] ) );
	return [
		'on'     => empty( $s['on'] ) ? 0 : 1,
		'pos'    => $pos,
		'colors' => isset( $s['colors'] ) && count( (array) $s['colors'] ) === 4 ? array_values( $s['colors'] ) : $d['colors'],
		'beam'   => isset( $s['beam'] ) ? (int) $s['beam'] : $d['beam'],
	];
}

function monospace_kit_spot_rgba( $hex, $alpha ) {
	$h = ltrim( (string) $hex, '#' );
	if ( ! preg_match( '/^[0-9a-f]{6}$/i', $h ) ) $h = 'ffffff';
	return sprintf( 'rgba(%d, %d, %d, %s)', hexdec( substr( $h, 0, 2 ) ), hexdec( substr( $h, 2, 2 ) ), hexdec( substr( $h, 4, 2 ) ), round( max( 0, min( 100, (int) $alpha ) ) / 100, 2 ) );
}

/** The CSS ('' when off). The screen's script builds the same (css() in the page JS) - change both together. */
function monospace_kit_spot_css( $s ) {
	if ( ! $s['on'] ) return '';
	$b = 'html body[class]';
	[ $l, $la, $d, $da ] = $s['colors'];
	$half = max( 0, min( 40, (int) $s['beam'] ) );
	$css  = $b . '{position:relative;z-index:0;--ms-spot-light:' . monospace_kit_spot_rgba( $l, $la ) . ';--ms-spot-shade:' . monospace_kit_spot_rgba( $d, $da ) . ';--ms-beam-half:' . $half . 'deg;--ms-beam-len:110vmax}';
	foreach ( monospace_kit_spot_devices() as $dev => [ , $media ] ) {
		[ $x, $y, $core, $reach, $angle ] = array_map( 'intval', $s['pos'][ $dev ] );
		$rule = $b . "{--ms-spot-x:{$x}vw;--ms-spot-y:{$y}vh;--ms-spot-core:{$core}%;--ms-spot-reach:{$reach}vw;--ms-beam-angle:{$angle}deg}";
		$css .= $media ? "@media $media{" . $rule . '}' : $rule;
	}
	$css .= $b . '::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;background:radial-gradient(circle at var(--ms-spot-x) var(--ms-spot-y),var(--ms-spot-light) var(--ms-spot-core),var(--ms-spot-shade) var(--ms-spot-reach))}';
	if ( $half > 0 ) {
		$mask = 'conic-gradient(from calc(var(--ms-beam-angle) + 180deg - var(--ms-beam-half)) at var(--sx) var(--sy),#000 0deg,rgba(0,0,0,.85) calc(var(--ms-beam-half) * .3),rgba(0,0,0,.1) calc(var(--ms-beam-half) * .8),transparent var(--ms-beam-half),rgba(0,0,0,.1) calc(var(--ms-beam-half) * 1.2),rgba(0,0,0,.85) calc(var(--ms-beam-half) * 1.7),#000 calc(var(--ms-beam-half) * 2)),radial-gradient(circle at var(--sx) var(--sy),transparent calc(var(--ms-beam-len) * .9),#000 calc(var(--ms-beam-len) + var(--ms-spot-reach) * 1.3))';
		$css .= $b . '::after{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;--sx:calc(var(--ms-spot-x) + var(--ms-beam-len) * sin(var(--ms-beam-angle)));--sy:calc(var(--ms-spot-y) - var(--ms-beam-len) * cos(var(--ms-beam-angle)));background:var(--ms-spot-shade);-webkit-mask-image:' . $mask . ';mask-image:' . $mask . '}';
	}
	return $css;
}

// Front end
add_action( 'wp_head', function () {
	$css = monospace_kit_spot_css( monospace_kit_spot_settings() );
	echo '<style id="ms-kit-spotlight">' . wp_strip_all_tags( $css ) . "</style>\n"; // printed even when empty: the preview replaces it
}, 99 );

// The preview frame: the page without the admin toolbar
add_filter( 'show_admin_bar', function ( $show ) {
	return isset( $_GET['ms_kit_spot_preview'] ) && current_user_can( 'edit_theme_options' ) ? false : $show;
} );

/* ============================================================
 * Appearance > Spotlight
 * ============================================================ */

add_action( 'admin_menu', function () {
	add_theme_page( 'Spotlight', 'Spotlight', 'edit_theme_options', 'monospace-kit-spotlight', 'monospace_kit_spot_screen' );
} );

add_action( 'admin_post_monospace_kit_spot_save', function () {
	if ( ! current_user_can( 'edit_theme_options' ) ) wp_die( 'Not allowed' );
	check_admin_referer( 'monospace_kit_spot_save' );
	$in = wp_unslash( (array) ( $_POST['spot'] ?? [] ) );
	$s  = [ 'on' => empty( $in['on'] ) ? 0 : 1, 'pos' => [], 'colors' => [], 'beam' => max( 0, min( 40, (int) ( $in['beam'] ?? 13 ) ) ) ];
	foreach ( array_keys( monospace_kit_spot_devices() ) as $dev ) {
		$p = array_map( 'intval', array_slice( array_values( (array) ( $in['pos'][ $dev ] ?? [] ) ), 0, 5 ) );
		if ( count( $p ) !== 5 ) $p = monospace_kit_spot_defaults()['pos'][ $dev ];
		$s['pos'][ $dev ] = [ max( 0, min( 100, $p[0] ) ), max( 0, min( 100, $p[1] ) ), max( 0, min( 100, $p[2] ) ), max( 10, min( 250, $p[3] ) ), ( ( $p[4] % 360 ) + 360 ) % 360 ];
	}
	$s['colors'] = [
		sanitize_hex_color( $in['light'] ?? '' ) ?: '#ffffff', max( 0, min( 100, (int) ( $in['light_a'] ?? 80 ) ) ),
		sanitize_hex_color( $in['shade'] ?? '' ) ?: '#532500', max( 0, min( 100, (int) ( $in['shade_a'] ?? 20 ) ) ),
	];
	update_option( 'monospace_kit_spotlight', $s, true );
	if ( function_exists( 'sg_cachepress_purge_everything' ) ) sg_cachepress_purge_everything();
	wp_safe_redirect( admin_url( 'themes.php?page=monospace-kit-spotlight&saved=1' ) );
	exit;
} );

function monospace_kit_spot_screen() {
	$s       = monospace_kit_spot_settings();
	$def     = monospace_kit_spot_defaults();
	$devices = monospace_kit_spot_devices();
	$pages   = [ 'Home page' => home_url( '/' ) ];
	foreach ( get_posts( [ 'post_type' => [ 'post', 'page' ], 'numberposts' => 8, 'post_status' => 'publish' ] ) as $p ) $pages[ wp_trim_words( get_the_title( $p ), 8 ) ?: '#' . $p->ID ] = get_permalink( $p );
	monospace_kit_panel_styles();
	$panel = function ( $id, $title, $sub ) {
		echo '<details class="lm-panel" data-panel="' . esc_attr( $id ) . '"><summary><span class="lm-t">' . esc_html( $title ) . '</span><span class="lm-chip" id="ms-ks-chip-' . esc_attr( $id ) . '"></span><span class="lm-sub">' . esc_html( $sub ) . '</span></summary><div class="ms-ks-body">';
	};
	$end = function () { echo '</div></details>'; };
	?>
	<div class="wrap ms-ks">
		<h1>Spotlight</h1>
		<p class="lm-where">The monospace light behind every page of <strong><?php echo esc_html( wp_parse_url( home_url(), PHP_URL_HOST ) . wp_parse_url( home_url(), PHP_URL_PATH ) ); ?></strong>. It stays put while the page scrolls. The preview shows your unsaved changes; nothing changes on the site until you save.</p>
		<?php if ( ! empty( $_GET['saved'] ) ) : ?><div class="notice notice-success is-dismissible"><p>Saved. The site’s page cache was cleared.</p></div><?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ms-ks-form">
			<input type="hidden" name="action" value="monospace_kit_spot_save">
			<?php wp_nonce_field( 'monospace_kit_spot_save' ); ?>
			<div class="ms-ks-grid">
				<div class="ms-ks-preview">
					<div class="ms-ks-bar">
						<span>
							<?php foreach ( $devices as $dev => $d ) : ?>
								<button type="button" class="button<?php echo $dev === 'desktop' ? ' button-primary' : ''; ?>" data-dev="<?php echo esc_attr( $dev ); ?>"><?php echo esc_html( $d[0] ); ?></button>
							<?php endforeach; ?>
						</span>
						<select id="ms-ks-url" aria-label="Page to preview">
							<?php foreach ( $pages as $label => $url ) : ?>
								<option value="<?php echo esc_url( add_query_arg( 'ms_kit_spot_preview', 1, $url ) ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="ms-ks-stage">
						<div class="ms-ks-frame">
							<iframe id="ms-ks-iframe" title="Preview" src="<?php echo esc_url( add_query_arg( 'ms_kit_spot_preview', 1, home_url( '/' ) ) ); ?>"></iframe>
							<div class="ms-ks-drag" hidden title="Drag to move the spotlight"><span class="ms-ks-dot"></span></div>
						</div>
					</div>
					<p class="description">Scroll and click through the preview like the site. To place the light by dragging, open the Spotlight panel and tick “Move the spotlight by dragging”.</p>
				</div>

				<div class="lm-panels">
					<?php $panel( 'on', 'Spotlight', 'The soft light behind every page' ); ?>
						<p><label><input type="checkbox" name="spot[on]" value="1" id="ms-ks-on" <?php checked( $s['on'] ); ?>> Show the spotlight</label></p>
						<p><label><input type="checkbox" id="ms-ks-dragmode"> Move the spotlight by dragging in the preview</label><br>
						<span class="description">While ticked, the preview can’t be scrolled or clicked: drag to place the light, then untick. It switches off when this panel is closed.</span></p>
					<?php $end(); ?>

					<?php $panel( 'pos', 'Position, size & angle', 'Separate for computers, tablets and phones: pick one above the preview' ); ?>
						<?php foreach ( $devices as $dev => $d ) : ?>
							<fieldset class="ms-ks-dev" data-dev="<?php echo esc_attr( $dev ); ?>"<?php echo $dev === 'desktop' ? '' : ' hidden'; ?>>
								<p><strong><?php echo esc_html( $d[0] ); ?></strong><?php echo $d[1] ? ' <span class="description">(' . esc_html( str_replace( [ '(max-width: ', ')' ], [ 'up to ', ' wide' ], $d[1] ) ) . ')</span>' : ''; ?></p>
								<?php
								$fields = [ [ 'Across', 0, 100, 'from the left edge of the screen' ], [ 'Down', 0, 100, 'from the top of the screen' ],
								            [ 'Bright core', 0, 100, 'how much stays at full light' ], [ 'Reach', 10, 250, 'where the edges reach the shade' ],
								            [ 'Light from', 0, 359, 'where the beam comes from, clockwise from straight above: 270 = left, 300 = 10 o’clock, 0 = above' ] ];
								foreach ( $fields as $i => [ $label, $min, $max, $help ] ) :
									$id = "ms-ks-{$dev}-{$i}"; ?>
									<div class="ms-ks-row">
										<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label>
										<input type="range" id="<?php echo esc_attr( $id ); ?>" name="spot[pos][<?php echo esc_attr( $dev ); ?>][<?php echo $i; ?>]" min="<?php echo $min; ?>" max="<?php echo $max; ?>" value="<?php echo (int) $s['pos'][ $dev ][ $i ]; ?>" data-unit="<?php echo [ 'vw', 'vh', '%', 'vw', '°' ][ $i ]; ?>">
										<output for="<?php echo esc_attr( $id ); ?>"></output>
										<span class="description"><?php echo esc_html( $help ); ?></span>
									</div>
								<?php endforeach; ?>
								<p><button type="button" class="button-link ms-ks-reset" data-dev="<?php echo esc_attr( $dev ); ?>">Back to the monospace default for <?php echo esc_html( strtolower( $d[0] ) ); ?>s</button></p>
							</fieldset>
						<?php endforeach; ?>
					<?php $end(); ?>

					<?php $panel( 'beam', 'Beam', 'The cone of light coming in at the angle above' ); ?>
						<div class="ms-ks-row">
							<label for="ms-ks-beam">Width</label>
							<input type="range" id="ms-ks-beam" name="spot[beam]" min="0" max="40" value="<?php echo (int) $s['beam']; ?>" data-unit="°">
							<output for="ms-ks-beam"></output>
							<span class="description">half the cone’s width; 0 = no beam, only the pool of light (monospace default 13)</span>
						</div>
					<?php $end(); ?>

					<?php $panel( 'colors', 'Colors', 'The light in the middle and the shade at the edges' ); ?>
						<div class="ms-ks-row ms-ks-crow">
							<label for="ms-ks-light">Light</label>
							<input type="color" id="ms-ks-light" name="spot[light]" value="<?php echo esc_attr( $s['colors'][0] ); ?>">
							<input type="range" id="ms-ks-light-a" name="spot[light_a]" min="0" max="100" value="<?php echo (int) $s['colors'][1]; ?>" data-unit="%" aria-label="Light strength">
							<output for="ms-ks-light-a"></output>
						</div>
						<div class="ms-ks-row ms-ks-crow">
							<label for="ms-ks-shade">Shade</label>
							<input type="color" id="ms-ks-shade" name="spot[shade]" value="<?php echo esc_attr( $s['colors'][2] ); ?>">
							<input type="range" id="ms-ks-shade-a" name="spot[shade_a]" min="0" max="100" value="<?php echo (int) $s['colors'][3]; ?>" data-unit="%" aria-label="Shade strength">
							<output for="ms-ks-shade-a"></output>
						</div>
						<p class="description">Strength: 0 = none, 100 = solid. A dark site wants a dark shade and a softer light.</p>
						<p><button type="button" class="button-link" id="ms-ks-reset-colors">Back to the monospace default colors</button></p>
					<?php $end(); ?>

					<p class="submit"><button class="button button-primary">Save</button></p>
				</div>
			</div>
		</form>
	</div>
	<style>
		.ms-ks-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(340px, 440px); gap: 24px; align-items: start; max-width: 1500px; }
		@media (max-width: 1100px) { .ms-ks-grid { grid-template-columns: minmax(0, 1fr); } }
		.ms-ks-preview { position: sticky; top: 46px; }
		@media (max-width: 1100px) { .ms-ks-preview { position: static; } }
		.ms-ks-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; margin-bottom: 8px; }
		.ms-ks-stage { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; padding: 10px; overflow: hidden; }
		.ms-ks-frame { position: relative; margin: 0 auto; overflow: hidden; border-radius: 3px; box-shadow: 0 0 0 1px #dcdcde; }
		.ms-ks-frame iframe { position: absolute; top: 0; left: 0; border: 0; transform-origin: 0 0; background: #fff; }
		.ms-ks-drag { position: absolute; inset: 0; cursor: crosshair; touch-action: none; }
		.ms-ks-drag[hidden], .ms-ks-dev[hidden] { display: none; }
		.ms-ks-dot { position: absolute; width: 22px; height: 22px; margin: -11px 0 0 -11px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 0 2px #2271b1, 0 1px 4px rgba(0,0,0,.4); background: rgba(34,113,177,.25); pointer-events: none; }
		.ms-ks-body { padding: 4px 16px 12px; }
		.ms-ks-dev { border: 0; padding: 0; margin: 0; }
		.ms-ks-row { display: grid; grid-template-columns: 90px minmax(0, 1fr) 52px; gap: 2px 10px; align-items: center; margin: 6px 0; }
		.ms-ks-row .description { grid-column: 2 / 4; margin-top: -4px; font-size: 12px; }
		.ms-ks-crow { grid-template-columns: 90px 44px minmax(0, 1fr) 44px; }
		.ms-ks-row output { font-variant-numeric: tabular-nums; color: #50575e; }
	</style>
	<script>
	(function () {
		var devices = <?php echo wp_json_encode( $devices ); ?>, defaults = <?php echo wp_json_encode( $def ); ?>,
			form = document.getElementById('ms-ks-form'), iframe = document.getElementById('ms-ks-iframe'),
			frame = document.querySelector('.ms-ks-frame'), stage = document.querySelector('.ms-ks-stage'),
			drag = document.querySelector('.ms-ks-drag'), dot = document.querySelector('.ms-ks-dot'), dev = 'desktop';
		function $(id) { return document.getElementById(id); }
		function pos(d) { return [0, 1, 2, 3, 4].map(function (i) { return +$('ms-ks-' + d + '-' + i).value; }); }
		function rgba(hex, a) { var h = hex.replace('#', ''); return 'rgba(' + parseInt(h.substr(0, 2), 16) + ', ' + parseInt(h.substr(2, 2), 16) + ', ' + parseInt(h.substr(4, 2), 16) + ', ' + Math.round(a) / 100 + ')'; }
		function chip(id, text, cls) { var c = $('ms-ks-chip-' + id); c.textContent = text; c.className = 'lm-chip' + (cls ? ' ' + cls : ''); }

		// The same CSS as monospace_kit_spot_css()
		function css() {
			if (!$('ms-ks-on').checked) return '';
			var b = 'html body[class]', half = +$('ms-ks-beam').value;
			var out = b + '{position:relative;z-index:0;--ms-spot-light:' + rgba($('ms-ks-light').value, $('ms-ks-light-a').value) + ';--ms-spot-shade:' + rgba($('ms-ks-shade').value, $('ms-ks-shade-a').value) + ';--ms-beam-half:' + half + 'deg;--ms-beam-len:110vmax}';
			Object.keys(devices).forEach(function (d) {
				var p = pos(d), rule = b + '{--ms-spot-x:' + p[0] + 'vw;--ms-spot-y:' + p[1] + 'vh;--ms-spot-core:' + p[2] + '%;--ms-spot-reach:' + p[3] + 'vw;--ms-beam-angle:' + p[4] + 'deg}';
				out += devices[d][1] ? '@media ' + devices[d][1] + '{' + rule + '}' : rule;
			});
			out += b + '::before{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;background:radial-gradient(circle at var(--ms-spot-x) var(--ms-spot-y),var(--ms-spot-light) var(--ms-spot-core),var(--ms-spot-shade) var(--ms-spot-reach))}';
			if (half > 0) {
				var mask = 'conic-gradient(from calc(var(--ms-beam-angle) + 180deg - var(--ms-beam-half)) at var(--sx) var(--sy),#000 0deg,rgba(0,0,0,.85) calc(var(--ms-beam-half) * .3),rgba(0,0,0,.1) calc(var(--ms-beam-half) * .8),transparent var(--ms-beam-half),rgba(0,0,0,.1) calc(var(--ms-beam-half) * 1.2),rgba(0,0,0,.85) calc(var(--ms-beam-half) * 1.7),#000 calc(var(--ms-beam-half) * 2)),radial-gradient(circle at var(--sx) var(--sy),transparent calc(var(--ms-beam-len) * .9),#000 calc(var(--ms-beam-len) + var(--ms-spot-reach) * 1.3))';
				out += b + '::after{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;--sx:calc(var(--ms-spot-x) + var(--ms-beam-len) * sin(var(--ms-beam-angle)));--sy:calc(var(--ms-spot-y) - var(--ms-beam-len) * cos(var(--ms-beam-angle)));background:var(--ms-spot-shade);-webkit-mask-image:' + mask + ';mask-image:' + mask + '}';
			}
			return out;
		}

		function apply() {
			try {
				var doc = iframe.contentDocument, st = doc && doc.getElementById('ms-kit-spotlight');
				if (st) st.textContent = css();
			} catch (x) {}
			var p = pos(dev);
			dot.style.left = p[0] + '%'; dot.style.top = p[1] + '%';
			// chips and readouts (listens to the form only, never to the chips)
			chip('on', $('ms-ks-on').checked ? 'Shown' : 'Not shown', $('ms-ks-on').checked ? 'is-on' : 'is-off');
			var own = Object.keys(devices).filter(function (d) { return pos(d).join() !== defaults.pos[d].join(); });
			chip('pos', own.length ? 'Your own: ' + own.map(function (d) { return devices[d][0].toLowerCase(); }).join(', ') : 'monospace default', own.length ? 'is-on' : '');
			chip('beam', +$('ms-ks-beam').value ? $('ms-ks-beam').value + '°' : 'No beam', +$('ms-ks-beam').value ? 'is-on' : 'is-empty');
			var c = [$('ms-ks-light').value, +$('ms-ks-light-a').value, $('ms-ks-shade').value, +$('ms-ks-shade-a').value];
			chip('colors', c.join() === defaults.colors.join() ? 'monospace default' : 'Your own', c.join() === defaults.colors.join() ? '' : 'is-on');
			form.querySelectorAll('input[type=range]').forEach(function (r) { var o = form.querySelector('output[for="' + r.id + '"]'); if (o) o.textContent = r.value + (r.dataset.unit || ''); });
		}
		form.addEventListener('input', apply);
		form.addEventListener('change', apply);
		document.querySelectorAll('.ms-ks-reset').forEach(function (b) {
			b.addEventListener('click', function () { defaults.pos[b.dataset.dev].forEach(function (v, i) { $('ms-ks-' + b.dataset.dev + '-' + i).value = v; }); apply(); });
		});
		$('ms-ks-reset-colors').addEventListener('click', function () {
			$('ms-ks-light').value = defaults.colors[0]; $('ms-ks-light-a').value = defaults.colors[1]; $('ms-ks-shade').value = defaults.colors[2]; $('ms-ks-shade-a').value = defaults.colors[3]; apply();
		});

		// The frame: the device's size, scaled to fit
		function size() {
			var d = devices[dev], w = d[2], h = d[3], room = stage.clientWidth - 20, k = Math.min(1, room / w, (window.innerHeight - 160) / h);
			iframe.style.width = w + 'px'; iframe.style.height = h + 'px'; iframe.style.transform = 'scale(' + k + ')';
			frame.style.width = Math.round(w * k) + 'px'; frame.style.height = Math.round(h * k) + 'px';
		}
		document.querySelectorAll('.ms-ks-bar [data-dev]').forEach(function (b) {
			b.addEventListener('click', function () {
				dev = b.dataset.dev;
				document.querySelectorAll('.ms-ks-bar [data-dev]').forEach(function (x) { x.classList.toggle('button-primary', x === b); });
				document.querySelectorAll('.ms-ks-dev').forEach(function (f) { f.hidden = f.dataset.dev !== dev; });
				size(); apply();
			});
		});
		window.addEventListener('resize', size);
		$('ms-ks-url').addEventListener('change', function () { iframe.src = this.value; });
		iframe.addEventListener('load', function () {
			apply();
			// keep links inside the preview in preview mode
			try {
				iframe.contentDocument.addEventListener('click', function (e) {
					var a = e.target.closest && e.target.closest('a[href]');
					if (!a || a.target === '_blank') return;
					var u = new URL(a.href, iframe.contentWindow.location.href);
					if (u.origin !== location.origin) { e.preventDefault(); return; }
					u.searchParams.set('ms_kit_spot_preview', '1'); e.preventDefault(); iframe.src = u.href;
				}, true);
			} catch (x) {}
		});

		// Drag mode: covers the frame while ticked; switches off when its panel closes
		var dragMode = $('ms-ks-dragmode'), dragPanel = dragMode.closest('details'), down = false;
		dragMode.addEventListener('change', function () { drag.hidden = !dragMode.checked; });
		dragPanel.addEventListener('toggle', function () { if (!dragPanel.open && dragMode.checked) { dragMode.checked = false; drag.hidden = true; } });
		function move(e) {
			var r = drag.getBoundingClientRect();
			$('ms-ks-' + dev + '-0').value = Math.round(Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)) * 100);
			$('ms-ks-' + dev + '-1').value = Math.round(Math.max(0, Math.min(1, (e.clientY - r.top) / r.height)) * 100);
			apply();
		}
		drag.addEventListener('pointerdown', function (e) { down = true; drag.setPointerCapture(e.pointerId); move(e); });
		drag.addEventListener('pointermove', function (e) { if (down) move(e); });
		drag.addEventListener('pointerup', function () { down = false; });
		drag.addEventListener('wheel', function (e) { try { iframe.contentWindow.scrollBy(0, e.deltaY); e.preventDefault(); } catch (x) {} }, { passive: false });

		size(); apply();
	})();
	</script>
	<?php
}

<?php

/**
 * The Quick Artwork Editor - part of monospace ART - Core (2026-10-19; until then the plugin
 * "monospace ART - Quick Artwork Editor", plugins/monospace-quick-product, v1.27). The Quick Artwork Editor box
 * on an artwork post: its WooCommerce product, price, availability, details and prints. Code name Quick Product:
 * namespace QuickProduct, qp_* fields, _qp_* meta - unchanged.
 */

namespace QuickProduct;

if (! defined('ABSPATH')) {
	exit;
}


/**
 * Main Plugin Class
 */
class Plugin
{

	private static $instance = null;

	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct()
	{
		add_action('plugins_loaded', [$this, 'check_dependencies']);
		add_action('add_meta_boxes', [$this, 'register_meta_box']);
		add_action('save_post_post', [$this, 'save_meta_fields'], 10, 3);
		add_action('wp_ajax_qp_create_update_product', [$this, 'ajax_create_update_product']);
	}

	public function check_dependencies()
	{
		if (! class_exists('WooCommerce')) {
			add_action('admin_notices', function () {
?>
				<div class="notice notice-error">
					<p><?php esc_html_e('Quick Artwork Editor requires WooCommerce to be installed and active.', 'quick-product'); ?></p>
				</div>
		<?php
			});
		}
	}

	public function register_meta_box()
	{
		add_meta_box(
			'quick_product',
			__('Quick Artwork Editor', 'quick-product'),
			[new MetaBox(), 'render'],
			'post',
			'side',
			'high'
		);
	}

	public function save_meta_fields($post_id, $post, $update)
	{
		$saver = new MetaSaver($post_id);
		$saver->save();
	}

	public function ajax_create_update_product()
	{
		$handler = new AjaxHandler();
		$handler->handle();
	}
}

/**
 * Meta Box Renderer
 *
 * Layout (2026-10-19): the box sizes itself to where it sits - one column in the editor's sidebar,
 * cards side by side in the Meta Boxes panel under the post (CSS container queries on .qp-box /
 * .qp-sec, so it follows the box's own width, not the window's). Field names and ids are unchanged:
 * MetaSaver, AjaxHandler and the theme's Prints box (prints-box.php) read them.
 */
class MetaBox
{

	private $post;
	private $data = [];

	// Attributes the AJAX handler refuses to save without (AjaxHandler::validate_required_fields())
	private $required_attributes = ['pa_format', 'pa_medium', 'pa_year-created'];

	public function render($post)
	{
		$this->post = $post;
		$this->load_data();

		wp_nonce_field('qp_meta_box', 'qp_nonce');

		$this->render_styles();

		echo '<div class="qp-box" id="qp-box">';

		// Find Matching Product - retired 2026-10-17 (no longer needed); its code stays below
		// $this->render_find_button();

		$this->render_header();
		$this->render_keybar();
		$this->render_status();

		echo '<div class="qp-grid">';

		// The sections are folding panels in the admin UI style (CLAUDE.md "Admin UI style", 2026-10-20): title, a
		// state chip, one line of what's in it; Details always open, Prints / Price card open when in use
		$s = $this->section_states();

		$this->section_open('details', __('Details', 'quick-product'), $s['details'], true);
		$this->render_attribute_dropdowns();
		$this->render_category_checkboxes();
		$this->section_close();

		$this->section_open('prints', __('Prints', 'quick-product'), $s['prints'], $s['prints'][0] === 'on');
		$this->render_print_fields();
		$this->section_close();

		$this->section_open('message', __('Price card', 'quick-product'), $s['card'], $s['card'][0] === 'on');
		$this->render_card_style_field();    // saved by the button below, not by the post save
		$this->render_card_message_fields(); // saved by the button below, not by the post save
		$this->section_close();

		echo '</div>';

		echo '</div>';

		$this->render_script();
	}

	/** Each section's state for its title line: [ state (on | warn | off | empty), chip text, one-line summary ] */
	private function section_states()
	{
		$names = ['pa_format' => __('format', 'quick-product'), 'pa_medium' => __('medium', 'quick-product'), 'pa_year-created' => __('year', 'quick-product')];
		$attrs = (array) ($this->data['attributes'] ?? []);
		$missing = array_values(array_intersect_key($names, array_filter($names, fn($k) => empty($attrs[$k]), ARRAY_FILTER_USE_KEY)));
		if (empty($this->data['categories'])) $missing[] = __('category', 'quick-product');
		$bits = [];
		foreach (['pa_medium', 'pa_surface', 'pa_size', 'pa_year-created'] as $tax) {
			if (empty($attrs[$tax])) continue;
			$term = get_term_by('slug', $attrs[$tax], $tax);
			$bits[] = $term ? $term->name : $attrs[$tax];
		}
		$details = $missing
			? ['warn', __('Missing: ', 'quick-product') . implode(', ', $missing), implode(' · ', $bits)]
			: ['on', __('Complete', 'quick-product'), implode(' · ', $bits)];

		// Prints: which ones, and hidden or shown on the site
		$id = $this->post->ID;
		$types = [];
		foreach (['framed' => __('Framed', 'quick-product'), 'unframed' => __('Unframed', 'quick-product')] as $type => $label) {
			if (! get_post_meta($id, $type === 'framed' ? '_framed_print_product_id' : '_print_product_id', true)) continue;
			$hidden = function_exists('monospace_prints_is_hidden') && monospace_prints_is_hidden($id, $type);
			$types[] = $label . ($hidden ? ' ' . __('(hidden)', 'quick-product') : '');
		}
		$prints = $types ? ['on', implode(' + ', $types), __('On the price card as tabs', 'quick-product')]
			: ['off', __('No prints', 'quick-product'), __('Add them here or in Artworks → Artwork Editor', 'quick-product')];

		// Price card: its own style / message, or the site's
		$style = function_exists('monospace_price_card_post_style') ? monospace_price_card_post_style($id) : '';
		$own_msg = (bool) get_post_meta($id, '_qp_card_message', true);
		$presets = function_exists('monospace_price_card_presets') ? monospace_price_card_presets() : [];
		$own = array_filter([$style ? ($presets[$style] ?? $style) : '', $own_msg ? __('own message', 'quick-product') : '']);
		$card = $own ? ['on', __('Own: ', 'quick-product') . implode(' · ', $own), __('This artwork only', 'quick-product')]
			: ['empty', __('Site settings', 'quick-product'), __('Style and message as set for the whole site', 'quick-product')];

		return ['details' => $details, 'prints' => $prints, 'card' => $card];
	}

	/** A section: a folding panel with its state on the title line */
	private function section_open($key, $title, array $state, $open)
	{
		[$st, $chip, $sub] = $state;
		echo '<details class="qp-sec qp-sec--' . esc_attr($key) . '"' . ($open ? ' open' : '') . '>'
			. '<summary class="qp-sec-head"><span class="qp-sec-title">' . esc_html($title) . '</span>'
			. '<span class="qp-chip is-' . esc_attr($st) . '">' . esc_html($chip) . '</span>'
			. ($sub !== '' ? '<span class="qp-sec-sub">' . esc_html($sub) . '</span>' : '')
			. '</summary><div class="qp-sec-body">';
	}

	private function section_close()
	{
		echo '</div></details>';
	}

	private function load_data()
	{
		$product_id = $this->linked_product_id();

		if (! $product_id) {
			// No product linked yet – set your temporary defaults
			$this->data = [
				'product_id'     => '',
				'product_name'   => '',                // optional default name
				'product_price'  => '',             // default price
				'sku'            => '',
				'attributes'     => [
					'pa_format'  => '',       // default format slug
					'pa_medium'  => '',         // default medium slug
					'pa_surface' => '',          // default surface slug
					'pa_size'    => '',             // default size slug
					'pa_year-created'   => date('Y'),         // default  year
				],
				'categories'     => [],                 // default categories handled below
				'availability'   => 'available',       // default availability
				'gallery_name'   => '',
				'gallery_url'    => '',
			];

			// Optional: assign default category “Original” if it exists
			$original = get_term_by('name', 'Original', 'product_cat');
			if ($original) {
				$this->data['categories'] = [$original->term_id];
			}

			return;
		}

		// If product exists, load real data
		$this->data = [
			'product_id'    => $product_id,
			'product_name'  => '',
			'product_price' => '',
			'sku'           => '',
			'attributes'    => [],
			'categories'    => [],
			'availability'  => 'available',
			'gallery_name'  => '',
			'gallery_url'   => '',
		];

		if (function_exists('wc_get_product')) {
			$this->load_from_product($product_id);
		}
	}


	/**
	 * The product linked to this post: its saved _qp_product_id, if that product
	 * still exists. The [price_card] shortcode reads the same value.
	 */
	private function linked_product_id()
	{
		$product_id = intval(get_post_meta($this->post->ID, '_qp_product_id', true));
		if ($product_id <= 0) {
			return 0;
		}

		if (function_exists('wc_get_product')) {
			return wc_get_product($product_id) ? $product_id : 0;
		}

		return $product_id;
	}

	private function render_find_button()
		{
			?>
			<div class="qp-field" style="margin-bottom:12px;">
				<button type="button" id="qp-find-match" class="button" style="width:100%; margin-bottom:6px;">
					<?php esc_html_e('Find Matching Product', 'quick-product'); ?>
				</button>
				<div id="qp-find-result" style="font-size:13px;color:#444;display:none;margin-top:6px;"></div>
			</div>
		<?php
		}

		private function load_from_product($product_id)
	{
		$product = wc_get_product($product_id);
		if (! $product) {
			return;
		}

		// Update with actual product data
		$this->data['product_name'] = $product->get_name();
		$this->data['product_price'] = $product->get_regular_price();
   		$this->data['sku'] = $product->get_sku();

		// Get product attributes
		$product_attributes = $product->get_attributes();
		$loaded_attributes = [];

		foreach ($product_attributes as $attribute) {
			if ($attribute->is_taxonomy()) {
				$taxonomy = $attribute->get_taxonomy();
				$terms = wp_get_post_terms($product_id, $taxonomy, ['fields' => 'all']);
				if (! is_wp_error($terms) && ! empty($terms)) {
					// Store the slug, not the name
					$loaded_attributes[$taxonomy] = $terms[0]->slug;
				}
			}
		}

		$this->data['attributes'] = $loaded_attributes;

		// Get product categories
		$categories = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
		if (! is_wp_error($categories)) {
			$this->data['categories'] = $categories;
		}

		// Get gallery info
		$this->data['gallery_name'] = get_post_meta($product_id, 'painting_gallery_name', true) ?: '';
		$this->data['gallery_url']  = get_post_meta($product_id, 'painting_gallery_url', true) ?: '';

		// Get stock quantity
		$stock_qty = $product->get_stock_quantity() ?? 0;

		// Get availability status (ACF stores it as post meta)
		$availability = get_post_meta($product_id, 'painting_availability_status', true);

		// If stock is 0, force "sold", else use saved availability
		if ($stock_qty <= 0) {
			$this->data['availability'] = 'sold';
		} else {
			$this->data['availability'] = $availability ?: 'available';
		}
	}

	/** Also used by the theme's Artworks > New artwork */
	public static function availability_options()
	{
		return [
			'available'        => __('Available', 'quick-product'),
			'reserved'         => __('Reserved', 'quick-product'),
			'sold'             => __('Sold', 'quick-product'),
			'private'          => __('Private Collection', 'quick-product'),
			'gallery'          => __('At Gallery', 'quick-product'),
			'gallery-reserved' => __('At Gallery (QR Purchase Only)', 'quick-product'),
		];
	}

	private function render_styles()
	{
	?>
		<style>
			.qp-box {
				container: qp-box / inline-size;
				--qp-line: #e0e0e0;
				--qp-muted: #757575;
				--qp-text: #1e1e1e;
				font-size: 13px;
				color: var(--qp-text);
				padding-top: 4px;
			}

			.qp-box *,
			.qp-box *::before,
			.qp-box *::after {
				box-sizing: border-box;
			}

			/* Header: status pill, product number, links */
			.qp-head {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: 6px 10px;
				padding-bottom: 12px;
			}

			.qp-head-id {
				font-weight: 600;
			}

			/* The SKU's number, big - how the owner identifies a piece (render_header()) */
			.qp-head-num {
				font-size: 30px;
				font-weight: 700;
				line-height: 1;
				letter-spacing: .02em;
				font-variant-numeric: tabular-nums;
				color: #1d2327;
			}

			.qp-head-num[hidden] {
				display: none;
			}

			/* Product number and its Edit link, together at the right */
			.qp-head-product {
				display: flex;
				align-items: baseline;
				gap: 10px;
				margin-left: auto;
			}

			.qp-head-product a {
				font-size: 12px;
				text-decoration: none;
			}

			.qp-pill {
				display: inline-flex;
				align-items: center;
				gap: 5px;
				padding: 1px 9px 1px 7px;
				border-radius: 999px;
				font-size: 11px;
				font-weight: 600;
				line-height: 20px;
				white-space: nowrap;
				background: #f0f0f1;
				color: #50575e;
			}

			.qp-pill::before {
				content: "";
				width: 7px;
				height: 7px;
				border-radius: 50%;
				background: currentColor;
			}

			/* Status colours, shared by the pill and the availability select */
			.qp-box [data-status] { --qp-st-bg: #f0f0f1; --qp-st-bd: #8c8f94; --qp-st-fg: #50575e; }
			.qp-box [data-status="available"] { --qp-st-bg: #edfaef; --qp-st-bd: #00a32a; --qp-st-fg: #00631f; }
			.qp-box [data-status="reserved"] { --qp-st-bg: #fcf9e8; --qp-st-bd: #dba617; --qp-st-fg: #6e4e00; }
			.qp-box [data-status="sold"] { --qp-st-bg: #f0f6fc; --qp-st-bd: #2271b1; --qp-st-fg: #135e96; }
			.qp-box [data-status="private"] { --qp-st-bg: #fcf0f1; --qp-st-bd: #d63638; --qp-st-fg: #8a2424; }
			.qp-box [data-status="gallery"] { --qp-st-bg: #f5effc; --qp-st-bd: #8a4fd1; --qp-st-fg: #5b2a99; }
			.qp-box [data-status="gallery-reserved"] { --qp-st-bg: #fdf1e7; --qp-st-bd: #e26f12; --qp-st-fg: #8a3f00; }

			.qp-pill[data-status] { background: var(--qp-st-bg); color: var(--qp-st-fg); }

			/* Sections: stacked with a rule in the sidebar, cards when there's room */
			.qp-grid {
				display: grid;
			}

			.qp-sec {
				container: qp-sec / inline-size;
				min-width: 0;
				border-top: 1px solid var(--qp-line);
				padding: 12px 0 2px;
			}

			/* Title line: title, state chip, one line of what's in it - click to fold (admin UI style) */
			.qp-sec-head {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: 2px 8px;
				margin: 0 0 10px;
				cursor: pointer;
				list-style: none;
			}

			.qp-sec-head::-webkit-details-marker { display: none; }
			.qp-sec-head::before { content: "▸"; color: var(--qp-muted); width: 8px; font-size: 10px; }
			.qp-sec[open] > .qp-sec-head::before { content: "▾"; }
			.qp-sec:not([open]) { padding-bottom: 0; align-self: start; } /* folded: only as tall as its title line */
			.qp-sec:not([open]) > .qp-sec-head { margin-bottom: 10px; }

			.qp-chip { font-size: 11px; line-height: 1.6; padding: 0 7px; border-radius: 9px; background: #f0f0f1; color: #50575e; white-space: nowrap; }
			.qp-chip.is-on { background: #e7f0fa; color: #135e96; }
			.qp-chip.is-warn { background: #fcf0f1; color: #8a2424; }
			.qp-chip.is-off { background: #f6f7f7; color: #8c8f94; }

			.qp-sec-sub {
				flex-basis: 100%;
				padding-left: 16px;
				font-size: 11px;
				color: var(--qp-muted);
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}

			.qp-box .qp-sec-title {
				margin: 0;
				padding: 0;
				font-size: 11px;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: .06em;
				color: var(--qp-muted);
			}

			.qp-sec-help,
			.qp-help {
				font-size: 11px;
				line-height: 1.45;
				color: var(--qp-muted);
				margin: 4px 0 0;
			}

			.qp-sec-help {
				margin: -4px 0 10px;
			}

			/* Fields */
			.qp-field {
				margin: 0 0 10px;
				min-width: 0;
			}

			.qp-box label,
			.qp-label-row {
				display: block;
				margin: 0 0 4px;
				font-size: 12px;
				font-weight: 600;
				line-height: 1.4;
			}

			.qp-label-row {
				display: flex;
				justify-content: space-between;
				align-items: baseline;
				gap: 6px;
			}

			.qp-label-row label {
				margin: 0;
			}

			.qp-label-row a {
				font-size: 11px;
				font-weight: 400;
				text-decoration: none;
				white-space: nowrap;
			}

			.qp-req {
				color: #d63638;
				font-weight: 400;
			}

			.qp-box input[type="text"],
			.qp-box input[type="number"],
			.qp-box input[type="url"],
			.qp-box select {
				width: 100%;
				max-width: none;
				min-height: 32px;
				margin: 0;
			}

			.qp-box input[readonly] {
				background: #f6f7f7;
			}

			.qp-field.is-invalid input,
			.qp-field.is-invalid select {
				border-color: #d63638;
				box-shadow: 0 0 0 1px #d63638;
			}

			/* Key fields: title, price, availability */
			.qp-box .qp-key label {
				font-size: 13px;
			}

			.qp-box .qp-key input[type="text"],
			.qp-box .qp-key select {
				min-height: 40px;
				font-size: 16px;
				font-weight: 600;
			}

			.qp-box #qp-availability {
				border-left-width: 4px;
			}

			.qp-box #qp-availability[data-status] { background-color: var(--qp-st-bg); border-left-color: var(--qp-st-bd); color: var(--qp-st-fg); }

			/* SKU line under the title */
			.qp-sku {
				display: flex;
				flex-wrap: wrap;
				align-items: center;
				gap: 4px 2px;
				margin-top: 6px;
				font-size: 12px;
				min-height: 24px;
			}

			.qp-sku-label {
				font-size: 10px;
				font-weight: 600;
				letter-spacing: .08em;
				color: var(--qp-muted);
			}

			.qp-box .qp-sku-code,
			.qp-box .qp-sku-copy {
				display: inline-flex;
				align-items: center;
				gap: 4px;
				margin: 0;
				padding: 1px 6px;
				border: 1px solid transparent;
				border-radius: 4px;
				background: none;
				color: #2271b1;
				cursor: pointer;
				font: 12px/20px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
				letter-spacing: .02em;
			}

			.qp-box .qp-sku-code.is-empty {
				color: var(--qp-muted);
				font-family: inherit;
				font-style: italic;
				letter-spacing: 0;
			}

			.qp-box .qp-sku-code:hover,
			.qp-box .qp-sku-code:focus-visible,
			.qp-box .qp-sku-copy:hover,
			.qp-box .qp-sku-copy:focus-visible {
				border-color: #c3c4c7;
				background: #fff;
				color: #135e96;
				outline: none;
			}

			.qp-sku .dashicons {
				width: 14px;
				height: 14px;
				font-size: 14px;
				line-height: 14px;
			}

			.qp-sku-code .dashicons {
				opacity: .4;
				transition: opacity .15s;
			}

			.qp-sku-code:hover .dashicons,
			.qp-sku-code:focus-visible .dashicons {
				opacity: .9;
			}

			.qp-box .qp-sku [hidden] {
				display: none;
			}

			.qp-box .qp-sku-copy {
				padding: 1px 4px;
				color: var(--qp-muted);
			}

			.qp-box .qp-sku-copy.is-done {
				color: #00a32a;
			}

			.qp-box .qp-sku input[type="text"] {
				flex: 1;
				width: auto;
				min-width: 160px;
				max-width: 240px;
				min-height: 26px;
				padding: 0 6px;
				font: 12px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
			}

			.qp-money {
				position: relative;
			}

			.qp-money::before {
				content: "$";
				position: absolute;
				left: 10px;
				top: 50%;
				transform: translateY(-50%);
				color: var(--qp-muted);
				pointer-events: none;
			}

			.qp-box .qp-money input {
				padding-left: 22px;
			}

			.qp-key .qp-money::before {
				font-size: 16px;
				font-weight: 600;
			}

			.qp-row-2,
			.qp-attrs {
				display: grid;
				grid-template-columns: minmax(0, 1fr);
				column-gap: 10px;
			}

			/* The Prints box adds its "Create / link prints" link after the framed field */
			.qp-row-2 > p {
				grid-column: 1 / -1;
				margin: -4px 0 10px !important;
				font-size: 12px;
			}

			.qp-sub {
				border-left: 2px solid var(--qp-line);
				padding-left: 10px;
				margin-bottom: 10px;
			}

			/* Reserved purchase link */
			.qp-reserved {
				background: #f0f6fc;
				border: 1px solid #c5d9ed;
				border-radius: 4px;
				padding: 10px;
				margin: 0 0 10px;
			}

			.qp-box .qp-reserved label {
				color: #135e96;
			}

			.qp-copy-row {
				display: flex;
				gap: 6px;
			}

			.qp-box .qp-copy-row input {
				flex: 1;
				min-width: 0;
				font-size: 11px;
				background: #fff;
				cursor: text;
			}

			.qp-copy-row .button {
				flex: none;
			}

			/* "Show prints" switch */
			.qp-prints-sale {
				padding-top: 2px;
			}

			.qp-box label.qp-switch {
				display: flex;
				align-items: center;
				gap: 8px;
				margin: 0;
				font-size: 13px;
				cursor: pointer;
			}

			.qp-box .qp-switch input[type="checkbox"] {
				-webkit-appearance: none;
				appearance: none;
				flex: none;
				position: relative;
				width: 32px;
				min-width: 32px;
				height: 18px;
				margin: 0;
				border: 0;
				border-radius: 9px;
				background: #c3c4c7;
				cursor: pointer;
				transition: background .15s;
			}

			.qp-box .qp-switch input[type="checkbox"]::before {
				content: "";
				position: absolute;
				top: 2px;
				left: 2px;
				float: none;
				width: 14px;
				height: 14px;
				margin: 0;
				border-radius: 50%;
				background: #fff;
				box-shadow: 0 1px 2px rgba(0, 0, 0, .2);
				transition: transform .15s;
			}

			.qp-box .qp-switch input[type="checkbox"]:checked {
				background: #00a32a;
			}

			.qp-box .qp-switch input[type="checkbox"]:checked::before {
				transform: translateX(14px);
			}

			.qp-box .qp-switch input[type="checkbox"]:disabled {
				opacity: .5;
			}

			/* Price card message, one row per tab */
			.qp-cm {
				padding: 8px 0;
				border-top: 1px dashed var(--qp-line);
			}

			.qp-cm:first-of-type {
				border-top: 0;
				padding-top: 0;
			}

			.qp-cm-head {
				display: grid;
				grid-template-columns: minmax(0, 1fr);
				gap: 4px;
			}

			.qp-cm-custom {
				display: grid;
				grid-template-columns: minmax(0, 1fr);
				gap: 6px;
				margin-top: 6px;
			}

			/* Key bar: title, price, availability, button - stacked, button first */
			.qp-keybar {
				display: grid;
				grid-template-columns: minmax(0, 1fr);
				column-gap: 10px;
			}

			.qp-actions {
				order: -1;
				margin: 0 0 12px;
			}

			.qp-box .qp-actions .button {
				width: 100%;
				justify-content: center;
				text-align: center;
			}

			.qp-dirty {
				flex-basis: 100%;
				font-size: 12px;
				color: #8a6100;
			}

			.qp-dirty[hidden] {
				display: none;
			}

			#qp-status {
				margin: 0 0 12px;
				padding: 8px 10px;
				border-radius: 3px;
				font-size: 12px;
				line-height: 1.5;
			}

			#qp-status.success {
				background-color: #edfaef;
				border-left: 3px solid #00a32a;
			}

			#qp-status.error {
				background-color: #fcf0f1;
				border-left: 3px solid #d63638;
			}

			/* A section with room: pairs side by side */
			@container qp-sec (min-width: 300px) {
				.qp-attrs {
					grid-template-columns: repeat(2, minmax(0, 1fr));
				}
			}

			@container qp-sec (min-width: 360px) {
				.qp-row-2 {
					grid-template-columns: repeat(2, minmax(0, 1fr));
				}

				.qp-cm-head {
					grid-template-columns: 110px minmax(0, 1fr);
					align-items: center;
				}

				.qp-box .qp-cm-head label {
					margin: 0;
				}

				.qp-cm-custom {
					grid-template-columns: minmax(0, 1fr) 100px;
					margin-left: 114px;
				}
			}

			/* Under the post: cards, two across */
			@container qp-box (min-width: 640px) {
				.qp-head {
					padding-bottom: 14px;
				}

				.qp-grid {
					grid-template-columns: repeat(2, minmax(0, 1fr));
					grid-template-areas: "details prints" "message message";
					gap: 14px;
				}

				.qp-sec {
					border: 1px solid var(--qp-line);
					border-radius: 6px;
					padding: 14px 14px 4px;
					background: #fff;
				}

				.qp-sec--details { grid-area: details; }
				.qp-sec--prints { grid-area: prints; }
				.qp-sec--message { grid-area: message; }

				.qp-dirty {
					flex-basis: auto;
				}

				/* Key bar: its own band - title on top, then price, availability, button */
				.qp-keybar {
					grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
					grid-template-areas: "title title title" "price avail button";
					align-items: start;
					margin: 0 0 14px;
					padding: 12px 14px;
					border: 1px solid var(--qp-line);
					border-radius: 6px;
					background: #f6f7f7;
				}

				.qp-keybar .qp-field {
					margin: 0 0 10px;
				}

				.qp-key--title { grid-area: title; }
				.qp-key--price { grid-area: price; }
				.qp-key--avail { grid-area: avail; }

				.qp-keybar .qp-key > label {
					line-height: 18px;
				}

				/* Button level with the fields: label height (18px) + its margin (4px) */
				.qp-keybar .qp-actions {
					grid-area: button;
					margin: 0 0 10px;
					padding-top: 22px;
				}

				/* Gallery fields / reserved link: full width under the fields */
				.qp-keybar .qp-sub,
				.qp-keybar .qp-reserved {
					grid-column: 1 / -1;
					margin: 2px 0 10px;
				}

				.qp-keybar .qp-sub {
					padding-left: 0;
					border-left: 0;
				}

				.qp-keybar .qp-sub-grid {
					display: grid;
					grid-template-columns: repeat(2, minmax(0, 1fr));
					column-gap: 10px;
				}

				.qp-keybar .qp-sub .qp-field {
					margin: 0;
				}

				.qp-box .qp-keybar .qp-actions .button {
					width: auto;
					min-width: 160px;
					min-height: 40px;
				}
			}

			/* Room for all of it on one line */
			@container qp-box (min-width: 900px) {
				.qp-keybar {
					grid-template-columns: minmax(0, 1fr) 150px 240px auto;
					grid-template-areas: "title price avail button";
				}

				.qp-keybar .qp-key,
				.qp-keybar .qp-actions {
					margin: 0;
				}

				.qp-keybar .qp-sub,
				.qp-keybar .qp-reserved {
					margin: 12px 0 0;
				}
			}

			/* Message wide enough for one line per tab: label, mode, text, colour */
			@container qp-sec (min-width: 640px) {
				.qp-cm {
					display: flex;
					align-items: center;
					gap: 6px;
				}

				.qp-cm-head {
					flex: 0 0 300px;
				}

				.qp-cm-custom {
					flex: 1;
					margin: 0;
				}
			}
		</style>
	<?php
	}

	private function render_header()
	{
		$product_id = (int) $this->data['product_id'];
		$options    = $this->availability_options();
		$selected   = $this->data['availability'] ?? 'available';
		$status     = $product_id ? $selected : 'new';
		$label      = $product_id ? ($options[$selected] ?? $selected) : __('Not created yet', 'quick-product');
	?>
		<div class="qp-head">
			<input type="hidden" name="qp_product_id" id="qp-product-id" value="<?php echo esc_attr($this->data['product_id']); ?>">
			<?php $num = preg_match('/(\d+)\D*$/', (string) $this->data['sku'], $m) ? (ltrim($m[1], '0') ?: '0') : ''; // MNSPC-2026-P-0426 -> 426 ?>
			<span class="qp-head-num" id="qp-head-num" title="<?php esc_attr_e('SKU number', 'quick-product'); ?>"<?php echo $num === '' ? ' hidden' : ''; ?>><?php echo esc_html($num); ?></span>
			<span class="qp-pill" id="qp-pill" data-status="<?php echo esc_attr($status); ?>"><?php echo esc_html($label); ?></span>
			<span class="qp-head-product">
				<span class="qp-head-id" id="qp-head-id"><?php
					echo $product_id
						? esc_html(sprintf(__('Product #%d', 'quick-product'), $product_id))
						: esc_html__('No product linked', 'quick-product');
				?></span>
				<a id="qp-edit-link" href="<?php echo $product_id ? esc_url(get_edit_post_link($product_id)) : '#'; ?>" target="_blank" <?php echo $product_id ? '' : 'hidden'; ?>><?php esc_html_e('Edit product ↗', 'quick-product'); ?></a>
			</span>
			<span class="qp-dirty" id="qp-dirty" hidden><?php esc_html_e('Changes not sent to the product yet', 'quick-product'); ?></span>
		</div>
	<?php
	}

	/**
	 * Title, price, availability and the button: one line across the top under the post, stacked
	 * (button first) in the sidebar. At the top so it's reachable when the box is only partly on screen.
	 */
	private function render_keybar()
	{
	?>
		<div class="qp-keybar">
			<div class="qp-field qp-key qp-key--title">
				<label for="qp-product-name"><?php esc_html_e('Title', 'quick-product'); ?> <span class="qp-req">*</span></label>
				<input type="text"
					name="qp_product_name"
					id="qp-product-name"
					data-qp-required="<?php esc_attr_e('Title', 'quick-product'); ?>"
					value="<?php echo esc_attr($this->data['product_name']); ?>"
					placeholder="<?php echo esc_attr(get_the_title($this->post) ?: __('Enter product name', 'quick-product')); ?>">
				<?php $this->render_sku(); ?>
			</div>

			<div class="qp-field qp-key qp-key--price">
				<label for="qp-product-price"><?php esc_html_e('Price', 'quick-product'); ?> <span class="qp-req" id="qp-price-req">*</span></label>
				<div class="qp-money">
					<input type="text"
						name="qp_product_price"
						id="qp-product-price"
						inputmode="decimal"
						data-qp-required="<?php esc_attr_e('Product Price', 'quick-product'); ?>"
						data-qp-optional-when="<?php echo esc_attr(implode(' ', function_exists('monospace_price_optional_statuses') ? monospace_price_optional_statuses() : [])); ?>"
						value="<?php echo esc_attr($this->data['product_price']); ?>"
						placeholder="<?php esc_attr_e('0.00', 'quick-product'); ?>">
				</div>
			</div>

			<?php $this->render_availability_field(); ?>
			<?php $this->render_button(); ?>
			<?php $this->render_gallery_fields(); ?>
			<?php $this->render_reserved_link(); ?>
		</div>
	<?php
	}

	/**
	 * SKU as a small line under the title: click the code to edit it (the qp_sku field, hidden until
	 * then - it still submits), copy button beside it. Blank = generated on Create / Update.
	 */
	private function render_sku()
	{
		$sku = (string) $this->data['sku'];
	?>
		<div class="qp-sku">
			<span class="qp-sku-label"><?php esc_html_e('SKU', 'quick-product'); ?></span>
			<button type="button" class="qp-sku-code<?php echo $sku === '' ? ' is-empty' : ''; ?>" id="qp-sku-edit" title="<?php esc_attr_e('Edit SKU', 'quick-product'); ?>"
				data-empty="<?php esc_attr_e('auto-generated on save', 'quick-product'); ?>"><?php echo $sku === '' ? esc_html__('auto-generated on save', 'quick-product') : esc_html($sku); ?><span class="dashicons dashicons-edit" aria-hidden="true"></span></button>
			<button type="button" class="qp-sku-copy" id="qp-sku-copy" title="<?php esc_attr_e('Copy SKU', 'quick-product'); ?>" aria-label="<?php esc_attr_e('Copy SKU', 'quick-product'); ?>"<?php echo $sku === '' ? ' hidden' : ''; ?>><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>
			<input type="text"
				name="qp_sku"
				id="qp-sku"
				hidden
				aria-label="<?php esc_attr_e('SKU', 'quick-product'); ?>"
				value="<?php echo esc_attr($sku); ?>"
				placeholder="<?php esc_attr_e('Blank = auto-generated', 'quick-product'); ?>">
		</div>
	<?php
	}

	private function render_attribute_dropdowns()
	{
		if (! function_exists('wc_get_attribute_taxonomies')) {
			return;
		}

		$attribute_taxonomies = wc_get_attribute_taxonomies();

		if (empty($attribute_taxonomies)) {
		?>
			<p class="qp-sec-help">
				<?php esc_html_e('No global product attributes found. Create them in Products → Attributes.', 'quick-product'); ?>
			</p>
		<?php
			return;
		}

		// Attributes that belong to Printful print products, not to the original artwork
		$print_only = ['pa_color'];

		echo '<div class="qp-attrs">';
		foreach ($attribute_taxonomies as $tax) {
			$taxonomy = wc_attribute_taxonomy_name($tax->attribute_name);
			if (in_array($taxonomy, $print_only, true)) {
				continue;
			}
			$selected_value = $this->data['attributes'][$taxonomy] ?? '';

			$this->render_single_attribute_dropdown($taxonomy, $tax->attribute_label, $selected_value);
		}
		echo '</div>';
		$this->render_detail_suggestions_script();
	}

	private function render_category_checkboxes()
	{
		$categories = get_terms([
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		]);

		if (is_wp_error($categories) || empty($categories)) {
			return;
		}

		// Find "Original" category ID for default
		$original_id = 0;
		foreach ($categories as $cat) {
			if (strtolower($cat->name) === 'original') {
				$original_id = $cat->term_id;
				break;
			}
		}

		// Set default if no categories selected
		$selected_categories = ! empty($this->data['categories'])
			? $this->data['categories']
			: ($original_id ? [$original_id] : []);

		// Hidden, not removed: the checkboxes still submit, so saves keep the
		// default (Original) or the product's existing categories.
		?>
		<div class="qp-field" style="display: none;">
			<label><?php esc_html_e('Product Categories', 'quick-product'); ?></label>
			<div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 8px; background: #fff;">
				<?php foreach ($categories as $category) : ?>
					<label style="display: block; margin-bottom: 6px; font-weight: normal;">
						<input type="checkbox"
							name="qp_categories[]"
							value="<?php echo esc_attr($category->term_id); ?>"
							<?php checked(in_array($category->term_id, $selected_categories)); ?>>
						<?php echo esc_html($category->name); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
	<?php
	}

	private function render_availability_field()
	{
		$selected = $this->data['availability'] ?? 'available';

	?>
		<div class="qp-field qp-key qp-key--avail">
			<label for="qp-availability"><?php esc_html_e('Availability', 'quick-product'); ?></label>
			<select name="qp_availability" id="qp-availability" data-status="<?php echo esc_attr($selected); ?>">
				<?php foreach ($this->availability_options() as $value => $label) : ?>
					<option value="<?php echo esc_attr($value); ?>"
						<?php selected($selected, $value); ?>>
						<?php echo esc_html($label); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

	<?php
	}

	private function render_gallery_fields()
	{
		$selected         = $this->data['availability'] ?? 'available';
		$gallery_statuses = ['gallery', 'gallery-reserved'];
		$show_gallery     = in_array($selected, $gallery_statuses) ? '' : 'display:none;';
	?>
		<div id="qp-gallery-fields" class="qp-sub" style="<?php echo esc_attr($show_gallery); ?>">
			<div class="qp-sub-grid">
				<div class="qp-field">
					<label for="qp-gallery-name"><?php esc_html_e('Gallery Name', 'quick-product'); ?></label>
					<input type="text"
						name="qp_gallery_name"
						id="qp-gallery-name"
						value="<?php echo esc_attr($this->data['gallery_name'] ?? ''); ?>"
						placeholder="<?php esc_attr_e('e.g. Fountain Art Fair', 'quick-product'); ?>">
				</div>
				<div class="qp-field">
					<label for="qp-gallery-url"><?php esc_html_e('Gallery URL', 'quick-product'); ?></label>
					<input type="url"
						name="qp_gallery_url"
						id="qp-gallery-url"
						value="<?php echo esc_attr($this->data['gallery_url'] ?? ''); ?>"
						placeholder="https://">
				</div>
			</div>
		</div>
	<?php
	}

	/**
	 * The Printful print product IDs - each adds a tab to the price card - and the "Prints for sale" switch.
	 */
	private function render_print_fields()
	{
		$fields = [
			'print_product_id'        => __('Unframed print ID', 'quick-product'),
			'framed_print_product_id' => __('Framed print ID', 'quick-product'),
		];
	?>
		<p class="qp-sec-help"><?php esc_html_e('Printful product IDs. Each one adds a tab to the price card.', 'quick-product'); ?></p>
		<div class="qp-row-2">
			<?php foreach ($fields as $key => $label) :
				$value = get_post_meta($this->post->ID, '_' . $key, true);
				$id    = 'qp-' . str_replace('_', '-', $key);
			?>
				<div class="qp-field">
					<div class="qp-label-row">
						<label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
						<?php if ($value && get_post($value)) : ?>
							<a href="<?php echo esc_url(get_edit_post_link($value)); ?>" target="_blank"><?php esc_html_e('Edit ↗', 'quick-product'); ?></a>
						<?php endif; ?>
					</div>
					<input type="number"
						name="qp_<?php echo esc_attr($key); ?>"
						id="<?php echo esc_attr($id); ?>"
						value="<?php echo esc_attr($value); ?>"
						placeholder="<?php esc_attr_e('None', 'quick-product'); ?>">
				</div>
			<?php endforeach; ?>
		</div>
		<?php $this->render_prints_for_sale_toggle(); ?>
	<?php
	}

	/**
	 * "Prints for sale" switch (2026-10-17): pauses / resumes the artwork's prints at once (theme,
	 * commerce/prints/prints-pause.php - out of stock + out of the Print category, nothing deleted). Only
	 * shown when the theme has the feature and the artwork has a print.
	 */
	private function render_prints_for_sale_toggle()
	{
		if (! function_exists('monospace_prints_is_paused') || ! function_exists('monospace_prints_of_artwork') || ! monospace_prints_of_artwork($this->post->ID)) {
			return;
		}
		$paused    = monospace_prints_is_paused($this->post->ID);
		$print_cat = get_term_by('slug', 'print', 'category');
	?>
		<div class="qp-field qp-prints-sale"
			data-nonce="<?php echo esc_attr(wp_create_nonce('monospace_prints_pause')); ?>"
			data-post="<?php echo (int) $this->post->ID; ?>"
			data-cat="<?php echo $print_cat ? (int) $print_cat->term_id : 0; ?>">
			<label class="qp-switch">
				<input type="checkbox" id="qp-prints-for-sale" <?php checked(! $paused); ?>>
				<?php esc_html_e('Show prints', 'quick-product'); ?>
			</label>
			<p class="qp-help qp-prints-sale-note"><?php
				$partly = ! $paused && function_exists('monospace_prints_hidden') && monospace_prints_hidden($this->post->ID);
				echo $paused
				? esc_html__('Hidden - off the price card and the site. Nothing is deleted. Tick to show prints.', 'quick-product')
				: ($partly
					? esc_html(monospace_prints_hidden_words($this->post->ID) . ' - framed and unframed one by one: Artworks > Artwork Editor. Untick to hide both.')
					: esc_html__('Shown on the price card and the site. Untick to hide prints - at once, nothing is deleted.', 'quick-product')); ?></p>
		</div>
		<script>
			(function () {
				var box = document.currentScript.previousElementSibling, input = box.querySelector('#qp-prints-for-sale'), note = box.querySelector('.qp-prints-sale-note');
				input.addEventListener('change', function () {
					var sell = input.checked;
					if (!confirm(sell ? 'Show prints? They go back on sale on the price card and the site.' : 'Hide prints? They come off the price card and the site. Nothing is deleted - Show prints puts them back.')) { input.checked = !sell; return; }
					input.disabled = true;
					var body = new URLSearchParams({ action: 'monospace_prints_pause', op: sell ? 'show' : 'hide', post_id: box.dataset.post, _ajax_nonce: box.dataset.nonce });
					fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
						.then(function (r) { return r.json(); })
						.then(function (r) {
							if (!r.success) throw new Error(r.data || 'Error');
							note.textContent = r.data.message;
							input.checked = !r.data.paused;
							// Keep the editor's own category panel in step, or saving the post would undo it
							var cat = +box.dataset.cat, ed = window.wp && wp.data && wp.data.select('core/editor');
							if (cat && ed && ed.getEditedPostAttribute) {
								var cats = (ed.getEditedPostAttribute('categories') || []).filter(function (c) { return c !== cat; });
								if (!r.data.paused) cats.push(cat);
								wp.data.dispatch('core/editor').editPost({ categories: cats });
							}
						})
						.catch(function (e) { input.checked = !sell; note.textContent = e.message; })
						.finally(function () { input.disabled = false; });
				});
			})();
		</script>
	<?php
	}

	private function generate_reserved_key($product_id)
	{
		// Generate a secure hash based on product ID + secret salt
		$secret = defined('MONOSPACE_RESERVED_SECRET') ? MONOSPACE_RESERVED_SECRET : 'ms-art-reserved-2024';
		return substr(md5($product_id . $secret), 0, 16);
	}

	private function render_reserved_link()
	{
		$is_reserved = in_array(($this->data['availability'] ?? ''), ['reserved', 'gallery-reserved']);
		$product_id = $this->data['product_id'] ?? 0;
		$post_id = get_the_ID();

		if (!$product_id) {
			return; // No product yet, can't generate link
		}

		$post_url = get_permalink($post_id);
		$reserved_key = $this->generate_reserved_key($product_id);
		$reserved_url = add_query_arg('reserved_key', $reserved_key, $post_url);

		$display_style = $is_reserved ? '' : 'display: none;';
	?>
		<div id="qp-reserved-link-section" class="qp-reserved" style="<?php echo esc_attr($display_style); ?>">
			<label for="qp-reserved-url"><?php esc_html_e('Reserved purchase link', 'quick-product'); ?></label>
			<div class="qp-copy-row">
				<input type="text"
					id="qp-reserved-url"
					readonly
					value="<?php echo esc_attr($reserved_url); ?>"
					onclick="this.select();">
				<button type="button" id="qp-copy-reserved-link" class="button">
					<?php esc_html_e('Copy', 'quick-product'); ?>
				</button>
			</div>
			<p class="qp-help">
				<?php esc_html_e('Send this to the customer: it bypasses the "Reserved" status and shows the buy button.', 'quick-product'); ?>
			</p>
		</div>
	<?php
	}


	/** Sizes are grouped by orientation: the first size of each group opens it */
	/**
	 * Size dropdowns, everywhere (2026-10-20, owner's wish): Recent & frequent (added by each editor's script),
	 * then Landscape, Portrait, Square and Other - worked out from the size itself, so a new size lands in the
	 * right group without a list to keep up to date. W" × H": wider than tall = Landscape, taller = Portrait,
	 * equal = Square; anything else (Sketchbook page, the 2- / 3-piece 4" × 4" sets, Other) = Other.
	 */
	public static function size_group($name)
	{
		$name = html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8');
		if (! preg_match('/^\s*(\d+(?:\.\d+)?)\s*["″]\s*[×x]\s*(\d+(?:\.\d+)?)\s*["″]\s*$/u', $name, $m)) return __('Other', 'quick-product');
		$w = (float) $m[1];
		$h = (float) $m[2];
		return $w > $h ? __('Landscape', 'quick-product') : ($h > $w ? __('Portrait', 'quick-product') : __('Square', 'quick-product'));
	}

	/** An original's size (with inch marks) or a special one - not Printful's bare print sizes ("8×10", "5x7-2") */
	public static function is_original_size($name)
	{
		$name = html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8');
		return strpos($name, '"') !== false || strpos($name, '″') !== false || ! preg_match('/\d/', $name);
	}

	/** Sizes in dropdown order: Landscape, Portrait, Square, Other; large to small inside each (also New artwork) */
	public static function sort_size_terms($terms)
	{
		$rank = array_flip([__('Landscape', 'quick-product'), __('Portrait', 'quick-product'), __('Square', 'quick-product'), __('Other', 'quick-product')]);
		$area = function ($name) {
			return preg_match_all('/\d+(?:\.\d+)?/', html_entity_decode((string) $name, ENT_QUOTES, 'UTF-8'), $n) >= 2 ? (float) $n[0][0] * (float) $n[0][1] : 0.0;
		};
		$other = __('Other', 'quick-product');
		usort($terms, function ($a, $b) use ($rank, $area, $other) {
			$ga = self::size_group($a->name);
			return ($rank[$ga] <=> $rank[self::size_group($b->name)])
				?: ($ga === $other ? 0 : ($area($b->name) <=> $area($a->name)))   // Other: by name (2-piece, 3-piece, …)
				?: strnatcasecmp($a->name, $b->name);
		});
		return $terms;
	}

	/**
	 * "Recent & frequent" for a detail dropdown (2026-10-19, as in the Artwork Editor): the newest originals'
	 * values (up to 3), then the most used among originals - slugs, best first. Originals only: print
	 * products share pa_size and would put the print sizes on top. The picks made in either editor are added
	 * in the browser (localStorage msAeRecent, the script under the dropdowns).
	 */
	public static function detail_suggestions($taxonomy)
	{
		static $all = null;
		if ($all === null) {
			$all = get_transient('qp_detail_suggestions');
			if (! is_array($all)) {
				$all = [];
				$ids = get_posts(['post_type' => 'product', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids',
					'orderby' => 'date', 'order' => 'DESC',
					'tax_query' => [['taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => ['original']]]]);
				$taxes = ['pa_format', 'pa_medium', 'pa_surface', 'pa_size', 'pa_year-created'];
				$by = [];
				$found = $ids ? wp_get_object_terms($ids, $taxes, ['fields' => 'all_with_object_id']) : [];
				if (! is_wp_error($found)) foreach ($found as $t) $by[$t->taxonomy][$t->object_id] = $t->slug;
				foreach ($taxes as $tax) {
					$list = [];
					foreach ($ids as $id) { // newest first
						if (count($list) >= 3) break;
						$v = $by[$tax][$id] ?? '';
						if ($v !== '' && ! in_array($v, $list, true)) $list[] = $v;
					}
					$counts = array_count_values(array_values($by[$tax] ?? []));
					arsort($counts);
					foreach (array_keys($counts) as $v) { $v = (string) $v; if (! in_array($v, $list, true)) $list[] = $v; } // numeric slugs (years) come back as integers
					$all[$tax] = array_slice($list, 0, 6);
				}
				set_transient('qp_detail_suggestions', $all, 6 * HOUR_IN_SECONDS);
			}
		}
		return $all[$taxonomy] ?? [];
	}

	/**
	 * The "Recent & frequent" group at the top of each detail dropdown: the picks made last in this editor or
	 * the Artwork Editor (shared localStorage msAeRecent - the same browser, the same site), then the server's
	 * suggestions (detail_suggestions()), up to 6; a pick is remembered for both editors.
	 */
	private function render_detail_suggestions_script()
	{
	?>
		<script>
			(function () {
				var box = document.currentScript.parentNode;
				function picks() { try { return JSON.parse(localStorage.getItem('msAeRecent') || '{}'); } catch (e) { return {}; } }
				box.querySelectorAll('select.qp-attribute-select[data-suggest]').forEach(function (sel) {
					var tax = sel.dataset.taxonomy, known = {};
					Array.prototype.forEach.call(sel.options, function (o) { if (o.value && !(o.value in known)) known[o.value] = o.textContent.trim(); });
					var list = [];
					((picks()[tax] || []).concat(sel.dataset.suggest ? sel.dataset.suggest.split(',') : [])).forEach(function (v) {
						if (v && v in known && list.indexOf(v) < 0 && list.length < 6) list.push(v);
					});
					if (list.length) {
						var group = document.createElement('optgroup');
						group.label = <?php echo wp_json_encode(__('Recent & frequent', 'quick-product')); ?>;
						list.forEach(function (v) { var o = document.createElement('option'); o.value = v; o.textContent = known[v]; group.appendChild(o); });
						var rest = document.createElement('optgroup');
						rest.label = <?php echo wp_json_encode(__('All', 'quick-product')); ?>;
						var keep = sel.value;
						// Everything after "— Select —" goes under "All" (size keeps its own groups after it)
						Array.prototype.slice.call(sel.children, 1).forEach(function (c) { c.tagName === 'OPTGROUP' ? sel.appendChild(c) : rest.appendChild(c); });
						sel.insertBefore(group, sel.children[1] || null);
						if (rest.children.length) sel.insertBefore(rest, group.nextSibling);
						sel.value = keep;
					}
					sel.addEventListener('change', function () {
						if (!sel.value) return;
						try {
							var all = picks();
							all[tax] = [sel.value].concat((all[tax] || []).filter(function (x) { return x !== sel.value; })).slice(0, 3);
							localStorage.setItem('msAeRecent', JSON.stringify(all));
						} catch (e) {}
					});
				});
			})();
		</script>
	<?php
	}

	private function render_single_attribute_dropdown($taxonomy, $label, $selected)
	{
		$terms = get_terms([
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		]);

		if (is_wp_error($terms) || empty($terms)) {
			return;
		}

		$is_size = $taxonomy === 'pa_size';
		if ($is_size) {
			// Originals' sizes only - Printful's print sizes share the taxonomy - but never drop the one set now
			$terms = array_values(array_filter($terms, fn($t) => self::is_original_size($t->name) || $t->slug === $selected));
			$terms = self::sort_size_terms($terms);
		}
		$required = in_array($taxonomy, $this->required_attributes, true);
		$id       = 'qp-attr-' . sanitize_html_class($taxonomy);

	?>
		<div class="qp-field">
			<label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?><?php if ($required) echo ' <span class="qp-req">*</span>'; ?></label>
			<select name="qp_attributes[<?php echo esc_attr($taxonomy); ?>]"
				id="<?php echo esc_attr($id); ?>"
				class="qp-attribute-select"
				<?php if ($required) echo 'data-qp-required="' . esc_attr($label) . '"'; ?>
				data-suggest="<?php echo esc_attr(implode(',', self::detail_suggestions($taxonomy))); ?>"
				data-taxonomy="<?php echo esc_attr($taxonomy); ?>">
				<option value=""><?php esc_html_e('— Select —', 'quick-product'); ?></option>
				<?php
				$open = false;
				$group = null;
				foreach ($terms as $term) :
					if ($is_size && self::size_group($term->name) !== $group) :
						$group = self::size_group($term->name);
						if ($open) echo '</optgroup>';
						echo '<optgroup label="' . esc_attr($group) . '">';
						$open = true;
					endif;
				?>
					<option value="<?php echo esc_attr($term->slug); ?>"
						<?php selected($selected, $term->slug); ?>>
						<?php echo esc_html($term->name); ?>
					</option>
				<?php endforeach;
				if ($open) echo '</optgroup>'; ?>
			</select>
		</div>
	<?php
	}

	private function button_label($has_product)
	{
		return $has_product ? __('Update product', 'quick-product') : __('Create product', 'quick-product');
	}

	private function render_button()
	{
	?>
		<div class="qp-actions">
			<button type="button"
				id="qp-create-update"
				class="button button-primary button-large">
				<?php echo esc_html($this->button_label((bool) $this->data['product_id'])); ?>
			</button>
		</div>
	<?php
	}

	private function render_status()
	{
	?>
		<div id="qp-status" style="display: none;"></div>
	<?php
	}

	/**
	 * This artwork's price card preset (_ms_price_card_style, Core's price-card-presets.php): Full / Compact /
	 * One line / Badge, or the site's setting (Layout Manager > Appearance & Effects > Price card). Saved by
	 * the Create / Update product button, like the message below.
	 */
	private function render_card_style_field()
	{
		if (! function_exists('monospace_price_card_presets')) return;
		$current = monospace_price_card_post_style($this->post->ID);
		$styles  = monospace_price_card_styles('', 0);
		$presets = monospace_price_card_presets();
		$site    = $presets[$styles['desk']] . ($styles['phone'] !== $styles['desk'] ? ', ' . $presets[$styles['phone']] . ' on phones' : '');
	?>
		<p class="qp-cm-head">
			<label for="qp-card-style"><?php esc_html_e('Style', 'quick-product'); ?></label>
			<select id="qp-card-style">
				<option value=""><?php echo esc_html(sprintf(__('Site setting (%s)', 'quick-product'), $site)); ?></option>
				<?php foreach ($presets as $value => $label) : ?>
					<option value="<?php echo esc_attr($value); ?>" <?php selected($current, $value); ?>><?php echo esc_html($label); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
	<?php
	}

	/**
	 * Per-post override of the price card's message strip, one row per tab the card has.
	 * Saved by the Create / Update product button (AjaxHandler -> save_card_message(), since
	 * 2026-10-16 - not by the post save) as _qp_card_message; read by the theme's
	 * monospace_card_sales_message(). "Use site messages" = Sales Dashboard > Messages.
	 */
	private function render_card_message_fields()
	{
		$saved = (array) get_post_meta($this->post->ID, '_qp_card_message', true);
		$tabs  = ['original' => __('Original', 'quick-product')];
		if (get_post_meta($this->post->ID, '_framed_print_product_id', true)) {
			$tabs['framed'] = __('Framed Print', 'quick-product');
		}
		if (get_post_meta($this->post->ID, '_print_product_id', true)) {
			$tabs['unframed'] = __('Unframed Print', 'quick-product');
		}
		$modes  = ['site' => __('Use site messages', 'quick-product'), 'custom' => __('Custom text', 'quick-product'), 'none' => __('No message', 'quick-product')];
		$styles = ['green' => __('Green', 'quick-product'), 'red' => __('Red', 'quick-product'), 'blue' => __('Blue', 'quick-product'), 'gray' => __('Gray', 'quick-product')];
	?>
		<p class="qp-sec-help"><?php esc_html_e('Overrides the site messages for this painting. Saved with the product button at the top.', 'quick-product'); ?></p>
		<?php foreach ($tabs as $tab => $label) :
			$o    = $saved[$tab] ?? [];
			$mode = $o['mode'] ?? 'site';
			$id   = 'qp-cm-' . $tab;
		?>
			<div class="qp-cm">
				<div class="qp-cm-head">
					<label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label>
					<select name="qp_card_message[<?php echo esc_attr($tab); ?>][mode]" id="<?php echo esc_attr($id); ?>" class="qp-cm-mode">
						<?php foreach ($modes as $value => $text) : ?>
							<option value="<?php echo esc_attr($value); ?>" <?php selected($mode, $value); ?>><?php echo esc_html($text); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="qp-cm-custom"<?php echo $mode === 'custom' ? '' : ' style="display: none;"'; ?>>
					<input type="text" name="qp_card_message[<?php echo esc_attr($tab); ?>][text]" value="<?php echo esc_attr($o['text'] ?? ''); ?>" placeholder="<?php esc_attr_e('Message ({gallery} = gallery name)', 'quick-product'); ?>" aria-label="<?php echo esc_attr(sprintf(__('%s message', 'quick-product'), $label)); ?>">
					<select name="qp_card_message[<?php echo esc_attr($tab); ?>][style]" aria-label="<?php echo esc_attr(sprintf(__('%s message colour', 'quick-product'), $label)); ?>">
						<?php foreach ($styles as $value => $text) : ?>
							<option value="<?php echo esc_attr($value); ?>" <?php selected($o['style'] ?? 'green', $value); ?>><?php echo esc_html($text); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
		<?php endforeach; ?>
		<script>
			document.querySelectorAll('.qp-cm-mode').forEach(function (select) {
				select.addEventListener('change', function () {
					select.closest('.qp-cm').querySelector('.qp-cm-custom').style.display = select.value === 'custom' ? '' : 'none';
				});
			});
		</script>
	<?php
	}

	private function render_script()
	{
		$config = [
			'postId'   => (int) $this->post->ID,
			'statuses' => $this->availability_options(),
			'text'     => [
				'create'      => $this->button_label(false),
				'update'      => $this->button_label(true),
				'working'     => __('Saving…', 'quick-product'),
				'productNo'   => __('Product #%d', 'quick-product'),
				'missing'     => __('Missing required fields:', 'quick-product'),
				'error'       => __('Error:', 'quick-product'),
				'failed'      => __('Request failed. Please try again.', 'quick-product'),
				'copied'      => __('Copied', 'quick-product'),
				'noTitle'     => __('Unable to detect post title.', 'quick-product'),
				'searching'   => __('Searching…', 'quick-product'),
				'matched'     => __('Product matched and fields populated.', 'quick-product'),
				'noMatch'     => __('No exact product match found.', 'quick-product'),
				'findFailed'  => __('Search failed. See console for details.', 'quick-product'),
			],
		];
	?>
		<script>
			(function boot() {
				// Meta box markup can arrive before the block editor has loaded jQuery
				if (!window.jQuery) { setTimeout(boot, 50); return; }

				jQuery(function($) {
					'use strict';

					var qp = <?php echo wp_json_encode($config); ?>;
					var $box = $('#qp-box');
					if (!$box.length) return;

					var $button = $('#qp-create-update');
					var $status = $('#qp-status');
					var $dirty = $('#qp-dirty');

					function hasProduct() { return !!$('#qp-product-id').val(); }
					function setDirty(on) { $dirty.prop('hidden', !on); }
					function showStatus(type, html) { $status.removeClass('success error').addClass(type).html(html).show(); }

					// Price needed or not, by availability: the * shows only when it is; an empty price reads "--"
					function priceRule() {
						var $price = $('#qp-product-price'), optional = String($price.data('qp-optional-when') || '').split(' ');
						var free = optional.indexOf($('#qp-availability').val()) > -1;
						$('#qp-price-req').toggle(!free);
						$price.attr('placeholder', free ? '--' : '0.00');
						if (free) $price.closest('.qp-field').removeClass('is-invalid');
					}
					$('#qp-availability').on('change', priceRule);
					priceRule();

					function updatePill() {
						var $pill = $('#qp-pill');
						if (!hasProduct()) return; // stays "Not created yet"
						var value = $('#qp-availability').val();
						$pill.attr('data-status', value).text(qp.statuses[value] || value);
					}

					// -------------------------
					// Unsent changes + Enter = send
					// -------------------------
					$box.on('input change', 'input, select', function() {
						if (this.id === 'qp-prints-for-sale' || this.id === 'qp-reserved-url') return;
						$(this).closest('.qp-field').removeClass('is-invalid');
						setDirty(true);
					});

					// Enter in a field would submit the editor's meta box form; send the product instead
					$box.on('keydown', 'input:not([readonly]):not([type=checkbox])', function(e) {
						if (e.key === 'Enter') {
							e.preventDefault();
							$button.trigger('click');
						}
					});

					// -------------------------
					// FIND MATCH: click handler (button retired 2026-10-17)
					// -------------------------
					$('#qp-find-match').on('click', function(e) {
						e.preventDefault();

						var $result = $('#qp-find-result');
						$result.hide().removeClass('success error').text('');

						// Get title from Gutenberg store first (reliable), fallback to #title
						var postTitle = '';
						if (typeof wp !== 'undefined' && wp.data && wp.data.select) {
							try {
								postTitle = wp.data.select('core/editor').getEditedPostAttribute('title') || '';
							} catch (err) {
								postTitle = '';
							}
						}
						if (!postTitle || !postTitle.trim()) {
							var $classicTitle = $('#title');
							if ($classicTitle.length) {
								postTitle = $classicTitle.val() || '';
							}
						}
						postTitle = (postTitle || '').trim();

						if (!postTitle) {
							$result.addClass('error').text(qp.text.noTitle).show();
							return;
						}

						$result.removeClass('error success').text(qp.text.searching).show();

						$.post(ajaxurl, { action: 'qp_find_product', title: postTitle })
							.done(function(resp) {
								if (resp && resp.success && resp.data) {
									var d = resp.data;

									if (d.id) $('#qp-product-id').val(d.id);
									if (d.title !== undefined) $('#qp-product-name').val(d.title);
									if (d.price !== undefined) $('#qp-product-price').val(d.price);
									if (d.sku !== undefined) $('#qp-sku').val(d.sku).trigger('change');
									if (d.availability !== undefined) $('#qp-availability').val(d.availability);

									// Attributes: { taxonomy: slug, ... }
									if (d.attributes && typeof d.attributes === 'object') {
										$('.qp-attribute-select').each(function() {
											var $sel = $(this), taxonomy = $sel.data('taxonomy');
											if (taxonomy && d.attributes[taxonomy]) $sel.val(d.attributes[taxonomy]).trigger('change');
										});
									}

									// Categories: array of term IDs
									if (Array.isArray(d.categories)) {
										$('input[name="qp_categories[]"]').prop('checked', false);
										d.categories.forEach(function(termId) {
											$('input[name="qp_categories[]"][value="' + termId + '"]').prop('checked', true);
										});
									}

									$result.addClass('success').text(qp.text.matched).show();
								} else {
									$result.addClass('error').text(qp.text.noMatch).show();
								}
							})
							.fail(function() {
								$result.addClass('error').text(qp.text.findFailed).show();
								console.error('qp_find_product AJAX failed');
							});
					});

					// -------------------------
					// CREATE / UPDATE
					// -------------------------
					$button.on('click', function() {
						// CLIENT-SIDE VALIDATION - the same required fields the server checks
						var errors = [];
						$box.find('.qp-field.is-invalid').removeClass('is-invalid');

						$box.find('[data-qp-required]').each(function() {
							// The price may be empty for some availabilities (sold, private, gallery)
							var optional = String($(this).data('qp-optional-when') || '').split(' ');
							if (optional.indexOf($('#qp-availability').val()) > -1) return;
							if (!String($(this).val() || '').trim()) {
								errors.push($(this).data('qp-required'));
								$(this).closest('.qp-field').addClass('is-invalid');
							}
						});

						if ($('input[name="qp_categories[]"]:checked').length === 0) {
							errors.push('At least one Category');
						}

						if (errors.length > 0) {
							showStatus('error', '<strong>' + qp.text.missing + '</strong> ' + $('<div>').text(errors.join(', ')).html());
							$box.find('.qp-field.is-invalid :input').first().trigger('focus');
							return; // Stop here, don't send AJAX
						}

						// Collect attribute data
						var attributes = {};
						$('.qp-attribute-select').each(function() {
							var $select = $(this);
							var value = $select.val();
							if (value) {
								attributes[$select.data('taxonomy')] = value;
							}
						});

						// Collect category data
						var categories = [];
						$('input[name="qp_categories[]"]:checked').each(function() {
							categories.push($(this).val());
						});

						var data = {
							action: 'qp_create_update_product',
							nonce: $('#qp_nonce').val(),
							post_id: qp.postId,
							product_id: $('#qp-product-id').val(),
							product_name: $('#qp-product-name').val(),
							product_price: $('#qp-product-price').val(),
							sku: $('#qp-sku').val(),
							attributes: attributes,
							categories: categories,
							availability: $('#qp-availability').val(),
							gallery_name: $('#qp-gallery-name').val(),
							gallery_url: $('#qp-gallery-url').val(),
							print_product_id: $('#qp-print-product-id').val(),
							framed_print_product_id: $('#qp-framed-print-product-id').val(),
							card_message: {}
						};
						// Price card style (only when the field is there - Core's presets)
						if ($('#qp-card-style').length) data.card_style = $('#qp-card-style').val();

						// Price card message: qp_card_message[tab][field] -> card_message[tab][field]
						$('.qp-cm [name^="qp_card_message["]').each(function() {
							var m = this.name.match(/^qp_card_message\[(\w+)\]\[(\w+)\]$/);
							if (m) (data.card_message[m[1]] = data.card_message[m[1]] || {})[m[2]] = $(this).val();
						});

						// Disable button and show processing
						$button.prop('disabled', true).text(qp.text.working);
						$status.removeClass('success error').hide();

						$.post(ajaxurl, data)
							.done(function(response) {
								if (response.success) {
									showStatus('success', response.data.message);
									setDirty(false);

									// A new product: show its number and link
									if (response.data.product_id) {
										$('#qp-product-id').val(response.data.product_id);
										$('#qp-head-id').text(qp.text.productNo.replace('%d', response.data.product_id));
									}
									if (response.data.edit_url) {
										$('#qp-edit-link').attr('href', response.data.edit_url).prop('hidden', false);
									}
									if (response.data.sku) {
										$('#qp-sku').val(response.data.sku);
										showSku();
									}
									// The details set some categories: keep the editor's category panel in step,
									// or saving the post would put the old ones back
									var ed = window.wp && wp.data && wp.data.select('core/editor');
									if (response.data.categories && ed && ed.getEditedPostAttribute) {
										wp.data.dispatch('core/editor').editPost({ categories: response.data.categories });
									}
									updatePill();
								} else {
									showStatus('error', '<strong>' + qp.text.error + '</strong> ' + response.data);
								}
							})
							.fail(function() {
								showStatus('error', qp.text.failed);
							})
							.always(function() {
								$button.prop('disabled', false).text(hasProduct() ? qp.text.update : qp.text.create);
							});
					});

					// -------------------------
					// SKU line: click to edit, copy
					// -------------------------
					var $sku = $('#qp-sku'), $skuCode = $('#qp-sku-edit'), $skuCopy = $('#qp-sku-copy');

					function showSku() {
						var value = $.trim($sku.val());
						$skuCode.contents().first().replaceWith(document.createTextNode(value || $skuCode.data('empty')));
						$skuCode.toggleClass('is-empty', !value).prop('hidden', false);
						$skuCopy.prop('hidden', !value);
						$sku.prop('hidden', true);
						// The big number at the top left: the SKU's last digits, no leading zeros (MNSPC-2026-P-0426 -> 426)
						var num = value.match(/(\d+)\D*$/);
						$('#qp-head-num').text(num ? num[1].replace(/^0+(?=\d)/, '') : '').prop('hidden', !num);
					}

					$skuCode.on('click', function() {
						$sku.data('was', $sku.val()).prop('hidden', false).trigger('focus').trigger('select');
						$skuCode.prop('hidden', true);
						$skuCopy.prop('hidden', true);
					});
					$sku.on('blur change', showSku).on('keydown', function(e) {
						if (e.key === 'Escape') { $sku.val($sku.data('was')); showSku(); $skuCode.trigger('focus'); }
					});

					$skuCopy.on('click', function() {
						var value = $.trim($sku.val());
						function done() {
							$skuCopy.addClass('is-done').find('.dashicons').removeClass('dashicons-admin-page').addClass('dashicons-yes');
							setTimeout(function() { $skuCopy.removeClass('is-done').find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-admin-page'); }, 1500);
						}
						if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(value).then(done);
					});

					// -------------------------
					// Gallery / Reserved: Show/Hide fields based on availability
					// -------------------------
					$('#qp-availability').on('change', function() {
						var availability = $(this).val();
						$(this).attr('data-status', availability);
						$('#qp-reserved-link-section')[availability === 'reserved' || availability === 'gallery-reserved' ? 'slideDown' : 'slideUp'](200);
						$('#qp-gallery-fields')[availability === 'gallery' || availability === 'gallery-reserved' ? 'slideDown' : 'slideUp'](200);
					});

					// -------------------------
					// Copy Reserved Link Button
					// -------------------------
					$('#qp-copy-reserved-link').on('click', function(e) {
						e.preventDefault();
						var $copy = $(this), url = $('#qp-reserved-url').val(), label = $copy.text();

						function done() {
							$copy.text(qp.text.copied);
							setTimeout(function() { $copy.text(label); }, 2000);
						}

						if (navigator.clipboard && window.isSecureContext) {
							navigator.clipboard.writeText(url).then(done);
						} else {
							$('#qp-reserved-url').trigger('select');
							document.execCommand('copy');
							done();
						}
					});

				}); // DOM ready
			})();
		</script>
<?php
	}
}

/**
 * AJAX Handler
 */
class AjaxHandler
{

	public function handle()
	{
		// Verify nonce
		if (! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'qp_meta_box')) {
			wp_send_json_error(__('Security check failed.', 'quick-product'));
		}

		// Check permissions
		if (! current_user_can('edit_posts')) {
			wp_send_json_error(__('Insufficient permissions.', 'quick-product'));
		}

		// Validate post ID
		$post_id = intval($_POST['post_id'] ?? 0);
		if (! $post_id || get_post_type($post_id) !== 'post') {
			wp_send_json_error(__('Invalid post ID.', 'quick-product'));
		}

		// Sanitize input
		$data = $this->sanitize_input();

		// Validate required fields
		$errors = $this->validate_required_fields($data);
		if (!empty($errors)) {
			wp_send_json_error(
				sprintf(
					__('Missing required fields: %s', 'quick-product'),
					implode(', ', $errors)
				)
			);
		}


		// Create syncer and sync product
		$syncer = new ProductSyncer($post_id, $data);
		$result = $syncer->sync();

		if (is_wp_error($result)) {
			wp_send_json_error($result->get_error_message());
		}

		// Price card style - saved here only, like the message below
		if (isset($_POST['card_style']) && function_exists('monospace_price_card_set_post_style')) {
			monospace_price_card_set_post_style($post_id, sanitize_key(wp_unslash($_POST['card_style'])));
			if (function_exists('sg_cachepress_purge_cache')) sg_cachepress_purge_cache(get_permalink($post_id));
		}

		// Price card message override - saved here only, not with the post. Not sent (an editor
		// page loaded before this existed) = left as it is, not cleared.
		if (isset($_POST['card_message']) && is_array($_POST['card_message'])) {
			self::save_card_message($post_id, wp_unslash($_POST['card_message']));
			// No post save happens, so the page cache doesn't clear by itself
			if (function_exists('sg_cachepress_purge_cache')) {
				sg_cachepress_purge_cache(get_permalink($post_id));
			}
		}

		// Get the updated product to fetch fresh SKU
		$product = wc_get_product($result['product_id']);
		$sku = $product ? $product->get_sku() : '';

		// Edit link in message
		$edit_link = get_edit_post_link($result['product_id']);
		$message = sprintf(
			__('Product #%d %s successfully. <a href="%s" target="_blank">Edit Product →</a>', 'quick-product'),
			$result['product_id'],
			$result['action'],
			esc_url($edit_link)
		);

		wp_send_json_success([
			'message'    => $message,
			'product_id' => $result['product_id'],
			'sku'        => $sku,
			'action'     => $result['action'],
			'product_url' => get_permalink($result['product_id']),
			'edit_url'   => get_edit_post_link($result['product_id'], 'raw'),
			// The details may have changed the post's categories (the theme's artwork-categories.php)
			'categories' => array_map('intval', wp_get_post_categories($post_id)),
		]);
	}



	/**
	 * Save the price card message override as _qp_card_message: only tabs set to something other
	 * than "Use site messages"; none left = the meta is deleted.
	 */
	public static function save_card_message($post_id, $raw)
	{
		$override = [];
		foreach ($raw as $tab => $o) {
			if (! in_array($tab, ['original', 'framed', 'unframed'], true) || ! is_array($o)) continue;
			$mode = in_array($o['mode'] ?? '', ['custom', 'none'], true) ? $o['mode'] : 'site';
			$text = sanitize_text_field($o['text'] ?? '');
			if ($mode === 'site' || ($mode === 'custom' && $text === '')) continue;
			$override[$tab] = [
				'mode'  => $mode,
				'text'  => $text,
				'style' => in_array($o['style'] ?? '', ['green', 'red', 'blue', 'gray'], true) ? $o['style'] : 'green',
			];
		}
		$override ? update_post_meta($post_id, '_qp_card_message', $override) : delete_post_meta($post_id, '_qp_card_message');
	}

	private function sanitize_input()
	{
		return [
			'product_id'      => intval($_POST['product_id'] ?? 0),
			'product_name'    => sanitize_text_field(wp_unslash($_POST['product_name'] ?? '')),
			'product_price'   => sanitize_text_field(wp_unslash($_POST['product_price'] ?? '')),
	        'sku'             => sanitize_text_field(wp_unslash($_POST['sku'] ?? '')),
			'attributes'      => $this->sanitize_attributes($_POST['attributes'] ?? []),
			'categories'      => $this->sanitize_categories($_POST['categories'] ?? []),
			'availability'    => sanitize_text_field(wp_unslash($_POST['availability'] ?? 'available')),
			'gallery_name'    => sanitize_text_field(wp_unslash($_POST['gallery_name'] ?? '')),
			'gallery_url'     => esc_url_raw(wp_unslash($_POST['gallery_url'] ?? '')),
			'print_product_id' => intval($_POST['print_product_id'] ?? 0),
			'framed_print_product_id' => intval($_POST['framed_print_product_id'] ?? 0),
		];
	}

	private function sanitize_attributes($attributes)
	{
		if (! is_array($attributes)) {
			return [];
		}

		$sanitized = [];
		foreach ($attributes as $taxonomy => $value) {
			$sanitized[sanitize_text_field($taxonomy)] = sanitize_text_field($value);
		}

		return $sanitized;
	}

	private function sanitize_categories($categories)
	{
		if (! is_array($categories)) {
			return [];
		}

		return array_map('intval', $categories);
	}

	private function validate_required_fields($data)
	{
		$errors = [];

		// Product name is required
		if (empty(trim($data['product_name'] ?? ''))) {
			$errors[] = __('Product Name', 'quick-product');
		}

		// Product price: needed unless the availability lets it be empty (sold, private, gallery -
		// monospace_price_required()), and a number when given
		$price_needed = function_exists('monospace_price_required') ? monospace_price_required($data['availability'] ?? '') : true;
		if (trim($data['product_price'] ?? '') === '') {
			if ($price_needed) $errors[] = __('Product Price', 'quick-product');
		} elseif (!is_numeric(preg_replace('/[^\d\.]/', '', $data['product_price']))) {
			$errors[] = __('Product Price (must be a valid number)', 'quick-product');
		}

		// At least one category is required
		if (empty($data['categories'])) {
			$errors[] = __('At least one Category', 'quick-product');
		}

		// Availability is required
		if (empty($data['availability'])) {
			$errors[] = __('Availability Status', 'quick-product');
		}

		// Validate SKU uniqueness if provided
		if (!empty(trim($data['sku']))) {
			$existing_id = wc_get_product_id_by_sku($data['sku']);
			// If SKU exists and belongs to a different product
			if ($existing_id && $existing_id != $data['product_id']) {
				$errors[] = sprintf(
					__('SKU "%s" is already in use by product #%d', 'quick-product'),
					$data['sku'],
					$existing_id
				);
			}
		}

		// Validate attributes (optional but recommended)
		$required_attributes = ['pa_format', 'pa_medium', 'pa_year-created'];
		foreach ($required_attributes as $attr) {
			if (empty($data['attributes'][$attr])) {
				// Get friendly name
				$tax_obj = get_taxonomy($attr);
				$label = $tax_obj ? $tax_obj->labels->singular_name : $attr;
				$errors[] = $label;
			}
		}

		return $errors;
	}

}

/**
 * Product Syncer
 */
class ProductSyncer
{

	private $post_id;
	private $data;

	public function __construct($post_id, $data)
	{
		$this->post_id = $post_id;
		$this->data    = $data;
	}

	public function sync()
{
    if (! function_exists('wc_get_product')) {
        return new \WP_Error('no_wc', __('WooCommerce is not active.', 'quick-product'));
    }

    // Determine if creating or updating
    $is_update = ! empty($this->data['product_id']);

    if ($is_update) {
        $product = wc_get_product($this->data['product_id']);
        if (! $product) {
            return new \WP_Error('invalid_product', __('Product not found.', 'quick-product'));
        }
        $action = 'updated';

        // -----------------------------------
        // STOCK + AVAILABILITY AUTO LOGIC
        // -----------------------------------
        $availability = strtolower(trim($this->data['availability'] ?? 'available'));

        switch ($availability) {

            case 'sold':
                // Sold must be 0 and out of stock
                $product->set_manage_stock(true);
                $product->set_stock_quantity(0);
                $product->set_stock_status('outofstock');
                break;

            case 'private':
            case 'gallery':
            case 'gallery-reserved':
            case 'available':
                // All "available-ish" states must have stock >= 1 and be in stock
                $stock = (int) $product->get_stock_quantity();
                if ($stock < 1) {
                    $product->set_stock_quantity(1);
                }
                $product->set_manage_stock(true);
                $product->set_stock_status('instock');
                break;
        }

    } else {
        // New product logic unchanged
        $product = new \WC_Product_Simple();
        $product->set_status('publish');
        $product->set_manage_stock(true);
        $product->set_stock_quantity(1);
        $product->set_stock_status('instock');
        $product->set_sold_individually(true);

        // Set default shipping class to "Regular" (ID: 1360)
        $product->set_shipping_class_id(1360);

        $action = 'created';
    }

    // Set basic product data
    $this->set_product_data($product);

    // Set featured image
    $this->set_featured_image($product);

    // Save product
    $product_id = $product->save();

	// Generate SKU if it was blank
	if (!empty($this->data['_needs_sku_generation'])) {
		$generated_sku = $this->generate_sku();
		$product->set_sku($generated_sku);
		$product->save();
		// Update our data array so it gets saved to post meta
		$this->data['sku'] = $generated_sku;

		// Also send back via AJAX so the field updates
		if (isset($_POST['ajax'])) {
			wp_send_json_success([
				'product_id' => $product_id,
				'sku' => $generated_sku,
				'action' => $action,
				'message' => __('Product created with SKU: ' . $generated_sku, 'quick-product')
			]);
		}
	}

	// Set attributes (must be done after product is saved)
	$this->set_attributes($product_id, $action);

	// Set shipping class based on size (Oversized if 20x16 or larger)
	$this->set_shipping_class_by_size($product_id);

    // Link product back to post with custom field
    update_post_meta($product_id, '_related_post_id', $this->post_id);

    // Save availability so filter can read it
    update_post_meta($product_id, 'painting_availability_status', $this->data['availability']);

    // Save gallery info
    update_post_meta($product_id, 'painting_gallery_name', $this->data['gallery_name'] ?? '');
    update_post_meta($product_id, 'painting_gallery_url',  $this->data['gallery_url'] ?? '');

    // Save relationship in post meta
    update_post_meta($this->post_id, '_qp_product_id', $product_id);
    update_post_meta($this->post_id, '_qp_product_name', $this->data['product_name']);
    update_post_meta($this->post_id, '_qp_product_price', $this->data['product_price']);
	update_post_meta($this->post_id, '_qp_sku', $this->data['sku']);
    update_post_meta($this->post_id, '_qp_attributes', $this->data['attributes']);
    update_post_meta($this->post_id, '_qp_categories', $this->data['categories']);
    update_post_meta($this->post_id, '_qp_availability', $this->data['availability']);

    // Save print product IDs (unframed and framed)
    foreach (['print_product_id', 'framed_print_product_id'] as $key) {
        $print_id = monospace_print_parent_id(intval($this->data[$key] ?? 0));
        if ($print_id > 0) {
            update_post_meta($this->post_id, '_' . $key, $print_id);
            monospace_sync_print_product_link($this->post_id, $print_id);

            // The reproduction is its own product and needs its own R SKU.
            // Runs after the original has been saved, so the sequence sees it.
            $this->ensure_print_product_sku($print_id);
        } else {
            delete_post_meta($this->post_id, '_' . $key);
        }
    }

    // A new artwork, or changed details: the dropdowns' "Recent & frequent" suggestions are worked out again
    delete_transient('qp_detail_suggestions');

    return [
        'product_id' => $product_id,
        'action'     => $action,
    ];
}


	private function set_product_data($product)
	{
		// Use product name if provided, otherwise use post title
		$name = ! empty($this->data['product_name'])
			? $this->data['product_name']
			: get_the_title($this->post_id);

		$product->set_name($name);

		// Set price - empty = no price, for an availability that allows it (sold, private, gallery; the callers
		// check monospace_price_required() first)
		if (! empty($this->data['product_price'])) {
			$price = preg_replace('/[^\d\.]/', '', $this->data['product_price']);
			$product->set_regular_price($price);
		} elseif (function_exists('monospace_price_required') && ! monospace_price_required($this->data['availability'] ?? '')) {
			$product->set_regular_price('');
		}
		// Set SKU - auto-generate if blank, otherwise use manual
		$sku = trim($this->data['sku'] ?? '');

		if (empty($sku)) {
			// Flag for later auto-generation
			$this->data['_needs_sku_generation'] = true;
		} else {
			// Check if SKU is unique
			$existing_id = wc_get_product_id_by_sku($sku);
			if ($existing_id && $existing_id != $product->get_id()) {
				return new \WP_Error(
					'duplicate_sku',
					sprintf(__('SKU "%s" is already used by another product.', 'quick-product'), $sku)
				);
			}
			$product->set_sku($sku);
		}

	}

	private function set_featured_image($product)
	{
		// Get post's featured image
		$thumbnail_id = get_post_thumbnail_id($this->post_id);

		// If no featured image, try to create one from post content
		if (! $thumbnail_id) {
			$thumbnail_id = $this->create_featured_image_from_content();
		}

		// Always sync the image state - set it if exists, clear it if doesn't
		if ($thumbnail_id) {
			$product->set_image_id($thumbnail_id);
		} else {
			// Clear product image if post has none
			$product->set_image_id(0);
		}
	}

	private function create_featured_image_from_content()
	{
		$post = get_post($this->post_id);
		if (! $post) {
			return 0;
		}

		$content = $post->post_content;

		// Try to find image in various formats
		$image_id = 0;

		// Method 1: Look for wp-image-XXX class in img tags
		if (preg_match('/wp-image-(\d+)/', $content, $matches)) {
			$image_id = intval($matches[1]);
		}

		// Method 2: Look for attachment IDs in image blocks
		if (! $image_id && preg_match('/wp:image\s+{"id":(\d+)/', $content, $matches)) {
			$image_id = intval($matches[1]);
		}

		// Method 3: Extract image URL and find attachment
		if (! $image_id && preg_match('/<img[^>]+src=["\']([^"\']+)["\']/', $content, $matches)) {
			$image_url = $matches[1];
			$image_id = attachment_url_to_postid($image_url);
		}

		// Set as featured image if found
		if ($image_id && wp_attachment_is_image($image_id)) {
			set_post_thumbnail($this->post_id, $image_id);
			return $image_id;
		}

		return 0;
	}

	/**
	 * Work out the artwork-type letter that goes in the middle of the SKU.
	 *
	 *   P  Painting   D  Drawing   M  Miniature   O  Object   C  Custom order
	 *
	 * Checked most specific first, because the signals overlap: a 4" x 4"
	 * painting is a miniature (M, not P). Only type-carrying signals are
	 * consulted - the product categories the user ticked, the size and format
	 * attributes, and the blog post's own categories as a fallback.
	 *
	 * R (Reproduction) is deliberately not produced here. A print is its own
	 * product, separate from the original: the painting keeps its P and the
	 * linked Printful product gets the R, via ensure_print_product_sku().
	 *
	 * @return string A single uppercase letter.
	 */
	private function resolve_type_letter()
	{
		$attributes = $this->data['attributes'] ?? [];
		$size       = $attributes['pa_size'] ?? '';
		$format     = $attributes['pa_format'] ?? '';

		// Product categories ticked in the meta box, as slugs.
		$product_cats = [];
		foreach ((array) ($this->data['categories'] ?? []) as $term_id) {
			$term = get_term((int) $term_id, 'product_cat');
			if ($term && ! is_wp_error($term)) {
				$product_cats[] = $term->slug;
			}
		}

		// The blog post's own categories.
		$post_cats = wp_get_post_terms($this->post_id, 'category', ['fields' => 'slugs']);
		if (is_wp_error($post_cats)) {
			$post_cats = [];
		}

		// 1. Commissions.
		if (in_array('custom-order', $product_cats, true)) {
			return 'C';
		}

		// 2. Miniature is a size, so it outranks the format below.
		if ($size === '4x4' || in_array('miniature', $post_cats, true)) {
			return 'M';
		}

		// 3. The format attribute, falling back to the matching blog category
		//    when no format was picked (or "other" was).
		$by_slug = [
			'painting' => 'P',
			'drawing'  => 'D',
			'object'   => 'O',
		];

		if (isset($by_slug[$format])) {
			return $by_slug[$format];
		}

		foreach ($by_slug as $slug => $letter) {
			if (in_array($slug, $post_cats, true)) {
				return $letter;
			}
		}

		// 4. Unclassified. Deliberately not "O" - that means Object, and a
		//    silent collision would be worse than an obvious odd letter.
		return 'X';
	}

	/**
	 * Give the linked Printful product an R SKU if it hasn't got one.
	 *
	 * The reproduction is a separate product from the original, so it gets
	 * its own number: "Mudflats Sunset" stays P, "Mudflats Sunset - unframed
	 * print" becomes R. Uses the current year rather than the artwork's - a
	 * 2025 painting printed this year is a 2026 reproduction, which is how
	 * the existing R SKUs are numbered.
	 *
	 * Never touches a SKU that is already set.
	 *
	 * @param int $print_id The Printful product ID.
	 */
	private function ensure_print_product_sku($print_id)
	{
		if (! function_exists('wc_get_product')) {
			return;
		}

		$print_product = wc_get_product($print_id);
		if (! $print_product) {
			return;
		}

		if (trim((string) $print_product->get_sku()) !== '') {
			return;
		}

		$print_product->set_sku($this->generate_sku('R', date('Y')));
		$print_product->save();
	}

	/**
	 * Build the next SKU: MNSPC-<year>-<type>-<nnnn>.
	 *
	 * The number is global and sequential across every type, so it is always
	 * the highest existing MNSPC number plus one.
	 *
	 * @param string|null $letter Type letter; resolved from the artwork when null.
	 * @param string|null $year   Four-digit year; the artwork's year when null.
	 */
	private function generate_sku($letter = null, $year = null)
	{
		global $wpdb;

		// Year for SKU
		$year = $year ?: ($this->data['attributes']['pa_year-created'] ?? date('Y'));

		// Artwork type letter (P/D/M/O/C, or R when forced for a print)
		$format = $letter ?: $this->resolve_type_letter();

		// Find ALL existing SKUs with MNSPC prefix to get the highest number
		$pattern = $wpdb->esc_like('MNSPC-') . '%';
		$existing_skus = $wpdb->get_col($wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta}
			WHERE meta_key = '_sku'
			AND meta_value LIKE %s
			ORDER BY meta_value DESC",
			$pattern
		));

		// Find the highest 4-digit number across ALL SKUs
		$next_number = 1;
		if (!empty($existing_skus)) {
			foreach ($existing_skus as $sku) {
				// Extract the last 4 digits from any MNSPC SKU
				if (preg_match('/MNSPC-\d{4}-[A-Z]-(\d{4})$/', $sku, $matches)) {
					$number = intval($matches[1]);
					if ($number >= $next_number) {
						$next_number = $number + 1;
					}
				}
			}
		}

		// Build final SKU: MNSPC-YEAR-FORMAT-XXXX
		return sprintf('MNSPC-%s-%s-%04d', $year, $format, $next_number);
	}




	private function set_attributes($product_id)
	{
		if (!empty($this->data['attributes'])) {



			$attributes = [];
			$position = 0;

			foreach ($this->data['attributes'] as $taxonomy => $slug) {
				if (! taxonomy_exists($taxonomy)) {
					continue;
				}

				// Get the term by slug to ensure we're using existing terms
				$term = get_term_by('slug', $slug, $taxonomy);

				if (! $term) {
					// If term doesn't exist by slug, skip it
					// This prevents creating new terms accidentally
					continue;
				}

				// Set the term for this product using term ID
				wp_set_object_terms($product_id, [$term->term_id], $taxonomy);

				// Build attribute array for product meta
				$attribute = new \WC_Product_Attribute();
				$attribute->set_id(wc_attribute_taxonomy_id_by_name($taxonomy));
				$attribute->set_name($taxonomy);
				$attribute->set_options([$term->name]); // WooCommerce expects term names in options
				$attribute->set_position($position);
				$attribute->set_visible(true);
				$attribute->set_variation(false);

				$attributes[] = $attribute;
				$position++;
			}


			// Save attributes to product
			if (! empty($attributes)) {
				$product = wc_get_product($product_id);
				$product->set_attributes($attributes);
				$product->save();
			}
		}
		// Set product categories
		$this->set_categories($product_id);



		// Save availability status
		update_post_meta($product_id, 'painting_availability_status', $this->data['availability']);
	}

	private function set_shipping_class_by_size($product_id)
	{
		// Get the size attribute from the data
		$size_slug = $this->data['attributes']['pa_size'] ?? '';

		if (empty($size_slug)) {
			// No size set, keep default shipping class (Regular)
			return;
		}

		// Parse dimensions from size slug (e.g., "16x20", "20x16", "9x12")
		// Size slugs are typically formatted as WxH or HxW in inches
		if (preg_match('/(\d+)\s*x\s*(\d+)/i', $size_slug, $matches)) {
			$dim1 = (int) $matches[1];
			$dim2 = (int) $matches[2];

			// Check if either dimension is >= 20 AND the other is >= 16
			// This catches 20x16, 16x20, 24x18, etc.
			$is_oversized = ($dim1 >= 20 && $dim2 >= 16) || ($dim2 >= 20 && $dim1 >= 16);

			if ($is_oversized) {
				// Set to Oversized shipping class (ID: 1361)
				$product = wc_get_product($product_id);
				if ($product) {
					$product->set_shipping_class_id(1361);
					$product->save();
				}
			}
			// Otherwise, keep the default "Regular" shipping class (1360) that was set on creation
		}
	}

	private function set_categories($product_id)
	{
		if (empty($this->data['categories'])) {
			// If no categories selected, try to set "Original" as default
			$original = get_term_by('name', 'Original', 'product_cat');
			if ($original) {
				wp_set_object_terms($product_id, [$original->term_id], 'product_cat');
			}
			return;
		}

		wp_set_object_terms($product_id, $this->data['categories'], 'product_cat');
	}
}

/**
 * Meta Saver - Saves meta fields on post save
 */
class MetaSaver
{

	private $post_id;

	public function __construct($post_id)
	{
		$this->post_id = $post_id;
	}

	public function save()
	{
		if (! $this->should_save()) {
			return;
		}

		// Save print product IDs (unframed and framed)
		foreach (['print_product_id', 'framed_print_product_id'] as $key) {
			if (! isset($_POST['qp_' . $key])) {
				continue;
			}
			$print_id = monospace_print_parent_id(intval($_POST['qp_' . $key]));
			if ($print_id > 0) {
				update_post_meta($this->post_id, '_' . $key, $print_id);
				monospace_sync_print_product_link($this->post_id, $print_id);
			} else {
				delete_post_meta($this->post_id, '_' . $key);
			}
		}

		$fields = [
			'qp_product_id'    => '_qp_product_id',
			'qp_product_name'  => '_qp_product_name',
			'qp_product_price' => '_qp_product_price',
			'qp_sku'           => '_qp_sku',
		];

		// Empty fields are deleted, not stored: a stored _qp_product_id is what makes a post an artwork
		// (monospace_is_artwork()), so a regular post must not get an empty one
		foreach ($fields as $input => $meta_key) {
			if (isset($_POST[$input])) {
				$value = sanitize_text_field(wp_unslash($_POST[$input]));
				if ($input === 'qp_product_id' && ! intval($value)) {
					$value = '';
				}
				if ($value === '') {
					delete_post_meta($this->post_id, $meta_key);
				} else {
					update_post_meta($this->post_id, $meta_key, $value);
				}
			}
		}

		// Save attributes
		if (isset($_POST['qp_attributes']) && is_array($_POST['qp_attributes'])) {
			$attributes = array_map('sanitize_text_field', wp_unslash($_POST['qp_attributes']));
			update_post_meta($this->post_id, '_qp_attributes', $attributes);
		}

		// Save categories
		if (isset($_POST['qp_categories']) && is_array($_POST['qp_categories'])) {
			$categories = array_map('intval', $_POST['qp_categories']);
			update_post_meta($this->post_id, '_qp_categories', $categories);
		}

		// The price card message (qp_card_message) is not saved here - the Create / Update
		// Product button saves it (AjaxHandler::save_card_message())
	}

	private function should_save()
	{
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return false;
		}

		if (wp_is_post_revision($this->post_id)) {
			return false;
		}

		if (! current_user_can('edit_post', $this->post_id)) {
			return false;
		}

		return true;
	}
}

/**
 * Sync _related_post_id onto a print product so the Art Filter can find it, put the product in the
 * "print" product category and the post in the "print" post category (which lists it on
 * /category/print/ - added, the post's other categories stay).
 * Called from MetaSaver, ProductSyncer and monospace_keep_print_category().
 *
 * @param int $post_id       The blog post ID.
 * @param int $print_product_id The Printful variable product ID.
 */
function monospace_sync_print_product_link( $post_id, $print_product_id ) {
    if ( ! $post_id || ! $print_product_id ) return;
    update_post_meta( $print_product_id, '_related_post_id', $post_id );
    wp_set_object_terms( $print_product_id, 'print', 'product_cat' );

    $print_cat = get_term_by( 'slug', 'print', 'category' );
    if ( $print_cat && ! has_category( $print_cat->term_id, $post_id ) ) {
        wp_set_post_categories( $post_id, [ $print_cat->term_id ], true );
    }
}

/**
 * A Printful ID entered as one of its variations means the variable (parent) product - the card
 * and the "print" category work on the parent.
 */
function monospace_print_parent_id( $id ) {
    $post = $id ? get_post( $id ) : null;
    return ( $post && 'product_variation' === $post->post_type && $post->post_parent ) ? (int) $post->post_parent : (int) $id;
}

/**
 * Keep a linked print product in "print" whenever the product itself is saved or modified - e.g.
 * when Printful re-syncs it (Printful puts its products in custom-order). Linked = a post's
 * _print_product_id / _framed_print_product_id points at it. Runs after WooCommerce has written the
 * product's categories (woocommerce_new_product / woocommerce_update_product).
 */
function monospace_keep_print_category( $product_id ) {
    static $busy = false;
    if ( $busy || has_term( 'print', 'product_cat', $product_id ) ) return;

    global $wpdb;
    $post_id = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_print_product_id', '_framed_print_product_id') AND meta_value = %s LIMIT 1",
        (string) $product_id
    ) );
    if ( ! $post_id ) return;

    $busy = true;
    monospace_sync_print_product_link( $post_id, $product_id );
    $busy = false;
}
// This file is namespaced (QuickProduct): hook the function by its full name
add_action( 'woocommerce_new_product', __NAMESPACE__ . '\\monospace_keep_print_category', 20 );
add_action( 'woocommerce_update_product', __NAMESPACE__ . '\\monospace_keep_print_category', 20 );





add_action('wp_ajax_qp_find_product', function () {

	if (! current_user_can('edit_posts')) {
		wp_send_json_error('Insufficient permissions');
	}

	$title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';

	if (empty($title)) {
		wp_send_json_error('Missing title');
	}

	// Slug-based exact match
	$slug = sanitize_title($title);

	$product = get_page_by_path($slug, OBJECT, 'product');

	if (! $product) {
		wp_send_json_error('No product matched');
	}


	// Build response: id, title, price, availability, attributes (taxonomy => slug), categories (ids)
	$product_id = $product->ID;
	$wc_product = function_exists('wc_get_product') ? wc_get_product($product_id) : null;
	$price = $wc_product ? $wc_product->get_regular_price() : get_post_meta($product_id, '_regular_price', true);
	$sku = $wc_product ? $wc_product->get_sku() : get_post_meta($product_id, '_sku', true);  // ADD THIS LINE

	// availability from post meta (ACF or stored meta)
	$availability = get_post_meta($product_id, 'painting_availability_status', true) ?: '';

	// Attributes: collect taxonomy => first-term-slug
	$attributes = [];
	if ($wc_product) {
		$product_attributes = $wc_product->get_attributes();
		foreach ($product_attributes as $attribute) {
			if ($attribute->is_taxonomy()) {
				$taxonomy = $attribute->get_taxonomy();
				$terms = wp_get_post_terms($product_id, $taxonomy, array('fields' => 'slugs'));
				if (! is_wp_error($terms) && ! empty($terms)) {
					$attributes[$taxonomy] = $terms[0];
				}
			}
		}
	} else {
		// Fallback: inspect registered product taxonomies
		$taxonomies = get_object_taxonomies('product');
		foreach ($taxonomies as $taxonomy) {
			$terms = wp_get_post_terms($product_id, $taxonomy, array('fields' => 'slugs'));
			if (! is_wp_error($terms) && ! empty($terms)) {
				$attributes[$taxonomy] = $terms[0];
			}
		}
	}

	// Categories (term IDs)
	$categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'ids'));
	if (is_wp_error($categories)) {
		$categories = [];
	}

	wp_send_json_success(array(
		'id' => $product_id,
		'title' => get_the_title($product_id),
		'price' => $price,
	    'sku' => $sku,
		'availability' => $availability,
		'attributes' => $attributes,
		'categories' => $categories,
		'product_url' => get_permalink($product_id),
	));
});

// Initialize plugin
Plugin::instance();
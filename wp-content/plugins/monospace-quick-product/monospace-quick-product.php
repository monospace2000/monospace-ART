<?php

/**
 * Plugin Name: monospace ART - Quick Product Creator
 * Description: Link posts to WooCommerce products with a simple meta box, and edit product attributes.
 * Version: 1.20
 * Author: monospace
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 */

namespace QuickProduct;

if (! defined('ABSPATH')) {
	exit;
}

// Declare WooCommerce HPOS compatibility
add_action('before_woocommerce_init', function () {
	if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

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
					<p><?php esc_html_e('Quick Product requires WooCommerce to be installed and active.', 'quick-product'); ?></p>
				</div>
		<?php
			});
		}
	}

	public function register_meta_box()
	{
		add_meta_box(
			'quick_product',
			__('Quick Product', 'quick-product'),
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
 */
class MetaBox
{

	private $post;
	private $data = [];

	public function render($post)
	{
		$this->post = $post;
		$this->load_data();

		wp_nonce_field('qp_meta_box', 'qp_nonce');

		$this->render_styles();

		// <-- Insert the Find button at the top of the box
		$this->render_find_button();

		// Existing fields
		$this->render_fields();
		$this->render_button();
		$this->render_status();
		$this->render_script();
	}

	private function load_data()
	{
		$product_id = $this->extract_product_id_from_shortcode();

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


	private function extract_product_id_from_shortcode()
	{
		$content = $this->post->post_content;

		// Look for [painting_buy_button id="xxxx"] pattern
		if (preg_match('/\[painting_buy_button\s+id=["\']?(\d+)["\']?\]/', $content, $matches)) {
			$product_id = intval($matches[1]);

			// Return 0 if ID is 0 or negative
			if ($product_id <= 0) {
				return 0;
			}

			// Verify the product actually exists in WooCommerce
			if (function_exists('wc_get_product')) {
				$product = wc_get_product($product_id);
				// Return the ID only if the product exists
				return $product ? $product_id : 0;
			}

			return $product_id;
		}

		return 0;
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


	private function render_styles()
	{
	?>
		<style>
			.qp-field {
				margin-bottom: 12px;
			}

			.qp-field label {
				display: block;
				font-weight: 600;
				margin-bottom: 4px;
				font-size: 13px;
			}

			.qp-field input[type="text"],
			.qp-field input[type="number"],
			.qp-field select {
				width: 100%;
				padding: 4px 8px;
			}

			.qp-field input[readonly] {
				background-color: #f0f0f1;
				cursor: not-allowed;
			}

			#qp-status {
				padding: 8px;
				margin-top: 12px;
				border-radius: 3px;
			}

			#qp-status.success {
				background-color: #d7f0db;
				border-left: 3px solid #00a32a;
			}

			#qp-status.error {
				background-color: #fcf0f1;
				border-left: 3px solid #d63638;
			}
		</style>
	<?php
	}

	private function render_fields()
	{
	?>
		<div class="qp-field">
			<label><?php esc_html_e('Product ID', 'quick-product'); ?></label>
			<input type="number"
				name="qp_product_id"
				id="qp-product-id"
				value="<?php echo esc_attr($this->data['product_id']); ?>"
				placeholder="<?php esc_attr_e('Auto-generated', 'quick-product'); ?>"
				readonly>
		</div>

		  <div class="qp-field">
			<label><?php esc_html_e('SKU', 'quick-product'); ?></label>
			<input type="text"
				name="qp_sku"
				id="qp-sku"
				value="<?php echo esc_attr($this->data['sku']); ?>"
				placeholder="<?php esc_attr_e('Auto-generated if blank', 'quick-product'); ?>">
		</div>

		<div class="qp-field">
			<label><?php esc_html_e('Product Name', 'quick-product'); ?></label>
			<input type="text"
				name="qp_product_name"
				id="qp-product-name"
				value="<?php echo esc_attr($this->data['product_name']); ?>"
				placeholder="<?php esc_attr_e('Enter product name', 'quick-product'); ?>">
		</div>

		<?php $this->render_availability_field(); ?>
		<?php $this->render_reserved_link(); ?>



		<div class="qp-field">
			<label><?php esc_html_e('Product Price', 'quick-product'); ?></label>
			<input type="text"
				name="qp_product_price"
				id="qp-product-price"
				value="<?php echo esc_attr($this->data['product_price']); ?>"
				placeholder="<?php esc_attr_e('0.00', 'quick-product'); ?>">
		</div>

		<?php $this->render_category_checkboxes(); ?>


		<?php $this->render_attribute_dropdowns(); ?>
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
			<div class="qp-field">
				<p style="color: #666; font-size: 12px;">
					<?php esc_html_e('No global product attributes found. Create them in Products → Attributes.', 'quick-product'); ?>
				</p>
			</div>
		<?php
			return;
		}


		echo '<hr style="margin: 15px 0;">';
		echo '<p style="font-weight: 600; margin-bottom: 10px;">' . esc_html__('Product Attributes', 'quick-product') . '</p>';

		// Attributes that belong to Printful print products, not to the original artwork
		$print_only = ['pa_color'];

		foreach ($attribute_taxonomies as $tax) {
			$taxonomy = wc_attribute_taxonomy_name($tax->attribute_name);
			if (in_array($taxonomy, $print_only, true)) {
				continue;
			}
			$selected_value = $this->data['attributes'][$taxonomy] ?? '';

			$this->render_single_attribute_dropdown($taxonomy, $tax->attribute_label, $selected_value);
		}
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
		$availability_options = [
			'available'        => __('Available', 'quick-product'),
			'reserved'         => __('Reserved', 'quick-product'),
			'sold'             => __('Sold', 'quick-product'),
			'private'          => __('Private Collection', 'quick-product'),
			'gallery'          => __('At Gallery', 'quick-product'),
			'gallery-reserved' => __('At Gallery (QR Purchase Only)', 'quick-product'),
		];

		$selected = $this->data['availability'] ?? 'available';

	?>
		<div class="qp-field">
			<label><?php esc_html_e('Availability Status', 'quick-product'); ?></label>
			<select name="qp_availability" id="qp-availability" style="width: 100%;">
				<?php foreach ($availability_options as $value => $label) : ?>
					<option value="<?php echo esc_attr($value); ?>"
						<?php selected($selected, $value); ?>>
						<?php echo esc_html($label); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<?php
		$gallery_statuses = ['gallery', 'gallery-reserved'];
		$show_gallery     = in_array($selected, $gallery_statuses) ? '' : 'display:none;';
		?>
		<div id="qp-gallery-fields" style="<?php echo esc_attr($show_gallery); ?>">
			<div class="qp-field">
				<label><?php esc_html_e('Gallery Name', 'quick-product'); ?></label>
				<input type="text"
					name="qp_gallery_name"
					id="qp-gallery-name"
					value="<?php echo esc_attr($this->data['gallery_name'] ?? ''); ?>"
					placeholder="<?php esc_attr_e('e.g. Fountain Art Fair', 'quick-product'); ?>">
			</div>
			<div class="qp-field">
				<label><?php esc_html_e('Gallery URL', 'quick-product'); ?></label>
				<input type="url"
					name="qp_gallery_url"
					id="qp-gallery-url"
					value="<?php echo esc_attr($this->data['gallery_url'] ?? ''); ?>"
					placeholder="https://">
			</div>
		</div>

		<hr style="margin: 15px 0;">

		<div class="qp-field">
			<label><?php esc_html_e('Print Product ID', 'quick-product'); ?></label>
			<input type="number"
				name="qp_print_product_id"
				id="qp-print-product-id"
				value="<?php echo esc_attr(get_post_meta($this->post->ID, '_print_product_id', true)); ?>"
				placeholder="<?php esc_attr_e('Printful variable product ID', 'quick-product'); ?>">
			<p style="font-size:11px;color:#666;margin:4px 0 0 0;">Used by <code>[print_order_button]</code> shortcode.</p>
		</div>

		<div class="qp-field">
			<label><?php esc_html_e('Framed Print Product ID', 'quick-product'); ?></label>
			<input type="number"
				name="qp_framed_print_product_id"
				id="qp-framed-print-product-id"
				value="<?php echo esc_attr(get_post_meta($this->post->ID, '_framed_print_product_id', true)); ?>"
				placeholder="<?php esc_attr_e('Printful framed product ID', 'quick-product'); ?>">
			<p style="font-size:11px;color:#666;margin:4px 0 0 0;">Adds an Unframed / Framed switch to the print card.</p>
		</div>
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
		<div id="qp-reserved-link-section" class="qp-field" style="<?php echo esc_attr($display_style); ?>">
			<div style="background: #f0f6fc; border: 1px solid #0073aa; border-radius: 4px; padding: 12px; margin-top: 8px;">
				<label style="color: #0073aa; margin-bottom: 8px;">
					🔗 <?php esc_html_e('Reserved Purchase Link', 'quick-product'); ?>
				</label>
				<input type="text"
					id="qp-reserved-url"
					readonly
					value="<?php echo esc_attr($reserved_url); ?>"
					style="width: 100%; font-size: 11px; margin-bottom: 8px; background: #fff; cursor: text;"
					onclick="this.select();">
				<button type="button"
					id="qp-copy-reserved-link"
					class="button button-secondary"
					style="width: 100%;">
					<?php esc_html_e('Copy Link', 'quick-product'); ?>
				</button>
				<p style="font-size: 11px; color: #666; margin: 8px 0 0 0; line-height: 1.4;">
					<?php esc_html_e('Share this link with the customer to allow them to complete the purchase. The link bypasses the "Reserved" status and shows the buy button.', 'quick-product'); ?>
				</p>
			</div>
		</div>
	<?php
	}


	private function sort_size_terms($terms)
	{
		$order = [
			// Landscape, large to small
			'20" × 16"',
			'14" × 11"',
			'12" × 9"',
			'10" × 8"',
			'7" × 5"',
			'6" × 4"',
			// Portrait, large to small
			'24" × 36"',
			'20" × 30"',
			'18" × 24"',
			'16" × 20"',
			'12" × 18"',
			'12" × 16"',
			'11" × 14"',
			'9" × 12"',
			'8" × 10"',
			'5" × 7"',
			'4" × 6"',
			// Square, large to small
			'18" × 18"',
			'16" × 16"',
			'14" × 14"',
			'12" × 12"',
			'10" × 10"',
			'4" × 4"',
			// Special
			'Sketchbook page',
			'Other',
		];

		$order_map = array_flip($order);

		usort($terms, function($a, $b) use ($order_map) {
			$pos_a = $order_map[$a->name] ?? 999;
			$pos_b = $order_map[$b->name] ?? 999;
			return $pos_a - $pos_b;
		});

		return $terms;
	}

	private function render_single_attribute_dropdown($taxonomy, $label, $selected)
	{
		$terms = get_terms([
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		]);

		if ($taxonomy === 'pa_size') {
			$terms = $this->sort_size_terms($terms);
		}


		if (is_wp_error($terms) || empty($terms)) {
			return;
		}

	?>
		<div class="qp-field">
			<label><?php echo esc_html($label); ?></label>
			<select name="qp_attributes[<?php echo esc_attr($taxonomy); ?>]"
				class="qp-attribute-select"
				data-taxonomy="<?php echo esc_attr($taxonomy); ?>">
				<option value=""><?php esc_html_e('— Select —', 'quick-product'); ?></option>
				<?php
				if ($taxonomy === 'pa_size') :
					$separators = [
						'20" × 16"' => '— Landscape —',
						'24" × 36"' => '— Portrait —',
						'18" × 18"' => '— Square —',
						'Sketchbook page' => '— Other —',
					];
				endif;
				foreach ($terms as $term) :
					if ($taxonomy === 'pa_size' && isset($separators[$term->name])) :
						echo '<option disabled>─────────────────</option>';
						echo '<option disabled>' . esc_html($separators[$term->name]) . '</option>';
					endif;
				?>
					<option value="<?php echo esc_attr($term->slug); ?>"
						<?php selected($selected, $term->slug); ?>>
						<?php echo esc_html($term->name); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
	<?php
	}

	private function render_button()
	{
	?>
		<div class="qp-field">
			<button type="button"
				id="qp-create-update"
				class="button button-primary button-large"
				style="width: 100%;">
				<?php esc_html_e('Create / Update Product', 'quick-product'); ?>
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

	private function render_script()
	{
	?>
		<script>
			(function($) {
				'use strict';

				// -------------------------------------------------------
				// Buy button shortcode: stamp the product ID into the body
				//
				// The server already rewrote post_content, but the open editor
				// still holds the old copy and would write it back on the next
				// save. Apply the same rewrite to the editor's own state so the
				// two agree.
				// -------------------------------------------------------

				// Rewrite every [painting_buy_button] in `text` to point at
				// productId. Returns null when nothing changed.
				function stampShortcode(text, productId) {
					if (typeof text !== 'string' || text.indexOf('[painting_buy_button') === -1) {
						return null;
					}

					var changed = false;

					var out = text.replace(/\[painting_buy_button\b([^\]]*)\]/g, function(full, atts) {
						if (/\bid\s*=/.test(atts)) {
							// Replace the existing value, quoted or bare.
							atts = atts.replace(/\bid\s*=\s*("[^"]*"|'[^']*'|[^\s\]]*)/, 'id="' + productId + '"');
						} else {
							atts = atts.replace(/\s+$/, '') + ' id="' + productId + '"';
						}

						var rebuilt = '[painting_buy_button' + atts + ']';
						if (rebuilt !== full) {
							changed = true;
						}
						return rebuilt;
					});

					return changed ? out : null;
				}

				// Block editor: walk the tree and patch whichever attribute
				// carries the raw text (core/shortcode uses `text`, paragraph /
				// html / freeform use `content`).
				function stampBlockEditor(productId) {
					if (typeof wp === 'undefined' || !wp.data || !wp.data.select('core/block-editor')) {
						return false;
					}

					var select = wp.data.select('core/block-editor');
					var dispatch = wp.data.dispatch('core/block-editor');
					var touched = false;

					(function walk(blocks) {
						(blocks || []).forEach(function(block) {
							var attrs = block.attributes || {};
							var updates = {};

							['text', 'content'].forEach(function(key) {
								var value = attrs[key];
								// Newer Gutenberg hands back a RichTextData object here.
								if (value && typeof value === 'object' && typeof value.toString === 'function') {
									value = value.toString();
								}
								var next = stampShortcode(value, productId);
								if (next !== null) {
									updates[key] = next;
								}
							});

							if (Object.keys(updates).length) {
								dispatch.updateBlockAttributes(block.clientId, updates);
								touched = true;
							}

							walk(block.innerBlocks);
						});
					})(select.getBlocks());

					return touched;
				}

				// Classic editor: TinyMCE when visual, the raw textarea when not.
				function stampClassicEditor(productId) {
					var touched = false;

					if (typeof tinymce !== 'undefined') {
						var mce = tinymce.get('content');
						if (mce && !mce.isHidden()) {
							var next = stampShortcode(mce.getContent(), productId);
							if (next !== null) {
								mce.setContent(next);
								mce.fire('change');
								touched = true;
							}
						}
					}

					var $textarea = $('#content');
					if ($textarea.length) {
						var nextRaw = stampShortcode($textarea.val(), productId);
						if (nextRaw !== null) {
							$textarea.val(nextRaw).trigger('change');
							touched = true;
						}
					}

					return touched;
				}

				function syncBuyButtonShortcode(productId) {
					if (!productId) {
						return false;
					}
					// Only one of these is live in any given screen.
					return stampBlockEditor(productId) || stampClassicEditor(productId);
				}

				// Wait for DOM ready
				$(function() {

					// -------------------------
					// FIND MATCH: click handler
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
							$result.addClass('error').text('<?php echo esc_js(__('Unable to detect post title.', 'quick-product')); ?>').show();
							return;
						}

						// Prepare AJAX POST (use global ajaxurl)
						var data = {
							action: 'qp_find_product',
							title: postTitle
						};

						// Show temporary status
						$result.removeClass('error success').text('<?php echo esc_js(__('Searching…', 'quick-product')); ?>').show();

						$.post(ajaxurl, data)
							.done(function(resp) {
								if (resp && resp.success && resp.data) {
									var d = resp.data;

									// Fill fields in the Quick Product box
									if (d.id) {
										$('#qp-product-id').val(d.id);
									}
									if (d.title !== undefined) {
										$('#qp-product-name').val(d.title);
									}
									if (d.price !== undefined) {
										$('#qp-product-price').val(d.price);
									}
							        if (d.sku !== undefined) {
										$('#qp-sku').val(d.sku);
									}
									if (d.availability !== undefined) {
										$('#qp-availability').val(d.availability);
									}

									// Attributes: resp.data.attributes is { taxonomy: slug, ... }
									if (d.attributes && typeof d.attributes === 'object') {
										$('.qp-attribute-select').each(function() {
											var $sel = $(this);
											var taxonomy = $sel.data('taxonomy');
											if (taxonomy && d.attributes[taxonomy]) {
												var slug = d.attributes[taxonomy];
												// Try to select option with value === slug
												$sel.val(slug);
												// trigger change in case something listens
												$sel.trigger('change');
											}
										});
									}

									// Categories: resp.data.categories is array of term IDs
									if (Array.isArray(d.categories)) {
										// Uncheck all first
										$('input[name="qp_categories[]"]').prop('checked', false);
										d.categories.forEach(function(termId) {
											$('input[name="qp_categories[]"][value="' + termId + '"]').prop('checked', true);
										});
									}

									$result.addClass('success').text('<?php echo esc_js(__('Product matched and fields populated.', 'quick-product')); ?>').show();

								} else {
									// no match — do nothing to fields, show message
									$result.addClass('error').text('<?php echo esc_js(__('No exact product match found.', 'quick-product')); ?>').show();
								}
							})
							.fail(function() {
								$result.addClass('error').text('<?php echo esc_js(__('Search failed. See console for details.', 'quick-product')); ?>').show();
								console.error('qp_find_product AJAX failed');
							});
					});

					// -------------------------
					// EXISTING CREATE / UPDATE handler (unchanged)
					// -------------------------
					$('#qp-create-update').on('click', function() {
						var $button = $(this);
						var $status = $('#qp-status');

						// CLIENT-SIDE VALIDATION
						var errors = [];

						if (!$('#qp-product-name').val().trim()) {
							errors.push('Product Name');
						}

						if (!$('#qp-product-price').val().trim()) {
							errors.push('Product Price');
						}

						if ($('input[name="qp_categories[]"]:checked').length === 0) {
							errors.push('At least one Category');
						}

						if (errors.length > 0) {
							$status
								.addClass('error')
								.html('<strong>Missing required fields:</strong> ' + errors.join(', '))
								.show();
							return; // Stop here, don't send AJAX
						}

						// Collect attribute data
						var attributes = {};
						$('.qp-attribute-select').each(function() {
							var $select = $(this);
							var taxonomy = $select.data('taxonomy');
							var value = $select.val();
							if (value) {
								attributes[taxonomy] = value;
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
							post_id: <?php echo intval($this->post->ID); ?>,
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
							framed_print_product_id: $('#qp-framed-print-product-id').val()
						};

						// Disable button and show processing
						$button.prop('disabled', true).text('<?php echo esc_js(__('Processing...', 'quick-product')); ?>');
						$status.removeClass('success error').hide();

						$.post(ajaxurl, data)
							.done(function(response) {
								if (response.success) {
									$status
										.addClass('success')
										.html(response.data.message)
										.show();

									// Update product ID field if new product was created
									if (response.data.product_id) {
										$('#qp-product-id').val(response.data.product_id);

										// Keep the editor's copy of the body in step with
										// the rewrite the server just made.
										syncBuyButtonShortcode(response.data.product_id);
									}

									if (response.data.sku) {
										$('#qp-sku').val(response.data.sku);
									}

								} else {
									$status
										.addClass('error')
										.html('<strong><?php echo esc_js(__('Error:', 'quick-product')); ?></strong> ' + response.data)
										.show();
								}
							})
							.fail(function() {
								$status
									.addClass('error')
									.html('<?php echo esc_js(__('Request failed. Please try again.', 'quick-product')); ?>')
									.show();
							})
							.always(function() {
								$button.prop('disabled', false).text('<?php echo esc_js(__('Create / Update Product', 'quick-product')); ?>');
							});
					});

					// -------------------------
					// Gallery / Reserved: Show/Hide fields based on availability
					// -------------------------
					function updateReservedLinkVisibility() {
						var availability = $('#qp-availability').val();
						var $section = $('#qp-reserved-link-section');

						if (availability === 'reserved' || availability === 'gallery-reserved') {
							$section.slideDown(200);
						} else {
							$section.slideUp(200);
						}
					}

					function updateGalleryFieldsVisibility() {
						var availability = $('#qp-availability').val();
						var $section = $('#qp-gallery-fields');

						if (availability === 'gallery' || availability === 'gallery-reserved') {
							$section.slideDown(200);
						} else {
							$section.slideUp(200);
						}
					}

					// Update visibility on availability change
					$('#qp-availability').on('change', function() {
						updateReservedLinkVisibility();
						updateGalleryFieldsVisibility();
					});

					// -------------------------
					// Copy Reserved Link Button
					// -------------------------
					$('#qp-copy-reserved-link').on('click', function(e) {
						e.preventDefault();
						var $input = $('#qp-reserved-url');
						var $button = $(this);

						// Select and copy
						$input.select();
						document.execCommand('copy');

						// Visual feedback
						var originalText = $button.text();
						$button.text('<?php echo esc_js(__('✓ Copied!', 'quick-product')); ?>');

						setTimeout(function() {
							$button.text(originalText);
						}, 2000);
					});

				}); // DOM ready
			})(jQuery);
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

		if (! empty($result['shortcode_updated'])) {
			$message .= '<br>' . esc_html__('Buy button shortcode updated in the post body.', 'quick-product');
		}

		wp_send_json_success([
			'message'    => $message,
			'product_id' => $result['product_id'],
			'sku'        => $sku,
			'action'     => $result['action'],
			'shortcode_updated' => ! empty($result['shortcode_updated']),
			'product_url' => get_permalink($result['product_id'])
		]);
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

		// Product price is required and must be valid
		if (empty(trim($data['product_price'] ?? ''))) {
			$errors[] = __('Product Price', 'quick-product');
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
        $print_id = intval($this->data[$key] ?? 0);
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

    // Stamp the product ID into [painting_buy_button] in the post body, if present.
    $shortcode_updated = stamp_buy_button_shortcode($this->post_id, $product_id);

    return [
        'product_id'        => $product_id,
        'action'            => $action,
        'shortcode_updated' => $shortcode_updated,
    ];
}


	private function set_product_data($product)
	{
		// Use product name if provided, otherwise use post title
		$name = ! empty($this->data['product_name'])
			? $this->data['product_name']
			: get_the_title($this->post_id);

		$product->set_name($name);

		// Set price
		if (! empty($this->data['product_price'])) {
			$price = preg_replace('/[^\d\.]/', '', $this->data['product_price']);
			$product->set_regular_price($price);
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
			$print_id = intval($_POST['qp_' . $key]);
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

		foreach ($fields as $input => $meta_key) {
			if (isset($_POST[$input])) {
				$value = sanitize_text_field(wp_unslash($_POST[$input]));
				update_post_meta($this->post_id, $meta_key, $value);
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
 * Rewrite the id attribute of every [painting_buy_button] in a string.
 *
 * Covers the three forms that turn up in post bodies:
 *   [painting_buy_button id="xxxx"]  - the "Coming Soon" placeholder
 *   [painting_buy_button id="123"]   - already pointing at a product
 *   [painting_buy_button]            - no id yet
 *
 * @param string $content    Post content.
 * @param int    $product_id Product ID to stamp in.
 * @return string Content with every buy button pointing at $product_id.
 */
function set_buy_button_shortcode_id($content, $product_id)
{
	// Cheap bail-out so we don't run a regex over every post body.
	if (strpos($content, '[painting_buy_button') === false) {
		return $content;
	}

	return preg_replace_callback(
		'/\[painting_buy_button\b([^\]]*)\]/',
		function ($matches) use ($product_id) {
			$atts = $matches[1];

			if (preg_match('/\bid\s*=/', $atts)) {
				// Replace the existing value, quoted or bare.
				$atts = preg_replace(
					'/\bid\s*=\s*("[^"]*"|\'[^\']*\'|[^\s\]]*)/',
					'id="' . $product_id . '"',
					$atts,
					1
				);
			} else {
				$atts = rtrim($atts) . ' id="' . $product_id . '"';
			}

			return '[painting_buy_button' . $atts . ']';
		},
		$content
	);
}

/**
 * Stamp a product ID into the post body's buy button shortcode.
 *
 * Only touches the post when a buy button is already present - it never adds
 * one. Saves the caller the copy/paste step after creating a product.
 *
 * @param int $post_id    The blog post ID.
 * @param int $product_id The product ID to stamp in.
 * @return bool True when the post content actually changed.
 */
function stamp_buy_button_shortcode($post_id, $product_id)
{
	$post = get_post($post_id);
	if (! $post || ! $product_id) {
		return false;
	}

	$updated = set_buy_button_shortcode_id($post->post_content, $product_id);

	if ($updated === $post->post_content) {
		return false;
	}

	// wp_update_post() re-fires save_post_post. This request carries no qp_*
	// fields, so letting MetaSaver run again would blank the meta we just
	// wrote - unhook it for the duration of the write.
	$plugin = Plugin::instance();
	remove_action('save_post_post', [$plugin, 'save_meta_fields'], 10);

	wp_update_post(wp_slash([
		'ID'           => $post_id,
		'post_content' => $updated,
	]));

	add_action('save_post_post', [$plugin, 'save_meta_fields'], 10, 3);

	return true;
}

/**
 * Sync _related_post_id onto a print product so the Art Filter can find it.
 * Called from both MetaSaver and ProductSyncer.
 *
 * @param int $post_id       The blog post ID.
 * @param int $print_product_id The Printful variable product ID.
 */
function monospace_sync_print_product_link( $post_id, $print_product_id ) {
    if ( ! $post_id || ! $print_product_id ) return;
    update_post_meta( $print_product_id, '_related_post_id', $post_id );
    wp_set_object_terms( $print_product_id, 'print', 'product_cat' );
}





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
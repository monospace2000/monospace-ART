<?php

/**
 * Plugin Name: monospace ART - Quick Product Creator
 * Description: Link posts to WooCommerce products with a simple meta box, and edit product attributes.
 * Version: 1.18
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

		foreach ($attribute_taxonomies as $tax) {
			$taxonomy = wc_attribute_taxonomy_name($tax->attribute_name);
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

		?>
		<div class="qp-field">
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
			'available' => __('Available', 'quick-product'),
			'sold'      => __('Sold', 'quick-product'),
			'private'   => __('Private Collection', 'quick-product'),
			'gallery'   => __('At Gallery', 'quick-product'),
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

	?>
		<div class="qp-field">
			<label><?php echo esc_html($label); ?></label>
			<select name="qp_attributes[<?php echo esc_attr($taxonomy); ?>]"
				class="qp-attribute-select"
				data-taxonomy="<?php echo esc_attr($taxonomy); ?>">
				<option value=""><?php esc_html_e('— Select —', 'quick-product'); ?></option>
				<?php foreach ($terms as $term) : ?>
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
							availability: $('#qp-availability').val()
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

		// Create or update product
		$syncer = new ProductSyncer($post_id, $data);
		$result = $syncer->sync();


		if (is_wp_error($result)) {
			wp_send_json_error($result->get_error_message());
		}

		// Success response
		$product_id = $result['product_id'];
		$edit_link = get_edit_post_link($product_id);


		$message = sprintf(
			__('Product #%d %s successfully. <a href="%s" target="_blank">Edit Product →</a>', 'quick-product'),
			$product_id,
			$result['action'] === 'created' ? __('created', 'quick-product') : __('updated', 'quick-product'),
			esc_url($edit_link)
		);

		wp_send_json_success([
			'message' => $message,
			'product_id' => $product_id,
			'action' => $result['action']
		]);
	}

	private function sanitize_input()
	{
		return [
			'product_id'    => intval($_POST['product_id'] ?? 0),
			'product_name'  => sanitize_text_field(wp_unslash($_POST['product_name'] ?? '')),
			'product_price' => sanitize_text_field(wp_unslash($_POST['product_price'] ?? '')),
	        'sku'           => sanitize_text_field(wp_unslash($_POST['sku'] ?? '')),
			'attributes'    => $this->sanitize_attributes($_POST['attributes'] ?? []),
			'categories'    => $this->sanitize_categories($_POST['categories'] ?? []),
			'availability'  => sanitize_text_field(wp_unslash($_POST['availability'] ?? 'available')),

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
		$generated_sku = $this->generate_sku($product_id);
		$product->set_sku($generated_sku);
		$product->save();
		// Update our data array so it gets saved to post meta
		$this->data['sku'] = $generated_sku;
	}

	// Set attributes (must be done after product is saved)
	$this->set_attributes($product_id, $action);

    // Link product back to post with custom field
    update_post_meta($product_id, '_related_post_id', $this->post_id);

    // Save availability so filter can read it
    update_post_meta($product_id, 'painting_availability_status', $this->data['availability']);

    // Save relationship in post meta
    update_post_meta($this->post_id, '_qp_product_id', $product_id);
    update_post_meta($this->post_id, '_qp_product_name', $this->data['product_name']);
    update_post_meta($this->post_id, '_qp_product_price', $this->data['product_price']);
	update_post_meta($this->post_id, '_qp_sku', $this->data['sku']);
    update_post_meta($this->post_id, '_qp_attributes', $this->data['attributes']);
    update_post_meta($this->post_id, '_qp_categories', $this->data['categories']);
    update_post_meta($this->post_id, '_qp_availability', $this->data['availability']);

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

		// Set price
		if (! empty($this->data['product_price'])) {
			$price = preg_replace('/[^\d\.]/', '', $this->data['product_price']);
			$product->set_regular_price($price);
		}
		    // Set SKU - auto-generate if blank
		$sku = trim($this->data['sku'] ?? '');
		if (empty($sku)) {
			// Will generate after product is saved and has an ID
			// Store flag to generate SKU
			$this->data['_needs_sku_generation'] = true;
		} else {
			$product->set_sku($sku);
		}
	}

	private function set_featured_image($product)
	{
		// First, ensure the post has a featured image
		$thumbnail_id = get_post_thumbnail_id($this->post_id);

		// If no featured image, try to create one from post content
		if (! $thumbnail_id) {
			$thumbnail_id = $this->create_featured_image_from_content();
		}

		if ($thumbnail_id) {
			$product->set_image_id($thumbnail_id);
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

	private function generate_sku($product_id)
	{
		$year = date('Y');

		// Get format from attributes
		$format_slug = $this->data['attributes']['pa_format'] ?? '';
		$format_code = 'O'; // default to "Other"

		switch (strtolower($format_slug)) {
			case 'painting':
				$format_code = 'P';
				break;
			case 'drawing':
				$format_code = 'D';
				break;
			case 'miniature':
				$format_code = 'M';
				break;
		}

		// Find next available number
		global $wpdb;
		$pattern = $wpdb->esc_like('HENS-' . $year . '-' . $format_code . '-') . '%';

		$existing_skus = $wpdb->get_col($wpdb->prepare(
			"SELECT meta_value FROM {$wpdb->postmeta}
			WHERE meta_key = '_sku'
			AND meta_value LIKE %s
			ORDER BY meta_value DESC",
			$pattern
		));

		$next_number = 1;

		if (!empty($existing_skus)) {
			// Extract the highest number
			foreach ($existing_skus as $sku) {
				if (preg_match('/HENS-\d{4}-[A-Z]-(\d{4})$/', $sku, $matches)) {
					$number = intval($matches[1]);
					if ($number >= $next_number) {
						$next_number = $number + 1;
					}
				}
			}
		}

		return sprintf('HENS-%s-%s-%04d', $year, $format_code, $next_number);
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
	    'sku' => $sku,  // ADD THIS LINE
		'availability' => $availability,
		'attributes' => $attributes,
		'categories' => $categories,
	));
});

// Initialize plugin
Plugin::instance();
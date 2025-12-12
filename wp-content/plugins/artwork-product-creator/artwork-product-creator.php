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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Declare WooCommerce HPOS compatibility
add_action( 'before_woocommerce_init', function() {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

/**
 * Main Plugin Class
 */
class Plugin {
	
	private static $instance = null;
	
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'check_dependencies' ] );
		add_action( 'add_meta_boxes', [ $this, 'register_meta_box' ] );
		add_action( 'save_post_post', [ $this, 'save_meta_fields' ], 10, 3 );
		add_action( 'wp_ajax_qp_create_update_product', [ $this, 'ajax_create_update_product' ] );
	}
	
	public function check_dependencies() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', function() {
				?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Quick Product requires WooCommerce to be installed and active.', 'quick-product' ); ?></p>
				</div>
				<?php
			});
		}
	}
	
	public function register_meta_box() {
		add_meta_box(
			'quick_product',
			__( 'Quick Product', 'quick-product' ),
			[ new MetaBox(), 'render' ],
			'post',
			'side',
			'high'
		);
	}
	
	public function save_meta_fields( $post_id, $post, $update ) {
		$saver = new MetaSaver( $post_id );
		$saver->save();
	}
	
	public function ajax_create_update_product() {
		$handler = new AjaxHandler();
		$handler->handle();
	}
}

/**
 * Meta Box Renderer
 */
class MetaBox {
	
	private $post;
	private $data = [];
	
	public function render( $post ) {
		$this->post = $post;
		$this->load_data();
		
		wp_nonce_field( 'qp_meta_box', 'qp_nonce' );
		
		$this->render_styles();
		$this->render_fields();
		$this->render_button();
		$this->render_status();
		$this->render_script();
	}
	
	private function load_data() {
		$product_id = $this->extract_product_id_from_shortcode();

		if ( ! $product_id ) {
			// No product linked yet – set your temporary defaults
			$this->data = [
				'product_id'    => '',
				'product_name'  => '',                // optional default name
				'product_price' => '',             // default price
				'attributes'    => [
					'pa_format' => '',       // default format slug
					'pa_medium' => '',         // default medium slug
					'pa_surface' => '',          // default surface slug
					'pa_size'   => '',             // default size slug
					'pa_year-created'   =>date('Y'),         // default  year
				],
				'categories'    => [],                 // default categories handled below
				'availability'  => 'available',       // default availability
			];

			// Optional: assign default category “Original” if it exists
			$original = get_term_by('name','Original','product_cat');
			if ( $original ) {
				$this->data['categories'] = [ $original->term_id ];
			}

			return;
		}

		// If product exists, load real data
		$this->data = [
			'product_id'   => $product_id,
			'product_name' => '',
			'product_price'=> '',
			'attributes'   => [],
			'categories'   => [],
			'availability' => 'available',
		];

		if ( function_exists( 'wc_get_product' ) ) {
			$this->load_from_product( $product_id );
		}
	}

	
	private function extract_product_id_from_shortcode() {
		$content = $this->post->post_content;
		
		// Look for [painting_buy_button id="xxxx"] pattern
		if ( preg_match( '/\[painting_buy_button\s+id=["\']?(\d+)["\']?\]/', $content, $matches ) ) {
			$product_id = intval( $matches[1] );
			
			// Return 0 if ID is 0 or negative
			if ( $product_id <= 0 ) {
				return 0;
			}
			
			// Verify the product actually exists in WooCommerce
			if ( function_exists( 'wc_get_product' ) ) {
				$product = wc_get_product( $product_id );
				// Return the ID only if the product exists
				return $product ? $product_id : 0;
			}
			
			return $product_id;
		}
		
		return 0;
	}
	
	private function load_from_product( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}
		
		// Update with actual product data
		$this->data['product_name'] = $product->get_name();
		$this->data['product_price'] = $product->get_regular_price();
		
		// Get product attributes
		$product_attributes = $product->get_attributes();
		$loaded_attributes = [];
		
		foreach ( $product_attributes as $attribute ) {
			if ( $attribute->is_taxonomy() ) {
				$taxonomy = $attribute->get_taxonomy();
				$terms = wp_get_post_terms( $product_id, $taxonomy, [ 'fields' => 'all' ] );
                if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                    // Store the slug, not the name
                    $loaded_attributes[ $taxonomy ] = $terms[0]->slug;
                }
			}
		}
		
		$this->data['attributes'] = $loaded_attributes;
		
		// Get product categories
		$categories = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'ids' ] );
		if ( ! is_wp_error( $categories ) ) {
			$this->data['categories'] = $categories;
		}

		// Get availability status (ACF stores it as post meta)
		$availability = get_post_meta( $product_id, 'painting_availability_status', true );
		$this->data['availability'] = $availability ?: 'available';

	}
	
	private function render_styles() {
		?>
		<style>
			.qp-field { margin-bottom: 12px; }
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
	
	private function render_fields() {
		?>
		<div class="qp-field">
			<label><?php esc_html_e( 'Product ID', 'quick-product' ); ?></label>
			<input type="number" 
				name="qp_product_id" 
				id="qp-product-id"
				value="<?php echo esc_attr( $this->data['product_id'] ); ?>" 
				placeholder="<?php esc_attr_e( 'Auto-generated', 'quick-product' ); ?>"
				readonly>
		</div>
		
		<div class="qp-field">
			<label><?php esc_html_e( 'Product Name', 'quick-product' ); ?></label>
			<input type="text" 
				name="qp_product_name" 
				id="qp-product-name"
				value="<?php echo esc_attr( $this->data['product_name'] ); ?>" 
				placeholder="<?php esc_attr_e( 'Enter product name', 'quick-product' ); ?>">
		</div>
		
		<?php $this->render_availability_field(); ?>



		<div class="qp-field">
			<label><?php esc_html_e( 'Product Price', 'quick-product' ); ?></label>
			<input type="text" 
				name="qp_product_price" 
				id="qp-product-price"
				value="<?php echo esc_attr( $this->data['product_price'] ); ?>" 
				placeholder="<?php esc_attr_e( '0.00', 'quick-product' ); ?>">
		</div>
		
		<?php $this->render_category_checkboxes(); ?>
		

		<?php $this->render_attribute_dropdowns(); ?>
		<?php
	}
	
	private function render_attribute_dropdowns() {
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return;
		}
		
		$attribute_taxonomies = wc_get_attribute_taxonomies();
		
		if ( empty( $attribute_taxonomies ) ) {
			?>
			<div class="qp-field">
				<p style="color: #666; font-size: 12px;">
					<?php esc_html_e( 'No global product attributes found. Create them in Products → Attributes.', 'quick-product' ); ?>
				</p>
			</div>
			<?php
			return;
		}
		
	
		echo '<hr style="margin: 15px 0;">';
		echo '<p style="font-weight: 600; margin-bottom: 10px;">' . esc_html__( 'Product Attributes', 'quick-product' ) . '</p>';
		
		foreach ( $attribute_taxonomies as $tax ) {
			$taxonomy = wc_attribute_taxonomy_name( $tax->attribute_name );
			$selected_value = $this->data['attributes'][ $taxonomy ] ?? '';

			$this->render_single_attribute_dropdown( $taxonomy, $tax->attribute_label, $selected_value );
		}
	}
	
	private function render_category_checkboxes() {
		$categories = get_terms([
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		]);
		
		if ( is_wp_error( $categories ) || empty( $categories ) ) {
			return;
		}
		
		// Find "Original" category ID for default
		$original_id = 0;
		foreach ( $categories as $cat ) {
			if ( strtolower( $cat->name ) === 'original' ) {
				$original_id = $cat->term_id;
				break;
			}
		}
		
		// Set default if no categories selected
		$selected_categories = ! empty( $this->data['categories'] ) 
			? $this->data['categories'] 
			: ( $original_id ? [ $original_id ] : [] );
		
		?>
		<div class="qp-field">
			<label><?php esc_html_e( 'Product Categories', 'quick-product' ); ?></label>
			<div style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 8px; background: #fff;">
				<?php foreach ( $categories as $category ) : ?>
					<label style="display: block; margin-bottom: 6px; font-weight: normal;">
						<input type="checkbox" 
							name="qp_categories[]" 
							value="<?php echo esc_attr( $category->term_id ); ?>"
							<?php checked( in_array( $category->term_id, $selected_categories ) ); ?>>
						<?php echo esc_html( $category->name ); ?>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
	
	private function render_availability_field() {
		$availability_options = [
			'available' => __( 'Available', 'quick-product' ),
			'sold'      => __( 'Sold', 'quick-product' ),
			'private'   => __( 'Private Collection', 'quick-product' ),
			'gallery'   => __( 'At Gallery', 'quick-product' ),
		];
		
		$selected = $this->data['availability'] ?? 'available';
		
		?>
		<div class="qp-field">
			<label><?php esc_html_e( 'Availability Status', 'quick-product' ); ?></label>
			<select name="qp_availability" id="qp-availability" style="width: 100%;">
				<?php foreach ( $availability_options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" 
						<?php selected( $selected, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}


	private function render_single_attribute_dropdown( $taxonomy, $label, $selected ) {
		$terms = get_terms([
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
		]);
		
		
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return;
		}
		
		?>
		<div class="qp-field">
			<label><?php echo esc_html( $label ); ?></label>
			<select name="qp_attributes[<?php echo esc_attr( $taxonomy ); ?>]" 
				class="qp-attribute-select"
				data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>">
				<option value=""><?php esc_html_e( '— Select —', 'quick-product' ); ?></option>
				<?php foreach ( $terms as $term ) : ?>
					<option value="<?php echo esc_attr( $term->slug ); ?>" 
						<?php selected( $selected, $term->slug ); ?>>
						<?php echo esc_html( $term->name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
		<?php
	}
	
	private function render_button() {
		?>
		<div class="qp-field">
			<button type="button" 
				id="qp-create-update" 
				class="button button-primary button-large" 
				style="width: 100%;">
				<?php esc_html_e( 'Create / Update Product', 'quick-product' ); ?>
			</button>
		</div>
		<?php
	}
	
	private function render_status() {
		?>
		<div id="qp-status" style="display: none;"></div>
		<?php
	}
	
	private function render_script() {
		?>
		<script>
		(function($) {
			'use strict';
			
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
					post_id: <?php echo intval( $this->post->ID ); ?>,
					product_id: $('#qp-product-id').val(),
					product_name: $('#qp-product-name').val(),
					product_price: $('#qp-product-price').val(),
					attributes: attributes,
					categories: categories,
					availability: $('#qp-availability').val() // Add this line
				};
				
				// Disable button and show processing
				$button.prop('disabled', true).text('<?php echo esc_js( __( 'Processing...', 'quick-product' ) ); ?>');
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
								.html('<strong><?php echo esc_js( __( 'Error:', 'quick-product' ) ); ?></strong> ' + response.data)
								.show();
						}
					})
					.fail(function() {
						$status
							.addClass('error')
							.html('<?php echo esc_js( __( 'Request failed. Please try again.', 'quick-product' ) ); ?>')
							.show();
					})
					.always(function() {
						$button.prop('disabled', false).text('<?php echo esc_js( __( 'Create / Update Product', 'quick-product' ) ); ?>');
					});
			});
		})(jQuery);
		</script>
		<?php
	}
}

/**
 * AJAX Handler
 */
class AjaxHandler {
	
	public function handle() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'qp_meta_box' ) ) {
			wp_send_json_error( __( 'Security check failed.', 'quick-product' ) );
		}
		
		// Check permissions
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( __( 'Insufficient permissions.', 'quick-product' ) );
		}
		
		// Validate post ID
		$post_id = intval( $_POST['post_id'] ?? 0 );
		if ( ! $post_id || get_post_type( $post_id ) !== 'post' ) {
			wp_send_json_error( __( 'Invalid post ID.', 'quick-product' ) );
		}
		
		// Sanitize input
		$data = $this->sanitize_input();
		
		// Create or update product
		$syncer = new ProductSyncer( $post_id, $data );
		$result = $syncer->sync();


		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		
		// Success response
		$product_id = $result['product_id'];
		$edit_link = get_edit_post_link( $product_id );
		

		$message = sprintf(
			__( 'Product #%d %s successfully. <a href="%s" target="_blank">Edit Product →</a>', 'quick-product' ),
			$product_id,
			$result['action'] === 'created' ? __( 'created', 'quick-product' ) : __( 'updated', 'quick-product' ),
			esc_url( $edit_link )
		);
		
		wp_send_json_success([
			'message' => $message,
			'product_id' => $product_id,
			'action' => $result['action']
		]);
	}
	
	private function sanitize_input() {
		return [
			'product_id'    => intval( $_POST['product_id'] ?? 0 ),
			'product_name'  => sanitize_text_field( wp_unslash( $_POST['product_name'] ?? '' ) ),
			'product_price' => sanitize_text_field( wp_unslash( $_POST['product_price'] ?? '' ) ),
			'attributes'    => $this->sanitize_attributes( $_POST['attributes'] ?? [] ),
			'categories'    => $this->sanitize_categories( $_POST['categories'] ?? [] ),
		    'availability'  => sanitize_text_field( wp_unslash( $_POST['availability'] ?? 'available' ) ), // Add this line

		];
	}
	
	private function sanitize_attributes( $attributes ) {
		if ( ! is_array( $attributes ) ) {
			return [];
		}
		
		$sanitized = [];
		foreach ( $attributes as $taxonomy => $value ) {
			$sanitized[ sanitize_text_field( $taxonomy ) ] = sanitize_text_field( $value );
		}
		
		return $sanitized;
	}
	
	private function sanitize_categories( $categories ) {
		if ( ! is_array( $categories ) ) {
			return [];
		}
		
		return array_map( 'intval', $categories );
	}
}

/**
 * Product Syncer
 */
class ProductSyncer {
	
	private $post_id;
	private $data;
	
	public function __construct( $post_id, $data ) {
		$this->post_id = $post_id;
		$this->data    = $data;
	}
	
	public function sync() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return new \WP_Error( 'no_wc', __( 'WooCommerce is not active.', 'quick-product' ) );
		}
		
		// Determine if creating or updating
		$is_update = ! empty( $this->data['product_id'] );
		
		if ( $is_update ) {
			$product = wc_get_product( $this->data['product_id'] );
			if ( ! $product ) {
				return new \WP_Error( 'invalid_product', __( 'Product not found.', 'quick-product' ) );
			}
			$action = 'updated';
		} else {
			$product = new \WC_Product_Simple();
			$product->set_status( 'publish' );
            // Enable inventory tracking
            $product->set_manage_stock( true );      // Track stock
            $product->set_stock_quantity( 1 );       // Only 1 in stock
            $product->set_stock_status( 'instock' ); // Mark as in stock
            $product->set_sold_individually( true ); // Customers cannot order more than 1
			$action = 'created';
		}
		
		// Set basic product data
		$this->set_product_data( $product );
		
		// Set featured image
		$this->set_featured_image( $product );
		
		// Save product
		$product_id = $product->save();
		
		// Set attributes (must be done after product is saved)
		$this->set_attributes( $product_id, $action );
		
		// Link product back to post with custom field
		update_post_meta( $product_id, '_related_post_id', $this->post_id );
		
        //make it available
        update_post_meta( $product_id, 'painting_availability_status', $this->data['availability'] );

		// Save relationship in post meta
		update_post_meta( $this->post_id, '_qp_product_id', $product_id );
		update_post_meta( $this->post_id, '_qp_product_name', $this->data['product_name'] );
		update_post_meta( $this->post_id, '_qp_product_price', $this->data['product_price'] );
		update_post_meta( $this->post_id, '_qp_attributes', $this->data['attributes'] );
		update_post_meta( $this->post_id, '_qp_categories', $this->data['categories'] );
		update_post_meta( $this->post_id, '_qp_availability', $this->data['availability'] );

		return [
			'product_id' => $product_id,
			'action'     => $action,
		];
	}
	
	private function set_product_data( $product ) {
		// Use product name if provided, otherwise use post title
		$name = ! empty( $this->data['product_name'] ) 
			? $this->data['product_name'] 
			: get_the_title( $this->post_id );
		
		$product->set_name( $name );
		
		// Set price
		if ( ! empty( $this->data['product_price'] ) ) {
			$price = preg_replace( '/[^\d\.]/', '', $this->data['product_price'] );
			$product->set_regular_price( $price );
		}
	}
	
	private function set_featured_image( $product ) {
		// First, ensure the post has a featured image
		$thumbnail_id = get_post_thumbnail_id( $this->post_id );
		
		// If no featured image, try to create one from post content
		if ( ! $thumbnail_id ) {
			$thumbnail_id = $this->create_featured_image_from_content();
		}
		
		if ( $thumbnail_id ) {
			$product->set_image_id( $thumbnail_id );
		}
	}
	
	private function create_featured_image_from_content() {
		$post = get_post( $this->post_id );
		if ( ! $post ) {
			return 0;
		}
		
		$content = $post->post_content;
		
		// Try to find image in various formats
		$image_id = 0;
		
		// Method 1: Look for wp-image-XXX class in img tags
		if ( preg_match( '/wp-image-(\d+)/', $content, $matches ) ) {
			$image_id = intval( $matches[1] );
		}
		
		// Method 2: Look for attachment IDs in image blocks
		if ( ! $image_id && preg_match( '/wp:image\s+{"id":(\d+)/', $content, $matches ) ) {
			$image_id = intval( $matches[1] );
		}
		
		// Method 3: Extract image URL and find attachment
		if ( ! $image_id && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $content, $matches ) ) {
			$image_url = $matches[1];
			$image_id = attachment_url_to_postid( $image_url );
		}
		
		// Set as featured image if found
		if ( $image_id && wp_attachment_is_image( $image_id ) ) {
			set_post_thumbnail( $this->post_id, $image_id );
			return $image_id;
		}
		
		return 0;
	}
	
	private function set_attributes( $product_id ) {
		if ( !empty( $this->data['attributes'] ) ) {
			
		
            
            $attributes = [];
            $position = 0;
            
            foreach ( $this->data['attributes'] as $taxonomy => $slug ) {
                if ( ! taxonomy_exists( $taxonomy ) ) {
                    continue;
                }
                
                // Get the term by slug to ensure we're using existing terms
                $term = get_term_by( 'slug', $slug, $taxonomy );
                
                if ( ! $term ) {
                    // If term doesn't exist by slug, skip it
                    // This prevents creating new terms accidentally
                    continue;
                }
                
                // Set the term for this product using term ID
                wp_set_object_terms( $product_id, [ $term->term_id ], $taxonomy );
                
                // Build attribute array for product meta
                $attribute = new \WC_Product_Attribute();
                $attribute->set_id( wc_attribute_taxonomy_id_by_name( $taxonomy ) );
                $attribute->set_name( $taxonomy );
                $attribute->set_options( [ $term->name ] ); // WooCommerce expects term names in options
                $attribute->set_position( $position );
                $attribute->set_visible( true );
                $attribute->set_variation( false );
                
                $attributes[] = $attribute;
                $position++;
            }

            
            // Save attributes to product
            if ( ! empty( $attributes ) ) {
                $product = wc_get_product( $product_id );
                $product->set_attributes( $attributes );
                $product->save();
            }
        }
		// Set product categories
		$this->set_categories( $product_id );
		
		

			// Save availability status
			update_post_meta( $product_id, 'painting_availability_status', $this->data['availability'] );

	}
	
	private function set_categories( $product_id ) {
		if ( empty( $this->data['categories'] ) ) {
			// If no categories selected, try to set "Original" as default
			$original = get_term_by( 'name', 'Original', 'product_cat' );
			if ( $original ) {
				wp_set_object_terms( $product_id, [ $original->term_id ], 'product_cat' );
			}
			return;
		}
		
		wp_set_object_terms( $product_id, $this->data['categories'], 'product_cat' );
	}
}

/**
 * Meta Saver - Saves meta fields on post save
 */
class MetaSaver {
	
	private $post_id;
	
	public function __construct( $post_id ) {
		$this->post_id = $post_id;
	}
	
	public function save() {
		if ( ! $this->should_save() ) {
			return;
		}
		
		$fields = [
			'qp_product_id'    => '_qp_product_id',
			'qp_product_name'  => '_qp_product_name',
			'qp_product_price' => '_qp_product_price',
		];
		
		foreach ( $fields as $input => $meta_key ) {
			if ( isset( $_POST[ $input ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $input ] ) );
				update_post_meta( $this->post_id, $meta_key, $value );
			}
		}
		
		// Save attributes
		if ( isset( $_POST['qp_attributes'] ) && is_array( $_POST['qp_attributes'] ) ) {
			$attributes = array_map( 'sanitize_text_field', wp_unslash( $_POST['qp_attributes'] ) );
			update_post_meta( $this->post_id, '_qp_attributes', $attributes );
		}
		
		// Save categories
		if ( isset( $_POST['qp_categories'] ) && is_array( $_POST['qp_categories'] ) ) {
			$categories = array_map( 'intval', $_POST['qp_categories'] );
			update_post_meta( $this->post_id, '_qp_categories', $categories );
		}
	}
	
	private function should_save() {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}
		
		if ( wp_is_post_revision( $this->post_id ) ) {
			return false;
		}
		
		if ( ! current_user_can( 'edit_post', $this->post_id ) ) {
			return false;
		}
		
		return true;
	}
}

// Initialize plugin
Plugin::instance();
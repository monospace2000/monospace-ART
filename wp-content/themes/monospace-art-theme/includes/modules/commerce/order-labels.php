<?php
/**
 * Order Labels for WooCommerce (HPOS Compatible - Simple Dropdown)
 *
 * Works with both Classic Orders and High-Performance Order Storage (HPOS)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ---------------------------------------------------------
 * HELPER: Get Available Labels
 * --------------------------------------------------------- */
function ms_get_order_labels() {
    return apply_filters( 'ms_order_labels', [
        'online-sale'    => 'Online Sale',
        'in-person-sale' => 'In-Person Sale',
        'commission'     => 'Commission',
        'loan'           => 'Loan',
        'test'           => 'Test',
    ] );
}

/* ---------------------------------------------------------
 * 1. REGISTER ORDER META (HPOS Compatible)
 * --------------------------------------------------------- */
add_action( 'init', function () {
    register_post_meta( 'shop_order', 'order_label', [
        'type'              => 'string',
        'single'            => true,
        'show_in_admin_ui'  => true,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback'     => function () {
            return current_user_can( 'edit_shop_orders' );
        },
    ] );
} );

/* ---------------------------------------------------------
 * 2. ADD COLUMN TO ALL ORDERS (HPOS + Classic)
 * --------------------------------------------------------- */

// For HPOS orders
add_filter( 'manage_woocommerce_page_wc-orders_columns', function ( $columns ) {
    $new = [];
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( $key === 'order_number' ) {
            $new['order_label'] = __( 'Label', 'monospace' );
        }
    }
    return $new;
}, 20 );

// For Classic orders
add_filter( 'manage_edit-shop_order_columns', function ( $columns ) {
    $new = [];
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( $key === 'order_number' ) {
            $new['order_label'] = __( 'Label', 'monospace' );
        }
    }
    return $new;
}, 20 );

/* ---------------------------------------------------------
 * 3. RENDER COLUMN CONTENT WITH DROPDOWN (HPOS + Classic)
 * --------------------------------------------------------- */

// For HPOS orders
add_action( 'manage_woocommerce_page_wc-orders_custom_column', function ( $column, $order ) {
    if ( $column !== 'order_label' ) {
        return;
    }

    if ( is_numeric( $order ) ) {
        $order = wc_get_order( $order );
    }

    if ( ! $order ) {
        return;
    }

    ms_render_order_label_dropdown( $order );

}, 20, 2 );

// For Classic orders
add_action( 'manage_shop_order_posts_custom_column', function ( $column, $post_id ) {
    if ( $column !== 'order_label' ) {
        return;
    }

    $order = wc_get_order( $post_id );
    if ( ! $order ) {
        return;
    }

    ms_render_order_label_dropdown( $order );

}, 20, 2 );

/* ---------------------------------------------------------
 * HELPER: Render Dropdown
 * --------------------------------------------------------- */
function ms_render_order_label_dropdown( $order ) {
    $order_id = $order->get_id();
    $current_label = $order->get_meta( 'order_label', true );
    $labels = ms_get_order_labels();

    ?>
    <select class="order-label-select"
            data-order-id="<?php echo esc_attr( $order_id ); ?>"
            style="width:140px;">
        <option value="">— Select —</option>
        <?php foreach ( $labels as $value => $label ) : ?>
            <option value="<?php echo esc_attr( $value ); ?>"
                    <?php selected( $current_label, $value ); ?>>
                <?php echo esc_html( $label ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
}

/* ---------------------------------------------------------
 * 4. ADMIN STYLES (COLOR CODING)
 * --------------------------------------------------------- */
add_action( 'admin_head', function () {
    $screen = get_current_screen();
    if ( ! $screen || ( $screen->id !== 'shop_order' && $screen->id !== 'woocommerce_page_wc-orders' ) ) {
        return;
    }
    ?>
    <style>
        .wp-list-table .column-order_label {
            white-space: nowrap;
        }
        .order-label-select {
            font-weight: 600;
        }
        .order-label-select option[value="online-sale"] { color:#166534; }
        .order-label-select option[value="in-person-sale"] { color:#166534; }
        .order-label-select option[value="commission"] { color:#6b21a8; }
        .order-label-select option[value="loan"] { color:#b45309; }
        .order-label-select option[value="test"] { color:#dc2626; }
    </style>
    <?php
} );

/* ---------------------------------------------------------
 * 5. AJAX HANDLER FOR LABEL UPDATE
 * --------------------------------------------------------- */
add_action( 'wp_ajax_update_order_label', function () {
    check_ajax_referer( 'order_label_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_shop_orders' ) ) {
        wp_send_json_error( 'Insufficient permissions' );
    }

    $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
    $label = isset( $_POST['label'] ) ? sanitize_text_field( $_POST['label'] ) : '';

    if ( ! $order_id ) {
        wp_send_json_error( 'Invalid order ID' );
    }

    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wp_send_json_error( 'Order not found' );
    }

    // Validate label is in allowed list
    $allowed_labels = array_keys( ms_get_order_labels() );
    if ( $label && ! in_array( $label, $allowed_labels ) ) {
        wp_send_json_error( 'Invalid label' );
    }

    $order->update_meta_data( 'order_label', $label );
    $order->save();

    wp_send_json_success( [
        'label' => $label,
        'message' => 'Label updated successfully'
    ] );
} );

/* ---------------------------------------------------------
 * 6. JAVASCRIPT FOR DROPDOWN AUTO-SAVE
 * --------------------------------------------------------- */
add_action( 'admin_footer', function () {
    $screen = get_current_screen();
    if ( ! $screen || ( $screen->id !== 'shop_order' && $screen->id !== 'woocommerce_page_wc-orders' ) ) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Prevent clicks on dropdown from opening order
        $(document).on('click', '.order-label-select', function(e) {
            e.stopPropagation();
        });

        // Auto-save on dropdown change
        $(document).on('change', '.order-label-select', function(e) {
            e.stopPropagation(); // Prevent order from opening

            var $select = $(this);
            var orderId = $select.data('order-id');
            var label = $select.val();
            var originalValue = $select.find('option:selected').data('original-value') || '';

            // Store original value for rollback on error
            $select.data('original-value', $select.val());

            // Disable during save
            $select.prop('disabled', true);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_order_label',
                    nonce: '<?php echo wp_create_nonce( "order_label_nonce" ); ?>',
                    order_id: orderId,
                    label: label
                },
                success: function(response) {
                    if (!response.success) {
                        alert('Error: ' + (response.data || 'Failed to update label'));
                        // Rollback to original value
                        $select.val(originalValue);
                    }
                    $select.prop('disabled', false);
                },
                error: function() {
                    alert('Failed to update label');
                    // Rollback to original value
                    $select.val(originalValue);
                    $select.prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
} );

/* ---------------------------------------------------------
 * 7. FILTER DROPDOWN (HPOS + Classic)
 * --------------------------------------------------------- */

// For HPOS orders
add_action( 'woocommerce_order_list_table_restrict_manage_orders', function () {
    $current = $_GET['order_label'] ?? '';
    $labels = ms_get_order_labels();
    ?>
    <select name="order_label" style="max-width:180px;">
        <option value="">Filter by label…</option>
        <?php foreach ( $labels as $value => $label ) : ?>
            <option value="<?php echo esc_attr( $value ); ?>"
                    <?php selected( $current, $value ); ?>>
                <?php echo esc_html( $label ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
} );

// For Classic orders
add_action( 'restrict_manage_posts', function ( $post_type ) {
    if ( $post_type !== 'shop_order' ) return;

    $current = $_GET['order_label'] ?? '';
    $labels = ms_get_order_labels();
    ?>
    <select name="order_label" style="max-width:180px;">
        <option value="">Filter by label…</option>
        <?php foreach ( $labels as $value => $label ) : ?>
            <option value="<?php echo esc_attr( $value ); ?>"
                    <?php selected( $current, $value ); ?>>
                <?php echo esc_html( $label ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
} );

// Filter query for HPOS
add_filter( 'woocommerce_order_list_table_prepare_items_query_args', function ( $args ) {
    if ( ! empty( $_GET['order_label'] ) ) {
        $args['meta_query'] = $args['meta_query'] ?? [];
        $args['meta_query'][] = [
            'key'     => 'order_label',
            'value'   => sanitize_text_field( wp_unslash( $_GET['order_label'] ) ),
            'compare' => '=',
        ];
    }
    return $args;
} );

// Filter query for Classic
add_filter( 'request', function ( $vars ) {
    global $typenow;

    if ( $typenow !== 'shop_order' ) {
        return $vars;
    }

    if ( ! empty( $_GET['order_label'] ) ) {
        $vars['meta_query'] = $vars['meta_query'] ?? [];
        $vars['meta_query'][] = [
            'key'     => 'order_label',
            'value'   => sanitize_text_field( wp_unslash( $_GET['order_label'] ) ),
            'compare' => '=',
        ];
    }

    return $vars;
} );

/* ---------------------------------------------------------
 * 8. AUTO-LABELING RULES
 * --------------------------------------------------------- */
add_action( 'woocommerce_checkout_order_processed', function ( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    // Do not override manual labels
    if ( $order->get_meta( 'order_label', true ) ) {
        return;
    }

    $total = (float) $order->get_total();
    $label = '';

    if ( $total <= 0 ) {
        $label = 'loan';
    } else {
        $label = 'online-sale';
    }

    $order->update_meta_data( 'order_label', $label );
    $order->save();

}, 20 );

/* ---------------------------------------------------------
 * 9. EXPOSE LABEL FOR INVOICES / PACKING SLIPS
 * --------------------------------------------------------- */
add_filter( 'woocommerce_order_get_meta', function ( $value, $order, $key ) {
    if ( $key === 'order_label' ) {
        return $order->get_meta( 'order_label', true );
    }
    return $value;
}, 10, 3 );
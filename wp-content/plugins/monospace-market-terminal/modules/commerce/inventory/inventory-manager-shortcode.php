<?php
/*
Plugin Name: Market Inventory Terminal v2
Description: Partial-SKU-driven market sales terminal with JS redirect and namespaced functions.
Version: 2.2
Author: Hens Breet
*/

if (!defined('ABSPATH')) exit;

add_shortcode('market_inventory_terminal', 'mit_render_terminal');

/* ------------------------------
   Availability options
-------------------------------*/
function mit_availability_options() {
    return [
        'available' => __('Available', 'quick-product'),
        'reserved'  => __('Reserved', 'quick-product'),
        'sold'      => __('Private Collection', 'quick-product'),
        'private'   => __('Artist\'s Private Collection', 'quick-product'),
        'gallery'   => __('At Gallery', 'quick-product'),
        'gallery-reserved' => __('At Gallery (QR Purchase Only)', 'quick-product'),
    ];
}

/* ------------------------------
   Originals only (2026-10-16): products in the "original" product category, whatever their
   availability status - never prints (category "print", R SKUs) or custom orders
-------------------------------*/
function mit_is_original($product_id) {
    return $product_id && has_term('original', 'product_cat', $product_id);
}

/* ------------------------------
   Prints on sale for an original's artwork (2026-10-17): [ label => price range HTML ], framed first.
   The card only says "Prints available" when there are any (owner's choice - no details).
   On sale = linked print product published and in stock (as the price card / "Prints available").
-------------------------------*/
function mit_prints_for_sale($product_id) {
    $post_id = (int) get_post_meta($product_id, '_related_post_id', true);
    $prints = [];
    if (!$post_id || !monospace_feature('prints')) return $prints; // none while the Prints plugin is off
    foreach (['_framed_print_product_id' => 'Framed', '_print_product_id' => 'Unframed'] as $meta => $label) {
        $print = wc_get_product((int) get_post_meta($post_id, $meta, true));
        if ($print && $print->get_status() === 'publish' && $print->is_in_stock()) {
            $min = (float) $print->get_variation_price('min');
            $max = (float) $print->get_variation_price('max');
            $prints[$label] = $min === $max ? wc_price($min) : wc_price($min) . '&ndash;' . wc_price($max);
        }
    }
    return $prints;
}

/* ------------------------------
   Find an original by SKU suffix
-------------------------------*/
function mit_find_product_by_suffix($suffix) {
    global $wpdb;

    $suffix = sanitize_text_field($suffix);

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "
            SELECT p.ID
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm
              ON p.ID = pm.post_id
            INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat'
            INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id AND t.slug = 'original'
            WHERE p.post_type = 'product'
              AND p.post_status = 'publish'
              AND pm.meta_key = '_sku'
              AND pm.meta_value LIKE %s
            LIMIT 1
            ",
            '%' . $wpdb->esc_like($suffix)
        )
    );

    return $row ? (int) $row->ID : 0;
}

/* ------------------------------
   Undo most recent sale
-------------------------------*/
function mit_undo_sale($product_id) {
    // Newest completed order containing this product, checked in batches,
    // so a sale can be undone even after other sales have happened since
    for ($page = 1; ; $page++) {
        $orders = wc_get_orders([
            'limit'   => 50,
            'paged'   => $page,
            'status'  => 'completed',
            'orderby' => 'date',
            'order'   => 'DESC',
        ]);
        if (! $orders) {
            return false;
        }

        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() == $product_id) {
                    $order->update_status('cancelled', 'Market correction: undo sale');
                    return true;
                }
            }
        }
    }
}

/* ------------------------------
   Main Terminal UI
-------------------------------*/
function mit_render_terminal() {

    // Logged-out visitors get a login prompt that returns them to this page.
    if (!is_user_logged_in()) {
        $redirect = get_permalink();
        if (!$redirect) {
            $redirect = home_url(add_query_arg([], null));
        }

        ob_start();
        ?>
        <div class="mit-terminal mit-login" style="max-width:850px; margin:0; padding:0; font-family:sans-serif;">
            <p>Please log in to access the market inventory terminal.</p>
            <?php
            wp_login_form([
                'echo'           => true,
                'redirect'       => $redirect,
                'label_username' => __('Username or Email', 'quick-product'),
                'remember'       => true,
            ]);
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    if (!current_user_can('manage_woocommerce')) {
        return '<p>Access denied.</p>';
    }

    $message = '';
    $product = null;
    $availability_options = mit_availability_options();

    if (isset($_GET['product_id']) || isset($_GET['msg'])) {
        if (isset($_GET['product_id'])) {
            $product = wc_get_product((int) $_GET['product_id']);
            if ($product && !mit_is_original($product->get_id())) { // a print / custom order: not market stock
                $product = null;
                $_GET['msg'] = 'not_found';
            }
        }

        if (isset($_GET['msg'])) {
            $msg_type = sanitize_text_field($_GET['msg']);
            switch ($msg_type) {
                case 'sold':
                    $message = "<span style='color:green;'>Marked as sold.</span>";
                    break;
                case 'not_available':
                    $message = "<span style='color:red;'>This artwork is not available for sale.</span>";
                    break;
                case 'undone':
                    $message = "<span style='color:orange;'>Sale undone.</span>";
                    break;
                case 'no_sale':
                    $message = "<span style='color:red;'>No completed sale found.</span>";
                    break;
                case 'not_found':
                    $message = "<span style='color:red;'>No artwork found for that number.</span>";
                    break;
            }
        }

        echo "<script>window.history.replaceState({}, '', " . json_encode(remove_query_arg(['msg'])) . ");</script>";
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $current_url = remove_query_arg(['product_id', 'msg']);

        if (isset($_POST['mit_search'])) {
            $suffix = trim($_POST['sku_suffix']);
            $product_id = mit_find_product_by_suffix($suffix);

            if ($product_id) {
                wp_redirect(add_query_arg('product_id', $product_id, $current_url));
            } else {
                wp_redirect(add_query_arg('msg', 'not_found', $current_url));
            }
            exit;
        }

        if (isset($_POST['mit_mark_sold'])) {
            $product_id = (int) $_POST['product_id'];
            if (!mit_is_original($product_id)) {
                wp_redirect(add_query_arg('msg', 'not_found', $current_url));
                exit;
            }
            $payment_method = sanitize_text_field($_POST['payment_method']);
            $notes = sanitize_textarea_field($_POST['notes']);

            $product_obj = wc_get_product($product_id);
            $availability_key = get_post_meta($product_id, 'painting_availability_status', true);

            if ($availability_key === 'available') {
                $order = wc_create_order();
                $order->add_product($product_obj, 1);
                $order->set_payment_method($payment_method);
                $order->set_status('completed');

                if ($notes) {
                    $order->add_order_note($notes);
                }

                $order->calculate_totals();
                $order->save();

                update_post_meta($product_id, 'painting_availability_status', 'sold');
                wp_redirect(add_query_arg(['product_id' => $product_id, 'msg' => 'sold'], $current_url));
            } else {
                wp_redirect(add_query_arg(['product_id' => $product_id, 'msg' => 'not_available'], $current_url));
            }
            exit;
        }

        if (isset($_POST['mit_undo_sale'])) {
            $product_id = (int) $_POST['product_id'];
            if (!mit_is_original($product_id)) {
                wp_redirect(add_query_arg('msg', 'not_found', $current_url));
                exit;
            }
            if (mit_undo_sale($product_id)) {
                update_post_meta($product_id, 'painting_availability_status', 'available');
                wp_redirect(add_query_arg(['product_id' => $product_id, 'msg' => 'undone'], $current_url));
            } else {
                wp_redirect(add_query_arg(['product_id' => $product_id, 'msg' => 'no_sale'], $current_url));
            }
            exit;
        }
    }

    ob_start();
    ?>

<style>
.mit-terminal input,
.mit-terminal select,
.mit-terminal textarea {
    font-size: 16px !important;
}
.availability-label-available { color: green; font-weight: bold; }
.availability-label-notavailable { color: red; font-weight: bold; }
</style>

<div class="mit-terminal" style="max-width:850px; margin:0; padding:0; font-family:sans-serif;">

    <?php if ($message): ?>
        <div style="margin-bottom:20px; font-weight:bold;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- START SCREEN -->
    <form method="post">
        <label>Artwork number (last 4 digits)</label>
        <input name="sku_suffix"
               id="mit-sku-input"
               inputmode="numeric"
               pattern="[0-9]*"
               placeholder="0000"
               required
               readonly
               onfocus="this.removeAttribute('readonly');"
               style="width:100%; padding:10px; margin:10px 0; font-size:16px; border-color: #333; background-color: white;">
        <button name="mit_search" style="width:100%;margin-bottom: 20px;">Search</button>
    </form>

    <?php if ($product):
        $availability_key = get_post_meta($product->get_id(), 'painting_availability_status', true);
        $availability_label = $availability_options[$availability_key] ?? 'Unknown';
        $related_post_id = get_post_meta($product->get_id(), '_related_post_id', true);

        // Determine label color
        $availability_class = ($availability_key === 'available') ? 'availability-label-available' : 'availability-label-notavailable';
    ?>

    <!-- PRODUCT CONFIRMATION -->
    <div style="border:1px solid #000; padding:15px; margin:10px 0; background-color: #fff;">
        <strong><?php echo esc_html($product->get_name()); ?></strong><br>
        SKU: <?php echo esc_html($product->get_sku()); ?><br>
        Status: <strong class="<?php echo $availability_class; ?>"><?php echo esc_html($availability_label); ?></strong><?php if ($availability_key === 'available'): ?><br>
        Price: <strong><?php echo wc_price($product->get_regular_price()); ?></strong><?php endif; ?>
        <?php if (mit_prints_for_sale($product->get_id())) : ?><br><strong class="availability-label-available">Prints available</strong><?php endif; ?>

        <div style="margin:15px 0;">
            <div style="margin:15px 0;">
                <?php
                $image = $product->get_image('full');
                echo str_replace('<img', '<img style="max-width:100%; height:auto; display:block;"', $image);
                ?>
            </div>
        </div>

        <?php if ($related_post_id): ?>
            <a href="<?php echo get_permalink($related_post_id); ?>" target="_blank">
                View artwork post
            </a>
        <?php endif; ?>
    </div>

    <?php if ($availability_key === 'available'): ?>
        <!-- MARK AS SOLD -->
        <form method="post"
              onsubmit="return confirm('Mark this artwork as sold?');"
              style="margin-bottom:20px;">
            <input type="hidden" name="product_id" value="<?php echo $product->get_id(); ?>">

            <label>Payment method</label>
            <select name="payment_method" required style="width:100%; margin-bottom:10px;">
                <option value="Square">Square</option>
                <option value="Cash">Cash</option>
                <option value="Venmo">Venmo</option>
                <option value="PayPal">PayPal</option>
            </select>

            <label>Notes (optional)</label>
            <textarea name="notes" style="width:100%; margin-bottom:10px;"></textarea>

            <button name="mit_mark_sold" style="width:100%;">
                Mark as Sold
            </button>
        </form>
    <?php endif; ?>

    <?php if ($availability_key === 'sold'): ?>
        <!-- UNDO -->
        <form method="post"
              onsubmit="return confirm('Undo the most recent sale of this artwork?');">
            <input type="hidden" name="product_id" value="<?php echo $product->get_id(); ?>">
            <button name="mit_undo_sale" style="width:100%;">
                Mark as Available
            </button>
        </form>
    <?php endif; ?>

    <?php endif; ?>

</div>
<?php
    return ob_get_clean();
}

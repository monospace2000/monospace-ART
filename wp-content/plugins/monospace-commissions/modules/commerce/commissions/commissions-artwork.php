<?php
/**
 * Commissions: the finished piece becomes an artwork. On the balance order screen "Create artwork from this
 * commission" puts the finished-piece photo into the Media Library and opens Artworks -> New artwork pre-filled
 * (photo, status Sold, the commission's price, category "custom artwork"); the artwork gets a normal P / D SKU
 * from Core's generator (the C SKUs belong to the orderable options only). The order and the artwork are linked
 * both ways. Uses Core's extension points monospace_new_artwork_prefill / _categories / _created.
 *
 * @package monospace-commissions
 */

if (!defined('ABSPATH')) exit;

define('MS_COMMISSION_ARTWORK_CATEGORY', 'custom-artwork');

/**
 * origin "commission-{balance order id}" -> the balance order, or null
 */
function ms_commission_origin_order($origin) {
    if (!is_string($origin) || !preg_match('/^commission-(\d+)$/', $origin, $m)) return null;

    $final = wc_get_order((int) $m[1]);
    return ($final && ms_commission_original_order($final)) ? $final : null;
}

function ms_commission_artwork_post($order) {
    $post_id = (int) $order->get_meta('_ms_commission_artwork_post');
    return ($post_id && get_post($post_id)) ? $post_id : 0;
}

/**
 * ===========================
 * ADMIN: BUTTON / LINK ON THE ORDER SCREENS
 * ===========================
 */
add_action('woocommerce_admin_order_data_after_order_details', function($order) {
    $original = ms_commission_original_order($order);
    $is_balance = (bool) $original;
    if (!$is_balance && !ms_commission_order_items($order)) return;

    $post_id = ms_commission_artwork_post($order);
    echo '<div class="ms-order-panel ms-zone-main">';
    echo '<h3 style="margin:0 0 10px;font-size:14px">The finished artwork</h3>';

    if ($post_id) {
        $pid = (int) get_post_meta($post_id, '_qp_product_id', true);
        $sku = $pid ? get_post_meta($pid, '_sku', true) : '';
        echo '<p style="margin:0">Artwork: <a href="' . esc_url(get_edit_post_link($post_id, 'raw')) . '">' . esc_html(html_entity_decode(get_the_title($post_id))) . '</a>'
            . ($sku ? ' - SKU ' . esc_html($sku) : '') . ' (' . esc_html(get_post_status($post_id)) . ')</p>';
    } elseif (!$is_balance) {
        echo '<p style="margin:0;color:#646970">Once the commission is finished and shipped, create its artwork from the balance order.</p>';
    } elseif (!$order->get_meta('_ms_commission_finished_photo')) {
        echo '<p style="margin:0;color:#646970">Add the finished-piece photo in "Shipping the commission" above first - it becomes the artwork\'s image.</p>';
    } else {
        echo '<p style="margin:0"><button type="button" class="button button-primary" id="ms-make-artwork" data-order="' . esc_attr($order->get_id()) . '">Create artwork from this commission</button> <span id="ms-make-artwork-status" style="margin-left:8px"></span></p>';
        echo '<p style="margin:10px 0 0;color:#646970">Opens Artworks &rarr; New artwork with the photo, status Sold and the category "custom artwork" filled in. You add the title and details; it gets a normal P or D number.</p>';
        ?>
        <script>
        jQuery(function($){
            $('#ms-make-artwork').on('click', function(){
                var $b = $(this), $s = $('#ms-make-artwork-status');
                $b.prop('disabled', true); $s.text('Adding the photo to the Media Library...');
                $.post(ajaxurl, { action: 'ms_commission_make_artwork', nonce: <?php echo wp_json_encode(wp_create_nonce('ms_commission_make_artwork')); ?>, order_id: $b.data('order') }, function(r){
                    if (r && r.success) { $s.text('Opening...'); location.href = r.data.url; }
                    else { $b.prop('disabled', false); $s.text((r && r.data) || 'Could not start.'); }
                }).fail(function(){ $b.prop('disabled', false); $s.text('Could not start.'); });
            });
        });
        </script>
        <?php
    }
    echo '</div>';
}, 18);

/**
 * The photo into the Media Library (once), then the New artwork URL
 */
add_action('wp_ajax_ms_commission_make_artwork', function() {
    check_ajax_referer('ms_commission_make_artwork', 'nonce');
    if (!current_user_can('upload_files') || !current_user_can('edit_posts') || !current_user_can('edit_products')) wp_send_json_error('Insufficient permissions.');

    $final = wc_get_order(absint($_POST['order_id'] ?? 0));
    if (!$final || !ms_commission_original_order($final)) wp_send_json_error('This is not a commission balance order.');
    if (ms_commission_artwork_post($final)) wp_send_json_error('This commission already has an artwork.');

    $attachment_id = (int) $final->get_meta('_ms_commission_attachment');
    if (!$attachment_id || !wp_attachment_is_image($attachment_id)) {
        $photo = (string) $final->get_meta('_ms_commission_finished_photo');
        $paths = ms_commission_paths();
        if (!$photo || strpos($photo, $paths['url']) !== 0) wp_send_json_error('Add the finished-piece photo first.');

        $file = $paths['dir'] . substr($photo, strlen($paths['url']));
        if (!is_file($file)) wp_send_json_error('The finished-piece photo file is missing.');

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = wp_tempnam(basename($file));
        if (!$tmp || !copy($file, $tmp)) wp_send_json_error('Could not copy the photo.');

        $attachment_id = media_handle_sideload(['name' => preg_replace('/^\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_/', '', basename($file)), 'tmp_name' => $tmp], 0);
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            wp_send_json_error($attachment_id->get_error_message());
        }
        $final->update_meta_data('_ms_commission_attachment', $attachment_id);
        $final->save();
    }

    wp_send_json_success(['url' => admin_url('admin.php?page=monospace-artworks&origin=commission-' . $final->get_id())]);
});

/**
 * ===========================
 * CORE'S EXTENSION POINTS (New artwork)
 * ===========================
 */
add_filter('monospace_new_artwork_prefill', function($prefill, $origin) {
    $final = ms_commission_origin_order($origin);
    if (!$final || !current_user_can('edit_products')) return $prefill;

    $original = ms_commission_original_order($final);
    $price = '';
    foreach (ms_commission_order_items($original) as $item) {
        $price = (float) ms_get_commission_meta($item, 'full_price');
        break;
    }

    return [
        'no_prints' => true, // never prints from a commission (owner's rule, 2026-11-07)
        'image_id' => (int) $final->get_meta('_ms_commission_attachment'),
        'status' => 'sold',
        'price' => $price ? wc_format_decimal($price) : '',
        'notice' => sprintf('From commission order #%d (%s). Add the title and details - the category "custom artwork" is added for you.', $original->get_id(), trim($original->get_formatted_billing_full_name()) ?: 'customer'),
    ];
}, 10, 2);

add_filter('monospace_new_artwork_allow_prints', function($allow, $in) {
    return (!empty($in['origin']) && ms_commission_origin_order($in['origin'])) ? false : $allow;
}, 10, 2);

add_filter('monospace_new_artwork_categories', function($slugs, $in) {
    if (!empty($in['origin']) && ms_commission_origin_order($in['origin']) && get_category_by_slug(MS_COMMISSION_ARTWORK_CATEGORY)) {
        $slugs[] = MS_COMMISSION_ARTWORK_CATEGORY;
    }
    return $slugs;
}, 10, 2);

add_action('monospace_new_artwork_created', function($post_id, $product, $in) {
    $final = !empty($in['origin']) ? ms_commission_origin_order($in['origin']) : null;
    if (!$final) return;

    $original = ms_commission_original_order($final);
    $sku = $product ? $product->get_sku() : '';

    update_post_meta($post_id, '_ms_commission_order_id', $original->get_id());
    foreach ([$final, $original] as $order) {
        $order->update_meta_data('_ms_commission_artwork_post', $post_id);
        $order->save();
        $order->add_order_note(sprintf('Artwork created from this commission: post #%d%s.', $post_id, $sku ? ', SKU ' . $sku : ''));
    }
}, 10, 3);

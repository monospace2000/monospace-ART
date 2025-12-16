<?php
/*
 * Painting Buy Button / Related Post ID Audit
 * Temporary admin utility
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_management_page(
        'Painting Data Audit',
        'Painting Data Audit',
        'manage_options',
        'painting-data-audit',
        'render_painting_data_audit'
    );
});

function render_painting_data_audit() {
    echo '<div class="wrap">';
    echo '<h1>Painting Data Audit</h1>';

    /* -------------------------------------------------
       Helper: edit links
    --------------------------------------------------*/
    $edit_post_link = function ($id) {
        return '<a href="' . esc_url(get_edit_post_link($id)) . '">Edit</a>';
    };

    /* -------------------------------------------------
       Collect painting_buy_button IDs
    --------------------------------------------------*/
    $posts = get_posts([
        'post_type'      => 'any',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        's'              => '[painting_buy_button',
    ]);

    $button_ids = [];
    $missing_id_posts = [];

    foreach ($posts as $post) {
        if (!has_shortcode($post->post_content, 'painting_buy_button')) {
            continue;
        }

        if (preg_match_all('/\[painting_buy_button([^\]]*)\]/', $post->post_content, $matches)) {
            foreach ($matches[1] as $attrs) {
                if (preg_match('/id\s*=\s*["\']?(\d+)["\']?/', $attrs, $id_match)) {
                    $id = $id_match[1];
                    $button_ids[$id][] = $post;
                } else {
                    $missing_id_posts[$post->ID] = $post;
                }
            }
        }
    }

    /* -------------------------------------------------
       1. Missing numeric painting_buy_button id
    --------------------------------------------------*/
    echo '<h2>Posts missing numeric id in [painting_buy_button]</h2>';

    if ($missing_id_posts) {
        foreach ($missing_id_posts as $post) {
            printf(
                '<p><strong>%s</strong> (Post ID %d) — %s</p>',
                esc_html(get_the_title($post)),
                $post->ID,
                $edit_post_link($post->ID)
            );
        }
    } else {
        echo '<p><em>No issues found.</em></p>';
    }

    /* -------------------------------------------------
       2. Duplicate painting_buy_button ids
    --------------------------------------------------*/
    echo '<h2>Duplicate [painting_buy_button] IDs</h2>';

    $found = false;

    foreach ($button_ids as $id => $posts_using_id) {
        if (count($posts_using_id) > 1) {
            $found = true;
            echo "<p><strong>ID {$id}</strong></p><ul>";
            foreach ($posts_using_id as $post) {
                printf(
                    '<li>%s (Post ID %d) — %s</li>',
                    esc_html(get_the_title($post)),
                    $post->ID,
                    $edit_post_link($post->ID)
                );
            }
            echo '</ul>';
        }
    }

    if (!$found) {
        echo '<p><em>No duplicate IDs found.</em></p>';
    }

    /* -------------------------------------------------
       Collect product linkbacks & SKUs
    --------------------------------------------------*/
    $products = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'any',
        'posts_per_page' => -1,
    ]);

    $linkbacks = [];
    $missing_linkbacks = [];
    $skus = [];

    foreach ($products as $product) {
        $related = get_post_meta($product->ID, '_related_post_id', true);
        $sku     = get_post_meta($product->ID, '_sku', true);

        if ($sku) {
            $skus[$sku][] = $product;
        }

        if (!is_numeric($related)) {
            $missing_linkbacks[] = $product;
        } else {
            $linkbacks[$related][] = $product;
        }
    }

    /* -------------------------------------------------
       3. Products missing numeric _related_post_id
    --------------------------------------------------*/
    echo '<h2>Products missing numeric _related_post_id</h2>';

    if ($missing_linkbacks) {
        foreach ($missing_linkbacks as $product) {
            $sku = get_post_meta($product->ID, '_sku', true);
            printf(
                '<p><strong>%s</strong> (Product ID %d, SKU: %s) — %s</p>',
                esc_html(get_the_title($product)),
                $product->ID,
                esc_html($sku ?: '—'),
                $edit_post_link($product->ID)
            );
        }
    } else {
        echo '<p><em>No issues found.</em></p>';
    }

    /* -------------------------------------------------
       4. Duplicate product linkbacks
    --------------------------------------------------*/
    echo '<h2>Duplicate product _related_post_id linkbacks</h2>';

    $found = false;

    foreach ($linkbacks as $post_id => $products_linked) {
        if (count($products_linked) > 1) {
            $found = true;
            echo '<p><strong>Post ID ' . intval($post_id) . ' — ' . esc_html(get_the_title($post_id)) . '</strong></p>';
            echo '<ul>';
            foreach ($products_linked as $product) {
                $sku = get_post_meta($product->ID, '_sku', true);
                printf(
                    '<li>%s (Product ID %d, SKU: %s) — %s</li>',
                    esc_html(get_the_title($product)),
                    $product->ID,
                    esc_html($sku ?: '—'),
                    $edit_post_link($product->ID)
                );
            }
            echo '</ul>';
        }
    }

    if (!$found) {
        echo '<p><em>No duplicate linkbacks found.</em></p>';
    }

    /* -------------------------------------------------
       5. Duplicate SKUs
    --------------------------------------------------*/
    echo '<h2>Duplicate Product SKUs</h2>';

    $found = false;

    foreach ($skus as $sku => $products_with_sku) {
        if (count($products_with_sku) > 1) {
            $found = true;
            echo '<p><strong>SKU: ' . esc_html($sku) . '</strong></p><ul>';
            foreach ($products_with_sku as $product) {
                printf(
                    '<li>%s (Product ID %d) — %s</li>',
                    esc_html(get_the_title($product)),
                    $product->ID,
                    $edit_post_link($product->ID)
                );
            }
            echo '</ul>';
        }
    }

    if (!$found) {
        echo '<p><em>No duplicate SKUs found.</em></p>';
    }

    echo '</div>';
}

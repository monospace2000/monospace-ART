<?php


function clean_staging_site() {
    if ( ! is_admin() ) return;

    global $wpdb;

    // --- STEP 1: Identify posts to KEEP ---
    $keep_posts = [];

    // 10 miniature paintings
    $miniatures = get_posts([
        'post_type'      => 'post',
        'posts_per_page' => 10,
        'tax_query'      => [
            [
                'taxonomy' => 'painting_type',
                'field'    => 'slug',
                'terms'    => 'miniature',
            ],
        ],
        'fields' => 'ids',
    ]);
    $keep_posts = array_merge($keep_posts, $miniatures);

    // 10 regular paintings
    $paintings = get_posts([
        'post_type'      => 'post',
        'posts_per_page' => 10,
        'tax_query'      => [
            [
                'taxonomy' => 'painting_type',
                'field'    => 'slug',
                'terms'    => 'painting',
            ],
        ],
        'fields' => 'ids',
    ]);
    $keep_posts = array_merge($keep_posts, $paintings);

    // 10 drawings
    $drawings = get_posts([
        'post_type'      => 'post',
        'posts_per_page' => 10,
        'tax_query'      => [
            [
                'taxonomy' => 'painting_type',
                'field'    => 'slug',
                'terms'    => 'drawing',
            ],
        ],
        'fields' => 'ids',
    ]);
    $keep_posts = array_merge($keep_posts, $drawings);

    // 5 blog posts
    $blogs = get_posts([
        'post_type'      => 'post',
        'posts_per_page' => 5,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
    ]);
    $keep_posts = array_merge($keep_posts, $blogs);

    // --- STEP 2: Keep all pages ---
    $pages = get_posts([
        'post_type'      => 'page',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);
    $keep_posts = array_merge($keep_posts, $pages);

    // --- STEP 3: Extract linked product IDs from shortcodes ---
    $keep_products = [];

    foreach ($keep_posts as $post_id) {
        $content = get_post_field('post_content', $post_id);
        if (preg_match_all('/\[painting buy button id="(\d+)"\]/', $content, $matches)) {
            foreach ($matches[1] as $prod_id) {
                $keep_products[] = intval($prod_id);
            }
        }
    }

    $keep_products = array_unique($keep_products);

    // --- STEP 4: Delete all other posts ---
    $all_posts = get_posts([
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    $posts_to_delete = array_diff($all_posts, $keep_posts);
    foreach ($posts_to_delete as $pid) {
        wp_delete_post($pid, true);
    }

    // --- STEP 5: Delete all other products ---
    $all_products = get_posts([
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    $products_to_delete = array_diff($all_products, $keep_products);
    foreach ($products_to_delete as $pid) {
        wp_delete_post($pid, true);
    }

    // --- STEP 6: Delete unattached media ---
    $all_attachments = get_posts([
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ]);

    $attachments_to_keep = [];
    foreach ($keep_posts as $pid) {
        $attachments = get_attached_media('', $pid);
        $attachments_to_keep = array_merge($attachments_to_keep, wp_list_pluck($attachments, 'ID'));
    }

    $attachments_to_delete = array_diff($all_attachments, $attachments_to_keep);
    foreach ($attachments_to_delete as $aid) {
        wp_delete_attachment($aid, true);
    }

    echo 'Staging site cleanup completed!';
}

// To run it once, uncomment this line and refresh an admin page:
// clean_staging_site();

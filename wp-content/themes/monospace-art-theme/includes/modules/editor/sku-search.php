<?php

// Index only the last 4 digits of the SKU for search, allowing partial matches
add_filter('relevanssi_content_to_index', function ($content, $post) {
    $sku = get_post_meta($post->ID, '_sku', true);
    if ($sku) {
        // Extract last 4 digits
        if (preg_match('/(\d{4})$/', $sku, $matches)) {
            $last4 = $matches[1];
            // Add full last 4 digits and each partial substring
            for ($i = 1; $i <= 4; $i++) {
                $content .= ' ' . substr($last4, -$i);
            }
        }
    }
    return $content;
}, 10, 2);

// Boost relevance for exact or partial last-4 matches
add_filter('relevanssi_hits_filter', function ($hits, $query) {
    $q = $query->query_vars['s'];
    foreach ($hits as $id => $hit) {
        $sku = get_post_meta($hit->ID, '_sku', true);
        if ($sku && preg_match('/(\d{4})$/', $sku, $matches)) {
            $last4 = $matches[1];
            if (strpos($last4, $q) !== false) {
                // Boost relevance
                $hits[$id]->relevance += 10;
            }
        }
    }
    return $hits;
}, 10, 2);



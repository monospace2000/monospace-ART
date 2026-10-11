<?php
/**
 * One-time migration: Normalise pa_size term names to W" × H" format.
 * Run once at: https://art.monospace.com/migrate-sizes.php?key=mnspc-migrate
 * Delete this file afterwards.
 */

if (($_GET['key'] ?? '') !== 'mnspc-migrate') {
    die('Forbidden.');
}

define('ABSPATH', __DIR__ . '/');
require_once __DIR__ . '/wp-load.php';

if (!current_user_can('manage_woocommerce')) {
    die('Not authorised.');
}

$taxonomy = 'pa_size';

// Map: old name => canonical name
// Where canonical already exists as a separate term, products will be
// reassigned to the canonical term and the old term deleted.
$renames = [
    '10×10'      => '10" × 10"',
    '11×14'      => '11" × 14"',
    '12×12'      => '12" × 12"',
    '12×16'      => '12" × 16"',
    '12×18'      => '12" × 18"',
    '14×14'      => '14" × 14"',
    '16×16'      => '16" × 16"',
    '16×20'      => '16" × 20"',
    '18×18'      => '18" × 18"',
    '18×24'      => '18" × 24"',
    '20×30'      => '20" × 30"',
    '24×36'      => '24" × 36"',
    '8×10'       => '8" × 10"',
    '20″ × 16″'  => '20" × 16"',
    '16″ × 20″'  => '16" × 20"',
];

$log = [];

foreach ($renames as $old_name => $new_name) {
    $old_term = get_term_by('name', $old_name, $taxonomy);
    if (!$old_term) {
        $log[] = "SKIP (not found): $old_name";
        continue;
    }

    $new_term = get_term_by('name', $new_name, $taxonomy);

    if ($new_term) {
        // Canonical term already exists — reassign products and delete old term
        $products = get_posts([
            'post_type'      => 'product',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [[
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $old_term->term_id,
            ]],
        ]);

        foreach ($products as $product_id) {
            wp_remove_object_terms($product_id, $old_term->term_id, $taxonomy);
            wp_set_object_terms($product_id, $new_term->term_id, $taxonomy, true);
        }

        wp_delete_term($old_term->term_id, $taxonomy);
        $log[] = "MERGED ($old_name → $new_name, " . count($products) . " products reassigned)";
    } else {
        // Just rename
        $result = wp_update_term($old_term->term_id, $taxonomy, ['name' => $new_name]);
        if (is_wp_error($result)) {
            $log[] = "ERROR renaming $old_name: " . $result->get_error_message();
        } else {
            $log[] = "RENAMED: $old_name → $new_name";
        }
    }
}

echo '<pre>' . implode("\n", $log) . "\n\nDone. Delete this file.</pre>";

<?php
/**
 * Search forms: the button does nothing while the field is empty (or only spaces) - no trip to an empty results page;
 * the cursor goes to the field instead (2026-10-22, owner: "Search Form should ignore clicks on button when field is
 * empty"). Every search form on the page - the [search] shortcode (sidebar-shortcodes.php), WordPress's own, the Search
 * block - the button or Enter alike. A few lines added to the menu script, which every page loads.
 *
 * @package monospace-art-theme
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', function () {
    wp_add_inline_script( 'monospace-art-menu', <<<'JS'
document.addEventListener('submit', function (e) {
    var f = e.target, q = f.querySelector && f.querySelector('input[name="s"]');
    if (!q || (f.getAttribute('role') !== 'search' && !f.classList.contains('wp-block-search'))) return;
    if (q.value.trim() === '') { e.preventDefault(); q.value = ''; q.focus(); }
}, true);
JS
    );
}, 20 ); // after the theme's scripts are registered (enqueue.php, priority 1)

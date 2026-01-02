<?php
// Enqueue custom cart JS
add_action('wp_enqueue_scripts', function() {
    if (is_cart()) {
        wp_add_inline_script('jquery-core', "
            (function($){
                function replaceRemoveLinks() {
                    $('td.product-remove').each(function() {
                        var cell = $(this);
                        var link = cell.find('a').first();
                        if (link.length && !cell.data('processed')) {
                            var href = link.attr('href');
                            var target = link.attr('target');
                            var title = link.attr('title');
                            var onclick = link.attr('onclick');

                            cell.empty();
                            var cross = $('<a>❌</a>');
                            cross.attr('href', href || '#');
                            if(target) cross.attr('target', target);
                            if(title) cross.attr('title', title);
                            if(onclick) cross.attr('onclick', onclick);
                            cross.css({
                                cursor: 'pointer',
                                color: '#d00',
                                fontSize: '1.2em',
                                textDecoration: 'none'
                            });
                            cell.append(cross);
                            cell.data('processed', true); // prevent double-processing
                        }
                    });
                }

                // Initial run on page load
                $(document).ready(replaceRemoveLinks);

                // Re-run after WooCommerce cart updates via AJAX
                $(document.body).on('updated_cart_totals updated_cart_fragment', replaceRemoveLinks);

            })(jQuery);
        ");
    }
});
?>

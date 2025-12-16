<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */



/**
 * Add keyboard shortcut (⌘S / Ctrl+S) to save posts/products in WP admin
 *
 * Features:
 * - Works on post and product edit screens
 * - Overrides browser default save (prevent page save dialog)
 * - Triggers the "Publish" or "Update" button
 */
add_action( 'admin_footer-post.php', 'monospace_save_shortcut' );
add_action( 'admin_footer-post-new.php', 'monospace_save_shortcut' );

function monospace_save_shortcut() {
    ?>
    <script>
    (function($){
        $(document).on('keydown', function(e) {
            // Detect ⌘+S (Mac) or Ctrl+S (Windows/Linux)
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 's') {
                e.preventDefault(); // prevent browser "Save Page" dialog

                // Trigger the "Publish" / "Update" button if it exists
                var $button = $('#publish');
                if ($button.length) {
                    $button.trigger('click');
                }
            }
        });
    })(jQuery);
    </script>
    <?php
}

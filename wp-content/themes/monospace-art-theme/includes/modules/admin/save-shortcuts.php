<?php
/**
 * Save Keyboard Shortcuts
 *
 * Adds Command/Ctrl+S keyboard shortcut for saving posts and products in WordPress admin.
 * This provides a familiar save experience for users coming from desktop applications.
 *
 * FUNCTIONALITY:
 * - Intercepts Cmd+S (Mac) or Ctrl+S (Windows/Linux) keypresses
 * - Prevents browser's default "save page" dialog
 * - Triggers WordPress's publish/update button click
 * - Works on both post and product edit screens
 *
 * TECHNICAL:
 * - Adds inline JavaScript to admin pages
 * - Listens for keydown events with metaKey or ctrlKey + 'S'
 * - Triggers click on #publish button (WordPress standard)
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

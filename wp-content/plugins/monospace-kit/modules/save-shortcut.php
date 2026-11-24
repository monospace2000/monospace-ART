<?php
/**
 * ⌘/Ctrl+S saves (from the art site's monospace-admin-tools, save-shortcuts.php). On the classic edit screens (media items, and
 * products on a shop) it presses Publish / Update instead of opening the browser's "Save page" dialog. The block editor already
 * saves with ⌘/Ctrl+S by itself.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

function monospace_kit_save_shortcut() {
	echo "<script>document.addEventListener('keydown',function(e){if((e.metaKey||e.ctrlKey)&&!e.shiftKey&&!e.altKey&&e.key&&e.key.toLowerCase()==='s'){var b=document.getElementById('publish');if(b){e.preventDefault();b.click();}}});</script>\n";
}
add_action( 'admin_footer-post.php', 'monospace_kit_save_shortcut' );
add_action( 'admin_footer-post-new.php', 'monospace_kit_save_shortcut' );

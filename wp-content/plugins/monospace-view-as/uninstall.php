<?php
/** View As: deleting the plugin removes its settings and the list of people each administrator looked at last */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

delete_option( 'ms_view_as_settings' );
delete_metadata( 'user', 0, 'ms_view_as_recent', '', true );

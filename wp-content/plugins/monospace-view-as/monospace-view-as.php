<?php
/**
 * Plugin Name: monospace View As
 * Plugin URI: https://art.monospace.com
 * Update URI: false
 * Description: See the site exactly as one user sees it - read-only. A red "View As" button in the admin toolbar picks the user; while it is on, the toolbar is red with just a Switch back button. Nothing done there is sent or saved for them: no emails, no orders, no comments, no password or profile changes. With WooCommerce, items added to the cart live in a throw-away cart that is cleared when you switch back.
 * Version: 1.1.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Hens Breet
 * License: GPL-2.0-or-later
 *
 * Works on any WordPress site, with or without WooCommerce; it needs nothing else. You stay logged in as yourself (nothing is swapped, the
 * way a "log in as" plugin does it) - only who the page is drawn for changes, and the writes are shut (includes/view-as.php). Settings > View As.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MS_VIEW_AS_FILE', __FILE__ );
define( 'MS_VIEW_AS_DIR', __DIR__ );

// Loaded at once, not on init: the current user and the gate must be in place before WordPress and WooCommerce start their own work on init
require_once __DIR__ . '/includes/settings.php';
require_once __DIR__ . '/includes/view-as.php';
require_once __DIR__ . '/modules/admin/view-as-toolbar.php';
require_once __DIR__ . '/modules/admin/view-as-users.php';

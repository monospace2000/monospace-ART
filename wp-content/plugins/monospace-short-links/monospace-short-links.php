<?php
/**
 * Plugin Name: monospace ART - Short Links
 * Update URI: false
 * Description: Short, shareable addresses with fewer slashes - example.com/shop, /paintings - that lead to a page, a category or any address. Edited under Pages → Short links.
 * Version: 1.0.0
 * Author: Hens Breet
 * Requires at least: 6.0
 *
 * A standalone, generic plugin (owner's wish, 2026-10-30) - no monospace ART dependencies, usable on any WordPress
 * site. Folder monospace-short-links (a plain name could be matched to another developer's plugin on WordPress.org).
 *
 *   modules/short-links-redirect.php   the list (option monospace_short_links), resolving a target, the redirect
 *   modules/short-links-screen.php     Pages → Short links: add, change, remove, ideas from the menus
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/modules/short-links-redirect.php';
require_once __DIR__ . '/modules/short-links-screen.php';

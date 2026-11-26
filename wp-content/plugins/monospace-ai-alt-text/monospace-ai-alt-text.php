<?php
/**
 * Plugin Name: monospace ART - AI alt text
 * Update URI: false
 * Description: Listing by voice (photo, dictate, publish) and buttons that ask Claude to write alt text and suggest tags: on Artworks > New artwork and an artwork's edit screen, and for blog posts a Writing help panel in the post editor (alt text, tags, category, excerpt, a caption to share, an artwork card), a Before you publish list and Posts > Alt text review. Nothing is written by itself. Needs an Anthropic API key in wp-config.php; settings under Artworks > AI writing.
 * Version: 1.3.0
 * Author: Hens Breet
 *
 * A feature plugin (ARCHITECTURE.md): it depends only on monospace ART - Core (the mu-plugin) and can be switched off
 * without breaking anything else - off, the buttons are gone and the alt text box on the New artwork screen stays.
 * monospace_feature( 'ai-alt-text' ) is true while it's on.
 *
 *   modules/ai-client.php         the key, the model, the request to the Anthropic Messages API, the resized picture
 *   modules/ai-prompts.php        the style rules and the tag rules (the owner's alt text style, workflows/alt-text.md in the KB)
 *   modules/ai-ajax.php           the three AJAX calls: write alt text, suggest tags, test the connection (none writes anything)
 *   modules/ai-new-artwork.php    the buttons and their script on Artworks > New artwork
 *   modules/ai-story.php          Clean up beside the story's Dictate button (New artwork, Quick Artwork Editor)
 *   modules/ai-voice-listing.php  Artworks > Listing by voice (2026-11-26): photo, dictate, check, Publish
 *   modules/ai-settings.php       Artworks > AI writing: connection state, the model
 *   modules/ai-post-tools.php     blog posts (2026-11-17): the editor script and its calls - alt text, tags, category, excerpt, caption, artwork finder
 *   assets/post-tools.js          the block editor's Writing help panel and Before you publish list
 *   modules/ai-alt-review.php     Posts > Alt text review (and the Posts list's bulk action): suggestions for pictures without alt text, saved when kept
 */

if ( ! defined( 'ABSPATH' ) ) exit;
if ( ! function_exists( 'monospace_load_modules' ) ) return; // needs monospace ART - Core (wp-content/mu-plugins)

define( 'MONOSPACE_AI_DIR', __DIR__ );

add_filter( 'monospace_feature_ai-alt-text', '__return_true' );
add_action( 'init', function () { monospace_load_modules( __DIR__ . '/modules' ); } );

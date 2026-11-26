# monospace Kit (shared plugin for every monospace WordPress site)

Plugin `wp-content/plugins/monospace-kit/` (listed as "monospace Kit"). **Switch parts on or off per site: Settings > monospace Kit** (the admin UI style: one closed panel per group - Visitors, Media Library, Editing - with a state chip "2 of 5 on" and an "On: ..." line; owner 2026-11-23: every admin screen on every site follows the admin GUI guidelines)
(option `monospace_kit_modules`; once saved it overrides the settings file's `modules`). Settings file:
`wp-content/mu-plugins/monospace-kit-config.php` with `monospace_kit_config()`: `modules` (first state), `own` (parts the site does with
its own code: shown as "This site has its own", never loaded), `ga4`, `ga4_consent`, `contact`, `alt` => [ 'about' => ... ].
**Edit the master here, copy the whole folder unchanged to each site.** Overview of all sites: `infrastructure/standard-site-kit.md`.

| Part | Replaces | Notes |
|---|---|---|
| `analytics` | Site Kit | `ga4` = measurement ID (no tag for logged-in editors); `ga4_consent` = Google consent mode, denied until the WP Consent API says allow (art's cookie banner), printed first in `<head>` |
| `contact` | Contact Form 7 (+ Akismet, WP Mail SMTP) | `[monospace_contact]`, and `[contact-form-7 ...]` while CF7 is off; CF7 markup + `wpcf7_form_elements` filter; `contact` => `to`, `from`, `privacy`, `subject_prefix`; spam: hidden field + signed time stamp (3 s to 1 day); admin-post.php, back with `?contact=sent|error#contact` |
| `spotlight` | (new 2026-11-23; the art site's Look > Spotlight) | the monospace light: pool (body::before) + beam (body::after), per device position / size / angle, beam width, light + shade colors; Appearance > Spotlight with live preview and drag; option `monospace_kit_spotlight`, **off until saved on**. Art marks it `own` (its theme's). Sites without WordPress: `static/spotlight.css` (link it, override the variables) |
| `related-posts` | Jetpack Related Posts (food) | from the art site's `related-posts-shortcode.php` (art lists it as `own`: artwork items + Look editor). Under a post: posts sharing its tags (most shared first), grid or stack, square thumbnails; automatic or `[related_posts]`; settings = `'related'` in the site's config (heading, limit, min_tags, fill, layout, columns, title, square, auto, exclude_categories); sets Jetpack's `jetpack_relatedposts_filter_enabled_for_request` to false while on |
| `lightbox` | Easy FancyBox, Featured Image Zoom | the art site's `ms-lightbox.js` (copy; edit on the art site first). Posts: every picture; pages: class `lightbox`; class `nolightbox` opts out |
| `media-replace` | Enable Media Replace | box "Replace file" on a media item's edit screen; same type, same name and address, sizes remade, page cache purged |
| `remove-background` | (the art site's own, 2026-11-18) | button in the Media Library's Edit Image row: swipe over colors to make them transparent (like Keynote); Save makes a NEW PNG "<title> (no background)", the original is never changed. Copy of monospace-admin-tools' module; edit there first |
| `media-usage` | (the art site's own "Used in", 2026-10-19) | **Used in** column in the Media Library list (sortable), Used / Not used filter (list and grid), "Used in" in the file details, "Rebuild usage"; nightly rebuild (WP-Cron 04:30). General version: featured images, galleries, text, patterns/menus, site icon, logo, widgets, theme code. A site adds its own with filters `monospace_kit_usage_types`, `_meta` (an ID, IDs, or any stored value naming pictures), `_options`, `_dirs` (color adds its BEAF sliders). Same meta names as the art site's (`_ms_used_in`, `_ms_used_count`) |
| `cookie-banner` | (art's monospace-privacy banner, made general) | opt-in dark glass sheet at the bottom; Accept / No thanks; stored in localStorage `cookieConsent`; switches Google consent to granted; turns on the analytics part's "denied by default"; a link to `#cookie-settings` reopens it |
| `ai-alt-text` | (Sightings' alt.php / art's AI alt text, made general) | new uploads without a description get one ~20 s later (WP-Cron, Claude `claude-sonnet-5-5`, the owner's alt-text rules); never replaces; "Write a description" button in the file details; needs `MONOSPACE_ANTHROPIC_KEY` in wp-config.php (copied to music, color, food, /hens 2026-11-23; backups in `~/backups/wp-config/`); `alt.about` = one line on what the site's pictures are |
| `alt-sync` | (art: alt-text-sync.php) | a picture's description is the same in the Media Library and in the image blocks of every post / page / product |
| `heic` | (art Core: heic-uploads.php) | HEIC/HEIF uploads become JPEG with the server's ffmpeg (`/usr/local/bin/ffmpeg`) |
| `video-poster` | (art Core video-poster.php + theme video-preview.php) | an uploaded video gets a still ("<title> poster"); video blocks without a poster use it, else preload=metadata + #t=0.001 |
| `youtube-poster` | (art: youtube-poster.php) | a post with a YouTube video and no pictures gets the video's thumbnail as featured image, once |
| `save-shortcut` | (art: save-shortcuts.php) | ⌘/Ctrl+S presses Publish / Update on classic edit screens |
| `post-navigation` | (art: admin-post-navigation.php) | "← Newer / Older →" in the toolbar on an edit screen, following your list's filters and sort (posts, pages, products) |
| `hide-blocks` | Block Visibility | the art site's "Hide this block" (`msHidden`); blocks hidden with Block Visibility (`blockVisibility.hideBlock`) stay hidden |
| `thumbnail-column` | Display Featured Image In Post List | 60 px featured image column in Posts and Pages |

The art site lists 12 parts as `own` (its theme, Core, monospace-admin-tools, monospace-ai-alt-text and monospace-privacy do them; its Used in also knows print files, home boxes and the RML "unused" folder); only `analytics` and `contact` are on there. Sightings lists `ai-alt-text` as own (theme alt.php, "Digital drawing"). The Kit parts copied from art are copies: change art first, then copy.

## Ideas for later (owner: "no need to build that yet", 2026-11-23)
**Settings > Contact form screen** in the Kit, the same on every site, so the owner can manage the form himself: fields (add,
remove, rename, reorder; text, email, phone, dropdown, checkbox, file upload; a "required" switch each), the thank-you / error
text or a thank-you page, recipients (several, CC/BCC, sender name and address), the email layout (subject template, plain or
formatted), an automatic reply to the visitor, and a list of received messages in wp-admin. Today all of this is fixed in code /
the per-site config file. Apple Reminder set for 2026-11-30.

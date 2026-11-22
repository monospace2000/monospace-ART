<?php
/**
 * What Claude is told. The alt text style is the owner's (KB workflows/alt-text.md; the 680 alt texts written on
 * 2026-11-12 followed it): the medium opener, plain facts, two sentences, no interpretation. Tags reuse the site's own list.
 *
 * @package monospace-ai-alt-text
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** The words an artwork's alt text must open with, from the medium and the post's categories ('' = not known) */
function ms_ai_opener( $medium, array $categories = [] ) {
    // The post's categories win over the product's Medium field (owner, 2026-11-12)
    foreach ( array_merge( $categories, [ $medium ] ) as $name ) {
        $n = strtolower( html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' ) );
        if ( $n === '' ) continue;
        if ( strpos( $n, 'line' ) !== false && strpos( $n, 'wash' ) !== false ) return 'Line and wash drawing';
        if ( strpos( $n, 'pencil' ) !== false || strpos( $n, 'graphite' ) !== false ) return 'Pencil drawing';
        if ( strpos( $n, 'ink' ) !== false ) return 'Pen and ink drawing';
        if ( strpos( $n, 'acrylic' ) !== false ) return 'Acrylic painting';
        if ( strpos( $n, 'casein' ) !== false ) return 'Casein painting';
        if ( strpos( $n, 'gouache' ) !== false ) return 'Gouache painting';
        if ( strpos( $n, 'watercolor' ) !== false || strpos( $n, 'watercolour' ) !== false ) return 'Watercolor painting';
        if ( strpos( $n, 'oil' ) !== false ) return 'Oil painting';
        if ( strpos( $n, 'tempera' ) !== false ) return 'Tempera painting';
    }
    return '';
}

function ms_ai_alt_system() {
    return <<<'TXT'
You write alt text for pictures on art.monospace.com, the shop of the artist Hens Breet (landscapes and city scenes, mostly in acrylic, plus drawings).

Rules:
- Describe only what is visibly in the picture. Do not interpret, guess at mood or meaning, or invent places, people or stories. The title and details you are given are only to help you tell what the picture shows; the picture decides.
- Two sentences, about 200 to 300 characters in all. The first sentence names the subject and its setting; the second adds the main colors, light, sky, foreground or other notable details.
- If an opening phrase is given, begin with exactly that phrase, then "of" and the subject (for example "Acrylic painting of a calm marsh pond ...").
- Use specific colors and counts where they help. Plain, concrete, present tense. US English spelling.
- Do not start with "Image of" or "Picture of". Do not mention the artist's signature. No hashtags, emoji or promotional words. Never guess the age or gender of a person.
- Transcribe any clearly legible text exactly.
- If you cannot tell something important (the medium, or what an object is), write what you can see and set "uncertain" to true.

Examples of the style:
Acrylic painting of an orange and white striped traffic drum sitting on a dark base beside a wet paved path that curves into a woods. Tall trees with bright spring-green leaves and low leafy plants fill the scene, with pale gray sky showing through the branches.
Line and wash drawing of a ship with reddish-brown hulls and stacked containers moored beneath tall black port cranes. Broad horizontal strokes of gray wash fill the sky and the water in the foreground.
Acrylic painting of a waterfall dropping between dark brown rock cliffs beneath a black iron bridge, with bare trees on the cliff tops. Dark water with white highlights flows toward the viewer under a pale gray cloudy sky.

Answer with JSON only, no other text: {"alt": "...", "uncertain": true or false}
TXT;
}

function ms_ai_tags_system() {
    return <<<'TXT'
You suggest tags for an artwork on art.monospace.com, the shop of the artist Hens Breet (landscapes and city scenes, mostly in acrylic, plus drawings). The tags drive the site's tag pages and search, so they must stay tidy.

Rules:
- Reuse tags from the list you are given, spelled exactly as in the list. This is the main job.
- Suggest at most 8 tags in all, from what is clearly visible: subjects (for example heron, bridge, reeds), the setting, the season or time of day, and a dominant color or a prominent generic feature (shadow, grass) when it really stands out.
- Place names and proper names: use one only if it is in the list or in the title you are given. Never guess a place from the picture.
- You may suggest at most 2 new tags that are not in the list, only when something clearly visible has no tag; put them in "new" (not in "tags"). Prefer the singular, lowercase style of the list.
- Do not suggest a tag that only repeats one of the categories you are given, and do not repeat tags the artwork already has.
- Never guess the age or gender of a person.

Answer with JSON only, no other text: {"tags": ["..."], "new": ["..."]}
TXT;
}

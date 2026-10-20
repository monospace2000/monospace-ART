<?php
/**
 * Miniature Set Block
 *
 * Custom Gutenberg block for displaying 2-3 miniature paintings side by side
 * with a shared caption. Perfect for showcasing small artworks as a collection.
 *
 * FUNCTIONALITY:
 * - Registers custom Gutenberg block 'monospace/miniature-set'
 * - Allows users to add 2-3 images in a horizontal row
 * - Supports shared caption for the entire set
 * - Responsive: stacks vertically on mobile devices
 *
 * USAGE IN EDITOR:
 * 1. Add "Miniature Set" block from block inserter
 * 2. Upload or select 2-3 images
 * 3. Add optional caption that applies to all images
 * 4. Block will display images side-by-side on desktop, stacked on mobile
 *
 * STYLING:
 * - Uses CSS class .miniature-set for custom styling
 * - Responsive breakpoints defined in theme CSS
 *
 * RELATED:
 * - clickable-feed-image.php handles making these clickable on archive pages
 *
 * @package monospace-art-theme
 */

if (!defined('ABSPATH')) exit;

// Register directly since this file is loaded during init
wp_register_script(
    'monospace-miniature-set-editor',
    MONOSPACE_ART_THEME_URI . '/includes/modules/frontend/miniature-set-block.js',
    ['wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components'],
    MONOSPACE_ART_THEME_VERSION
);

register_block_type('monospace/miniature-set', [
    'editor_script' => 'monospace-miniature-set-editor',
    'render_callback' => 'monospace_miniature_set_render',
    'attributes' => [
        'images' => [
            'type' => 'array',
            'default' => []
        ],
        'caption' => [
            'type' => 'string',
            'default' => ''
        ],
        'gap' => [
            'type' => 'number',
            'default' => 20
        ],
        'padding' => [
            'type' => 'number',
            'default' => 0
        ]
    ]
]);

function monospace_miniature_set_render($attributes) {
    $images = $attributes['images'] ?? [];
    $caption = $attributes['caption'] ?? '';
    $gap = $attributes['gap'] ?? 20;
    $padding = $attributes['padding'] ?? 0;

    if (empty($images)) {
        return '';
    }

    // Each image is a column of the content width (660px on feeds, 1000px on posts - see
    // image-sizes.php), on phones too. The wp-image-* class lets the lightbox open the
    // full-size original.
    $columns = max(1, count($images));
    $column_width = (int) round((is_singular() ? 1000 : 660) / $columns);
    $style = 'width: 100%; height: auto; display: block; border-radius: 5px;';

    ob_start();
    ?>
    <figure class="monospace-miniature-set" style="padding: <?php echo $padding; ?>px;">
        <div class="miniature-set-grid" style="display: grid; grid-template-columns: repeat(<?php echo count($images); ?>, 1fr); gap: <?php echo $gap; ?>px;">
            <?php foreach ($images as $image):
                $attachment_id = !empty($image['id']) ? (int) $image['id'] : attachment_url_to_postid($image['url'] ?? '');
                $img = $attachment_id ? wp_get_attachment_image($attachment_id, 'large', false, [
                    'class'   => 'miniature-set-image wp-image-' . $attachment_id,
                    'alt'     => $image['alt'] ?? '',
                    'style'   => $style,
                    'sizes'   => '(max-width: 700px) ' . (int) round(100 / $columns) . 'vw, ' . $column_width . 'px',
                    'loading' => 'lazy',
                ]) : '';
                if ($img):
                    echo $img;
                else: ?>
                <img class="miniature-set-image" src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" style="<?php echo $style; ?>" loading="lazy" />
            <?php endif; endforeach; ?>
        </div>
        <?php if ($caption): ?>
            <figcaption class="miniature-set-caption" style="margin-top: 12px; text-align: center; font-style: italic; color: #666;">
                <?php echo esc_html($caption); ?>
            </figcaption>
        <?php endif; ?>
    </figure>
    <?php
    return ob_get_clean();
}

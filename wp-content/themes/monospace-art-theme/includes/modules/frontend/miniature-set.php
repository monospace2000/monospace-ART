<?php
/**
 * Miniature Set Block
 * Display 2-3 images side by side with shared caption
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

    ob_start();
    ?>
    <figure class="monospace-miniature-set" style="padding: <?php echo $padding; ?>px;">
        <div class="miniature-set-grid" style="display: grid; grid-template-columns: repeat(<?php echo count($images); ?>, 1fr); gap: <?php echo $gap; ?>px;">
            <?php foreach ($images as $image): ?>
                <img class="miniature-set-image" src="<?php echo esc_url($image['url']); ?>" alt="<?php echo esc_attr($image['alt']); ?>" style="width: 100%; height: auto; display: block; border-radius: 5px;" />
            <?php endforeach; ?>
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

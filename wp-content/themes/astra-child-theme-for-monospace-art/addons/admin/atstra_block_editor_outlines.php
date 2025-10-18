<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */
// Color-coded outlines for nested Gutenberg blocks (adaptive)
add_action( 'enqueue_block_editor_assets', function() {
    wp_add_inline_style(
        'wp-block-editor',
        '
        /* Base look */
        .block-editor-block-list__block {
            outline: 1px dotted rgba(0,0,0,0.25);
            outline-offset: -2px;
        }

        /* Nesting levels (light mode) */
        .block-editor-block-list__block .block-editor-block-list__block { outline-color: rgba(0,0,0,0.3); }
        .block-editor-block-list__block .block-editor-block-list__block .block-editor-block-list__block { outline-color: rgba(0,0,0,0.35); }
        .block-editor-block-list__block .block-editor-block-list__block .block-editor-block-list__block .block-editor-block-list__block { outline-color: rgba(0,0,0,0.4); }
        .block-editor-block-list__block .block-editor-block-list__block .block-editor-block-list__block .block-editor-block-list__block .block-editor-block-list__block { outline-color: rgba(0,0,0,0.45); }

        /* Selected block highlight */
        .block-editor-block-list__block.is-selected {
            outline-style: solid;
            outline-color: rgba(0,0,0,0.6);
        }

        /* Parent of selected block */
        .block-editor-block-list__block.has-child-selected {
            outline-style: solid;
            outline-color: rgba(0,0,0,0.4);
        }

        /* Dark mode */
        @media (prefers-color-scheme: dark) {
            .block-editor-block-list__block {
                outline-color: rgba(255,255,255,0.2);
            }
            .block-editor-block-list__block .block-editor-block-list__block { outline-color: rgba(255,255,255,0.3); }
            .block-editor-block-list__block .block-editor-block-list__block .block-editor-block-list__block { outline-color: rgba(255,255,255,0.4); }
            .block-editor-block-list__block .block-editor-block-list__block .block-editor-block-list__block .block-editor-block-list__block { outline-color: rgba(255,255,255,0.5); }
            .block-editor-block-list__block .block-editor-block-list__block .block-editor-block-list__block .block-editor-block-list__block .block-editor-block-list__block { outline-color: rgba(255,255,255,0.6); }

            .block-editor-block-list__block.is-selected {
                outline-color: rgba(255,255,255,0.85);
            }
            .block-editor-block-list__block.has-child-selected {
                outline-color: rgba(255,255,255,0.65);
            }
        }

        /* Clean up placeholders and UI chrome */
        .block-editor-block-list__block.is-placeholder,
        .block-editor-default-block-appender,
        .block-list-appender {
            outline: none !important;
        }
        '
    );
});





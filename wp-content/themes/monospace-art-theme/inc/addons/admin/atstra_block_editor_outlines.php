<?php
/**
 * Enqueue updated block editor outlines
 */
add_action('enqueue_block_editor_assets', function() {
    wp_add_inline_style(
        'wp-edit-blocks',
        '
        /* Base outline for all blocks */
        .wp-block, 
        .editor-block-list__block {
            outline: 1px dotted rgba(0,0,0,0.25) !important;
            outline-offset: -2px;
        }

        /* Nesting levels */
        .wp-block .wp-block,
        .editor-block-list__block .editor-block-list__block {
            outline-color: rgba(0,0,0,0.3) !important;
        }
        .wp-block .wp-block .wp-block,
        .editor-block-list__block .editor-block-list__block .editor-block-list__block {
            outline-color: rgba(0,0,0,0.35) !important;
        }
        .wp-block .wp-block .wp-block .wp-block,
        .editor-block-list__block .editor-block-list__block .editor-block-list__block .editor-block-list__block {
            outline-color: rgba(0,0,0,0.4) !important;
        }
        .wp-block .wp-block .wp-block .wp-block .wp-block,
        .editor-block-list__block .editor-block-list__block .editor-block-list__block .editor-block-list__block .editor-block-list__block {
            outline-color: rgba(0,0,0,0.45) !important;
        }

        /* Selected block */
        .wp-block.is-selected,
        .editor-block-list__block.is-selected {
            outline-style: solid !important;
            outline-color: rgba(0,0,0,0.6) !important;
        }

        /* Parent of selected block */
        .wp-block.has-child-selected,
        .editor-block-list__block.has-child-selected {
            outline-style: solid !important;
            outline-color: rgba(0,0,0,0.4) !important;
        }

        /* Dark mode */
        @media (prefers-color-scheme: dark) {
            .wp-block, .editor-block-list__block { outline-color: rgba(255,255,255,0.2) !important; }
            .wp-block .wp-block, .editor-block-list__block .editor-block-list__block { outline-color: rgba(255,255,255,0.3) !important; }
            .wp-block .wp-block .wp-block, .editor-block-list__block .editor-block-list__block .editor-block-list__block { outline-color: rgba(255,255,255,0.4) !important; }
            .wp-block .wp-block .wp-block .wp-block, .editor-block-list__block .editor-block-list__block .editor-block-list__block .editor-block-list__block { outline-color: rgba(255,255,255,0.5) !important; }
            .wp-block .wp-block .wp-block .wp-block .wp-block, .editor-block-list__block .editor-block-list__block .editor-block-list__block .editor-block-list__block .editor-block-list__block { outline-color: rgba(255,255,255,0.6) !important; }

            .wp-block.is-selected, .editor-block-list__block.is-selected { outline-color: rgba(255,255,255,0.85) !important; }
            .wp-block.has-child-selected, .editor-block-list__block.has-child-selected { outline-color: rgba(255,255,255,0.65) !important; }
        }

        /* Exclude placeholders and UI chrome */
        .wp-block.is-placeholder,
        .block-editor-default-block-appender,
        .block-list-appender {
            outline: none !important;
        }
        '
    );
});

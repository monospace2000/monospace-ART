<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */




/**
 * Custom Home Button
 * Adds a clickable home button with an SVG icon and responsive styling.
 * Can be inserted via shortcode [monospace_home].
 *
 * @since 1.0.0
 */

/**
 * Output the home button HTML.
 */
function monospace_custom_home() {
    echo '<div class="monospace-home-wrapper">';
    echo '<a href="https://art.monospace.com/" class="monospace-home-link" aria-label="Go to home page">';
    echo '<div class="monospace-home-icon">';
    
    // Home icon SVG
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
    echo '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>';
    echo '<polyline points="9 22 9 12 15 12 15 22"></polyline>';
    echo '</svg>';
    
    echo '</div>';
    echo '</a>';
    echo '</div>';
}
add_shortcode( 'monospace_home', 'monospace_custom_home' );


/**
 * Add inline styles for the home button.
 */
add_action( 'wp_head', 'monospace_home_styles', 999 );
function monospace_home_styles() {
    echo '<style>
        /* Home button wrapper */
        .monospace-home-wrapper {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        /* Home link */
        .monospace-home-link {
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            padding: 10px;
            position: relative;
        }

        /* Home icon container */
        .monospace-home-icon {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }

        /* White home SVG */
        .monospace-home-icon svg {
            stroke: #ffffff;
            fill: none;
            width: 24px;
            height: 24px;
            transition: transform 0.2s ease;
        }

        /* Hover effect */
        .monospace-home-link:hover .monospace-home-icon svg {
            transform: scale(1.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .monospace-home-link {
                padding: 8px;
            }
            
            .monospace-home-icon {
                width: 36px;
                height: 36px;
            }
            
            .monospace-home-icon svg {
                width: 20px;
                height: 20px;
            }
        }
    </style>';
}



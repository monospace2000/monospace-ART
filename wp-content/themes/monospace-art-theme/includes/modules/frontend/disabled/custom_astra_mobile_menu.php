<?php
/**
 * Enqueue scripts and styles
 *
 * @package astra-child-theme-for-monospace-art
 */

function custom_astra_move_mobile_menu() {
    ?>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var offCanvas = document.querySelector(".ast-mobile-menu-wrap");
        var topRow = document.querySelector(".ast-header-row-top");
        var mainRow = document.querySelector(".ast-header-row-main");
        var menuToggle = document.querySelector(".ast-menu-toggle");

        if (offCanvas && topRow && mainRow && menuToggle) {
            // Move the menu between top and main rows
            topRow.parentNode.insertBefore(offCanvas, mainRow);

            // Make menu relative to header
            offCanvas.style.position = "relative";
            offCanvas.style.transform = "none";
            offCanvas.style.width = "100%";
            offCanvas.style.zIndex = "999";
            
            // Handle toggle click
            menuToggle.addEventListener("click", function() {
                offCanvas.classList.toggle("ast-mobile-menu-open");
            });
        }
    });
    </script>
    <style>
    /* Mobile menu styling between header rows */
    .ast-mobile-menu-wrap {
        display: none; /* hidden by default */
        background: #fff; /* adjust to match your header */
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .ast-mobile-menu-wrap.ast-mobile-menu-open {
        display: block;
    }
    .ast-mobile-menu-wrap .main-header-menu {
        flex-direction: column;
        margin: 0;
        padding: 0;
    }
    @media (min-width: 921px) {
        .ast-mobile-menu-wrap {
            display: none !important; /* hide on desktop */
        }
    }
    </style>
    <?php
}
add_action('wp_footer', 'custom_astra_move_mobile_menu', 100);

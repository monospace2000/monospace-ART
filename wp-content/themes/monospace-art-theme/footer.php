
<?php $ms_footer = function_exists( 'monospace_footer_settings' ) ? monospace_footer_settings() : [ 'menu' => 1, 'copyright_on' => 1 ]; // Layout Manager > Footer ?>
<footer id="site-footer">
    <?php if ( $ms_footer['menu'] && ! has_nav_menu( 'footer' ) ) : // The footer menu has the social links (Elsewhere column) ?>
    <div class="social-icons">
    <a href="https://bsky.app/profile/monospace.com">
        <img src="https://art.monospace.com/wp-content/uploads/bluesky-outline-150x150.png?wsr" alt="Bluesky">
    </a>

    <a href="https://www.instagram.com/hens.breet/">
        <img src="https://art.monospace.com/wp-content/uploads/insta-outline-150x150.png?wsr" alt="Instagram">
    </a>

    <a href="https://www.facebook.com/hens.breet">
        <img src="https://art.monospace.com/wp-content/uploads/fb-outline-150x150.png?wsr" alt="Facebook">
    </a>
    </div>
    <?php endif; ?>

    <?php
    // The Footer Menu (Appearance > Menus): columns, each a "#" heading item with its links
    // (menu-items.php) - else the legal links
    if ( ! $ms_footer['menu'] ) {
        // Layout Manager > Footer: the menu part is switched off
    } elseif ( has_nav_menu( 'footer' ) ) {
        wp_nav_menu( array(
            'theme_location'  => 'footer',
            'container'       => 'nav',
            'container_class' => 'footer-menu-wrapper',
            'container_aria_label' => 'Footer',
            'menu_class'      => 'footer-menu',
            'depth'           => 2,
            'fallback_cb'     => false,
        ) );
    } elseif ( function_exists( 'monospace_render_legal_links' ) ) {
        echo monospace_render_legal_links();
    }
    ?>

    <?php if ( $ms_footer['copyright_on'] ) : ?>
    <div class="copyright-notice">
        <?php echo function_exists( 'monospace_footer_copyright_html' ) ? monospace_footer_copyright_html() : 'Copyright &copy;' . date( 'Y' ) . ' Hens Breet | monospace ART LLC. All rights reserved.'; ?>
    </div>
    <?php endif; ?>
</footer>


<?php if ( function_exists( 'monospace_rubber_scroll_active' ) && monospace_rubber_scroll_active() ) echo '</div></div>'; // #smooth-content, #smooth-wrapper (header.php) ?>

<?php wp_footer(); ?>
</body>
</html>

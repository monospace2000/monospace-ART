
<footer id="site-footer">
    <div class="footer-inner">
        <div class="footer-widgets">
            <?php if (is_active_sidebar('footer-1')) : ?>
                <?php dynamic_sidebar('footer-1'); ?>
            <?php endif; ?>
        </div>
    </div>
</footer>


<?php wp_footer(); ?>
</body>
</html>

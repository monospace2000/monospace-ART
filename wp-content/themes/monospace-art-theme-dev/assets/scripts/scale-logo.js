(function($) {

    function scaleLogoTagline() {
        var $wrapper = $('.site-logo-wrapper');
        var $logo = $wrapper.find('img.custom-logo');
        var $tagline = $wrapper.find('.site-tagline');

        if (!$wrapper.length || !$logo.length || !$tagline.length) return;

        var containerWidth;

        // Determine container width
        if ($('body').hasClass('page-template-start_template')) {
            var globalWidth = parseFloat(getComputedStyle(document.body).getPropertyValue('--global-width')) || $wrapper.parent().width();
            var globalPadding = parseFloat(getComputedStyle(document.body).getPropertyValue('--global-padding')) || 0;
            containerWidth = globalWidth - (globalPadding * 2);
        } else {
            containerWidth = parseFloat(getComputedStyle(document.body).getPropertyValue('--column-center-width')) || $wrapper.parent().width();
        }

        // Base CSS sizes
        var baseLogoWidth = parseFloat($logo.width()) || 100; // in px, whatever CSS sets initially
        var baseTaglineSize = parseFloat($tagline.css('font-size')) || 28; // in px, matches CSS 1.8em

        // Determine scaling factor: ratio of container width to base wrapper width
        var wrapperBaseWidth = $wrapper.width() || containerWidth; // wrapper width from CSS
        var scaleFactor = containerWidth / wrapperBaseWidth;

        // Apply scaled sizes
        $logo.css('width', baseLogoWidth * scaleFactor + 'px');
        $tagline.css('font-size', baseTaglineSize * scaleFactor + 'px');
    }

    $(document).ready(scaleLogoTagline);
    $(window).on('resize', scaleLogoTagline);

})(jQuery);

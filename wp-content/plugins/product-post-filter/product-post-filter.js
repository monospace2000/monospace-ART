jQuery(document).ready(function($){
    $(document).on('change submit', '.ms-post-filter-form select, .ms-post-filter-form button', function(e){
        e.preventDefault();
        var form = $(this).closest('form');
        var data = form.serialize();

        $.ajax({
            url: msPostFilter.ajaxurl,
            type: 'GET',
            data: data + '&action=ms_filter_posts',
            beforeSend: function(){ $('#ms-post-container').fadeTo(200, 0.5); },
            success: function(response){
                $('#ms-post-container').html(response).fadeTo(200,1);

                // Notify Jetpack Infinite Scroll
                if(typeof $(window).trigger === 'function') $(window).trigger('post-load');

                // Reset Jetpack page counter if present
                if(typeof jetpack_inf_scroll !== 'undefined') jetpack_inf_scroll.next_page = 2;
            },
            error: function(){ console.error('Error loading filtered posts.'); }
        });
    });
});

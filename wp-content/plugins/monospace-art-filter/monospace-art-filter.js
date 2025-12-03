jQuery(document).ready(function($){
    function updateProducts(){
        var data = { action:'ms_art_filter' };
        $('.ms-art-filter-container select').each(function(){
            var key = $(this).data('tax');
            data[key] = $(this).val();
        });
        $.get(msArtFilter.ajaxurl, data, function(response){
            $('#ms-art-filter-results').html(response);
        });
    }

    // Initial load
    updateProducts();

    // Update on change
    $('.ms-art-filter-container select').on('change', updateProducts);
});

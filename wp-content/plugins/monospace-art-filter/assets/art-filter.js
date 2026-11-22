/**
 * Art Filter - the filter form, the viewing toolbar and the results ([monospace_art_filter],
 * modules/frontend/art-filter-shortcode.php). The results come from admin-ajax.php?action=ms_filter_products
 * (modules/frontend/art-filter-results.php). msArtFilter = { ajaxUrl } (wp_localize_script).
 *
 * The address keeps the filters (?pa_medium=…&pa_medium_not=1, ?availability=…, ?price_min / price_max, ?sort / seed),
 * so a filtered view can be shared; the view (grid / list / price card) is remembered in this browser
 * (localStorage monospace_art_filter_view).
 */
jQuery(function($){

    function loadFiltersFromURL() {
        const urlParams = new URLSearchParams(window.location.search);

        $('.ms-filter-select').each(function(){
            const name = $(this).attr('name');
            const value = urlParams.get(name);
            if(value) {
                $(this).val(value);
            }

            const notParam = urlParams.get(name + '_not');
            if(notParam === '1') {
                const notID = $(this).data('not-checkbox');
                $('#' + notID).prop('checked', true);
            }
        });

        const priceMin = urlParams.get('price_min');
        const priceMax = urlParams.get('price_max');
        if(priceMin) $priceMin.val(priceMin);
        if(priceMax) $priceMax.val(priceMax);

        // Sort: ?sort= (and its shuffle, ?seed=); the old ?order=ASC = oldest first
        const s = urlParams.get('sort');
        if (s && $('#ms-sort option[value="' + s + '"]').length) sort = s;
        else if (urlParams.get('order') === 'ASC') sort = 'oldest';
        seed = parseInt(urlParams.get('seed')) || 0;
        if (sort === 'random' && !seed) seed = 1 + Math.floor(Math.random() * 999999);
        $('#ms-sort').val(sort);

        toggleNotCheckboxes();
        togglePriceSlider();
        updatePriceDisplay();
        updateButtonStates();
    }

    function updateURL() {
        const params = new URLSearchParams();

        $('.ms-filter-select').each(function(){
            const name = $(this).attr('name');
            const value = $(this).val();
            if(value) {
                params.set(name, value);
            }
        });

        $('.ms-filter-not').each(function(){
            if($(this).is(':checked') && !$(this).is(':disabled')){
                params.set($(this).attr('name'), '1');
            }
        });

        // Only add price params if availability is 'available' AND NOT excluded
        const availability = $('select[name="availability"]').val();
        const isExcluded = $('#availability_not_checkbox').is(':checked');

        if(availability === 'available' && !isExcluded) {
            if($priceMin.val() != minPrice) {
                params.set('price_min', $priceMin.val());
            }
            if($priceMax.val() != maxPrice) {
                params.set('price_max', $priceMax.val());
            }
        }

        if (sort !== 'newest') params.set('sort', sort);
        if (sort === 'random') params.set('seed', seed);

        const newURL = params.toString()
            ? window.location.pathname + '?' + params.toString()
            : window.location.pathname;

        window.history.pushState({}, '', newURL);
    }

    let $form = $('.ms-product-filter-form');
    let $spinner = $('.ms-filter-spinner');
    let $grid = $('.ms-product-grid');
    let $priceMin = $('#ms-price-min');
    let $priceMax = $('#ms-price-max');
    let $minDisplay = $('#ms-price-min-display');
    let $maxDisplay = $('#ms-price-max-display');

    const minPrice = parseInt($priceMin.attr('min')) || 0;
    const maxPrice = parseInt($priceMax.attr('max')) || 1000;

    // The viewing toolbar: sort (in the URL) and view (remembered in this browser)
    let sort = 'newest', seed = 0;
    let view = 'grid';
    try { view = localStorage.getItem('monospace_art_filter_view') || 'grid'; } catch (e) {}
    if (!$('.ms-view-btn[data-view="' + view + '"]').length) view = 'grid';
    function showView() {
        $('.ms-view-btn').each(function () { $(this).attr('aria-pressed', $(this).data('view') === view ? 'true' : 'false'); });
        $grid.attr('data-view', view);
    }

    function updateButtonStates(){

        let anyFilterActive = false;
        $('.ms-product-filter-form select').each(function(){
            if($(this).val() !== '') anyFilterActive = true;
        });
        $('.ms-filter-not').each(function(){
            if($(this).is(':checked')) anyFilterActive = true;
        });

        if($('#ms-price-range-container').is(':visible')) {
            if($priceMin.val() != minPrice || $priceMax.val() != maxPrice) anyFilterActive = true;
        }

        $('#ms-reset-filters').prop('disabled', !anyFilterActive);
    }

    $('#ms-load-more').on('click', function () { page++; updateProducts(true); });
    $('#ms-sort').on('change', function () {
        sort = this.value;
        if (sort === 'random') seed = 1 + Math.floor(Math.random() * 999999); // a fresh shuffle each time it's chosen
        updateProducts();
    });
    $('.ms-view-btn').on('click', function () {
        view = $(this).data('view');
        try { localStorage.setItem('monospace_art_filter_view', view); } catch (e) {}
        showView();
        updateProducts();
    });

    $('#ms-reset-filters').on('click', function(){
        $('.ms-product-filter-form select').val('');
        $('.ms-filter-not').prop('checked', false).prop('disabled', true);
        $priceMin.val(minPrice);
        $priceMax.val(maxPrice);
        updatePriceDisplay();
        toggleNotCheckboxes();
        togglePriceSlider();
        sort = 'newest'; seed = 0; $('#ms-sort').val('newest');
        updateButtonStates();

        window.history.pushState({}, '', window.location.pathname);

        updateProducts();
    });

    $('.ms-filter-select, .ms-filter-not').on('change', function(){
        updateButtonStates();
    });

    $priceMin.on('input change', updateButtonStates);
    $priceMax.on('input change', updateButtonStates);

    updateButtonStates();

    function toggleNotCheckboxes(){
        $('.ms-filter-select').each(function(){
            var $select = $(this);
            var notID = $select.data('not-checkbox');
            var $not = $('#' + notID);
            if($select.val() === ''){
                $not.prop('disabled',true).prop('checked',false);
            } else {
                $not.prop('disabled',false);
            }
        });
    }



    function togglePriceSlider() {
        const availability = $('select[name="availability"]').val();
        const isExcluded = $('#availability_not_checkbox').is(':checked');

        // Only show when availability is 'available' AND NOT excluded
        const show = (availability === 'available' && !isExcluded);
        $('#ms-price-range-container').prop('hidden', !show);
    }

    function updatePriceDisplay(){
        let min = parseInt($priceMin.val());
        let max = parseInt($priceMax.val());

        if(min >= max) min = max - 1;
        if(max <= min) max = min + 1;

        $priceMin.val(min);
        $priceMax.val(max);

        $minDisplay.text('$' + min);
        $maxDisplay.text('$' + max);

        // The filled part of the one track, between the two handles
        const span = (maxPrice - minPrice) || 1;
        const at = function (p) { return 'calc(10px + (100% - 20px) * ' + p + ')'; }; // the track runs 10px in from each end
        $('.ms-range-fill').css({ left: at((min - minPrice) / span), right: at((maxPrice - max) / span) });
    }



    function startLoading(){
        $form.css({'opacity':'0.5','pointer-events':'none'});
        $grid.css({'opacity':'0.4'});
        $spinner.show();
    }

    function stopLoading(){
        $form.css({'opacity':'1','pointer-events':'auto'});
        $grid.css({'opacity':'1'});
        $spinner.hide();
    }

    var page = 1;
    function updateProducts(append){
        append = append === true; // event handlers pass their event here
        if (!append) page = 1;
        startLoading();

        var data = { action:'ms_filter_products', order: sort === 'oldest' ? 'ASC' : 'DESC', sort: sort, seed: seed, view: view, page: page };
        $('.ms-filter-select').each(function(){
            data[$(this).attr('name')] = $(this).val();
        });
        $('.ms-filter-not').each(function(){
            if($(this).is(':checked') && !$(this).is(':disabled')){
                data[$(this).attr('name')] = '1';
            }
        });

        // Only send price data if availability is 'available' AND NOT excluded
        const availability = $('select[name="availability"]').val();
        const isExcluded = $('#availability_not_checkbox').is(':checked');

        if(availability === 'available' && !isExcluded) {
            data['price_min'] = $priceMin.val();
            data['price_max'] = $priceMax.val();
        }

        $.ajax({
            url: msArtFilter.ajaxUrl,
            data: data,
            dataType: 'json',
            method: 'POST',
            success: function(resp){
                if (append) $grid.append(resp.html); else $grid.html(resp.html);
                $('.ms-load-more-wrap').prop('hidden', !resp.more); // price card view: 12 at a time
                $('#ms-filter-count').text(`Found ${resp.count} items`);
                stopLoading();
                updateURL();
            },
            error: function(){
                $grid.html('<p>Error loading items.</p>');
                stopLoading();
            }
        });
    }

    $('.ms-filter-select').on('change', function(){
        toggleNotCheckboxes();
        togglePriceSlider();
        updateProducts();
    });

    $('.ms-filter-not').on('change', function(){
        togglePriceSlider();
        updateProducts();
    });

    $priceMin.on('input change', function(){ updatePriceDisplay(); });
    $priceMax.on('input change', function(){ updatePriceDisplay(); });
    $priceMin.on('change', updateProducts);
    $priceMax.on('change', updateProducts);
    // A tap or click on the track (not a handle) moves the nearer handle there
    $('.ms-range').on('pointerdown', function (e) {
        if (e.target.type === 'range') return;
        const r = this.getBoundingClientRect(), p = Math.min(1, Math.max(0, (e.clientX - r.left - 10) / (r.width - 20)));
        const v = Math.round(minPrice + p * (maxPrice - minPrice));
        const $h = Math.abs(v - $priceMin.val()) <= Math.abs(v - $priceMax.val()) ? $priceMin : $priceMax;
        $h.val(v).trigger('input').trigger('change');
    });

    toggleNotCheckboxes();
    togglePriceSlider();
    updatePriceDisplay();
    loadFiltersFromURL();
    showView();
    updateProducts();
});

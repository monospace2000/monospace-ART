/**
 * monospace Sales Dashboard Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // Toggle visibility based on sale mode
        $('#msd_sale_mode').on('change', function() {
            const mode = $(this).val();

            if (mode === 'global') {
                $('tr:has(#msd_sale_exclude_categories)').show();
                $('tr:has(#msd_sale_exclude_tags)').show();
                $('tr:has(#msd_sale_categories)').hide();
                $('tr:has(#msd_sale_tags)').hide();
            } else {
                $('tr:has(#msd_sale_categories)').show();
                $('tr:has(#msd_sale_tags)').show();
                $('tr:has(#msd_sale_exclude_categories)').hide();
                $('tr:has(#msd_sale_exclude_tags)').hide();
            }
        }).trigger('change');

        // Toggle recurring schedule fields
        $('#msd_recurring_pattern').on('change', function() {
            const pattern = $(this).val();

            $('.msd-filter-tag, .msd-filter-search, .msd-filter-manual').hide();
            $('tr:has(#msd_recurring_weekday)').hide();
            $('tr:has(#msd_recurring_monthday)').hide();
            $('tr:has(#msd_recurring_week)').hide();

            if (pattern === 'weekly' || pattern === 'monthly_day') {
                $('tr:has(#msd_recurring_weekday)').show();
            }
            if (pattern === 'monthly_date') {
                $('tr:has(#msd_recurring_monthday)').show();
            }
            if (pattern === 'monthly_day') {
                $('tr:has(#msd_recurring_week)').show();
            }
        }).trigger('change');

        // Date picker validation
        $('#msd_schedule_start, #msd_schedule_end').on('blur', function() {
            const value = $(this).val().trim();

            if (value === '') return;

            const datePattern = /^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/;

            if (!datePattern.test(value)) {
                $(this).css('border-color', '#dc3232');
                alert('Invalid date format. Use: YYYY-MM-DD HH:MM');
            } else {
                $(this).css('border-color', '#46b450');
            }
        });

        // Conflict resolution info
        $('#msd_priority').on('change', function() {
            const priority = $(this).val();
            let message = '';

            switch(priority) {
                case 'best':
                    message = 'Customer always gets the highest discount available.';
                    break;
                case 'product':
                    message = 'Product overrides â†’ Volume discounts â†’ Sale prices â†’ Global adjustments.';
                    break;
                case 'volume':
                    message = 'Volume discounts always take precedence over other rules.';
                    break;
            }

            let $notice = $('.msd-priority-notice');
            if ($notice.length === 0) {
                $(this).closest('tr').after(
                    '<tr class="msd-priority-notice-row"><td colspan="2">' +
                    '<div class="msd-priority-notice"><p>' + message + '</p></div>' +
                    '</td></tr>'
                );
            } else {
                $notice.find('p').text(message);
            }
        });

        // Initialize WooCommerce enhanced selects
        if (typeof $.fn.selectWoo === 'function') {
            $('.wc-enhanced-select').selectWoo({
                width: '50%',
                placeholder: 'Select...',
                allowClear: true
            });
        }

        // ========================================
        // VISUAL RULE BUILDER
        // ========================================

        if ($('#msd-rules-container').length) {
            initRuleBuilder();
        }

        // Toggle JSON viewer
        $('#msd-toggle-json').on('click', function() {
            const $viewer = $('#msd-json-viewer');
            const $button = $(this);

            if ($viewer.is(':visible')) {
                $viewer.slideUp();
                $button.text('View JSON');
            } else {
                // Update JSON display from hidden field
                const json = $('#msd_volume_rules').val();
                let formatted = json;

                try {
                    if (json) {
                        const parsed = JSON.parse(json);
                        formatted = JSON.stringify(parsed, null, 2);
                    } else {
                        formatted = '{}';
                    }
                } catch(e) {
                    formatted = json || '{}';
                }

                $('#msd-json-display').val(formatted);
                $viewer.slideDown();
                $button.text('Hide JSON');
            }
        });

        function initRuleBuilder() {
            const $container = $('#msd-rules-container');
            const $hiddenInput = $('#msd_volume_rules');

            // Load existing rules
            loadRules();

            // Add rule group button
            $('#msd-add-rule-group').on('click', function() {
                addRuleGroup();
            });

            // Save rules on form submit
            $('form').on('submit', function() {
                saveRules();
            });

            function loadRules() {
                const json = $hiddenInput.val();
                if (!json) {
                    addRuleGroup(); // Add empty group
                    return;
                }

                try {
                    const rules = JSON.parse(json);
                    Object.keys(rules).forEach(key => {
                        addRuleGroup(key, rules[key]);
                    });
                } catch(e) {
                    console.error('Invalid JSON', e);
                }
            }

            function addRuleGroup(key = '', rules = []) {
                const groupId = 'group-' + Date.now();

                const $group = $(`
                    <div class="msd-rule-group" data-group-id="${groupId}">
                        <div class="msd-rule-group-header">
                            <input type="text" class="msd-rule-key" placeholder="Rule key (e.g., miniature, category-slug, attr:pa_size=4x4)" value="${key}">
                            <button type="button" class="button button-small msd-remove-group">Remove Group</button>
                        </div>
                        <div class="msd-rules-list"></div>
                        <button type="button" class="button button-small msd-add-rule">+ Add Rule</button>
                    </div>
                `);

                $container.append($group);

                // Add existing rules
                if (rules.length) {
                    rules.forEach(rule => addRule($group, rule));
                } else {
                    addRule($group);
                }

                // Remove group
                $group.find('.msd-remove-group').on('click', function() {
                    $group.remove();
                });

                // Add rule
                $group.find('.msd-add-rule').on('click', function() {
                    addRule($group);
                });
            }

            function addRule($group, ruleData = {}) {
                const ruleId = 'rule-' + Date.now() + '-' + Math.random();
                const type = ruleData.type || 'fixed_bundle';

                const $rule = $(`
                    <div class="msd-rule" data-rule-id="${ruleId}">
                        <select class="msd-rule-type">
                            <option value="fixed_bundle">Fixed Bundle (3 for $99)</option>
                            <option value="buy_x_get_y">Buy X Get Y Free</option>
                            <option value="second_half">Second Half Price</option>
                            <option value="percent_discount">Percent Discount</option>
                            <option value="fixed_discount">Fixed Discount</option>
                        </select>
                        <div class="msd-rule-fields"></div>
                        <button type="button" class="button button-small msd-remove-rule">Ã—</button>
                    </div>
                `);

                $rule.find('.msd-rule-type').val(type).on('change', function() {
                    updateRuleFields($rule, $(this).val());
                });

                $group.find('.msd-rules-list').append($rule);
                updateRuleFields($rule, type, ruleData);

                $rule.find('.msd-remove-rule').on('click', function() {
                    $rule.remove();
                });
            }

            function updateRuleFields($rule, type, data = {}) {
                const $fields = $rule.find('.msd-rule-fields');
                $fields.empty();

                switch(type) {
                    case 'fixed_bundle':
                        $fields.html(`
                            <input type="number" class="msd-field-qty" placeholder="Qty" value="${data.qty || ''}" min="1">
                            for $<input type="number" class="msd-field-total" placeholder="Total" value="${data.total || ''}" min="0" step="0.01">
                        `);
                        break;

                    case 'buy_x_get_y':
                        $fields.html(`
                            Buy <input type="number" class="msd-field-buy" placeholder="X" value="${data.buy || ''}" min="1">
                            Get <input type="number" class="msd-field-get" placeholder="Y" value="${data.get || ''}" min="1"> free
                        `);
                        break;

                    case 'second_half':
                        $fields.html(`<span>Second item at 50% off</span>`);
                        break;

                    case 'percent_discount':
                        $fields.html(`
                            <input type="number" class="msd-field-percent" placeholder="%" value="${data.percent || ''}" min="0" max="100" step="0.1">% off
                        `);
                        break;

                    case 'fixed_discount':
                        $fields.html(`
                            $<input type="number" class="msd-field-amount" placeholder="Amount" value="${data.amount || ''}" min="0" step="0.01"> off each
                        `);
                        break;
                }
            }

            function saveRules() {
                const rules = {};

                $('.msd-rule-group').each(function() {
                    const $group = $(this);
                    const key = $group.find('.msd-rule-key').val().trim();
                    if (!key) return;

                    rules[key] = [];

                    $group.find('.msd-rule').each(function() {
                        const $rule = $(this);
                        const type = $rule.find('.msd-rule-type').val();
                        const ruleObj = { type: type };

                        switch(type) {
                            case 'fixed_bundle':
                                ruleObj.qty = parseInt($rule.find('.msd-field-qty').val()) || 0;
                                ruleObj.total = parseFloat($rule.find('.msd-field-total').val()) || 0;
                                break;

                            case 'buy_x_get_y':
                                ruleObj.buy = parseInt($rule.find('.msd-field-buy').val()) || 0;
                                ruleObj.get = parseInt($rule.find('.msd-field-get').val()) || 0;
                                break;

                            case 'percent_discount':
                                ruleObj.percent = parseFloat($rule.find('.msd-field-percent').val()) || 0;
                                break;

                            case 'fixed_discount':
                                ruleObj.amount = parseFloat($rule.find('.msd-field-amount').val()) || 0;
                                break;
                        }

                        rules[key].push(ruleObj);
                    });
                });

                $hiddenInput.val(JSON.stringify(rules, null, 2));

                // Update JSON viewer if visible
                if ($('#msd-json-viewer').is(':visible')) {
                    $('#msd-json-display').val(JSON.stringify(rules, null, 2));
                }
            }
        }

        // ========================================
        // BULK EDITOR
        // ========================================

        if ($('#msd-bulk-form').length) {
            initBulkEditor();
        }

        // ========================================
        // FREE SHIPPING RULE BUILDER
        // ========================================

        if ($('#msd-freeship-rules-container').length) {
            initFreeShipRuleBuilder();
        }

        // Toggle free shipping JSON viewer
        $('#msd-toggle-freeship-json').on('click', function() {
            const $viewer = $('#msd-freeship-json-viewer');
            const $button = $(this);

            if ($viewer.is(':visible')) {
                $viewer.slideUp();
                $button.text('View JSON');
            } else {
                const json = $('#msd_freeship_rules').val();
                let formatted = json;

                try {
                    if (json) {
                        const parsed = JSON.parse(json);
                        formatted = JSON.stringify(parsed, null, 2);
                    } else {
                        formatted = '{}';
                    }
                } catch(e) {
                    formatted = json || '{}';
                }

                $('#msd-freeship-json-display').val(formatted);
                $viewer.slideDown();
                $button.text('Hide JSON');
            }
        });

        function initFreeShipRuleBuilder() {
            const $container = $('#msd-freeship-rules-container');
            const $hiddenInput = $('#msd_freeship_rules');

            // Load existing rules
            loadFreeShipRules();

            // Add rule group button
            $('#msd-add-freeship-rule-group').on('click', function() {
                addFreeShipRuleGroup();
            });

            // Save rules on form submit
            $('form').on('submit', function() {
                saveFreeShipRules();
            });

            function loadFreeShipRules() {
                const json = $hiddenInput.val();
                if (!json) {
                    addFreeShipRuleGroup(); // Add empty group
                    return;
                }

                try {
                    const rules = JSON.parse(json);
                    Object.keys(rules).forEach(key => {
                        addFreeShipRuleGroup(key, rules[key]);
                    });
                } catch(e) {
                    console.error('Invalid JSON', e);
                }
            }

            function addFreeShipRuleGroup(key = '', rules = []) {
                const groupId = 'freeship-group-' + Date.now();

                const $group = $(`
                    <div class="msd-rule-group" data-group-id="${groupId}">
                        <div class="msd-rule-group-header">
                            <input type="text" class="msd-rule-key" placeholder="Rule key (e.g., global, miniature, prints)" value="${key}">
                            <button type="button" class="button button-small msd-remove-group">Remove Group</button>
                        </div>
                        <div class="msd-rules-list"></div>
                        <button type="button" class="button button-small msd-add-rule">+ Add Rule</button>
                    </div>
                `);

                $container.append($group);

                // Add existing rules
                if (rules.length) {
                    rules.forEach(rule => addFreeShipRule($group, rule));
                } else {
                    addFreeShipRule($group);
                }

                // Remove group
                $group.find('.msd-remove-group').on('click', function() {
                    $group.remove();
                });

                // Add rule
                $group.find('.msd-add-rule').on('click', function() {
                    addFreeShipRule($group);
                });
            }

            function addFreeShipRule($group, ruleData = {}) {
                const ruleId = 'freeship-rule-' + Date.now() + '-' + Math.random();
                const type = ruleData.type || 'min_amount';

                const $rule = $(`
                    <div class="msd-rule" data-rule-id="${ruleId}">
                        <select class="msd-rule-type">
                            <option value="min_amount">Minimum Cart Amount</option>
                            <option value="min_quantity">Minimum Cart Quantity</option>
                            <option value="category_amount">Category Minimum Amount</option>
                            <option value="category_quantity">Category Minimum Quantity</option>
                            <option value="all_from_category">All Items From Category</option>
                        </select>
                        <div class="msd-rule-fields"></div>
                        <button type="button" class="button button-small msd-remove-rule">×</button>
                    </div>
                `);

                $rule.find('.msd-rule-type').val(type).on('change', function() {
                    updateFreeShipRuleFields($rule, $(this).val());
                });

                $group.find('.msd-rules-list').append($rule);
                updateFreeShipRuleFields($rule, type, ruleData);

                $rule.find('.msd-remove-rule').on('click', function() {
                    $rule.remove();
                });
            }

            function updateFreeShipRuleFields($rule, type, data = {}) {
                const $fields = $rule.find('.msd-rule-fields');
                $fields.empty();

                switch(type) {
                    case 'min_amount':
                        $fields.html(`
                            Cart total $<input type="number" class="msd-field-amount" placeholder="Amount" value="${data.amount || ''}" min="0" step="0.01"> or more
                        `);
                        break;

                    case 'min_quantity':
                        $fields.html(`
                            <input type="number" class="msd-field-quantity" placeholder="Qty" value="${data.quantity || ''}" min="1"> or more items in cart
                        `);
                        break;

                    case 'category_amount':
                        $fields.html(`
                            $<input type="number" class="msd-field-amount" placeholder="Amount" value="${data.amount || ''}" min="0" step="0.01"> or more from this category
                        `);
                        break;

                    case 'category_quantity':
                        $fields.html(`
                            <input type="number" class="msd-field-quantity" placeholder="Qty" value="${data.quantity || ''}" min="1"> or more items from this category
                        `);
                        break;

                    case 'all_from_category':
                        $fields.html(`<span>All cart items must be from this category</span>`);
                        break;
                }
            }

            function saveFreeShipRules() {
                const rules = {};

                $('.msd-rule-group').each(function() {
                    const $group = $(this);
                    const key = $group.find('.msd-rule-key').val().trim() || 'global';

                    rules[key] = [];

                    $group.find('.msd-rule').each(function() {
                        const $rule = $(this);
                        const type = $rule.find('.msd-rule-type').val();
                        const ruleObj = { type: type };

                        switch(type) {
                            case 'min_amount':
                            case 'category_amount':
                                ruleObj.amount = parseFloat($rule.find('.msd-field-amount').val()) || 0;
                                break;

                            case 'min_quantity':
                            case 'category_quantity':
                                ruleObj.quantity = parseInt($rule.find('.msd-field-quantity').val()) || 0;
                                break;
                        }

                        rules[key].push(ruleObj);
                    });
                });

                $hiddenInput.val(JSON.stringify(rules, null, 2));

                // Update JSON viewer if visible
                if ($('#msd-freeship-json-viewer').is(':visible')) {
                    $('#msd-freeship-json-display').val(JSON.stringify(rules, null, 2));
                }
            }
        }

        function initBulkEditor() {
            let selectedProducts = [];

            // Toggle filter fields
            $('#msd-filter-method').on('change', function() {
                $('.msd-filter-category, .msd-filter-tag, .msd-filter-search, .msd-filter-manual').hide();
                $('.msd-filter-' + $(this).val()).show();
            }).trigger('change');

            // Load products
            $('#msd-load-products').on('click', function() {
                const method = $('#msd-filter-method').val();
                const $btn = $(this);
                const $spinner = $btn.next('.spinner');

                $btn.prop('disabled', true);
                $spinner.addClass('is-active');

                let query = { action: 'msd_load_products_bulk' };

                switch(method) {
                    case 'category':
                        query.category = $('[name="filter_category"]').val();
                        break;
                    case 'tag':
                        query.tag = $('[name="filter_tag"]').val();
                        break;
                    case 'manual':
                        query.ids = $('[name="filter_manual"]').val();
                        break;
                    case 'all':
                        query.all = '1';
                        break;
                }

                $.post(ajaxurl, query, function(response) {
                    displayProducts(response.data || []);
                    $btn.prop('disabled', false);
                    $spinner.removeClass('is-active');
                });
            });

            function displayProducts(products) {
                selectedProducts = products;
                const $list = $('#msd-product-list');
                $list.empty();

                if (!products.length) {
                    $list.html('<p>No products found.</p>');
                    $('#msd-product-count').text('');
                    $('#msd-bulk-submit').prop('disabled', true);
                    return;
                }

                $('#msd-product-count').html(`<strong>${products.length} products</strong> selected`);
                $('#msd-bulk-submit').prop('disabled', false);

                const $table = $('<table class="wp-list-table widefat"><thead><tr><th>Select</th><th>Product</th><th>ID</th></tr></thead><tbody></tbody></table>');

                products.forEach(p => {
                    $table.find('tbody').append(`
                        <tr>
                            <td><input type="checkbox" class="msd-product-checkbox" value="${p.id}" checked></td>
                            <td>${p.title}</td>
                            <td>${p.id}</td>
                        </tr>
                    `);
                });

                $list.append($table);

                // Update hidden field
                updateProductIds();

                $('.msd-product-checkbox').on('change', updateProductIds);
            }

            function updateProductIds() {
                const ids = [];
                $('.msd-product-checkbox:checked').each(function() {
                    ids.push($(this).val());
                });
                $('#msd-product-ids').val(ids.join(','));
                $('#msd-bulk-submit').prop('disabled', ids.length === 0);
            }
        }

    });

})(jQuery);
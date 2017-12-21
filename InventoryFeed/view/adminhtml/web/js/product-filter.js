require(['jquery'], function (jQuery) {

    require(['Urbit_InventoryFeed/js/vendor/multiselect'], function () {

        jQuery(document).ready(function () {
            if (typeof ifActivateProductFilter !== 'undefined' && ifActivateProductFilter) {
                var $loadingWrapper = jQuery('.if-advanced-multiselect-load-wrapper');
                var $categoriesFilter = jQuery('#inventoryfeed_config_filter_category');
                var $tagFilterAttributeName = jQuery('#inventoryfeed_config_filter_tag_name');
                var $tagFilterAttributeValue = jQuery('#inventoryfeed_config_filter_tag_value');
                var $leftMultiselect = jQuery('#inventoryfeed_config_attributes_additional_from');
                var $rightMultiselect = jQuery('#inventoryfeed_config_attributes_additional_to');

                var loadProducts = function (data) {
                    if (!Array.isArray(data)) {
                        data = [data];
                    }

                    $leftMultiselect.empty();

                    data.forEach(function (value, index) {
                        if ($rightMultiselect.children('option[value="'+ value.id +'"]').length > 0) {
                            return;
                        }

                        $leftMultiselect.append('<option value="'+ value.id +'">'+ value.id +': '+ value.name +'</option>');
                    });
                };

                var updateMultiselects = function () {
                    $loadingWrapper.css('display', 'flex');

                    jQuery.ajax({
                        url: ifGetAllProductsUrl,
                        method: 'GET',
                        data: {
                            categoryFiler: $categoriesFilter.val(),
                            tagFilterName: $tagFilterAttributeName.val(),
                            tagFilterValue: $tagFilterAttributeValue.val()
                        },
                        success: function (data) {
                            loadProducts(data);
                            $loadingWrapper.css('display', 'none');
                        }
                    });
                };

                jQuery(window).load(function () {
                    jQuery('#inventoryfeed_config_attributes_additional_from').multiselect();
                    updateMultiselects();
                });

                jQuery(document).on('change', '#inventoryfeed_config_filter_category, #inventoryfeed_config_filter_tag_name, #inventoryfeed_config_filter_tag_value', function () {
                    updateMultiselects();
                });

                jQuery(document).on('click', '#save', function () {
                    jQuery('#inventoryfeed_config_attributes_additional_to option').prop('selected', true);
                });
            }

        });

    });

});
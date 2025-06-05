/**
 * Products Archive JavaScript
 * Handles AJAX filtering and interactions
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Initialize products archive functionality
        if ($('.handy-products-archive').length) {
            ProductsArchive.init();
        }
        
    });

    /**
     * Products Archive Object
     */
    var ProductsArchive = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            console.log('Products Archive initialized');
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Filter change events
            $(document).on('change', '.product-filter', this.handleFilterChange);
            
            // Clear filters button
            $(document).on('click', '.btn-clear-filters', this.clearFilters);
            
            // Description toggle
            $(document).on('click', '.description-toggle', this.toggleDescription);
        },
        
        /**
         * Handle filter changes
         */
        handleFilterChange: function() {
            var $container = $('.handy-products-archive');
            var filters = {};
            
            // Collect all filter values
            $('.product-filter').each(function() {
                var name = $(this).attr('name');
                var value = $(this).val();
                if (name && value) {
                    filters[name] = value;
                }
            });
            
            console.log('Filters changed:', filters);
            
            // Show loading
            ProductsArchive.showLoading();
            
            // Send AJAX request
            $.ajax({
                url: handyCustomAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'filter_products',
                    nonce: handyCustomAjax.nonce,
                    ...filters
                },
                success: function(response) {
                    if (response.success) {
                        $('#products-results').html($(response.data.html).find('#products-results').html());
                        console.log('Products filtered successfully');
                    } else {
                        console.error('Filter error:', response.data);
                        ProductsArchive.showError('Failed to filter products');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    ProductsArchive.showError('Connection error. Please try again.');
                },
                complete: function() {
                    ProductsArchive.hideLoading();
                }
            });
        },
        
        /**
         * Clear all filters
         */
        clearFilters: function(e) {
            e.preventDefault();
            
            $('.product-filter').val('');
            $('.product-filter').first().trigger('change');
            
            console.log('Filters cleared');
        },
        
        /**
         * Toggle description expansion
         */
        toggleDescription: function(e) {
            e.preventDefault();
            
            var $toggle = $(this);
            var $text = $toggle.closest('.description-text');
            var fullText = $toggle.data('full-text');
            var currentText = $text.text();
            
            if ($toggle.text() === '...') {
                // Expand
                $text.html(fullText);
                $toggle.text(' Show Less').appendTo($text);
            } else {
                // Collapse
                var truncatedText = currentText.substring(0, 270);
                $text.html(truncatedText + '<span class="description-toggle" data-full-text="' + fullText + '">...</span>');
            }
        },
        
        /**
         * Show loading state
         */
        showLoading: function() {
            $('.loading-indicator').show();
            $('.handy-products-grid').css('opacity', '0.6');
        },
        
        /**
         * Hide loading state
         */
        hideLoading: function() {
            $('.loading-indicator').hide();
            $('.handy-products-grid').css('opacity', '1');
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            var $grid = $('.handy-products-grid');
            $grid.html('<div class="no-results"><p>' + message + '</p></div>');
        }
        
    };

})(jQuery);
/**
 * Recipes Archive JavaScript
 * Handles AJAX filtering and interactions for recipe archive
 * Features 3-dropdown filtering system for recipes
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Initialize recipes archive functionality
        if ($('.handy-recipes-archive').length) {
            RecipesArchive.init();
        }
        
    });

    /**
     * Recipes Archive Object
     */
    var RecipesArchive = {
        
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            console.log('Recipes Archive initialized');
        },
        
        /**
         * Bind events
         */
        bindEvents: function() {
            // Filter change events
            $(document).on('change', '.recipe-filter', this.handleFilterChange);
            
            // Clear filters button
            $(document).on('click', '#clear-recipe-filters', this.clearFilters);
            
            // Recipe card hover effects (if needed)
            $(document).on('mouseenter', '.recipe-card', this.handleCardHover);
            $(document).on('mouseleave', '.recipe-card', this.handleCardLeave);
        },
        
        /**
         * Handle filter changes
         */
        handleFilterChange: function() {
            var $container = $('.handy-recipes-archive');
            var filters = {};
            
            // Collect all filter values
            $('.recipe-filter').each(function() {
                var name = $(this).attr('name');
                var value = $(this).val();
                if (name && value) {
                    filters[name] = value;
                }
            });
            
            console.log('Recipe filters changed:', filters);
            
            // Show loading
            RecipesArchive.showLoading();
            
            // Send AJAX request
            $.ajax({
                url: handyCustomRecipesAjax.ajaxUrl,
                type: 'POST',
                data: {
                    action: handyCustomRecipesAjax.action,
                    nonce: handyCustomRecipesAjax.nonce,
                    ...filters
                },
                success: function(response) {
                    if (response.success) {
                        // Replace the entire archive content
                        $('.handy-recipes-archive').html($(response.data.html).find('.handy-recipes-archive').html());
                        console.log('Recipes filtered successfully');
                        
                        // Reinitialize events for new content
                        RecipesArchive.bindEvents();
                    } else {
                        console.error('Recipe filter error:', response.data);
                        RecipesArchive.showError('Failed to filter recipes. Please try again.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Recipe AJAX error:', error);
                    RecipesArchive.showError('Connection error. Please refresh the page and try again.');
                },
                complete: function() {
                    RecipesArchive.hideLoading();
                }
            });
        },
        
        /**
         * Clear all filters
         */
        clearFilters: function(e) {
            e.preventDefault();
            
            // Reset all filter dropdowns
            $('.recipe-filter').val('');
            
            // Trigger filter change to reload all recipes
            $('.recipe-filter').first().trigger('change');
            
            console.log('Recipe filters cleared');
        },
        
        /**
         * Handle recipe card hover effects
         */
        handleCardHover: function() {
            // Optional: Add custom hover effects beyond CSS
            $(this).addClass('recipe-card-hovered');
        },
        
        /**
         * Handle recipe card leave effects
         */
        handleCardLeave: function() {
            $(this).removeClass('recipe-card-hovered');
        },
        
        /**
         * Show loading state
         */
        showLoading: function() {
            $('.loading-indicator').show();
            $('.handy-recipes-grid').css('opacity', '0.6');
            $('.handy-recipes-filters').css('pointer-events', 'none');
        },
        
        /**
         * Hide loading state
         */
        hideLoading: function() {
            $('.loading-indicator').hide();
            $('.handy-recipes-grid').css('opacity', '1');
            $('.handy-recipes-filters').css('pointer-events', 'auto');
        },
        
        /**
         * Show error message
         */
        showError: function(message) {
            var $grid = $('.handy-recipes-grid');
            $grid.html('<div class="no-results"><p>' + message + '</p><button type="button" class="btn-clear-filters" id="clear-recipe-filters">Clear Filters</button></div>');
        },
        
        /**
         * Get current filter state
         */
        getCurrentFilters: function() {
            var filters = {};
            $('.recipe-filter').each(function() {
                var name = $(this).attr('name');
                var value = $(this).val();
                if (name && value) {
                    filters[name] = value;
                }
            });
            return filters;
        },
        
        /**
         * Update URL with current filters (optional enhancement)
         */
        updateURL: function(filters) {
            if (history.pushState) {
                var url = new URL(window.location);
                
                // Clear existing recipe filter params
                url.searchParams.delete('recipe_category');
                url.searchParams.delete('recipe_cooking_method');
                url.searchParams.delete('recipe_menu_occasion');
                
                // Add current filters
                Object.keys(filters).forEach(function(key) {
                    if (filters[key]) {
                        url.searchParams.set('recipe_' + key, filters[key]);
                    }
                });
                
                history.pushState(null, '', url.toString());
            }
        },
        
        /**
         * Initialize filters from URL parameters (optional enhancement)
         */
        initFromURL: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var hasFilters = false;
            
            // Check for recipe filter parameters
            if (urlParams.has('recipe_category')) {
                $('select[name="category"]').val(urlParams.get('recipe_category'));
                hasFilters = true;
            }
            
            if (urlParams.has('recipe_cooking_method')) {
                $('select[name="cooking_method"]').val(urlParams.get('recipe_cooking_method'));
                hasFilters = true;
            }
            
            if (urlParams.has('recipe_menu_occasion')) {
                $('select[name="menu_occasion"]').val(urlParams.get('recipe_menu_occasion'));
                hasFilters = true;
            }
            
            // Trigger filter if any URL parameters were found
            if (hasFilters) {
                $('.recipe-filter').first().trigger('change');
            }
        }
        
    };
    
    // Enhanced initialization with URL parameter support
    $(document).ready(function() {
        if ($('.handy-recipes-archive').length) {
            RecipesArchive.init();
            RecipesArchive.initFromURL();
        }
    });

})(jQuery);
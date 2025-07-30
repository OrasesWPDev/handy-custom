/**
 * Unified filter functionality for both products and recipes
 * 
 * User request: "I think 1 css file for all filters and 1 js file for all filters, 
 * as long as the code is well documented, will do. make sure all code has logical 
 * logging, using the plugin's logs so if needed, a developer can turn logging to 
 * true and debug issues."
 *
 * @package Handy_Custom
 */

(function($) {
    'use strict';

    /**
     * Main filter handling class
     */
    class HandyCustomFilters {
        
        /**
         * Initialize the filter system
         */
        constructor() {
            this.debug = (typeof handyCustomFiltersAjax !== 'undefined' && handyCustomFiltersAjax.debug) || false;
            this.ajaxUrl = (typeof handyCustomFiltersAjax !== 'undefined' && handyCustomFiltersAjax.ajaxUrl) || '/wp-admin/admin-ajax.php';
            this.nonce = (typeof handyCustomFiltersAjax !== 'undefined' && handyCustomFiltersAjax.nonce) || '';
            
            // Store context boundaries to prevent users from breaking out of intended scope
            this.contextBoundaries = {};
            
            this.log('Initializing Handy Custom Filters system', 'info');
            this.init();
        }

        /**
         * Initialize event handlers and setup
         */
        init() {
            this.log('Setting up filter event handlers', 'info');
            
            // Handle filter changes
            $(document).on('change', '.handy-filters .filter-select', (event) => {
                this.handleFilterChange(event);
            });
            
            // Handle clear filters button (all variations)
            $(document).on('click', '.btn-clear-filters, .btn-clear-filters-main, .btn-clear-filters-universal', (event) => {
                this.handleClearFilters(event);
            });
            
            // Initialize context boundaries from data attributes
            this.initializeContextBoundaries();
            
            // Initialize filter states from URL on page load
            this.initializeFromURL();
            
            // Handle browser back/forward buttons
            $(window).on('popstate', () => {
                this.log('Browser popstate detected, reinitializing filters', 'info');
                this.initializeFromURL();
                this.triggerContentUpdate();
            });

            this.log('Filter system initialization complete', 'info');
        }

        /**
         * Initialize context boundaries from filter container data attributes
         * These boundaries prevent users from breaking out of the intended filtering scope
         */
        initializeContextBoundaries() {
            $('.handy-filters').each((index, filterContainer) => {
                const $container = $(filterContainer);
                const contentType = $container.data('content-type');
                
                if (contentType) {
                    this.contextBoundaries[contentType] = {
                        context_category: $container.data('context-category') || '',
                        context_subcategory: $container.data('context-subcategory') || ''
                    };
                    
                    this.log('Context boundaries initialized', 'info', {
                        contentType: contentType,
                        boundaries: this.contextBoundaries[contentType]
                    });
                }
            });
        }

        /**
         * Handle individual filter changes
         * 
         * @param {Event} event Filter change event
         */
        handleFilterChange(event) {
            const $select = $(event.target);
            const filterName = $select.attr('name');
            const filterValue = $select.val();
            const contentType = $select.data('content-type');
            
            this.log('Filter changed', 'info', {
                filter: filterName,
                value: filterValue,
                contentType: contentType,
                timestamp: new Date().toISOString()
            });
            
            // Update select visual state
            this.updateSelectState($select, filterValue);
            
            // Update URL parameters
            this.updateURL(filterName, filterValue);
            
            // Store the triggering filter for smart cascading updates
            this.lastChangedFilter = filterName;
            
            // Trigger content update for matching content type
            this.triggerContentUpdate(contentType);
        }

        /**
         * Handle clear all filters
         * 
         * @param {Event} event Clear button click event
         */
        handleClearFilters(event) {
            event.preventDefault();
            
            const $button = $(event.target);
            const contentType = $button.data('content-type');
            
            this.log('Clearing all filters', 'info', {
                contentType: contentType,
                timestamp: new Date().toISOString()
            });
            
            // Clear all filter selects in this filter group
            // Since button may be outside filter container, find by content type
            const $filterContainer = $(`.handy-filters[data-content-type="${contentType}"]`);
            $filterContainer.find('.filter-select').each((index, select) => {
                const $select = $(select);
                $select.val('');
                this.updateSelectState($select, '');
            });
            
            // Clear URL parameters
            this.clearURLParameters(contentType);
            
            // Clear last changed filter since we're clearing all
            this.lastChangedFilter = null;
            
            // Trigger content update
            this.triggerContentUpdate(contentType);
            
            // Note: Universal clear button remains visible (no need to hide)
        }

        /**
         * Update visual state of select element
         * 
         * @param {jQuery} $select Select element
         * @param {string} value Current value
         */
        updateSelectState($select, value) {
            if (value && value.trim() !== '') {
                $select.attr('data-has-value', 'true');
                this.log('Select state updated to active', 'debug', {
                    select: $select.attr('name'),
                    value: value
                });
            } else {
                $select.removeAttr('data-has-value');
                this.log('Select state updated to inactive', 'debug', {
                    select: $select.attr('name')
                });
            }
            
            // Show/hide clear button based on any active filters
            this.updateClearButtonVisibility($select.closest('.handy-filters'));
        }

        /**
         * Update clear button visibility based on active filters
         * Note: Universal clear button is always visible, but we keep this method for legacy support
         * 
         * @param {jQuery} $filterContainer Filter container
         */
        updateClearButtonVisibility($filterContainer) {
            // Universal clear button is always visible, so we don't need to hide/show it
            // Keep this method for legacy clear button support
            const hasActiveFilters = $filterContainer.find('.filter-select[data-has-value="true"]').length > 0;
            const $legacyClearButton = $filterContainer.find('.btn-clear-filters, .btn-clear-filters-main').parent();
            
            if ($legacyClearButton.length > 0) {
                if (hasActiveFilters) {
                    $legacyClearButton.show();
                    this.log('Legacy clear button shown - active filters detected', 'debug');
                } else {
                    $legacyClearButton.hide();
                    this.log('Legacy clear button hidden - no active filters', 'debug');
                }
            }
        }

        /**
         * Update URL parameters with current filter state
         * 
         * @param {string} filterName Filter parameter name
         * @param {string} filterValue Filter value
         */
        updateURL(filterName, filterValue) {
            const url = new URL(window.location);
            
            if (filterValue && filterValue.trim() !== '') {
                url.searchParams.set(filterName, filterValue);
                this.log('URL parameter added', 'debug', {
                    parameter: filterName,
                    value: filterValue
                });
            } else {
                url.searchParams.delete(filterName);
                this.log('URL parameter removed', 'debug', {
                    parameter: filterName
                });
            }
            
            // Update browser history without triggering popstate
            window.history.pushState({}, '', url);
            
            this.log('URL updated', 'info', {
                newUrl: url.toString(),
                timestamp: new Date().toISOString()
            });
        }

        /**
         * Clear URL parameters for specific content type
         * 
         * @param {string} contentType Content type (products/recipes)
         */
        clearURLParameters(contentType) {
            const url = new URL(window.location);
            const $filterContainer = $(`.handy-filters[data-content-type="${contentType}"]`);
            
            // Get all filter names for this content type
            $filterContainer.find('.filter-select').each((index, select) => {
                const filterName = $(select).attr('name');
                url.searchParams.delete(filterName);
            });
            
            // Update browser history
            window.history.pushState({}, '', url);
            
            this.log('URL parameters cleared for content type', 'info', {
                contentType: contentType,
                newUrl: url.toString()
            });
        }

        /**
         * Initialize filter states from current URL parameters
         */
        initializeFromURL() {
            const urlParams = new URLSearchParams(window.location.search);
            
            this.log('Initializing filters from URL', 'info', {
                parameters: Object.fromEntries(urlParams.entries())
            });
            
            // Update all filter selects based on URL parameters
            $('.handy-filters .filter-select').each((index, select) => {
                const $select = $(select);
                const filterName = $select.attr('name');
                const urlValue = urlParams.get(filterName);
                
                if (urlValue) {
                    $select.val(urlValue);
                    this.updateSelectState($select, urlValue);
                    
                    this.log('Filter initialized from URL', 'debug', {
                        filter: filterName,
                        value: urlValue
                    });
                }
            });
        }

        /**
         * Trigger content update for matching content shortcodes
         * 
         * @param {string} contentType Content type to update (optional)
         */
        triggerContentUpdate(contentType) {
            this.log('Triggering content update', 'info', {
                contentType: contentType || 'all',
                timestamp: new Date().toISOString()
            });
            
            // Show loading state
            this.showLoadingState();
            
            // Get current filter parameters
            const filterParams = this.getCurrentFilterParameters(contentType);
            
            this.log('Filter parameters for content update', 'debug', filterParams);
            
            // Trigger AJAX update based on content type
            if (contentType === 'products' || !contentType) {
                this.updateProductsContent(filterParams);
            }
            
            if (contentType === 'recipes' || !contentType) {
                this.updateRecipesContent(filterParams);
            }
        }

        /**
         * Get current filter parameters from URL
         * 
         * @param {string} contentType Content type filter (optional)
         * @return {Object} Filter parameters
         */
        getCurrentFilterParameters(contentType) {
            const params = {};
            const urlParams = new URLSearchParams(window.location.search);
            
            // If content type specified, only get relevant filters
            if (contentType) {
                $(`.handy-filters[data-content-type="${contentType}"] .filter-select`).each((index, select) => {
                    const filterName = $(select).attr('name');
                    const value = urlParams.get(filterName);
                    if (value) {
                        params[filterName] = value;
                    }
                });
            } else {
                // Get all URL parameters
                for (const [key, value] of urlParams.entries()) {
                    params[key] = value;
                }
            }
            
            return params;
        }

        /**
         * Update products content via AJAX
         * 
         * @param {Object} filterParams Filter parameters
         */
        updateProductsContent(filterParams) {
            const $productsContainer = $('.handy-products-archive');
            
            if ($productsContainer.length === 0) {
                this.log('No products container found, skipping products update', 'debug');
                return;
            }
            
            // Get display mode with better debugging
            const displayMode = $productsContainer.data('display-mode') || 'categories';
            this.log('Updating products content via AJAX', 'info', {
                filterParams: filterParams,
                displayMode: displayMode,
                containerFound: true,
                containerClass: $productsContainer.attr('class')
            });
            
            // Add display mode, context boundaries, and other required parameters
            const ajaxParams = Object.assign({
                action: 'filter_products',
                nonce: this.nonce,
                display: displayMode
            }, filterParams);
            
            // CRITICAL: Add context boundaries to preserve filtering scope
            if (this.contextBoundaries.products) {
                ajaxParams.context_category = this.contextBoundaries.products.context_category;
                ajaxParams.context_subcategory = this.contextBoundaries.products.context_subcategory;
                
                this.log('Adding context boundaries to AJAX request', 'info', {
                    context_category: ajaxParams.context_category,
                    context_subcategory: ajaxParams.context_subcategory
                });
            }
            
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: ajaxParams,
                timeout: 30000,
                success: (response) => {
                    this.handleAjaxSuccess(response, $productsContainer, 'products');
                },
                error: (xhr, status, error) => {
                    this.handleAjaxError(xhr, status, error, 'products');
                }
            });
        }

        /**
         * Update recipes content via AJAX
         * 
         * @param {Object} filterParams Filter parameters
         */
        updateRecipesContent(filterParams) {
            const $recipesContainer = $('.handy-recipes-archive');
            
            if ($recipesContainer.length === 0) {
                this.log('No recipes container found, skipping recipes update', 'debug');
                return;
            }
            
            this.log('Updating recipes content via AJAX', 'info', filterParams);
            
            const ajaxParams = Object.assign({
                action: 'filter_recipes',
                nonce: this.nonce
            }, filterParams);
            
            // Add context boundaries for recipes if available
            if (this.contextBoundaries.recipes) {
                ajaxParams.context_category = this.contextBoundaries.recipes.context_category;
                ajaxParams.context_subcategory = this.contextBoundaries.recipes.context_subcategory;
                
                this.log('Adding context boundaries to recipes AJAX request', 'info', {
                    context_category: ajaxParams.context_category,
                    context_subcategory: ajaxParams.context_subcategory
                });
            }
            
            $.ajax({
                url: this.ajaxUrl,
                type: 'POST',
                data: ajaxParams,
                timeout: 30000,
                success: (response) => {
                    this.handleAjaxSuccess(response, $recipesContainer, 'recipes');
                },
                error: (xhr, status, error) => {
                    this.handleAjaxError(xhr, status, error, 'recipes');
                }
            });
        }

        /**
         * Handle successful AJAX response
         * 
         * @param {Object} response AJAX response
         * @param {jQuery} $container Content container
         * @param {string} contentType Content type
         */
        handleAjaxSuccess(response, $container, contentType) {
            this.hideLoadingState();
            
            this.log('AJAX response received', 'info', {
                contentType: contentType,
                success: response.success,
                hasData: !!response.data,
                hasHtml: !!(response.data && response.data.html),
                responseKeys: Object.keys(response)
            });
            
            if (response.success && response.data && response.data.html) {
                $container.replaceWith(response.data.html);
                
                this.log('Content updated successfully', 'info', {
                    contentType: contentType,
                    timestamp: new Date().toISOString()
                });
                
                // Process cascading filter options if provided
                if (response.data.updated_filter_options) {
                    this.updateFilterDropdowns(response.data.updated_filter_options, contentType);
                }
                
                // Trigger custom event for other scripts
                $(document).trigger('handyCustomContentUpdated', {
                    contentType: contentType,
                    container: $container
                });
                
            } else {
                this.log('Invalid AJAX response received', 'error', {
                    response: response,
                    responseSuccess: response.success,
                    responseData: response.data
                });
                this.showErrorMessage('Invalid response received from server.');
            }
        }

        /**
         * Handle AJAX error
         * 
         * @param {Object} xhr XMLHttpRequest object
         * @param {string} status Error status
         * @param {string} error Error message
         * @param {string} contentType Content type
         */
        handleAjaxError(xhr, status, error, contentType) {
            this.hideLoadingState();
            
            this.log('AJAX request failed', 'error', {
                contentType: contentType,
                status: status,
                error: error,
                responseText: xhr.responseText
            });
            
            this.showErrorMessage('Failed to update content. Please refresh the page and try again.');
        }

        /**
         * Show loading state
         */
        showLoadingState() {
            $('.handy-filters .filter-loading').show();
            $('.handy-filters .filter-select').prop('disabled', true);
            
            this.log('Loading state shown', 'debug');
        }

        /**
         * Hide loading state
         */
        hideLoadingState() {
            $('.handy-filters .filter-loading').hide();
            $('.handy-filters .filter-select').prop('disabled', false);
            
            this.log('Loading state hidden', 'debug');
        }

        /**
         * Show error message to user
         * 
         * @param {string} message Error message
         */
        showErrorMessage(message) {
            // Create or update error message
            let $errorDiv = $('.handy-filters-error');
            
            if ($errorDiv.length === 0) {
                $errorDiv = $('<div class="handy-filters-error filter-error"><p></p></div>');
                $('.handy-filters').first().before($errorDiv);
            }
            
            $errorDiv.find('p').text(message);
            $errorDiv.show();
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                $errorDiv.fadeOut();
            }, 5000);
            
            this.log('Error message shown to user', 'info', { message: message });
        }

        /**
         * Logging function with debug control
         * 
         * @param {string} message Log message
         * @param {string} level Log level (info, debug, error, warning)
         * @param {Object} data Additional data to log
         */
        log(message, level = 'info', data = null) {
            if (!this.debug) {
                return;
            }
            
            const timestamp = new Date().toISOString();
            const logEntry = `[Handy Filters ${level.toUpperCase()}] ${timestamp}: ${message}`;
            
            // Log to console based on level
            switch (level) {
                case 'error':
                    console.error(logEntry, data);
                    break;
                case 'warning':
                    console.warn(logEntry, data);
                    break;
                case 'debug':
                    console.debug(logEntry, data);
                    break;
                default:
                    console.log(logEntry, data);
            }
        }

        /**
         * Update filter dropdowns with cascading options
         * Dynamically updates all filter select elements with new options based on current selections
         * 
         * @param {Object} updatedOptions Updated filter options from server
         * @param {string} contentType Content type (products/recipes)
         */
        updateFilterDropdowns(updatedOptions, contentType) {
            this.log('Updating filter dropdowns with cascading options', 'info', {
                contentType: contentType,
                taxonomyCount: Object.keys(updatedOptions).length,
                updatedOptions: updatedOptions
            });

            const $filterContainer = $(`.handy-filters[data-content-type="${contentType}"]`);
            
            if ($filterContainer.length === 0) {
                this.log('No filter container found for content type: ' + contentType, 'warning');
                return;
            }

            // Update each filter select element (except the one that triggered the change)
            Object.keys(updatedOptions).forEach((taxonomyKey) => {
                // CRITICAL FIX: Don't update the filter that the user just changed
                if (this.lastChangedFilter && taxonomyKey === this.lastChangedFilter) {
                    this.log(`Skipping update for triggering filter: ${taxonomyKey}`, 'info');
                    return;
                }
                
                const $select = $filterContainer.find(`select[name="${taxonomyKey}"]`);
                
                if ($select.length === 0) {
                    this.log(`No select element found for taxonomy: ${taxonomyKey}`, 'debug');
                    return;
                }

                // Store current selection
                const currentValue = $select.val();
                const terms = updatedOptions[taxonomyKey];

                this.log(`Updating ${taxonomyKey} filter with ${terms.length} options`, 'debug', {
                    currentValue: currentValue,
                    newTermCount: terms.length,
                    triggeringFilter: this.lastChangedFilter
                });

                // Remove all options except the first (placeholder)
                $select.find('option:not(:first)').remove();

                // Add new options
                terms.forEach((term) => {
                    const option = $('<option></option>')
                        .attr('value', term.slug)
                        .text(term.name);
                    $select.append(option);
                });

                // Restore selection if it's still available
                if (currentValue && $select.find(`option[value="${currentValue}"]`).length > 0) {
                    $select.val(currentValue);
                    this.log(`Restored selection for ${taxonomyKey}: ${currentValue}`, 'debug');
                } else if (currentValue) {
                    // Current selection is no longer available, clear it
                    $select.val('');
                    this.updateSelectState($select, '');
                    this.log(`Cleared unavailable selection for ${taxonomyKey}: ${currentValue}`, 'info');
                    
                    // Update URL to remove this filter
                    this.updateURL(taxonomyKey, '');
                }

                // Update visual state
                this.updateSelectState($select, $select.val());
            });

            this.log('Filter dropdowns updated successfully', 'info', {
                contentType: contentType,
                timestamp: new Date().toISOString()
            });
        }
    }

    /**
     * Initialize when document is ready
     */
    $(document).ready(function() {
        // Only initialize if filter elements exist
        if ($('.handy-filters').length > 0) {
            window.handyCustomFilters = new HandyCustomFilters();
        }
    });

})(jQuery);
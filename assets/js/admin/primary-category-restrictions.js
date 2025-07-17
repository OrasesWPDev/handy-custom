/**
 * Admin JavaScript for Primary Category Restrictions
 * 
 * Hides the "Make Primary" option for subcategories in the WordPress admin
 * Only allows top-level categories to be marked as primary
 * 
 * @package Handy_Custom
 */

(function($) {
    'use strict';

    // Wait for DOM to be ready
    $(document).ready(function() {
        initPrimaryCategoryRestrictions();
    });

    /**
     * Initialize primary category restrictions
     */
    function initPrimaryCategoryRestrictions() {
        // Only run on product post type edit pages
        if (!isProductEditPage()) {
            return;
        }

        // Hide primary options for subcategories on page load
        restrictSubcategoryPrimaryOptions();
        
        // Watch for changes to category selections
        watchCategoryChanges();
        
        // Handle dynamic category additions (if using AJAX)
        $(document).on('DOMNodeInserted', function(e) {
            if ($(e.target).hasClass('categorychecklist') || $(e.target).find('.categorychecklist').length) {
                restrictSubcategoryPrimaryOptions();
            }
        });
    }

    /**
     * Check if we're on a product edit page
     */
    function isProductEditPage() {
        return $('body').hasClass('post-type-product') && 
               ($('body').hasClass('post-php') || $('body').hasClass('post-new-php'));
    }

    /**
     * Restrict primary options for subcategories
     */
    function restrictSubcategoryPrimaryOptions() {
        // Get all product category checkboxes
        $('#product-categorychecklist input[type="checkbox"]').each(function() {
            var $checkbox = $(this);
            var categoryId = $checkbox.val();
            
            // Skip if no category ID
            if (!categoryId) return;
            
            // Get category data from WordPress localized data
            var categoryData = getCategoryData(categoryId);
            
            if (categoryData && categoryData.parent > 0) {
                // This is a subcategory - hide primary option
                hidePrimaryOptionForCategory($checkbox);
            } else {
                // This is a top-level category - ensure primary option is visible
                showPrimaryOptionForCategory($checkbox);
            }
        });
    }

    /**
     * Get category data from WordPress localized data or DOM
     */
    function getCategoryData(categoryId) {
        // Try to get from localized data first
        if (typeof handyCustomAdmin !== 'undefined' && handyCustomAdmin.categoryData) {
            return handyCustomAdmin.categoryData[categoryId];
        }
        
        // Fallback: parse from DOM structure
        var $checkbox = $('#product-categorychecklist input[value="' + categoryId + '"]');
        var $listItem = $checkbox.closest('li');
        
        // Check if this item is nested (has parent)
        var isNested = $listItem.parents('.children').length > 0;
        
        return {
            id: categoryId,
            parent: isNested ? 1 : 0 // Simple check - nested = has parent
        };
    }

    /**
     * Hide primary option for a specific category
     */
    function hidePrimaryOptionForCategory($checkbox) {
        var $listItem = $checkbox.closest('li');
        
        // Hide Yoast SEO primary category button
        $listItem.find('.wpseo-make-primary-term').hide();
        
        // Hide any other primary category controls
        $listItem.find('.make-primary-category').hide();
        $listItem.find('[class*="primary"]').filter(':not(input[type="checkbox"])').hide();
        
        // Add visual indicator
        if (!$listItem.find('.subcategory-notice').length) {
            $checkbox.after('<span class="subcategory-notice" style="font-size: 11px; color: #666; margin-left: 5px;">(subcategory - cannot be primary)</span>');
        }
    }

    /**
     * Show primary option for a specific category
     */
    function showPrimaryOptionForCategory($checkbox) {
        var $listItem = $checkbox.closest('li');
        
        // Show Yoast SEO primary category button
        $listItem.find('.wpseo-make-primary-term').show();
        
        // Show any other primary category controls
        $listItem.find('.make-primary-category').show();
        
        // Remove subcategory notice
        $listItem.find('.subcategory-notice').remove();
    }

    /**
     * Watch for changes to category selections
     */
    function watchCategoryChanges() {
        // Watch for checkbox changes
        $(document).on('change', '#product-categorychecklist input[type="checkbox"]', function() {
            // Re-apply restrictions after a short delay
            setTimeout(restrictSubcategoryPrimaryOptions, 100);
        });
        
        // Watch for category additions/removals
        $(document).on('click', '.wp-tab-panel a', function() {
            // Re-apply restrictions after category operations
            setTimeout(restrictSubcategoryPrimaryOptions, 500);
        });
    }

    /**
     * Validation before form submission
     */
    $(document).on('submit', '#post', function(e) {
        // Check if any subcategories are marked as primary
        var hasInvalidPrimary = false;
        
        $('#product-categorychecklist input[type="checkbox"]:checked').each(function() {
            var categoryId = $(this).val();
            var categoryData = getCategoryData(categoryId);
            
            if (categoryData && categoryData.parent > 0) {
                // Check if this subcategory is somehow marked as primary
                var $listItem = $(this).closest('li');
                if ($listItem.find('.wpseo-make-primary-term.wpseo-primary-term').length) {
                    hasInvalidPrimary = true;
                    return false; // Break out of loop
                }
            }
        });
        
        if (hasInvalidPrimary) {
            e.preventDefault();
            alert('Subcategories cannot be marked as primary. Please select a top-level category as primary.');
            return false;
        }
    });

})(jQuery);
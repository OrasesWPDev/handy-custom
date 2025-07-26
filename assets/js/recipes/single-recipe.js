/**
 * Single Recipe Template JavaScript
 * 
 * Handles accordion functionality for recipe detail sections and featured product card equalization.
 * Based on product single template functionality with recipe-specific enhancements.
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize accordion functionality
    initRecipeAccordion();
    
    // Initialize card equalizer for featured products
    initFeaturedProductCardEqualizer();
    
    /**
     * Initialize recipe detail accordion
     * Per user instructions: Each section independent, content appears directly below header
     */
    function initRecipeAccordion() {
        const accordionHeaders = document.querySelectorAll('.handy-accordion-header');
        
        if (accordionHeaders.length === 0) {
            return;
        }
        
        // Add click event listeners to accordion headers
        accordionHeaders.forEach(function(header) {
            header.addEventListener('click', function() {
                const targetSection = this.getAttribute('data-section');
                const targetContent = document.getElementById(targetSection);
                const isActive = this.classList.contains('active');
                
                // Toggle current section
                if (isActive) {
                    // Close the currently active section
                    this.classList.remove('active');
                    if (targetContent) {
                        targetContent.classList.remove('active');
                    }
                } else {
                    // Open the clicked section
                    this.classList.add('active');
                    if (targetContent) {
                        targetContent.classList.add('active');
                    }
                }
            });
        });
        
        if (typeof handyCustomSingleRecipe !== 'undefined' && handyCustomSingleRecipe.debug) {
            console.log('Recipe accordion initialized');
            console.log('Accordion headers found:', accordionHeaders.length);
        }
    }
    
    /**
     * Initialize card equalizer for featured products section
     */
    function initFeaturedProductCardEqualizer() {
        const featuredProductsGrid = document.querySelector('.handy-featured-products-grid');
        
        if (featuredProductsGrid && typeof window.HandyCardEqualizer !== 'undefined') {
            
            // Function to run equalization
            function runEqualization() {
                if (window.HandyCardEqualizer) {
                    window.HandyCardEqualizer.refresh();
                    
                    if (typeof handyCustomSingleRecipe !== 'undefined' && handyCustomSingleRecipe.debug) {
                        console.log('Featured products card equalizer executed');
                    }
                }
            }
            
            // Multiple triggers for better reliability
            
            // 1. Initial timeout (increased from 100ms to 500ms)
            setTimeout(runEqualization, 500);
            
            // 2. Window load event for when all resources are loaded
            window.addEventListener('load', function() {
                setTimeout(runEqualization, 100);
            });
            
            // 3. Wait for images to load
            const images = featuredProductsGrid.querySelectorAll('img');
            if (images.length > 0) {
                let imagesLoaded = 0;
                images.forEach(function(img) {
                    if (img.complete) {
                        imagesLoaded++;
                    } else {
                        img.addEventListener('load', function() {
                            imagesLoaded++;
                            if (imagesLoaded === images.length) {
                                setTimeout(runEqualization, 50);
                            }
                        });
                    }
                });
                
                // If all images already loaded
                if (imagesLoaded === images.length) {
                    setTimeout(runEqualization, 50);
                }
            }
            
            // 4. Fallback trigger after longer delay
            setTimeout(runEqualization, 1000);
            
            // 5. Re-equalize on window resize
            window.addEventListener('resize', function() {
                setTimeout(runEqualization, 250);
            });
            
            if (typeof handyCustomSingleRecipe !== 'undefined' && handyCustomSingleRecipe.debug) {
                console.log('Featured products card equalizer initialized with multiple triggers');
            }
        }
    }
    
    // Debug logging for recipe functionality
    if (typeof handyCustomSingleRecipe !== 'undefined' && handyCustomSingleRecipe.debug) {
        console.log('Single Recipe JavaScript initialized');
        console.log('Featured products grid found:', document.querySelector('.handy-featured-products-grid') ? 'Yes' : 'No');
    }
    
});
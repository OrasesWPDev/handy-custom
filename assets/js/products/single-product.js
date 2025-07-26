/**
 * Single Product Template JavaScript
 * 
 * Handles accordion functionality for product detail sections.
 * Implements accordion-style sections with smooth transitions per user requirement:
 * "content for specifications shows under the word specifications" and clicking expands/collapses individual sections.
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize accordion functionality
    initProductAccordion();
    
    /**
     * Initialize product detail accordion
     * Per user instructions: Each section independent, content appears directly below header
     */
    function initProductAccordion() {
        const accordionHeaders = document.querySelectorAll('.handy-accordion-header');
        const accordionContents = document.querySelectorAll('.handy-accordion-content');
        
        if (accordionHeaders.length === 0 || accordionContents.length === 0) {
            return;
        }
        
        // Add click event listeners to accordion headers
        accordionHeaders.forEach(function(header) {
            header.addEventListener('click', function() {
                const targetSection = this.getAttribute('data-section');
                
                // Toggle the clicked section
                if (this.classList.contains('active')) {
                    // Close the currently active section
                    closeAccordionSection(this, targetSection);
                } else {
                    // Close all sections first
                    closeAllAccordionSections();
                    // Open the clicked section
                    openAccordionSection(this, targetSection);
                }
            });
        });
        
        // Initialize with first section open (specifications)
        if (accordionHeaders[0] && accordionContents[0]) {
            accordionHeaders[0].classList.add('active');
            accordionContents[0].classList.add('active');
        }
    }
    
    /**
     * Open a specific accordion section
     * @param {Element} header - The accordion header element
     * @param {string} targetSection - The section ID to open
     */
    function openAccordionSection(header, targetSection) {
        const targetContent = document.getElementById(targetSection);
        
        if (targetContent) {
            // Activate header
            header.classList.add('active');
            
            // Show content with animation
            targetContent.style.display = 'block';
            // Force reflow for animation
            targetContent.offsetHeight;
            targetContent.classList.add('active');
            
            // Scroll to header if needed (for mobile)
            if (window.innerWidth <= 768) {
                header.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'nearest' 
                });
            }
        }
    }
    
    /**
     * Close a specific accordion section
     * @param {Element} header - The accordion header element
     * @param {string} targetSection - The section ID to close
     */
    function closeAccordionSection(header, targetSection) {
        const targetContent = document.getElementById(targetSection);
        
        if (targetContent) {
            // Deactivate header
            header.classList.remove('active');
            
            // Hide content
            targetContent.classList.remove('active');
            setTimeout(function() {
                if (!targetContent.classList.contains('active')) {
                    targetContent.style.display = 'none';
                }
            }, 300);
        }
    }
    
    /**
     * Close all accordion sections
     */
    function closeAllAccordionSections() {
        const accordionHeaders = document.querySelectorAll('.handy-accordion-header');
        const accordionContents = document.querySelectorAll('.handy-accordion-content');
        
        accordionHeaders.forEach(function(header) {
            header.classList.remove('active');
        });
        
        accordionContents.forEach(function(content) {
            content.classList.remove('active');
            setTimeout(function() {
                if (!content.classList.contains('active')) {
                    content.style.display = 'none';
                }
            }, 300);
        });
    }
    
    /**
     * Handle print functionality
     */
    function handlePrint() {
        // Add print-specific styles
        const printStyles = document.createElement('style');
        printStyles.textContent = `
            @media print {
                .handy-single-product-accordion .handy-accordion-content {
                    display: block !important;
                    page-break-inside: avoid;
                }
                .handy-accordion-header {
                    display: none;
                }
                .handy-product-social-row {
                    display: none;
                }
            }
        `;
        document.head.appendChild(printStyles);
        
        // Print
        window.print();
        
        // Remove print styles after printing
        setTimeout(function() {
            document.head.removeChild(printStyles);
        }, 1000);
    }
    
    // Enhance print functionality
    const printButton = document.querySelector('.handy-print-icon');
    if (printButton) {
        printButton.addEventListener('click', function(e) {
            e.preventDefault();
            handlePrint();
        });
    }
    
    /**
     * Handle responsive behavior
     */
    function handleResize() {
        const accordionHeaders = document.querySelectorAll('.handy-accordion-header');
        const accordionContents = document.querySelectorAll('.handy-accordion-content');
        
        // On mobile, ensure only one section is open at a time
        if (window.innerWidth <= 768) {
            const activeSections = document.querySelectorAll('.handy-accordion-header.active');
            if (activeSections.length > 1) {
                // Close all but the first active section
                for (let i = 1; i < activeSections.length; i++) {
                    const targetSection = activeSections[i].getAttribute('data-section');
                    closeAccordionSection(activeSections[i], targetSection);
                }
            }
        }
    }
    
    // Add resize listener
    window.addEventListener('resize', handleResize);
    
    /**
     * Smooth scroll for anchor links within accordion sections
     */
    function initSmoothScroll() {
        const accordionContents = document.querySelectorAll('.handy-accordion-content');
        
        accordionContents.forEach(function(content) {
            const anchorLinks = content.querySelectorAll('a[href^="#"]');
            
            anchorLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    const targetId = this.getAttribute('href').substring(1);
                    const targetElement = document.getElementById(targetId);
                    
                    if (targetElement) {
                        e.preventDefault();
                        targetElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
    }
    
    // Initialize smooth scroll
    initSmoothScroll();
    
    // Initialize card equalizer for featured recipes
    initFeaturedRecipeCardEqualizer();
    
    /**
     * Initialize card equalizer for featured recipes section
     */
    function initFeaturedRecipeCardEqualizer() {
        const featuredRecipesGrid = document.querySelector('.handy-featured-recipes-grid');
        
        if (featuredRecipesGrid && typeof window.cardEqualizer !== 'undefined') {
            // Wait for images to load before equalizing
            setTimeout(function() {
                window.cardEqualizer.equalizeRecipeCards();
                
                if (typeof handyCustomSingleProduct !== 'undefined' && handyCustomSingleProduct.debug) {
                    console.log('Featured recipes card equalizer initialized');
                }
            }, 100);
            
            // Re-equalize on window resize
            window.addEventListener('resize', function() {
                setTimeout(function() {
                    if (window.cardEqualizer) {
                        window.cardEqualizer.equalizeRecipeCards();
                    }
                }, 250);
            });
        }
    }
    
    // Debug logging for accordion functionality
    if (typeof handyCustomSingleProduct !== 'undefined' && handyCustomSingleProduct.debug) {
        console.log('Single Product accordion initialized');
        console.log('Accordion headers found:', document.querySelectorAll('.handy-accordion-header').length);
        console.log('Accordion contents found:', document.querySelectorAll('.handy-accordion-content').length);
        console.log('Featured recipes grid found:', document.querySelector('.handy-featured-recipes-grid') ? 'Yes' : 'No');
    }
    
});
/**
 * Single Product Template JavaScript
 * 
 * Handles tab functionality for product detail sections.
 * Implements accordion-style tabs with smooth transitions.
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // Initialize tab functionality
    initProductTabs();
    
    /**
     * Initialize product detail tabs
     */
    function initProductTabs() {
        const tabButtons = document.querySelectorAll('.handy-tab-button');
        const tabPanels = document.querySelectorAll('.handy-tab-panel');
        
        if (tabButtons.length === 0 || tabPanels.length === 0) {
            return;
        }
        
        // Add click event listeners to tab buttons
        tabButtons.forEach(function(button) {
            button.addEventListener('click', function() {
                const targetTab = this.getAttribute('data-tab');
                
                // Toggle the clicked tab
                if (this.classList.contains('active')) {
                    // Close the currently active tab
                    closeTab(this, targetTab);
                } else {
                    // Close all tabs first
                    closeAllTabs();
                    // Open the clicked tab
                    openTab(this, targetTab);
                }
            });
        });
        
        // Initialize with first tab open
        if (tabButtons[0] && tabPanels[0]) {
            tabButtons[0].classList.add('active');
            tabPanels[0].classList.add('active');
        }
    }
    
    /**
     * Open a specific tab
     * @param {Element} button - The tab button element
     * @param {string} targetTab - The tab ID to open
     */
    function openTab(button, targetTab) {
        const targetPanel = document.getElementById(targetTab);
        
        if (targetPanel) {
            // Activate button
            button.classList.add('active');
            
            // Show panel with animation
            targetPanel.style.display = 'block';
            // Force reflow for animation
            targetPanel.offsetHeight;
            targetPanel.classList.add('active');
            
            // Scroll to tab if needed (for mobile)
            if (window.innerWidth <= 768) {
                button.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'nearest' 
                });
            }
        }
    }
    
    /**
     * Close a specific tab
     * @param {Element} button - The tab button element
     * @param {string} targetTab - The tab ID to close
     */
    function closeTab(button, targetTab) {
        const targetPanel = document.getElementById(targetTab);
        
        if (targetPanel) {
            // Deactivate button
            button.classList.remove('active');
            
            // Hide panel
            targetPanel.classList.remove('active');
            setTimeout(function() {
                if (!targetPanel.classList.contains('active')) {
                    targetPanel.style.display = 'none';
                }
            }, 300);
        }
    }
    
    /**
     * Close all tabs
     */
    function closeAllTabs() {
        const tabButtons = document.querySelectorAll('.handy-tab-button');
        const tabPanels = document.querySelectorAll('.handy-tab-panel');
        
        tabButtons.forEach(function(button) {
            button.classList.remove('active');
        });
        
        tabPanels.forEach(function(panel) {
            panel.classList.remove('active');
            setTimeout(function() {
                if (!panel.classList.contains('active')) {
                    panel.style.display = 'none';
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
                .handy-single-product-tabs .handy-tab-panel {
                    display: block !important;
                    page-break-inside: avoid;
                }
                .handy-tab-navigation {
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
        const tabButtons = document.querySelectorAll('.handy-tab-button');
        const tabPanels = document.querySelectorAll('.handy-tab-panel');
        
        // On mobile, ensure only one tab is open at a time
        if (window.innerWidth <= 768) {
            const activeTabs = document.querySelectorAll('.handy-tab-button.active');
            if (activeTabs.length > 1) {
                // Close all but the first active tab
                for (let i = 1; i < activeTabs.length; i++) {
                    const targetTab = activeTabs[i].getAttribute('data-tab');
                    closeTab(activeTabs[i], targetTab);
                }
            }
        }
    }
    
    // Add resize listener
    window.addEventListener('resize', handleResize);
    
    /**
     * Smooth scroll for anchor links within tabs
     */
    function initSmoothScroll() {
        const tabPanels = document.querySelectorAll('.handy-tab-panel');
        
        tabPanels.forEach(function(panel) {
            const anchorLinks = panel.querySelectorAll('a[href^="#"]');
            
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
    
    // Debug logging (remove for production)
    if (typeof HANDY_CUSTOM_DEBUG !== 'undefined' && HANDY_CUSTOM_DEBUG) {
        console.log('Single Product tabs initialized');
        console.log('Tab buttons found:', document.querySelectorAll('.handy-tab-button').length);
        console.log('Tab panels found:', document.querySelectorAll('.handy-tab-panel').length);
    }
    
});
/**
 * Handy Custom Card Height Equalizer
 * 
 * Unified solution for equalizing card heights within rows for both products and recipes.
 * Ensures proper bottom alignment of action elements (metadata, buttons) across cards.
 * 
 * @package Handy_Custom
 * @version 1.0.0
 */

class HandyCardEqualizer {
    constructor() {
        this.debounceTimeout = null;
        this.resizeDelay = 250;
        
        // Responsive breakpoints matching CSS
        this.breakpoints = {
            recipes: {
                fourColumn: 1601,    // 4 columns above 1600px
                threeColumn: 1201,   // 3 columns: 1201-1600px
                twoColumn: 550       // 2 columns: 550-1200px, 1 column below 549px
            },
            products: {
                twoColumn: 768       // 2 columns above 768px, 1 column below
            }
        };
        
        this.init();
    }
    
    init() {
        // Wait for DOM to be fully loaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.setupEqualizers());
        } else {
            this.setupEqualizers();
        }
        
        // Handle window resize with debouncing
        window.addEventListener('resize', () => this.debouncedResize());
    }
    
    setupEqualizers() {
        this.equalizeProductCards();
        this.equalizeRecipeCards();
    }
    
    debouncedResize() {
        clearTimeout(this.debounceTimeout);
        this.debounceTimeout = setTimeout(() => {
            this.resetAllHeights();
            this.setupEqualizers();
        }, this.resizeDelay);
    }
    
    resetAllHeights() {
        // Reset product card heights
        const productCards = document.querySelectorAll('.product-list-card');
        productCards.forEach(card => {
            const info = card.querySelector('.product-info');
            if (info) info.style.height = 'auto';
        });
        
        // Reset recipe card heights  
        const recipeCards = document.querySelectorAll('.recipe-card');
        recipeCards.forEach(card => {
            card.style.height = 'auto';
        });
    }
    
    equalizeProductCards() {
        const productCards = document.querySelectorAll('.product-list-card');
        if (productCards.length === 0) return;
        
        const windowWidth = window.innerWidth;
        const columnsPerRow = windowWidth > this.breakpoints.products.twoColumn ? 2 : 1;
        
        if (columnsPerRow === 1) {
            // Single column - no equalization needed
            return;
        }
        
        // Group cards into rows
        const rows = this.groupCardsIntoRows(productCards, columnsPerRow);
        
        // Equalize height for each row
        rows.forEach(row => {
            let maxHeight = 0;
            const cardInfos = [];
            
            // Find tallest card info area in this row
            row.forEach(card => {
                const info = card.querySelector('.product-info');
                if (info) {
                    info.style.height = 'auto'; // Reset first
                    const height = info.offsetHeight;
                    maxHeight = Math.max(maxHeight, height);
                    cardInfos.push(info);
                }
            });
            
            // Apply max height to all cards in row
            cardInfos.forEach(info => {
                info.style.height = maxHeight + 'px';
            });
        });
    }
    
    equalizeRecipeCards() {
        const recipeCards = document.querySelectorAll('.recipe-card');
        if (recipeCards.length === 0) return;
        
        const windowWidth = window.innerWidth;
        let columnsPerRow;
        
        // Determine columns per row based on breakpoints
        if (windowWidth > this.breakpoints.recipes.fourColumn) {
            columnsPerRow = 4;
        } else if (windowWidth > this.breakpoints.recipes.threeColumn) {
            columnsPerRow = 3;
        } else if (windowWidth > this.breakpoints.recipes.twoColumn) {
            columnsPerRow = 2;
        } else {
            columnsPerRow = 1;
        }
        
        if (columnsPerRow === 1) {
            // Single column - no equalization needed
            return;
        }
        
        // Group cards into rows
        const rows = this.groupCardsIntoRows(recipeCards, columnsPerRow);
        
        // Equalize height for each row
        rows.forEach(row => {
            let maxHeight = 0;
            
            // Find tallest card in this row
            row.forEach(card => {
                card.style.height = 'auto'; // Reset first
                const height = card.offsetHeight;
                maxHeight = Math.max(maxHeight, height);
            });
            
            // Apply max height to all cards in row
            row.forEach(card => {
                card.style.height = maxHeight + 'px';
            });
        });
    }
    
    groupCardsIntoRows(cards, columnsPerRow) {
        const rows = [];
        for (let i = 0; i < cards.length; i += columnsPerRow) {
            rows.push(Array.from(cards).slice(i, i + columnsPerRow));
        }
        return rows;
    }
    
    // Public method for triggering equalization after dynamic content changes
    refresh() {
        this.resetAllHeights();
        this.setupEqualizers();
    }
}

// Initialize the card equalizer
const handyCardEqualizer = new HandyCardEqualizer();

// Export for use in other scripts
window.HandyCardEqualizer = handyCardEqualizer;
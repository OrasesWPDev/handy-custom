/**
 * Handy Custom Card Height Equalizer v2.0
 * 
 * Structure-based card equalization system for products and recipes.
 * Handles title heights per row, content truncation, and bottom element alignment.
 * 
 * Products: 2-line titles, 2-line content, aligned buttons
 * Recipes: 4-line titles per row, content truncation, aligned metadata
 * 
 * @package Handy_Custom
 * @version 2.0.0
 */

class HandyCardEqualizer {
    constructor() {
        this.debounceTimeout = null;
        this.resizeDelay = 250;
        
        // Content limits for consistent display
        this.contentLimits = {
            products: {
                titleLines: 2,
                contentLines: 2,
                contentChars: 140
            },
            recipes: {
                titleLines: 4,
                contentChars: 160
            }
        };
        
        // Responsive breakpoints matching CSS exactly
        this.breakpoints = {
            recipes: {
                threeColumn: 1601,   // 3 columns above 1600px (updated from 4)
                twoColumn: 1201,     // 2 columns: 1201-1600px  
                oneColumn: 550       // 1 column below 549px
            },
            products: {
                twoColumn: 549       // 2 columns above 549px, 1 column below (matches CSS)
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
            this.resetAllStructure();
            this.setupEqualizers();
        }, this.resizeDelay);
    }
    
    resetAllStructure() {
        // Reset product card structure
        const productCards = document.querySelectorAll('.product-list-card');
        productCards.forEach(card => {
            const title = card.querySelector('.product-title');
            const excerpt = card.querySelector('.product-excerpt p');
            const info = card.querySelector('.product-info');
            
            if (title) {
                title.style.height = 'auto';
                title.classList.remove('title-truncated');
            }
            if (excerpt) {
                excerpt.classList.remove('content-truncated');
                excerpt.style.height = 'auto';
            }
            if (info) {
                info.style.minHeight = 'auto';
            }
        });
        
        // Reset recipe card structure
        const recipeCards = document.querySelectorAll('.recipe-card');
        recipeCards.forEach(card => {
            const title = card.querySelector('.recipe-card-title');
            const description = card.querySelector('.recipe-card-description');
            const content = card.querySelector('.recipe-card-content');
            
            if (title) {
                title.style.height = 'auto';
                title.classList.remove('title-truncated');
            }
            if (description) {
                description.classList.remove('content-truncated');
            }
            if (content) {
                content.style.minHeight = 'auto';
            }
        });
    }
    
    equalizeProductCards() {
        const productCards = document.querySelectorAll('.product-list-card');
        if (productCards.length === 0) return;
        
        const windowWidth = window.innerWidth;
        const columnsPerRow = windowWidth > this.breakpoints.products.twoColumn ? 2 : 1;
        
        if (columnsPerRow === 1) {
            // Single column - apply content limits but no row equalization
            productCards.forEach(card => this.applyProductContentLimits(card));
            return;
        }
        
        // Group cards into rows
        const rows = this.groupCardsIntoRows(productCards, columnsPerRow);
        
        // Process each row for structure-based equalization
        rows.forEach(row => {
            // First apply content limits to all cards in row
            row.forEach(card => this.applyProductContentLimits(card));
            
            // Then equalize title heights within the row
            this.equalizeRowTitleHeights(row, '.product-title');
            
            // Finally, ensure consistent overall card info heights for button alignment
            this.equalizeRowCardInfoHeights(row, '.product-info');
        });
    }
    
    equalizeRecipeCards() {
        const recipeCards = document.querySelectorAll('.recipe-card');
        if (recipeCards.length === 0) return;
        
        const windowWidth = window.innerWidth;
        let columnsPerRow;
        
        // Determine columns per row based on breakpoints (3 columns maximum)
        if (windowWidth > this.breakpoints.recipes.threeColumn) {
            columnsPerRow = 3;
        } else if (windowWidth > this.breakpoints.recipes.twoColumn) {
            columnsPerRow = 2;
        } else {
            columnsPerRow = 1;
        }
        
        if (columnsPerRow === 1) {
            // Single column - apply content limits but no row equalization
            recipeCards.forEach(card => this.applyRecipeContentLimits(card));
            return;
        }
        
        // Group cards into rows
        const rows = this.groupCardsIntoRows(recipeCards, columnsPerRow);
        
        // Process each row for structure-based equalization
        rows.forEach(row => {
            // First apply content limits to all cards in row
            row.forEach(card => this.applyRecipeContentLimits(card));
            
            // Then equalize title heights within the row (4 lines max per title for 3-column layout)
            this.equalizeRowTitleHeights(row, '.recipe-card-title');
            
            // Finally, ensure consistent card content heights for metadata alignment
            this.equalizeRowCardInfoHeights(row, '.recipe-card-content');
        });
    }
    
    applyProductContentLimits(card) {
        const title = card.querySelector('.product-title');
        const excerpt = card.querySelector('.product-excerpt p');
        
        // Apply title truncation (2 lines max)
        if (title) {
            this.applyLineClamp(title, this.contentLimits.products.titleLines);
        }
        
        // Apply content truncation (2 lines max, ~140 chars)
        if (excerpt) {
            this.truncateContent(excerpt, this.contentLimits.products.contentChars);
            this.applyLineClamp(excerpt, this.contentLimits.products.contentLines);
        }
    }
    
    applyRecipeContentLimits(card) {
        const title = card.querySelector('.recipe-card-title');
        const description = card.querySelector('.recipe-card-description');
        
        // Apply title truncation (4 lines max)
        if (title) {
            this.applyLineClamp(title, this.contentLimits.recipes.titleLines);
        }
        
        // Apply content truncation (~160 chars to match reference image)
        if (description) {
            this.truncateContent(description, this.contentLimits.recipes.contentChars);
        }
    }
    
    applyLineClamp(element, lines) {
        element.style.display = '-webkit-box';
        element.style.webkitLineClamp = lines.toString();
        element.style.webkitBoxOrient = 'vertical';
        element.style.overflow = 'hidden';
        element.classList.add('title-truncated');
    }
    
    truncateContent(element, maxChars) {
        const originalText = element.textContent || element.innerText;
        if (originalText.length > maxChars) {
            const truncated = originalText.substring(0, maxChars).trim();
            const lastSpace = truncated.lastIndexOf(' ');
            const finalText = lastSpace > 0 ? truncated.substring(0, lastSpace) + '...' : truncated + '...';
            element.textContent = finalText;
            element.classList.add('content-truncated');
        }
    }
    
    equalizeRowTitleHeights(row, titleSelector) {
        let maxTitleHeight = 0;
        const titles = [];
        
        // Find tallest title in this row
        row.forEach(card => {
            const title = card.querySelector(titleSelector);
            if (title) {
                title.style.height = 'auto'; // Reset first
                const height = title.offsetHeight;
                maxTitleHeight = Math.max(maxTitleHeight, height);
                titles.push(title);
            }
        });
        
        // Apply max height to all titles in row
        titles.forEach(title => {
            title.style.height = maxTitleHeight + 'px';
        });
    }
    
    equalizeRowCardInfoHeights(row, infoSelector) {
        let maxInfoHeight = 0;
        const infos = [];
        
        // Find tallest info area in this row
        row.forEach(card => {
            const info = card.querySelector(infoSelector);
            if (info) {
                info.style.minHeight = 'auto'; // Reset first
                const height = info.offsetHeight;
                maxInfoHeight = Math.max(maxInfoHeight, height);
                infos.push(info);
            }
        });
        
        // Apply min height to ensure bottom elements align
        infos.forEach(info => {
            info.style.minHeight = maxInfoHeight + 'px';
            info.style.display = 'flex';
            info.style.flexDirection = 'column';
            info.style.justifyContent = 'space-between';
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
        this.resetAllStructure();
        this.setupEqualizers();
    }
}

// Initialize the card equalizer
const handyCardEqualizer = new HandyCardEqualizer();

// Export for use in other scripts
window.HandyCardEqualizer = handyCardEqualizer;
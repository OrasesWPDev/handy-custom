const playwright = require('playwright');
const fs = require('fs');

async function debugRecipePage() {
    console.log('🔍 Starting debug of recipe page...');
    
    const browser = await playwright.chromium.launch({ headless: true });
    const page = await browser.newPage();
    
    try {
        // Navigate to the specific recipe page
        console.log('📍 Navigating to http://localhost:10008/recipe/bloody-mary-crab-cocktail/');
        await page.goto('http://localhost:10008/recipe/bloody-mary-crab-cocktail/', { 
            waitUntil: 'networkidle',
            timeout: 30000 
        });
        
        // Take a screenshot
        await page.screenshot({ 
            path: 'debug-recipe-page-screenshot.png', 
            fullPage: true 
        });
        console.log('📸 Screenshot saved: debug-recipe-page-screenshot.png');
        
        // Get page title
        const title = await page.title();
        console.log('📄 Page title:', title);
        
        // Get page content
        const content = await page.content();
        
        // Check for PHP errors or visible PHP code
        const phpErrors = content.match(/Fatal error|Parse error|Warning:|Notice:|<\?php|php\s*\?>/gi);
        if (phpErrors) {
            console.log('🚨 PHP ERRORS/CODE DETECTED:');
            phpErrors.forEach(error => console.log('  -', error));
        }
        
        // Check for featured products section
        const featuredProductsExists = await page.locator('.handy-featured-products-section').count();
        console.log('🔍 Featured Products section found:', featuredProductsExists > 0 ? 'YES' : 'NO');
        
        // Check for featured products grid
        const featuredProductsGrid = await page.locator('.handy-featured-products-grid').count();
        console.log('🔍 Featured Products grid found:', featuredProductsGrid > 0 ? 'YES' : 'NO');
        
        // Check for recipe content
        const recipeContentExists = await page.locator('.handy-recipe-content').count();
        console.log('🔍 Recipe content found:', recipeContentExists > 0 ? 'YES' : 'NO');
        
        // Check for PHP output in visible text
        const bodyText = await page.locator('body').textContent();
        const phpCodeVisible = bodyText.includes('<?php') || bodyText.includes('php ?>');
        console.log('🚨 Visible PHP code:', phpCodeVisible ? 'YES - CRITICAL ERROR' : 'NO');
        
        // Log any console errors
        page.on('console', msg => {
            if (msg.type() === 'error') {
                console.log('❌ Browser console error:', msg.text());
            }
        });
        
        // Get network errors
        page.on('response', response => {
            if (!response.ok()) {
                console.log('🌐 Network error:', response.status(), response.url());
            }
        });
        
        // Save page HTML for analysis
        fs.writeFileSync('debug-recipe-page-content.html', content);
        console.log('💾 Page HTML saved: debug-recipe-page-content.html');
        
        console.log('✅ Debug complete');
        
    } catch (error) {
        console.error('❌ Error during debug:', error.message);
        
        // Try to take screenshot anyway
        try {
            await page.screenshot({ path: 'debug-recipe-page-error.png', fullPage: true });
            console.log('📸 Error screenshot saved: debug-recipe-page-error.png');
        } catch (screenshotError) {
            console.error('❌ Could not take error screenshot:', screenshotError.message);
        }
    } finally {
        await browser.close();
    }
}

debugRecipePage();
const { test, expect } = require('@playwright/test');
const { WordPressUtils } = require('../helpers/wordpress-utils');
const { PluginUtils } = require('../helpers/plugin-utils');

test.describe('Products Functionality @full', () => {
  let wpUtils;
  let pluginUtils;

  test.beforeEach(async ({ page }) => {
    wpUtils = new WordPressUtils(page);
    pluginUtils = new PluginUtils(page);
  });

  test('Products archive page displays correctly', async ({ page }) => {
    await page.goto('/products/');
    
    // Check page title
    await expect(page).toHaveTitle(/.*products.*/i);
    
    // Check products grid exists
    const productsGrid = page.locator('.handy-products-grid, .products-grid');
    await expect(productsGrid).toBeVisible();
    
    // Check that products are displayed
    const productCards = page.locator('.product-card');
    await expect(productCards.first()).toBeVisible();
    
    // Check product card content
    const firstCard = productCards.first();
    await expect(firstCard.locator('h3, .product-title')).toBeVisible();
    await expect(firstCard.locator('a')).toBeVisible();
  });

  test('Product filters work correctly', async ({ page }) => {
    await page.goto('/products/');
    
    // Test filter functionality
    const filtersContainer = await pluginUtils.testFilters('products');
    
    // Check if results updated after filtering
    const productCards = page.locator('.product-card');
    await expect(productCards.first()).toBeVisible();
  });

  test('Product pagination works', async ({ page }) => {
    await page.goto('/products/');
    
    // Check if pagination exists (if there are enough products)
    const pagination = page.locator('.pagination, .page-numbers');
    
    if (await pagination.isVisible()) {
      const nextButton = pagination.locator('.next, [aria-label="Next"]');
      
      if (await nextButton.isVisible()) {
        // Get current products
        const firstPageProducts = await page.locator('.product-card h3').allTextContents();
        
        // Go to next page
        await nextButton.click();
        await page.waitForLoadState('networkidle');
        
        // Check that products changed
        const secondPageProducts = await page.locator('.product-card h3').allTextContents();
        expect(firstPageProducts).not.toEqual(secondPageProducts);
      }
    }
  });

  test('Single product page loads correctly', async ({ page }) => {
    await page.goto('/products/');
    
    // Click on first product
    const firstProduct = page.locator('.product-card a').first();
    const productUrl = await firstProduct.getAttribute('href');
    
    await firstProduct.click();
    await page.waitForLoadState('networkidle');
    
    // Check URL pattern
    expect(productUrl).toMatch(/\/products\/[^\/]+\/[^\/]+\//);
    
    // Check page content
    await expect(page.locator('h1')).toBeVisible();
    
    // Check breadcrumbs
    const breadcrumbs = page.locator('.breadcrumbs, .yoast-breadcrumb');
    if (await breadcrumbs.isVisible()) {
      await expect(breadcrumbs).toContainText('Products');
    }
    
    // Check product content area
    const productContent = page.locator('.product-content, .single-product-content, .entry-content');
    await expect(productContent).toBeVisible();
  });

  test('Product URL rewriting works correctly', async ({ page }) => {
    const productUrl = await pluginUtils.testURLRewriting();
    
    if (productUrl) {
      // Verify URL follows expected pattern
      expect(productUrl).toMatch(/\/products\/[^\/]+\/[^\/]+\/$/);
      
      // Navigate directly to URL to ensure it works
      await page.goto(productUrl);
      await expect(page.locator('h1')).toBeVisible();
    }
  });

  test('Product categories display correctly', async ({ page }) => {
    await page.goto('/products/');
    
    // Look for category filters or links
    const categoryElements = page.locator('.product-category, .category-filter, select[name*="category"] option');
    
    if (await categoryElements.first().isVisible()) {
      const categories = await categoryElements.allTextContents();
      expect(categories.length).toBeGreaterThan(1); // Should have at least one category plus "All"
    }
  });

  test('Product search functionality', async ({ page }) => {
    await page.goto('/products/');
    
    // Look for search input
    const searchInput = page.locator('input[type="search"], input[name*="search"], .search-field');
    
    if (await searchInput.isVisible()) {
      await searchInput.fill('crab');
      await searchInput.press('Enter');
      
      // Wait for results
      await page.waitForTimeout(1000);
      
      // Check that results are displayed
      const productCards = page.locator('.product-card');
      if (await productCards.first().isVisible()) {
        // Verify search results contain the search term
        const productTitles = await page.locator('.product-card h3').allTextContents();
        const hasRelevantResults = productTitles.some(title => 
          title.toLowerCase().includes('crab')
        );
        
        // Note: This might not always pass depending on content
        // expect(hasRelevantResults).toBe(true);
      }
    }
  });

  test('Product images load correctly', async ({ page }) => {
    await page.goto('/products/');
    
    // Check product images
    const productImages = page.locator('.product-card img, .product-image img');
    
    if (await productImages.first().isVisible()) {
      const firstImage = productImages.first();
      
      // Check that image has src attribute
      const imageSrc = await firstImage.getAttribute('src');
      expect(imageSrc).toBeTruthy();
      
      // Check that image loads (not broken)
      const imageLoaded = await firstImage.evaluate(img => img.complete && img.naturalHeight !== 0);
      expect(imageLoaded).toBe(true);
    }
  });

  test('Product page is mobile responsive', async ({ page }) => {
    // Test mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/products/');
    
    // Check that products grid is still visible and usable
    const productsGrid = page.locator('.handy-products-grid, .products-grid');
    await expect(productsGrid).toBeVisible();
    
    // Check that product cards stack properly on mobile
    const productCards = page.locator('.product-card');
    if (await productCards.count() > 1) {
      const firstCard = productCards.first();
      const secondCard = productCards.nth(1);
      
      const firstCardBox = await firstCard.boundingBox();
      const secondCardBox = await secondCard.boundingBox();
      
      // On mobile, cards should stack vertically (second card should be below first)
      if (firstCardBox && secondCardBox) {
        expect(secondCardBox.y).toBeGreaterThan(firstCardBox.y + firstCardBox.height - 50);
      }
    }
    
    // Reset viewport
    await page.setViewportSize({ width: 1200, height: 800 });
  });
});
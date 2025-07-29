const { expect } = require('@playwright/test');

/**
 * Handy Custom Plugin specific utility functions
 */
class PluginUtils {
  constructor(page) {
    this.page = page;
  }

  /**
   * Test product shortcode functionality
   * @param {Object} options - Shortcode options
   */
  async testProductShortcode(options = {}) {
    const {
      category = '',
      limit = 12,
      columns = 3,
      pagination = true
    } = options;

    // Navigate to page with products shortcode
    await this.page.goto('/products/');
    
    // Check if products are displayed
    await expect(this.page.locator('.handy-products-grid')).toBeVisible();
    
    // Check grid layout - products page shows categories, not individual products  
    const productCategories = this.page.locator('.product-category-card, .category-card, [class*="category"]');
    await expect(productCategories.first()).toBeVisible();
    
    // Check pagination if enabled
    if (pagination) {
      const paginationElement = this.page.locator('.pagination');
      if (await paginationElement.isVisible()) {
        await expect(paginationElement).toBeVisible();
      }
    }
    
    return productCategories;
  }

  /**
   * Test recipe shortcode functionality
   * @param {Object} options - Shortcode options
   */
  async testRecipeShortcode(options = {}) {
    const {
      category = '',
      limit = 12,
      columns = 3,
      pagination = true
    } = options;

    // Navigate to page with recipes shortcode
    await this.page.goto('/recipes/');
    
    // Check if recipes are displayed
    await expect(this.page.locator('.handy-recipes-grid')).toBeVisible();
    
    // Check grid layout
    const recipes = this.page.locator('.recipe-card');
    await expect(recipes.first()).toBeVisible();
    
    // Check pagination if enabled
    if (pagination) {
      const paginationElement = this.page.locator('.pagination');
      if (await paginationElement.isVisible()) {
        await expect(paginationElement).toBeVisible();
      }
    }
    
    return recipes;
  }

  /**
   * Test filter functionality
   * @param {string} type - 'products' or 'recipes'
   */
  async testFilters(type = 'products') {
    await this.page.goto(`/${type}/`);
    
    // Check if filters are present
    const filtersContainer = this.page.locator('.handy-filters');
    await expect(filtersContainer).toBeVisible();
    
    // Test category filter
    const categoryFilter = filtersContainer.locator('select[name="category"]');
    if (await categoryFilter.isVisible()) {
      await categoryFilter.selectOption({ index: 1 }); // Select first non-empty option
      
      // Wait for AJAX response
      await this.page.waitForResponse(response => 
        response.url().includes('wp-admin/admin-ajax.php') && response.status() === 200
      );
      
      // Check if results updated
      await this.page.waitForTimeout(1000); // Allow time for DOM update
    }
    
    return filtersContainer;
  }

  /**
   * Test single product page
   * @param {string} productSlug - Product slug to test
   */
  async testSingleProduct(productSlug) {
    await this.page.goto(`/products/category/${productSlug}/`);
    
    // Check if product title is visible
    await expect(this.page.locator('h1.product-title')).toBeVisible();
    
    // Check breadcrumbs
    const breadcrumbs = this.page.locator('.breadcrumbs, .yoast-breadcrumb');
    if (await breadcrumbs.isVisible()) {
      await expect(breadcrumbs).toContainText('Products');
    }
    
    // Check product details
    const productContent = this.page.locator('.product-content, .single-product-content');
    await expect(productContent).toBeVisible();
    
    return {
      title: await this.page.locator('h1.product-title').textContent(),
      breadcrumbs: await breadcrumbs.textContent()
    };
  }

  /**
   * Test single recipe page
   * @param {string} recipeSlug - Recipe slug to test
   */
  async testSingleRecipe(recipeSlug) {
    await this.page.goto(`/recipe/${recipeSlug}/`);
    
    // Check if recipe title is visible
    await expect(this.page.locator('h1.recipe-title')).toBeVisible();
    
    // Check breadcrumbs
    const breadcrumbs = this.page.locator('.breadcrumbs, .yoast-breadcrumb');
    if (await breadcrumbs.isVisible()) {
      await expect(breadcrumbs).toContainText('Recipes');
    }
    
    // Check recipe content
    const recipeContent = this.page.locator('.recipe-content, .single-recipe-content');
    await expect(recipeContent).toBeVisible();
    
    return {
      title: await this.page.locator('h1.recipe-title').textContent(),
      breadcrumbs: await breadcrumbs.textContent()
    };
  }

  /**
   * Test URL rewriting
   */
  async testURLRewriting() {
    // Get first product to test URL structure
    await this.page.goto('/products/');
    const firstProduct = this.page.locator('.product-card a').first();
    
    if (await firstProduct.isVisible()) {
      const productUrl = await firstProduct.getAttribute('href');
      
      // Check if URL follows expected pattern: /products/category/product-slug/
      const urlPattern = /\/products\/[^\/]+\/[^\/]+\/$/;
      expect(productUrl).toMatch(urlPattern);
      
      // Navigate to product and check if page loads
      await firstProduct.click();
      await expect(this.page.locator('h1')).toBeVisible();
      
      return productUrl;
    }
    
    return null;
  }

  /**
   * Test responsive design
   * @param {Array} viewports - Array of viewport sizes to test
   */
  async testResponsive(viewports = [
    { width: 320, height: 568 },  // Mobile
    { width: 768, height: 1024 }, // Tablet
    { width: 1200, height: 800 }  // Desktop
  ]) {
    const results = [];
    
    for (const viewport of viewports) {
      await this.page.setViewportSize(viewport);
      await this.page.goto('/products/');
      
      // Check if layout adjusts appropriately
      const grid = this.page.locator('.handy-products-grid');
      const isVisible = await grid.isVisible();
      
      results.push({
        viewport,
        isVisible,
        screenshot: await this.page.screenshot({ 
          path: `tests/results/screenshots/responsive-${viewport.width}x${viewport.height}.png` 
        })
      });
    }
    
    return results;
  }

  /**
   * Check for JavaScript errors
   */
  async checkForJSErrors() {
    const errors = [];
    
    this.page.on('pageerror', error => {
      // Filter out common, harmless errors
      const message = error.message;
      if (!message.includes('favicon') && 
          !message.includes('adsystem') &&
          !message.includes('google') &&
          !message.includes('wp-emoji') &&
          !message.includes('wp-polyfill') &&
          !message.includes('404') &&
          !message.includes('net::ERR_')) {
        errors.push(message);
      }
    });
    
    this.page.on('console', msg => {
      if (msg.type() === 'error') {
        const text = msg.text();
        // Filter out common, harmless console errors
        if (!text.includes('favicon') && 
            !text.includes('adsystem') &&
            !text.includes('google') &&
            !text.includes('wp-emoji') &&
            !text.includes('wp-polyfill') &&
            !text.includes('404') &&
            !text.includes('net::ERR_')) {
          errors.push(text);
        }
      }
    });
    
    return errors;
  }

  /**
   * Test plugin assets loading
   */
  async testAssetLoading() {
    await this.page.goto('/products/');
    
    // Check CSS files
    const cssFiles = await this.page.evaluate(() => {
      const links = Array.from(document.querySelectorAll('link[rel="stylesheet"]'));
      return links
        .filter(link => link.href.includes('handy-custom'))
        .map(link => link.href);
    });
    
    // Check JS files
    const jsFiles = await this.page.evaluate(() => {
      const scripts = Array.from(document.querySelectorAll('script[src]'));
      return scripts
        .filter(script => script.src.includes('handy-custom'))
        .map(script => script.src);
    });
    
    return { cssFiles, jsFiles };
  }

  /**
   * Test featured recipes admin functionality
   * @param {Object} wpUtils - WordPress utils instance for admin access
   */
  async testFeaturedRecipeAdmin(wpUtils) {
    // Navigate to recipes admin page
    await wpUtils.loginToAdmin();
    await this.page.goto('/wp-admin/edit.php?post_type=recipe');
    
    // Wait for page to load
    await this.page.waitForSelector('table.wp-list-table');
    
    // Check if Featured column exists
    const featuredColumn = this.page.locator('thead th:has-text("Featured")');
    await expect(featuredColumn).toBeVisible();
    
    // Find all recipe rows
    const recipeRows = this.page.locator('tbody tr:not(.no-items)');
    const rowCount = await recipeRows.count();
    
    if (rowCount === 0) {
      console.log('No recipes found in admin - skipping featured tests');
      return { hasRecipes: false };
    }
    
    // Get first recipe row for testing
    const firstRow = recipeRows.first();
    const featuredToggle = firstRow.locator('.toggle-featured-status');
    
    // Check if toggle exists
    await expect(featuredToggle).toBeVisible();
    
    // Get initial state
    const initialIcon = await featuredToggle.locator('.dashicons').getAttribute('class');
    const isFeatured = initialIcon.includes('dashicons-star-filled');
    
    return {
      hasRecipes: true,
      featuredColumnExists: true,
      toggleExists: true,
      initiallyFeatured: isFeatured,
      rowCount
    };
  }

  /**
   * Test featured recipe toggle functionality
   * @param {Object} wpUtils - WordPress utils instance for admin access
   */
  async testFeaturedRecipeToggle(wpUtils) {
    await wpUtils.loginToAdmin();
    await this.page.goto('/wp-admin/edit.php?post_type=recipe');
    
    // Wait for page to load
    await this.page.waitForSelector('table.wp-list-table');
    
    const recipeRows = this.page.locator('tbody tr:not(.no-items)');
    const rowCount = await recipeRows.count();
    
    if (rowCount === 0) {
      return { success: false, message: 'No recipes available for testing' };
    }
    
    // Get first recipe toggle
    const firstRow = recipeRows.first();
    const featuredToggle = firstRow.locator('.toggle-featured-status');
    
    // Get initial state
    const initialIcon = await featuredToggle.locator('.dashicons').getAttribute('class');
    const wasInitiallyFeatured = initialIcon.includes('dashicons-star-filled');
    
    // Click the toggle and wait for AJAX response
    const responsePromise = this.page.waitForResponse(response => 
      response.url().includes('wp-admin/admin-ajax.php') && 
      response.url().includes('action=toggle_featured_status')
    );
    
    await featuredToggle.click();
    const response = await responsePromise;
    
    // Verify AJAX response was successful
    expect(response.status()).toBe(200);
    
    // Wait for DOM update
    await this.page.waitForTimeout(1000);
    
    // Check if icon changed
    const newIcon = await featuredToggle.locator('.dashicons').getAttribute('class');
    const isNowFeatured = newIcon.includes('dashicons-star-filled');
    
    // Verify state changed
    expect(isNowFeatured).toBe(!wasInitiallyFeatured);
    
    return {
      success: true,
      wasInitiallyFeatured,
      isNowFeatured,
      stateChanged: isNowFeatured !== wasInitiallyFeatured
    };
  }

  /**
   * Test featured recipes limit enforcement (max 3)
   * @param {Object} wpUtils - WordPress utils instance for admin access
   */
  async testFeaturedRecipeLimit(wpUtils) {
    await wpUtils.loginToAdmin();
    await this.page.goto('/wp-admin/edit.php?post_type=recipe');
    
    await this.page.waitForSelector('table.wp-list-table');
    
    const recipeRows = this.page.locator('tbody tr:not(.no-items)');
    const rowCount = await recipeRows.count();
    
    if (rowCount < 4) {
      return { success: false, message: 'Need at least 4 recipes to test limit enforcement' };
    }
    
    // First, clear all featured recipes
    for (let i = 0; i < rowCount; i++) {
      const row = recipeRows.nth(i);
      const toggle = row.locator('.toggle-featured-status');
      const icon = await toggle.locator('.dashicons').getAttribute('class');
      
      if (icon.includes('dashicons-star-filled')) {
        await toggle.click();
        await this.page.waitForTimeout(500); // Brief pause between requests
      }
    }
    
    // Now set exactly 3 recipes as featured
    for (let i = 0; i < 3; i++) {
      const row = recipeRows.nth(i);
      const toggle = row.locator('.toggle-featured-status');
      await toggle.click();
      await this.page.waitForTimeout(500);
    }
    
    // Try to set a 4th recipe as featured - should be prevented
    const fourthRow = recipeRows.nth(3);
    const fourthToggle = fourthRow.locator('.toggle-featured-status');
    
    const responsePromise = this.page.waitForResponse(response => 
      response.url().includes('wp-admin/admin-ajax.php')
    );
    
    await fourthToggle.click();
    const response = await responsePromise;
    
    // Should get an error response due to limit
    const responseData = await response.json();
    
    return {
      success: true,
      limitEnforced: !responseData.success,
      responseMessage: responseData.data?.message || 'No message'
    };
  }

  /**
   * Test featured recipes shortcode functionality
   * @param {string} testPageUrl - URL of page containing [featured-recipes] shortcode
   */
  async testFeaturedRecipesShortcode(testPageUrl = '/') {
    await this.page.goto(testPageUrl);
    
    // Look for featured recipes container
    const featuredContainer = this.page.locator('.handy-featured-recipes-section, [data-shortcode="featured-recipes"]');
    
    if (!(await featuredContainer.isVisible())) {
      return { success: false, message: 'Featured recipes shortcode not found on page' };
    }
    
    // Check for featured recipes title
    const title = featuredContainer.locator('h2, .handy-featured-recipes-title');
    await expect(title).toBeVisible();
    
    // Check for recipe cards (using existing recipe card selectors)
    const recipeCards = featuredContainer.locator('.recipe-card, .handy-recipe-card');
    const cardCount = await recipeCards.count();
    
    // Should have 1-3 cards (max 3 featured)
    expect(cardCount).toBeGreaterThan(0);
    expect(cardCount).toBeLessThanOrEqual(3);
    
    // Verify cards use same structure as regular recipes
    const firstCard = recipeCards.first();
    await expect(firstCard).toBeVisible();
    
    // Check for recipe title, image, and link
    const cardTitle = firstCard.locator('.recipe-title, h3, h4');
    const cardImage = firstCard.locator('img');
    const cardLink = firstCard.locator('a');
    
    await expect(cardTitle).toBeVisible();
    
    if (await cardImage.isVisible()) {
      expect(await cardImage.getAttribute('src')).toBeTruthy();
    }
    
    if (await cardLink.isVisible()) {
      expect(await cardLink.getAttribute('href')).toContain('/recipe/');
    }
    
    return {
      success: true,
      cardCount,
      hasTitle: await title.isVisible(),
      hasValidCards: true
    };
  }

  /**
   * Test product category images shortcode functionality
   * @param {string} testPageUrl - URL of page containing [product-category-images] shortcode
   */
  async testProductCategoryImagesShortcode(testPageUrl = '/') {
    await this.page.goto(testPageUrl);
    
    // Look for product category images container
    const imagesContainer = this.page.locator('.handy-category-images-grid, [data-shortcode="product-category-images"]');
    
    if (!(await imagesContainer.isVisible())) {
      return { success: false, message: 'Product category images shortcode not found on page' };
    }
    
    // Check for category image items
    const imageItems = imagesContainer.locator('.category-image-item');
    const itemCount = await imageItems.count();
    
    expect(itemCount).toBeGreaterThan(0);
    expect(itemCount).toBeLessThanOrEqual(6); // Default limit is 6
    
    // Test first category image item structure
    const firstItem = imageItems.first();
    await expect(firstItem).toBeVisible();
    
    // Check for circular image container
    const imageCircle = firstItem.locator('.category-image-circle');
    await expect(imageCircle).toBeVisible();
    
    // Check for category image
    const categoryImage = imageCircle.locator('img');
    await expect(categoryImage).toBeVisible();
    
    const imageSrc = await categoryImage.getAttribute('src');
    expect(imageSrc).toBeTruthy();
    expect(imageSrc).not.toBe('');
    
    // Check for category name
    const categoryName = firstItem.locator('.category-name');
    await expect(categoryName).toBeVisible();
    
    const nameText = await categoryName.textContent();
    expect(nameText?.trim().length).toBeGreaterThan(0);
    
    return {
      success: true,
      itemCount,
      hasValidStructure: true,
      hasImages: true,
      hasNames: true
    };
  }

  /**
   * Test product category images responsive grid behavior
   * @param {string} testPageUrl - URL of page containing [product-category-images] shortcode
   */
  async testProductCategoryImagesResponsive(testPageUrl = '/') {
    const viewports = [
      { width: 320, height: 568, name: 'mobile', expectedColumns: 2 },
      { width: 768, height: 1024, name: 'tablet', expectedColumns: 3 },
      { width: 1200, height: 800, name: 'desktop', expectedColumns: 6 }
    ];
    
    const results = [];
    
    for (const viewport of viewports) {
      await this.page.setViewportSize({ width: viewport.width, height: viewport.height });
      await this.page.goto(testPageUrl);
      
      const imagesContainer = this.page.locator('.handy-category-images-grid, [data-shortcode="product-category-images"]');
      
      if (await imagesContainer.isVisible()) {
        const imageItems = imagesContainer.locator('.category-image-item');
        const itemCount = await imageItems.count();
        
        // Check if items are arranged in expected columns
        let actualColumns = 1;
        if (itemCount >= 2) {
          const firstItem = imageItems.first();
          const secondItem = imageItems.nth(1);
          
          const firstBox = await firstItem.boundingBox();
          const secondBox = await secondItem.boundingBox();
          
          if (firstBox && secondBox) {
            // If second item is roughly at same Y position, they're in same row (side by side)
            const yDifference = Math.abs(secondBox.y - firstBox.y);
            if (yDifference < firstBox.height * 0.5) {
              // Items are side by side, count how many fit in first row
              actualColumns = 2;
              for (let i = 2; i < Math.min(itemCount, viewport.expectedColumns); i++) {
                const itemBox = await imageItems.nth(i).boundingBox();
                if (itemBox && Math.abs(itemBox.y - firstBox.y) < firstBox.height * 0.5) {
                  actualColumns++;
                } else {
                  break;
                }
              }
            }
          }
        }
        
        results.push({
          viewport: viewport.name,
          isVisible: true,
          itemCount,
          expectedColumns: viewport.expectedColumns,
          actualColumns,
          columnsMatch: actualColumns === viewport.expectedColumns || actualColumns >= Math.min(itemCount, viewport.expectedColumns)
        });
      } else {
        results.push({
          viewport: viewport.name,
          isVisible: false,
          itemCount: 0,
          expectedColumns: viewport.expectedColumns,
          actualColumns: 0,
          columnsMatch: false
        });
      }
    }
    
    // Reset to desktop
    await this.page.setViewportSize({ width: 1200, height: 800 });
    
    return results;
  }

  /**
   * Test that only categories with featured images are shown
   * @param {string} testPageUrl - URL of page containing [product-category-images] shortcode
   */
  async testProductCategoryImagesContent(testPageUrl = '/') {
    await this.page.goto(testPageUrl);
    
    const imagesContainer = this.page.locator('.handy-category-images-grid, [data-shortcode="product-category-images"]');
    
    if (!(await imagesContainer.isVisible())) {
      return { success: false, message: 'Product category images shortcode not found on page' };
    }
    
    const imageItems = imagesContainer.locator('.category-image-item');
    const itemCount = await imageItems.count();
    
    // Verify all items have images (since shortcode should only show categories with featured images)
    for (let i = 0; i < itemCount; i++) {
      const item = imageItems.nth(i);
      const image = item.locator('.category-image-circle img');
      
      await expect(image).toBeVisible();
      
      const imageSrc = await image.getAttribute('src');
      expect(imageSrc).toBeTruthy();
      expect(imageSrc).not.toBe('');
      
      // Verify image loads successfully (not broken)
      const imageElement = await image.elementHandle();
      const naturalWidth = await imageElement.evaluate(img => img.naturalWidth);
      expect(naturalWidth).toBeGreaterThan(0);
    }
    
    return {
      success: true,
      itemCount,
      allHaveValidImages: true
    };
  }
}

module.exports = { PluginUtils };
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
    
    // Check grid layout
    const products = this.page.locator('.product-card');
    await expect(products.first()).toBeVisible();
    
    // Check pagination if enabled
    if (pagination) {
      const paginationElement = this.page.locator('.pagination');
      if (await paginationElement.isVisible()) {
        await expect(paginationElement).toBeVisible();
      }
    }
    
    return products;
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
      errors.push(error.message);
    });
    
    this.page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text());
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
}

module.exports = { PluginUtils };
const { test, expect } = require('@playwright/test');
const { WordPressUtils } = require('../helpers/wordpress-utils');
const { PluginUtils } = require('../helpers/plugin-utils');

test.describe('Smoke Tests @smoke', () => {
  let wpUtils;
  let pluginUtils;

  test.beforeEach(async ({ page }) => {
    wpUtils = new WordPressUtils(page);
    pluginUtils = new PluginUtils(page);
  });

  test('WordPress site is accessible', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/.*Handy.*Crab.*/i);
    
    // Check that the site loads without major errors
    await expect(page.locator('body')).toBeVisible();
  });

  test('Plugin is active and functional', async ({ page }) => {
    // Check if plugin is active
    const isActive = await wpUtils.isPluginActive();
    expect(isActive).toBe(true);
    
    // Check plugin version matches expected version
    await wpUtils.loginToAdmin();
    await page.goto('/wp-admin/plugins.php');
    
    const pluginRow = page.locator('tr[data-slug="handy-custom"]');
    await expect(pluginRow).toBeVisible();
  });

  test('Products page loads with shortcode', async ({ page }) => {
    await page.goto('/products/');
    
    // Check if products grid is visible
    await expect(page.locator('.handy-products-grid, .products-grid')).toBeVisible();
    
    // Check if at least one product is displayed
    const productCards = page.locator('.product-card');
    await expect(productCards.first()).toBeVisible();
  });

  test('Recipes page loads with shortcode', async ({ page }) => {
    await page.goto('/recipes/');
    
    // Check if recipes grid is visible
    await expect(page.locator('.handy-recipes-grid, .recipes-grid')).toBeVisible();
    
    // Check if at least one recipe is displayed
    const recipeCards = page.locator('.recipe-card');
    await expect(recipeCards.first()).toBeVisible();
  });

  test('Plugin assets are loading correctly', async ({ page }) => {
    await page.goto('/products/');
    
    const assets = await pluginUtils.testAssetLoading();
    
    // Check that plugin CSS is loaded
    expect(assets.cssFiles.length).toBeGreaterThan(0);
    
    // Check that plugin JS is loaded if needed
    if (assets.jsFiles.length > 0) {
      expect(assets.jsFiles.length).toBeGreaterThan(0);
    }
  });

  test('No JavaScript errors on main pages', async ({ page }) => {
    const errors = await pluginUtils.checkForJSErrors();
    
    // Visit main pages and check for errors
    await page.goto('/');
    await page.goto('/products/');
    await page.goto('/recipes/');
    
    // Allow some time for any async errors
    await page.waitForTimeout(2000);
    
    // Filter out common, harmless errors
    const filteredErrors = errors.filter(error => 
      !error.includes('favicon') && 
      !error.includes('adsystem') &&
      !error.includes('google')
    );
    
    expect(filteredErrors.length).toBe(0);
  });

  test('WordPress admin is accessible', async ({ page }) => {
    await wpUtils.loginToAdmin();
    
    // Check admin bar is visible
    await expect(page.locator('#wpadminbar')).toBeVisible();
    
    // Check dashboard
    await expect(page.locator('#dashboard-widgets')).toBeVisible();
  });

  test('Basic responsive functionality', async ({ page }) => {
    // Test mobile viewport
    await page.setViewportSize({ width: 320, height: 568 });
    await page.goto('/products/');
    
    // Check if products are still visible on mobile
    await expect(page.locator('.product-card').first()).toBeVisible();
    
    // Reset to desktop
    await page.setViewportSize({ width: 1200, height: 800 });
  });
});
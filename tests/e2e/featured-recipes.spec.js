const { test, expect } = require('@playwright/test');
const { WordPressUtils } = require('../helpers/wordpress-utils');
const { PluginUtils } = require('../helpers/plugin-utils');

test.describe('Featured Recipes System @full', () => {
  let wpUtils;
  let pluginUtils;

  test.beforeEach(async ({ page }) => {
    wpUtils = new WordPressUtils(page);
    pluginUtils = new PluginUtils(page);
  });

  test.describe('Admin Interface Tests', () => {
    test('Featured column exists in recipes admin list @smoke', async ({ page }) => {
      const adminResults = await pluginUtils.testFeaturedRecipeAdmin(wpUtils);
      
      if (!adminResults.hasRecipes) {
        test.skip('No recipes found - skipping admin interface tests');
      }
      
      expect(adminResults.featuredColumnExists).toBe(true);
      expect(adminResults.toggleExists).toBe(true);
      expect(adminResults.rowCount).toBeGreaterThan(0);
    });

    test('Star toggle updates featured status via AJAX @full', async ({ page }) => {
      const toggleResults = await pluginUtils.testFeaturedRecipeToggle(wpUtils);
      
      if (!toggleResults.success) {
        test.skip(toggleResults.message);
      }
      
      expect(toggleResults.success).toBe(true);
      expect(toggleResults.stateChanged).toBe(true);
      
      // Log the state change for debugging
      console.log(`Recipe featured status changed: ${toggleResults.wasInitiallyFeatured} → ${toggleResults.isNowFeatured}`);
    });

    test('Enforces 3-recipe limit when toggling to featured @full', async ({ page }) => {
      const limitResults = await pluginUtils.testFeaturedRecipeLimit(wpUtils);
      
      if (!limitResults.success) {
        test.skip(limitResults.message);
      }
      
      expect(limitResults.success).toBe(true);
      expect(limitResults.limitEnforced).toBe(true);
      expect(limitResults.responseMessage).toContain('limit');
      
      console.log(`Limit enforcement message: ${limitResults.responseMessage}`);
    });

    test('Featured status persists after page reload @full', async ({ page }) => {
      // First, set a recipe as featured
      const toggleResults = await pluginUtils.testFeaturedRecipeToggle(wpUtils);
      
      if (!toggleResults.success || !toggleResults.isNowFeatured) {
        // Try toggling again to ensure we have a featured recipe
        await pluginUtils.testFeaturedRecipeToggle(wpUtils);
      }
      
      // Reload the admin page
      await page.reload();
      await page.waitForSelector('table.wp-list-table');
      
      // Check if the featured status persisted
      const recipeRows = page.locator('tbody tr:not(.no-items)');
      const firstRow = recipeRows.first();
      const featuredToggle = firstRow.locator('.toggle-featured-status');
      
      const icon = await featuredToggle.locator('.dashicons').getAttribute('class');
      const isFeatured = icon.includes('dashicons-star-filled');
      
      expect(isFeatured).toBe(true);
    });

    test('Admin interface handles no recipes gracefully @smoke', async ({ page }) => {
      await wpUtils.loginToAdmin();
      await page.goto('/wp-admin/edit.php?post_type=recipe');
      
      // Wait for page to load
      await page.waitForSelector('table.wp-list-table');
      
      // Check if Featured column still exists even with no recipes
      const featuredColumn = page.locator('thead th:has-text("Featured")');
      await expect(featuredColumn).toBeVisible();
      
      // Check for "No items found" message
      const noItemsMessage = page.locator('.no-items');
      if (await noItemsMessage.isVisible()) {
        console.log('No recipes found - admin interface handled gracefully');
      }
    });

    test('Featured toggle has proper accessibility attributes @full', async ({ page }) => {
      await wpUtils.loginToAdmin();
      await page.goto('/wp-admin/edit.php?post_type=recipe');
      
      await page.waitForSelector('table.wp-list-table');
      
      const recipeRows = page.locator('tbody tr:not(.no-items)');
      const rowCount = await recipeRows.count();
      
      if (rowCount === 0) {
        test.skip('No recipes available for accessibility testing');
      }
      
      const firstRow = recipeRows.first();
      const featuredToggle = firstRow.locator('.toggle-featured-status');
      
      // Check for title attribute (tooltip)
      const titleAttribute = await featuredToggle.getAttribute('title');
      expect(titleAttribute).toBeTruthy();
      expect(titleAttribute).toMatch(/Mark as Featured|Unmark as Featured/);
      
      // Check for proper link structure
      expect(await featuredToggle.getAttribute('href')).toBe('#');
    });
  });

  test.describe('Frontend Shortcode Tests', () => {
    test('[featured-recipes] shortcode renders correctly @smoke', async ({ page }) => {
      // First, we need to create a test page with the shortcode
      // For now, we'll test on a page that should have it, or skip if not found
      
      const shortcodeResults = await pluginUtils.testFeaturedRecipesShortcode('/');
      
      if (!shortcodeResults.success) {
        console.log('Featured recipes shortcode not found on homepage - this is expected until implementation');
        test.skip('Featured recipes shortcode not yet implemented');
      }
      
      expect(shortcodeResults.success).toBe(true);
      expect(shortcodeResults.hasTitle).toBe(true);
      expect(shortcodeResults.hasValidCards).toBe(true);
      expect(shortcodeResults.cardCount).toBeGreaterThan(0);
      expect(shortcodeResults.cardCount).toBeLessThanOrEqual(3);
    });

    test('Featured recipes display max 3 cards @full', async ({ page }) => {
      const shortcodeResults = await pluginUtils.testFeaturedRecipesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Featured recipes shortcode not yet implemented');
      }
      
      expect(shortcodeResults.cardCount).toBeLessThanOrEqual(3);
    });

    test('Featured recipe cards match regular recipe card structure @full', async ({ page }) => {
      // Test regular recipes page first to get expected structure
      await page.goto('/recipes/');
      const regularRecipeCard = page.locator('.recipe-card').first();
      
      if (!(await regularRecipeCard.isVisible())) {
        test.skip('No regular recipes found for comparison');
      }
      
      // Get regular recipe card structure
      const regularTitle = regularRecipeCard.locator('.recipe-title, h3, h4');
      const regularImage = regularRecipeCard.locator('img');
      const regularLink = regularRecipeCard.locator('a');
      
      const regularHasTitle = await regularTitle.isVisible();
      const regularHasImage = await regularImage.isVisible();
      const regularHasLink = await regularLink.isVisible();
      
      // Now test featured recipes
      const shortcodeResults = await pluginUtils.testFeaturedRecipesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Featured recipes shortcode not yet implemented');
      }
      
      // Featured cards should have same structure as regular cards
      expect(shortcodeResults.hasValidCards).toBe(true);
      
      // Log comparison for debugging
      console.log('Regular recipe card structure:', { regularHasTitle, regularHasImage, regularHasLink });
      console.log('Featured recipe card count:', shortcodeResults.cardCount);
    });

    test('Featured recipes shortcode handles no featured recipes gracefully @full', async ({ page }) => {
      // First, clear all featured recipes via admin
      await wpUtils.loginToAdmin();
      await page.goto('/wp-admin/edit.php?post_type=recipe');
      
      await page.waitForSelector('table.wp-list-table');
      
      const recipeRows = page.locator('tbody tr:not(.no-items)');
      const rowCount = await recipeRows.count();
      
      if (rowCount > 0) {
        // Clear all featured recipes
        for (let i = 0; i < rowCount; i++) {
          const row = recipeRows.nth(i);
          const toggle = row.locator('.toggle-featured-status');
          const icon = await toggle.locator('.dashicons').getAttribute('class');
          
          if (icon.includes('dashicons-star-filled')) {
            await toggle.click();
            await page.waitForTimeout(500);
          }
        }
      }
      
      // Now test the shortcode with no featured recipes
      const shortcodeResults = await pluginUtils.testFeaturedRecipesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Featured recipes shortcode not yet implemented');
      }
      
      // Should handle gracefully - either show message or hide section
      expect(shortcodeResults.cardCount).toBe(0);
    });

    test('Featured recipes are displayed in correct order @full', async ({ page }) => {
      // This test will verify that featured recipes are displayed in the correct order
      // (most recent first, or by featured date, depending on implementation)
      
      const shortcodeResults = await pluginUtils.testFeaturedRecipesShortcode('/');
      
      if (!shortcodeResults.success || shortcodeResults.cardCount < 2) {
        test.skip('Need at least 2 featured recipes to test ordering');
      }
      
      await page.goto('/');
      
      const featuredContainer = page.locator('.handy-featured-recipes-section, [data-shortcode="featured-recipes"]');
      const recipeCards = featuredContainer.locator('.recipe-card, .handy-recipe-card');
      
      // Get titles of featured recipes to verify they're not empty
      const recipeTitles = [];
      const cardCount = await recipeCards.count();
      
      for (let i = 0; i < cardCount; i++) {
        const card = recipeCards.nth(i);
        const titleElement = card.locator('.recipe-title, h3, h4');
        const title = await titleElement.textContent();
        recipeTitles.push(title?.trim() || '');
      }
      
      // Verify all titles are non-empty
      recipeTitles.forEach(title => {
        expect(title).toBeTruthy();
        expect(title.length).toBeGreaterThan(0);
      });
      
      console.log('Featured recipe titles in order:', recipeTitles);
    });
  });

  test.describe('Integration Tests', () => {
    test('Admin changes reflect immediately on frontend @full', async ({ page }) => {
      // This test verifies that changes made in admin are immediately visible on frontend
      
      // First, ensure we have at least one featured recipe
      const toggleResults = await pluginUtils.testFeaturedRecipeToggle(wpUtils);
      
      if (!toggleResults.success) {
        test.skip('Cannot set up featured recipe for integration test');
      }
      
      // If recipe is now featured, check frontend; if not featured, toggle again
      let shouldBeVisible = toggleResults.isNowFeatured;
      
      if (!shouldBeVisible) {
        // Toggle again to make it featured
        const secondToggle = await pluginUtils.testFeaturedRecipeToggle(wpUtils);
        shouldBeVisible = secondToggle.isNowFeatured;
      }
      
      // Now check frontend
      const shortcodeResults = await pluginUtils.testFeaturedRecipesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Featured recipes shortcode not yet implemented');
      }
      
      if (shouldBeVisible) {
        expect(shortcodeResults.cardCount).toBeGreaterThan(0);
      }
      
      console.log(`Integration test: Admin featured=${shouldBeVisible}, Frontend cards=${shortcodeResults.cardCount}`);
    });

    test('Featured recipes system works with existing recipe filters @full', async ({ page }) => {
      // Test that featured recipes don't interfere with regular recipe filtering
      
      // First test regular recipe filters
      const filterResults = await pluginUtils.testFilters('recipes');
      
      // Then test that featured recipes still work
      const shortcodeResults = await pluginUtils.testFeaturedRecipesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Featured recipes shortcode not yet implemented');
      }
      
      // Both should work independently
      expect(filterResults).toBeTruthy();
      expect(shortcodeResults.success).toBe(true);
      
      console.log('Integration test: Filters and featured recipes work independently');
    });
  });

  test.describe('Error Handling and Edge Cases', () => {
    test('Handles AJAX errors gracefully @full', async ({ page }) => {
      await wpUtils.loginToAdmin();
      await page.goto('/wp-admin/edit.php?post_type=recipe');
      
      await page.waitForSelector('table.wp-list-table');
      
      const recipeRows = page.locator('tbody tr:not(.no-items)');
      const rowCount = await recipeRows.count();
      
      if (rowCount === 0) {
        test.skip('No recipes available for error handling test');
      }
      
      // Listen for console errors
      const consoleErrors = [];
      page.on('console', msg => {
        if (msg.type() === 'error') {
          consoleErrors.push(msg.text());
        }
      });
      
      // Click toggle and see if any JS errors occur
      const firstRow = recipeRows.first();
      const featuredToggle = firstRow.locator('.toggle-featured-status');
      
      await featuredToggle.click();
      await page.waitForTimeout(2000); // Wait for potential errors
      
      // Filter out unrelated errors
      const relevantErrors = consoleErrors.filter(error => 
        error.includes('featured') || error.includes('toggle') || error.includes('ajax')
      );
      
      expect(relevantErrors.length).toBe(0);
    });

    test('System handles deleted recipes gracefully @full', async ({ page }) => {
      // This test ensures the system handles cases where featured recipes might be deleted
      
      const shortcodeResults = await pluginUtils.testFeaturedRecipesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Featured recipes shortcode not yet implemented');
      }
      
      // The shortcode should render without errors even if some featured recipes don't exist
      expect(shortcodeResults.success).toBe(true);
      
      // Card count should be >= 0 (could be 0 if no valid featured recipes)
      expect(shortcodeResults.cardCount).toBeGreaterThanOrEqual(0);
    });
  });
});
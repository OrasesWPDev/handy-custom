const { test, expect } = require('@playwright/test');
const { WordPressUtils } = require('../helpers/wordpress-utils');
const { PluginUtils } = require('../helpers/plugin-utils');

test.describe('Recipes Functionality @full', () => {
  let wpUtils;
  let pluginUtils;

  test.beforeEach(async ({ page }) => {
    wpUtils = new WordPressUtils(page);
    pluginUtils = new PluginUtils(page);
  });

  test('Recipes archive page displays correctly', async ({ page }) => {
    await page.goto('/recipes/');
    
    // Check page title
    await expect(page).toHaveTitle(/.*recipes.*/i);
    
    // Check recipes grid exists
    const recipesGrid = page.locator('.handy-recipes-grid, .recipes-grid');
    await expect(recipesGrid).toBeVisible();
    
    // Check that recipes are displayed
    const recipeCards = page.locator('.recipe-card');
    await expect(recipeCards.first()).toBeVisible();
    
    // Check recipe card content
    const firstCard = recipeCards.first();
    await expect(firstCard.locator('h3, .recipe-title')).toBeVisible();
    await expect(firstCard.locator('a')).toBeVisible();
  });

  test('Recipe filters work correctly', async ({ page }) => {
    await page.goto('/recipes/');
    
    // Test filter functionality
    const filtersContainer = await pluginUtils.testFilters('recipes');
    
    // Check if results updated after filtering
    const recipeCards = page.locator('.recipe-card');
    await expect(recipeCards.first()).toBeVisible();
  });

  test('Recipe pagination works', async ({ page }) => {
    await page.goto('/recipes/');
    
    // Check if pagination exists (if there are enough recipes)
    const pagination = page.locator('.pagination, .page-numbers');
    
    if (await pagination.isVisible()) {
      const nextButton = pagination.locator('.next, [aria-label="Next"]');
      
      if (await nextButton.isVisible()) {
        // Get current recipes
        const firstPageRecipes = await page.locator('.recipe-card h3').allTextContents();
        
        // Go to next page
        await nextButton.click();
        await page.waitForLoadState('networkidle');
        
        // Check that recipes changed
        const secondPageRecipes = await page.locator('.recipe-card h3').allTextContents();
        expect(firstPageRecipes).not.toEqual(secondPageRecipes);
      }
    }
  });

  test('Single recipe page loads correctly', async ({ page }) => {
    await page.goto('/recipes/');
    
    // Click on first recipe
    const firstRecipe = page.locator('.recipe-card a').first();
    const recipeUrl = await firstRecipe.getAttribute('href');
    
    await firstRecipe.click();
    await page.waitForLoadState('networkidle');
    
    // Check URL pattern
    expect(recipeUrl).toMatch(/\/recipe\/[^\/]+\//);
    
    // Check page content
    await expect(page.locator('h1')).toBeVisible();
    
    // Check breadcrumbs
    const breadcrumbs = page.locator('.breadcrumbs, .yoast-breadcrumb');
    if (await breadcrumbs.isVisible()) {
      await expect(breadcrumbs).toContainText('Recipes');
    }
    
    // Check recipe content area
    const recipeContent = page.locator('.recipe-content, .single-recipe-content, .entry-content');
    await expect(recipeContent).toBeVisible();
  });

  test('Recipe ingredients are displayed', async ({ page }) => {
    await page.goto('/recipes/');
    
    // Click on first recipe
    const firstRecipe = page.locator('.recipe-card a').first();
    await firstRecipe.click();
    await page.waitForLoadState('networkidle');
    
    // Look for ingredients section
    const ingredients = page.locator('.ingredients, .recipe-ingredients, [class*="ingredient"]');
    
    if (await ingredients.isVisible()) {
      await expect(ingredients).toBeVisible();
      
      // Check for ingredient list items
      const ingredientItems = ingredients.locator('li, .ingredient-item');
      if (await ingredientItems.first().isVisible()) {
        await expect(ingredientItems.first()).toBeVisible();
      }
    }
  });

  test('Recipe instructions are displayed', async ({ page }) => {
    await page.goto('/recipes/');
    
    // Click on first recipe
    const firstRecipe = page.locator('.recipe-card a').first();
    await firstRecipe.click();
    await page.waitForLoadState('networkidle');
    
    // Look for instructions section
    const instructions = page.locator('.instructions, .recipe-instructions, [class*="instruction"]');
    
    if (await instructions.isVisible()) {
      await expect(instructions).toBeVisible();
      
      // Check for instruction list items or steps
      const instructionItems = instructions.locator('li, .instruction-step, .step');
      if (await instructionItems.first().isVisible()) {
        await expect(instructionItems.first()).toBeVisible();
      }
    }
  });

  test('Recipe categories display correctly', async ({ page }) => {
    await page.goto('/recipes/');
    
    // Look for category filters or links
    const categoryElements = page.locator('.recipe-category, .category-filter, select[name*="category"] option');
    
    if (await categoryElements.first().isVisible()) {
      const categories = await categoryElements.allTextContents();
      expect(categories.length).toBeGreaterThan(1); // Should have at least one category plus "All"
    }
  });

  test('Recipe cooking method filters work', async ({ page }) => {
    await page.goto('/recipes/');
    
    // Look for cooking method filter
    const cookingMethodFilter = page.locator('select[name*="cooking"], select[name*="method"]');
    
    if (await cookingMethodFilter.isVisible()) {
      // Select a cooking method
      await cookingMethodFilter.selectOption({ index: 1 });
      
      // Wait for AJAX response
      await page.waitForResponse(response => 
        response.url().includes('wp-admin/admin-ajax.php') && response.status() === 200
      );
      
      // Check if results updated
      await page.waitForTimeout(1000);
      const recipeCards = page.locator('.recipe-card');
      await expect(recipeCards.first()).toBeVisible();
    }
  });

  test('Recipe prep time is displayed', async ({ page }) => {
    await page.goto('/recipes/');
    
    // Click on first recipe
    const firstRecipe = page.locator('.recipe-card a').first();
    await firstRecipe.click();
    await page.waitForLoadState('networkidle');
    
    // Look for prep time information
    const prepTime = page.locator('.prep-time, .recipe-prep-time, [class*="time"]');
    
    if (await prepTime.isVisible()) {
      await expect(prepTime).toBeVisible();
      
      // Check that it contains time information
      const timeText = await prepTime.textContent();
      expect(timeText).toMatch(/\d+/); // Should contain at least one number
    }
  });

  test('Recipe images load correctly', async ({ page }) => {
    await page.goto('/recipes/');
    
    // Check recipe images in archive
    const recipeImages = page.locator('.recipe-card img, .recipe-image img');
    
    if (await recipeImages.first().isVisible()) {
      const firstImage = recipeImages.first();
      
      // Check that image has src attribute
      const imageSrc = await firstImage.getAttribute('src');
      expect(imageSrc).toBeTruthy();
      
      // Check that image loads (not broken)
      const imageLoaded = await firstImage.evaluate(img => img.complete && img.naturalHeight !== 0);
      expect(imageLoaded).toBe(true);
    }
  });

  test('Recipe page is mobile responsive', async ({ page }) => {
    // Test mobile viewport
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/recipes/');
    
    // Check that recipes grid is still visible and usable
    const recipesGrid = page.locator('.handy-recipes-grid, .recipes-grid');
    await expect(recipesGrid).toBeVisible();
    
    // Check that recipe cards stack properly on mobile
    const recipeCards = page.locator('.recipe-card');
    if (await recipeCards.count() > 1) {
      const firstCard = recipeCards.first();
      const secondCard = recipeCards.nth(1);
      
      const firstCardBox = await firstCard.boundingBox();
      const secondCardBox = await secondCard.boundingBox();
      
      // On mobile, cards should stack vertically
      if (firstCardBox && secondCardBox) {
        expect(secondCardBox.y).toBeGreaterThan(firstCardBox.y + firstCardBox.height - 50);
      }
    }
    
    // Reset viewport
    await page.setViewportSize({ width: 1200, height: 800 });
  });

  test('Recipe nutritional information displays', async ({ page }) => {
    await page.goto('/recipes/');
    
    // Click on first recipe
    const firstRecipe = page.locator('.recipe-card a').first();
    await firstRecipe.click();
    await page.waitForLoadState('networkidle');
    
    // Look for nutritional information
    const nutrition = page.locator('.nutrition, .nutritional-info, [class*="nutrition"]');
    
    if (await nutrition.isVisible()) {
      await expect(nutrition).toBeVisible();
      
      // Check for common nutritional fields
      const calories = page.locator('[class*="calorie"], .calories');
      const protein = page.locator('[class*="protein"]');
      
      if (await calories.isVisible()) {
        await expect(calories).toBeVisible();
      }
      
      if (await protein.isVisible()) {
        await expect(protein).toBeVisible();
      }
    }
  });
});
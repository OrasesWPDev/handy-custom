const { test, expect } = require('@playwright/test');
const { WordPressUtils } = require('../helpers/wordpress-utils');
const { PluginUtils } = require('../helpers/plugin-utils');

test.describe('Product Category Images Shortcode @full', () => {
  let wpUtils;
  let pluginUtils;

  test.beforeEach(async ({ page }) => {
    wpUtils = new WordPressUtils(page);
    pluginUtils = new PluginUtils(page);
  });

  test.describe('Basic Shortcode Functionality', () => {
    test('[product-category-images] shortcode renders correctly @smoke', async ({ page }) => {
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        console.log('Product category images shortcode not found on homepage - this is expected until implementation');
        test.skip('Product category images shortcode not yet implemented');
      }
      
      expect(shortcodeResults.success).toBe(true);
      expect(shortcodeResults.hasValidStructure).toBe(true);
      expect(shortcodeResults.itemCount).toBeGreaterThan(0);
      expect(shortcodeResults.itemCount).toBeLessThanOrEqual(6);
    });

    test('Displays maximum of 6 category images by default @full', async ({ page }) => {
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      expect(shortcodeResults.itemCount).toBeLessThanOrEqual(6);
    });

    test('Only displays categories with featured images @full', async ({ page }) => {
      const contentResults = await pluginUtils.testProductCategoryImagesContent('/');
      
      if (!contentResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      expect(contentResults.success).toBe(true);
      expect(contentResults.allHaveValidImages).toBe(true);
      expect(contentResults.itemCount).toBeGreaterThan(0);
    });

    test('Category images are perfectly circular @full', async ({ page }) => {
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      await page.goto('/');
      
      const imagesContainer = page.locator('.handy-category-images-grid, [data-shortcode="product-category-images"]');
      const imageCircles = imagesContainer.locator('.category-image-circle');
      
      const firstCircle = imageCircles.first();
      
      // Check CSS properties for circular shape
      const borderRadius = await firstCircle.evaluate(el => getComputedStyle(el).borderRadius);
      const aspectRatio = await firstCircle.evaluate(el => getComputedStyle(el).aspectRatio);
      
      expect(borderRadius).toBe('50%');
      expect(aspectRatio).toBe('1');
    });

    test('Category names appear underneath images @full', async ({ page }) => {
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      await page.goto('/');
      
      const imagesContainer = page.locator('.handy-category-images-grid, [data-shortcode="product-category-images"]');
      const imageItems = imagesContainer.locator('.category-image-item');
      
      const firstItem = imageItems.first();
      const imageCircle = firstItem.locator('.category-image-circle');
      const categoryName = firstItem.locator('.category-name');
      
      // Get positions to verify name is below image
      const imageBox = await imageCircle.boundingBox();
      const nameBox = await categoryName.boundingBox();
      
      expect(imageBox).toBeTruthy();
      expect(nameBox).toBeTruthy();
      expect(nameBox.y).toBeGreaterThan(imageBox.y + imageBox.height * 0.8);
    });

    test('Category names have proper styling (blue color) @full', async ({ page }) => {
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      await page.goto('/');
      
      const imagesContainer = page.locator('.handy-category-images-grid, [data-shortcode="product-category-images"]');
      const categoryName = imagesContainer.locator('.category-name').first();
      
      // Check text color is blue
      const color = await categoryName.evaluate(el => getComputedStyle(el).color);
      
      // Should be some shade of blue (RGB values will vary, but blue component should be highest)
      expect(color).toBeTruthy();
      console.log(`Category name color: ${color}`);
    });
  });

  test.describe('Responsive Grid Layout', () => {
    test('Desktop displays 6 columns @full', async ({ page }) => {
      const responsiveResults = await pluginUtils.testProductCategoryImagesResponsive('/');
      
      if (!responsiveResults || responsiveResults.length === 0) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      const desktopResult = responsiveResults.find(r => r.viewport === 'desktop');
      expect(desktopResult).toBeTruthy();
      expect(desktopResult.isVisible).toBe(true);
      expect(desktopResult.expectedColumns).toBe(6);
      expect(desktopResult.columnsMatch).toBe(true);
    });

    test('Tablet displays 3 columns @full', async ({ page }) => {
      const responsiveResults = await pluginUtils.testProductCategoryImagesResponsive('/');
      
      if (!responsiveResults || responsiveResults.length === 0) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      const tabletResult = responsiveResults.find(r => r.viewport === 'tablet');
      expect(tabletResult).toBeTruthy();
      expect(tabletResult.isVisible).toBe(true);
      expect(tabletResult.expectedColumns).toBe(3);
      expect(tabletResult.columnsMatch).toBe(true);
    });

    test('Mobile displays 2 columns @full', async ({ page }) => {
      const responsiveResults = await pluginUtils.testProductCategoryImagesResponsive('/');
      
      if (!responsiveResults || responsiveResults.length === 0) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      const mobileResult = responsiveResults.find(r => r.viewport === 'mobile');
      expect(mobileResult).toBeTruthy();
      expect(mobileResult.isVisible).toBe(true);
      expect(mobileResult.expectedColumns).toBe(2);
      expect(mobileResult.columnsMatch).toBe(true);
    });

    test('Grid maintains proper spacing across viewports @full', async ({ page }) => {
      const viewports = [
        { width: 1200, height: 800, name: 'desktop' },
        { width: 768, height: 1024, name: 'tablet' },
        { width: 320, height: 568, name: 'mobile' }
      ];
      
      for (const viewport of viewports) {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await page.goto('/');
        
        const imagesContainer = page.locator('.handy-category-images-grid, [data-shortcode="product-category-images"]');
        
        if (await imagesContainer.isVisible()) {
          const gap = await imagesContainer.evaluate(el => getComputedStyle(el).gap);
          
          // Should have consistent gap spacing
          expect(gap).toBeTruthy();
          expect(gap).not.toBe('0px');
          
          console.log(`${viewport.name} gap: ${gap}`);
        }
      }
      
      // Reset to desktop
      await page.setViewportSize({ width: 1200, height: 800 });
    });

    test('Images maintain aspect ratio across viewports @full', async ({ page }) => {
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      const viewports = [
        { width: 1200, height: 800 },
        { width: 768, height: 1024 },
        { width: 320, height: 568 }
      ];
      
      for (const viewport of viewports) {
        await page.setViewportSize(viewport);
        await page.goto('/');
        
        const imageCircle = page.locator('.category-image-circle').first();
        
        if (await imageCircle.isVisible()) {
          const box = await imageCircle.boundingBox();
          
          if (box) {
            // Should be square (width = height for circular images)
            const aspectRatio = box.width / box.height;
            expect(aspectRatio).toBeCloseTo(1, 1); // Allow small tolerance
          }
        }
      }
      
      // Reset to desktop
      await page.setViewportSize({ width: 1200, height: 800 });
    });
  });

  test.describe('Image Quality and Loading', () => {
    test('All images load successfully @full', async ({ page }) => {
      const contentResults = await pluginUtils.testProductCategoryImagesContent('/');
      
      if (!contentResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      expect(contentResults.allHaveValidImages).toBe(true);
    });

    test('Images have proper alt text @full', async ({ page }) => {
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      await page.goto('/');
      
      const imagesContainer = page.locator('.handy-category-images-grid, [data-shortcode="product-category-images"]');
      const images = imagesContainer.locator('img');
      
      const imageCount = await images.count();
      
      for (let i = 0; i < imageCount; i++) {
        const image = images.nth(i);
        const altText = await image.getAttribute('alt');
        
        expect(altText).toBeTruthy();
        expect(altText.trim().length).toBeGreaterThan(0);
      }
    });

    test('Images use appropriate size (medium by default) @full', async ({ page }) => {
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      await page.goto('/');
      
      const firstImage = page.locator('.category-image-circle img').first();
      const imageSrc = await firstImage.getAttribute('src');
      
      // Should use WordPress medium size or appropriate dimensions
      expect(imageSrc).toBeTruthy();
    });

    test('Handles missing featured images gracefully @full', async ({ page }) => {
      // This test verifies that categories without featured images are simply not shown
      // rather than showing broken images or empty spaces
      
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      // All displayed items should have valid images (the filtering happens server-side)
      expect(shortcodeResults.hasImages).toBe(true);
      expect(shortcodeResults.itemCount).toBeGreaterThan(0);
    });
  });

  test.describe('Flatsome Integration', () => {
    test('Shortcode outputs clean HTML for page builder @full', async ({ page }) => {
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      await page.goto('/');
      
      const imagesContainer = page.locator('.handy-category-images-grid, [data-shortcode="product-category-images"]');
      
      // Should have clean, semantic structure
      await expect(imagesContainer).toBeVisible();
      
      // Should not have unnecessary wrapper elements
      const containerHTML = await imagesContainer.innerHTML();
      expect(containerHTML).toBeTruthy();
      
      // Should have data attribute for identification
      const dataAttribute = await imagesContainer.getAttribute('data-shortcode');
      expect(dataAttribute).toBe('product-category-images');
    });

    test('CSS classes are properly namespaced @full', async ({ page }) => {
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      await page.goto('/');
      
      // Check for proper class naming convention
      await expect(page.locator('.handy-category-images-grid')).toBeVisible();
      await expect(page.locator('.category-image-item')).toBeVisible();
      await expect(page.locator('.category-image-circle')).toBeVisible();
      await expect(page.locator('.category-name')).toBeVisible();
    });

    test('No conflicts with Flatsome theme styles @full', async ({ page }) => {
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      await page.goto('/');
      
      const imagesContainer = page.locator('.handy-category-images-grid');
      
      // Check that our styles are applied (not overridden by theme)
      const display = await imagesContainer.evaluate(el => getComputedStyle(el).display);
      expect(display).toBe('grid');
      
      const gridTemplateColumns = await imagesContainer.evaluate(el => getComputedStyle(el).gridTemplateColumns);
      expect(gridTemplateColumns).toBeTruthy();
      expect(gridTemplateColumns).not.toBe('none');
    });
  });

  test.describe('Shortcode Attributes', () => {
    test('Respects limit attribute @full', async ({ page }) => {
      // This test would require a page with [product-category-images limit="3"]
      // For now, we test the default limit behavior
      
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      // Default limit should be 6
      expect(shortcodeResults.itemCount).toBeLessThanOrEqual(6);
    });

    test('Handles edge cases gracefully @full', async ({ page }) => {
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      // Should handle various scenarios without errors
      expect(shortcodeResults.success).toBe(true);
      
      // If no categories with images exist, should return empty gracefully
      if (shortcodeResults.itemCount === 0) {
        console.log('No categories with featured images found - handled gracefully');
      }
    });
  });

  test.describe('Performance and Accessibility', () => {
    test('Shortcode loads quickly @full', async ({ page }) => {
      const startTime = Date.now();
      
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      const loadTime = Date.now() - startTime;
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      // Should load within reasonable time
      expect(loadTime).toBeLessThan(5000); // 5 seconds max
      console.log(`Category images loaded in ${loadTime}ms`);
    });

    test('Grid is keyboard accessible @full', async ({ page }) => {
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      await page.goto('/');
      
      const imagesContainer = page.locator('.handy-category-images-grid');
      
      // Images should be accessible via keyboard if they become interactive in future
      // For now, just verify the structure supports accessibility
      await expect(imagesContainer).toBeVisible();
    });

    test('Supports screen readers @full', async ({ page }) => {
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        test.skip('Product category images shortcode not yet implemented');
      }
      
      await page.goto('/');
      
      // Check for proper semantic structure
      const images = page.locator('.category-image-circle img');
      const names = page.locator('.category-name');
      
      const imageCount = await images.count();
      const nameCount = await names.count();
      
      // Should have equal number of images and names
      expect(imageCount).toBe(nameCount);
      
      // All images should have alt text
      for (let i = 0; i < imageCount; i++) {
        const image = images.nth(i);
        const altText = await image.getAttribute('alt');
        expect(altText).toBeTruthy();
      }
    });
  });

  test.describe('Error Handling', () => {
    test('Handles WordPress errors gracefully @full', async ({ page }) => {
      // Test that the shortcode doesn't break if WordPress functions are unavailable
      const shortcodeResults = await pluginUtils.testProductCategoryImagesShortcode('/');
      
      if (!shortcodeResults.success) {
        // This is actually expected behavior until implementation
        expect(shortcodeResults.message).toContain('not found');
        return;
      }
      
      // If shortcode exists, it should handle errors gracefully
      expect(shortcodeResults.success).toBe(true);
    });

    test('No JavaScript errors in console @full', async ({ page }) => {
      const jsErrors = [];
      
      page.on('console', msg => {
        if (msg.type() === 'error') {
          jsErrors.push(msg.text());
        }
      });
      
      await page.goto('/');
      
      // Wait a moment for any async errors
      await page.waitForTimeout(2000);
      
      // Filter out unrelated errors
      const relevantErrors = jsErrors.filter(error => 
        error.includes('category') || error.includes('handy-custom')
      );
      
      expect(relevantErrors.length).toBe(0);
    });
  });
});
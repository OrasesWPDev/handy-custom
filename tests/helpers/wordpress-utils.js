const { expect } = require('@playwright/test');

/**
 * WordPress utility functions for testing
 */
class WordPressUtils {
  constructor(page) {
    this.page = page;
    this.baseURL = 'http://localhost:10008';
  }

  /**
   * Login to WordPress admin
   * @param {string} username - WordPress username (default: admin)
   * @param {string} password - WordPress password (default: password)
   */
  async loginToAdmin(username = 'admin', password = 'password') {
    await this.page.goto('/wp-admin');
    
    // Check if already logged in
    if (await this.page.locator('#wpadminbar').isVisible()) {
      return;
    }

    await this.page.fill('#user_login', username);
    await this.page.fill('#user_pass', password);
    await this.page.click('#wp-submit');
    
    // Wait for admin dashboard
    await expect(this.page.locator('#wpadminbar')).toBeVisible();
  }

  /**
   * Logout from WordPress
   */
  async logout() {
    await this.page.goto('/wp-admin');
    await this.page.locator('#wp-admin-bar-my-account').hover();
    await this.page.click('#wp-admin-bar-logout a');
  }

  /**
   * Navigate to plugin settings page
   */
  async goToPluginSettings() {
    await this.loginToAdmin();
    await this.page.goto('/wp-admin/admin.php?page=handy-custom-settings');
  }

  /**
   * Navigate to a specific page by slug
   * @param {string} slug - Page slug
   */
  async goToPage(slug) {
    await this.page.goto(`/${slug}/`);
  }

  /**
   * Check if plugin is active
   */
  async isPluginActive() {
    await this.loginToAdmin();
    await this.page.goto('/wp-admin/plugins.php');
    
    const pluginRow = this.page.locator('tr[data-slug="handy-custom"]');
    const deactivateLink = pluginRow.locator('.deactivate a');
    
    return await deactivateLink.isVisible();
  }

  /**
   * Activate plugin
   */
  async activatePlugin() {
    await this.loginToAdmin();
    await this.page.goto('/wp-admin/plugins.php');
    
    const pluginRow = this.page.locator('tr[data-slug="handy-custom"]');
    const activateLink = pluginRow.locator('.activate a');
    
    if (await activateLink.isVisible()) {
      await activateLink.click();
      await expect(this.page.locator('.notice-success')).toBeVisible();
    }
  }

  /**
   * Check if shortcode is present on page
   * @param {string} shortcode - Shortcode to check for
   */
  async hasShortcode(shortcode) {
    const content = await this.page.content();
    return content.includes(shortcode);
  }

  /**
   * Wait for AJAX requests to complete
   */
  async waitForAjax() {
    await this.page.waitForFunction(() => {
      return window.jQuery && window.jQuery.active === 0;
    });
  }

  /**
   * Clear WordPress cache (if caching plugin is active)
   */
  async clearCache() {
    // This would depend on your caching setup
    // For now, just wait a moment
    await this.page.waitForTimeout(1000);
  }

  /**
   * Get current WordPress user info
   */
  async getCurrentUser() {
    return await this.page.evaluate(() => {
      if (window.wp && window.wp.data) {
        return window.wp.data.select('core').getCurrentUser();
      }
      return null;
    });
  }

  /**
   * Check if element is visible in viewport
   * @param {string} selector - CSS selector
   */
  async isInViewport(selector) {
    return await this.page.locator(selector).isInViewport();
  }

  /**
   * Scroll element into view
   * @param {string} selector - CSS selector
   */
  async scrollIntoView(selector) {
    await this.page.locator(selector).scrollIntoViewIfNeeded();
  }
}

module.exports = { WordPressUtils };
# Browser Testing Setup for Handy Custom Plugin

This document describes the automated browser testing setup for the Handy Custom WordPress plugin using Playwright and Local by WP Engine.

## Overview

The testing system provides:
- **Local-first testing** against your Local by WP Engine site
- **Automated deployment** from development to test environment
- **Cross-browser testing** (Chrome, Firefox, Safari)
- **WordPress-specific utilities** for common testing scenarios
- **Database management** for consistent test states

## Prerequisites

1. **Local by WP Engine** installed and running
2. **handy-crab site** set up and accessible at `http://localhost:10008`
3. **Node.js** (v16 or higher)
4. **Plugin development directory** at `/Users/chadmacbook/projects/handy-custom/`

## Installation

The testing framework is already configured. Dependencies are installed with:

```bash
npm install
```

## Directory Structure

```
/
├── package.json                    # Node.js dependencies and scripts
├── playwright.config.js            # Playwright configuration
├── scripts/                        # Automation scripts
│   ├── deploy-to-local.js          # Deploy plugin to Local site
│   ├── watch-and-deploy.js         # Auto-deploy on file changes
│   ├── update-version.js           # Update plugin version
│   └── reset-test-db.js            # Database management
├── tests/
│   ├── e2e/                        # End-to-end test files
│   │   ├── smoke.spec.js           # Basic smoke tests
│   │   ├── products.spec.js        # Product functionality tests
│   │   └── recipes.spec.js         # Recipe functionality tests
│   ├── helpers/                    # Testing utilities
│   │   ├── wordpress-utils.js      # WordPress-specific helpers
│   │   └── plugin-utils.js         # Plugin-specific helpers
│   ├── configs/                    # Test configurations
│   ├── data/                       # Test data and database backups
│   └── results/                    # Test reports and screenshots
```

## Available Commands

### Development Workflow

```bash
# Deploy plugin to Local site manually
npm run deploy:local

# Watch for changes and auto-deploy
npm run watch:deploy

# Update plugin version (all 3 locations)
npm run version:update -- 2.0.4
npm run version:update -- --increment patch
```

### Testing Commands

```bash
# Run smoke tests (quick validation)
npm run test:smoke

# Run full test suite
npm run test:full

# Run tests with browser visible (debugging)
npm run test:headed

# Run all tests
npm test
```

### Database Management

```bash
# Test database connection
npm run db:reset test

# Create a backup
npm run db:reset backup --name "clean-state"

# Restore from original Local SQL
npm run db:reset restore

# Restore from backup
npm run db:reset restore --source "clean-state"

# List available backups
npm run db:reset list
```

## Testing Workflow

### 1. Development Phase
```bash
# Start file watcher for auto-deployment
npm run watch:deploy

# Make changes to plugin files
# Files automatically deploy to Local site
```

### 2. Testing Phase
```bash
# Run smoke tests for basic validation
npm run test:smoke

# Run full test suite
npm run test:full

# Check test results in tests/results/
```

### 3. Release Preparation
```bash
# Update version number
npm run version:update -- --increment patch

# Deploy updated plugin
npm run deploy:local

# Run comprehensive tests
npm run test:full

# Create database backup of clean state
npm run db:reset backup --name "release-ready"

# Manual testing and validation
# If all tests pass, proceed with GitHub PR
```

## Test Categories

### Smoke Tests (`@smoke`)
- WordPress site accessibility
- Plugin activation status
- Basic shortcode functionality
- Asset loading
- No JavaScript errors

### Full Tests (`@full`)
- **Products**: Archive display, filtering, pagination, single pages, URL rewriting
- **Recipes**: Archive display, filtering, single pages, ingredients, instructions
- **Responsive**: Mobile/tablet/desktop compatibility
- **Cross-browser**: Chrome, Firefox, Safari compatibility

## Configuration

### Local Site Settings
Configure your Local by WP Engine site details in `package.json`:

```json
{
  "config": {
    "localSitePath": "/Users/chadmacbook/Local Sites/handy-crab/app/public/wp-content/plugins/handy-custom",
    "localSiteUrl": "http://localhost:10008",
    "testDataPath": "./tests/data"
  }
}
```

### Database Connection
Default MySQL settings for Local by WP Engine in `scripts/reset-test-db.js`:

```javascript
const CONFIG = {
  mysqlConfig: {
    host: 'localhost',
    port: '10004',        // Default Local MySQL port
    database: 'local',    // Default Local database name
    username: 'root',
    password: 'root'      // Default Local MySQL password
  }
};
```

## Writing Tests

### Basic Test Structure
```javascript
const { test, expect } = require('@playwright/test');
const { WordPressUtils } = require('../helpers/wordpress-utils');
const { PluginUtils } = require('../helpers/plugin-utils');

test.describe('My Feature Tests', () => {
  let wpUtils;
  let pluginUtils;

  test.beforeEach(async ({ page }) => {
    wpUtils = new WordPressUtils(page);
    pluginUtils = new PluginUtils(page);
  });

  test('My test case', async ({ page }) => {
    await page.goto('/my-page/');
    await expect(page.locator('.my-element')).toBeVisible();
  });
});
```

### Using Utilities
```javascript
// WordPress utilities
await wpUtils.loginToAdmin();
await wpUtils.goToPage('products');
await wpUtils.isPluginActive();

// Plugin utilities
await pluginUtils.testProductShortcode();
await pluginUtils.testFilters('products');
await pluginUtils.testSingleProduct('my-product-slug');
```

## Troubleshooting

### Common Issues

1. **Database connection fails**
   ```bash
   npm run db:reset test
   ```
   - Ensure Local by WP Engine is running
   - Check handy-crab site is started
   - Verify MySQL port in Local settings

2. **Tests fail with timeout**
   - Check if Local site is accessible at `http://localhost:10008`
   - Verify plugin is deployed and active
   - Check for JavaScript errors in browser console

3. **File deployment fails**
   ```bash
   npm run deploy:local -- --verbose --dry-run
   ```
   - Check target directory permissions
   - Verify Local site path is correct

4. **Version update fails**
   ```bash
   npm run version:update -- --dry-run --verbose
   ```
   - Check file permissions
   - Verify all version files exist

### Debugging Tests

1. **Run with visible browser**
   ```bash
   npm run test:headed
   ```

2. **Check test reports**
   - HTML report: `tests/results/html-report/index.html`
   - Screenshots: `tests/results/screenshots/`
   - Videos: `tests/results/videos/`

3. **Verbose output**
   ```bash
   npx playwright test --reporter=list --verbose
   ```

## Best Practices

1. **Always test locally first** before creating GitHub PRs
2. **Create database backups** before significant changes
3. **Run smoke tests** after any plugin modifications
4. **Use file watcher** during development for instant feedback
5. **Update version numbers** before release testing
6. **Document any test failures** and their resolutions

## Integration with Development Workflow

This testing system integrates with the existing plugin development workflow:

1. **Development** → Auto-deploy via file watcher
2. **Testing** → Comprehensive browser testing
3. **Version Management** → Automated version updates
4. **Database Management** → Consistent test states
5. **Release Preparation** → Manual validation before GitHub PR

The goal is to catch issues early and ensure only thoroughly tested code reaches production.
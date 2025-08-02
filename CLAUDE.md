# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Essential Commands

### Development Workflow
- `npm run watch:deploy` - Auto-deploy files to Local site on changes (development mode)
- `npm run deploy:local` - Manual deployment to Local test site
- `npm run version:update -- --increment patch` - Increment version and update all files

### Testing Commands
- `npm run test:smoke` - Quick smoke tests (@smoke tag) for rapid validation
- `npm run test:full` - Comprehensive test suite (@full tag) for complete validation
- `npm run test:e2e` - Run all Playwright tests
- `npm run test:headed` - Debug tests with visible browser

### Database Management
- `npm run db:reset backup --name "clean-state"` - Create database backup
- `npm run db:reset restore --source "clean-state"` - Restore from backup
- `npm run db:reset test` - Test database connection

## WordPress Plugin Architecture

This is a WordPress plugin for Handy Crab providing product and recipe management with custom URL structures and filtering systems.

### Core Architecture
```
Handy_Custom (Main Controller)
├── Single Post Templates (Product & Recipe custom URLs)
├── Shortcodes ([products], [recipes], [filter-products], [filter-recipes])
├── Base_Utils (Shared caching & utilities)
├── Products Module (Products_Utils, Products_Filters, Products_Display, Products_Renderer)
└── Recipes Module (Recipes_Utils, Recipes_Filters, Recipes_Display, Recipes_Renderer)
```

### Version Management (Critical)
**Always update ALL three version references simultaneously:**
1. `handy-custom.php` header: `* Version: X.X.X`
2. `HANDY_CUSTOM_VERSION` constant: `define('HANDY_CUSTOM_VERSION', 'X.X.X');`
3. `Handy_Custom::VERSION` constant: `const VERSION = 'X.X.X';`

Use `npm run version:update -- --increment patch` for automated updates.

### Custom URL System
- **Product URLs**: `/products/{category}/{product-slug}/` or `/products/{category}/{subcategory}/{product-slug}/`
- **Recipe URLs**: `/recipe/{recipe-slug}/` (standard WordPress structure)
- **Rewrite Rules**: Generated dynamically for published posts only
- **Primary Category Detection**: Uses Yoast SEO API with multiple fallbacks

### Post Types & Taxonomies
- **Post Types**: `product`, `recipe`
- **Product Taxonomies**: `product-category` (hierarchical), `grade`, `market-segment`, etc.
- **Recipe Taxonomies**: `recipe-category`, `recipe-cooking-method`, etc.
- **ACF Integration**: Extensive ACF field usage throughout

### Template System
- **Single Templates**: Custom templates in `/templates/product/single.php` and `/templates/recipe/single.php`
- **Shortcode Templates**: In `/templates/shortcodes/` for archive displays
- **Conditional Asset Loading**: CSS/JS only loaded when shortcodes present or on single posts

### AJAX Filtering System
- **Unified Filters**: `/includes/class-filters-renderer.php` handles both product and recipe filters
- **AJAX Handlers**: In shortcodes class with nonce verification
- **Cache System**: Two-tier caching with intelligent invalidation

## Local Development Environment

### Local by WP Engine Setup
- **Local Site URL**: http://localhost:10008
- **Plugin Path**: `/Users/chadmacbook/Local Sites/handy-crab/app/public/wp-content/plugins/handy-custom`
- **Database**: MySQL on port 10004

### File Deployment
Files are automatically deployed to the Local site via Node.js scripts:
- `scripts/deploy-to-local.js` - Manual deployment
- `scripts/watch-and-deploy.js` - Auto-deployment on file changes

## Browser Testing with Playwright

### Test Structure
- **Test Directory**: `/tests/e2e/`
- **Helper Functions**: `/tests/helpers/` (plugin-utils.js, wordpress-utils.js)
- **Configuration**: `playwright.config.js` (Chrome, Firefox, Safari)
- **Base URL**: http://localhost:10008

### Test Tags
- `@smoke` - Quick validation tests
- `@full` - Comprehensive test suite

### Test Coverage
- Cross-browser testing (Chrome, Firefox, Safari)
- Responsive testing (mobile, tablet, desktop)
- WordPress integration (login, admin functions)
- Plugin functionality (shortcodes, filtering, single pages)

## Security & Performance Constraints

### Database Access
- **READ ONLY SQL queries** - never modify data through direct SQL
- All database access must remain read-only for security

### WordPress Security
- **AJAX Nonces**: All AJAX requests use WordPress nonce verification
- **Capability Checks**: Admin functionality requires appropriate WordPress capabilities
- **Input Sanitization**: All user inputs sanitized using WordPress functions

### Performance Optimizations
- **Conditional Loading**: Assets only load when shortcodes present or on single posts
- **Query Optimization**: Efficient database queries with proper caching
- **Template Caching**: Smart template loading and caching system

## Development Workflow

### Pre-Release Process
1. Run comprehensive tests (`npm run test:full`)
2. Manual validation on localhost:10008
3. Verify version numbers updated consistently
4. Create database backup of clean state
5. Test end-to-end functionality

### Release Management
- Work on feature branches
- Update plugin version before completing work
- Push to GitHub and create PR when complete
- Switch to main, pull from GitHub, confirm PR merged
- Delete all non-main branches after confirmation

### GitHub Integration
- **Auto-updater**: Uses YahnisElsts library for GitHub-based updates
- **Version Matching**: Plugin version must exactly match GitHub release tag
- **Update Notifications**: Automatic detection within 1 minute of new releases

## File Structure Guidelines

### Size Constraints
- Keep files under 600 lines when possible
- Follow WordPress coding standards
- Maintain modular architecture with separated concerns

### Asset Organization
```
assets/
├── css/ (Feature-specific stylesheets)
│   ├── products/ (single-product.css, archive.css)
│   ├── recipes/ (single-recipe.css, archive.css)
│   └── filters.css (unified filtering styles)
├── js/ (Feature-specific scripts)
│   ├── products/ (archive.js, single-product.js)
│   ├── recipes/ (archive.js, single-recipe.js)
│   ├── shared/ (card-equalizer.js)
│   └── filters.js (unified filtering)
└── images/ (Plugin-specific images)
```

## Debugging & Logging

### Debug Mode
- Set `HANDY_CUSTOM_DEBUG` to `true` in `handy-custom.php`
- Logs written to `/logs/` directory (auto-created)
- Comprehensive logging throughout core functionality

### Common Debug Areas
- URL rewrite rule generation
- Primary category detection
- AJAX filtering requests
- Template loading
- Cache invalidation
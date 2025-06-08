# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## WordPress Plugin Architecture

This is a WordPress plugin providing product and recipe archive functionality with shortcode support and AJAX filtering.

### Core Components

- **Main Plugin Class**: `Handy_Custom` - singleton pattern, handles initialization, asset loading, and URL rewriting
- **Admin Class**: `Handy_Custom_Admin` - WordPress admin functionality including taxonomy dropdown filters for product management
- **Base Utils**: `Handy_Custom_Base_Utils` - abstract base class with shared caching and taxonomy utilities
- **Shortcodes**: `Handy_Custom_Shortcodes` - handles `[products]` and `[recipes]` shortcodes with AJAX filtering
- **Logger**: `Handy_Custom_Logger` - centralized logging system (controlled by `HANDY_CUSTOM_DEBUG` constant)

### Plugin Structure

Each content type (products/recipes) has its own namespace with four classes:
- `*_Utils` - extends base utils, handles taxonomy mappings and data retrieval
- `*_Filters` - processes filter parameters and builds WP_Query arguments  
- `*_Display` - formatting and display helper functions
- `*_Renderer` - orchestrates the complete rendering process using templates

### Asset Loading Strategy

Assets are loaded conditionally based on shortcode presence:
- Products: `/assets/css/products/archive.css` + `/assets/js/products/archive.js`
- Recipes: `/assets/css/recipes/archive.css` + `/assets/js/recipes/archive.js`  
- Legacy support: `/assets/css/custom.css` + `/assets/js/custom.js`

### Products Shortcode Display Modes

The `[products]` shortcode supports two display modes:

**Categories Mode (Default):**
- Parameter: `display="categories"` or no display parameter
- Shows only top-level category cards in custom order (crab, shrimp, appetizers, dietary alternatives)
- Category filter excluded from filter options
- Use case: Main product browsing page

**List Mode:**
- Parameter: `display="list"`
- Shows individual product posts alphabetically by title
- Includes category filter in filter options
- 3-column responsive layout (desktop), 2-column (tablet), 1-column (mobile)
- Product cards include: thumbnail (medium size), title, 150-char excerpt, arrow link
- Default pagination: 12 products per page (prevents performance issues)
- Use case: Product catalog page

### Pagination Support (v1.5.0+)

Shortcodes support `per_page` and `page` parameters. List mode defaults to 12 items per page for performance.

### URL Rewriting System

Supports SEO-friendly URLs for products:
- `/products/{category}/` - category page
- `/products/{category}/{subcategory}/` - subcategory page

URL parameters are automatically merged with shortcode attributes, with URL taking precedence.

### Caching System

**Multi-tier caching architecture for optimal performance:**

**Taxonomy Terms Caching:**
1. WordPress object cache (1 hour TTL) with `handy_custom_terms` cache group
2. Static class-level cache for request duration
3. Automatic cache invalidation when terms are updated

**Query Result Caching (v1.5.0+):**
1. Filtered query results cached with 30-minute TTL using `handy_custom_queries` cache group
2. Intelligent cache key generation based on query parameters
3. Cache size limits (skip caching for >200 posts) to prevent memory issues
4. Automatic invalidation when posts or relevant terms are updated
5. Proper WP_Query object reconstruction from cached data

**Cache Groups:**
- `handy_custom_terms` - Taxonomy term data
- `handy_custom_queries` - Query result data

**Cache Invalidation Hooks:**
- Post updates: `save_post`, `delete_post`, `publish_post`, `trash_post`
- Term updates: `created_term`, `edited_term`, `deleted_term`

### Template System

Templates located in `/templates/shortcodes/{type}/archive.php` with these variables:
- `$filters` - current filter values
- `$categories` - category terms to display (categories mode)
- `$products` - WP_Query object with product posts (list mode)
- `$display_mode` - display mode: 'categories' or 'list'
- `$filter_options` - available filter options (includes category filter only in list mode)
- `$subcategory_context` - subcategory context (products only)

### Admin Functionality

**Product Filtering**: The plugin includes comprehensive admin filtering functionality for the Products custom post type:
- **Taxonomy Dropdown Filters**: All product taxonomies available as dropdown filters in admin listing
- **Supported Taxonomies**: Category, grade, market segment, cooking method, menu occasion, product type, size, species, brand, certification
- **Filter Integration**: Uses `restrict_manage_posts` and `parse_query` hooks for efficient filtering
- **Term Counts**: Dropdowns show term counts for better admin UX
- **Multi-filter Support**: Combine multiple taxonomy filters with AND logic

### Debug/Logging

Set `HANDY_CUSTOM_DEBUG = true` in main plugin file to enable file-based logging to `/logs/` directory.

**Performance Monitoring (v1.5.0+):**
- Cache hit/miss logging for both term and query caches
- Query execution logging with pagination details
- Cache invalidation event logging
- Memory usage tracking in cache statistics
- Large result set detection and handling
- Cache group flush status monitoring

**Cache Statistics Available:**
```php
$stats = Handy_Custom_Base_Utils::get_cache_stats();
// Returns: term_cache_count, term_exists_cache_count, memory_usage
```

### Responsive Breakpoints

Standardized breakpoints used throughout the codebase:
- **Desktop**: 850px and above (no media query)
- **Tablet**: 550px to 849px (`@media (max-width: 849px)`)
- **Mobile**: 549px and below (`@media (max-width: 549px)`)

### URL Parameter System

AJAX filtering automatically updates URLs with query parameters:
- Example: `/products/?category=crab&grade=premium`
- Example: `/products/?display=list&category=shrimp&cooking_method=baking`
- JavaScript handles URL updates via `window.history.pushState()`
- Supports deep linking and shareable filtered states
- Browser back/forward navigation preserved

## Data Import System

### Product Import (One-time Use)

**Location**: Root directory scripts for initial data migration
- `import-products.php` - Main CSV import script
- `test-import.php` - Pre-import validation and testing
- `IMPORT_README.md` - Comprehensive documentation

**Purpose**: One-time import of product data from CSV exports. After import completion, these scripts can be safely removed.

**Field Mapping**: Comprehensive mapping system between CSV columns and WordPress:
- Core fields: `product_title` → post_title, `description` → post_content
- ACF fields: All custom fields mapped (sub_header, item_number, gtin_code, upc_number, etc.)
- Taxonomies: Comma-separated CSV values mapped to existing taxonomy terms
- Auto-categorization: Products automatically assigned to categories based on title/description analysis

**Safety Features**:
- Duplicate prevention (by title, item number, UPC)
- Data validation (required fields, format checking)
- Import as drafts for review
- Comprehensive reporting system
- ACF plugin dependency checking

**Reports Generated**: Detailed JSON reports in `import-reports/` directory:
- Successful imports with complete field mapping
- Failed taxonomy mappings with reasons
- Validation errors and duplicate skips
- Summary statistics

**Note**: A similar import script will be created for recipes when needed. Import scripts are designed for one-time use during initial setup and can be removed after successful import.

## Development Notes

- Plugin version is defined in two places: main file header and class constant
- All classes use strict security checks (`ABSPATH` validation)
- AJAX handlers include nonce verification
- Error handling with try/catch blocks and proper logging
- Subcategory filtering includes automatic parent category detection
- Import scripts include memory management and execution time controls for large datasets
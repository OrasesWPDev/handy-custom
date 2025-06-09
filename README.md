# Handy Custom WordPress Plugin

![WordPress](https://img.shields.io/badge/WordPress-5.3%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.2%2B-purple.svg)
![Version](https://img.shields.io/badge/version-1.6.4-green.svg)
![License](https://img.shields.io/badge/license-GPL--2.0-orange.svg)

A powerful WordPress plugin providing advanced product and recipe archive functionality with AJAX filtering, SEO-friendly URLs, and hierarchical category management.

## 🚀 Overview

Handy Custom transforms your WordPress site's product and recipe displays into dynamic, filterable archives. Perfect for e-commerce sites, recipe blogs, and catalog presentations that need sophisticated filtering without page reloads.

### Key Problems Solved
- **Static product displays** → Dynamic AJAX-powered filtering
- **Poor SEO URLs** → Clean, hierarchical URL structures (`/products/crab/crab-cakes/`)
- **Performance issues** → Built-in caching and optimized asset loading
- **Complex category relationships** → Automatic parent/child category detection

## ✨ Features

### 🛍️ Products Module
- **Advanced Shortcode**: `[products]` with 8 filter parameters
- **SEO-Friendly URLs**: `/products/{category}/{subcategory}/` structure
- **Smart Category Detection**: Automatic parent category resolution for subcategories
- **Rich Product Cards**: Featured images, descriptions, action buttons
- **Comprehensive Filtering**:
  - Categories & Subcategories
  - Grades & Market Segments
  - Cooking Methods & Menu Occasions
  - Product Types & Sizes

### 🍳 Recipes Module
- **Recipe Shortcode**: `[recipes]` with category and method filtering
- **Recipe Cards**: Prep time, servings, and allergen information
- **ACF Integration**: Custom fields for enhanced recipe metadata
- **Allergen Support**: Built-in icons for common allergens

### ⚡ Technical Features
- **AJAX Filtering**: Real-time updates without page reloads
- **Performance Optimized**: Two-tier caching system for taxonomy terms
- **Conditional Asset Loading**: CSS/JS only loaded when needed
- **Debug Logging**: Comprehensive logging system for troubleshooting
- **Template System**: Customizable templates for complete control
- **Data Import Tools**: One-time CSV import scripts for initial product data migration
- **Admin Filtering**: Advanced taxonomy dropdown filters in WordPress admin for efficient product management

## 📋 Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 5.3+ |
| PHP | 7.2+ |
| Advanced Custom Fields | Latest |

### Optional Dependencies
- Custom post types: `product`, `recipe`
- Custom taxonomies for advanced filtering
- ACF fields for featured images and recipe metadata

## 🔧 Installation

1. **Upload the plugin**
   ```bash
   # Upload to your WordPress plugins directory
   /wp-content/plugins/handy-custom/
   ```

2. **Activate the plugin**
   - Go to WordPress Admin → Plugins
   - Activate "Handy Custom"

3. **Install dependencies**
   - Install and activate Advanced Custom Fields (ACF)

4. **Configure taxonomies** (if not already set up)
   - Product taxonomies: `product-category`, `grade`, `market-segment`, etc.
   - Recipe taxonomies: `recipe-category`, `recipe-cooking-method`, etc.

5. **Flush permalinks**
   - Go to Settings → Permalinks
   - Click "Save Changes" to enable custom URL routing

## 🎯 Usage

### Basic Shortcodes

```php
// Display all products
[products]

// Display all recipes
[recipes]

// Display product filters (new in v1.6.0)
[filter-products]

// Display recipe filters (new in v1.6.0)
[filter-recipes]
```

### New Filter Shortcodes (v1.6.0)

The plugin now includes dedicated filter shortcodes that provide dynamic, standalone filtering controls. These work independently from content shortcodes and use URL parameters for state management.

```php
// Basic filter usage
[filter-products]                                    // Show all product taxonomy filters
[filter-recipes]                                     // Show all recipe taxonomy filters

// Show only specific taxonomies
[filter-products display="grade,market_segment"]     // Show only grade and market segment filters
[filter-recipes display="category,cooking_method"]   // Show only category and cooking method filters

// Exclude specific taxonomies  
[filter-products exclude="size,product_type"]        // Show all except size and product type
[filter-recipes exclude="difficulty"]               // Show all except difficulty filter
```

**Key Benefits:**
- **Dynamic Updates**: Filters automatically show new terms when added to taxonomies
- **URL State Management**: Filter selections persist in URL for bookmarking and sharing
- **Standalone Design**: Use filters anywhere on the page, independent of content shortcodes
- **Real-time Interaction**: Filter changes instantly update URL parameters
- **Cross-Shortcode Communication**: Filter selections affect matching content shortcodes via URL parameters

**Typical Usage Pattern:**
```php
// On a products page
[filter-products display="grade,market_segment,cooking_method"]
[products display="list"]

// On a recipes page  
[filter-recipes]
[recipes]
```

### Advanced Product Filtering

```php
// Display top-level categories (default)
[products]

// Display all products alphabetically (product catalog mode)
[products display="list"]

// Filter by category in list mode
[products display="list" category="crab"]

// Filter by subcategory (auto-detects parent)
[products subcategory="crab-cakes"]

// Multiple filters in list mode
[products display="list" category="shrimp" grade="premium" cooking_method="frying"]

// Market-specific filtering
[products display="list" market_segment="retail" product_type="appetizers"]
```

### Recipe Filtering

```php
// Filter by category
[recipes category="desserts"]

// Filter by cooking method
[recipes cooking_method="baking"]

// Combined filters
[recipes category="main-course" menu_occasion="dinner"]
```

### Pagination Support (v1.5.0+)

Both products and recipes shortcodes support pagination to improve performance with large datasets.

**Shortcode Parameters:**
- `per_page` - Number of items per page (default: 12 for list mode, unlimited for categories mode)
- `page` - Current page number (default: 1)

**Examples:**
```php
// Display 24 products per page
[products display="list" per_page="24" page="1"]

// Display 16 recipes per page, showing page 2
[recipes per_page="16" page="2"]

// Combine with filters
[products display="list" category="crab" per_page="12" page="1"]
```

**Safety Features:**
- Maximum `per_page` limit: 100 (prevents abuse)
- Minimum `page` number: 1 (prevents invalid pagination)
- Automatic pagination for list mode (prevents runaway queries)
- Large result set protection (caching skipped for >200 posts)

**AJAX Compatibility:**
- Pagination state maintained during AJAX filtering
- URL parameters updated to reflect current page
- Cache-aware pagination for optimal performance

### SEO-Friendly URLs

The plugin automatically supports clean URLs:

```
/products/                          → Main products page
/products/crab/                     → Crab category
/products/crab/crab-cakes/          → Crab cakes subcategory
/products/appetizers/specialty/     → Specialty appetizers
```

URL parameters automatically merge with shortcode attributes.

## ⚙️ Configuration

### ACF Field Setup

Create these custom fields for enhanced functionality:

```php
// For category terms
'category_featured_image' // Image field for category cards

// For recipe posts  
'prep_time'              // Number field (minutes)
'servings'               // Number field
```

### Asset Customization

Add category icons to enhance visual appeal:
```
/assets/images/{category-slug}-icon.png
```

Example: `crab-icon.png`, `shrimp-icon.png`, `appetizers-icon.png`

### Debug Logging

Enable detailed logging for troubleshooting:

```php
// In handy-custom.php
define('HANDY_CUSTOM_DEBUG', true);
```

Logs are stored in `/logs/` directory (automatically secured).

### Product Categories & Subcategories

**Main Categories:**
- **Shrimp** (`shrimp`) - Premium shrimp products
- **Crab** (`crab`) - Fresh and processed crab products  
- **Appetizers** (`appetizers`) - Ready-to-serve appetizer items
- **Dietary Alternatives** (`dietary-alternatives`) - Specialized dietary options

**Subcategories by Parent:**

*Crab Products:*
- Crab Cakes (`crab-cakes`)
- Crab Meat (`crab-meat`) 
- Soft Shell Crab (`soft-shell-crab`)

*Appetizers:*
- Crab Cake Minis (`crab-cake-minis`)
- Specialty (`specialty`)
- Soft Crab (`soft-crab`)
- Shrimp (`appetizer-shrimp`)

*Dietary Alternatives:*
- Gluten Free (`gluten-free`)
- Keto Friendly (`keto-friendly`)
- Plant Based (`plant-based`)

### Display Modes

The `[products]` shortcode supports two display modes:

| Mode | Parameter | Description | Use Case |
|------|-----------|-------------|----------|
| **Categories** | `display="categories"` (default) | Shows top-level category cards only | Main product browsing page |
| **List** | `display="list"` | Shows individual products alphabetically | Product catalog page |

**Key Differences:**
- **Categories mode**: No category filter, shows 4 top-level categories in specified order
- **List mode**: Includes category filter, shows all products with thumbnails and excerpts

### Filter Parameters Reference

| Parameter | Taxonomy | Description | Available In |
|-----------|----------|-------------|--------------|
| `display` | - | Display mode: 'categories' or 'list' | Both modes |
| `category` | `product-category` | Main product categories | List mode only |
| `subcategory` | `product-category` | Child categories | Both modes |
| `grade` | `grade` | Product quality grades | Both modes |
| `market_segment` | `market-segment` | Target markets | Both modes |
| `cooking_method` | `product-cooking-method` | Cooking methods | Both modes |
| `menu_occasion` | `product-menu-occasion` | Meal occasions | Both modes |
| `product_type` | `product-type` | Product classifications | Both modes |
| `size` | `size` | Product sizes | Both modes |

## 🛠️ Development

### Architecture Overview

```
Handy_Custom (Main Controller)
├── Shortcodes (AJAX handlers)
├── Base_Utils (Shared caching & utilities)
├── Products Module
│   ├── Utils (URL handling, taxonomy mapping)
│   ├── Filters (Query building)
│   ├── Display (UI helpers)
│   └── Renderer (Template orchestration)
└── Recipes Module
    ├── Utils (Formatting, icons)
    ├── Filters (Recipe filtering)
    ├── Display (Card generation)
    └── Renderer (Template rendering)
```

### Extending the Plugin

#### Adding Custom Filters

```php
// Extend the taxonomy mapping
add_filter('handy_custom_product_taxonomies', function($taxonomies) {
    $taxonomies['custom_field'] = 'custom-taxonomy';
    return $taxonomies;
});
```

#### Custom Templates

Override templates by copying to your theme:
```
/wp-content/themes/your-theme/handy-custom/
    └── shortcodes/
        ├── products/archive.php
        └── recipes/archive.php
```

### Performance Optimization

The plugin includes several performance features:

- **Term Caching**: Two-tier caching (WordPress object cache + static cache)
- **Conditional Loading**: Assets only load on pages with shortcodes
- **Query Optimization**: Efficient database queries with proper indexing

### Debugging

Monitor plugin behavior with detailed logging:

```php
// Check cache statistics
$stats = Handy_Custom_Base_Utils::get_cache_stats();

// Clear term cache
Handy_Custom_Base_Utils::clear_term_cache('product-category');
```

## 📥 Data Import

For initial data migration, the plugin includes comprehensive CSV import tools:

- **Product Import**: `import-products.php` - One-time import script for product data
- **Testing**: `test-import.php` - Validates setup before running import
- **Documentation**: `IMPORT_README.md` - Complete import guide with troubleshooting

### Quick Import Guide

1. **Backup your database** before running any import
2. **Test setup**: Run `php test-import.php` to validate configuration
3. **Run import**: Execute `php import-products.php` from WordPress root
4. **Review reports**: Check generated reports in `import-reports/` directory

See [IMPORT_README.md](IMPORT_README.md) for detailed instructions and field mapping information.

**Note**: Import scripts are designed for one-time use during initial setup. A similar script will be available for recipe imports.

## 🔍 Troubleshooting

### Common Issues

**Filters not working?**
- Verify ACF is installed and active
- Check that taxonomies exist and have terms
- Enable debug logging to see detailed error messages

**Custom URLs not working?**
- Flush permalinks in WordPress admin
- Verify your server supports URL rewriting

**AJAX requests failing?**
- Check browser console for JavaScript errors
- Verify nonce security tokens are valid
- Ensure proper AJAX URL configuration

**Performance issues?**
- Enable term caching
- Check for conflicting plugins
- Monitor database queries with debug logging

**Import issues?**
- Ensure ACF is active before running import
- Check CSV file format and location
- Review generated error reports for specific issues

## 📝 Changelog

### Version 1.6.4 (Latest)
- **Direct Single Product URLs**: Single product URLs like `/products/appetizers/ultimate-mini-crab-cakes/` now serve content directly without redirecting
- **Enhanced URL Structure**: Products maintain clean category-based URLs while displaying proper single product content
- **Improved Yoast Integration**: Better breadcrumb support for single product pages on clean URLs
- **WordPress Query Optimization**: Proper query variable setup for single product display on custom URLs
- **Template System Enhancement**: Single products use WordPress's native template system while preserving clean URLs
- **Category Context Preservation**: Single product pages maintain category context for navigation and breadcrumbs

### Version 1.6.3
- **Fixed Page Editing Interference**: Removed plugin interference with WordPress page editing functionality
- **Enhanced Admin Context Handling**: Added proper admin context checks to prevent template_redirect issues
- **Single Product URL Support**: Implemented `/products/{category}/{product-slug}/` URL structure for individual products
- **Improved UX Builder Compatibility**: Category pages can now be edited freely without plugin interference
- **Yoast Breadcrumb Integration**: Ensured compatibility with `[wpseo_breadcrumb]` shortcode on all pages
- **Template Override Fixes**: Removed code preventing basic page editing via Flatsome UX Builder
- **URL Rewrite Improvements**: Added proper single product URL handling with category validation

### Version 1.6.2
- **Fixed URL Override Issue**: Fixed shortcode override forcing on category pages without shortcodes
- **Enhanced UX Builder Compatibility**: Category pages can now be edited freely with Flatsome UX Builder
- **Conditional URL Handling**: URL parameters only apply when product shortcodes are present on page
- **Improved Template Logic**: Template override now checks for shortcode presence before activation
- **Page Editing Freedom**: `/products/{category}/{subcategory}/` pages no longer locked to shortcode behavior

### Version 1.6.1
- **Bug Fixes**: Minor stability improvements and performance optimizations

### Version 1.6.0
- **New Filter Shortcodes**: Introduced dedicated `[filter-products]` and `[filter-recipes]` shortcodes for standalone filtering controls
- **Unified Filter System**: Created consolidated filter rendering with single CSS/JS files for all filter functionality
- **Dynamic Filter Updates**: Filters automatically show new terms when added to taxonomies and hide deleted terms
- **URL State Management**: Filter selections persist in URL parameters for bookmarking and sharing
- **Cross-Shortcode Communication**: Filter shortcodes communicate with content shortcodes via URL parameters
- **Modular Design**: Filters can be placed anywhere on page, independent of content shortcodes
- **Comprehensive Logging**: Added extensive debug logging throughout filter system for troubleshooting
- **CSS Cleanup**: Removed obsolete filter styles from product and recipe CSS files to prevent conflicts
- **Performance Optimization**: Filter assets only load when filter shortcodes are present on page
- **Responsive Design**: Unified filter styles include full responsive support and dark mode compatibility

### Version 1.5.3
- **Frontend Filter Display**: Fixed grade and other taxonomy filters not appearing on frontend when using `[products]` shortcode in categories mode
- **Admin Category Filter Enhancement**: Fixed admin product category dropdown to display all categories (both parent and child) in hierarchical structure
- **User Experience**: Restored complete filter interface on frontend products page to match design requirements
- **Admin Interface**: Improved category filtering functionality for better product management workflow

### Version 1.5.2
- **Bug Fix**: Fixed category display order not appearing correctly when categories lack display_order meta field
- **Admin Filter Cleanup**: Removed grade dropdown from admin product filtering interface
- **Query Optimization**: Changed category retrieval from meta-key based query to fetch-all-then-sort approach
- **Mixed Scenario Support**: Categories with display_order values appear first (numerically sorted), followed by categories without order (alphabetically sorted)

### Version 1.6.0
- **Category Display Order System**: Admin interface to set custom display order for top-level product categories
- **Enhanced Admin Filtering**: Added Product Categories dropdown to admin product listing with hierarchical display
- **Comprehensive Filter Updates**: Updated admin filters to include Categories, Market Segment, Cooking Method, Menu Occasions, and Grade
- **Draft Product Support**: Admin filters now show ALL terms and can filter both published and draft products
- **Frontend Grid Layout**: Enforced 2x2 grid layout for category display mode with fixed responsive design
- **Database Optimization**: Categories now use display_order meta field instead of hard-coded ordering
- **UI Improvements**: Category dropdowns show hierarchical structure with subcategory indentation
- **Backwards Compatibility**: Existing categories display in alphabetical order if no display order is set

### Version 1.5.0
- **Performance Optimization**: Comprehensive performance improvements addressing GitHub issues #7, #8, and #13
- **Query Result Caching**: Added intelligent caching system for filtered product and recipe queries with 30-minute TTL
- **Pagination Support**: Added `per_page` and `page` parameters to shortcodes to prevent performance degradation
- **Cache Management**: Fixed cache group flush implementation and added proper cache invalidation hooks
- **Safety Features**: Added pagination limits (max 100 per page), large result set handling, and input validation
- **Memory Management**: Intelligent cache size limits and proper WP_Query object reconstruction
- **Backwards Compatibility**: All existing shortcode usage continues to work without changes
- **Debug Improvements**: Enhanced logging for cache hits/misses and query performance monitoring

### Version 1.4.0
- **Admin Product Filtering**: Added comprehensive taxonomy dropdown filters to WordPress admin product listing
- **Enhanced Product Management**: Filter products by category, grade, market segment, cooking method, menu occasion, product type, size, species, brand, and certification
- **Admin Integration**: New admin class with optimized query filtering and user-friendly dropdown interfaces
- **Product Import System**: Complete CSV import functionality with refined duplicate checking and comprehensive reporting
- **Import Documentation**: Detailed import summary reports and troubleshooting guides
- **Version Management**: Updated plugin version across all files and documentation

### Version 1.3.0
- **New display parameter**: Added `display="list"` mode for product catalog pages
- **Product list view**: Individual product cards with thumbnails, excerpts, and links
- **Enhanced filtering**: Category filter now available in list mode only
- **Custom category ordering**: Top-level categories display in specified order (crab, shrimp, appetizers, dietary alternatives)
- **Responsive design**: 3-column desktop, 2-column tablet, 1-column mobile layout
- **URL parameter support**: AJAX filtering updates URLs with query parameters (?filter=value)
- **Standardized breakpoints**: Desktop 850px+, Tablet 550-849px, Mobile 549px-

### Version 1.2.2
- **Code optimization phase 1**: Consolidated asset loading to eliminate duplication
- **Enhanced caching**: Updated recipes and products utils to extend base class
- **Performance improvements**: Reduced code duplication across modules

### Version 1.2.1
- **URL rewrite system**: Added SEO-friendly URLs for product categories
- **URL integration**: Product shortcode now supports URL parameters
- **Documentation updates**: Comprehensive URL rewrite documentation

### Version 1.2.0
- **Base utility class**: Created shared functionality to eliminate code duplication
- **Improved architecture**: Better separation of concerns across modules

## 🤝 Contributing

### Development Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/OrasesWPDev/handy-custom.git
   ```

2. **Install in WordPress**
   ```bash
   # Symlink to WordPress plugins directory
   ln -s /path/to/handy-custom /wp-content/plugins/handy-custom
   ```

3. **Enable debug mode**
   ```php
   define('HANDY_CUSTOM_DEBUG', true);
   ```

### Code Standards

- Follow WordPress coding standards
- Include comprehensive logging for debugging
- Write meaningful commit messages
- Add unit tests for new functionality

### Submitting Changes

1. Fork the repository
2. Create a feature branch
3. Make your changes with tests
4. Submit a pull request

## 📄 License

This plugin is licensed under the [GPL v2 or later](http://www.gnu.org/licenses/gpl-2.0.txt).

## 🔗 Links

- **Repository**: [https://github.com/OrasesWPDev/handy-custom](https://github.com/OrasesWPDev/handy-custom)
- **Author**: [Orases](https://orases.com)

**Note**: This is a custom plugin built specifically for this project and is not intended for public distribution on WordPress.org.

---

Built with ❤️ by [Orases](https://orases.com) for the WordPress community.
# Handy Custom WordPress Plugin

![WordPress](https://img.shields.io/badge/WordPress-5.3%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.2%2B-purple.svg)
![Version](https://img.shields.io/badge/version-1.2.2-green.svg)
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
```

### Advanced Product Filtering

```php
// Filter by category
[products category="crab"]

// Filter by subcategory (auto-detects parent)
[products subcategory="crab-cakes"]

// Multiple filters
[products category="shrimp" grade="premium" cooking_method="frying"]

// Market-specific filtering
[products market_segment="retail" product_type="appetizers"]
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

### Filter Parameters Reference

| Parameter | Taxonomy | Description |
|-----------|----------|-------------|
| `category` | `product-category` | Main product categories |
| `subcategory` | `product-category` | Child categories |
| `grade` | `grade` | Product quality grades |
| `market_segment` | `market-segment` | Target markets |
| `cooking_method` | `product-cooking-method` | Cooking methods |
| `menu_occasion` | `product-menu-occasion` | Meal occasions |
| `product_type` | `product-type` | Product classifications |
| `size` | `size` | Product sizes |

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

## 📝 Changelog

### Version 1.2.2 (Latest)
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
- **WordPress Plugin Directory**: Coming soon

---

Built with ❤️ by [Orases](https://orases.com) for the WordPress community.
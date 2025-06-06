# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a WordPress plugin called "Handy Custom" that provides shortcode-based product and recipe archives with AJAX filtering functionality. The plugin is designed to work with ACF Pro and the Flatsome theme.

## Architecture

The plugin follows a modular, object-oriented architecture:

- **Main Plugin File**: `handy-custom.php` - Entry point with logging control
- **Core Classes**: `/includes/` - Main functionality and utilities
- **Product Classes**: `/includes/products/` - Product-specific functionality
- **Templates**: `/templates/shortcodes/` - Shortcode output templates
- **Assets**: `/assets/` - Organized by post type (products/recipes)

## Core Functionality

### Phase 1 - Products (Completed)
1. **Shortcode System**: `[products]` with filtering parameters
2. **AJAX Filtering**: Live filtering with 7 taxonomy dropdowns
3. **Category Display**: Cards with featured images, icons, descriptions
4. **Template System**: Modular, reusable template structure

### Phase 2 - Recipes (Completed)
1. **Shortcode System**: `[recipes]` with filtering parameters
2. **AJAX Filtering**: Live filtering with 3 taxonomy dropdowns
3. **Recipe Display**: Cards with featured images, prep time, servings
4. **Clickable Cards**: Link to single recipe posts
5. **Template System**: Recipe-specific template structure

### Phase 3 - Product URL Structure (Completed)
1. **Subcategory Support**: `[products subcategory="crab-cakes"]` parameter
2. **Hierarchical Filtering**: Auto-detect parent categories from subcategories
3. **URL Rewrite System**: `/products/{category}/{subcategory}/` URLs
4. **Contextual Filtering**: Smart filter dropdowns based on subcategory context
5. **URL Integration**: Automatic parameter detection from URLs

### Future Phases
- Single post type templates
- Recipe URL structure (if needed)

## Shortcode Usage

### Main Products Shortcode
```php
[products] // All products with filter dropdowns
[products category="crab"] // Filtered by category
[products subcategory="crab-cakes"] // Filtered by subcategory (auto-detects parent)
[products category="crab" subcategory="crab-cakes"] // Explicit parent + child
[products grade="premium" market_segment="retail"] // Multiple filters
[products subcategory="gluten-free" grade="premium"] // Subcategory + additional filters
```

### URL Structure Integration
The shortcode automatically detects URL parameters when placed on pages with the new URL structure:
- `/products/` - Shows all products (equivalent to `[products]`)
- `/products/crab/` - Shows crab category (equivalent to `[products category="crab"]`)
- `/products/crab/crab-cakes/` - Shows crab cakes subcategory (equivalent to `[products subcategory="crab-cakes"]`)

URL parameters take precedence over shortcode attributes, enabling clean URLs while maintaining shortcode flexibility.

### Main Recipes Shortcode
```php
[recipes] // All recipes with filter dropdowns
[recipes category="appetizers"] // Filtered by category
[recipes cooking_method="baked" menu_occasion="dinner"] // Multiple filters
```

### Available Filter Parameters

#### Product Filters
- `category` - Product category (top-level)
- `subcategory` - Product subcategory (auto-detects parent category)
- `grade` - Product grade  
- `market_segment` - Market segment
- `cooking_method` - Cooking method
- `menu_occasion` - Menu occasion
- `product_type` - Product type
- `size` - Product size

#### Recipe Filters
- `category` - Recipe category
- `cooking_method` - Recipe cooking method
- `menu_occasion` - Recipe menu occasion

## File Structure

```
/includes/
├── class-handy-custom.php        # Main plugin class
├── class-logger.php              # Centralized logging
├── class-shortcodes.php          # Shortcode handlers & AJAX
├── /products/
│   ├── class-products-utils.php      # Shared utilities
│   ├── class-products-filters.php    # Filtering logic
│   ├── class-products-display.php    # Display helpers
│   └── class-products-renderer.php   # Main renderer
└── /recipes/
    ├── class-recipes-utils.php       # Recipe utilities
    ├── class-recipes-filters.php     # Recipe filtering logic
    ├── class-recipes-display.php     # Recipe display helpers
    └── class-recipes-renderer.php    # Recipe renderer

/templates/shortcodes/
├── /products/
│   └── archive.php               # Products shortcode template
└── /recipes/
    └── archive.php               # Recipes shortcode template

/assets/
├── /css/
│   ├── products/archive.css      # Products styling
│   └── recipes/archive.css       # Recipes styling
├── /js/
│   ├── products/archive.js       # Products AJAX functionality
│   └── recipes/archive.js        # Recipes AJAX functionality
└── /images/                      # Category icons ({slug}-icon.png)
```

## Custom Post Types & Taxonomies

### Product Post Type
- **Taxonomies**: product-category, grade, market-segment, product-cooking-method, product-menu-occasion, product-type, size
- **ACF Fields**: sub_header, carton_image, product_size, carton_size, case_pack_size, special_logos, item_number, upc_number, gtin_code, features_benefits, cooking_instructions, featured_recipes, food_handling, nutritional_facts, serving_suggestions, ingredients, allergens

### Recipe Post Type  
- **Taxonomies**: recipe-category, recipe-cooking-method, recipe-menu-occasion
- **ACF Fields**: related_products, prep_cook_time, servings, ingredients, cooking_instructions, where_to_buy

## Environment Setup

- **WordPress Theme**: Flatsome (latest version)
- **ACF Pro**: Registered, active, latest version
- **Field Groups**: Managed via ACF plugin (not in code)
- **Category Images**: ACF field `category_featured_image` on taxonomy terms

## Logging System

**Control Location**: `handy-custom.php` line 37
```php
define('HANDY_CUSTOM_DEBUG', false); // Set to true to enable logging
```

**Features**:
- Automatic `/logs/` directory creation when enabled
- Daily log rotation: `handy-custom-YYYY-MM-DD.log`
- Security: `.htaccess` protection, `/logs/` in `.gitignore`
- Usage: `Handy_Custom_Logger::log($message, $level)`

## Asset Loading

- **Smart Loading**: CSS/JS only loads on pages with shortcodes
- **Cache Busting**: Uses `filemtime()` for versioning
- **Organized Structure**: Separate assets by post type
- **Legacy Support**: Maintains compatibility with existing custom.css/js

## Category Icons

- **Naming Convention**: `{category-slug}-icon.png`
- **Location**: `/assets/images/`
- **Fallback**: Placeholder div when icon missing
- **Examples**: `product-crab-cakes-icon.png`, `product-shrimp-icon.png`

## Development Workflow

1. **Enable Logging**: Set `HANDY_CUSTOM_DEBUG` to `true` in main plugin file
2. **Add Category Icons**: Upload to `/assets/images/` with proper naming
3. **Customize Styling**: Edit `/assets/css/products/archive.css`
4. **Template Changes**: Modify `/templates/shortcodes/products/archive.php`
5. **New Filters**: Add to `Handy_Custom_Products_Utils::get_taxonomy_mapping()`

## Helpful documentation when needed
https://docs.uxthemes.com/
https://wordpress.org/documentation/
https://www.advancedcustomfields.com/resources/
https://www.advancedcustomfields.com/resources/shortcode/
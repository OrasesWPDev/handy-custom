# Handy Custom WordPress Plugin

![WordPress](https://img.shields.io/badge/WordPress-6.5%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)
![Version](https://img.shields.io/badge/version-2.0.9-green.svg)
![License](https://img.shields.io/badge/license-GPL--2.0-orange.svg)

A powerful WordPress plugin providing advanced product and recipe management with AJAX filtering, SEO-friendly URLs, custom single post templates, and hierarchical category management.

## 🚀 Overview

Handy Custom transforms your WordPress site's product and recipe displays into dynamic, filterable content systems. Perfect for e-commerce sites, recipe blogs, and catalog presentations that need sophisticated content management with professional single post templates.

### Key Features Delivered
- **Dynamic AJAX-powered filtering** → Real-time content updates without page reloads
- **SEO-friendly URL structures** → Clean, hierarchical URLs (`/products/crab/crab-cakes/`, `/recipe/recipe-slug/`)
- **Professional single post templates** → Dedicated templates for products and recipes with rich content display
- **Comprehensive breadcrumb integration** → Proper navigation hierarchy matching URL structure
- **Performance optimized** → Built-in caching and conditional asset loading
- **Auto-updater system** → GitHub-based automatic updates through WordPress admin

## ✨ Current Features

### 🛍️ Products System
- **Advanced Shortcodes**: `[products]` and `[filter-products]` with comprehensive filtering
- **SEO-Friendly URLs**: `/products/{category}/{product-slug}/` structure for single products
- **Single Product Templates**: Professional two-column layout with specifications, features, cooking instructions, nutritional facts, and allergens
- **Featured Recipes Integration**: Display up to 3 related recipes on product single pages with responsive grid layout
- **Social Sharing Integration**: Print, email, text, Facebook, X (Twitter), and LinkedIn sharing
- **Smart Category Detection**: Automatic parent category resolution for hierarchical navigation
- **Rich Product Cards**: Featured images, descriptions, and action buttons in archive views
- **Comprehensive Filtering**: Categories, subcategories, grades, market segments, cooking methods, menu occasions, product types, and sizes

### 🍳 Recipes System  
- **Recipe Shortcodes**: `[recipes]` and `[filter-recipes]` with category and method filtering
- **Single Recipe Templates**: Dedicated single recipe layout with ingredients, prep instructions, cooking instructions, and recipe details
- **Featured Products Integration**: Display up to 2 related products on recipe single pages with automatic card height equalization
- **Recipe Cards**: Prep time, servings, and comprehensive recipe information
- **Advanced Custom Fields Integration**: Full ACF support for recipe metadata including prep instructions, where to buy links
- **Social Sharing**: Consistent sharing functionality across recipe templates
- **Breadcrumb Integration**: Proper navigation showing Home / Recipes / Recipe Title

### ⚡ Technical Features
- **AJAX Filtering**: Real-time updates without page reloads
- **Professional Templates**: Custom single post templates for both products and recipes
- **Breadcrumb Integration**: Full Yoast SEO breadcrumb support with proper hierarchy
- **Performance Optimized**: Conditional asset loading - CSS/JS only loaded when needed
- **Debug Logging**: Comprehensive logging system for troubleshooting
- **Auto-Updater**: GitHub-based automatic plugin updates through WordPress admin using YahnisElsts library
- **Template System**: Customizable templates for complete control over presentation

## 📋 Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.5+ |
| PHP | 8.0+ |
| Advanced Custom Fields | Latest |

### Required Dependencies
- **Custom post types**: `product`, `recipe`
- **Custom taxonomies**: Product and recipe categories with filtering taxonomies
- **ACF fields**: For enhanced product and recipe metadata

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

### Core Shortcodes

```php
// Display all products (archive view)
[products]

// Display all recipes (archive view)
[recipes]

// Display product filters (standalone filtering)
[filter-products]

// Display recipe filters (standalone filtering)
[filter-recipes]
```

### Product Display Options

```php
// Display top-level categories as cards (default)
[products]

// Display all products in list format
[products display="list"]

// Display products from specific category
[products category="crab"]

// Display products with filtering
[products display="list" category="crab" grade="premium"]
```

### Recipe Display Options

```php
// Display all recipes
[recipes]

// Filter by category
[recipes category="desserts"]

// Filter by cooking method
[recipes cooking_method="baking"]

// Combined filters
[recipes category="main-course" menu_occasion="dinner"]
```

### Single Post Templates

The plugin automatically provides professional single post templates:

**Product Singles** (`/products/{category}/{product-slug}/`):
- Two-column responsive layout
- Collapsible specification tabs (Specifications, Features & Benefits, Cooking Instructions, Nutritional Facts, Allergens)
- Social sharing integration
- Breadcrumb navigation
- ACF field integration for all product data

**Recipe Singles** (`/recipe/{recipe-slug}/`):
- Professional recipe layout
- Ingredients, prep instructions, and cooking instructions in collapsible sections
- Recipe details table (prep time, servings, where to buy)
- Social sharing functionality
- Proper breadcrumb hierarchy

### SEO-Friendly URLs

The plugin automatically supports clean URLs:

```
/products/                          → Products archive page
/products/crab/                     → Crab category
/products/crab/crab-cakes/          → Crab cakes subcategory  
/products/crab/awesome-crab-cakes/  → Single product
/recipe/delicious-crab-cakes/       → Single recipe
```

## ⚙️ Configuration

### Advanced Custom Fields Setup

**For product posts:**
- Product specifications, features, cooking instructions
- Nutritional facts and allergen information
- Featured images and product details

**For recipe posts:**
- `prep_instructions` - WYSIWYG field for preparation steps
- `prep_time` - Text field for preparation time
- `servings` - Text field for serving information  
- `ingredients` - WYSIWYG field for ingredient list
- `cooking_instructions` - WYSIWYG field for cooking steps
- `where_to_buy` - URL field for purchase links

**For category terms:**
- `category_featured_image` - Image field for category cards
- `internal_url_for_this_product_category_or_subcategory` - URL field for custom links

### Debug Logging

Enable detailed logging for troubleshooting:

```php
// In handy-custom.php
define('HANDY_CUSTOM_DEBUG', true);
```

## 🔄 Auto-Updater System

The plugin includes a sophisticated GitHub-based auto-updater:

### How It Works
1. **GitHub Integration**: Monitors repository for new releases
2. **Version Detection**: Compares current version with latest GitHub release
3. **WordPress Integration**: Update notifications in WordPress admin
4. **One-Click Updates**: Standard WordPress update interface

### User Experience
- Automatic detection within 1 minute of new releases
- Standard WordPress update notifications
- Click "Update Now" for automatic installation
- Zero downtime updates

## 🛠️ Development

### Architecture Overview

```
Handy_Custom (Main Controller)
├── Single Post Templates (Product & Recipe)
├── Shortcodes (Archive displays & AJAX handlers)
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

### Browser Testing

The plugin includes comprehensive browser testing using Playwright for quality assurance across Chrome, Firefox, and Safari, ensuring consistent functionality and responsive design across all devices.

### Extending the Plugin

#### Custom Templates

Override templates by copying to your theme:
```
/wp-content/themes/your-theme/handy-custom/
    ├── product/single.php
    ├── recipe/single.php
    └── shortcodes/
        ├── products/archive.php
        └── recipes/archive.php
```

### Performance Features

- **Conditional Loading**: Assets only load on pages with shortcodes or single posts
- **Query Optimization**: Efficient database queries with proper indexing
- **Template Caching**: Smart template loading and caching
- **AJAX Optimization**: Streamlined AJAX requests for filtering

## 📝 Recent Updates (v2.0.9)

### Featured Content System (v2.0.8 - v2.0.9)
- ✅ **Featured Recipes on Products**: Display up to 3 related recipes on product single pages
- ✅ **Featured Products on Recipes**: Display up to 2 related products on recipe single pages
- ✅ **Card Height Equalization**: JavaScript-based height matching for consistent layouts
- ✅ **Responsive Grid Design**: CSS Grid with dynamic centering based on content count
- ✅ **Domain-Agnostic URL Parsing**: Flexible ACF URL field integration across different domains

### Enhanced Filtering System (v2.0.7)
- ✅ **Recipe Filter Implementation**: Complete `[filter-recipes]` shortcode with design consistency
- ✅ **Contextual Filtering**: Auto-detection of category context for relevant filter options
- ✅ **Universal Clear Button**: Left-aligned clear functionality across all filter types
- ✅ **Responsive Filter Design**: 3-column recipe filters, 7-column product filters

### Product & Recipe System Foundation (v2.0.0 - v2.0.6)
- ✅ **Single Templates**: Professional layouts for both products and recipes
- ✅ **Social Sharing Integration**: Consistent sharing across all templates
- ✅ **Breadcrumb Integration**: Complete Yoast SEO support with proper hierarchy
- ✅ **"Where to Buy" Sections**: Updated design matching provided examples

## 🤝 Contributing

**Lead Developer**: [@chad-orases](https://github.com/chad-orases)

**Contributors**:

<a href="https://github.com/Orases-Javier">
  <img src="https://github.com/Orases-Javier.png" width="50" height="50" alt="Orases-Javier" style="border-radius: 50%;">
</a>
<a href="https://github.com/luke3butler">
  <img src="https://github.com/luke3butler.png" width="50" height="50" alt="luke3butler" style="border-radius: 50%;">
</a>

### Code Standards

- Follow WordPress coding standards
- Include comprehensive logging for debugging
- Write meaningful commit messages
- Test both archive and single post functionality

## 📄 License

This plugin is licensed under the [GPL v2 or later](http://www.gnu.org/licenses/gpl-2.0.txt).

## 🔗 Links

- **Repository**: [https://github.com/OrasesWPDev/handy-custom](https://github.com/OrasesWPDev/handy-custom)
- **Author**: [Orases](https://orases.com)

---

Built with ❤️ by [Orases](https://orases.com) for advanced WordPress content management.
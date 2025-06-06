# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## WordPress Plugin Architecture

This is a WordPress plugin providing product and recipe archive functionality with shortcode support and AJAX filtering.

### Core Components

- **Main Plugin Class**: `Handy_Custom` - singleton pattern, handles initialization, asset loading, and URL rewriting
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

### URL Rewriting System

Supports SEO-friendly URLs for products:
- `/products/{category}/` - category page
- `/products/{category}/{subcategory}/` - subcategory page

URL parameters are automatically merged with shortcode attributes, with URL taking precedence.

### Caching System

Two-tier caching for taxonomy terms:
1. WordPress object cache (1 hour TTL)
2. Static class-level cache for request duration

### Template System

Templates located in `/templates/shortcodes/{type}/archive.php` with these variables:
- `$filters` - current filter values
- `$categories` - content to display  
- `$filter_options` - available filter options
- `$subcategory_context` - subcategory context (products only)

### Debug/Logging

Set `HANDY_CUSTOM_DEBUG = true` in main plugin file to enable file-based logging to `/logs/` directory.

## Development Notes

- Plugin version is defined in two places: main file header and class constant
- All classes use strict security checks (`ABSPATH` validation)
- AJAX handlers include nonce verification
- Error handling with try/catch blocks and proper logging
- Subcategory filtering includes automatic parent category detection
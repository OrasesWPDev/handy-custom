# CLAUDE.md

This file provides essential development guidance for Claude Code when working with this WordPress plugin.

## Critical Development Notes

- **Plugin Structure**: Each content type (products/recipes) has 4 classes: Utils, Filters, Display, Renderer
- **Version Management**: Update both plugin header and `Handy_Custom::VERSION` constant
- **Category Display Order**: Use `display_order` meta field for top-level categories, fallback to alphabetical
- **Admin Filters**: Product admin uses taxonomies: product-category, market-segment, product-cooking-method, product-menu-occasion
- **Debug Logging**: Set `HANDY_CUSTOM_DEBUG = true` in main plugin file to enable logging to `/logs/`
- **Responsive Breakpoints**: Desktop 850px+, Tablet 550-849px, Mobile 549px-
- **Display Modes**: `[products]` supports `display="categories"` (default) and `display="list"`
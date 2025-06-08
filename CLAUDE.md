# CLAUDE.md

Essential development guidance for Claude Code when working with this WordPress plugin.

## Development Notes

- **Filter System**: New `[filter-products]` and `[filter-recipes]` shortcodes use unified `Handy_Custom_Filters_Renderer` class
- **Asset Loading**: Filter CSS/JS only enqueue when filter shortcodes detected on page
- **Template Structure**: Filter HTML removed from content templates, now in `templates/shortcodes/filters/archive.php`
- **Version Updates**: Always update both `handy-custom.php` header AND `Handy_Custom::VERSION` constant
- **Debug Flag**: Filter JS respects `HANDY_CUSTOM_DEBUG` constant for console logging
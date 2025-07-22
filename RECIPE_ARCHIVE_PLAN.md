# Recipe Archive [recipes] Shortcode Implementation Plan

## Project Overview
Implement the recipe archive layout for the `[recipes]` shortcode to match the Adobe XD design template. This will create a 4-column grid of recipe cards with consistent styling to match the existing products archive.

## Design Requirements
- **Layout**: 4 columns on full screen, responsive down to 1 column on mobile
- **Pagination**: 16 recipes per page (4 rows × 4 columns)
- **Card Content**: Recipe thumbnail, title, 120-char excerpt, prep time, and servings
- **Consistency**: Match products CSS colors, fonts, sizing, and responsive behavior
- **Icons**: FontAwesome clock (`fa-regular fa-clock`) and user (`fa-regular fa-user`)

## Technical Specifications

### 1. Card Structure
Each recipe card contains:
1. **Recipe Thumbnail** - `the_post_thumbnail()`
2. **Recipe Title** - `get_the_title()`
3. **Recipe Excerpt** - 120 characters max from content with "..." (using `wp_trim_excerpt()`)
4. **Recipe Metadata Row**:
   - Left: Clock icon + prep_time field
   - Right: User icon + servings field (auto-append "servings" if only numbers)

### 2. Styling Consistency with Products
**Colors & Typography:**
- Font family: `Poppins, sans-serif`
- Primary color: `#0145AB` (blue)
- Title color: `#273749`
- Body text color: `#3E434A`
- Card background: white
- Border radius: `60px`
- Box shadow: `10px 25px 0px #2329330F`

**Grid Layout:**
- 4 columns on desktop (vs products 2-column)
- Same responsive breakpoints as products
- Same padding and margin structure

### 3. Field Mappings (from ACF JSON)
- **prep_time**: Custom field `field_6842f90940596` 
- **servings**: Custom field `field_6823619f1a790`
- **content**: WordPress editor field for excerpt generation
- **featured_image**: WordPress thumbnail

## Implementation Tasks

### Phase 1: Foundation
- [x] Switch to main branch and pull latest changes
- [x] Create this plan documentation
- [x] Create new feature branch for work

### Phase 2: Core Implementation  
- [x] Update recipe archive template structure (`templates/shortcodes/recipes/archive.php`)
- [x] Create/update recipe display helper methods for card data
- [x] Update recipe archive CSS with product consistency and 4-column grid
- [x] Update recipes renderer class for pagination (16 per page)

### Phase 3: Testing & Finalization
- [x] Test recipe card display with various content lengths
- [x] Update plugin version in all required files
- [ ] Push to GitHub and create PR

## Files to Modify
1. `templates/shortcodes/recipes/archive.php` - Main template structure
2. `assets/css/recipes/archive.css` - Styling with product consistency
3. `includes/recipes/class-recipes-renderer.php` - Pagination logic
4. `includes/recipes/class-recipes-display.php` - Helper methods (create if needed)
5. Plugin version files - Version updates

## Success Criteria
- ✅ 4-column responsive grid matching design template
- ✅ Recipe cards with all required content elements
- ✅ Consistent styling with products archive
- ✅ Proper pagination (16 recipes per page)
- ✅ FontAwesome icons for prep time and servings
- ✅ Auto-formatting of servings text
- ✅ 120-character excerpt truncation with ellipsis
- ✅ Mobile responsiveness matching products behavior

## Implementation Completed

### Changes Made:

#### Templates & Display
- **Recipe Archive Template**: Removed header section, cleaned up structure for pure 4-column grid
- **FontAwesome Icons**: Updated from emoji to `fa-regular fa-clock` and `fa-regular fa-user`
- **Recipe Display Helper**: Updated to use FontAwesome icons consistently

#### Data & Logic
- **Content Truncation**: Changed from 150 to 120 characters as per design spec
- **Servings Auto-formatting**: Enhanced logic to append "servings" when field contains only numbers
- **Pagination**: Updated default from 12 to 16 recipes per page (4 rows × 4 columns)

#### Styling & Responsive
- **Product Consistency**: Matched exact colors, fonts, spacing, and responsive behavior
- **Typography**: Poppins font family, #273749 titles, #3E434A body text
- **Cards**: 60px border radius, product-style shadows, same padding structure
- **Grid**: 4→3→2→1 columns responsive breakpoints matching products
- **Version**: Updated to 1.9.30 in all required files

### Files Modified:
1. `templates/shortcodes/recipes/archive.php` - Template structure
2. `assets/css/recipes/archive.css` - Complete styling overhaul
3. `includes/recipes/class-recipes-display.php` - FontAwesome icons
4. `includes/recipes/class-recipes-utils.php` - 120-char truncation
5. `includes/recipes/class-recipes-filters.php` - 16-per-page pagination
6. `handy-custom.php` - Version update
7. `includes/class-handy-custom.php` - Version constant

## Layout Feedback & Corrections Required

### Issues Identified in Initial Implementation
1. **Content Width Problem**: Container padding of `240px` is too restrictive, should be `20px` for better content width usage
2. **Unwanted Category Icon**: The `<div class="recipe-category-icon recipe-category-icon-placeholder"><span>C</span></div>` was not requested and needs removal
3. **Card Layout Consistency**: Cards need dynamic heights where all cards in a row match the height of the longest title in that row, with metadata always bottom-aligned

### Additional Implementation Phase: Layout Corrections

#### Phase 4: Container Padding Fix
- [x] Change `.handy-recipes-archive` padding from `20px 240px` to `20px 20px`
- [x] Allow 4-column grid to utilize full available content width

#### Phase 5: Template Cleanup  
- [x] Remove entire `<div class="recipe-category-icon recipe-category-icon-placeholder">` section from archive template
- [x] Clean up any related CSS for category icon styling

#### Phase 6: Dynamic Card Heights
- [x] Ensure cards in each row adjust to match longest title height in that row
- [x] Maintain bottom-aligned metadata across all cards using flexbox
- [x] Keep full titles displayed without truncation (dynamic height system)

### Updated Success Criteria
- ✅ Recipe grid uses full content width with proper 20px margins  
- ✅ No category icon placeholder visible
- ✅ Cards in each row match height of longest title in that row
- ✅ Metadata consistently bottom-aligned across all cards
- ✅ Full titles always displayed without truncation
- ✅ Responsive behavior maintained

## Notes
- Focus only on `[recipes]` shortcode output - no filter controls
- Next project will handle `[filter-recipes]` shortcode layout
- Ensure all work follows DRY and KISS principles
- Make iterative commits as work progresses
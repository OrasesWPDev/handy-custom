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
- [ ] Create new feature branch for work

### Phase 2: Core Implementation  
- [ ] Update recipe archive template structure (`templates/shortcodes/recipes/archive.php`)
- [ ] Create/update recipe display helper methods for card data
- [ ] Update recipe archive CSS with product consistency and 4-column grid
- [ ] Update recipes renderer class for pagination (16 per page)

### Phase 3: Testing & Finalization
- [ ] Test recipe card display with various content lengths
- [ ] Update plugin version in all required files
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

## Notes
- Focus only on `[recipes]` shortcode output - no filter controls
- Next project will handle `[filter-recipes]` shortcode layout
- Ensure all work follows DRY and KISS principles
- Make iterative commits as work progresses
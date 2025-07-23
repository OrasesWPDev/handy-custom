# Single Recipe Template Development Plan

## Project Overview
Develop a single recipe post type template with layout similar to the existing single product template, featuring two-column layout with image left, content right, followed by accordion sections.

## ACF Fields Available (Research Complete - Recipe Fields Only)

### Core Recipe Fields:
- **prep_time**: Number field (minutes) - already implemented in archive display
- **servings**: Number field - already implemented in archive display

### Recipe Content Fields:
- **ingredients**: WYSIWYG field - for recipe ingredients list
- **cooking_instructions**: WYSIWYG field - step-by-step cooking instructions

### Category Fields (Shared):
- **category_featured_image**: Image field - for recipe category images

**Important Note**: Research confirmed that recipes have only these 4 ACF fields. Other fields found in codebase are product-specific and should not be used for recipes.

## Layout Plan (Based on Single Product Template)

### Header Section Structure:
1. **Breadcrumbs**: Full-width background component (same as products)
   - Background: #E7EDF0 with #DBE1E4 borders
   - Contains category > recipe name navigation

2. **Main Content Area**: Two-column layout (1fr 1fr grid)
   - **Left Column**: Featured image with styling similar to product carton
   - **Right Column**: Recipe title, prep time/servings display, main content, social share icons

### Accordion Section Structure:
Below the two-column section, implement accordion sections for recipe-specific content:

## Recipe Details Section (NOT in accordion):
**Specifications-Style Table** displaying:
- **Prep Time**: Display formatted `prep_time` field (using existing utility functions)
- **Servings**: Display formatted `servings` field (using existing utility functions)

## Planned Accordion Sections (Recipe Fields Only):

### Section 1: Ingredients
- **Field**: `ingredients` (WYSIWYG)
- **Icon**: `fas fa-list`
- **Purpose**: Display recipe ingredients list

### Section 2: Cooking Instructions  
- **Field**: `cooking_instructions` (WYSIWYG)
- **Icon**: `fas fa-utensils`
- **Purpose**: Step-by-step cooking instructions

## Files to Create:

### 1. Template File:
- **Path**: `/templates/recipe/single.php`
- **Purpose**: Main single recipe template
- **Base**: Copy structure from `/templates/product/single.php` and adapt

### 2. CSS File:
- **Path**: `/assets/css/recipes/single-recipe.css`
- **Purpose**: Recipe-specific styling
- **Base**: Copy relevant styles from `/assets/css/products/single-product.css`

## Implementation Phases:

### Phase 1: Template Structure ✏️ (Next)
- Create basic template file with two-column layout
- Implement breadcrumbs, title, and featured image display
- Add prep time/servings display in right column
- Include social share functionality

### Phase 2: Accordion Implementation
- Create accordion sections for recipe-specific fields
- Implement field display logic with proper fallbacks
- Add JavaScript functionality for accordion behavior

### Phase 3: Styling & Responsive Design
- Create recipe-specific CSS file
- Implement responsive breakpoints matching product template
- Style accordion sections and field displays

### Phase 4: Testing & Refinement
- Test with various recipe posts and field combinations
- Verify responsive design functionality
- Test accordion interactions and field fallbacks

### Phase 5: Version Management
- Update plugin version for single recipe template feature
- Commit changes iteratively during development
- Create pull request when complete

## Technical Notes:

### URL Structure:
- Recipes already use standard WordPress permalinks via `get_permalink()`
- Archive cards already link properly to single recipe pages

### Asset Loading:
- Single recipe CSS should load when `is_singular('recipe')` is true
- Follow existing pattern from product template asset loading

### Breadcrumb Integration:
- Recipe categories should display in breadcrumb navigation
- Follow existing breadcrumb logic from product template

## Development Progress Tracking:

- [x] Research recipe ACF fields and structure
- [x] Plan layout based on product template
- [x] Create development branch
- [x] Create planning documentation
- [x] Confirm recipe-only fields (prep_time, servings, ingredients, cooking_instructions)
- [x] Plan specifications-style recipe details section
- [x] Plan 2 accordion sections (ingredients, cooking instructions)
- [x] Create basic template structure
- [x] Implement accordion sections with proper Font Awesome icons
- [x] Create recipe-specific CSS file adapted from product template
- [x] Add CSS loading logic to main plugin class
- [x] Add template loading hook for WordPress integration
- [x] Version update to 2.0.0 and deployment preparation
- [x] **IMPLEMENTATION COMPLETE**

## Final Template Structure Planned:

### 1. Header Section:
- Breadcrumbs (full-width background matching products)
- Two-column layout: Featured image left, title/content/social right

### 2. Recipe Details Table (NOT accordion):
- **Prep Time** (formatted using existing utility function)
- **Servings** (formatted using existing utility function)

### 3. Accordion Sections:
- **Ingredients** (fas fa-list icon)
- **Cooking Instructions** (fas fa-utensils icon)

### 4. Files to Create:
- `/templates/recipe/single.php` - Main template file
- `/assets/css/recipes/single-recipe.css` - Recipe-specific styling

## Ready for Implementation
## Implementation Summary

Single recipe template v2.0.0 has been successfully implemented with the following features:

### ✅ Completed Features:
- **Template Structure**: Two-column layout matching product template (title left, image right)
- **Recipe Details Table**: Non-accordion section displaying prep time and servings with proper formatting
- **Accordion Sections**: Two sections for ingredients (fas fa-list) and cooking instructions (fas fa-utensils) 
- **CSS Integration**: Custom stylesheet with responsive design matching product template patterns
- **Asset Loading**: Automatic CSS loading when viewing single recipe posts via `is_singular('recipe')`
- **Template Loading**: WordPress template hierarchy integration with single_template filter hook
- **Version Management**: Major version bump to 2.0.0 reflecting significant new functionality

### 📁 Files Created/Modified:
- `templates/recipe/single.php` - Main single recipe template (enhanced with icons)
- `assets/css/recipes/single-recipe.css` - Recipe-specific styling (347 lines)
- `includes/class-handy-custom.php` - Asset loading and template hooks (67 lines added)
- `handy-custom.php` - Version updates to 2.0.0
- `SINGLE_RECIPE_PLAN.md` - Planning documentation (this file)

### 🎯 Ready for Production:
Template is ready for testing with actual recipe posts and creating pull request for v2.0.0 release.

---
*Implementation completed successfully following comprehensive planning phase.*
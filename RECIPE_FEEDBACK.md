# Recipe Single Post Template - Feedback & Fixes

## Overview
This document tracks feedback and planned fixes for the recipe single post template and related functionality. All feedback items will be documented here before any coding begins.

## Feedback Item #1: Social Sharing Icons Standardization ✅ COMPLETED

### Issue Description
Social sharing icons are inconsistent between product and recipe templates. The recipe template has a cleaner implementation with JavaScript popups that is preferred, but both templates need standardization and improvements.

### Current State Analysis

#### Products Template Current Icons:
- Print (JavaScript popup)
- Email (mailto functionality) 
- Facebook (Flatsome native sharing OR fallback link)
- LinkedIn (Flatsome native sharing OR fallback link)  
- Pinterest (fallback link only)
- Instagram (static link)
- YouTube (static link)

#### Recipe Template Current Icons:
- Print (JavaScript popup)
- Facebook (JavaScript popup)
- Twitter (JavaScript popup) - **OUTDATED BRANDING**
- LinkedIn (JavaScript popup)

### Required Changes

#### For Products Template (`/templates/product/single.php`):
- **Replace complex Flatsome native sharing system** with clean recipe-style JavaScript popup approach
- **Add text sharing option** next to email sharing
- **Update Twitter icon to "X" branding** with new icon and branding
- **Standardize icon order**: Print | Email | Text | Facebook | X | LinkedIn

#### For Recipe Template (`/templates/recipe/single.php`):
- **Add email sharing icon** with mailto functionality (missing currently)
- **Add text sharing option** next to email sharing  
- **Update Twitter icon to "X" branding** with new icon and branding
- **Standardize icon order**: Print | Email | Text | Facebook | X | LinkedIn

### Target Implementation Details

#### Preferred Approach (Recipe-style):
- Use JavaScript `window.open()` popups for social media sharing
- Use `onclick` handlers for dynamic URL generation
- Clean, consistent styling across both templates

#### Required Icon Set:
1. **Print** - `fas fa-print` - JavaScript `window.print()`
2. **Email** - `fas fa-envelope` - `mailto:` functionality  
3. **Text** - `fas fa-sms` - Native text sharing functionality
4. **Facebook** - `fab fa-facebook-f` - JavaScript popup
5. **X (formerly Twitter)** - `fab fa-x-twitter` - JavaScript popup with X branding
6. **LinkedIn** - `fab fa-linkedin-in` - JavaScript popup

### Implementation Priority
**High Priority** - Ensures consistent user experience across both product and recipe templates.

---

## Feedback Item #2: Remove Unwanted Accordion Icons & Fix Alignment ✅ COMPLETED

### Issue Description
Recipe accordions have unwanted descriptive icons on the left side that weren't requested and don't match the cleaner product accordion layout.

### Current State Analysis

#### Products Template Accordion Headers (Correct):
```html
<button class="handy-accordion-header active" data-section="specifications">
    <span>Specifications</span>
    <i class="fas fa-chevron-down"></i>
</button>
```
- **Layout**: Title left-aligned + chevron icon right-aligned
- **Clean appearance**: No extra visual elements

#### Recipe Template Accordion Headers (Issue):
```html
<button class="handy-accordion-header active" data-section="ingredients">
    <i class="fas fa-list handy-accordion-icon"></i>
    <span>Ingredients</span>
    <i class="fas fa-chevron-down"></i>
</button>
```
- **Extra icons**: `fas fa-list` (Ingredients), `fas fa-utensils` (Cooking Instructions)  
- **Unnecessary styling**: Magenta color (#B5016E) with custom `handy-accordion-icon` class
- **Layout inconsistency**: Title not properly left-justified due to left icon

### Required Changes

#### For Recipe Template (`/templates/recipe/single.php`):
- **Remove left-side descriptive icons** from both accordion sections
- **Keep only chevron-down icon** on the right side (matching products)
- **Ensure accordion titles are left-justified** like product accordions
- **Apply same color and hover/active effects** as product accordions

#### For Recipe CSS (`/assets/css/recipes/single-recipe.css`):
- **Remove `.handy-accordion-icon` styling** as it will no longer be needed
- **Ensure accordion header layout matches products** for consistency

### Target Implementation
Match the clean product accordion layout:
- Title text positioned on the left
- Only chevron-down icon on the right
- Same styling, colors, and interactive effects as products

### Implementation Priority
**Medium Priority** - Visual consistency improvement that affects user experience.

---

## Feedback Item #3: Missing Prep Instructions Accordion Section ✅ COMPLETED

### Issue Description
Recipe template is missing the "prep instructions" field and accordion section that should appear between ingredients and cooking instructions.

### Current State Analysis

#### Current Recipe Template Accordion Order:
1. **Ingredients** (existing)
2. **Cooking Instructions** (existing)

#### Required Recipe Accordion Order:
1. **Ingredients** (existing)
2. **Prep Instructions** (missing from template)
3. **Cooking Instructions** (existing)

### Updated Recipe Field Structure Analysis

#### Complete Recipe ACF Fields (7 total):
1. **`related_products`** - Repeater field with URL sub-field (new)
2. **`prep_instructions`** - WYSIWYG field (exists in field groups, missing from template)
3. **`prep_time`** - Text field (field type changed from number to text)
4. **`servings`** - Text field (field type changed from number to text)
5. **`ingredients`** - WYSIWYG field (existing)
6. **`cooking_instructions`** - WYSIWYG field (existing)
7. **`where_to_buy`** - URL field (new)

### Required Changes

#### For Recipe Template (`/templates/recipe/single.php`):
- **Add prep instructions accordion section** between ingredients and cooking instructions
- **Update field handling** for text-based prep_time and servings (changed from number fields)
- **Consider implementation** of related_products and where_to_buy fields

#### Logic Verification:
**Accordion Order: Ingredients → Prep Instructions → Cooking Instructions**
- ✅ **Correct workflow**: Gather ingredients first, prep them, then cook
- ✅ **Logical sequence**: Follows natural recipe preparation process

### Implementation Priority
**High Priority** - Critical missing functionality that affects recipe usability.

---

## Feedback Item #4: Missing "Where to Buy" in Recipe Details Section ✅ COMPLETED

### Issue Description
The "where to buy" field should be included in the recipe details table section (non-accordion area) below servings, but is currently missing from the template implementation.

### Current State Analysis

#### Current Recipe Details Table (Non-Accordion):
- **Prep Time** (existing)
- **Servings** (existing)

#### Required Recipe Details Table (Non-Accordion):
- **Prep Time** (existing)
- **Servings** (existing)
- **Where to Buy** (missing from template)

### Field Status
- **Field exists in ACF**: `where_to_buy` (URL field) - confirmed in field groups
- **Field type**: URL field for external links (e.g., Instacart)
- **Implementation status**: Field exists but not used in template

### Required Changes

#### For Recipe Template (`/templates/recipe/single.php`):
- **Add "Where to Buy" row** to the recipe details table below servings
- **Display as clickable link** when URL field has content
- **Follow same table styling** as prep time and servings rows
- **Include proper fallback** when field is empty (optional field)

#### Target Table Structure:
```
Recipe Details Table (Specifications-style):
┌─────────────────┬─────────────────────────────┐
│ Prep Time       │ [formatted prep time]      │
├─────────────────┼─────────────────────────────┤
│ Servings        │ [formatted servings]       │  
├─────────────────┼─────────────────────────────┤
│ Where to Buy    │ [clickable URL link]       │
└─────────────────┴─────────────────────────────┘
```

### Implementation Priority
**Medium Priority** - Enhances user experience by providing purchase information in logical location.

---

## Feedback Item #5: Recipe Breadcrumb URL Structure Fix

### Issue Description
Recipe single pages have incorrect breadcrumb structure that doesn't match their URL pattern. The URLs are correctly structured as `/recipe/recipe-slug/` but the breadcrumbs only show `Home / Recipe Title` instead of the expected `Home / Recipes / Recipe Title`.

### Current State Analysis

#### Current URL Structure (Correct):
- Recipe URL: `https://handycrab.wpenginepowered.com/recipe/awesome-crab-cakes/`
- URL pattern: `/recipe/{recipe-slug}/`

#### Current Breadcrumb Output (Issue):
```html
<div class="handy-single-recipe-breadcrumbs">
    <nav class="handy-breadcrumb-nav">
        <span>
            <span>
                <a href="https://handycrab.wpenginepowered.com/">Home</a>
            </span> / 
            <span class="breadcrumb_last" aria-current="page">Awesome Crab Cakes</span>
        </span>
    </nav>
</div>
```
- Current breadcrumb: `Home / Awesome Crab Cakes`
- **Missing intermediate level**: Should be `Home / Recipes / Awesome Crab Cakes`

#### Root Cause Analysis
The existing `modify_yoast_breadcrumbs()` function in `/includes/class-handy-custom.php` only handles product post types:

```php
public function modify_yoast_breadcrumbs($breadcrumbs) {
    if (!is_singular('product')) {
        return $breadcrumbs;
    }
    // ... product breadcrumb logic only
}
```

Recipe post types bypass this function entirely, falling back to default Yoast breadcrumb behavior which only shows `Home / Post Title`.

### Required Changes

#### For Main Class (`/includes/class-handy-custom.php`):
- **Expand breadcrumb condition** from `if (!is_singular('product'))` to handle both products and recipes
- **Add recipe-specific breadcrumb logic** similar to products but simpler (no category hierarchy)
- **Recipe breadcrumb structure**: `Home / Recipes / Recipe Title`
- **Recipes archive URL**: Point to `/recipes/` (standard WordPress archive URL)

### Target Implementation

#### Expected Recipe Breadcrumb Structure:
```
Home / Recipes / Recipe Title
├─ Home: Link to site homepage
├─ Recipes: Link to /recipes/ archive page  
└─ Recipe Title: Current page (no link)
```

#### Code Structure (Similar to Products):
```php
public function modify_yoast_breadcrumbs($breadcrumbs) {
    // Handle both products and recipes
    if (!is_singular(array('product', 'recipe'))) {
        return $breadcrumbs;
    }
    
    global $post;
    
    if ($post->post_type === 'product') {
        // Existing product logic (unchanged)
        // ...
    } elseif ($post->post_type === 'recipe') {
        // New recipe breadcrumb logic
        // Home / Recipes / Recipe Title
        // ...
    }
}
```

### Implementation Priority
**High Priority** - Affects SEO and navigation consistency. URLs and breadcrumbs should match for proper user experience and search engine understanding.

---

## Feedback Items Complete
All feedback has been documented. Parts 1-4 have been implemented and deployed. Part 5 is ready for implementation.

---

## Implementation Status
- ✅ **Parts 1-4**: Completed and deployed in v2.0.1 (PR #76)
- 🔄 **Part 5**: Ready for implementation in v2.0.2

---

## Implementation Notes
- Parts 1-4 were successfully implemented and merged via PR #76
- Plugin version updated to v2.0.1 for completed work
- Part 5 will be implemented as v2.0.2 update
- Comprehensive testing will be conducted before deployment

---

## Status: Part 5 Implementation Ready
**Next Step**: Implement recipe breadcrumb fix as outlined in Part 5.
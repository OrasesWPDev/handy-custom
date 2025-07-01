# Drupal Database Recipe Analysis

## Overview
Analysis of the Drupal database structure for recipes to understand what data is available for migration to WordPress.

## Recipe Content Type Structure

### Core Recipe Tables
- **Main content**: `node_field_data` (where `type = 'recipe'` and `status = 1` for active recipes)
- **Recipe-specific fields**: Multiple `node__recipe_*` tables

### Active Recipe Query
```sql
SELECT 
    n.nid,
    n.title,
    n.status,
    n.created,
    n.changed,
    rd.recipe_description_value,
    pt.recipe_prep_time_value,
    ct.recipe_cook_time_value,
    ya.recipe_yield_amount_value,
    yu.recipe_yield_unit_value,
    ri.recipe_instructions_value,
    cm.field_cooking_method_target_id,
    dl.field_difficulty_level_value
FROM node_field_data n
LEFT JOIN node__recipe_description rd ON n.nid = rd.entity_id
LEFT JOIN node__recipe_prep_time pt ON n.nid = pt.entity_id  
LEFT JOIN node__recipe_cook_time ct ON n.nid = ct.entity_id
LEFT JOIN node__recipe_yield_amount ya ON n.nid = ya.entity_id
LEFT JOIN node__recipe_yield_unit yu ON n.nid = yu.entity_id
LEFT JOIN node__recipe_instructions ri ON n.nid = ri.entity_id
LEFT JOIN node__field_cooking_method cm ON n.nid = cm.entity_id
LEFT JOIN node__field_difficulty_level dl ON n.nid = dl.entity_id
WHERE n.type = 'recipe' AND n.status = 1;
```

## Available Recipe Fields

### Basic Information
- `nid` (int) - Unique recipe ID
- `title` (varchar 255) - Recipe title
- `status` (tinyint) - Published status (1 = active, 0 = unpublished)
- `created` (int) - Creation timestamp
- `changed` (int) - Last modified timestamp

### Recipe-Specific Data
- `recipe_description_value` (longtext) - Recipe description/summary (HTML format)
- `recipe_prep_time_value` (int) - Preparation time in minutes
- `recipe_cook_time_value` (int) - Cooking time in minutes  
- `recipe_yield_amount_value` (int) - Yield quantity (e.g., 4, 6, 8)
- `recipe_yield_unit_value` (varchar) - Yield unit (typically "Servings")
- `recipe_instructions_value` (longtext) - Cooking instructions (HTML format)
- `field_difficulty_level_value` (varchar) - Difficulty level (e.g., "Easy")
- `field_cooking_method_target_id` (int) - Reference to cooking method taxonomy

### Additional Fields Available
- `recipe_notes` - Recipe notes/tips
- `recipe_source` - Recipe source attribution

## Taxonomy Structure

### Cooking Methods (`cooking_methods` vocabulary)
Available cooking methods from `taxonomy_term_field_data`:
- Broil (tid: 23)
- Bake (tid: 24) 
- Saute (tid: 25)
- Deep Fry (tid: 46)
- Grill (tid: 48)
- Ready to Eat (tid: 49)
- Stew (tid: 51)
- Microwave (tid: 53)
- Pan Seared (tid: 54)
- Toast (tid: 190)

### Other Relevant Taxonomies
- `menu_occasions` - Menu occasion categories
- `certifications` - Food certifications
- `market_segments` - Market targeting

## Image Handling

### Recipe Images
- Table: `node__field_image` (where `bundle = 'recipe'`)
- Fields:
  - `field_image_target_id` - References `file_managed` table
  - `field_image_alt` - Alt text
  - `field_image_title` - Image title
  - `field_image_width/height` - Dimensions

### File Management
- `file_managed` - Core file storage table
- `file_usage` - File usage tracking

## Ingredients Structure

### Current State
- `node__recipe_ingredient` table exists but is **empty**
- `ingredient` and `ingredient_field_data` tables exist with basic ingredient entities
- **Ingredients appear to be embedded within recipe instructions text rather than structured data**

### Ingredient Entity Structure (for reference)
- `id` - Ingredient ID
- `name` - Ingredient name (e.g., "Flour", "All-Purpose Flour", "Egg")
- `created/changed` - Timestamps

## Sample Data

### Recent Active Recipes (Top 10 by last modified)
1. Shrimp Roll Fried Rice (nid: 443)
2. Handy's Awesome Crab Cakes (nid: 284)
3. Gluten-Free Keto Friendly Crab Cake Stuffed Portobello (nid: 515)
4. Gluten Free Crab Cake Waffle (nid: 335)
5. Crab Cake Eggs Benedict (nid: 458)
6. Pull-Apart OLD BAY Crab Cake Dip (nid: 457)
7. Crab Cake Egg Rolls (nid: 449)
8. Crab Cake Stuffed Peppers (nid: 450)
9. Crab Cake BLT (nid: 459)
10. Old Bay Crab and Broccoli Quiche (nid: 451)

## Migration Considerations

### Data Quality
- HTML content in description and instructions fields needs cleanup
- Time values are in minutes (integer format)
- Ingredients are likely text-based within instructions
- Images need file path resolution from `file_managed`

### Missing Structured Data
- No structured ingredient lists with quantities/units
- Nutritional information not present in recipe structure
- Recipe categories/tags may need extraction from other taxonomy relationships

## Next Steps for WordPress Migration
1. Map Drupal recipe fields to WordPress ACF structure
2. Plan HTML-to-clean-text conversion for descriptions/instructions  
3. Design ingredient extraction strategy from instruction text
4. Map cooking method taxonomy to WordPress terms
5. Plan image migration from Drupal file system
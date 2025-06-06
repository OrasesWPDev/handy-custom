# Product Import Script

This directory contains scripts to import products from CSV files into the WordPress custom post type system.

## Files

- `import-products.php` - Main import script
- `test-import.php` - Test script to validate setup before importing
- `IMPORT_README.md` - This documentation file

## Prerequisites

1. WordPress installation with the Handy Custom plugin active
2. CSV file located at `assets/csv/HC - Product Export - 5.15.25v1 - full export.csv`
3. Proper file permissions for creating the `import-reports/` directory

## Usage

### Step 1: Test the Setup

Before running the actual import, test your setup:

```bash
# Via command line
php test-import.php

# Or via browser
https://yoursite.com/test-import.php?run_test=1
```

This will verify:
- CSV file accessibility
- WordPress integration
- Taxonomy loading
- Field mapping configuration
- Sample data validation

### Step 2: Backup Your Database

**IMPORTANT:** Always backup your database before running the import!

### Step 3: Run the Import

```bash
# Via command line (recommended)
php import-products.php

# Or via browser
https://yoursite.com/import-products.php?run_import=1
```

## What the Script Does

### Data Processing

1. **Validation**: Checks required fields and data formats
2. **Duplicate Prevention**: Prevents importing duplicate products based on:
   - Exact title matches
   - Item number matches
   - UPC number matches
3. **Field Mapping**: Maps CSV columns to WordPress fields:
   - Core fields (title, description)
   - ACF custom fields
   - Taxonomy assignments
4. **Auto-categorization**: Automatically assigns product categories based on title/description analysis

### Import Behavior

- Products are imported as **drafts** for review
- Image fields are **skipped** (to be handled manually)
- Invalid or duplicate products are **skipped** with detailed reporting
- Taxonomy terms are matched to existing terms only

## Reports Generated

After import, detailed reports are created in `import-reports/` directory:

- `successful-imports-[timestamp].json` - Successfully imported products
- `failed-mappings-[timestamp].json` - CSV values that couldn't be mapped to taxonomies
- `taxonomy-mappings-[timestamp].json` - Successful taxonomy mappings
- `validation-errors-[timestamp].json` - Products that failed validation
- `duplicates-skipped-[timestamp].json` - Duplicate products that were skipped
- `import-summary-[timestamp].json` - Overall import summary

## Field Mapping

### Core WordPress Fields
- `product_title` → Post Title
- `description` → Post Content

### ACF Custom Fields
- `sub_header` → Sub Header
- `item_number` → Item Number
- `gtin_number` → GTIN Code
- `upc_number` → UPC Number
- `cooking_instructions` → Cooking Instructions
- `ingredients` → Ingredients
- `carton_size` → Carton Size
- `case_pack_size` → Case Pack Size
- `features_benefits` → Features & Benefits
- `Allergens` → Allergens (radio field)

### Taxonomies (Comma-separated values)
- `grades` → Grade taxonomy
- `market_segments` → Market Segment taxonomy
- `cooking_methods` → Product Cooking Method taxonomy
- `menu_occasions` → Product Menu Occasion taxonomy
- `product_types` → Product Type taxonomy
- `product_sizes` → Size taxonomy

### Skipped Fields
- `brands` - Not defined in current system
- `product_species` - Not defined in current system
- `certifications` - Not defined in current system
- `market_channels` - Not defined in current system
- `country_of_origin` - Not defined in ACF
- `product_id` - Using WordPress auto-generated IDs

## Category Auto-Assignment

Products are automatically assigned to categories based on keyword detection:

- **Crab Meat**: Keywords like "crab meat", "lump", "backfin", "claw"
- **Crab Cakes**: Keywords like "crab cake", "cake"
- **Soft Shell Crab**: Keywords like "soft shell", "soft crab"
- **Shrimp**: Keywords like "shrimp", "tempura"
- **Crab Cake Minis**: Keywords like "mini", "bite"
- **Gluten Free**: Keywords like "gluten free", "gluten-free"
- **Keto Friendly**: Keywords like "keto", "low carb"
- **Plant Based**: Keywords like "plant based", "vegan", "vegetarian"

## Troubleshooting

### Common Issues

1. **"WordPress not found" error**
   - Ensure you're running the script from the WordPress root directory
   - Or copy the script to your WordPress root directory

2. **CSV file not found**
   - Verify the CSV file exists at the expected path
   - Check file permissions

3. **Taxonomy terms not loading**
   - Ensure the Handy Custom plugin is active
   - Verify taxonomies are properly registered

4. **Memory or timeout issues**
   - Increase PHP memory limit: `ini_set('memory_limit', '512M');`
   - Increase execution time: `ini_set('max_execution_time', 300);`
   - Run via command line instead of browser

### Getting Help

Check the generated reports for detailed information about any issues encountered during import. The reports will show exactly what was imported, what failed, and why.

## Post-Import Steps

1. Review imported products in WordPress admin (they'll be in draft status)
2. Add featured images and other media manually
3. Review and publish products as needed
4. Verify taxonomy assignments and category placements
5. Check ACF field values for accuracy

## Security Notes

- The script includes WordPress nonce verification when run via browser
- All data is sanitized before insertion
- Products are imported as drafts to prevent accidental publication
- The script can be safely removed after import is complete
<?php
/**
 * CSV Structure Fix Script
 * 
 * This script manually reconstructs the CSV with proper data alignment
 * based on the patterns observed in the validation output.
 */

$input_csv = __DIR__ . '/assets/csv/cleaned_output.csv';
$output_csv = __DIR__ . '/assets/csv/products_fixed.csv';

if (!file_exists($input_csv)) {
    die("Input CSV file not found: $input_csv\n");
}

echo "Starting CSV structure fix...\n";

$handle = fopen($input_csv, 'r');
$output_handle = fopen($output_csv, 'w');

// Expected headers (22 columns)
$headers = [
    'product_id', 'product_title', 'description', 'upc_number', 'case_number', 
    'item_number', 'cooking_instructions', 'ingredients', 'sub_header', 
    'carton_size', 'case_pack_size', 'features_benefits', 'country_of_origin', 
    'cooking_methods', 'grades', 'market_segments', 'menu_occasions', 
    'brands', 'product_types', 'product_sizes', 'product_species', 'certifications'
];

// Write clean headers to output
fputcsv($output_handle, $headers);

// Skip the malformed header row
$malformed_headers = fgetcsv($handle);

echo "Processing and reconstructing product data...\n";

$products = [];
$current_product = [];
$row_count = 0;
$product_count = 0;

// Read all data first
$all_rows = [];
while (($row = fgetcsv($handle)) !== false) {
    $row_count++;
    $clean_row = array_slice($row, 0, 22); // Only first 22 columns
    
    if (!empty(array_filter($clean_row))) {
        $all_rows[] = $clean_row;
    }
}

echo "Read $row_count data rows\n";

// Process rows in groups - each product seems to span multiple rows
// Based on the pattern, it looks like every 3-4 rows represent one product
$i = 0;
while ($i < count($all_rows)) {
    $product = array_fill(0, 22, ''); // Initialize empty product
    
    // Look for UPC pattern (numeric, 10+ digits) to identify product start
    $current_row = $all_rows[$i];
    
    // Try different patterns to reconstruct the product
    $upc_found = false;
    $title_found = false;
    $description_found = false;
    
    // Scan the next few rows to gather product data
    for ($j = 0; $j < 4 && ($i + $j) < count($all_rows); $j++) {
        $scan_row = $all_rows[$i + $j];
        
        foreach ($scan_row as $col_idx => $value) {
            $value = trim($value);
            if (empty($value)) continue;
            
            // Detect UPC numbers (numeric, 10+ digits)
            if (preg_match('/^\d{10,}$/', $value) && !$upc_found) {
                $product[3] = $value; // upc_number
                $upc_found = true;
                continue;
            }
            
            // Detect product titles (descriptive text, not HTML, not just country)
            if (!$title_found && !preg_match('/^</', $value) && strlen($value) > 15 && 
                !in_array($value, ['Thailand', 'Indonesia', 'India', 'Product of Indonesia', 'Indonesia / U.S.A.'])) {
                $product[1] = $value; // product_title
                $title_found = true;
                continue;
            }
            
            // Detect descriptions (HTML content)
            if (!$description_found && preg_match('/^<p>/', $value)) {
                $product[2] = $value; // description
                $description_found = true;
                continue;
            }
            
            // Detect country of origin
            if (in_array($value, ['Thailand', 'Indonesia', 'India', 'Product of Indonesia', 'Indonesia / U.S.A.'])) {
                $product[12] = $value; // country_of_origin
                continue;
            }
            
            // If we have ingredients content, put it in ingredients
            if (preg_match('/^<p>(crab meat|shrimp|INGREDIENTS:|CRAB MEAT|SHRIMP)/i', $value)) {
                $product[7] = $value; // ingredients
                continue;
            }
        }
    }
    
    // Only save if we found at least a UPC or a title
    if ($upc_found || $title_found) {
        // Generate a product ID if missing
        if (empty($product[0])) {
            $product[0] = $product_count + 1;
        }
        
        fputcsv($output_handle, $product);
        $product_count++;
        
        if ($product_count <= 5) {
            echo "Product $product_count: UPC={$product[3]}, Title={$product[1]}\n";
        }
    }
    
    $i += 3; // Skip to next potential product (assuming 3-row pattern)
}

fclose($handle);
fclose($output_handle);

echo "\nStructure fix complete!\n";
echo "Created $product_count product records\n";
echo "Output file: $output_csv\n";

// Validate the output
echo "\nValidating output...\n";
$validation_handle = fopen($output_csv, 'r');
$validation_headers = fgetcsv($validation_handle);
$valid_products = 0;

while (($row = fgetcsv($validation_handle)) !== false) {
    $upc_number = trim($row[3] ?? '');
    $product_title = trim($row[1] ?? '');
    
    if (!empty($upc_number) || !empty($product_title)) {
        $valid_products++;
    }
}

fclose($validation_handle);

echo "Valid products found: $valid_products\n";
echo "Structure fix completed successfully!\n";
?>
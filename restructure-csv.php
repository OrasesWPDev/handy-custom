<?php
/**
 * CSV Restructuring Script
 * 
 * This script attempts to restructure the malformed CSV data where product information
 * is split across multiple rows instead of being in proper columns.
 */

$input_csv = __DIR__ . '/assets/csv/cleaned_output.csv';
$output_csv = __DIR__ . '/assets/csv/products_restructured.csv';

if (!file_exists($input_csv)) {
    die("Input CSV file not found: $input_csv\n");
}

echo "Starting CSV restructuring...\n";

$handle = fopen($input_csv, 'r');
$output_handle = fopen($output_csv, 'w');

// Read the header row
$headers = fgetcsv($handle);
$clean_headers = array_slice($headers, 0, 22); // Only first 22 columns

// Write clean headers to output
fputcsv($output_handle, $clean_headers);

echo "Headers: " . implode(', ', $clean_headers) . "\n";
echo "Processing rows...\n";

$products = [];
$current_product = array_fill(0, 22, ''); // Initialize with 22 empty values
$row_count = 0;
$product_count = 0;

while (($row = fgetcsv($handle)) !== false) {
    $row_count++;
    $clean_row = array_slice($row, 0, 22); // Only first 22 columns
    
    // Skip completely empty rows
    if (empty(array_filter($clean_row))) {
        continue;
    }
    
    // Check if this might be the start of a new product
    // Look for patterns that indicate a new product (UPC numbers, product IDs, etc.)
    $first_col = trim($clean_row[0] ?? '');
    $second_col = trim($clean_row[1] ?? '');
    
    $is_new_product = false;
    
    // Detect new product based on patterns
    if (
        // UPC number pattern (starts with numbers, 10+ digits)
        (preg_match('/^\d{10,}$/', $first_col) || preg_match('/^\d{10,}$/', $second_col)) ||
        // Product ID pattern 
        (preg_match('/^\d+$/', $first_col) && strlen($first_col) >= 6) ||
        // If we find a clear product title in the expected position
        (!empty($second_col) && !preg_match('/^<p>/', $second_col) && strlen($second_col) > 10 && !preg_match('/^\d+$/', $second_col))
    ) {
        $is_new_product = true;
    }
    
    if ($is_new_product && !empty(array_filter($current_product))) {
        // Save the previous product if it has data
        fputcsv($output_handle, $current_product);
        $product_count++;
        echo "Product $product_count saved\n";
        
        // Reset for new product
        $current_product = array_fill(0, 22, '');
    }
    
    // Merge current row data into current product
    for ($i = 0; $i < 22; $i++) {
        $value = trim($clean_row[$i] ?? '');
        if (!empty($value) && empty($current_product[$i])) {
            $current_product[$i] = $value;
        }
    }
    
    // If this looks like a complete product row, save it immediately
    if ($is_new_product && !empty($current_product[1])) { // Has product title
        fputcsv($output_handle, $current_product);
        $product_count++;
        echo "Complete product $product_count saved (row $row_count)\n";
        $current_product = array_fill(0, 22, '');
    }
}

// Save the last product if it has data
if (!empty(array_filter($current_product))) {
    fputcsv($output_handle, $current_product);
    $product_count++;
    echo "Final product $product_count saved\n";
}

fclose($handle);
fclose($output_handle);

echo "\nRestructuring complete!\n";
echo "Processed $row_count input rows\n";
echo "Created $product_count product records\n";
echo "Output file: $output_csv\n";

// Validate the output
echo "\nValidating output...\n";
$validation_handle = fopen($output_csv, 'r');
$validation_headers = fgetcsv($validation_handle);
$valid_products = 0;
$validation_row = 0;

while (($row = fgetcsv($validation_handle)) !== false) {
    $validation_row++;
    
    // Check if product has required fields
    $product_title = trim($row[1] ?? '');
    $upc_number = trim($row[3] ?? '');
    $item_number = trim($row[5] ?? '');
    
    if (!empty($product_title) && !empty($item_number)) {
        $valid_products++;
        
        if ($validation_row <= 5) {
            echo "Sample product $validation_row: $product_title\n";
        }
    }
}

fclose($validation_handle);

echo "Valid products found: $valid_products\n";
echo "Restructuring completed successfully!\n";
?>
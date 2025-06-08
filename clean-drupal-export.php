<?php
/**
 * Clean Drupal Export CSV
 * 
 * This script cleans the Drupal export CSV that has data spanning multiple lines
 * and converts it to a proper single-line-per-product format using proper CSV parsing.
 */

$input_csv = __DIR__ . '/assets/csv/Handy Crab Products Drupal Export 5.13.25v2.csv';
$output_csv = __DIR__ . '/assets/csv/products_clean.csv';

if (!file_exists($input_csv)) {
    die("Input CSV file not found: $input_csv\n");
}

echo "Starting Drupal export CSV cleanup...\n";

// Read the entire file content
$content = file_get_contents($input_csv);

// Replace problematic characters that break CSV parsing
$content = str_replace(["\r\n", "\r"], "\n", $content);

// Create a temporary file with the cleaned content
$temp_file = tempnam(sys_get_temp_dir(), 'drupal_csv_');
file_put_contents($temp_file, $content);

// Use fgetcsv to properly parse the multi-line CSV
$input_handle = fopen($temp_file, 'r');
$output_handle = fopen($output_csv, 'w');

if (!$input_handle || !$output_handle) {
    die("Error: Cannot open files for processing\n");
}

// Read headers
$headers = fgetcsv($input_handle);
echo "Headers found: " . implode(', ', $headers) . "\n";
echo "Expected columns: " . count($headers) . "\n";

// Write headers to output
fputcsv($output_handle, $headers);

$product_count = 0;
$valid_products = 0;

// Process each row with proper CSV parsing
while (($row = fgetcsv($input_handle)) !== false) {
    $product_count++;
    
    // Ensure row has correct number of columns
    while (count($row) < count($headers)) {
        $row[] = '';
    }
    
    // Trim extra columns if any
    $row = array_slice($row, 0, count($headers));
    
    // Clean HTML from fields and normalize data
    for ($i = 0; $i < count($row); $i++) {
        // Remove HTML tags and normalize whitespace
        $row[$i] = strip_tags($row[$i]);
        $row[$i] = preg_replace('/\s+/', ' ', $row[$i]);
        $row[$i] = trim($row[$i]);
    }
    
    // Validate that this looks like a real product
    $product_title = trim($row[1] ?? '');
    $upc_number = trim($row[3] ?? '');
    
    if (!empty($product_title) && strlen($product_title) > 3 && !preg_match('/^[<>\s]*$/', $product_title)) {
        $valid_products++;
        fputcsv($output_handle, $row);
        
        if ($valid_products <= 5) {
            echo "Product $valid_products: $product_title (UPC: $upc_number)\n";
        }
    }
}

fclose($input_handle);
fclose($output_handle);
unlink($temp_file);

echo "\nCleanup complete!\n";
echo "Processed $product_count total rows\n";
echo "Created $valid_products valid product records\n";
echo "Output file: $output_csv\n";

// Validate the output
echo "\nValidating output...\n";
$validation_handle = fopen($output_csv, 'r');
$validation_headers = fgetcsv($validation_handle);
$final_valid_products = 0;

while (($row = fgetcsv($validation_handle)) !== false) {
    $product_title = trim($row[1] ?? '');
    $item_number = trim($row[5] ?? '');
    $upc_number = trim($row[3] ?? '');
    
    if (!empty($product_title) && strlen($product_title) > 3) {
        $final_valid_products++;
        
        if ($final_valid_products <= 3) {
            echo "Sample product $final_valid_products: $product_title (Item: $item_number, UPC: $upc_number)\n";
        }
    }
}

fclose($validation_handle);

echo "Final valid products: $final_valid_products\n";
echo "Cleanup completed successfully!\n";
?>
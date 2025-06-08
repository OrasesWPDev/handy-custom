<?php
/**
 * CSV Structure Analyzer
 * Helps identify how the data is structured in the cleaned_output.csv
 */

$csv_file = __DIR__ . '/assets/csv/cleaned_output.csv';

if (!file_exists($csv_file)) {
    die("CSV file not found: $csv_file\n");
}

$handle = fopen($csv_file, 'r');
$headers = fgetcsv($handle);

echo "Headers (first 22 columns):\n";
for ($i = 0; $i < 22 && $i < count($headers); $i++) {
    echo ($i + 1) . ". " . $headers[$i] . "\n";
}

echo "\nFirst 10 data rows:\n";
for ($row_num = 1; $row_num <= 10; $row_num++) {
    $row = fgetcsv($handle);
    if (!$row) break;
    
    echo "\nRow $row_num:\n";
    for ($i = 0; $i < 22 && $i < count($row); $i++) {
        $value = trim($row[$i]);
        if (!empty($value)) {
            echo "  [{$headers[$i]}]: " . substr($value, 0, 60) . (strlen($value) > 60 ? '...' : '') . "\n";
        }
    }
}

fclose($handle);
?>
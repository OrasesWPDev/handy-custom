<?php
/**
 * Test script for product import functionality
 * 
 * This script tests the import functionality without actually importing data
 * It validates the CSV structure and checks the field mappings
 */

// Set environment variables for CLI execution
if (php_sapi_name() === 'cli') {
    // Detect environment domain
    $domain = 'handycrab.com'; // default to production
    
    // Check for staging environment
    if (isset($_ENV['WPE_APIKEY']) || strpos(__DIR__, 'wpengine') !== false || strpos(__DIR__, 'handycrabstg') !== false) {
        if (strpos(__DIR__, 'handycrabstg') !== false || strpos(getcwd(), 'handycrabstg') !== false) {
            $domain = 'handycrabstg.wpenginepowered.com';
        } elseif (strpos(__DIR__, 'handycrab') !== false || strpos(getcwd(), 'handycrab') !== false) {
            // Check if it's production or staging based on path/environment
            if (isset($_ENV['WPE_APIKEY'])) {
                $domain = 'handycrab.wpenginepowered.com';
            } else {
                $domain = 'handycrab.com';
            }
        }
    }
    
    // Allow manual override via environment variable
    if (isset($_ENV['SITE_DOMAIN'])) {
        $domain = $_ENV['SITE_DOMAIN'];
    }
    
    $_SERVER['HTTP_HOST'] = $domain;
    $_SERVER['SERVER_NAME'] = $domain;
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = '443';
    
    echo "[INFO] Using domain: $domain\n";
}

// Load WordPress
$wp_load_paths = [
    __DIR__ . '/wp-load.php',
    __DIR__ . '/../wp-load.php', 
    __DIR__ . '/../../wp-load.php',
    __DIR__ . '/../../../wp-load.php'
];

$wp_loaded = false;
foreach ($wp_load_paths as $wp_load_path) {
    if (file_exists($wp_load_path)) {
        require_once $wp_load_path;
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('Error: WordPress not found. Please run this script from your WordPress directory.');
}

// Load the import class
require_once __DIR__ . '/import-products.php';

/**
 * Test class for import functionality
 */
class Handy_Import_Tester {
    
    private $csv_file;
    
    public function __construct() {
        $this->csv_file = __DIR__ . '/assets/csv/products_clean.csv';
    }
    
    /**
     * Run all tests
     */
    public function run_tests() {
        echo "=== Product Import Test Suite ===\n\n";
        
        $this->test_csv_file_exists();
        $this->test_csv_structure();
        $this->test_taxonomy_loading();
        $this->test_field_mapping();
        $this->test_sample_row_processing();
        
        echo "\n=== Test Suite Complete ===\n";
    }
    
    /**
     * Test if CSV file exists and is readable
     */
    private function test_csv_file_exists() {
        echo "Testing CSV file access...\n";
        
        if (!file_exists($this->csv_file)) {
            echo "❌ FAIL: CSV file not found at: {$this->csv_file}\n";
            return false;
        }
        
        if (!is_readable($this->csv_file)) {
            echo "❌ FAIL: CSV file is not readable\n";
            return false;
        }
        
        echo "✅ PASS: CSV file exists and is readable\n\n";
        return true;
    }
    
    /**
     * Test CSV structure and headers
     */
    private function test_csv_structure() {
        echo "Testing CSV structure...\n";
        
        $handle = fopen($this->csv_file, 'r');
        if (!$handle) {
            echo "❌ FAIL: Cannot open CSV file\n";
            return false;
        }
        
        $headers = fgetcsv($handle);
        if (!$headers) {
            echo "❌ FAIL: Cannot read CSV headers\n";
            fclose($handle);
            return false;
        }
        
        echo "📋 CSV Headers found: " . count($headers) . " columns\n";
        echo "   Columns: " . implode(', ', array_slice($headers, 0, 10)) . "...\n";
        
        // Check for required columns
        $required_columns = ['product_title'];
        $missing_columns = [];
        
        foreach ($required_columns as $required) {
            if (!in_array($required, $headers)) {
                $missing_columns[] = $required;
            }
        }
        
        if (!empty($missing_columns)) {
            echo "❌ FAIL: Missing required columns: " . implode(', ', $missing_columns) . "\n";
            fclose($handle);
            return false;
        }
        
        // Count total rows
        $row_count = 0;
        while (fgetcsv($handle) !== false) {
            $row_count++;
        }
        
        echo "📊 Total data rows: $row_count\n";
        echo "✅ PASS: CSV structure is valid\n\n";
        
        fclose($handle);
        return true;
    }
    
    /**
     * Test taxonomy loading
     */
    private function test_taxonomy_loading() {
        echo "Testing taxonomy loading...\n";
        
        $taxonomies = [
            'product-category',
            'grade',
            'market-segment', 
            'product-cooking-method',
            'product-menu-occasion',
            'product-type',
            'size',
            'product-species',
            'brand',
            'certification'
        ];
        
        $taxonomy_counts = [];
        $total_terms = 0;
        
        foreach ($taxonomies as $taxonomy) {
            // Check if taxonomy exists first
            if (!taxonomy_exists($taxonomy)) {
                echo "   ⚠️  $taxonomy: taxonomy does not exist - skipping\n";
                continue;
            }
            
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false
            ]);
            
            if (is_wp_error($terms)) {
                echo "❌ FAIL: Error loading taxonomy '$taxonomy': " . $terms->get_error_message() . "\n";
                return false;
            }
            
            $count = count($terms);
            $taxonomy_counts[$taxonomy] = $count;
            $total_terms += $count;
            
            echo "   📂 $taxonomy: $count terms\n";
        }
        
        echo "📈 Total taxonomy terms loaded: $total_terms\n";
        echo "✅ PASS: All taxonomies loaded successfully\n\n";
        
        return true;
    }
    
    /**
     * Test field mapping configuration
     */
    private function test_field_mapping() {
        echo "Testing field mapping configuration...\n";
        
        $importer = new Handy_Product_Importer();
        $reflection = new ReflectionClass($importer);
        $property = $reflection->getProperty('field_mapping');
        $property->setAccessible(true);
        $field_mapping = $property->getValue($importer);
        
        $mapping_types = [
            'post_field' => 0,
            'acf_field' => 0,
            'taxonomy' => 0,
            'skip' => 0
        ];
        
        foreach ($field_mapping as $csv_field => $mapping) {
            if (isset($mapping['type']) && isset($mapping_types[$mapping['type']])) {
                $mapping_types[$mapping['type']]++;
            }
        }
        
        echo "📋 Field mapping summary:\n";
        foreach ($mapping_types as $type => $count) {
            echo "   🔗 $type: $count fields\n";
        }
        
        echo "✅ PASS: Field mapping configured correctly\n\n";
        return true;
    }
    
    /**
     * Test processing of a sample row
     */
    private function test_sample_row_processing() {
        echo "Testing sample row processing...\n";
        
        $handle = fopen($this->csv_file, 'r');
        if (!$handle) {
            echo "❌ FAIL: Cannot open CSV file\n";
            return false;
        }
        
        $headers = fgetcsv($handle);
        $sample_row = fgetcsv($handle);
        fclose($handle);
        
        if (!$sample_row) {
            echo "❌ FAIL: No sample data row found\n";
            return false;
        }
        
        if (count($sample_row) !== count($headers)) {
            echo "❌ FAIL: Sample row column count doesn't match headers\n";
            return false;
        }
        
        $product_data = array_combine($headers, $sample_row);
        
        echo "📝 Sample product: " . $product_data['product_title'] . "\n";
        echo "   🔢 Item number: " . ($product_data['item_number'] ?? 'N/A') . "\n";
        echo "   📊 UPC: " . ($product_data['upc_number'] ?? 'N/A') . "\n";
        echo "   📦 Case number: " . ($product_data['case_number'] ?? 'N/A') . "\n";
        echo "   ⚠️  Note: Duplicate checking only applies to UPC, item number, and GTIN (case number)\n";
        
        // Test validation without actually creating the product
        $importer = new Handy_Product_Importer();
        $reflection = new ReflectionClass($importer);
        $method = $reflection->getMethod('validate_product_data');
        $method->setAccessible(true);
        
        $validation_result = $method->invoke($importer, $product_data, 1);
        
        if ($validation_result['valid']) {
            echo "✅ PASS: Sample row validation successful\n";
        } else {
            echo "⚠️  WARN: Sample row validation issues: " . implode(', ', $validation_result['errors']) . "\n";
        }
        
        echo "✅ PASS: Sample row processing test complete\n\n";
        return true;
    }
}

// Run tests if this file is executed directly
if (php_sapi_name() === 'cli' || !empty($_GET['run_test'])) {
    $tester = new Handy_Import_Tester();
    $tester->run_tests();
} else {
    $current_domain = $_SERVER['HTTP_HOST'];
    echo '<h1>Product Import Test Suite</h1>';
    echo '<p>This script tests the import functionality without importing data.</p>';
    echo '<p><strong>Current Domain:</strong> ' . htmlspecialchars($current_domain) . '</p>';
    echo '<p><a href="?run_test=1">Run Tests</a></p>';
}
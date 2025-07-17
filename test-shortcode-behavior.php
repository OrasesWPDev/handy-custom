<?php
/**
 * Test file to verify shortcode behavior across all URL patterns
 * 
 * This file tests the expected behavior documented in the requirements:
 * 1. Categories with subcategories should show subcategory cards
 * 2. Categories without subcategories should show product list
 * 3. Subcategories should always show product list
 * 4. Filter shortcodes should be context-aware
 * 
 * @package Handy_Custom
 */

// This is a test file - do not include in production
if (!defined('TESTING_SHORTCODES')) {
    exit('This is a test file for development only');
}

/**
 * Test cases for shortcode behavior
 */
$test_cases = array(
    // Top level - should show 6 category cards
    array(
        'url' => '/products/',
        'shortcode' => '[products]',
        'expected' => 'category_cards',
        'expected_count' => 6,
        'description' => 'Top-level categories: Shrimp, Appetizers, Dietary Alternatives, Crab Cakes, Crab Meat, Soft Shell Crab'
    ),
    
    // Categories with subcategories - should show subcategory cards
    array(
        'url' => '/products/appetizers/',
        'shortcode' => '[products category="appetizers"]',
        'expected' => 'category_cards',
        'expected_count' => 4,
        'description' => 'Appetizers subcategories: Crab Cake Minis, Specialty, Soft Crab, Shrimp (appetizer-shrimp)'
    ),
    array(
        'url' => '/products/dietary-alternatives/',
        'shortcode' => '[products category="dietary-alternatives"]',
        'expected' => 'category_cards',
        'expected_count' => 3,
        'description' => 'Dietary Alternatives subcategories: Gluten Free, Keto Friendly, Plant Based'
    ),
    
    // Categories without subcategories - should show product list
    array(
        'url' => '/products/crab-cakes/',
        'shortcode' => '[products category="crab-cakes"]',
        'expected' => 'product_list',
        'description' => 'Crab Cakes products (no subcategories)'
    ),
    array(
        'url' => '/products/crab-meat/',
        'shortcode' => '[products category="crab-meat"]',
        'expected' => 'product_list',
        'description' => 'Crab Meat products (no subcategories)'
    ),
    array(
        'url' => '/products/soft-shell-crab/',
        'shortcode' => '[products category="soft-shell-crab"]',
        'expected' => 'product_list',
        'description' => 'Soft Shell Crab products (no subcategories)'
    ),
    array(
        'url' => '/products/shrimp/',
        'shortcode' => '[products category="shrimp"]',
        'expected' => 'product_list',
        'description' => 'Shrimp products (no subcategories)'
    ),
    
    // Subcategories - should always show product list
    array(
        'url' => '/products/appetizers/appetizer-shrimp/',
        'shortcode' => '[products subcategory="appetizer-shrimp"]',
        'expected' => 'product_list',
        'description' => 'Appetizer Shrimp products (subcategory)'
    ),
    array(
        'url' => '/products/appetizers/crab-cake-minis/',
        'shortcode' => '[products subcategory="crab-cake-minis"]',
        'expected' => 'product_list',
        'description' => 'Crab Cake Minis products (subcategory)'
    ),
    array(
        'url' => '/products/appetizers/soft-crab/',
        'shortcode' => '[products subcategory="soft-crab"]',
        'expected' => 'product_list',
        'description' => 'Soft Crab appetizer products (subcategory)'
    ),
    array(
        'url' => '/products/appetizers/specialty/',
        'shortcode' => '[products subcategory="specialty"]',
        'expected' => 'product_list',
        'description' => 'Specialty appetizer products (subcategory)'
    ),
    array(
        'url' => '/products/dietary-alternatives/gluten-free/',
        'shortcode' => '[products subcategory="gluten-free"]',
        'expected' => 'product_list',
        'description' => 'Gluten Free products (subcategory)'
    ),
    array(
        'url' => '/products/dietary-alternatives/keto-friendly/',
        'shortcode' => '[products subcategory="keto-friendly"]',
        'expected' => 'product_list',
        'description' => 'Keto Friendly products (subcategory)'
    ),
    array(
        'url' => '/products/dietary-alternatives/plant-based/',
        'shortcode' => '[products subcategory="plant-based"]',
        'expected' => 'product_list',
        'description' => 'Plant Based products (subcategory)'
    ),
    
    // Special case - product catalog
    array(
        'url' => '/products/product-catalog/',
        'shortcode' => '[products display="list"]',
        'expected' => 'product_list',
        'description' => 'Complete product catalog (all products)'
    ),
);

/**
 * Filter shortcode test cases
 */
$filter_test_cases = array(
    array(
        'shortcode' => '[filter-products]',
        'expected' => 'all_filters',
        'description' => 'All available product taxonomy filters'
    ),
    array(
        'shortcode' => '[filter-products category="appetizers"]',
        'expected' => 'context_filters',
        'description' => 'Filters relevant to appetizers category and subcategories'
    ),
    array(
        'shortcode' => '[filter-products subcategory="crab-cake-minis"]',
        'expected' => 'context_filters',
        'description' => 'Filters relevant to crab-cake-minis subcategory only'
    ),
    array(
        'shortcode' => '[filter-products category="dietary-alternatives"]',
        'expected' => 'context_filters',
        'description' => 'Filters relevant to dietary-alternatives category and subcategories'
    ),
);

/**
 * Expected URL patterns for subcategory cards
 */
$expected_urls = array(
    'appetizers' => array(
        'crab-cake-minis' => '/products/appetizers/crab-cake-minis/',
        'specialty' => '/products/appetizers/specialty/',
        'soft-crab' => '/products/appetizers/soft-crab/',
        'appetizer-shrimp' => '/products/appetizers/appetizer-shrimp/',
    ),
    'dietary-alternatives' => array(
        'gluten-free' => '/products/dietary-alternatives/gluten-free/',
        'keto-friendly' => '/products/dietary-alternatives/keto-friendly/',
        'plant-based' => '/products/dietary-alternatives/plant-based/',
    ),
);

/**
 * Test results output
 */
function output_test_results($test_cases, $filter_test_cases, $expected_urls) {
    echo "<h1>Shortcode Behavior Test Results</h1>\n";
    
    echo "<h2>Products Shortcode Tests</h2>\n";
    echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
    echo "<tr><th>URL</th><th>Shortcode</th><th>Expected</th><th>Description</th></tr>\n";
    
    foreach ($test_cases as $test) {
        $count_info = isset($test['expected_count']) ? " ({$test['expected_count']} items)" : '';
        echo "<tr>\n";
        echo "<td>{$test['url']}</td>\n";
        echo "<td><code>{$test['shortcode']}</code></td>\n";
        echo "<td>{$test['expected']}{$count_info}</td>\n";
        echo "<td>{$test['description']}</td>\n";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
    
    echo "<h2>Filter Shortcode Tests</h2>\n";
    echo "<table border='1' cellpadding='5' cellspacing='0'>\n";
    echo "<tr><th>Shortcode</th><th>Expected</th><th>Description</th></tr>\n";
    
    foreach ($filter_test_cases as $test) {
        echo "<tr>\n";
        echo "<td><code>{$test['shortcode']}</code></td>\n";
        echo "<td>{$test['expected']}</td>\n";
        echo "<td>{$test['description']}</td>\n";
        echo "</tr>\n";
    }
    
    echo "</table>\n";
    
    echo "<h2>Expected Subcategory Card URLs</h2>\n";
    foreach ($expected_urls as $category => $subcategories) {
        echo "<h3>Category: {$category}</h3>\n";
        echo "<ul>\n";
        foreach ($subcategories as $subcategory => $url) {
            echo "<li><strong>{$subcategory}</strong> → <code>{$url}</code></li>\n";
        }
        echo "</ul>\n";
    }
    
    echo "<h2>Key Behavioral Rules</h2>\n";
    echo "<ul>\n";
    echo "<li><strong>Top-level categories with subcategories</strong> → Show subcategory cards</li>\n";
    echo "<li><strong>Top-level categories without subcategories</strong> → Show product list</li>\n";
    echo "<li><strong>Subcategories</strong> → Always show product list</li>\n";
    echo "<li><strong>Filter shortcodes</strong> → Context-sensitive filter options</li>\n";
    echo "<li><strong>Subcategory card URLs</strong> → Hierarchical /products/category/subcategory/ format</li>\n";
    echo "</ul>\n";
}

// Output test results if this file is accessed directly during testing
if (defined('TESTING_SHORTCODES')) {
    output_test_results($test_cases, $filter_test_cases, $expected_urls);
}
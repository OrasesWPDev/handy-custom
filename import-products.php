<?php
/**
 * Product Import Script
 * 
 * Imports products from CSV file into WordPress custom post type 'product'
 * Generates detailed reports of successful imports and failed mappings
 * 
 * Usage: Run this file from WordPress root directory or via browser
 * 
 * @package Handy_Custom
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

// Load WordPress if not already loaded
if (!defined('ABSPATH') && !defined('WP_CLI')) {
    // Load WordPress if running standalone
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
}

/**
 * Product Import Class
 */
class Handy_Product_Importer {
    
    /**
     * CSV file path
     */
    private $csv_file;
    
    /**
     * Import reports
     */
    private $reports = [
        'successful_imports' => [],
        'failed_mappings' => [],
        'skipped_fields' => [],
        'taxonomy_mappings' => [],
        'duplicates_skipped' => [],
        'validation_errors' => [],
        'errors' => []
    ];
    
    /**
     * Existing taxonomy terms cache
     */
    private $taxonomy_terms = [];
    
    /**
     * Field mapping between CSV and WordPress
     */
    private $field_mapping = [];
    
    /**
     * Constructor
     */
    public function __construct($csv_file = null, $start_row = 1, $batch_size = 250) {
        // Increase memory and execution limits for large imports
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 600); // 10 minutes
        
        $this->csv_file = $csv_file ?: __DIR__ . '/assets/csv/Product_CSV_6.15.25_FIXED_for_import.csv';
        $this->start_row = $start_row;
        $this->batch_size = $batch_size;
        $this->init_field_mapping();
        $this->load_existing_taxonomies();
    }
    
    private $start_row = 1;
    private $batch_size = 250;
    
    /**
     * Initialize field mapping between CSV columns and WordPress fields
     */
    private function init_field_mapping() {
        $this->field_mapping = [
            // Core WordPress fields
            'product_title' => [
                'type' => 'post_field',
                'wp_field' => 'post_title'
            ],
            'description' => [
                'type' => 'post_field', 
                'wp_field' => 'post_content'
            ],
            
            // ACF Custom Fields
            'sub_header' => [
                'type' => 'acf_field',
                'acf_name' => 'sub_header'
            ],
            'item_number' => [
                'type' => 'acf_field',
                'acf_name' => 'item_number'
            ],
            'case_number' => [
                'type' => 'acf_field',
                'acf_name' => 'gtin_code'
            ],
            'upc_number' => [
                'type' => 'acf_field',
                'acf_name' => 'upc_number'
            ],
            'cooking_instructions' => [
                'type' => 'acf_field',
                'acf_name' => 'cooking_instructions'
            ],
            'ingredients' => [
                'type' => 'acf_field',
                'acf_name' => 'ingredients'
            ],
            'carton_size' => [
                'type' => 'acf_field',
                'acf_name' => 'carton_size'
            ],
            'case_pack_size' => [
                'type' => 'acf_field',
                'acf_name' => 'case_pack_size'
            ],
            'features_benefits' => [
                'type' => 'acf_field',
                'acf_name' => 'features_benefits'
            ],
            'country_of_origin' => [
                'type' => 'acf_field',
                'acf_name' => 'country_of_origin'
            ],
            
            // Missing ACF Fields from the CSV
            'allergens' => [
                'type' => 'acf_field_special',
                'acf_name' => 'allergens',
                'handler' => 'allergen_mapping'
            ],
            'product_sizes' => [
                'type' => 'acf_field',
                'acf_name' => 'product_size'
            ],
            
            // Taxonomies (comma-separated values in CSV)
            'grades' => [
                'type' => 'taxonomy',
                'taxonomy' => 'grade'
            ],
            'market_segments' => [
                'type' => 'taxonomy',
                'taxonomy' => 'market-segment'
            ],
            'cooking_methods' => [
                'type' => 'taxonomy',
                'taxonomy' => 'product-cooking-method'
            ],
            'menu_occasions' => [
                'type' => 'taxonomy',
                'taxonomy' => 'product-menu-occasion'
            ],
            'product_types' => [
                'type' => 'taxonomy',
                'taxonomy' => 'product-type'
            ],
            'product_sizes' => [
                'type' => 'taxonomy',
                'taxonomy' => 'size'
            ],
            'product_species' => [
                'type' => 'taxonomy',
                'taxonomy' => 'product-species'
            ],
            'brands' => [
                'type' => 'taxonomy',
                'taxonomy' => 'brand'
            ],
            'certifications' => [
                'type' => 'taxonomy',
                'taxonomy' => 'certification'
            ],
            
            // Skipped fields
            'product_id' => ['type' => 'skip', 'reason' => 'Using WordPress auto-generated post IDs']
        ];
    }
    
    /**
     * Load existing taxonomy terms for mapping
     */
    private function load_existing_taxonomies() {
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
        
        foreach ($taxonomies as $taxonomy) {
            // Check if taxonomy exists first
            if (!taxonomy_exists($taxonomy)) {
                $this->log_message("Warning: Taxonomy '$taxonomy' does not exist - skipping");
                continue;
            }
            
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false
            ]);
            
            if (!is_wp_error($terms)) {
                $this->taxonomy_terms[$taxonomy] = [];
                foreach ($terms as $term) {
                    // Store both by slug and name for flexible matching
                    $this->taxonomy_terms[$taxonomy][$term->slug] = $term;
                    $this->taxonomy_terms[$taxonomy][strtolower($term->name)] = $term;
                }
            }
        }
        
        $this->log_message("Loaded existing taxonomy terms for mapping");
    }
    
    /**
     * Run the import process
     */
    public function run_import() {
        $this->log_message("=== Starting Product Import Process ===");
        
        // Set memory and time limits for large imports
        ini_set('memory_limit', '512M');
        if (php_sapi_name() === 'cli') {
            ini_set('max_execution_time', 0); // No limit for CLI
        } else {
            ini_set('max_execution_time', 300); // 5 minutes for web
        }
        
        // Check if ACF is available
        if (!function_exists('update_field')) {
            $this->add_error("ACF (Advanced Custom Fields) plugin is not active or available");
            return false;
        }
        
        // Validate CSV file
        if (!file_exists($this->csv_file)) {
            $this->add_error("CSV file not found: " . $this->csv_file);
            return false;
        }
        
        // Create reports directory
        $reports_dir = __DIR__ . '/import-reports';
        if (!file_exists($reports_dir)) {
            wp_mkdir_p($reports_dir);
        }
        
        // Process CSV
        $this->process_csv();
        
        // Generate reports
        $this->generate_reports();
        
        $this->log_message("=== Import Process Complete ===");
        return true;
    }
    
    /**
     * Process the CSV file
     */
    private function process_csv() {
        $handle = fopen($this->csv_file, 'r');
        if (!$handle) {
            $this->add_error("Unable to open CSV file");
            return;
        }
        
        // Read header row
        $headers = fgetcsv($handle);
        if (!$headers) {
            $this->add_error("Unable to read CSV headers");
            fclose($handle);
            return;
        }
        
        $this->log_message("CSV Headers: " . implode(', ', $headers));
        
        $row_count = 0;
        $imported_count = 0;
        $processed_count = 0;
        
        // Skip to start row if batch processing
        while ($row_count < $this->start_row - 1 && ($row = fgetcsv($handle)) !== false) {
            $row_count++;
        }
        
        // Process each row
        while (($row = fgetcsv($handle)) !== false) {
            $row_count++;
            $processed_count++;
            
            // Stop if we've processed the batch size
            if ($processed_count > $this->batch_size) {
                $this->log_message("Batch limit reached. Processed $processed_count rows.");
                break;
            }
            
            // Skip empty rows
            if (empty(array_filter($row))) {
                continue;
            }
            
            if (count($row) !== count($headers)) {
                $this->add_error("Row $row_count: Column count mismatch - expected " . count($headers) . ", got " . count($row));
                continue;
            }
            
            // Combine headers with row data
            $product_data = array_combine($headers, $row);
            
            // Skip if no product title
            if (empty(trim($product_data['product_title']))) {
                $this->add_error("Row $row_count: Empty product title, skipping");
                continue;
            }
            
            // Import product
            $result = $this->import_product($product_data, $row_count);
            if ($result) {
                $imported_count++;
            }
        }
        
        fclose($handle);
        
        $this->log_message("Processed $row_count rows, imported $imported_count products");
    }
    
    /**
     * Import a single product
     */
    private function import_product($product_data, $row_number) {
        $this->log_message("Processing row $row_number: " . $product_data['product_title']);
        
        // Validate required fields
        $validation_result = $this->validate_product_data($product_data, $row_number);
        if (!$validation_result['valid']) {
            $this->reports['validation_errors'][] = [
                'row_number' => $row_number,
                'title' => $product_data['product_title'],
                'errors' => $validation_result['errors']
            ];
            return false;
        }
        
        // Check for duplicates based on UPC, item number, and GTIN only (not title)
        $duplicate_check = $this->check_for_duplicates($product_data, $row_number);
        if ($duplicate_check['is_duplicate']) {
            $this->reports['duplicates_skipped'][] = [
                'row_number' => $row_number,
                'title' => $product_data['product_title'],
                'existing_post_id' => $duplicate_check['existing_post_id'],
                'reason' => $duplicate_check['reason']
            ];
            $this->log_message("Skipping duplicate: " . $product_data['product_title']);
            return false;
        }
        
        // Prepare post data
        $post_data = [
            'post_type' => 'product',
            'post_status' => 'draft', // Import as draft for review
            'post_title' => sanitize_text_field($product_data['product_title']),
            'post_content' => wp_kses_post($product_data['description'])
        ];
        
        // Insert post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            $this->add_error("Row $row_number: Failed to create post - " . $post_id->get_error_message());
            return false;
        }
        
        $import_result = [
            'row_number' => $row_number,
            'post_id' => $post_id,
            'title' => $product_data['product_title'],
            'acf_fields' => [],
            'taxonomies' => [],
            'skipped_fields' => []
        ];
        
        // Process ACF fields and taxonomies
        foreach ($product_data as $csv_field => $csv_value) {
            if (!isset($this->field_mapping[$csv_field])) {
                continue;
            }
            
            $mapping = $this->field_mapping[$csv_field];
            
            switch ($mapping['type']) {
                case 'acf_field':
                    $this->process_acf_field($post_id, $mapping['acf_name'], $csv_value, $import_result);
                    break;
                    
                case 'acf_field_special':
                    $this->process_special_acf_field($post_id, $mapping, $csv_value, $import_result);
                    break;
                    
                case 'taxonomy':
                    $this->process_taxonomy_field($post_id, $mapping['taxonomy'], $csv_value, $import_result);
                    break;
                    
                case 'skip':
                    $import_result['skipped_fields'][] = [
                        'field' => $csv_field,
                        'value' => $csv_value,
                        'reason' => $mapping['reason']
                    ];
                    break;
            }
        }
        
        // Add category mapping based on product title and content
        $this->auto_assign_categories($post_id, $product_data, $import_result);
        
        $this->reports['successful_imports'][] = $import_result;
        return true;
    }
    
    /**
     * Process ACF field
     */
    private function process_acf_field($post_id, $field_name, $csv_value, &$import_result) {
        $cleaned_value = trim($csv_value);
        
        if (empty($cleaned_value)) {
            return;
        }
        
        update_field($field_name, $cleaned_value, $post_id);
        
        $import_result['acf_fields'][] = [
            'field_name' => $field_name,
            'value' => $cleaned_value
        ];
    }
    
    /**
     * Process special ACF fields with custom handlers
     */
    private function process_special_acf_field($post_id, $mapping, $csv_value, &$import_result) {
        $cleaned_value = trim($csv_value);
        
        if (empty($cleaned_value)) {
            return;
        }
        
        $field_name = $mapping['acf_name'];
        $handler = $mapping['handler'];
        
        switch ($handler) {
            case 'allergen_mapping':
                $cleaned_value = $this->map_allergen_value($cleaned_value);
                break;
            default:
                // No special handling, use as-is
                break;
        }
        
        update_field($field_name, $cleaned_value, $post_id);
        
        $import_result['acf_fields'][] = [
            'field_name' => $field_name,
            'value' => $cleaned_value,
            'handler' => $handler
        ];
    }
    
    /**
     * Process taxonomy field
     */
    private function process_taxonomy_field($post_id, $taxonomy, $csv_value, &$import_result) {
        if (empty(trim($csv_value))) {
            return;
        }
        
        // Split pipe-separated values (primary) or comma-separated values (fallback)
        if (strpos($csv_value, '|') !== false) {
            $values = array_map('trim', explode('|', $csv_value));
        } else {
            $values = array_map('trim', explode(',', $csv_value));
        }
        $matched_terms = [];
        $unmatched_values = [];
        
        foreach ($values as $value) {
            $term = $this->find_matching_term($taxonomy, $value);
            if ($term) {
                $matched_terms[] = $term->term_id;
                $this->reports['taxonomy_mappings'][] = [
                    'taxonomy' => $taxonomy,
                    'csv_value' => $value,
                    'matched_term' => $term->name,
                    'term_slug' => $term->slug
                ];
            } else {
                $unmatched_values[] = $value;
                $this->reports['failed_mappings'][] = [
                    'taxonomy' => $taxonomy,
                    'csv_value' => $value,
                    'reason' => 'No matching term found'
                ];
            }
        }
        
        // Assign matched terms to post
        if (!empty($matched_terms)) {
            wp_set_object_terms($post_id, $matched_terms, $taxonomy);
            
            $import_result['taxonomies'][] = [
                'taxonomy' => $taxonomy,
                'assigned_terms' => $matched_terms,
                'unmatched_values' => $unmatched_values
            ];
        }
    }
    
    /**
     * Auto-assign product categories based on title and content analysis
     */
    private function auto_assign_categories($post_id, $product_data, &$import_result) {
        $title_lower = strtolower($product_data['product_title']);
        $description_lower = strtolower($product_data['description']);
        $combined_text = $title_lower . ' ' . $description_lower;
        
        $assigned_categories = [];
        
        // Category detection rules
        $category_rules = [
            'crab-meat' => ['crab meat', 'lump', 'backfin', 'claw'],
            'crab-cakes' => ['crab cake', 'cake'],
            'soft-shell-crab' => ['soft shell', 'soft crab'],
            'appetizer-shrimp' => ['shrimp', 'tempura'],
            'crab-cake-minis' => ['mini', 'bite'],
            'gluten-free' => ['gluten free', 'gluten-free'],
            'keto-friendly' => ['keto', 'low carb'],
            'plant-based' => ['plant based', 'vegan', 'vegetarian']
        ];
        
        foreach ($category_rules as $category_slug => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($combined_text, $keyword) !== false) {
                    // Get the term
                    if (isset($this->taxonomy_terms['product-category'][$category_slug])) {
                        $term = $this->taxonomy_terms['product-category'][$category_slug];
                        $assigned_categories[] = $term->term_id;
                        
                        // Also assign parent category if this is a subcategory
                        if ($term->parent > 0) {
                            $assigned_categories[] = $term->parent;
                        }
                        
                        break; // Only assign one subcategory per product
                    }
                }
            }
        }
        
        // Assign categories
        if (!empty($assigned_categories)) {
            wp_set_object_terms($post_id, array_unique($assigned_categories), 'product-category');
            
            $import_result['taxonomies'][] = [
                'taxonomy' => 'product-category',
                'assigned_terms' => array_unique($assigned_categories),
                'method' => 'auto-detected'
            ];
        }
    }
    
    /**
     * Find matching taxonomy term
     */
    private function find_matching_term($taxonomy, $value) {
        if (!isset($this->taxonomy_terms[$taxonomy])) {
            return null;
        }
        
        $search_value = strtolower(trim($value));
        
        // Direct slug match
        if (isset($this->taxonomy_terms[$taxonomy][$search_value])) {
            return $this->taxonomy_terms[$taxonomy][$search_value];
        }
        
        // Fuzzy matching for common variations
        $fuzzy_matches = [
            'food service' => 'food-service',
            'retail & club' => 'retail-and-club',
            'air fry' => 'air-fry',
            'deep fry' => 'deep-fry',
            'ready to eat' => 'ready-to-eat'
        ];
        
        if (isset($fuzzy_matches[$search_value])) {
            $slug = $fuzzy_matches[$search_value];
            if (isset($this->taxonomy_terms[$taxonomy][$slug])) {
                return $this->taxonomy_terms[$taxonomy][$slug];
            }
        }
        
        return null;
    }
    
    /**
     * Validate product data before import
     */
    private function validate_product_data($product_data, $row_number) {
        $errors = [];
        // Only require product_title - other fields can be empty for initial import
        $required_fields = ['product_title'];
        
        // Check required fields
        foreach ($required_fields as $field) {
            if (empty(trim($product_data[$field]))) {
                $errors[] = "Missing required field: $field";
            }
        }
        
        // Validate title length
        if (strlen($product_data['product_title']) > 200) {
            $errors[] = "Product title too long (max 200 characters)";
        }
        
        // Validate numeric fields only if they have values
        $numeric_fields = ['item_number', 'upc_number', 'case_number'];
        foreach ($numeric_fields as $field) {
            $value = trim($product_data[$field] ?? '');
            if (!empty($value)) {
                // Allow some formatting in numbers but check if basically numeric
                $clean_value = str_replace(['/', '-', ' '], '', $value);
                if (!is_numeric($clean_value) && !preg_match('/^[\d\s\-\/]+$/', $value)) {
                    $errors[] = "Invalid format for $field: should be numeric";
                }
            }
        }
        
        // Note: Duplicate checking is handled separately in check_for_duplicates method
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check for duplicate products by UPC, item number, and GTIN only
     */
    private function check_for_duplicates($product_data, $row_number) {
        // Title-based duplicate checking removed - only check numeric identifiers
        
        // Check by item number if provided
        if (!empty(trim($product_data['item_number']))) {
            $existing_posts = get_posts([
                'post_type' => 'product',
                'meta_query' => [
                    [
                        'key' => 'item_number',
                        'value' => trim($product_data['item_number']),
                        'compare' => '='
                    ]
                ],
                'post_status' => ['publish', 'draft', 'private'],
                'numberposts' => 1
            ]);
            
            if (!empty($existing_posts)) {
                return [
                    'is_duplicate' => true,
                    'existing_post_id' => $existing_posts[0]->ID,
                    'reason' => 'Item number match'
                ];
            }
        }
        
        // Check by UPC if provided
        if (!empty(trim($product_data['upc_number']))) {
            $existing_posts = get_posts([
                'post_type' => 'product',
                'meta_query' => [
                    [
                        'key' => 'upc_number',
                        'value' => trim($product_data['upc_number']),
                        'compare' => '='
                    ]
                ],
                'post_status' => ['publish', 'draft', 'private'],
                'numberposts' => 1
            ]);
            
            if (!empty($existing_posts)) {
                return [
                    'is_duplicate' => true,
                    'existing_post_id' => $existing_posts[0]->ID,
                    'reason' => 'UPC number match'
                ];
            }
        }
        
        // Check by case number if provided
        if (!empty(trim($product_data['case_number']))) {
            $existing_posts = get_posts([
                'post_type' => 'product',
                'meta_query' => [
                    [
                        'key' => 'gtin_code',
                        'value' => trim($product_data['case_number']),
                        'compare' => '='
                    ]
                ],
                'post_status' => ['publish', 'draft', 'private'],
                'numberposts' => 1
            ]);
            
            if (!empty($existing_posts)) {
                return [
                    'is_duplicate' => true,
                    'existing_post_id' => $existing_posts[0]->ID,
                    'reason' => 'Case number match'
                ];
            }
        }
        
        return [
            'is_duplicate' => false
        ];
    }
    
    /**
     * Map allergen values to ACF radio options
     */
    private function map_allergen_value($csv_value) {
        $csv_value = trim($csv_value);
        
        if (empty($csv_value)) {
            return 'None';
        }
        
        // Handle pipe-separated allergens like "Wheat|Soy"
        $allergen_values = array_map('trim', explode('|', $csv_value));
        
        $allergen_mapping = [
            'none' => 'None',
            'milk' => 'Milk',
            'eggs' => 'Eggs', 
            'fish' => 'Fish (e.g., bass, flounder, cod)',
            'shellfish' => 'Crustacean Shellfish (e.g., crab, lobster, shrimp)',
            'crustacean shellfish' => 'Crustacean Shellfish (e.g., crab, lobster, shrimp)',
            'tree nuts' => 'Tree Nuts (e.g., almonds, walnuts, pecans)',
            'peanuts' => 'Peanuts',
            'wheat' => 'Wheat',
            'soybeans' => 'Soybeans',
            'soy' => 'Soybeans',
            'sesame' => 'Sesame'
        ];
        
        // Find the first valid allergen match
        foreach ($allergen_values as $allergen) {
            $allergen_lower = strtolower(trim($allergen));
            if (isset($allergen_mapping[$allergen_lower])) {
                return $allergen_mapping[$allergen_lower];
            }
        }
        
        // If no match found, return 'None'
        return 'None';
    }
    
    /**
     * Generate import reports
     */
    private function generate_reports() {
        $timestamp = date('Y-m-d_H-i-s');
        $reports_dir = __DIR__ . '/import-reports';
        
        // Successful imports report
        $this->write_report_file(
            $reports_dir . "/successful-imports-{$timestamp}.json",
            $this->reports['successful_imports'],
            'Successful Product Imports'
        );
        
        // Failed mappings report
        $this->write_report_file(
            $reports_dir . "/failed-mappings-{$timestamp}.json", 
            $this->reports['failed_mappings'],
            'Failed Taxonomy Mappings'
        );
        
        // Taxonomy mappings report
        $this->write_report_file(
            $reports_dir . "/taxonomy-mappings-{$timestamp}.json",
            $this->reports['taxonomy_mappings'],
            'Successful Taxonomy Mappings'
        );
        
        // Validation errors report
        $this->write_report_file(
            $reports_dir . "/validation-errors-{$timestamp}.json",
            $this->reports['validation_errors'],
            'Validation Errors'
        );
        
        // Duplicates skipped report
        $this->write_report_file(
            $reports_dir . "/duplicates-skipped-{$timestamp}.json",
            $this->reports['duplicates_skipped'],
            'Duplicate Products Skipped'
        );
        
        // Summary report
        $summary = [
            'import_date' => date('Y-m-d H:i:s'),
            'total_imported' => count($this->reports['successful_imports']),
            'total_failed_mappings' => count($this->reports['failed_mappings']),
            'total_taxonomy_mappings' => count($this->reports['taxonomy_mappings']),
            'total_validation_errors' => count($this->reports['validation_errors']),
            'total_duplicates_skipped' => count($this->reports['duplicates_skipped']),
            'errors' => $this->reports['errors']
        ];
        
        $this->write_report_file(
            $reports_dir . "/import-summary-{$timestamp}.json",
            $summary,
            'Import Summary'
        );
        
        $this->log_message("Reports generated in: $reports_dir");
    }
    
    /**
     * Write report file
     */
    private function write_report_file($file_path, $data, $title) {
        $content = [
            'title' => $title,
            'generated_at' => date('Y-m-d H:i:s'),
            'data' => $data
        ];
        
        file_put_contents($file_path, json_encode($content, JSON_PRETTY_PRINT));
    }
    
    /**
     * Add error to reports
     */
    private function add_error($message) {
        $this->reports['errors'][] = $message;
        $this->log_message("ERROR: $message");
    }
    
    /**
     * Log message
     */
    private function log_message($message) {
        $timestamp = date('Y-m-d H:i:s');
        echo "[$timestamp] $message\n";
    }
    
    /**
     * Clean up trashed product posts
     */
    public function cleanup_trashed_products() {
        $this->log_message("=== Starting Cleanup of Trashed Products ===");
        
        // Get all trashed product posts
        $trashed_posts = get_posts([
            'post_type' => 'product',
            'post_status' => 'trash',
            'numberposts' => -1
        ]);
        
        $deleted_count = 0;
        
        foreach ($trashed_posts as $post) {
            // Permanently delete the post and all its metadata
            $result = wp_delete_post($post->ID, true);
            if ($result) {
                $deleted_count++;
                $this->log_message("Permanently deleted: {$post->post_title} (ID: {$post->ID})");
            } else {
                $this->log_message("Failed to delete: {$post->post_title} (ID: {$post->ID})");
            }
        }
        
        $this->log_message("=== Cleanup Complete: Deleted $deleted_count trashed products ===");
        return $deleted_count;
    }
}

// Run the import if this file is executed directly
if (php_sapi_name() === 'cli' || !empty($_GET['run_import']) || !empty($_GET['cleanup'])) {
    // Parse command line arguments for batch processing
    $start_row = 1;
    $batch_size = 250;
    
    if (php_sapi_name() === 'cli') {
        foreach ($argv as $arg) {
            if (strpos($arg, '--start=') === 0) {
                $start_row = (int)substr($arg, 8);
            }
            if (strpos($arg, '--batch=') === 0) {
                $batch_size = (int)substr($arg, 8);
            }
        }
    } else {
        if (!empty($_GET['start'])) {
            $start_row = (int)$_GET['start'];
        }
        if (!empty($_GET['batch'])) {
            $batch_size = (int)$_GET['batch'];
        }
    }
    
    $importer = new Handy_Product_Importer(null, $start_row, $batch_size);
    
    if (!empty($_GET['cleanup']) || (php_sapi_name() === 'cli' && in_array('--cleanup', $argv))) {
        // Run cleanup instead of import
        $importer->cleanup_trashed_products();
    } else {
        // Run normal import
        $importer->run_import();
    }
} else {
    // Show simple interface if accessed via browser
    $current_domain = $_SERVER['HTTP_HOST'];
    echo '<h1>Product Import Script</h1>';
    echo '<p>This script will import products from the CSV file.</p>';
    echo '<p><strong>Current Domain:</strong> ' . htmlspecialchars($current_domain) . '</p>';
    echo '<p><strong>Warning:</strong> Make sure you have a database backup before running this import.</p>';
    echo '<p><a href="?run_import=1" onclick="return confirm(\'Are you sure you want to run the import? Make sure you have a backup!\');">Run Import</a></p>';
    echo '<p><a href="?cleanup=1" onclick="return confirm(\'Are you sure you want to delete all existing products? This cannot be undone!\');">Clean Up Products</a></p>';
}
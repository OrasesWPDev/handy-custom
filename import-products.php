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

// Prevent direct access if not running from command line
if (!defined('ABSPATH') && !defined('WP_CLI') && php_sapi_name() !== 'cli') {
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
    public function __construct($csv_file = null) {
        $this->csv_file = $csv_file ?: __DIR__ . '/assets/csv/HC - Product Export - 5.15.25v1 - full export.csv';
        $this->init_field_mapping();
        $this->load_existing_taxonomies();
    }
    
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
            'gtin_number' => [
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
            
            // Special handling for Allergens (single value, map to ACF radio field)
            'Allergens' => [
                'type' => 'acf_field',
                'acf_name' => 'allergens'
            ],
            
            // Skipped fields (images and complex data)
            'brands' => ['type' => 'skip', 'reason' => 'Brand taxonomy not defined in current system'],
            'product_species' => ['type' => 'skip', 'reason' => 'Species taxonomy not defined in current system'],
            'certifications' => ['type' => 'skip', 'reason' => 'Certifications taxonomy not defined in current system'],
            'market_channels' => ['type' => 'skip', 'reason' => 'Market channels taxonomy not defined in current system'],
            'country_of_origin' => ['type' => 'skip', 'reason' => 'Country of origin field not defined in ACF'],
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
            'size'
        ];
        
        foreach ($taxonomies as $taxonomy) {
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
        
        // Process each row
        while (($row = fgetcsv($handle)) !== false) {
            $row_count++;
            
            if (count($row) !== count($headers)) {
                $this->add_error("Row $row_count: Column count mismatch");
                continue;
            }
            
            // Combine headers with row data
            $product_data = array_combine($headers, $row);
            
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
        
        // Special handling for allergens field (radio button)
        if ($field_name === 'allergens') {
            $cleaned_value = $this->map_allergen_value($cleaned_value);
        }
        
        update_field($field_name, $cleaned_value, $post_id);
        
        $import_result['acf_fields'][] = [
            'field_name' => $field_name,
            'value' => $cleaned_value
        ];
    }
    
    /**
     * Process taxonomy field
     */
    private function process_taxonomy_field($post_id, $taxonomy, $csv_value, &$import_result) {
        if (empty(trim($csv_value))) {
            return;
        }
        
        // Split comma-separated values
        $values = array_map('trim', explode(',', $csv_value));
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
     * Map allergen values to ACF radio options
     */
    private function map_allergen_value($csv_value) {
        $value_lower = strtolower(trim($csv_value));
        
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
        
        return isset($allergen_mapping[$value_lower]) ? $allergen_mapping[$value_lower] : 'None';
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
        
        // Summary report
        $summary = [
            'import_date' => date('Y-m-d H:i:s'),
            'total_imported' => count($this->reports['successful_imports']),
            'total_failed_mappings' => count($this->reports['failed_mappings']),
            'total_taxonomy_mappings' => count($this->reports['taxonomy_mappings']),
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
}

// Run the import if this file is executed directly
if (php_sapi_name() === 'cli' || !empty($_GET['run_import'])) {
    $importer = new Handy_Product_Importer();
    $importer->run_import();
} else {
    // Show simple interface if accessed via browser
    echo '<h1>Product Import Script</h1>';
    echo '<p>This script will import products from the CSV file.</p>';
    echo '<p><strong>Warning:</strong> Make sure you have a database backup before running this import.</p>';
    echo '<p><a href="?run_import=1" onclick="return confirm(\'Are you sure you want to run the import? Make sure you have a backup!\')">Run Import</a></p>';
}
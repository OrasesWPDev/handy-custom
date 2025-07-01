<?php
/**
 * Recipe Import Script
 * 
 * Imports recipes from CSV file into WordPress custom post type 'recipe'
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
 * Recipe Import Class
 */
class Handy_Recipe_Importer {
    
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
        
        $this->csv_file = $csv_file ?: __DIR__ . '/drupal_recipes_published_only.csv';
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
            'Title' => [
                'type' => 'post_field',
                'wp_field' => 'post_title'
            ],
            'Description' => [
                'type' => 'post_field', 
                'wp_field' => 'post_content'
            ],
            
            // ACF Custom Fields for recipes - using actual field names from display code
            'Prep_Time_Minutes' => [
                'type' => 'acf_field',
                'acf_name' => 'prep_time'  // Updated to match display code
            ],
            'Yield_Amount' => [
                'type' => 'acf_field',
                'acf_name' => 'servings'  // Updated to match display code
            ],
            
            'Instructions' => [
                'type' => 'acf_field',
                'acf_name' => 'cooking_instructions'
            ],
            
            'Cook_Time_Minutes' => [
                'type' => 'acf_field',
                'acf_name' => 'prep_cook_time'
            ],
            
            // Unused fields - commenting out since they're not used in display code
            /*
            'Yield_Unit' => [
                'type' => 'acf_field',
                'acf_name' => 'yield_unit'
            ],
            'Difficulty_Level' => [
                'type' => 'acf_field',
                'acf_name' => 'difficulty_level'
            ],
            */
            
            // Taxonomies (pipe-separated values in CSV)
            'Cooking_Methods' => [
                'type' => 'taxonomy',
                'taxonomy' => 'recipe-cooking-method'
            ],
            'Menu_Occasions' => [
                'type' => 'taxonomy',
                'taxonomy' => 'recipe-menu-occasion'
            ],
            'Species_Categories' => [
                'type' => 'taxonomy',
                'taxonomy' => 'recipe-category'
            ],
            
            // Enhanced ingredient field with rich content
            'Ingredients_WYSIWYG' => [
                'type' => 'acf_field',
                'acf_name' => 'ingredients'
            ],
            
            // Skipped fields
            'Recipe_ID' => ['type' => 'skip', 'reason' => 'Using WordPress auto-generated post IDs']
        ];
    }
    
    /**
     * Load existing taxonomy terms for mapping
     */
    private function load_existing_taxonomies() {
        $taxonomies = [
            'recipe-category',
            'recipe-cooking-method',
            'recipe-menu-occasion',
            'recipe-difficulty'
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
        $this->log_message("=== Starting Recipe Import Process ===");
        
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
            $recipe_data = array_combine($headers, $row);
            
            // Skip if no recipe title
            if (empty(trim($recipe_data['Title']))) {
                $this->add_error("Row $row_count: Empty recipe title, skipping");
                continue;
            }
            
            // Import recipe
            $result = $this->import_recipe($recipe_data, $row_count);
            if ($result) {
                $imported_count++;
            }
        }
        
        fclose($handle);
        
        $this->log_message("Processed $row_count rows, imported $imported_count recipes");
    }
    
    /**
     * Import a single recipe
     */
    private function import_recipe($recipe_data, $row_number) {
        $this->log_message("Processing row $row_number: " . $recipe_data['Title']);
        
        // Validate required fields
        $validation_result = $this->validate_recipe_data($recipe_data, $row_number);
        if (!$validation_result['valid']) {
            $this->reports['validation_errors'][] = [
                'row_number' => $row_number,
                'title' => $recipe_data['Title'],
                'errors' => $validation_result['errors']
            ];
            return false;
        }
        
        // Check for duplicates based on title
        $duplicate_check = $this->check_for_duplicates($recipe_data, $row_number);
        if ($duplicate_check['is_duplicate']) {
            $this->reports['duplicates_skipped'][] = [
                'row_number' => $row_number,
                'title' => $recipe_data['Title'],
                'existing_post_id' => $duplicate_check['existing_post_id'],
                'reason' => $duplicate_check['reason']
            ];
            $this->log_message("Skipping duplicate: " . $recipe_data['Title']);
            return false;
        }
        
        // Prepare post data
        $post_data = [
            'post_type' => 'recipe',
            'post_status' => 'draft', // Import as draft for review
            'post_title' => sanitize_text_field($recipe_data['Title']),
            'post_content' => wp_kses_post($recipe_data['Description'])
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
            'title' => $recipe_data['Title'],
            'acf_fields' => [],
            'taxonomies' => [],
            'skipped_fields' => []
        ];
        
        // Process ACF fields and taxonomies
        foreach ($recipe_data as $csv_field => $csv_value) {
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
        
        // Auto-assign categories based on recipe title and content
        $this->auto_assign_categories($post_id, $recipe_data, $import_result);
        
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
                // Create new term if it doesn't exist
                $new_term = wp_insert_term($value, $taxonomy);
                if (!is_wp_error($new_term)) {
                    $matched_terms[] = $new_term['term_id'];
                    $this->reports['taxonomy_mappings'][] = [
                        'taxonomy' => $taxonomy,
                        'csv_value' => $value,
                        'matched_term' => $value,
                        'term_slug' => sanitize_title($value),
                        'created_new' => true
                    ];
                } else {
                    $unmatched_values[] = $value;
                    $this->reports['failed_mappings'][] = [
                        'taxonomy' => $taxonomy,
                        'csv_value' => $value,
                        'reason' => 'Could not create term: ' . $new_term->get_error_message()
                    ];
                }
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
     * Auto-assign recipe categories based on title and content analysis
     */
    private function auto_assign_categories($post_id, $recipe_data, &$import_result) {
        $title_lower = strtolower($recipe_data['Title']);
        $description_lower = strtolower($recipe_data['Description']);
        $combined_text = $title_lower . ' ' . $description_lower;
        
        $assigned_categories = [];
        
        // Category detection rules for recipes
        $category_rules = [
            'appetizers' => ['appetizer', 'bites', 'dip', 'wings'],
            'entrees' => ['entrée', 'entree', 'main', 'bowl'],
            'breakfast' => ['breakfast', 'benedict', 'scramble'],
            'lunch' => ['sandwich', 'wrap', 'salad', 'tacos'],
            'dinner' => ['dinner', 'pasta', 'rice'],
            'snacks' => ['snack', 'muffin', 'cups'],
            'beverages' => ['bloody mary', 'michelada', 'cocktail', 'martini']
        ];
        
        foreach ($category_rules as $category_slug => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($combined_text, $keyword) !== false) {
                    // Get the term or create it if it doesn't exist
                    $term = get_term_by('slug', $category_slug, 'recipe-category');
                    if (!$term) {
                        $new_term = wp_insert_term(ucfirst($category_slug), 'recipe-category', ['slug' => $category_slug]);
                        if (!is_wp_error($new_term)) {
                            $assigned_categories[] = $new_term['term_id'];
                        }
                    } else {
                        $assigned_categories[] = $term->term_id;
                    }
                    break; // Only assign one category per product
                }
            }
        }
        
        // Assign categories
        if (!empty($assigned_categories)) {
            wp_set_object_terms($post_id, array_unique($assigned_categories), 'recipe-category');
            
            $import_result['taxonomies'][] = [
                'taxonomy' => 'recipe-category',
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
            'air fry' => 'air-fry',
            'deep fry' => 'deep-fry',
            'pan seared' => 'pan-seared',
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
     * Validate recipe data before import
     */
    private function validate_recipe_data($recipe_data, $row_number) {
        $errors = [];
        $required_fields = ['Title'];
        
        // Check required fields
        foreach ($required_fields as $field) {
            if (empty(trim($recipe_data[$field]))) {
                $errors[] = "Missing required field: $field";
            }
        }
        
        // Validate title length
        if (strlen($recipe_data['Title']) > 200) {
            $errors[] = "Recipe title too long (max 200 characters)";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check for duplicate recipes by title
     */
    private function check_for_duplicates($recipe_data, $row_number) {
        // Check by recipe title
        $existing_posts = get_posts([
            'post_type' => 'recipe',
            'title' => trim($recipe_data['Title']),
            'post_status' => ['publish', 'draft', 'private'],
            'numberposts' => 1
        ]);
        
        if (!empty($existing_posts)) {
            return [
                'is_duplicate' => true,
                'existing_post_id' => $existing_posts[0]->ID,
                'reason' => 'Title match'
            ];
        }
        
        return [
            'is_duplicate' => false
        ];
    }
    
    /**
     * Generate import reports
     */
    private function generate_reports() {
        $timestamp = date('Y-m-d_H-i-s');
        $reports_dir = __DIR__ . '/import-reports';
        
        // Successful imports report
        $this->write_report_file(
            $reports_dir . "/successful-recipe-imports-{$timestamp}.json",
            $this->reports['successful_imports'],
            'Successful Recipe Imports'
        );
        
        // Failed mappings report
        $this->write_report_file(
            $reports_dir . "/failed-recipe-mappings-{$timestamp}.json", 
            $this->reports['failed_mappings'],
            'Failed Recipe Taxonomy Mappings'
        );
        
        // Taxonomy mappings report
        $this->write_report_file(
            $reports_dir . "/recipe-taxonomy-mappings-{$timestamp}.json",
            $this->reports['taxonomy_mappings'],
            'Successful Recipe Taxonomy Mappings'
        );
        
        // Validation errors report
        $this->write_report_file(
            $reports_dir . "/recipe-validation-errors-{$timestamp}.json",
            $this->reports['validation_errors'],
            'Recipe Validation Errors'
        );
        
        // Duplicates skipped report
        $this->write_report_file(
            $reports_dir . "/recipe-duplicates-skipped-{$timestamp}.json",
            $this->reports['duplicates_skipped'],
            'Duplicate Recipes Skipped'
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
            $reports_dir . "/recipe-import-summary-{$timestamp}.json",
            $summary,
            'Recipe Import Summary'
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
    
    $importer = new Handy_Recipe_Importer(null, $start_row, $batch_size);
    $importer->run_import();
} else {
    // Show simple interface if accessed via browser
    $current_domain = $_SERVER['HTTP_HOST'];
    echo '<h1>Recipe Import Script</h1>';
    echo '<p>This script will import recipes from the CSV file.</p>';
    echo '<p><strong>Current Domain:</strong> ' . htmlspecialchars($current_domain) . '</p>';
    echo '<p><strong>Warning:</strong> Make sure you have a database backup before running this import.</p>';
    echo '<p><a href="?run_import=1" onclick="return confirm(\'Are you sure you want to run the import? Make sure you have a backup!\');">Run Recipe Import</a></p>';
}
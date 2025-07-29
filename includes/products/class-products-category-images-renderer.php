<?php
/**
 * Product Category Images rendering functionality
 * Creates a simple grid of category featured images for Flatsome integration
 * 
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
    exit;
}

class Handy_Custom_Products_Category_Images_Renderer {
    
    /**
     * Render category images grid
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function render($atts = array()) {
        $defaults = array(
            'limit' => 6,
            'size' => 'medium'
        );
        $atts = array_merge($defaults, $atts);
        
        $categories = $this->get_categories_with_images(intval($atts['limit']));
        
        if (empty($categories)) {
            Handy_Custom_Logger::log('No product categories with featured images found', 'info');
            return '';
        }
        
        Handy_Custom_Logger::log('Found ' . count($categories) . ' product categories with featured images', 'info');
        
        ob_start();
        $this->load_template('product-category-images/grid', array(
            'categories' => $categories,
            'image_size' => $atts['size']
        ));
        
        return ob_get_clean();
    }
    
    /**
     * Get top-level product categories with featured images
     *
     * @param int $limit Maximum number of categories
     * @return array
     */
    private function get_categories_with_images($limit) {
        // Get all top-level categories first, then we'll sort them manually
        $categories = get_terms(array(
            'taxonomy' => 'product-category',
            'parent' => 0, // Only top-level categories
            'hide_empty' => false, // Include empty categories - we'll filter by featured images instead
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        if (is_wp_error($categories)) {
            Handy_Custom_Logger::log('Error getting product categories: ' . $categories->get_error_message(), 'error');
            return array();
        }
        
        Handy_Custom_Logger::log('Found ' . count($categories) . ' top-level product categories', 'info');
        
        // Filter to only categories with featured images
        $categories_with_images = array();
        foreach ($categories as $category) {
            $featured_image = Handy_Custom_Products_Display::get_category_featured_image($category->term_id);
            
            if (!empty($featured_image)) {
                $categories_with_images[] = $category;
            }
        }
        
        // Sort categories in the desired order: crab cakes, crab meat, soft shell, shrimp, appetizers, dietary alternatives
        $sorted_categories = $this->sort_categories_by_custom_order($categories_with_images);
        
        // Limit to the requested number
        $final_categories = array_slice($sorted_categories, 0, $limit);
        
        Handy_Custom_Logger::log('Final result: ' . count($final_categories) . ' categories with featured images in custom order', 'info');
        
        return $final_categories;
    }
    
    /**
     * Sort categories in the desired custom order
     *
     * @param array $categories Array of category objects
     * @return array Sorted array of categories
     */
    private function sort_categories_by_custom_order($categories) {
        // Define the desired order by slug
        $desired_order = array(
            'crab-cakes',
            'crab-meat',
            'soft-shell-crab',
            'shrimp',
            'appetizers',
            'dietary-alternatives'
        );
        
        $sorted_categories = array();
        
        // Sort according to the desired order
        foreach ($desired_order as $slug) {
            foreach ($categories as $category) {
                if ($category->slug === $slug) {
                    $sorted_categories[] = $category;
                    break;
                }
            }
        }
        
        // Add any remaining categories that weren't in the predefined order
        foreach ($categories as $category) {
            $found = false;
            foreach ($sorted_categories as $sorted_category) {
                if ($sorted_category->term_id === $category->term_id) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $sorted_categories[] = $category;
            }
        }
        
        return $sorted_categories;
    }
    
    /**
     * Load template file
     *
     * @param string $template_name Template name
     * @param array $args Template arguments
     */
    private function load_template($template_name, $args = array()) {
        $template_path = HANDY_CUSTOM_PLUGIN_DIR . 'templates/shortcodes/' . $template_name . '.php';
        
        if (file_exists($template_path)) {
            extract($args);
            include $template_path;
        } else {
            Handy_Custom_Logger::log('Template not found: ' . $template_path, 'error');
        }
    }
}
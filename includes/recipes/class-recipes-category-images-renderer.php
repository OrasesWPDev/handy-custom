<?php
/**
 * Recipe Category Images rendering functionality
 * Creates a simple grid of recipe category featured images for Flatsome integration
 * 
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
    exit;
}

class Handy_Custom_Recipes_Category_Images_Renderer {
    
    /**
     * Render recipe category images grid
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
            Handy_Custom_Logger::log('No recipe categories with featured images found', 'info');
            return '';
        }
        
        Handy_Custom_Logger::log('Found ' . count($categories) . ' recipe categories with featured images', 'info');
        
        ob_start();
        $this->load_template('recipe-category-images/grid', array(
            'categories' => $categories,
            'image_size' => $atts['size']
        ));
        
        return ob_get_clean();
    }
    
    /**
     * Get top-level recipe categories with featured images
     *
     * @param int $limit Maximum number of categories
     * @return array
     */
    private function get_categories_with_images($limit) {
        // Get all top-level recipe categories
        $categories = get_terms(array(
            'taxonomy' => 'recipe-category',
            'parent' => 0, // Only top-level categories
            'hide_empty' => false, // Include empty categories - we'll filter by featured images instead
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        if (is_wp_error($categories)) {
            Handy_Custom_Logger::log('Error getting recipe categories: ' . $categories->get_error_message(), 'error');
            return array();
        }
        
        Handy_Custom_Logger::log('Found ' . count($categories) . ' top-level recipe categories', 'info');
        
        // Filter to only categories with featured images
        $categories_with_images = array();
        foreach ($categories as $category) {
            $featured_image = Handy_Custom_Recipes_Display::get_category_featured_image($category->term_id);
            
            if (!empty($featured_image)) {
                $categories_with_images[] = $category;
            }
        }
        
        // Limit to the requested number (no custom ordering needed for recipes)
        $final_categories = array_slice($categories_with_images, 0, $limit);
        
        Handy_Custom_Logger::log('Final result: ' . count($final_categories) . ' recipe categories with featured images', 'info');
        
        return $final_categories;
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
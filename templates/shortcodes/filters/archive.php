<?php
/**
 * Unified filter template for both products and recipes
 * 
 * User request: "this HTML code should show even if all of the taxonomies are empty. 
 * they are to loop through what is available in that taxonomy and then have the 
 * drop down display it. this way, if a user adds a new grade, or a new cooking method, 
 * the filter auto shows any new additions and removes any deletions."
 *
 * @package Handy_Custom
 * 
 * Available variables:
 * @var string $content_type Content type ('products' or 'recipes')
 * @var array $filter_options Available filter options per taxonomy
 * @var array $filters Current filter values from URL parameters
 * @var array $attributes Shortcode attributes (display, exclude)
 * @var array $context_data Context boundaries for JavaScript
 */

if (!defined('ABSPATH')) {
    exit;
}

// Log template loading
Handy_Custom_Logger::log("Loading unified filter template for content type: {$content_type}", 'info');
Handy_Custom_Logger::log("Available filter groups: " . wp_json_encode(array_keys($filter_options)), 'debug');
?>

<div class="handy-filters" data-content-type="<?php echo esc_attr($content_type); ?>" 
     data-shortcode="filter-<?php echo esc_attr($content_type); ?>"
     <?php if (!empty($context_data['context_category'])): ?>
         data-context-category="<?php echo esc_attr($context_data['context_category']); ?>"
     <?php endif; ?>
     <?php if (!empty($context_data['context_subcategory'])): ?>
         data-context-subcategory="<?php echo esc_attr($context_data['context_subcategory']); ?>"
     <?php endif; ?>>
    
    <!-- Filter Header -->
    <div class="handy-filter-header">
        <i class="fas fa-tag handy-filter-tag-icon"></i>
        <span class="handy-filter-title">FILTER</span>
    </div>
    
    <?php if (!empty($filter_options)): ?>
        <div class="filters-row">
            <?php foreach ($filter_options as $filter_key => $terms): ?>
                <?php
                // Generate human-readable label
                $label = generate_filter_label($filter_key);
                $select_id = "filter-{$content_type}-{$filter_key}";
                $current_value = isset($filters[$filter_key]) ? $filters[$filter_key] : '';
                
                // Log filter rendering
                $term_count = is_array($terms) ? count($terms) : 0;
                Handy_Custom_Logger::log("Rendering filter: {$filter_key} with {$term_count} terms, current value: '{$current_value}'", 'debug');
                ?>
                
                <div class="filter-group" data-taxonomy="<?php echo esc_attr($filter_key); ?>">
                    <label for="<?php echo esc_attr($select_id); ?>">
                        <?php echo esc_html($label); ?>:
                    </label>
                    <select id="<?php echo esc_attr($select_id); ?>" 
                            name="<?php echo esc_attr($filter_key); ?>" 
                            class="filter-select"
                            data-content-type="<?php echo esc_attr($content_type); ?>"
                            data-taxonomy="<?php echo esc_attr($filter_key); ?>">
                        
                        <!-- Default "All" option -->
                        <option value="">All <?php echo esc_html($label); ?></option>
                        
                        <?php if (!empty($terms) && is_array($terms)): ?>
                            <?php foreach ($terms as $term): ?>
                                <?php if (is_object($term) && isset($term->slug, $term->name)): ?>
                                    <option value="<?php echo esc_attr($term->slug); ?>" 
                                            <?php selected($current_value, $term->slug); ?>>
                                        <?php echo esc_html($term->name); ?>
                                    </option>
                                    <?php Handy_Custom_Logger::log("Added filter option: {$term->name} ({$term->slug})", 'debug'); ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php Handy_Custom_Logger::log("No terms available for taxonomy: {$filter_key}", 'debug'); ?>
                        <?php endif; ?>
                        
                    </select>
                </div>
                
            <?php endforeach; ?>
        </div>
        <?php Handy_Custom_Logger::log("Universal clear filters button displayed", 'debug'); ?>
        
    <?php else: ?>
        <!-- No filter options available -->
        <div class="no-filters">
            <p>No filter options available for <?php echo esc_html($content_type); ?>.</p>
        </div>
        <?php Handy_Custom_Logger::log("No filter options available for content type: {$content_type}", 'info'); ?>
    <?php endif; ?>
    
    <!-- Loading indicator for AJAX operations -->
    <div class="filter-loading" style="display: none;">
        <p>Updating filters...</p>
    </div>
    
</div>

<!-- Universal Clear Filters Button - separate container below filters -->
<div class="handy-filter-clear-container" data-content-type="<?php echo esc_attr($content_type); ?>">
    <button type="button" class="btn btn-clear-filters-universal" 
            data-content-type="<?php echo esc_attr($content_type); ?>">
        Clear (view all)
        <i class="fas fa-arrow-right"></i>
    </button>
</div>

<?php
/**
 * Generate human-readable label from taxonomy key
 * 
 * @param string $filter_key Taxonomy key
 * @return string Human-readable label
 */
function generate_filter_label($filter_key) {
    // Custom labels for specific taxonomies
    $custom_labels = array(
        'market_segment' => 'Market Segment',
        'cooking_method' => 'Cooking Method', 
        'menu_occasion' => 'Menu Occasion',
        'product_type' => 'Product Type',
        'recipe_category' => 'Recipe Category'
    );
    
    if (isset($custom_labels[$filter_key])) {
        return $custom_labels[$filter_key];
    }
    
    // Default: capitalize and replace underscores
    return ucwords(str_replace('_', ' ', $filter_key));
}

// Log template completion
Handy_Custom_Logger::log("Unified filter template rendered successfully for {$content_type}", 'info');
?>
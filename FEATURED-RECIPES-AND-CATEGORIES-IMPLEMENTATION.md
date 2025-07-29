# Featured Recipes & Product Categories Implementation Guide

## Overview

This document outlines the complete implementation plan for two new features in the Handy Custom WordPress Plugin:

1. **Featured Recipes System** - Admin interface to mark up to 3 recipes as featured, plus shortcode to display them
2. **Product Category Images Shortcode** - Simple grid of 6 circular category featured images with names, optimized for Flatsome UX Builder integration

## Test-Driven Development Approach

### Testing Infrastructure Created

The implementation follows a comprehensive test-first approach using the existing Playwright testing infrastructure:

#### Enhanced Test Utilities
- **Extended `PluginUtils` class** with new methods:
  - `testFeaturedRecipeAdmin()` - Tests admin interface functionality
  - `testFeaturedRecipeToggle()` - Tests star toggle AJAX functionality  
  - `testFeaturedRecipeLimit()` - Tests 3-recipe limit enforcement
  - `testFeaturedRecipesShortcode()` - Tests frontend shortcode display
  - `testProductCategoryImagesShortcode()` - Tests the new image grid shortcode
  - `testProductCategoryImagesResponsive()` - Tests 6/3/2 column responsive behavior
  - `testProductCategoryImagesContent()` - Validates only categories with featured images show

#### Comprehensive Test Files

**`tests/e2e/featured-recipes.spec.js`** - Complete featured recipes testing:
- Admin interface tests (column display, star toggle, AJAX functionality)
- Limit enforcement (max 3 featured recipes)
- Frontend shortcode tests (rendering, card structure, responsive design)
- Integration tests (admin changes reflect on frontend)
- Error handling and edge cases

**`tests/e2e/product-category-images.spec.js`** - Complete category images testing:
- 6-column desktop grid layout
- 3-column tablet responsive behavior  
- 2-column mobile responsive behavior
- Circular image display and CSS masking
- Category names underneath images
- Filtering for categories with featured images only
- Flatsome theme integration compatibility

**Enhanced `tests/e2e/smoke.spec.js`** - Added smoke tests:
- Basic admin column existence for featured recipes
- Shortcode rendering without errors for both features
- Regression testing to ensure new features don't break existing functionality

### Running the Tests

```bash
# Run smoke tests (quick validation)
npm run test:smoke

# Run full test suite including new features
npm run test:full

# Run specific feature tests
npx playwright test tests/e2e/featured-recipes.spec.js
npx playwright test tests/e2e/product-category-images.spec.js

# Run with visible browser for debugging
npm run test:headed
```

## Development Implementation Plan

### Feature 1: Featured Recipes System

Following the exact pattern from the provided featured posts implementation.

#### Step 1: Admin Interface Implementation

**Create `includes/recipes/class-recipes-featured-admin.php`**:
```php
<?php
/**
 * Featured Recipes Admin functionality
 * Handles admin column, star toggle, and AJAX processing
 */
class Handy_Custom_Recipes_Featured_Admin {
    const FEATURED_META_KEY = '_is_featured_recipe';
    const MAX_FEATURED_RECIPES = 3;
    
    public static function init() {
        // Add admin column
        add_filter('manage_recipe_posts_columns', array(__CLASS__, 'add_featured_column'));
        add_action('manage_recipe_posts_custom_column', array(__CLASS__, 'display_featured_column'), 10, 2);
        
        // AJAX handler
        add_action('wp_ajax_toggle_featured_recipe_status', array(__CLASS__, 'ajax_toggle_featured_status'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
    }
    
    public static function add_featured_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            if ($key == 'date') {
                $new_columns['featured_recipe_admin_col'] = __('Featured', 'handy-custom');
            }
            $new_columns[$key] = $value;
        }
        return $new_columns;
    }
    
    public static function display_featured_column($column_name, $post_id) {
        if ($column_name == 'featured_recipe_admin_col') {
            $is_featured = get_post_meta($post_id, self::FEATURED_META_KEY, true);
            $star_class = $is_featured ? 'dashicons-star-filled' : 'dashicons-star-empty';
            $title_text = $is_featured ? 'Unmark as Featured' : 'Mark as Featured';
            $new_status = $is_featured ? '0' : '1';
            
            printf(
                '<a href="#" class="toggle-featured-recipe-status" data-postid="%d" data-status="%s" data-nonce="%s" title="%s">
                    <span class="dashicons %s" style="font-size: 20px; color: #ffb900;"></span>
                </a>',
                esc_attr($post_id),
                esc_attr($new_status),
                wp_create_nonce('toggle_featured_recipe_status_nonce_' . $post_id),
                esc_attr($title_text),
                esc_attr($star_class)
            );
        }
    }
    
    public static function ajax_toggle_featured_status() {
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!check_ajax_referer('toggle_featured_recipe_status_nonce_' . $post_id, 'nonce', false)) {
            wp_send_json_error(array('message' => 'Security check failed'), 403);
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Permission denied'), 403);
        }
        
        $new_status = isset($_POST['new_status']) ? $_POST['new_status'] : null;
        
        if ($new_status === '1') {
            // Check if we already have 3 featured recipes
            $featured_count = self::get_featured_recipes_count();
            if ($featured_count >= self::MAX_FEATURED_RECIPES) {
                wp_send_json_error(array('message' => 'Maximum of ' . self::MAX_FEATURED_RECIPES . ' recipes can be featured'));
            }
            
            update_post_meta($post_id, self::FEATURED_META_KEY, '1');
            wp_send_json_success(array(
                'new_status' => '1',
                'new_icon' => 'dashicons-star-filled',
                'new_title' => 'Unmark as Featured'
            ));
        } elseif ($new_status === '0') {
            delete_post_meta($post_id, self::FEATURED_META_KEY);
            wp_send_json_success(array(
                'new_status' => '0',
                'new_icon' => 'dashicons-star-empty',
                'new_title' => 'Mark as Featured'
            ));
        }
        
        wp_die();
    }
    
    private static function get_featured_recipes_count() {
        $args = array(
            'post_type' => 'recipe',
            'meta_query' => array(
                array(
                    'key' => self::FEATURED_META_KEY,
                    'value' => '1',
                    'compare' => '='
                )
            ),
            'fields' => 'ids'
        );
        $query = new WP_Query($args);
        return $query->found_posts;
    }
    
    public static function enqueue_admin_scripts($hook_suffix) {
        if ('edit.php' != $hook_suffix) return;
        
        global $typenow;
        if ($typenow != 'recipe') return;
        
        wp_enqueue_script(
            'admin-recipe-featured-toggle',
            HANDY_CUSTOM_PLUGIN_URL . 'assets/js/admin-recipe-featured-toggle.js',
            array('jquery'),
            HANDY_CUSTOM_VERSION,
            true
        );
        
        wp_localize_script('admin-recipe-featured-toggle', 'adminRecipeFeaturedToggle', array(
            'ajax_url' => admin_url('admin-ajax.php')
        ));
    }
}
```

**Create `assets/js/admin-recipe-featured-toggle.js`**:
```javascript
jQuery(document).ready(function($) {
    $('.toggle-featured-recipe-status').on('click', function(e) {
        e.preventDefault();
        
        var $this = $(this);
        var postId = $this.data('postid');
        var newStatus = $this.data('status');
        var nonce = $this.data('nonce');
        
        $.ajax({
            url: adminRecipeFeaturedToggle.ajax_url,
            type: 'POST',
            data: {
                action: 'toggle_featured_recipe_status',
                post_id: postId,
                new_status: newStatus,
                nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    $this.find('.dashicons')
                        .removeClass('dashicons-star-filled dashicons-star-empty')
                        .addClass(response.data.new_icon);
                    $this.attr('title', response.data.new_title);
                    $this.data('status', response.data.new_status === '1' ? '0' : '1');
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('An error occurred while updating the featured status.');
            }
        });
    });
});
```

#### Step 2: Featured Recipes Shortcode

**Extend `includes/class-shortcodes.php`**:
```php
public static function init() {
    // Existing shortcodes...
    add_shortcode('featured-recipes', array(__CLASS__, 'featured_recipes_shortcode'));
}

public static function featured_recipes_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => 3,
        'columns' => '' // Auto-calculate based on count
    ), $atts, 'featured-recipes');
    
    $args = array(
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'posts_per_page' => intval($atts['limit']),
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => array(
            array(
                'key' => Handy_Custom_Recipes_Featured_Admin::FEATURED_META_KEY,
                'value' => '1',
                'compare' => '='
            )
        )
    );
    
    $featured_query = new WP_Query($args);
    
    if (!$featured_query->have_posts()) {
        return ''; // Return empty if no featured recipes
    }
    
    $recipe_ids = wp_list_pluck($featured_query->posts, 'ID');
    $recipe_count = count($recipe_ids);
    
    // Auto-calculate columns if not specified
    if (empty($atts['columns'])) {
        $atts['columns'] = $recipe_count;
    }
    
    // Use existing recipes renderer
    $renderer = new Handy_Custom_Recipes_Renderer();
    
    ob_start();
    ?>
    <div class="handy-featured-recipes-section">
        <div class="handy-featured-recipes-content">
            <h2 class="handy-featured-recipes-title">Featured Recipes</h2>
        </div>
    </div>
    <?php echo $renderer->render_specific_recipes($recipe_ids, $atts); ?>
    <?php
    
    wp_reset_postdata();
    return ob_get_clean();
}
```

#### Step 3: Integration and Asset Loading

**Extend `includes/class-handy-custom.php`**:
```php
public function init() {
    // Existing initialization...
    Handy_Custom_Recipes_Featured_Admin::init();
}

private function enqueue_conditional_assets() {
    // Existing asset loading...
    
    // Featured recipes CSS
    if ($this->has_shortcode('featured-recipes')) {
        wp_enqueue_style(
            'handy-custom-featured-recipes',
            HANDY_CUSTOM_PLUGIN_URL . 'assets/css/featured-recipes.css',
            array(),
            self::VERSION
        );
    }
}
```

### Feature 2: Product Category Images Shortcode

Simple grid of 6 circular category featured images with names, optimized for Flatsome UX Builder integration.

#### Step 1: Category Images Renderer

**Create `includes/products/class-products-category-images-renderer.php`**:
```php
<?php
/**
 * Product Category Images rendering functionality
 * Creates a simple grid of category featured images for Flatsome integration
 */
class Handy_Custom_Products_Category_Images_Renderer {
    
    public function render($atts = array()) {
        $defaults = array(
            'limit' => 6,
            'size' => 'medium'
        );
        $atts = array_merge($defaults, $atts);
        
        $categories = $this->get_categories_with_images(intval($atts['limit']));
        
        if (empty($categories)) {
            return '';
        }
        
        ob_start();
        $this->load_template('product-category-images/grid', array(
            'categories' => $categories,
            'image_size' => $atts['size']
        ));
        
        return ob_get_clean();
    }
    
    private function get_categories_with_images($limit) {
        $categories = get_terms(array(
            'taxonomy' => 'product-category',
            'parent' => 0, // Only top-level categories
            'hide_empty' => true,
            'number' => $limit,
            'orderby' => 'name',
            'order' => 'ASC'
        ));
        
        if (is_wp_error($categories)) {
            return array();
        }
        
        // Filter to only categories with featured images
        return array_filter($categories, function($category) {
            return Handy_Custom_Products_Display::get_category_featured_image($category->term_id);
        });
    }
    
    private function load_template($template_name, $args = array()) {
        $template_path = HANDY_CUSTOM_PLUGIN_DIR . 'templates/shortcodes/' . $template_name . '.php';
        
        if (file_exists($template_path)) {
            extract($args);
            include $template_path;
        }
    }
}
```

#### Step 2: Simple Grid Template

**Create `templates/shortcodes/product-category-images/grid.php`**:
```php
<?php
/**
 * Product Category Images Grid Template
 * Simple grid of circular category images with names for Flatsome integration
 */

if (empty($categories)) {
    return;
}
?>

<div class="handy-category-images-grid" data-shortcode="product-category-images">
    <?php foreach ($categories as $category) : ?>
        <?php 
        $featured_image = Handy_Custom_Products_Display::get_category_featured_image($category->term_id);
        if ($featured_image) :
        ?>
            <div class="category-image-item">
                <div class="category-image-circle">
                    <img src="<?php echo esc_url($featured_image); ?>" 
                         alt="<?php echo esc_attr($category->name); ?>" 
                         class="category-featured-image">
                </div>
                <div class="category-name"><?php echo esc_html($category->name); ?></div>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
```

#### Step 3: Shortcode Registration

**Extend `includes/class-shortcodes.php`**:
```php
public static function init() {
    // Existing shortcodes...
    add_shortcode('product-category-images', array(__CLASS__, 'product_category_images_shortcode'));
}

public static function product_category_images_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => 6,
        'size' => 'medium'
    ), $atts, 'product-category-images');
    
    try {
        $renderer = new Handy_Custom_Products_Category_Images_Renderer();
        return $renderer->render($atts);
    } catch (Exception $e) {
        Handy_Custom_Logger::log('Product category images shortcode error: ' . $e->getMessage(), 'error');
        return '';
    }
}
```

#### Step 4: Responsive CSS for Flatsome Integration

**Create `assets/css/product-category-images.css`**:
```css
.handy-category-images-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 2rem 1rem;
    margin: 0;
    padding: 0;
}

.category-image-item {
    text-align: center;
}

.category-image-circle {
    width: 100%;
    aspect-ratio: 1;
    border-radius: 50%;
    overflow: hidden;
    margin-bottom: 0.75rem;
}

.category-featured-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.category-name {
    font-size: 1rem;
    font-weight: 500;
    color: #2c5aa0; /* Blue color from design */
    margin: 0;
    line-height: 1.2;
}

/* Tablet: 3 columns */
@media (max-width: 1199px) {
    .handy-category-images-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem 1rem;
    }
}

/* Mobile: 2 columns */
@media (max-width: 767px) {
    .handy-category-images-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem 0.75rem;
    }
    
    .category-name {
        font-size: 0.9rem;
    }
    
    .category-image-circle {
        margin-bottom: 0.5rem;
    }
}

/* Very small screens: still 2 columns but smaller */
@media (max-width: 480px) {
    .handy-category-images-grid {
        gap: 0.75rem 0.5rem;
    }
    
    .category-name {
        font-size: 0.85rem;
    }
}
```

## Implementation Workflow

### Development Process
1. **Run initial tests** - All tests should skip/fail gracefully (expected behavior)
2. **Implement featured recipes admin interface** - Tests should start passing for admin functionality
3. **Implement featured recipes shortcode** - Frontend tests should start passing
4. **Implement product categories shortcode** - Category tests should start passing
5. **Add styling and responsive design** - Responsive tests should pass
6. **Final integration testing** - All tests should pass

### Version Management
Following CLAUDE.md requirements, update all three version locations:
- `handy-custom.php` header: `* Version: X.X.X`
- `HANDY_CUSTOM_VERSION` constant: `define('HANDY_CUSTOM_VERSION', 'X.X.X');`
- `Handy_Custom::VERSION` constant: `const VERSION = 'X.X.X';`

### Testing Commands

```bash
# Deploy to local test environment
npm run deploy:local

# Run smoke tests (quick validation)
npm run test:smoke

# Run specific feature tests during development
npx playwright test tests/e2e/featured-recipes.spec.js --headed
npx playwright test tests/e2e/product-categories.spec.js --headed

# Run full test suite before release
npm run test:full

# Auto-deploy during development
npm run watch:deploy
```

## Architecture Integration

### File Structure
```
/includes/
├── recipes/class-recipes-featured-admin.php      # New
├── products/class-products-categories-renderer.php # New
├── class-shortcodes.php                          # Extended
├── class-handy-custom.php                        # Extended (asset loading)
/templates/shortcodes/
├── product-categories/archive.php                # New
/assets/
├── css/
│   ├── featured-recipes.css                     # New (minimal - reuses existing styles)
│   └── product-categories.css                   # New
└── js/admin-recipe-featured-toggle.js           # New
```

### Key Design Principles
- **Leverage existing architecture** - Reuse existing renderers, templates, and utilities
- **Follow established patterns** - Mirror successful featured posts implementation
- **Maintain consistency** - Use existing CSS classes and JavaScript patterns
- **Ensure accessibility** - Proper ARIA labels, keyboard navigation, alt text
- **Mobile-first responsive** - Grid layouts that work across all devices

This implementation provides comprehensive functionality while maintaining code quality, performance, and consistency with the existing plugin architecture.
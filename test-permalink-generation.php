<?php
/**
 * Test Script for Product Permalink Generation
 * 
 * Place this file in the plugin root and access via:
 * http://localhost:10008/wp-content/plugins/handy-custom/test-permalink-generation.php
 * 
 * User requirement: "when creating and when a top level category is assigned, 
 * and then when I save the post, it needs to reflect the /products/{category}/{single-post-type-title}"
 */

// Load WordPress
require_once '../../../wp-load.php';

// Only allow admin access
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin access required.');
}

echo '<h1>Product Permalink Generation Test</h1>';
echo '<p><strong>Testing requirement:</strong> Products should generate URLs like <code>/products/{category}/{product-title}/</code></p>';

// Test 1: Get all products
$products = get_posts(array(
    'post_type' => 'product',
    'post_status' => 'publish',
    'numberposts' => 10
));

if (empty($products)) {
    echo '<div style="background: #ffe4e4; padding: 10px; border: 1px solid #ff0000; margin: 10px 0;">';
    echo '<strong>No products found!</strong> Please create some test products with categories assigned.';
    echo '</div>';
} else {
    echo '<h2>Found ' . count($products) . ' products</h2>';
    
    foreach ($products as $product) {
        echo '<div style="background: #f9f9f9; padding: 15px; margin: 10px 0; border: 1px solid #ddd;">';
        echo '<h3>' . $product->post_title . ' (ID: ' . $product->ID . ')</h3>';
        
        // Get categories
        $categories = wp_get_post_terms($product->ID, 'product-category');
        
        if (empty($categories)) {
            echo '<p style="color: orange;"><strong>❌ No categories assigned</strong></p>';
        } else {
            echo '<p><strong>Categories:</strong></p><ul>';
            foreach ($categories as $cat) {
                $parent_info = $cat->parent == 0 ? ' (TOP-LEVEL)' : ' (child of ' . get_term($cat->parent)->name . ')';
                echo '<li>' . $cat->name . ' (' . $cat->slug . ')' . $parent_info . '</li>';
            }
            echo '</ul>';
        }
        
        // Test current permalink generation
        echo '<p><strong>Current WordPress Permalink:</strong> <code>' . get_permalink($product->ID) . '</code></p>';
        
        // Test our custom function manually
        $handy_custom = Handy_Custom::get_instance();
        $custom_permalink = $handy_custom->custom_product_permalink(get_permalink($product->ID), $product);
        
        echo '<p><strong>Our Custom Permalink:</strong> <code>' . $custom_permalink . '</code></p>';
        
        // Test if URLs match expectation
        if (!empty($categories)) {
            $expected_pattern = '/products/[^/]+/' . $product->post_name . '/';
            if (preg_match('#' . preg_quote($expected_pattern, '#') . '#', $custom_permalink)) {
                echo '<p style="color: green;"><strong>✅ URL Format Correct!</strong></p>';
            } else {
                echo '<p style="color: red;"><strong>❌ URL Format Issue</strong></p>';
                echo '<p>Expected pattern: <code>/products/{category}/' . $product->post_name . '/</code></p>';
            }
        }
        
        echo '</div>';
    }
}

// Test 2: Check if post_type_link filter is working
echo '<h2>Filter Hook Test</h2>';
$filters_attached = has_filter('post_type_link', array(Handy_Custom::get_instance(), 'custom_product_permalink'));
if ($filters_attached) {
    echo '<p style="color: green;"><strong>✅ post_type_link filter is attached</strong></p>';
} else {
    echo '<p style="color: red;"><strong>❌ post_type_link filter is NOT attached</strong></p>';
}

// Test 3: Product categories taxonomy check
$product_categories = get_terms(array(
    'taxonomy' => 'product-category',
    'hide_empty' => false
));

if (empty($product_categories)) {
    echo '<p style="color: orange;"><strong>❌ No product categories found</strong></p>';
} else {
    echo '<h2>Available Product Categories (' . count($product_categories) . ')</h2>';
    echo '<ul>';
    foreach ($product_categories as $cat) {
        $level = $cat->parent == 0 ? 'TOP-LEVEL' : 'Subcategory';
        echo '<li>' . $cat->name . ' (' . $cat->slug . ') - ' . $level . '</li>';
    }
    echo '</ul>';
}

echo '<hr><p><em>Test completed at ' . date('Y-m-d H:i:s') . '</em></p>';
?>
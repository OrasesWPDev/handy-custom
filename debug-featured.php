<?php
/**
 * Debug script to check featured recipes
 * Run this from browser at: http://localhost:10008/wp-content/plugins/handy-custom/debug-featured.php
 */

// WordPress environment
define('WP_USE_THEMES', false);
require_once('../../../wp-load.php');

echo "<h1>Featured Recipes Debug</h1>";

// Check if featured admin class exists
if (class_exists('Handy_Custom_Recipes_Featured_Admin')) {
    echo "<p>✅ Handy_Custom_Recipes_Featured_Admin class exists</p>";
    echo "<p>Meta key: " . Handy_Custom_Recipes_Featured_Admin::FEATURED_META_KEY . "</p>";
} else {
    echo "<p>❌ Handy_Custom_Recipes_Featured_Admin class missing</p>";
}

// Get all recipes
$all_recipes = get_posts(array(
    'post_type' => 'recipe',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'fields' => 'ids'
));

echo "<p>Total published recipes: " . count($all_recipes) . "</p>";

// Get featured recipes using the same query as shortcode
$featured_recipe_ids = get_posts(array(
    'post_type' => 'recipe',
    'post_status' => 'publish',
    'meta_key' => '_is_featured_recipe',
    'meta_value' => '1',
    'posts_per_page' => 10,
    'orderby' => 'date',  
    'order' => 'DESC',
    'fields' => 'ids'
));

echo "<p>Featured recipes found: " . count($featured_recipe_ids) . "</p>";

if (!empty($featured_recipe_ids)) {
    echo "<h2>Featured Recipe Details:</h2>";
    foreach ($featured_recipe_ids as $recipe_id) {
        $title = get_the_title($recipe_id);
        $meta_value = get_post_meta($recipe_id, '_is_featured_recipe', true);
        echo "<p>ID: {$recipe_id} - Title: {$title} - Featured: {$meta_value}</p>";
    }
} else {
    echo "<p>❌ No featured recipes found</p>";
    
    // Check if any recipes have the meta key at all
    $recipes_with_meta = get_posts(array(
        'post_type' => 'recipe',
        'post_status' => 'publish',
        'meta_key' => '_is_featured_recipe',
        'posts_per_page' => -1,
        'fields' => 'ids'
    ));
    
    echo "<p>Recipes with _is_featured_recipe meta (any value): " . count($recipes_with_meta) . "</p>";
}

// Test shortcode output
echo "<h2>Shortcode Test:</h2>";
$shortcode_output = do_shortcode('[featured-recipes]');
echo "<p>Shortcode output length: " . strlen($shortcode_output) . "</p>";
echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>";
echo $shortcode_output;
echo "</div>";
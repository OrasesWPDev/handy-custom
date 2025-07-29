<?php
/**
 * Test script to trigger featured recipes shortcode and generate logs
 */

// Load WordPress
define('WP_USE_THEMES', false);
if (file_exists('/Users/chadmacbook/Local Sites/handy-crab/app/public/wp-load.php')) {
    require_once('/Users/chadmacbook/Local Sites/handy-crab/app/public/wp-load.php');
} else {
    die('WordPress not found');
}

echo "Testing Featured Recipes Shortcode\n";
echo "===================================\n\n";

// Trigger shortcode directly
$shortcode_output = do_shortcode('[featured-recipes]');

echo "Shortcode output length: " . strlen($shortcode_output) . "\n";
echo "Shortcode output:\n";
echo $shortcode_output . "\n\n";

// Check for featured recipes in database
$featured_recipes = get_posts(array(
    'post_type' => 'recipe',
    'post_status' => 'publish',
    'meta_key' => '_is_featured_recipe',
    'meta_value' => '1',
    'posts_per_page' => 10,
    'fields' => 'ids'
));

echo "Featured recipes found: " . count($featured_recipes) . "\n";
if (!empty($featured_recipes)) {
    echo "Featured recipe IDs: " . implode(', ', $featured_recipes) . "\n";
} else {
    echo "No featured recipes found - this may be the issue!\n";
}
<?php
/**
 * Recipe Category Images Grid Template
 * Simple grid of circular category images with names for Flatsome integration
 * Links to /recipes/?category={slug} for seamless filter integration
 * 
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
    exit;
}

if (empty($categories)) {
    return;
}
?>

<div class="handy-category-images-grid" data-shortcode="recipe-category-images">
    <?php foreach ($categories as $category) : ?>
        <?php 
        // Use same method as recipe display system for image consistency
        $featured_image = Handy_Custom_Recipes_Display::get_category_featured_image($category->term_id);
        if ($featured_image) :
            // Build the recipe filter URL in format: /recipes/?category={category-slug}
            $category_url = home_url('/recipes/?category=' . $category->slug);
        ?>
            <a href="<?php echo esc_url($category_url); ?>" class="category-image-item-link">
                <div class="category-image-item">
                    <div class="category-image-circle">
                        <img src="<?php echo esc_url($featured_image); ?>" 
                             alt="<?php echo esc_attr($category->name); ?>" 
                             class="category-featured-image"
                             loading="lazy">
                    </div>
                    <div class="category-name"><?php echo esc_html($category->name); ?></div>
                </div>
            </a>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php
/**
 * Single Recipe Template
 * 
 * Template for displaying individual recipe posts.
 * Implements two-column layout with breadcrumbs, social sharing, and accordion content sections.
 * Uses only recipe-specific ACF fields: prep_time, servings, ingredients, cooking_instructions.
 * 
 * @package Handy_Custom
 */

get_header(); ?>

<div class="handy-single-recipe-container">
    <?php while (have_posts()) : the_post(); ?>
        
        <!-- Breadcrumbs Section -->
        <div class="handy-single-recipe-breadcrumbs-wrapper">
            <div class="handy-single-recipe-breadcrumbs">
                <?php
                if (function_exists('yoast_breadcrumb')) {
                    yoast_breadcrumb('<nav class="handy-breadcrumb-nav">', '</nav>');
                }
                ?>
            </div>
        </div>

        <!-- Two Column Main Content Section -->
        <div class="handy-single-recipe-main">
            <div class="handy-recipe-content-wrapper">
                
                <!-- Left Column -->
                <div class="handy-recipe-left-column">
                    <!-- Recipe Title -->
                    <h1 class="handy-recipe-title"><?php the_title(); ?></h1>
                    
                    <!-- Social Icons Row -->
                    <div class="handy-recipe-social-row">
                        <!-- Print Icon -->
                        <a href="#" class="handy-social-icon handy-print-icon" onclick="window.print(); return false;" title="Print">
                            <i class="fas fa-print"></i>
                        </a>
                        
                        <span class="handy-social-separator">|</span>
                        
                        <!-- Email Icon -->
                        <a href="mailto:?subject=<?php echo urlencode(get_the_title()); ?>&body=<?php echo urlencode(get_permalink()); ?>" 
                           class="handy-social-icon handy-email-icon" title="Email">
                            <i class="fas fa-envelope"></i>
                        </a>
                        
                        <span class="handy-social-separator">|</span>
                        
                        <!-- Text Share Icon -->
                        <a href="#" class="handy-social-icon handy-text-icon" 
                           onclick="if(navigator.share){navigator.share({title:document.title,url:window.location.href})}else{navigator.clipboard.writeText(window.location.href).then(()=>alert('Link copied to clipboard!'))}; return false;" 
                           title="Share via Text">
                            <i class="fas fa-sms"></i>
                        </a>
                        
                        <span class="handy-social-separator">|</span>
                        
                        <!-- Social Media Icons -->
                        <div class="handy-social-media-icons">
                            <!-- Facebook Share -->
                            <a href="#" class="handy-social-icon handy-facebook-icon" 
                               onclick="window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(window.location.href), 'facebook-share', 'width=580,height=296'); return false;" 
                               title="Share on Facebook">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            
                            <!-- X (formerly Twitter) Share -->
                            <a href="#" class="handy-social-icon handy-x-icon" 
                               onclick="window.open('https://twitter.com/intent/tweet?url=' + encodeURIComponent(window.location.href) + '&text=' + encodeURIComponent(document.title), 'x-share', 'width=550,height=235'); return false;" 
                               title="Share on X">
                                <i class="fab fa-x-twitter"></i>
                            </a>
                            
                            <!-- LinkedIn Share -->
                            <a href="#" class="handy-social-icon handy-linkedin-icon" 
                               onclick="window.open('https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(window.location.href), 'linkedin-share', 'width=550,height=550'); return false;" 
                               title="Share on LinkedIn">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Recipe Content -->
                    <div class="handy-recipe-content">
                        <?php the_content(); ?>
                    </div>
                    
                </div>
                
                <!-- Right Column -->
                <div class="handy-recipe-right-column">
                    <!-- Recipe Featured Image -->
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="handy-recipe-image">
                            <?php the_post_thumbnail('large', array('class' => 'handy-recipe-main-image')); ?>
                        </div>
                    <?php endif; ?>
                </div>
                
            </div>
        </div>

        <!-- Recipe Details Section (Similar to Product Specifications) -->
        <div class="handy-single-recipe-details">
            <div class="handy-recipe-details-container">
                <table class="handy-recipe-details-table">
                    <tbody>
                        <?php 
                        $prep_time = function_exists('get_field') ? get_field('prep_time') : '';
                        if (!empty($prep_time)) : ?>
                        <tr>
                            <td class="handy-detail-label">Prep Time</td>
                            <td class="handy-detail-value"><?php echo esc_html(Handy_Custom_Recipes_Utils::format_prep_time($prep_time)); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php 
                        $servings = function_exists('get_field') ? get_field('servings') : '';
                        if (!empty($servings)) : ?>
                        <tr>
                            <td class="handy-detail-label">Servings</td>
                            <td class="handy-detail-value"><?php echo esc_html(Handy_Custom_Recipes_Utils::format_servings($servings)); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Accordion Content Section -->
        <div class="handy-single-recipe-accordion">
            <div class="handy-accordion-container">

                <!-- Ingredients Section -->
                <?php 
                $ingredients = function_exists('get_field') ? get_field('ingredients') : '';
                if (!empty($ingredients)) : ?>
                <div class="handy-accordion-section">
                    <button class="handy-accordion-header active" data-section="ingredients">
                        <span>Ingredients</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="handy-accordion-content active" id="ingredients">
                        <div class="handy-ingredients-content">
                            <?php echo wp_kses_post($ingredients); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Prep Instructions Section -->
                <?php 
                $prep_instructions = function_exists('get_field') ? get_field('prep_instructions') : '';
                if (!empty($prep_instructions)) : ?>
                <div class="handy-accordion-section">
                    <button class="handy-accordion-header" data-section="prep-instructions">
                        <span>Prep Instructions</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="handy-accordion-content" id="prep-instructions">
                        <div class="handy-prep-content">
                            <?php the_field('prep_instructions'); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Cooking Instructions Section -->
                <?php 
                $cooking_instructions = function_exists('get_field') ? get_field('cooking_instructions') : '';
                if (!empty($cooking_instructions)) : ?>
                <div class="handy-accordion-section">
                    <button class="handy-accordion-header" data-section="cooking">
                        <span>Cooking Instructions</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="handy-accordion-content" id="cooking">
                        <div class="handy-cooking-content">
                            <?php the_field('cooking_instructions'); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Featured Products Section -->
        <?php
        $related_products = function_exists('get_field') ? get_field('related_products') : '';
        if (!empty($related_products) && is_array($related_products)): 
            $product_ids = array();
            
            // Extract product IDs from ACF URLs
            foreach ($related_products as $related_product) {
                if (!empty($related_product['related_product_url'])) {
                    $product_id = Handy_Custom_Products_Renderer::extract_product_id_from_url($related_product['related_product_url']);
                    if ($product_id) {
                        $product_ids[] = $product_id;
                    }
                }
            }
            
            // Only show section if we have valid product IDs
            if (!empty($product_ids)):
                // Limit to 2 products maximum
                $product_ids = array_slice($product_ids, 0, 2);
                $product_count = count($product_ids);
                $renderer = new Handy_Custom_Products_Renderer();
        ?>
        <div class="handy-featured-products-section">
            <div class="handy-featured-products-content">
                <h2 class="handy-featured-products-title">Featured Products</h2>
            </div>
        </div>
        <?php echo $renderer->render_specific_products($product_ids, array('columns' => $product_count)); ?>
        <?php endif; ?>
        <?php endif; ?>

    <?php endwhile; ?>
</div>

<?php get_footer(); ?>
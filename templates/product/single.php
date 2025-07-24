<?php
/**
 * Single Product Template
 * 
 * Template for displaying individual product posts.
 * Implements two-column layout with breadcrumbs, social sharing, and tabbed content sections.
 * 
 * Based on instructions: Header with breadcrumbs matching archive shortcode,
 * two-column layout (left: title/social/content/where-to-buy, right: images),
 * followed by tabbed sections for specifications, features, cooking, nutrition, allergens.
 */

get_header(); ?>

<div class="handy-single-product-container">
    <?php while (have_posts()) : the_post(); ?>
        
        <!-- Breadcrumbs Section -->
        <div class="handy-single-product-breadcrumbs-wrapper">
            <div class="handy-single-product-breadcrumbs">
                <?php
                if (function_exists('yoast_breadcrumb')) {
                    yoast_breadcrumb('<nav class="handy-breadcrumb-nav">', '</nav>');
                }
                ?>
            </div>
        </div>

        <!-- Two Column Main Content Section -->
        <div class="handy-single-product-main">
            <div class="handy-product-content-wrapper">
                
                <!-- Left Column -->
                <div class="handy-product-left-column">
                    <!-- Product Title -->
                    <h1 class="handy-product-title"><?php the_title(); ?></h1>
                    
                    <!-- Sub Header -->
                    <?php 
                    $sub_header = function_exists('get_field') ? get_field('sub_header') : '';
                    if (!empty($sub_header)) : ?>
                        <h3 class="handy-product-sub-header" style="color: #0145AB;"><?php echo esc_html($sub_header); ?></h3>
                    <?php endif; ?>
                    
                    <!-- Social Icons Row -->
                    <div class="handy-product-social-row">
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
                    
                    <!-- Product Content -->
                    <div class="handy-product-content">
                        <?php the_content(); ?>
                    </div>
                    
                    <!-- Where to Buy Section -->
                    <div class="handy-where-to-buy-section">
                        <h3 class="handy-where-to-buy-title">Where to buy?</h3>
                        
                        <div class="handy-buy-options">
                            <!-- Retailer Row -->
                            <div class="handy-buy-row">
                                <div class="handy-buy-label">Retailer</div>
                                <div class="handy-buy-button-wrapper">
                                    <a href="/contact" class="handy-buy-button">
                                        <i class="fas fa-comment"></i>
                                        <span>Contact a sales rep</span>
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Individual Row -->
                            <div class="handy-buy-row">
                                <div class="handy-buy-label">Individual</div>
                                <div class="handy-buy-button-wrapper">
                                    <a href="https://www.instacart.com/store" target="_blank" rel="noopener" class="handy-buy-button">
                                        <i class="fas fa-shopping-cart"></i>
                                        <span>Instacart</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="handy-product-right-column">
                    <!-- Product Thumbnail Image -->
                    <div class="handy-product-thumbnail">
                        <?php if (has_post_thumbnail()) : ?>
                            <?php the_post_thumbnail('large', array('class' => 'handy-product-main-image')); ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Carton Image -->
                    <div class="handy-product-carton">
                        <?php 
                        $carton_image = function_exists('get_field') ? get_field('carton_image') : null;
                        if (!empty($carton_image) && is_array($carton_image) && !empty($carton_image['url'])) : ?>
                            <img src="<?php echo esc_url($carton_image['url']); ?>" 
                                 alt="<?php echo esc_attr(!empty($carton_image['alt']) ? $carton_image['alt'] : get_the_title()); ?>" 
                                 class="handy-product-carton-image">
                        <?php endif; ?>
                    </div>
                </div>
                
            </div>
        </div>

        <!-- Accordion Content Section -->
        <div class="handy-single-product-accordion full-width">
            <div class="handy-accordion-container">
                
                <!-- Specifications Section -->
                <div class="handy-accordion-section">
                    <button class="handy-accordion-header active" data-section="specifications">
                        <span>Specifications</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="handy-accordion-content active" id="specifications">
                        <div class="handy-specifications-content">
                            <!-- Specifications Table -->
                            <table class="handy-product-specs-table">
                                <tbody>
                                    <?php 
                                    // Safely get ACF field values with null checks
                                    if (function_exists('get_field')) {
                                        $spec_fields = array(
                                            'product_size' => 'Product Size',
                                            'carton_size' => 'Carton Size', 
                                            'case_pack_size' => 'Case Pack Size',
                                            'item_number' => 'Item Number',
                                            'upc_number' => 'UPC',
                                            'gtin_code' => 'GTIN Code'
                                        );
                                        
                                        foreach ($spec_fields as $field_key => $field_label) {
                                            $field_value = get_field($field_key);
                                            if (!empty($field_value)) :
                                    ?>
                                    <tr>
                                        <td class="handy-spec-label"><?php echo esc_html($field_label); ?></td>
                                        <td class="handy-spec-value"><?php echo esc_html($field_value); ?></td>
                                    </tr>
                                    <?php 
                                            endif;
                                        }
                                    } else {
                                        echo '<tr><td colspan="2">Specifications not available (ACF not loaded)</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                            
                            <!-- Special Logos Gallery -->
                            <?php 
                            $special_logos = function_exists('get_field') ? get_field('special_logos') : null;
                            if (!empty($special_logos) && is_array($special_logos)) : ?>
                                <div class="handy-special-logos-gallery">
                                    <?php $logo_count = count($special_logos); ?>
                                    <?php foreach ($special_logos as $index => $logo) : ?>
                                        <?php if (is_array($logo) && !empty($logo['sizes']['medium'])) : ?>
                                            <div class="handy-special-logo-item">
                                                <img src="<?php echo esc_url($logo['sizes']['medium']); ?>" 
                                                     alt="<?php echo esc_attr(!empty($logo['alt']) ? $logo['alt'] : 'Special Logo'); ?>" 
                                                     class="handy-special-logo-image">
                                            </div>
                                            <?php if ($index < $logo_count - 1) : ?>
                                                <span class="handy-logo-separator">|</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Features and Benefits Section -->
                <div class="handy-accordion-section">
                    <button class="handy-accordion-header" data-section="features">
                        <span>Features and Benefits</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="handy-accordion-content" id="features">
                        <div class="handy-features-content">
                            <?php if (get_field('features_benefits')) : ?>
                                <?php the_field('features_benefits'); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Ingredients Section -->
                <div class="handy-accordion-section">
                    <button class="handy-accordion-header" data-section="ingredients">
                        <span>Ingredients</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="handy-accordion-content" id="ingredients">
                        <div class="handy-ingredients-content">
                            <?php 
                            $ingredients = function_exists('get_field') ? get_field('ingredients') : '';
                            if (!empty($ingredients)) : ?>
                                <?php echo wp_kses_post($ingredients); ?>
                            <?php else : ?>
                                <p>Ingredient information not available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Cooking Instructions Section -->
                <div class="handy-accordion-section">
                    <button class="handy-accordion-header" data-section="cooking">
                        <span>Cooking Instructions</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="handy-accordion-content" id="cooking">
                        <div class="handy-cooking-content">
                            <?php if (get_field('cooking_instructions')) : ?>
                                <div class="handy-cooking-instructions">
                                    <?php the_field('cooking_instructions'); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (get_field('serving_suggestions')) : ?>
                                <div class="handy-serving-suggestions">
                                    <?php the_field('serving_suggestions'); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php 
                            $food_handling = get_field('food_handling');
                            if ($food_handling && !empty($food_handling)) : ?>
                                <div class="handy-food-handling">
                                    <h4>Food Handling:</h4>
                                    <p><?php echo implode(' | ', $food_handling); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Nutritional Facts Section -->
                <div class="handy-accordion-section">
                    <button class="handy-accordion-header" data-section="nutrition">
                        <span>Nutritional Facts</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="handy-accordion-content" id="nutrition">
                        <div class="handy-nutrition-content">
                            <?php 
                            $nutritional_facts = get_field('nutritional_facts');
                            if ($nutritional_facts) : ?>
                                <div class="handy-nutritional-facts-image">
                                    <img src="<?php echo esc_url($nutritional_facts['url']); ?>" 
                                         alt="<?php echo esc_attr($nutritional_facts['alt']); ?>" 
                                         class="handy-nutrition-image">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Allergens Section -->
                <div class="handy-accordion-section">
                    <button class="handy-accordion-header" data-section="allergens">
                        <span>Allergen</span>
                        <i class="fas fa-chevron-down"></i>
                    </button>
                    <div class="handy-accordion-content" id="allergens">
                        <div class="handy-allergens-content">
                            <?php 
                            $allergens = get_field('allergens');
                            if ($allergens && !empty($allergens) && !in_array('None', $allergens)) : ?>
                                <div class="handy-allergen-icons">
                                    <?php 
                                    $allergen_count = count($allergens);
                                    foreach ($allergens as $index => $allergen) : 
                                        // Map allergen names to image files
                                        $allergen_images = array(
                                            'Milk' => 'milk.webp',
                                            'Eggs' => 'eggs.webp',
                                            'Fish (e.g., bass, flounder, cod)' => 'fish.webp',
                                            'Crustacean Shellfish (e.g., crab, lobster, shrimp)' => 'shellfish.webp',
                                            'Tree Nuts (e.g., almonds, walnuts, pecans)' => 'nuts.webp',
                                            'Peanuts' => 'peanuts.webp',
                                            'Wheat' => 'wheat.webp',
                                            'Soybeans' => 'soy.webp',
                                            'Sesame' => 'sesame.webp'
                                        );
                                        
                                        if (isset($allergen_images[$allergen])) : ?>
                                            <div class="handy-allergen-icon-item">
                                                <img src="<?php echo HANDY_CUSTOM_PLUGIN_URL; ?>assets/images/<?php echo $allergen_images[$allergen]; ?>" 
                                                     alt="<?php echo esc_attr($allergen); ?>" 
                                                     class="handy-allergen-icon">
                                            </div>
                                            <?php if ($index < $allergen_count - 1) : ?>
                                                <span class="handy-allergen-separator">|</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            <?php else : ?>
                                <p class="handy-no-allergens">No Allergens Known for this product</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    <?php endwhile; ?>
</div>

<?php get_footer(); ?>
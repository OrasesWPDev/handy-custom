<?php
/**
 * Admin functionality for the Handy Custom plugin
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
    exit;
}

class Handy_Custom_Admin {

    /**
     * Initialize admin functionality
     */
    public static function init() {
        add_action('restrict_manage_posts', array(__CLASS__, 'add_taxonomy_filters'));
        add_filter('parse_query', array(__CLASS__, 'filter_posts_by_taxonomy'));
        
        // Add display order functionality for product categories
        add_action('product-category_add_form_fields', array(__CLASS__, 'add_category_display_order_field'));
        add_action('product-category_edit_form_fields', array(__CLASS__, 'edit_category_display_order_field'));
        add_action('edited_product-category', array(__CLASS__, 'save_category_display_order'));
        add_action('create_product-category', array(__CLASS__, 'save_category_display_order'));
        add_filter('manage_edit-product-category_columns', array(__CLASS__, 'add_category_display_order_column'));
        add_filter('manage_product-category_custom_column', array(__CLASS__, 'populate_category_display_order_column'), 10, 3);
    }

    /**
     * Add taxonomy filter dropdowns to the admin posts list
     *
     * @param string $post_type The current post type
     */
    public static function add_taxonomy_filters($post_type) {
        // Only add filters for product post type
        if ($post_type !== 'product') {
            return;
        }

        // Get priority product taxonomies for admin filtering
        // Order reflects priority per user requirements: Categories, Market Segment, Cooking Method, Menu Occasions, Grade
        $taxonomies = array(
            'product-category' => 'Category',
            'market-segment' => 'Market Segment', 
            'product-cooking-method' => 'Cooking Method',
            'product-menu-occasion' => 'Menu Occasion',
            'grade' => 'Grade'
        );

        foreach ($taxonomies as $taxonomy => $label) {
            // Check if taxonomy exists
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            // Get current filter value
            $selected = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';

            // Get all terms for this taxonomy (including those without published posts)
            $terms_args = array(
                'taxonomy' => $taxonomy,
                'hide_empty' => false, // Show ALL terms regardless of publish status
                'orderby' => 'name',
                'order' => 'ASC'
            );
            
            // For product categories, show hierarchical structure
            if ($taxonomy === 'product-category') {
                $terms_args['orderby'] = 'meta_value_num name';
                $terms_args['meta_key'] = 'display_order';
            }
            
            $terms = get_terms($terms_args);

            if (!empty($terms) && !is_wp_error($terms)) {
                echo '<select name="' . esc_attr($taxonomy) . '" id="filter-by-' . esc_attr($taxonomy) . '">';
                echo '<option value="">' . sprintf(__('All %s', 'handy-custom'), esc_html($label)) . '</option>';
                
                foreach ($terms as $term) {
                    $selected_attr = selected($selected, $term->slug, false);
                    
                    // For categories, show hierarchical structure
                    $term_name = $term->name;
                    if ($taxonomy === 'product-category' && $term->parent > 0) {
                        $term_name = '— ' . $term_name; // Indent subcategories
                    }
                    
                    echo '<option value="' . esc_attr($term->slug) . '"' . $selected_attr . '>';
                    echo esc_html($term_name) . ' (' . $term->count . ')';
                    echo '</option>';
                }
                
                echo '</select>';
            }
        }
    }

    /**
     * Filter posts based on selected taxonomy filters
     *
     * @param WP_Query $query The WordPress query object
     */
    public static function filter_posts_by_taxonomy($query) {
        global $pagenow;

        // Only apply to admin product listings
        if (!is_admin() || $pagenow !== 'edit.php' || !isset($_GET['post_type']) || $_GET['post_type'] !== 'product') {
            return;
        }

        // Get priority product taxonomies for admin filtering - updated per user requirements
        $taxonomies = array(
            'product-category',
            'market-segment',
            'product-cooking-method',
            'product-menu-occasion',
            'grade'
        );

        $tax_query = array();

        foreach ($taxonomies as $taxonomy) {
            if (!empty($_GET[$taxonomy])) {
                $tax_query[] = array(
                    'taxonomy' => $taxonomy,
                    'field' => 'slug',
                    'terms' => sanitize_text_field($_GET[$taxonomy])
                );
            }
        }

        // Apply taxonomy filters if any are set
        if (!empty($tax_query)) {
            $tax_query['relation'] = 'AND';
            $query->set('tax_query', $tax_query);
        }
        
        // Ensure we can filter both published and draft products per user requirements
        if (!empty($tax_query)) {
            $current_post_status = $query->get('post_status');
            if (empty($current_post_status)) {
                $query->set('post_status', array('publish', 'draft'));
            }
        }
    }

    /**
     * Add custom columns to product admin list (future enhancement)
     */
    public static function add_product_columns($columns) {
        // Add custom columns after title
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'title') {
                $new_columns['product_category'] = __('Category', 'handy-custom');
                $new_columns['grade'] = __('Grade', 'handy-custom');
                $new_columns['brand'] = __('Brand', 'handy-custom');
            }
        }
        return $new_columns;
    }

    /**
     * Populate custom columns with data (future enhancement)
     */
    public static function populate_product_columns($column, $post_id) {
        switch ($column) {
            case 'product_category':
                $terms = get_the_terms($post_id, 'product-category');
                if ($terms && !is_wp_error($terms)) {
                    $term_names = wp_list_pluck($terms, 'name');
                    echo esc_html(implode(', ', $term_names));
                } else {
                    echo '—';
                }
                break;
                
            case 'grade':
                $terms = get_the_terms($post_id, 'grade');
                if ($terms && !is_wp_error($terms)) {
                    $term_names = wp_list_pluck($terms, 'name');
                    echo esc_html(implode(', ', $term_names));
                } else {
                    echo '—';
                }
                break;
                
            case 'brand':
                $terms = get_the_terms($post_id, 'brand');
                if ($terms && !is_wp_error($terms)) {
                    $term_names = wp_list_pluck($terms, 'name');
                    echo esc_html(implode(', ', $term_names));
                } else {
                    echo '—';
                }
                break;
        }
    }

    /**
     * Add display order field to category add form (only for top-level categories)
     *
     * @param string $taxonomy The taxonomy slug
     */
    public static function add_category_display_order_field($taxonomy) {
        if ($taxonomy !== 'product-category') {
            return;
        }
        
        // Get next available display order
        $next_order = self::get_next_display_order();
        ?>
        <div class="form-field">
            <label for="display_order"><?php _e('Display Order', 'handy-custom'); ?></label>
            <input type="number" id="display_order" name="display_order" value="<?php echo esc_attr($next_order); ?>" min="1" max="999" />
            <p class="description"><?php _e('Order for displaying top-level categories on frontend (1 = first). Only applies to top-level categories.', 'handy-custom'); ?></p>
        </div>
        <?php
    }

    /**
     * Add display order field to category edit form (only for top-level categories)
     *
     * @param WP_Term $term The term object
     */
    public static function edit_category_display_order_field($term) {
        // Only show for top-level categories
        if ($term->parent !== 0) {
            return;
        }
        
        $display_order = get_term_meta($term->term_id, 'display_order', true);
        ?>
        <tr class="form-field">
            <th scope="row">
                <label for="display_order"><?php _e('Display Order', 'handy-custom'); ?></label>
            </th>
            <td>
                <input type="number" id="display_order" name="display_order" value="<?php echo esc_attr($display_order); ?>" min="1" max="999" />
                <p class="description"><?php _e('Order for displaying this category on frontend (1 = first). Only applies to top-level categories.', 'handy-custom'); ?></p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save display order when category is created or updated
     *
     * @param int $term_id The term ID
     */
    public static function save_category_display_order($term_id) {
        if (!isset($_POST['display_order'])) {
            return;
        }

        $display_order = absint($_POST['display_order']);
        
        if ($display_order > 0) {
            update_term_meta($term_id, 'display_order', $display_order);
        } else {
            delete_term_meta($term_id, 'display_order');
        }
    }

    /**
     * Add display order column to category admin list
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public static function add_category_display_order_column($columns) {
        // Add display order column after name
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'name') {
                $new_columns['display_order'] = __('Display Order', 'handy-custom');
            }
        }
        return $new_columns;
    }

    /**
     * Populate display order column with data
     *
     * @param string $content Column content
     * @param string $column_name Column name
     * @param int $term_id Term ID
     * @return string Column content
     */
    public static function populate_category_display_order_column($content, $column_name, $term_id) {
        if ($column_name === 'display_order') {
            $term = get_term($term_id);
            
            // Only show order for top-level categories
            if ($term && $term->parent === 0) {
                $display_order = get_term_meta($term_id, 'display_order', true);
                return !empty($display_order) ? esc_html($display_order) : '—';
            } else {
                return '—';
            }
        }
        
        return $content;
    }

    /**
     * Get the next available display order number
     *
     * @return int Next available order number
     */
    private static function get_next_display_order() {
        $terms = get_terms(array(
            'taxonomy' => 'product-category',
            'parent' => 0,
            'hide_empty' => false,
            'meta_query' => array(
                array(
                    'key' => 'display_order',
                    'compare' => 'EXISTS'
                )
            )
        ));

        $max_order = 0;
        if (!empty($terms) && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                $order = get_term_meta($term->term_id, 'display_order', true);
                if (is_numeric($order) && $order > $max_order) {
                    $max_order = (int) $order;
                }
            }
        }

        return $max_order + 1;
    }
}
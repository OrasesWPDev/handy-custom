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
        // Order reflects priority: market-segment, cooking-method, product-type, grade, size
        $taxonomies = array(
            'market-segment' => 'Market Segment',
            'product-cooking-method' => 'Cooking Method',
            'product-type' => 'Product Type',
            'grade' => 'Grade',
            'size' => 'Size'
        );

        foreach ($taxonomies as $taxonomy => $label) {
            // Check if taxonomy exists
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }

            // Get current filter value
            $selected = isset($_GET[$taxonomy]) ? $_GET[$taxonomy] : '';

            // Get all terms for this taxonomy
            $terms = get_terms(array(
                'taxonomy' => $taxonomy,
                'hide_empty' => true,
                'orderby' => 'name',
                'order' => 'ASC'
            ));

            if (!empty($terms) && !is_wp_error($terms)) {
                echo '<select name="' . esc_attr($taxonomy) . '" id="filter-by-' . esc_attr($taxonomy) . '">';
                echo '<option value="">' . sprintf(__('All %s', 'handy-custom'), esc_html($label)) . '</option>';
                
                foreach ($terms as $term) {
                    $selected_attr = selected($selected, $term->slug, false);
                    echo '<option value="' . esc_attr($term->slug) . '"' . $selected_attr . '>';
                    echo esc_html($term->name) . ' (' . $term->count . ')';
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

        // Get priority product taxonomies for admin filtering
        $taxonomies = array(
            'market-segment',
            'product-cooking-method',
            'product-type',
            'grade',
            'size'
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
}
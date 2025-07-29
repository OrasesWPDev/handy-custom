<?php
/**
 * Featured Recipes Admin functionality
 * Handles admin column, star toggle, and AJAX processing
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

class Handy_Custom_Recipes_Featured_Admin {
	
	/**
	 * Meta key for featured status
	 */
	const FEATURED_META_KEY = '_is_featured_recipe';
	
	/**
	 * Maximum number of featured recipes allowed
	 */
	const MAX_FEATURED_RECIPES = 3;
	
	/**
	 * Initialize the admin functionality
	 */
	public static function init() {
		// Add admin column
		add_filter('manage_recipe_posts_columns', array(__CLASS__, 'add_featured_column'));
		add_action('manage_recipe_posts_custom_column', array(__CLASS__, 'display_featured_column'), 10, 2);
		
		// AJAX handler
		add_action('wp_ajax_toggle_featured_recipe_status', array(__CLASS__, 'ajax_toggle_featured_status'));
		
		// Enqueue admin scripts
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_scripts'));
		
		// Add sortable column
		add_filter('manage_edit-recipe_sortable_columns', array(__CLASS__, 'add_featured_sortable_column'));
		add_action('pre_get_posts', array(__CLASS__, 'featured_column_orderby'));
	}
	
	/**
	 * Add Featured column to recipes admin
	 */
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
	
	/**
	 * Display Featured column content
	 */
	public static function display_featured_column($column_name, $post_id) {
		if ($column_name == 'featured_recipe_admin_col') {
			$post_status = get_post_status($post_id);
			$is_featured = get_post_meta($post_id, self::FEATURED_META_KEY, true);
			$star_class = $is_featured ? 'dashicons-star-filled' : 'dashicons-star-empty';
			
			// Only show interactive stars for published recipes
			if ($post_status === 'publish') {
				$title_text = $is_featured ? 'Unmark as Featured' : 'Mark as Featured';
				$new_status = $is_featured ? '0' : '1';
				
				printf(
					'<a href="#" class="toggle-featured-status" data-postid="%d" data-status="%s" data-nonce="%s" title="%s">
						<span class="dashicons %s" style="font-size: 20px; color: #ffb900;"></span>
					</a>',
					esc_attr($post_id),
					esc_attr($new_status),
					wp_create_nonce('toggle_featured_recipe_status_nonce_' . $post_id),
					esc_attr($title_text),
					esc_attr($star_class)
				);
			} else {
				// Show non-interactive star for drafts/unpublished posts
				printf(
					'<span class="dashicons %s" style="font-size: 20px; color: #ccc;" title="Only published recipes can be featured"></span>',
					esc_attr($star_class)
				);
			}
		}
	}
	
	/**
	 * Handle AJAX toggle featured status
	 */
	public static function ajax_toggle_featured_status() {
		$post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
		
		if (!check_ajax_referer('toggle_featured_recipe_status_nonce_' . $post_id, 'nonce', false)) {
			wp_send_json_error(array('message' => 'Security check failed'), 403);
		}
		
		if (!current_user_can('edit_post', $post_id)) {
			wp_send_json_error(array('message' => 'Permission denied'), 403);
		}
		
		// Only allow featuring published recipes
		if (get_post_status($post_id) !== 'publish') {
			wp_send_json_error(array('message' => 'Only published recipes can be featured'));
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
	
	/**
	 * Get count of currently featured recipes
	 */
	private static function get_featured_recipes_count() {
		$args = array(
			'post_type' => 'recipe',
			'post_status' => 'publish',
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
	
	/**
	 * Enqueue admin scripts
	 */
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
	
	/**
	 * Make Featured column sortable
	 */
	public static function add_featured_sortable_column($columns) {
		$columns['featured_recipe_admin_col'] = 'featured_recipe_admin_col';
		return $columns;
	}
	
	/**
	 * Handle sorting by featured status
	 */
	public static function featured_column_orderby($query) {
		if (!is_admin() || !$query->is_main_query()) {
			return;
		}
		
		if ('featured_recipe_admin_col' == $query->get('orderby')) {
			$query->set('meta_key', self::FEATURED_META_KEY);
			$query->set('orderby', 'meta_value');
		}
	}
	
	/**
	 * Get featured recipes (for use by shortcode)
	 */
	public static function get_featured_recipes($limit = 3) {
		$args = array(
			'post_type' => 'recipe',
			'post_status' => 'publish',
			'posts_per_page' => intval($limit),
			'orderby' => 'date',
			'order' => 'DESC',
			'meta_query' => array(
				array(
					'key' => self::FEATURED_META_KEY,
					'value' => '1',
					'compare' => '='
				)
			)
		);
		
		return new WP_Query($args);
	}
}
<?php
/**
 * Handy Custom Plugin
 *
 * @package           Handy_Custom
 * @author            Orases
 * @copyright         2023 Orases
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Handy Custom
 * Plugin URI:        https://github.com/OrasesWPDev/handy-custom
 * Description:       A handy collection of custom WordPress functionality and utilities.
 * Version:           1.0.0
 * Requires at least: 5.3
 * Requires PHP:      7.2
 * Author:            Orases
 * Author URI:        https://orases.com
 * Text Domain:       handy-custom
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://github.com/OrasesWPDev/handy-custom
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

// Define plugin constants
define('HANDY_CUSTOM_VERSION', '1.0.0');
define('HANDY_CUSTOM_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Debug mode - toggle to enable/disable logging
define('HANDY_CUSTOM_DEBUG', false);

// Simple logger function
function handy_custom_log($message, $level = 'info') {
	if (!defined('HANDY_CUSTOM_DEBUG') || !HANDY_CUSTOM_DEBUG) {
		return;
	}

	$log_dir = HANDY_CUSTOM_PLUGIN_DIR . 'logs/';

	// Create logs directory if it doesn't exist
	if (!file_exists($log_dir)) {
		wp_mkdir_p($log_dir);
		file_put_contents($log_dir . 'index.php', '<?php // Silence is golden');
	}

	$timestamp = date('Y-m-d H:i:s');
	$log_file = $log_dir . 'handy-custom-' . date('Y-m-d') . '.log';
	$formatted_message = "[$timestamp] [$level] $message" . PHP_EOL;

	file_put_contents($log_file, $formatted_message, FILE_APPEND);
}

// Basic plugin initialization
function handy_custom_init() {
	handy_custom_log('Plugin initialized');
}
add_action('plugins_loaded', 'handy_custom_init');

// Add sortable taxonomy columns to admin list tables
function handy_custom_add_taxonomy_columns($columns) {
	global $post_type;

	// Define which taxonomies to add for each post type
	$post_type_taxonomies = array(
		'product' => array('cooking-method', 'grade', 'market-segment', 'menu-occasion',
			'product-category', 'product-type', 'size'),
		'recipe'  => array('cooking-method', 'recipe-category')
	);

	// Only proceed if we have taxonomies defined for this post type
	if (!isset($post_type_taxonomies[$post_type])) {
		return $columns;
	}

	// Add a column for each taxonomy
	foreach ($post_type_taxonomies[$post_type] as $tax) {
		$tax_obj = get_taxonomy($tax);
		if ($tax_obj) {
			$columns[$tax] = $tax_obj->labels->name;
		}
	}

	return $columns;
}
add_filter('manage_posts_columns', 'handy_custom_add_taxonomy_columns');

// Fill the taxonomy columns with values
function handy_custom_fill_taxonomy_columns($column_name, $post_id) {
	$taxonomies = array('cooking-method', 'grade', 'market-segment', 'menu-occasion',
		'product-category', 'product-type', 'size', 'recipe-category');

	if (in_array($column_name, $taxonomies)) {
		$terms = get_the_terms($post_id, $column_name);
		if (!empty($terms) && !is_wp_error($terms)) {
			$term_links = array();
			foreach ($terms as $term) {
				$term_links[] = sprintf(
					'<a href="%s">%s</a>',
					esc_url(add_query_arg(array($column_name => $term->slug), admin_url('edit.php?post_type=' . get_post_type($post_id)))),
					esc_html($term->name)
				);
			}
			echo implode(', ', $term_links);
		} else {
			echo '—';
		}
	}
}
add_action('manage_posts_custom_column', 'handy_custom_fill_taxonomy_columns', 10, 2);

// Make taxonomy columns sortable
function handy_custom_make_taxonomy_columns_sortable($sortable_columns) {
	global $post_type;

	$taxonomies = array();

	if ($post_type === 'product') {
		$taxonomies = array('cooking-method', 'grade', 'market-segment', 'menu-occasion',
			'product-category', 'product-type', 'size');
	} elseif ($post_type === 'recipe') {
		$taxonomies = array('cooking-method', 'recipe-category');
	}

	foreach ($taxonomies as $tax) {
		$sortable_columns[$tax] = $tax;
	}

	return $sortable_columns;
}
add_filter('manage_edit-product_sortable_columns', 'handy_custom_make_taxonomy_columns_sortable');
add_filter('manage_edit-recipe_sortable_columns', 'handy_custom_make_taxonomy_columns_sortable');

// Adjust the query to sort by taxonomy
function handy_custom_sort_by_taxonomy($query) {
	if (!is_admin() || !$query->is_main_query()) {
		return;
	}

	$taxonomies = array('cooking-method', 'grade', 'market-segment', 'menu-occasion',
		'product-category', 'product-type', 'size', 'recipe-category');

	$orderby = $query->get('orderby');

	if (in_array($orderby, $taxonomies)) {
		$query->set('meta_key', '');
		$query->set('orderby', 'tax_' . $orderby);
		$query->set('tax_query', array(
			array(
				'taxonomy' => $orderby,
				'field'    => 'id',
				'terms'    => get_terms(array(
					'taxonomy'   => $orderby,
					'fields'     => 'ids',
					'hide_empty' => false,
				))
			)
		));
	}
}
add_action('pre_get_posts', 'handy_custom_sort_by_taxonomy');
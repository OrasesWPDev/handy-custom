<?php
/**
 * Base utility functions for shared functionality
 *
 * @package Handy_Custom
 */

if (!defined('ABSPATH')) {
	exit;
}

abstract class Handy_Custom_Base_Utils {

	/**
	 * Cache for taxonomy terms to avoid repeated database queries
	 *
	 * @var array
	 */
	private static $term_cache = array();

	/**
	 * Cache for taxonomy term existence checks
	 *
	 * @var array
	 */
	private static $term_exists_cache = array();

	/**
	 * Get the cache group name for taxonomy terms
	 *
	 * @return string
	 */
	private static function get_cache_group() {
		return 'handy_custom_terms';
	}

	/**
	 * Get the cache group name for query results
	 *
	 * @return string
	 */
	private static function get_query_cache_group() {
		return 'handy_custom_queries';
	}

	/**
	 * Get taxonomy mapping for filter keys (to be implemented by child classes)
	 *
	 * @return array
	 */
	abstract public static function get_taxonomy_mapping();

	/**
	 * Convert filter key to taxonomy name
	 *
	 * @param string $key Filter key
	 * @return string|false
	 */
	public static function get_taxonomy_name($key) {
		$mapping = static::get_taxonomy_mapping();
		return isset($mapping[$key]) ? $mapping[$key] : false;
	}

	/**
	 * Get terms for a specific taxonomy with caching and error handling
	 *
	 * @param string $taxonomy Taxonomy name
	 * @param array $args Additional arguments
	 * @return array
	 */
	public static function get_taxonomy_terms($taxonomy, $args = array()) {
		$default_args = array(
			'taxonomy' => $taxonomy,
			'hide_empty' => false,
			'orderby' => 'name',
			'order' => 'ASC'
		);

		$args = wp_parse_args($args, $default_args);
		
		// Create cache key based on taxonomy and arguments
		$cache_key = 'handy_custom_terms_' . md5($taxonomy . serialize($args));
		
		// Check WordPress object cache first
		$terms = wp_cache_get($cache_key, self::get_cache_group());
		
		if (false === $terms) {
			// Check internal static cache
			$internal_cache_key = md5($cache_key);
			if (isset(self::$term_cache[$internal_cache_key])) {
				$terms = self::$term_cache[$internal_cache_key];
			} else {
				// Fetch from database
				$terms = get_terms($args);

				if (is_wp_error($terms)) {
					Handy_Custom_Logger::log("Error getting terms for taxonomy {$taxonomy}: " . $terms->get_error_message(), 'error');
					$terms = array();
				}

				// Cache in both WordPress object cache and internal static cache
				wp_cache_set($cache_key, $terms, self::get_cache_group(), HOUR_IN_SECONDS);
				self::$term_cache[$internal_cache_key] = $terms;
			}
		}

		return $terms;
	}

	/**
	 * Check if a term exists in a taxonomy with caching
	 *
	 * @param string $slug Term slug
	 * @param string $taxonomy Taxonomy name
	 * @return WP_Term|false
	 */
	public static function get_term_by_slug($slug, $taxonomy) {
		$cache_key = "{$taxonomy}_{$slug}";
		
		if (isset(self::$term_exists_cache[$cache_key])) {
			return self::$term_exists_cache[$cache_key];
		}

		$term = get_term_by('slug', $slug, $taxonomy);
		
		if (!$term || is_wp_error($term)) {
			self::$term_exists_cache[$cache_key] = false;
			return false;
		}

		self::$term_exists_cache[$cache_key] = $term;
		return $term;
	}

	/**
	 * Validate and sanitize filter parameters
	 *
	 * @param array $filters Raw filter parameters
	 * @return array Sanitized filters
	 */
	public static function sanitize_filters($filters) {
		$allowed_keys = array_keys(static::get_taxonomy_mapping());
		$sanitized = array();

		// Sanitize taxonomy-based filters
		foreach ($allowed_keys as $key) {
			$sanitized[$key] = isset($filters[$key]) ? sanitize_text_field($filters[$key]) : '';
		}

		// Sanitize pagination parameters
		if (isset($filters['per_page'])) {
			$sanitized['per_page'] = absint($filters['per_page']);
		}
		
		if (isset($filters['page'])) {
			$sanitized['page'] = max(1, absint($filters['page'])); // Ensure minimum page is 1
		}
		
		// Sanitize display parameter (for products)
		if (isset($filters['display'])) {
			$sanitized['display'] = in_array($filters['display'], array('categories', 'list')) ? $filters['display'] : 'categories';
		}

		return $sanitized;
	}

	/**
	 * Clear cached taxonomy terms (useful for testing or when terms are updated)
	 *
	 * @param string $taxonomy Optional specific taxonomy to clear
	 */
	public static function clear_term_cache($taxonomy = null) {
		if ($taxonomy) {
			// Clear specific taxonomy from caches
			foreach (self::$term_cache as $key => $value) {
				if (strpos($key, $taxonomy) !== false) {
					unset(self::$term_cache[$key]);
				}
			}
			foreach (self::$term_exists_cache as $key => $value) {
				if (strpos($key, $taxonomy . '_') === 0) {
					unset(self::$term_exists_cache[$key]);
				}
			}
		} else {
			// Clear all caches
			self::$term_cache = array();
			self::$term_exists_cache = array();
		}

		// Clear WordPress object cache for taxonomy terms
		if (wp_cache_supports('flush_group')) {
			wp_cache_flush_group(self::get_cache_group());
			Handy_Custom_Logger::log('Term cache group flushed' . ($taxonomy ? " for taxonomy: {$taxonomy}" : ''), 'info');
		} else {
			// Fallback for cache backends that don't support group flushing
			// Clear individual cache keys by iterating through known patterns
			foreach (self::$term_cache as $internal_key => $value) {
				$cache_key = 'handy_custom_terms_' . $internal_key;
				wp_cache_delete($cache_key, self::get_cache_group());
			}
			Handy_Custom_Logger::log('Term cache cleared via individual key deletion' . ($taxonomy ? " for taxonomy: {$taxonomy}" : ''), 'info');
		}
	}

	/**
	 * Generate cache key for query results
	 *
	 * @param array $query_args WP_Query arguments
	 * @param string $query_type Type of query (products, recipes, etc.)
	 * @return string Cache key
	 */
	public static function generate_query_cache_key($query_args, $query_type = 'query') {
		// Remove variable elements that shouldn't affect caching
		$cache_args = $query_args;
		unset($cache_args['cache_results']);
		unset($cache_args['update_post_meta_cache']);
		unset($cache_args['update_post_term_cache']);
		
		// Sort arrays to ensure consistent cache keys
		ksort($cache_args);
		if (isset($cache_args['tax_query'])) {
			ksort($cache_args['tax_query']);
		}
		
		$cache_key = 'handy_' . $query_type . '_' . md5(serialize($cache_args));
		return $cache_key;
	}

	/**
	 * Get cached query results
	 *
	 * @param string $cache_key Cache key
	 * @return WP_Query|false Cached query object or false if not found
	 */
	public static function get_cached_query($cache_key) {
		$cached_data = wp_cache_get($cache_key, self::get_query_cache_group());
		
		if (false !== $cached_data && is_array($cached_data)) {
			// Reconstruct WP_Query object from cached data
			$query = new WP_Query();
			$query->posts = $cached_data['posts'];
			$query->post_count = $cached_data['post_count'];
			$query->found_posts = $cached_data['found_posts'];
			$query->max_num_pages = $cached_data['max_num_pages'];
			$query->query_vars = $cached_data['query_vars'];
			
			// Set additional properties for proper WP_Query behavior
			$query->current_post = -1;
			$query->in_the_loop = false;
			
			// Validate cached data integrity
			if (!is_array($query->posts)) {
				Handy_Custom_Logger::log("Invalid cached query data for key: {$cache_key}", 'warning');
				return false;
			}
			
			Handy_Custom_Logger::log("Query cache hit for key: {$cache_key}", 'info');
			return $query;
		}
		
		return false;
	}

	/**
	 * Cache query results
	 *
	 * @param string $cache_key Cache key
	 * @param WP_Query $query Query object to cache
	 * @param int $ttl Time to live in seconds (default: 30 minutes)
	 */
	public static function cache_query_results($cache_key, $query, $ttl = 1800) {
		// Only cache successful queries
		if (!($query instanceof WP_Query) || is_wp_error($query)) {
			return;
		}
		
		// Don't cache excessively large result sets to prevent memory issues
		if ($query->post_count > 200) {
			Handy_Custom_Logger::log("Skipping cache for large result set ({$query->post_count} posts)", 'info');
			return;
		}
		
		// Extract essential data for caching
		$cache_data = array(
			'posts' => $query->posts,
			'post_count' => $query->post_count,
			'found_posts' => $query->found_posts,
			'max_num_pages' => $query->max_num_pages,
			'query_vars' => $query->query_vars,
			'cached_at' => time()
		);
		
		wp_cache_set($cache_key, $cache_data, self::get_query_cache_group(), $ttl);
		Handy_Custom_Logger::log("Query results cached with key: {$cache_key} (TTL: {$ttl}s)", 'info');
	}

	/**
	 * Clear query result cache
	 *
	 * @param string $query_type Optional specific query type to clear
	 */
	public static function clear_query_cache($query_type = null) {
		if (wp_cache_supports('flush_group')) {
			wp_cache_flush_group(self::get_query_cache_group());
			Handy_Custom_Logger::log('Query cache group flushed' . ($query_type ? " for type: {$query_type}" : ''), 'info');
		} else {
			// Fallback: We can't efficiently clear specific cached queries without group support
			// This would require tracking all cache keys, which is complex
			Handy_Custom_Logger::log('Query cache clear requested but group flushing not supported' . ($query_type ? " for type: {$query_type}" : ''), 'warning');
		}
	}

	/**
	 * Initialize cache invalidation hooks
	 * Should be called during plugin initialization
	 */
	public static function init_cache_invalidation() {
		// Clear query cache when posts are updated
		add_action('save_post', array(__CLASS__, 'invalidate_query_cache_on_post_update'));
		add_action('delete_post', array(__CLASS__, 'invalidate_query_cache_on_post_update'));
		add_action('publish_post', array(__CLASS__, 'invalidate_query_cache_on_post_update'));
		add_action('trash_post', array(__CLASS__, 'invalidate_query_cache_on_post_update'));
		
		// Clear both term and query caches when terms are updated
		add_action('created_term', array(__CLASS__, 'invalidate_cache_on_term_update'), 10, 3);
		add_action('edited_term', array(__CLASS__, 'invalidate_cache_on_term_update'), 10, 3);
		add_action('deleted_term', array(__CLASS__, 'invalidate_cache_on_term_update'), 10, 3);
		
		Handy_Custom_Logger::log('Cache invalidation hooks initialized', 'info');
	}

	/**
	 * Invalidate query cache when posts are updated
	 *
	 * @param int $post_id Post ID
	 */
	public static function invalidate_query_cache_on_post_update($post_id) {
		$post_type = get_post_type($post_id);
		
		// Only clear cache for relevant post types
		if (in_array($post_type, array('product', 'recipe'))) {
			self::clear_query_cache($post_type);
			Handy_Custom_Logger::log("Query cache cleared due to {$post_type} post update (ID: {$post_id})", 'info');
		}
	}

	/**
	 * Invalidate caches when terms are updated
	 *
	 * @param int $term_id Term ID
	 * @param int $taxonomy_id Taxonomy ID  
	 * @param string $taxonomy Taxonomy slug
	 */
	public static function invalidate_cache_on_term_update($term_id, $taxonomy_id, $taxonomy) {
		// Clear term cache for the specific taxonomy
		self::clear_term_cache($taxonomy);
		
		// Clear query cache if it's a relevant taxonomy
		$relevant_taxonomies = array(
			'product-category', 'grade', 'market-segment', 'product-cooking-method',
			'product-menu-occasion', 'product-type', 'size', 'product-species',
			'brand', 'certification', 'recipe-category', 'recipe-cooking-method',
			'recipe-menu-occasion'
		);
		
		if (in_array($taxonomy, $relevant_taxonomies)) {
			// Determine post type from taxonomy to clear appropriate cache
			$post_type = (strpos($taxonomy, 'product-') === 0 || in_array($taxonomy, array('grade', 'market-segment', 'size', 'brand', 'certification'))) ? 'product' : 'recipe';
			self::clear_query_cache($post_type);
			
			Handy_Custom_Logger::log("Caches cleared due to {$taxonomy} term update (ID: {$term_id})", 'info');
		}
	}

	/**
	 * Get cache statistics for debugging
	 *
	 * @return array Cache statistics
	 */
	public static function get_cache_stats() {
		return array(
			'term_cache_count' => count(self::$term_cache),
			'term_exists_cache_count' => count(self::$term_exists_cache),
			'memory_usage' => memory_get_usage(true)
		);
	}
}
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

		foreach ($allowed_keys as $key) {
			$sanitized[$key] = isset($filters[$key]) ? sanitize_text_field($filters[$key]) : '';
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
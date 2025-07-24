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
 * Description:       Custom functionality for product and recipe archives with shortcode support.
 * Version:           2.0.6
 * Requires at least: 6.5
 * Requires PHP:      8.0
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

// Plugin constants
define('HANDY_CUSTOM_VERSION', '2.0.6');
define('HANDY_CUSTOM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('HANDY_CUSTOM_PLUGIN_URL', plugin_dir_url(__FILE__));

// LOGGING CONTROL - Set to true to enable logging
define('HANDY_CUSTOM_DEBUG', false);

// Load main plugin class
require_once plugin_dir_path(__FILE__) . 'includes/class-handy-custom.php';

// Initialize plugin on plugins_loaded hook to prevent early theme function calls
add_action('plugins_loaded', function() {
	Handy_Custom::get_instance();
});
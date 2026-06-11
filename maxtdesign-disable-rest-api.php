<?php
/**
 * Plugin Name:       MaxtDesign Disable REST API
 * Plugin URI:        https://maxtdesign.com/plugins/disable-rest-api
 * Description:       Full control over your WordPress REST API. Block, restrict, or whitelist endpoints per user role. Lightweight, fast, zero frontend footprint.
 * Version:           1.0.3
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            MaxtDesign
 * Author URI:        https://maxtdesign.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       maxtdesign-disable-rest-api
 * Domain Path:       /languages
 *
 * @package MaxtDesign\DisableRestApi
 * @since   1.0.0
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'MDRA_VERSION', '1.0.3' );
define( 'MDRA_PLUGIN_FILE', __FILE__ );
define( 'MDRA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MDRA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MDRA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4 style autoloader for plugin classes.
 *
 * Maps class names in the MaxtDesign\DisableRestApi namespace
 * to files in the includes/ directory.
 *
 * @since 1.0.0
 *
 * @param string $class_name The fully-qualified class name.
 */
spl_autoload_register( static function ( string $class_name ): void {
	$prefix   = 'MaxtDesign\\DisableRestApi\\';
	$base_dir = MDRA_PLUGIN_DIR . 'includes/';

	$len = strlen( $prefix );
	if ( strncmp( $prefix, $class_name, $len ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class_name, $len );
	$file           = $base_dir . 'class-' . strtolower( str_replace( [ '\\', '_' ], [ '/', '-' ], $relative_class ) ) . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

/**
 * Returns the main plugin instance.
 *
 * @since 1.0.0
 *
 * @return \MaxtDesign\DisableRestApi\Plugin
 */
function mdra_plugin(): MaxtDesign\DisableRestApi\Plugin {
	return MaxtDesign\DisableRestApi\Plugin::get_instance();
}

// Boot the plugin.
mdra_plugin();

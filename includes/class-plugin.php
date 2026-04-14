<?php
/**
 * Main plugin class.
 *
 * Bootstraps all plugin components and manages the singleton instance.
 *
 * @package MaxtDesign\DisableRestApi
 * @since   1.0.0
 */

declare(strict_types=1);

namespace MaxtDesign\DisableRestApi;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin bootstrap class.
 *
 * @since 1.0.0
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * REST controller instance.
	 *
	 * @since 1.0.0
	 * @var Rest_Controller
	 */
	private Rest_Controller $rest_controller;

	/**
	 * Settings page instance.
	 *
	 * @since 1.0.0
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Role manager instance.
	 *
	 * @since 1.0.0
	 * @var Role_Manager
	 */
	private Role_Manager $role_manager;

	/**
	 * Returns the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return self
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor. Registers hooks and initializes components.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->role_manager    = new Role_Manager();
		$this->rest_controller = new Rest_Controller( $this->role_manager );
		$this->settings        = new Settings( $this->role_manager );

		$this->register_hooks();
	}

	/**
	 * Registers activation and deactivation hooks.
	 *
	 * @since 1.0.0
	 */
	private function register_hooks(): void {
		register_activation_hook( MDRA_PLUGIN_FILE, [ $this, 'activate' ] );
		register_deactivation_hook( MDRA_PLUGIN_FILE, [ $this, 'deactivate' ] );

		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_filter( 'plugin_action_links_' . MDRA_PLUGIN_BASENAME, [ $this, 'add_settings_link' ] );
	}

	/**
	 * Runs on plugin activation.
	 *
	 * Sets smart defaults based on the current site configuration.
	 *
	 * @since 1.0.0
	 */
	public function activate(): void {
		if ( false !== get_option( 'mdra_settings' ) ) {
			return;
		}

		$defaults = mdra_get_default_settings();

		// Smart defaults: whitelist known plugin endpoints.
		$whitelisted = $defaults['whitelisted_endpoints'];

		// Contact Form 7: requires REST API for form submissions.
		if ( is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ) || defined( 'WPCF7_VERSION' ) ) {
			$whitelisted[] = 'contact-form-7';
		}

		// WooCommerce Store API: required for cart/checkout blocks.
		if ( is_plugin_active( 'woocommerce/woocommerce.php' ) || defined( 'WC_VERSION' ) ) {
			$whitelisted[] = 'wc/store';
			$whitelisted[] = 'wc/store/v1';
		}

		$defaults['whitelisted_endpoints'] = array_unique( $whitelisted );

		update_option( 'mdra_settings', $defaults, true );
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * @since 1.0.0
	 */
	public function deactivate(): void {
		// Intentionally left empty. Settings are preserved on deactivation.
		// Full cleanup happens only via uninstall.php.
	}

	/**
	 * Loads the plugin text domain for translations.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'maxtdesign-disable-rest-api',
			false,
			dirname( MDRA_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Adds a "Settings" link to the plugins list table.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $links Existing plugin action links.
	 * @return array<string, string> Modified plugin action links.
	 */
	public function add_settings_link( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=mdra-settings' ) ),
			esc_html__( 'Settings', 'maxtdesign-disable-rest-api' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Returns the REST controller instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Rest_Controller
	 */
	public function get_rest_controller(): Rest_Controller {
		return $this->rest_controller;
	}

	/**
	 * Returns the settings instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Settings
	 */
	public function get_settings(): Settings {
		return $this->settings;
	}

	/**
	 * Returns the role manager instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Role_Manager
	 */
	public function get_role_manager(): Role_Manager {
		return $this->role_manager;
	}

	/**
	 * Prevent cloning.
	 *
	 * @since 1.0.0
	 */
	private function __clone() {}
}

/**
 * Returns the plugin settings with defaults merged.
 *
 * @since 1.0.0
 *
 * @return array<string, mixed> Plugin settings.
 */
function mdra_get_settings(): array {
	$defaults = mdra_get_default_settings();
	$settings = get_option( 'mdra_settings', [] );

	if ( ! is_array( $settings ) ) {
		$settings = [];
	}

	return wp_parse_args( $settings, $defaults );
}

/**
 * Returns the default plugin settings.
 *
 * @since 1.0.0
 *
 * @return array<string, mixed> Default settings.
 */
function mdra_get_default_settings(): array {
	return [
		'disable_rest_api'       => true,
		'error_message'          => __( 'REST API access restricted.', 'maxtdesign-disable-rest-api' ),
		'allow_logged_in'        => true,
		'whitelisted_endpoints'  => [],
		'role_restrictions'      => [],
	];
}

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
	 * Only instantiated in the admin context; null on front-end / REST requests.
	 *
	 * @since 1.0.0
	 * @var Settings|null
	 */
	private ?Settings $settings = null;

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

		// The settings page and its admin_* hooks are only useful in the admin
		// context. Skipping construction on front-end and REST requests keeps
		// the runtime footprint to the single REST filter the plugin needs.
		if ( is_admin() ) {
			$this->settings = new Settings( $this->role_manager );
		}

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

		add_filter( 'plugin_action_links_' . MDRA_PLUGIN_BASENAME, [ $this, 'add_settings_link' ] );

		// On multisite, seed smart defaults for sites created after a
		// network-wide activation.
		if ( is_multisite() ) {
			add_action( 'wp_initialize_site', [ $this, 'on_new_site' ], 100 );
		}
	}

	/**
	 * Runs on plugin activation.
	 *
	 * Sets smart defaults based on each affected site's configuration. On a
	 * network-wide activation every existing site is seeded; otherwise only the
	 * current site is.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $network_wide Whether the plugin was network-activated.
	 */
	public function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				$this->maybe_set_defaults();
				restore_current_blog();
			}

			return;
		}

		$this->maybe_set_defaults();
	}

	/**
	 * Seeds smart defaults for a newly created site on a network where the
	 * plugin is network-active.
	 *
	 * @since 1.0.3
	 *
	 * @param \WP_Site $new_site The new site object.
	 */
	public function on_new_site( \WP_Site $new_site ): void {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active_for_network( MDRA_PLUGIN_BASENAME ) ) {
			return;
		}

		switch_to_blog( (int) $new_site->blog_id );
		$this->maybe_set_defaults();
		restore_current_blog();
	}

	/**
	 * Writes smart defaults for the current site, unless settings already exist.
	 *
	 * @since 1.0.3
	 */
	private function maybe_set_defaults(): void {
		if ( false !== get_option( 'mdra_settings' ) ) {
			return;
		}

		// is_plugin_active() lives in wp-admin/includes/plugin.php and isn't
		// guaranteed loaded during programmatic activation (WP-CLI, multisite
		// bulk activate). Include it before use.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
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
	 * @return Settings|null Settings instance in admin context, otherwise null.
	 */
	public function get_settings(): ?Settings {
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
		// Empty means "use the translated default", resolved at render time so
		// the stored option never freezes a single locale's string.
		'error_message'          => '',
		'allow_logged_in'        => true,
		'whitelisted_endpoints'  => [],
		'role_restrictions'      => [],
	];
}

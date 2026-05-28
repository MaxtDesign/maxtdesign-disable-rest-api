<?php
/**
 * Settings page.
 *
 * Registers the admin settings page, handles form submissions,
 * and renders the settings UI with endpoint discovery.
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
 * Settings class.
 *
 * @since 1.0.0
 */
final class Settings {

	/**
	 * Settings page hook suffix.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $hook_suffix = '';

	/**
	 * Role manager instance.
	 *
	 * @since 1.0.0
	 * @var Role_Manager
	 */
	private Role_Manager $role_manager;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Role_Manager $role_manager Role manager instance.
	 */
	public function __construct( Role_Manager $role_manager ) {
		$this->role_manager = $role_manager;
		$this->register_hooks();
	}

	/**
	 * Registers admin hooks.
	 *
	 * @since 1.0.0
	 */
	private function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init', [ $this, 'handle_form_submission' ] );
		add_action( 'admin_notices', [ $this, 'render_admin_notices' ] );
	}

	/**
	 * Adds the settings page under the Settings menu.
	 *
	 * @since 1.0.0
	 */
	public function add_settings_page(): void {
		$this->hook_suffix = add_options_page(
			__( 'REST API Control', 'maxtdesign-disable-rest-api' ),
			__( 'REST API Control', 'maxtdesign-disable-rest-api' ),
			'manage_options',
			'mdra-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Enqueues admin assets only on the plugin settings page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_style(
			'mdra-settings',
			MDRA_PLUGIN_URL . 'assets/admin/css/settings' . $suffix . '.css',
			[],
			MDRA_VERSION
		);

		wp_enqueue_script(
			'mdra-settings',
			MDRA_PLUGIN_URL . 'assets/admin/js/settings' . $suffix . '.js',
			[],
			MDRA_VERSION,
			true
		);
	}

	/**
	 * Handles all form submissions for the settings page.
	 *
	 * @since 1.0.0
	 */
	public function handle_form_submission(): void {
		// Nonce is verified inside each dispatched handler (save/import/export/
		// reset) because each form ships its own nonce. We need the action
		// name first to know which nonce to check.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_POST['mdra_action'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$action = sanitize_text_field( wp_unslash( $_POST['mdra_action'] ) );

		switch ( $action ) {
			case 'save_settings':
				$this->save_settings();
				break;

			case 'import_settings':
				$this->import_settings();
				break;

			case 'export_settings':
				$this->export_settings();
				break;

			case 'reset_settings':
				$this->reset_settings();
				break;
		}
	}

	/**
	 * Saves plugin settings from the form POST data.
	 *
	 * @since 1.0.0
	 */
	private function save_settings(): void {
		if ( ! check_admin_referer( 'mdra_save_settings', 'mdra_nonce' ) ) {
			return;
		}

		$settings = [
			'disable_rest_api'      => ! empty( $_POST['mdra_disable_rest_api'] ),
			'error_message'         => isset( $_POST['mdra_error_message'] )
				? sanitize_text_field( wp_unslash( $_POST['mdra_error_message'] ) )
				: __( 'REST API access restricted.', 'maxtdesign-disable-rest-api' ),
			'allow_logged_in'       => ! empty( $_POST['mdra_allow_logged_in'] ),
			// Sanitization happens element-wise inside sanitize_endpoint_list()
			// and sanitize_role_restrictions() — PHPCS can't see through that.
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'whitelisted_endpoints' => $this->sanitize_endpoint_list(
				isset( $_POST['mdra_whitelisted_endpoints'] ) && is_array( $_POST['mdra_whitelisted_endpoints'] )
					? wp_unslash( $_POST['mdra_whitelisted_endpoints'] )
					: []
			),
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			'role_restrictions'     => $this->sanitize_role_restrictions(
				isset( $_POST['mdra_role_restrictions'] ) && is_array( $_POST['mdra_role_restrictions'] )
					? wp_unslash( $_POST['mdra_role_restrictions'] )
					: []
			),
		];

		update_option( 'mdra_settings', $settings, true );

		wp_safe_redirect( add_query_arg( 'mdra_message', 'saved', $this->get_settings_url() ) );
		exit;
	}

	/**
	 * Imports settings from an uploaded JSON file.
	 *
	 * @since 1.0.0
	 */
	private function import_settings(): void {
		if ( ! check_admin_referer( 'mdra_import_settings', 'mdra_import_nonce' ) ) {
			return;
		}

		if ( empty( $_FILES['mdra_import_file']['tmp_name'] ) ) {
			wp_safe_redirect( add_query_arg( 'mdra_message', 'import_error', $this->get_settings_url() ) );
			exit;
		}

		// tmp_name is a server-generated path, not user input. Use it as-is
		// after validating it via is_uploaded_file() (defense-in-depth against
		// $_FILES tampering).
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$tmp_file = $_FILES['mdra_import_file']['tmp_name'];

		if ( ! is_string( $tmp_file ) || ! is_uploaded_file( $tmp_file ) ) {
			wp_safe_redirect( add_query_arg( 'mdra_message', 'import_error', $this->get_settings_url() ) );
			exit;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();

		global $wp_filesystem;

		$file_content = $wp_filesystem->get_contents( $tmp_file );

		if ( false === $file_content ) {
			wp_safe_redirect( add_query_arg( 'mdra_message', 'import_error', $this->get_settings_url() ) );
			exit;
		}

		$imported = json_decode( $file_content, true );

		if ( ! is_array( $imported ) ) {
			wp_safe_redirect( add_query_arg( 'mdra_message', 'import_error', $this->get_settings_url() ) );
			exit;
		}

		$defaults = mdra_get_default_settings();
		$settings = [
			'disable_rest_api'      => isset( $imported['disable_rest_api'] ) ? (bool) $imported['disable_rest_api'] : $defaults['disable_rest_api'],
			'error_message'         => isset( $imported['error_message'] ) ? sanitize_text_field( $imported['error_message'] ) : $defaults['error_message'],
			'allow_logged_in'       => isset( $imported['allow_logged_in'] ) ? (bool) $imported['allow_logged_in'] : $defaults['allow_logged_in'],
			'whitelisted_endpoints' => isset( $imported['whitelisted_endpoints'] ) && is_array( $imported['whitelisted_endpoints'] )
				? $this->sanitize_endpoint_list( $imported['whitelisted_endpoints'] )
				: $defaults['whitelisted_endpoints'],
			'role_restrictions'     => isset( $imported['role_restrictions'] ) && is_array( $imported['role_restrictions'] )
				? $this->sanitize_role_restrictions( $imported['role_restrictions'] )
				: $defaults['role_restrictions'],
		];

		update_option( 'mdra_settings', $settings, true );

		wp_safe_redirect( add_query_arg( 'mdra_message', 'imported', $this->get_settings_url() ) );
		exit;
	}

	/**
	 * Exports settings as a JSON file download.
	 *
	 * @since 1.0.0
	 */
	private function export_settings(): void {
		if ( ! check_admin_referer( 'mdra_export_settings', 'mdra_export_nonce' ) ) {
			return;
		}

		$settings = mdra_get_settings();

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=mdra-settings-' . gmdate( 'Y-m-d' ) . '.json' );

		echo wp_json_encode( $settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Resets settings to defaults.
	 *
	 * @since 1.0.0
	 */
	private function reset_settings(): void {
		if ( ! check_admin_referer( 'mdra_reset_settings', 'mdra_reset_nonce' ) ) {
			return;
		}

		delete_option( 'mdra_settings' );

		wp_safe_redirect( add_query_arg( 'mdra_message', 'reset', $this->get_settings_url() ) );
		exit;
	}

	/**
	 * Renders admin notices on the settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_admin_notices(): void {
		$screen = get_current_screen();

		if ( null === $screen || $screen->id !== $this->hook_suffix ) {
			return;
		}

		// Read-only notice lookup whose value is whitelisted against a fixed
		// set below — no state-changing action depends on this $_GET, so a
		// nonce isn't needed.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['mdra_message'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$message = sanitize_text_field( wp_unslash( $_GET['mdra_message'] ) );
		$notices = [
			'saved'        => [ 'success', __( 'Settings saved successfully.', 'maxtdesign-disable-rest-api' ) ],
			'imported'     => [ 'success', __( 'Settings imported successfully.', 'maxtdesign-disable-rest-api' ) ],
			'import_error' => [ 'error', __( 'Import failed. Please upload a valid JSON file.', 'maxtdesign-disable-rest-api' ) ],
			'reset'        => [ 'success', __( 'Settings reset to defaults.', 'maxtdesign-disable-rest-api' ) ],
		];

		if ( ! isset( $notices[ $message ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $notices[ $message ][0] ),
			esc_html( $notices[ $message ][1] )
		);

		// Show plugin compatibility notices.
		$this->render_compatibility_notices();
	}

	/**
	 * Renders compatibility notices for plugins that require REST API.
	 *
	 * @since 1.0.0
	 */
	private function render_compatibility_notices(): void {
		$settings    = mdra_get_settings();
		$whitelisted = $settings['whitelisted_endpoints'] ?? [];

		if ( empty( $settings['disable_rest_api'] ) ) {
			return;
		}

		$known_plugins = $this->get_known_rest_plugins();

		foreach ( $known_plugins as $plugin ) {
			if ( ! $plugin['active'] ) {
				continue;
			}

			$is_whitelisted = false;
			foreach ( $plugin['namespaces'] as $ns ) {
				if ( in_array( $ns, $whitelisted, true ) ) {
					$is_whitelisted = true;
					break;
				}
			}

			if ( ! $is_whitelisted ) {
				printf(
					'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
					sprintf(
						/* translators: %s: plugin name */
						esc_html__( '%s requires REST API access to function properly. Consider whitelisting its endpoints.', 'maxtdesign-disable-rest-api' ),
						'<strong>' . esc_html( $plugin['name'] ) . '</strong>'
					)
				);
			}
		}
	}

	/**
	 * Returns a list of known plugins that require the REST API.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{name: string, active: bool, namespaces: array<string>}> Plugin info.
	 */
	private function get_known_rest_plugins(): array {
		return [
			[
				'name'       => 'Contact Form 7',
				'active'     => defined( 'WPCF7_VERSION' ),
				'namespaces' => [ 'contact-form-7' ],
			],
			[
				'name'       => 'WooCommerce',
				'active'     => defined( 'WC_VERSION' ),
				'namespaces' => [ 'wc/store', 'wc/store/v1' ],
			],
			[
				'name'       => 'Jetpack',
				'active'     => defined( 'JETPACK__VERSION' ),
				'namespaces' => [ 'jetpack' ],
			],
			[
				'name'       => 'WPForms',
				'active'     => defined( 'WPFORMS_VERSION' ),
				'namespaces' => [ 'wpforms' ],
			],
		];
	}

	/**
	 * Discovers all registered REST API endpoints grouped by namespace.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, array<string>> Namespace => list of routes.
	 */
	public function discover_endpoints(): array {
		$server = rest_get_server();
		$routes = $server->get_routes();
		$grouped = [];

		foreach ( $routes as $route => $handlers ) {
			$route = ltrim( $route, '/' );

			// Skip the root route.
			if ( '' === $route ) {
				continue;
			}

			// Extract namespace from route.
			$parts     = explode( '/', $route );
			$namespace = $parts[0];

			// Build a more specific namespace for versioned APIs (e.g. wp/v2, wc/store/v1).
			if ( count( $parts ) > 1 && preg_match( '/^v\d+/', $parts[1] ) ) {
				$namespace = $parts[0] . '/' . $parts[1];
			}

			if ( ! isset( $grouped[ $namespace ] ) ) {
				$grouped[ $namespace ] = [];
			}

			if ( ! in_array( $route, $grouped[ $namespace ], true ) ) {
				$grouped[ $namespace ][] = $route;
			}
		}

		ksort( $grouped );

		return $grouped;
	}

	/**
	 * Renders the settings page HTML.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings  = mdra_get_settings();
		$endpoints = $this->discover_endpoints();
		$roles     = $this->role_manager->get_all_roles();

		?>
		<div class="wrap mdra-settings-wrap">
			<h1><?php esc_html_e( 'REST API Control', 'maxtdesign-disable-rest-api' ); ?></h1>

			<form method="post" action="">
				<?php wp_nonce_field( 'mdra_save_settings', 'mdra_nonce' ); ?>
				<input type="hidden" name="mdra_action" value="save_settings">

				<?php $this->render_global_settings_section( $settings ); ?>
				<?php $this->render_endpoint_whitelist_section( $settings, $endpoints ); ?>
				<?php $this->render_role_controls_section( $settings, $endpoints, $roles ); ?>

				<?php submit_button( __( 'Save Settings', 'maxtdesign-disable-rest-api' ) ); ?>
			</form>

			<hr>

			<?php $this->render_import_export_section(); ?>
		</div>
		<?php
	}

	/**
	 * Renders the Global Settings section.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $settings Current settings.
	 */
	private function render_global_settings_section( array $settings ): void {
		?>
		<div class="mdra-section">
			<h2><?php esc_html_e( 'Global Settings', 'maxtdesign-disable-rest-api' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row">
						<label for="mdra_disable_rest_api">
							<?php esc_html_e( 'Disable REST API for unauthenticated users', 'maxtdesign-disable-rest-api' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox"
								id="mdra_disable_rest_api"
								name="mdra_disable_rest_api"
								value="1"
								<?php checked( ! empty( $settings['disable_rest_api'] ) ); ?>
							>
							<?php esc_html_e( 'Block REST API requests from unauthenticated visitors.', 'maxtdesign-disable-rest-api' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mdra_error_message">
							<?php esc_html_e( 'Custom error message', 'maxtdesign-disable-rest-api' ); ?>
						</label>
					</th>
					<td>
						<input type="text"
							id="mdra_error_message"
							name="mdra_error_message"
							value="<?php echo esc_attr( $settings['error_message'] ?? '' ); ?>"
							class="regular-text"
						>
						<p class="description">
							<?php esc_html_e( 'The message returned to blocked REST API requests.', 'maxtdesign-disable-rest-api' ); ?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row">
						<label for="mdra_allow_logged_in">
							<?php esc_html_e( 'Allow REST API for all logged-in users', 'maxtdesign-disable-rest-api' ); ?>
						</label>
					</th>
					<td>
						<label>
							<input type="checkbox"
								id="mdra_allow_logged_in"
								name="mdra_allow_logged_in"
								value="1"
								<?php checked( ! empty( $settings['allow_logged_in'] ) ); ?>
							>
							<?php esc_html_e( 'Allow full REST API access for any logged-in user (unless restricted by role below).', 'maxtdesign-disable-rest-api' ); ?>
						</label>
					</td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Renders the Endpoint Whitelist section for unauthenticated users.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>              $settings  Current settings.
	 * @param array<string, array<string>> $endpoints Discovered endpoints.
	 */
	private function render_endpoint_whitelist_section( array $settings, array $endpoints ): void {
		$whitelisted = $settings['whitelisted_endpoints'] ?? [];
		?>
		<div class="mdra-section">
			<h2><?php esc_html_e( 'Endpoint Whitelist (Unauthenticated Users)', 'maxtdesign-disable-rest-api' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Select endpoints that should remain accessible to unauthenticated visitors, even when the REST API is disabled.', 'maxtdesign-disable-rest-api' ); ?>
			</p>

			<?php if ( empty( $endpoints ) ) : ?>
				<p><em><?php esc_html_e( 'No REST API endpoints discovered. Endpoints will appear after the REST API has been initialized.', 'maxtdesign-disable-rest-api' ); ?></em></p>
			<?php else : ?>
				<div class="mdra-endpoint-tree" id="mdra-endpoint-tree-global">
					<?php $this->render_endpoint_tree( $endpoints, $whitelisted, 'mdra_whitelisted_endpoints' ); ?>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the Per-Role Controls section.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>              $settings  Current settings.
	 * @param array<string, array<string>> $endpoints Discovered endpoints.
	 * @param array<string, string>             $roles     Role slug => name map.
	 */
	private function render_role_controls_section( array $settings, array $endpoints, array $roles ): void {
		$role_restrictions = $settings['role_restrictions'] ?? [];
		?>
		<div class="mdra-section">
			<h2>
				<button type="button" class="mdra-toggle-section" aria-expanded="false" data-target="mdra-role-controls">
					<?php esc_html_e( 'Per-Role Controls (Advanced)', 'maxtdesign-disable-rest-api' ); ?>
					<span class="mdra-toggle-icon" aria-hidden="true"></span>
				</button>
			</h2>
			<p class="description">
				<?php esc_html_e( 'Restrict REST API access for specific user roles. By default, all logged-in roles have full access.', 'maxtdesign-disable-rest-api' ); ?>
			</p>

			<div id="mdra-role-controls" class="mdra-collapsible" hidden>
				<?php foreach ( $roles as $role_slug => $role_name ) : ?>
					<?php
					$role_config    = $role_restrictions[ $role_slug ] ?? [];
					$is_restricted  = ! empty( $role_config['restricted'] );
					$role_whitelist = $role_config['whitelisted_endpoints'] ?? [];
					?>
					<div class="mdra-role-block">
						<h3>
							<label>
								<input type="checkbox"
									class="mdra-role-restrict-toggle"
									name="mdra_role_restrictions[<?php echo esc_attr( $role_slug ); ?>][restricted]"
									value="1"
									data-role="<?php echo esc_attr( $role_slug ); ?>"
									<?php checked( $is_restricted ); ?>
								>
								<?php
								printf(
									/* translators: %s: user role name */
									esc_html__( 'Restrict %s', 'maxtdesign-disable-rest-api' ),
									esc_html( $role_name )
								);
								?>
							</label>
						</h3>

						<div class="mdra-role-endpoints" id="mdra-role-endpoints-<?php echo esc_attr( $role_slug ); ?>" <?php echo $is_restricted ? '' : 'hidden'; ?>>
							<?php if ( ! empty( $endpoints ) ) : ?>
								<p class="description">
									<?php
									printf(
										/* translators: %s: user role name */
										esc_html__( 'Select which endpoints the %s role can access:', 'maxtdesign-disable-rest-api' ),
										esc_html( $role_name )
									);
									?>
								</p>
								<?php
								$this->render_endpoint_tree(
									$endpoints,
									$role_whitelist,
									'mdra_role_restrictions[' . esc_attr( $role_slug ) . '][whitelisted_endpoints]'
								);
								?>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders a collapsible endpoint tree with checkboxes.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, array<string>> $endpoints  Namespace => routes map.
	 * @param array<string>                $whitelisted Currently whitelisted items.
	 * @param string                       $field_name  Form field name for checkboxes.
	 */
	private function render_endpoint_tree( array $endpoints, array $whitelisted, string $field_name ): void {
		foreach ( $endpoints as $namespace => $routes ) :
			$ns_checked    = in_array( $namespace, $whitelisted, true );
			$namespace_id  = sanitize_html_class( $field_name . '-' . $namespace );
			?>
			<div class="mdra-namespace-group">
				<div class="mdra-namespace-header">
					<button type="button" class="mdra-toggle-namespace" aria-expanded="false" data-target="<?php echo esc_attr( $namespace_id ); ?>">
						<span class="mdra-toggle-icon" aria-hidden="true"></span>
					</button>
					<label class="mdra-namespace-label">
						<input type="checkbox"
							class="mdra-namespace-checkbox"
							name="<?php echo esc_attr( $field_name ); ?>[]"
							value="<?php echo esc_attr( $namespace ); ?>"
							data-namespace="<?php echo esc_attr( $namespace_id ); ?>"
							<?php checked( $ns_checked ); ?>
						>
						<code><?php echo esc_html( $namespace ); ?></code>
						<span class="mdra-route-count">(<?php echo count( $routes ); ?>)</span>
					</label>
					<span class="mdra-namespace-actions">
						<button type="button" class="button-link mdra-select-all" data-namespace="<?php echo esc_attr( $namespace_id ); ?>">
							<?php esc_html_e( 'Select All', 'maxtdesign-disable-rest-api' ); ?>
						</button>
						|
						<button type="button" class="button-link mdra-deselect-all" data-namespace="<?php echo esc_attr( $namespace_id ); ?>">
							<?php esc_html_e( 'Deselect All', 'maxtdesign-disable-rest-api' ); ?>
						</button>
					</span>
				</div>
				<div class="mdra-namespace-routes" id="<?php echo esc_attr( $namespace_id ); ?>" hidden>
					<?php foreach ( $routes as $route ) : ?>
						<?php $route_checked = in_array( $route, $whitelisted, true ) || $ns_checked; ?>
						<label class="mdra-route-label">
							<input type="checkbox"
								class="mdra-route-checkbox"
								name="<?php echo esc_attr( $field_name ); ?>[]"
								value="<?php echo esc_attr( $route ); ?>"
								data-parent-namespace="<?php echo esc_attr( $namespace_id ); ?>"
								<?php checked( $route_checked ); ?>
								<?php disabled( $ns_checked ); ?>
							>
							<code>/<?php echo esc_html( $route ); ?></code>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach;
	}

	/**
	 * Renders the Import/Export section.
	 *
	 * @since 1.0.0
	 */
	private function render_import_export_section(): void {
		?>
		<div class="mdra-section mdra-import-export">
			<h2><?php esc_html_e( 'Import / Export', 'maxtdesign-disable-rest-api' ); ?></h2>

			<div class="mdra-import-export-grid">
				<div class="mdra-export-box">
					<h3><?php esc_html_e( 'Export Settings', 'maxtdesign-disable-rest-api' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Download your current settings as a JSON file.', 'maxtdesign-disable-rest-api' ); ?>
					</p>
					<form method="post" action="">
						<?php wp_nonce_field( 'mdra_export_settings', 'mdra_export_nonce' ); ?>
						<input type="hidden" name="mdra_action" value="export_settings">
						<?php submit_button( __( 'Export Settings', 'maxtdesign-disable-rest-api' ), 'secondary', 'mdra_export', false ); ?>
					</form>
				</div>

				<div class="mdra-import-box">
					<h3><?php esc_html_e( 'Import Settings', 'maxtdesign-disable-rest-api' ); ?></h3>
					<p class="description">
						<?php esc_html_e( 'Upload a previously exported JSON settings file.', 'maxtdesign-disable-rest-api' ); ?>
					</p>
					<form method="post" action="" enctype="multipart/form-data">
						<?php wp_nonce_field( 'mdra_import_settings', 'mdra_import_nonce' ); ?>
						<input type="hidden" name="mdra_action" value="import_settings">
						<input type="file" name="mdra_import_file" accept=".json" required>
						<?php submit_button( __( 'Import Settings', 'maxtdesign-disable-rest-api' ), 'secondary', 'mdra_import', false ); ?>
					</form>
				</div>
			</div>

			<hr>

			<div class="mdra-reset-box">
				<h3><?php esc_html_e( 'Reset to Defaults', 'maxtdesign-disable-rest-api' ); ?></h3>
				<p class="description">
					<?php esc_html_e( 'This will reset all settings to their defaults. This action cannot be undone.', 'maxtdesign-disable-rest-api' ); ?>
				</p>
				<form method="post" action="" id="mdra-reset-form">
					<?php wp_nonce_field( 'mdra_reset_settings', 'mdra_reset_nonce' ); ?>
					<input type="hidden" name="mdra_action" value="reset_settings">
					<?php submit_button( __( 'Reset to Defaults', 'maxtdesign-disable-rest-api' ), 'delete', 'mdra_reset', false ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Sanitizes an array of endpoint strings.
	 *
	 * @since 1.0.0
	 *
	 * @param array<mixed> $endpoints Raw endpoint list.
	 * @return array<string> Sanitized endpoint list.
	 */
	private function sanitize_endpoint_list( array $endpoints ): array {
		$sanitized = [];

		foreach ( $endpoints as $endpoint ) {
			if ( ! is_string( $endpoint ) ) {
				continue;
			}

			$clean = sanitize_text_field( $endpoint );

			if ( '' !== $clean ) {
				$sanitized[] = $clean;
			}
		}

		return array_values( array_unique( $sanitized ) );
	}

	/**
	 * Sanitizes role restriction settings.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $restrictions Raw role restrictions.
	 * @return array<string, array{restricted: bool, whitelisted_endpoints: array<string>}> Sanitized restrictions.
	 */
	private function sanitize_role_restrictions( array $restrictions ): array {
		$valid_roles = array_keys( $this->role_manager->get_all_roles() );
		$sanitized   = [];

		foreach ( $restrictions as $role => $config ) {
			$role = sanitize_text_field( (string) $role );

			if ( ! in_array( $role, $valid_roles, true ) ) {
				continue;
			}

			if ( ! is_array( $config ) ) {
				continue;
			}

			$sanitized[ $role ] = [
				'restricted'            => ! empty( $config['restricted'] ),
				'whitelisted_endpoints' => isset( $config['whitelisted_endpoints'] ) && is_array( $config['whitelisted_endpoints'] )
					? $this->sanitize_endpoint_list( $config['whitelisted_endpoints'] )
					: [],
			];
		}

		return $sanitized;
	}

	/**
	 * Returns the settings page URL.
	 *
	 * @since 1.0.0
	 *
	 * @return string Settings page URL.
	 */
	private function get_settings_url(): string {
		return admin_url( 'options-general.php?page=mdra-settings' );
	}
}

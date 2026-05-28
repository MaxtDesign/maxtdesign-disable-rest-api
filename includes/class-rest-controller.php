<?php
/**
 * REST API controller.
 *
 * Handles the core logic for blocking, restricting, and whitelisting
 * REST API endpoints based on plugin settings.
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
 * REST controller class.
 *
 * Filters REST API requests using the `rest_authentication_errors` hook
 * to enforce access rules configured in the plugin settings.
 *
 * @since 1.0.0
 */
final class Rest_Controller {

	/**
	 * Role manager instance.
	 *
	 * @since 1.0.0
	 * @var Role_Manager
	 */
	private Role_Manager $role_manager;

	/**
	 * Cached plugin settings.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>|null
	 */
	private ?array $settings = null;

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
	 * Registers the REST API filter hooks.
	 *
	 * @since 1.0.0
	 */
	private function register_hooks(): void {
		// Priority 99 to run after other authentication checks.
		add_filter( 'rest_authentication_errors', [ $this, 'restrict_rest_api' ], 99 );
	}

	/**
	 * Restricts REST API access based on plugin settings.
	 *
	 * This is the main filter callback that determines whether a REST API
	 * request should be allowed or blocked.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Error|true|null $result Authentication result from previous filters.
	 * @return \WP_Error|true|null Modified authentication result.
	 */
	public function restrict_rest_api( \WP_Error|true|null $result ): \WP_Error|true|null {
		// If a previous authentication handler already blocked the request, respect it.
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$settings = $this->get_settings();

		// If the REST API restriction is not enabled, allow all requests.
		if ( empty( $settings['disable_rest_api'] ) ) {
			return $result;
		}

		// An empty route here means the REST root index (/wp-json/) or an
		// equivalent root-level request. The rest_authentication_errors
		// filter only fires from inside the REST server, so we know we're
		// handling a REST request even when the route lookup comes back
		// empty — don't fail open. Empty routes flow through the whitelist
		// matcher like any other route; the root index is blocked by default
		// because no whitelist entry can match it.
		$current_route = $this->get_current_route();

		$is_logged_in = is_user_logged_in();

		// Handle logged-in users.
		if ( $is_logged_in ) {
			return $this->handle_authenticated_request( $result, $current_route, $settings );
		}

		// Handle unauthenticated users.
		return $this->handle_unauthenticated_request( $current_route, $settings );
	}

	/**
	 * Handles access control for authenticated requests.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Error|true|null $result   Current authentication result.
	 * @param string              $route    The current REST route.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return \WP_Error|true|null Modified authentication result.
	 */
	private function handle_authenticated_request(
		\WP_Error|true|null $result,
		string $route,
		array $settings
	): \WP_Error|true|null {
		// If all logged-in users are allowed, pass through.
		if ( ! empty( $settings['allow_logged_in'] ) ) {
			// Check per-role restrictions.
			$user  = wp_get_current_user();
			$roles = $user->roles;

			if ( empty( $roles ) ) {
				return $result;
			}

			// Check if any of the user's roles have restrictions.
			$role_restrictions = $settings['role_restrictions'] ?? [];

			foreach ( $roles as $role ) {
				if ( ! isset( $role_restrictions[ $role ] ) ) {
					continue;
				}

				$role_config = $role_restrictions[ $role ];

				if ( empty( $role_config['restricted'] ) ) {
					continue;
				}

				// This role is restricted. Check its whitelist.
				$role_whitelist = $role_config['whitelisted_endpoints'] ?? [];

				if ( $this->is_route_whitelisted( $route, $role_whitelist ) ) {
					return $result;
				}

				return $this->get_error_response( $settings );
			}

			return $result;
		}

		// If allow_logged_in is off, treat like unauthenticated but check role restrictions.
		$user  = wp_get_current_user();
		$roles = $user->roles;

		$role_restrictions = $settings['role_restrictions'] ?? [];

		foreach ( $roles as $role ) {
			if ( isset( $role_restrictions[ $role ] ) ) {
				$role_config = $role_restrictions[ $role ];

				if ( empty( $role_config['restricted'] ) ) {
					// This role is explicitly unrestricted.
					return $result;
				}

				$role_whitelist = $role_config['whitelisted_endpoints'] ?? [];

				if ( $this->is_route_whitelisted( $route, $role_whitelist ) ) {
					return $result;
				}

				return $this->get_error_response( $settings );
			}
		}

		// No specific role config and allow_logged_in is off — block.
		return $this->handle_unauthenticated_request( $route, $settings );
	}

	/**
	 * Handles access control for unauthenticated requests.
	 *
	 * @since 1.0.0
	 *
	 * @param string              $route    The current REST route.
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return \WP_Error|true|null Error if blocked, null if allowed.
	 */
	private function handle_unauthenticated_request(
		string $route,
		array $settings
	): \WP_Error|null {
		$whitelisted = $settings['whitelisted_endpoints'] ?? [];

		if ( $this->is_route_whitelisted( $route, $whitelisted ) ) {
			return null;
		}

		return $this->get_error_response( $settings );
	}

	/**
	 * Checks if a route matches any whitelisted namespace or endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param string        $route       The REST route to check.
	 * @param array<string> $whitelisted List of whitelisted namespaces/routes.
	 * @return bool True if the route is whitelisted.
	 */
	private function is_route_whitelisted( string $route, array $whitelisted ): bool {
		$route = ltrim( $route, '/' );

		foreach ( $whitelisted as $allowed ) {
			$allowed = ltrim( $allowed, '/' );

			if ( '' === $allowed ) {
				continue;
			}

			// Exact match.
			if ( $route === $allowed ) {
				return true;
			}

			// Namespace/prefix match: if route starts with the allowed pattern.
			if ( str_starts_with( $route, $allowed . '/' ) ) {
				return true;
			}
		}

		/**
		 * Filters whether a specific route is whitelisted.
		 *
		 * @since 1.0.0
		 *
		 * @param bool   $is_whitelisted Whether the route is currently whitelisted.
		 * @param string $route          The REST route being checked.
		 * @param array  $whitelisted    The whitelist from settings.
		 */
		return (bool) apply_filters( 'mdra_is_route_whitelisted', false, $route, $whitelisted );
	}

	/**
	 * Returns the current REST API route from the request.
	 *
	 * @since 1.0.0
	 *
	 * @return string The current REST route, or empty string if unavailable.
	 */
	private function get_current_route(): string {
		$rest_route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';

		if ( '' !== $rest_route ) {
			return ltrim( (string) $rest_route, '/' );
		}

		// Fallback: parse from REQUEST_URI.
		$rest_prefix = rest_get_url_prefix();

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return '';
		}

		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$request_path = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( ! is_string( $request_path ) ) {
			return '';
		}

		$rest_position = strpos( $request_path, '/' . $rest_prefix . '/' );

		if ( false === $rest_position ) {
			return '';
		}

		return ltrim( substr( $request_path, $rest_position + strlen( '/' . $rest_prefix ) ), '/' );
	}

	/**
	 * Returns a WP_Error response for blocked requests.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 * @return \WP_Error The error response.
	 */
	private function get_error_response( array $settings ): \WP_Error {
		$message = $settings['error_message'] ?? __( 'REST API access restricted.', 'maxtdesign-disable-rest-api' );

		/**
		 * Filters the error response returned when a REST API request is blocked.
		 *
		 * @since 1.0.0
		 *
		 * @param \WP_Error $error    The error object.
		 * @param array     $settings The current plugin settings.
		 */
		return apply_filters(
			'mdra_rest_error_response',
			new \WP_Error(
				'mdra_rest_disabled',
				$message,
				[ 'status' => 401 ]
			),
			$settings
		);
	}

	/**
	 * Returns the cached plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> Plugin settings.
	 */
	private function get_settings(): array {
		if ( null === $this->settings ) {
			$this->settings = mdra_get_settings();
		}

		return $this->settings;
	}
}

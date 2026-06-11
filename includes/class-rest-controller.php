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
		$roles             = wp_get_current_user()->roles;
		$role_restrictions = $settings['role_restrictions'] ?? [];

		if ( ! empty( $settings['allow_logged_in'] ) ) {
			// All logged-in users are allowed by default. A user with no roles,
			// or with at least one role that is NOT restricted, gets full
			// access — the most permissive role always wins.
			if ( empty( $roles ) ) {
				return $result;
			}

			$restricted_roles = [];

			foreach ( $roles as $role ) {
				if ( empty( $role_restrictions[ $role ]['restricted'] ) ) {
					// This role is unrestricted; the user keeps full access.
					return $result;
				}

				$restricted_roles[] = $role;
			}

			// Every role the user holds is restricted. Combine the whitelists
			// of all of them and allow the route if any of them permits it.
			$union = $this->collect_role_whitelists( $restricted_roles, $role_restrictions );

			if ( $this->is_route_whitelisted( $route, $union ) ) {
				return $result;
			}

			return $this->get_error_response( $settings );
		}

		// allow_logged_in is off: logged-in users are not auto-granted access.
		// Still honour the most-permissive rule — an explicitly unrestricted
		// role grants full access.
		foreach ( $roles as $role ) {
			if ( isset( $role_restrictions[ $role ] ) && empty( $role_restrictions[ $role ]['restricted'] ) ) {
				return $result;
			}
		}

		// Otherwise the user may reach the global whitelist plus the combined
		// whitelists of any restricted roles they hold.
		$restricted_roles = [];

		foreach ( $roles as $role ) {
			if ( ! empty( $role_restrictions[ $role ]['restricted'] ) ) {
				$restricted_roles[] = $role;
			}
		}

		$union = array_merge(
			$settings['whitelisted_endpoints'] ?? [],
			$this->collect_role_whitelists( $restricted_roles, $role_restrictions )
		);

		if ( $this->is_route_whitelisted( $route, $union ) ) {
			return $result;
		}

		return $this->get_error_response( $settings );
	}

	/**
	 * Collects and de-duplicates the whitelisted endpoints across a set of roles.
	 *
	 * @since 1.0.3
	 *
	 * @param array<string>        $roles             Role slugs to collect from.
	 * @param array<string, mixed> $role_restrictions The role_restrictions settings map.
	 * @return array<string> Combined, de-duplicated whitelist.
	 */
	private function collect_role_whitelists( array $roles, array $role_restrictions ): array {
		$union = [];

		foreach ( $roles as $role ) {
			$whitelist = $role_restrictions[ $role ]['whitelisted_endpoints'] ?? [];

			if ( is_array( $whitelist ) ) {
				$union = array_merge( $union, $whitelist );
			}
		}

		return array_values( array_unique( $union ) );
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
			$allowed = ltrim( (string) $allowed, '/' );

			if ( '' === $allowed ) {
				continue;
			}

			// Exact match (covers namespaces and static routes).
			if ( $route === $allowed ) {
				return true;
			}

			// Namespace / static-prefix match: route lives under the allowed path.
			if ( str_starts_with( $route, $allowed . '/' ) ) {
				return true;
			}

			// Pattern-route match. Discovered route keys carry their regex
			// syntax verbatim (e.g. "wp/v2/posts/(?P<id>[\d]+)"). When a
			// whitelist entry contains regex metacharacters, match it against
			// the concrete requested route the same way WP_REST_Server does,
			// instead of comparing the literal pattern string.
			if ( $this->is_pattern_route( $allowed ) && $this->matches_route_pattern( $allowed, $route ) ) {
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
	 * Determines whether a whitelist entry is a regex route pattern rather
	 * than a plain namespace or static route.
	 *
	 * @since 1.0.3
	 *
	 * @param string $candidate The whitelist entry to inspect.
	 * @return bool True if the entry contains regex metacharacters.
	 */
	private function is_pattern_route( string $candidate ): bool {
		return 1 === preg_match( '/[()\[\]?+*|]/', $candidate );
	}

	/**
	 * Matches a registered route pattern against a concrete requested route.
	 *
	 * Mirrors WP_REST_Server::dispatch(), which anchors the registered route
	 * regex with ^...$ and matches case-insensitively.
	 *
	 * @since 1.0.3
	 *
	 * @param string $pattern The registered route pattern (regex body).
	 * @param string $route   The concrete requested route.
	 * @return bool True if the route matches the pattern.
	 */
	private function matches_route_pattern( string $pattern, string $route ): bool {
		// Use '@' delimiters so unescaped slashes in routes don't break the
		// expression; escape any literal '@' in the pattern just in case.
		$regex = '@^' . str_replace( '@', '\@', $pattern ) . '$@i';

		// A malformed pattern must never fatal the request — suppress the
		// warning and treat an error as "no match".
		return 1 === @preg_match( $regex, $route );
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
		// An empty stored message resolves to the translated default at render
		// time, so the response always speaks the current site's locale rather
		// than whichever locale was active when the setting was saved.
		$message = ! empty( $settings['error_message'] )
			? $settings['error_message']
			: __( 'REST API access restricted.', 'maxtdesign-disable-rest-api' );

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

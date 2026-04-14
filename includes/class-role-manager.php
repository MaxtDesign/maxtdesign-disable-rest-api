<?php
/**
 * Role manager.
 *
 * Manages per-role REST API access configuration and provides
 * helper methods for role-based endpoint whitelisting.
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
 * Role manager class.
 *
 * @since 1.0.0
 */
final class Role_Manager {

	/**
	 * Returns all WordPress user roles with labels.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, string> Role slug => translated role name.
	 */
	public function get_all_roles(): array {
		$wp_roles = wp_roles();
		$roles    = [];

		foreach ( $wp_roles->role_names as $slug => $name ) {
			$roles[ $slug ] = translate_user_role( $name );
		}

		return $roles;
	}

	/**
	 * Returns the role restriction settings for a specific role.
	 *
	 * @since 1.0.0
	 *
	 * @param string $role The role slug.
	 * @return array{restricted: bool, whitelisted_endpoints: array<string>} Role config.
	 */
	public function get_role_config( string $role ): array {
		$settings    = mdra_get_settings();
		$restrictions = $settings['role_restrictions'] ?? [];

		if ( isset( $restrictions[ $role ] ) && is_array( $restrictions[ $role ] ) ) {
			return wp_parse_args( $restrictions[ $role ], [
				'restricted'            => false,
				'whitelisted_endpoints' => [],
			] );
		}

		return [
			'restricted'            => false,
			'whitelisted_endpoints' => [],
		];
	}

	/**
	 * Checks if a specific role has REST API restrictions.
	 *
	 * @since 1.0.0
	 *
	 * @param string $role The role slug.
	 * @return bool True if the role is restricted.
	 */
	public function is_role_restricted( string $role ): bool {
		$config = $this->get_role_config( $role );

		return ! empty( $config['restricted'] );
	}

	/**
	 * Returns the whitelisted endpoints for a specific role.
	 *
	 * @since 1.0.0
	 *
	 * @param string $role The role slug.
	 * @return array<string> List of whitelisted namespace/route strings.
	 */
	public function get_role_whitelist( string $role ): array {
		$config = $this->get_role_config( $role );

		return $config['whitelisted_endpoints'];
	}
}

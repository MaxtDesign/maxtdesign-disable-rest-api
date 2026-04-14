<?php
/**
 * Logger.
 *
 * Placeholder class for future request logging functionality.
 * Architected for Tier 2 pro features — not active in v1.0.
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
 * Logger class.
 *
 * Reserved for future implementation of REST API request logging,
 * rate limiting, and analytics features.
 *
 * @since 1.0.0
 */
final class Logger {

	/**
	 * Whether logging is enabled.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private bool $enabled = false;

	/**
	 * Checks whether logging is currently enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		/**
		 * Filters whether REST API request logging is enabled.
		 *
		 * @since 1.0.0
		 *
		 * @param bool $enabled Whether logging is enabled.
		 */
		return (bool) apply_filters( 'mdra_logging_enabled', $this->enabled );
	}
}

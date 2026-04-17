<?php
/**
 * Main plugin bootstrap.
 *
 * @package Agent_Ready
 */

namespace Agent_Ready;

/**
 * Plugin singleton and entry point for namespaced code.
 */
final class Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Get the plugin instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function init(): void {
		// Intentionally minimal — extend here as features are added.
	}
}

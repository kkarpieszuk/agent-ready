<?php
/**
 * Main plugin bootstrap.
 *
 * @package Kolibia_AR
 */

namespace Kolibia_AR;

use Kolibia_AR\Admin\Post_List;
use Kolibia_AR\Markdown\Single_Post_Markdown;

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
		( new Single_Post_Markdown() )->register();
		( new Post_List() )->register();
	}
}

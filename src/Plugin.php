<?php
/**
 * Main plugin bootstrap.
 *
 * @package Wp_Are
 */

namespace Wp_Are;

use Wp_Are\Admin\Post_List;
use Wp_Are\Markdown\Single_Post_Markdown;

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

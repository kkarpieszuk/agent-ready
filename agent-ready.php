<?php
/**
 * Plugin Name:     Agent Ready
 * Description:     Adds API discovery and Markdown responses so AI agents can use your WordPress content and services. Aligns your site with emerging agent-readiness patterns for WordPress.
 * Author:          Konrad Karpieszuk
 * Author URI:      https://kolibia.pl
 * Text Domain:     agent-ready
 * Domain Path:     /languages
 * Version:         {VERSION}
 * License:         GPLv2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package         Agent_Ready
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AGENT_READY_VERSION', '{VERSION}' );
define( 'AGENT_READY_PLUGIN_FILE', __FILE__ );
define( 'AGENT_READY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once AGENT_READY_PLUGIN_DIR . 'vendor/autoload.php';

add_action(
	'plugins_loaded',
	static function (): void {
		\Agent_Ready\Plugin::instance()->init();
	}
);

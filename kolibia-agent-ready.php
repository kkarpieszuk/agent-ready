<?php
/**
 * Plugin Name:     Kolibia Agent-Ready
 * Description:     Adds API discovery and Markdown responses so AI agents can use your WordPress content and services. Aligns your site with emerging agent-readiness patterns for WordPress.
 * Author:          Konrad Karpieszuk
 * Author URI:      https://kolibia.pl
 * Text Domain:     kolibia-agent-ready
 * Domain Path:     /languages
 * Version:         1.0.1
 * License:         GPLv2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package         Kolibia_AR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KOLIBIA_AR_VERSION', '1.0.1' );
define( 'KOLIBIA_AR_PLUGIN_FILE', __FILE__ );
define( 'KOLIBIA_AR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once KOLIBIA_AR_PLUGIN_DIR . 'vendor/autoload.php';

add_action(
	'plugins_loaded',
	static function (): void {
		\Kolibia_AR\Plugin::instance()->init();
	}
);

<?php
/**
 * Plugin Name:     Agent Ready Essentials
 * Description:     Adds API discovery and Markdown responses so AI agents can use your WordPress content and services. Aligns your site with emerging agent-readiness patterns for WordPress.
 * Author:          Konrad Karpieszuk
 * Author URI:      https://kolibia.pl
 * Text Domain:     agent-ready-essentials
 * Domain Path:     /languages
 * Version:         {VERSION}
 * License:         GPLv2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package         Wp_Are
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WP_ARE_VERSION', '{VERSION}' );
define( 'WP_ARE_PLUGIN_FILE', __FILE__ );
define( 'WP_ARE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once WP_ARE_PLUGIN_DIR . 'vendor/autoload.php';

add_action(
	'plugins_loaded',
	static function (): void {
		\Wp_Are\Plugin::instance()->init();
	}
);

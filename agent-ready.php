<?php
/**
 * Plugin Name:     Agent Ready
 * Description:     Agent Ready implements emerging “agent readiness” standards for WordPress. Make your site visible for LLMs and other AI agents!
 * Author:          Konrad Karpieszuk
 * Author URI:      https://kolibia.pl
 * Text Domain:     agent-ready
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Agent_Ready
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AGENT_READY_VERSION', '0.1.0' );
define( 'AGENT_READY_PLUGIN_FILE', __FILE__ );
define( 'AGENT_READY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

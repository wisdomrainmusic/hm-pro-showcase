<?php
/**
 * Plugin Name: HM Pro Showcase
 * Description: Showcase + navigable demo preview system for HM Pro theme packages.
 * Version: 0.1.0
 * Author: Wisdom Rain Music
 * License: GPLv2 or later
 * Text Domain: hm-pro-showcase
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HMPS_VERSION', '0.1.0' );
define( 'HMPS_PLUGIN_FILE', __FILE__ );
define( 'HMPS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HMPS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once HMPS_PLUGIN_DIR . 'inc/class-hmps-loader.php';

/**
 * Plugin bootstrap.
 */
final class HMPS_Plugin {
	private static $instance = null;

	/** @var HMPS_Loader */
	public $loader;

	public static function instance() : self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->loader = new HMPS_Loader();
		$this->loader->init();
	}
}

/**
 * Activation: set defaults if missing.
 */
function hmps_activate() : void {
	$defaults = array(
		'packages_base_dir' => '',
		'preview_base_slug' => 'demo',
	);

	$existing = get_option( 'hmps_settings', array() );
	if ( ! is_array( $existing ) ) {
		$existing = array();
	}

	$merged = array_merge( $defaults, $existing );

	// If base dir empty, set to uploads/hmps-packages.
	if ( empty( $merged['packages_base_dir'] ) ) {
		$uploads = wp_upload_dir();
		$base    = isset( $uploads['basedir'] ) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
		$merged['packages_base_dir'] = trailingslashit( $base ) . 'hmps-packages';
	}

	update_option( 'hmps_settings', $merged, false );
}
register_activation_hook( __FILE__, 'hmps_activate' );

// Bootstrap on plugins_loaded.
add_action(
	'plugins_loaded',
	static function() {
		HMPS_Plugin::instance();
	}
);

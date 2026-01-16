<?php
/**
 * Plugin Name: HM Pro Showcase
 * Description: Showcase + navigable demo preview system for HM Pro theme packages.
 * Version: 0.2.0
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

	// If base dir empty, set to uploads/hmpro-demo-packages (single canonical folder).
	if ( empty( $merged['packages_base_dir'] ) ) {
		$uploads = wp_upload_dir();
		$base    = isset( $uploads['basedir'] ) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
		$merged['packages_base_dir'] = trailingslashit( wp_normalize_path( $base ) ) . 'hmpro-demo-packages';
	}

	// Ensure directory exists.
	if ( ! is_dir( $merged['packages_base_dir'] ) ) {
		wp_mkdir_p( $merged['packages_base_dir'] );
	}

	update_option( 'hmps_settings', $merged, false );

	require_once HMPS_PLUGIN_DIR . 'inc/preview/class-hmps-preview-router.php';
	HMPS_Preview_Router::activate();
}
register_activation_hook( __FILE__, 'hmps_activate' );

/**
 * Deactivation: flush rewrite rules.
 */
function hmps_deactivate() : void {
	require_once HMPS_PLUGIN_DIR . 'inc/preview/class-hmps-preview-router.php';
	HMPS_Preview_Router::deactivate();
}

register_deactivation_hook( __FILE__, 'hmps_deactivate' );

// Bootstrap on plugins_loaded.
add_action(
	'plugins_loaded',
	static function() {
		HMPS_Plugin::instance();
	}
);

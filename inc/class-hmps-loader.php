<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HMPS_Loader {
	public function init() : void {
		require_once HMPS_PLUGIN_DIR . 'inc/packages/class-hmps-package-repository.php';
		require_once HMPS_PLUGIN_DIR . 'inc/admin/class-hmps-admin.php';
		require_once HMPS_PLUGIN_DIR . 'inc/player/class-hmps-player-api.php';

		add_action( 'rest_api_init', array( 'HMPS_Player_API', 'register_routes' ) );

		// Showcase-only mode: no preview router, no rewrite rules.

		if ( is_admin() ) {
			add_action( 'admin_menu', array( 'HMPS_Admin', 'register_menu' ) );
			add_action( 'admin_init', array( 'HMPS_Admin', 'register_settings' ) );
		} else {
			require_once HMPS_PLUGIN_DIR . 'inc/frontend/class-hmps-shortcodes.php';
			add_action( 'init', array( 'HMPS_Shortcodes', 'register' ) );
		}
	}
}

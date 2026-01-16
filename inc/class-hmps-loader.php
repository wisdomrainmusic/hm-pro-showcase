<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HMPS_Loader {
	public function init() : void {
		require_once HMPS_PLUGIN_DIR . 'inc/packages/class-hmps-package-repository.php';
		require_once HMPS_PLUGIN_DIR . 'inc/preview/class-hmps-preview-router.php';

		// Preview router must be available both admin+frontend.
		add_action( 'init', array( 'HMPS_Preview_Router', 'register' ) );
		add_filter( 'query_vars', array( 'HMPS_Preview_Router', 'register_query_vars' ) );
		add_filter( 'request', array( 'HMPS_Preview_Router', 'filter_request' ), 1 );

		if ( is_admin() ) {
			require_once HMPS_PLUGIN_DIR . 'inc/admin/class-hmps-admin.php';
			add_action( 'admin_menu', array( 'HMPS_Admin', 'register_menu' ) );
			add_action( 'admin_init', array( 'HMPS_Admin', 'register_settings' ) );
		} else {
			require_once HMPS_PLUGIN_DIR . 'inc/frontend/class-hmps-shortcodes.php';
			add_action( 'init', array( 'HMPS_Shortcodes', 'register' ) );
		}
	}
}

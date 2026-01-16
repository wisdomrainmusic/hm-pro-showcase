<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HMPS_Loader {
	public function init() : void {
		if ( is_admin() ) {
			require_once HMPS_PLUGIN_DIR . 'inc/admin/class-hmps-admin.php';
			add_action( 'admin_menu', array( 'HMPS_Admin', 'register_menu' ) );
			add_action( 'admin_init', array( 'HMPS_Admin', 'register_settings' ) );
		}
	}
}
